<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Church;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class TransactionService
{
    // ---------------------------------------------------------------
    // Query
    // ---------------------------------------------------------------

    /**
     * Return a paginated, role-scoped list of transactions.
     *
     * Accepted filters: church_id, transaction_type_id, category, service_type,
     *                   is_verified (bool|null), from, to, member_id, search
     */
    public function index(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Transaction::with(['church', 'transactionType', 'recorder', 'member'])
            ->when($filters['church_id'] ?? null, fn ($q, $v) => $q->where('church_id', $v))
            ->when($filters['transaction_type_id'] ?? null, fn ($q, $v) => $q->where('transaction_type_id', $v))
            ->when($filters['category'] ?? null, fn ($q, $v) =>
                $q->whereHas('transactionType', fn ($t) => $t->where('category', $v))
            )
            ->when($filters['service_type'] ?? null, fn ($q, $v) => $q->where('service_type', $v))
            ->when(isset($filters['is_verified']), fn ($q) => $q->where('is_verified', $filters['is_verified']))
            ->when(
                ($filters['from'] ?? null) && ($filters['to'] ?? null),
                fn ($q) => $q->whereBetween('transaction_date', [$filters['from'], $filters['to']])
            )
            ->when($filters['member_id'] ?? null, fn ($q, $v) => $q->where('member_id', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) =>
                $q->where('reference_number', 'like', "%{$v}%")
                  ->orWhere('description', 'like', "%{$v}%")
            );

        if ($user->isZoneAdmin()) {
            $churchIds = Church::where('zone_id', $user->zone_id)->pluck('id');
            $query->whereIn('church_id', $churchIds);
        } elseif ($user->isChurchAdmin()) {
            $query->where('church_id', $user->church_id);
        }

        return $query->orderByDesc('transaction_date')->orderByDesc('id')->paginate($perPage);
    }

    // ---------------------------------------------------------------
    // Create
    // ---------------------------------------------------------------

    /**
     * Record a new transaction.
     * The caller is responsible for verifying scope access before calling this method.
     */
    public function store(User $user, array $data): Transaction
    {
        $data['recorded_by'] = $user->id;
        $data['currency']    = $data['currency'] ?? config('church.default_currency');

        if (empty($data['reference_number'])) {
            $data['reference_number'] = $this->generateReference($data['church_id'], $data['transaction_date']);
        }

        $transaction = Transaction::create($data);

        ActivityLog::record(
            $user,
            'created',
            'transactions',
            "Recorded {$transaction->currency} " . number_format($transaction->amount, 2) . " ({$transaction->transactionType?->name})",
            ['record_id' => $transaction->id, 'record_type' => 'Transaction']
        );

        return $transaction->load(['transactionType', 'recorder', 'member', 'church']);
    }

    // ---------------------------------------------------------------
    // Show
    // ---------------------------------------------------------------

    /**
     * Load a transaction with all relationships.
     */
    public function show(Transaction $transaction): Transaction
    {
        return $transaction->load(['church.zone', 'transactionType', 'recorder', 'member', 'verifier']);
    }

    // ---------------------------------------------------------------
    // Update
    // ---------------------------------------------------------------

    /**
     * Update a transaction.
     * Throws \DomainException(422) when a non-admin tries to edit a verified record.
     */
    public function update(User $user, Transaction $transaction, array $data): Transaction
    {
        if ($transaction->is_verified && ! $user->isMinistryAdmin()) {
            throw new \DomainException(
                'Verified transactions can only be edited by a Ministry Administrator.',
                422
            );
        }

        $old = $transaction->only(['amount', 'transaction_type_id', 'transaction_date', 'service_type', 'description']);

        $transaction->update($data);

        ActivityLog::record(
            $user,
            'updated',
            'transactions',
            "Updated transaction #{$transaction->id}",
            [
                'record_id'   => $transaction->id,
                'record_type' => 'Transaction',
                'old_values'  => $old,
                'new_values'  => $data,
            ]
        );

        return $transaction->fresh()->load(['transactionType', 'recorder', 'church']);
    }

    // ---------------------------------------------------------------
    // Delete
    // ---------------------------------------------------------------

    /**
     * Delete a transaction.
     * Throws \DomainException(422) when a non-admin tries to delete a verified record.
     */
    public function destroy(User $user, Transaction $transaction): void
    {
        if ($transaction->is_verified && ! $user->isMinistryAdmin()) {
            throw new \DomainException('Verified transactions cannot be deleted.', 422);
        }

        ActivityLog::record(
            $user,
            'deleted',
            'transactions',
            "Deleted transaction #{$transaction->id} ({$transaction->currency} " . number_format($transaction->amount, 2) . ")",
            ['record_id' => $transaction->id, 'record_type' => 'Transaction']
        );

        $transaction->delete();
    }

    // ---------------------------------------------------------------
    // Verify / Unverify
    // ---------------------------------------------------------------

    /**
     * Mark a transaction as verified.
     * Throws \DomainException(422) when it is already verified.
     */
    public function verify(User $user, Transaction $transaction): Transaction
    {
        if ($transaction->is_verified) {
            throw new \DomainException('Transaction is already verified.', 422);
        }

        $transaction->update([
            'is_verified' => true,
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        ActivityLog::record(
            $user,
            'verified',
            'transactions',
            "Verified transaction #{$transaction->id}",
            ['record_id' => $transaction->id, 'record_type' => 'Transaction']
        );

        return $transaction->fresh()->load(['transactionType', 'verifier', 'church']);
    }

    /**
     * Reverse verification on a transaction.
     */
    public function unverify(Transaction $transaction): Transaction
    {
        $transaction->update([
            'is_verified' => false,
            'verified_by' => null,
            'verified_at' => null,
        ]);

        return $transaction->fresh();
    }

    // ---------------------------------------------------------------
    // Authorization helper
    // ---------------------------------------------------------------

    /**
     * Whether the given user may access this transaction.
     */
    public function canAccess(User $user, Transaction $transaction): bool
    {
        if ($user->isMinistryAdmin()) {
            return true;
        }

        if ($user->isZoneAdmin()) {
            return $transaction->church?->zone_id === $user->zone_id;
        }

        return $transaction->church_id === $user->church_id;
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function generateReference(string $churchId, string $date): string
    {
        $church = Church::find($churchId);
        $code   = Str::upper(substr($church?->code ?? 'CHR', 0, 4));
        $d      = str_replace('-', '', $date);
        $rand   = strtoupper(Str::random(4));

        return "TXN-{$code}-{$d}-{$rand}";
    }
}
