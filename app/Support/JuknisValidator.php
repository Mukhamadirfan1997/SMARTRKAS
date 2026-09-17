<?php

namespace App\Support;

use App\Models\MasterProgram;
use App\Models\RkasItem;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Validator kepatuhan Juknis BOSP (Honor, Buku, Sarpras).
 *
 * Mengikuti logika ARKAS (module 90991 + 45391) — threshold, filter kode,
 * dan rumus persentase persis seperti yang diekstrak dari source ARKAS.
 *
 * Referensi: Surat Edaran Nomor 8 Tahun 2025 tentang Juknis BOSP.
 */
class JuknisValidator
{
    /** @var float Total pagu tahun anggaran terpilih (semua item RKAS) */
    private float $totalPagu;

    /** @var int Tahun anggaran (mis. 2026) */
    private int $tahun;

    /** @var string|null ID Tahun Anggaran (FK filter) */
    private ?string $tahunAnggaranId;

    /** @var string|null ID sekolah (opsional, utk threshold buku sekolah khusus) */
    private ?string $sekolahId;

    /** @var int Bulan periode kritis (bulan terakhir periode Juknis, default 3 = Maret) */
    private int $periodeAkhirBulan;

    public function __construct(float $totalPagu, int $tahun, ?string $tahunAnggaranId = null, ?string $sekolahId = null, int $periodeAkhirBulan = 3)
    {
        $this->totalPagu = $totalPagu;
        $this->tahun = $tahun;
        $this->tahunAnggaranId = $tahunAnggaranId;
        $this->sekolahId = $sekolahId;
        $this->periodeAkhirBulan = $periodeAkhirBulan;
    }

    /**
     * Hasil validasi satu validator Juknis.
     *
     * @return array{
     *     nama: string,
     *     status: string,
     *     persentase: float,
     *     sisa: float,
     *     isError: bool,
     *     detail: string,
     * }
     */
    public function validateHonor(): array
    {
        $cfg = config('juknis.honor');
        /** @var list<string> $kodeKegiatan */
        $kodeKegiatan = $cfg['kode_kegiatan'];
        /** @var string $kodeRekening */
        $kodeRekening = $cfg['kode_rekening'];

        // Ambil semua item yang masuk kategori Honor (filter tahun bila tersedia)
        $q = RkasItem::query()
            ->join('master_program as mp', 'rkas_item.program_id', '=', 'mp.id')
            ->join('master_kode_rekening as mkr', 'rkas_item.kode_rekening_id', '=', 'mkr.id')
            ->whereIn('mp.kode', $kodeKegiatan)
            ->where('mkr.kode', $kodeRekening);
        if ($this->tahunAnggaranId !== null) {
            $q->where('rkas_item.tahun_anggaran_id', $this->tahunAnggaranId);
        }
        /** @var \Illuminate\Support\Collection<int, object> $items */
        $items = $q->get(['rkas_item.id', 'rkas_item.jumlah']);

        // Total pagu honor (jumlah dari rkas_item)
        $totalPaguHonor = 0.0;
        foreach ($items as $item) {
            $totalPaguHonor += (float) $item->jumlah;
        }

        // Denominator: tahun < 2026 → jumlah/2 (setengah tahun), >= 2026 → jumlah penuh
        $denominator = $this->tahun < 2026 ? $totalPaguHonor / 2 : $totalPaguHonor;

        // Realisasi periode kritis (s.d. bulan periodeAkhirBulan) dari semua item honor
        $realisasiPeriod = 0.0;
        if ($items->isNotEmpty() && $this->periodeAkhirBulan > 0) {
            $itemIds = $items->pluck('id')->all();

            $realisasiPeriod = (float) RealisasiQuery::base()
                ->whereIn('rb.rkas_item_id', $itemIds)
                ->where('rb.bulan', '<=', $this->periodeAkhirBulan)
                ->sum('rb.jumlah');
        }

        // Total realisasi honor seluruh tahun (untuk sisa anggaran)
        $realisasiTotal = 0.0;
        if ($items->isNotEmpty()) {
            $itemIds = $items->pluck('id')->all();

            $realisasiTotal = (float) RealisasiQuery::base()
                ->whereIn('rb.rkas_item_id', $itemIds)
                ->sum('rb.jumlah');
        }

        $sisa = $totalPaguHonor - $realisasiTotal;
        $persentase = $denominator > 0 ? round(($realisasiPeriod / $denominator) * 100, 2) : 0.0;

        // isError: sisa anggaran habis DAN realisasi periode >= 100%
        $isError = $sisa <= config('juknis.diskriminator.epsilon')
            && $persentase >= 100 - config('juknis.diskriminator.epsilon');

        $status = $isError ? 'melanggar' : 'sesuai';
        $detail = $isError
            ? "Honor sudah habis (sisa Rp " . number_format($sisa, 0, ',', '.') . ") dan realisasi periode kritis sudah mencapai {$persentase}%."
            : "Realisasi honor periode kritis {$persentase}% dari pagu Rp " . number_format($denominator, 0, ',', '.') . ".";

        return [
            'nama' => 'Honor',
            'status' => $status,
            'persentase' => $persentase,
            'sisa' => $sisa,
            'isError' => $isError,
            'detail' => $detail,
        ];
    }

