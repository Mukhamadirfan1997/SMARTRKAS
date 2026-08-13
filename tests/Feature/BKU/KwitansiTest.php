<?php

namespace Tests\Feature\BKU;

use App\Models\Kwitansi;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\PengaturanSekolah;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KwitansiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private TahunAnggaran $tahun;
    private TransaksiBku $transaksi;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = User::factory()->create();
        $this->tahun = TahunAnggaran::factory()->create(['status' => true]);
        $sumber = SumberDana::factory()->create();
        $program = MasterProgram::factory()->create([
            'kode' => '1.2.3',
            'nama' => 'Kegiatan ATK',
            'program' => 'Program Sarana',
            'sub_program' => 'Sub Program ATK',
        ]);
        $rekening = MasterKodeRekening::factory()->create();

        PengaturanSekolah::create([
            'npsn' => '20519260',
            'nama' => 'SDN TOYANING I REJOSO',
            'kabupaten' => 'Kab. Pasuruan',
            'kecamatan' => 'Kec. Rejoso',
            'nama_kepsek' => 'H. Abdul Rohim',
            'nip_kepsek' => '196812312000031001',
            'nama_bendahara' => 'Siti Aminah',
            'nip_bendahara' => '198501012010012001',
        ]);

        $item = \App\Models\RkasItem::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $sumber->id,
            'program_id' => $program->id,
            'kode_rekening_id' => $rekening->id,
            'no_urut' => 1,
            'uraian' => 'Belanja ATK',
            'jumlah' => 500000,
        ]);

        $this->transaksi = TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'sumber_dana_id' => $sumber->id,
            'rkas_item_id' => $item->id,
            'tanggal' => '2026-01-15',
            'bulan' => 1,
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 250000,
            'toko_penerima' => 'Toko Sumber Rejeki',
            'metode_pengadaan' => 'non_siplah',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/transaksi-bku/' . $this->transaksi->id . '/cetak-kwitansi')->assertRedirect('/login');
    }

    public function test_cetak_kwitansi_single_returns_pdf_and_saves_record(): void
    {
        $response = $this->actingAs($this->user)->get('/transaksi-bku/' . $this->transaksi->id . '/cetak-kwitansi');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));

        $this->assertDatabaseHas('kwitansi', [
            'transaksi_bku_id' => $this->transaksi->id,
            'nomor' => 'BPU001/20519260/01/2026',
        ]);

        $kwitansi = Kwitansi::where('transaksi_bku_id', $this->transaksi->id)->first();
        $this->assertNotNull($kwitansi);
        $this->assertNotNull($kwitansi->dicetak_pada);
        Storage::disk('public')->assertExists($kwitansi->file_pdf_path);
    }

    public function test_cetak_kwitansi_batch_returns_pdf(): void
    {
        $transaksi2 = TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'tanggal' => '2026-01-20',
            'bulan' => 1,
            'no_bukti' => 'BPU002/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 50000,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->post('/transaksi-bku/cetak-kwitansi-batch', [
            'ids' => [$this->transaksi->id, $transaksi2->id],
        ]);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));

        $this->assertDatabaseCount('kwitansi', 2);
    }

    public function test_cetak_kwitansi_batch_errors_when_no_ids(): void
    {
        $response = $this->actingAs($this->user)->post('/transaksi-bku/cetak-kwitansi-batch', [
            'ids' => [],
        ]);

        $response->assertSessionHas('error');
    }

    public function test_cetak_kwitansi_reuses_nomor_when_no_bukti_reused_after_soft_delete(): void
    {
        // Cetak pertama utk transaksi ini (transaksi = BPU001/20519260/01/2026).
        $this->actingAs($this->user)
            ->get('/transaksi-bku/' . $this->transaksi->id . '/cetak-kwitansi')
            ->assertOk();
        $this->assertDatabaseCount('kwitansi', 1);

        // Transaksi lama di-soft-delete, lalu `no_bukti` dipakai ulang oleh
        // transaksi baru (perilaku reuse nomor terkecil bebas per bulan).
        $this->transaksi->delete();

        $transaksiBaru = TransaksiBku::factory()->create([
            'tahun_anggaran_id' => $this->tahun->id,
            'tanggal' => '2026-01-22',
            'bulan' => 1,
            'no_bukti' => 'BPU001/20519260/01/2026',
            'jenis' => 'pengeluaran',
            'jumlah' => 200000,
            'created_by' => $this->user->id,
        ]);

        // Cetak utk transaksi baru: tidak boleh 500 (UNIQUE kwitansi.nomor).
        $response = $this->actingAs($this->user)
            ->get('/transaksi-bku/' . $transaksiBaru->id . '/cetak-kwitansi');
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));

        // Baris kwitansi utk nomor tsb hanya satu & kini milik transaksi baru.
        $this->assertDatabaseCount('kwitansi', 1);
        $this->assertDatabaseHas('kwitansi', [
            'transaksi_bku_id' => $transaksiBaru->id,
            'nomor' => 'BPU001/20519260/01/2026',
        ]);
    }
}
