<?php

namespace Tests\Unit;

use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Models\User;
use App\Support\NomorDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NomorDokumenTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private TahunAnggaran $tahun;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tahun = TahunAnggaran::factory()->create(['status' => true]);
    }

    private function makeTransaksi(string $noBukti, int $bulan, string $jenis = 'pengeluaran'): TransaksiBku
    {
        return TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'rkas_item_id' => null,
            'created_by' => $this->user->id,
            'no_bukti' => $noBukti,
            'bulan' => $bulan,
            'jenis' => $jenis,
        ]);
    }

    public function test_empty_year_starts_at_001(): void
    {
        $this->assertSame('BPU001/00000000/01/2026', NomorDokumen::noBukti('pengeluaran', '2026-01-15'));
        $this->assertSame('BBU001/00000000/01/2026', NomorDokumen::noBukti('penerimaan', '2026-01-15'));
    }

    public function test_sequential_across_months_not_restart(): void
    {
        // BPU001 di Januari, BPU002 di Februari.
        $this->makeTransaksi('BPU001/00000000/01/2026', 1);
        $this->makeTransaksi('BPU002/00000000/02/2026', 2);

        // Maret harus lanjut ke BPU003, bukan restart ke BPU001.
        $this->assertSame('BPU003/00000000/03/2026', NomorDokumen::noBukti('pengeluaran', '2026-03-15'));
    }

    public function test_sequential_bbu_across_months(): void
    {
        $this->makeTransaksi('BBU001/00000000/01/2026', 1, 'penerimaan');
        $this->makeTransaksi('BBU002/00000000/02/2026', 2, 'penerimaan');

        // BBU juga harus lanjut, bukan restart.
        $this->assertSame('BBU003/00000000/03/2026', NomorDokumen::noBukti('penerimaan', '2026-03-15'));
    }

    public function test_continues_from_highest_number_instead_of_filling_gap(): void
    {
        foreach (range(11, 17) as $i) {
            $this->makeTransaksi('BPU' . str_pad((string) $i, 3, '0', STR_PAD_LEFT) . '/00000000/02/2026', 2);
        }

        // Gap BPU001-010 tidak pernah ada -> lanjut dari BPU017.
        $this->assertSame('BPU018/00000000/02/2026', NomorDokumen::noBukti('pengeluaran', '2026-02-15'));
    }

    public function test_soft_deleted_number_not_reused(): void
    {
        $top = $this->makeTransaksi('BPU007/00000000/01/2026', 1);
        $this->makeTransaksi('BPU006/00000000/01/2026', 1);
        $top->delete();

        // Soft-deleted -> tidak reuse, lanjut ke 008.
        $this->assertSame('BPU008/00000000/01/2026', NomorDokumen::noBukti('pengeluaran', '2026-01-15'));
    }

    public function test_does_not_reuse_mid_sequence_deleted_number(): void
    {
        $mid = $this->makeTransaksi('BPU005/00000000/01/2026', 1);
        $this->makeTransaksi('BPU007/00000000/01/2026', 1);
        $mid->delete();

        // Nomor di tengah dihapus -> lanjut BPU008 (sama dgn per-tahun).
        $this->assertSame('BPU008/00000000/01/2026', NomorDokumen::noBukti('pengeluaran', '2026-01-15'));
    }

    public function test_next_seq_per_bulan_returns_same_for_all_months(): void
    {
        $this->makeTransaksi('BPU001/00000000/01/2026', 1);
        $this->makeTransaksi('BPU002/00000000/02/2026', 2);
        $this->makeTransaksi('BPU003/00000000/03/2026', 3);

        $hints = NomorDokumen::nextSeqPerBulan('pengeluaran');
        $this->assertCount(12, $hints);

        // Semua bulan return value sama (seq per tahun, bukan per bulan).
        $this->assertSame(4, $hints[1]);
        $this->assertSame(4, $hints[2]);
        $this->assertSame(4, $hints[3]);
        $this->assertSame(4, $hints[4]);
        $this->assertSame(4, $hints[12]);
    }

    public function test_no_bukti_uses_correct_month_in_format(): void
    {
        $this->makeTransaksi('BPU001/00000000/01/2026', 1);
        $this->makeTransaksi('BPU002/00000000/02/2026', 2);

        // Seq lanjut (3), tapi bulan di format harus sesuai tanggal.
        $this->assertSame('BPU003/00000000/06/2026', NomorDokumen::noBukti('pengeluaran', '2026-06-15'));
    }

    public function test_prefix_bbu_and_bpu_are_independent(): void
    {
        $this->makeTransaksi('BPU001/00000000/01/2026', 1, 'pengeluaran');
        $this->makeTransaksi('BPU002/00000000/02/2026', 2, 'pengeluaran');
        $this->makeTransaksi('BBU001/00000000/01/2026', 1, 'penerimaan');
        $this->makeTransaksi('BBU002/00000000/03/2026', 3, 'penerimaan');

        // BPU lanjut dari 002 -> 003
        $this->assertSame('BPU003/00000000/04/2026', NomorDokumen::noBukti('pengeluaran', '2026-04-15'));
        // BBU lanjut dari 002 -> 003 (independen dari BPU)
        $this->assertSame('BBU003/00000000/05/2026', NomorDokumen::noBukti('penerimaan', '2026-05-15'));
    }
}
