<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Shared component — not owned by a single module.
 * Authors: Liong Ka Kien, Lee Kar How, Chia Yi Kuang, Kartik, Ng Yu Xun
 */

namespace Tests\Feature;

use App\Enums\AidProgramStatus;
use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Http\Middleware\ApplyInterfaceAgreement;
use App\Models\AidProgram;
use App\Models\Application;
use App\Models\User;
use App\Services\Integration\DisbursementSummaryClient;
use App\Services\Integration\EligibilityClient;
use App\Services\Integration\ProgrammeCatalogueClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Web service technologies: the Interface Agreement envelope, the two new
 * module exposures, and the module-to-module consumption clients.
 */
class WebServiceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['email' => 'ws-admin@test.local']);
        $user->role = UserRole::Admin;
        $user->nric = '900101145566';
        $user->save();

        return $user;
    }

    private function beneficiary(string $email = 'ws-ben@test.local'): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->role = UserRole::Beneficiary;
        $user->nric = '910202145566';
        $user->save();

        return $user;
    }

    private function programme(): AidProgram
    {
        return AidProgram::create([
            'title' => 'WS Programme',
            'slug' => 'ws-programme',
            'type' => 'cash_disbursement',
            'budget_allocated' => 50000,
            'budget_remaining' => 50000,
            'payout_amount' => 500,
            'income_threshold' => 5250,
            'status' => AidProgramStatus::Open,
        ]);
    }

    private function application(User $user): Application
    {
        $application = Application::create([
            'user_id' => $user->id,
            'aid_program_id' => $this->programme()->id,
            'status' => ApplicationStatus::UnderReview,
            'household_income' => 2800,
            'dependents_count' => 3,
            'state' => 'Selangor',
        ]);

        $application->forceFill([
            'eligibility_score' => 57,
            'verified_at' => now(),
            'eligibility_breakdown' => [
                'eligible' => true,
                'blended_score' => 57,
                'threshold' => 50,
                'recommendation' => 'approve',
                'flagged_for_review' => false,
                'strategies' => [
                    ['strategy' => 'b40_income', 'score' => 57, 'eligible' => true, 'reasons' => ['Income below threshold.']],
                ],
            ],
            'agency_verification' => ['status' => 'matched', 'checked_at' => now()->toIso8601String()],
        ])->save();

        return $application;
    }

    // -----------------------------------------------------------------
    // Interface Agreement envelope
    // -----------------------------------------------------------------

    public function test_a_successful_api_response_carries_the_agreed_envelope(): void
    {
        $user = $this->beneficiary();

        $response = $this->actingAs($user)->getJson('/api/programmes');

        $response->assertOk()
            ->assertJsonStructure(['status', 'timeStamp', 'requestID', 'data'])
            ->assertJsonPath('status', 'S');

        // timeStamp must follow the agreed format exactly.
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $response->json('timeStamp')
        );
    }

    public function test_a_failed_api_response_uses_the_same_envelope_with_status_f(): void
    {
        $this->getJson('/api/programmes')
            ->assertUnauthorized()
            ->assertJsonPath('status', 'F')
            ->assertJsonStructure(['status', 'timeStamp', 'requestID', 'data']);
    }

    public function test_a_caller_supplied_request_id_is_echoed_back(): void
    {
        $user = $this->beneficiary();

        $this->actingAs($user)
            ->withHeaders([ApplyInterfaceAgreement::REQUEST_ID_HEADER => 'consumer-abc-123'])
            ->getJson('/api/programmes')
            ->assertOk()
            ->assertJsonPath('requestID', 'consumer-abc-123');
    }

    public function test_a_malformed_request_id_is_replaced_rather_than_echoed(): void
    {
        $user = $this->beneficiary();

        // An injected value must never be reflected back into a response or a log.
        $this->actingAs($user)
            ->withHeaders([ApplyInterfaceAgreement::REQUEST_ID_HEADER => '<script>alert(1)</script>'])
            ->getJson('/api/programmes')
            ->assertOk()
            ->assertJsonMissing(['requestID' => '<script>alert(1)</script>']);
    }

    // -----------------------------------------------------------------
    // Module 3 exposure
    // -----------------------------------------------------------------

    public function test_module_three_publishes_the_eligibility_outcome(): void
    {
        $user = $this->beneficiary();
        $application = $this->application($user);

        $this->actingAs($user)
            ->getJson("/api/applications/{$application->reference}/eligibility")
            ->assertOk()
            ->assertJsonPath('status', 'S')
            ->assertJsonPath('data.blended_score', 57)
            ->assertJsonPath('data.recommendation', 'approve')
            ->assertJsonPath('data.strategies.0.strategy', 'b40_income');
    }

    public function test_one_beneficiary_cannot_read_anothers_eligibility(): void
    {
        $owner = $this->beneficiary('ws-owner@test.local');
        $intruder = $this->beneficiary('ws-intruder@test.local');
        $application = $this->application($owner);

        $this->actingAs($intruder)
            ->getJson("/api/applications/{$application->reference}/eligibility")
            ->assertForbidden()
            ->assertJsonPath('status', 'F');
    }

    // -----------------------------------------------------------------
    // Module 4 exposure
    // -----------------------------------------------------------------

    public function test_module_four_publishes_ledger_totals_to_administrators(): void
    {
        $this->actingAs($this->admin())
            ->getJson('/api/disbursements/summary')
            ->assertOk()
            ->assertJsonPath('status', 'S')
            ->assertJsonPath('data.currency', 'MYR')
            ->assertJsonStructure(['data' => ['settled_total', 'by_status']]);
    }

    public function test_a_beneficiary_cannot_read_ledger_totals(): void
    {
        $this->actingAs($this->beneficiary())
            ->getJson('/api/disbursements/summary')
            ->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Payment gateway webhook — the signed, accepted path
    // -----------------------------------------------------------------

    public function test_a_correctly_signed_webhook_is_accepted_and_advances_the_ledger(): void
    {
        config(['services.payment_gateway.webhook_secret' => 'test-secret']);

        $admin = $this->admin();
        $user = $this->beneficiary('ws-paid@test.local');
        $application = $this->application($user);
        $application->forceFill(['status' => ApplicationStatus::Approved])->save();

        $service = app(\App\Services\Disbursement\DisbursementService::class);
        $disbursement = $service->approve($service->createForApplication($application, $admin), $admin);

        $payload = [
            'idempotency_key' => 'evt_signed_1',
            'event_type' => 'payment.completed',
            'reference_code' => $disbursement->reference_code,
            'bank_reference' => 'BNK-TEST-1',
        ];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'test-secret');

        $this->call(
            'POST',
            '/webhooks/payments',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_AIDBRIDGE_SIGNATURE' => $signature],
            $body
        )
            ->assertOk()
            ->assertJsonPath('status', 'processed')
            ->assertJsonStructure(['status', 'timeStamp', 'requestID']);

        $this->assertSame(\App\Enums\DisbursementStatus::Disbursed, $disbursement->fresh()->status);
    }

    // -----------------------------------------------------------------
    // Module-to-module consumption
    // -----------------------------------------------------------------

    public function test_a_consumer_sends_the_mandatory_tracking_fields(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 'S', 'timeStamp' => '2026-09-02 10:00:00',
            'requestID' => 'x', 'data' => [],
        ], 200)]);

        app(ProgrammeCatalogueClient::class)->openProgrammes($this->admin());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'requestID=')
                && str_contains($request->url(), 'timeStamp=')
                && $request->hasHeader(ApplyInterfaceAgreement::REQUEST_ID_HEADER);
        });
    }

    public function test_a_consumer_unwraps_the_envelope(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 'S', 'timeStamp' => '2026-09-02 10:00:00', 'requestID' => 'abc',
            'data' => ['currency' => 'MYR', 'settled_total' => 1234.5],
        ], 200)]);

        $result = app(DisbursementSummaryClient::class)->summary($this->admin());

        $this->assertTrue($result->ok);
        $this->assertSame('S', $result->status);
        $this->assertSame('MYR', $result->data['currency']);
        $this->assertSame('Module 5 — Reporting & Monitoring', $result->sourceModule);
        $this->assertSame('Module 4 — Fund Allocation & Disbursement', $result->targetModule);
    }

    public function test_a_consumer_degrades_safely_when_the_provider_fails(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 'F', 'timeStamp' => '2026-09-02 10:00:00', 'requestID' => 'abc',
            'data' => ['message' => 'Unauthenticated.'],
        ], 401)]);

        $user = $this->beneficiary();
        $result = app(EligibilityClient::class)->forApplication($user, $this->application($user));

        // A failing provider must be reported, never thrown, so one module being
        // unreachable cannot take another module's screen down.
        $this->assertFalse($result->ok);
        $this->assertSame('F', $result->status);
        $this->assertSame('Unauthenticated.', $result->error);
    }

    public function test_the_short_lived_integration_token_is_revoked_after_the_call(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 'S', 'timeStamp' => '2026-09-02 10:00:00', 'requestID' => 'abc', 'data' => [],
        ], 200)]);

        $admin = $this->admin();
        app(ProgrammeCatalogueClient::class)->openProgrammes($admin);

        // No machine credential is left behind once the call completes.
        $this->assertSame(0, $admin->tokens()->where('name', 'module-integration')->count());
    }
}
