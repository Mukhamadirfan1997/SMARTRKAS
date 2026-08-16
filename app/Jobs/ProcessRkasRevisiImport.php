<?php

namespace App\Jobs;

use App\Imports\ImportRevisiImport;
use App\Models\AuditLog;
use App\Models\ImportLog;
use App\Models\Outbox;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\RkasRevisi;
use App\Models\RkasRevisiItem;
use App\Support\NomorDokumen;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Proses impor hasil pergeseran/PAK dari ARKAS.
 *
 * Sinkron (tanpa ShouldQueue) — dipanggil langsung dari controller agar
 * hasil all-or-nothing bisa dikembalikan (ok/no_revisi/errors) dan ditampilkan
 * ke user dalam satu request, mengikuti pola ProcessRkasImport (desktop offline).
 *
 * Alur:
 *  1. diff setiap file per bulan (ImportRevisiImport::diff) — kumpulkan rows + errors;
 *  2. jika ada error parse / validasi gagal / rows kosong -> semua log failed (all-or-nothing);
 *  3. DB::transaction: buat RkasRevisi + RkasRevisiItem, tulis rkas_item_bulan
 *     (item baru dibuat bila belum ada), renumber + syncJumlah;
 *  4. AuditLog + Outbox + Cache::increment dashboard ver.
 */
