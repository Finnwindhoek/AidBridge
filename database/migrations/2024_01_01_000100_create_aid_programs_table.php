<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aid_programs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Discriminator consumed by AidProgramFactory to rebuild the concrete
            // program type (cash / voucher / emergency grant) from a database row.
            $table->enum('type', ['cash_disbursement', 'voucher', 'emergency_grant'])->index();

            $table->decimal('budget_allocated', 15, 2)->default(0);
            $table->decimal('budget_remaining', 15, 2)->default(0);
            $table->decimal('payout_amount', 12, 2)->default(0);

            // Eligibility envelope evaluated by the Strategy layer.
            $table->decimal('income_threshold', 12, 2)->default(5250);
            $table->unsignedTinyInteger('min_dependents')->default(0);

            $table->enum('status', ['draft', 'open', 'closed', 'archived'])->default('draft')->index();
            $table->date('opens_at')->nullable();
            $table->date('closes_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aid_programs');
    }
};
