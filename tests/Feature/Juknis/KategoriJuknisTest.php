<?php

namespace Tests\Feature\Juknis;

use App\Models\KategoriJuknis;
use App\Models\MasterKodeRekening;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KategoriJuknisTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_menggunakan_uuid_sebagai_primary_key(): void
    {
        $kategori = KategoriJuknis::factory()->create();

        $this->assertSame(36, strlen($kategori->id));
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $kategori->id,
        );
    }

    public function test_relasi_dua_arah_kategori_dan_kode_rekening(): void
    {
        $kategori = KategoriJuknis::factory()->maksimal(50)->create();
        $rekeningA = MasterKodeRekening::factory()->create();
        $rekeningB = MasterKodeRekening::factory()->create();

        $kategori->kodeRekenings()->attach([$rekeningA->id, $rekeningB->id]);

        $this->assertTrue($kategori->kodeRekenings->contains($rekeningA->getKey()));
        $this->assertTrue($kategori->kodeRekenings->contains($rekeningB->getKey()));

        // Sisi sebaliknya: kode rekening melihat kategorinya.
        $this->assertTrue($rekeningA->fresh()->kategoriJuknis->contains($kategori->getKey()));
        $this->assertTrue($rekeningB->fresh()->kategoriJuknis->contains($kategori->getKey()));

        $kategori->kodeRekenings()->detach($rekeningA->id);

        $this->assertFalse($rekeningA->fresh()->kategoriJuknis->contains($kategori->getKey()));
        $this->assertTrue($rekeningB->fresh()->kategoriJuknis->contains($kategori->getKey()));
    }

    public function test_satu_kode_rekening_bisa_punya_banyak_kategori(): void
    {
        $rekening = MasterKodeRekening::factory()->create();
        $kategori1 = KategoriJuknis::factory()->create();
        $kategori2 = KategoriJuknis::factory()->create();
        $kategori3 = KategoriJuknis::factory()->create();

        $rekening->kategoriJuknis()->attach([$kategori1->id, $kategori2->id, $kategori3->id]);

        $this->assertCount(3, $rekening->fresh()->kategoriJuknis);
    }

    public function test_pivot_unique_mencegah_pasangan_duplikat(): void
    {
        $kategori = KategoriJuknis::factory()->create();
        $rekening = MasterKodeRekening::factory()->create();

        $rekening->kategoriJuknis()->attach($kategori->id);

        $this->expectException(UniqueConstraintViolationException::class);

        $rekening->kategoriJuknis()->attach($kategori->id);
    }

    public function test_hapus_kategori_menghapus_baris_pivot_cascade(): void
    {
        $kategori = KategoriJuknis::factory()->create();
        $rekening = MasterKodeRekening::factory()->create();

        $rekening->kategoriJuknis()->attach($kategori->id);
        $this->assertDatabaseCount('kode_rekening_kategori_juknis', 1);

        $kategori->delete();

        $this->assertDatabaseCount('kode_rekening_kategori_juknis', 0);
        $this->assertCount(0, $rekening->fresh()->kategoriJuknis);
    }

    public function test_hapus_kode_rekening_menghapus_baris_pivot_cascade(): void
    {
        $kategori = KategoriJuknis::factory()->create();
        $rekening = MasterKodeRekening::factory()->create();

        $rekening->kategoriJuknis()->attach($kategori->id);

        $rekening->delete();

        $this->assertDatabaseCount('kode_rekening_kategori_juknis', 0);
        $this->assertCount(0, $kategori->fresh()->kodeRekenings);
    }

    public function test_batas_persen_tercast_float_dan_berlaku_untuk_nullable(): void
    {
        $kategori = KategoriJuknis::factory()->create([
            'nama' => 'Honor Uji Cast',
            'arah' => 'maksimal',
            'batas_persen' => '50.00',
            'berlaku_untuk' => null,
        ]);

        $this->assertSame(50.0, $kategori->batas_persen);
        $this->assertSame('maksimal', $kategori->arah);
        $this->assertNull($kategori->berlaku_untuk);

        $kategori->update(['berlaku_untuk' => 'negeri']);
        $this->assertSame('negeri', $kategori->fresh()->berlaku_untuk);
    }
}
