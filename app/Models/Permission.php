<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'start_date',
        'reason',
        'status',
        'approved_by',
        'approval_notes',
    ];

    /**
     * `start_date` is date-only input. It is cast with an explicit
     * `date:Y-m-d` format so the value is serialized as a plain date
     * string instead of being shifted to UTC by the application
     * timezone (Asia/Jakarta).
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
        ];
    }

    /**
     * Permission owner.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * User who approved the permission.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
