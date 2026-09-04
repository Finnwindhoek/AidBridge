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
use App\Enums\DisbursementStatus;
use App\Enums\UserRole;
use App\Models\AidProgram;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AidProgram\AidProgramFactory;
use App\Services\Application\ApplicationService;
use App\Services\Disbursement\DisbursementService;
use App\Services\Eligibility\EligibilityService;
use App\Services\Reporting\ApplicationReportBuilder;
use App\Services\Workflow\ApplicationWorkflowFacade;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Exercises the five design patterns through the real service layer.
 */
class AidWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['email' => 'admin@test.local']);
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    private function beneficiary(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['email' => 'ben'.uniqid().'@test.local']);
        $user->role = UserRole::Beneficiary;
        $user->nric = '900101145566';
        $user->save();

        return $user;
    }

    private function programme(string $type = 'cash_disbursement', array $overrides = []): AidProgram
    {
        return AidProgram::create(array_merge([
            'title' => 'Programme '.uniqid(),
            'slug' => 'programme-'.uniqid(),
            'type' => $type,
            'budget_allocated' => 100000,
            'budget_remaining' => 100000,
            'payout_amount' => 500,
            'income_threshold' => 5250,
            'status' => AidProgramStatus::Open,
        ], $overrides));
    }

    private function application(User $user, AidProgram $programme, array $overrides = []): Application
    {
        return Application::create(array_merge([
            'user_id' => $user->id,
            'aid_program_id' => $programme->id,
            'status' => ApplicationStatus::Draft,
            'household_income' => 3000,
            'dependents_count' => 2,
            'state' => 'Selangor',
        ], $overrides));
    }

    // -----------------------------------------------------------------
    // FACTORY PATTERN
    // -----------------------------------------------------------------

    public function test_factory_produces_the_correct_payout_per_programme_type(): void
    {
        $factory = app(AidProgramFactory::class);

        // Cash: base + RM50 per dependent, capped at 5 dependents.
        $this->assertSame(650.0, $factory->make('cash_disbursement')->calculatePayout(500, 3, 50));
        $this->assertSame(750.0, $factory->make('cash_disbursement')->calculatePayout(500, 9, 50));

        // Voucher: rounded down to a whole RM50 denomination, since a voucher
        // cannot be part-issued. RM180 therefore yields three RM50 vouchers.
        $this->assertSame(350.0, $factory->make('voucher')->calculatePayout(200, 3, 50));
        $this->assertSame(150.0, $factory->make('voucher')->calculatePayout(180, 0, 50));

        // Emergency grant: scaled 1.0x - 1.5x by eligibility score.
        $this->assertSame(1000.0, $factory->make('emergency_grant')->calculatePayout(1000, 0, 0));
        $this->assertSame(1500.0, $factory->make('emergency_grant')->calculatePayout(1000, 0, 100));
    }

    public function test_factory_rejects_an_unknown_programme_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(AidProgramFactory::class)->make('crypto_airdrop');
    }

    // -----------------------------------------------------------------
    // STRATEGY PATTERN
    // -----------------------------------------------------------------

    public function test_income_strategy_disqualifies_an_applicant_above_the_threshold(): void
    {
        $application = $this->application(
            $this->beneficiary(),
            $this->programme(),
            ['household_income' => 9000, 'dependents_count' => 0]
        );

        $breakdown = app(EligibilityService::class)->assess($application, callExternalRegistry: false);

        $this->assertFalse($breakdown['eligible']);
        $this->assertSame(0, $breakdown['blended_score']);
        $this->assertSame('reject', $breakdown['recommendation']);
    }

    public function test_dependents_raise_the_effective_income_threshold(): void
    {
        // RM 6,000 is over the RM 5,250 base, but 3 dependents lift the adjusted
        // threshold to RM 6,450, so this applicant still qualifies.
        $application = $this->application(
            $this->beneficiary(),
            $this->programme(),
            ['household_income' => 6000, 'dependents_count' => 3]
        );

        $breakdown = app(EligibilityService::class)->assess($application, callExternalRegistry: false);

        $this->assertTrue($breakdown['eligible']);
    }

    public function test_emergency_and_disability_strategies_apply_only_when_relevant(): void
    {
        $ordinary = $this->application($this->beneficiary(), $this->programme());
        $names = collect(app(EligibilityService::class)
            ->assess($ordinary, callExternalRegistry: false)['strategies'])
            ->pluck('strategy');

        $this->assertContains('b40_income', $names);
        $this->assertNotContains('emergency_relief', $names);
        $this->assertNotContains('disability_support', $names);

        // A disaster-affected applicant with a disability picks up both uplifts.
        $priority = $this->application(
            $this->beneficiary(['is_disabled' => true]),
            $this->programme('emergency_grant'),
            ['is_disaster_victim' => true]
        );

        $result = app(EligibilityService::class)->assess($priority, callExternalRegistry: false);
        $priorityNames = collect($result['strategies'])->pluck('strategy');

        $this->assertContains('emergency_relief', $priorityNames);
        $this->assertContains('disability_support', $priorityNames);
        $this->assertGreaterThan(50, $result['blended_score']);
    }

    // -----------------------------------------------------------------
    // OBSERVER PATTERN
    // -----------------------------------------------------------------

    public function test_observer_writes_an_audit_entry_on_every_status_change(): void
    {
        $application = $this->application($this->beneficiary(), $this->programme());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'application.created',
            'auditable_id' => $application->id,
        ]);

        $application->update(['status' => ApplicationStatus::Submitted, 'submitted_at' => now()]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'application.status_changed',
            'auditable_id' => $application->id,
        ]);
    }

    public function test_audit_payloads_redact_sensitive_values(): void
    {
        app(\App\Services\AuditLogger::class)->log('test.event', null, [
            'password' => 'hunter2',
            'nric' => '900101145566',
            'nested' => ['token' => 'secret-token', 'safe' => 'visible'],
        ]);

        $payload = AuditLog::where('action', 'test.event')->first()->payload;

        $this->assertSame('[redacted]', $payload['password']);
        $this->assertSame('[redacted]', $payload['nric']);
        $this->assertSame('[redacted]', $payload['nested']['token']);
        $this->assertSame('visible', $payload['nested']['safe']);
    }

    // -----------------------------------------------------------------
    // Module 2 — submission rules and upload security
    // -----------------------------------------------------------------

    public function test_submission_is_blocked_until_required_documents_are_present(): void
    {
        $application = $this->application($this->beneficiary(), $this->programme());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Please upload these documents first');

        app(ApplicationService::class)->submit($application);
    }

    public function test_a_beneficiary_cannot_apply_twice_to_one_programme(): void
    {
        $user = $this->beneficiary();
        $programme = $this->programme();

        $this->application($user, $programme);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already applied');

        app(ApplicationService::class)->create($user, $programme, [
            'household_income' => 2000,
            'dependents_count' => 1,
        ]);
    }

    public function test_a_disguised_php_upload_is_rejected(): void
    {
        Storage::fake('private');

        $user = $this->beneficiary();
        $application = $this->application($user, $this->programme());

        // A PHP web shell renamed to .pdf. A real file on disk is required here:
        // UploadedFile::fake() derives its MIME type from the filename, so it
        // could never exercise the content-sniffing rule this test is about.
        $path = tempnam(sys_get_temp_dir(), 'shell');
        file_put_contents($path, '<?php system($_GET["c"]); ?>');

        $malicious = new UploadedFile($path, 'invoice.pdf', 'application/pdf', null, true);

        $this->actingAs($user)
            ->post("/applications/{$application->reference}/documents", [
                'document_type' => 'income_proof',
                'file' => $malicious,
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('documents', 0);

        @unlink($path);
    }

    // -----------------------------------------------------------------
    // STATE + REPOSITORY PATTERNS
    // -----------------------------------------------------------------

    public function test_the_disbursement_lifecycle_runs_in_order_and_commits_budget(): void
    {
        $admin = $this->admin();
        $programme = $this->programme();
        $application = $this->application($this->beneficiary(), $programme);

        $application->forceFill([
            'status' => ApplicationStatus::Approved,
            'eligibility_score' => 70,
            'decided_at' => now(),
        ])->save();

        $service = app(DisbursementService::class);

        $disbursement = $service->createForApplication($application, $admin);
        $this->assertSame(DisbursementStatus::Pending, $disbursement->status);
        // 500 base + 2 dependents x 50.
        $this->assertSame('600.00', $disbursement->amount);

        // Budget is untouched until approval.
        $this->assertSame('100000.00', $programme->fresh()->budget_remaining);

        $disbursement = $service->approve($disbursement, $admin);
        $this->assertSame(DisbursementStatus::Approved, $disbursement->status);
        $this->assertSame('99400.00', $programme->fresh()->budget_remaining);

        // The lifecycle columns are excluded from $fillable, so these assertions
        // are what stop update() silently discarding the ledger's audit trail.
        // (Seeders run under Model::unguard(), which would otherwise hide this.)
        $this->assertNotNull($disbursement->approved_at);
        $this->assertSame($admin->id, $disbursement->approved_by);

        $disbursement = $service->markDisbursed($disbursement, $admin, 'BNK-1');
        $this->assertNotNull($disbursement->disbursed_at);
        $this->assertSame('BNK-1', $disbursement->bank_reference);

        $disbursement = $service->reconcile($disbursement, $admin);

        $this->assertSame(DisbursementStatus::Reconciled, $disbursement->status);
        $this->assertNotNull($disbursement->reconciled_at);
        $this->assertFalse($disbursement->state()->isActionable());
    }

    public function test_submission_and_decision_timestamps_are_persisted(): void
    {
        $admin = $this->admin();
        $user = $this->beneficiary();
        $application = $this->application($user, $this->programme());

        foreach (['nric', 'income_proof'] as $type) {
            $application->documents()->create([
                'document_type' => $type,
                'file_path' => "documents/{$application->id}/{$type}.pdf",
                'original_name' => "{$type}.pdf",
                'mime_type' => 'application/pdf',
                'size_bytes' => 2048,
            ]);
        }

        $service = app(ApplicationService::class);

        $application = $service->submit($application->refresh());
        $this->assertNotNull($application->submitted_at, 'submitted_at must survive the write');

        $application = $service->decide($application, $admin, approved: true, note: 'Approved on review.');
        $this->assertNotNull($application->decided_at, 'decided_at must survive the write');
        $this->assertSame($admin->id, $application->decided_by);
    }

    public function test_document_verification_is_persisted(): void
    {
        $admin = $this->admin();
        $application = $this->application($this->beneficiary(), $this->programme());

        $document = $application->documents()->create([
            'document_type' => 'nric',
            'file_path' => 'documents/1/nric.pdf',
            'original_name' => 'nric.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
        ]);

        $this->actingAs($admin)
            ->patch("/documents/{$document->id}/verify", ['decision' => 'verify'])
            ->assertRedirect();

        $document->refresh();

        $this->assertNotNull($document->verified_at, 'verified_at must survive the write');
        $this->assertSame($admin->id, $document->verified_by);
        $this->assertTrue($document->isVerified());
    }

    public function test_a_failed_payout_returns_its_committed_budget(): void
    {
        $admin = $this->admin();
        $programme = $this->programme();
        $application = $this->application($this->beneficiary(), $programme);
        $application->forceFill(['status' => ApplicationStatus::Approved])->save();

        $service = app(DisbursementService::class);
        $disbursement = $service->approve($service->createForApplication($application, $admin), $admin);

        $this->assertSame('99400.00', $programme->fresh()->budget_remaining);

        $service->fail($disbursement, 'Invalid bank account', $admin);

        $this->assertSame('100000.00', $programme->fresh()->budget_remaining);
    }

    public function test_budget_cannot_be_overdrawn(): void
    {
        $admin = $this->admin();
        $programme = $this->programme('cash_disbursement', [
            'budget_allocated' => 100,
            'budget_remaining' => 100,
        ]);

        $application = $this->application($this->beneficiary(), $programme);
        $application->forceFill(['status' => ApplicationStatus::Approved])->save();

        $service = app(DisbursementService::class);
        $disbursement = $service->createForApplication($application, $admin);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient remaining budget');

        $service->approve($disbursement, $admin);
    }

    public function test_a_retried_webhook_does_not_pay_twice(): void
    {
        $admin = $this->admin();
        $application = $this->application($this->beneficiary(), $this->programme());
        $application->forceFill(['status' => ApplicationStatus::Approved])->save();

        $service = app(DisbursementService::class);
        $disbursement = $service->approve($service->createForApplication($application, $admin), $admin);

        $first = $service->handleWebhook('evt_1', 'payment.completed', $disbursement->reference_code, []);
        $second = $service->handleWebhook('evt_1', 'payment.completed', $disbursement->reference_code, []);

        $this->assertSame('processed', $first['status']);
        $this->assertSame('duplicate', $second['status']);
        $this->assertSame(DisbursementStatus::Disbursed, $disbursement->fresh()->status);
        $this->assertDatabaseCount('webhook_receipts', 1);
    }

    public function test_the_payment_webhook_endpoint_rejects_a_bad_signature(): void
    {
        config(['services.payment_gateway.webhook_secret' => 'test-secret']);

        $this->postJson('/webhooks/payments', [
            'idempotency_key' => 'evt_bad',
            'event_type' => 'payment.completed',
            'reference_code' => 'AB-000',
        ], ['X-AidBridge-Signature' => 'not-the-right-hmac'])
            ->assertStatus(401);
    }

    // -----------------------------------------------------------------
    // SINGLETON PATTERN
    // -----------------------------------------------------------------

    public function test_the_request_context_cannot_be_instantiated_from_outside(): void
    {
        $constructor = (new \ReflectionClass(RequestContext::class))->getConstructor();

        $this->assertTrue($constructor->isPrivate(), 'A singleton must not expose its constructor.');
        $this->assertTrue(
            (new \ReflectionClass(RequestContext::class))->getMethod('__clone')->isPrivate(),
            'A singleton must not be cloneable.'
        );
    }

    public function test_the_request_context_is_one_shared_instance(): void
    {
        $first = RequestContext::getInstance();

        // Both the pattern's own accessor and the container hand back the same object.
        $this->assertSame($first, RequestContext::getInstance());
        $this->assertSame($first, app(RequestContext::class));
        $this->assertSame($first->correlationId(), app(RequestContext::class)->correlationId());

        // ...until the request ends, which is what reset() stands in for.
        RequestContext::reset();

        $this->assertNotSame($first, RequestContext::getInstance());
        $this->assertNotSame($first->correlationId(), RequestContext::getInstance()->correlationId());
    }

    public function test_every_audit_row_from_one_action_shares_a_correlation_id(): void
    {
        $admin = $this->admin();
        $application = $this->application($this->beneficiary(), $this->programme());
        $application->forceFill(['status' => ApplicationStatus::Submitted])->save();

        AuditLog::query()->delete();

        // One admin action fanning out across ApplicationService, the Observer and
        // DisbursementService — none of which know about each other.
        app(ApplicationWorkflowFacade::class)->close($application, $admin, approved: true);

        $correlationIds = AuditLog::pluck('correlation_id')->unique();

        $this->assertGreaterThan(1, AuditLog::count(), 'The close sequence should write several rows.');
        $this->assertCount(1, $correlationIds, 'All rows from one request must share one correlation ID.');
        $this->assertSame(RequestContext::getInstance()->correlationId(), $correlationIds->first());
    }

    // -----------------------------------------------------------------
    // FACADE PATTERN
    // -----------------------------------------------------------------

    public function test_the_facade_reviews_an_application_in_one_call(): void
    {
        $admin = $this->admin();
        $application = $this->application($this->beneficiary(), $this->programme());
        $application->forceFill(['status' => ApplicationStatus::Submitted])->save();

        $breakdown = app(ApplicationWorkflowFacade::class)->review($application, $admin);

        // The Strategy chain ran...
        $this->assertTrue($breakdown['eligible']);
        $this->assertNotNull($application->fresh()->eligibility_score);

        // ...and the status moved, from the single call.
        $this->assertSame(ApplicationStatus::UnderReview, $application->fresh()->status);
    }

    public function test_the_facade_tolerates_re_assessing_an_application_already_under_review(): void
    {
        $admin = $this->admin();
        $application = $this->application($this->beneficiary(), $this->programme());
        $application->forceFill(['status' => ApplicationStatus::Submitted])->save();

        $facade = app(ApplicationWorkflowFacade::class);
        $facade->review($application, $admin);

        // The second pass must not blow up on the "already under review" refusal.
        $breakdown = $facade->review($application->fresh(), $admin);

        $this->assertTrue($breakdown['eligible']);
        $this->assertSame(ApplicationStatus::UnderReview, $application->fresh()->status);
    }

    public function test_the_facade_closes_an_approval_and_raises_the_payout(): void
    {
        $admin = $this->admin();
        $programme = $this->programme();
        $application = $this->application($this->beneficiary(), $programme);

        $closure = app(ApplicationWorkflowFacade::class)->close($application, $admin, approved: true, note: 'Qualified.');

        $this->assertTrue($closure->approved);
        $this->assertSame(ApplicationStatus::Approved, $closure->application->status);
        $this->assertNotNull($closure->application->decided_at);

        // The payout was raised by the same call, sized by the Factory's formula.
        $this->assertNotNull($closure->disbursement);
        $this->assertSame(DisbursementStatus::Pending, $closure->disbursement->status);
        $this->assertSame('600.00', $closure->disbursement->amount);
        $this->assertFalse($closure->needsManualDisbursement());
    }

    public function test_the_facade_raises_no_payout_on_a_rejection(): void
    {
        $admin = $this->admin();
        $application = $this->application($this->beneficiary(), $this->programme());

        $closure = app(ApplicationWorkflowFacade::class)->close($application, $admin, approved: false, note: 'Over threshold.');

        $this->assertFalse($closure->approved);
        $this->assertSame(ApplicationStatus::Rejected, $closure->application->status);
        $this->assertNull($closure->disbursement);
        $this->assertFalse($closure->needsManualDisbursement());
        $this->assertDatabaseCount('disbursements', 0);
    }

    public function test_an_approval_survives_a_payout_that_cannot_be_raised(): void
    {
        $admin = $this->admin();

        // A misconfigured programme: nothing to pay out.
        $programme = $this->programme('cash_disbursement', ['payout_amount' => 0]);
        $application = $this->application($this->beneficiary(), $programme, ['dependents_count' => 0]);

        $closure = app(ApplicationWorkflowFacade::class)->close($application, $admin, approved: true);

        // The decision is the legally meaningful act, so it must not be rolled back
        // by an operational failure further down the sequence.
        $this->assertSame(ApplicationStatus::Approved, $application->fresh()->status);
        $this->assertTrue($closure->approved);
        $this->assertNull($closure->disbursement);
        $this->assertTrue($closure->needsManualDisbursement());
        $this->assertStringContainsString('payout is zero', (string) $closure->disbursementError);
        $this->assertStringContainsString('manually', $closure->summary());
    }

    public function test_the_admin_decision_endpoint_goes_through_the_facade(): void
    {
        $admin = $this->admin();
        $application = $this->application($this->beneficiary(), $this->programme());
        $application->forceFill(['status' => ApplicationStatus::UnderReview])->save();

        $this->actingAs($admin)
            ->post("/admin/applications/{$application->reference}/decide", [
                'decision' => 'approve',
                'note' => 'Approved on review.',
            ])
            ->assertRedirect();

        $this->assertSame(ApplicationStatus::Approved, $application->fresh()->status);
        $this->assertDatabaseCount('disbursements', 1);
    }

    // -----------------------------------------------------------------
    // BUILDER PATTERN
    // -----------------------------------------------------------------

    public function test_report_builder_chains_filters_into_one_query(): void
    {
        $programme = $this->programme();

        $this->application($this->beneficiary(), $programme, [
            'state' => 'Selangor', 'dependents_count' => 4, 'status' => ApplicationStatus::Approved,
        ]);
        $this->application($this->beneficiary(), $programme, [
            'state' => 'Johor', 'dependents_count' => 4, 'status' => ApplicationStatus::Approved,
        ]);
        $this->application($this->beneficiary(), $programme, [
            'state' => 'Selangor', 'dependents_count' => 1, 'status' => ApplicationStatus::Approved,
        ]);
        $this->application($this->beneficiary(), $programme, [
            'state' => 'Selangor', 'dependents_count' => 4, 'status' => ApplicationStatus::Rejected,
        ]);

        // "Approved applicants in Selangor with more than 3 dependents."
        $results = ApplicationReportBuilder::make()
            ->approved()
            ->inState('Selangor')
            ->withMinDependents(4)
            ->get();

        $this->assertCount(1, $results);

        $builder = ApplicationReportBuilder::make()->approved()->inState('Selangor');
        $this->assertSame(2, $builder->summary()['total']);
        $this->assertArrayHasKey('State', $builder->appliedFilters());
    }

    public function test_report_builder_ignores_an_unknown_sort_column(): void
    {
        // An injection attempt in the sort column falls back to the default.
        $sql = ApplicationReportBuilder::make()
            ->sortBy('id; DROP TABLE users; --')
            ->toQuery()
            ->toSql();

        $this->assertStringContainsString('order by "applications"."created_at"', $sql);
        $this->assertStringNotContainsString('DROP TABLE', $sql);
    }
}
