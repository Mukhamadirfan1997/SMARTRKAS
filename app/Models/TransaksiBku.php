<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $rkas_item_id
 * @property string $tahun_anggaran_id
 * @property string|null $sumber_dana_id
 * @property string $tanggal
 * @property int|null $bulan
 * @property string $no_bukti
 * @property string $jenis
 * @property float $jumlah
 * @property float|null $volume
 * @property string|null $satuan
 * @property string|null $toko_penerima
 * @property string|null $metode_pengadaan
 * @property string|null $uraian
 * @property string|null $override_note
 * @property int $tahap
 * @property bool $status_lunas
 * @property float|null $saldo_berjalan
 * @property int $created_by
 * @property string|null $deleted_at
 * @property string $jenis_belanja
 * @property float $total
 * @property float $siplah
 * @property float $non_siplah
 * @property float $belum_diisi
 * @property float $persen_siplah
 * @property float $persen_non_siplah
 * @use HasFactory<\Database\Factories\TransaksiBkuFactory>
 */
class TransaksiBku extends Model
{
    /** @use HasFactory<\Database\Factories\TransaksiBkuFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'transaksi_bku';

    protected $fillable = [
        'rkas_item_id',
        'tahun_anggaran_id',
        'sumber_dana_id',
        'tanggal',
        'bulan',
        'no_bukti',
        'jenis',
        'jumlah',
        'volume',
        'satuan',
        'toko_penerima',
        'metode_pengadaan',
        'uraian',
        'override_note',
        'tahap',
        'status_lunas',
        'saldo_berjalan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status_lunas' => 'boolean',
        ];
    }

    private ?bool $masihOverBudgetCache = null;

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\RkasItem, $this> */
    public function rkasItem(): BelongsTo
    {
        return $this->belongsTo(RkasItem::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\TahunAnggaran, $this> */
    public function tahunAnggaran(): BelongsTo
    {
        return $this->belongsTo(TahunAnggaran::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\Kwitansi, $this> */
    public function kwitansi(): HasOne
    {
        return $this->hasOne(Kwitansi::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\SumberDana, $this> */
    public function sumberDana(): BelongsTo
    {
        return $this->belongsTo(SumberDana::class);
    }

    /**
     * Transaksi dibuat lewat override dan item RKAS terkait masih over budget
     * (realisasi kumulatif sampai bulan transaksi > rencana kumulatif).
     */
    public function masihOverBudget(): bool
    {
        if ($this->masihOverBudgetCache !== null) {
            return $this->masihOverBudgetCache;
        }

        if (empty($this->override_note)) {
            return $this->masihOverBudgetCache = false;
        }

        if (strtolower((string) $this->jenis) !== 'pengeluaran') {
            return $this->masihOverBudgetCache = false;
        }

        $item = $this->rkasItem;
        if ($item === null) {
            return $this->masihOverBudgetCache = false;
        }

        $bulan = max(1, (int) $this->bulan);

        $rencana = (float) $item->bulanRencana
            ->where('bulan', '<=', $bulan)
            ->sum('rencana');

        $realisasi = (float) $item->transaksiBkus
            ->where('jenis', 'pengeluaran')
            ->where('bulan', '<=', $bulan)
            ->sum('jumlah');

        return $this->masihOverBudgetCache = $realisasi > $rencana;
    }
}
