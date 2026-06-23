<?php

namespace App\Services;

use App\Models\TransactionType;
use Illuminate\Database\Eloquent\Collection;

class TransactionTypeService
{
    /**
     * Return all transaction types, optionally filtered by is_active and category.
     *
     * Accepted filters: is_active (bool|null), category
     */
    public function index(array $filters): Collection
    {
        return TransactionType::query()
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']))
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a transaction type from validated data.
     */
    public function store(array $data): TransactionType
    {
        return TransactionType::create($data);
    }

    /**
     * Load a transaction type with its transaction count.
     */
    public function show(TransactionType $transactionType): TransactionType
    {
        return $transactionType->loadCount('transactions');
    }

    /**
     * Update a transaction type and return the refreshed model.
     */
    public function update(TransactionType $transactionType, array $data): TransactionType
    {
        $transactionType->update($data);
        return $transactionType->fresh();
    }

    /**
     * Delete a transaction type.
     * Throws \DomainException(422) when transactions still reference it.
     */
    public function destroy(TransactionType $transactionType): void
    {
        if ($transactionType->transactions()->exists()) {
            throw new \DomainException(
                'Cannot delete a transaction type that has existing financial records.',
                422
            );
        }

        $transactionType->delete();
    }
}
