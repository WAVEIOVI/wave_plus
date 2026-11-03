<?php

namespace App\Modules\Procurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRfqResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ── Response Header ──────────────────────────────────────────
            'supplier_id' => ['ulid', 'required', 'exists:suppliers,id'],
            'validity_until' => ['nullable', 'date', 'after_or_equal:today'],
            'can_deliver_by' => ['nullable', 'date'],
            'payment_terms_offered' => [
                'nullable', 'string',
                'in:net_7,net_15,net_30,net_45,net_60,net_90,cod,upfront,immediate,other',
            ],
            'total_ht' => ['required', 'numeric', 'min:0'],
            'supplier_notes' => ['nullable', 'string'],

            // ── Response Lines ───────────────────────────────────────────
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.rfq_line_id' => ['required', 'string', 'exists:rfq_lines,id'],
            'lines.*.offered_unit_price_ht' => ['required', 'numeric', 'min:0'],
            'lines.*.offered_qty' => ['required', 'numeric', 'min:0.01'],
            'lines.*.offered_lead_time_days' => ['nullable', 'integer', 'min:0'],
            'lines.*.certifications_confirmed' => ['nullable', 'array'],
        ];
    }

    /**
     * Configure the validator instance — cross-line validation.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $rfq = $this->route('rfq');
            $lines = $this->input('lines', []);

            // Ensure the supplier has been invited to this RFQ
            $supplierId = $this->input('supplier_id');
            if ($supplierId && $rfq) {
                $isInvited = $rfq->invitations()
                    ->where('supplier_id', $supplierId)
                    ->exists();

                if (! $isInvited) {
                    $validator->errors()->add(
                        'supplier_id',
                        'This supplier has not been invited to the RFQ.'
                    );
                }
            }

            // Ensure all rfq_line_ids actually belong to this RFQ
            if ($rfq) {
                $validLineIds = $rfq->lines()->pluck('id')->toArray();

                foreach ($lines as $index => $line) {
                    if (isset($line['rfq_line_id']) && ! in_array($line['rfq_line_id'], $validLineIds)) {
                        $validator->errors()->add(
                            "lines.{$index}.rfq_line_id",
                            'This line does not belong to the specified RFQ.'
                        );
                    }
                }
            }
        });
    }
}
