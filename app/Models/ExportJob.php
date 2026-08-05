<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $status
 * @property string|null $filename
 * @property string|null $file_path
 * @property string|null $error_message
 * @property \Carbon\Carbon|null $completed_at
 * @use HasFactory<\Database\Factories\ExportJobFactory>
 */
class ExportJob extends Model
{
    /** @use HasFactory<\Database\Factories\ExportJobFactory> */
    use HasFactory;

    protected $table = 'export_jobs';

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'filename',
        'file_path',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param \Illuminate\Database\Eloquent\Builder<\App\Models\ExportJob> $query */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /** @param \Illuminate\Database\Eloquent\Builder<\App\Models\ExportJob> $query */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', 'completed');
    }

    /** @param \Illuminate\Database\Eloquent\Builder<\App\Models\ExportJob> $query */
    public function scopeProcessing(Builder $query): void
    {
        $query->where('status', 'processing');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
