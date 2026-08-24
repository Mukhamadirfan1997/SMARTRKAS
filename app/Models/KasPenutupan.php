<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hasil opname penutupan kas per bulan (Formulir BOS-K7b).
 * Satu baris per (tahun_anggaran, bulan, sumber_dana) — di-upsert, tanpa soft delete.
 *
 * @property string $id
 * @property string $tahun_anggaran_id
 * @property int $bulan
 * @property string|null $sumber_dana_id
 * @property \Illuminate\Support\Carbon|null $tanggal_penutupan
 * @property int $lembar_100000
 * @property int $lembar_50000
 * @property int $lembar_20000
 * @property int $lembar_10000
 * @property int $lembar_5000
 * @property int $lembar_2000
 * @property int $lembar_1000
 * @property int $keping_500
 * @property int $keping_200
 * @property int $keping_100
 * @property int $keping_50
 * @property float $saldo_bank
 * @property string|null $catatan
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class KasPenutupan extends Model
{
    /** @use HasFactory<\Database\Factories\KasPenutupanFactory> */
    use HasFactory, HasUuids;

    protected $table = 'kas_penutupan';

    protected $fillable = [
        'tahun_anggaran_id',
        'bulan',
        'sumber_dana_id',
        'tanggal_penutupan',
        'lembar_100000',
        'lembar_50000',
        'lembar_20000',
        'lembar_10000',
        'lembar_5000',
        'lembar_2000',
        'lembar_1000',
        'keping_500',
        'keping_200',
        'keping_100',
        'keping_50',
        'saldo_bank',
        'catatan',
    ];

    protected $casts = [
        'bulan' => 'integer',
        'tanggal_penutupan' => 'date',
        'lembar_100000' => 'integer',
        'lembar_50000' => 'integer',
        'lembar_20000' => 'integer',
        'lembar_10000' => 'integer',
        'lembar_5000' => 'integer',
        'lembar_2000' => 'integer',
        'lembar_1000' => 'integer',
        'keping_500' => 'integer',
        'keping_200' => 'integer',
        'keping_100' => 'integer',
        'keping_50' => 'integer',
        'saldo_bank' => 'float',
    ];

    /**
     * Denominasi uang kertas: kolom => nominal.
     *
     * @return array<string, int>
     */
    public static function daftarKertas(): array
    {
        return [
            'lembar_100000' => 100000,
            'lembar_50000' => 50000,
            'lembar_20000' => 20000,
            'lembar_10000' => 10000,
            'lembar_5000' => 5000,
            'lembar_2000' => 2000,
            'lembar_1000' => 1000,
        ];
    }

    /**
     * Denominasi uang logam: kolom => nominal.
     *
     * @return array<string, int>
     */
    public static function daftarLogam(): array
    {
        return [
            'keping_500' => 500,
            'keping_200' => 200,
            'keping_100' => 100,
            'keping_50' => 50,
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\TahunAnggaran, $this> */
    public function tahunAnggaran(): BelongsTo
    {
        return $this->belongsTo(TahunAnggaran::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\SumberDana, $this> */
    public function sumberDana(): BelongsTo
    {
        return $this->belongsTo(SumberDana::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function subtotalKertas(): float
    {
        $total = 0.0;
        foreach (self::daftarKertas() as $kolom => $nominal) {
            $total += (int) $this->getAttribute($kolom) * $nominal;
        }

        return $total;
    }

    public function subtotalLogam(): float
    {
        $total = 0.0;
        foreach (self::daftarLogam() as $kolom => $nominal) {
            $total += (int) $this->getAttribute($kolom) * $nominal;
        }

        return $total;
    }

    /** Sub-Total A fisik: uang kertas + uang logam. */
    public function subtotalFisik(): float
    {
        return $this->subtotalKertas() + $this->subtotalLogam();
    }

    /** Total kas riil yang diperiksa: fisik + saldo bank rekening koran. */
    public function totalRiil(): float
    {
        return $this->subtotalFisik() + (float) $this->saldo_bank;
    }
}
