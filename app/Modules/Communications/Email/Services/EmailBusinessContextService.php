<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Services;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Organisation;
use App\Modules\BusinessPartners\Models\ServiceProvider;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Communications\Email\Models\EmailRecordLink;
use App\Modules\Communications\Email\Models\EmailSenderProfile;
use App\Modules\Crm\Models\Quotation;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Finance\Models\SalesInvoice;
use App\Modules\Hr\Models\Employee;
use App\Modules\OperationalHub\Models\Mission;
use App\Modules\Procurement\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;

class EmailBusinessContextService
{
    public function __construct(
        protected EmailRecordLinkService $recordLinkService
    ) {}

    /**
     * Resolve fully-hydrated business context for an authorized user, primary record,
     * recipient and sender profile.
     *
     * @param  array<string, mixed>  $recipient
     * @return array{
     *   context: array<string, mixed>,
     *   summary: array{
     *     record_type: string|null,
     *     record_id: string|int|null,
     *     reference: string|null,
     *     label: string|null
     *   },
     *   variables: array<string, mixed>
     * }
     */
    public function resolveContext(
        User $user,
        ?string $recordType = null,
        string|int|null $recordId = null,
        array $recipient = [],
        ?int $senderProfileId = null
    ): array {
        $context = [];
        $summary = [
            'record_type' => $recordType,
            'record_id' => $recordId,
            'reference' => null,
            'label' => null,
        ];

        // 1. Company defaults
        $appName = config('app.name');
        $defaultName = ($appName && $appName !== 'Laravel') ? $appName : 'WAVE PLUS';

        $context['company'] = [
            'name' => $defaultName,
            'email' => 'contact@waveplus.xyz',
            'phone' => '+216 71 000 000',
            'website' => 'https://waveplus.xyz',
            'address' => 'Tunis, Tunisia',
        ];

        // 2. Sender details
        $senderProfile = null;
        if ($senderProfileId) {
            $senderProfile = EmailSenderProfile::find($senderProfileId);
        }

        $context['sender'] = [
            'name' => $senderProfile?->name ?? $user->name,
            'email' => $senderProfile?->email ?? $user->email,
            'title' => $user->employee?->job_title ?? 'Team Member',
            'department' => $senderProfile?->department ?? 'Operations',
            'phone' => $user->employee?->work_phone ?? '',
        ];

        // 3. Recipient details
        $context['recipient'] = [
            'name' => $recipient['name'] ?? ($recipient['email'] ?? 'Client / Partner'),
            'email' => $recipient['email'] ?? '',
            'phone' => $recipient['phone'] ?? '',
            'company' => $recipient['company'] ?? '',
        ];

        // 4. Primary Business Record Context (if provided, rigorously authorized)
        if ($recordType && $recordId) {
            $record = $this->recordLinkService->authorizeRecordAccess($user, $recordType, $recordId);
            $this->hydrateRecordContext($context, $summary, $recordType, $record);
        }

        return [
            'context' => $context,
            'summary' => $summary,
            'variables' => $this->flattenContext($context),
        ];
    }

