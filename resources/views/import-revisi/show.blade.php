<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Detail Revisi {{ $rkasRevisi->no_revisi }}</div>
    </x-slot>

    <div class="flex gap-2 mb-6">
        <a href="{{ route('import-revisi.index') }}" class="btn btn-secondary btn-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Kembali
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
        <div class="stat-card green">
            <div class="stat-icon bg-emerald-50">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <div class="stat-label">Sebelum Total</div>
                <div class="stat-value">Rp {{ number_format($rkasRevisi->sebelum_total, 0, ',', '.') }}</div>
            </div>
        </div>
        <div class="stat-card blue">
            <div class="stat-icon bg-blue-50">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <div class="stat-label">Sesudah Total</div>
                <div class="stat-value">Rp {{ number_format($rkasRevisi->sesudah_total, 0, ',', '.') }}</div>
            </div>
        </div>
        <div class="stat-card {{ $rkasRevisi->sesudah_total >= $rkasRevisi->sebelum_total ? 'green' : 'red' }}">
            <div class="stat-icon {{ $rkasRevisi->sesudah_total >= $rkasRevisi->sebelum_total ? 'bg-emerald-50' : 'bg-red-50' }}">
                <svg class="w-5 h-5 {{ $rkasRevisi->sesudah_total >= $rkasRevisi->sebelum_total ? 'text-emerald-600' : 'text-red-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/></svg>
            </div>
            <div>
                <div class="stat-label">Selisih</div>
                <div class="stat-value">{{ $rkasRevisi->sesudah_total >= $rkasRevisi->sebelum_total ? '+' : '' }}Rp {{ number_format($rkasRevisi->sesudah_total - $rkasRevisi->sebelum_total, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <div class="card mb-6">
        <div class="card-header">
            <span class="card-title">Informasi Revisi</span>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <div class="text-xs text-slate-400 mb-1">No. Revisi</div>
                    <div class="font-mono font-semibold">{{ $rkasRevisi->no_revisi }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-400 mb-1">Jenis</div>
                    <div>
                        <span class="badge {{ $rkasRevisi->jenis === 'pak' ? 'badge-purple' : 'badge-blue' }}">{{ strtoupper($rkasRevisi->jenis) }}</span>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-slate-400 mb-1">Tanggal</div>
                    <div>{{ $rkasRevisi->tanggal->translatedFormat('d F Y') }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-400 mb-1">Sumber Dana</div>
                    <div>{{ $rkasRevisi->sumberDana?->kode }} - {{ $rkasRevisi->sumberDana?->nama ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-400 mb-1">Tahun Anggaran</div>
                    <div>{{ $rkasRevisi->tahunAnggaran?->tahun ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-400 mb-1">Dibuat Oleh</div>
                    <div>{{ $rkasRevisi->createdBy?->name ?? '-' }}</div>
                </div>
                @if($rkasRevisi->keterangan)
                <div class="md:col-span-2">
                    <div class="text-xs text-slate-400 mb-1">Keterangan</div>
                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-3">{{ $rkasRevisi->keterangan }}</div>
                </div>
                @endif
                @if(!empty($rkasRevisi->data_perubahan))
                <div class="md:col-span-2">
                    <div class="text-xs text-slate-400 mb-1">Ringkasan Perubahan per Bulan</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($rkasRevisi->data_perubahan as $bulan => $per)
                            <span class="text-xs bg-blue-50 text-blue-700 border border-blue-200 rounded-lg px-3 py-1.5">
                                {{ \Carbon\Carbon::create()->month((int) $bulan)->translatedFormat('F') }}:
                                {{ $per['jumlah_item'] }} item &middot;
                                {{ $per['selisih'] >= 0 ? '+' : '' }}Rp {{ number_format((float) $per['selisih'], 0, ',', '.') }}
                            </span>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Rincian Perubahan per Item</span>
        </div>
        <div class="card-body">
            @if($rkasRevisi->items->count() > 0)
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Bulan</th>
                                <th>Uraian</th>
                                <th>Arah</th>
                                <th class="text-right">Sebelum</th>
                                <th class="text-right">Sesudah</th>
                                <th class="text-right">Selisih</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rkasRevisi->items as $item)
                                <tr>
                                    <td>{{ \Carbon\Carbon::create()->month($item->bulan)->translatedFormat('F') }}</td>
                                    <td>{{ $item->rkasItem?->uraian ?? '-' }}</td>
                                    <td>
                                        <span class="badge {{ $item->arah === 'naik' ? 'badge-green' : 'badge-red' }}">{{ strtoupper($item->arah) }}</span>
                                    </td>
                                    <td class="text-right">Rp {{ number_format($item->sebelum, 0, ',', '.') }}</td>
                                    <td class="text-right">Rp {{ number_format($item->sesudah, 0, ',', '.') }}</td>
                                    <td class="text-right {{ $item->delta >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                        {{ $item->delta >= 0 ? '+' : '' }}Rp {{ number_format($item->delta, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-right font-semibold">Total</td>
                                <td class="text-right font-semibold">Rp {{ number_format($rkasRevisi->sebelum_total, 0, ',', '.') }}</td>
                                <td class="text-right font-semibold">Rp {{ number_format($rkasRevisi->sesudah_total, 0, ',', '.') }}</td>
                                <td class="text-right font-semibold">{{ $rkasRevisi->sesudah_total >= $rkasRevisi->sebelum_total ? '+' : '' }}Rp {{ number_format($rkasRevisi->sesudah_total - $rkasRevisi->sebelum_total, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="text-center text-slate-400 py-8 text-sm">Tidak ada rincian perubahan.</div>
            @endif
        </div>
    </div>
</x-app-layout>
