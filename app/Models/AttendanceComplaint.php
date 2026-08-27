<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceComplaint extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'attendance_id',
        'user_id',
        'complaint_reason',
        'attachment',
        'status',
        'approved_by',
        'approval_notes',
    ];

    /**
     * Complaint belongs to an attendance record.
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * Employee submitting the complaint.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * User who approved the complaint.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
