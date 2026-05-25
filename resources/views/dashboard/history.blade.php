@extends('layouts.dashboard')
@section('title', 'Riwayat — Butler')
@section('content')

<div class="animate-in">
    <h2>Riwayat Transaksi</h2>
    <p class="page-desc">Semua catatan yang sudah dikonfirmasi.</p>
</div>

{{-- Filters --}}
<form method="GET" class="filter-bar animate-in" style="animation-delay:.05s">
    <select name="type" onchange="this.form.submit()">
        <option value="">Semua tipe</option>
        <option value="expense"      {{ request('type') === 'expense'      ? 'selected' : '' }}>Pengeluaran</option>
        <option value="meal"         {{ request('type') === 'meal'         ? 'selected' : '' }}>Makanan</option>
        <option value="income"       {{ request('type') === 'income'       ? 'selected' : '' }}>Pemasukan</option>
        <option value="saving"       {{ request('type') === 'saving'       ? 'selected' : '' }}>Tabungan</option>
        <option value="bill_payment" {{ request('type') === 'bill_payment' ? 'selected' : '' }}>Tagihan</option>
    </select>
    <input type="date" name="from" value="{{ request('from') }}" onchange="this.form.submit()" placeholder="Dari">
    <input type="date" name="to"   value="{{ request('to') }}"   onchange="this.form.submit()" placeholder="Sampai">
</form>

{{-- Table --}}
<div class="table-wrap animate-in" style="animation-delay:.1s">
    @if($entries->isNotEmpty())
    <table>
        <thead>
            <tr>
                <th>Waktu</th>
                <th>Keterangan</th>
                <th>Tipe</th>
                <th style="text-align:right">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entries as $entry)
            <tr>
                <td style="color:var(--text-muted);white-space:nowrap">
                    {{ $entry->entry_time->format('d M, H:i') }}
                </td>
                <td>
                    <div style="font-weight:500">
                        {{ $entry->food_item ?? $entry->merchant ?? $entry->note ?? '—' }}
                    </div>
                    @if($entry->category)
                    <div style="font-size:11px;color:var(--text-dim);margin-top:2px">{{ $entry->category }}</div>
                    @endif
                </td>
                <td>
                    <span class="badge badge-{{ $entry->type }}">
                        {{ match($entry->type) {
                            'expense'      => 'Pengeluaran',
                            'meal'         => 'Makanan',
                            'income'       => 'Pemasukan',
                            'saving'       => 'Tabungan',
                            'bill_payment' => 'Tagihan',
                            default        => $entry->type,
                        } }}
                    </span>
                </td>
                <td style="text-align:right;white-space:nowrap">
                    @if($entry->type === 'meal' && $entry->calories)
                        <span style="font-weight:600">{{ number_format($entry->calories) }} kcal</span>
                    @elseif($entry->amount)
                        <span class="{{ in_array($entry->type, ['income']) ? 'amount-income' : 'amount-expense' }}">
                            {{ in_array($entry->type, ['income']) ? '+' : '−' }}Rp {{ number_format($entry->amount, 0, ',', '.') }}
                        </span>
                    @else
                        <span style="color:var(--text-dim)">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div style="padding:48px 24px;text-align:center;color:var(--text-dim)">
        <div style="font-size:32px;margin-bottom:12px">📋</div>
        <div>Belum ada catatan yang cocok.</div>
    </div>
    @endif
</div>

{{-- Pagination --}}
@if($entries->hasPages())
<div class="pagination">
    @if($entries->onFirstPage())
        <span class="page-btn" style="opacity:.3">← Prev</span>
    @else
        <a href="{{ $entries->previousPageUrl() }}" class="page-btn">← Prev</a>
    @endif

    <span class="page-btn active">{{ $entries->currentPage() }}</span>

    @if($entries->hasMorePages())
        <a href="{{ $entries->nextPageUrl() }}" class="page-btn">Next →</a>
    @else
        <span class="page-btn" style="opacity:.3">Next →</span>
    @endif
</div>
@endif

@endsection
