<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessTrip extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'destination',
        'purpose',
        'notes',
        'attachment',
        'status',
        'approved_by',
        'approval_notes',
    ];

    /**
     * `start_date` and `end_date` are date-only inputs. They are cast
     * with an explicit `date:Y-m-d` format so the values are serialized
     * as plain date strings instead of being shifted to UTC by the
     * application timezone (Asia/Jakarta).
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
        ];
    }

    /** Business trip owner. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** User who approved or rejected the business trip. */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
