<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'attendance_date',

        'check_in_time',
        'check_in_photo',
        'check_in_latitude',
        'check_in_longitude',
        'check_in_address',

        'check_out_time',
        'check_out_photo',
        'check_out_latitude',
        'check_out_longitude',
        'check_out_address',

        'attendance_status',
        'branch_area',
        'notes',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in_time' => 'datetime:H:i:s',
            'check_out_time' => 'datetime:H:i:s',

            'check_in_latitude' => 'decimal:7',
            'check_in_longitude' => 'decimal:7',

            'check_out_latitude' => 'decimal:7',
            'check_out_longitude' => 'decimal:7',

            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * Attendance belongs to a User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Attendance has many complaints.
     */
    public function attendanceComplaints(): HasMany
    {
        return $this->hasMany(AttendanceComplaint::class);
    }
}
