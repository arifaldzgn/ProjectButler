@extends('layouts.dashboard')
@section('title', 'Review Keuangan — Langkah 5')
@section('content')

@php $pct = round(5/7*100); @endphp

<div style="max-width:600px;margin:0 auto">

    <div class="animate-in" style="margin-bottom:20px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <span style="font-size:12px;font-weight:600;color:var(--text-muted)">Langkah 5 dari 7 — Tagihan & Langganan</span>
            <span style="font-size:12px;color:var(--text-dim)">{{ $pct }}%</span>
        </div>
        <div style="height:4px;border-radius:999px;background:var(--border);overflow:hidden">
            <div style="height:100%;border-radius:999px;background:var(--accent);width:{{ $pct }}%;transition:width .4s"></div>
        </div>
    </div>

    <div class="page-header animate-in animate-in-delay-1" style="margin-bottom:24px">
        <div style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--accent);margin-bottom:6px">Review Keuangan</div>
        <h2 style="margin-bottom:6px">Tagihan & Langganan</h2>
        <p style="color:var(--text-muted);font-size:14px">Tagihan bulanan dan langganan yang kamu bayar. Sudah terisi otomatis dari data Butler kamu — tinggal ceklis yang relevan.</p>
    </div>

    <form method="POST" action="{{ route('finance-review.step.save', 5) }}"
          class="animate-in animate-in-delay-2"
          x-data="step5Form({{ json_encode($billsList) }}, {{ json_encode($recurringList) }})">
        @csrf

        {{-- Bills --}}
        <div class="card" style="padding:20px;margin-bottom:12px">
            <div style="font-size:13px;font-weight:700;color:var(--text-secondary);margin-bottom:14px;display:flex;align-items:center;gap:8px">
                <i class="fa-solid fa-file-invoice-dollar" style="color:var(--blue)"></i> Tagihan Bulanan
                <span style="font-size:11px;font-weight:500;color:var(--text-dim);margin-left:auto" x-text="bills.filter(b=>b.included).length + ' dipilih'"></span>
            </div>

            <template x-if="bills.length === 0">
                <div style="padding:16px;text-align:center;color:var(--text-dim);font-size:13px">
                    <i class="fa-solid fa-inbox" style="font-size:24px;display:block;margin-bottom:8px"></i>
                    Belum ada tagihan. Setup dulu di <a href="{{ route('dashboard.index') }}" style="color:var(--accent)">halaman Bills</a>.
                </div>
            </template>

            <template x-for="(item, i) in bills" :key="item.id">
                <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)"
                     :style="i === bills.length-1 ? 'border-bottom:none' : ''">
                    <label style="display:flex;align-items:center;gap:0;cursor:pointer;flex-shrink:0">
                        <input type="checkbox" x-model="item.included" style="display:none">
                        <div style="width:18px;height:18px;border-radius:4px;border:2px solid;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0"
                             :style="item.included ? 'background:var(--accent);border-color:var(--accent)' : 'border-color:var(--border-strong)'">
                            <i class="fa-solid fa-check" style="font-size:10px;color:#fff" x-show="item.included"></i>
                        </div>
                    </label>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:13px;font-weight:500;color:var(--text-primary)" x-text="item.name"></div>
                    </div>
                    <input type="text" :value="parseInt(item.amount).toLocaleString('id-ID')"
                           @input="item.amount = parseInt($event.target.value.replace(/\D/g,'')) || 0"
                           style="width:110px;padding:5px 10px;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--bg);color:var(--text-primary);font-size:12px;text-align:right;outline:none"
                           onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'">
                </div>
            </template>
        </div>

        {{-- Recurring --}}
        <div class="card" style="padding:20px;margin-bottom:12px" x-show="recurring.length > 0">
            <div style="font-size:13px;font-weight:700;color:var(--text-secondary);margin-bottom:14px;display:flex;align-items:center;gap:8px">
                <i class="fa-solid fa-rotate" style="color:var(--purple)"></i> Pengeluaran Rutin Bulanan
                <span style="font-size:11px;font-weight:500;color:var(--text-dim);margin-left:auto" x-text="recurring.filter(r=>r.included).length + ' dipilih'"></span>
            </div>
            <template x-for="(item, i) in recurring" :key="item.id">
                <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)"
                     :style="i === recurring.length-1 ? 'border-bottom:none' : ''">
                    <label style="display:flex;align-items:center;gap:0;cursor:pointer;flex-shrink:0">
                        <input type="checkbox" x-model="item.included" style="display:none">
                        <div style="width:18px;height:18px;border-radius:4px;border:2px solid;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0"
                             :style="item.included ? 'background:var(--accent);border-color:var(--accent)' : 'border-color:var(--border-strong)'">
                            <i class="fa-solid fa-check" style="font-size:10px;color:#fff" x-show="item.included"></i>
                        </div>
                    </label>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:13px;font-weight:500;color:var(--text-primary)" x-text="item.name"></div>
                    </div>
                    <input type="text" :value="parseInt(item.amount).toLocaleString('id-ID')"
                           @input="item.amount = parseInt($event.target.value.replace(/\D/g,'')) || 0"
                           style="width:110px;padding:5px 10px;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--bg);color:var(--text-primary);font-size:12px;text-align:right;outline:none"
                           onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'">
                </div>
            </template>
        </div>

        {{-- Total --}}
        <div class="card" style="padding:14px 20px;margin-bottom:16px">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <span style="font-size:13px;color:var(--text-muted)">Total tagihan terpilih</span>
                <span style="font-size:16px;font-weight:700;color:var(--text-primary)" x-text="'Rp ' + totalTagihan.toLocaleString('id-ID')"></span>
            </div>
        </div>

        <input type="hidden" name="bills_json" :value="JSON.stringify(bills)">
        <input type="hidden" name="recurring_json" :value="JSON.stringify(recurring)">

        <div style="display:flex;gap:10px">
            <a href="{{ route('finance-review.step', 4) }}"
               style="padding:12px 20px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:14px;font-weight:500;color:var(--text-muted);text-decoration:none;white-space:nowrap">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <button type="submit"
                    style="flex:1;padding:12px;background:var(--accent);color:#fff;border:none;border-radius:var(--radius-sm);font-size:14px;font-weight:600;cursor:pointer">
                Lanjut <i class="fa-solid fa-arrow-right" style="margin-left:4px"></i>
            </button>
        </div>
    </form>
</div>

<script>
function step5Form(billsData, recurringData) {
    return {
        bills: billsData,
        recurring: recurringData,
        get totalTagihan() {
            const b = this.bills.filter(x=>x.included).reduce((s,x)=>s+(parseInt(x.amount)||0),0);
            const r = this.recurring.filter(x=>x.included).reduce((s,x)=>s+(parseInt(x.amount)||0),0);
            return b + r;
        },
    };
}
</script>
@endsection
