@extends('layouts.dashboard')
@section('title', 'Nutrition')

@section('content')
@php
    $goalType      = $user->calorie_goal_type ?? 'maintenance';
    $goalMeta      = [
        'bulking'     => ['emoji' => '💪', 'label' => 'Bulking',     'color' => 'var(--blue)'],
        'maintenance' => ['emoji' => '⚖️',  'label' => 'Maintenance', 'color' => 'var(--green)'],
        'cutting'     => ['emoji' => '🎯', 'label' => 'Cutting',     'color' => 'var(--orange)'],
    ][$goalType] ?? ['emoji' => '⚖️', 'label' => 'Maintenance', 'color' => 'var(--green)'];

    $calPct    = $calorieGoal > 0 ? min(round(($todayMacros['calories'] / $calorieGoal) * 100), 100) : 0;
    $pctUsed   = $calPct;
    $pctLeft   = max(100 - $pctUsed, 0);
    $remaining = max($calorieGoal - $todayMacros['calories'], 0);
    $overBy    = max($todayMacros['calories'] - $calorieGoal, 0);
@endphp

{{-- ── Header --}}
<div class="page-header animate-in" style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div>
        <h2>Nutrition 🍽️</h2>
        <p>Pantau kalori harian dan progress target-mu</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <div style="
            padding:6px 14px;border-radius:var(--radius-pill);
            border:1px solid var(--border);background:var(--bg-card);
            font-size:12px;font-weight:600;color:{{ $goalMeta['color'] }};
            display:flex;align-items:center;gap:5px;box-shadow:var(--card-shadow)
        ">
            {{ $goalMeta['emoji'] }} {{ $goalMeta['label'] }}
        </div>
        <a href="{{ route('dashboard.settings') }}#kalori"
           style="padding:6px 12px;border-radius:var(--radius-sm);border:1px solid var(--border);
                  background:var(--bg-card);font-size:12px;color:var(--text-muted);text-decoration:none;
                  box-shadow:var(--card-shadow)">⚙️ Ubah Goal</a>
    </div>
</div>

{{-- ── Stat Cards --}}
<div class="grid-4 animate-in" style="animation-delay:.04s">

    <div class="card stat-card orange">
        <div class="stat-icon">🔥</div>
        <div class="card-title">Kalori Hari Ini</div>
        <div class="card-value">{{ number_format($todayMacros['calories']) }}</div>
        <div class="progress-bar">
            <div class="progress-fill {{ $todayMacros['calories'] > $calorieGoal ? 'red' : 'orange' }}" style="width: {{ $calPct }}%"></div>
        </div>
        <div class="card-subtitle">{{ $calPct }}% dari {{ number_format($calorieGoal) }} kcal</div>
    </div>

    <div class="card stat-card accent">
        <div class="stat-icon">🍽️</div>
        <div class="card-title">Makanan Hari Ini</div>
        <div class="card-value">{{ $todayMeals->count() }}</div>
        <div class="card-subtitle">
            {{ $todayMeals->where('is_calorie_estimated', true)->count() }} estimasi AI
            · {{ $todayMeals->where('is_calorie_estimated', false)->count() }} manual
        </div>
    </div>

    <div class="card stat-card blue">
        <div class="stat-icon">📅</div>
        <div class="card-title">Rata-rata 7 Hari</div>
        <div class="card-value">{{ number_format($avgCalories) }}</div>
        <div class="card-subtitle">kcal per hari</div>
    </div>

    <div class="card stat-card green">
        <div class="stat-icon">🎯</div>
        <div class="card-title">{{ $overBy > 0 ? 'Melebihi Target' : 'Sisa Hari Ini' }}</div>
        <div class="card-value" style="color: {{ $overBy > 0 ? 'var(--red)' : 'var(--green)' }}">
            {{ $overBy > 0 ? number_format($overBy) : number_format($remaining) }}
        </div>
        <div class="card-subtitle">{{ $overBy > 0 ? 'kcal over target' : 'kcal tersisa' }}</div>
    </div>

</div>

