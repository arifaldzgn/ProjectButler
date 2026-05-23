@extends('layouts.dashboard')
@section('title', 'Spending')

@section('content')
<div class="page-header">
    <h2>Spending Analytics 💸</h2>
    <p>Track where your money goes</p>
</div>

<!-- Stat Cards -->
<div class="grid-4">
    <div class="card stat-card red animate-in">
        <div class="stat-icon">📅</div>
        <div class="card-title">This Month</div>
        <div class="card-value">Rp {{ number_format($monthSpending, 0, ',', '.') }}</div>
        @php $pct = $monthBudget > 0 ? min(round(($monthSpending / $monthBudget) * 100), 100) : 0; @endphp
        <div class="progress-bar">
            <div class="progress-fill {{ $pct > 80 ? 'red' : 'green' }}" style="width: {{ $pct }}%"></div>
        </div>
        <div class="card-subtitle">{{ $pct }}% of Rp {{ number_format($monthBudget, 0, ',', '.') }}</div>
    </div>

    <div class="card stat-card green animate-in">
        <div class="stat-icon">🎯</div>
        <div class="card-title">Budget Remaining</div>
        @php $remaining = max($monthBudget - $monthSpending, 0); @endphp
        <div class="card-value">Rp {{ number_format($remaining, 0, ',', '.') }}</div>
        <div class="card-subtitle">{{ 100 - $pct }}% left this month</div>
    </div>

    <div class="card stat-card blue animate-in">
        <div class="stat-icon">💎</div>
        <div class="card-title">Month Savings</div>
        <div class="card-value">Rp {{ number_format($monthSavings, 0, ',', '.') }}</div>
        <div class="card-subtitle">Total: Rp {{ number_format($totalSavings, 0, ',', '.') }}</div>
    </div>

    <div class="card stat-card accent animate-in">
        <div class="stat-icon">📊</div>
        <div class="card-title">Daily Average</div>
        @php
            $daysCount = count($dailySpending) ?: 1;
            $avg = array_sum($dailySpending) / $daysCount;
        @endphp
        <div class="card-value">Rp {{ number_format($avg, 0, ',', '.') }}</div>
        <div class="card-subtitle">Over last {{ $daysCount }} days</div>
    </div>
</div>

<!-- Charts -->
<div class="grid-2">
    <div class="card animate-in">
        <div class="card-title">Daily Spending (Last 30 Days)</div>
        <div class="chart-container">
            <canvas id="dailySpendingChart"></canvas>
        </div>
    </div>

    <div class="card animate-in">
        <div class="card-title">Spending by Category</div>
        <div class="chart-container">
            <canvas id="categoryChart"></canvas>
        </div>
    </div>
</div>

<!-- Transaction History -->
<div class="card animate-in">
    <div class="card-title">Transaction History</div>
    @if($transactions->count() > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Category</th>
                    <th>Source</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transactions as $tx)
                    <tr>
                        <td>{{ $tx->transaction_date->format('d M Y') }}</td>
                        <td><span class="badge badge-{{ $tx->type }}">{{ $tx->type }}</span></td>
                        <td>{{ $tx->description }}</td>
                        <td>{{ $tx->category ?? '-' }}</td>
                        <td>{{ $tx->source ?? '-' }}</td>
                        <td style="text-align: right; font-weight: 600; color: {{ $tx->type === 'expense' ? 'var(--red)' : 'var(--green)' }};">
                            {{ $tx->type === 'expense' ? '-' : '+' }}Rp {{ number_format($tx->amount, 0, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-state">
            <div class="empty-icon">💸</div>
            <h3>No transactions yet</h3>
            <p>Start logging with "spent 50rb mie ayam"</p>
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    // Daily spending line chart
    const dailyData = @json($dailySpending);
    const dailyLabels = Object.keys(dailyData).map(d => {
        const date = new Date(d);
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
    });

    new Chart(document.getElementById('dailySpendingChart'), {
        type: 'line',
        data: {
            labels: dailyLabels,
            datasets: [{
                label: 'Spending',
                data: Object.values(dailyData),
                borderColor: '#ff6b81',
                backgroundColor: 'rgba(255, 107, 129, 0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => formatRupiah(ctx.parsed.y)
                    }
                }
            },
            scales: {
                y: {
                    ticks: { callback: v => 'Rp ' + (v/1000) + 'k' },
                    grid: { color: 'rgba(35,35,47,0.5)' }
                },
                x: { grid: { display: false } }
            }
        }
    });

    // Category pie chart
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
                hoverOffset: 8,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: { padding: 12, usePointStyle: true, pointStyle: 'circle', font: { size: 12 } }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.label + ': ' + formatRupiah(ctx.parsed)
                    }
                }
            }
        }
    });
</script>
@endsection