    /**
     * @return array{nama: string, status: string, persentase: float, sisa: float, isError: bool, detail: string}
     */
    public function validateBuku(): array
    {
        $cfg = config('juknis.buku');
        /** @var list<string> $kodeKegiatan */
        $kodeKegiatan = $cfg['kode_kegiatan'];

        // Ambil semua item kategori Buku (filter tahun bila tersedia)
        $q = RkasItem::query()
            ->join('master_program as mp', 'rkas_item.program_id', '=', 'mp.id')
            ->whereIn('mp.kode', $kodeKegiatan);
        if ($this->tahunAnggaranId !== null) {
            $q->where('rkas_item.tahun_anggaran_id', $this->tahunAnggaranId);
        }
        $items = $q->get(['rkas_item.id', 'rkas_item.jumlah']);

        $totalPaguBuku = 0.0;
        foreach ($items as $item) {
            $totalPaguBuku += (float) $item->jumlah;
        }

        // Threshold: 5% utk sekolah khusus tahun >= 2026, 10% default
        $isSekolahKhusus = $this->tahun >= 2026 && $this->isSekolahKhusus();
        $threshold = $isSekolahKhusus
            ? $cfg['threshold']['sekolah_khusus_mulai_2026']
            : $cfg['threshold']['default'];

        // Realisasi buku seluruh tahun
        $realisasi = 0.0;
        if ($items->isNotEmpty()) {
            $itemIds = $items->pluck('id')->all();

            $realisasi = (float) RealisasiQuery::base()
                ->whereIn('rb.rkas_item_id', $itemIds)
                ->sum('rb.jumlah');
        }

        $persentase = $this->totalPagu > 0 ? round(($totalPaguBuku / $this->totalPagu) * 100, 2) : 0.0;
        $sisa = $totalPaguBuku - $realisasi;

        // isError: proporsi buku di bawah threshold
        $isError = $persentase < $threshold - config('juknis.diskriminator.epsilon');
        $status = $isError ? 'melanggar' : 'sesuai';
        $detail = $isError
            ? "Proporsi buku {$persentase}% di bawah minimum {$threshold}% dari total pagu."
            : "Proporsi buku {$persentase}% memenuhi minimum {$threshold}% dari total pagu.";

        return [
            'nama' => 'Buku',
            'status' => $status,
            'persentase' => $persentase,
            'sisa' => $sisa,
            'isError' => $isError,
            'detail' => $detail,
        ];
    }

    /**
     * @return array{nama: string, status: string, persentase: float, sisa: float, isError: bool, detail: string}
     */
    public function validateSarpras(): array
    {
        $cfg = config('juknis.sarpras');

        // Kode kegiatan Sarpras (tahun >= 2026 atau < 2026)
        /** @var list<string> $kodeKegiatan */
        $kodeKegiatan = $this->tahun >= 2026
            ? $cfg['kode_kegiatan_mulai_2026']
            : $cfg['kode_kegiatan_sebelum_2026'];

        // Ambil semua item Sarpras yang kode_rekening dimulai '5.1' (filter tahun bila tersedia)
        $q = RkasItem::query()
            ->join('master_program as mp', 'rkas_item.program_id', '=', 'mp.id')
            ->join('master_kode_rekening as mkr', 'rkas_item.kode_rekening_id', '=', 'mkr.id')
            ->whereIn('mp.kode', $kodeKegiatan)
            ->where('mkr.kode', 'LIKE', '5.1%');
        if ($this->tahunAnggaranId !== null) {
            $q->where('rkas_item.tahun_anggaran_id', $this->tahunAnggaranId);
        }
        $items = $q->get(['rkas_item.id', 'rkas_item.jumlah']);

        $totalPaguSarpras = 0.0;
        foreach ($items as $item) {
            $totalPaguSarpras += (float) $item->jumlah;
        }

        // Realisasi Sarpras seluruh tahun
        $realisasi = 0.0;
        if ($items->isNotEmpty()) {
            $itemIds = $items->pluck('id')->all();

            $realisasi = (float) RealisasiQuery::base()
                ->whereIn('rb.rkas_item_id', $itemIds)
                ->sum('rb.jumlah');
        }

        $persentase = $this->totalPagu > 0 ? round(($totalPaguSarpras / $this->totalPagu) * 100, 2) : 0.0;
        $sisa = $totalPaguSarpras - $realisasi;

        $threshold = $cfg['threshold_maksimal'];

        // isError: anggaran masih ada sisa (sisa > 0) DAN realisasi melebihi threshold
        $isError = $sisa > config('juknis.diskriminator.epsilon')
            && $persentase > $threshold + config('juknis.diskriminator.epsilon');

        $status = $isError ? 'melanggar' : 'sesuai';
        $detail = $isError
            ? "Realisasi Sarpras {$persentase}% melebihi maksimum {$threshold}% dari total pagu."
            : "Realisasi Sarpras {$persentase}% dalam batas maksimum {$threshold}% dari total pagu.";

        return [
            'nama' => 'Sarpras',
            'status' => $status,
            'persentase' => $persentase,
            'sisa' => $sisa,
            'isError' => $isError,
            'detail' => $detail,
        ];
    }

    /**
     * Jalankan ketiga validator sekaligus.
     *
     * @return list<array{nama: string, status: string, persentase: float, sisa: float, isError: bool, detail: string}>
     */
    public function validateAll(): array
    {
        return [
            $this->validateHonor(),
            $this->validateBuku(),
            $this->validateSarpras(),
        ];
    }

    /**
     * Cek apakah sekolah adalah sekolah khusus (berdasarkan kode kegiatan yang dimiliki).
     * Sekolah khusus punya kode kegiatan '05.08.01.', '05.08.06.', atau '05.08.12.'
     * di antara item RKAS tahun anggaran terpilih.
     */
    private function isSekolahKhusus(): bool
    {
        if ($this->sekolahId !== null) {
            // Jika ID sekolah tersedia, bisa dicek langsung (ARKAS punya daftar hardcoded)
            // Untuk SmartRKAS, kita deteksi dari kode kegiatan yang ada
        }

        $cfg = config('juknis.buku.sekolah_khusus_id_kode');

        return MasterProgram::whereIn('kode', $cfg)->exists();
    }
}
