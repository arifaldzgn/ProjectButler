@extends('layouts.dashboard')
@section('title', 'Sanggup Ga? — Review Keuangan')
@section('content')

@php
$gaji        = $breakdown['gaji'];
$sisa        = $breakdown['sisa'];
$sisaPct     = $breakdown['sisa_pct'];
$totalPct    = $breakdown['total_pct'];
$domisili    = ucfirst($profile->domisili ?? 'Tidak diketahui');
$umkVal      = $umk['umk'] ?? 0;
$umkLabel    = $umk['label'] ?? $domisili;
$umkPct      = $umkVal > 0 ? round($gaji / $umkVal * 100, 1) : null;
$gajiVsUmk   = $umkVal > 0 ? round(($gaji - $umkVal) / $umkVal * 100, 1) : null;
$idealNyaman = $breakdown['total'] > 0 ? (int) round($breakdown['total'] / 0.70) : 0;
$tabunganTahunan = max(0, $sisa) * 12;

$heroColor   = $sisaPct >= 20 ? 'var(--green)' : ($sisaPct >= 5 ? 'var(--orange)' : 'var(--red)');
$fmt = fn(int $v) => 'Rp ' . number_format($v, 0, ',', '.');
$fmtShort = fn(int $v) => $v >= 1000000 ? 'Rp ' . number_format($v/1000000, 1, ',', '.') . ' Jt' : 'Rp ' . number_format($v/1000, 0, ',', '.') . ' rb';

$statusLabel  = ['baik' => 'Baik', 'perhatian' => 'Perlu Perhatian', 'bahaya' => 'Bahaya'];
$statusColor  = ['baik' => 'var(--green)', 'perhatian' => 'var(--orange)', 'bahaya' => 'var(--red)'];
$statusBorder = ['baik' => 'rgba(16,185,129,.3)', 'perhatian' => 'rgba(249,115,22,.3)', 'bahaya' => 'rgba(239,68,68,.3)'];
$statusBadgeBg= ['baik' => 'rgba(16,185,129,.15)', 'perhatian' => 'rgba(249,115,22,.15)', 'bahaya' => 'rgba(239,68,68,.15)'];
$statusBg     = ['baik' => 'rgba(16,185,129,.07)', 'perhatian' => 'rgba(249,115,22,.07)', 'bahaya' => 'rgba(239,68,68,.07)'];

$chartColors = ['#8b5cf6','#3b82f6','#10b981','#f97316','#ef4444','#eab308'];
$chartLabels = [];
$chartData   = [];
foreach (['makan','transport','tempat_tinggal','tagihan','cicilan','gaya_hidup'] as $k) {
    if ($breakdown[$k]['amount'] > 0) {
        $chartLabels[] = $breakdown[$k]['label'];
        $chartData[]   = $breakdown[$k]['amount'];
    }
}

// ── Badge Achievements ────────────────────────────────────────────────
$badges = [
    ['fa-check-double',    'var(--green)',  'var(--green)',  'Bebas Cicilan',            'Tidak ada cicilan aktif',              $breakdown['cicilan']['amount'] === 0],
    ['fa-utensils',        'var(--orange)', 'var(--orange)', 'Hemat Makan',              'Makan < 25% dari gaji',                $gaji > 0 && $breakdown['makan']['amount'] / $gaji <= 0.25],
    ['fa-piggy-bank',      '#8b5cf6',       '#8b5cf6',       'Super Saver',              'Sisa dana > 30% gaji',                 $sisaPct >= 30],
    ['fa-road',            'var(--blue)',   'var(--blue)',   'Transport Smart',          'Transport < 10% dari gaji',            $gaji > 0 && $breakdown['transport']['amount'] / $gaji <= 0.10],
    ['fa-trophy',          '#eab308',       '#eab308',       'UMK Achiever',             'Gaji di atas UMK kota',                $umkVal > 0 && $gaji >= $umkVal],
    ['fa-bolt',            'var(--blue)',   'var(--blue)',   'Tagihan Ringan',           'Tagihan < 10% dari gaji',              $gaji > 0 && $breakdown['tagihan']['amount'] / $gaji <= 0.10],
    ['fa-star',            'var(--orange)', 'var(--orange)', 'Gaya Hidup Oke',           'Gaya hidup < 15% dari gaji',           $gaji > 0 && $breakdown['gaya_hidup']['amount'] / $gaji <= 0.15],
    ['fa-hand-holding-heart','var(--green)','var(--green)',  'Tidak Defisit',            'Sisa dana positif bulan ini',          $sisa >= 0],
];
$unlockedCount = count(array_filter($badges, fn($b) => $b[5]));

// ── Rencana Aksi 30 Hari ─────────────────────────────────────────────
$aksi = [];

