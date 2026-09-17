<?php

namespace Tests\Feature\Juknis;

use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Support\JuknisValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JuknisValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_validator_honor_tahun_filter(): void
    {
        $ta2026 = TahunAnggaran::factory()->create(['tahun' => 2026, 'status' => true]);
        $ta2025 = TahunAnggaran::factory()->create(['tahun' => 2025, 'status' => false]);

        $prog = MasterProgram::factory()->create(['kode' => '07.12.01.']);
        $rek = MasterKodeRekening::factory()->create(['kode' => '5.1.02.02.01.0013']);

        // Item honor di 2026
        RkasItem::factory()->create([
            'tahun_anggaran_id' => $ta2026->id,
            'program_id' => $prog->id,
            'kode_rekening_id' => $rek->id,
            'jumlah' => 1_000_000,
        ]);
        // Item honor di 2025 (harus TIDAK ikut di validator 2026)
        RkasItem::factory()->create([
            'tahun_anggaran_id' => $ta2025->id,
            'program_id' => $prog->id,
            'kode_rekening_id' => $rek->id,
            'jumlah' => 9_000_000,
        ]);

        $totalPagu2026 = (float) RkasItem::where('tahun_anggaran_id', $ta2026->id)->sum('jumlah');
        $v = new JuknisValidator($totalPagu2026, 2026, $ta2026->id);
        $r = $v->validateHonor();

        // Denominator 2026 = full (1jt), bukan 10jt -> persentase 0 karena belum realisasi
        $this->assertSame('Honor', $r['nama']);
        $this->assertSame(0.0, $r['persentase']);
        $this->assertFalse($r['isError']);
    }

    public function test_validator_buku_dan_sarpras_tahun_filter(): void
    {
        $ta = TahunAnggaran::factory()->create(['tahun' => 2026, 'status' => true]);
        $taLain = TahunAnggaran::factory()->create(['tahun' => 2025, 'status' => false]);

        // Buku: kode kegiatan 03.02.02.
        $progBuku = MasterProgram::factory()->create(['kode' => '03.02.02.']);
        RkasItem::factory()->create(['tahun_anggaran_id' => $ta->id, 'program_id' => $progBuku->id, 'jumlah' => 200_000]);
        RkasItem::factory()->create(['tahun_anggaran_id' => $taLain->id, 'program_id' => $progBuku->id, 'jumlah' => 800_000]);

        // Sarpras
        $progSarpras = MasterProgram::factory()->create(['kode' => '05.08.01.']);
        $rekSarpras = MasterKodeRekening::factory()->create(['kode' => '5.1.02.01.01.0001']);
        RkasItem::factory()->create(['tahun_anggaran_id' => $ta->id, 'program_id' => $progSarpras->id, 'kode_rekening_id' => $rekSarpras->id, 'jumlah' => 100_000]);
        RkasItem::factory()->create(['tahun_anggaran_id' => $taLain->id, 'program_id' => $progSarpras->id, 'kode_rekening_id' => $rekSarpras->id, 'jumlah' => 900_000]);

        $totalPagu = (float) RkasItem::where('tahun_anggaran_id', $ta->id)->sum('jumlah');
        $v = new JuknisValidator($totalPagu, 2026, $ta->id);

        $buku = $v->validateBuku();
        // 200k / 300k total pagu = 66.6% -> di atas threshold 10 -> sesuai
        $this->assertFalse($buku['isError']);

        $sarpras = $v->validateSarpras();
        $this->assertSame('Sarpras', $sarpras['nama']);
    }

    public function test_validate_all_returns_three(): void
    {
        $ta = TahunAnggaran::factory()->create(['tahun' => 2026, 'status' => true]);
        $totalPagu = (float) RkasItem::where('tahun_anggaran_id', $ta->id)->sum('jumlah');
        $v = new JuknisValidator($totalPagu, 2026, $ta->id);
        $all = $v->validateAll();
        $this->assertCount(3, $all);
        $this->assertSame(['Honor', 'Buku', 'Sarpras'], array_column($all, 'nama'));
    }
}
