<?php

namespace App\Logging;

use App\Jobs\SendTelegramNotificationJob;
use App\Models\User;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class TelegramLogHandler extends AbstractProcessingHandler
{
    /** @var array<int, string> */
    private array $sensitiveKeys = [
        'password', 'passwd', 'secret', 'token', 'api_key', 'api_token',
        'access_token', 'refresh_token', 'authorization', 'auth_token',
        'private_key', 'database_url', 'db_password',
    ];

    protected function write(LogRecord $record): void
    {
        $level = $record->level;

        if (Level::Error->includes($level)) {
            $extra = $this->sanitize($record->extra);
            $extra['url'] = request()->fullUrl();

            $user = auth()->user();
            $extra['user_email'] = $user instanceof User
                ? ($user->email ?? $user->name)
                : 'Guest';

            SendTelegramNotificationJob::dispatch(
                level: $level->getName(),
                message: $record->message,
                context: $this->sanitize($record->context),
                extra: $extra,
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sanitize(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if ($value instanceof \Closure || is_resource($value)) {
                continue;
            }
            if (in_array(strtolower((string) $key), $this->sensitiveKeys, true)) {
                $result[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $result[$key] = $this->sanitize($value);
            } elseif (is_object($value)) {
                try {
                    serialize($value);
                    $result[$key] = $value;
                } catch (\Throwable) {
                    $result[$key] = '[' . get_class($value) . ']';
                }
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
