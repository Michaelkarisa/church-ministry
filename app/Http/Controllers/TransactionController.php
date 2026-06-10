<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\ActivityLog;
use App\Models\Church;
use App\Models\Transaction;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    use ApiResponse;

    // ---------------------------------------------------------------
    // List
    // ---------------------------------------------------------------

    /** GET /api/transactions */
    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $perPage = min((int) $request->get('per_page', 15), 100);

        $query = Transaction::with(['church', 'transactionType', 'recorder', 'member'])
            ->when($request->church_id, fn ($q) => $q->where('church_id', $request->church_id))
            ->when($request->transaction_type_id, fn ($q) => $q->where('transaction_type_id', $request->transaction_type_id))
            ->when($request->category, fn ($q) =>
                $q->whereHas('transactionType', fn ($t) => $t->where('category', $request->category))
            )
            ->when($request->service_type, fn ($q) => $q->where('service_type', $request->service_type))
            ->when($request->filled('is_verified'), fn ($q) => $q->where('is_verified', $request->boolean('is_verified')))
            ->when($request->from && $request->to, fn ($q) =>
                $q->whereBetween('transaction_date', [$request->from, $request->to])
            )
            ->when($request->member_id, fn ($q) => $q->where('member_id', $request->member_id))
            ->when($request->search, fn ($q) =>
                $q->where('reference_number', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%")
            );

        // Scope by role
        if ($user->isZoneAdmin()) {
            $churchIds = Church::where('zone_id', $user->zone_id)->pluck('id');
            $query->whereIn('church_id', $churchIds);
        } elseif ($user->isChurchAdmin()) {
            $query->where('church_id', $user->church_id);
        }

        return $this->successResponse(
            $query->orderByDesc('transaction_date')->orderByDesc('id')->paginate($perPage)
        );
    }

    // ---------------------------------------------------------------
    // Create
    // ---------------------------------------------------------------

    /** POST /api/transactions */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Enforce church scope
        if ($user->isChurchAdmin() && $data["church_id"] !== $user->church_id) {
            return $this->forbiddenResponse('You can only record transactions for your own church.');
        }

        if ($user->isZoneAdmin()) {
            $church = Church::find($data['church_id']);
            if (! $church || $church->zone_id !== $user->zone_id) {
                return $this->forbiddenResponse('That church is not in your zone.');
            }
        }

        $data['recorded_by'] = $user->id;
        $data['currency']    = $data['currency'] ?? config('church.default_currency');

        // Auto-generate reference number if not provided
        if (empty($data['reference_number'])) {
            $data['reference_number'] = $this->generateReference($data['church_id'], $data['transaction_date']);
        }

        $transaction = Transaction::create($data);

        ActivityLog::record($user, 'created', 'transactions',
            "Recorded {$transaction->currency} " . number_format($transaction->amount, 2) . " ({$transaction->transactionType?->name})",
            ['record_id' => $transaction->id, 'record_type' => 'Transaction']
        );

        return $this->createdResponse(
            $transaction->load(['transactionType', 'recorder', 'member', 'church']),
            'Transaction recorded successfully.'
        );
    }

    // ---------------------------------------------------------------
    // Show
    // ---------------------------------------------------------------

    /** GET /api/transactions/{transaction} */
    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        if (! $this->canAccess($request->user(), $transaction)) {
            return $this->forbiddenResponse();
        }

        return $this->successResponse(
            $transaction->load(['church.zone', 'transactionType', 'recorder', 'member', 'verifier'])
        );
    }

    // ---------------------------------------------------------------
    // Update
    // ---------------------------------------------------------------

    /** PUT /api/transactions/{transaction} */
    public function update(UpdateTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        $user = $request->user();

        if (! $this->canAccess($user, $transaction)) {
            return $this->forbiddenResponse();
        }

        if ($transaction->is_verified && ! $user->isMinistryAdmin()) {
            return $this->errorResponse('Verified transactions can only be edited by a Ministry Administrator.', 422);
        }

        $old = $transaction->only(['amount', 'transaction_type_id', 'transaction_date', 'service_type', 'description']);
        $transaction->update($request->validated());

        ActivityLog::record($user, 'updated', 'transactions',
            "Updated transaction #{$transaction->id}",
            [
                'record_id'   => $transaction->id,
                'record_type' => 'Transaction',
                'old_values'  => $old,
                'new_values'  => $request->validated(),
            ]
        );

        return $this->successResponse(
            $transaction->fresh()->load(['transactionType', 'recorder', 'church']),
            'Transaction updated.'
        );
    }

    // ---------------------------------------------------------------
    // Delete
    // ---------------------------------------------------------------

    /** DELETE /api/transactions/{transaction} */
    public function destroy(Request $request, Transaction $transaction): JsonResponse
    {
        $user = $request->user();

        if (! $this->canAccess($user, $transaction)) {
            return $this->forbiddenResponse();
        }

        if ($transaction->is_verified && ! $user->isMinistryAdmin()) {
            return $this->errorResponse('Verified transactions cannot be deleted.', 422);
        }

        ActivityLog::record($user, 'deleted', 'transactions',
            "Deleted transaction #{$transaction->id} ({$transaction->currency} " . number_format($transaction->amount, 2) . ")",
            ['record_id' => $transaction->id, 'record_type' => 'Transaction']
        );

        $transaction->delete();
        return $this->noContentResponse('Transaction deleted.');
    }

    // ---------------------------------------------------------------
    // Verify
    // ---------------------------------------------------------------

    /** POST /api/transactions/{transaction}/verify */
    public function verify(Request $request, Transaction $transaction): JsonResponse
    {
        $user = $request->user();

        if (! $this->canAccess($user, $transaction)) {
            return $this->forbiddenResponse();
        }

        if ($transaction->is_verified) {
            return $this->errorResponse('Transaction is already verified.', 422);
        }

        $transaction->update([
            'is_verified' => true,
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        ActivityLog::record($user, 'verified', 'transactions',
            "Verified transaction #{$transaction->id}",
            ['record_id' => $transaction->id, 'record_type' => 'Transaction']
        );

        return $this->successResponse(
            $transaction->fresh()->load(['transactionType', 'verifier', 'church']),
            'Transaction verified.'
        );
    }

    /** POST /api/transactions/{transaction}/unverify */
    public function unverify(Request $request, Transaction $transaction): JsonResponse
    {
        if (! $request->user()->isMinistryAdmin()) {
            return $this->forbiddenResponse('Only Ministry Administrators can reverse verification.');
        }

        $transaction->update([
            'is_verified' => false,
            'verified_by' => null,
            'verified_at' => null,
        ]);

        return $this->successResponse($transaction->fresh(), 'Transaction verification reversed.');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function canAccess($user, Transaction $transaction): bool
    {
        if ($user->isMinistryAdmin()) return true;
        if ($user->isZoneAdmin()) {
            return $transaction->church?->zone_id === $user->zone_id;
        }
        return $transaction->church_id === $user->church_id;
    }

    private function generateReference(string $churchId, string $date): string
    {
        $church = Church::find($churchId);
        $code   = Str::upper(substr($church?->code ?? 'CHR', 0, 4));
        $d      = str_replace('-', '', $date);
        $rand   = strtoupper(Str::random(4));
        return "TXN-{$code}-{$d}-{$rand}";
    }
}
