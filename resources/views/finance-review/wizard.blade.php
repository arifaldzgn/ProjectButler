@extends('layouts.dashboard')
@section('title', 'Review Keuangan')
@section('content')

@php
$alpineData = [
    'startStep'    => $startStep,
    'lastDone'     => $profile->last_completed_step,
    'profile'      => [
        'domisili'            => $profile->domisili ?? '',
        'gaji_bersih'         => (int) ($profile->gaji_bersih ?? 0),
        'housing_status'      => $profile->housing_status ?? '',
        'housing_cost'        => (int) ($profile->housing_cost ?? 0),
        'cook_percentage'     => (int) ($profile->cook_percentage ?? 50),
        'food_base_monthly'   => (int) ($profile->food_base_monthly ?? 0),
        'hangout_frequency'   => $profile->hangout_frequency ?? '',
        'transport_type'      => $profile->transport_type ?? '',
        'commute_km_daily'    => (int) ($profile->commute_km_daily ?? 0),
        'transport_monthly'   => (int) ($profile->transport_monthly ?? 0),
        'tanggungan_snapshot' => $profile->tanggungan_snapshot ?? [],
        'rokok_monthly'       => (int) ($profile->rokok_monthly ?? 0),
        'gym_monthly'         => (int) ($profile->gym_monthly ?? 0),
        'asuransi_monthly'    => (int) ($profile->asuransi_monthly ?? 0),
        'mudik_annual'        => (int) ($profile->mudik_annual ?? 0),
    ],
    'incomeHint'   => $incomeHint ?? null,
    'billsList'    => array_values($billsList),
    'recurringList'=> array_values($recurringList),
    'debtsList'    => array_values($debtsList),
];
@endphp

<style>
[x-cloak] { display: none !important; }

/* ── Wizard shell ──────────────────────────────────────────────── */
.wizard-wrap { max-width: 560px; margin: 0 auto; padding-bottom: 32px; }

