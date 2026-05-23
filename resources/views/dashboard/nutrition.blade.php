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
        <div class="stat-icon">🥩</div>
        <div class="card-title">Protein</div>
        <div class="card-value">{{ $todayMacros['protein'] }}g</div>
        <div class="card-subtitle">Today's total</div>
    </div>

    <div class="card stat-card blue animate-in">
        <div class="stat-icon">🍚</div>
        <div class="card-title">Carbs</div>
        <div class="card-value">{{ $todayMacros['carbs'] }}g</div>
        <div class="card-subtitle">Today's total</div>
    </div>

    <div class="card stat-card green animate-in">
        <div class="stat-icon">📈</div>
        <div class="card-title">7-Day Average</div>
        <div class="card-value">{{ number_format($avgCalories) }}</div>
        <div class="card-subtitle">kcal per day</div>
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
        <div class="card-title">Macro Breakdown</div>
        <div class="chart-container" style="height: 220px;">
            <canvas id="macroChart"></canvas>
        </div>
        <div style="display: flex; justify-content: space-around; margin-top: 16px;">
            <div style="text-align: center;">
                <div style="font-size: 20px; font-weight: 700; color: var(--accent);">{{ $todayMacros['protein'] }}g</div>
                <div style="font-size: 11px; color: var(--text-muted);">Protein</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 20px; font-weight: 700; color: var(--orange);">{{ $todayMacros['carbs'] }}g</div>
                <div style="font-size: 11px; color: var(--text-muted);">Carbs</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 20px; font-weight: 700; color: var(--red);">{{ $todayMacros['fat'] }}g</div>
                <div style="font-size: 11px; color: var(--text-muted);">Fat</div>
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
                    <h4>
                        @php
                            $mealEmoji = match($meal->meal_type) {
                                'breakfast' => '🌅', 'lunch' => '☀️', 'dinner' => '🌙', default => '🍪'
                            };
                        @endphp
                        {{ $mealEmoji }} {{ $meal->food_name }}
                    </h4>
                    <p>{{ ucfirst($meal->meal_type ?? 'snack') }} · P: {{ $meal->protein_g ?? 0 }}g · C: {{ $meal->carbs_g ?? 0 }}g · F: {{ $meal->fat_g ?? 0 }}g</p>
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
                    <th>Meal</th>
                    <th>Protein</th>
                    <th>Carbs</th>
                    <th>Fat</th>
                    <th style="text-align: right;">Calories</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentMeals as $meal)
                    <tr>
                        <td>{{ $meal->log_date->format('d M') }}</td>
                        <td>{{ $meal->food_name }}</td>
                        <td><span class="badge badge-food">{{ $meal->meal_type ?? 'snack' }}</span></td>
                        <td>{{ $meal->protein_g ?? 0 }}g</td>
                        <td>{{ $meal->carbs_g ?? 0 }}g</td>
                        <td>{{ $meal->fat_g ?? 0 }}g</td>
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

    // Macro donut
    new Chart(document.getElementById('macroChart'), {
        type: 'doughnut',
        data: {
            labels: ['Protein', 'Carbs', 'Fat'],
            datasets: [{
                data: [{{ $todayMacros['protein'] }}, {{ $todayMacros['carbs'] }}, {{ $todayMacros['fat'] }}],
                backgroundColor: ['#6c5ce7', '#ffa726', '#ff6b81'],
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: { legend: { display: false } }
        }
    });
</script>
@endsection