{{-- ── Charts --}}
<div class="grid-2 animate-in" style="animation-delay:.07s">
    <div class="card">
        <div class="card-title">Kalori Harian (30 Hari Terakhir)</div>
        <div class="chart-container"><canvas id="dailyCaloriesChart"></canvas></div>
    </div>

    <div class="card">
        <div class="card-title">Progress Kalori Hari Ini</div>
        <div class="chart-container" style="height:200px"><canvas id="goalChart"></canvas></div>
        <div style="display:flex;justify-content:space-around;margin-top:16px">
            <div style="text-align:center">
                <div style="font-size:20px;font-weight:700;color:var(--orange)">{{ number_format($todayMacros['calories']) }}</div>
                <div style="font-size:11px;color:var(--text-muted)">Dikonsumsi</div>
            </div>
            <div style="text-align:center">
                <div style="font-size:20px;font-weight:700;color:var(--green)">{{ number_format($calorieGoal) }}</div>
                <div style="font-size:11px;color:var(--text-muted)">Target</div>
            </div>
            <div style="text-align:center">
                <div style="font-size:20px;font-weight:700;color:{{ $pctLeft === 0 ? 'var(--red)' : 'var(--accent)' }}">{{ $pctUsed }}%</div>
                <div style="font-size:11px;color:var(--text-muted)">Terpakai</div>
            </div>
        </div>
    </div>
</div>