/* ── Step trail ────────────────────────────────────────────────── */
.step-trail { display: flex; align-items: center; justify-content: center; gap: 0; margin-bottom: 24px; }
.step-dot {
    width: 28px; height: 28px; border-radius: 50%;
    border: 2px solid var(--border-strong);
    display: flex; align-items: center; justify-content: center;
    font-size: 10px; font-weight: 700; flex-shrink: 0;
    background: var(--bg-elevated); color: var(--text-dim);
    transition: background .25s, border-color .25s, box-shadow .25s;
}
.step-dot.done  { background: var(--accent); border-color: var(--accent); color: #fff; }
.step-dot.active { background: var(--accent); border-color: var(--accent); color: #fff;
    box-shadow: 0 0 0 5px rgba(139,92,246,.15); }
.step-line {
    width: 20px; height: 2px; flex-shrink: 0;
    background: var(--border); transition: background .25s;
}
.step-line.done { background: var(--accent); }

/* ── Step badge + sublabel ──────────────────────────────────────── */
.step-badge {
    display: inline-flex; align-items: center;
    padding: 3px 12px; border-radius: 999px;
    background: rgba(139,92,246,.12);
    font-size: 11px; font-weight: 700; letter-spacing: .07em;
    text-transform: uppercase; color: var(--accent);
    margin-bottom: 4px;
}

/* ── Selection tiles ────────────────────────────────────────────── */
.sel-tile {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 13px; border-radius: var(--radius-sm);
    border: 2px solid var(--border); cursor: pointer;
    transition: border-color .15s, background .15s;
    background: var(--bg-elevated);
}
.sel-tile.active { border-color: var(--accent); background: rgba(139,92,246,.07); }

.sel-tile-icon {
    width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: var(--bg-hover); transition: background .15s;
    font-size: 13px; color: var(--text-dim);
}
.sel-tile.active .sel-tile-icon { background: var(--accent); color: #fff; }

/* Column tile (transport icons) */
.sel-col {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    padding: 14px 8px; border-radius: var(--radius-sm);
    border: 2px solid var(--border); cursor: pointer;
    transition: border-color .15s, background .15s;
    background: var(--bg-elevated); text-align: center;
}
.sel-col.active { border-color: var(--accent); background: rgba(139,92,246,.07); }
.sel-col i { font-size: 20px; color: var(--text-dim); transition: color .15s; }
.sel-col.active i { color: var(--accent); }
.sel-col span { font-size: 11px; font-weight: 700; color: var(--text-secondary); }

/* ── Checkbox toggle row ────────────────────────────────────────── */
.check-row {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 0; border-bottom: 1px solid var(--border);
}
.check-row:last-child { border-bottom: none; }
.check-box {
    width: 20px; height: 20px; border-radius: 5px; flex-shrink: 0;
    border: 2px solid var(--border-strong);
    display: flex; align-items: center; justify-content: center;
    transition: background .15s, border-color .15s; cursor: pointer;
}
.check-box.checked { background: var(--accent); border-color: var(--accent); }

/* ── Amount input with Rp prefix ────────────────────────────────── */
.amt-wrap { position: relative; }
.amt-prefix {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    font-size: 13px; font-weight: 600; color: var(--text-muted);
    pointer-events: none;
}
.amt-wrap .field-input { padding-left: 40px; }

/* ── Mode toggle (per hari / per bulan) ─────────────────────────── */
.mode-toggle {
    display: inline-flex; border: 1px solid var(--border);
    border-radius: var(--radius-sm); overflow: hidden; font-size: 11px; font-weight: 600;
}
.mode-btn {
    padding: 5px 12px; border: none; cursor: pointer;
    background: var(--bg-elevated); color: var(--text-muted);
    transition: background .15s, color .15s; font-family: inherit; font-size: 11px; font-weight: 600;
}
.mode-btn.active { background: var(--accent); color: #fff; }

/* ── Wizard nav buttons ─────────────────────────────────────────── */
.wiz-back {
    padding: 12px 18px; border: 1px solid var(--border);
    border-radius: var(--radius-sm); background: var(--bg-elevated);
    color: var(--text-muted); cursor: pointer; font-family: inherit;
    font-size: 14px; flex-shrink: 0; transition: border-color .15s, color .15s;
}
.wiz-back:hover { border-color: var(--border-strong); color: var(--text-primary); }

.wiz-next {
    flex: 1; padding: 13px; border: none;
    border-radius: var(--radius-sm); background: var(--accent);
    font-family: inherit; font-size: 14px; font-weight: 700; color: #fff;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    gap: 8px; transition: opacity .15s;
}
.wiz-next:disabled { opacity: .55; cursor: not-allowed; }
.wiz-next.finish { background: var(--green); }

/* ── Section sub-header inside card ────────────────────────────── */
.card-section-head {
    display: flex; align-items: center; gap: 8px; margin-bottom: 14px;
}
.card-section-icon {
    width: 28px; height: 28px; border-radius: 8px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 12px;
}
</style>

<div x-data='wizardApp(@json($alpineData))' x-cloak class="wizard-wrap animate-in">

    {{-- ── Step Trail ──────────────────────────────────────────────── --}}
    <div class="step-trail">
        @for($i = 1; $i <= 7; $i++)
            <div class="step-dot"
                 :class="{{ $i }} < step ? 'done' : ({{ $i }} === step ? 'active' : '')">
                <i class="fa-solid fa-check" style="font-size:9px" x-show="{{ $i }} < step"></i>
                <span x-show="{{ $i }} >= step">{{ $i }}</span>
            </div>
            @if($i < 7)
            <div class="step-line" :class="{{ $i }} < step ? 'done' : ''"></div>
            @endif
        @endfor
    </div>

    {{-- ── Step Label ──────────────────────────────────────────────── --}}
    <div style="text-align:center;margin-bottom:24px">
        <div class="step-badge" x-text="stepLabels[step-1]"></div>
        <div style="font-size:12px;color:var(--text-dim)" x-text="'Langkah ' + step + ' dari 7'"></div>
    </div>

    {{-- ── Error Banner ─────────────────────────────────────────────── --}}
    <div x-show="error" x-transition
         style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:var(--radius-sm);background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);margin-bottom:16px;font-size:13px;color:var(--red)">
        <i class="fa-solid fa-circle-exclamation" style="flex-shrink:0"></i>
        <span x-text="error" style="flex:1"></span>
        <button @click="error=null" style="background:none;border:none;color:var(--red);cursor:pointer;font-size:18px;line-height:1;padding:0">&times;</button>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         STEP 1 — Profil & Pendapatan
    ══════════════════════════════════════════════════════════════════ --}}
    <div x-show="step===1" x-transition>
        <div class="page-header">
            <h2>Profil & Pendapatan</h2>
            <p>Dua hal dasar: kamu tinggal di mana, dan berapa gaji bersihmu.</p>
        </div>

        <div class="card">
            <div class="field">
                <label class="field-label">
                    Kota domisili <span style="color:var(--red)">*</span>
                </label>
                <div style="position:relative">
                    <i class="fa-solid fa-location-dot"
                       style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-dim);font-size:12px;pointer-events:none"></i>
                    <input type="text" x-model="domisili" list="kota-list"
                           class="field-input" style="padding-left:36px"
                           placeholder="mis. Batam, Jakarta, Bandung...">
                </div>
                <datalist id="kota-list">
                    @foreach(['Jakarta','Surabaya','Bandung','Batam','Bekasi','Depok','Tangerang','Semarang','Yogyakarta','Solo','Medan','Makassar','Palembang','Bogor','Malang'] as $k)
                    <option value="{{ $k }}">
                    @endforeach
                </datalist>
            </div>

            <div class="field" style="margin-bottom:0">
                <label class="field-label">
                    Gaji bersih per bulan
                    <span style="font-weight:400;color:var(--text-dim)">(setelah pajak)</span>
                    <span style="color:var(--red)">*</span>
                </label>
                <div class="amt-wrap">
                    <span class="amt-prefix">Rp</span>
                    <input type="text" :value="gajiFmt"
                           @input="fmtCurrency('gajiRaw','gajiFmt',$event.target.value)"
                           class="field-input" placeholder="6.000.000">
                </div>
                @if($incomeHint)
                <div style="margin-top:8px;font-size:12px;color:var(--text-dim);display:flex;align-items:center;gap:6px">
                    <i class="fa-solid fa-lightbulb" style="color:var(--yellow)"></i>
                    Terakhir tercatat: Rp {{ number_format($incomeHint, 0, ',', '.') }}
                    <button type="button" @click="useIncomeHint({{ $incomeHint }})"
                            style="background:none;border:none;color:var(--accent);font-size:12px;font-weight:600;cursor:pointer;padding:0">
                        Gunakan ini
                    </button>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         STEP 2 — Tempat Tinggal
    ══════════════════════════════════════════════════════════════════ --}}
    <div x-show="step===2" x-transition>
        <div class="page-header">
            <h2>Tempat Tinggal</h2>
            <p>Status hunian dan berapa yang kamu bayar setiap bulan.</p>
        </div>

        <div class="card">
            <div class="field">
                <label class="field-label">
                    Status tempat tinggal <span style="color:var(--red)">*</span>
                </label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    @foreach([
                        ['kpr',     'fa-house-chimney', 'Nyicil (KPR)',    'Cicilan rumah kredit'],
                        ['sewa',    'fa-building',       'Sewa / Kos',      'Kos atau apartemen'],
                        ['kontrak', 'fa-key',            'Kontrak Tahunan', 'Kontrak rumah per tahun'],
                        ['ortu',    'fa-people-roof',    'Tinggal di Ortu', 'Tidak ada biaya hunian'],
                        ['lainnya', 'fa-ellipsis',       'Lainnya',         'Dinas, mess, dll'],
                    ] as [$val, $icon, $lbl, $sub])
                    <label class="sel-tile" :class="housingStatus==='{{ $val }}' ? 'active' : ''">
                        <input type="radio" value="{{ $val }}" x-model="housingStatus" style="display:none">
                        <div class="sel-tile-icon">
                            <i class="fa-solid {{ $icon }}"></i>
                        </div>
                        <div>
                            <div style="font-size:13px;font-weight:600;color:var(--text-primary)">{{ $lbl }}</div>
                            <div style="font-size:11px;color:var(--text-dim);margin-top:1px">{{ $sub }}</div>
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>

            <div class="field" style="margin-bottom:0" x-show="housingStatus && housingStatus !== 'ortu'" x-transition>
                <label class="field-label">Biaya tempat tinggal per bulan</label>
                <div class="amt-wrap">
                    <span class="amt-prefix">Rp</span>
                    <input type="text" :value="housingFmt"
                           @input="fmtCurrency('housingRaw','housingFmt',$event.target.value)"
                           class="field-input"
                           :placeholder="housingStatus==='kpr' ? 'cicilan KPR per bulan' : 'biaya sewa per bulan'">
                </div>
            </div>

            <div x-show="housingStatus==='ortu'" x-transition
                 style="padding:11px 14px;border-radius:var(--radius-sm);background:rgba(16,185,129,.07);border:1px solid rgba(16,185,129,.18)">
                <div style="font-size:13px;color:var(--green);display:flex;align-items:center;gap:8px">
                    <i class="fa-solid fa-circle-check"></i>
                    Biaya hunian Rp 0 — tidak dihitung dalam kalkulasi.
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         STEP 3 — Makan & Gaya Hidup
    ══════════════════════════════════════════════════════════════════ --}}
    <div x-show="step===3" x-transition>
        <div class="page-header">
            <h2>Kebutuhan Makan</h2>
            <p>Estimasi pengeluaran makan dan kebiasaan dine-out kamu.</p>
        </div>

        <div class="card">
            {{-- Cook slider --}}
            <div class="field">
                <label class="field-label">Seberapa sering masak sendiri?</label>
                <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                    <span style="font-size:12px;color:var(--text-muted)">Beli semua</span>
                    <span style="font-size:13px;font-weight:700;color:var(--accent)" x-text="cookPct + '% masak sendiri'"></span>
                    <span style="font-size:12px;color:var(--text-muted)">Masak semua</span>
                </div>
                <input type="range" x-model="cookPct" min="0" max="100" step="5"
                       style="width:100%;accent-color:var(--accent)">
                <div x-show="cookPct >= 60" x-transition
                     style="margin-top:10px;padding:9px 12px;border-radius:var(--radius-sm);background:rgba(16,185,129,.07);border:1px solid rgba(16,185,129,.18);font-size:12px;color:var(--green);display:flex;align-items:center;gap:6px">
                    <i class="fa-solid fa-fire-burner"></i> Masak sendiri bisa hemat 30–50% vs beli jadi!
                </div>
            </div>

            {{-- Food cost — daily/monthly toggle --}}
            <div class="field">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                    <label class="field-label" style="margin-bottom:0">
                        Estimasi biaya makan dasar <span style="color:var(--red)">*</span>
                    </label>
                    <div class="mode-toggle">
                        <button type="button" @click="foodMode='daily'"
                                class="mode-btn" :class="foodMode==='daily' ? 'active' : ''">Per Hari</button>
                        <button type="button" @click="foodMode='monthly'"
                                class="mode-btn" :class="foodMode==='monthly' ? 'active' : ''">Per Bulan</button>
                    </div>
                </div>

                <div x-show="foodMode==='daily'">
                    <div style="font-size:11px;color:var(--text-dim);margin-bottom:6px">Total untuk 1 orang per hari — akan dikalikan 30 hari.</div>
                    <div class="amt-wrap">
                        <span class="amt-prefix">Rp</span>
                        <input type="text" :value="foodDailyFmt"
                               @input="setFoodDaily($event.target.value)"
                               class="field-input" placeholder="mis. 40.000 / hari">
                    </div>
                    <div x-show="foodDailyRaw"
                         style="margin-top:8px;padding:9px 12px;border-radius:var(--radius-sm);background:var(--bg-elevated);border:1px solid var(--border);font-size:12px;color:var(--text-muted);display:flex;justify-content:space-between;align-items:center">
                        <span>Estimasi per bulan (× 30)</span>
                        <strong style="color:var(--text-primary)" x-text="'Rp ' + ((parseInt(foodDailyRaw)||0)*30).toLocaleString('id-ID')"></strong>
                    </div>
                </div>

                <div x-show="foodMode==='monthly'">
                    <div style="font-size:11px;color:var(--text-dim);margin-bottom:6px">Total biaya makan satu bulan penuh (tidak termasuk nongkrong).</div>
                    <div class="amt-wrap">
                        <span class="amt-prefix">Rp</span>
                        <input type="text" :value="foodMonthlyFmt"
                               @input="setFoodMonthly($event.target.value)"
                               class="field-input" placeholder="estimasi per bulan">
                    </div>
                    <div x-show="foodMonthlyRaw" style="margin-top:6px;font-size:12px;color:var(--text-dim)">
                        = Rp <span x-text="Math.round((parseInt(foodMonthlyRaw)||0)/30).toLocaleString('id-ID')"></span> / hari
                    </div>
                </div>
            </div>

            {{-- Hangout frequency --}}
            <div class="field" style="margin-bottom:0">
                <label class="field-label">Frekuensi nongkrong / dine-out</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    @foreach([
                        ['jarang',      'Jarang',              '+ Rp 0',        'var(--text-dim)'],
                        ['1-2x',        '1–2× seminggu',       '+ Rp 200rb/bln', 'var(--blue)'],
                        ['3-4x',        '3–4× seminggu',       '+ Rp 430rb/bln', 'var(--orange)'],
                        ['setiap-hari', 'Hampir Setiap Hari',  '+ Rp 800rb/bln', 'var(--red)'],
                    ] as [$val, $lbl, $est, $clr])
                    <label style="padding:12px;border-radius:var(--radius-sm);border:2px solid;cursor:pointer;transition:all .15s;background:var(--bg-elevated)"
                           :style="hangout==='{{ $val }}' ? 'border-color:var(--accent);background:rgba(139,92,246,.07)' : 'border-color:var(--border)'">
                        <input type="radio" value="{{ $val }}" x-model="hangout" style="display:none">
                        <div style="font-size:13px;font-weight:600;color:var(--text-primary);margin-bottom:3px">{{ $lbl }}</div>
                        <div style="font-size:11px;font-weight:700;color:{{ $clr }}">{{ $est }}</div>
                    </label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         STEP 4 — Transportasi
    ══════════════════════════════════════════════════════════════════ --}}
    <div x-show="step===4" x-transition>
        <div class="page-header">
            <h2>Transportasi</h2>
            <p>Jenis kendaraan dan estimasi biaya transport bulanan.</p>
        </div>

        <div class="card">
            <div class="field">
                <label class="field-label">Transportasi utama</label>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
                    @foreach([
                        ['motor',   'fa-motorcycle',   'Motor'],
                        ['mobil',   'fa-car',           'Mobil'],
                        ['krl',     'fa-train',         'KRL/MRT'],
                        ['ojek',    'fa-person-biking', 'Ojek Online'],
                        ['angkot',  'fa-bus',           'Angkot/Bus'],
                        ['mixed',   'fa-shuffle',       'Campuran'],
                    ] as [$val, $icon, $lbl])
                    <label class="sel-col" :class="transportType==='{{ $val }}' ? 'active' : ''">
                        <input type="radio" value="{{ $val }}" x-model="transportType" style="display:none">
                        <i class="fa-solid {{ $icon }}"></i>
                        <span>{{ $lbl }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            <div class="field">
                <label class="field-label">Jarak tempuh harian (sekali jalan)</label>
                <div style="display:flex;align-items:center;gap:12px">
                    <input type="number" x-model="commuteKm" min="0" max="200" placeholder="0"
                           class="field-input" style="width:90px;text-align:center;padding:11px 14px">
                    <div>
                        <div style="font-size:13px;color:var(--text-muted)">km / sekali jalan</div>
                        <div x-show="commuteKm > 0" style="font-size:12px;color:var(--text-dim)"
                             x-text="'≈ ' + (commuteKm*2) + ' km / hari (PP)'"></div>
                    </div>
                </div>
            </div>

            <div class="field" style="margin-bottom:0">
                <label class="field-label">
                    Estimasi biaya transport per bulan <span style="color:var(--red)">*</span>
                </label>
                <div style="font-size:11px;color:var(--text-dim);margin-bottom:6px">Bensin, tol, parkir, saldo ojek — semua dijumlah.</div>
                <div class="amt-wrap">
                    <span class="amt-prefix">Rp</span>
                    <input type="text" :value="transportFmt"
                           @input="fmtCurrency('transportRaw','transportFmt',$event.target.value)"
                           class="field-input" placeholder="estimasi per bulan">
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         STEP 5 — Tagihan & Langganan
    ══════════════════════════════════════════════════════════════════ --}}
    <div x-show="step===5" x-transition>
        <div class="page-header">
            <h2>Tagihan & Langganan</h2>
            <p>Terisi otomatis dari data Butler. Centang yang aktif bulan ini.</p>
        </div>

        {{-- Bills --}}
        <div class="card" style="margin-bottom:10px">
            <div class="card-section-head">
                <div class="card-section-icon" style="background:rgba(59,130,246,.1)">
                    <i class="fa-solid fa-file-invoice-dollar" style="color:var(--blue)"></i>
                </div>
                <span style="font-size:13px;font-weight:700;color:var(--text-secondary)">Tagihan Bulanan</span>
                <span style="margin-left:auto;font-size:11px;color:var(--text-dim)" x-text="bills.filter(b=>b.included).length + ' dipilih'"></span>
            </div>

            <template x-if="bills.length===0">
                <div class="empty-state" style="padding:20px 0">
                    <div class="empty-icon"><i class="fa-solid fa-inbox"></i></div>
                    <p>Belum ada tagihan tersimpan.</p>
                </div>
            </template>

            <template x-for="(item, i) in bills" :key="item.id">
                <div class="check-row">
                    <div class="check-box" :class="item.included ? 'checked' : ''"
                         @click="item.included = !item.included">
                        <i class="fa-solid fa-check" style="font-size:10px;color:#fff" x-show="item.included"></i>
                    </div>
                    <span style="flex:1;font-size:13px;font-weight:500;color:var(--text-primary)" x-text="item.name"></span>
                    <input type="text" :value="parseInt(item.amount).toLocaleString('id-ID')"
                           @input="item.amount = parseInt($event.target.value.replace(/\D/g,''))||0"
                           class="field-input" style="width:115px;text-align:right;padding:7px 12px;font-size:13px;font-weight:600">
                </div>
            </template>
        </div>

        {{-- Recurring --}}
        <div class="card" style="margin-bottom:10px" x-show="recurring.length>0">
            <div class="card-section-head">
                <div class="card-section-icon" style="background:rgba(139,92,246,.1)">
                    <i class="fa-solid fa-rotate" style="color:var(--accent)"></i>
                </div>
                <span style="font-size:13px;font-weight:700;color:var(--text-secondary)">Pengeluaran Rutin</span>
                <span style="margin-left:auto;font-size:11px;color:var(--text-dim)" x-text="recurring.filter(r=>r.included).length + ' dipilih'"></span>
            </div>
            <template x-for="(item, i) in recurring" :key="item.id">
                <div class="check-row">
                    <div class="check-box" :class="item.included ? 'checked' : ''"
                         @click="item.included = !item.included">
                        <i class="fa-solid fa-check" style="font-size:10px;color:#fff" x-show="item.included"></i>
                    </div>
                    <span style="flex:1;font-size:13px;font-weight:500;color:var(--text-primary)" x-text="item.name"></span>
                    <input type="text" :value="parseInt(item.amount).toLocaleString('id-ID')"
                           @input="item.amount = parseInt($event.target.value.replace(/\D/g,''))||0"
                           class="field-input" style="width:115px;text-align:right;padding:7px 12px;font-size:13px;font-weight:600">
                </div>
            </template>
        </div>

        {{-- Total --}}
        <div class="card" style="margin-bottom:0">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <span style="font-size:13px;color:var(--text-muted)">Total tagihan terpilih</span>
                <span style="font-size:18px;font-weight:800;color:var(--text-primary)" x-text="'Rp ' + totalTagihan.toLocaleString('id-ID')"></span>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         STEP 6 — Tanggungan & Gaya Hidup
    ══════════════════════════════════════════════════════════════════ --}}
    <div x-show="step===6" x-transition>
        <div class="page-header">
            <h2>Tanggungan & Gaya Hidup</h2>
            <p>Semua opsional — isi yang relevan dengan kondisimu.</p>
        </div>

        {{-- Dynamic tanggungan --}}
        <div class="card" style="margin-bottom:10px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
                <div class="card-section-head" style="margin-bottom:0">
                    <div class="card-section-icon" style="background:rgba(239,68,68,.1)">
                        <i class="fa-solid fa-users" style="color:var(--red)"></i>
                    </div>
                    <div>
                        <div style="font-size:13px;font-weight:700;color:var(--text-secondary)">Tanggungan</div>
                        <div style="font-size:11px;color:var(--text-dim)">Orang yang bergantung padamu</div>
                    </div>
                </div>
                <span style="font-size:12px;color:var(--text-dim)" x-text="tanggungan.length + ' orang'"></span>
            </div>

            <template x-for="(row, i) in tanggungan" :key="i">
                <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
                    <input type="text" x-model="row.name"
                           :placeholder="'mis. Ibu, Adik ' + (i+1)"
                           class="field-input" style="flex:1;min-width:0;padding:9px 12px">
                    <div class="amt-wrap" style="flex-shrink:0">
                        <span class="amt-prefix" style="font-size:12px">Rp</span>
                        <input type="text" :value="row.amtFmt"
                               @input="setTanggunganAmt(i, $event.target.value)"
                               class="field-input" style="width:128px" placeholder="per bulan">
                    </div>
                    <button @click="removeTanggungan(i)" type="button"
                            style="width:34px;height:34px;border-radius:var(--radius-sm);border:1px solid var(--border);background:none;color:var(--text-dim);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s"
                            onmouseover="this.style.borderColor='var(--red)';this.style.color='var(--red)'"
                            onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-dim)'">
                        <i class="fa-solid fa-times" style="font-size:11px"></i>
                    </button>
                </div>
            </template>

            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px">
                <button @click="addTanggungan()" type="button"
                        style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:var(--radius-sm);border:1px dashed var(--border);background:none;color:var(--text-muted);font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;font-family:inherit"
                        onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'"
                        onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-muted)'">
                    <i class="fa-solid fa-plus" style="font-size:11px"></i> Tambah Tanggungan
                </button>
                <div x-show="tanggungan.length>0" style="font-size:12px;color:var(--text-muted)">
                    Total: <strong x-text="'Rp ' + totalTanggungan.toLocaleString('id-ID')"></strong>/bln
                </div>
            </div>
        </div>

        {{-- Lifestyle fields --}}
        <div class="card">
            <div class="card-section-head" style="margin-bottom:16px">
                <div class="card-section-icon" style="background:rgba(249,115,22,.1)">
                    <i class="fa-solid fa-star" style="color:var(--orange)"></i>
                </div>
                <span style="font-size:13px;font-weight:700;color:var(--text-secondary)">
                    Pengeluaran Gaya Hidup
                </span>
                <span style="font-size:11px;font-weight:400;color:var(--text-dim)">(opsional)</span>
            </div>

            @php
            $fields6 = [
                ['rokok',    'rokokRaw',    'rokokFmt',    'fa-smoking',      'var(--orange)', 'Rokok / Vape',    'estimasi bulanan'],
                ['gym',      'gymRaw',      'gymFmt',      'fa-dumbbell',     'var(--blue)',   'Gym / Olahraga',  'membership atau kelas'],
                ['asuransi', 'asuransiRaw', 'asuransiFmt', 'fa-shield-heart', 'var(--green)',  'Premi Asuransi',  'jiwa, kesehatan, dll'],
                ['mudik',    'mudikRaw',    'mudikFmt',    'fa-bus-simple',   'var(--purple)', 'Mudik per Tahun', 'dibagi 12 bulan'],
            ];
            @endphp

            @foreach($fields6 as [$key, $raw, $fmt, $icon, $color, $lbl, $sub])
            <div class="field" style="margin-bottom:14px">
                <label class="field-label" style="display:flex;align-items:center;gap:7px">
                    <i class="fa-solid {{ $icon }}" style="color:{{ $color }};width:14px;text-align:center"></i>
                    {{ $lbl }}
                    <span style="font-size:11px;font-weight:400;color:var(--text-dim)">— {{ $sub }}</span>
                </label>
                <div class="amt-wrap">
                    <span class="amt-prefix">Rp</span>
                    <input type="text" :value="{{ $fmt }}"
                           @input="fmtCurrency('{{ $raw }}','{{ $fmt }}',$event.target.value)"
                           class="field-input" placeholder="0">
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         STEP 7 — Cicilan & Hutang
    ══════════════════════════════════════════════════════════════════ --}}
    <div x-show="step===7" x-transition>
        <div class="page-header">
            <h2>Cicilan & Hutang</h2>
            <p>Cicilan aktif yang kamu bayar tiap bulan. Terisi otomatis dari Butler.</p>
        </div>

        <template x-if="debts.length===0">
            <div class="card empty-state" style="padding:36px 20px;margin-bottom:12px">
                <div class="empty-icon"><i class="fa-solid fa-circle-check" style="color:var(--green)"></i></div>
                <h3>Tidak ada cicilan aktif</h3>
                <p>Bebas cicilan berarti lebih banyak ruang untuk nabung dan investasi.</p>
                <div style="margin-top:14px">
                    <a href="{{ route('dashboard.debts') }}" style="font-size:12px;color:var(--accent);font-weight:600">
                        <i class="fa-solid fa-plus" style="margin-right:4px"></i>Ada cicilan yang belum di-setup? Tambah di sini
                    </a>
                </div>
            </div>
        </template>

        <template x-if="debts.length > 0">
            <div>
                <div class="card" style="margin-bottom:10px">
                    <div class="card-section-head">
                        <div class="card-section-icon" style="background:rgba(239,68,68,.1)">
                            <i class="fa-solid fa-credit-card" style="color:var(--red)"></i>
                        </div>
                        <span style="font-size:13px;font-weight:700;color:var(--text-secondary)">Cicilan Aktif</span>
                        <span style="margin-left:auto;font-size:11px;color:var(--text-dim)" x-text="debts.filter(d=>d.included).length + ' dipilih'"></span>
                    </div>

                    <template x-for="(item, i) in debts" :key="item.id">
                        <div class="check-row">
                            <div class="check-box" :class="item.included ? 'checked' : ''"
                                 @click="item.included = !item.included">
                                <i class="fa-solid fa-check" style="font-size:10px;color:#fff" x-show="item.included"></i>
                            </div>
                            <div style="flex:1;min-width:0">
                                <div style="font-size:13px;font-weight:500;color:var(--text-primary)" x-text="item.name"></div>
                                <div style="font-size:11px;color:var(--text-dim)">per bulan</div>
                            </div>
                            <input type="text" :value="parseInt(item.amount).toLocaleString('id-ID')"
                                   @input="item.amount = parseInt($event.target.value.replace(/\D/g,''))||0"
                                   class="field-input" style="width:115px;text-align:right;padding:7px 12px;font-size:13px;font-weight:600">
                        </div>
                    </template>
                </div>

                <div class="card" style="margin-bottom:0">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <span style="font-size:13px;color:var(--text-muted)">Total cicilan terpilih</span>
                        <span style="font-size:18px;font-weight:800;color:var(--text-primary)" x-text="'Rp ' + totalCicilan.toLocaleString('id-ID')"></span>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- ── Navigation ───────────────────────────────────────────────────── --}}
    <div style="display:flex;gap:10px;margin-top:20px">
        <button x-show="step > 1" @click="prevStep()" type="button" class="wiz-back">
            <i class="fa-solid fa-arrow-left"></i>
        </button>
        <button @click="nextStep()" :disabled="loading" type="button"
                class="wiz-next" :class="!loading && step===7 ? 'finish' : ''">
            <i class="fa-solid fa-spinner fa-spin" x-show="loading"></i>
            <span x-show="!loading && step < 7">
                Lanjut <i class="fa-solid fa-arrow-right" style="font-size:12px"></i>
            </span>
            <span x-show="!loading && step===7">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Lihat Analisis
            </span>
        </button>
    </div>

    <div x-show="step===7" style="text-align:center;margin-top:12px;font-size:12px;color:var(--text-dim)">
        <i class="fa-solid fa-circle-info" style="margin-right:4px"></i>
        AI akan menganalisis data kamu setelah tombol ditekan (~5–15 detik)
    </div>