if ($tangga['level'] <= 1) {
    $aksi[] = ['fa-scissors',        'var(--red)',    'Audit Pengeluaran Darurat',   'Buat daftar semua pengeluaran bulan ini. Tandai mana yang bisa dipotong sekarang.'];
    $aksi[] = ['fa-ban',             'var(--red)',    'Stop Utang Baru',             'Hindari kartu kredit atau cicilan apapun sampai kondisi membaik.'];
}
if ($breakdown['cicilan']['pct'] > 30) {
    $aksi[] = ['fa-money-bill-wave', 'var(--orange)', 'Evaluasi Cicilan',            "Rasio cicilan {$breakdown['cicilan']['pct']}% — hubungi kreditur untuk renegosiasi atau prioritaskan lunasi cicilan berbunga tertinggi."];
}
if ($breakdown['tagihan']['pct'] > 20) {
    $aksi[] = ['fa-wifi',            'var(--orange)', 'Audit Langganan',             "Tagihan {$breakdown['tagihan']['pct']}% dari gaji — batalkan minimal 1 langganan yang tidak terpakai bulan ini."];
}
if ($breakdown['makan']['pct'] > 40) {
    $aksi[] = ['fa-fire-burner',     'var(--orange)', 'Coba Meal Prep',              "Biaya makan {$breakdown['makan']['pct']}% dari gaji — masak 2–3× seminggu bisa hemat 20–30%."];
}
if ($ratioCards['darurat']['status'] !== 'baik') {
    $minSave = max((int)($sisa * 0.5), 100000);
    $aksi[]  = ['fa-vault',          'var(--blue)',   'Mulai Dana Darurat',          'Buka rekening terpisah khusus darurat. Auto-transfer Rp ' . number_format($minSave, 0, ',', '.') . ' di hari gajian.'];
}
if ($tangga['level'] >= 3 && $ratioCards['tabungan']['status'] !== 'baik') {
    $aksi[]  = ['fa-robot',          '#8b5cf6',       'Otomasi Tabungan',            'Setup auto-debit ke rekening tabungan di hari gajian — sebelum uang sempat terpakai.'];
}
if ($tangga['level'] >= 4 && $sisaPct >= 20) {
    $aksi[]  = ['fa-chart-line',     'var(--green)',  'Mulai Investasi Rutin',       'Keuanganmu siap. Buka reksa dana dan auto-invest minimal 10% gaji setiap bulan.'];
}
if (empty($aksi)) {
    $aksi[]  = ['fa-circle-check',   'var(--green)',  'Pertahankan & Tingkatkan',    'Kondisi keuanganmu sudah sehat. Perluas ke instrumen investasi berisiko moderat untuk pertumbuhan jangka panjang.'];
}
$aksi = array_slice($aksi, 0, 4);
@endphp

{{-- Section 0: Export bar --}}
<div style="display:flex;justify-content:flex-end;align-items:center;gap:8px;margin-bottom:20px;flex-wrap:wrap" class="animate-in">
    <span style="font-size:12px;color:var(--text-dim);margin-right:4px">Sanggup Ga?</span>
    <form method="POST" action="{{ route('finance-review.reset') }}" style="display:inline"
          x-data onsubmit="return confirm('Reset semua data review? Kamu harus mengisi ulang dari awal.')">
        @csrf
        <button type="submit" style="padding:7px 14px;background:none;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:12px;font-weight:600;color:var(--text-muted);cursor:pointer;display:inline-flex;align-items:center;gap:6px">
            <i class="fa-solid fa-rotate-left"></i> Mulai Ulang
        </button>
    </form>
</div>

