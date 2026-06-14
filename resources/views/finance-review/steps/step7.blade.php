@extends('layouts.dashboard')
@section('title', 'Review Keuangan — Langkah 7')
@section('content')

@php $pct = 100; @endphp

<div style="max-width:600px;margin:0 auto">

    <div class="animate-in" style="margin-bottom:20px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <span style="font-size:12px;font-weight:600;color:var(--text-muted)">Langkah 7 dari 7 — Cicilan & Hutang</span>
            <span style="font-size:12px;color:var(--accent);font-weight:700">Langkah terakhir!</span>
        </div>
        <div style="height:4px;border-radius:999px;background:var(--border);overflow:hidden">
            <div style="height:100%;border-radius:999px;background:var(--accent);width:100%;transition:width .4s"></div>
        </div>
    </div>

    <div class="page-header animate-in animate-in-delay-1" style="margin-bottom:24px">
        <div style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--accent);margin-bottom:6px">Review Keuangan</div>
        <h2 style="margin-bottom:6px">Cicilan & Hutang</h2>
        <p style="color:var(--text-muted);font-size:14px">Cicilan aktif yang kamu bayar tiap bulan. Otomatis terisi dari data Butler kamu.</p>
    </div>

    <form method="POST" action="{{ route('finance-review.step.save', 7) }}"
          class="animate-in animate-in-delay-2"
          x-data="step7Form({{ json_encode($debtsList) }})">
        @csrf

        @if(count($debtsList) === 0)
        <div class="card" style="padding:24px;text-align:center;margin-bottom:16px">
            <i class="fa-solid fa-circle-check" style="font-size:32px;color:var(--green);margin-bottom:12px;display:block"></i>
            <div style="font-size:15px;font-weight:600;color:var(--text-primary);margin-bottom:6px">Tidak ada cicilan aktif</div>
            <div style="font-size:13px;color:var(--text-muted);margin-bottom:16px">Bagus! Tidak ada beban cicilan berarti lebih banyak ruang untuk nabung.</div>
            <a href="{{ route('dashboard.debts') }}" style="font-size:12px;color:var(--accent)">
                Punya cicilan yang belum di-setup? Tambah di sini
            </a>
        </div>
        @else
        <div class="card" style="padding:20px;margin-bottom:12px">
            <div style="font-size:13px;font-weight:700;color:var(--text-secondary);margin-bottom:14px;display:flex;align-items:center;gap:8px">
                <i class="fa-solid fa-credit-card" style="color:var(--red)"></i> Cicilan Aktif
                <span style="font-size:11px;font-weight:500;color:var(--text-dim);margin-left:auto" x-text="debts.filter(d=>d.included).length + ' dipilih'"></span>
            </div>

            <template x-for="(item, i) in debts" :key="item.id">
                <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)"
                     :style="i === debts.length-1 ? 'border-bottom:none' : ''">
                    <label style="display:flex;align-items:center;cursor:pointer;flex-shrink:0">
                        <input type="checkbox" x-model="item.included" style="display:none">
                        <div style="width:18px;height:18px;border-radius:4px;border:2px solid;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0"
                             :style="item.included ? 'background:var(--accent);border-color:var(--accent)' : 'border-color:var(--border-strong)'">
                            <i class="fa-solid fa-check" style="font-size:10px;color:#fff" x-show="item.included"></i>
                        </div>
                    </label>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:13px;font-weight:500;color:var(--text-primary)" x-text="item.name"></div>
                        <div style="font-size:11px;color:var(--text-dim)">per bulan</div>
                    </div>
                    <input type="text" :value="parseInt(item.amount).toLocaleString('id-ID')"
                           @input="item.amount = parseInt($event.target.value.replace(/\D/g,'')) || 0"
                           style="width:110px;padding:5px 10px;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--bg);color:var(--text-primary);font-size:12px;text-align:right;outline:none"
                           onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'">
                </div>
            </template>
        </div>

        <div class="card" style="padding:14px 20px;margin-bottom:16px">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <span style="font-size:13px;color:var(--text-muted)">Total cicilan terpilih</span>
                <span style="font-size:16px;font-weight:700;color:var(--text-primary)" x-text="'Rp ' + totalCicilan.toLocaleString('id-ID')"></span>
            </div>
        </div>
        @endif

        <input type="hidden" name="debts_json" :value="JSON.stringify(debts)">

        <div style="display:flex;gap:10px">
            <a href="{{ route('finance-review.step', 6) }}"
               style="padding:12px 20px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:14px;font-weight:500;color:var(--text-muted);text-decoration:none;white-space:nowrap">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <button type="submit"
                    style="flex:1;padding:12px;background:var(--green);color:#fff;border:none;border-radius:var(--radius-sm);font-size:14px;font-weight:700;cursor:pointer">
                <i class="fa-solid fa-wand-magic-sparkles" style="margin-right:6px"></i>Lihat Hasil Analisis
            </button>
        </div>
    </form>
</div>

<script>
function step7Form(debtsData) {
    return {
        debts: debtsData,
        get totalCicilan() {
            return this.debts.filter(d=>d.included).reduce((s,d)=>s+(parseInt(d.amount)||0),0);
        },
    };
}
</script>
@endsection
