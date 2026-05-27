@extends('layouts.dashboard')
@section('title', 'Nutrition')

@section('content')
<div class="page-header">
    <h2>Nutrition Tracker 🍽️</h2>
    <p>Monitor your daily calorie and macro intake</p>
</div>

<!-- Stat Cards -->
<div class="grid-4">
    <div class="card stat-card orange animate-in">
        <div class="stat-icon">🔥</div>
        <div class="card-title">Today's Calories</div>
        <div class="card-value">{{ number_format($todayMacros['calories']) }}</div>
        @php $calPct = $calorieGoal > 0 ? min(round(($todayMacros['calories'] / $calorieGoal) * 100), 100) : 0; @endphp
        <div class="progress-bar">
            <div class="progress-fill {{ $todayMacros['calories'] > $calorieGoal ? 'red' : 'orange' }}" style="width: {{ $calPct }}%"></div>
        </div>
        <div class="card-subtitle">{{ $calPct }}% of {{ number_format($calorieGoal) }} kcal</div>
    </div>

    <div class="card stat-card accent animate-in">
        <div class="stat-icon">🍽️</div>
        <div class="card-title">Meals Today</div>
        <div class="card-value">{{ $todayMeals->count() }}</div>
        <div class="card-subtitle">entries logged</div>
    </div>

    <div class="card stat-card blue animate-in">
        <div class="stat-icon">📅</div>
        <div class="card-title">7-Day Average</div>
        <div class="card-value">{{ number_format($avgCalories) }}</div>
        <div class="card-subtitle">kcal per day</div>
    </div>

    <div class="card stat-card green animate-in">
        <div class="stat-icon">🎯</div>
        <div class="card-title">Remaining Today</div>
        @php $remaining = max($calorieGoal - $todayMacros['calories'], 0); @endphp
        <div class="card-value" style="color: {{ $todayMacros['calories'] > $calorieGoal ? 'var(--red)' : 'var(--green)' }}">
            {{ number_format($remaining) }}
        </div>
        <div class="card-subtitle">kcal left</div>
    </div>
</div>

<!-- Charts -->
<div class="grid-2">
    <div class="card animate-in">
        <div class="card-title">Daily Calories (Last 30 Days)</div>
        <div class="chart-container">
            <canvas id="dailyCaloriesChart"></canvas>
        </div>
    </div>

    <div class="card animate-in">
        <div class="card-title">Calorie Goal Progress</div>
        <div class="chart-container" style="height: 220px;">
            <canvas id="goalChart"></canvas>
        </div>
        @php
            $pctUsed  = $calorieGoal > 0 ? min(round(($todayMacros['calories'] / $calorieGoal) * 100), 100) : 0;
            $pctLeft  = max(100 - $pctUsed, 0);
        @endphp
        <div style="display: flex; justify-content: space-around; margin-top: 16px;">
            <div style="text-align: center;">
                <div style="font-size: 20px; font-weight: 700; color: var(--orange);">{{ number_format($todayMacros['calories']) }}</div>
                <div style="font-size: 11px; color: var(--text-muted);">Consumed</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 20px; font-weight: 700; color: var(--green);">{{ number_format($calorieGoal) }}</div>
                <div style="font-size: 11px; color: var(--text-muted);">Goal</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 20px; font-weight: 700; color: {{ $pctLeft === 0 ? 'var(--red)' : 'var(--accent)' }};">{{ $pctUsed }}%</div>
                <div style="font-size: 11px; color: var(--text-muted);">Used</div>
            </div>
        </div>
    </div>
</div>

<!-- Today's Meals -->
<div class="card animate-in">
    <div class="card-title">Today's Meals</div>
    @if($todayMeals->count() > 0)
        @foreach($todayMeals as $meal)
            <div class="meal-item">
                <div class="meal-info">
                    <h4>🍽️ {{ $meal->food_item ?? '-' }}</h4>
                    <p>{{ $meal->entry_time->format('H:i') }}{{ $meal->is_calorie_estimated ? ' · estimasi' : '' }}</p>
                </div>
                <div class="meal-cal">{{ $meal->calories ?? 0 }} kcal</div>
            </div>
        @endforeach
    @else
        <div class="empty-state">
            <div class="empty-icon">🍽️</div>
            <h3>No meals logged today</h3>
            <p>Send "makan nasi goreng" to your Butler bot!</p>
        </div>
    @endif
</div>

<!-- Recent Food Logs -->
<div class="card animate-in" style="margin-top: 16px;">
    <div class="card-title">Recent Food History</div>
    @if($recentMeals->count() > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Food</th>
                    <th>Time</th>
                    <th>Source</th>
                    <th style="text-align: right;">Calories</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentMeals as $meal)
                    <tr>
                        <td>{{ $meal->entry_time->format('d M') }}</td>
                        <td>{{ $meal->food_item ?? '-' }}</td>
                        <td>{{ $meal->entry_time->format('H:i') }}</td>
                        <td><span class="badge badge-food">{{ $meal->is_calorie_estimated ? 'estimasi' : 'manual' }}</span></td>
                        <td style="text-align: right; font-weight: 600; color: var(--orange);">{{ $meal->calories ?? 0 }} kcal</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <h3>No food history yet</h3>
            <p>Start tracking your meals!</p>
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    // Daily calories chart with goal line
    const calData = @json($dailyCalories);
    const calLabels = Object.keys(calData).map(d => {
        const date = new Date(d);
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
    });
    const goalLine = Array(Object.keys(calData).length).fill({{ $calorieGoal }});

    new Chart(document.getElementById('dailyCaloriesChart'), {
        type: 'bar',
        data: {
            labels: calLabels,
            datasets: [
                {
                    label: 'Calories',
                    data: Object.values(calData),
                    backgroundColor: Object.values(calData).map(v => v > {{ $calorieGoal }} ? 'rgba(255,107,129,0.7)' : 'rgba(255,167,38,0.7)'),
                    borderRadius: 6,
                    borderSkipped: false,
                },
                {
                    label: 'Goal',
                    data: goalLine,
                    type: 'line',
                    borderColor: 'rgba(108,92,231,0.6)',
                    borderDash: [5, 5],
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
                legend: { display: true, position: 'top', labels: { usePointStyle: true } },
            },
            scales: {
                y: { grid: { color: 'rgba(35,35,47,0.5)' } },
                x: { grid: { display: false } }
            }
        }
    });

    // Calorie goal donut
    const consumed = {{ $todayMacros['calories'] }};
    const goal     = {{ $calorieGoal }};
    const leftOver = Math.max(goal - consumed, 0);
    new Chart(document.getElementById('goalChart'), {
        type: 'doughnut',
        data: {
            labels: ['Consumed', 'Remaining'],
            datasets: [{
                data: [consumed, leftOver || (consumed > goal ? 0 : goal)],
                backgroundColor: consumed > goal ? ['#ff6b81', 'rgba(35,35,47,0.3)'] : ['#ffa726', 'rgba(35,35,47,0.3)'],
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { display: true, position: 'bottom', labels: { usePointStyle: true } },
                tooltip: { callbacks: { label: ctx => ctx.label + ': ' + ctx.parsed + ' kcal' } }
            }
        }
    });
</script>
@endsection
