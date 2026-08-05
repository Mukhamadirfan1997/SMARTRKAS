<?php

namespace Tests\Feature\Export;

use App\Exports\BkuExport;
use App\Exports\RekapKuartalExport;
use App\Exports\RekapRekeningExport;
use App\Exports\RekapSiplahExport;
use App\Jobs\GenerateExportJob;
use App\Models\ExportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class GenerateExportJobTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_sets_completed_status_and_stores_file(): void
    {
        Storage::fake('public');
        Excel::fake();

        $exportJob = ExportJob::create([
            'user_id' => $this->user->id,
            'type' => 'BKU',
            'status' => 'processing',
        ]);

        GenerateExportJob::dispatch(
            $exportJob->id,
            BkuExport::class,
            ['bulan' => 1, 'profil' => 'sekolah_test', 'tahunAnggaranId' => null, 'sumberDanaId' => null],
            'bku-bulan-1-sekolah_test.xlsx',
        );

        $exportJob->refresh();
        $this->assertEquals('completed', $exportJob->status);
        $this->assertEquals('bku-bulan-1-sekolah_test.xlsx', $exportJob->filename);
        $this->assertNotNull($exportJob->completed_at);
        $this->assertStringContainsString('exports/', $exportJob->file_path);
        $this->assertStringContainsString($exportJob->id . '_', $exportJob->file_path);
    }

    public function test_sets_completed_for_rekap_rekening(): void
    {
        Storage::fake('public');
        Excel::fake();

        $exportJob = ExportJob::create([
            'user_id' => $this->user->id,
            'type' => 'Rekap Realisasi',
            'status' => 'processing',
        ]);

        GenerateExportJob::dispatch(
            $exportJob->id,
            RekapRekeningExport::class,
            ['bulan' => 1, 'tahunAnggaranId' => null, 'sumberDanaId' => null],
            'rekap-bulan-1.xlsx',
        );

        $exportJob->refresh();
        $this->assertEquals('completed', $exportJob->status);
        $this->assertStringContainsString('exports/', $exportJob->file_path);
    }

    public function test_sets_completed_for_rekap_kuartal(): void
    {
        Storage::fake('public');
        Excel::fake();

        $exportJob = ExportJob::create([
            'user_id' => $this->user->id,
            'type' => 'Rekap Kuartal',
            'status' => 'processing',
        ]);

        GenerateExportJob::dispatch(
            $exportJob->id,
            RekapKuartalExport::class,
            ['kuartal' => 1, 'namaSekolah' => 'sekolah_test', 'tahunAnggaranId' => null, 'sumberDanaId' => null],
            'rekap-kuartal-1.xlsx',
        );

        $exportJob->refresh();
        $this->assertEquals('completed', $exportJob->status);
    }

    public function test_sets_completed_for_rekap_siplah(): void
    {
        Storage::fake('public');
        Excel::fake();

        $exportJob = ExportJob::create([
            'user_id' => $this->user->id,
            'type' => 'Rekap Siplah',
            'status' => 'processing',
        ]);

        GenerateExportJob::dispatch(
            $exportJob->id,
            RekapSiplahExport::class,
            ['months' => [1, 2, 3], 'periodeLabel' => 'Jan-Mar', 'tahunAnggaranId' => null, 'sumberDanaId' => null],
            'rekap-siplah.xlsx',
        );

        $exportJob->refresh();
        $this->assertEquals('completed', $exportJob->status);
    }

    public function test_sets_failed_on_exception(): void
    {
        Storage::fake('public');

        $exportJob = ExportJob::create([
            'user_id' => $this->user->id,
            'type' => 'BKU',
            'status' => 'processing',
        ]);

        $mock = $this->createMock(\Maatwebsite\Excel\Fakes\ExcelFake::class);
        $mock->method('store')->willThrowException(new \Exception('Simulated export failure'));
        $mock->method('queue')->willReturn(true);
        Excel::swap($mock);

        try {
            GenerateExportJob::dispatch(
                $exportJob->id,
                BkuExport::class,
                ['bulan' => 1, 'profil' => 'sekolah', 'tahunAnggaranId' => null, 'sumberDanaId' => null],
                'fail.xlsx',
            );
        } catch (\Throwable) {
        }

        $exportJob->refresh();
        $this->assertEquals('failed', $exportJob->status);
        $this->assertEquals('Simulated export failure', $exportJob->error_message);
    }
}
