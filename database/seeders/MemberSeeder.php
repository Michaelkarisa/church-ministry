<?php

namespace Database\Seeders;

use App\Models\Church;
use App\Models\Member;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MemberSeeder extends Seeder
{
    /**
     * Seed members for every active church.
     *
     * Each church gets between 15-25 members with realistic Kenyan names,
     * varied demographics, and staggered membership dates (2018–2024).
     */
    public function run(): void
    {
        $churches = Church::where('is_active', true)->get();

        if ($churches->isEmpty()) {
            $this->command->warn('No active churches found — run MinistrySeeder first.');
            return;
        }

        // ---------------------------------------------------------------
        // Kenyan name pools
        // ---------------------------------------------------------------
        $maleFirstNames = [
            'John', 'James', 'Peter', 'Paul', 'David', 'Samuel', 'Daniel',
            'Joseph', 'Moses', 'Elijah', 'Michael', 'Philip', 'Stephen',
            'George', 'Simon', 'Andrew', 'Thomas', 'Mark', 'Luke', 'Nathan',
        ];

        $femaleFirstNames = [
            'Mary', 'Grace', 'Faith', 'Hope', 'Joy', 'Ruth', 'Esther',
            'Lydia', 'Deborah', 'Hannah', 'Sarah', 'Miriam', 'Naomi',
            'Elizabeth', 'Agnes', 'Rose', 'Anne', 'Lucy', 'Alice', 'Gladys',
        ];

        $lastNames = [
            'Kamau', 'Wanjiku', 'Mwangi', 'Ouma', 'Otieno', 'Achieng',
            'Kipchoge', 'Chebet', 'Mutua', 'Muthoni', 'Njoroge', 'Kariuki',
            'Odhiambo', 'Onyango', 'Ndegwa', 'Waweru', 'Gitau', 'Kimani',
            'Njenga', 'Gacheru', 'Mugo', 'Mburu', 'Wainaina', 'Kinyua',
            'Auma', 'Adhiambo', 'Maina', 'Gitahi', 'Kihara', 'Wangari',
        ];

        $occupations = [
            'Teacher', 'Nurse', 'Business Person', 'Farmer', 'Engineer',
            'Driver', 'Mechanic', 'Accountant', 'Lawyer', 'Doctor',
            'Social Worker', 'Pastor', 'Student', 'Entrepreneur', 'Clerk',
            'Security Officer', 'IT Professional', 'Tailor', 'Chef', 'Carpenter',
        ];

        $maritalStatuses = ['single', 'married', 'widowed', 'divorced'];
        $maritalWeights  = [40, 50, 5, 5]; // % probabilities

        $serviceCounters = []; // track member_number per church code

        $total = 0;

        foreach ($churches as $church) {
            $churchCode = $church->code;
            $memberCount = rand(15, 25);
            $serviceCounters[$churchCode] = 1;

            for ($i = 0; $i < $memberCount; $i++) {
                $gender = (rand(1, 100) <= 55) ? 'female' : 'male'; // slight female majority
                $firstName = ($gender === 'male')
                    ? $maleFirstNames[array_rand($maleFirstNames)]
                    : $femaleFirstNames[array_rand($femaleFirstNames)];
                $lastName = $lastNames[array_rand($lastNames)];

                // Weighted marital status
                $maritalStatus = $this->weightedRandom($maritalStatuses, $maritalWeights);

                // Age range: 18–70
                $age = rand(18, 70);
                $dob = now()->subYears($age)->subDays(rand(0, 364));

                // Membership date between 2018 and 2024
                $membershipDate = now()
                    ->subYears(rand(0, 6))
                    ->subMonths(rand(0, 11))
                    ->startOfMonth();

                $seqNumber = str_pad($serviceCounters[$churchCode]++, 4, '0', STR_PAD_LEFT);
                $memberNumber = strtoupper(Str::slug($churchCode, '')) . '-' . $seqNumber;

                Member::firstOrCreate(
                    ['member_number' => $memberNumber],
                    [
                        'church_id'       => $church->id,
                        'first_name'      => $firstName,
                        'last_name'       => $lastName,
                        'email'           => strtolower("{$firstName}.{$lastName}" . rand(10, 99) . '@example.com'),
                        'phone'           => '+2547' . rand(10000000, 99999999),
                        'date_of_birth'   => $dob->toDateString(),
                        'gender'          => $gender,
                        'marital_status'  => $maritalStatus,
                        'address'         => $church->location ?? 'Kenya',
                        'occupation'      => $occupations[array_rand($occupations)],
                        'membership_date' => $membershipDate->toDateString(),
                        'is_active'       => (rand(1, 100) > 5), // 95% active
                    ]
                );

                $total++;
            }
        }

        $this->command->info("✓ {$total} members seeded across {$churches->count()} churches.");
    }

    /**
     * Pick a random value from an array using percentage weights.
     *
     * @param  array<string>   $values
     * @param  array<int>      $weights  Must sum to 100
     */
    private function weightedRandom(array $values, array $weights): string
    {
        $rand = rand(1, 100);
        $cumulative = 0;
        foreach ($values as $i => $value) {
            $cumulative += $weights[$i];
            if ($rand <= $cumulative) {
                return $value;
            }
        }
        return $values[array_key_last($values)];
    }
}