{{-- Macro Breakdown (if any data) --}}
@php $hasMacros = ($todayMacros['protein'] + $todayMacros['carbs'] + $todayMacros['fat']) > 0; @endphp
@if($hasMacros)
<div class="card animate-in" style="animation-delay:.08s">
    <div class="card-title">Makro Hari Ini (Estimasi)</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:center">
        <div class="chart-container" style="height:180px">
            <canvas id="macroChart"></canvas>
        </div>
        <div>
            <div style="display:flex;flex-direction:column;gap:10px">
                <div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                        <span style="color:#3b82f6;font-weight:600">Protein</span>
                        <span style="font-weight:700;color:var(--text-primary)">{{ $todayMacros['protein'] }}g</span>
                    </div>
                    <div class="progress-bar"><div class="progress-fill blue" style="width:{{ min(100, $todayMacros['protein'] > 0 ? round(($todayMacros['protein'] / max(50, $todayMacros['protein'] + $todayMacros['carbs'] + $todayMacros['fat'])) * 100) : 0) }}%"></div></div>
                </div>
                <div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                        <span style="color:#f97316;font-weight:600">Karbohidrat</span>
                        <span style="font-weight:700;color:var(--text-primary)">{{ $todayMacros['carbs'] }}g</span>
                    </div>
                    <div class="progress-bar"><div class="progress-fill orange" style="width:{{ min(100, $todayMacros['carbs'] > 0 ? round(($todayMacros['carbs'] / max(50, $todayMacros['protein'] + $todayMacros['carbs'] + $todayMacros['fat'])) * 100) : 0) }}%"></div></div>
                </div>
                <div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                        <span style="color:#eab308;font-weight:600">Lemak</span>
                        <span style="font-weight:700;color:var(--text-primary)">{{ $todayMacros['fat'] }}g</span>
                    </div>
                    <div class="progress-bar"><div class="progress-fill" style="width:{{ min(100, $todayMacros['fat'] > 0 ? round(($todayMacros['fat'] / max(50, $todayMacros['protein'] + $todayMacros['carbs'] + $todayMacros['fat'])) * 100) : 0) }}%;background:#eab308"></div></div>
                </div>
                <div style="margin-top:4px;padding:8px;background:var(--bg-hover);border-radius:8px;font-size:11px;color:var(--text-muted)">
                    Estimasi AI · Koreksi kalori di Telegram dengan tombol 🔢
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── Today's Meals --}}
<div class="card animate-in" style="animation-delay:.10s">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div class="card-title" style="margin-bottom:0">Makanan Hari Ini</div>
        <div style="font-size:11px;color:var(--text-muted);display:flex;align-items:center;gap:6px">
            <span style="display:inline-block;width:8px;height:8px;background:var(--orange);border-radius:50%"></span>
            kcal estimasi AI · klik ✏️ untuk koreksi
        </div>
    </div>

    @if($todayMeals->count() > 0)
        @foreach($todayMeals as $meal)
        <div class="meal-item" id="meal-row-{{ $meal->id }}">
            <div class="meal-info" style="flex:1;min-width:0">
                <h4 style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                    🍽️ {{ $meal->food_item ?? '-' }}
                    @if($meal->is_calorie_estimated)
                        <span style="
                            display:inline-flex;align-items:center;gap:3px;
                            padding:2px 7px;border-radius:var(--radius-pill);
                            background:rgba(249,115,22,0.12);color:var(--orange);
                            font-size:10px;font-weight:600;
                        " title="Diestimasi oleh AI berdasarkan database makanan umum">⚡ est. AI</span>
                    @else
                        <span style="
                            padding:2px 7px;border-radius:var(--radius-pill);
                            background:rgba(16,185,129,0.12);color:var(--green);
                            font-size:10px;font-weight:600;
                        ">✓ manual</span>
                    @endif
                </h4>
                <p style="margin-top:3px">
                    {{ $meal->entry_time->format('H:i') }}
                    @if($meal->is_calorie_estimated)
                    · <span style="color:var(--text-dim);font-style:italic">
                        Estimasi berdasarkan rata-rata database makanan umum
                    </span>
                    @endif
                </p>
            </div>
            <div style="display:flex;align-items:center;gap:10px;flex-shrink:0">
                <div class="meal-cal" id="meal-cal-{{ $meal->id }}">{{ $meal->calories ?? 0 }} kcal</div>
                <button type="button"
                    onclick="openCalEdit({{ $meal->id }}, '{{ addslashes($meal->food_item ?? '') }}', {{ $meal->calories ?? 0 }})"
                    style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:14px;padding:4px 6px;border-radius:6px;line-height:1"
                    title="Edit kalori">✏️</button>
            </div>
        </div>

        {{-- Inline edit form (hidden by default) --}}
        <div id="meal-edit-{{ $meal->id }}" style="display:none;padding:14px 0 6px;border-top:1px solid var(--border)">
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                <div style="flex:1;min-width:140px">
                    <label style="font-size:11px;color:var(--text-muted);font-weight:500;display:block;margin-bottom:4px">Nama Makanan</label>
                    <input type="text" id="edit-food-{{ $meal->id }}" placeholder="{{ $meal->food_item }}"
                           style="width:100%;padding:8px 12px;background:var(--bg-elevated);border:1px solid var(--border);
                                  border-radius:var(--radius-sm);color:var(--text-primary);font-family:inherit;font-size:13px;outline:none">
                </div>
                <div style="width:110px">
                    <label style="font-size:11px;color:var(--text-muted);font-weight:500;display:block;margin-bottom:4px">Kalori (kcal)</label>
                    <input type="number" id="edit-cal-{{ $meal->id }}" value="{{ $meal->calories ?? 0 }}" min="0"
                           style="width:100%;padding:8px 12px;background:var(--bg-elevated);border:1px solid var(--border);
                                  border-radius:var(--radius-sm);color:var(--text-primary);font-family:inherit;font-size:13px;outline:none">
                </div>
                <div style="display:flex;gap:6px">
                    <button type="button" onclick="saveCalEdit({{ $meal->id }})"
                        style="padding:8px 16px;background:var(--text-primary);color:var(--bg-card);
                               border:none;border-radius:var(--radius-sm);font-size:13px;font-weight:600;cursor:pointer">
                        Simpan
                    </button>
                    <button type="button" onclick="cancelCalEdit({{ $meal->id }})"
                        style="padding:8px 12px;background:transparent;border:1px solid var(--border);
                               border-radius:var(--radius-sm);color:var(--text-muted);font-size:13px;cursor:pointer">
                        Batal
                    </button>
                </div>
            </div>
            <div id="edit-err-{{ $meal->id }}" style="font-size:12px;color:var(--red);margin-top:6px;display:none"></div>
        </div>
        @endforeach
    @else
        <div class="empty-state">
            <div class="empty-icon">🍽️</div>
            <h3>Belum ada makanan hari ini</h3>
            <p>Kirim "makan nasi goreng" ke Butler-mu!</p>
        </div>
    @endif
