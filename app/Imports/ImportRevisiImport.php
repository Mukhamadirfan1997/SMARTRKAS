<?php

namespace App\Imports;

use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Parser file revisi pergeseran/PAK per bulan (satu file = satu bulan),
 * "diff-first": file dibaca, item RKAS dicocokkan, lalu dihasilkan diff per
 * (item, bulan) — TIDAK menulis apa pun ke database.
 *
 * Guard yang dilakukan di level parser (all-or-nothing, dipanggil controller):
 *  - jumlah negatif ditolak;
 *  - item yang menjadi SUMBER (turun) tapi sudah ber-realisasi ditolak —
 *    "ber-realisasi" diartikan lintas-bulan (total realisasi seluruh bulan
 *    dalam tahun anggaran), bukan hanya s.d. bulan file, agar item dengan
 *    realisasi di bulan yang lebih akhir dari bulan file tetap tidak bisa
 *    dijadikan sumber;
 *  - net-zero per scope (pergeseran: per sumber_dana + jenis_belanja; PAK: per
 *    sumber_dana) dengan toleransi ~Rp1.
 *
 * Pola header/kolom sama dengan template import RKAS (RkasImportHeaderDetector).
 */
class ImportRevisiImport
{
    protected string $tahunAnggaranId;

    protected string $sumberDanaId;

    protected string $jenis;

    public function __construct(string $tahunAnggaranId, string $sumberDanaId, string $jenis)
    {
        $this->tahunAnggaranId = $tahunAnggaranId;
        $this->sumberDanaId = $sumberDanaId;
        $this->jenis = $jenis;
    }

    /**
     * Baca satu file bulanan dan hasilkan diff per (item, bulan).
     *
     * @return array{rows: \Illuminate\Support\Collection<int, array<string, mixed>>, errors: array<int, string>}
     */
    public function diff(string $filePath, int $bulan): array
    {
        $detected = RkasImportHeaderDetector::detectColumns($filePath);
        $rows = self::readRows($filePath);
        $startRow = $detected['start_row'];
        $columns = $detected['columns'];

        /** @var array<int, array<string, mixed>> $diffs */
        $diffs = [];
        $errors = [];

        for ($i = $startRow - 1; $i < count($rows); $i++) {
            $row = $rows[$i] ?? [];

            $noUrut       = trim((string) $this->cell($row, 'no_urut', $columns));
            $kodeRekening = trim((string) $this->cell($row, 'kode_rekening', $columns));
            $kodeProgram  = trim((string) $this->cell($row, 'kode_program', $columns));
            $uraian       = trim((string) $this->cell($row, 'uraian', $columns));
            $volume       = $this->cell($row, 'volume', $columns);
            $satuan       = trim((string) $this->cell($row, 'satuan', $columns));
            $tarif        = $this->cell($row, 'tarif', $columns);
            $jumlah       = $this->cell($row, 'jumlah', $columns);

            if (!is_numeric($noUrut) || empty($uraian)) {
                continue;
            }

            if ($jumlah === null || !preg_match('/\d/', (string) $jumlah)) {
                continue;
            }

            $parsedJumlah = $this->parseNumber($jumlah);
            if ($parsedJumlah < 0) {
                $errors[] = "No. Urut $noUrut: Jumlah tidak boleh negatif ($parsedJumlah)";
                continue;
            }

            $parsedVolume = $this->parseNumber($volume);
            if ($parsedVolume < 0) {
                $errors[] = "No. Urut $noUrut: Volume tidak boleh negatif";
                continue;
            }

            $parsedTarif = $this->parseNumber($tarif);
            if ($parsedTarif < 0) {
                $errors[] = "No. Urut $noUrut: Tarif tidak boleh negatif";
                continue;
            }

            if (empty($kodeRekening)) {
                $errors[] = "No. Urut $noUrut: Kode rekening kosong ($uraian)";
                continue;
            }

            $kodeProgram = str_replace(' ', '', $kodeProgram);

            $program = null;
            if (!empty($kodeProgram)) {
                $program = MasterProgram::where('kode', $kodeProgram)->first();
            }

            if (!$program) {
                $errors[] = "No. Urut $noUrut: Program tidak ditemukan ($kodeProgram)";
                continue;
            }

            $kodeRekeningRecord = MasterKodeRekening::where('kode', rtrim($kodeRekening, '.'))->first();

            if (!$kodeRekeningRecord) {
                $errors[] = "No. Urut $noUrut: Kode rekening tidak ditemukan ($kodeRekening)";
                continue;
            }

            $item = $this->resolveItem($program->id, $kodeRekeningRecord->id, $uraian);

            $sebelum = 0.0;
            if ($item !== null) {
                $sebelum = (float) RkasItemBulan::where('rkas_item_id', $item->id)
                    ->where('bulan', $bulan)
                    ->value('rencana');
            }

            $sesudah = (float) $parsedJumlah;
            $delta = round($sesudah - $sebelum, 2);

            if (abs($delta) < 0.005) {
                continue;
            }

            $realisasi = $item !== null ? $item->realisasiTotal() : 0.0;

            $diffs[] = [
                'rkas_item_id'    => $item?->id,
                'no_urut'         => (int) $noUrut,
                'bulan'           => $bulan,
                'uraian'          => $uraian,
                'program_id'      => $program->id,
                'kode_rekening_id'=> $kodeRekeningRecord->id,
                'jenis_belanja_id'=> $kodeRekeningRecord->jenis_belanja_id,
                'sumber_dana_id'  => $this->sumberDanaId,
                'volume'          => $parsedVolume,
                'satuan'          => $satuan,
                'tarif'           => $parsedTarif,
                'sebelum'         => $sebelum,
                'sesudah'         => $sesudah,
                'delta'           => $delta,
                'arah'            => $delta > 0 ? 'naik' : 'turun',
                'realisasi'       => $realisasi,
            ];
        }

        return ['rows' => collect($diffs), 'errors' => $errors];
    }

