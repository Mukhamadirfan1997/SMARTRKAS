<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $nama_template
 * @property string $kode_rekening_id
 * @property string $kegiatan_id
 * @property string $uraian_item_snapshot
 * @property string|null $toko_penerima
 * @property string|null $metode_pengadaan
 * @property string|null $uraian_dasar
 * @property string|null $sumber_dana_id
 * @property int $created_by
 * @property string|null $deleted_at
 * @property \App\Models\MasterKodeRekening|null $kodeRekening
 * @property \App\Models\MasterProgram|null $kegiatan
 * @property \App\Models\SumberDana|null $sumberDana
 * @property \App\Models\User $createdByUser
 * @use HasFactory<\Database\Factories\TransaksiTemplateFactory>
 */
class TransaksiTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\TransaksiTemplateFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'transaksi_template';

    protected $fillable = [
        'nama_template',
        'kode_rekening_id',
        'kegiatan_id',
        'uraian_item_snapshot',
        'toko_penerima',
        'metode_pengadaan',
        'uraian_dasar',
        'sumber_dana_id',
        'created_by',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\MasterKodeRekening, $this> */
    public function kodeRekening(): BelongsTo
    {
        return $this->belongsTo(MasterKodeRekening::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\MasterProgram, $this> */
    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(MasterProgram::class, 'kegiatan_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\SumberDana, $this> */
    public function sumberDana(): BelongsTo
    {
        return $this->belongsTo(SumberDana::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this> */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Cari RkasItem di tahun anggaran aktif yang match kode_rekening_id + kegiatan_id
     * + uraian mirip (normalizeUraian). Mengembalikan item pertama yang cocok, atau null.
     */
    public function cariItemDiTahunAktif(): ?RkasItem
    {
        $tahunAktif = TahunAnggaran::where('status', true)->first();

        if ($tahunAktif === null) {
            return null;
        }

        $normalized = RkasItem::normalizeUraian($this->uraian_item_snapshot);

        $candidates = RkasItem::query()
            ->where('tahun_anggaran_id', $tahunAktif->id)
            ->where('kode_rekening_id', $this->kode_rekening_id)
            ->where('program_id', $this->kegiatan_id)
            ->get();

        foreach ($candidates as $item) {
            if (RkasItem::normalizeUraian((string) $item->uraian) === $normalized) {
                return $item;
            }
        }

        return null;
    }
}