{{-- Section 1: Hero --}}
<div class="card animate-in" style="padding:24px;margin-bottom:20px;background:linear-gradient(135deg,var(--bg-elevated),var(--bg-card));border:1px solid var(--border-strong);animation-delay:.02s">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px">
        <div>
            <span style="display:inline-block;padding:3px 10px;background:rgba(255,255,255,.08);border-radius:999px;font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:10px">
                <i class="fa-solid fa-location-dot" style="margin-right:4px"></i>{{ $domisili }}
            </span>
            <div style="font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px">ESTIMASI SISA DANA / BULAN</div>
            <div style="font-size:36px;font-weight:800;color:{{ $heroColor }};line-height:1.1">{{ $fmt($sisa) }}</div>
            @if($gajiVsUmk !== null)
            <div style="margin-top:8px;font-size:13px;color:var(--text-muted)">
                Gaji vs UMK {{ $umkLabel }}: <strong style="color:{{ $gajiVsUmk >= 0 ? 'var(--green)' : 'var(--red)' }}">{{ $gajiVsUmk >= 0 ? '+' : '' }}{{ $gajiVsUmk }}%</strong>
            </div>
            @endif
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;min-width:160px">
            <div style="padding:10px 14px;border-radius:var(--radius-sm);background:var(--bg);border:1px solid var(--border)">
                <div style="font-size:11px;color:var(--text-dim);margin-bottom:2px">Pengeluaran</div>
                <div style="font-size:18px;font-weight:700;color:{{ $totalPct > 80 ? 'var(--red)' : 'var(--text-primary)' }}">{{ $totalPct }}%</div>
                <div style="font-size:11px;color:var(--text-dim)">dari gaji</div>
            </div>
            <div style="padding:10px 14px;border-radius:var(--radius-sm);background:var(--bg);border:1px solid var(--border)">
                <div style="font-size:11px;color:var(--text-dim);margin-bottom:2px">Sisa</div>
                <div style="font-size:18px;font-weight:700;color:{{ $heroColor }}">{{ $sisaPct }}%</div>
                @if($umkVal > 0)
                <div style="font-size:11px;color:var(--text-dim)">UMK: {{ $fmt($umkVal) }}</div>
                @endif
            </div>
        </div>
    </div>
    {{-- Spending bar --}}
    <div style="margin-top:16px">
        <div style="height:8px;border-radius:999px;background:var(--border);overflow:hidden;display:flex">
            @php $colors6 = ['#8b5cf6','#3b82f6','#10b981','#f97316','#ef4444','#eab308']; $ci = 0; @endphp
            @foreach(['makan','transport','tempat_tinggal','tagihan','cicilan','gaya_hidup'] as $k)
            @if($breakdown[$k]['amount'] > 0 && $gaji > 0)
            <div style="height:100%;background:{{ $colors6[$ci] }};width:{{ $breakdown[$k]['pct'] }}%;transition:width .6s"></div>
            @php $ci++; @endphp
            @endif
            @endforeach
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px">
            @php $ci = 0; @endphp
            @foreach(['makan','transport','tempat_tinggal','tagihan','cicilan','gaya_hidup'] as $k)
            @if($breakdown[$k]['amount'] > 0)
            <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--text-muted)">
                <div style="width:8px;height:8px;border-radius:2px;background:{{ $colors6[$ci] }};flex-shrink:0"></div>
                {{ $breakdown[$k]['label'] }} {{ $breakdown[$k]['pct'] }}%
            </div>
            @php $ci++; @endphp
            @endif
            @endforeach
        </div>
    </div>
</div>

{{-- Section 2: Tangga Finansial --}}
<div class="card animate-in" style="padding:20px;margin-bottom:20px;animation-delay:.04s">
    <div style="margin-bottom:4px">
        <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-dim)">POSISI FINANSIALMU</div>
    </div>
    <h3 style="font-size:18px;font-weight:700;margin-bottom:4px">Tangga Finansial</h3>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:18px">Kamu ada di anak tangga mana — dan apa langkah berikutnya.</p>

    <div x-data="{ open: {{ $tangga['level'] }} }">
        @php
        $ladderItems = [
            5 => ['Bertumbuh', 'fa-rocket'],
            4 => ['Aman & Proteksi', 'fa-shield-halved'],
            3 => ['Mulai Menabung', 'fa-piggy-bank'],
            2 => ['Stabil', 'fa-house'],
            1 => ['Bertahan Hidup', 'fa-seedling'],
        ];
        @endphp
        @foreach($ladderItems as $lvl => [$lbl, $icon])
        @php $isActive = $lvl === $tangga['level']; $isPast = $lvl < $tangga['level']; @endphp
        <div style="border-radius:var(--radius-sm);overflow:hidden;margin-bottom:6px;border:1px solid {{ $isActive ? 'rgba(16,185,129,.35)' : 'var(--border)' }};background:{{ $isActive ? 'rgba(16,185,129,.07)' : 'var(--bg)' }}">
            <button type="button"
                    @click="open = open === {{ $lvl }} ? null : {{ $lvl }}"
                    style="width:100%;display:flex;align-items:center;gap:12px;padding:12px 14px;background:none;border:none;cursor:pointer;text-align:left">
                <div style="width:28px;height:28px;border-radius:999px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:{{ $isActive ? 'var(--green)' : ($isPast ? 'rgba(16,185,129,.15)' : 'var(--bg-elevated)') }}">
                    @if($isPast)
                    <i class="fa-solid fa-check" style="font-size:11px;color:var(--green)"></i>
                    @elseif($isActive)
                    <i class="fa-solid {{ $icon }}" style="font-size:11px;color:#fff"></i>
                    @else
                    <i class="fa-solid fa-lock" style="font-size:10px;color:var(--text-dim)"></i>
                    @endif
                </div>
                <div style="flex:1">
                    <span style="font-size:13px;font-weight:{{ $isActive ? '700' : '500' }};color:{{ $isActive ? 'var(--green)' : ($isPast ? 'var(--text-muted)' : 'var(--text-dim)') }}">
                        {{ $lbl }}
                    </span>
                </div>
                @if($isActive)
                <span style="padding:2px 8px;border-radius:999px;background:var(--green);color:#fff;font-size:10px;font-weight:700">Tangga {{ $lvl }}</span>
                @endif
                <i class="fa-solid fa-chevron-down" style="font-size:11px;color:var(--text-dim);transition:transform .2s"
                   :style="open === {{ $lvl }} ? 'transform:rotate(180deg)' : ''"></i>
            </button>

            <div x-show="open === {{ $lvl }}" x-transition style="padding:0 14px 16px">
                @if($isActive)
                <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">{{ $tangga['desc'] }}</p>
                <div style="margin-bottom:12px;padding:10px 12px;border-radius:var(--radius-sm);background:var(--bg-elevated);border:1px solid var(--border)">
                    <div style="font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-dim);margin-bottom:4px">FOKUS UTAMA</div>
                    <div style="font-size:13px;color:var(--text-primary)">{{ $tangga['fokus'] }}</div>
                </div>
                <div style="margin-bottom:12px;padding:10px 12px;border-radius:var(--radius-sm);background:var(--bg-elevated);border:1px solid var(--border)">
                    <div style="font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-dim);margin-bottom:4px">TARGET NAIK TANGGA</div>
                    <div style="font-size:13px;color:var(--text-primary)">{{ $tangga['target'] }}</div>
                </div>
                <div style="padding:10px 12px;border-radius:var(--radius-sm);background:var(--bg-elevated);border:1px solid var(--border)">
                    <div style="font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-dim);margin-bottom:8px">LANGKAH KONKRET</div>
                    @foreach($tangga['langkah'] as $i => $l)
                    <div style="display:flex;gap:8px;margin-bottom:{{ $i < 2 ? '6px' : '0' }}">
                        <span style="width:18px;height:18px;border-radius:999px;background:rgba(16,185,129,.15);color:var(--green);font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px">{{ $i+1 }}</span>
                        <span style="font-size:13px;color:var(--text-secondary)">{{ $l }}</span>
                    </div>
                    @endforeach
                </div>
                @else
                <p style="font-size:12px;color:var(--text-dim)">{{ $isActive ? '' : ($isPast ? 'Sudah terlewati.' : 'Belum dicapai.') }}</p>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>