    /**
     * Search authorized context records for the UI Autocomplete / Selector.
     *
     * @return array<int, array{
     *   id: string|int,
     *   type: string,
     *   reference: string,
     *   label: string,
     *   date: string|null,
     *   total: string|null,
     *   status: string|null
     * }>
     */
    public function searchContextRecords(
        User $user,
        string $type,
        string $query = '',
        int $limit = 15
    ): array {
        $modelClass = EmailRecordLink::resolveModelClass($type);
        if (! $modelClass) {
            return [];
        }

        // Check read permission for this module
        $hasPermission = match (strtolower($type)) {
            'quotation', 'sales_order' => $user->hasRole('Admin') || $user->can('read-CrmSales') || $user->can('access-CrmSales'),
            'purchase_order' => $user->hasRole('Admin') || $user->can('read-Procurement') || $user->can('access-Procurement'),
            'sales_invoice', 'invoice' => $user->hasRole('Admin') || $user->can('read-Finance') || $user->can('access-Finance'),
            'mission' => $user->hasRole('Admin') || $user->can('read-OperationalHub') || $user->can('access-OperationalHub'),
            'client', 'supplier', 'organisation', 'organization', 'service_provider', 'bp_contact', 'contact' => $user->hasRole('Admin') || $user->can('read-BusinessPartners') || $user->can('access-BusinessPartners'),
            'employee' => $user->hasRole('Admin') || $user->can('read-Hr') || $user->can('access-Hr'),
            default => $user->hasRole('Admin'),
        };

        if (! $hasPermission) {
            return [];
        }

        $cleanQuery = trim($query);

        return match (strtolower($type)) {
            'quotation' => $this->searchQuotations($cleanQuery, $limit),
            'sales_order' => $this->searchSalesOrders($cleanQuery, $limit),
            'purchase_order' => $this->searchPurchaseOrders($cleanQuery, $limit),
            'sales_invoice', 'invoice' => $this->searchInvoices($cleanQuery, $limit),
            'mission' => $this->searchMissions($cleanQuery, $limit),
            'client' => $this->searchClients($cleanQuery, $limit),
            'supplier' => $this->searchSuppliers($cleanQuery, $limit),
            'organisation', 'organization' => $this->searchOrganisations($cleanQuery, $limit),
            'service_provider' => $this->searchServiceProviders($cleanQuery, $limit),
            'bp_contact', 'contact' => $this->searchContacts($cleanQuery, $limit),
            'employee' => $this->searchEmployees($cleanQuery, $limit),
            default => [],
        };
    }

    /**
     * Get variable catalog metadata for frontend palette and insertion helpers.
     *
     * @return array<string, array<int, array{ key: string, label: string, example: string, required?: bool }>>
     */
    public function getVariableCatalog(?string $recordType = null): array
    {
        $catalog = [
            'recipient' => [
                ['key' => 'recipient.name', 'label' => 'Recipient Name', 'example' => 'John Doe'],
                ['key' => 'recipient.email', 'label' => 'Recipient Email', 'example' => 'johndoe@example.com'],
                ['key' => 'recipient.company', 'label' => 'Recipient Company', 'example' => 'Acme Corp'],
                ['key' => 'recipient.phone', 'label' => 'Recipient Phone', 'example' => '+216 71 123 456'],
            ],
            'document' => [
                ['key' => 'document.reference', 'label' => 'Document Reference', 'example' => 'QUO-2026-0042'],
                ['key' => 'document.date', 'label' => 'Document Date', 'example' => '2026-11-01'],
                ['key' => 'document.due_date', 'label' => 'Due Date / Validity', 'example' => '2026-11-30'],
                ['key' => 'document.total', 'label' => 'Total Amount (TTC)', 'example' => '3,450.000 TND'],
                ['key' => 'document.subtotal', 'label' => 'Subtotal (HT)', 'example' => '2,899.160 TND'],
                ['key' => 'document.status', 'label' => 'Document Status', 'example' => 'Validated'],
            ],
            'client' => [
                ['key' => 'client.name', 'label' => 'Client Name', 'example' => 'Polytechnic Industries'],
                ['key' => 'client.code', 'label' => 'Client Code', 'example' => 'CLI-0012'],
                ['key' => 'client.tax_id', 'label' => 'Client Tax ID / MF', 'example' => '1234567M/A/M/000'],
                ['key' => 'client.email', 'label' => 'Client Email', 'example' => 'contact@polytechnic.tn'],
            ],
            'supplier' => [
                ['key' => 'supplier.name', 'label' => 'Supplier Name', 'example' => 'Global Logistics SA'],
                ['key' => 'supplier.code', 'label' => 'Supplier Code', 'example' => 'SUP-0008'],
                ['key' => 'supplier.tax_id', 'label' => 'Supplier Tax ID', 'example' => '9876543A/B/C/000'],
                ['key' => 'supplier.email', 'label' => 'Supplier Email', 'example' => 'sales@globallogistics.com'],
            ],
            'sender' => [
                ['key' => 'sender.name', 'label' => 'Sender Name', 'example' => 'Amine Trabelsi'],
                ['key' => 'sender.email', 'label' => 'Sender Email', 'example' => 'amine@waveplus.xyz'],
                ['key' => 'sender.title', 'label' => 'Sender Title', 'example' => 'Sales Director'],
                ['key' => 'sender.department', 'label' => 'Sender Department', 'example' => 'Commercial'],
            ],
            'company' => [
                ['key' => 'company.name', 'label' => 'Company Name', 'example' => 'WAVE PLUS'],
                ['key' => 'company.email', 'label' => 'Company Email', 'example' => 'contact@waveplus.xyz'],
                ['key' => 'company.phone', 'label' => 'Company Phone', 'example' => '+216 71 000 000'],
                ['key' => 'company.website', 'label' => 'Company Website', 'example' => 'https://waveplus.xyz'],
                ['key' => 'company.address', 'label' => 'Company Address', 'example' => 'Tunis, Tunisia'],
            ],
        ];

        return $catalog;
    }

