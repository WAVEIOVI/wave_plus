<?php

namespace App\Modules\Procurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsReceiptNoteRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'po_id' => ['required', 'ulid', 'exists:purchase_orders,id'],
            'supplier_id' => ['required', 'ulid', 'exists:suppliers,id'],
            'received_by' => ['required', 'ulid', 'exists:users,id'],
            'received_at' => ['required', 'date'],
            'supplier_delivery_note_ref' => ['nullable', 'string', 'max:100'],
            'supplier_delivery_note_path' => ['nullable', 'string', 'max:300'],
            'delivery_note_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'allow_over_receive' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:draft,under_qc,confirmed,partially_rejected,fully_rejected,stocked'],
            'is_partial_delivery' => ['boolean'],
            'overall_qc_result' => ['nullable', 'string', 'in:passed,passed_with_remarks,partially_rejected,fully_rejected'],
            'qc_performed_by' => ['nullable', 'ulid', 'exists:users,id'],
            'qc_performed_at' => ['nullable', 'date'],
            'delivery_on_time' => ['prohibited'],
            'days_late' => ['prohibited'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.po_line_id' => ['required', 'string', 'exists:po_lines,id'],
            'lines.*.is_ad_hoc' => ['sometimes', 'boolean'],
            'lines.*.catalog_item_id' => ['nullable', 'required_without:lines.*.is_ad_hoc', 'string', 'exists:catalog_items,id'],
            'lines.*.catalog_variant_id' => ['nullable', 'string', 'exists:catalog_variants,id'],
            'lines.*.ad_hoc_name' => ['nullable', 'required_if:lines.*.is_ad_hoc,true', 'string', 'max:255'],
            'lines.*.ad_hoc_sku' => ['nullable', 'string', 'max:100'],
            'lines.*.qty_received' => ['required', 'numeric', 'min:0.01'],
            'lines.*.qty_accepted' => ['nullable', 'numeric', 'min:0'],
            'lines.*.qty_rejected' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_cost_ht' => ['required', 'numeric', 'min:0'],
            'lines.*.lot_number' => ['nullable', 'string', 'max:80'],
            'lines.*.expiry_date' => ['nullable', 'date'],
            'lines.*.storage_location_id' => ['nullable', 'string'],
            'lines.*.qc_result' => ['nullable', 'string', 'in:passed,passed_with_remarks,rejected'],
            'lines.*.qc_notes' => ['nullable', 'string'],
            'lines.*.qc_photos' => ['nullable', 'array'],
            'lines.*.certificates_received' => ['nullable', 'array'],
        ];
    }
}
