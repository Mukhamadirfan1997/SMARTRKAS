<?php

/*
 * Stub khusus untuk analisis statis IDE (DEVsense/Intelephense).
 * Dipakai oleh barryvdh/laravel-ide-helper lewat config('ide-helper.helper_files').
 * File ini TIDAK PERNAH dieksekusi saat runtime - hanya dibaca IDE.
 */

if (! function_exists('auth')) {
    /**
     * Get the available auth instance.
     *
     * @param string|null $guard
     * @return \Illuminate\Auth\AuthManager|\Illuminate\Contracts\Auth\Guard
     */
    function auth($guard = null)
    {
        return app('auth');
    }
}
