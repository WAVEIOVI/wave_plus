<?php

declare(strict_types=1);

namespace App\Modules\Crm\Services;

use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Catalog\Services\CatalogLineSnapshotService;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\Crm\Enums\PaymentTerms;
use App\Modules\Crm\Enums\SalesOrderStatus;
use App\Modules\Crm\Enums\SalesOrderType;
use App\Modules\Crm\Enums\SoCreationPath;
use App\Modules\Crm\Enums\SoFulfillmentMode;
use App\Modules\Crm\Models\Contract;
use App\Modules\Crm\Models\Quotation;
use App\Modules\Crm\Models\SalesOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderWizardService
{
    public function __construct(
        private readonly CatalogLineSnapshotService $lineSnapshotService,
        private readonly PriceResolutionService $priceResolutionService,
        private readonly SalesOrderConversionService $conversionService,
    ) {}

    /**
     * Initialize a Sales Order from the Starter Wizard.
     *
     * Validates client ownership of all associated entities, ensures valid fulfillment mode,
     * checks date windows, optionally imports lines from quotation or contract, and locks starter fields.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function initFromWizard(array $data): SalesOrder
    {
        $clientId = $data['client_id'] ?? null;
        if (! is_string($clientId) || $clientId === '' || ! Client::where('id', $clientId)->exists()) {
            throw ValidationException::withMessages([
                'client_id' => 'A valid client must be selected.',
            ]);
        }

        $client = Client::findOrFail($clientId);

        $soType = $data['so_type'] instanceof SalesOrderType
            ? $data['so_type']->value
            : (string) ($data['so_type'] ?? 'sales');

        if (! in_array($soType, ['sales', 'service'], true)) {
            throw ValidationException::withMessages([
                'so_type' => "Invalid Sales Order type: {$soType}.",
            ]);
        }

        $creationPath = $data['creation_path'] instanceof SoCreationPath
            ? $data['creation_path']->value
            : (string) ($data['creation_path'] ?? 'manual');

        // Validate fulfillment mode
        $fulfillmentMode = $data['requested_fulfillment_mode'] ?? null;
        if ($fulfillmentMode instanceof SoFulfillmentMode) {
            $fulfillmentMode = $fulfillmentMode->value;
        }

        if ($fulfillmentMode !== null) {
            if ($fulfillmentMode === SoFulfillmentMode::MobileWorkshop->value) {
                $fulfillmentMode = SoFulfillmentMode::FieldService->value;
            }
            if (! SoFulfillmentMode::isValidForSoType($fulfillmentMode, $soType)) {
                throw ValidationException::withMessages([
                    'requested_fulfillment_mode' => "Fulfillment mode '{$fulfillmentMode}' is not valid for {$soType} orders.",
                ]);
            }
        } else {
            // Default fulfillment mode based on SO type
            $fulfillmentMode = $soType === 'service'
                ? SoFulfillmentMode::FieldService->value
                : SoFulfillmentMode::OwnDelivery->value;
        }

        // Validate date intervals
        $reqFrom = $data['requested_delivery_date_from'] ?? null;
        $reqTo = $data['requested_delivery_date_to'] ?? null;
        if ($reqFrom && $reqTo && Carbon::parse($reqTo)->lt(Carbon::parse($reqFrom))) {
            throw ValidationException::withMessages([
                'requested_delivery_date_to' => 'Requested delivery date (to) must be greater than or equal to date (from).',
            ]);
        }

        $commFrom = $data['committed_delivery_date_from'] ?? null;
        $commTo = $data['committed_delivery_date_to'] ?? null;
        if ($commFrom && $commTo && Carbon::parse($commTo)->lt(Carbon::parse($commFrom))) {
            throw ValidationException::withMessages([
                'committed_delivery_date_to' => 'Committed delivery date (to) must be greater than or equal to date (from).',
            ]);
        }

        // Validate source quotation / contract ownership
        $quotation = null;
        if (! empty($data['quotation_id'])) {
            $quotation = Quotation::find($data['quotation_id']);
            if (! $quotation || $quotation->client_id !== $clientId) {
                throw ValidationException::withMessages([
                    'quotation_id' => 'The selected quotation does not belong to this client.',
                ]);
            }
        } elseif ($creationPath === 'quotation') {
            throw ValidationException::withMessages([
                'quotation_id' => 'A quotation must be selected when creation path is quotation.',
            ]);
        }

        $contract = null;
        if (! empty($data['contract_id'])) {
            $contract = Contract::find($data['contract_id']);
            if (! $contract || $contract->client_id !== $clientId) {
                throw ValidationException::withMessages([
                    'contract_id' => 'The selected contract does not belong to this client.',
                ]);
            }
        } elseif ($creationPath === 'contract_call_off') {
            throw ValidationException::withMessages([
                'contract_id' => 'A contract must be selected when creation path is contract call-off.',
            ]);
        }

        // Validate locations
        $locations = $data['locations'] ?? [];
        if (! empty($locations) && is_array($locations)) {
            $validAddressIds = BpAddress::where('addressable_type', Client::class)
                ->where('addressable_id', $clientId)
                ->pluck('id')
                ->toArray();

            foreach ($locations as $idx => $loc) {
                $addrId = $loc['bp_address_id'] ?? null;
                if (! $addrId || ! in_array($addrId, $validAddressIds, true)) {
                    throw ValidationException::withMessages([
                        "locations.{$idx}.bp_address_id" => "Address #{$addrId} does not belong to the selected client.",
                    ]);
                }

                if (! empty($loc['client_site_id'])) {
                    $site = ClientSite::where('id', $loc['client_site_id'])
                        ->where('client_id', $clientId)
                        ->first();

                    if (! $site) {
                        throw ValidationException::withMessages([
                            "locations.{$idx}.client_site_id" => 'Site does not belong to the selected client.',
                        ]);
                    }

                    if ($site->bp_address_id && $site->bp_address_id !== $addrId) {
                        throw ValidationException::withMessages([
                            "locations.{$idx}.client_site_id" => 'Site does not match the specified address.',
                        ]);
                    }
                }
            }
        }

        // Delegate quotation/contract conversion to canonical service
        if ($creationPath === 'quotation' && $quotation) {
            return $this->handleQuotationConversion($quotation, $soType, $fulfillmentMode, $data, $locations);
        }

        if ($creationPath === 'contract_call_off' && $contract) {
            return $this->handleContractConversion($contract, $soType, $fulfillmentMode, $data, $locations);
        }

        // Manual creation - create empty draft SO
        return $this->createManualSo($client, $soType, $fulfillmentMode, $data, $locations);
    }

    /**
     * Handle quotation conversion by delegating to SalesOrderConversionService.
     * Returns the primary (first) SO for the wizard UI.
     */
    private function handleQuotationConversion(
        Quotation $quotation,
        string $soType,
        string $fulfillmentMode,
        array $data,
        array $locations
    ): SalesOrder {
        // Use canonical conversion - it handles mixed quotations by creating separate SOs
        $orders = $this->conversionService->createFromQuotation($quotation);

        // For wizard, return the SO matching the requested type
        // Mixed quotations will create both, but wizard UI expects one
        $matching = $orders->first(fn (SalesOrder $so) => $so->so_type->value === $soType);

        if (! $matching) {
            // If no matching type (e.g. requested 'sales' but quotation only has service lines),
            // return the first created SO
            $matching = $orders->first();
        }

        // Apply any additional wizard data (locations, dates, etc.) to the created SO
        return $this->applyWizardData($matching, $data, $locations, $fulfillmentMode);
    }

    /**
     * Handle contract call-off conversion by delegating to SalesOrderConversionService.
     */
    private function handleContractConversion(
        Contract $contract,
        string $soType,
        string $fulfillmentMode,
        array $data,
        array $locations
    ): SalesOrder {
        // Canonical conversion always creates Service SO from contract
        $so = $this->conversionService->createFromContract($contract);

        // Apply any additional wizard data
        return $this->applyWizardData($so, $data, $locations, $fulfillmentMode);
    }

    /**
     * Create a manual (empty) Sales Order draft.
     */
    private function createManualSo(
        Client $client,
        string $soType,
        string $fulfillmentMode,
        array $data,
        array $locations
    ): SalesOrder {
        return DB::transaction(function () use ($client, $soType, $fulfillmentMode, $data, $locations) {
            $paymentTerms = $data['payment_terms']
                ?? $client->payment_terms
                ?? PaymentTerms::Net30;

            $currency = $data['currency']
                ?? $client->default_currency
                ?? 'TND';

            $so = SalesOrder::create([
                'client_id' => $client->id,
                'so_type' => $soType,
                'creation_path' => SoCreationPath::Manual,
                'price_list_id' => $data['price_list_id'] ?? null,
                'client_po_reference' => $data['client_po_reference'] ?? null,
                'client_po_document_path' => $data['client_po_document_path'] ?? null,
                'requested_fulfillment_mode' => $fulfillmentMode,
                'requested_delivery_date_from' => $data['requested_delivery_date_from'] ?? null,
                'requested_delivery_date_to' => $data['requested_delivery_date_to'] ?? null,
                'committed_delivery_date_from' => $data['committed_delivery_date_from'] ?? null,
                'committed_delivery_date_to' => $data['committed_delivery_date_to'] ?? null,
                'status' => SalesOrderStatus::Draft->value,
                'pricing_locked_at' => now(),
                'starter_locked_at' => now(),
                'currency' => $currency,
                'payment_terms' => $paymentTerms,
                'internal_notes' => $data['internal_notes'] ?? null,
                'client_notes' => $data['client_notes'] ?? null,
                'total_ht' => 0.0,
                'total_tva' => 0.0,
                'total_ttc' => 0.0,
                'has_service_lines' => $soType === 'service',
                'has_goods_lines' => $soType === 'sales',
            ]);

            // Save locations
            if (! empty($locations)) {
                foreach ($locations as $loc) {
                    if (! empty($loc['bp_address_id'])) {
                        $so->locations()->create([
                            'bp_address_id' => $loc['bp_address_id'],
                            'client_site_id' => $loc['client_site_id'] ?? null,
                            'purpose' => $loc['purpose'] ?? ($soType === 'service' ? 'service' : 'delivery'),
                            'is_primary' => (bool) ($loc['is_primary'] ?? false),
                            'metadata' => $loc['metadata'] ?? null,
                        ]);
                    }
                }
            }

            return $so->load(['client', 'lines', 'locations.bpAddress', 'locations.clientSite']);
        });
    }

    /**
     * Apply additional wizard data (locations, dates) to an already-created SO.
     */
    private function applyWizardData(
        SalesOrder $so,
        array $data,
        array $locations,
        string $fulfillmentMode
    ): SalesOrder {
        return DB::transaction(function () use ($so, $data, $locations, $fulfillmentMode) {
            // Update dates and fulfillment mode if provided
            $updates = [];
            if (! empty($data['requested_delivery_date_from'])) {
                $updates['requested_delivery_date_from'] = $data['requested_delivery_date_from'];
            }
            if (! empty($data['requested_delivery_date_to'])) {
                $updates['requested_delivery_date_to'] = $data['requested_delivery_date_to'];
            }
            if (! empty($data['committed_delivery_date_from'])) {
                $updates['committed_delivery_date_from'] = $data['committed_delivery_date_from'];
            }
            if (! empty($data['committed_delivery_date_to'])) {
                $updates['committed_delivery_date_to'] = $data['committed_delivery_date_to'];
            }
            if (! empty($data['internal_notes'])) {
                $updates['internal_notes'] = $data['internal_notes'];
            }
            if (! empty($data['client_notes'])) {
                $updates['client_notes'] = $data['client_notes'];
            }
            // Allow fulfillment mode override if explicitly provided
            if (isset($data['requested_fulfillment_mode']) && $data['requested_fulfillment_mode'] !== null) {
                $updates['requested_fulfillment_mode'] = $fulfillmentMode;
            }

            if (! empty($updates)) {
                $so->update($updates);
            }

            // Add locations if provided (merge with existing)
            if (! empty($locations)) {
                foreach ($locations as $loc) {
                    if (! empty($loc['bp_address_id'])) {
                        // Check if location already exists
                        $exists = $so->locations()->where('bp_address_id', $loc['bp_address_id'])->exists();
                        if (! $exists) {
                            $so->locations()->create([
                                'bp_address_id' => $loc['bp_address_id'],
                                'client_site_id' => $loc['client_site_id'] ?? null,
                                'purpose' => $loc['purpose'] ?? ($so->so_type->value === 'service' ? 'service' : 'delivery'),
                                'is_primary' => (bool) ($loc['is_primary'] ?? false),
                                'metadata' => $loc['metadata'] ?? null,
                            ]);
                        }
                    }
                }
            }

            return $so->fresh(['client', 'contract', 'quotation', 'lines', 'locations.bpAddress', 'locations.clientSite']);
        });
    }
}
