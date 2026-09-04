<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Shared component — not owned by a single module.
 * Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
 */

namespace Database\Seeders;

use App\Enums\AidProgramStatus;
use App\Enums\AidProgramType;
use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\AidProgram;
use App\Models\Application;
use App\Models\User;
use App\Services\Disbursement\DisbursementService;
use App\Services\Eligibility\EligibilityService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Builds a demonstrable dataset: three programmes covering all three Factory
 * types, and beneficiaries spread across the application lifecycle so every
 * screen has something to show.
 */
class DatabaseSeeder extends Seeder
{
    private const MALAYSIAN_STATES = [
        'Selangor', 'Johor', 'Kelantan', 'Sabah', 'Sarawak',
        'Pulau Pinang', 'Perak', 'Kuala Lumpur', 'Pahang', 'Kedah',
    ];

    public function run(): void
    {
        $admin = $this->createAdmin();
        $programmes = $this->createProgrammes($admin);
        $beneficiaries = $this->createBeneficiaries();

        $this->createApplications($programmes, $beneficiaries, $admin);

        $this->command->newLine();
        $this->command->info('AidBridge seed complete.');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Administrator', 'admin@aidbridge.test', 'password123'],
                ['Beneficiary', 'siti@example.test', 'password123'],
                ['Beneficiary', '(and 9 more)', 'password123'],
            ]
        );
    }

    private function createAdmin(): User
    {
        $admin = new User([
            'name' => 'Nurul Admin',
            'email' => 'admin@aidbridge.test',
            'password' => 'password123',
            'state' => 'Kuala Lumpur',
        ]);

        $admin->role = UserRole::Admin;
        $admin->nric = '850312105566';
        $admin->save();

        return $admin;
    }

    /** @return array<string, AidProgram> */
    private function createProgrammes(User $admin): array
    {
        $definitions = [
            'food' => [
                'title' => 'Monthly B40 Food Subsidy',
                'type' => AidProgramType::CashDisbursement,
                'description' => 'Recurring cash assistance towards essential food costs for B40 households.',
                'budget_allocated' => 250000,
                'payout_amount' => 500,
                'income_threshold' => 5250,
                'closes_at' => now()->addMonths(6),
            ],
            'school' => [
                'title' => 'Back-to-School Fund',
                'type' => AidProgramType::Voucher,
                'description' => 'Vouchers for uniforms, books and school supplies for families with school-age children.',
                'budget_allocated' => 120000,
                'payout_amount' => 200,
                'income_threshold' => 5000,
                'min_dependents' => 1,
                'closes_at' => now()->addMonths(3),
            ],
            'flood' => [
                'title' => 'Emergency Flood Relief 2026',
                'type' => AidProgramType::EmergencyGrant,
                'description' => 'One-off grant for households displaced by the declared monsoon flooding.',
                'budget_allocated' => 400000,
                'payout_amount' => 1000,
                'income_threshold' => 7000,
                'closes_at' => now()->addMonths(2),
            ],
        ];

        $programmes = [];

        foreach ($definitions as $key => $definition) {
            $programmes[$key] = AidProgram::create(array_merge($definition, [
                'type' => $definition['type']->value,
                'slug' => Str::slug($definition['title']),
                'budget_remaining' => $definition['budget_allocated'],
                'status' => AidProgramStatus::Open,
                'opens_at' => now()->subMonth(),
                'created_by' => $admin->id,
            ]));
        }

        return $programmes;
    }

    /** @return array<int, User> */
    private function createBeneficiaries(): array
    {
        $people = [
            ['Siti Nurhaliza binti Rahman', 'siti@example.test', 'Selangor', false],
            ['Ahmad Faizal bin Osman', 'ahmad@example.test', 'Kelantan', false],
            ['Lim Wei Ming', 'lim@example.test', 'Pulau Pinang', true],
            ['Kavitha a/p Subramaniam', 'kavitha@example.test', 'Johor', false],
            ['Mohd Hafiz bin Ismail', 'hafiz@example.test', 'Pahang', false],
            ['Rosnah binti Yusof', 'rosnah@example.test', 'Sabah', true],
            ['Tan Chee Keong', 'tan@example.test', 'Kuala Lumpur', false],
            ['Nurul Ain binti Karim', 'nurul@example.test', 'Perak', false],
            ['Anak Jimmy anak Belaja', 'jimmy@example.test', 'Sarawak', false],
            ['Fatimah binti Zakaria', 'fatimah@example.test', 'Kedah', false],
        ];

        $users = [];

        foreach ($people as $index => [$name, $email, $state, $isDisabled]) {
            $user = new User([
                'name' => $name,
                'email' => $email,
                'password' => 'password123',
                'state' => $state,
                'phone' => '01'.random_int(1, 9).'-'.random_int(1000000, 9999999),
                'is_disabled' => $isDisabled,
            ]);

            $user->role = UserRole::Beneficiary;
            // Synthetic NRICs; stored encrypted by the model mutator.
            $user->nric = sprintf('%06d%02d%04d', random_int(700101, 991231), random_int(1, 14), random_int(1000, 9999));
            $user->save();

            $users[] = $user;
        }

        return $users;
    }

    /**
     * Walks a spread of applications through the real services, so the seeded data
     * exercises the Observer, Strategy, State and Repository code paths rather than
     * being written straight to the tables.
     */
    private function createApplications(array $programmes, array $beneficiaries, User $admin): void
    {
        $eligibility = app(EligibilityService::class);
        $disbursements = app(DisbursementService::class);

        // Spread across programmes and lifecycle stages.
        $plan = [
            // [beneficiary index, programme key, income, dependents, disaster, target stage]
            [0, 'food',   2800, 3, false, 'reconciled'],
            [1, 'food',   3400, 4, false, 'disbursed'],
            [2, 'food',   4100, 2, false, 'approved'],
            [3, 'school', 3200, 3, false, 'reconciled'],
            [4, 'school', 4800, 2, false, 'under_review'],
            [5, 'flood',  3900, 5, true,  'disbursed'],
            [6, 'flood',  5600, 1, true,  'under_review'],
            [7, 'food',   6200, 1, false, 'rejected'],
            [8, 'flood',  2400, 4, true,  'approved'],
            [9, 'school', 3600, 2, false, 'submitted'],
        ];

        foreach ($plan as [$userIndex, $programmeKey, $income, $dependents, $disaster, $stage]) {
            $user = $beneficiaries[$userIndex];
            $programme = $programmes[$programmeKey];

            $application = Application::create([
                'user_id' => $user->id,
                'aid_program_id' => $programme->id,
                'status' => ApplicationStatus::Draft,
                'household_income' => $income,
                'dependents_count' => $dependents,
                'state' => $user->state,
                'is_disaster_victim' => $disaster,
            ]);

            // Backdate so the dashboard trend chart has a spread of months.
            $submittedAt = now()->subDays(random_int(5, 120));

            $application->forceFill([
                'status' => ApplicationStatus::Submitted,
                'submitted_at' => $submittedAt,
                'created_at' => $submittedAt,
            ])->save();

            $this->seedDocuments($application);

            if ($stage === 'submitted') {
                continue;
            }

            // Runs the real Strategy chain. The external registry is skipped so the
            // seed does not depend on network access.
            $eligibility->assess($application, callExternalRegistry: false);

            $application->forceFill(['status' => ApplicationStatus::UnderReview])->save();

            if ($stage === 'under_review') {
                continue;
            }

            if ($stage === 'rejected') {
                $application->forceFill([
                    'status' => ApplicationStatus::Rejected,
                    'decided_at' => $submittedAt->copy()->addDays(3),
                    'decided_by' => $admin->id,
                    'notes' => 'Household income exceeds the B40 threshold for this programme.',
                ])->save();

                continue;
            }

            $application->forceFill([
                'status' => ApplicationStatus::Approved,
                'decided_at' => $submittedAt->copy()->addDays(random_int(2, 8)),
                'decided_by' => $admin->id,
            ])->save();

            // Money movement goes through the real State machine.
            $disbursement = $disbursements->createForApplication($application->refresh(), $admin);
            $disbursement = $disbursements->approve($disbursement, $admin);

            if ($stage === 'approved') {
                continue;
            }

            $disbursement = $disbursements->markDisbursed(
                $disbursement,
                $admin,
                'BNK'.strtoupper(Str::random(10))
            );

            // Backdate the payment so the velocity metric is meaningful.
            $disbursement->forceFill([
                'disbursed_at' => $application->decided_at->copy()->addDays(random_int(1, 5)),
            ])->save();

            if ($stage === 'disbursed') {
                continue;
            }

            $disbursements->reconcile($disbursement, $admin);
        }
    }

    /**
     * Writes document rows without real files. The download route will 404 for
     * these, which is correct: the record exists, the file does not.
     */
    private function seedDocuments(Application $application): void
    {
        $types = ['nric', 'income_proof'];

        if ($application->aidProgram->type === AidProgramType::Voucher) {
            $types[] = 'household_proof';
        }

        if ($application->user->is_disabled) {
            $types[] = 'disability_cert';
        }

        foreach ($types as $type) {
            $application->documents()->create([
                'document_type' => $type,
                'file_path' => "documents/{$application->id}/seed-".Str::uuid().'.pdf',
                'original_name' => $type.'.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => random_int(80_000, 900_000),
                'checksum' => hash('sha256', $application->id.$type),
                'verified_at' => now(),
            ]);
        }
    }
}
