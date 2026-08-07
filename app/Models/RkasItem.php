<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tahun_anggaran_id
 * @property int|null $no_urut
 * @property string $uraian
 * @property string|null $program_id
 * @property string|null $kode_rekening_id
 * @property string|null $sumber_dana_id
 * @property float|null $volume
 * @property string|null $satuan
 * @property float|null $tarif
 * @property float $jumlah
 * @property float $realisasi_sum
 * @property array<int, float> $realisasi_per_bulan
 * @property float $total_realisasi
 * @property float $total_rencana
 * @property float $persen
 * @property float $rencana_bulan
 * @property float $realisasi_bulan
 * @property float $sisa
 * @property float $sisa_bulan
 * @property float $total
 * @property float $persentase
 * @property string $nama
 * @property float $m0
 * @property float $m1
 * @property float $m2
 * @property float $dynamic_rencana
 * @property float $dynamic_realisasi
 * @property float $dynamic_sisa
 * @property float $dynamic_rencana_volume
 * @property float $dynamic_realisasi_volume
 * @property float $dynamic_sisa_volume
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @use HasFactory<\Database\Factories\RkasItemFactory>
 */
class RkasItem extends Model
{
    /** @use HasFactory<\Database\Factories\RkasItemFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'rkas_item';

    protected $fillable = [
        'tahun_anggaran_id',
        'no_urut',
        'uraian',
        'program_id',
        'kode_rekening_id',
        'sumber_dana_id',
        'volume',
        'satuan',
        'tarif',
        'jumlah',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\TahunAnggaran, $this> */
    public function tahunAnggaran(): BelongsTo
    {
        return $this->belongsTo(TahunAnggaran::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\MasterProgram, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(MasterProgram::class, 'program_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\MasterKodeRekening, $this> */
    public function kodeRekening(): BelongsTo
    {
        return $this->belongsTo(MasterKodeRekening::class, 'kode_rekening_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\SumberDana, $this> */
    public function sumberDana(): BelongsTo
    {
        return $this->belongsTo(SumberDana::class, 'sumber_dana_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\TransaksiBku, $this> */
    public function transaksiBkus(): HasMany
    {
        return $this->hasMany(TransaksiBku::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\RkasItemBulan, $this> */
    public function bulanRencana(): HasMany
    {
        return $this->hasMany(RkasItemBulan::class, 'rkas_item_id');
    }

    /**
     * Sisa anggaran kumulatif s.d. bulan tertentu: total rencana (rkas_item_bulan)
     * dikurangi total realisasi pengeluaran (transaksi_bku) sampai bulan itu.
     * Nilai inilah yang diperiksa oleh guard input BKU (store/update), jadi
     * tampilan picker/form harus memakai nilai yang sama agar tidak ambigu.
     */
    public function sisaKumulatifSd(int $bulan): float
    {
        $rencana = (float) RkasItemBulan::query()
            ->where('rkas_item_id', $this->id)
            ->where('bulan', '<=', $bulan)
            ->sum('rencana');

        $realisasi = (float) $this->transaksiBkus()
            ->where('jenis', 'pengeluaran')
            ->where('bulan', '<=', $bulan)
            ->sum('jumlah');

        return $rencana - $realisasi;
    }

    /**
     * Normalisasi uraian agar dua uraian yang hampir sama (spasi ganda, kapital)
     * dianggap identik saat mencocokkan item RKAS.
     */
    public static function normalizeUraian(string $uraian): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', $uraian);

        return mb_strtolower(trim($collapsed ?? $uraian));
    }

    /**
     * Beri no_urut unik berurutan (1..N) untuk semua item tahun anggaran tertentu.
     * Mengembalikan jumlah item yang berubah.
     */
    public static function renumber(string $tahunAnggaranId): int
    {
        $items = self::query()
            ->withTrashed()
            ->where('tahun_anggaran_id', $tahunAnggaranId)
            ->orderBy('no_urut')
            ->orderBy('created_at')
            ->get();

        $updated = 0;
        $seq = 0;

        foreach ($items as $item) {
            $seq++;
            if ((int) $item->no_urut === $seq) {
                continue;
            }

            $item->updateQuietly(['no_urut' => $seq]);
            $updated++;
        }

        return $updated;
    }

    /**
     * Sinkronkan jumlah agar sama dengan total rencana semua bulan (rkas_item_bulan)
     * untuk tahun anggaran tertentu. Mengembalikan jumlah item yang berubah.
     */
    public static function syncJumlah(string $tahunAnggaranId): int
    {
        $items = self::query()
            ->withTrashed()
            ->where('tahun_anggaran_id', $tahunAnggaranId)
            ->get(['id', 'jumlah']);

        $updated = 0;

        foreach ($items as $item) {
            $sum = (float) RkasItemBulan::where('rkas_item_id', $item->id)->sum('rencana');

            if ((float) $item->jumlah !== $sum) {
                $item->updateQuietly(['jumlah' => $sum]);
                $updated++;
            }
        }

        return $updated;
    }
}
