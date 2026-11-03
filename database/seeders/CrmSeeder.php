<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;

class CrmSeeder extends Seeder
{
    private array $ids = [];

    // ── helpers ────────────────────────────────────────────────────────────────

    private function i18n(string $en, string $fr = '', string $ar = ''): string
    {
        return json_encode([
            'en' => $en,
            'fr' => $fr ?: $en,
            'ar' => $ar ?: $en,
        ]);
    }

    private function hqAddress(string $street, string $city, string $postcode = '1000', string $country = 'Tunisia'): array
    {
        $countryObj = match ($country) {
            'Germany' => ['en' => 'Germany', 'fr' => 'Allemagne', 'ar' => 'ألمانيا'],
            'France' => ['en' => 'France', 'fr' => 'France', 'ar' => 'فرنسا'],
            default => ['en' => 'Tunisia', 'fr' => 'Tunisie', 'ar' => 'تونس'],
        };

        return [
            'hq_address' => json_encode(['en' => $street, 'fr' => $street, 'ar' => $street]),
            'hq_city' => json_encode(['en' => $city,   'fr' => $city,   'ar' => $city]),
            'hq_postcode' => $postcode,
            'hq_country' => json_encode($countryObj),
        ];
    }

    private function idFor(string $table, int $sequence): string
    {
        return $this->ids[$table][$sequence] ??= (string) Str::ulid();
    }

    private function userId(string $email): ?string
    {
        return User::where('email', $email)->value('id');
    }

    private function adminId(): ?string
    {
        return $this->userId('admin@waveplus.io');
    }

    private function secondaryAdminId(): ?string
    {
        return $this->userId('admin2@waveplus.io');
    }

    // ── run ────────────────────────────────────────────────────────────────────

