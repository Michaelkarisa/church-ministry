<?php

namespace App\Support;

use App\Models\Role;
use Illuminate\Validation\ValidationException;

/**
 * One rule for every place an admin's role or area can change:
 *
 *   region_admin    must have region_id
 *   zone_admin      must have zone_id
 *   sub_zone_admin  must have sub_zone_id
 *   church_admin    must have church_id
 *   ministry_admin  has none of them
 *
 * Ids that belong to a different level are cleared, so an account never carries
 * a stray id (e.g. a zone admin that still has an old church_id).
 */
class AdminScope
{
    public const COLUMNS = ['region_id', 'zone_id', 'sub_zone_id', 'church_id'];

    private const LABELS = [
        'region_id'   => 'a region',
        'zone_id'     => 'a zone',
        'sub_zone_id' => 'a sub-zone',
        'church_id'   => 'a church',
    ];

    /**
     * @param  Role   $role     the role the user will have
     * @param  array  $ids      the ids the user will have (existing values overlaid with new input)
     * @return array            all four columns, with the one this role needs set and the rest null
     * @throws ValidationException when the id this role needs is missing
     */
    public static function resolve(Role $role, array $ids): array
    {
        $needed = Role::SCOPE_COLUMNS[$role->name] ?? null;   // null => ministry admin

        $out = array_fill_keys(self::COLUMNS, null);

        if ($needed === null) {
            return $out;
        }

        if (empty($ids[$needed])) {
            throw ValidationException::withMessages([
                $needed => ["A {$role->display_name} must be assigned " . self::LABELS[$needed] . " to manage ({$needed} is required)."],
            ]);
        }

        $out[$needed] = $ids[$needed];

        return $out;
    }
}
