@extends('layouts.dashboard')
@section('title', 'Review Keuangan — Langkah 1')
@section('content')

@php
$steps = ['Profil & Pendapatan','Tempat Tinggal','Makan & Gaya Hidup','Transportasi','Tagihan & Langganan','Tanggungan','Cicilan'];
$pct   = round(1/7*100);
@endphp

<div style="max-width:600px;margin:0 auto">

    <div class="animate-in" style="margin-bottom:20px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <span style="font-size:12px;font-weight:600;color:var(--text-muted)">Langkah 1 dari 7 — {{ $steps[0] }}</span>
            <span style="font-size:12px;color:var(--text-dim)">{{ $pct }}%</span>
        </div>
        <div style="height:4px;border-radius:999px;background:var(--border);overflow:hidden">
            <div style="height:100%;border-radius:999px;background:var(--accent);width:{{ $pct }}%;transition:width .4s"></div>
        </div>
    </div>

    <div class="page-header animate-in animate-in-delay-1" style="margin-bottom:24px">
        <div style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--accent);margin-bottom:6px">Review Keuangan</div>
        <h2 style="margin-bottom:6px">Profil & Pendapatan</h2>
        <p style="color:var(--text-muted);font-size:14px">Kita mulai dari dua hal dasar: kamu tinggal di mana dan berapa gaji bersihmu setiap bulan.</p>
    </div>

    <form method="POST" action="{{ route('finance-review.step.save', 1) }}"
          class="animate-in animate-in-delay-2"
          x-data="step1Form()">
        @csrf

        <div class="card" style="padding:20px;margin-bottom:16px">
            <div style="margin-bottom:20px">
                <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:8px">
                    Kota domisili kamu <span style="color:var(--red)">*</span>
                </label>
                <input type="text" name="domisili" list="kota-list"
                       value="{{ old('domisili', $profile->domisili) }}"
                       placeholder="contoh: Batam, Jakarta, Surabaya..."
                       required
                       style="width:100%;padding:10px 14px;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--bg);color:var(--text-primary);font-size:14px;outline:none;transition:border-color .15s"
                       onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'">
                <datalist id="kota-list">
                    <option value="Jakarta">
                    <option value="Surabaya">
                    <option value="Bandung">
                    <option value="Batam">
                    <option value="Bekasi">
                    <option value="Depok">
                    <option value="Tangerang">
                    <option value="Semarang">
                    <option value="Yogyakarta">
                    <option value="Solo">
                    <option value="Medan">
                    <option value="Makassar">
                    <option value="Palembang">
                    <option value="Bogor">
                    <option value="Malang">
                </datalist>
            </div>

            <div>
                <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:8px">
                    Gaji bersih per bulan (setelah pajak) <span style="color:var(--red)">*</span>
                </label>
                <div style="position:relative">
                    <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;font-weight:600">Rp</span>
                    <input type="text" id="gaji_display" x-model="formatted"
                           @input="format($event.target.value)"
                           placeholder="contoh: 6.000.000"
                           required
                           style="width:100%;padding:10px 14px 10px 42px;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--bg);color:var(--text-primary);font-size:14px;outline:none;transition:border-color .15s"
                           onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'">
                    <input type="hidden" name="gaji_bersih" :value="raw">
                </div>
                @if($incomeHint)
                <div style="font-size:12px;color:var(--text-dim);margin-top:5px">
                    <i class="fa-solid fa-lightbulb" style="color:var(--yellow)"></i>
                    Terakhir tercatat: Rp {{ number_format($incomeHint, 0, ',', '.') }}
                    <button type="button" @click="useHint({{ $incomeHint }})"
                            style="background:none;border:none;color:var(--accent);font-size:12px;cursor:pointer;padding:0;margin-left:4px">Gunakan ini</button>
                </div>
                @endif
            </div>
        </div>

        <div style="display:flex;gap:10px">
            <button type="submit"
                    style="flex:1;padding:12px;background:var(--accent);color:#fff;border:none;border-radius:var(--radius-sm);font-size:14px;font-weight:600;cursor:pointer">
                Lanjut <i class="fa-solid fa-arrow-right" style="margin-left:4px"></i>
            </button>
        </div>
    </form>
</div>

<script>
function step1Form() {
    return {
        raw: '{{ old('gaji_bersih', $profile->gaji_bersih ?? '') }}',
        formatted: '',
        init() {
            if (this.raw) this.formatted = parseInt(this.raw).toLocaleString('id-ID');
        },
        format(val) {
            const num = val.replace(/\D/g, '');
            this.raw = num;
            this.formatted = num ? parseInt(num).toLocaleString('id-ID') : '';
        },
        useHint(val) {
            this.raw = String(val);
            this.formatted = parseInt(val).toLocaleString('id-ID');
        },
    };
}
</script>
@endsection
