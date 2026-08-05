<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\TransaksiBku;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class DeduplicateRkas extends Command
{
    protected $signature = 'rkas:dedup {--tahun= : Batasi ke ID tahun anggaran tertentu} {--dry-run : Hanya tampilkan rencana tanpa mengubah data}';

    protected $description = 'Gabungkan item RKAS duplikat (uraian + program + kode rekening sama) pada tahun anggaran yang sama';

    public function handle(): int
    {
        $query = RkasItem::withTrashed();

        $tahunId = $this->option('tahun');
        if ($tahunId !== null && $tahunId !== '') {
            $query->where('tahun_anggaran_id', (string) $tahunId);
        }

        $items = $query->get([
            'id',
            'tahun_anggaran_id',
            'sumber_dana_id',
            'program_id',
            'kode_rekening_id',
            'no_urut',
            'uraian',
            'jumlah',
        ]);

        $groups = $items->groupBy(function (RkasItem $item): string {
            return implode('|', [
                $item->tahun_anggaran_id,
                $item->sumber_dana_id,
                $item->program_id,
                $item->kode_rekening_id,
                RkasItem::normalizeUraian((string) $item->uraian),
            ]);
        });

        $merged = 0;

        foreach ($groups as $group) {
            if ($group->count() < 2) {
                continue;
            }

            /** @var RkasItem|null $survivor */
            $survivor = $group->sortBy('id')->first();
            if ($survivor === null) {
                continue;
            }

            /** @var Collection<int, RkasItem> $duplicates */
            $duplicates = $group->reject(fn (RkasItem $item): bool => $item->is($survivor));

            $this->info("Duplikat: no_urut {$survivor->no_urut} => {$survivor->uraian}");
            foreach ($duplicates as $dup) {
                $this->info("  - hapus item #{$dup->id} (no_urut {$dup->no_urut}, jumlah " . number_format((float) $dup->jumlah) . ')');
            }

            if ($this->option('dry-run')) {
                continue;
            }

            $this->mergeInto($survivor, $duplicates);

            AuditLog::create([
                'user_id'    => null,
                'tabel'      => 'rkas_item',
                'aksi'       => 'dedup_merge',
                'data_baru'  => [
                    'survivor_id'         => $survivor->id,
                    'uraian'              => $survivor->uraian,
                    'jumlah_item_digabung' => $duplicates->count(),
                    'item_ids'            => $duplicates->pluck('id')->all(),
                ],
            ]);

            $merged += $duplicates->count();
        }

        $this->info("Selesai. {$merged} item duplikat digabung/dihapus.");

        return 0;
    }

    /**
     * @param Collection<int, RkasItem> $duplicates
     */
    protected function mergeInto(RkasItem $survivor, Collection $duplicates): void
    {
        foreach ($duplicates as $dup) {
            foreach (RkasItemBulan::where('rkas_item_id', $dup->id)->get() as $bulan) {
                $existing = RkasItemBulan::where('rkas_item_id', $survivor->id)
                    ->where('bulan', $bulan->bulan)
                    ->first();

                if ($existing) {
                    $existing->updateQuietly([
                        'rencana' => (float) $existing->rencana + (float) $bulan->rencana,
                    ]);
                } else {
                    RkasItemBulan::create([
                        'rkas_item_id' => $survivor->id,
                        'bulan'        => $bulan->bulan,
                        'rencana'      => $bulan->rencana,
                    ]);
                }
            }

            TransaksiBku::where('rkas_item_id', $dup->id)
                ->update(['rkas_item_id' => $survivor->id]);

            $dup->forceDelete();
        }

        $survivor->updateQuietly([
            'jumlah' => (float) RkasItemBulan::where('rkas_item_id', $survivor->id)->sum('rencana'),
        ]);
    }
}
