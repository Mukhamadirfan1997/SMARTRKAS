<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ResetUserPassword extends Command
{
    protected $signature = 'user:reset-password {user : ID (UUID) atau email user} {password? : Password baru (kosong = generate acak)}';

    protected $description = 'Reset password user tanpa perlu akses email/mail server';

    public function handle(): int
    {
        $user = $this->findUser((string) $this->argument('user'));

        if (! $user) {
            $this->error("User tidak ditemukan: {$this->argument('user')}");

            return self::FAILURE;
        }

        $password = (string) $this->argument('password');
        if ($password === '') {
            $password = Str::password(12);
        }

        $user->password = $password;
        $user->save();

        $this->info("Password user {$user->email} berhasil di-reset.");

        if ($this->argument('password') === null) {
            $this->warn("Password baru (simpan baik-baik): {$password}");
        }

        return self::SUCCESS;
    }

    private function findUser(string $key): ?User
    {
        $user = User::where('email', $key)->first();

        if (! $user && ctype_digit($key)) {
            $user = User::where('id', (int) $key)->first();
        }

        return $user;
    }
}