    public function run(): void
    {
        $this->seedClients();
        $this->seedSuppliers();
        $this->seedServiceProviders();
        $this->seedOrganisations();
        $this->seedBpAddresses();
        $this->seedBpContacts();
        $this->seedBpContactAddresses();
        $this->seedBpBankAccounts();
        $this->seedBpVatCertificates();
        $this->seedBpDocuments();
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  CLIENTS
    // ──────────────────────────────────────────────────────────────────────────

    private function seedClients(): void
    {
        $clients = [
            [
                'client_id' => 'CLT-00000001',
                'legal_name' => $this->i18n('TechnoGuard SARL', 'TechnoGuard SARL', 'تيكنوغارد ش.م.م'),
                'commercial_name' => $this->i18n('TechnoGuard', 'TechnoGuard', 'تيكنوغارد'),
                'tax_id' => '1234567/A/M/000',
                'rne_id' => 'B123456789',
                'legal_status' => 'sarl',
                'company_size' => '51-200',
                'industry' => 'Fire Safety',
                'founding_year' => 2010,
                'website' => 'https://technoguard.tn',
                'primary_phone' => '+216 71 234 567',
                'primary_email' => 'contact@technoguard.tn',
                'account_manager_id' => $this->adminId(),
                'partner_since' => '2021-03-15',
                'satisfaction_score' => 4.5,
                'client_category' => 'enterprise',
                'credit_limit' => 150000.000,
                'payment_terms' => 'net_30',
                'default_currency' => 'TND',
                'discount_rate' => 5.00,
                'status' => 'active',
                'notes' => json_encode(['en' => 'Key enterprise account. Annual review in Q4.'], JSON_UNESCAPED_UNICODE),
                ...$this->hqAddress('Rue du Lac Malaren 12', 'Tunis', '1053'),
            ],
            [
                'client_id' => 'CLT-00000002',
                'legal_name' => $this->i18n('SafeBuilding SA', 'SafeBuilding SA', 'سيف بيلدنج ش.م'),
                'commercial_name' => $this->i18n('SafeBuilding', 'SafeBuilding', 'سيف بيلدنج'),
                'tax_id' => '9876543/A/P/000',
                'rne_id' => 'B987654321',
                'legal_status' => 'sa',
                'company_size' => '201-500',
                'industry' => 'Construction',
                'founding_year' => 2005,
                'website' => 'https://safebuilding.tn',
                'primary_phone' => '+216 73 456 789',
                'primary_email' => 'info@safebuilding.tn',
                'account_manager_id' => $this->secondaryAdminId(),
                'partner_since' => '2019-06-01',
                'satisfaction_score' => 4.2,
                'client_category' => 'premium',
                'credit_limit' => 250000.000,
                'payment_terms' => 'net_60',
                'default_currency' => 'TND',
                'discount_rate' => 8.00,
                'status' => 'active',
                'notes' => json_encode(['en' => 'Premium client. Prefers on-site meetings.'], JSON_UNESCAPED_UNICODE),
                ...$this->hqAddress('Avenue Habib Bourguiba 45', 'Sfax', '3000'),
            ],
            [
                'client_id' => 'CLT-00000003',
                'legal_name' => $this->i18n('Hotel Méditerranée SA', 'Hôtel Méditerranée SA', 'فندق المتوسط ش.م'),
                'commercial_name' => $this->i18n('Hotel Méditerranée', 'Hôtel Méditerranée', 'فندق المتوسط'),
                'tax_id' => '1122334/B/P/000',
                'rne_id' => null,
                'legal_status' => 'sa',
                'company_size' => '51-200',
                'industry' => 'Hospitality',
                'founding_year' => 1998,
                'website' => 'https://hotelmed.tn',
                'primary_phone' => '+216 72 111 222',
                'primary_email' => 'direction@hotelmed.tn',
                'account_manager_id' => $this->adminId(),
                'partner_since' => '2022-01-10',
                'satisfaction_score' => 3.8,
                'client_category' => 'standard',
                'credit_limit' => 50000.000,
                'payment_terms' => 'net_30',
                'default_currency' => 'TND',
                'discount_rate' => 2.00,
                'status' => 'active',
                'notes' => json_encode(['en' => 'Seasonal business. Higher volume in summer.'], JSON_UNESCAPED_UNICODE),
                ...$this->hqAddress('Boulevard de la Corniche', 'Hammamet', '8050'),
            ],
            [
                'client_id' => 'CLT-00000004',
                'legal_name' => $this->i18n('Industrie Chimique du Sud SARL', 'Industrie Chimique du Sud SARL', 'الصناعة الكيميائية الجنوبية ش.م.م'),
                'commercial_name' => $this->i18n('ICS', 'ICS', 'الصناعة الكيميائية الجنوبية'),
                'tax_id' => '5566778/A/M/000',
                'rne_id' => 'B556677889',
                'legal_status' => 'sarl',
                'company_size' => '501-1000',
                'industry' => 'Chemical Industry',
                'founding_year' => 1995,
                'website' => 'https://ics-tn.com',
                'primary_phone' => '+216 74 333 444',
                'primary_email' => 'procurement@ics-tn.com',
                'account_manager_id' => $this->secondaryAdminId(),
                'partner_since' => '2018-09-20',
                'satisfaction_score' => 4.7,
                'client_category' => 'enterprise',
                'credit_limit' => 500000.000,
                'payment_terms' => 'net_60',
                'default_currency' => 'TND',
                'discount_rate' => 10.00,
                'status' => 'active',
                'notes' => json_encode(['en' => 'ATEX environment. Requires specialized equipment.'], JSON_UNESCAPED_UNICODE),
                ...$this->hqAddress('Zone Industrielle El Mdhilla', 'Gabès', '6011'),
            ],
            [
                'client_id' => 'CLT-00000005',
                'legal_name' => $this->i18n('GreenMall SUARL', 'GreenMall SUARL', 'غرين مول ش.ف.ش.م.م'),
                'commercial_name' => $this->i18n('GreenMall', 'GreenMall', 'غرين مول'),
                'tax_id' => null,
                'rne_id' => null,
                'legal_status' => 'suarl',
                'company_size' => '11-50',
                'industry' => 'Retail',
                'founding_year' => 2018,
                'website' => null,
                'primary_phone' => '+216 71 999 888',
                'primary_email' => 'owner@greenmall.tn',
                'account_manager_id' => null,
                'partner_since' => null,
                'satisfaction_score' => null,
                'client_category' => 'unclassified',
                'credit_limit' => 0.000,
                'payment_terms' => null,
                'default_currency' => 'TND',
                'discount_rate' => 0.00,
                'status' => 'prospect',
                'notes' => json_encode(['en' => 'Lead from trade fair. Follow up pending.'], JSON_UNESCAPED_UNICODE),
                ...$this->hqAddress('Centre Commercial GreenMall, RN1', 'Ariana', '2080'),
            ],
        ];

        foreach ($clients as $index => $client) {
            DB::table('clients')->insert(array_merge($client, [
                'id' => $this->idFor('clients', $index + 1),
                'created_at' => now()->subMonths(rand(1, 24)),
                'updated_at' => now(),
            ]));
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  SUPPLIERS
    // ──────────────────────────────────────────────────────────────────────────

    private function seedSuppliers(): void
    {
        $suppliers = [
            [
                'supplier_id' => 'SUP-00000001',
                'legal_name' => $this->i18n('FireTech Europe GmbH', 'FireTech Europe GmbH', 'فايرتيك أوروبا'),
                'commercial_name' => $this->i18n('FireTech Europe', 'FireTech Europe', 'فايرتيك أوروبا'),
                'tax_id' => null,
                'rne_id' => null,
                'legal_status' => 'other',
                'company_size' => '201-500',
                'industry' => 'Fire Safety Equipment',
                'founding_year' => 1982,
                'website' => 'https://firetech-eu.com',
                'primary_phone' => '+49 89 1234567',
                'primary_email' => 'sales@firetech-eu.com',
                'account_manager_id' => $this->adminId(),
                'partner_since' => '2015-01-01',
                'satisfaction_score' => 4.6,
                'supplier_category' => 'manufacturer',
                'lead_time_days' => 21,
                'payment_terms' => 'net_60',
                'minimum_order_value' => 5000.000,
                'status' => 'active',
                'notes' => json_encode(['en' => 'Primary supplier for extinguishers. Annual contract negotiated every January.']),
                ...$this->hqAddress('Industriestrasse 42', 'Munich', '80339', 'Germany'),
            ],
            [
                'supplier_id' => 'SUP-00000002',
                'legal_name' => $this->i18n('MagnumParts Tunisie SARL', 'MagnumParts Tunisie SARL', 'ماغنوم بارتس تونس ش.م.م'),
                'commercial_name' => $this->i18n('MagnumParts', 'MagnumParts', 'ماغنوم بارتس'),
                'tax_id' => '2233445/A/M/000',
                'rne_id' => 'B223344556',
                'legal_status' => 'sarl',
                'company_size' => '11-50',
                'industry' => 'Industrial Parts Distribution',
                'founding_year' => 2012,
                'website' => 'https://magnumparts.tn',
                'primary_phone' => '+216 71 456 789',
                'primary_email' => 'orders@magnumparts.tn',
                'account_manager_id' => $this->secondaryAdminId(),
                'partner_since' => '2020-05-15',
                'satisfaction_score' => 3.9,
                'supplier_category' => 'distributor',
                'lead_time_days' => 7,
                'payment_terms' => 'net_30',
                'minimum_order_value' => 500.000,
                'status' => 'active',
                'notes' => json_encode(['en' => 'Local distributor. Good for urgent orders.']),
                ...$this->hqAddress('Rue Alain Savary 8', 'Tunis', '1002'),
            ],
            [
                'supplier_id' => 'SUP-00000003',
                'legal_name' => $this->i18n('GlobalSigns SNC', 'GlobalSigns SNC', 'غلوبال سايغنز ش.م.ف'),
                'commercial_name' => $this->i18n('GlobalSigns', 'GlobalSigns', 'غلوبال سايغنز'),
                'tax_id' => '6677889/B/M/000',
                'rne_id' => null,
                'legal_status' => 'snc',
                'company_size' => '1-10',
                'industry' => 'Safety Signage',
                'founding_year' => 2016,
                'website' => null,
                'primary_phone' => '+216 98 777 666',
                'primary_email' => 'globalsigns@mail.tn',
                'account_manager_id' => null,
                'partner_since' => '2023-02-01',
                'satisfaction_score' => 4.0,
                'supplier_category' => 'wholesaler',
                'lead_time_days' => 14,
                'payment_terms' => 'net_30',
                'minimum_order_value' => 200.000,
                'status' => 'active',
                'notes' => json_encode(['en' => 'Safety signs and labels only.']),
                ...$this->hqAddress('Route Mornag km 5', 'Ben Arous', '2013'),
            ],
            [
                'supplier_id' => 'SUP-00000004',
                'legal_name' => $this->i18n('ChemAgent International SA', 'ChemAgent International SA', 'كيم إيجنت إنترناسيونال ش.م'),
                'commercial_name' => $this->i18n('ChemAgent', 'ChemAgent', 'كيم أجنت'),
                'tax_id' => null,
                'rne_id' => null,
                'legal_status' => 'sa',
                'company_size' => '51-200',
                'industry' => 'Chemical Agents',
                'founding_year' => 2001,
                'website' => 'https://chemagent.fr',
                'primary_phone' => '+33 1 23 45 67 89',
                'primary_email' => 'export@chemagent.fr',
                'account_manager_id' => $this->adminId(),
                'partner_since' => '2017-08-10',
                'satisfaction_score' => 4.3,
                'supplier_category' => 'manufacturer',
                'lead_time_days' => 30,
                'payment_terms' => 'net_60',
                'minimum_order_value' => 10000.000,
                'status' => 'active',
                'notes' => json_encode(['en' => 'Dry powder and CO2 agent supplier.']),
                ...$this->hqAddress('17 Rue de la République', 'Lyon', '69001', 'France'),
            ],
        ];

        foreach ($suppliers as $index => $supplier) {
            DB::table('suppliers')->insert(array_merge($supplier, [
                'id' => $this->idFor('suppliers', $index + 1),
                'created_at' => now()->subMonths(rand(1, 36)),
                'updated_at' => now(),
            ]));
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  SERVICE PROVIDERS
    // ──────────────────────────────────────────────────────────────────────────

    private function seedServiceProviders(): void
    {
        $providers = [
            [
                'service_provider_id' => 'SRV-00000001',
                'legal_name' => $this->i18n('TechServ Solutions SARL', 'TechServ Solutions SARL', 'تيك سيرف سولوشنز ش.م.م'),
                'commercial_name' => $this->i18n('TechServ', 'TechServ', 'تيك سيرف'),
                'tax_id' => '3344556/A/P/000',
                'rne_id' => 'B334455667',
                'legal_status' => 'sarl',
                'company_size' => '11-50',
                'industry' => 'Technical Services',
                'founding_year' => 2014,
                'website' => 'https://techserv.tn',
                'primary_phone' => '+216 71 888 999',
                'primary_email' => 'ops@techserv.tn',
                'account_manager_id' => $this->adminId(),
                'partner_since' => '2020-07-01',
                'satisfaction_score' => 4.4,
                'provider_tier' => 'preferred',
                'availability_status' => 'available',
                'contract_type' => 'time_and_materials',
                'base_rate' => 180.000,
                'rate_currency' => 'TND',
                'specialties' => json_encode(['fire extinguisher maintenance', 'detection systems', 'hydro testing']),
                'service_zones' => json_encode(['Tunis', 'Ariana', 'Ben Arous', 'Manouba']),
                'certifications' => json_encode(['ISO 9001:2015', 'APSAD R1']),
                'status' => 'active',
                'notes' => json_encode(['en' => 'Preferred partner for Grand Tunis area.'], JSON_UNESCAPED_UNICODE),
                ...$this->hqAddress('Zone Industrielle La Charguia 1', 'Tunis', '2035'),
            ],
            [
                'service_provider_id' => 'SRV-00000002',
                'legal_name' => $this->i18n('Sud Maintenance Individuel', 'Sud Maintenance Individuel', 'صيانة الجنوب الفردية'),
                'commercial_name' => $this->i18n('Sud Maintenance', 'Sud Maintenance', 'صيانة الجنوب'),
                'tax_id' => null,
                'rne_id' => null,
                'legal_status' => 'individual',
                'company_size' => '1-10',
                'industry' => 'Maintenance',
                'founding_year' => 2019,
                'website' => null,
                'primary_phone' => '+216 95 123 456',
                'primary_email' => 'sud.maintenance@gmail.com',
                'account_manager_id' => null,
                'partner_since' => '2023-01-01',
                'satisfaction_score' => 3.5,
                'provider_tier' => 'standard',
                'availability_status' => 'partially_available',
                'contract_type' => 'per_mission',
                'base_rate' => 120.000,
                'rate_currency' => 'TND',
                'specialties' => json_encode(['fire extinguisher maintenance']),
                'service_zones' => json_encode(['Sfax', 'Gabès', 'Gafsa']),
                'certifications' => null,
                'status' => 'active',
                'notes' => json_encode(['en' => 'Freelancer. Coverage for southern regions only.'], JSON_UNESCAPED_UNICODE),
                ...$this->hqAddress('Cité El Habib', 'Sfax', '3052'),
            ],
        ];

        foreach ($providers as $index => $provider) {
            DB::table('service_providers')->insert(array_merge($provider, [
                'id' => $this->idFor('service_providers', $index + 1),
                'created_at' => now()->subMonths(rand(1, 24)),
                'updated_at' => now(),
            ]));
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  ORGANISATIONS
    // ──────────────────────────────────────────────────────────────────────────

    private function seedOrganisations(): void
    {
        $orgs = [
            [
                'organisation_id' => 'ORG-00000001',
                'legal_name' => $this->i18n('Office National de la Protection Civile', 'Office National de la Protection Civile', 'الديوان الوطني للحماية المدنية'),
                'commercial_name' => $this->i18n('ONPC', 'ONPC', 'المكتب الوطني للحماية المدنية'),
                'tax_id' => null,
                'rne_id' => null,
                'primary_phone' => '+216 71 111 000',
                'primary_email' => 'contact@onpc.gov.tn',
                'account_manager_id' => $this->adminId(),
                'partner_since' => '2016-01-01',
                'organisation_type' => 'government_agency',
                'registration_number' => 'GOV-2001-0042',
                'registration_date' => '2001-06-15',
                'accreditation_body' => 'Ministère de l\'Intérieur',
                'regulatory_authority' => 'Gouvernement Tunisien',
                'parent_organisation' => 'Ministère de l\'Intérieur',
                'member_count' => null,
                'scope' => 'national',
                'partnership_start_date' => '2016-01-01',
                'partnership_end_date' => null,
                'status' => 'active',
                'notes' => json_encode(['en' => 'Regulatory body for fire safety compliance.']),
                ...$this->hqAddress('Avenue Habib Bourguiba', 'Tunis', '1000'),
            ],
            [
                'organisation_id' => 'ORG-00000002',
                'legal_name' => $this->i18n('Association Tunisienne de la Sécurité Industrielle', 'Association Tunisienne de la Sécurité Industrielle', 'الجمعية التونسية للسلامة الصناعية'),
                'commercial_name' => $this->i18n('ATSI', 'ATSI', 'الجمعية التونسية للأمن الصناعي'),
                'tax_id' => null,
                'rne_id' => 'A112233445',
                'primary_phone' => '+216 71 222 333',
                'primary_email' => 'info@atsi.org.tn',
                'account_manager_id' => $this->secondaryAdminId(),
                'partner_since' => '2020-03-01',
                'organisation_type' => 'association',
                'registration_number' => 'ASSOC-2010-0155',
                'registration_date' => '2010-04-20',
                'accreditation_body' => null,
                'regulatory_authority' => null,
                'parent_organisation' => null,
                'member_count' => 320,
                'scope' => 'national',
                'partnership_start_date' => '2020-03-01',
                'partnership_end_date' => null,
                'status' => 'active',
                'notes' => json_encode(['en' => 'Industry association. Good for networking and referrals.']),
                ...$this->hqAddress('Rue Ibn Khaldoun 22', 'Tunis', '1001'),
            ],
        ];

        foreach ($orgs as $index => $org) {
            DB::table('organisations')->insert(array_merge($org, [
                'id' => $this->idFor('organisations', $index + 1),
                'created_at' => now()->subMonths(rand(3, 36)),
                'updated_at' => now(),
            ]));
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  BP ADDRESSES  (polymorphic)
    // ──────────────────────────────────────────────────────────────────────────

    private function seedBpAddresses(): void
    {
        $addresses = [
            // Client 1 – TechnoGuard
            [
                'addressable_type' => 'App\Models\Client',
                'addressable_id' => $this->idFor('clients', 1),
                'type' => 'headquarters',
                'is_primary' => true,
                'is_billing_default' => true,
                'is_shipping_default' => false,
                'label' => json_encode(['en' => 'Head Office']),
                'address_street' => json_encode(['en' => 'Rue du Lac Malaren 12', 'fr' => 'Rue du Lac Malaren 12', 'ar' => 'شارع بحيرة مالارن 12']),
                'address_street2' => null,
                'city' => json_encode(['en' => 'Tunis', 'fr' => 'Tunis', 'ar' => 'تونس']),
                'state' => null,
                'postcode' => '1053',
                'country' => $this->i18n('Tunisia', 'Tunisie', 'تونس'),
                'phone' => '+216 71 234 567',
                'email' => 'contact@technoguard.tn',
                'responsible' => 'Karim Ben Salah',
                'nearest_fire_station' => 'Caserne Les Berges du Lac',
                'work_team' => null,
                'latitude' => 36.832,
                'longitude' => 10.236,
                'notes' => null,
            ],
            // Client 1 – TechnoGuard – second site (warehouse)
            [
                'addressable_type' => 'App\Models\Client',
                'addressable_id' => $this->idFor('clients', 1),
                'type' => 'warehouse',
                'is_primary' => false,
                'is_billing_default' => false,
                'is_shipping_default' => true,
                'label' => json_encode(['en' => 'Storage Facility']),
                'address_street' => json_encode(['en' => 'Zone Industrielle Charguia 2, Lot 14', 'fr' => 'Zone Industrielle Charguia 2, Lot 14', 'ar' => 'المنطقة الصناعية شرقية 2، قطعة 14']),
                'address_street2' => null,
                'city' => json_encode(['en' => 'Tunis', 'fr' => 'Tunis', 'ar' => 'تونس']),
                'state' => null,
                'postcode' => '2035',
                'country' => $this->i18n('Tunisia', 'Tunisie', 'تونس'),
                'phone' => '+216 71 234 999',
                'email' => 'warehouse@technoguard.tn',
                'responsible' => 'Sami Trabelsi',
                'nearest_fire_station' => 'Caserne Charguia',
                'work_team' => 'Team B',
                'latitude' => 36.856,
                'longitude' => 10.213,
                'notes' => null,
            ],
            // Client 2 – SafeBuilding
            [
                'addressable_type' => 'App\Models\Client',
                'addressable_id' => $this->idFor('clients', 2),
                'type' => 'headquarters',
                'is_primary' => true,
                'is_billing_default' => true,
                'is_shipping_default' => true,
                'label' => json_encode(['en' => 'Siege Social']),
                'address_street' => json_encode(['en' => 'Avenue Habib Bourguiba 45', 'fr' => 'Avenue Habib Bourguiba 45', 'ar' => 'شارع الحبيب بورقيبة 45']),
                'address_street2' => null,
                'city' => json_encode(['en' => 'Sfax', 'fr' => 'Sfax', 'ar' => 'صفاقس']),
                'state' => null,
                'postcode' => '3000',
                'country' => $this->i18n('Tunisia', 'Tunisie', 'تونس'),
                'phone' => '+216 73 456 789',
                'email' => 'info@safebuilding.tn',
                'responsible' => 'Hédi Mejri',
                'nearest_fire_station' => 'Caserne Sfax Centre',
                'work_team' => null,
                'latitude' => 34.740,
                'longitude' => 10.760,
                'notes' => null,
            ],
            // Client 4 – ICS – operational site (ATEX)
            [
                'addressable_type' => 'App\Models\Client',
                'addressable_id' => $this->idFor('clients', 4),
                'type' => 'operational_site',
                'is_primary' => true,
                'is_billing_default' => true,
                'is_shipping_default' => true,
                'label' => json_encode(['en' => 'Plant Gabès – ATEX Zone']),
                'address_street' => json_encode(['en' => 'Zone Industrielle El Mdhilla, BP 55', 'fr' => 'Zone Industrielle El Mdhilla, BP 55', 'ar' => 'المنطقة الصناعية المذيلة، ص.ب 55']),
                'address_street2' => null,
                'city' => json_encode(['en' => 'Gabès', 'fr' => 'Gabès', 'ar' => 'قابس']),
                'state' => null,
                'postcode' => '6011',
                'country' => $this->i18n('Tunisia', 'Tunisie', 'تونس'),
                'phone' => '+216 75 666 777',
                'email' => 'plant@ics-tn.com',
                'responsible' => 'Faouzi Grami',
                'nearest_fire_station' => 'Caserne Gabès Industrielle',
                'work_team' => 'Safety Team Gabès',
                'latitude' => 33.882,
                'longitude' => 9.985,
                'notes' => null,
            ],
            // Supplier 1 – FireTech Europe
            [
                'addressable_type' => 'App\Models\Supplier',
                'addressable_id' => $this->idFor('suppliers', 1),
                'type' => 'headquarters',
                'is_primary' => true,
                'is_billing_default' => true,
                'is_shipping_default' => false,
                'label' => json_encode(['en' => 'European HQ']),
                'address_street' => json_encode(['en' => 'Industriestrasse 42', 'fr' => 'Industriestrasse 42', 'ar' => 'شارع الصناعة 42']),
                'address_street2' => null,
                'city' => json_encode(['en' => 'Munich', 'fr' => 'Munich', 'ar' => 'ميونيخ']),
                'state' => null,
                'postcode' => '80339',
                'country' => $this->i18n('Germany', 'Allemagne', 'ألمانيا'),
                'phone' => '+49 89 1234567',
                'email' => 'sales@firetech-eu.com',
                'responsible' => 'Hans Müller',
                'nearest_fire_station' => null,
                'work_team' => null,
                'latitude' => 48.137,
                'longitude' => 11.576,
                'notes' => null,
            ],
            // Service Provider 1
            [
                'addressable_type' => 'App\Models\ServiceProvider',
                'addressable_id' => $this->idFor('service_providers', 1),
                'type' => 'headquarters',
                'is_primary' => true,
                'is_billing_default' => true,
                'is_shipping_default' => false,
                'label' => json_encode(['en' => 'Main Office']),
                'address_street' => json_encode(['en' => 'Zone Industrielle La Charguia 1, Bât. C', 'fr' => 'Zone Industrielle La Charguia 1, Bât. C', 'ar' => 'المنطقة الصناعية الشرقية 1، مبنى ج']),
                'address_street2' => null,
                'city' => json_encode(['en' => 'Tunis', 'fr' => 'Tunis', 'ar' => 'تونس']),
                'state' => null,
                'postcode' => '2035',
                'country' => $this->i18n('Tunisia', 'Tunisie', 'تونس'),
                'phone' => '+216 71 888 999',
                'email' => 'ops@techserv.tn',
                'responsible' => 'Yassine Hammami',
                'nearest_fire_station' => null,
                'work_team' => null,
                'latitude' => 36.855,
                'longitude' => 10.212,
                'notes' => null,
            ],
        ];

        foreach ($addresses as $index => $address) {
            DB::table('bp_addresses')->insert(array_merge($address, [
                'id' => $this->idFor('bp_addresses', $index + 1),
                'created_at' => now()->subMonths(rand(1, 20)),
                'updated_at' => now(),
            ]));
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  BP CONTACTS  (polymorphic)
    // ──────────────────────────────────────────────────────────────────────────

    private function seedBpContacts(): void
    {
        $contacts = [
            // Client 1 – TechnoGuard
            [
                'contactable_type' => 'App\Models\Client',
                'contactable_id' => $this->idFor('clients', 1),
                'first_name' => $this->i18n('Karim', 'Karim', 'كريم'),
                'last_name' => $this->i18n('Ben Salah', 'Ben Salah', 'بن صالح'),
                'gender' => 'male',
                'job_title' => 'Directeur Général',
                'department' => 'Direction',
                'phone' => '+216 71 234 567',
                'mobile' => '+216 98 111 222',
                'whatsapp_number' => '+216 98 111 222',
                'email' => 'k.bensalah@technoguard.tn',
                'linkedin_url' => 'https://linkedin.com/in/karim-ben-salah',
                'is_primary' => true,
                'is_decision_maker' => true,
                'status' => 'active',
                'notes' => $this->i18n('CEO. Available after 10am.'),
            ],
            [
                'contactable_type' => 'App\Models\Client',
                'contactable_id' => $this->idFor('clients', 1),
                'first_name' => $this->i18n('Sonia', 'Sonia', 'سونيا'),
                'last_name' => $this->i18n('Mansouri', 'Mansouri', 'منصوري'),
                'gender' => 'female',
                'job_title' => 'Responsable Achats',
                'department' => 'Achats',
                'phone' => '+216 71 234 568',
                'mobile' => '+216 97 333 444',
                'whatsapp_number' => null,
                'email' => 's.mansouri@technoguard.tn',
                'linkedin_url' => null,
                'is_primary' => false,
                'is_decision_maker' => false,
                'status' => 'active',
                'notes' => $this->i18n('Main procurement contact.'),
            ],
            // Client 2 – SafeBuilding
            [
                'contactable_type' => 'App\Models\Client',
                'contactable_id' => $this->idFor('clients', 2),
                'first_name' => $this->i18n('Hédi', 'Hédi', 'هادي'),
                'last_name' => $this->i18n('Mejri', 'Mejri', 'مجري'),
                'gender' => 'male',
                'job_title' => 'PDG',
                'department' => 'Direction',
                'phone' => '+216 73 456 789',
                'mobile' => '+216 98 555 666',
                'whatsapp_number' => '+216 98 555 666',
                'email' => 'h.mejri@safebuilding.tn',
                'linkedin_url' => null,
                'is_primary' => true,
                'is_decision_maker' => true,
                'status' => 'active',
                'notes' => $this->i18n('Decision maker.'),
            ],
            // Client 4 – ICS
            [
                'contactable_type' => 'App\Models\Client',
                'contactable_id' => $this->idFor('clients', 4),
                'first_name' => $this->i18n('Faouzi', 'Faouzi', 'فوزي'),
                'last_name' => $this->i18n('Grami', 'Grami', 'غرامي'),
                'gender' => 'male',
                'job_title' => 'HSE Manager',
                'department' => 'HSE',
                'phone' => '+216 75 666 777',
                'mobile' => '+216 99 123 456',
                'whatsapp_number' => '+216 99 123 456',
                'email' => 'f.grami@ics-tn.com',
                'linkedin_url' => null,
                'is_primary' => true,
                'is_decision_maker' => true,
                'status' => 'active',
                'notes' => $this->i18n('HSE approvals required for all interventions.'),
            ],
            // Supplier 1
            [
                'contactable_type' => 'App\Models\Supplier',
                'contactable_id' => $this->idFor('suppliers', 1),
                'first_name' => $this->i18n('Hans', 'Hans', 'هانس'),
                'last_name' => $this->i18n('Müller', 'Müller', 'مولر'),
                'gender' => 'male',
                'job_title' => 'Export Sales Manager',
                'department' => 'Sales',
                'phone' => '+49 89 1234567',
                'mobile' => '+49 171 9876543',
                'whatsapp_number' => null,
                'email' => 'h.muller@firetech-eu.com',
                'linkedin_url' => null,
                'is_primary' => true,
                'is_decision_maker' => true,
                'status' => 'active',
                'notes' => $this->i18n('Export contact. Responds within 48h.'),
            ],
            // Supplier 2
            [
                'contactable_type' => 'App\Models\Supplier',
                'contactable_id' => $this->idFor('suppliers', 2),
                'first_name' => $this->i18n('Mourad', 'Mourad', 'مراد'),
                'last_name' => $this->i18n('Khelifi', 'Khelifi', 'خليفي'),
                'gender' => 'male',
                'job_title' => 'Commercial',
                'department' => 'Sales',
                'phone' => '+216 71 456 789',
                'mobile' => '+216 92 777 888',
                'whatsapp_number' => '+216 92 777 888',
                'email' => 'm.khelifi@magnumparts.tn',
                'linkedin_url' => null,
                'is_primary' => true,
                'is_decision_maker' => false,
                'status' => 'active',
                'notes' => $this->i18n('Quick response via WhatsApp.'),
            ],
        ];

        foreach ($contacts as $index => $contact) {
            DB::table('bp_contacts')->insert(array_merge($contact, [
                'id' => $this->idFor('bp_contacts', $index + 1),
                'created_at' => now()->subMonths(rand(1, 18)),
                'updated_at' => now(),
            ]));
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  BP CONTACT ↔ ADDRESS  (pivot)
    // ──────────────────────────────────────────────────────────────────────────

    private function seedBpContactAddresses(): void
    {
        // contact_id → address_id mappings (both 1-indexed from above inserts)
        $links = [
            ['bp_contact_id' => $this->idFor('bp_contacts', 1), 'bp_address_id' => $this->idFor('bp_addresses', 1), 'is_primary_location' => true],
            ['bp_contact_id' => $this->idFor('bp_contacts', 2), 'bp_address_id' => $this->idFor('bp_addresses', 2), 'is_primary_location' => false],
            ['bp_contact_id' => $this->idFor('bp_contacts', 2), 'bp_address_id' => $this->idFor('bp_addresses', 1), 'is_primary_location' => true],
            ['bp_contact_id' => $this->idFor('bp_contacts', 3), 'bp_address_id' => $this->idFor('bp_addresses', 3), 'is_primary_location' => true],
            ['bp_contact_id' => $this->idFor('bp_contacts', 4), 'bp_address_id' => $this->idFor('bp_addresses', 4), 'is_primary_location' => true],
            ['bp_contact_id' => $this->idFor('bp_contacts', 5), 'bp_address_id' => $this->idFor('bp_addresses', 5), 'is_primary_location' => true],
            ['bp_contact_id' => $this->idFor('bp_contacts', 6), 'bp_address_id' => $this->idFor('bp_addresses', 5), 'is_primary_location' => false],
        ];

        foreach ($links as $link) {
            DB::table('bp_contact_address')->insert(array_merge($link, [
                'id' => (string) Str::ulid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  BP BANK ACCOUNTS  (polymorphic)
    // ──────────────────────────────────────────────────────────────────────────

    private function seedBpBankAccounts(): void
    {
        $accounts = [
            // Client 1
            [
                'bankable_type' => 'App\Models\Client',
                'bankable_id' => $this->idFor('clients', 1),
                'bank_name' => 'Banque Nationale Agricole',
                'bank_code' => '08',
                'branch' => 'Agence Les Berges du Lac',
                'account_holder' => 'TechnoGuard SARL',
                'rib' => '08 006 0123456789 45',
                'iban' => 'TN59 0800 6012 3456 7894 5',
                'swift_bic' => 'BNATNTT1',
                'account_number' => '0123456789',
                'currency' => 'TND',
                'is_primary' => true,
                'is_verified' => true,
                'usage' => 'both',
                'notes' => null,
            ],
            // Client 2
            [
                'bankable_type' => 'App\Models\Client',
                'bankable_id' => $this->idFor('clients', 2),
                'bank_name' => 'Attijari Bank',
                'bank_code' => '11',
                'branch' => 'Agence Sfax Centre',
                'account_holder' => 'SafeBuilding SA',
                'rib' => '11 001 0987654321 67',
                'iban' => null,
                'swift_bic' => 'BSTUTNTT',
                'account_number' => '0987654321',
                'currency' => 'TND',
                'is_primary' => true,
                'is_verified' => true,
                'usage' => 'both',
                'notes' => null,
            ],
            // Supplier 1 (EUR account)
            [
                'bankable_type' => 'App\Models\Supplier',
                'bankable_id' => $this->idFor('suppliers', 1),
                'bank_name' => 'Deutsche Bank',
                'bank_code' => '700',
                'branch' => 'Munich Isartor',
                'account_holder' => 'FireTech Europe GmbH',
                'rib' => null,
                'iban' => 'DE89 3704 0044 0532 0130 00',
                'swift_bic' => 'DEUTDEDB',
                'account_number' => '532013000',
                'currency' => 'EUR',
                'is_primary' => true,
                'is_verified' => true,
                'usage' => 'payments',
                'notes' => json_encode(['en' => 'Wire transfers only. Min. 5 business days processing.'], JSON_UNESCAPED_UNICODE),
            ],
            // Supplier 2
            [
                'bankable_type' => 'App\Models\Supplier',
                'bankable_id' => $this->idFor('suppliers', 2),
                'bank_name' => 'BIAT',
                'bank_code' => '14',
                'branch' => 'Agence Lafayette',
                'account_holder' => 'MagnumParts Tunisie SARL',
                'rib' => '14 002 1122334455 22',
                'iban' => null,
                'swift_bic' => 'BIATTNTT',
                'account_number' => '1122334455',
                'currency' => 'TND',
                'is_primary' => true,
                'is_verified' => false,
                'usage' => 'payments',
                'notes' => json_encode(['en' => 'Awaiting IBAN verification.'], JSON_UNESCAPED_UNICODE),
            ],
        ];

        foreach ($accounts as $index => $account) {
            DB::table('bp_bank_accounts')->insert(array_merge($account, [
                'id' => $this->idFor('bp_bank_accounts', $index + 1),
                'created_at' => now()->subMonths(rand(1, 20)),
                'updated_at' => now(),
            ]));
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  BP VAT CERTIFICATES  (polymorphic)
    // ──────────────────────────────────────────────────────────────────────────

    private function seedBpVatCertificates(): void
    {
        $certs = [
            [
                'certifiable_type' => 'App\Models\Client',
                'certifiable_id' => $this->idFor('clients', 1),
                'certificate_number' => 'CERT-TN-2024-001234',
                'issuing_authority' => 'Direction des Impôts',
                'issue_date' => '2024-01-15',
                'expiration_date' => '2025-01-14',
                'status' => 'active',
                'document_path' => 'bp_docs/clients/1/vat_cert_2024.pdf',
                'notes' => null,
            ],
            [
                'certifiable_type' => 'App\Models\Client',
                'certifiable_id' => $this->idFor('clients', 2),
                'certificate_number' => 'CERT-TN-2023-009876',
                'issuing_authority' => 'Direction des Impôts – Sfax',
                'issue_date' => '2023-03-01',
                'expiration_date' => '2024-02-29',
                'status' => 'expired',
                'document_path' => null,
                'notes' => 'Renewal pending.',
            ],
            [
                'certifiable_type' => 'App\Models\Client',
                'certifiable_id' => $this->idFor('clients', 4),
                'certificate_number' => 'CERT-TN-2024-005500',
                'issuing_authority' => 'Direction des Impôts – Gabès',
                'issue_date' => '2024-06-01',
                'expiration_date' => '2025-05-31',
                'status' => 'active',
                'document_path' => 'bp_docs/clients/4/vat_cert_2024.pdf',
                'notes' => null,
            ],
            [
                'certifiable_type' => 'App\Models\Supplier',
                'certifiable_id' => $this->idFor('suppliers', 2),
                'certificate_number' => 'CERT-TN-2024-007711',
                'issuing_authority' => 'Direction des Impôts',
                'issue_date' => '2024-04-01',
                'expiration_date' => '2025-03-31',
                'status' => 'active',
                'document_path' => 'bp_docs/suppliers/2/vat_cert_2024.pdf',
                'notes' => null,
            ],
        ];

        foreach ($certs as $index => $cert) {
            DB::table('bp_vat_certificates')->insert(array_merge($cert, [
                'id' => $this->idFor('bp_vat_certificates', $index + 1),
                'created_at' => now()->subMonths(rand(1, 18)),
                'updated_at' => now(),
            ]));
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  BP DOCUMENTS  (polymorphic)
    // ──────────────────────────────────────────────────────────────────────────

    private function seedBpDocuments(): void
    {
        $docs = [
            [
                'documentable_type' => 'App\Models\Client',
                'documentable_id' => $this->idFor('clients', 1),
                'uploaded_by' => $this->adminId(),
                'verified_by' => $this->adminId(),
                'type' => 'contract',
                'title' => json_encode(['en' => 'Master Service Agreement 2024', 'fr' => 'Contrat Cadre 2024', 'ar' => 'عقد الإطار 2024']),
                'reference_number' => 'CTR-2024-001',
                'issue_date' => '2024-01-01',
                'effective_date' => '2024-01-01',
                'expiration_date' => '2024-12-31',
                'auto_renewable' => true,
                'status' => 'active',
                'is_verified' => true,
                'file_path' => 'bp_docs/clients/1/msa_2024.pdf',
                'file_name' => 'msa_2024.pdf',
                'mime_type' => 'application/pdf',
                'file_size_bytes' => 204800,
                'notes' => json_encode(['en' => 'Signed by both parties.']),
            ],
            [
                'documentable_type' => 'App\Models\Client',
                'documentable_id' => $this->idFor('clients', 4),
                'uploaded_by' => $this->secondaryAdminId(),
                'verified_by' => null,
                'type' => 'compliance_certificate',
                'title' => json_encode(['en' => 'ATEX Zone Compliance Certificate', 'fr' => 'Certificat de Conformité Zone ATEX', 'ar' => 'شهادة الامتثال لمنطقة ATEX']),
                'reference_number' => 'ATEX-2024-ICS-001',
                'issue_date' => '2024-02-10',
                'effective_date' => '2024-02-10',
                'expiration_date' => '2026-02-09',
                'auto_renewable' => false,
                'status' => 'active',
                'is_verified' => false,
                'file_path' => 'bp_docs/clients/4/atex_cert_2024.pdf',
                'file_name' => 'atex_cert_2024.pdf',
                'mime_type' => 'application/pdf',
                'file_size_bytes' => 512000,
                'notes' => null,
            ],
            [
                'documentable_type' => 'App\Models\Supplier',
                'documentable_id' => $this->idFor('suppliers', 1),
                'uploaded_by' => $this->adminId(),
                'verified_by' => $this->adminId(),
                'type' => 'nda',
                'title' => json_encode(['en' => 'Non-Disclosure Agreement', 'fr' => 'Accord de Confidentialité', 'ar' => 'اتفاقية عدم الإفصاح']),
                'reference_number' => 'NDA-2023-SUP001',
                'issue_date' => '2023-06-01',
                'effective_date' => '2023-06-01',
                'expiration_date' => null,
                'auto_renewable' => false,
                'status' => 'active',
                'is_verified' => true,
                'file_path' => 'bp_docs/suppliers/1/nda.pdf',
                'file_name' => 'nda.pdf',
                'mime_type' => 'application/pdf',
                'file_size_bytes' => 153600,
                'notes' => null,
            ],
            [
                'documentable_type' => 'App\Models\Supplier',
                'documentable_id' => $this->idFor('suppliers', 2),
                'uploaded_by' => $this->adminId(),
                'verified_by' => null,
                'type' => 'tax_id',
                'title' => json_encode(['en' => 'Fiscal Card', 'fr' => 'Carte Fiscale', 'ar' => 'البطاقة الجبائية']),
                'reference_number' => null,
                'issue_date' => '2023-12-01',
                'effective_date' => '2024-01-01',
                'expiration_date' => '2024-12-31',
                'auto_renewable' => true,
                'status' => 'active',
                'is_verified' => false,
                'file_path' => 'bp_docs/suppliers/2/carte_fiscale.pdf',
                'file_name' => 'carte_fiscale.pdf',
                'mime_type' => 'application/pdf',
                'file_size_bytes' => 51200,
                'notes' => null,
            ],
        ];

        foreach ($docs as $index => $doc) {
            DB::table('bp_documents')->insert(array_merge($doc, [
                'id' => $this->idFor('bp_documents', $index + 1),
                'created_at' => now()->subMonths(rand(1, 18)),
                'updated_at' => now(),
            ]));
        }
    }
}
