<?php

namespace Database\Seeders;

use App\Models\QuickCommand;
use Illuminate\Database\Seeder;

/**
 * Seeds the quick_commands table with the default set of keyword aliases.
 *
 * Run via: php artisan db:seed --class=QuickCommandSeeder
 * Or add to DatabaseSeeder if you want it in a fresh install.
 *
 * Admins can add/edit/deactivate rows without a code deploy.
 */
class QuickCommandSeeder extends Seeder
{
    public function run(): void
    {
        $commands = [
            // Balance / saldo
            ['alias' => 'saldo',              'handler' => 'handleQueryBalance',  'description' => 'Cek saldo semua akun',          'sort_order' => 1],
            ['alias' => 'balance',            'handler' => 'handleQueryBalance',  'description' => 'Cek saldo (English)',            'sort_order' => 2],
            ['alias' => '/saldo',             'handler' => 'handleQueryBalance',  'description' => 'Cek saldo (slash command)',      'sort_order' => 3],
            ['alias' => '/balance',           'handler' => 'handleQueryBalance',  'description' => 'Cek saldo (slash English)',      'sort_order' => 4],
            ['alias' => 'cek saldo',          'handler' => 'handleQueryBalance',  'description' => 'Cek saldo (natural)',            'sort_order' => 5],
            ['alias' => 'lihat saldo',        'handler' => 'handleQueryBalance',  'description' => 'Lihat saldo',                   'sort_order' => 6],

            // Summary / ringkasan
            ['alias' => 'summary',            'handler' => 'sendQuickSummary',    'description' => 'Ringkasan hari ini',             'sort_order' => 10],
            ['alias' => 'ringkasan',          'handler' => 'sendQuickSummary',    'description' => 'Ringkasan hari ini (Bahasa)',    'sort_order' => 11],
            ['alias' => '/summary',           'handler' => 'sendQuickSummary',    'description' => 'Ringkasan (slash command)',      'sort_order' => 12],

            // Help / bantuan
            ['alias' => 'help',               'handler' => 'sendHelp',            'description' => 'Tampilkan bantuan',              'sort_order' => 20],
            ['alias' => 'bantuan',            'handler' => 'sendHelp',            'description' => 'Bantuan (Bahasa)',               'sort_order' => 21],
            ['alias' => '/help',              'handler' => 'sendHelp',            'description' => 'Bantuan (slash command)',        'sort_order' => 22],

            // Tagihan / bills
            ['alias' => 'tagihan',            'handler' => 'sendBillList',        'description' => 'Daftar tagihan',                 'sort_order' => 30],
            ['alias' => '/tagihan',           'handler' => 'sendBillList',        'description' => 'Daftar tagihan (slash)',         'sort_order' => 31],
            ['alias' => 'list tagihan',       'handler' => 'sendBillList',        'description' => 'Daftar tagihan (natural)',       'sort_order' => 32],
            ['alias' => 'daftar tagihan',     'handler' => 'sendBillList',        'description' => 'Daftar tagihan (natural 2)',     'sort_order' => 33],

            // Settings / pengaturan
            ['alias' => 'settings',           'handler' => 'sendSettingsLink',    'description' => 'Link ke dashboard settings',    'sort_order' => 40],
            ['alias' => '/settings',          'handler' => 'sendSettingsLink',    'description' => 'Settings (slash command)',       'sort_order' => 41],
            ['alias' => 'pengaturan',         'handler' => 'sendSettingsLink',    'description' => 'Pengaturan (Bahasa)',            'sort_order' => 42],
            ['alias' => 'ubah profil',        'handler' => 'sendSettingsLink',    'description' => 'Ubah profil',                   'sort_order' => 43],
            ['alias' => 'ganti pengaturan',   'handler' => 'sendSettingsLink',    'description' => 'Ganti pengaturan',              'sort_order' => 44],
        ];

        foreach ($commands as $cmd) {
            QuickCommand::updateOrCreate(
                ['alias' => $cmd['alias']],
                $cmd + ['is_active' => true]
            );
        }
    }
}
