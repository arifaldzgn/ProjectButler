@extends('layouts.dashboard')
@section('title', 'Settings — Butler')

@section('content')

<div class="page-header animate-in">
    <h2>Settings ⚙️</h2>
    <p>Ubah profil, budget, dan preferensi Butler-mu</p>
</div>

<form action="{{ route('dashboard.settings.save') }}" method="POST">
    @csrf

    {{-- ── Profile ─────────────────────────────────────────────── --}}
    <div class="card animate-in" style="animation-delay:.04s">
        <div class="card-title">Profil</div>

        <div class="field">
            <label class="field-label" for="name">Nama</label>
            <input class="field-input" type="text" id="name" name="name"
                   value="{{ old('name', $user->name) }}" required>
            @error('name')<div style="font-size:12px;color:var(--red);margin-top:4px">{{ $message }}</div>@enderror
        </div>

        <div class="field">
            <label class="field-label" for="timezone">Timezone</label>
            <select class="field-input" id="timezone" name="timezone">
                @php
                    $zones = [
                        'Asia/Jakarta'    => 'WIB — Jakarta, Bandung, Surabaya',
                        'Asia/Makassar'   => 'WITA — Bali, Makassar',
                        'Asia/Jayapura'   => 'WIT — Papua',
                        'Asia/Singapore'  => 'SGT — Singapore',
                        'Asia/Kuala_Lumpur' => 'MYT — Kuala Lumpur',
                        'UTC'             => 'UTC',
                    ];
                @endphp
                @foreach($zones as $tz => $label)
                    <option value="{{ $tz }}" {{ $user->timezone === $tz ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="tracking_mode">Mode Tracking</label>
            <select class="field-input" id="tracking_mode" name="tracking_mode">
                <option value="finance"  {{ $user->tracking_mode === 'finance'  ? 'selected' : '' }}>💸 Finance Only</option>
                <option value="calorie"  {{ $user->tracking_mode === 'calorie'  ? 'selected' : '' }}>🔥 Calorie Only</option>
                <option value="both"     {{ $user->tracking_mode === 'both'     ? 'selected' : '' }}>💸🔥 Finance + Calorie</option>
            </select>
        </div>

        <div class="field" style="margin-bottom:0">
            <label class="field-label" for="currency">Mata Uang</label>
            <select class="field-input" id="currency" name="currency">
                <option value="IDR" {{ ($user->currency ?? 'IDR') === 'IDR' ? 'selected' : '' }}>IDR — Rupiah</option>
                <option value="USD" {{ $user->currency === 'USD' ? 'selected' : '' }}>USD — Dollar</option>
                <option value="SGD" {{ $user->currency === 'SGD' ? 'selected' : '' }}>SGD — Singapore Dollar</option>
                <option value="MYR" {{ $user->currency === 'MYR' ? 'selected' : '' }}>MYR — Ringgit</option>
            </select>
        </div>
    </div>

    {{-- ── Budget ───────────────────────────────────────────────── --}}
    <div class="card animate-in" style="animation-delay:.07s">
        <div class="card-title">Budget & Income</div>

        <div class="grid-2" style="margin-bottom:0">
            <div class="field">
                <label class="field-label" for="daily_budget_idr">Budget Harian (Rp)</label>
                <input class="field-input" type="number" id="daily_budget_idr" name="daily_budget_idr"
                       value="{{ old('daily_budget_idr', $user->daily_budget_idr) }}"
                       placeholder="Contoh: 100000" min="0">
                @error('daily_budget_idr')<div style="font-size:12px;color:var(--red);margin-top:4px">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label class="field-label" for="monthly_budget_idr">Budget Bulanan (Rp)</label>
                <input class="field-input" type="number" id="monthly_budget_idr" name="monthly_budget_idr"
                       value="{{ old('monthly_budget_idr', $user->monthly_budget_idr) }}"
                       placeholder="Contoh: 3000000" min="0">
            </div>
        </div>

        <div class="field" style="margin-bottom:0">
            <label class="field-label" for="monthly_income_idr">Income Bulanan (Rp)</label>
            <input class="field-input" type="number" id="monthly_income_idr" name="monthly_income_idr"
                   value="{{ old('monthly_income_idr', $user->monthly_income_idr) }}"
                   placeholder="Contoh: 8000000" min="0">
            <div style="font-size:11px;color:var(--text-dim);margin-top:4px">Dipakai Butler untuk estimasi free balance di ringkasan harian.</div>
        </div>
    </div>

    {{-- ── Health / Calories ────────────────────────────────────── --}}
    <div class="card animate-in" style="animation-delay:.10s">
        <div class="card-title">Target Kalori</div>

        <div class="field">
            <label class="field-label" for="daily_calorie_goal">Target Kalori Harian (kcal)</label>
            <input class="field-input" type="number" id="daily_calorie_goal" name="daily_calorie_goal"
                   value="{{ old('daily_calorie_goal', $user->daily_calorie_goal) }}"
                   placeholder="Contoh: 2000" min="0">
            <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Biasanya 1800–2500 kcal/hari untuk orang dewasa.</div>
        </div>

        <div class="field" style="margin-bottom:0">
            <label class="field-label">Tujuan Kalori</label>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
                @php $goalType = old('calorie_goal_type', $user->calorie_goal_type ?? 'maintenance'); @endphp

                @foreach([
                    ['value' => 'bulking',     'emoji' => '💪', 'label' => 'Bulking',      'desc' => 'Naikkan massa otot'],
                    ['value' => 'maintenance', 'emoji' => '⚖️', 'label' => 'Maintenance',   'desc' => 'Jaga berat badan'],
                    ['value' => 'cutting',     'emoji' => '🎯', 'label' => 'Cutting',       'desc' => 'Turunkan lemak'],
                ] as $opt)
                <label style="
                    display:flex;flex-direction:column;align-items:center;gap:6px;
                    padding:14px 10px;border-radius:var(--radius-sm);border:2px solid;
                    cursor:pointer;text-align:center;transition:all .2s;
                    border-color:{{ $goalType === $opt['value'] ? 'var(--accent)' : 'var(--border)' }};
                    background:{{ $goalType === $opt['value'] ? 'rgba(139,92,246,.08)' : 'transparent' }};
                    font-size:12px;
                " onclick="selectGoalType('{{ $opt['value'] }}', this)">
                    <input type="radio" name="calorie_goal_type" value="{{ $opt['value'] }}"
                           {{ $goalType === $opt['value'] ? 'checked' : '' }}
                           style="display:none">
                    <span style="font-size:22px">{{ $opt['emoji'] }}</span>
                    <span style="font-weight:600;color:var(--text-primary)">{{ $opt['label'] }}</span>
                    <span style="color:var(--text-muted);font-size:11px">{{ $opt['desc'] }}</span>
                </label>
                @endforeach
            </div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:8px">
                Butler akan menyesuaikan saran dan reminder kalori berdasarkan tujuanmu.
            </div>
        </div>
    </div>

    {{-- ── Notifications ────────────────────────────────────────── --}}
    <div class="card animate-in" style="animation-delay:.13s">
        <div class="card-title">Notifikasi</div>

        <label class="toggle-row {{ $user->daily_summary_enabled ? 'is-on' : '' }}"
               x-data="{ on: {{ $user->daily_summary_enabled ? 'true' : 'false' }} }"
               :class="{ 'is-on': on }"
               @click="on = !on">
            <input type="checkbox" name="daily_summary_enabled" value="1"
                   x-bind:checked="on" style="display:none">
            <div class="toggle-visual"></div>
            <div>
                <div class="toggle-label">Ringkasan Harian</div>
                <div class="toggle-desc">Terima ringkasan otomatis via Telegram setiap malam</div>
            </div>
        </label>

        <div class="field" style="margin-bottom:0">
            <label class="field-label" for="summary_time">Waktu Pengiriman Ringkasan</label>
            <input class="field-input" type="time" id="summary_time" name="summary_time"
                   value="{{ old('summary_time', $user->summary_time ?? '21:00') }}">
            <div style="font-size:11px;color:var(--text-dim);margin-top:4px">
                Sesuai timezone kamu ({{ $user->timezone ?? 'Asia/Jakarta' }})
            </div>
        </div>
    </div>

    {{-- ── Re-do Setup ──────────────────────────────────────────── --}}
    <div class="card animate-in" style="animation-delay:.14s">
        <div class="card-title">Ulangi Langkah Setup</div>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">
            Perlu ubah akun, budget awal, atau notifikasi? Kamu bisa masuk ulang ke langkah onboarding tertentu.
        </p>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @php
                $tid = $user->telegram_chat_id;
                $steps = [
                    ['route' => 'onboarding.profile',       'label' => '👤 Profil'],
                    ['route' => 'onboarding.accounts',      'label' => '💳 Akun'],
                    ['route' => 'onboarding.budget',        'label' => '📊 Budget'],
                    ['route' => 'onboarding.health',        'label' => '🏃 Kesehatan'],
                    ['route' => 'onboarding.notifications', 'label' => '🔔 Notifikasi'],
                ];
            @endphp
            @foreach($steps as $step)
            <a href="{{ route($step['route'], ['telegram_id' => $tid]) }}"
               style="padding:8px 14px;border-radius:var(--radius-sm);border:1px solid var(--border);
                      background:var(--bg-card);font-size:12px;color:var(--text-secondary);
                      text-decoration:none;box-shadow:var(--card-shadow);font-weight:500;
                      transition:border-color .2s,color .2s"
               onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'"
               onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-secondary)'">
                {{ $step['label'] }}
            </a>
            @endforeach
        </div>
    </div>

    {{-- ── Telegram info (read-only) ───────────────────────────── --}}
    <div class="card animate-in" style="animation-delay:.15s;background:var(--bg)">
        <div class="card-title">Akun Telegram</div>
        <div style="display:flex;flex-direction:column;gap:10px">
            <div style="display:flex;justify-content:space-between;font-size:13px">
                <span style="color:var(--text-muted)">Chat ID</span>
                <span style="font-weight:600;font-family:monospace">{{ $user->telegram_chat_id }}</span>
            </div>
            @if($user->telegram_username)
            <div style="display:flex;justify-content:space-between;font-size:13px">
                <span style="color:var(--text-muted)">Username</span>
                <span style="font-weight:600">@{{ $user->telegram_username }}</span>
            </div>
            @endif
            <div style="display:flex;justify-content:space-between;font-size:13px">
                <span style="color:var(--text-muted)">Member sejak</span>
                <span style="font-weight:600">{{ $user->created_at->format('d M Y') }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:13px">
                <span style="color:var(--text-muted)">Onboarding</span>
                <span style="font-weight:600;color:var(--green)">✓ Selesai</span>
            </div>
        </div>
    </div>

    {{-- ── Save ─────────────────────────────────────────────────── --}}
    <div class="animate-in" style="animation-delay:.17s;padding-bottom:8px">
        <button type="submit" class="btn-save">
            ✓ Simpan Pengaturan
        </button>
    </div>

</form>

<script>
function selectGoalType(value, clickedLabel) {
    // Uncheck all labels
    document.querySelectorAll('[name="calorie_goal_type"]').forEach(radio => {
        const lbl = radio.closest('label');
        lbl.style.borderColor = 'var(--border)';
        lbl.style.background  = 'transparent';
        radio.checked = false;
    });
    // Activate clicked
    const radio = clickedLabel.querySelector('input[type="radio"]');
    radio.checked = true;
    clickedLabel.style.borderColor = 'var(--accent)';
    clickedLabel.style.background  = 'rgba(139,92,246,0.08)';
}
</script>

@endsection
