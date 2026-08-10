<?php

namespace App\Services\Reporting;

use App\Models\Application;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * BUILDER PATTERN — Module 5.
 *
 * Assembles a filtered analytical query step by step through a fluent interface,
 * so a report like "approved applicants in Selangor with more than 3 dependents,
 * funded in Q2" reads as one chained expression instead of a wall of conditionals:
 *
 *   $builder->approved()->inState('Selangor')->withMinDependents(4)->between($from, $to)->get();
 *
 * Every value is passed as a bound parameter, so no filter can inject SQL.
 */
class ApplicationReportBuilder
{
    private Builder $query;

    /** Human-readable record of the filters applied, printed on the export. */
    private array $appliedFilters = [];

    public function __construct()
    {
        $this->query = Application::query()->with(['user', 'aidProgram', 'disbursement']);
    }

    public static function make(): self
    {
        return new self();
    }

    // ---------------------------------------------------------------------
    // Filter steps
    // ---------------------------------------------------------------------

    public function status(?string $status): self
    {
        if (filled($status)) {
            $this->query->where('applications.status', $status);
            $this->appliedFilters['Status'] = str_replace('_', ' ', $status);
        }

        return $this;
    }

    public function approved(): self
    {
        return $this->status('approved');
    }

    public function inState(?string $state): self
    {
        if (filled($state)) {
            $this->query->where('applications.state', $state);
            $this->appliedFilters['State'] = $state;
        }

        return $this;
    }

    public function forProgramme(?string $slug): self
    {
        if (filled($slug)) {
            $this->query->whereHas('aidProgram', fn (Builder $q) => $q->where('slug', $slug));
            $this->appliedFilters['Programme'] = $slug;
        }

        return $this;
    }

    public function forProgrammeType(?string $type): self
    {
        if (filled($type)) {
            $this->query->whereHas('aidProgram', fn (Builder $q) => $q->where('type', $type));
            $this->appliedFilters['Programme type'] = str_replace('_', ' ', $type);
        }

        return $this;
    }

    public function withMinDependents(?int $min): self
    {
        if ($min !== null && $min > 0) {
            $this->query->where('applications.dependents_count', '>=', $min);
            $this->appliedFilters['Minimum dependents'] = (string) $min;
        }

        return $this;
    }

    public function withIncomeBelow(?float $ceiling): self
    {
        if ($ceiling !== null && $ceiling > 0) {
            $this->query->where('applications.household_income', '<=', $ceiling);
            $this->appliedFilters['Income at or below'] = 'RM '.number_format($ceiling, 2);
        }

        return $this;
    }

    public function withMinScore(?int $score): self
    {
        if ($score !== null) {
            $this->query->where('applications.eligibility_score', '>=', $score);
            $this->appliedFilters['Minimum eligibility score'] = (string) $score;
        }

        return $this;
    }

    /** Filters on the decision date, which is the meaningful date for funding reports. */
    public function decidedBetween(?string $from, ?string $to): self
    {
        if (filled($from)) {
            $this->query->whereDate('applications.decided_at', '>=', $from);
            $this->appliedFilters['Decided from'] = $from;
        }

        if (filled($to)) {
            $this->query->whereDate('applications.decided_at', '<=', $to);
            $this->appliedFilters['Decided to'] = $to;
        }

        return $this;
    }

    public function submittedBetween(?string $from, ?string $to): self
    {
        if (filled($from)) {
            $this->query->whereDate('applications.submitted_at', '>=', $from);
            $this->appliedFilters['Submitted from'] = $from;
        }

        if (filled($to)) {
            $this->query->whereDate('applications.submitted_at', '<=', $to);
            $this->appliedFilters['Submitted to'] = $to;
        }

        return $this;
    }

    /** Restricts to applications that actually resulted in money moving. */
    public function fundedOnly(bool $only = true): self
    {
        if ($only) {
            $this->query->whereHas('disbursement', fn (Builder $q) => $q->settled());
            $this->appliedFilters['Funded only'] = 'Yes';
        }

        return $this;
    }

    public function sortBy(string $column, string $direction = 'desc'): self
    {
        // Allow-list: an unchecked column name here would be an injection point,
        // since column names cannot be bound as parameters.
        $allowed = [
            'created_at', 'submitted_at', 'decided_at',
            'eligibility_score', 'household_income', 'dependents_count',
        ];

        $column = in_array($column, $allowed, true) ? $column : 'created_at';
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        $this->query->orderBy('applications.'.$column, $direction);

        return $this;
    }

    /** Applies a whole filter array in one call, e.g. straight from the request. */
    public function applyFilters(array $filters): self
    {
        return $this
            ->status($filters['status'] ?? null)
            ->inState($filters['state'] ?? null)
            ->forProgramme($filters['programme'] ?? null)
            ->forProgrammeType($filters['programme_type'] ?? null)
            ->withMinDependents(isset($filters['min_dependents']) ? (int) $filters['min_dependents'] : null)
            ->withIncomeBelow(isset($filters['max_income']) ? (float) $filters['max_income'] : null)
            ->withMinScore(isset($filters['min_score']) ? (int) $filters['min_score'] : null)
            ->decidedBetween($filters['decided_from'] ?? null, $filters['decided_to'] ?? null)
            ->fundedOnly((bool) ($filters['funded_only'] ?? false))
            ->sortBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc');
    }

    // ---------------------------------------------------------------------
    // Terminal operations
    // ---------------------------------------------------------------------

    public function get(): Collection
    {
        return $this->query->get();
    }

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->query->paginate($perPage)->withQueryString();
    }

    /** Streams large exports so a 50k-row CSV does not exhaust memory. */
    public function cursor(): \Generator
    {
        yield from $this->query->cursor();
    }

    /** Aggregates over the same filtered set, for the summary band on a report. */
    public function summary(): array
    {
        // Drops down to the query builder so the eager loads and ORDER BY on the
        // Eloquent builder do not interfere with the aggregate.
        $row = (clone $this->query)->getQuery()
            ->reorder()
            ->selectRaw('
                COUNT(*) as total,
                COALESCE(AVG(household_income), 0) as avg_income,
                COALESCE(AVG(dependents_count), 0) as avg_dependents,
                COALESCE(AVG(eligibility_score), 0) as avg_score
            ')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'avg_income' => round((float) ($row->avg_income ?? 0), 2),
            'avg_dependents' => round((float) ($row->avg_dependents ?? 0), 1),
            'avg_score' => round((float) ($row->avg_score ?? 0), 1),
        ];
    }

    public function appliedFilters(): array
    {
        return $this->appliedFilters;
    }

    /** Escape hatch for callers that need the raw builder. */
    public function toQuery(): Builder
    {
        return $this->query;
    }
}
