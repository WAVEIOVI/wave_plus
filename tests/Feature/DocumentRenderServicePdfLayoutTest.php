<?php

namespace Tests\Feature;

use App\Modules\Shared\Services\DocumentRenderService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DocumentRenderServicePdfLayoutTest extends TestCase
{
    private DocumentRenderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DocumentRenderService::class);
    }

    public function test_brand_logos_resolve_to_non_empty_base64_png_data_uris(): void
    {
        $ssPlusLogo = $this->service->logoDataUri('ss-plus');
        $wavePlusLogo = $this->service->logoDataUri('wave-plus');

        $this->assertStringStartsWith('data:image/png;base64,', $ssPlusLogo);
        $this->assertGreaterThan(1000, strlen($ssPlusLogo));

        $this->assertStringStartsWith('data:image/png;base64,', $wavePlusLogo);
        $this->assertGreaterThan(1000, strlen($wavePlusLogo));
    }

    public function test_portrait_layout_emits_expected_meta_and_page_geometry(): void
    {
        $client = $this->makePartnerStub('client_id', 'CLI-001');
        $html = view('pdf.clients.client-record', compact('client'))->render();

        $meta = $this->service->extractMeta($html);

        $this->assertSame('portrait', $meta['orientation'] ?? null);
        $this->assertSame('Fiche Client — CLI-001', $meta['title'] ?? null);
        $this->assertSame('CRM · B3', $meta['badge'] ?? null);
        $this->assertStringContainsString('size: A4 portrait;', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
    }

    public function test_landscape_layout_emits_expected_meta_and_page_geometry(): void
    {
        $clients = new Collection([$this->makePartnerStub('client_id', 'CLI-001')]);
        $html = view('pdf.clients.clients-list', compact('clients'))->render();

        $meta = $this->service->extractMeta($html);

        $this->assertSame('landscape', $meta['orientation'] ?? null);
        $this->assertSame('Partenaires Commerciaux — Liste des Clients', $meta['title'] ?? null);
        $this->assertSame('CRM · B3', $meta['badge'] ?? null);
        $this->assertStringContainsString('Répertoire confidentiel', $meta['footer-note'] ?? '');
        $this->assertStringContainsString('size: A4 landscape;', $html);
        $this->assertStringContainsString('display: table-header-group;', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
    }

    public function test_pro_vertical_layout_renders_purchase_order_with_meta_and_table_pagination_rules(): void
    {
        $po = (object) [
            'reference' => 'BC-2026-0099',
            'status' => 'approved',
            'payment_terms' => 'net_30',
            'currency' => 'TND',
            'exchange_rate' => 1,
            'created_at' => Carbon::parse('2026-10-30 09:00:00'),
            'committed_delivery_date' => '2026-11-15',
            'approved_by' => 1,
            'approved_at' => '2026-10-30 10:00:00',
            'approver' => (object) ['full_name' => 'Admin Approver'],
            'creator' => (object) ['full_name' => 'Buyer User'],
            'acknowledged_at' => null,
            'supplier' => (object) [
                'name' => 'Fournisseur Test SARL',
                'address' => 'Zone Industrielle Sfax',
                'city' => 'Sfax',
                'country' => 'Tunisie',
                'tax_id' => '1234567/A/M/000',
            ],
            'supplierContact' => null,
            'supplier_po_reference' => null,
            'delivery_address' => 'Route de Tunis km 2.5, Sfax 3000, Tunisie',
            'incoterms' => 'DDP',
            'creation_path' => 'manual',
            'sent_at' => '2026-10-30',
            'lines' => new Collection([
                (object) [
                    'is_ad_hoc' => true,
                    'ad_hoc_sku' => 'SKU-01',
                    'ad_hoc_name' => 'Gants de protection mécanique EN 388',
                    'catalogItem' => null,
                    'description_snapshot' => null,
                    'variant' => null,
                    'supplier_reference' => 'REF-01',
                    'qty_ordered' => 10,
                    'unit_price_ht' => 12.5,
                    'tax_rate_pct' => 19,
                    'total_ht' => 125.0,
                    'total_ttc' => 148.75,
                    'fulfillment_status' => 'pending',
                    'qty_received' => 0,
                ],
            ]),
            'notes_to_supplier' => 'Livraison sur palette.',
            'internal_notes' => null,
            'total_ht' => 125.0,
            'total_tax' => 23.75,
            'total_ttc' => 148.75,
        ];

        $html = view('pdf.procurement.po-record', compact('po'))->render();
        $meta = $this->service->extractMeta($html);

        $this->assertSame('portrait', $meta['orientation'] ?? null);
        $this->assertSame('Bon de Commande — BC-2026-0099', $meta['title'] ?? null);
        $this->assertSame('PO', $meta['badge'] ?? null);
        $this->assertSame('BC-2026-0099', $meta['ref'] ?? null);
        $this->assertStringContainsString('size: A4 portrait;', $html);
        $this->assertStringContainsString('display: table-header-group;', $html);
    }

    public function test_all_partner_and_catalog_pdf_templates_render_cleanly(): void
    {
        $supplier = $this->makePartnerStub('supplier_id', 'SUP-001');
        $serviceProvider = $this->makePartnerStub('service_provider_id', 'SP-001');
        $organisation = $this->makePartnerStub('organisation_id', 'ORG-001');

        $this->assertStringContainsString('SUP-001', view('pdf.suppliers.supplier-record', compact('supplier'))->render());
        $this->assertStringContainsString('SUP-001', view('pdf.suppliers.suppliers-list', ['suppliers' => collect([$supplier])])->render());

        $this->assertStringContainsString('SP-001', view('pdf.service-providers.service-provider-record', compact('serviceProvider'))->render());
        $this->assertStringContainsString('SP-001', view('pdf.service-providers.service-providers-list', ['serviceProviders' => collect([$serviceProvider])])->render());

        $this->assertStringContainsString('ORG-001', view('pdf.organisations.organisation-record', compact('organisation'))->render());
        $this->assertStringContainsString('ORG-001', view('pdf.organisations.organisations-list', ['organisations' => collect([$organisation])])->render());
    }

    private function makePartnerStub(string $idField, string $idValue): object
    {
        return (object) [
            $idField => $idValue,
            'legal_name' => 'Partenaire Test SA',
            'commercial_name' => ['fr' => 'Partenaire Test'],
            'status' => 'active',
            'client_category' => 'corporate',
            'supplier_category' => 'manufacturer',
            'service_provider_category' => 'maintenance',
            'organisation_category' => 'association',
            'provider_tier' => 'tier_1',
            'industry' => 'manufacturing',
            'company_size' => 'sme',
            'legal_status' => 'sarl',
            'tax_id' => '1234567/A/M/000',
            'rne_id' => 'B0123456',
            'founding_year' => 2015,
            'credit_limit' => 50000,
            'payment_terms' => 'net_30',
            'default_currency' => 'TND',
            'discount_rate' => 5,
            'primary_phone' => '+216 74 000 000',
            'primary_email' => 'contact@partenaire.tn',
            'website' => 'https://partenaire.tn',
            'hq_city' => 'Sfax',
            'hq_country' => 'Tunisie',
            'partner_since' => '2022-01-15',
            'accountManager' => (object) ['full_name' => 'Manager Test'],
            'satisfaction_score' => 8,
            'addresses' => new Collection,
            'contacts' => new Collection,
            'bankAccounts' => new Collection,
            'vatCertificates' => new Collection,
        ];
    }
}
