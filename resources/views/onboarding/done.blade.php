@extends('layouts.webview')
@section('title', 'Setup Selesai!')
@section('content')

<div class="page" style="justify-content:center; align-items:center; text-align:center; padding:40px 20px">

    <div class="animate-in" style="font-size:56px; margin-bottom:24px">✅</div>

    <h1 class="animate-in animate-in-delay-1" style="font-size:28px; margin-bottom:12px">Siap digunakan!</h1>

    <p class="animate-in animate-in-delay-2" style="color:var(--text-muted); font-size:15px; line-height:1.6; margin-bottom:32px">
        Butler sudah tahu tentang kamu.<br>
        Mulai catat di Telegram sekarang.
    </p>

    <div class="animate-in animate-in-delay-3" style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius);padding:20px;text-align:left;width:100%;margin-bottom:32px">
        <div style="font-size:11px;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-dim);margin-bottom:14px">Contoh perintah</div>
        @foreach(['makan ayam geprek 35k', 'grab 18rb', 'gajian 5 juta', 'rangkuman hari ini', 'buka dashboard'] as $example)
        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);color:var(--text-muted);font-size:14px">
            <span style="color:var(--text-dim)">•</span>
            <span>"{{ $example }}"</span>
        </div>
        @endforeach
    </div>

    <a href="tg://resolve?domain={{ $bot_username }}"
       class="btn btn-primary animate-in animate-in-delay-3"
       style="display:block;max-width:320px">
        Buka Telegram →
    </a>
</div>

@endsection