    /**
     * Hydrate context array and summary according to model type.
     */
    protected function hydrateRecordContext(array &$context, array &$summary, string $type, Model $record): void
    {
        switch (strtolower($type)) {
            case 'quotation':
                /** @var Quotation $record */
                $ref = $record->reference;
                $summary['reference'] = $ref;
                $summary['label'] = "Quotation #{$ref}";

                $context['document'] = [
                    'reference' => $ref,
                    'date' => $record->created_at?->format('Y-m-d') ?? '',
                    'due_date' => $record->valid_until ? $record->valid_until->format('Y-m-d') : '',
                    'total' => number_format((float) $record->total_ttc, 2).' '.($record->currency ?? 'TND'),
                    'subtotal' => number_format((float) $record->total_ht, 2).' '.($record->currency ?? 'TND'),
                    'tax_total' => number_format((float) $record->total_tva, 2).' '.($record->currency ?? 'TND'),
                    'status' => (string) ($record->status?->value ?? $record->status ?? ''),
                ];

                if ($record->client) {
                    $this->hydrateClientContext($context, $record->client);
                }
                break;

            case 'sales_order':
                /** @var SalesOrder $record */
                $ref = $record->reference;
                $summary['reference'] = $ref;
                $summary['label'] = "Sales Order #{$ref}";

                $context['document'] = [
                    'reference' => $ref,
                    'date' => $record->created_at?->format('Y-m-d') ?? '',
                    'due_date' => $record->requested_delivery_date_to ? $record->requested_delivery_date_to->format('Y-m-d') : '',
                    'total' => number_format((float) $record->total_ttc, 2).' '.($record->currency ?? 'TND'),
                    'subtotal' => number_format((float) $record->total_ht, 2).' '.($record->currency ?? 'TND'),
                    'status' => (string) ($record->status?->value ?? $record->status ?? ''),
                ];

                if ($record->client) {
                    $this->hydrateClientContext($context, $record->client);
                }
                break;

            case 'purchase_order':
                /** @var PurchaseOrder $record */
                $ref = $record->reference;
                $summary['reference'] = $ref;
                $summary['label'] = "Purchase Order #{$ref}";

                $context['document'] = [
                    'reference' => $ref,
                    'date' => $record->created_at?->format('Y-m-d') ?? '',
                    'due_date' => $record->committed_delivery_date ? $record->committed_delivery_date->format('Y-m-d') : '',
                    'total' => number_format((float) $record->total_ttc, 2).' '.($record->currency ?? 'TND'),
                    'subtotal' => number_format((float) $record->total_ht, 2).' '.($record->currency ?? 'TND'),
                    'status' => (string) $record->status,
                ];

                if ($record->supplier) {
                    $this->hydrateSupplierContext($context, $record->supplier);
                }
                break;

            case 'sales_invoice':
            case 'invoice':
                /** @var SalesInvoice $record */
                $ref = $record->invoice_number;
                $summary['reference'] = $ref;
                $summary['label'] = "Invoice #{$ref}";

                $context['document'] = [
                    'reference' => $ref,
                    'date' => $record->invoice_date?->format('Y-m-d') ?? $record->created_at?->format('Y-m-d') ?? '',
                    'due_date' => $record->due_date ? $record->due_date->format('Y-m-d') : '',
                    'total' => number_format((float) $record->total_ttc, 2).' '.($record->currency ?? 'TND'),
                    'subtotal' => number_format((float) $record->subtotal_ht, 2).' '.($record->currency ?? 'TND'),
                    'status' => (string) $record->status,
                ];

                if ($record->client) {
                    $this->hydrateClientContext($context, $record->client);
                }
                break;

            case 'mission':
                /** @var Mission $record */
                $ref = $record->mission_number ?? ('MSN-'.$record->id);
                $summary['reference'] = $ref;
                $summary['label'] = "Mission #{$ref}";

                $context['document'] = [
                    'reference' => $ref,
                    'date' => $record->planned_date ? (is_string($record->planned_date) ? $record->planned_date : $record->planned_date->format('Y-m-d')) : $record->created_at?->format('Y-m-d') ?? '',
                    'status' => (string) ($record->status?->value ?? $record->status ?? ''),
                    'notes' => (string) ($record->preparation_notes ?? $record->completion_notes ?? ''),
                ];
                break;

            case 'client':
                /** @var Client $record */
                $this->hydrateClientContext($context, $record);
                $summary['reference'] = $record->client_id ?? (string) $record->id;
                $summary['label'] = "Client: {$context['client']['name']}";
                $context['partner'] = $context['client'];
                break;

            case 'supplier':
                /** @var Supplier $record */
                $this->hydrateSupplierContext($context, $record);
                $summary['reference'] = $record->supplier_id ?? (string) $record->id;
                $summary['label'] = "Supplier: {$context['supplier']['name']}";
                $context['partner'] = $context['supplier'];
                break;

            case 'organisation':
            case 'organization':
                /** @var Organisation $record */
                $this->hydrateOrganisationContext($context, $record);
                $summary['reference'] = $record->organisation_id ?? (string) $record->id;
                $summary['label'] = "Organisation: {$context['organisation']['name']}";
                $context['partner'] = $context['organisation'];
                break;

            case 'service_provider':
            case 'serviceprovider':
                /** @var ServiceProvider $record */
                $this->hydrateServiceProviderContext($context, $record);
                $summary['reference'] = $record->service_provider_id ?? (string) $record->id;
                $summary['label'] = "Service Provider: {$context['service_provider']['name']}";
                $context['partner'] = $context['service_provider'];
                break;

            case 'employee':
                /** @var Employee $record */
                $name = $record->user?->name ?? trim(($record->first_name['en'] ?? '').' '.($record->last_name['en'] ?? ''));
                $context['employee'] = [
                    'name' => $name,
                    'email' => $record->work_email ?? '',
                    'phone' => $record->work_phone ?? '',
                ];
                $summary['reference'] = $record->employee_id ?? (string) $record->id;
                $summary['label'] = "Employee: {$name}";
                break;
        }
    }

