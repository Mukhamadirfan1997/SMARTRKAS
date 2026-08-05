<?php

namespace App\Console\Commands;

use App\Models\RkasItem;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RenumberRkas extends Command
{
    protected $signature = 'rkas:renumber {--tahun= : Batasi ke ID tahun anggaran tertentu} {--dry-run : Hanya tampilkan rencana tanpa mengubah data}';

    protected $description = 'Beri no_urut unik berurutan (1..N) untuk item RKAS per tahun anggaran';

    public function handle(): int
    {
        $query = RkasItem::withTrashed();

        $tahunId = $this->option('tahun');
        if ($tahunId !== null && $tahunId !== '') {
            $query->where('tahun_anggaran_id', (string) $tahunId);
        }

        /** @var Collection<int, RkasItem> $items */
        $items = $query
            ->orderBy('tahun_anggaran_id')
            ->orderBy('no_urut')
            ->orderBy('id')
            ->get();

        $groups = $items->groupBy(fn (RkasItem $item): string => $item->tahun_anggaran_id);

        $updated = 0;

        foreach ($groups as $group) {
            $first = $group->first();
            if ($first === null) {
                continue;
            }

            $tahunIdGroup = $first->tahun_anggaran_id;

            if ($this->option('dry-run')) {
                $seq = 0;
                foreach ($group as $item) {
                    $seq++;
                    if ((int) $item->no_urut === $seq) {
                        continue;
                    }
                    $this->info("[tahun {$tahunIdGroup}] #{$item->id}: no_urut {$item->no_urut} -> {$seq} ({$item->uraian})");
                    $updated++;
                }
                continue;
            }

            $updated += RkasItem::renumber($tahunIdGroup);
        }

        $this->info("Selesai. {$updated} item diberi no_urut baru.");

        return 0;
    }
}
