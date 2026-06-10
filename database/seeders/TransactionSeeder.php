<?php

namespace Database\Seeders;

use App\Models\Church;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TransactionSeeder extends Seeder
{
    /**
     * Seed realistic transactions for every active church.
     *
     * Strategy:
     *   - Weekly Sunday offerings for the past 52 weeks (every church)
     *   - Monthly tithes from a random subset of members
     *   - Periodic special transactions (donations, building fund, welfare)
     *   - ~30% of transactions are member-linked; rest are anonymous
     *   - ~80% are verified; the remainder stay unverified (mimics real backlog)
     */
    public function run(): void
    {
        $churches      = Church::where('is_active', true)->with('zone')->get();
        $transTypes    = TransactionType::where('is_active', true)->get()->keyBy('code');
        $recorderCache = []; // church_id → User

        if ($churches->isEmpty()) {
            $this->command->warn('No active churches — run MinistrySeeder first.');
            return;
        }

        if ($transTypes->isEmpty()) {
            $this->command->warn('No transaction types — run TransactionTypeSeeder first.');
            return;
        }

        // Pre-fetch church admins to use as recorders
        $allUsers = User::all()->groupBy('church_id');

        $total      = 0;
        $refCounter = 1;

        foreach ($churches as $church) {
            $members = Member::where('church_id', $church->id)
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            // Fallback recorder: pick any user scoped to this church, else any user
            $recorder = $allUsers->get($church->id)?->first()
                ?? User::first();

            if (! $recorder) {
                $this->command->warn("No users found — run DefaultUsersSeeder first.");
                return;
            }

            // ------------------------------------------------------------------
            // 1. Weekly Sunday offerings — last 52 Sundays
            // ------------------------------------------------------------------
            $sundayType = $transTypes->get('SUNDAY-OFF');

            for ($week = 0; $week < 52; $week++) {
                $sunday = now()->startOfWeek()->subWeeks($week)->next('Sunday');

                // Skip future dates
                if ($sunday->isFuture()) {
                    continue;
                }

                $amount = $this->randomAmount(5000, 45000); // KES

                $this->createTransaction([
                    'church_id'            => $church->id,
                    'transaction_type_id'  => $sundayType->id,
                    'recorded_by'          => $recorder->id,
                    'member_id'            => null,
                    'amount'               => $amount,
                    'transaction_date'     => $sunday->toDateString(),
                    'service_type'         => 'sunday_morning',
                    'reference_number'     => $this->ref($refCounter++),
                    'description'          => 'Sunday morning service offering',
                    'is_verified'          => (rand(1, 100) <= 85),
                    'verified_by'          => $recorder->id,
                ]);

                $total++;
            }

            // ------------------------------------------------------------------
            // 2. Monthly tithes — last 12 months, random members
            // ------------------------------------------------------------------
            $titheType = $transTypes->get('TITHE');

            for ($month = 0; $month < 12; $month++) {
                $monthDate = now()->startOfMonth()->subMonths($month);

                // 30–60% of members tithe each month
                $tithingMembers = $members
                    ? array_slice(
                        $this->shuffle($members),
                        0,
                        (int) ceil(count($members) * (rand(30, 60) / 100))
                    )
                    : [];

                foreach ($tithingMembers as $memberId) {
                    $titheAmount = $this->randomAmount(500, 8000);

                    $this->createTransaction([
                        'church_id'           => $church->id,
                        'transaction_type_id' => $titheType->id,
                        'recorded_by'         => $recorder->id,
                        'member_id'           => $memberId,
                        'amount'              => $titheAmount,
                        'transaction_date'    => $monthDate->copy()->addDays(rand(0, 27))->toDateString(),
                        'service_type'        => 'sunday_morning',
                        'reference_number'    => $this->ref($refCounter++),
                        'description'         => 'Monthly tithe',
                        'is_verified'         => (rand(1, 100) <= 80),
                        'verified_by'         => $recorder->id,
                    ]);

                    $total++;
                }
            }

            // ------------------------------------------------------------------
            // 3. Periodic special transactions — 6 months of variety
            // ------------------------------------------------------------------
            $specialTypes = [
                'MPESA-DON'    => ['min' => 500,   'max' => 15000,  'service' => null,           'desc' => 'M-Pesa donation received'],
                'CASH-DON'     => ['min' => 1000,  'max' => 50000,  'service' => null,           'desc' => 'Cash donation'],
                'BLDG-FUND'    => ['min' => 5000,  'max' => 100000, 'service' => 'sunday_morning','desc' => 'Building fund contribution'],
                'WELFARE'      => ['min' => 2000,  'max' => 20000,  'service' => 'sunday_morning','desc' => 'Welfare / benevolence collection'],
                'PLEDGE'       => ['min' => 1000,  'max' => 30000,  'service' => null,           'desc' => 'Pledge fulfilment'],
                'THANK-OFF'    => ['min' => 1000,  'max' => 25000,  'service' => 'sunday_morning','desc' => 'Thanksgiving offering'],
                'HARV-OFF'     => ['min' => 3000,  'max' => 60000,  'service' => 'special',      'desc' => 'Harvesting offering'],
                'PROJ-CONTRIB' => ['min' => 2000,  'max' => 40000,  'service' => 'special',      'desc' => 'Project contribution'],
            ];

            for ($month = 0; $month < 6; $month++) {
                $monthDate = now()->startOfMonth()->subMonths($month);

                // 2–5 special transactions per month
                $numSpecial = rand(2, 5);
                $typeCodes  = array_keys($specialTypes);

                for ($s = 0; $s < $numSpecial; $s++) {
                    $code   = $typeCodes[array_rand($typeCodes)];
                    $config = $specialTypes[$code];
                    $type   = $transTypes->get($code);

                    if (! $type) {
                        continue;
                    }

                    $memberId = (! empty($members) && rand(1, 100) <= 40)
                        ? $members[array_rand($members)]
                        : null;

                    $this->createTransaction([
                        'church_id'           => $church->id,
                        'transaction_type_id' => $type->id,
                        'recorded_by'         => $recorder->id,
                        'member_id'           => $memberId,
                        'amount'              => $this->randomAmount($config['min'], $config['max']),
                        'transaction_date'    => $monthDate->copy()->addDays(rand(0, 27))->toDateString(),
                        'service_type'        => $config['service'],
                        'reference_number'    => $this->ref($refCounter++),
                        'description'         => $config['desc'],
                        'is_verified'         => (rand(1, 100) <= 75),
                        'verified_by'         => $recorder->id,
                    ]);

                    $total++;
                }
            }

            // ------------------------------------------------------------------
            // 4. Midweek / Wednesday offerings — last 24 weeks
            // ------------------------------------------------------------------
            $midweekType = $transTypes->get('MID-OFF');

            for ($week = 0; $week < 24; $week++) {
                $wednesday = now()->startOfWeek()->subWeeks($week)->next('Wednesday');

                if ($wednesday->isFuture()) {
                    continue;
                }

                $this->createTransaction([
                    'church_id'           => $church->id,
                    'transaction_type_id' => $midweekType->id,
                    'recorded_by'         => $recorder->id,
                    'member_id'           => null,
                    'amount'              => $this->randomAmount(1500, 12000),
                    'transaction_date'    => $wednesday->toDateString(),
                    'service_type'        => 'wednesday',
                    'reference_number'    => $this->ref($refCounter++),
                    'description'         => 'Midweek service offering',
                    'is_verified'         => (rand(1, 100) <= 70),
                    'verified_by'         => $recorder->id,
                ]);

                $total++;
            }
        }

        $this->command->info("✓ {$total} transactions seeded across {$churches->count()} churches.");
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Create a transaction, silently skipping duplicate reference numbers. */
    private function createTransaction(array $data): void
    {
        // Only set verified_by / verified_at when actually verified
        $isVerified  = $data['is_verified'];
        $verifiedBy  = $isVerified ? ($data['verified_by'] ?? null) : null;
        $verifiedAt  = $isVerified ? now()->subDays(rand(0, 3)) : null;

        Transaction::firstOrCreate(
            ['reference_number' => $data['reference_number']],
            [
                'church_id'           => $data['church_id'],
                'transaction_type_id' => $data['transaction_type_id'],
                'recorded_by'         => $data['recorded_by'],
                'member_id'           => $data['member_id'] ?? null,
                'amount'              => $data['amount'],
                'currency'            => 'KES',
                'transaction_date'    => $data['transaction_date'],
                'service_type'        => $data['service_type'] ?? null,
                'description'         => $data['description'] ?? null,
                'is_verified'         => $isVerified,
                'verified_by'         => $verifiedBy,
                'verified_at'         => $verifiedAt,
            ]
        );
    }

    /** Generate a padded reference number. */
    private function ref(int $counter): string
    {
        return 'TXN-' . str_pad($counter, 7, '0', STR_PAD_LEFT);
    }

    /** Random decimal amount rounded to nearest 50. */
    private function randomAmount(int $min, int $max): float
    {
        $raw = rand($min, $max);
        return round($raw / 50) * 50;
    }

    /** Shuffle an array and return it (preserves values, new keys). */
    private function shuffle(array $arr): array
    {
        shuffle($arr);
        return $arr;
    }
}
