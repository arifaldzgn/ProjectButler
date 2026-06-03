<?php

namespace App\Http\Controllers;

use App\Models\PairingCode;
use Illuminate\Http\Request;

class ShortcutInstallController extends Controller
{
    /**
     * Public install page — opened on the user's iPhone after tapping the
     * Telegram "Setup" button. Shows the pairing code and step-by-step guide.
     * No authentication required: the code itself is the credential.
     */
    public function show(Request $request, string $code): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        $pairingCode = PairingCode::where('code', strtoupper($code))->first();

        // Invalid code — show the page in a degraded state rather than 404,
        // so the user can still navigate to Telegram for help.
        if (!$pairingCode) {
            return view('shortcut.install', [
                'code'      => strtoupper($code),
                'expiresAt' => null,
                'invalid'   => true,
            ]);
        }

        return view('shortcut.install', [
            'code'      => $pairingCode->code,
            'expiresAt' => $pairingCode->expires_at,
            'invalid'   => false,
            'claimed'   => $pairingCode->isClaimed(),
        ]);
    }
}
