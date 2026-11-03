<?php

namespace App\Modules\Procurement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'creation_path' => ['required', 'string', 'in:from_rfq,from_ipr,manual'],
            'rfq_id' => ['nullable', 'ulid', 'exists:rfq_headers,id'],
            'ipr_ids' => ['nullable', 'array'],
            'supplier_id' => ['required', 'ulid', 'exists:suppliers,id'],
            'site_id' => ['nullable', 'ulid', 'exists:warehouse_sites,id'],
            'supplier_contact_id' => ['nullable', 'ulid', 'exists:bp_contacts,id'],
            'supplier_address_id' => ['nullable', 'ulid', 'exists:bp_addresses,id'],
            'currency' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric'],
            'payment_terms' => ['required', 'string', 'in:immediate,net_15,net_30,net_60,net_90,advance_100,split_50_50'],
            'incoterms' => ['nullable', 'string', 'max:10'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'committed_delivery_date' => ['nullable', 'date'],

            'approval_required' => ['boolean'],
            'status' => ['nullable', 'string', 'in:draft,pending_approval,approved,sent,acknowledged,in_delivery,partially_received,fully_received,closed,cancelled'],

            'supplier_po_reference' => ['nullable', 'string', 'max:100'],
            'sent_at' => ['nullable', 'date'],
            'acknowledged_at' => ['nullable', 'date'],
            'notes_to_supplier' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.catalog_item_id' => ['nullable', 'required_without:lines.*.is_ad_hoc', 'string', 'exists:catalog_items,id'],
            'lines.*.variant_id' => ['nullable', 'string', 'exists:catalog_variants,id'],
            'lines.*.is_ad_hoc' => ['boolean'],
            'lines.*.ad_hoc_name' => ['nullable', 'required_if:lines.*.is_ad_hoc,true', 'string', 'max:255'],
            'lines.*.ad_hoc_sku' => ['nullable', 'string', 'max:100'],
            'lines.*.ipr_line_id' => ['nullable', 'string'],
            'lines.*.supplier_reference' => ['nullable', 'string', 'max:100'],
            'lines.*.description_snapshot' => ['nullable', 'string', 'max:300'],
            'lines.*.qty_ordered' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price_ht' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate_pct' => ['required', 'numeric', 'min:0'],
            'lines.*.expected_delivery_date' => ['nullable', 'date'],
        ];
    }
}
