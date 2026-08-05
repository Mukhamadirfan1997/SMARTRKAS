<?php

namespace App\Console\Commands;

use App\Models\RkasItem;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SyncRkasJumlah extends Command
{
    protected $signature = 'rkas:sync-jumlah {--tahun= : Batasi ke ID tahun anggaran tertentu}';

    protected $description = 'Sinkronkan jumlah rkas_item agar sama dengan total rencana semua bulan (rkas_item_bulan)';

    public function handle(): int
    {
        $query = RkasItem::withTrashed();

        $tahunId = $this->option('tahun');
        if ($tahunId !== null && $tahunId !== '') {
            $query->where('tahun_anggaran_id', (string) $tahunId);
        }

        /** @var Collection<int, RkasItem> $items */
        $items = $query->get(['tahun_anggaran_id', 'id', 'jumlah']);

        $groups = $items->groupBy(fn (RkasItem $item): string => $item->tahun_anggaran_id);

        $count = 0;

        foreach ($groups as $group) {
            $first = $group->first();
            if ($first === null) {
                continue;
            }

            $count += RkasItem::syncJumlah($first->tahun_anggaran_id);
        }

        $this->info("Sinkron selesai. {$count} item diperbaiki dari {$items->count()} total item.");

        return 0;
    }
}
