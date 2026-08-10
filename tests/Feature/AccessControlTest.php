<?php

namespace Tests\Feature;

use App\Enums\AidProgramStatus;
use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\AidProgram;
use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the RBAC middleware and the Policies actually hold, which is the
 * security core of the assignment.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->nric = '900101145566';
        $user->save();

        return $user;
    }

    private function beneficiary(string $email = 'ben@example.test'): User
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
            'title' => 'Test Cash Aid',
            'slug' => 'test-cash-aid',
            'type' => 'cash_disbursement',
            'budget_allocated' => 10000,
            'budget_remaining' => 10000,
            'payout_amount' => 500,
            'income_threshold' => 5250,
            'status' => AidProgramStatus::Open,
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/applications')->assertRedirect('/login');
        $this->get('/programmes')->assertRedirect('/login');
    }

    public function test_beneficiary_cannot_reach_admin_routes(): void
    {
        $this->actingAs($this->beneficiary());

        $this->get('/admin/review-queue')->assertForbidden();
        $this->get('/admin/disbursements')->assertForbidden();
        $this->get('/admin/reports')->assertForbidden();
        $this->get('/admin/audit-trail')->assertForbidden();
        $this->get('/admin/programmes/create')->assertForbidden();
    }

    public function test_admin_can_reach_admin_routes(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/review-queue')->assertOk();
        $this->get('/admin/disbursements')->assertOk();
        $this->get('/admin/programmes/create')->assertOk();
    }

    public function test_beneficiary_cannot_view_another_beneficiarys_application(): void
    {
        $owner = $this->beneficiary('owner@example.test');
        $intruder = $this->beneficiary('intruder@example.test');

        $application = Application::create([
            'user_id' => $owner->id,
            'aid_program_id' => $this->programme()->id,
            'status' => ApplicationStatus::Draft,
            'household_income' => 3000,
            'dependents_count' => 2,
            'state' => 'Selangor',
        ]);

        // The owner may read it.
        $this->actingAs($owner)->get("/applications/{$application->reference}")->assertOk();

        // A different beneficiary may not, even with the exact reference.
        $this->actingAs($intruder)->get("/applications/{$application->reference}")->assertForbidden();
    }

    public function test_application_index_is_scoped_to_the_owner(): void
    {
        $owner = $this->beneficiary('owner2@example.test');
        $other = $this->beneficiary('other2@example.test');
        $programme = $this->programme();

        $ownersApplication = Application::create([
            'user_id' => $owner->id, 'aid_program_id' => $programme->id,
            'status' => ApplicationStatus::Draft, 'household_income' => 3000,
            'dependents_count' => 1, 'state' => 'Johor',
        ]);

        $this->actingAs($other)
            ->get('/applications')
            ->assertOk()
            ->assertDontSee($ownersApplication->reference);
    }

    public function test_registration_cannot_escalate_to_admin(): void
    {
        // A crafted `role` field must be ignored by the controller.
        $this->post('/register', [
            'name' => 'Sneaky User',
            'email' => 'sneaky@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nric' => '900101-14-5566',
            'role' => 'admin',
        ]);

        $this->assertSame(UserRole::Beneficiary, User::where('email', 'sneaky@example.test')->first()->role);
    }

    public function test_nric_is_encrypted_at_rest_and_never_rendered_in_full(): void
    {
        $user = $this->beneficiary('crypt@example.test');

        // The raw column must not contain the plain value.
        $stored = \DB::table('users')->where('id', $user->id)->value('nric_encrypted');
        $this->assertNotSame('910202145566', $stored);
        $this->assertStringNotContainsString('910202145566', (string) $stored);

        // But it round-trips through the model.
        $this->assertSame('910202145566', $user->fresh()->nric);
        $this->assertSame('••••••••5566', $user->fresh()->masked_nric);
    }

    public function test_document_download_requires_a_valid_signature(): void
    {
        $owner = $this->beneficiary('doc@example.test');

        $application = Application::create([
            'user_id' => $owner->id, 'aid_program_id' => $this->programme()->id,
            'status' => ApplicationStatus::Draft, 'household_income' => 3000,
            'dependents_count' => 1, 'state' => 'Perak',
        ]);

        $document = $application->documents()->create([
            'document_type' => 'nric',
            'file_path' => 'documents/1/fake.pdf',
            'original_name' => 'nric.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
        ]);

        // Unsigned request is rejected before the controller is reached.
        $this->actingAs($owner)->get("/documents/{$document->id}/download")->assertForbidden();
    }
}
