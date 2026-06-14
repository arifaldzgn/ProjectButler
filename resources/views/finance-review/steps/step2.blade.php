@extends('layouts.dashboard')
@section('title', 'Review Keuangan — Langkah 2')
@section('content')

@php
$steps = ['Profil & Pendapatan','Tempat Tinggal','Makan & Gaya Hidup','Transportasi','Tagihan & Langganan','Tanggungan','Cicilan'];
$pct   = round(2/7*100);
@endphp

<div style="max-width:600px;margin:0 auto">

    <div class="animate-in" style="margin-bottom:20px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <span style="font-size:12px;font-weight:600;color:var(--text-muted)">Langkah 2 dari 7 — {{ $steps[1] }}</span>
            <span style="font-size:12px;color:var(--text-dim)">{{ $pct }}%</span>
        </div>
        <div style="height:4px;border-radius:999px;background:var(--border);overflow:hidden">
            <div style="height:100%;border-radius:999px;background:var(--accent);width:{{ $pct }}%;transition:width .4s"></div>
        </div>
    </div>

    <div class="page-header animate-in animate-in-delay-1" style="margin-bottom:24px">
        <div style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--accent);margin-bottom:6px">Review Keuangan</div>
        <h2 style="margin-bottom:6px">Tempat Tinggal</h2>
        <p style="color:var(--text-muted);font-size:14px">Status hunian dan berapa yang kamu keluarkan setiap bulan untuk tempat tinggal.</p>
    </div>

    <form method="POST" action="{{ route('finance-review.step.save', 2) }}"
          class="animate-in animate-in-delay-2"
          x-data="step2Form()">
        @csrf

        <div class="card" style="padding:20px;margin-bottom:16px">
            <div style="margin-bottom:20px">
                <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:10px">
                    Status tempat tinggal <span style="color:var(--red)">*</span>
                </label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    @foreach([
                        ['kpr', 'fa-house', 'Nyicil (KPR)', 'Rumah sendiri, masih kredit'],
                        ['sewa', 'fa-building', 'Sewa / Kontrak', 'Kos, apartemen, atau rumah sewa'],
                        ['kontrak', 'fa-key', 'Kontrak Tahunan', 'Kontrak rumah per tahun'],
                        ['ortu', 'fa-people-roof', 'Tinggal di Ortu', 'Gratis, tidak ada biaya hunian'],
                        ['lainnya', 'fa-ellipsis', 'Lainnya', 'Dinas, mess, dll'],
                    ] as [$val, $icon, $label, $sub])
                    <label style="display:flex;align-items:flex-start;gap:10px;padding:12px;border-radius:var(--radius-sm);border:1px solid var(--border);cursor:pointer;transition:all .15s"
                           :style="status === '{{ $val }}' ? 'border-color:var(--accent);background:rgba(139,92,246,.08)' : ''">
                        <input type="radio" name="housing_status" value="{{ $val }}" x-model="status" style="display:none">
                        <i class="fa-solid {{ $icon }}" style="font-size:16px;margin-top:2px"
                           :style="status === '{{ $val }}' ? 'color:var(--accent)' : 'color:var(--text-dim)'"></i>
                        <div>
                            <div style="font-size:13px;font-weight:600;color:var(--text-primary)">{{ $label }}</div>
                            <div style="font-size:11px;color:var(--text-dim)">{{ $sub }}</div>
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>

            <div x-show="status && status !== 'ortu'" x-transition>
                <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:8px">
                    Biaya tempat tinggal per bulan
                </label>
                <div style="position:relative">
                    <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;font-weight:600">Rp</span>
                    <input type="text" x-model="formatted" @input="format($event.target.value)"
                           :placeholder="status === 'kpr' ? 'cicilan KPR per bulan' : 'biaya sewa per bulan'"
                           style="width:100%;padding:10px 14px 10px 42px;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--bg);color:var(--text-primary);font-size:14px;outline:none;transition:border-color .15s"
                           onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'">
                    <input type="hidden" name="housing_cost" :value="raw">
                </div>
            </div>

            <div x-show="status === 'ortu'" x-transition
                 style="padding:10px 12px;border-radius:var(--radius-sm);background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2)">
                <div style="font-size:13px;color:var(--green)"><i class="fa-solid fa-circle-check" style="margin-right:6px"></i>Biaya hunian Rp 0 — tidak dihitung dalam kalkulasi.</div>
            </div>
        </div>

        <div style="display:flex;gap:10px">
            <a href="{{ route('finance-review.step', 1) }}"
               style="padding:12px 20px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:14px;font-weight:500;color:var(--text-muted);text-decoration:none;white-space:nowrap">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <button type="submit" :disabled="!status"
                    style="flex:1;padding:12px;background:var(--accent);color:#fff;border:none;border-radius:var(--radius-sm);font-size:14px;font-weight:600;cursor:pointer;opacity:1;transition:opacity .15s"
                    :style="!status ? 'opacity:.5;cursor:not-allowed' : ''">
                Lanjut <i class="fa-solid fa-arrow-right" style="margin-left:4px"></i>
            </button>
        </div>
    </form>
</div>

<script>
function step2Form() {
    return {
        status: '{{ old('housing_status', $profile->housing_status ?? '') }}',
        raw: '{{ old('housing_cost', $profile->housing_cost ?? '') }}',
        formatted: '',
        init() {
            if (this.raw) this.formatted = parseInt(this.raw).toLocaleString('id-ID');
        },
        format(val) {
            const num = val.replace(/\D/g, '');
            this.raw = num;
            this.formatted = num ? parseInt(num).toLocaleString('id-ID') : '';
        },
    };
}
</script>
@endsection
