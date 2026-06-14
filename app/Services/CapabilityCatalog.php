<?php

namespace App\Services;

use App\Models\User;

/**
 * CapabilityCatalog — the single source of truth for "what can Butler do?".
 *
 * Drives BOTH surfaces so they never drift apart:
 *   • Telegram  — MessageRouter::sendHelp() renders these groups as text.
 *   • Web       — the Help tab (/dashboard/help) renders them as cards.
 *
 * Each item declares the `modes` it belongs to (finance / calorie) so help
 * can be filtered to what the user actually tracks. Items with both modes
 * (or 'all') always show.
 *
 * NOTE: this is the *display* catalog (rich descriptions + examples).
 * CommandSuggestionService keeps its own lean trigger catalog tuned for
 * fuzzy "did you mean?" matching — the two serve different jobs.
 */
class CapabilityCatalog
{
    /**
     * Full grouped catalog. Each group:
     *   ['key', 'label', 'icon', 'items' => [ ['label','desc','examples','modes'] ]]
     */
    public function groups(): array
    {
        return [
            [
                'key'   => 'catat_uang',
                'label' => 'Catat Keuangan',
                'icon'  => 'fa-pen-to-square',
                'items' => [
                    ['label' => 'Pengeluaran', 'modes' => ['finance'],
                     'desc' => 'Catat uang keluar. Butler kira-kira kategorinya otomatis.',
                     'examples' => ['makan siang 35k', 'grab ke kantor 23rb', 'beli kopi 18000']],
                    ['label' => 'Pemasukan', 'modes' => ['finance'],
                     'desc' => 'Catat uang masuk seperti gaji, bonus, atau hasil freelance.',
                     'examples' => ['gajian 5jt', 'dapet bonus 500rb', 'freelance 1.2jt']],
                    ['label' => 'Tabungan', 'modes' => ['finance'],
                     'desc' => 'Sisihkan uang ke tabungan atau dana tertentu.',
                     'examples' => ['nabung 500rb', 'nabung 200k ke dana darurat']],
                    ['label' => 'Bayar Tagihan', 'modes' => ['finance'],
                     'desc' => 'Tandai tagihan rutin sudah dibayar.',
                     'examples' => ['bayar kos 1.5jt', 'bayar listrik 200rb', 'bayar netflix 65rb']],
                    ['label' => 'Bayar Cicilan', 'modes' => ['finance'],
                     'desc' => 'Catat pembayaran cicilan / hutang.',
                     'examples' => ['bayar cicilan motor 800rb', 'bayar paylater 350rb']],
                    ['label' => 'Pindah / Transfer Dana', 'modes' => ['finance'],
                     'desc' => 'Pindah uang antar akun, terima transfer, atau transfer keluar. '
                             . 'Kalau Butler belum tahu dari akun mana, dia akan tanya dulu.',
                     'examples' => ['transfer 100k ke BCA', 'terima 50k ke gopay', 'pindahin 200k dari gopay ke tabungan']],
                    ['label' => 'Setor Sinking Fund', 'modes' => ['finance'],
                     'desc' => 'Isi dana yang kamu kumpulkan untuk tujuan tertentu.',
                     'examples' => ['masukin 200k ke nabung liburan', 'setor 500rb ke dana nikah']],
                ],
            ],
            [
                'key'   => 'catat_sehat',
                'label' => 'Catat Kesehatan',
                'icon'  => 'fa-heart-pulse',
                'items' => [
                    ['label' => 'Makanan', 'modes' => ['calorie'],
                     'desc' => 'Catat makanan, Butler estimasi kalorinya. Koreksi kapan saja kalau meleset.',
                     'examples' => ['makan nasi goreng', 'sarapan roti 2 lembar', 'nasi padang ayam']],
                    ['label' => 'Mood', 'modes' => ['calorie'],
                     'desc' => 'Catat perasaan & level energi harian.',
                     'examples' => ['mood: good, energi 4', 'mood lagi capek']],
                ],
            ],
            [
                'key'   => 'atur',
                'label' => 'Atur Keuangan',
                'icon'  => 'fa-sliders',
                'items' => [
                    ['label' => 'Tambah Tagihan Rutin', 'modes' => ['finance'],
                     'desc' => 'Daftarkan tagihan bulanan supaya Butler ingatkan menjelang jatuh tempo.',
                     'examples' => ['tambahin tagihan Netflix 65rb tiap tgl 15', 'tagihan baru wifi 350rb tgl 5']],
                    ['label' => 'Buat Goal / Sinking Fund', 'modes' => ['finance'],
                     'desc' => 'Bikin target tabungan dengan nominal & deadline.',
                     'examples' => ['bikin goal liburan bali 5jt', 'buat dana nikah 50jt']],
                    ['label' => 'Buat Pengingat', 'modes' => ['all'],
                     'desc' => 'Minta Butler mengingatkanmu di waktu tertentu.',
                     'examples' => ['ingetin bayar listrik tiap tgl 5', 'ingetin minum air jam 2 siang']],
                ],
            ],
            [
                'key'   => 'cek',
                'label' => 'Cek & Lihat',
                'icon'  => 'fa-magnifying-glass',
                'items' => [
                    ['label' => 'Cek Saldo', 'modes' => ['finance'],
                     'desc' => 'Lihat semua dana, budget harian, tagihan, dan cicilan sekaligus.',
                     'examples' => ['saldo', 'cek saldo', 'lihat semua dana']],
                    ['label' => 'Ringkasan Hari', 'modes' => ['all'],
                     'desc' => 'Rekap singkat aktivitas hari ini.',
                     'examples' => ['ringkasan', 'summary']],
                    ['label' => 'Lihat Pengeluaran', 'modes' => ['finance'],
                     'desc' => 'Total pengeluaran hari ini atau bulan ini.',
                     'examples' => ['pengeluaran bulan ini', 'udah habis berapa hari ini']],
                    ['label' => 'Daftar Tagihan', 'modes' => ['finance'],
                     'desc' => 'Semua tagihan tetap beserta status bayarnya.',
                     'examples' => ['tagihan', 'daftar tagihan']],
                    ['label' => 'Saran Budget', 'modes' => ['finance'],
                     'desc' => 'Rekomendasi budget berdasarkan pola pengeluaranmu.',
                     'examples' => ['saran budget', 'saran keuangan']],
                ],
            ],
            [
                'key'   => 'lainnya',
                'label' => 'Lainnya',
                'icon'  => 'fa-ellipsis',
                'items' => [
                    ['label' => 'Scan Struk', 'modes' => ['finance'],
                     'desc' => 'Kirim foto struk belanja, Butler baca total & itemnya otomatis.',
                     'examples' => ['(kirim foto struk)']],
                    ['label' => 'Buka Dashboard', 'modes' => ['all'],
                     'desc' => 'Link ke dashboard web lengkap (grafik, riwayat, dll).',
                     'examples' => ['buka dashboard']],
                    ['label' => 'Pengaturan', 'modes' => ['all'],
                     'desc' => 'Ubah profil, budget, tujuan kalori, dan notifikasi.',
                     'examples' => ['settings', 'pengaturan']],
                    ['label' => 'Hubungkan iPhone / Shortcut', 'modes' => ['all'],
                     'desc' => 'Pasang Shortcut supaya bisa catat dari Siri / layar utama.',
                     'examples' => ['/pair_iphone']],
                    ['label' => 'Perangkat Saya', 'modes' => ['all'],
                     'desc' => 'Lihat & kelola perangkat yang terhubung.',
                     'examples' => ['/my_devices']],
                    ['label' => 'Bantuan', 'modes' => ['all'],
                     'desc' => 'Tampilkan daftar semua yang bisa Butler lakukan.',
                     'examples' => ['help', 'butler bisa apa aja']],
                ],
            ],
        ];
    }

    /**
     * Groups filtered to a user's tracking mode, with empty groups removed.
     * An item shows when its `modes` contains 'all', or the user's mode.
     */
    public function groupsForUser(User $user): array
    {
        $userModes = [];
        if ($user->isFinanceMode()) $userModes[] = 'finance';
        if ($user->isCalorieMode()) $userModes[] = 'calorie';
        if (empty($userModes)) $userModes = ['finance']; // sensible default

        $out = [];
        foreach ($this->groups() as $group) {
            $items = array_values(array_filter($group['items'], function ($item) use ($userModes) {
                if (in_array('all', $item['modes'], true)) return true;
                return count(array_intersect($item['modes'], $userModes)) > 0;
            }));
            if ($items) {
                $group['items'] = $items;
                $out[] = $group;
            }
        }
        return $out;
    }
}