class ProcessRkasRevisiImport
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private string $tahunAnggaranId;

    private string $sumberDanaId;

    private string $jenis;

    private string $tanggal;

    private ?string $keterangan;

    /** @var array<int, array{log_id: string, path: string}> */
    private array $files;

    private ?int $userId;

    /**
     * @param array<int, array{log_id: string, path: string}> $files
     */
    public function __construct(
        string $tahunAnggaranId,
        string $sumberDanaId,
        string $jenis,
        string $tanggal,
        ?string $keterangan,
        array $files,
        ?int $userId = null,
    ) {
        $this->tahunAnggaranId = $tahunAnggaranId;
        $this->sumberDanaId = $sumberDanaId;
        $this->jenis = $jenis;
        $this->tanggal = $tanggal;
        $this->keterangan = $keterangan;
        $this->files = $files;
        $this->userId = $userId;
    }

    /**
     * @return array{ok: bool, no_revisi: string|null, errors: array<int, string>}
     */
    public function handle(): array
    {
        $parser = new ImportRevisiImport($this->tahunAnggaranId, $this->sumberDanaId, $this->jenis);

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = collect();
        $errorsByLog = [];

        foreach ($this->files as $meta) {
            $log = ImportLog::find($meta['log_id']);
            if ($log === null) {
                continue;
            }

            $diff = $parser->diff($meta['path'], (int) $log->bulan);
            $rows = $rows->merge($diff['rows']);

            if ($diff['errors'] !== []) {
                $errorsByLog[$log->id] = $diff['errors'];
            }
        }

        if ($errorsByLog !== []) {
            $this->failAll($errorsByLog);

            return ['ok' => false, 'no_revisi' => null, 'errors' => $this->flattenErrors($errorsByLog)];
        }

        if ($rows->isEmpty()) {
            $message = 'Tidak ada perubahan yang ditemukan pada file yang diunggah.';
            $this->failAll($this->mapAllLogs($message));

            return ['ok' => false, 'no_revisi' => null, 'errors' => [$message]];
        }

        $validation = $parser->validate($rows);
        if (!$validation['ok']) {
            $this->failAll($this->mapAllLogs($validation['errors']));

            return ['ok' => false, 'no_revisi' => null, 'errors' => $validation['errors']];
        }

        try {
            $noRevisi = DB::transaction(fn (): string => $this->apply($rows));
        } catch (\Throwable $e) {
            $message = 'System Error: ' . $e->getMessage();
            $this->failAll($this->mapAllLogs($message));

            return ['ok' => false, 'no_revisi' => null, 'errors' => [$message]];
        }

        $this->markSuccess();

        return ['ok' => true, 'no_revisi' => $noRevisi, 'errors' => []];
    }

    /**
     * Terapkan revisi ke database dalam satu transaksi.
     *
     * @param Collection<int, array<string, mixed>> $rows
     */
    private function apply(Collection $rows): string
    {
        $noRevisi = NomorDokumen::noRevisi($this->jenis, $this->tanggal);

        $sebelumTotal = 0.0;
        $sesudahTotal = 0.0;
        /** @var array<int, array{jumlah_item: int, selisih: float}> $dataPerubahan */
        $dataPerubahan = [];

        $revisi = RkasRevisi::create([
            'no_revisi' => $noRevisi,
            'jenis' => $this->jenis,
            'tanggal' => $this->tanggal,
            'tahun_anggaran_id' => $this->tahunAnggaranId,
            'sumber_dana_id' => $this->sumberDanaId,
            'keterangan' => $this->keterangan,
            'sebelum_total' => 0,
            'sesudah_total' => 0,
            'data_perubahan' => [],
            'created_by' => $this->userId ?? auth()->id(),
        ]);

        $urutan = 0;
        /** @var array<string, RkasItem> $memoNewItems */
        $memoNewItems = [];

        foreach ($rows as $row) {
            $bulan = (int) $row['bulan'];

            $rkasItemId = $row['rkas_item_id'] ?? null;
            if ($rkasItemId === null) {
                $key = (string) $row['program_id'] . '|' . (string) $row['kode_rekening_id'] . '|' . RkasItem::normalizeUraian((string) $row['uraian']);
                if (!isset($memoNewItems[$key])) {
                    $memoNewItems[$key] = RkasItem::create([
                        'tahun_anggaran_id' => $this->tahunAnggaranId,
                        'sumber_dana_id' => $this->sumberDanaId,
                        'program_id' => $row['program_id'],
                        'kode_rekening_id' => $row['kode_rekening_id'],
                        'uraian' => $row['uraian'],
                        'volume' => (float) ($row['volume'] ?? 0),
                        'satuan' => (string) ($row['satuan'] ?? ''),
                        'tarif' => (float) ($row['tarif'] ?? 0),
                        'no_urut' => 0,
                        'jumlah' => 0,
                    ]);
                }
                $rkasItemId = $memoNewItems[$key]->id;
            }

            RkasItemBulan::updateOrCreate(
                ['rkas_item_id' => $rkasItemId, 'bulan' => $bulan],
                ['rencana' => (float) $row['sesudah']]
            );

            $urutan++;
            RkasRevisiItem::create([
                'rkas_revisi_id' => $revisi->id,
                'rkas_item_id' => $rkasItemId,
                'bulan' => $bulan,
                'arah' => (string) $row['arah'],
                'sebelum' => (float) $row['sebelum'],
                'sesudah' => (float) $row['sesudah'],
                'delta' => (float) $row['delta'],
                'urutan' => $urutan,
            ]);

            $sebelumTotal += (float) $row['sebelum'];
            $sesudahTotal += (float) $row['sesudah'];

            $dataPerubahan[$bulan] = [
                'jumlah_item' => ($dataPerubahan[$bulan]['jumlah_item'] ?? 0) + 1,
                'selisih' => ($dataPerubahan[$bulan]['selisih'] ?? 0.0) + (float) $row['delta'],
            ];
        }

        $revisi->update([
            'sebelum_total' => $sebelumTotal,
            'sesudah_total' => $sesudahTotal,
            'data_perubahan' => $dataPerubahan,
        ]);

        RkasItem::renumber($this->tahunAnggaranId);
        RkasItem::syncJumlah($this->tahunAnggaranId);

        AuditLog::record(
            'rkas_revisi',
            $this->jenis === 'pak' ? 'import_pak' : 'import_pergeseran',
            [
                'no_revisi' => $noRevisi,
                'jenis' => $this->jenis,
                'tanggal' => $this->tanggal,
                'keterangan' => $this->keterangan,
                'jumlah_item' => $urutan,
                'sebelum_total' => $sebelumTotal,
                'sesudah_total' => $sesudahTotal,
                'data_perubahan' => $dataPerubahan,
            ],
            null,
            $this->userId
        );

        Outbox::record('RkasRevisi', $revisi->id, 'create', [
            'no_revisi' => $noRevisi,
            'jenis' => $this->jenis,
            'tanggal' => $this->tanggal,
            'keterangan' => $this->keterangan,
            'jumlah_item' => $urutan,
            'sebelum_total' => $sebelumTotal,
            'sesudah_total' => $sesudahTotal,
        ]);

        if ($this->userId !== null) {
            Cache::increment('dash_ver_' . $this->userId);
        }

        return $noRevisi;
    }

    /**
     * Tandai semua log pada request ini sebagai failed + cleanup file.
     *
     * @param array<int|string, array<int, string>> $errorsByLog
     */
    private function failAll(array $errorsByLog): void
    {
        foreach ($this->files as $meta) {
            $log = ImportLog::find($meta['log_id']);
            if ($log === null) {
                continue;
            }

            $log->update([
                'status' => 'failed',
                'error_detail' => $errorsByLog[$log->id] ?? ['Revisi ditolak. Tidak ada perubahan yang diterapkan.'],
                'finished_at' => now(),
            ]);

            if ($log->file_path) {
                Storage::disk('local')->delete($log->file_path);
                $log->updateQuietly(['file_path' => null]);
            }
        }
    }

    private function markSuccess(): void
    {
        foreach ($this->files as $meta) {
            $log = ImportLog::find($meta['log_id']);
            if ($log === null) {
                continue;
            }

            $log->update([
                'status' => 'success',
                'finished_at' => now(),
            ]);

            if ($log->file_path) {
                Storage::disk('local')->delete($log->file_path);
                $log->updateQuietly(['file_path' => null]);
            }
        }
    }

    /**
     * @return array<int|string, string> log_id => path
     */
    private function filesWithLogs(): array
    {
        $result = [];

        foreach ($this->files as $meta) {
            $log = ImportLog::find($meta['log_id']);
            if ($log !== null) {
                $result[$log->id] = $meta['path'];
            }
        }

        return $result;
    }

    /**
     * @param array<int|string, array<int, string>> $errorsByLog
     * @return array<int, string>
     */
    private function flattenErrors(array $errorsByLog): array
    {
        $all = [];

        foreach ($errorsByLog as $errors) {
            foreach ($errors as $error) {
                $all[] = $error;
            }
        }

        return array_values(array_unique($all));
    }

    /**
     * Peta error yang sama untuk semua log pada request ini.
     *
     * @param array<int, string>|string $errors
     * @return array<int|string, array<int, string>>
     */
    private function mapAllLogs(array|string $errors): array
    {
        $normalized = is_array($errors) ? $errors : [$errors];
        $result = [];

        foreach (array_keys($this->filesWithLogs()) as $logId) {
            $result[$logId] = $normalized;
        }

        return $result;
    }
}
