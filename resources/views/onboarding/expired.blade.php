@extends('layouts.webview')
@section('title', 'Link Tidak Valid')
@section('content')

<div style="display:flex;flex-direction:column;justify-content:center;align-items:center;min-height:100dvh;text-align:center;padding:40px 20px">
    <div style="font-size:48px;margin-bottom:24px">🔗</div>
    <h1 style="font-size:22px;margin-bottom:12px">Link sudah tidak berlaku</h1>
    <p style="color:var(--text-muted);font-size:14px;line-height:1.6;margin-bottom:32px">
        Link setup kamu sudah kedaluwarsa atau sudah pernah digunakan.<br>
        Ketik <strong>/start</strong> di Telegram untuk mendapatkan link baru.
    </p>
    <a href="tg://resolve?domain={{ config('butler.telegram_bot_username') }}"
       class="btn btn-primary" style="max-width:280px">
        Buka Telegram
    </a>
</div>

@endsection
