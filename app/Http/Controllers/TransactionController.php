<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Church;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    use ApiResponse;

    public function __construct(private TransactionService $transactionService) {}

    /** GET /api/transactions */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $paginator = $this->transactionService->index(
            $request->user(),
            [
                'church_id'           => $request->church_id,
                'transaction_type_id' => $request->transaction_type_id,
                'category'            => $request->category,
                'service_type'        => $request->service_type,
                'is_verified'         => $request->filled('is_verified') ? $request->boolean('is_verified') : null,
                'from'                => $request->from,
                'to'                  => $request->to,
                'member_id'           => $request->member_id,
                'search'              => $request->search,
            ],
            $perPage,
        );

        return $this->successResponse($paginator);
    }

    /** POST /api/transactions */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if ($user->isChurchAdmin() && $data['church_id'] !== $user->church_id) {
            return $this->forbiddenResponse('You can only record transactions for your own church.');
        }

        if ($user->isZoneAdmin()) {
            $church = Church::find($data['church_id']);
            if (! $church || $church->zone_id !== $user->zone_id) {
                return $this->forbiddenResponse('That church is not in your zone.');
            }
        }

        $transaction = $this->transactionService->store($user, $data);

        return $this->createdResponse($transaction, 'Transaction recorded successfully.');
    }

    /** GET /api/transactions/{transaction} */
    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        if (! $this->transactionService->canAccess($request->user(), $transaction)) {
            return $this->forbiddenResponse();
        }

        return $this->successResponse($this->transactionService->show($transaction));
    }

    /** PUT /api/transactions/{transaction} */
    public function update(UpdateTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        $user = $request->user();

        if (! $this->transactionService->canAccess($user, $transaction)) {
            return $this->forbiddenResponse();
        }

        try {
            $transaction = $this->transactionService->update($user, $transaction, $request->validated());
            return $this->successResponse($transaction, 'Transaction updated.');
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /** DELETE /api/transactions/{transaction} */
    public function destroy(Request $request, Transaction $transaction): JsonResponse
    {
        $user = $request->user();

        if (! $this->transactionService->canAccess($user, $transaction)) {
            return $this->forbiddenResponse();
        }

        try {
            $this->transactionService->destroy($user, $transaction);
            return $this->noContentResponse('Transaction deleted.');
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /** POST /api/transactions/{transaction}/verify */
    public function verify(Request $request, Transaction $transaction): JsonResponse
    {
        $user = $request->user();

        if (! $this->transactionService->canAccess($user, $transaction)) {
            return $this->forbiddenResponse();
        }

        try {
            $transaction = $this->transactionService->verify($user, $transaction);
            return $this->successResponse($transaction, 'Transaction verified.');
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /** POST /api/transactions/{transaction}/unverify */
    public function unverify(Request $request, Transaction $transaction): JsonResponse
    {
        if (! $request->user()->isMinistryAdmin()) {
            return $this->forbiddenResponse('Only Ministry Administrators can reverse verification.');
        }

        $transaction = $this->transactionService->unverify($transaction);

        return $this->successResponse($transaction, 'Transaction verification reversed.');
    }
}
