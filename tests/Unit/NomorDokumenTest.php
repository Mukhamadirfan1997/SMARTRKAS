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

    public function test_empty_month_starts_at_001(): void
    {
        $this->assertSame('BPU001/00000000/01/2026', NomorDokumen::noBukti('pengeluaran', '2026-01-15'));
        $this->assertSame('BBU001/00000000/01/2026', NomorDokumen::noBukti('penerimaan', '2026-01-15'));
    }

    public function test_continues_from_highest_number_in_month_instead_of_filling_gap(): void
    {
        foreach (range(11, 17) as $i) {
            $this->makeTransaksi('BPU' . str_pad((string) $i, 3, '0', STR_PAD_LEFT) . '/00000000/02/2026', 2);
        }

        // BPU001-010 bulan 2 tidak pernah ada (gap), tapi bukan nomor "berikutnya".
        $this->assertSame('BPU018/00000000/02/2026', NomorDokumen::noBukti('pengeluaran', '2026-02-15'));
    }

    public function test_reuses_soft_deleted_top_number(): void
    {
        $top = $this->makeTransaksi('BPU007/00000000/01/2026', 1);
        $this->makeTransaksi('BPU006/00000000/01/2026', 1);
        $top->delete();

        // Nomor teratas dihapus (soft-delete) -> boleh dipakai ulang.
        $this->assertSame('BPU007/00000000/01/2026', NomorDokumen::noBukti('pengeluaran', '2026-01-15'));
    }

    public function test_does_not_reuse_mid_sequence_deleted_number(): void
    {
        $mid = $this->makeTransaksi('BPU005/00000000/01/2026', 1);
        $this->makeTransaksi('BPU007/00000000/01/2026', 1);
        $mid->delete();

        // Nomor di tengah yang dihapus TIDAK diisi ulang -> lanjut BPU008.
        $this->assertSame('BPU008/00000000/01/2026', NomorDokumen::noBukti('pengeluaran', '2026-01-15'));
    }

    public function test_next_seq_per_bulan_provides_preview_hints_for_all_months(): void
    {
        foreach (range(1, 3) as $m) {
            $this->makeTransaksi('BPU001/00000000/' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . '/2026', $m);
        }

        $hints = NomorDokumen::nextSeqPerBulan('pengeluaran');
        $this->assertCount(12, $hints);
        $this->assertSame(2, $hints[1]);
        $this->assertSame(2, $hints[2]);
        $this->assertSame(2, $hints[3]);
        $this->assertSame(1, $hints[4]);
    }
}