    /**
     * Validasi kumpulan diff seluruh file (semua bulan) secara all-or-nothing.
     * Net-zero dihitung per scope; item ber-realisasi tidak boleh menjadi sumber.
     *
     * @param Collection<int, array<string, mixed>> $rows
     * @return array{ok: bool, errors: array<int, string>}
     */
    public function validate(Collection $rows): array
    {
        $errors = [];

        foreach ($rows as $row) {
            $realisasi = (float) ($row['realisasi'] ?? 0);
            if (($row['arah'] ?? '') === 'turun' && $realisasi > 0) {
                $errors[] = "Item '{$row['uraian']}' (bulan {$row['bulan']}) menjadi SUMBER (turun) tapi sudah ber-realisasi — tidak diizinkan.";
            }
        }

        $totals = [];
        foreach ($rows as $row) {
            $key = strtolower($this->jenis) === 'pak'
                ? (string) $row['sumber_dana_id']
                : (string) $row['sumber_dana_id'] . '|' . (string) $row['jenis_belanja_id'];

            $totals[$key] = ($totals[$key] ?? 0.0) + (float) $row['delta'];
        }

        foreach ($totals as $scope => $total) {
            if (abs($total) > 1.0) {
                $errors[] = "Net-zero tidak seimbang pada scope '$scope' (selisih Rp " . number_format($total, 2, ',', '.') . ').';
            }
        }

        return ['ok' => $errors === [], 'errors' => $errors];
    }

    /**
     * @param array<int, mixed> $row
     * @param array<string, int> $columns
     */
    protected function cell(array $row, string $field, array $columns): mixed
    {
        if (isset($columns[$field])) {
            return $row[$columns[$field] - 1] ?? null;
        }

        return $row[$field] ?? null;
    }

    /**
     * Cari item RKAS yang cocok berdasarkan (tahun, sumber dana, program,
     * kode rekening, uraian). TIDAK membuat item baru (beda dengan RkasImport).
     */
    protected function resolveItem(string $programId, string $kodeRekeningId, string $uraian): ?RkasItem
    {
        $normalized = RkasItem::normalizeUraian($uraian);

        /** @var Collection<int, RkasItem> $candidates */
        $candidates = RkasItem::where('tahun_anggaran_id', $this->tahunAnggaranId)
            ->where('sumber_dana_id', $this->sumberDanaId)
            ->where('program_id', $programId)
            ->where('kode_rekening_id', $kodeRekeningId)
            ->orderBy('id')
            ->get(['id', 'uraian']);

        return $candidates->first(
            fn (RkasItem $item): bool => RkasItem::normalizeUraian((string) $item->uraian) === $normalized
        );
    }

    protected function parseNumber(mixed $value): float|int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $cleaned = preg_replace('/[^0-9\,\.\-]/', '', (string) $value);
        $isNegative = str_starts_with($cleaned, '-');
        $cleaned = str_replace('-', '', $cleaned);

        $commaCount = substr_count($cleaned, ',');
        $dotCount = substr_count($cleaned, '.');

        if ($dotCount > 0 && $commaCount > 0) {
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        } elseif ($dotCount > 1) {
            $cleaned = str_replace('.', '', $cleaned);
        } elseif ($dotCount === 1) {
            $fraction = substr($cleaned, strpos($cleaned, '.') + 1);
            if (strlen($fraction) === 3 && preg_match('/^\d{1,3}\.\d{3}$/', $cleaned)) {
                $cleaned = str_replace('.', '', $cleaned);
            }
        } elseif ($commaCount === 1) {
            $cleaned = str_replace(',', '.', $cleaned);
        } elseif ($commaCount > 1) {
            $cleaned = str_replace(',', '', $cleaned);
        }

        $number = is_numeric($cleaned) ? (float) $cleaned : 0;

        return $isNegative ? -$number : $number;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private static function readRows(string $filePath): array
    {
        $import = new class implements WithMultipleSheets {
            /**
             * @return array<int, object>
             */
            public function sheets(): array
            {
                return [0 => new class {
                }];
            }
        };

        $sheets = Excel::toArray($import, $filePath);

        return $sheets[0] ?? [];
    }
}
