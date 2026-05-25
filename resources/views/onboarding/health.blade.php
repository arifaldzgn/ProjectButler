@extends('layouts.webview')
@section('title', 'Setup — Kesehatan')
@section('content')

<div class="progress-wrap animate-in">
    <div class="progress-label">Langkah 4 dari 5</div>
    <div class="progress-bar-track"><div class="progress-bar-fill" style="width:80%"></div></div>
</div>

<div class="page-header animate-in animate-in-delay-1">
    <div class="butler-mark">Butler Setup</div>
    <h1>Mau track<br>kalori juga?</h1>
    <p class="subtitle">Butler bisa estimasi kalori dari makanan yang kamu catat.</p>
</div>

<form method="POST" action="{{ route('onboarding.health.save', $telegram_id) }}"
      class="form-body animate-in animate-in-delay-2"
      x-data="{ track: false }">
    @csrf

    <label class="toggle-row" :class="{ 'is-on': track }" @click="track = !track">
        <input type="checkbox" name="calorie_tracking" value="1" x-model="track">
        <div class="toggle-visual"></div>
        <div>
            <div class="toggle-label">Aktifkan calorie tracking</div>
            <div class="toggle-desc">Estimasi dari nama makanan yang kamu ketik</div>
        </div>
    </label>

    <div class="subform" x-show="track" x-transition x-cloak>
        <div class="field" style="margin-top:16px">
            <label class="field-label" for="calorie_goal">Target kalori harian (kcal)</label>
            <input type="number" id="calorie_goal" name="calorie_goal"
                   value="{{ old('calorie_goal') }}"
                   placeholder="contoh: 2200" min="500" max="5000" step="50">
        </div>

        <div class="field">
            <label class="field-label">Tujuan kesehatan</label>
            <div class="chips" style="margin-top:4px">
                @foreach(['maintain' => 'Maintain berat', 'lose' => 'Turunkan berat', 'gain' => 'Naikkan berat', 'protein' => 'Tambah protein'] as $val => $label)
                <button type="button" class="chip"
                        x-data="{ active: false }"
                        :class="{ active }"
                        @click="active = !active; document.getElementById('health_goal').value = active ? '{{ $val }}' : ''"
                        >{{ $label }}</button>
                @endforeach
                <input type="hidden" id="health_goal" name="health_goal" value="{{ old('health_goal') }}">
            </div>
        </div>
    </div>

    <div class="form-footer">
        <button type="submit" class="btn btn-primary">Lanjut →</button>
        <button type="submit" name="skip" value="1" class="btn btn-ghost">Lewati</button>
    </div>
</form>

@endsection
