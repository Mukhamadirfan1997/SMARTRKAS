<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $kode
 * @property string $nama
 * @property string|null $program
 * @property string|null $sub_program
 * @property string|null $parent_id
 * @property int $level
 * @use HasFactory<\Database\Factories\MasterProgramFactory>
 */
class MasterProgram extends Model
{
    /** @use HasFactory<\Database\Factories\MasterProgramFactory> */
    use HasFactory, HasUuids;

    protected $table = 'master_program';

    protected $fillable = [
        'kode',
        'nama',
        'program',
        'sub_program',
        'parent_id',
        'level',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\MasterProgram, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MasterProgram::class, 'parent_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\MasterProgram, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(MasterProgram::class, 'parent_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\RkasItem, $this> */
    public function rkasItems(): HasMany
    {
        return $this->hasMany(RkasItem::class, 'program_id');
    }
}
