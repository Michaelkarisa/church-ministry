<?php

namespace App\Http\Controllers;

use App\Models\TransactionType;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionTypeController extends Controller
{
    use ApiResponse;

    /** GET /api/transaction-types — public to all admins */
    public function index(Request $request): JsonResponse
    {
        $types = TransactionType::query()
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return $this->successResponse($types);
    }

    /** POST /api/transaction-types — ministry admin only */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'code'          => ['required', 'string', 'max:30', 'unique:transaction_types,code'],
            'description'   => ['nullable', 'string'],
            'category'      => ['required', Rule::in(array_keys(config('church.transaction_categories')))],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active'     => ['boolean'],
        ]);

        $type = TransactionType::create($request->validated());
        return $this->createdResponse($type, 'Transaction type created.');
    }

    /** GET /api/transaction-types/{type} */
    public function show(TransactionType $transactionType): JsonResponse
    {
        return $this->successResponse($transactionType->loadCount('transactions'));
    }

    /** PUT /api/transaction-types/{type} */
    public function update(Request $request, TransactionType $transactionType): JsonResponse
    {
        $request->validate([
            'name'          => ['sometimes', 'string', 'max:100'],
            'code'          => ['sometimes', 'string', 'max:30', Rule::unique('transaction_types', 'code')->ignore($transactionType->id)],
            'description'   => ['nullable', 'string'],
            'category'      => ['sometimes', Rule::in(array_keys(config('church.transaction_categories')))],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active'     => ['boolean'],
        ]);

        $transactionType->update($request->validated());
        return $this->successResponse($transactionType->fresh(), 'Transaction type updated.');
    }

    /** DELETE /api/transaction-types/{type} */
    public function destroy(TransactionType $transactionType): JsonResponse
    {
        if ($transactionType->transactions()->exists()) {
            return $this->errorResponse(
                'Cannot delete a transaction type that has existing financial records.',
                422
            );
        }

        $transactionType->delete();
        return $this->noContentResponse('Transaction type deleted.');
    }
}