</div>

{{-- ── Calorie estimation info --}}
<div class="card animate-in" style="animation-delay:.12s;background:rgba(249,115,22,0.04);border-color:rgba(249,115,22,0.15)">
    <div style="display:flex;gap:14px;align-items:flex-start">
        <span style="font-size:22px;flex-shrink:0;line-height:1;margin-top:2px">⚡</span>
        <div>
            <div style="font-weight:600;font-size:13px;color:var(--text-primary);margin-bottom:4px">
                Tentang Estimasi Kalori AI
            </div>
            <div style="font-size:12px;color:var(--text-secondary);line-height:1.6">
                Kalori dihitung menggunakan estimasi AI berdasarkan rata-rata dari database makanan umum (USDA, FoodData Central, dan sumber makanan Asia Tenggara).
                Karena variasi porsi dan resep, angka ini bisa berbeda.
                Klik <strong>✏️</strong> di setiap makanan untuk koreksi manual — koreksi akan otomatis disimpan sebagai data "manual" (bukan estimasi).
            </div>
        </div>
    </div>
</div>

{{-- ── Recent Food Logs --}}
<div class="card animate-in" style="animation-delay:.14s">
    <div class="card-title">Riwayat Makanan</div>
    @if($recentMeals->count() > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Makanan</th>
                    <th>Waktu</th>
                    <th>Sumber</th>
                    <th style="text-align:right">Kalori</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentMeals as $meal)
                    <tr>
                        <td style="white-space:nowrap">{{ $meal->entry_time->format('d M') }}</td>
                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            {{ $meal->food_item ?? '-' }}
                        </td>
                        <td>{{ $meal->entry_time->format('H:i') }}</td>
                        <td>
                            @if($meal->is_calorie_estimated)
                                <span class="badge" style="background:rgba(249,115,22,0.1);color:var(--orange)">
                                    ⚡ est. AI
                                </span>
                            @else
                                <span class="badge" style="background:rgba(16,185,129,0.1);color:var(--green)">
                                    ✓ manual
                                </span>
                            @endif
                        </td>
                        <td style="text-align:right;font-weight:600;color:var(--orange)">
                            {{ $meal->calories ?? 0 }} kcal
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-state" style="padding:28px">
            <div class="empty-icon">📋</div>
            <h3>Belum ada riwayat makanan</h3>
            <p>Mulai tracking mealmu!</p>
        </div>
    @endif
</div>

@endsection

