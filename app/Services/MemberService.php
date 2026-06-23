<?php

namespace App\Services;

use App\Models\Member;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class MemberService
{
    /**
     * Return a paginated, role-scoped list of members.
     *
     * Accepted filters: search, is_active (bool|null), gender, church_id
     */
    public function index(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Member::with('church')
            ->when($filters['search'] ?? null, fn ($q, $v) =>
                $q->where('first_name', 'like', "%{$v}%")
                  ->orWhere('last_name', 'like', "%{$v}%")
                  ->orWhere('member_number', 'like', "%{$v}%")
                  ->orWhere('email', 'like', "%{$v}%")
                  ->orWhere('phone', 'like', "%{$v}%")
            )
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']))
            ->when($filters['gender'] ?? null, fn ($q, $v) => $q->where('gender', $v))
            ->when($filters['church_id'] ?? null, fn ($q, $v) => $q->where('church_id', $v));

        if ($user->isZoneAdmin()) {
            $query->whereHas('church', fn ($q) => $q->where('zone_id', $user->zone_id));
        } elseif ($user->isChurchAdmin()) {
            $query->where('church_id', $user->church_id);
        }

        return $query->orderBy('first_name')->orderBy('last_name')->paginate($perPage);
    }

    /**
     * Create a member from validated data.
     */
    public function store(array $data): Member
    {
        return Member::create($data);
    }

    /**
     * Load a member with church, zone, and transaction count.
     */
    public function show(Member $member): Member
    {
        return $member->load(['church.zone'])->loadCount('transactions');
    }

    /**
     * Update member fields and return the refreshed model.
     */
    public function update(Member $member, array $data): Member
    {
        $member->update($data);
        return $member->fresh()->load('church');
    }

    /**
     * Soft-delete a member.
     */
    public function destroy(Member $member): void
    {
        $member->delete();
    }

    /**
     * Whether the given user may read/write this member record.
     */
    public function canAccess(User $user, Member $member): bool
    {
        if ($user->isMinistryAdmin()) {
            return true;
        }

        if ($user->isZoneAdmin()) {
            return $member->church->zone_id === $user->zone_id;
        }

        return $member->church_id === $user->church_id;
    }
}
