<?php

namespace Tests\Feature\BKU;

use App\Models\Kwitansi;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanOldKwitansiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function makeKwitansi(?\DateTimeInterface $createdAt = null): Kwitansi
    {
        $user = User::factory()->create();
        $tahun = TahunAnggaran::factory()->create();
        $sumber = SumberDana::factory()->create();
        $program = MasterProgram::factory()->create();
        $rekening = MasterKodeRekening::factory()->create();
        $item = \App\Models\RkasItem::factory()->create([
            'tahun_anggaran_id' => $tahun->id,
            'sumber_dana_id' => $sumber->id,
            'program_id' => $program->id,
            'kode_rekening_id' => $rekening->id,
        ]);
        $transaksi = TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $tahun->id,
            'sumber_dana_id' => $sumber->id,
            'rkas_item_id' => $item->id,
            'created_by' => $user->id,
        ]);

        $path = 'kwitansi/kwitansi-' . $transaksi->no_bukti . '.pdf';
        Storage::disk('public')->put($path, 'pdf-content');

        $kwitansi = Kwitansi::factory()->create([
            'transaksi_bku_id' => $transaksi->id,
            'file_pdf_path' => $path,
        ]);

        if ($createdAt) {
            Kwitansi::withoutTimestamps(fn () => $kwitansi->forceFill(['created_at' => $createdAt])->save());
        }

        return $kwitansi;
    }

    public function test_command_noop_when_no_old_kwitansi(): void
    {
        $this->makeKwitansi();

        $this->artisan('kwitansi:clean')
            ->expectsOutputToContain('Tidak ada kwitansi yang perlu dibersihkan.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('kwitansi', 1);
    }

    public function test_command_deletes_old_records_and_files(): void
    {
        $old = $this->makeKwitansi(now()->subYears(3));
        $new = $this->makeKwitansi(now()->subMonths(1));

        $this->artisan('kwitansi:clean')
            ->expectsOutputToContain('1 record kwitansi')
            ->assertExitCode(0);

        $this->assertDatabaseCount('kwitansi', 1);
        $this->assertDatabaseMissing('kwitansi', ['id' => $old->id]);
        $this->assertDatabaseHas('kwitansi', ['id' => $new->id]);

        Storage::disk('public')->assertMissing($old->file_pdf_path);
        Storage::disk('public')->assertExists($new->file_pdf_path);
    }

    public function test_command_defaults_to_two_years(): void
    {
        $this->makeKwitansi(now()->subYears(2)->subDay());
        $this->makeKwitansi(now()->subYears(1));

        $this->artisan('kwitansi:clean')
            ->expectsOutputToContain('1 record kwitansi')
            ->assertExitCode(0);

        $this->assertDatabaseCount('kwitansi', 1);
    }
}