@section('scripts')
<script>
    // ── Inline calorie edit helpers ───────────────────────────────
    const ENTRY_BASE_URL = '{{ url("/dashboard/entries") }}';
    const CSRF = document.querySelector('meta[name=csrf-token]')?.content
              || document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || '';

    function openCalEdit(id, food, cal) {
        document.getElementById('meal-edit-' + id).style.display = 'block';
        const foodInput = document.getElementById('edit-food-' + id);
        const calInput  = document.getElementById('edit-cal-' + id);
        foodInput.value = food;
        calInput.value  = cal;
        foodInput.focus();
    }
    function cancelCalEdit(id) {
        document.getElementById('meal-edit-' + id).style.display = 'none';
        document.getElementById('edit-err-' + id).style.display  = 'none';
    }
    async function saveCalEdit(id) {
        const food = document.getElementById('edit-food-' + id).value.trim();
        const cal  = parseInt(document.getElementById('edit-cal-' + id).value, 10);
        const err  = document.getElementById('edit-err-' + id);
        if (isNaN(cal) || cal < 0) { err.textContent = 'Masukkan kalori yang valid.'; err.style.display = 'block'; return; }
        try {
            const resp = await fetch(`${ENTRY_BASE_URL}/${id}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(CSRF) },
                body: JSON.stringify({ calories: cal, food_item: food || undefined }),
            });
            if (!resp.ok) {
                const data = await resp.json();
                err.textContent = data.error || 'Gagal menyimpan. Coba lagi.';
                err.style.display = 'block'; return;
            }
            // Update UI without reload
            document.getElementById('meal-cal-' + id).textContent = cal + ' kcal';
            cancelCalEdit(id);
            // Replace "est. AI" badge with "manual"
            const row = document.getElementById('meal-row-' + id);
            if (row) {
                row.querySelectorAll('[title*="Diestimasi"]').forEach(el => {
                    el.outerHTML = '<span style="padding:2px 7px;border-radius:999px;background:rgba(16,185,129,0.12);color:var(--green);font-size:10px;font-weight:600">✓ manual</span>';
                });
            }
        } catch (e) {
            err.textContent = 'Network error. Coba lagi.'; err.style.display = 'block';
        }
    }

    // ── Daily calories chart ──────────────────────────────────────
    const calData  = @json($dailyCalories);
    const calLabels = Object.keys(calData).map(d => {
        const date = new Date(d);
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
    });
    const calorieGoal = {{ $calorieGoal }};
    const goalLine    = Array(calLabels.length).fill(calorieGoal);
    const gridColor   = getComputedStyle(document.documentElement).getPropertyValue('--border').trim();

    new Chart(document.getElementById('dailyCaloriesChart'), {
        type: 'bar',
        data: {
            labels: calLabels,
            datasets: [
                {
                    label: 'Kalori',
                    data: Object.values(calData),
                    backgroundColor: Object.values(calData).map(v => v > calorieGoal ? 'rgba(239,68,68,0.7)' : 'rgba(249,115,22,0.7)'),
                    borderRadius: 6,
                    borderSkipped: false,
                },
                {
                    label: 'Target',
                    data: goalLine,
                    type: 'line',
                    borderColor: 'rgba(139,92,246,0.65)',
                    borderDash: [5, 4],
                    borderWidth: 2,
                    pointRadius: 0,
                    fill: false,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, position: 'top', labels: { usePointStyle: true, pointStyle: 'circle' } },
            },
            scales: {
                y: { grid: { color: gridColor }, ticks: { callback: v => v + ' kcal' } },
                x: { grid: { display: false } }
            }
        }
    });

    // ── Macro doughnut ───────────────────────────────────────────
    @if(($todayMacros['protein'] + $todayMacros['carbs'] + $todayMacros['fat']) > 0)
    new Chart(document.getElementById('macroChart'), {
        type: 'doughnut',
        data: {
            labels: ['Protein', 'Karbohidrat', 'Lemak'],
            datasets: [{
                data: [{{ $todayMacros['protein'] }}, {{ $todayMacros['carbs'] }}, {{ $todayMacros['fat'] }}],
                backgroundColor: ['#3b82f6', '#f97316', '#eab308'],
                borderWidth: 0,
                hoverOffset: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.label + ': ' + ctx.parsed + 'g'
                    }
                }
            }
        }
    });
    @endif

    // ── Calorie goal donut ────────────────────────────────────────
    const consumed = {{ $todayMacros['calories'] }};
    const leftOver = Math.max(calorieGoal - consumed, 0);
    new Chart(document.getElementById('goalChart'), {
        type: 'doughnut',
        data: {
            labels: ['Dikonsumsi', 'Sisa'],
            datasets: [{
                data: [consumed, leftOver || (consumed > calorieGoal ? 0 : calorieGoal)],
                backgroundColor: consumed > calorieGoal
                    ? ['rgba(239,68,68,0.8)', 'rgba(239,68,68,0.08)']
                    : ['rgba(249,115,22,0.85)', 'rgba(249,115,22,0.08)'],
                borderWidth: 0,
                hoverOffset: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: { display: true, position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle' } },
                tooltip: { callbacks: { label: ctx => ctx.label + ': ' + ctx.parsed + ' kcal' } }
            }
        }
    });
</script>
@endsection
