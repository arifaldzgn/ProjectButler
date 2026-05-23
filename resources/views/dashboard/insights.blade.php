@extends('layouts.dashboard')
@section('title', 'Insights')

@section('content')
<div class="page-header">
    <h2>Insights & Trends 💡</h2>
    <p>Patterns and analytics across your daily habits</p>
</div>

<!-- Overview Stats -->
<div class="grid-3">
    <div class="card stat-card accent animate-in">
        <div class="stat-icon">📈</div>
        <div class="card-title">Spending Trend</div>
        @php
            $spendingValues = array_values($dailySpending);
            $recentAvg = count($spendingValues) > 7
                ? array_sum(array_slice($spendingValues, -7)) / 7
                : (count($spendingValues) > 0 ? array_sum($spendingValues) / count($spendingValues) : 0);
            $olderAvg = count($spendingValues) > 14
                ? array_sum(array_slice($spendingValues, -14, 7)) / 7
                : $recentAvg;
            $trendPct = $olderAvg > 0 ? round((($recentAvg - $olderAvg) / $olderAvg) * 100) : 0;
        @endphp
        <div class="card-value" style="color: {{ $trendPct > 0 ? 'var(--red)' : 'var(--green)' }}">
            {{ $trendPct > 0 ? '+' : '' }}{{ $trendPct }}%
        </div>
        <div class="card-subtitle">vs previous week</div>
    </div>

    <div class="card stat-card orange animate-in">
        <div class="stat-icon">🔥</div>
        <div class="card-title">Avg Daily Calories</div>
        @php
            $calValues = array_values($dailyCalories);
            $avgCal = count($calValues) > 0 ? round(array_sum($calValues) / count($calValues)) : 0;
        @endphp
        <div class="card-value">{{ number_format($avgCal) }} kcal</div>
        <div class="card-subtitle">Goal: {{ number_format($calorieGoal) }} kcal</div>
    </div>

    <div class="card stat-card green animate-in">
        <div class="stat-icon">🎯</div>
        <div class="card-title">Budget Health</div>
        @php
            $monthTotal = array_sum(array_values($dailySpending));
            $budgetPct = $monthBudget > 0 ? round(($monthTotal / $monthBudget) * 100) : 0;
        @endphp
        <div class="card-value" style="color: {{ $budgetPct > 80 ? 'var(--red)' : 'var(--green)' }}">
            {{ $budgetPct }}%
        </div>
        <div class="card-subtitle">of monthly budget used</div>
    </div>
</div>

<!-- Combined Trend Chart -->
<div class="card animate-in">
    <div class="card-title">Spending vs Calories (30 Days)</div>
    <div class="chart-container" style="height: 320px;">
        <canvas id="combinedChart"></canvas>
    </div>
</div>

<!-- Mood + Category -->
<div class="grid-2" style="margin-top: 16px;">
    <div class="card animate-in">
        <div class="card-title">Mood History</div>
        @if(count($moodHistory) > 0)
            <div class="chart-container">
                <canvas id="moodChart"></canvas>
            </div>
        @else
            <div class="empty-state">
                <div class="empty-icon">😊</div>
                <h3>No mood data yet</h3>
                <p>Send "mood: good, energi 4" to log!</p>
            </div>
        @endif
    </div>

    <div class="card animate-in">
        <div class="card-title">Spending Categories (This Month)</div>
        @if(count($categoryBreakdown) > 0)
            <div class="chart-container">
                <canvas id="categoryChart"></canvas>
            </div>
        @else
            <div class="empty-state">
                <div class="empty-icon">📊</div>
                <h3>No spending data yet</h3>
                <p>Start tracking to see your category breakdown!</p>
            </div>
        @endif
    </div>
</div>

