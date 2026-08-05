<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SmartRKAS') }}</title>

        <link rel="icon" type="image/png" href="/icons/smartrkas.png">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            @keyframes gradientShift {
                0%, 100% { background-position: 0% 50%; }
                50% { background-position: 100% 50%; }
            }
            @keyframes logoFloat {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-8px); }
            }
            @keyframes logoGlow {
                0%, 100% { box-shadow: 0 0 30px rgba(255,255,255,0.15), 0 25px 50px rgba(0,0,0,0.15); }
                50% { box-shadow: 0 0 60px rgba(255,255,255,0.3), 0 25px 50px rgba(0,0,0,0.15); }
            }
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(16px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .guest-left {
                background-size: 200% 200%;
                animation: gradientShift 8s ease infinite;
            }
            .guest-logo {
                animation: logoFloat 4s ease-in-out infinite, logoGlow 4s ease-in-out infinite;
            }
            .feature-item {
                opacity: 0;
                animation: fadeInUp 0.5s ease forwards;
            }
            .feature-item:nth-child(1) { animation-delay: 0.3s; }
            .feature-item:nth-child(2) { animation-delay: 0.55s; }
            .feature-item:nth-child(3) { animation-delay: 0.8s; }
        </style>
    </head>
    <body class="font-sans text-slate-900 antialiased min-h-screen flex">
        <div class="hidden lg:flex lg:w-1/2 guest-left bg-gradient-to-br from-blue-900 via-blue-900 to-teal-800 relative overflow-hidden items-center justify-center p-12">
            <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmYiIGZpbGwtb3BhY2l0eT0iMC4wNiI+PHBhdGggZD0iTTM2IDE4YzEuNjU3IDAgMy0xLjM0MyAzLTNzLTEuMzQzLTMtMy0zLTMgMS4zNDMtMyAzIDEuMzQzIDMgMyAzem0tMjQgMGMxLjY1NyAwIDMtMS4zNDMgMy0zcy0xLjM0My0zLTMtMy0zIDEuMzQzLTMgMyAxLjM0MyAzIDMgM3oiLz48L2c+PC9nPjwvc3ZnPg==')] opacity-30"></div>
            <div class="relative z-10 text-center max-w-md">
                <div class="w-24 h-24 rounded-2xl bg-white/15 backdrop-blur-sm flex items-center justify-center mx-auto mb-7 shadow-2xl shadow-blue-900/30 guest-logo overflow-hidden">
                    <img src="/icons/smartrkas.png" alt="SmartRKAS" class="w-20 h-20 object-contain">
                </div>
                <h1 class="text-5xl font-extrabold text-white mb-3 tracking-tight">SMARTRKAS</h1>
                <p class="text-white/90 text-xl font-medium">Sistem Informasi Realisasi Anggaran RKAS</p>
                <p class="text-blue-100 text-base mt-5 leading-relaxed">Kelola RKAS, BKU, dan pelaporan anggaran sekolah secara sederhana di satu aplikasi.</p>

                <div class="mt-10 space-y-4 text-left max-w-sm mx-auto">
                    <div class="flex items-center gap-4 feature-item">
                        <div class="w-10 h-10 rounded-xl bg-white/15 backdrop-blur-sm flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </div>
                        <span class="text-white text-base font-medium">Rencana, Realisasi &amp; Monitoring</span>
                    </div>
                    <div class="flex items-center gap-4 feature-item">
                        <div class="w-10 h-10 rounded-xl bg-white/15 backdrop-blur-sm flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <span class="text-white text-base font-medium">BKU, Kwitansi &amp; Laporan</span>
                    </div>
                    <div class="flex items-center gap-4 feature-item">
                        <div class="w-10 h-10 rounded-xl bg-white/15 backdrop-blur-sm flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <span class="text-white text-base font-medium">Data tersimpan lokal &amp; backup otomatis</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex-1 flex items-center justify-center p-6 bg-gradient-to-br from-slate-50 to-blue-50/30">
            <div class="w-full max-w-md">
                <div class="lg:hidden flex items-center gap-3 mb-8 justify-center">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 flex items-center justify-center shadow-lg overflow-hidden">
                        <img src="/icons/smartrkas.png" alt="SmartRKAS" class="w-9 h-9 object-contain">
                    </div>
                    <div>
                        <div class="text-xl font-bold text-slate-800">SMARTRKAS</div>
                        <div class="text-sm text-slate-500">Sistem Informasi Anggaran</div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-100">
                    <div class="p-10">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