</div>

<script>
function wizardApp(config) {
    return {
        step: config.startStep,
        loading: false,
        error: null,

        stepLabels: [
            'Profil & Pendapatan','Tempat Tinggal','Makan & Gaya Hidup',
            'Transportasi','Tagihan & Langganan','Tanggungan & Gaya Hidup','Cicilan & Hutang',
        ],

        // Step 1
        domisili: config.profile.domisili || '',
        gajiRaw:  String(config.profile.gaji_bersih || ''),
        gajiFmt:  '',

        // Step 2
        housingStatus: config.profile.housing_status || '',
        housingRaw:    String(config.profile.housing_cost || ''),
        housingFmt:    '',

        // Step 3
        cookPct:       config.profile.cook_percentage ?? 50,
        foodMode:      'daily',
        foodDailyRaw:  '',
        foodDailyFmt:  '',
        foodMonthlyRaw:'',
        foodMonthlyFmt:'',
        hangout:       config.profile.hangout_frequency || '',

        // Step 4
        transportType: config.profile.transport_type || '',
        commuteKm:     config.profile.commute_km_daily ?? 0,
        transportRaw:  String(config.profile.transport_monthly || ''),
        transportFmt:  '',

        // Step 5
        bills:     config.billsList     || [],
        recurring: config.recurringList || [],

        // Step 6
        tanggungan: (config.profile.tanggungan_snapshot || []).map(t => ({
            name:   t.name   || '',
            amount: t.amount || 0,
            amtFmt: t.amount ? parseInt(t.amount).toLocaleString('id-ID') : '',
        })),
        rokokRaw:    String(config.profile.rokok_monthly    || ''),
        rokokFmt:    '',
        gymRaw:      String(config.profile.gym_monthly      || ''),
        gymFmt:      '',
        asuransiRaw: String(config.profile.asuransi_monthly || ''),
        asuransiFmt: '',
        mudikRaw:    String(config.profile.mudik_annual     || ''),
        mudikFmt:    '',

        // Step 7
        debts: config.debtsList || [],

        // ── Init ──────────────────────────────────────────────────────────
        init() {
            const fmt = v => v && v !== '0' ? parseInt(v).toLocaleString('id-ID') : '';
            this.gajiFmt      = fmt(this.gajiRaw);
            this.housingFmt   = fmt(this.housingRaw);
            this.transportFmt = fmt(this.transportRaw);
            this.rokokFmt     = fmt(this.rokokRaw);
            this.gymFmt       = fmt(this.gymRaw);
            this.asuransiFmt  = fmt(this.asuransiRaw);
            this.mudikFmt     = fmt(this.mudikRaw);

            const fm = parseInt(config.profile.food_base_monthly) || 0;
            if (fm > 0) {
                this.foodMonthlyRaw = String(fm);
                this.foodMonthlyFmt = fm.toLocaleString('id-ID');
                const daily = Math.round(fm / 30);
                this.foodDailyRaw = String(daily);
                this.foodDailyFmt = daily.toLocaleString('id-ID');
            }
        },

        // ── Helpers ───────────────────────────────────────────────────────
        fmtCurrency(rawField, fmtField, val) {
            const num = val.replace(/\D/g, '');
            this[rawField] = num;
            this[fmtField] = num ? parseInt(num).toLocaleString('id-ID') : '';
        },

        setFoodDaily(val) {
            const num = val.replace(/\D/g, '');
            this.foodDailyRaw = num;
            this.foodDailyFmt = num ? parseInt(num).toLocaleString('id-ID') : '';
            if (num) {
                const m = parseInt(num) * 30;
                this.foodMonthlyRaw = String(m);
                this.foodMonthlyFmt = m.toLocaleString('id-ID');
            }
        },

        setFoodMonthly(val) {
            const num = val.replace(/\D/g, '');
            this.foodMonthlyRaw = num;
            this.foodMonthlyFmt = num ? parseInt(num).toLocaleString('id-ID') : '';
            if (num) {
                const d = Math.round(parseInt(num) / 30);
                this.foodDailyRaw = String(d);
                this.foodDailyFmt = d.toLocaleString('id-ID');
            }
        },

        useIncomeHint(val) {
            this.gajiRaw = String(val);
            this.gajiFmt = val.toLocaleString('id-ID');
        },

        addTanggungan() {
            this.tanggungan.push({ name: '', amount: 0, amtFmt: '' });
        },

        removeTanggungan(i) {
            this.tanggungan.splice(i, 1);
        },

        setTanggunganAmt(i, val) {
            const num = val.replace(/\D/g, '');
            this.tanggungan[i].amount = parseInt(num) || 0;
            this.tanggungan[i].amtFmt = num ? parseInt(num).toLocaleString('id-ID') : '';
        },

        get totalTagihan() {
            return [...this.bills, ...this.recurring]
                .filter(x => x.included)
                .reduce((s, x) => s + (parseInt(x.amount) || 0), 0);
        },

        get totalTanggungan() {
            return this.tanggungan.reduce((s, t) => s + (parseInt(t.amount) || 0), 0);
        },

        get totalCicilan() {
            return this.debts.filter(d => d.included)
                .reduce((s, d) => s + (parseInt(d.amount) || 0), 0);
        },

        // ── Validate ──────────────────────────────────────────────────────
        validateStep(n) {
            switch (n) {
                case 1:
                    if (!this.domisili.trim()) return 'Kota domisili harus diisi.';
                    if (!this.gajiRaw || parseInt(this.gajiRaw) < 500000) return 'Gaji bersih tidak valid. Minimal Rp 500.000.';
                    break;
                case 2:
                    if (!this.housingStatus) return 'Pilih status tempat tinggal terlebih dahulu.';
                    break;
                case 3:
                    if (!this.foodMonthlyRaw || parseInt(this.foodMonthlyRaw) < 1) return 'Estimasi biaya makan harus diisi.';
                    if (!this.hangout) return 'Pilih frekuensi nongkrong terlebih dahulu.';
                    break;
                case 4:
                    if (!this.transportType) return 'Pilih jenis transportasi terlebih dahulu.';
                    if (!this.transportRaw || parseInt(this.transportRaw) < 1) return 'Estimasi biaya transport harus diisi.';
                    break;
            }
            return null;
        },

        // ── Navigation ────────────────────────────────────────────────────
        prevStep() {
            if (this.step > 1) {
                this.error = null;
                this.step--;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        },

        getStepData(n) {
            switch (n) {
                case 1:
                    return { domisili: this.domisili, gaji_bersih: this.gajiRaw };
                case 2:
                    return { housing_status: this.housingStatus, housing_cost: this.housingRaw };
                case 3:
                    return {
                        cook_percentage:   String(this.cookPct),
                        food_base_monthly: this.foodMonthlyRaw || '0',
                        hangout_frequency: this.hangout || 'jarang',
                    };
                case 4:
                    return {
                        transport_type:    this.transportType,
                        commute_km_daily:  String(this.commuteKm),
                        transport_monthly: this.transportRaw,
                    };
                case 5:
                    return {
                        bills_json:     JSON.stringify(this.bills),
                        recurring_json: JSON.stringify(this.recurring),
                    };
                case 6:
                    return {
                        tanggungan_json: JSON.stringify(
                            this.tanggungan.map(t => ({ name: t.name, amount: parseInt(t.amount) || 0 }))
                        ),
                        rokok_monthly:    this.rokokRaw,
                        gym_monthly:      this.gymRaw,
                        asuransi_monthly: this.asuransiRaw,
                        mudik_annual:     this.mudikRaw,
                    };
                case 7:
                    return { debts_json: JSON.stringify(this.debts) };
                default:
                    return {};
            }
        },

        async nextStep() {
            this.error = null;

            const err = this.validateStep(this.step);
            if (err) { this.error = err; return; }

            this.loading = true;

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const res  = await fetch(`/dashboard/finance-review/step/${this.step}`, {
                    method:  'POST',
                    headers: {
                        'X-CSRF-TOKEN':  csrf,
                        'Accept':        'application/json',
                        'Content-Type':  'application/json',
                    },
                    body: JSON.stringify(this.getStepData(this.step)),
                });

                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();

                if (json.success) {
                    if (json.redirect) {
                        window.location.href = json.redirect;
                    } else {
                        this.step++;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                } else {
                    this.error = json.message || 'Terjadi kesalahan. Coba lagi.';
                }
            } catch (e) {
                this.error = 'Koneksi bermasalah. Coba lagi.';
                console.error(e);
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>
@endsection
