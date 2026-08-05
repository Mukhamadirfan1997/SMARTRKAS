<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaksi_bku_has_performance_indexes(): void
    {
        $this->assertTrue(Schema::hasIndex('transaksi_bku', 'transaksi_bku_item_jenis_bulan_idx'));
        $this->assertTrue(Schema::hasIndex('transaksi_bku', 'transaksi_bku_jenis_bulan_idx'));
    }

    public function test_kwitansi_has_transaksi_bku_index(): void
    {
        $this->assertTrue(Schema::hasIndex('kwitansi', 'kwitansi_transaksi_bku_idx'));
    }

    public function test_import_log_has_tahun_bulan_index(): void
    {
        $this->assertTrue(Schema::hasIndex('import_log', 'import_log_tahun_bulan_idx'));
    }

    public function test_audit_log_has_created_at_index(): void
    {
        $this->assertTrue(Schema::hasIndex('audit_log', 'audit_log_created_at_idx'));
    }

    public function test_rkas_item_bulan_has_unique_index(): void
    {
        $this->assertTrue(Schema::hasIndex('rkas_item_bulan', ['rkas_item_id', 'bulan']));
    }
}
