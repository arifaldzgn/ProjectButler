@extends('layouts.dashboard')
@section('title', 'Review Keuangan — Langkah 6')
@section('content')

@php $pct = round(6/7*100); @endphp

<div style="max-width:600px;margin:0 auto">

    <div class="animate-in" style="margin-bottom:20px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <span style="font-size:12px;font-weight:600;color:var(--text-muted)">Langkah 6 dari 7 — Tanggungan & Gaya Hidup</span>
            <span style="font-size:12px;color:var(--text-dim)">{{ $pct }}%</span>
        </div>
        <div style="height:4px;border-radius:999px;background:var(--border);overflow:hidden">
            <div style="height:100%;border-radius:999px;background:var(--accent);width:{{ $pct }}%;transition:width .4s"></div>
        </div>
    </div>

    <div class="page-header animate-in animate-in-delay-1" style="margin-bottom:24px">
        <div style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--accent);margin-bottom:6px">Review Keuangan</div>
        <h2 style="margin-bottom:6px">Tanggungan & Gaya Hidup</h2>
        <p style="color:var(--text-muted);font-size:14px">Semua opsional. Isi yang relevan dengan kondisi kamu.</p>
    </div>

    <form method="POST" action="{{ route('finance-review.step.save', 6) }}"
          class="animate-in animate-in-delay-2"
          x-data="step6Form()">
        @csrf

        <div class="card" style="padding:20px;margin-bottom:12px">

            {{-- Tanggungan --}}
            <div style="margin-bottom:20px">
                <label style="display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:8px">
                    Jumlah tanggungan <span style="color:var(--text-dim);font-weight:400">(opsional)</span>
                </label>
                <div style="font-size:12px;color:var(--text-dim);margin-bottom:8px">Orang yang bergantung secara finansial padamu (anak, orang tua, pasangan tidak bekerja).</div>
                <div style="display:flex;gap:8px">
                    @for($i = 0; $i <= 5; $i++)
                    <label style="flex:1;display:flex;flex-direction:column;align-items:center;padding:10px 4px;border-radius:var(--radius-sm);border:1px solid var(--border);cursor:pointer;transition:all .15s"
                           :style="tanggungan == {{ $i }} ? 'border-color:var(--accent);background:rgba(139,92,246,.08)' : ''">
                        <input type="radio" name="tanggungan_count" value="{{ $i }}" x-model="tanggungan" style="display:none">
                        <span style="font-size:16px;font-weight:700" :style="tanggungan == {{ $i }} ? 'color:var(--accent)' : 'color:var(--text-primary)'">{{ $i }}</span>
                        <span style="font-size:10px;color:var(--text-dim)">{{ $i === 0 ? 'Tidak ada' : ($i === 1 ? 'orang' : 'orang') }}</span>
                    </label>
                    @endfor
                    <label style="flex:1;display:flex;flex-direction:column;align-items:center;padding:10px 4px;border-radius:var(--radius-sm);border:1px solid var(--border);cursor:pointer;transition:all .15s"
                           :style="tanggungan == 6 ? 'border-color:var(--accent);background:rgba(139,92,246,.08)' : ''">
                        <input type="radio" name="tanggungan_count" value="6" x-model="tanggungan" style="display:none">
                        <span style="font-size:14px;font-weight:700" :style="tanggungan == 6 ? 'color:var(--accent)' : 'color:var(--text-primary)'">6+</span>
                        <span style="font-size:10px;color:var(--text-dim)">orang</span>
                    </label>
                </div>
            </div>

            {{-- Kiriman keluarga --}}
            @php
            $fields6 = [
                ['family_remittance', 'fa-heart', 'var(--red)',    'Kiriman ke keluarga per bulan', 'Orang tua, adik, saudara — rutin setiap bulan', 'Rp per bulan'],
                ['rokok_monthly',     'fa-smoking','var(--orange)', 'Rokok / Vape per bulan',       'Estimasi pengeluaran rokok atau vape',           'Rp per bulan'],
                ['gym_monthly',       'fa-dumbbell','var(--blue)',  'Gym / Olahraga per bulan',      'Membership gym, fitness, dll',                   'Rp per bulan'],
                ['asuransi_monthly',  'fa-shield-heart','var(--green)','Asuransi per bulan',         'Premi asuransi jiwa, kesehatan, dll',            'Rp per bulan'],
                ['mudik_annual',      'fa-bus-simple','var(--purple)','Mudik per tahun',             'Total biaya mudik 1 tahun — akan dibagi 12',     'Rp per tahun'],
            ];
            @endphp

            @foreach($fields6 as [$name, $icon, $color, $label, $sub, $ph])
            <div style="margin-bottom:16px">
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:4px">
                    <i class="fa-solid {{ $icon }}" style="color:{{ $color }};width:14px"></i>{{ $label }}
                    <span style="font-size:11px;font-weight:400;color:var(--text-dim)">opsional</span>
                </label>
                <div style="font-size:11px;color:var(--text-dim);margin-bottom:6px">{{ $sub }}</div>
                <div style="position:relative">
                    <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;font-weight:600">Rp</span>
                    <input type="text"
                           :value="fmt_{{ $name }}"
                           @input="setField('{{ $name }}', $event.target.value)"
                           placeholder="{{ $ph }}"
                           style="width:100%;padding:10px 14px 10px 42px;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--bg);color:var(--text-primary);font-size:14px;outline:none;transition:border-color .15s"
                           onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border)'">
                    <input type="hidden" name="{{ $name }}" :value="raw_{{ $name }}">
                </div>
            </div>
            @endforeach

        </div>

        <div style="display:flex;gap:10px">
            <a href="{{ route('finance-review.step', 5) }}"
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
function step6Form() {
    const fields = ['family_remittance','rokok_monthly','gym_monthly','asuransi_monthly','mudik_annual'];
    const saved = {
        family_remittance: '{{ $profile->family_remittance ?? '' }}',
        rokok_monthly: '{{ $profile->rokok_monthly ?? '' }}',
        gym_monthly: '{{ $profile->gym_monthly ?? '' }}',
        asuransi_monthly: '{{ $profile->asuransi_monthly ?? '' }}',
        mudik_annual: '{{ $profile->mudik_annual ?? '' }}',
    };

    const data = { tanggungan: {{ $profile->tanggungan_count ?? 0 }} };
    fields.forEach(f => {
        const v = saved[f];
        data[`raw_${f}`] = v;
        data[`fmt_${f}`] = v ? parseInt(v).toLocaleString('id-ID') : '';
    });

    data.setField = function(name, val) {
        const num = val.replace(/\D/g, '');
        this[`raw_${name}`] = num;
        this[`fmt_${name}`] = num ? parseInt(num).toLocaleString('id-ID') : '';
    };

    return data;
}
</script>
@endsection
