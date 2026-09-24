<?php

namespace App\Imports;

use App\Models\JenisBelanja;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\SumberDana;
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
 *  - net-zero per scope (pergeseran: per sumber_dana + kelompok [Modal vs Barjas];
 *    PAK: per sumber_dana) dengan toleransi ~Rp1. Kelompok Modal = jenis_belanja
 *    yang mengandung kata "Modal", sisanya Barjas.
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

        // Deteksi hapus otomatis: item di DB bulan ini yang tidak ada di file
        // File pergeseran dari PDF ARKAS diharapkan full per bulan; baris hilang
        // berarti dihapus di ARKAS (rencana -> 0). Diperketat: hanya jika file
        // tampak full (>=80% baris DB) agar file parsial (hanya 1-2 baris ubahan)
        // tidak menghapus massal. Item yang sudah terpakai tidak boleh dihapus
        // (akan ditolak di validate: sesudah 0 < realisasi).
        $presentKeys = [];
        for ($i = $startRow - 1; $i < count($rows); $i++) {
            $row = $rows[$i] ?? [];
            $noUrut = trim((string) $this->cell($row, 'no_urut', $columns));
            $uraianTmp = trim((string) $this->cell($row, 'uraian', $columns));
            $kodeRekTmp = trim((string) $this->cell($row, 'kode_rekening', $columns));
            $kodeProgTmp = str_replace(' ', '', trim((string) $this->cell($row, 'kode_program', $columns)));
            if (!is_numeric($noUrut) || $uraianTmp === '') {
                continue;
            }
            $progTmp = MasterProgram::where('kode', $kodeProgTmp)->first();
            $rekTmp = MasterKodeRekening::where('kode', rtrim($kodeRekTmp, '.'))->first();
            if (!$progTmp || !$rekTmp) {
                continue;
            }
            $presentKeys[] = RkasItem::normalizeUraian($uraianTmp) . '|' . $rekTmp->id . '|' . $progTmp->id;
        }
        $presentKeys = array_unique($presentKeys);

        $dbItemsBulan = RkasItem::where('tahun_anggaran_id', $this->tahunAnggaranId)
            ->where('sumber_dana_id', $this->sumberDanaId)
            ->whereHas('bulanRencana', fn ($q) => $q->where('bulan', $bulan)->where('rencana', '>', 0))
            ->with(['kodeRekening', 'program'])
            ->get();

        $dbCount = $dbItemsBulan->count();
        $fileCount = count($presentKeys);
        $isFullFile = $dbCount > 0 && $fileCount >= $dbCount * 0.8;

        if ($isFullFile) {
            foreach ($dbItemsBulan as $dbItem) {
                $key = RkasItem::normalizeUraian((string) $dbItem->uraian) . '|' . $dbItem->kode_rekening_id . '|' . $dbItem->program_id;
                if (in_array($key, $presentKeys, true)) {
                    continue;
                }
                $sebelumHapus = (float) RkasItemBulan::where('rkas_item_id', $dbItem->id)->where('bulan', $bulan)->value('rencana');
                if ($sebelumHapus < 0.005) {
                    continue;
                }
                $diffs[] = [
                    'rkas_item_id'    => $dbItem->id,
                    'no_urut'         => (int) $dbItem->no_urut,
                    'bulan'           => $bulan,
                    'uraian'          => (string) $dbItem->uraian,
                    'program_id'      => $dbItem->program_id,
                    'kode_rekening_id'=> $dbItem->kode_rekening_id,
                    'jenis_belanja_id'=> $dbItem->kodeRekening->jenis_belanja_id,
                    'sumber_dana_id'  => $this->sumberDanaId,
                    'volume'          => 0,
                    'satuan'          => (string) $dbItem->satuan,
                    'tarif'           => 0,
                    'sebelum'         => $sebelumHapus,
                    'sesudah'         => 0.0,
                    'delta'           => -$sebelumHapus,
                    'arah'            => 'turun',
                    'realisasi'       => $dbItem->realisasiTotal(),
                ];
            }
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
            $sesudah = (float) ($row['sesudah'] ?? 0);
            $sebelum = (float) ($row['sebelum'] ?? 0);
            if ($sesudah < $realisasi - 0.005) {
                $sisaBoleh = max(0.0, $sebelum - $realisasi);
                $errors[] = "Item '{$row['uraian']}' (bulan {$row['bulan']}) sudah terpakai Rp " . number_format($realisasi, 0, ',', '.') . " — rencana baru Rp " . number_format($sesudah, 0, ',', '.') . " di bawah realisasi. Sisa yang boleh dialihkan hanya Rp " . number_format($sisaBoleh, 0, ',', '.') . " (boleh turun sampai Rp " . number_format($realisasi, 0, ',', '.') . " via pergeseran/PAK). Net-zero tidak seimbang pada item ini.";
            }
        }

        $isPak = strtolower($this->jenis) === 'pak';

        if ($isPak) {
            $totals = [];
            $sumberMap = SumberDana::whereIn('id', $rows->pluck('sumber_dana_id')->filter()->unique()->values()->all())
                ->pluck('nama', 'id');

            foreach ($rows as $row) {
                $key = (string) $row['sumber_dana_id'];
                $totals[$key] = ($totals[$key] ?? 0.0) + (float) $row['delta'];
            }

            foreach ($totals as $sumberId => $total) {
                if (abs($total) > 1.0) {
                    $sumberNama = (string) ($sumberMap[$sumberId] ?? $sumberId);
                    $kelebihan = $total > 0 ? 'kelebihan' : 'kekurangan';
                    $errors[] = "PAK tidak seimbang pada {$sumberNama} — selisih Rp " . number_format(abs($total), 2, ',', '.') . " ({$kelebihan}). Net-zero tidak seimbang pada scope '{$sumberId}' (PAK harus seimbang per Sumber Dana, Modal <-> Barjas boleh).";
                }
            }
        } else {
            $jenisIds = $rows->pluck('jenis_belanja_id')->filter()->unique()->values()->all();
            $jenisMap = JenisBelanja::whereIn('id', $jenisIds)->pluck('nama', 'id');
            $sumberMap = SumberDana::whereIn('id', $rows->pluck('sumber_dana_id')->filter()->unique()->values()->all())
                ->pluck('nama', 'id');

            $totals = [];
            $kelompokMap = [];

            foreach ($rows as $row) {
                $jenisNama = (string) ($jenisMap[$row['jenis_belanja_id']] ?? '');
                $kelompok = str_contains(mb_strtolower($jenisNama), 'modal') ? 'Modal' : 'Barjas';
                $key = (string) $row['sumber_dana_id'] . '|' . $kelompok;
                $totals[$key] = ($totals[$key] ?? 0.0) + (float) $row['delta'];
                $kelompokMap[$key] = $kelompok;
            }

            foreach ($totals as $scope => $total) {
                if (abs($total) > 1.0) {
                    [$sumberId, $kelompok] = explode('|', $scope, 2) + ['', ''];
                    $sumberNama = (string) ($sumberMap[$sumberId] ?? $sumberId);
                    $kelompok = $kelompokMap[$scope] ?? $kelompok;
                    $kelebihan = $total > 0 ? 'kelebihan' : 'kekurangan';
                    $saran = $total > 0 ? 'Kurangi' : 'Tambah';
                    $errors[] = "Pergeseran tidak seimbang pada {$sumberNama} — Kelompok {$kelompok} {$kelebihan} Rp " . number_format(abs($total), 2, ',', '.') . ". Net-zero tidak seimbang pada scope '{$scope}' (Pergeseran harus seimbang Modal->Modal dan Barjas->Barjas, {$saran} Rp " . number_format(abs($total), 2, ',', '.') . " di kelompok {$kelompok} yang sama).";
                }
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
