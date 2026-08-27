<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'role_id',
        'office_id',
        'nip',
        'name',
        'position',
        'email',
        'join_date',
        'city',
        'phone',
        'password',
        'status',
        'approved_by',
        'approved_at',
        'rejected_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'password' => 'hashed',
        ];
    }

    /**
     * Role of the user.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Office where the user is assigned.
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * User attendances.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * User permissions.
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    /**
     * User sick leaves.
     */
    public function sickLeaves(): HasMany
    {
        return $this->hasMany(SickLeave::class);
    }

    /**
     * User leave requests.
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * User attendance complaints.
     */
    public function attendanceComplaints(): HasMany
    {
        return $this->hasMany(AttendanceComplaint::class);
    }

    /**
     * User business trips.
     */
    public function businessTrips(): HasMany
    {
        return $this->hasMany(BusinessTrip::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->name === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === 'admin';
    }

    public function isUser(): bool
    {
        return $this->role?->name === 'user';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * The user who reviewed this registration.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
