<?php

namespace Tests\Feature\Import;

use App\Models\ImportLog;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ImportRkasControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private SumberDana $sumberDana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->sumberDana = SumberDana::factory()->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/import-rkas')->assertRedirect('/login');
    }

    public function test_index_shows_page(): void
    {
        $response = $this->actingAs($this->user)->get('/import-rkas');

        $response->assertOk();
        $response->assertSee('Import RKAS');
    }

    public function test_index_warns_when_no_active_tahun(): void
    {
        $response = $this->actingAs($this->user)->get('/import-rkas');

        $response->assertOk();
        $response->assertSee('Tahun anggaran belum diaktifkan');
    }

    public function test_download_template_returns_file(): void
    {
        $response = $this->actingAs($this->user)->get('/import-rkas/download-template');

        $response->assertOk();
        $this->assertNotEmpty($response->headers->get('content-disposition'));
    }

    public function test_status_returns_logs_as_json(): void
    {
        TahunAnggaran::factory()->create(['status' => true]);
        ImportLog::factory()->count(2)->create(['uploaded_by' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson('/import-rkas/status');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_store_requires_files(): void
    {
        $response = $this->actingAs($this->user)->post('/import-rkas', [
            'sumber_dana_id' => $this->sumberDana->id,
        ]);

        $response->assertSessionHasErrors('files');
    }

    public function test_store_returns_error_when_no_active_tahun(): void
    {
        $response = $this->actingAs($this->user)->post('/import-rkas', [
            'files' => [1 => UploadedFile::fake()->create('januari.xlsx', 100)],
            'sumber_dana_id' => $this->sumberDana->id,
        ]);

        $response->assertSessionHas('error');
    }

    public function test_store_processes_uploaded_file_and_creates_import_log(): void
    {
        TahunAnggaran::factory()->create(['status' => true]);
        MasterProgram::factory()->create(['kode' => 'P.001.01']);
        MasterKodeRekening::factory()->create(['kode' => '5.1.01.01.001']);

        Storage::fake('local');

        $export = new class implements FromArray {
            /** @return array<int, array<int, string>> */
            public function array(): array
            {
                return [
                    ['No Urut', 'Kode Rekening', 'Kode Program', 'Uraian', 'Volume', 'Satuan', 'Tarif', 'Jumlah'],
                    ['1', '5.1.01.01.001', 'P.001.01', 'Alat Tulis Kantor', '10', 'buah', '1000', '10000'],
                ];
            }
        };

        Excel::store($export, 'uploads/bulan1.xlsx', 'local');

        $upload = new UploadedFile(
            Storage::disk('local')->path('uploads/bulan1.xlsx'),
            'bulan1.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->user)->post('/import-rkas', [
            'files' => [1 => $upload],
            'sumber_dana_id' => $this->sumberDana->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $log = ImportLog::where('bulan', 1)->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);
        $this->assertSame(1, $log->baris_berhasil);
        $this->assertSame('bulan1.xlsx', $log->file_name);

        $this->assertDatabaseHas('rkas_item', [
            'uraian' => 'Alat Tulis Kantor',
        ]);
    }

    public function test_store_returns_error_when_all_files_null(): void
    {
        TahunAnggaran::factory()->create(['status' => true]);

        $response = $this->actingAs($this->user)->post('/import-rkas', [
            'files' => [1 => null, 2 => null],
            'sumber_dana_id' => $this->sumberDana->id,
        ]);

        $response->assertSessionHas('error');
    }
}
