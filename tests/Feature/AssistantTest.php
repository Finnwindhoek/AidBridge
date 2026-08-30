<?php

namespace Tests\Feature;

use App\Enums\AidProgramStatus;
use App\Enums\ApplicationStatus;
use App\Enums\DisbursementStatus;
use App\Enums\UserRole;
use App\Models\AidProgram;
use App\Models\Application;
use App\Models\User;
use App\Services\Chatbot\ChatbotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The help assistant — intent routing, personalised answers, and the tenancy
 * rule that matters most: an answer may only ever be built from the asker's own
 * records.
 */
class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private function beneficiary(string $email): User
    {
        $user = User::factory()->create(['email' => $email, 'state' => 'Selangor']);
        $user->role = UserRole::Beneficiary;
        $user->nric = '900101145566';
        $user->save();

        return $user;
    }

    private function programme(string $title = 'Monthly B40 Food Subsidy'): AidProgram
    {
        return AidProgram::create([
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'type' => 'cash_disbursement',
            'budget_allocated' => 100000,
            'budget_remaining' => 100000,
            'payout_amount' => 500,
            'income_threshold' => 5250,
            'status' => AidProgramStatus::Open,
        ]);
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

    private function ask(string $question, User $user): array
    {
        return app(ChatbotService::class)->ask($question, $user)->toArray();
    }

    // -----------------------------------------------------------------
    // Intent routing
    // -----------------------------------------------------------------

    public static function questionProvider(): array
    {
        return [
            'status'      => ['What is my application status?', 'application_status'],
            'phrased'     => ['has my application been approved yet', 'application_status'],
            'payment'     => ['Where is my payment?', 'payment_status'],
            'money slang' => ['when will i receive the money', 'payment_status'],
            'documents'   => ['What documents do I need?', 'required_documents'],
            'eligibility' => ['am i eligible for this', 'eligibility'],
            'rejection'   => ['why was i rejected', 'eligibility'],
            'apply'       => ['How do I apply?', 'how_to_apply'],
            'timing'      => ['how long does it take', 'processing_time'],
            'privacy'     => ['is my data secure', 'privacy_security'],
            'programmes'  => ['what programmes are available', 'programme_list'],
        ];
    }

    /** @dataProvider questionProvider */
    public function test_questions_route_to_the_expected_intent(string $question, string $expected): void
    {
        $user = $this->beneficiary('router@test.local');
        $this->application($user, $this->programme());

        $this->assertSame($expected, $this->ask($question, $user)['intent']);
    }

    public function test_an_unrecognised_question_falls_back_rather_than_guessing(): void
    {
        $user = $this->beneficiary('fallback@test.local');

        $reply = $this->ask('what is the weather in kuala lumpur tomorrow', $user);

        $this->assertSame('fallback', $reply['intent']);
        $this->assertStringContainsString('did not understand', $reply['message']);
        $this->assertNotEmpty($reply['suggestions'], 'The fallback must offer what it can answer.');
    }

    public function test_an_empty_question_falls_back(): void
    {
        $user = $this->beneficiary('empty@test.local');

        $this->assertSame('fallback', $this->ask('   ', $user)['intent']);
    }

    // -----------------------------------------------------------------
    // Answers are drawn from the asker's real records
    // -----------------------------------------------------------------

    public function test_status_answer_reflects_the_real_application_state(): void
    {
        $user = $this->beneficiary('status@test.local');
        $programme = $this->programme('Back-to-School Fund');
        $application = $this->application($user, $programme);

        $draft = $this->ask('what is my application status', $user);
        $this->assertStringContainsString('draft', $draft['message']);
        $this->assertStringContainsString('Back-to-School Fund', $draft['message']);

        $application->forceFill([
            'status' => ApplicationStatus::Approved,
            'decided_at' => now(),
        ])->save();

        $approved = $this->ask('what is my application status', $user);
        $this->assertStringContainsString('approved', $approved['message']);
        $this->assertNotNull($approved['link'], 'The reply should deep-link to the application.');
    }

    public function test_payment_answer_reports_the_ledger_record(): void
    {
        $user = $this->beneficiary('pay@test.local');
        $application = $this->application($user, $this->programme(), [
            'status' => ApplicationStatus::Approved,
        ]);

        $disbursement = $application->disbursement()->create([
            'amount' => 600,
            'status' => DisbursementStatus::Approved,
            'payment_channel' => 'bank_transfer',
        ]);

        $reply = $this->ask('where is my payment', $user);

        $this->assertStringContainsString('600.00', $reply['message']);
        $this->assertStringContainsString('queued with the bank', $reply['message']);
        $this->assertStringContainsString($disbursement->refresh()->reference_code, $reply['message']);
    }

    public function test_document_answer_lists_what_is_still_missing(): void
    {
        $user = $this->beneficiary('docs@test.local');
        $application = $this->application($user, $this->programme());

        $application->documents()->create([
            'document_type' => 'nric',
            'file_path' => 'documents/1/nric.pdf',
            'original_name' => 'nric.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
        ]);

        $reply = $this->ask('what documents do i need', $user);

        // NRIC is uploaded, income proof is not.
        $this->assertStringContainsString('still need to upload', $reply['message']);
        $this->assertStringContainsString('income proof', $reply['message']);
    }

    public function test_a_user_with_no_application_is_pointed_at_the_programmes(): void
    {
        $user = $this->beneficiary('new@test.local');

        $reply = $this->ask('what is my application status', $user);

        $this->assertStringContainsString('not applied for anything yet', $reply['message']);
    }

    // -----------------------------------------------------------------
    // Tenancy: the answer must never come from someone else's record
    // -----------------------------------------------------------------

    public function test_one_beneficiary_never_sees_another_beneficiarys_case(): void
    {
        $siti = $this->beneficiary('siti@test.local');
        $ahmad = $this->beneficiary('ahmad@test.local');

        $this->application($siti, $this->programme('Siti Secret Programme'), [
            'household_income' => 1234,
            'status' => ApplicationStatus::Approved,
        ]);

        // Ahmad has nothing of his own and asks about "my application".
        $reply = $this->ask('what is my application status', $ahmad);

        $this->assertStringNotContainsString('Siti Secret Programme', $reply['message']);
        $this->assertStringNotContainsString('1234', $reply['message']);
        $this->assertStringContainsString('not applied for anything yet', $reply['message']);
    }

    // -----------------------------------------------------------------
    // HTTP endpoint
    // -----------------------------------------------------------------

    public function test_the_endpoint_answers_a_signed_in_beneficiary(): void
    {
        $user = $this->beneficiary('http@test.local');
        $this->application($user, $this->programme());

        $this->actingAs($user)
            ->postJson(route('assistant.ask'), ['question' => 'what documents do i need'])
            ->assertOk()
            ->assertJsonStructure(['message', 'suggestions', 'link', 'intent'])
            ->assertJsonPath('intent', 'required_documents');
    }

    public function test_the_endpoint_rejects_a_guest(): void
    {
        $this->postJson(route('assistant.ask'), ['question' => 'hello'])
            ->assertUnauthorized();
    }

    public function test_the_endpoint_validates_the_question(): void
    {
        $user = $this->beneficiary('validate@test.local');

        $this->actingAs($user)
            ->postJson(route('assistant.ask'), ['question' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('question');

        $this->actingAs($user)
            ->postJson(route('assistant.ask'), ['question' => str_repeat('a', 400)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('question');
    }

    public function test_the_widget_is_offered_to_beneficiaries_but_not_admins(): void
    {
        $beneficiary = $this->beneficiary('widget@test.local');

        $admin = User::factory()->create(['email' => 'widget-admin@test.local']);
        $admin->role = UserRole::Admin;
        $admin->save();

        $this->actingAs($beneficiary)
            ->get(route('applications.index'))
            ->assertOk()
            ->assertSee('AidBridge Assistant');

        $this->actingAs($admin)
            ->get(route('applications.index'))
            ->assertOk()
            ->assertDontSee('AidBridge Assistant');
    }
}
