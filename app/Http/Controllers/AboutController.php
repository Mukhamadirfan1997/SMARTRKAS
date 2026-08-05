<?php

namespace App\Http\Controllers;

use App\Services\AppUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AboutController extends Controller
{
    public function __construct(private AppUpdateService $updateService)
    {
    }

    public function index(): View
    {
        $release = $this->updateService->latestRelease();

        return view('pengaturan.tentang', [
            'version' => (string) config('app.version', '0.1.0'),
            'release' => $release,
            'updateAvailable' => $this->updateService->isUpdateAvailable($release),
        ]);
    }

    public function check(): RedirectResponse
    {
        $this->updateService->forget();

        return back();
    }
}
