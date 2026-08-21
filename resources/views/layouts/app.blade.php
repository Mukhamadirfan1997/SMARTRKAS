<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($pageTitle) ? $pageTitle . ' — ' : '' }}{{ config('app.name', 'SmartRKAS') }}</title>

        <link rel="icon" type="image/png" href="/icons/smartrkas.png">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-slate-50">
        <a href="#main-content" class="skip-link">Lompat ke konten utama</a>

        <div id="page-loader" class="fixed inset-0 z-50 flex items-center justify-center bg-white/80 transition-opacity duration-300" style="display:none">
            <div class="flex flex-col items-center gap-3">
                <svg class="animate-spin h-8 w-8 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-sm text-slate-500 dark:text-slate-400 font-medium">Memuat...</span>
            </div>
        </div>

        <script>
            (function() {
                var el = document.getElementById('page-loader');
                var hidden = false;
                function hideLoader() {
                    if (hidden) return;
                    hidden = true;
                    el.style.opacity = '0';
                    setTimeout(function() { el.style.display = 'none'; }, 300);
                }
                document.addEventListener('DOMContentLoaded', function() {
                    el.style.display = 'flex';
                    setTimeout(hideLoader, 1200);
                });
                window.addEventListener('load', hideLoader);
                setTimeout(hideLoader, 3000);
            })();
        </script>

        @include('layouts.navigation')

        <div class="main-wrapper">

            <div class="top-header">
                <div class="flex items-center gap-3">
                    <button onclick="toggleSidebar()" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all duration-150" title="Toggle sidebar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    @isset($header)
                        {{ $header }}
                    @endisset
                </div>
                <div class="flex items-center gap-3">
                    <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800" title="Waktu berjalan">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="leading-tight text-right">
                            <div id="realtime-clock" class="text-sm font-semibold text-slate-700 dark:text-slate-200 tabular-nums">--:--:--</div>
                            <div id="realtime-date" class="text-[10px] text-slate-400 dark:text-slate-500">&mdash;</div>
                        </div>
                    </div>
                    <button id="dark-mode-toggle" onclick="toggleDarkMode()" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all duration-150" title="Toggle dark mode">
                        <svg class="w-5 h-5 dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                        <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </button>
                    <div class="flex items-center gap-2.5">
                        <div class="sidebar-avatar text-xs">
                            {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                        </div>
                        <span class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ Auth::user()->name }}</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex items-center gap-1.5 text-sm text-slate-400 hover:text-red-600 dark:hover:text-red-400 transition-all duration-150 hover:bg-red-50 dark:hover:bg-red-900/30 px-3 py-1.5 rounded-lg">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Keluar
                        </button>
                    </form>
                </div>
            </div>

            <main id="main-content" class="page-content">
                @if(\Carbon\Carbon::now()->day >= 22)
                    <div class="alert-warning mb-6">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span>Pengingat: Kita sudah memasuki minggu terakhir bulan <strong>{{ \Carbon\Carbon::now()->translatedFormat('F') }}</strong>. Silakan segera input transaksi BKU agar data Realisasi Anggaran selalu up-to-date.</span>
                    </div>
                @endif

                @if(session('info'))
                    <div class="alert-info mb-6">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{!! session('info') !!}</span>
                    </div>
                @endif

                {{ $slot }}
            </main>

        </div>

        <div id="break-reminder-modal" class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/50 p-4 opacity-0 pointer-events-none transition-opacity duration-200 ease-out">
            <div id="break-reminder-card" class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-xl dark:bg-slate-800 scale-90 transition-transform duration-200 ease-out">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                    <svg class="h-7 w-7 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Waktunya Istirahat Sejenak</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">Anda sudah bekerja kurang lebih 2 jam. Berdirilah sebentar, regangkan badan, dan biarkan mata beristirahat agar tetap segar dan fokus.</p>
                <button id="break-reminder-ok" type="button" class="btn-primary mt-5 w-full">Baik, Saya Istirahat Sebentar</button>
                <button id="break-reminder-snooze" type="button" class="mt-2 w-full text-xs font-medium text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors">Tunda 15 Menit</button>
            </div>
        </div>

        <script>
        (function() {
            if (localStorage.getItem('sidebar-collapsed') === 'true') {
                document.body.classList.add('sidebar-collapsed');
            }
            window.toggleSidebar = function() {
                document.body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebar-collapsed', document.body.classList.contains('sidebar-collapsed'));
            };
            if (localStorage.getItem('dark-mode') === 'true' || (!localStorage.getItem('dark-mode') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
            window.toggleDarkMode = function() {
                document.documentElement.classList.toggle('dark');
                localStorage.setItem('dark-mode', document.documentElement.classList.contains('dark'));
            };
        })();
        (function() {
            var clockEl = document.getElementById('realtime-clock');
            var dateEl = document.getElementById('realtime-date');
            if (!clockEl) return;
            var pad = function(n) { return String(n).padStart(2, '0'); };
            function tickClock() {
                var now = new Date();
                clockEl.textContent = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
                if (dateEl) {
                    dateEl.textContent = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short' });
                }
            }
            tickClock();
            setInterval(tickClock, 1000);
        })();
        (function() {
            var modal = document.getElementById('break-reminder-modal');
            var card = document.getElementById('break-reminder-card');
            if (!modal || !card) return;
            var KEY = 'smartrkas-break-reminder-at';
            var TWO_HOURS = 2 * 60 * 60 * 1000;
            var FOUR_HOURS = 4 * 60 * 60 * 1000;
            var shown = false;

            function lastAt() {
                return parseInt(localStorage.getItem(KEY) || '0', 10);
            }
            function markNow() {
                try { localStorage.setItem(KEY, String(Date.now())); } catch (e) {}
            }
            function due() {
                var elapsed = Date.now() - lastAt();
                return elapsed >= TWO_HOURS && elapsed < FOUR_HOURS;
            }
            function show() {
                if (shown) return;
                shown = true;
                modal.classList.remove('opacity-0', 'pointer-events-none');
                card.classList.remove('scale-90');
            }
            function close() {
                shown = false;
                modal.classList.add('opacity-0', 'pointer-events-none');
                card.classList.add('scale-90');
            }

            document.getElementById('break-reminder-ok').addEventListener('click', function() {
                markNow();
                close();
            });
            document.getElementById('break-reminder-snooze').addEventListener('click', function() {
                try { localStorage.setItem(KEY, String(Date.now() - TWO_HOURS + 15 * 60 * 1000)); } catch (e) {}
                close();
            });

            if (!lastAt()) {
                markNow();
            } else if (due()) {
                show();
            } else if (Date.now() - lastAt() >= FOUR_HOURS) {
                markNow();
            }

            setInterval(function() { if (!shown && due()) show(); }, 60000);
        })();
        </script>

    </body>
</html>