{{-- Section 3: AI Insights --}}
<div class="card animate-in" style="padding:20px;margin-bottom:20px;animation-delay:.06s">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px">
        <i class="fa-solid fa-wand-magic-sparkles" style="color:var(--accent)"></i>
        <span style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-dim)">INSIGHT AI — ANALISIS PERSONAL</span>
    </div>
    <div style="font-size:13px;line-height:1.7;color:var(--text-secondary);white-space:pre-wrap">{{ $profile->ai_insights ?? 'Analisis belum tersedia.' }}</div>
    <form method="POST" action="{{ route('finance-review.recalculate') }}" style="margin-top:14px">
        @csrf
        <button type="submit" style="padding:7px 14px;background:none;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:12px;font-weight:600;color:var(--text-muted);cursor:pointer;display:inline-flex;align-items:center;gap:6px">
            <i class="fa-solid fa-rotate"></i> Perbarui Analisis
        </button>
        @if($profile->insights_generated_at)
        <span style="font-size:11px;color:var(--text-dim);margin-left:10px">Terakhir: {{ $profile->insights_generated_at->diffForHumans() }}</span>
        @endif
    </form>
</div>

{{-- Section 4: Kartu Rasio --}}
<div class="card animate-in" style="padding:20px;margin-bottom:20px;animation-delay:.08s">
    <div style="margin-bottom:4px">
        <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-dim)">DIAGNOSTIK PERSONAL</div>
    </div>
    <h3 style="font-size:18px;font-weight:700;margin-bottom:4px">Kartu Rasio Keuangan</h3>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:18px">Perhitungan deterministik dari data yang kamu masukkan — bukan estimasi AI.</p>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
        @foreach($ratioCards as $card)
        <div style="padding:14px;border-radius:var(--radius-sm);border:1px solid {{ $statusBorder[$card['status']] }};background:{{ $statusBg[$card['status']] }}">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
                <span style="font-size:11px;font-weight:600;color:var(--text-muted);line-height:1.3;flex:1;margin-right:6px">{{ $card['title'] }}</span>
                <span style="padding:2px 7px;border-radius:999px;background:{{ $statusBadgeBg[$card['status']] }};color:{{ $statusColor[$card['status']] }};font-size:9px;font-weight:700;white-space:nowrap;flex-shrink:0">{{ $statusLabel[$card['status']] }}</span>
            </div>
            <div style="font-size:21px;font-weight:800;color:{{ $statusColor[$card['status']] }};margin-bottom:2px">{{ $card['value'] }}</div>
            <div style="font-size:10px;color:var(--text-dim);margin-bottom:8px">{{ $card['target'] }}</div>
            {{-- Mini progress bar --}}
            @php
                $barPct = 0;
                if (str_contains($card['value'], '%')) {
                    $barPct = min(100, (float) str_replace('%', '', $card['value']));
                } elseif (str_contains($card['note'], '%')) {
                    preg_match('/(\d+)%/', $card['note'], $m);
                    $barPct = min(100, (int) ($m[1] ?? 0));
                }
            @endphp
            <div style="height:3px;border-radius:999px;background:var(--border);overflow:hidden;margin-bottom:8px">
                <div style="height:100%;border-radius:999px;background:{{ $statusColor[$card['status']] }};width:{{ $barPct }}%;transition:width .6s"></div>
            </div>
            <div style="font-size:11px;color:var(--text-muted)">{{ $card['sub'] }}</div>
        </div>
        @endforeach
    </div>
