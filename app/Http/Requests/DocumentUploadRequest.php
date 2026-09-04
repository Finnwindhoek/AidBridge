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

class DocumentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'document_type' => [
                'required',
                Rule::in(['nric', 'income_proof', 'household_proof', 'disability_cert', 'other']),
            ],

            // `mimes` checks the real extension and `mimetypes` the sniffed content
            // type; both must agree before DocumentStorageService re-checks on disk.
            'file' => [
                'required',
                'file',
                'mimes:png,jpg,jpeg,pdf',
                'mimetypes:image/png,image/jpeg,application/pdf',
                'max:4096',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Only PNG, JPG or PDF files may be uploaded.',
            'file.mimetypes' => 'The file contents do not match a PNG, JPG or PDF.',
            'file.max' => 'Documents must be 4 MB or smaller.',
        ];
    }
}
