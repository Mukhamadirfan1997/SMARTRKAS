<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::query()->with('user')->latest();

        if ($request->filled('tabel')) {
            $query->where('tabel', (string) $request->string('tabel'));
        }

        if ($request->filled('q')) {
            $q = $request->string('q');

            $query->where(function (Builder $sub) use ($q): void {
                $sub->where('tabel', 'like', "%{$q}%")
                    ->orWhere('aksi', 'like', "%{$q}%")
                    ->orWhereHas('user', function (Builder $userQuery) use ($q): void {
                        $userQuery->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%");
                    });
            });
        }

        $logs = $query->paginate(50)->withQueryString();
        $tabels = AuditLog::query()->select('tabel')->distinct()->orderBy('tabel')->pluck('tabel');

        return view('pengaturan.audit-log', compact('logs', 'tabels'));
    }
}