    protected function hydrateClientContext(array &$context, Client $client): void
    {
        $name = $this->resolveModelTranslatable($client, 'legal_name') ?: $this->resolveModelTranslatable($client, 'commercial_name');
        $context['client'] = [
            'name' => $name,
            'code' => $client->client_id ?? '',
            'tax_id' => $client->tax_id ?? '',
            'email' => $client->primary_email ?? '',
            'phone' => $client->primary_phone ?? '',
            'address' => $this->resolveModelTranslatable($client, 'hq_address') ?: ($client->hq_city ?? ''),
        ];
    }

    protected function hydrateSupplierContext(array &$context, Supplier $supplier): void
    {
        $name = $this->resolveModelTranslatable($supplier, 'legal_name') ?: $this->resolveModelTranslatable($supplier, 'commercial_name');
        $context['supplier'] = [
            'name' => $name,
            'code' => $supplier->supplier_id ?? '',
            'tax_id' => $supplier->tax_id ?? '',
            'email' => $supplier->primary_email ?? '',
            'phone' => $supplier->primary_phone ?? '',
            'address' => $this->resolveModelTranslatable($supplier, 'hq_address') ?: ($supplier->hq_city ?? ''),
        ];
    }

    protected function hydrateOrganisationContext(array &$context, Organisation $organisation): void
    {
        $name = $this->resolveModelTranslatable($organisation, 'legal_name') ?: $this->resolveModelTranslatable($organisation, 'commercial_name');
        $context['organisation'] = [
            'name' => $name,
            'code' => $organisation->organisation_id ?? '',
            'tax_id' => $organisation->tax_id ?? '',
            'email' => $organisation->primary_email ?? '',
            'phone' => $organisation->primary_phone ?? '',
            'address' => $this->resolveModelTranslatable($organisation, 'hq_address') ?: ($organisation->hq_city ?? ''),
        ];
        $context['organization'] = $context['organisation'];
    }

