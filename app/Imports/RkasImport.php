<?php

namespace App\Imports;

use App\Models\RkasItem;
use App\Models\MasterProgram;
use App\Models\MasterKodeRekening;
use App\Models\ImportLog;
use App\Models\RkasItemBulan;
use App\Models\TransaksiBku;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class RkasImport implements ToModel, WithStartRow, WithChunkReading
{
    protected string $tahunAnggaranId;
    protected int $bulan;
    protected string $sumberDanaId;
    protected string $importLogId;
    protected int $headingRow;
    /** @var array<string, int>|null */
    protected ?array $columns = null;
    protected ?int $startRow = null;

    /**
     * @param array<string, int>|null $columns
     */
    public function __construct(
        string $tahunAnggaranId,
        int $bulan,
        string $sumberDanaId,
        string $importLogId,
        ?int $headingRow = null,
        ?array $columns = null,
        ?int $startRow = null
    ) {
        $this->tahunAnggaranId = $tahunAnggaranId;
        $this->bulan = $bulan;
        $this->sumberDanaId = $sumberDanaId;
        $this->importLogId = $importLogId;
        $this->headingRow = $headingRow ?? 1;
        $this->columns = $columns;
        $this->startRow = $startRow;
    }

    public function startRow(): int
    {
        return $this->startRow ?? $this->headingRow + 1;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    /**
     * @param array<string|int, mixed> $row
     */
    public function model(array $row)
    {
        $noUrut       = trim((string) $this->cell($row, 'no_urut'));
        $kodeRekening = trim((string) $this->cell($row, 'kode_rekening'));
        $kodeProgram  = trim((string) $this->cell($row, 'kode_program'));
        $uraian       = trim((string) $this->cell($row, 'uraian'));
        $volume       = $this->cell($row, 'volume');
        $satuan       = trim((string) $this->cell($row, 'satuan'));
        $tarif        = $this->cell($row, 'tarif');
        $jumlah       = $this->cell($row, 'jumlah');

        if (!is_numeric($noUrut) || empty($uraian)) {
            return null;
        }

        if ($jumlah === null || !preg_match('/\d/', (string) $jumlah)) {
            return null;
        }

        $parsedJumlah = $this->parseNumber($jumlah);
        if ($parsedJumlah < 0) {
            $this->logError("No. Urut $noUrut: Jumlah tidak boleh negatif ($parsedJumlah)");
            return null;
        }

        if (empty($kodeRekening)) {
            if ($this->looksLikeDataRow($volume, $satuan, $tarif)) {
                $this->logWarning("No. Urut $noUrut: dilewati karena kode rekening kosong ({$uraian}).");
            }

            return null;
        }

        $kodeProgram = str_replace(' ', '', $kodeProgram);

        $program = null;
        if (!empty($kodeProgram)) {
            $program = MasterProgram::where('kode', $kodeProgram)->first();
        }

        if (!$program) {
            $this->logError("No. Urut $noUrut: Program tidak ditemukan ($kodeProgram)");
            return null;
        }

        $kodeRekeningRecord = MasterKodeRekening::where('kode', rtrim($kodeRekening, '.'))->first();

        if (!$kodeRekeningRecord) {
            $this->logError("No. Urut $noUrut: Kode rekening tidak ditemukan ($kodeRekening)");
            return null;
        }

        $parsedVolume = $this->parseNumber($volume);
        if ($parsedVolume < 0) {
            $this->logError("No. Urut $noUrut: Volume tidak boleh negatif");
            return null;
        }

        $parsedTarif = $this->parseNumber($tarif);
        if ($parsedTarif < 0) {
            $this->logError("No. Urut $noUrut: Tarif tidak boleh negatif");
            return null;
        }

        $rkasItem = $this->resolveItem(
            (int) $noUrut,
            $program->id,
            $kodeRekeningRecord->id,
            $uraian,
            $parsedVolume,
            $satuan,
            $parsedTarif,
            $parsedJumlah
        );

        RkasItemBulan::updateOrCreate(
            [
                'rkas_item_id' => $rkasItem->id,
                'bulan'        => $this->bulan,
            ],
            [
                'rencana'      => $parsedJumlah,
            ]
        );

        $rkasItem->updateQuietly([
            'jumlah' => (float) RkasItemBulan::where('rkas_item_id', $rkasItem->id)->sum('rencana'),
        ]);

        $this->incrementBerhasil();

        return null;
    }

    /**
     * @param array<string|int, mixed> $row
     */
    protected function cell(array $row, string $field): mixed
    {
        if ($this->columns !== null && isset($this->columns[$field])) {
            return $row[$this->columns[$field] - 1] ?? null;
        }

        return $row[$field] ?? null;
    }

    /**
     * Cari item RKAS yang cocok berdasarkan (tahun, sumber dana, program,
     * kode rekening, uraian). no_urut TIDAK dipakai untuk identitas karena bisa
     * berbeda antar file bulanan (menyebabkan duplikat). Jika ada beberapa item
     * yang identik, gabungkan otomatis.
     */
    protected function resolveItem(
        int $noUrut,
        string $programId,
        string $kodeRekeningId,
        string $uraian,
        float $volume,
        string $satuan,
        float $tarif,
        float $jumlah
    ): RkasItem {
        $normalized = RkasItem::normalizeUraian($uraian);

        /** @var Collection<int, RkasItem> $candidates */
        $candidates = RkasItem::where('tahun_anggaran_id', $this->tahunAnggaranId)
            ->where('sumber_dana_id', $this->sumberDanaId)
            ->where('program_id', $programId)
            ->where('kode_rekening_id', $kodeRekeningId)
            ->orderBy('id')
            ->get(['id', 'no_urut', 'uraian']);

        $matches = $candidates->filter(
            fn (RkasItem $item): bool => RkasItem::normalizeUraian((string) $item->uraian) === $normalized
        );

        if ($matches->isEmpty()) {
            return RkasItem::create([
                'tahun_anggaran_id' => $this->tahunAnggaranId,
                'no_urut'           => $noUrut,
                'sumber_dana_id'    => $this->sumberDanaId,
                'uraian'            => $uraian,
                'program_id'        => $programId,
                'kode_rekening_id'  => $kodeRekeningId,
                'volume'            => $volume,
                'satuan'            => $satuan,
                'tarif'             => $tarif,
                'jumlah'            => $jumlah,
            ]);
        }

        /** @var RkasItem $survivor */
        $survivor = $matches->first();

        if ($matches->count() > 1) {
            $this->consolidateDuplicates($survivor, $matches);
        }

        $survivor->update([
            'no_urut' => $noUrut,
            'uraian'  => $uraian,
            'volume'  => $volume,
            'satuan'  => $satuan,
            'tarif'   => $tarif,
        ]);

        return $survivor;
    }

    /**
     * Gabungkan item duplikat (uraian+program+rekening sama) ke item utama:
     * rencana bulanan dijumlahkan, transaksi BKU dialihkan, item ganda dihapus permanen.
     *
     * @param Collection<int, RkasItem> $duplicates
     */
    protected function consolidateDuplicates(RkasItem $survivor, Collection $duplicates): void
    {
        foreach ($duplicates as $dup) {
            if ($dup->is($survivor)) {
                continue;
            }

            foreach (RkasItemBulan::where('rkas_item_id', $dup->id)->get() as $bulan) {
                $existing = RkasItemBulan::where('rkas_item_id', $survivor->id)
                    ->where('bulan', $bulan->bulan)
                    ->first();

                if ($existing) {
                    $existing->updateQuietly([
                        'rencana' => (float) $existing->rencana + (float) $bulan->rencana,
                    ]);
                } else {
                    RkasItemBulan::create([
                        'rkas_item_id' => $survivor->id,
                        'bulan'        => $bulan->bulan,
                        'rencana'      => $bulan->rencana,
                    ]);
                }
            }

            TransaksiBku::where('rkas_item_id', $dup->id)
                ->update(['rkas_item_id' => $survivor->id]);

            RkasItem::withoutEvents(function () use ($dup): void {
                $dup->forceDelete();
            });
        }

        $survivor->updateQuietly([
            'jumlah' => (float) RkasItemBulan::where('rkas_item_id', $survivor->id)->sum('rencana'),
        ]);
    }

    protected function looksLikeDataRow(mixed $volume, string $satuan, mixed $tarif): bool
    {
        return ($volume !== null && $volume !== '')
            || $satuan !== ''
            || ($tarif !== null && $tarif !== '');
    }

    protected function logWarning(string $message): void
    {
        $log = ImportLog::find($this->importLogId);
        if ($log) {
            $warnings = $log->error_detail ?? [];
            $warnings[] = $message;
            $log->error_detail = $warnings;
            $log->save();
        }
    }

    protected function logError(string $message): void
    {
        $log = ImportLog::find($this->importLogId);
        if ($log) {
            $log->increment('baris_gagal');
            $errs = $log->error_detail ?? [];
            $errs[] = $message;
            $log->error_detail = $errs;
            $log->save();
        }
    }

    protected function incrementBerhasil(): void
    {
        ImportLog::where('id', $this->importLogId)
            ->increment('baris_berhasil');
    }

    protected function parseNumber(mixed $value): float|int
    {
        if ($value === null || $value === '') return 0;
        if (is_int($value) || is_float($value)) return (float) $value;

        $cleaned = preg_replace('/[^0-9\,\.\-]/', '', (string) $value);
        $isNegative = str_starts_with($cleaned, '-');
        $cleaned = str_replace('-', '', $cleaned);

        $commaCount = substr_count($cleaned, ',');
        $dotCount = substr_count($cleaned, '.');

        if ($dotCount > 0 && $commaCount > 0) {
            // Format Indonesia: "500.000,50" => 500000.50
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        } elseif ($dotCount > 1) {
            // "20.993.500" => 20993500 (titik ribuan)
            $cleaned = str_replace('.', '', $cleaned);
        } elseif ($dotCount === 1) {
            // Ambigu: "2.500" => 2500 (titik ribuan), "500000.50" => 500000.50 (desimal)
            $fraction = substr($cleaned, strpos($cleaned, '.') + 1);
            if (strlen($fraction) === 3 && preg_match('/^\d{1,3}\.\d{3}$/', $cleaned)) {
                $cleaned = str_replace('.', '', $cleaned);
            }
        } elseif ($commaCount === 1) {
            // "500000,50" => 500000.50 (koma desimal)
            $cleaned = str_replace(',', '.', $cleaned);
        } elseif ($commaCount > 1) {
            // "20,993,500" => 20993500 (koma ribuan gaya US)
            $cleaned = str_replace(',', '', $cleaned);
        }

        $number = is_numeric($cleaned) ? (float) $cleaned : 0;
        return $isNegative ? -$number : $number;
    }
}
