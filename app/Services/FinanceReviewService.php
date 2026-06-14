<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Debt;
use App\Models\Entry;
use App\Models\FinanceReviewProfile;
use App\Models\Fund;
use App\Models\RecurringEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FinanceReviewService
{
    // BPS 2026 UMK data (approximate)
    private const UMK = [
        'jakarta'    => ['label' => 'Jakarta',    'umk' => 5067381],
        'surabaya'   => ['label' => 'Surabaya',   'umk' => 4638951],
        'batam'      => ['label' => 'Batam',      'umk' => 4244880],
        'bekasi'     => ['label' => 'Bekasi',     'umk' => 5690752],
        'bogor'      => ['label' => 'Bogor',      'umk' => 4813988],
        'bandung'    => ['label' => 'Bandung',    'umk' => 4168000],
        'semarang'   => ['label' => 'Semarang',   'umk' => 3454827],
        'yogyakarta' => ['label' => 'Yogyakarta', 'umk' => 2492811],
        'solo'       => ['label' => 'Solo',       'umk' => 2269070],
        'medan'      => ['label' => 'Medan',      'umk' => 3769082],
        'makassar'   => ['label' => 'Makassar',   'umk' => 3800000],
        'palembang'  => ['label' => 'Palembang',  'umk' => 3456874],
        'depok'      => ['label' => 'Depok',      'umk' => 5195721],
        'tangerang'  => ['label' => 'Tangerang',  'umk' => 4460758],
        'malang'     => ['label' => 'Malang',     'umk' => 3309144],
    ];

    // Relative cost-of-living multipliers (vs national average = 1.0) for makan+transport
    private const COL_FACTOR = [
        'jakarta'    => 1.35,
        'surabaya'   => 1.10,
        'batam'      => 1.15,
        'bekasi'     => 1.20,
        'bogor'      => 1.05,
        'bandung'    => 1.00,
        'semarang'   => 0.90,
        'yogyakarta' => 0.80,
        'solo'       => 0.75,
        'medan'      => 0.95,
        'makassar'   => 0.95,
        'palembang'  => 0.88,
        'depok'      => 1.18,
        'tangerang'  => 1.22,
        'malang'     => 0.82,
    ];

    public function __construct(private readonly OpenRouterClient $ai) {}

    public function getOrCreateProfile(User $user): FinanceReviewProfile
    {
        return FinanceReviewProfile::firstOrCreate(['user_id' => $user->id]);
    }

    // ── Auto-fill helpers ──────────────────────────────────────────────────

    public function getAutoFilledBills(User $user): array
    {
        return Bill::where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->map(fn($b) => [
                'id'       => $b->id,
                'name'     => $b->name,
                'amount'   => $b->amount,
                'included' => true,
            ])
            ->values()
            ->all();
    }

    public function getAutoFilledRecurring(User $user): array
    {
        return RecurringEntry::where('user_id', $user->id)
            ->where('frequency', 'monthly')
            ->where('type', 'expense')
            ->where('is_active', true)
            ->get()
            ->map(fn($r) => [
                'id'       => $r->id,
                'name'     => $r->description,
                'amount'   => (int) $r->amount,
                'included' => true,
            ])
            ->values()
            ->all();
    }

    public function getAutoFilledDebts(User $user): array
    {
        return Debt::where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->map(fn($d) => [
                'id'       => $d->id,
                'name'     => $d->name,
                'amount'   => $d->monthly_installment,
                'included' => true,
            ])
            ->values()
            ->all();
    }

    public function getFoodEstimate(User $user): int
    {
        $since = now()->subDays(30);
        $total = Entry::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereNotNull('confirmed_at')
            ->where('is_undone', false)
            ->where('entry_time', '>=', $since)
            ->where(function ($q) {
                $q->where('category', 'like', '%makan%')
                  ->orWhere('category', 'like', '%food%')
                  ->orWhere('category', 'like', '%makanan%');
            })
            ->sum('amount');

        return (int) $total;
    }

    public function getTransportEstimate(User $user): int
    {
        $since = now()->subDays(30);
        $total = Entry::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereNotNull('confirmed_at')
            ->where('is_undone', false)
            ->where('entry_time', '>=', $since)
            ->where(function ($q) {
                $q->where('category', 'like', '%transport%')
                  ->orWhere('category', 'like', '%ojek%')
                  ->orWhere('category', 'like', '%bensin%');
            })
            ->sum('amount');

        return (int) $total;
    }

    public function hangoutEstimate(string $frequency): int
    {
        return match($frequency) {
            '1-2x'        => 200000,
            '3-4x'        => 430000,
            'setiap-hari' => 800000,
            default       => 0,
        };
    }

    // ── Breakdown calculation ──────────────────────────────────────────────

    public function calculateBreakdown(FinanceReviewProfile $profile): array
    {
        $gaji = (int) ($profile->gaji_bersih ?? 0);

        // Makan & Minum
        $makan = (int) ($profile->food_base_monthly ?? 0)
               + $this->hangoutEstimate($profile->hangout_frequency ?? 'jarang');

        // Tempat Tinggal
        $housing = $profile->housing_status === 'ortu' ? 0 : (int) ($profile->housing_cost ?? 0);

        // Transportasi
        $transport = (int) ($profile->transport_monthly ?? 0);

        // Tagihan & Langganan
        $tagihan = 0;
        foreach ((array) ($profile->bills_snapshot ?? []) as $b) {
            if ($b['included'] ?? false) $tagihan += (int) $b['amount'];
        }
        foreach ((array) ($profile->recurring_snapshot ?? []) as $r) {
            if ($r['included'] ?? false) $tagihan += (int) $r['amount'];
        }

        // Cicilan
        $cicilan = 0;
        foreach ((array) ($profile->debts_snapshot ?? []) as $d) {
            if ($d['included'] ?? false) $cicilan += (int) $d['amount'];
        }

        // Gaya Hidup & Tanggungan
        $tanggunganTotal = 0;
        if (!empty($profile->tanggungan_snapshot)) {
            foreach ((array) $profile->tanggungan_snapshot as $t) {
                $tanggunganTotal += (int) ($t['amount'] ?? 0);
            }
        } else {
            $tanggunganTotal = (int) ($profile->family_remittance ?? 0);
        }

        $gayaHidup = $tanggunganTotal
                   + (int) ($profile->rokok_monthly ?? 0)
                   + (int) ($profile->gym_monthly ?? 0)
                   + (int) ($profile->asuransi_monthly ?? 0)
                   + (int) round(($profile->mudik_annual ?? 0) / 12);

        $total = $makan + $housing + $transport + $tagihan + $cicilan + $gayaHidup;
        $sisa  = $gaji - $total;

        $pct = fn(int $v) => $gaji > 0 ? round($v / $gaji * 100, 1) : 0;

        return [
            'gaji'          => $gaji,
            'makan'         => ['label' => 'Makanan & Minum',        'amount' => $makan,    'pct' => $pct($makan)],
            'transport'     => ['label' => 'Transportasi',           'amount' => $transport, 'pct' => $pct($transport)],
            'tempat_tinggal'=> ['label' => 'Tempat Tinggal',         'amount' => $housing,   'pct' => $pct($housing)],
            'tagihan'       => ['label' => 'Tagihan & Langganan',    'amount' => $tagihan,   'pct' => $pct($tagihan)],
            'cicilan'       => ['label' => 'Cicilan & Hutang',       'amount' => $cicilan,   'pct' => $pct($cicilan)],
            'gaya_hidup'    => ['label' => 'Tanggungan & Gaya Hidup','amount' => $gayaHidup, 'pct' => $pct($gayaHidup)],
            'total'         => $total,
            'total_pct'     => $pct($total),
            'sisa'          => $sisa,
            'sisa_pct'      => $pct($sisa),
        ];
    }

    // ── Tangga Finansial ───────────────────────────────────────────────────

    public function getTangga(array $breakdown): array
    {
        $sisaPct = $breakdown['sisa_pct'];
        $sisa    = $breakdown['sisa'];
        $gaji    = $breakdown['gaji'];

        if ($sisaPct >= 35) {
            $level = 5;
            $label = 'Bertumbuh';
            $desc  = 'Fondasi finansialmu kuat. Uang sudah bekerja untukmu. Waktunya membangun masa depan secara aktif.';
            $fokus = 'Investasi rutin dan konsisten untuk tujuan jangka panjang.';
            $target = 'Targetkan alokasi investasi rutin — mulai dari 10% gaji (Rp ' . number_format($gaji * 0.10, 0, ',', '.') . '/bulan) sebagai target awal.';
            $langkah = [
                'Bagi tiga dana: Investasi rutin + Tujuan jangka menengah (Buruut, properti)',
                'Diversifikasi instrumen investasi sesuai horizon waktu dan profil risiko',
                'Review rencana keuangan setiap 6 bulan dan sesuaikan dengan perubahan hidup',
            ];
        } elseif ($sisaPct >= 25) {
            $level = 4;
            $label = 'Aman & Proteksi';
            $desc  = 'Keuanganmu sudah stabil dan ada ruang untuk proteksi diri. Saatnya memastikan kamu terlindungi dari risiko.';
            $fokus = 'Lengkapi proteksi: asuransi jiwa, kesehatan, dan mulai investasi rutin.';
            $target = 'Pastikan premi asuransi ≤ 10% gaji dan mulai investasi minimal Rp ' . number_format($gaji * 0.05, 0, ',', '.') . '/bulan.';
            $langkah = [
                'Bandingkan dan ambil asuransi kesehatan jika belum punya',
                'Buka rekening investasi (reksa dana atau saham) dan auto-debit setiap bulan',
                'Naikkan dana darurat ke target 6 bulan pengeluaran',
            ];
        } elseif ($sisaPct >= 15) {
            $level = 3;
            $label = 'Mulai Menabung';
            $desc  = 'Kamu sudah bisa menabung secara rutin. Langkah ini penting sebelum naik ke level proteksi.';
            $fokus = 'Bangun dana darurat 3 bulan pengeluaran dan biasakan menabung otomatis.';
            $target = 'Target dana darurat: Rp ' . number_format($breakdown['total'] * 3, 0, ',', '.') . ' (3× pengeluaran bulanan).';
            $langkah = [
                'Otomasi tabungan — transfer ke rekening terpisah di hari gajian',
                'Kurangi pengeluaran variabel (nongkrong, belanja impulsif) minimal 10%',
                'Setelah 3 bulan darurat terpenuhi, lanjutkan ke asuransi dasar',
            ];
        } elseif ($sisaPct >= 5) {
            $level = 2;
            $label = 'Stabil';
            $desc  = 'Kamu bisa memenuhi kebutuhan dasar tapi ruang gerak finansialmu masih terbatas.';
            $fokus = 'Kurangi beban pengeluaran dan cari cara naikkan pemasukan.';
            $target = 'Naikkan sisa dana ke ≥ 15% gaji (Rp ' . number_format($gaji * 0.15, 0, ',', '.') . '/bulan).';
            $langkah = [
                'Review semua langganan dan tagihan — matikan yang tidak terpakai',
                'Negosiasi cicilan atau cari refinancing dengan bunga lebih rendah',
                'Cari sumber penghasilan tambahan: freelance, jual produk, skill monetization',
            ];
        } else {
            $level = 1;
            $label = 'Bertahan Hidup';
            $desc  = 'Pengeluaranmu mendekati atau melebihi pemasukan. Ini kondisi darurat yang perlu ditangani segera.';
            $fokus = 'Kurangi pengeluaran non-esensial dan stabilkan arus kas.';
            $target = 'Prioritas: nol defisit dulu. Target sisa minimal Rp ' . number_format($gaji * 0.05, 0, ',', '.') . '/bulan.';
            $langkah = [
                'Buat daftar pengeluaran wajib vs tidak wajib — potong semua yang tidak wajib',
                'Hindari utang baru apapun sampai kondisi membaik',
                'Pertimbangkan pindah ke tempat tinggal lebih murah atau tingkatkan income',
            ];
        }

        $levels = [
            5 => 'Bertumbuh',
            4 => 'Aman & Proteksi',
            3 => 'Mulai Menabung',
            2 => 'Stabil',
            1 => 'Bertahan Hidup',
        ];

        return compact('level', 'label', 'desc', 'fokus', 'target', 'langkah', 'levels');
    }

    // ── Rasio cards ────────────────────────────────────────────────────────

    public function getRatioCards(array $breakdown, FinanceReviewProfile $profile): array
    {
        $gaji = $breakdown['gaji'];

        // Card 1: Rasio Kebutuhan Pokok
        $pokok = $breakdown['makan']['amount'] + $breakdown['transport']['amount'] + $breakdown['tempat_tinggal']['amount'];
        $pokokPct = $gaji > 0 ? round($pokok / $gaji * 100, 1) : 0;
        $pokokStatus = $pokokPct <= 55 ? 'baik' : ($pokokPct <= 70 ? 'perhatian' : 'bahaya');

        // Card 2: Rasio Tabungan (sisa = potential savings)
        $tabunganPct = $breakdown['sisa_pct'];
        $tabunganStatus = $tabunganPct >= 20 ? 'baik' : ($tabunganPct >= 10 ? 'perhatian' : 'bahaya');

        // Card 3: Dana Darurat
        $target = $breakdown['total'] * 6;
        $emergencyFund = Fund::where('user_id', $profile->user_id)
            ->where('fund_type', 'emergency_fund')
            ->where('is_active', true)
            ->sum('current_balance');

        $emergencyPct = $target > 0 ? round($emergencyFund / $target * 100) : 0;
        $emergencyStatus = $emergencyFund >= $target ? 'baik'
                         : ($emergencyFund >= $target * 0.5 ? 'perhatian' : 'bahaya');

        return [
            'pokok' => [
                'title'    => 'Rasio Kebutuhan Pokok',
                'value'    => $pokokPct . '%',
                'target'   => 'Target: < 55%',
                'status'   => $pokokStatus,
                'note'     => 'Makan + Transport + Tempat Tinggal',
                'sub'      => $pokokStatus === 'baik' ? 'Komposisi kebutuhan pokokmu sudah cukup sehat.' : 'Kebutuhan pokok mendominasi pengeluaran. Coba evaluasi salah satu komponen.',
            ],
            'tabungan' => [
                'title'    => 'Rasio Tabungan',
                'value'    => $tabunganPct . '%',
                'target'   => 'Target: ≥ 20%',
                'status'   => $tabunganStatus,
                'note'     => 'Sisa dari gaji setelah semua pengeluaran',
                'sub'      => $tabunganStatus === 'baik' ? 'Rasio tabunganmu sudah di atas target 20%. Pertahankan!' : 'Rasio tabungan masih di bawah target. Usahakan kurangi satu kategori pengeluaran.',
            ],
            'darurat' => [
                'title'    => 'Dana Darurat',
                'value'    => 'Rp ' . ($emergencyFund >= 1000000 ? number_format($emergencyFund / 1000000, 1, ',', '.') . ' Jt' : number_format($emergencyFund, 0, ',', '.')),
                'target'   => 'Target: Rp ' . number_format($target / 1000000, 1, ',', '.') . ' Jt (6× pengeluaran)',
                'status'   => $emergencyStatus,
                'note'     => $emergencyPct . '% terpenuhi',
                'sub'      => $emergencyStatus === 'baik' ? 'Dana darurat sudah cukup. Lanjutkan ke investasi.' : 'Prioritaskan mengisi dana darurat sebelum investasi lainnya.',
            ],
        ];
    }

    // ── Alternatif Kota ────────────────────────────────────────────────────

    public function getAlternatifKota(FinanceReviewProfile $profile, array $breakdown): array
    {
        $currentKey   = $profile->domisili_key ?? '';
        $currentFactor = self::COL_FACTOR[$currentKey] ?? 1.0;
        $currentSisa   = $breakdown['sisa'];
        $gaji          = $breakdown['gaji'];

        // Base variable costs (makan + transport) that scale with city CoL
        $variableCosts = $breakdown['makan']['amount'] + $breakdown['transport']['amount'];
        $fixed         = $breakdown['total'] - $variableCosts;

        $results = [];
        foreach (self::UMK as $key => $data) {
            if ($key === $currentKey) continue;
            $factor   = self::COL_FACTOR[$key] ?? 1.0;
            $adjusted = (int) ($variableCosts * ($factor / max($currentFactor, 0.01)));
            $sisaKota = $gaji - $fixed - $adjusted;
            $selisih  = $sisaKota - $currentSisa;

            $results[] = ['kota' => $data['label'], 'selisih_sisa' => $selisih];
        }

        usort($results, fn($a, $b) => $b['selisih_sisa'] - $a['selisih_sisa']);

        return array_slice($results, 0, 6);
    }

    // ── UMK helpers ────────────────────────────────────────────────────────

    public function getUmkData(string $key): array
    {
        return self::UMK[$key] ?? ['label' => 'Tidak Diketahui', 'umk' => 0];
    }

    public static function normalizeDomisili(string $raw): string
    {
        $map = [
            'jakarta'     => ['jakarta', 'jkt', 'dki'],
            'surabaya'    => ['surabaya', 'sby'],
            'batam'       => ['batam'],
            'bekasi'      => ['bekasi'],
            'bogor'       => ['bogor'],
            'bandung'     => ['bandung', 'bdg'],
            'semarang'    => ['semarang', 'smg'],
            'yogyakarta'  => ['yogyakarta', 'yogya', 'jogja', 'jogjakarta'],
            'solo'        => ['solo', 'surakarta'],
            'medan'       => ['medan'],
            'makassar'    => ['makassar', 'ujung pandang'],
            'palembang'   => ['palembang'],
            'depok'       => ['depok'],
            'tangerang'   => ['tangerang'],
            'malang'      => ['malang'],
        ];

        $lower = strtolower(trim($raw));
        foreach ($map as $key => $aliases) {
            foreach ($aliases as $alias) {
                if (str_contains($lower, $alias)) return $key;
            }
        }
        return '';
    }

    // ── AI insights ────────────────────────────────────────────────────────

    public function generateInsights(FinanceReviewProfile $profile, array $breakdown): string
    {
        $gaji     = 'Rp ' . number_format($breakdown['gaji'], 0, ',', '.');
        $sisaPct  = $breakdown['sisa_pct'];
        $lines    = [];

        foreach (['makan', 'transport', 'tempat_tinggal', 'tagihan', 'cicilan', 'gaya_hidup'] as $k) {
            $item   = $breakdown[$k];
            $lines[] = "- {$item['label']}: Rp " . number_format($item['amount'], 0, ',', '.') . " ({$item['pct']}% gaji)";
        }
        $lines[] = "- Total: Rp " . number_format($breakdown['total'], 0, ',', '.') . " ({$breakdown['total_pct']}% gaji)";
        $lines[] = "- Sisa: Rp " . number_format($breakdown['sisa'], 0, ',', '.') . " ({$sisaPct}% gaji)";

        $domisili = ucfirst($profile->domisili ?? 'tidak diketahui');
        $system   = <<<PROMPT
Kamu adalah konsultan keuangan personal yang membantu anak muda Indonesia memahami kondisi finansial mereka.
Berikan analisis ringkas, jujur, dan empati dalam Bahasa Indonesia yang natural (bukan formal kaku).
Tujuan: bantu pengguna memahami kondisi mereka dan apa yang perlu diperbaiki.
Format: 3 paragraf singkat. Paragraf 1: gambaran kondisi keseluruhan. Paragraf 2: identifikasi 1-2 area yang perlu perhatian. Paragraf 3: 3 langkah konkret yang bisa dilakukan bulan ini.
Batasan: maksimal 250 kata. Jangan gunakan bullet points atau header. Jangan terlalu memuji. Jangan gunakan kata "luar biasa" atau "fantastis".
Benchmark: Makan ≤40%, Cicilan ≤30%, Transport ≤15%, Tagihan ≤20% gaji.
PROMPT;

        $userMsg = "Gaji bersih: {$gaji}. Domisili: {$domisili}.\n" . implode("\n", $lines);

        try {
            $result = $this->ai->generateText($system, $userMsg, 600);
            return $result['text'] ?? 'Analisis tidak tersedia saat ini.';
        } catch (\Exception $e) {
            Log::warning('FinanceReviewService: AI insights failed', ['error' => $e->getMessage()]);
            return 'Analisis sedang tidak tersedia. Silakan coba lagi nanti dengan klik "Perbarui Analisis".';
        }
    }
}
