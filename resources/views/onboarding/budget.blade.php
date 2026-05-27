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
      x-data="budgetManager()">
    @csrf

    <div class="field">
        <label class="field-label" for="daily_budget_idr">Budget harian (IDR) — opsional</label>
        <input type="text" id="daily_budget_idr_display"
               x-model="formatted_daily" @input="formatDaily($event.target.value)"
               placeholder="contoh: 150.000">
        <input type="hidden" name="daily_budget_idr" :value="daily">
    </div>

    <div class="field">
        <label class="field-label" for="monthly_budget_idr">Budget bulanan (IDR)</label>
        <input type="text" id="monthly_budget_idr_display"
               x-model="formatted_monthly" @input="formatMonthly($event.target.value)"
               placeholder="contoh: 3.000.000">
        <input type="hidden" name="monthly_budget_idr" :value="monthly">
        <div x-show="autoFilled" style="font-size:12px;color:var(--text-dim);margin-top:4px;" x-cloak>
            💡 Otomatis dihitung dari budget harian × <span x-text="daysLeft"></span> hari sisa di bulan ini.
        </div>
    </div>

    <div class="form-footer">
        <button type="submit" class="btn btn-primary">Lanjut →</button>
        <button type="submit" name="skip" value="1" class="btn btn-ghost">Lewati</button>
    </div>
</form>

<script>
function budgetManager() {
    return {
        daily: '{{ old('daily_budget_idr') }}',
        monthly: '{{ old('monthly_budget_idr') }}',
        formatted_daily: '',
        formatted_monthly: '',
        autoFilled: false,
        init() {
            if (this.daily) this.formatted_daily = parseInt(this.daily).toLocaleString('en-US');
            if (this.monthly) this.formatted_monthly = parseInt(this.monthly).toLocaleString('en-US');
        },
        get daysLeft() {
            const now = new Date();
            const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            return lastDay.getDate() - now.getDate() + 1;
        },
        formatDaily(val) {
            let num = val.toString().replace(/\D/g, '');
            this.daily = num;
            this.formatted_daily = num ? parseInt(num).toLocaleString('en-US') : '';
            this.updateMonthly();
        },
        formatMonthly(val) {
            let num = val.toString().replace(/\D/g, '');
            this.monthly = num;
            this.formatted_monthly = num ? parseInt(num).toLocaleString('en-US') : '';
            this.autoFilled = false;
        },
        updateMonthly() {
            if (this.daily > 0) {
                this.monthly = this.daily * this.daysLeft;
                this.formatted_monthly = parseInt(this.monthly).toLocaleString('en-US');
                this.autoFilled = true;
            } else {
                this.monthly = '';
                this.formatted_monthly = '';
                this.autoFilled = false;
            }
        }
    }
}
</script>

@endsection