</div>

{{-- Section 5: Alternatif Kota + Distribusi --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;animation-delay:.10s" class="animate-in">

    {{-- Alternatif Kota --}}
    <div class="card" style="padding:20px">
        <div style="display:flex;align-items:center;gap:6px;margin-bottom:14px">
            <span style="font-size:13px;font-weight:700;color:var(--text-secondary)">Alternatif Kota</span>
            <span title="Estimasi penghematan jika biaya kebutuhan pokok mengikuti rata-rata kota tersebut."
                  style="cursor:help;color:var(--text-dim);font-size:11px">
                <i class="fa-solid fa-circle-info"></i>
            </span>
        </div>
        @foreach($alternatifKota as $kota)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border)">
            <span style="font-size:13px;color:var(--text-secondary)">{{ $kota['kota'] }}</span>
            <span style="font-size:13px;font-weight:700;color:{{ $kota['selisih_sisa'] >= 0 ? 'var(--green)' : 'var(--red)' }}">
                {{ $kota['selisih_sisa'] >= 0 ? '+' : '' }}{{ $fmt($kota['selisih_sisa']) }}
            </span>
        </div>
        @endforeach
        @if(count($alternatifKota) === 0)
        <div style="font-size:12px;color:var(--text-dim);text-align:center;padding:16px 0">Data kota tidak tersedia.</div>
        @endif
    </div>

    {{-- Distribusi Pengeluaran --}}
    <div class="card" style="padding:20px">
        <div style="font-size:13px;font-weight:700;color:var(--text-secondary);margin-bottom:14px">Distribusi Pengeluaran</div>
        <canvas id="distribusiChart" height="180"></canvas>
        <div style="margin-top:10px">
            @php $ci = 0; @endphp
            @foreach(['makan','transport','tempat_tinggal','tagihan','cicilan','gaya_hidup'] as $k)
            @if($breakdown[$k]['amount'] > 0)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:3px 0;font-size:11px">
                <div style="display:flex;align-items:center;gap:6px">
                    <div style="width:8px;height:8px;border-radius:2px;background:{{ $chartColors[$ci] }};flex-shrink:0"></div>
                    <span style="color:var(--text-muted)">{{ $breakdown[$k]['label'] }}</span>
                </div>
                <span style="font-weight:600;color:var(--text-secondary)">{{ $breakdown[$k]['pct'] }}%</span>
            </div>
            @php $ci++; @endphp
            @endif
            @endforeach
        </div>
    </div>
</div>

{{-- Section 6: What If Simulation --}}
<div class="card animate-in" style="padding:20px;margin-bottom:20px;animation-delay:.12s"
     x-data="whatIfSim({{ $breakdown['makan']['amount'] }}, {{ $breakdown['transport']['amount'] }}, {{ $breakdown['tempat_tinggal']['amount'] }}, {{ $breakdown['total'] }}, {{ $gaji }})">
    <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-dim);margin-bottom:4px">SIMULASI</div>
    <h3 style="font-size:18px;font-weight:700;margin-bottom:4px">Simulasi "What If?"</h3>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:18px">Sesuaikan untuk melihat dampak ke sisa dana kamu.</p>

    <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:20px">
        <label style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-radius:var(--radius-sm);border:1px solid var(--border);cursor:pointer;transition:all .15s"
               :style="cookSelf ? 'border-color:var(--green);background:rgba(16,185,129,.06)' : ''">
            <div>
                <div style="font-size:13px;font-weight:600;color:var(--text-primary)">Masak Sendiri</div>
                <div style="font-size:11px;color:var(--text-dim)">Hemat ~15% biaya makan</div>
            </div>
            <div style="position:relative">
                <input type="checkbox" x-model="cookSelf" style="display:none">
                <div style="width:40px;height:22px;border-radius:999px;transition:all .15s;display:flex;align-items:center;padding:2px"
                     :style="cookSelf ? 'background:var(--green)' : 'background:var(--border-strong)'">
                    <div style="width:18px;height:18px;border-radius:999px;background:#fff;transition:transform .15s"
                         :style="cookSelf ? 'transform:translateX(18px)' : ''"></div>
                </div>
            </div>
        </label>

        <label style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-radius:var(--radius-sm);border:1px solid var(--border);cursor:pointer;transition:all .15s"
               :style="publicTransport ? 'border-color:var(--blue);background:rgba(59,130,246,.06)' : ''">
            <div>
                <div style="font-size:13px;font-weight:600;color:var(--text-primary)">Transportasi Umum</div>
                <div style="font-size:11px;color:var(--text-dim)">Hemat ~80% biaya transport</div>
            </div>
            <div style="position:relative">
                <input type="checkbox" x-model="publicTransport" style="display:none">
                <div style="width:40px;height:22px;border-radius:999px;transition:all .15s;display:flex;align-items:center;padding:2px"
                     :style="publicTransport ? 'background:var(--blue)' : 'background:var(--border-strong)'">
                    <div style="width:18px;height:18px;border-radius:999px;background:#fff;transition:transform .15s"
                         :style="publicTransport ? 'transform:translateX(18px)' : ''"></div>
                </div>
            </div>
        </label>

        @if($breakdown['tempat_tinggal']['amount'] > 0)
        <div style="padding:12px 14px;border-radius:var(--radius-sm);border:1px solid var(--border)">
            <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                <div>
                    <div style="font-size:13px;font-weight:600;color:var(--text-primary)">Biaya Kos/Sewa</div>
                    <div style="font-size:11px;color:var(--text-dim)">Sesuaikan biaya hunian</div>
                </div>
                <span style="font-size:13px;font-weight:700;color:var(--text-primary)" x-text="'Rp ' + housingAdj.toLocaleString('id-ID')"></span>
            </div>
            <input type="range" x-model="housingAdj" min="0" :max="{{ $breakdown['tempat_tinggal']['amount'] * 2 }}" step="50000"
                   style="width:100%;accent-color:var(--accent)">
        </div>
        @endif
    </div>

    <div style="padding:16px;border-radius:var(--radius-sm);background:var(--bg-elevated);border:1px solid var(--border-strong)">
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">Estimasi Sisa Dana (simulasi)</div>
        <div style="font-size:28px;font-weight:800;transition:color .3s"
             :style="simSisa >= 0 ? 'color:var(--green)' : 'color:var(--red)'"
             x-text="'Rp ' + Math.abs(simSisa).toLocaleString('id-ID') + (simSisa < 0 ? ' (defisit)' : '')"></div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px"
             x-text="'Hemat Rp ' + Math.abs(simSisa - {{ $sisa }}).toLocaleString('id-ID') + ' dari kondisi saat ini'"></div>
    </div>
