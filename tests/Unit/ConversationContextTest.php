<?php

namespace Tests\Unit;

use App\Services\AIService;
use App\Services\MessageRouter;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Conversational short-term memory (v2.6.3).
 *
 * Pure prompt-building + normalization contract — no DB, no network. Guards that:
 *   • the parser prompt gains a RECENT CONVERSATION block only when turns exist,
 *     and that block is framed as context-only (current message stays primary);
 *   • turn text is de-tagged and truncated before it reaches the prompt.
 */
class ConversationContextTest extends TestCase
{
    private function buildParserPrompt(array $userContext): string
    {
        $ai = app(AIService::class);
        $m  = new ReflectionMethod($ai, 'buildParserPrompt');
        $m->setAccessible(true);
        return $m->invoke($ai, [], $userContext);
    }

    private function sanitize(string $text): string
    {
        $router = app(MessageRouter::class);
        $m = new ReflectionMethod($router, 'sanitizeTurnText');
        $m->setAccessible(true);
        return $m->invoke($router, $text);
    }

    public function test_prompt_omits_history_block_when_no_turns(): void
    {
        $prompt = $this->buildParserPrompt(['mode' => 'both']);
        $this->assertStringNotContainsString('RECENT CONVERSATION', $prompt);
    }

    public function test_prompt_includes_history_block_when_turns_present(): void
    {
        $prompt = $this->buildParserPrompt([
            'mode'         => 'both',
            'recent_turns' => [
                ['role' => 'user',      'text' => 'makan gojek 25k'],
                ['role' => 'assistant', 'text' => '🤔 Maksudmu Catat Pengeluaran?'],
            ],
        ]);

        $this->assertStringContainsString('RECENT CONVERSATION', $prompt);
        // Transcript renders with U:/B: role prefixes.
        $this->assertStringContainsString('U: makan gojek 25k', $prompt);
        $this->assertStringContainsString('B: 🤔 Maksudmu Catat Pengeluaran?', $prompt);
        // Framing keeps the current message primary.
        $this->assertStringContainsString('CURRENT message', $prompt);
    }

    public function test_sanitize_strips_shortcut_tag(): void
    {
        $this->assertSame('beli kopi 18k', $this->sanitize('__shortcut:42|beli kopi 18k'));
        $this->assertSame('beli kopi 18k', $this->sanitize('__shortcut:42|intent:expense|beli kopi 18k'));
    }

    public function test_sanitize_drops_photo_marker(): void
    {
        $this->assertSame('', $this->sanitize('__photo:AgACfileid|caption:struk'));
    }

    public function test_sanitize_strips_debug_footer_and_truncates(): void
    {
        $withFooter = $this->sanitize("Dicatat! Rp 25.000\n\n[🤖 Model: gemini-flash]");
        $this->assertStringContainsString('Dicatat! Rp 25.000', $withFooter);
        $this->assertStringNotContainsString('Model:', $withFooter);

        $long = str_repeat('a', 300);
        $this->assertSame(150, mb_strlen($this->sanitize($long)));
    }
}
