@extends('layouts.dashboard')
@section('title', 'Sesi Kedaluwarsa — Butler')
@section('content')

<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:60vh;text-align:center;padding:40px 0">
    <div style="font-size:48px;margin-bottom:24px">🔒</div>
    <h2 style="margin-bottom:12px">Sesi Kedaluwarsa</h2>
    <p style="color:var(--text-muted);font-size:14px;line-height:1.6;max-width:280px">
        Sesi dashboard kamu sudah habis. Ketik <strong>"buka dashboard"</strong> di Telegram untuk mendapatkan link baru.
    </p>
</div>

@endsection
