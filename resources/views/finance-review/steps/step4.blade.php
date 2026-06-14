@extends('layouts.dashboard')
@section('title', 'Review Keuangan — Langkah 4')
@section('content')

@php $pct = round(4/7*100); @endphp

<div style="max-width:600px;margin:0 auto">

    <div class="animate-in" style="margin-bottom:20px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <span style="font-size:12px;font-weight:600;color:var(--text-muted)">Langkah 4 dari 7 — Transportasi</span>
            <span style="font-size:12px;color:var(--text-dim)">{{ $pct }}%</span>
        </div>
        <div style="height:4px;border-radius:999px;background:var(--border);overflow:hidden">
            <div style="height:100%;border-radius:999px;background:var(--accent);width:{{ $pct }}%;transition:width .4s"></div>
        </div>
    </div>

    <div class="page-header animate-in animate-in-delay-1" style="margin-bottom:24px">
        <div style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--accent);margin-bottom:6px">Review Keuangan</div>
        <h2 style="margin-bottom:6px">Transportasi</h2>
        <p style="color:var(--text-muted);font-size:14px">Jenis transportasi yang kamu gunakan dan berapa estimasi biayanya per bulan.</p>
    </div>

    <form method="POST" action="{{ route('finance-review.step.save', 4) }}"
          class="animate-in animate-in-delay-2"
          x-data="step4Form()">
        @csrf

        <div class="card" style="padding:20px;margin-bottom:16px">

            <div style="margin-bottom:20px">
                <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:10px">
                    Jenis transportasi utama
                </label>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
                    @foreach([
                        ['motor', 'fa-motorcycle', 'Motor'],
                        ['mobil', 'fa-car', 'Mobil'],
                        ['krl', 'fa-train', 'KRL/MRT/LRT'],
                        ['ojek', 'fa-person-biking', 'Ojek Online'],
                        ['angkot', 'fa-bus', 'Angkot/Bus'],
                        ['mixed', 'fa-shuffle', 'Campuran'],
                    ] as [$val, $icon, $label])
                    <label style="display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 8px;border-radius:var(--radius-sm);border:1px solid var(--border);cursor:pointer;transition:all .15s;text-align:center"
                           :style="transport === '{{ $val }}' ? 'border-color:var(--accent);background:rgba(139,92,246,.08)' : ''">
                        <input type="radio" name="transport_type" value="{{ $val }}" x-model="transport" style="display:none">
                        <i class="fa-solid {{ $icon }}" style="font-size:18px"
                           :style="transport === '{{ $val }}' ? 'color:var(--accent)' : 'color:var(--text-dim)'"></i>
                        <span style="font-size:11px;font-weight:600;color:var(--text-secondary)">{{ $label }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            <div style="margin-bottom:20px">
                <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:6px">
                    Jarak tempuh harian (sekali jalan)
                </label>
                <div style="display:flex;align-items:center;gap:10px">
                    <input type="number" name="commute_km_daily" x-model="km" min="0" max="200"
                           placeholder="0"
                           style="width:100px;padding:10px 14px;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--bg);color:var(--text-primary);font-size:14px;outline:none;transition:border-color .15s;text-align:center"
                           onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'">
                    <span style="font-size:14px;color:var(--text-muted)">km / sekali jalan</span>
                    <span style="font-size:12px;color:var(--text-dim)" x-show="km > 0">≈ <strong x-text="(km * 2) + ' km'"></strong>/hari</span>
                </div>
            </div>

            <div>
                <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:6px">
                    Estimasi biaya transport per bulan <span style="color:var(--red)">*</span>
                </label>
                <div style="font-size:12px;color:var(--text-dim);margin-bottom:8px">Bensin, tol, parkir, atau saldo ojek — semua dihitung.</div>
                <div style="position:relative">
                    <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;font-weight:600">Rp</span>
                    <input type="text" x-model="formatted" @input="format($event.target.value)"
                           placeholder="estimasi per bulan"
                           required
                           style="width:100%;padding:10px 14px 10px 42px;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--bg);color:var(--text-primary);font-size:14px;outline:none;transition:border-color .15s"
                           onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'">
                    <input type="hidden" name="transport_monthly" :value="raw">
                </div>
            </div>
        </div>

        <div style="display:flex;gap:10px">
            <a href="{{ route('finance-review.step', 3) }}"
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
function step4Form() {
    return {
        transport: '{{ old('transport_type', $profile->transport_type ?? '') }}',
        km: {{ old('commute_km_daily', $profile->commute_km_daily ?? 0) }},
        raw: '{{ old('transport_monthly', $profile->transport_monthly ?? '') }}',
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