    protected function hydrateServiceProviderContext(array &$context, ServiceProvider $serviceProvider): void
    {
        $name = $this->resolveModelTranslatable($serviceProvider, 'legal_name') ?: $this->resolveModelTranslatable($serviceProvider, 'commercial_name');
        $context['service_provider'] = [
            'name' => $name,
            'code' => $serviceProvider->service_provider_id ?? '',
            'tax_id' => $serviceProvider->tax_id ?? '',
            'email' => $serviceProvider->primary_email ?? '',
            'phone' => $serviceProvider->primary_phone ?? '',
            'address' => $this->resolveModelTranslatable($serviceProvider, 'hq_address') ?: ($serviceProvider->hq_city ?? ''),
        ];
        $context['serviceprovider'] = $context['service_provider'];
    }

    protected function resolveModelTranslatable(mixed $model, string $attribute): string
    {
        if (! $model) {
            return '';
        }

        if (method_exists($model, 'getTranslations')) {
            $translations = $model->getTranslations($attribute);
            if (! empty($translations) && is_array($translations)) {
                $locale = app()->getLocale();
                if (! empty($translations[$locale])) {
                    return (string) $translations[$locale];
                }
                if (! empty($translations['fr'])) {
                    return (string) $translations['fr'];
                }
                if (! empty($translations['en'])) {
                    return (string) $translations['en'];
                }
                $first = reset($translations);
                if (! empty($first)) {
                    return (string) $first;
                }
            }
        }

        return $this->formatTranslatable($model->{$attribute} ?? null);
    }

    protected function formatTranslatable(mixed $val): string
    {
        if (is_string($val) && $val !== '') {
            return $val;
        }
        if (is_array($val)) {
            $locale = app()->getLocale();
            if (! empty($val[$locale])) {
                return (string) $val[$locale];
            }
            if (! empty($val['fr'])) {
                return (string) $val['fr'];
            }
            if (! empty($val['en'])) {
                return (string) $val['en'];
            }
            $first = reset($val);

            return ! empty($first) ? (string) $first : '';
        }

        return '';
    }

