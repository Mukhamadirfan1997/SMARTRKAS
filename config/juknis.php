<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Juknis BOSP Threshold Constants
    |--------------------------------------------------------------------------
    |
    | Nilai threshold dari ARKAS (module 45391). Digunakan oleh JuknisValidator
    | untuk menentukan batas kepatuhan Honor, Buku, dan Sarpras.
    |
    */

    'honor' => [
        // Kode kegiatan yang masuk kategori Honor (id_kode IN ...)
        'kode_kegiatan' => [
            '07.12.01.',
            '07.12.02.',
            '07.12.03.',
            '07.12.04.',
        ],
        // Kode rekening Honor
        'kode_rekening' => '5.1.02.02.01.0013',
        // Filter jenis transaksi pengeluaran (id_ref_bku IN ...)
        'id_ref_bku_keluar' => [4, 15, 24, 35],
        // Periode dalam hari ke- dalam tahun fiskal
        'periode' => [
            'awal' => [
                // Tahun < 2026 → hari ke-87
                'default' => 87,
                // Tahun >= 2026 → hari ke-81
                'mulai_2026' => 81,
            ],
            'akhir' => 92,
        ],
    ],

    'buku' => [
        // Kode kegiatan level 3 yang masuk kategori Buku (id_kode IN ... WHERE id_level_kode = 3)
        'kode_kegiatan' => [
            '03.02.02.',
            '05.02.02.',
            '05.02.03.',
            '05.02.04.',
            '05.02.05.',
        ],
        // Threshold minimum % dari total pagu
        'threshold' => [
            // Sekolah khusus tahun >= 2026
            'sekolah_khusus_mulai_2026' => 5,
            // Default (semua lainnya)
            'default' => 10,
        ],
        // ID sekolah khusus (id_kode)
        'sekolah_khusus_id_kode' => [
            '05.08.01.',
            '05.08.06.',
            '05.08.12.',
        ],
    ],

    'sarpras' => [
        // Kode kegiatan Sarpras tahun >= 2026 (id_kode IN ... AND SUBSTR(kode_rekening,1,3)='5.1')
        'kode_kegiatan_mulai_2026' => [
            '05.08.01.',
            '05.08.03.',
            '05.08.05.',
            '05.08.06.',
            '05.08.08.',
            '05.08.09.',
            '05.08.10.',
            '05.08.12.',
        ],
        // Kode kegiatan Sarpras tahun < 2026 — TODO: extract dari ARKAS (i.rE)
        // Sementara pakai kode kegiatan yang sama dengan >= 2026
        'kode_kegiatan_sebelum_2026' => [
            '05.08.01.',
            '05.08.03.',
            '05.08.05.',
            '05.08.06.',
            '05.08.08.',
            '05.08.09.',
            '05.08.10.',
            '05.08.12.',
        ],
        // Threshold maksimum % realisasi terhadap pagu
        'threshold_maksimal' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Komponen Validasi
    |--------------------------------------------------------------------------
    |
    | Menghitung total pagu, realisasi, dan status patuh/melanggar.
    | Arsip baku: "Surat Edaran Nomor 8 Tahun 2025 tentang Juknis BOSP".
    |
    */

    'diskriminator' => [
        // Toleransi perbandingan float (floating point)
        'epsilon' => 0.000001,
    ],

];
