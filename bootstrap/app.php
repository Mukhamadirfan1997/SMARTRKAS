<?php

use App\Support\DataDirEnv;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

$dataDir = getenv('SMARTRKAS_DATA_DIR');
if ($dataDir !== false && $dataDir !== '') {
    $app->useStoragePath($dataDir.'/storage');

    $storageDir = $dataDir.'/storage';
    foreach (['framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'app'] as $sub) {
        $dir = $storageDir.'/'.$sub;
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }

    DataDirEnv::load($dataDir);
}

return $app;
