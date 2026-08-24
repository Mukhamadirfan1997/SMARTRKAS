<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Semantik Formulir BOS-K7b/K7c resmi (gabungan kas + bank):
     * - Pencairan bank dicatat sebagai Penerimaan biasa (masuk ke kolom D).
     * - Tarik tunai adalah MUTASI internal (bank -> kas tunai): tetap tersimpan
     *   sebagai Penerimaan agar jejak dokumen utuh, tapi diberi tanda
     *   kategori_arus = 'mutasi' sehingga bernilai NETRAL (0) dalam perhitungan
     *   penerimaan (D), saldo berjalan BKU, dan kartu total.
     * - Tabel kas_penutupan menyimpan hasil opname fisik per bulan
     *   (denominasi + saldo bank riil dari rekening koran).
     */
    public function up(): void
    {
        Schema::table('transaksi_bku', function (Blueprint $table) {
            $table->string('kategori_arus', 20)->nullable()->after('uraian');
        });

        // Backfill: penerimaan tarik tunai lama menjadi mutasi netral.
        // Menangkap juga typo umum "Tari Tunai".
        DB::table('transaksi_bku')
            ->whereRaw("LOWER(jenis) = 'penerimaan'")
            ->whereNull('kategori_arus')
            ->where(function ($q) {
                $q->whereRaw("LOWER(COALESCE(uraian, '')) LIKE '%tarik tunai%'")
                    ->orWhereRaw("LOWER(COALESCE(uraian, '')) LIKE '%tari tunai%'");
            })
            ->update(['kategori_arus' => 'mutasi']);

        Schema::create('kas_penutupan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tahun_anggaran_id');
            $table->unsignedTinyInteger('bulan');
            $table->uuid('sumber_dana_id')->nullable();
            $table->date('tanggal_penutupan')->nullable();
            // Rincian uang kertas (jumlah lembar)
            $table->integer('lembar_100000')->default(0);
            $table->integer('lembar_50000')->default(0);
            $table->integer('lembar_20000')->default(0);
            $table->integer('lembar_10000')->default(0);
            $table->integer('lembar_5000')->default(0);
            $table->integer('lembar_2000')->default(0);
            $table->integer('lembar_1000')->default(0);
            // Rincian uang logam (jumlah keping)
            $table->integer('keping_500')->default(0);
            $table->integer('keping_200')->default(0);
            $table->integer('keping_100')->default(0);
            $table->integer('keping_50')->default(0);
            // Saldo rekening koran riil
            $table->decimal('saldo_bank', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('tahun_anggaran_id')->references('id')->on('tahun_anggaran')->restrictOnDelete();
            $table->foreign('sumber_dana_id')->references('id')->on('sumber_dana')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tahun_anggaran_id', 'bulan', 'sumber_dana_id'], 'kas_penutupan_periode_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kas_penutupan');

        Schema::table('transaksi_bku', function (Blueprint $table) {
            $table->dropColumn('kategori_arus');
        });
    }
};
