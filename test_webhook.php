<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$payload = [
    'update_id' => 123,
    'message' => [
        'message_id' => 1,
        'from' => ['id' => 1706077211, 'is_bot' => false, 'first_name' => 'Arif', 'username' => 'arifaldzgn'],
        'chat' => ['id' => 1706077211, 'first_name' => 'Arif', 'username' => 'arifaldzgn', 'type' => 'private'],
        'date' => 1710000000,
        'text' => 'buka dashboard'
    ]
];

$request = \Illuminate\Http\Request::create('/api/telegram/webhook', 'POST', $payload);
$controller = $app->make(\App\Http\Controllers\TelegramWebhookController::class);
$response = $controller->__invoke($request);

echo "Controller Response: " . $response->getContent() . "\n";
