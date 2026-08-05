<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="page-title">Riwayat Aktivitas</div>
        </div>
    </x-slot>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Log Aktivitas Sistem</span>
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-2 p-4 border-b border-slate-200">
            <div>
                <label class="form-label">Jenis Data</label>
                <select name="tabel" class="form-input">
                    <option value="">Semua</option>
                    @foreach($tabels as $tabel)
                        <option value="{{ $tabel }}" @selected(request('tabel') === $tabel)>
                            {{ \Illuminate\Support\Str::headline($tabel) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Cari</label>
                <input type="text" name="q" class="form-input" placeholder="User / tabel / aksi..." value="{{ request('q') }}">
            </div>
            <button type="submit" class="btn btn-secondary btn-sm">Terapkan</button>
            @if(request('tabel') || request('q'))
                <a href="{{ route('pengaturan.audit.index') }}" class="btn btn-secondary btn-sm">Reset</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>User</th>
                        <th>Jenis Data</th>
                        <th>Aksi</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        @php
                            $detail = $log->data_baru ?? $log->data_lama;
                            $summary = is_array($detail)
                                ? collect($detail)->map(fn ($v, $k) => is_scalar($v) || $v === null ? "{$k}: {$v}" : "{$k}: [...]")->implode('; ')
                                : '-';
                            $tabelLabel = \Illuminate\Support\Str::headline($log->tabel);
                            $aksiBadges = [
                                'import' => 'badge-green',
                                'create' => 'badge-green',
                                'update' => 'badge-blue',
                                'delete' => 'badge-red',
                                'delete_bulk' => 'badge-red',
                                'override_anggaran' => 'badge-yellow',
                            ];
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                @if($log->user)
                                    <span class="font-medium text-slate-800">{{ $log->user->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ $log->user->email }}</span>
                                @else
                                    <span class="text-slate-400">Sistem</span>
                                @endif
                            </td>
                            <td>{{ $tabelLabel }}</td>
                            <td>
                                <span class="badge {{ $aksiBadges[$log->aksi] ?? 'badge-gray' }}">{{ \Illuminate\Support\Str::headline($log->aksi) }}</span>
                            </td>
                            <td class="max-w-md break-words text-slate-600">{{ \Illuminate\Support\Str::limit($summary, 140) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-slate-500 py-8">Belum ada aktivitas tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
