<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 1 — Aid Programme Management
 * Author: Liong Ka Kien
 */

namespace App\Http\Requests;

use App\Enums\AidProgramStatus;
use App\Enums\AidProgramType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AidProgramRequest extends FormRequest
{
    /** Route middleware already enforces role:admin; the policy is the second gate. */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],

            // Type is immutable after creation: changing it would invalidate the
            // payout maths of applications already assessed under the old type.
            'type' => [$isUpdate ? 'prohibited' : 'required', Rule::enum(AidProgramType::class)],

            'budget_allocated' => [$isUpdate ? 'sometimes' : 'required', 'numeric', 'min:0', 'max:999999999.99'],
            'payout_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'income_threshold' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'min_dependents' => ['nullable', 'integer', 'min:0', 'max:20'],

            'status' => ['nullable', Rule::enum(AidProgramStatus::class)],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.prohibited' => 'The programme type cannot be changed after creation.',
            'closes_at.after_or_equal' => 'The closing date must not be before the opening date.',
        ];
    }
}
