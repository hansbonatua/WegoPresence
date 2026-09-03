<?php

namespace App\Services;

use App\Exceptions\AttendanceException;
use App\Exceptions\GeocodingException;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class AttendanceService
{
    /**
     * Start attendance time (Asia/Jakarta). Check-in exactly at or before
     * this time is PRESENT (Hadir), after it is LATE (Terlambat).
     */
    private const START_ATTENDANCE_TIME = '08:46:00';

    /**
     * Recap on-time cutoff. Aligned with the attendance start time: no
     * late minutes when checking in at or before 08:46:00.
     */
    private const RECAP_CUTOFF_TIME = '08:46:00';

    /**
     * Maximum acceptable age of the browser position timestamp in
     * milliseconds. Positions older or newer than this are rejected.
     */
    private const POSITION_MAX_AGE_MS = 30_000;

    public function __construct(
        private readonly GeocodingService $geocodingService,
    ) {}

    /**
     * Record the check-in for a user for today.
     *
     * @param  array{latitude: float|string, longitude: float|string, position_timestamp?: int, photo?: UploadedFile}  $data
     *
     * @throws AttendanceException When the user has already checked in today,
     *                             the position data is stale, the office is
     *                             not configured, the GPS location cannot be
     *                             verified, or the city does not match the
     *                             assigned office.
     */
    public function checkIn(User $user, array $data): Attendance
    {
        if ($this->todayFor($user) !== null) {
            throw new AttendanceException('You have already checked in today.');
        }

        $this->assertPositionFresh((int) ($data['position_timestamp'] ?? 0));

        $office = $user->office;

        if ($office === null || blank($office->city)) {
            throw new AttendanceException('Your office location is not configured.');
        }

        $latitude = (float) $data['latitude'];
        $longitude = (float) $data['longitude'];

        if (! $this->matchesOfficeCity($latitude, $longitude, $office->city)) {
            throw new AttendanceException('Your current location is outside your assigned office city.');
        }

        $now = Carbon::now('Asia/Jakarta');
        $status = $now->gt(Carbon::parse(self::START_ATTENDANCE_TIME, 'Asia/Jakarta'))
            ? 'late'
            : 'present';

        $photoPath = null;

        if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
            $photoPath = $data['photo']->store('attendance/check-in', 'public');
        }

        try {
            return Attendance::query()->create([
                'user_id' => $user->id,
                'attendance_date' => $now->format('Y-m-d'),
                'check_in_time' => $now->format('H:i:s'),
                'attendance_status' => $status,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'check_in_photo' => $photoPath,
            ]);
        } catch (QueryException) {
            if ($photoPath !== null) {
                Storage::disk('public')->delete($photoPath);
            }

            throw new AttendanceException('You have already checked in today.');
        }
    }

    /**
     * Record the check-out for a user for today.
     *
     * @throws AttendanceException When the user has not checked in yet or
     *                             has already checked out today.
     */
    public function checkOut(User $user, UploadedFile $photo): Attendance
    {
        $attendance = $this->todayFor($user);

        if ($attendance === null) {
            throw new AttendanceException('You have not checked in today.');
        }

        if ($attendance->check_out_time !== null) {
            throw new AttendanceException('You have already checked out today.');
        }

        $photoPath = $photo->store('attendance/check-out', 'public');

        try {
            $attendance->update([
                'check_out_photo' => $photoPath,
                'check_out_time' => now()->format('H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($photoPath);

            throw $e;
        }

        return $attendance->refresh();
    }

    /**
     * Find today's attendance record for a user, if any.
     */
    public function todayFor(User $user): ?Attendance
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('attendance_date', today())
            ->first();
    }

    /**
     * Check whether the user already has an attendance record today.
     */
    public function hasAttendance(User $user): bool
    {
        return $this->todayFor($user) !== null;
    }

    /**
     * Latest attendance records of a user.
     *
     * @return Collection<int, Attendance>
     */
    public function historyFor(User $user, int $limit = 5): Collection
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->latest('attendance_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Paginated attendance recap scoped to the authenticated user.
     *
     * Super admins see every record, admins see records of users in
     * their own office, and employees only see their own records.
     *
     * @param  array{start_date?: string|null, end_date?: string|null, search?: string|null, nip?: string|null, name?: string|null, office_id?: int|string|null, attendance_status?: string|null}  $filters
     */
    public function getAttendanceRecap(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->buildRecapQuery($user, $filters)
            ->latest('attendance_date')
            ->latest('check_in_time')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Attendance $attendance): array => [
                'id' => $attendance->id,
                'user' => $attendance->user ? [
                    'id' => $attendance->user->id,
                    'nip' => $attendance->user->nip,
                    'name' => $attendance->user->name,
                ] : null,
                'office' => $attendance->user?->office ? [
                    'id' => $attendance->user->office->id,
                    'office_code' => $attendance->user->office->office_code,
                    'office_name' => $attendance->user->office->office_name,
                    'city' => $attendance->user->office->city,
                ] : null,
                'attendance_date' => $attendance->attendance_date->format('Y-m-d'),
                'check_in_time' => $attendance->check_in_time?->format('H:i'),
                'check_out_time' => $attendance->check_out_time?->format('H:i'),
                'attendance_status' => self::computeRecapStatus($attendance->check_in_time),
                'late_minutes' => self::computeRecapLateMinutes($attendance->check_in_time),
                'latitude' => $attendance->latitude,
                'longitude' => $attendance->longitude,
            ]);
    }

    /**
     * All recap records (unpaginated) scoped to the authenticated user.
     *
     * @param  array{start_date?: string|null, end_date?: string|null, search?: string|null, nip?: string|null, name?: string|null, office_id?: int|string|null, attendance_status?: string|null}  $filters
     * @return Collection<int, Attendance>
     */
    public function recapRecords(User $user, array $filters = []): Collection
    {
        return $this->buildRecapQuery($user, $filters)
            ->latest('attendance_date')
            ->latest('check_in_time')
            ->get();
    }

    /**
     * Build the role-scoped, filter-aware recap query.
     *
     * @param  array{start_date?: string|null, end_date?: string|null, search?: string|null, nip?: string|null, name?: string|null, office_id?: int|string|null, attendance_status?: string|null}  $filters
     * @return Builder<Attendance>
     */
    private function buildRecapQuery(User $user, array $filters): Builder
    {
        $query = Attendance::query()
            ->with(['user:id,office_id,nip,name,position', 'user.office:id,office_code,office_name,city']);

        if ($user->isSuperAdmin()) {
            // Super admins can see every attendance record.
        } elseif ($user->isAdmin()) {
            $query->whereHas('user', function ($query) use ($user) {
                $query->where('office_id', $user->office_id);
            });
        } else {
            $query->where('user_id', $user->id);
        }

        $startDate = $filters['start_date'] ?? null;

        if (filled($startDate)) {
            $query->whereDate('attendance_date', '>=', $startDate);
        }

        $endDate = $filters['end_date'] ?? null;

        if (filled($endDate)) {
            $query->whereDate('attendance_date', '<=', $endDate);
        }

        $nip = $filters['nip'] ?? null;

        if (filled($nip)) {
            $query->whereHas('user', function ($query) use ($nip) {
                $query->whereRaw('lower(nip) like ?', ['%'.mb_strtolower($nip).'%']);
            });
        }

        $name = $filters['name'] ?? null;

        if (filled($name)) {
            $query->whereHas('user', function ($query) use ($name) {
                $query->whereRaw('lower(name) like ?', ['%'.mb_strtolower($name).'%']);
            });
        }

        $search = $filters['search'] ?? null;

        if (filled($search)) {
            $query->whereHas('user', function ($query) use ($search) {
                $query->whereRaw('lower(nip) like ?', ['%'.mb_strtolower($search).'%'])
                    ->orWhereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%']);
            });
        }

        $officeId = $filters['office_id'] ?? null;

        if (filled($officeId)) {
            $query->whereHas('user', function ($query) use ($officeId) {
                $query->where('office_id', $officeId);
            });
        }

        $status = $filters['attendance_status'] ?? null;

        if (filled($status)) {
            $query->where('attendance_status', $status);
        }

        return $query;
    }

    /**
     * Reject positions whose browser timestamp is too old or ahead of
     * the server clock. A normal check-in happens within milliseconds,
     * so a 30 second tolerance never rejects legitimate users.
     *
     * @throws AttendanceException When the position data is stale.
     */
    private function assertPositionFresh(int $positionTimestamp): void
    {
        $age = now()->getTimestampMs() - $positionTimestamp;

        if ($age > self::POSITION_MAX_AGE_MS) {
            throw new AttendanceException('Your location data is stale. Please refresh your location and try again.');
        }

        if ($age < -self::POSITION_MAX_AGE_MS) {
            throw new AttendanceException('Your location data is stale. Please refresh your location and try again.');
        }
    }

    /**
     * Verify that the GPS coordinates resolve to the office city.
     */
    private function matchesOfficeCity(float $latitude, float $longitude, string $officeCity): bool
    {
        $gpsCity = $this->resolveGpsCity($latitude, $longitude);

        $normalizedOfficeCity = CityNormalizer::normalize($officeCity);

        foreach ($gpsCity['candidates'] as $candidate) {
            if (CityNormalizer::normalize($candidate) === $normalizedOfficeCity) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the city for a coordinate pair, translating geocoding
     * failures into a user-friendly attendance error.
     *
     * @return array{city: string, candidates: array<int, string>}
     *
     * @throws AttendanceException
     */
    private function resolveGpsCity(float $latitude, float $longitude): array
    {
        try {
            return $this->geocodingService->reverse($latitude, $longitude);
        } catch (GeocodingException) {
            throw new AttendanceException('Unable to verify your location. Please make sure GPS is enabled and try again.');
        }
    }

    /**
     * Compute the recap status for a given attendance record.
     *
     * Uses a fixed 08:46:00 cutoff regardless of office schedule.
     * Returns 'present' if check-in time is <= 08:46:00, 'late' if
     * > 08:46:00, or null when check_in_time is not set.
     */
    public static function computeRecapStatus(?CarbonInterface $checkInTime): ?string
    {
        if ($checkInTime === null) {
            return null;
        }

        // Compare at second precision: 08:46 and below = Present (Hadir),
        // anything after 08:46:00 = Late (Terlambat).
        return $checkInTime->format('H:i:s') <= self::RECAP_CUTOFF_TIME
            ? 'present'
            : 'late';
    }

    /**
     * Compute late minutes for the Recap view.
     *
     * Late minutes = max(0, floor((check_in_time - 08:46:00) in total
     * seconds / 60)). Returns null when check_in_time is not set.
     *
     * Boundary examples:
     *   08:46:00 → 0 min (present)
     *   08:46:59 → 0 min (late, diff = 59 s → floor(59/60) = 0)
     *   08:47:00 → 1 min (late, diff = 60 s → floor(60/60) = 1)
     *   09:00:00 → 14 min (late, diff = 840 s → floor(840/60) = 14)
     */
    public static function computeRecapLateMinutes(?CarbonInterface $checkInTime): ?int
    {
        if ($checkInTime === null) {
            return null;
        }

        $cutoff = Carbon::parse(self::RECAP_CUTOFF_TIME);

        if ($checkInTime->lte($cutoff)) {
            return 0;
        }

        return intdiv(abs($checkInTime->timestamp - $cutoff->timestamp), 60);
    }
}