</div>

{{-- Section 7: Realitas di Kota --}}
<div class="card animate-in" style="padding:20px;margin-bottom:20px;animation-delay:.14s">
    <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-dim);margin-bottom:10px">
        REALITAS DI {{ strtoupper($domisili) }}
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:14px">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-radius:var(--radius-sm);background:var(--bg)">
            <span style="font-size:13px;color:var(--text-muted)">Pemasukan Kamu</span>
            <span style="font-size:15px;font-weight:700;color:var(--text-primary)">{{ $fmt($gaji) }}</span>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-radius:var(--radius-sm);background:var(--bg)">
            <span style="font-size:13px;color:var(--text-muted)">
                Gaji "Ideal Nyaman"
                <i class="fa-solid fa-circle-info" style="font-size:10px;color:var(--text-dim);margin-left:3px"
                   title="Gaji yang membuat pengeluaranmu tidak lebih dari 70% pendapatan."></i>
            </span>
            <span style="font-size:15px;font-weight:700;color:var(--text-primary)">{{ $fmt($idealNyaman) }}</span>
        </div>
        @if($umkVal > 0)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-radius:var(--radius-sm);background:var(--bg)">
            <span style="font-size:13px;color:var(--text-muted)">UMK {{ $umkLabel }} 2026</span>
            <span style="font-size:15px;font-weight:700;color:var(--text-primary)">{{ $fmt($umkVal) }}</span>
        </div>
        @endif
    </div>
    @if($umkPct !== null)
    <div style="padding:12px 14px;border-radius:var(--radius-sm);background:{{ $umkPct >= 100 ? 'rgba(16,185,129,.1)' : ($umkPct >= 80 ? 'rgba(249,115,22,.1)' : 'rgba(239,68,68,.1)') }};border:1px solid {{ $umkPct >= 100 ? 'rgba(16,185,129,.25)' : ($umkPct >= 80 ? 'rgba(249,115,22,.25)' : 'rgba(239,68,68,.25)') }}">
        <span style="font-size:13px;color:{{ $umkPct >= 100 ? 'var(--green)' : ($umkPct >= 80 ? 'var(--orange)' : 'var(--red)') }};font-weight:600">
            Gajimu adalah {{ $umkPct }}% dari standar UMK {{ $umkLabel }}.
            @if($umkPct >= 140) Jauh di atas UMK — kamu punya ruang lebih untuk investasi.
            @elseif($umkPct >= 100) Di atas UMK, tapi tetap perhatikan efisiensi pengeluaran.
            @elseif($umkPct >= 80) Mendekati UMK — pertimbangkan cara menambah penghasilan.
            @else Masih di bawah UMK kota ini. Evaluasi pengeluaran dan cari peluang tambahan.
            @endif
        </span>
    </div>
    @endif
</div>

