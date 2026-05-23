@extends('layouts.dashboard')
@section('title', 'Overview')

@section('content')
<div class="page-header">
    <h2>Good {{ now()->timezone(config('butler.timezone'))->format('H') < 12 ? 'Morning' : (now()->timezone(config('butler.timezone'))->format('H') < 18 ? 'Afternoon' : 'Evening') }} 👋</h2>
    <p>{{ $today->format('l, d F Y') }} — Here's your daily snapshot</p>
</div>

<!-- Stat Cards -->
<div class="grid-4">
    <div class="card stat-card red animate-in">
        <div class="stat-icon">💸</div>
        <div class="card-title">Today's Spending</div>
        <div class="card-value">Rp {{ number_format($todaySpending, 0, ',', '.') }}</div>
        <div class="card-subtitle">Budget: Rp {{ number_format($monthBudget, 0, ',', '.') }}/mo</div>
        @php $budgetPct = $monthBudget > 0 ? min(round(($monthSpending / $monthBudget) * 100), 100) : 0; @endphp
        <div class="progress-bar">
            <div class="progress-fill {{ $budgetPct > 80 ? 'red' : 'green' }}" style="width: {{ $budgetPct }}%"></div>
        </div>
        <div class="card-subtitle">{{ $budgetPct }}% of monthly budget used</div>
    </div>

    <div class="card stat-card green animate-in">
        <div class="stat-icon">💰</div>
        <div class="card-title">Today's Income</div>
        <div class="card-value">Rp {{ number_format($todayIncome, 0, ',', '.') }}</div>
        <div class="card-subtitle">Month total: Rp {{ number_format($monthSpending, 0, ',', '.') }} spent</div>
    </div>

    <div class="card stat-card orange animate-in">
        <div class="stat-icon">🔥</div>
        <div class="card-title">Calories Today</div>
        <div class="card-value">{{ number_format($todayMacros['calories']) }}</div>
        <div class="card-subtitle">of {{ number_format($calorieGoal) }} kcal goal</div>
        @php $calPct = $calorieGoal > 0 ? min(round(($todayMacros['calories'] / $calorieGoal) * 100), 100) : 0; @endphp
        <div class="progress-bar">
            <div class="progress-fill {{ $calPct > 100 ? 'red' : 'orange' }}" style="width: {{ $calPct }}%"></div>
        </div>
        <div class="card-subtitle">{{ $calPct }}% of daily goal</div>
    </div>

    <div class="card stat-card blue animate-in">
        <div class="stat-icon">💎</div>
        <div class="card-title">Total Savings</div>
        <div class="card-value">Rp {{ number_format($totalSavings, 0, ',', '.') }}</div>
        @if($todayMood)
            <div class="card-subtitle">Mood: {{ $todayMood->mood_emoji }} {{ ucfirst($todayMood->mood) }}</div>
        @else
            <div class="card-subtitle">No mood logged yet today</div>
        @endif
    </div>
</div>

<!-- Charts + Meals -->
<div class="grid-2">
    <!-- Macros Breakdown -->
    <div class="card animate-in">
        <div class="card-title">Today's Macros</div>
        <div class="chart-container" style="height: 220px;">
            <canvas id="macroChart"></canvas>
        </div>
    </div>

    <!-- Today's Meals -->
    <div class="card animate-in">
        <div class="card-title">Today's Meals</div>
        @if($todayMeals->count() > 0)
            @foreach($todayMeals as $meal)
                <div class="meal-item">
                    <div class="meal-info">
                        <h4>{{ $meal->food_name }}</h4>
                        <p>{{ ucfirst($meal->meal_type ?? 'snack') }} · {{ $meal->serving_size ?? 1 }} {{ $meal->serving_unit ?? 'porsi' }}</p>
                    </div>
                    <div class="meal-cal">{{ $meal->calories ?? 0 }} kcal</div>
                </div>
            @endforeach
        @else
            <div class="empty-state">
                <div class="empty-icon">🍽️</div>
                <h3>No meals logged yet</h3>
                <p>Send "makan nasi goreng" to your Butler bot!</p>
            </div>
        @endif
    </div>
</div>

<!-- Recent Transactions -->
<div class="card animate-in">
    <div class="card-title">Recent Transactions</div>
    @if($recentTransactions->count() > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Category</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentTransactions as $tx)
                    <tr>
                        <td>{{ $tx->transaction_date->format('d M') }}</td>
                        <td><span class="badge badge-{{ $tx->type }}">{{ $tx->type }}</span></td>
                        <td>{{ $tx->description }}</td>
                        <td>{{ $tx->category ?? '-' }}</td>
                        <td style="text-align: right; font-weight: 600; color: {{ $tx->type === 'expense' ? 'var(--red)' : 'var(--green)' }};">
                            {{ $tx->type === 'expense' ? '-' : '+' }}Rp {{ number_format($tx->amount, 0, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-state">
            <div class="empty-icon">📝</div>
            <h3>No transactions yet</h3>
            <p>Send "spent 50rb mie ayam" to start tracking!</p>
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    // Macros donut chart
    const macroCtx = document.getElementById('macroChart');
    if (macroCtx) {
        new Chart(macroCtx, {
            type: 'doughnut',
            data: {
                labels: ['Protein', 'Carbs', 'Fat'],
                datasets: [{
                    data: [{{ $todayMacros['protein'] }}, {{ $todayMacros['carbs'] }}, {{ $todayMacros['fat'] }}],
                    backgroundColor: ['#6c5ce7', '#ffa726', '#ff6b81'],
                    borderWidth: 0,
                    hoverOffset: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 16, usePointStyle: true, pointStyle: 'circle' }
                    }
                }
            }
        });
    }
</script>
@endsection
