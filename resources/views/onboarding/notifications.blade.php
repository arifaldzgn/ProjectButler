@extends('layouts.webview')
@section('title', 'Setup — Notifikasi')
@section('content')

<div class="progress-wrap animate-in">
    <div class="progress-label">Langkah 5 dari 5</div>
    <div class="progress-bar-track"><div class="progress-bar-fill" style="width:100%"></div></div>
</div>

<div class="page-header animate-in animate-in-delay-1">
    <div class="butler-mark">Butler Setup</div>
    <h1>Ringkasan<br>harian?</h1>
    <p class="subtitle">Butler kirim ringkasan pengeluaran dan kalori harian ke Telegram kamu.</p>
</div>

<form method="POST" action="{{ route('onboarding.notifications.save', $telegram_id) }}"
      class="form-body animate-in animate-in-delay-2"
      x-data="{ enabled: true }">
    @csrf

    <label class="toggle-row" :class="{ 'is-on': enabled }" @click="enabled = !enabled">
        <input type="checkbox" name="daily_summary" value="1" x-model="enabled" checked>
        <div class="toggle-visual"></div>
        <div>
            <div class="toggle-label">Aktifkan ringkasan harian</div>
            <div class="toggle-desc">Dikirim via Telegram setiap hari</div>
        </div>
    </label>

    <div class="subform" x-show="enabled" x-transition x-cloak>
        <div class="field" style="margin-top:16px">
            <label class="field-label" for="summary_time">Jam berapa?</label>
            <input type="time" id="summary_time" name="summary_time" value="21:00">
        </div>
    </div>

    <div class="form-footer">
        <button type="submit" class="btn btn-primary">Selesai →</button>
    </div>
</form>

@endsection
