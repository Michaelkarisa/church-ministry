<?php

namespace App\Http\Controllers;

use App\Models\TransactionType;
use App\Services\TransactionTypeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionTypeController extends Controller
{
    use ApiResponse;

    public function __construct(private TransactionTypeService $transactionTypeService) {}

    /** GET /api/transaction-types */
    public function index(Request $request): JsonResponse
    {
        $types = $this->transactionTypeService->index([
            'is_active' => $request->filled('is_active') ? $request->boolean('is_active') : null,
            'category'  => $request->category,
        ]);

        return $this->successResponse($types);
    }

    /** POST /api/transaction-types */
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

        $type = $this->transactionTypeService->store($request->validated());

        return $this->createdResponse($type, 'Transaction type created.');
    }

    /** GET /api/transaction-types/{transactionType} */
    public function show(TransactionType $transactionType): JsonResponse
    {
        return $this->successResponse(
            $this->transactionTypeService->show($transactionType)
        );
    }

    /** PUT /api/transaction-types/{transactionType} */
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

        $type = $this->transactionTypeService->update($transactionType, $request->validated());

        return $this->successResponse($type, 'Transaction type updated.');
    }

    /** DELETE /api/transaction-types/{transactionType} */
    public function destroy(TransactionType $transactionType): JsonResponse
    {
        try {
            $this->transactionTypeService->destroy($transactionType);
            return $this->noContentResponse('Transaction type deleted.');
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 422);
        }
    }
}
