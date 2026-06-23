<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use App\Models\Member;
use App\Services\MemberService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    use ApiResponse;

    public function __construct(private MemberService $memberService) {}

    /** GET /api/members */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $paginator = $this->memberService->index(
            $request->user(),
            [
                'search'    => $request->search,
                'is_active' => $request->filled('is_active') ? $request->boolean('is_active') : null,
                'gender'    => $request->gender,
                'church_id' => $request->church_id,
            ],
            $perPage,
        );

        return $this->successResponse($paginator);
    }

    /** POST /api/members */
    public function store(StoreMemberRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isChurchAdmin() && $request->church_id !== $user->church_id) {
            return $this->forbiddenResponse('You can only add members to your own church.');
        }

        $member = $this->memberService->store($request->validated());

        return $this->createdResponse($member->load('church'), 'Member added successfully.');
    }

    /** GET /api/members/{member} */
    public function show(Request $request, Member $member): JsonResponse
    {
        if (! $this->memberService->canAccess($request->user(), $member)) {
            return $this->forbiddenResponse();
        }

        return $this->successResponse($this->memberService->show($member));
    }

    /** PUT /api/members/{member} */
    public function update(Request $request, Member $member): JsonResponse
    {
        if (! $this->memberService->canAccess($request->user(), $member)) {
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

        $member = $this->memberService->update($member, $request->validated());

        return $this->successResponse($member, 'Member updated.');
    }

    /** DELETE /api/members/{member} */
    public function destroy(Request $request, Member $member): JsonResponse
    {
        if (! $this->memberService->canAccess($request->user(), $member)) {
            return $this->forbiddenResponse();
        }

        $this->memberService->destroy($member);

        return $this->noContentResponse('Member deleted.');
    }
}
