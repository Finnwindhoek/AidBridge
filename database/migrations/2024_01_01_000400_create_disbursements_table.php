<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();

            // Ledger-safe public handle used in webhooks and exports.
            $table->string('reference_code', 40)->unique();

            $table->decimal('amount', 12, 2);

            // Drives the State pattern; each value maps to a DisbursementState class.
            $table->enum('status', ['pending', 'approved', 'disbursed', 'reconciled', 'failed'])
                ->default('pending')
                ->index();

            $table->string('payment_channel', 40)->default('bank_transfer');
            $table->string('bank_reference')->nullable();
            $table->string('failure_reason')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();

            $table->timestamps();
        });

        // Idempotency ledger. A payment provider retrying a webhook must never
        // produce a second payout, so every callback key is recorded exactly once.
        Schema::create('webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->string('source', 60)->default('payment_gateway');
            $table->string('event_type', 60)->nullable();
            $table->foreignId('disbursement_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_receipts');
        Schema::dropIfExists('disbursements');
    }
};