{{-- Section 8: Stat cards --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;animation-delay:.16s" class="animate-in">
    <div class="card stat-card {{ $sisa >= $gaji * 0.2 ? 'green' : ($sisa > 0 ? 'orange' : 'red') }}">
        <div class="stat-icon"><i class="fa-solid fa-piggy-bank"></i></div>
        <div class="stat-label">Potensi Tabungan Tahunan</div>
        <div class="stat-value" style="font-size:18px">
            @if($tabunganTahunan >= 1000000)
                Rp {{ number_format($tabunganTahunan/1000000, 1, ',', '.') }} Jt
            @else
                {{ $fmt($tabunganTahunan) }}
            @endif
        </div>
        <div class="stat-sub">Jika konsisten menabung sisa tiap bulan</div>
    </div>
    <div class="card stat-card {{ $totalPct <= 70 ? 'green' : ($totalPct <= 85 ? 'orange' : 'red') }}">
        <div class="stat-icon"><i class="fa-solid fa-chart-pie"></i></div>
        <div class="stat-label">Rasio Pengeluaran</div>
        <div class="stat-value">{{ $totalPct }}%</div>
        <div class="stat-sub">Target ideal &lt;70% (sisa untuk tabungan & investasi)</div>
    </div>
</div>

{{-- Section 9: Badge Achievements --}}
<div class="card animate-in" style="padding:20px;margin-bottom:20px;animation-delay:.17s">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-dim)">PENCAPAIAN</div>
            <h3 style="font-size:18px;font-weight:700">Badge Finansial</h3>
        </div>
        <div style="text-align:right">
            <div style="font-size:26px;font-weight:800;color:var(--accent)">{{ $unlockedCount }}<span style="font-size:16px;font-weight:600;color:var(--text-dim)">/{{ count($badges) }}</span></div>
            <div style="font-size:11px;color:var(--text-dim)">diraih</div>
        </div>
    </div>
    {{-- Progress bar --}}
    <div style="height:4px;border-radius:999px;background:var(--border);overflow:hidden;margin-bottom:16px">
        <div style="height:100%;border-radius:999px;background:var(--accent);width:{{ round($unlockedCount/count($badges)*100) }}%;transition:width .6s"></div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px">
        @foreach($badges as [$icon, $color, $borderColor, $title, $desc, $unlocked])
        <div style="padding:14px 10px;border-radius:var(--radius-sm);text-align:center;border:1px solid {{ $unlocked ? 'rgba(139,92,246,.25)' : 'var(--border)' }};background:{{ $unlocked ? 'rgba(139,92,246,.06)' : 'var(--bg)' }};transition:all .2s;{{ !$unlocked ? 'opacity:.38;filter:grayscale(.6)' : '' }}">
            <i class="fa-solid {{ $icon }}" style="font-size:22px;color:{{ $color }};margin-bottom:8px;display:block"></i>
            <div style="font-size:11px;font-weight:700;color:var(--text-primary);margin-bottom:2px;line-height:1.2">{{ $title }}</div>
            <div style="font-size:10px;color:var(--text-dim);line-height:1.3">{{ $desc }}</div>
            @if($unlocked)
            <div style="margin-top:6px;font-size:9px;font-weight:700;color:{{ $color }};letter-spacing:.04em;text-transform:uppercase">DIRAIH</div>
            @endif
        </div>
        @endforeach
    </div>
</div>

{{-- Section 9.5: Rencana Aksi 30 Hari --}}
<div class="card animate-in" style="padding:20px;margin-bottom:20px;animation-delay:.19s">
    <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-dim);margin-bottom:4px">AKSI BULAN INI</div>
    <h3 style="font-size:18px;font-weight:700;margin-bottom:4px">Rencana Aksi 30 Hari</h3>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">Konkret, spesifik, dan bisa dimulai hari ini berdasarkan kondisi finansialmu.</p>
    @foreach($aksi as $i => [$icon, $color, $title, $desc])
    <div style="display:flex;align-items:flex-start;gap:14px;padding:14px 0;{{ $i < count($aksi)-1 ? 'border-bottom:1px solid var(--border)' : '' }}">
        <div style="width:38px;height:38px;border-radius:10px;background:var(--bg-elevated);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="fa-solid {{ $icon }}" style="font-size:15px;color:{{ $color }}"></i>
        </div>
        <div style="flex:1">
            <div style="font-size:13px;font-weight:700;color:var(--text-primary);margin-bottom:4px">{{ $title }}</div>
            <div style="font-size:12px;color:var(--text-muted);line-height:1.6">{{ $desc }}</div>
        </div>
        <div style="width:20px;height:20px;border-radius:50%;border:2px solid var(--border);flex-shrink:0;margin-top:2px"></div>
    </div>
    @endforeach
</div>

