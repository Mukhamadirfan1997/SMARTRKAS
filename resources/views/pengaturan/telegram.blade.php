<x-app-layout>
    <x-slot name="header">
        <div class="page-title">Notifikasi Telegram</div>
    </x-slot>

    @if(session('status'))
        <div class="alert-success mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('status') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert-error mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    <div class="alert-info mb-6">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            Fitur ini mengirimkan <strong>kode pemulihan password</strong> ke chat Telegram Anda setiap kali kode dibuat baru
            (menu <em>Kode Pemulihan</em>). Kode tetap ditampilkan sekali di layar — Telegram hanyalah cadangan.
        </div>
    </div>

    <div class="card mb-6">
        <div class="card-header">
            <span class="card-title">Status Bot Telegram</span>
        </div>
        <div class="card-body flex flex-wrap items-center gap-3">
            @if($botToken)
                <span class="badge badge-green">Bot Aktif</span>
                <p class="text-sm text-slate-600">
                    Token bot terkonfigurasi {{ $botSource === 'db' ? 'dari form di bawah ini' : 'dari file .env' }}.
                </p>
            @else
                <span class="badge badge-gray">Bot Tidak Aktif</span>
                <p class="text-sm text-slate-600">
                    Token bot belum dikonfigurasi. Isi Token Bot pada form di bawah, atau set
                    <code class="text-xs bg-slate-100 px-1 py-0.5 rounded">TELEGRAM_BOT_TOKEN</code> di file .env.
                </p>
            @endif
        </div>
    </div>

    <div class="card mb-6">
        <div class="card-header">
            <span class="card-title">Pengaturan Pengiriman</span>
        </div>
        <div class="card-body space-y-4">
            <form method="POST" action="{{ route('pengaturan.telegram.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="form-label">Token Bot (dari @BotFather)</label>
                    <input type="password" name="telegram_bot_token" class="form-input font-mono" placeholder="123456789:AAH..." autocomplete="new-password" />
                    @error('telegram_bot_token')
                        <p class="text-red-600 text-xs mt-2">{{ $message }}</p>
                    @enderror
                    @if($user->telegram_bot_token)
                        <p class="text-xs text-slate-500 mt-1">Token dari form sudah tersimpan. Biarkan kosong untuk mempertahankannya, atau isi token baru untuk mengganti.</p>
                    @else
                        <p class="text-xs text-slate-500 mt-1">Kosongkan bila ingin memakai token dari file .env (mode web / folder data desktop).</p>
                    @endif
                </div>

                <div>
                    <label class="form-label">ID Telegram (dari @userinfobot)</label>
                    <input type="text" name="telegram_chat_id" value="{{ $user->telegram_chat_id }}" class="form-input font-mono" placeholder="mis. 123456789" />
                    @error('telegram_chat_id')
                        <p class="text-red-600 text-xs mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <button type="submit" class="btn btn-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                        Simpan Pengaturan
                    </button>
                </div>
            </form>

            <div class="border-t border-slate-200 pt-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">Setelah menyimpan, uji apakah bot berhasil mengirim pesan ke Telegram Anda.</p>
                <form method="POST" action="{{ route('pengaturan.telegram.test') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        Kirim Pesan Uji
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="card mb-6">
        <div class="card-header">
            <span class="card-title">Panduan Mengaktifkan Notifikasi Telegram</span>
        </div>
        <div class="card-body">
            <div class="space-y-3">

                <details class="border border-slate-200 rounded-xl px-4 py-3 bg-slate-50/60" open>
                    <summary class="cursor-pointer font-semibold text-slate-800 text-sm">Langkah 1 — Dapatkan ID Telegram Anda</summary>
                    <div class="mt-3 space-y-2 text-sm text-slate-600 leading-relaxed">
                        <p>ID Telegram dipakai untuk mengirim pesan ke akun Telegram Anda. Dapatkan dengan cara:</p>
                        <ol class="list-decimal list-inside space-y-1">
                            <li>Buka aplikasi Telegram (HP atau komputer).</li>
                            <li>Ketik <code class="text-xs bg-slate-100 px-1 py-0.5 rounded">@userinfobot</code> di kolom pencarian, lalu pilih bot tersebut.</li>
                            <li>Tekan tombol <strong>Start</strong> (atau kirim <code class="text-xs bg-slate-100 px-1 py-0.5 rounded">/start</code>).</li>
                            <li>Bot membalas pesan berisi <strong>ID</strong> Anda (angka, mis. <code class="text-xs bg-slate-100 px-1 py-0.5 rounded">123456789</code>).</li>
                            <li>Salin angka tersebut dan paste ke kolom <em>ID Telegram</em> di atas.</li>
                        </ol>
                    </div>
                </details>

                <details class="border border-slate-200 rounded-xl px-4 py-3 bg-slate-50/60">
                    <summary class="cursor-pointer font-semibold text-slate-800 text-sm">Langkah 2 — Buat Bot &amp; dapatkan Token (sekali saja)</summary>
                    <div class="mt-3 space-y-2 text-sm text-slate-600 leading-relaxed">
                        <p>Bot adalah "akun pengirim" SmartRKAS di Telegram. Cara membuatnya:</p>
                        <ol class="list-decimal list-inside space-y-1">
                            <li>Buka Telegram, cari <code class="text-xs bg-slate-100 px-1 py-0.5 rounded">@BotFather</code>.</li>
                            <li>Tekan <strong>Start</strong>, lalu ketik <code class="text-xs bg-slate-100 px-1 py-0.5 rounded">/newbot</code>.</li>
                            <li>Ikuti petunjuk: beri nama bot (bebas), lalu buat username yang <strong>wajib diakhiri kata <code class="text-xs bg-slate-100 px-1 py-0.5 rounded">bot</code></strong> (mis. <code class="text-xs bg-slate-100 px-1 py-0.5 rounded">rkas_sekolah_bot</code>).</li>
                            <li>BotFather membalas dengan <strong>token</strong> berbentuk <code class="text-xs bg-slate-100 px-1 py-0.5 rounded">123456789:AAH...</code>.</li>
                            <li><strong>Token bersifat rahasia</strong> — salin dan simpan baik-baik, lalu paste ke kolom <em>Token Bot</em> di atas.</li>
                        </ol>
                    </div>
                </details>

                <details class="border border-slate-200 rounded-xl px-4 py-3 bg-slate-50/60">
                    <summary class="cursor-pointer font-semibold text-slate-800 text-sm">Langkah 3 — Sambungkan ke aplikasi</summary>
                    <div class="mt-3 space-y-2 text-sm text-slate-600 leading-relaxed">
                        <p><strong>Cara termudah:</strong> isi kolom <em>Token Bot</em> dan <em>ID Telegram</em> di atas, lalu klik <em>Simpan Pengaturan</em>. Selesai — tidak perlu menyentuh file.</p>
                        <p><strong>Cara lain (memakai file):</strong></p>
                        <ul class="list-disc list-inside space-y-1">
                            @if(config('app.data_dir'))
                                <li>
                                    <strong>Mode desktop:</strong> buka folder data aplikasi berikut, buat file <code class="text-xs bg-slate-100 px-1 py-0.5 rounded">.env</code>, isi baris
                                    <code class="text-xs bg-slate-100 px-1 py-0.5 rounded">TELEGRAM_BOT_TOKEN=token_anda</code>, simpan, lalu tutup &amp; buka kembali aplikasi.
                                    <div class="mt-2 bg-slate-100 rounded-lg px-3 py-2 font-mono text-xs text-slate-700 break-all">{{ config('app.data_dir') }}</div>
                                </li>
                            @else
                                <li>
                                    <strong>Mode web:</strong> buka file <code class="text-xs bg-slate-100 px-1 py-0.5 rounded">.env</code> di folder aplikasi, isi baris
                                    <code class="text-xs bg-slate-100 px-1 py-0.5 rounded">TELEGRAM_BOT_TOKEN=token_anda</code>, simpan, lalu jalankan ulang server (atau <code class="text-xs bg-slate-100 px-1 py-0.5 rounded">php artisan config:clear</code>).
                                </li>
                            @endif
                            <li>Catatan: token dari file <code class="text-xs bg-slate-100 px-1 py-0.5 rounded">.env</code> menimpa isi form bila keduanya terisi.</li>
                        </ul>
                    </div>
                </details>

                <details class="border border-slate-200 rounded-xl px-4 py-3 bg-slate-50/60">
                    <summary class="cursor-pointer font-semibold text-slate-800 text-sm">Langkah 4 — Simpan lalu uji</summary>
                    <div class="mt-3 space-y-2 text-sm text-slate-600 leading-relaxed">
                        <ol class="list-decimal list-inside space-y-1">
                            <li>Klik <em>Simpan Pengaturan</em>. Status bot di atas berubah menjadi <span class="badge badge-green">Bot Aktif</span>.</li>
                            <li>Klik <em>Kirim Pesan Uji</em>.</li>
                            <li>Buka chat bot Anda di Telegram. Jika muncul pesan <em>"Pesan uji dari SmartRKAS"</em>, semuanya siap dipakai.</li>
                        </ol>
                    </div>
                </details>

                <details class="border border-slate-200 rounded-xl px-4 py-3 bg-slate-50/60">
                    <summary class="cursor-pointer font-semibold text-slate-800 text-sm">Langkah 5 — Selesai</summary>
                    <div class="mt-3 space-y-2 text-sm text-slate-600 leading-relaxed">
                        <p>Setiap kali Anda membuat kode pemulihan baru (menu <em>Kode Pemulihan</em>), aplikasi:</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>Menampilkan kode <strong>sekali</strong> di layar, dan</li>
                            <li>Mengirim salinan kode ke Telegram Anda sebagai cadangan.</li>
                        </ul>
                    </div>
                </details>

            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Pemecahan Masalah</span>
        </div>
        <div class="card-body text-sm text-slate-600 leading-relaxed">
            <ul class="list-disc list-inside space-y-2">
                <li><strong>Status "Bot Tidak Aktif"</strong> — token belum diisi. Lengkapi Token Bot di form, atau set <code class="text-xs bg-slate-100 px-1 py-0.5 rounded">TELEGRAM_BOT_TOKEN</code> di file .env.</li>
                <li><strong>Pesan uji tidak muncul di Telegram</strong> — periksa berurutan: sudah menekan <strong>Start</strong> pada bot? (wajib, Telegram memblokir bot yang belum pernah di-Start), token sudah benar dari @BotFather? ID sudah benar dari @userinfobot?</li>
                <li><strong>Bot membalas "Unauthorized"</strong> — token salah. Salin ulang dari @BotFather.</li>
                <li><strong>Bot membalas "chat not found"</strong> — ID salah, atau Anda belum menekan Start pada bot.</li>
            </ul>
        </div>
    </div>
</x-app-layout>
