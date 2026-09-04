<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 2 — Application & Document Management
 * Author: Lee Kar How
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            // Only required on create; on update the programme is fixed.
            'aid_program_slug' => [
                $isUpdate ? 'prohibited' : 'required',
                Rule::exists('aid_programs', 'slug'),
            ],

            'household_income' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'dependents_count' => ['required', 'integer', 'min:0', 'max:20'],
            'state' => ['required', 'string', 'max:60'],
            'is_disaster_victim' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'aid_program_slug.prohibited' => 'The programme cannot be changed after the application is created.',
            'household_income.required' => 'Please state your gross monthly household income.',
        ];
    }

    /** Only the fields the model is allowed to receive. */
    public function applicationData(): array
    {
        return $this->safe()->only([
            'household_income',
            'dependents_count',
            'state',
            'is_disaster_victim',
            'notes',
        ]);
    }
}
