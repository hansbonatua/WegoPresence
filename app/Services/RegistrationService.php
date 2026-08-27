<?php

namespace App\Services;

use App\Exceptions\UserRegistrationException;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RegistrationService
{
    /**
     * The prefix of NIPs handed out when an admin activates a registration.
     */
    private const NIP_PREFIX = '010';

    /**
     * The number of digits after the prefix the generated NIP holds.
     */
    private const NIP_LENGTH = 3;

    /**
     * How many attempts are made to find a free NIP before giving up.
     */
    private const NIP_MAX_ATTEMPTS = 5;

    /**
     * Register a new employee account. The role (always "user") and the
     * status ("pending") are always determined by the server, never by
     * the request. The NIP is supplied by the registrant.
     *
     * @param  array{nip: string, name: string, email: string, password: string, join_date: string, phone: string, office_id: int, city: string, position: string}  $data
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $role = Role::query()->firstOrCreate(['name' => 'user'], ['name' => 'user']);

            return User::query()->create([
                'role_id' => $role->id,
                'office_id' => $data['office_id'],
                'nip' => $data['nip'],
                'name' => $data['name'],
                'position' => $data['position'],
                'email' => $data['email'],
                'join_date' => $data['join_date'],
                'city' => $data['city'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'status' => 'pending',
            ]);
        });
    }

    /**
     * Activate a pending registration by marking the account active.
     * If the user already has a NIP (registered with NIP), it is
     * preserved. For legacy pending users without a NIP, a new one
     * is generated.
     *
     * @throws UserRegistrationException When the account is not pending
     *                                   or no NIP can be generated.
     */
    public function activate(User $user, User $reviewer): User
    {
        return DB::transaction(function () use ($user, $reviewer): User {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            $this->assertPending($user);

            $user->update([
                'status' => 'active',
                'nip' => $user->nip ?? $this->nextFreeNip(),
                'approved_by' => $reviewer->id,
                'approved_at' => now(),
                'rejected_reason' => null,
            ]);

            return $user->refresh();
        });
    }

    /**
     * Reject a pending registration, recording the reviewer and reason.
     *
     * @throws UserRegistrationException When the account is not pending.
     */
    public function reject(User $user, User $reviewer, string $reason): User
    {
        return DB::transaction(function () use ($user, $reviewer, $reason): User {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            $this->assertPending($user);

            $user->update([
                'status' => 'rejected',
                'nip' => null,
                'approved_by' => $reviewer->id,
                'approved_at' => now(),
                'rejected_reason' => $reason,
            ]);

            return $user->refresh();
        });
    }

    /**
     * Paginate registrations by status, scoping admins to their own office.
     *
     * @param  array{search?: string|null}  $filters
     */
    public function paginate(User $reviewer, string $status, array $filters = []): LengthAwarePaginator
    {
        $this->assertStatus($status);

        $query = User::query()
            ->with(['role:id,name', 'office:id,office_code,office_name'])
            ->where('status', $status)
            ->latest();

        if ($reviewer->isAdmin()) {
            $query->where('office_id', $reviewer->office_id);
        }

        $search = $filters['search'] ?? null;

        if (filled($search)) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('nip', 'ilike', "%{$search}%");
            });
        }

        return $query->paginate(10)->withQueryString();
    }

    /**
     * The number of pending registrations the reviewer is allowed to see.
     */
    public function pendingCount(User $reviewer): int
    {
        return $this->countByStatus($reviewer, 'pending');
    }

    /**
     * The number of accounts in the given status the reviewer can see.
     */
    public function countByStatus(User $reviewer, string $status): int
    {
        $this->assertStatus($status);

        return User::query()
            ->where('status', $status)
            ->when($reviewer->isAdmin(), fn ($query) => $query->where('office_id', $reviewer->office_id))
            ->count();
    }

    /**
     * The smallest free NIP in the sequence, avoiding every NIP that
     * already exists (including soft deleted accounts).
     *
     * @throws UserRegistrationException When the sequence is exhausted.
     */
    private function nextFreeNip(): string
    {
        for ($attempt = 1; $attempt <= self::NIP_MAX_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(function () {
                    $taken = User::query()
                        ->withTrashed()
                        ->where('nip', 'like', self::NIP_PREFIX.'%')
                        ->pluck('nip')
                        ->map(fn (string $nip): int => (int) substr($nip, strlen(self::NIP_PREFIX)))
                        ->filter(fn (int $number): bool => $number >= 1 && $number <= 999)
                        ->all();

                    $free = collect(range(1, 999))->diff($taken)->min();

                    if ($free === null) {
                        throw new UserRegistrationException('No NIP numbers are available.');
                    }

                    $nip = self::NIP_PREFIX.str_pad((string) $free, self::NIP_LENGTH, '0', STR_PAD_LEFT);

                    return $nip;
                });
            } catch (QueryException $exception) {
                if (! $this->isUniqueViolation($exception) || $attempt === self::NIP_MAX_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        throw new UserRegistrationException('No NIP numbers are available.');
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $message = $exception->getPrevious()?->getMessage() ?? $exception->getMessage();

        return str_contains($message, 'unique') || str_contains($message, 'Duplicate entry');
    }

    /**
     * @throws UserRegistrationException
     */
    private function assertPending(User $user): void
    {
        if ($user->status !== 'pending') {
            throw new UserRegistrationException('Only pending registrations can be modified.');
        }
    }

    private function assertStatus(string $status): void
    {
        if (! in_array($status, ['pending', 'active', 'rejected'], true)) {
            throw new \InvalidArgumentException("Unsupported registration status [{$status}].");
        }
    }
}
