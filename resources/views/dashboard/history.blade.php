@extends('layouts.dashboard')
@section('title', 'Riwayat — Butler')
@section('content')

<div class="page-header animate-in">
    <div>
        <h2>Riwayat Transaksi</h2>
        <p>Semua catatan yang sudah dikonfirmasi.</p>
    </div>
    <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;
              border-radius:var(--radius-sm);border:1px solid var(--border);
              background:var(--bg-card);font-size:12px;color:var(--text-secondary);
              text-decoration:none;box-shadow:var(--card-shadow);font-weight:500;white-space:nowrap">
        ⬇ Export CSV
    </a>
</div>

{{-- Filters --}}
<div class="card animate-in" style="animation-delay:.04s;padding:14px 16px">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        {{-- keep export param out of filter form --}}
        <input type="hidden" name="export" value="">

        <div>
            <label class="field-label">Tipe</label>
            <select name="type" class="field-input" style="width:auto">
                <option value="">Semua tipe</option>
                <option value="expense"              {{ request('type') === 'expense'              ? 'selected' : '' }}>Pengeluaran</option>
                <option value="meal"                 {{ request('type') === 'meal'                 ? 'selected' : '' }}>Makanan</option>
                <option value="income"               {{ request('type') === 'income'               ? 'selected' : '' }}>Pemasukan</option>
                <option value="saving"               {{ request('type') === 'saving'               ? 'selected' : '' }}>Tabungan</option>
                <option value="bill_payment"         {{ request('type') === 'bill_payment'         ? 'selected' : '' }}>Tagihan</option>
                <option value="debt_payment"         {{ request('type') === 'debt_payment'         ? 'selected' : '' }}>Cicilan</option>
                <option value="sinking_fund_deposit" {{ request('type') === 'sinking_fund_deposit' ? 'selected' : '' }}>Sinking Fund</option>
            </select>
        </div>

        <div>
            <label class="field-label">Dari</label>
            <input type="date" name="from" value="{{ request('from') }}" class="field-input" style="width:auto">
        </div>
        <div>
            <label class="field-label">Sampai</label>
            <input type="date" name="to" value="{{ request('to') }}" class="field-input" style="width:auto">
        </div>

        <div>
            <label class="field-label">Min Jumlah (Rp)</label>
            <input type="number" name="min_amount" value="{{ request('min_amount') }}"
                   placeholder="0" min="0" class="field-input" style="width:120px">
        </div>
        <div>
            <label class="field-label">Max Jumlah (Rp)</label>
            <input type="number" name="max_amount" value="{{ request('max_amount') }}"
                   placeholder="∞" min="0" class="field-input" style="width:120px">
        </div>

        @if($funds->isNotEmpty())
        <div>
            <label class="field-label">Akun / Dana</label>
            <select name="fund_id" class="field-input" style="width:auto">
                <option value="">Semua akun</option>
                @foreach($funds as $f)
                    <option value="{{ $f->id }}" {{ request('fund_id') == $f->id ? 'selected' : '' }}>{{ $f->name }}</option>
                @endforeach
            </select>
        </div>
        @endif

        <button type="submit" class="btn-save" style="padding:10px 16px">Filter</button>

        @if(request()->hasAny(['type','from','to','min_amount','max_amount','fund_id']))
        <a href="{{ route('dashboard.history') }}"
           style="font-size:13px;color:var(--text-muted);text-decoration:none;padding:10px 6px">Reset</a>
        @endif
    </form>
</div>

{{-- Table --}}
<div class="table-wrap animate-in" style="animation-delay:.1s">
    @if($entries->isNotEmpty())
    <table>
        <thead>
            <tr>
                <th>Waktu</th>
                <th>Keterangan</th>
                <th>Tipe</th>
                <th>Akun</th>
                <th style="text-align:right">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entries as $entry)
            <tr>
                <td style="color:var(--text-muted);white-space:nowrap;font-size:12px">
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
                            'expense'              => 'Pengeluaran',
                            'meal'                 => 'Makanan',
                            'income'               => 'Pemasukan',
                            'saving'               => 'Tabungan',
                            'bill_payment'         => 'Tagihan',
                            'debt_payment'         => 'Cicilan',
                            'sinking_fund_deposit' => 'Sinking Fund',
                            default                => $entry->type,
                        } }}
                    </span>
                </td>
                <td style="font-size:12px;color:var(--text-dim)">
                    {{ $entry->fundTransactions->first()?->fund?->name ?? '—' }}
                </td>
                <td style="text-align:right;white-space:nowrap">
                    @if($entry->type === 'meal' && $entry->calories)
                        <span style="font-weight:600">{{ number_format($entry->calories) }} kcal</span>
                    @elseif($entry->amount)
                        <span class="{{ in_array($entry->type, ['income','saving','sinking_fund_deposit']) ? 'amount-income' : 'amount-expense' }}">
                            {{ in_array($entry->type, ['income','saving','sinking_fund_deposit']) ? '+' : '−' }}Rp {{ number_format($entry->amount, 0, ',', '.') }}
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
        @if(request()->hasAny(['type','from','to','min_amount','max_amount','fund_id']))
            <a href="{{ route('dashboard.history') }}" style="font-size:13px;color:var(--accent);margin-top:8px;display:inline-block">
                Reset filter →
            </a>
        @endif
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

    <span class="page-btn active">{{ $entries->currentPage() }} / {{ $entries->lastPage() }}</span>

    @if($entries->hasMorePages())
        <a href="{{ $entries->nextPageUrl() }}" class="page-btn">Next →</a>
    @else
        <span class="page-btn" style="opacity:.3">Next →</span>
    @endif
</div>
@endif

@endsection
