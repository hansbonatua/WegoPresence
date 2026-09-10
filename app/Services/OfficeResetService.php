<?php

namespace App\Services;

use App\Models\Office;
use Illuminate\Support\Facades\DB;

/**
 * Resets the office master data to the five official offices.
 *
 * No legacy office is mapped: every user pointing to any office other
 * than Jakarta is moved to JKT001 first, then all non-official offices
 * are hard deleted and the five official offices are recreated.
 */
class OfficeResetService
{
    /**
     * Final master data for the five official offices.
     *
     * @var array<string, array{office_code: string, office_name: string, city: string, address: string, status: string, start_time: string, end_time: string}>
     */
    private const TARGETS = [
        'JKT001' => [
            'office_code' => 'JKT001',
            'office_name' => 'Jakarta Head Office',
            'city' => 'Jakarta',
            'address' => 'Wisma 67, Jl. Tanah Abang II No. 67, Petojo Selatan, Gambir, Jakarta Pusat 10160',
            'status' => 'active',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ],
        'MDN001' => [
            'office_code' => 'MDN001',
            'office_name' => 'Medan Office',
            'city' => 'Medan',
            'address' => 'Medan',
            'status' => 'active',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ],
        'SBY001' => [
            'office_code' => 'SBY001',
            'office_name' => 'Surabaya Office',
            'city' => 'Surabaya',
            'address' => 'Surabaya',
            'status' => 'active',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ],
        'MKS001' => [
            'office_code' => 'MKS001',
            'office_name' => 'Makassar Office',
            'city' => 'Makassar',
            'address' => 'Makassar',
            'status' => 'active',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ],
        'PDG001' => [
            'office_code' => 'PDG001',
            'office_name' => 'Padang Office',
            'city' => 'Padang',
            'address' => 'Padang',
            'status' => 'active',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ],
    ];

    /**
     * Recreate the five official offices, move every remaining user to
     * Jakarta and purge all other offices.
     *
     * @return array{created_offices: list<string>, users_moved_to_jakarta: int, deleted_offices: list<string>, official_offices: list<string>}
     */
    public function reset(): array
    {
        $created = [];
        $targets = [];

        foreach (self::TARGETS as $code => $attributes) {
            $office = Office::withTrashed()->firstOrCreate(
                ['office_code' => $code],
                $attributes,
            );

            if ($office->wasRecentlyCreated) {
                $created[] = $code;
            }

            if ($office->trashed()) {
                $office->restore();
            }

            // Reconcile existing offices to the canonical master data.
            $office->forceFill($attributes)->save();

            $targets[$code] = $office;
        }

        $jakarta = $targets['JKT001'];

        // Move every user that does not belong to Jakarta.
        $usersMoved = DB::table('users')
            ->where('office_id', '!=', $jakarta->id)
            ->update(['office_id' => $jakarta->id]);

        // Purge every office that is not one of the five official ones.
        $deleted = [];

        $offices = Office::withTrashed()
            ->whereNotIn('office_code', array_keys(self::TARGETS))
            ->get();

        foreach ($offices as $office) {
            $deleted[] = $office->office_code;
            $office->forceDelete();
        }

        return [
            'created_offices' => $created,
            'users_moved_to_jakarta' => $usersMoved,
            'deleted_offices' => $deleted,
            'official_offices' => array_keys(self::TARGETS),
        ];
    }
}