<!-- Insights Cards -->
<div class="card animate-in" style="margin-top: 16px;">
    <div class="card-title">🧠 AI Insights</div>
    <div style="padding: 16px 0;">
        @php
            $insights = [];

            // Spending insights
            if ($trendPct > 10) {
                $insights[] = ['icon' => '⚠️', 'text' => "Your spending increased by {$trendPct}% compared to last week. Try to cut down on non-essential expenses."];
            } elseif ($trendPct < -10) {
                $insights[] = ['icon' => '✅', 'text' => "Great job! Spending decreased by " . abs($trendPct) . "% this week."];
            }

            // Calorie insights
            if ($avgCal > $calorieGoal * 1.1) {
                $excess = $avgCal - $calorieGoal;
                $insights[] = ['icon' => '⚠️', 'text' => "You're averaging {$excess} kcal above your daily goal. Consider lighter meals or more exercise."];
            } elseif ($avgCal > 0 && $avgCal < $calorieGoal * 0.7) {
                $insights[] = ['icon' => '💡', 'text' => "You might not be eating enough. Average is only {$avgCal} kcal vs your {$calorieGoal} goal."];
            }

            // Budget insights
            if ($budgetPct > 80) {
                $insights[] = ['icon' => '🔴', 'text' => "You've used {$budgetPct}% of your monthly budget. Be careful with remaining spending!"];
            }

            // Top category
            if (!empty($categoryBreakdown)) {
                $topCat = array_key_first($categoryBreakdown);
                arsort($categoryBreakdown);
                $topCat = array_key_first($categoryBreakdown);
                $topAmount = number_format($categoryBreakdown[$topCat], 0, ',', '.');
                $insights[] = ['icon' => '📊', 'text' => "Your biggest spending category is '{$topCat}' at Rp {$topAmount} this month."];
            }

            if (empty($insights)) {
                $insights[] = ['icon' => '🤖', 'text' => 'Start logging more data to get personalized insights! Send messages to your Butler Telegram bot.'];
            }
        @endphp

        @foreach($insights as $insight)
            <div style="display: flex; gap: 12px; align-items: flex-start; padding: 12px 0; border-bottom: 1px solid rgba(35,35,47,0.5);">
                <span style="font-size: 20px;">{{ $insight['icon'] }}</span>
                <p style="font-size: 14px; color: var(--text-secondary); line-height: 1.5;">{{ $insight['text'] }}</p>
            </div>
        @endforeach
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Combined spending + calories chart
    const spendData = @json($dailySpending);
    const calData = @json($dailyCalories);

    // Merge dates from both datasets
    const allDates = [...new Set([...Object.keys(spendData), ...Object.keys(calData)])].sort();
    const labels = allDates.map(d => {
        const date = new Date(d);
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
    });

    new Chart(document.getElementById('combinedChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Spending (Rp)',
                    data: allDates.map(d => spendData[d] || 0),
                    borderColor: '#ff6b81',
                    backgroundColor: 'rgba(255,107,129,0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    yAxisID: 'y',
                },
                {
                    label: 'Calories (kcal)',
                    data: allDates.map(d => calData[d] || 0),
                    borderColor: '#ffa726',
                    backgroundColor: 'rgba(255,167,38,0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true, padding: 16 } },
            },
            scales: {
                y: {
                    type: 'linear',
                    position: 'left',
                    ticks: { callback: v => 'Rp ' + (v/1000) + 'k' },
                    grid: { color: 'rgba(35,35,47,0.5)' },
                },
                y1: {
                    type: 'linear',
                    position: 'right',
                    ticks: { callback: v => v + ' kcal' },
                    grid: { display: false },
                },
                x: { grid: { display: false } }
            }
        }
    });

    // Mood chart
    @if(count($moodHistory) > 0)
    const moodData = @json($moodHistory);
    new Chart(document.getElementById('moodChart'), {
        type: 'line',
        data: {
            labels: moodData.map(m => {
                const d = new Date(m.date);
                return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
            }),
            datasets: [
                {
                    label: 'Mood',
                    data: moodData.map(m => m.mood_score),
                    borderColor: '#6c5ce7',
                    backgroundColor: 'rgba(108,92,231,0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 4,
                },
                {
                    label: 'Energy',
                    data: moodData.map(m => m.energy),
                    borderColor: '#ffa726',
                    borderDash: [5,5],
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 3,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    min: 0, max: 5,
                    ticks: {
                        stepSize: 1,
                        callback: v => ['', '😢', '😔', '😐', '😊', '🤩'][v] || v,
                    },
                    grid: { color: 'rgba(35,35,47,0.5)' },
                },
                x: { grid: { display: false } }
            },
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true } },
            }
        }
    });
    @endif

    // Category donut
    @if(count($categoryBreakdown) > 0)
    const catData = @json($categoryBreakdown);
    const catColors = {
        food: '#ffa726', transport: '#42a5f5', entertainment: '#ab47bc',
        shopping: '#ec407a', bills: '#78909c', health: '#66bb6a',
        education: '#5c6bc0', savings: '#26c6da', other: '#8d6e63'
    };

    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(catData).map(k => k.charAt(0).toUpperCase() + k.slice(1)),
            datasets: [{
                data: Object.values(catData),
                backgroundColor: Object.keys(catData).map(k => catColors[k] || '#8d6e63'),
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: { usePointStyle: true, pointStyle: 'circle', font: { size: 11 } }
                },
                tooltip: {
                    callbacks: { label: ctx => ctx.label + ': ' + formatRupiah(ctx.parsed) }
                }
            }
        }
    });
    @endif
</script>
@endsection
