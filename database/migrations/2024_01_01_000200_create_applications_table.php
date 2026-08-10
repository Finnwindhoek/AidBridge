<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();

            // Public-facing identifier. Primary keys are never exposed in URLs or
            // exports, per the security checklist.
            $table->uuid('reference')->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aid_program_id')->constrained()->cascadeOnDelete();

            $table->enum('status', [
                'draft',
                'submitted',
                'under_review',
                'approved',
                'rejected',
                'withdrawn',
            ])->default('draft')->index();

            $table->decimal('household_income', 12, 2)->default(0);
            $table->unsignedTinyInteger('dependents_count')->default(0);
            $table->string('state', 60)->nullable();
            $table->boolean('is_disaster_victim')->default(false);
            $table->text('notes')->nullable();

            // Outcome of the eligibility Strategy run.
            $table->unsignedSmallInteger('eligibility_score')->nullable();
            $table->json('eligibility_breakdown')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->json('agency_verification')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One live application per beneficiary per programme; enforced again in
            // the service layer to block double-dipping.
            $table->unique(['user_id', 'aid_program_id']);
            $table->index(['status', 'aid_program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
