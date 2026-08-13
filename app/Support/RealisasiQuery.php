<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Sumber data realisasi per item RKAS yang menggabungkan dua jalur belanja:
 *  1. transaksi_bku jenis=pengeluaran (baris aktif, rkas_item_id tidak null) —
 *     input BKU single-item;
 *  2. nota_bku_item (join nota_bku, hanya nota aktif) — rincian nota multi-item.
 *
 * Satu nota multi-item dibukukan sebagai SATU transaksi_bku (rkas_item_id = null),
 * sehingga realisasi per item dari nota diambil dari nota_bku_item, bukan transaksi.
 *
 * Kolom hasil union: id, rkas_item_id, bulan, jumlah.
 */
final class RealisasiQuery
{
    public static function union(): Builder
    {
        $transaksi = DB::table('transaksi_bku')
            ->where('jenis', 'pengeluaran')
            ->whereNull('deleted_at')
            ->whereNotNull('rkas_item_id')
            ->selectRaw('id, rkas_item_id, bulan, jumlah');

        $nota = DB::table('nota_bku_item as nbi')
            ->join('nota_bku as nb', 'nb.id', '=', 'nbi.nota_bku_id')
            ->whereNull('nb.deleted_at')
            ->selectRaw('nbi.id, nbi.rkas_item_id, nb.bulan as bulan, nbi.subtotal as jumlah');

        return $transaksi->union($nota);
    }

    /**
     * Bungkus union sebagai derived table (SELECT * FROM ({union}) AS {alias})
     * agar bisa di-join / where / sum / groupBy seperti query biasa.
     */
    public static function base(string $alias = 'rb'): Builder
    {
        return DB::query()->fromSub(self::union(), $alias);
    }
}
