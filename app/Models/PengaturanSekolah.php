<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan identitas sekolah (1 baris tunggal).
 *
 * @property int $id
 * @property string|null $npsn
 * @property string $nama
 * @property string|null $alamat
 * @property string|null $kecamatan
 * @property string|null $kabupaten
 * @property string|null $provinsi
 * @property string|null $telepon
 * @property string|null $email
 * @property string|null $nama_kepsek
 * @property string|null $nip_kepsek
 * @property string|null $nama_bendahara
 * @property string|null $nip_bendahara
 */
class PengaturanSekolah extends Model
{
    protected $table = 'pengaturan_sekolah';

    protected $fillable = [
        'npsn',
        'nama',
        'alamat',
        'kecamatan',
        'kabupaten',
        'provinsi',
        'telepon',
        'email',
        'nama_kepsek',
        'nip_kepsek',
        'nama_bendahara',
        'nip_bendahara',
    ];

    public static function get(): ?self
    {
        return static::first();
    }
}
