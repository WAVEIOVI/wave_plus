<?php

namespace App\Modules\BusinessPartners\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBpDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $metadata = $this->input('metadata');

        if (! is_string($metadata)) {
            return;
        }

        try {
            $this->merge(['metadata' => json_decode($metadata, true, 512, JSON_THROW_ON_ERROR)]);
        } catch (\JsonException) {
            // Keep invalid JSON unchanged so the array validation reports it.
        }
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:contract,nda,framework_agreement,purchase_order,compliance_certificate,insurance_policy,tax_id,rne,other',
            'title' => 'required|string|max:255',
            'reference_number' => 'nullable|string|max:255',
            'issue_date' => 'nullable|date',
            'effective_date' => 'nullable|date',
            'expiration_date' => 'nullable|date|after_or_equal:issue_date',
            'auto_renewable' => 'nullable|boolean',
            'status' => 'nullable|in:draft,active,under_review,expired,terminated',
            'notes' => 'nullable|string|max:5000',
            'metadata' => 'nullable|array',
            'source' => 'nullable|string|in:upload,camera_scan,camera_image',
            'page_count' => 'nullable|integer|min:1|max:5000',
            // 'mimes' checks the real (content-sniffed) MIME type in Laravel, so
            // both the extension and the detected type must agree.
            'file' => 'file|max:20480|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,txt',
        ];
    }
}