<?php

namespace Tests\Feature\Juknis;

use App\Models\AuditLog;
use App\Models\KategoriJuknis;
use App\Models\MasterKodeRekening;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KategoriJuknisPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * RefreshDatabase menjalankan migrasi 000030, sehingga 3 kategori default
     * (Honor/Pemeliharaan/Buku) SELALU ada di setiap test.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('pengaturan.kategori-juknis.index'))->assertRedirect('/login');
    }

    public function test_index_page_renders_with_default_seed(): void
    {
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->get(route('pengaturan.kategori-juknis.index'));

        $response->assertOk();
        $response->assertSee('Honor');
        $response->assertSee('Pemeliharaan');
        $response->assertSee('Buku');
        $response->assertSee('Maksimal', false);
        $response->assertSee('Minimal', false);
        $response->assertSee(route('pengaturan.kategori-juknis.pemetaan'));
    }

    public function test_store_creates_kategori_maksimal(): void
    {
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->post(route('pengaturan.kategori-juknis.store'), [
            'nama' => 'Belanja Modal Uji',
            'arah' => 'maksimal',
            'batas_persen' => '25',
            'berlaku_untuk' => null,
        ]);

        $response->assertRedirect(route('pengaturan.kategori-juknis.index'));
        $this->assertDatabaseHas('kategori_juknis', [
            'nama' => 'Belanja Modal Uji',
            'arah' => 'maksimal',
        ]);

        $kategori = KategoriJuknis::where('nama', 'Belanja Modal Uji')->first();
        $this->assertSame(25.0, $kategori->batas_persen);

        $this->assertDatabaseHas('audit_log', [
            'tabel' => 'kategori_juknis',
            'aksi' => 'create',
        ]);
    }

    public function test_store_creates_kategori_minimal_with_berlaku_untuk(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)->post(route('pengaturan.kategori-juknis.store'), [
            'nama' => 'Perpustakaan Uji',
            'arah' => 'minimal',
            'batas_persen' => '10.5',
            'berlaku_untuk' => 'negeri',
        ])->assertRedirect(route('pengaturan.kategori-juknis.index'));

        $this->assertDatabaseHas('kategori_juknis', [
            'nama' => 'Perpustakaan Uji',
            'arah' => 'minimal',
            'berlaku_untuk' => 'negeri',
        ]);
    }

    public function test_store_rejects_duplicate_nama(): void
    {
        $user = \App\Models\User::factory()->create();

        // "Honor" sudah ada dari seed migrasi 000030
        $this->actingAs($user)->post(route('pengaturan.kategori-juknis.store'), [
            'nama' => 'Honor',
            'arah' => 'maksimal',
            'batas_persen' => '20',
        ])->assertSessionHasErrors('nama');

        $this->assertSame(3, DB::table('kategori_juknis')->count());
    }

    public function test_store_rejects_batas_persen_lebih_dari_100(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)->post(route('pengaturan.kategori-juknis.store'), [
            'nama' => 'Kategori Tak Wajar',
            'arah' => 'maksimal',
            'batas_persen' => '101',
        ])->assertSessionHasErrors('batas_persen');

        $this->assertDatabaseMissing('kategori_juknis', ['nama' => 'Kategori Tak Wajar']);
    }

    public function test_update_changes_values(): void
    {
        $user = \App\Models\User::factory()->create();
        $kategori = KategoriJuknis::factory()->maksimal(15.0)->create(['nama' => 'Uji Ubah']);

        $this->actingAs($user)
            ->put(route('pengaturan.kategori-juknis.update', $kategori), [
                'nama' => 'Uji Ubah Baru',
                'arah' => 'minimal',
                'batas_persen' => '12.5',
                'berlaku_untuk' => 'swasta',
            ])
            ->assertRedirect(route('pengaturan.kategori-juknis.index'));

        $kategori->refresh();
        $this->assertSame('Uji Ubah Baru', $kategori->nama);
        $this->assertSame('minimal', $kategori->arah);
        $this->assertSame(12.5, $kategori->batas_persen);
        $this->assertSame('swasta', $kategori->berlaku_untuk);
    }

    public function test_destroy_removes_kategori_and_detaches_pivot(): void
    {
        $user = \App\Models\User::factory()->create();
        $kategori = KategoriJuknis::factory()->create(['nama' => 'Hapus Aku']);
        $rekening = MasterKodeRekening::factory()->create();

        $rekening->kategoriJuknis()->attach($kategori->id);
        $this->assertDatabaseCount('kode_rekening_kategori_juknis', 1);

        $this->actingAs($user)
            ->delete(route('pengaturan.kategori-juknis.destroy', $kategori))
            ->assertRedirect(route('pengaturan.kategori-juknis.index'));

        $this->assertDatabaseMissing('kategori_juknis', ['nama' => 'Hapus Aku']);
        $this->assertDatabaseCount('kode_rekening_kategori_juknis', 0);
    }

    public function test_pemetaan_page_renders_and_checks_existing_mapping(): void
    {
        $user = \App\Models\User::factory()->create();
        $honor = KategoriJuknis::where('nama', 'Honor')->firstOrFail();
        $buku = KategoriJuknis::where('nama', 'Buku')->firstOrFail();
        $rekening = MasterKodeRekening::factory()->create(['nama' => 'Rekening Peta Uji']);

        $rekening->kategoriJuknis()->attach($honor->id);

        $response = $this->actingAs($user)
            ->get(route('pengaturan.kategori-juknis.pemetaan', ['q' => 'Rekening Peta Uji']));

        $response->assertOk();
        $response->assertSee('Rekening Peta Uji');
        $response->assertSee("map[{$rekening->id}][{$honor->id}]", false);
        $response->assertSee('checked', false);
    }

    public function test_simpan_pemetaan_maps_kode_rekening_to_multiple_categories(): void
    {
        $user = \App\Models\User::factory()->create();
        $honor = KategoriJuknis::where('nama', 'Honor')->firstOrFail();
        $buku = KategoriJuknis::where('nama', 'Buku')->firstOrFail();
        $r1 = MasterKodeRekening::factory()->create();
        $r2 = MasterKodeRekening::factory()->create(); // tidak dikirim -> tidak boleh tersentuh

        $this->actingAs($user)
            ->post(route('pengaturan.kategori-juknis.simpan-pemetaan'), [
                'rows' => [$r1->id],
                'map' => [
                    $r1->id => [$honor->id, $buku->id],
                ],
            ])
            ->assertRedirect(route('pengaturan.kategori-juknis.pemetaan'));

        $mapped = $r1->kategoriJuknis()->pluck('kategori_juknis.id')->all();
        $this->assertEqualsCanonicalizing([$honor->id, $buku->id], $mapped);
        $this->assertSame(0, $r2->kategoriJuknis()->count());

        $this->assertDatabaseHas('audit_log', [
            'tabel' => 'kategori_juknis',
            'aksi' => 'update_pemetaan',
        ]);
    }

    public function test_unchecking_all_boxes_clears_mapping_for_that_row(): void
    {
        $user = \App\Models\User::factory()->create();
        $honor = KategoriJuknis::where('nama', 'Honor')->firstOrFail();
        $rekening = MasterKodeRekening::factory()->create();
        $rekening->kategoriJuknis()->attach($honor->id);

        $this->actingAs($user)
            ->from(route('pengaturan.kategori-juknis.pemetaan'))
            ->post(route('pengaturan.kategori-juknis.simpan-pemetaan'), [
                'rows' => [$rekening->id],
                'map' => [],
            ])
            ->assertRedirect(route('pengaturan.kategori-juknis.pemetaan'));

        $this->assertSame(0, $rekening->refresh()->kategoriJuknis()->count());
    }

    public function test_simpan_pemetaan_only_updates_visible_rows(): void
    {
        $user = \App\Models\User::factory()->create();
        $honor = KategoriJuknis::where('nama', 'Honor')->firstOrFail();

        $jenis = \App\Models\JenisBelanja::factory()->create();
        $terlihat = MasterKodeRekening::factory()->create(['jenis_belanja_id' => $jenis->id]);
        $takTerlihat = MasterKodeRekening::factory()->create(['jenis_belanja_id' => $jenis->id]);

        $takTerlihat->kategoriJuknis()->attach($honor->id);

        $this->actingAs($user)
            ->post(route('pengaturan.kategori-juknis.simpan-pemetaan'), [
                'rows' => [$terlihat->id],
                'map' => [$terlihat->id => [$honor->id]],
            ])
            ->assertRedirect(route('pengaturan.kategori-juknis.pemetaan'));

        // Rekening yang tampil: dipetakan
        $this->assertSame(1, $terlihat->kategoriJuknis()->count());
        // Rekening di halaman lain (tidak dalam rows[]): pemetaannya UTUH
        $this->assertSame(1, $takTerlihat->kategoriJuknis()->count());
    }
}
