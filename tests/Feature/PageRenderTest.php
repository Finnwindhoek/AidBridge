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
use App\Models\AidProgram;
use App\Models\Application;
use App\Models\User;
use App\Services\Disbursement\DisbursementService;
use App\Services\Workflow\ApplicationWorkflowFacade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests for the front end.
 *
 * Every screen is rendered with real data as the role that owns it. A Blade
 * change that breaks a component, a route helper or an undefined variable fails
 * here rather than in the browser.
 */
class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['email' => 'render-admin@test.local']);
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    private function beneficiary(): User
    {
        $user = User::factory()->create(['email' => 'render-ben@test.local', 'state' => 'Selangor']);
        $user->role = UserRole::Beneficiary;
        $user->nric = '900101145566';
        $user->save();

        return $user;
    }

    private function programme(): AidProgram
    {
        return AidProgram::create([
            'title' => 'Monthly B40 Food Subsidy',
            'slug' => 'monthly-b40-food-subsidy',
            'type' => 'cash_disbursement',
            'description' => 'Direct cash assistance for low-income households.',
            'budget_allocated' => 100000,
            'budget_remaining' => 100000,
            'payout_amount' => 500,
            'income_threshold' => 5250,
            'status' => AidProgramStatus::Open,
        ]);
    }

    public function test_guest_pages_render(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Sign in to your account');
        $this->get(route('register'))->assertOk()->assertSee('Create a beneficiary account');
    }

    public function test_every_admin_screen_renders(): void
    {
        $admin = $this->admin();
        $programme = $this->programme();
        $application = Application::create([
            'user_id' => $this->beneficiary()->id,
            'aid_program_id' => $programme->id,
            'status' => ApplicationStatus::Draft,
            'household_income' => 3000,
            'dependents_count' => 2,
            'state' => 'Selangor',
        ]);

        // Push it all the way through so the detail pages have something to show.
        $application->forceFill(['status' => ApplicationStatus::Submitted, 'submitted_at' => now()])->save();
        app(ApplicationWorkflowFacade::class)->review($application, $admin);
        $closure = app(ApplicationWorkflowFacade::class)->close($application->refresh(), $admin, approved: true);
        $disbursement = $closure->disbursement ?? app(DisbursementService::class)->createForApplication($application->refresh(), $admin);

        $this->actingAs($admin);

        // Route names rather than literal paths, so a prefix change cannot make
        // this test silently stop covering a screen.
        $pages = [
            // The dashboard is deliberately absent: DashboardMetricsService uses
            // MySQL's DATE_FORMAT and TIMESTAMPDIFF, which SQLite does not provide.
            // It is covered against MySQL instead — see the README.
            route('aid-programs.index'),
            route('aid-programs.create'),
            route('aid-programs.show', $programme),
            route('aid-programs.edit', $programme),
            route('applications.index'),
            route('applications.show', $application),
            route('eligibility.queue'),
            route('disbursements.index'),
            route('disbursements.show', $disbursement),
            route('reports.applications'),
            route('reports.audit'),
        ];

        foreach ($pages as $page) {
            $this->get($page)
                ->assertOk()
                // A Blade component that failed to resolve leaves its tag in the output.
                ->assertDontSee('<x-', false);
        }
    }

    public function test_every_beneficiary_screen_renders(): void
    {
        $user = $this->beneficiary();
        $programme = $this->programme();

        $application = Application::create([
            'user_id' => $user->id,
            'aid_program_id' => $programme->id,
            'status' => ApplicationStatus::Draft,
            'household_income' => 2500,
            'dependents_count' => 1,
            'state' => 'Selangor',
        ]);

        $this->actingAs($user);

        $pages = [
            route('applications.index'),
            route('applications.create'),
            route('applications.show', $application),
            route('applications.edit', $application),
            route('aid-programs.index'),
            route('aid-programs.show', $programme),
        ];

        foreach ($pages as $page) {
            $this->get($page)->assertOk()->assertDontSee('<x-', false);
        }
    }

    public function test_pagination_renders_as_bootstrap_not_tailwind(): void
    {
        $user = $this->beneficiary();
        $programme = $this->programme();

        // One page of results is 15, so 20 rows guarantees a paginator.
        for ($i = 0; $i < 20; $i++) {
            $extra = AidProgram::create([
                'title' => "Programme {$i}",
                'slug' => "programme-{$i}",
                'type' => 'cash_disbursement',
                'budget_allocated' => 1000,
                'budget_remaining' => 1000,
                'payout_amount' => 100,
                'income_threshold' => 5250,
                'status' => AidProgramStatus::Open,
            ]);
        }

        $response = $this->actingAs($user)->get(route('aid-programs.index'));

        $response->assertOk();
        $response->assertSee('pagination', false);
    }
}
