<?php

namespace App\Services\AidProgram;

use App\Enums\AidProgramStatus;
use App\Models\AidProgram;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Business logic for Module 1. Controllers stay thin: they validate input and
 * delegate here.
 */
class AidProgramService
{
    public function __construct(
        private readonly AidProgramFactory $factory,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(array $data, int $adminId): AidProgram
    {
        // The factory fills in any field the admin left blank with the defaults
        // for the chosen programme type.
        $attributes = $this->factory->make($data['type'])->buildAttributes($data);

        $attributes['slug'] = $this->uniqueSlug($attributes['title']);
        $attributes['created_by'] = $adminId;

        // A new programme starts with its full allocation available.
        $attributes['budget_remaining'] = $attributes['budget_allocated'];

        $program = AidProgram::create($attributes);

        $this->auditLogger->log('aid_program.created', $program, [
            'type' => $program->type->value,
            'budget_allocated' => $program->budget_allocated,
        ]);

        return $program;
    }

    public function update(AidProgram $program, array $data): AidProgram
    {
        $original = $program->only(['title', 'status', 'budget_allocated', 'payout_amount']);

        return DB::transaction(function () use ($program, $data, $original) {
            // Raising or lowering the allocation shifts the remaining budget by the
            // same delta, so money already committed to approved applications is
            // never silently freed or double-counted.
            if (array_key_exists('budget_allocated', $data)) {
                $delta = (float) $data['budget_allocated'] - (float) $program->budget_allocated;
                $newRemaining = (float) $program->budget_remaining + $delta;

                if ($newRemaining < 0) {
                    throw new RuntimeException(
                        'Cannot reduce the allocation below the amount already committed to approved applications.'
                    );
                }

                $data['budget_remaining'] = $newRemaining;
            }

            if (isset($data['title']) && $data['title'] !== $program->title) {
                $data['slug'] = $this->uniqueSlug($data['title'], $program->id);
            }

            $program->update($data);

            $this->auditLogger->log('aid_program.updated', $program, [
                'from' => $original,
                'to' => $program->only(['title', 'status', 'budget_allocated', 'payout_amount']),
            ]);

            return $program->refresh();
        });
    }

    /** Programmes with history are archived rather than deleted. */
    public function archive(AidProgram $program): AidProgram
    {
        $program->update(['status' => AidProgramStatus::Archived]);

        $this->auditLogger->log('aid_program.archived', $program);

        return $program;
    }

    public function delete(AidProgram $program): void
    {
        if ($program->applications()->exists()) {
            throw new RuntimeException('This programme has applications and can only be archived.');
        }

        $this->auditLogger->log('aid_program.deleted', $program, ['title' => $program->title]);

        $program->delete();
    }

    /**
     * Payout for one approved application, delegated to the programme type.
     */
    public function payoutFor(AidProgram $program, int $dependents, int $eligibilityScore): float
    {
        return $this->factory->forProgram($program)->calculatePayout(
            (float) $program->payout_amount,
            $dependents,
            $eligibilityScore,
        );
    }

    public function requiredDocumentsFor(AidProgram $program): array
    {
        return $this->factory->forProgram($program)->requiredDocuments();
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'programme';
        $slug = $base;
        $suffix = 2;

        while (AidProgram::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