{{-- Section 10: Edukasi Finansial --}}
<div class="card animate-in" style="padding:20px;margin-bottom:20px;animation-delay:.18s" x-data="{open: null}">
    <h3 style="font-size:18px;font-weight:700;margin-bottom:4px">Pelajari Lebih Lanjut</h3>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px">Konten relevan berdasarkan tangga finansialmu saat ini.</p>

    <div style="padding:10px 14px;border-radius:var(--radius-sm);background:rgba(234,179,8,.08);border:1px solid rgba(234,179,8,.25);margin-bottom:16px;font-size:12px;color:var(--text-muted)">
        <i class="fa-solid fa-circle-exclamation" style="color:var(--yellow);margin-right:6px"></i>
        Informasi ini bersifat edukasi umum, bukan saran finansial. Sanggup Ga? bukan penasihat keuangan tersertifikasi. Untuk keputusan finansial penting, konsultasikan dengan perencana keuangan profesional.
    </div>

    @php
    $eduItems = [
        ['Dana Darurat: Fondasi Finansial', 'fa-vault', 'Dana darurat adalah tabungan yang disisihkan khusus untuk kebutuhan mendesak — bukan investasi, bukan tabungan biasa. Idealnya 3–6 bulan pengeluaran. Simpan di rekening terpisah dengan akses mudah tapi tidak terlalu mudah digoda. Mulai dengan target kecil: Rp 1–2 juta dulu, lalu naikkan perlahan.', true],
        ['Proteksi: Lindungi Sebelum Investasi', 'fa-shield-heart', 'Banyak orang langsung investasi tapi lupa proteksi. Asuransi kesehatan penting karena satu rawat inap bisa menguras tabungan bertahun-tahun. Jika bekerja, manfaatkan BPJS Kesehatan maksimal. Untuk yang sudah punya tanggungan, pertimbangkan asuransi jiwa term (berjangka) — lebih murah dan efisien dari whole life.', $tangga['level'] >= 3],
        ['Investasi: Uang Bekerja untuk Kamu', 'fa-chart-line', 'Investasi bukan cuma saham. Mulai dari reksa dana pasar uang untuk belajar, lalu reksadana campuran atau saham setelah paham risikonya. Prinsip dasar: mulai lebih awal (meski kecil), rutin, dan jangan panik saat pasar turun. Dollar-cost averaging — investasi rutin terlepas kondisi pasar — terbukti efektif untuk investor jangka panjang.', $tangga['level'] >= 4],
        ['PPh 21: Pajak Penghasilanmu', 'fa-receipt', 'Karyawan dengan gaji di atas PTKP (Rp 54 juta/tahun atau Rp 4,5 juta/bulan) wajib bayar pajak penghasilan. Biasanya sudah dipotong langsung oleh perusahaan. Pastikan kamu punya NPWP dan lapor SPT tahunan setiap Maret. Kalau punya penghasilan sampingan di atas Rp 4,8 juta/tahun, wajib dilaporkan juga.', true],
    ];
    @endphp

    @foreach($eduItems as $i => [$title, $icon, $body, $show])
    @if($show)
    <div style="border-radius:var(--radius-sm);border:1px solid var(--border);margin-bottom:6px;overflow:hidden">
        <button type="button" @click="open = open === {{ $i }} ? null : {{ $i }}"
                style="width:100%;display:flex;align-items:center;gap:12px;padding:13px 14px;background:none;border:none;cursor:pointer;text-align:left">
            <div style="width:28px;height:28px;border-radius:999px;background:var(--bg-elevated);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="fa-solid {{ $icon }}" style="font-size:12px;color:var(--accent)"></i>
            </div>
            <span style="flex:1;font-size:13px;font-weight:500;color:var(--text-primary)">{{ $title }}</span>
            <i class="fa-solid fa-chevron-down" style="font-size:11px;color:var(--text-dim);transition:transform .2s"
               :style="open === {{ $i }} ? 'transform:rotate(180deg)' : ''"></i>
        </button>
        <div x-show="open === {{ $i }}" x-transition style="padding:0 14px 14px 54px;font-size:13px;color:var(--text-muted);line-height:1.7">
            {{ $body }}
        </div>
    </div>
    @endif
    @endforeach
</div>

<div style="text-align:center;padding:20px 0;font-size:11px;color:var(--text-dim)">
    &copy; {{ date('Y') }} Sanggup Ga? &middot; Data: BPS Statistik Indonesia 2026 &middot; Kalkulasi deterministik berdasarkan input pengguna
</div>

<script>
(function() {
    const ctx = document.getElementById('distribusiChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: @json($chartLabels),
            datasets: [{
                data: @json($chartData),
                backgroundColor: ['#8b5cf6','#3b82f6','#10b981','#f97316','#ef4444','#eab308'],
                borderWidth: 0,
                hoverOffset: 6,
            }]
        },
        options: {
            responsive: true,
            cutout: '65%',
            plugins: { legend: { display: false }, tooltip: {
                callbacks: { label: ctx => ' Rp ' + ctx.raw.toLocaleString('id-ID') }
            }},
        }
    });
})();

function whatIfSim(baseMakan, baseTransport, baseHousing, baseTotal, gaji) {
    return {
        cookSelf: false,
        publicTransport: false,
        housingAdj: baseHousing,
        get simTotal() {
            const m = this.cookSelf ? baseMakan * 0.85 : baseMakan;
            const t = this.publicTransport ? baseTransport * 0.20 : baseTransport;
            const h = this.housingAdj;
            const other = baseTotal - baseMakan - baseTransport - baseHousing;
            return Math.round(m + t + h + other);
        },
        get simSisa() { return gaji - this.simTotal; },
    };
}
</script>
@endsection
