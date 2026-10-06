<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\ExportType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportJob extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'export_type',
        'scope',
        'filter_criteria',
        'file_path',
        'file_checksum',
        'record_count',
        'status',
        'error_message',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'export_type' => ExportType::class,
            'filter_criteria' => 'array',
            'record_count' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
