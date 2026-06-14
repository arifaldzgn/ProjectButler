@extends('layouts.dashboard')
@section('title', 'Review Keuangan — Langkah 3')
@section('content')

@php $pct = round(3/7*100); @endphp

<div style="max-width:600px;margin:0 auto">

    <div class="animate-in" style="margin-bottom:20px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <span style="font-size:12px;font-weight:600;color:var(--text-muted)">Langkah 3 dari 7 — Makan & Gaya Hidup</span>
            <span style="font-size:12px;color:var(--text-dim)">{{ $pct }}%</span>
        </div>
        <div style="height:4px;border-radius:999px;background:var(--border);overflow:hidden">
            <div style="height:100%;border-radius:999px;background:var(--accent);width:{{ $pct }}%;transition:width .4s"></div>
        </div>
    </div>

    <div class="page-header animate-in animate-in-delay-1" style="margin-bottom:24px">
        <div style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--accent);margin-bottom:6px">Review Keuangan</div>
        <h2 style="margin-bottom:6px">Kebutuhan Makan</h2>
        <p style="color:var(--text-muted);font-size:14px">Perkiraan pengeluaran makan harian dan kebiasaan nongkrong / dine-out kamu.</p>
    </div>

    <form method="POST" action="{{ route('finance-review.step.save', 3) }}"
          class="animate-in animate-in-delay-2"
          x-data="step3Form()">
        @csrf

        <div class="card" style="padding:20px;margin-bottom:16px">

            <div style="margin-bottom:24px">
                <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:4px">
                    Persentase masak sendiri vs beli
                </label>
                <div style="font-size:12px;color:var(--text-dim);margin-bottom:12px">Geser slider untuk menyesuaikan kebiasaanmu.</div>
                <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                    <span style="font-size:13px;color:var(--text-muted)">Masak: <strong x-text="cook + '%'"></strong></span>
                    <span style="font-size:13px;color:var(--text-muted)">Beli: <strong x-text="(100 - cook) + '%'"></strong></span>
                </div>
                <input type="range" name="cook_percentage" x-model="cook" min="0" max="100" step="5"
                       style="width:100%;accent-color:var(--accent)">
                <input type="hidden" name="cook_percentage" :value="cook">
                <div style="margin-top:8px;padding:10px 12px;border-radius:var(--radius-sm);background:var(--bg);border:1px solid var(--border);font-size:12px;color:var(--text-muted)" x-show="cook >= 70">
                    <i class="fa-solid fa-fire-burner" style="color:var(--green);margin-right:5px"></i>Masak sendiri bisa hemat 30-50% vs beli jadi. Bagus!
                </div>
            </div>

            <div style="margin-bottom:24px">
                <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:6px">
                    Estimasi biaya makan dasar per bulan <span style="color:var(--red)">*</span>
                </label>
                <div style="font-size:12px;color:var(--text-dim);margin-bottom:8px">Tidak termasuk dine-out. Masak sendiri: estimasi belanja bahan. Beli: rata-rata 2–3x makan per hari.</div>
                <div style="position:relative">
                    <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;font-weight:600">Rp</span>
                    <input type="text" x-model="foodFormatted" @input="formatFood($event.target.value)"
                           placeholder="estimasi per bulan"
                           required
                           style="width:100%;padding:10px 14px 10px 42px;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--bg);color:var(--text-primary);font-size:14px;outline:none;transition:border-color .15s"
                           onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'">
                    <input type="hidden" name="food_base_monthly" :value="foodRaw">
                </div>
            </div>

            <div>
                <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:10px">
                    Frekuensi nongkrong / dine-out per minggu
                </label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    @foreach([
                        ['jarang', 'Jarang / Tidak Pernah', '+ Rp 0/bln', 'var(--text-dim)'],
                        ['1-2x', '1–2x per minggu', '+ Rp 200.000/bln', 'var(--blue)'],
                        ['3-4x', '3–4x per minggu', '+ Rp 430.000/bln', 'var(--orange)'],
                        ['setiap-hari', 'Hampir Setiap Hari', '+ Rp 800.000/bln', 'var(--red)'],
                    ] as [$val, $label, $est, $color])
                    <label style="display:flex;flex-direction:column;gap:2px;padding:12px;border-radius:var(--radius-sm);border:1px solid var(--border);cursor:pointer;transition:all .15s"
                           :style="hangout === '{{ $val }}' ? 'border-color:var(--accent);background:rgba(139,92,246,.08)' : ''">
                        <input type="radio" name="hangout_frequency" value="{{ $val }}" x-model="hangout" style="display:none">
                        <span style="font-size:13px;font-weight:600;color:var(--text-primary)">{{ $label }}</span>
                        <span style="font-size:11px;font-weight:600" style="color:{{ $color }}">{{ $est }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
        </div>

        <div style="display:flex;gap:10px">
            <a href="{{ route('finance-review.step', 2) }}"
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
function step3Form() {
    return {
        cook: {{ old('cook_percentage', $profile->cook_percentage ?? 50) }},
        hangout: '{{ old('hangout_frequency', $profile->hangout_frequency ?? 'jarang') }}',
        foodRaw: '{{ old('food_base_monthly', $profile->food_base_monthly ?? '') }}',
        foodFormatted: '',
        init() {
            if (this.foodRaw) this.foodFormatted = parseInt(this.foodRaw).toLocaleString('id-ID');
        },
        formatFood(val) {
            const num = val.replace(/\D/g, '');
            this.foodRaw = num;
            this.foodFormatted = num ? parseInt(num).toLocaleString('id-ID') : '';
        },
    };
}
</script>
@endsection
