@extends('layouts.webview')
@section('title', 'Setup — Profil')
@section('content')

<div class="progress-wrap animate-in">
    <div class="progress-label">Langkah 1 dari 5</div>
    <div class="progress-bar-track"><div class="progress-bar-fill" style="width:20%"></div></div>
</div>

<div class="page-header animate-in animate-in-delay-1">
    <div class="butler-mark">Butler Setup</div>
    <h1>Hai! Siapa nama<br>kamu?</h1>
    <p class="subtitle">Butler akan menggunakannya untuk menyapa kamu.</p>
</div>

<form method="POST" action="{{ route('onboarding.profile.save', $telegram_id) }}" class="form-body animate-in animate-in-delay-2">
    @csrf

    <div class="field">
        <label class="field-label" for="name">Nama / Panggilan</label>
        <input type="text" id="name" name="name"
               value="{{ old('name', $user->name !== 'User' ? $user->name : '') }}"
               placeholder="contoh: Andi" autofocus autocomplete="given-name" required>
        @error('name')<div class="field-error">{{ $message }}</div>@enderror
    </div>

    <div class="field">
        <label class="field-label" for="currency">Mata uang</label>
        <select id="currency" name="currency">
            <option value="IDR" {{ old('currency', $user->currency ?? 'IDR') === 'IDR' ? 'selected' : '' }}>IDR — Rupiah</option>
            <option value="USD" {{ old('currency') === 'USD' ? 'selected' : '' }}>USD — US Dollar</option>
            <option value="SGD" {{ old('currency') === 'SGD' ? 'selected' : '' }}>SGD — Singapore Dollar</option>
            <option value="MYR" {{ old('currency') === 'MYR' ? 'selected' : '' }}>MYR — Ringgit</option>
        </select>
    </div>

    <div class="field">
        <label class="field-label" for="timezone">Zona waktu</label>
        <select id="timezone" name="timezone">
            <option value="Asia/Jakarta"  {{ old('timezone', $user->timezone) === 'Asia/Jakarta'  ? 'selected' : '' }}>WIB — Jakarta (UTC+7)</option>
            <option value="Asia/Makassar" {{ old('timezone', $user->timezone) === 'Asia/Makassar' ? 'selected' : '' }}>WITA — Makassar (UTC+8)</option>
            <option value="Asia/Jayapura" {{ old('timezone', $user->timezone) === 'Asia/Jayapura' ? 'selected' : '' }}>WIT — Jayapura (UTC+9)</option>
        </select>
    </div>

    <div class="form-footer">
        <button type="submit" class="btn btn-primary">Lanjut →</button>
    </div>
</form>

@endsection
