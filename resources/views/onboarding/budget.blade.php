@extends('layouts.webview')
@section('title', 'Setup — Budget')
@section('content')

<div class="progress-wrap animate-in">
    <div class="progress-label">Langkah 3 dari 5</div>
    <div class="progress-bar-track"><div class="progress-bar-fill" style="width:60%"></div></div>
</div>

<div class="page-header animate-in animate-in-delay-1">
    <div class="butler-mark">Butler Setup</div>
    <h1>Berapa target<br>belanja bulanan?</h1>
    <p class="subtitle">Opsional. Butler pakai ini buat alert kalau kamu mendekati limit.</p>
</div>

<form method="POST" action="{{ route('onboarding.budget.save', $telegram_id) }}"
      class="form-body animate-in animate-in-delay-2"
      x-data="{ hasMonthly: false, hasDaily: false }">
    @csrf

    <div class="field">
        <label class="field-label" for="monthly_budget_idr">Budget bulanan (IDR)</label>
        <input type="number" id="monthly_budget_idr" name="monthly_budget_idr"
               value="{{ old('monthly_budget_idr') }}"
               placeholder="contoh: 3000000" min="0" step="50000"
               @input="hasMonthly = $event.target.value > 0">
    </div>

    <div class="field">
        <label class="field-label" for="daily_budget_idr">Budget harian (IDR) — opsional</label>
        <input type="number" id="daily_budget_idr" name="daily_budget_idr"
               value="{{ old('daily_budget_idr') }}"
               placeholder="contoh: 150000" min="0" step="10000"
               @input="hasDaily = $event.target.value > 0">
    </div>

    <div class="form-footer">
        <button type="submit" class="btn btn-primary">Lanjut →</button>
        <button type="submit" name="skip" value="1" class="btn btn-ghost">Lewati</button>
    </div>
</form>

@endsection
