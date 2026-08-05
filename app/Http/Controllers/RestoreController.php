<?php

namespace App\Http\Controllers;

use App\Exceptions\RestoreException;
use App\Services\RestoreService;
use App\Support\AppState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RestoreController extends Controller
{
    public function __construct(private RestoreService $restoreService)
    {
    }

    public function create(): View|RedirectResponse
    {
        if (! AppState::isFirstRun()) {
            return redirect()->route('login');
        }

        return view('auth.restore');
    }

    public function store(Request $request): View|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:51200'],
        ]);

        $file = $request->file('file');
        if ($file === null) {
            return back()->with('error', 'File backup tidak ditemukan.');
        }

        try {
            $this->restoreService->restore($file->getPathname());
        } catch (RestoreException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable) {
            return back()->with('error', 'Restore gagal. Pastikan file backup valid dan coba lagi.');
        }

        AppState::initialize();

        return view('auth.restore-success');
    }
}
