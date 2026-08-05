<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property string|null $recovery_code_hash
 * @property \Illuminate\Support\Carbon|null $recovery_code_generated_at
 * @property string|null $telegram_chat_id
 * @property string|null $telegram_bot_token
 * @use HasFactory<\Database\Factories\UserFactory>
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'last_login_at',
        'recovery_code_hash',
        'recovery_code_generated_at',
        'telegram_chat_id',
        'telegram_bot_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'telegram_bot_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'recovery_code_generated_at' => 'datetime',
        ];
    }

    public function hasRecoveryCode(): bool
    {
        return $this->recovery_code_hash !== null;
    }

    /**
     * Generate kode pemulihan baru, simpan hash-nya, dan kembalikan
     * kode plaintext (hanya ditampilkan sekali ke pengguna).
     */
    public function setRecoveryCode(): string
    {
        $code = self::generateRecoveryCodeValue();
        $this->recovery_code_hash = Hash::make($code);
        $this->recovery_code_generated_at = now();

        return $code;
    }

    public function verifyRecoveryCode(string $code): bool
    {
        if ($this->recovery_code_hash === null) {
            return false;
        }

        return Hash::check(self::normalizeRecoveryCode($code), $this->recovery_code_hash);
    }

    public static function generateRecoveryCodeValue(): string
    {
        $random = Str::upper(Str::random(8));

        return substr($random, 0, 4).'-'.substr($random, 4, 4);
    }

    public static function normalizeRecoveryCode(string $code): string
    {
        return Str::upper(str_replace([' ', '_'], '', $code));
    }

    /**
     * True bila akun ini punya ID Telegram untuk menerima kode pemulihan.
     */
    public function hasTelegramChannel(): bool
    {
        return $this->telegram_chat_id !== null && $this->telegram_chat_id !== '';
    }

    /**
     * ID Telegram penerima kode pemulihan, atau null bila belum diisi.
     */
    public function telegramChatId(): ?string
    {
        return $this->hasTelegramChannel() ? $this->telegram_chat_id : null;
    }

    /**
     * Token bot Telegram untuk akun ini. Prioritas: token yang diisi lewat
     * form aplikasi (database); fallback ke token env (TELEGRAM_BOT_TOKEN).
     */
    public function telegramBotToken(): ?string
    {
        $stored = $this->telegram_bot_token;

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        $env = config('logging.telegram_bot_token');

        return is_string($env) && $env !== '' ? $env : null;
    }

    /**
     * True bila pengiriman Telegram siap dipakai (token bot + ID ada).
     */
    public function hasTelegramDelivery(): bool
    {
        return $this->telegramBotToken() !== null && $this->telegramChatId() !== null;
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ExportJob, $this> */
    public function exportJobs(): HasMany
    {
        return $this->hasMany(ExportJob::class);
    }
}
