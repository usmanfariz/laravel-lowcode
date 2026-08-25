<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Basis untuk test yang benar-benar butuh MySQL.
 *
 * Sebagian engine membaca `information_schema` dan definisi kolom `ENUM` —
 * keduanya tidak ada padanannya di SQLite. Alih-alih memaksa seluruh suite
 * butuh MySQL, test seperti itu ditaruh di sini dan **dilewati** bila database
 * ujinya belum disiapkan.
 *
 * Untuk menyalakannya, jalankan sekali:
 *
 *     sudo mysql -e "CREATE DATABASE laravel_lowcode_test
 *         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
 *         GRANT ALL ON laravel_lowcode_test.* TO 'lowcode'@'127.0.0.1';"
 *
 * Nama database bisa diganti lewat env MYSQL_TEST_DATABASE.
 */
abstract class MysqlTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->beforeApplicationDestroyed(fn () => null);

        parent::setUp();
    }

    /**
     * Arahkan koneksi bawaan ke MySQL sebelum RefreshDatabase bekerja.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $database = env('MYSQL_TEST_DATABASE', 'laravel_lowcode_test');

        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', $database);

        if (! $this->mysqlSiap($database)) {
            $this->markTestSkipped(
                "Database MySQL uji '{$database}' tidak tersedia. "
                .'Lihat tests/MysqlTestCase.php untuk cara menyiapkannya.'
            );
        }
    }

    private function mysqlSiap(string $database): bool
    {
        if (! extension_loaded('pdo_mysql')) {
            return false;
        }

        try {
            DB::connection('mysql')->select('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
