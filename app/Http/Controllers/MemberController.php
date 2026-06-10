<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use App\Models\Member;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    use ApiResponse;

    /** GET /api/members */
    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $perPage = min((int) $request->get('per_page', 15), 100);

        $query = Member::with('church')
            ->when($request->search, fn ($q) =>
                $q->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%")
                  ->orWhere('member_number', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
            )
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->gender, fn ($q) => $q->where('gender', $request->gender))
            ->when($request->church_id, fn ($q) => $q->where('church_id', $request->church_id));

        // Scope by role
        if ($user->isZoneAdmin()) {
            $query->whereHas('church', fn ($q) => $q->where('zone_id', $user->zone_id));
        } elseif ($user->isChurchAdmin()) {
            $query->where('church_id', $user->church_id);
        }

        return $this->successResponse(
            $query->orderBy('first_name')->orderBy('last_name')->paginate($perPage)
        );
    }

    /** POST /api/members */
    public function store(StoreMemberRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isChurchAdmin() && $request->church_id !== $user->church_id) {
            return $this->forbiddenResponse('You can only add members to your own church.');
        }

        $member = Member::create($request->validated());
        return $this->createdResponse($member->load('church'), 'Member added successfully.');
    }

    /** GET /api/members/{member} */
    public function show(Request $request, Member $member): JsonResponse
    {
        if (! $this->canAccess($request->user(), $member)) {
            return $this->forbiddenResponse();
        }

        return $this->successResponse(
            $member->load(['church.zone'])
                   ->loadCount('transactions')
        );
    }

    /** PUT /api/members/{member} */
    public function update(Request $request, Member $member): JsonResponse
    {
        if (! $this->canAccess($request->user(), $member)) {
            return $this->forbiddenResponse();
        }

        $request->validate([
            'member_number'   => ['sometimes', 'string', 'max:30', Rule::unique('members')->ignore($member->id)],
            'first_name'      => ['sometimes', 'string', 'max:100'],
            'last_name'       => ['sometimes', 'string', 'max:100'],
            'email'           => ['nullable', 'email'],
            'phone'           => ['nullable', 'string', 'max:20'],
            'date_of_birth'   => ['nullable', 'date'],
            'gender'          => ['nullable', Rule::in(['male', 'female', 'other'])],
            'marital_status'  => ['nullable', Rule::in(['single', 'married', 'widowed', 'divorced'])],
            'address'         => ['nullable', 'string'],
            'occupation'      => ['nullable', 'string', 'max:150'],
            'membership_date' => ['nullable', 'date'],
            'is_active'       => ['boolean'],
        ]);

        $member->update($request->validated());
        return $this->successResponse($member->fresh()->load('church'), 'Member updated.');
    }

    /** DELETE /api/members/{member} */
    public function destroy(Request $request, Member $member): JsonResponse
    {
        if (! $this->canAccess($request->user(), $member)) {
            return $this->forbiddenResponse();
        }

        $member->delete();
        return $this->noContentResponse('Member deleted.');
    }

    private function canAccess($user, Member $member): bool
    {
        if ($user->isMinistryAdmin()) return true;
        if ($user->isZoneAdmin())     return $member->church->zone_id === $user->zone_id;
        return $member->church_id === $user->church_id;
    }
}