    /**
     * Flatten nested context into dot-notation array for quick lookup.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function flattenContext(array $context, string $prefix = ''): array
    {
        $result = [];
        foreach ($context as $key => $value) {
            $currentKey = $prefix === '' ? $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenContext($value, $currentKey));
            } else {
                $result[$currentKey] = $value;
            }
        }

        return $result;
    }

    // ── Search Helpers ─────────────────────────────────────────────────────────

    private function searchQuotations(string $q, int $limit): array
    {
        $query = Quotation::query()->with('client')->latest('id');
        if ($q !== '') {
            $query->where('reference', 'like', "%{$q}%");
        }

        return $query->limit($limit)->get()->map(function (Quotation $row) {
            $clientName = $row->client ? ($this->formatTranslatable($row->client->legal_name) ?: $this->formatTranslatable($row->client->commercial_name)) : '';

            return [
                'id' => (string) $row->id,
                'type' => 'quotation',
                'reference' => $row->reference,
                'label' => "Quotation #{$row->reference}".($clientName ? " ({$clientName})" : ''),
                'date' => $row->created_at?->format('Y-m-d'),
                'total' => number_format((float) $row->total_ttc, 2).' '.($row->currency ?? 'TND'),
                'status' => (string) ($row->status?->value ?? $row->status),
            ];
        })->all();
    }

    private function searchSalesOrders(string $q, int $limit): array
    {
        $query = SalesOrder::query()->with('client')->latest('id');
        if ($q !== '') {
            $query->where('reference', 'like', "%{$q}%");
        }

        return $query->limit($limit)->get()->map(function (SalesOrder $row) {
            $clientName = $row->client ? ($this->formatTranslatable($row->client->legal_name) ?: $this->formatTranslatable($row->client->commercial_name)) : '';

            return [
                'id' => (string) $row->id,
                'type' => 'sales_order',
                'reference' => $row->reference,
                'label' => "Sales Order #{$row->reference}".($clientName ? " ({$clientName})" : ''),
                'date' => $row->created_at?->format('Y-m-d'),
                'total' => number_format((float) $row->total_ttc, 2).' '.($row->currency ?? 'TND'),
                'status' => (string) ($row->status?->value ?? $row->status),
            ];
        })->all();
    }

    private function searchPurchaseOrders(string $q, int $limit): array
    {
        $query = PurchaseOrder::query()->with('supplier')->latest('id');
        if ($q !== '') {
            $query->where('reference', 'like', "%{$q}%");
        }

        return $query->limit($limit)->get()->map(function (PurchaseOrder $row) {
            $supName = $row->supplier ? ($this->formatTranslatable($row->supplier->legal_name) ?: $this->formatTranslatable($row->supplier->commercial_name)) : '';

            return [
                'id' => (string) $row->id,
                'type' => 'purchase_order',
                'reference' => $row->reference,
                'label' => "Purchase Order #{$row->reference}".($supName ? " ({$supName})" : ''),
                'date' => $row->created_at?->format('Y-m-d'),
                'total' => number_format((float) $row->total_ttc, 2).' '.($row->currency ?? 'TND'),
                'status' => (string) $row->status,
            ];
        })->all();
    }

    private function searchInvoices(string $q, int $limit): array
    {
        $query = SalesInvoice::query()->with('client')->latest('id');
        if ($q !== '') {
            $query->where('invoice_number', 'like', "%{$q}%");
        }

        return $query->limit($limit)->get()->map(function (SalesInvoice $row) {
            $clientName = $row->client ? ($this->formatTranslatable($row->client->legal_name) ?: $this->formatTranslatable($row->client->commercial_name)) : '';

            return [
                'id' => (string) $row->id,
                'type' => 'sales_invoice',
                'reference' => $row->invoice_number,
                'label' => "Invoice #{$row->invoice_number}".($clientName ? " ({$clientName})" : ''),
                'date' => $row->invoice_date?->format('Y-m-d') ?? $row->created_at?->format('Y-m-d'),
                'total' => number_format((float) $row->total_ttc, 2).' '.($row->currency ?? 'TND'),
                'status' => (string) $row->status,
            ];
        })->all();
    }

    private function searchMissions(string $q, int $limit): array
    {
        $query = Mission::query()->latest('id');
        if ($q !== '') {
            $query->where('mission_number', 'like', "%{$q}%");
        }

        return $query->limit($limit)->get()->map(function (Mission $row) {
            $ref = $row->mission_number ?? ('MSN-'.$row->id);

            return [
                'id' => (string) $row->id,
                'type' => 'mission',
                'reference' => $ref,
                'label' => "Mission #{$ref}",
                'date' => is_string($row->planned_date) ? $row->planned_date : $row->planned_date?->format('Y-m-d'),
                'total' => null,
                'status' => (string) ($row->status?->value ?? $row->status),
            ];
        })->all();
    }

    private function searchClients(string $q, int $limit): array
    {
        $query = Client::query()->latest('id');
        if ($q !== '') {
            $query->where(function ($b) use ($q) {
                $b->where('legal_name', 'like', "%{$q}%")
                    ->orWhere('commercial_name', 'like', "%{$q}%")
                    ->orWhere('client_id', 'like', "%{$q}%");
            });
        }

        return $query->limit($limit)->get()->map(function (Client $row) {
            $name = $this->formatTranslatable($row->legal_name) ?: $this->formatTranslatable($row->commercial_name);

            return [
                'id' => (string) $row->id,
                'type' => 'client',
                'reference' => $row->client_id ?? (string) $row->id,
                'label' => "Client: {$name} ({$row->client_id})",
                'date' => null,
                'total' => null,
                'status' => (string) $row->status,
            ];
        })->all();
    }

    private function searchSuppliers(string $q, int $limit): array
    {
        $query = Supplier::query()->latest('id');
        if ($q !== '') {
            $query->where(function ($b) use ($q) {
                $b->where('legal_name', 'like', "%{$q}%")
                    ->orWhere('commercial_name', 'like', "%{$q}%")
                    ->orWhere('supplier_id', 'like', "%{$q}%");
            });
        }

        return $query->limit($limit)->get()->map(function (Supplier $row) {
            $name = $this->formatTranslatable($row->legal_name) ?: $this->formatTranslatable($row->commercial_name);

            return [
                'id' => (string) $row->id,
                'type' => 'supplier',
                'reference' => $row->supplier_id ?? (string) $row->id,
                'label' => "Supplier: {$name} ({$row->supplier_id})",
                'date' => null,
                'total' => null,
                'status' => (string) $row->status,
            ];
        })->all();
    }

    private function searchOrganisations(string $q, int $limit): array
    {
        $query = \App\Modules\BusinessPartners\Models\Organisation::query()->latest('id');
        if ($q !== '') {
            $query->where(function ($b) use ($q) {
                $b->where('legal_name', 'like', "%{$q}%")
                    ->orWhere('commercial_name', 'like', "%{$q}%");
            });
        }

        return $query->limit($limit)->get()->map(function (\App\Modules\BusinessPartners\Models\Organisation $row) {
            $name = $this->formatTranslatable($row->legal_name) ?: $this->formatTranslatable($row->commercial_name);

            return [
                'id' => (string) $row->id,
                'type' => 'organisation',
                'reference' => (string) $row->id,
                'label' => "Organisation: {$name}".($row->primary_email ? " ({$row->primary_email})" : ''),
                'date' => null,
                'total' => null,
                'status' => (string) ($row->status ?? 'active'),
            ];
        })->all();
    }

    private function searchServiceProviders(string $q, int $limit): array
    {
        $query = \App\Modules\BusinessPartners\Models\ServiceProvider::query()->latest('id');
        if ($q !== '') {
            $query->where(function ($b) use ($q) {
                $b->where('legal_name', 'like', "%{$q}%")
                    ->orWhere('commercial_name', 'like', "%{$q}%");
            });
        }

        return $query->limit($limit)->get()->map(function (\App\Modules\BusinessPartners\Models\ServiceProvider $row) {
            $name = $this->formatTranslatable($row->legal_name) ?: $this->formatTranslatable($row->commercial_name);

            return [
                'id' => (string) $row->id,
                'type' => 'service_provider',
                'reference' => (string) $row->id,
                'label' => "Service Provider: {$name}".($row->primary_email ? " ({$row->primary_email})" : ''),
                'date' => null,
                'total' => null,
                'status' => (string) ($row->status ?? 'active'),
            ];
        })->all();
    }

    private function searchContacts(string $q, int $limit): array
    {
        $query = \App\Modules\BusinessPartners\Models\BpContact::query()->with('contactable')->latest('id');
        if ($q !== '') {
            $query->where(function ($b) use ($q) {
                $b->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        return $query->limit($limit)->get()->map(function (\App\Modules\BusinessPartners\Models\BpContact $row) {
            $first = $this->formatTranslatable($row->first_name);
            $last = $this->formatTranslatable($row->last_name);
            $name = trim("{$first} {$last}");
            if ($name === '') {
                $name = $row->job_title ?: 'Contact';
            }
            $parent = $row->contactable ? ($this->formatTranslatable($row->contactable->legal_name) ?: $this->formatTranslatable($row->contactable->commercial_name)) : '';

            return [
                'id' => (string) $row->id,
                'type' => 'bp_contact',
                'reference' => (string) $row->id,
                'label' => "Contact: {$name}".($parent ? " ({$parent})" : '').($row->email ? " <{$row->email}>" : ''),
                'date' => null,
                'total' => null,
                'status' => 'active',
            ];
        })->all();
    }

    private function searchEmployees(string $q, int $limit): array
    {
        $query = Employee::query()->latest('id');
        if ($q !== '') {
            $query->where(function ($b) use ($q) {
                $b->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('work_email', 'like', "%{$q}%")
                    ->orWhere('employee_id', 'like', "%{$q}%");
            });
        }

        return $query->limit($limit)->get()->map(function (Employee $row) {
            $name = trim($this->formatTranslatable($row->first_name).' '.$this->formatTranslatable($row->last_name));
            if ($name === '' && $row->user) {
                $name = $row->user->name;
            }

            return [
                'id' => (string) $row->id,
                'type' => 'employee',
                'reference' => $row->employee_id ?? (string) $row->id,
                'label' => "Employee: {$name}".($row->work_email ? " ({$row->work_email})" : ''),
                'date' => null,
                'total' => null,
                'status' => (string) ($row->status ?? 'active'),
            ];
        })->all();
    }
}
