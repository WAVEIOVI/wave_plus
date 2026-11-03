<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CatalogSeeder — Wave Plus / SS Plus
 *
 * Covers ALL catalog tables in dependency order:
 *   catalog_categories → catalog_brands → units_of_measure
 *   → catalog_items (product|consumable|eq_servicing)
 *   → catalog_products / catalog_consumables / catalog_eq_servicing  (1-to-1 extensions)
 *   → catalog_options → catalog_option_values
 *   → catalog_variants → catalog_variant_option_values
 *   → servicing_consumables
 *   → guidelines → guideline_steps
 *   → equipment_blueprints
 *   → blueprint_options → blueprint_option_values
 *   → blueprint_intervention_cycles
 *   → blueprint_servicing → blueprint_servicing_consumables
 *   → blueprint_guideline
 *
 * NOTE: catalog_item_suppliers is skipped — it requires the `suppliers` table
 *       which belongs to the Supply-Chain module. Add a SupplierSeeder later
 *       and call seedItemSuppliers() from there.
 *
 * Usage:
 *   php artisan db:seed --class=CatalogSeeder
 *   php artisan migrate:fresh --seed   (if registered in DatabaseSeeder)
 */
class CatalogSeeder extends Seeder
{
    // ──────────────────────────────────────────────────────────────────────────
    // State bags (ulid → label) for cross-section references
    // ──────────────────────────────────────────────────────────────────────────
    private array $cats = [];   // key → ulid

    private array $brands = [];   // key → ulid

    private array $uoms = [];   // code → ulid

    private array $items = [];   // key → ulid  (all catalog_items)

    private array $variants = [];   // key → ulid

    private array $ovIds = [];   // "option_key.value_key" → ulid  (catalog_option_values)

    private array $bpOvIds = [];   // "bp_key.optCode.valCode" → ulid  (blueprint_option_values)

    private array $bpOptionIds = []; // "bp_key.optCode" → ulid (blueprint_options)

    private array $guides = [];   // key → ulid

    private array $bps = [];   // key → ulid  (equipment_blueprints)

    private array $bpSvcs = [];   // "bp_key.svc_key" → ulid  (blueprint_servicing)

    // ──────────────────────────────────────────────────────────────────────────
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        $this->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->seedCategories();
        $this->seedBrands();
        $this->seedUnitsOfMeasure();
        $this->seedProducts();
        $this->seedConsumables();
        $this->seedEqServicing();
        $this->seedOptionsAndVariants();
        $this->seedServicingConsumables();
        $this->seedGuidelines();
        $this->seedEquipmentBlueprints();
        $this->ensureDefaultVariants();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────────────────
    private function ulid(): string
    {
        return (string) Str::ulid();
    }

    private function i18n(string $en, string $fr, string $ar): string
    {
        return json_encode(['en' => $en, 'fr' => $fr, 'ar' => $ar], JSON_UNESCAPED_UNICODE);
    }

    private function now(): string
    {
        return Carbon::now()->toDateTimeString();
    }

    private function truncate(): void
    {
        $tables = [
            'blueprint_guideline',
            'blueprint_servicing_consumables',
            'blueprint_servicing',
            'blueprint_intervention_cycles',
            'blueprint_option_values',
            'blueprint_options',
            'equipment_blueprints',
            'guideline_steps',
            'guidelines',
            'servicing_consumables',
            'catalog_variant_option_values',
            'catalog_variants',
            'catalog_option_values',
            'catalog_options',
            'catalog_eq_servicing',
            'catalog_consumables',
            'catalog_products',
            'catalog_items',
            'units_of_measure',
            'catalog_brands',
            'catalog_categories',
        ];
        foreach ($tables as $t) {
            DB::table($t)->truncate();
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. CATEGORIES  (self-referential tree)
    // ──────────────────────────────────────────────────────────────────────────
    private function seedCategories(): void
    {
        $now = $this->now();

        // ── Root categories ───────────────────────────────────────────────────
        $roots = [
            'fire' => [$this->i18n('Fire Protection', 'Protection Incendie', 'حماية من الحريق'),       json_encode(['product', 'eq_servicing']), '🔥', 1],
            'ppe' => [$this->i18n('Personal Protective Equipment', 'EPI', 'معدات الحماية الشخصية'), json_encode(['product']),                 '🦺', 2],
            'consumables' => [$this->i18n('Consumables & Spare Parts', 'Consommables & Pièces', 'المواد الاستهلاكية'),    json_encode(['consumable']),              '🔩', 3],
            'services' => [$this->i18n('Maintenance Services', 'Services de Maintenance', 'خدمات الصيانة'),         json_encode(['eq_servicing']),            '🛠️', 4],
        ];

        foreach ($roots as $key => [$name, $scope, $icon, $sort]) {
            $id = $this->ulid();
            DB::table('catalog_categories')->insert([
                'id' => $id,
                'name' => $name,
                'parent_id' => null,
                'item_type_scope' => $scope,
                'icon' => $icon,
                'sort_order' => $sort,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->cats[$key] = $id;
        }

        // ── Children ─────────────────────────────────────────────────────────
        $children = [
            // key, parent_key, en, fr, ar, scope, icon, sort
            ['extinguishers',  'fire',        'Extinguishers',              'Extincteurs',                     'طفايات الحريق',             ['product'],              '🧯', 1],
            ['detection',      'fire',        'Detection Systems',          'Systèmes de Détection',           'أنظمة الكشف',               ['product'],              '🚨', 2],
            ['suppression',    'fire',        'Suppression Systems',        'Systèmes de Suppression',         'أنظمة الإطفاء',             ['product'],              '💧', 3],
            ['signage',        'fire',        'Safety Signage',             'Signalétique Sécurité',           'لافتات السلامة',            ['product', 'consumable'], '⚠️', 4],
            ['resp_prot',      'ppe',         'Respiratory Protection',     'Protection Respiratoire',         'حماية الجهاز التنفسي',      ['product'],              '😷', 1],
            ['hand_prot',      'ppe',         'Hand Protection',            'Protection des Mains',            'حماية اليدين',              ['product'],              '🧤', 2],
            ['head_prot',      'ppe',         'Head Protection',            'Protection de la Tête',           'حماية الرأس',               ['product'],              '⛑️', 3],
            ['eye_prot',       'ppe',         'Eye & Face Protection',      'Protection Yeux & Visage',        'حماية العينين والوجه',      ['product'],              '🥽', 4],
            ['agents',         'consumables', 'Extinguishing Agents',       'Agents Extincteurs',              'عوامل الإطفاء',             ['consumable'],           '🫙', 1],
            ['parts',          'consumables', 'Replacement Parts',          'Pièces de Rechange',              'قطع الغيار',                ['consumable'],           '⚙️', 2],
            ['insp_test',      'services',    'Inspection & Testing',       'Inspection & Test',               'الفحص والاختبار',           ['eq_servicing'],         '🔍', 1],
            ['refill_svc',     'services',    'Refill & Recharge',          'Recharge & Remplissage',          'إعادة الشحن',               ['eq_servicing'],         '🔋', 2],
            ['maintenance',    'services',    'Maintenance & Repair',       'Maintenance & Réparation',        'الصيانة والإصلاح',          ['eq_servicing'],         '🔧', 3],
        ];

        foreach ($children as [$key, $parentKey, $en, $fr, $ar, $scope, $icon, $sort]) {
            $id = $this->ulid();
            DB::table('catalog_categories')->insert([
                'id' => $id,
                'name' => $this->i18n($en, $fr, $ar),
                'parent_id' => $this->cats[$parentKey],
                'item_type_scope' => json_encode($scope),
                'icon' => $icon,
                'sort_order' => $sort,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->cats[$key] = $id;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. BRANDS
    // ──────────────────────────────────────────────────────────────────────────
    private function seedBrands(): void
    {
        $now = $this->now();

        $brands = [
            // key, name, code, country, warranty_notes
            ['sicli',    'Sicli',    'SICLI',    'FR', 'Garantie 2 ans pièces et main-d\'œuvre. SAV France.'],
            ['gloria',   'Gloria',   'GLORIA',   'DE', 'Garantie 5 ans sur le corps de l\'extincteur.'],
            ['desautel', 'Desautel', 'DESAUTEL', 'FR', 'Garantie 2 ans. Réseau de distributeurs agréés.'],
            ['amerex',   'Amerex',   'AMEREX',   'US', 'Garantie limitée à vie sur les corps en acier inoxydable.'],
            ['hochiki',  'Hochiki',  'HOCHIKI',  'JP', 'Garantie 2 ans. Support technique disponible.'],
            ['notifier', 'Notifier', 'NOTIFIER', 'US', 'Garantie 1 an pièces. Certification EN54.'],
            ['delta',    'DeltaPlus', 'DELTAPLUS', 'FR', 'EPI certifiés CE. Conformité EN ISO 20345.'],
            ['ansell',   'Ansell',   'ANSELL',   'AU', 'Gants certifiés EN388/EN374. Garantie fabricant 12 mois.'],
            ['3m',       '3M',       '3M',       'US', 'Conformité EN149 pour appareils respiratoires.'],
        ];

        foreach ($brands as [$key, $name, $code, $country, $warranty]) {
            $id = $this->ulid();
            DB::table('catalog_brands')->insert([
                'id' => $id,
                'name' => $name,
                'code' => $code,
                'country_of_origin' => $country,
                'warranty_policy_notes' => $warranty,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->brands[$key] = $id;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. UNITS OF MEASURE
    // ──────────────────────────────────────────────────────────────────────────
    private function seedUnitsOfMeasure(): void
    {
        $now = $this->now();

        $uoms = [
            // code, type, en, fr, ar
            ['PCE',     'count',   'Piece',    'Pièce',   'قطعة'],
            ['KG',      'mass',    'Kilogram', 'Kilogramme', 'كيلوغرام'],
            ['G',       'mass',    'Gram',     'Gramme',  'غرام'],
            ['L',       'volume',  'Litre',    'Litre',   'لتر'],
            ['ML',      'volume',  'Millilitre', 'Millilitre', 'ميليليتر'],
            ['M',       'length',  'Metre',    'Mètre',   'متر'],
            ['MM',      'length',  'Millimetre', 'Millimètre', 'ميليمتر'],
            ['HR',      'service', 'Hour',     'Heure',   'ساعة'],
            ['SESSION', 'service', 'Session',  'Session', 'جلسة'],
            ['DAY',     'service', 'Day',      'Jour',    'يوم'],
            ['SET',     'count',   'Set',      'Jeu',     'مجموعة'],
            ['PAIR',    'count',   'Pair',     'Paire',   'زوج'],
        ];

        foreach ($uoms as [$code, $type, $en, $fr, $ar]) {
            $id = $this->ulid();
            DB::table('units_of_measure')->insert([
                'id' => $id,
                'code' => $code,
                'name' => $this->i18n($en, $fr, $ar),
                'type' => $type,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->uoms[$code] = $id;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. CATALOG ITEMS  — type: product  +  catalog_products extension
    // ──────────────────────────────────────────────────────────────────────────
    private function seedProducts(): void
    {
        $now = $this->now();

        // ── Physical products ─────────────────────────────────────────────────
        $physicals = [
            // [key, code, name_en/fr/ar, cat_key, brand_key, uom, tags, certif, status, warranty_months,
            //   physical_type, capacity_value, capacity_unit, material, expiry_years, technical_specs]
            [
                'ext_abc_6kg',
                'EXT-ABC-6KG',
                ['Dry Powder ABC Extinguisher 6kg', 'Extincteur Poudre ABC 6kg', 'طفاية مسحوق جاف ABC 6كغ'],
                'extinguishers',
                'sicli',
                'PCE',
                ['extincteur', 'abc', 'poudre', 'portable'],
                ['CE', 'EN3', 'NF', 'CNPP'],
                'active',
                24,
                'extinguisher',
                6.00,
                'kg',
                'Acier galvanisé',
                20,
                ['pressure_type' => 'stored_pressure', 'agent' => 'ABC powder', 'fire_class' => 'A/B/C', 'working_pressure_bar' => 14, 'test_pressure_bar' => 21],
            ],
            [
                'ext_abc_9kg',
                'EXT-ABC-9KG',
                ['Dry Powder ABC Extinguisher 9kg', 'Extincteur Poudre ABC 9kg', 'طفاية مسحوق جاف ABC 9كغ'],
                'extinguishers',
                'sicli',
                'PCE',
                ['extincteur', 'abc', 'poudre', 'portable'],
                ['CE', 'EN3', 'NF'],
                'active',
                24,
                'extinguisher',
                9.00,
                'kg',
                'Acier galvanisé',
                20,
                ['pressure_type' => 'stored_pressure', 'agent' => 'ABC powder', 'fire_class' => 'A/B/C', 'working_pressure_bar' => 14, 'test_pressure_bar' => 21],
            ],
            [
                'ext_co2_2kg',
                'EXT-CO2-2KG',
                ['CO₂ Extinguisher 2kg', 'Extincteur CO₂ 2kg', 'طفاية ثاني أكسيد الكربون 2كغ'],
                'extinguishers',
                'gloria',
                'PCE',
                ['extincteur', 'co2', 'carbone', 'electrique'],
                ['CE', 'EN3', 'TÜV'],
                'active',
                24,
                'extinguisher',
                2.00,
                'kg',
                'Aluminium anodisé',
                15,
                ['pressure_type' => 'liquefied_gas', 'agent' => 'CO2', 'fire_class' => 'B/C/E', 'fill_pressure_bar' => 60],
            ],
            [
                'ext_co2_5kg',
                'EXT-CO2-5KG',
                ['CO₂ Extinguisher 5kg', 'Extincteur CO₂ 5kg', 'طفاية ثاني أكسيد الكربون 5كغ'],
                'extinguishers',
                'gloria',
                'PCE',
                ['extincteur', 'co2', 'carbone'],
                ['CE', 'EN3', 'TÜV'],
                'active',
                24,
                'extinguisher',
                5.00,
                'kg',
                'Aluminium anodisé',
                15,
                ['pressure_type' => 'liquefied_gas', 'agent' => 'CO2', 'fire_class' => 'B/C/E', 'fill_pressure_bar' => 60],
            ],
            [
                'ext_eau_9l',
                'EXT-EAU-9L',
                ['Water Mist Extinguisher 9L', 'Extincteur Eau Pulvérisée 9L', 'طفاية بالماء المرذوذ 9 لتر'],
                'extinguishers',
                'desautel',
                'PCE',
                ['extincteur', 'eau', 'water-mist'],
                ['CE', 'EN3'],
                'active',
                24,
                'extinguisher',
                9.00,
                'L',
                'Acier inoxydable',
                20,
                ['pressure_type' => 'stored_pressure', 'agent' => 'water_with_additive', 'fire_class' => 'A/F', 'working_pressure_bar' => 12],
            ],
            [
                'ext_roue_50kg',
                'EXT-ROUE-50KG',
                ['Wheeled Extinguisher ABC 50kg', 'Extincteur sur Roues ABC 50kg', 'طفاية على عجلات ABC 50كغ'],
                'extinguishers',
                'amerex',
                'PCE',
                ['extincteur', 'roues', 'industrie'],
                ['CE', 'EN3', 'UL'],
                'active',
                24,
                'wheeled_extinguisher',
                50.00,
                'kg',
                'Acier galvanisé',
                20,
                ['pressure_type' => 'stored_pressure', 'agent' => 'ABC powder', 'fire_class' => 'A/B/C', 'working_pressure_bar' => 14],
            ],
            [
                'det_fumee',
                'DET-FUMEE-OPT',
                ['Optical Smoke Detector', 'Détecteur de Fumée Optique', 'كاشف الدخان الضوئي'],
                'detection',
                'hochiki',
                'PCE',
                ['detecteur', 'fumee', 'optique', 'adressable'],
                ['CE', 'EN54-7'],
                'active',
                12,
                'detector',
                null,
                null,
                'Polycarbonate ABS',
                null,
                ['protocol' => 'addressable', 'sensitivity' => '0.1 dB/m', 'supply_voltage' => '17-30V DC', 'ip_rating' => 'IP44'],
            ],
            [
                'det_chaleur',
                'DET-CHALEUR-FIX',
                ['Fixed Temperature Heat Detector', 'Détecteur Thermique Fixe 57°C', 'كاشف الحرارة الثابت 57 درجة'],
                'detection',
                'hochiki',
                'PCE',
                ['detecteur', 'chaleur', 'thermique'],
                ['CE', 'EN54-5'],
                'active',
                12,
                'detector',
                null,
                null,
                'Polycarbonate',
                null,
                ['trip_temperature_c' => 57, 'protocol' => 'addressable', 'supply_voltage' => '17-30V DC'],
            ],
            [
                'cdi_8z',
                'CDI-8Z-NOTIF',
                ['Fire Detection Panel 8 Zones', 'Centrale Détection Incendie 8 Zones', 'مركزية كشف الحريق 8 مناطق'],
                'detection',
                'notifier',
                'PCE',
                ['centrale', 'detection', 'incendie', 'panel'],
                ['CE', 'EN54-2', 'EN54-4', 'NF'],
                'active',
                24,
                'detection_panel',
                null,
                null,
                'Acier laqué',
                null,
                ['zones' => 8, 'supply' => '230V AC / 24V DC', 'battery_backup_hours' => 72, 'protocol' => 'conventional'],
            ],
            [
                'rob_incendie',
                'ROB-INC-19MM',
                ['19mm Fire Hose Reel', 'Robinet Incendie Armé Ø19mm', 'حنفية حريق مسلحة قطر 19ملم'],
                'suppression',
                'desautel',
                'PCE',
                ['robinet', 'hose-reel', 'armoire'],
                ['CE', 'EN671-1', 'NF'],
                'active',
                12,
                'hose_reel',
                null,
                null,
                'Acier / Laiton',
                null,
                ['hose_diameter_mm' => 19, 'hose_length_m' => 30, 'min_pressure_bar' => 3, 'max_pressure_bar' => 12],
            ],
            // ── PPE ────────────────────────────────────────────────────────
            [
                'casque_sec',
                'PPE-CASQUE-EN397',
                ['Safety Helmet EN397', 'Casque de Sécurité EN397', 'خوذة السلامة EN397'],
                'head_prot',
                'delta',
                'PCE',
                ['casque', 'ppe', 'tete'],
                ['CE', 'EN397'],
                'active',
                12,
                'ppe',
                null,
                null,
                'HDPE',
                null,
                ['standard' => 'EN397', 'voltage_class' => '1000V AC', 'temp_range' => '-20°C / +50°C'],
            ],
            [
                'gants_coupure',
                'PPE-GANTS-EN388',
                ['Anti-Cut Gloves EN388 Level C', 'Gants Anti-Coupure EN388 Niveau C', 'قفازات مقاومة للقطع EN388 مستوى C'],
                'hand_prot',
                'ansell',
                'PAIR',
                ['gants', 'ppe', 'coupure'],
                ['CE', 'EN388:2016'],
                'active',
                12,
                'ppe',
                null,
                null,
                'HPPE / Nitrile',
                null,
                ['cut_level' => 'C', 'standard' => 'EN388:2016', 'grip' => 'Nitrile foam palm'],
            ],
            [
                'masque_ffp2',
                'PPE-MASQUE-FFP2',
                ['FFP2 Disposable Respirator', 'Masque Respiratoire FFP2', 'قناع تنفسي FFP2'],
                'resp_prot',
                '3m',
                'PCE',
                ['masque', 'ffp2', 'respiratoire'],
                ['CE', 'EN149:2001+A1:2009'],
                'active',
                12,
                'ppe',
                null,
                null,
                'Polypropylène non tissé',
                null,
                ['filter_class' => 'FFP2', 'standard' => 'EN149:2001+A1:2009', 'valve' => false, 'filtration_efficiency' => '94%'],
            ],
            [
                'lunettes_sec',
                'PPE-LUNETTES-EN166',
                ['Safety Glasses EN166', 'Lunettes de Sécurité EN166', 'نظارات السلامة EN166'],
                'eye_prot',
                'delta',
                'PCE',
                ['lunettes', 'ppe', 'yeux'],
                ['CE', 'EN166:2002'],
                'active',
                12,
                'ppe',
                null,
                null,
                'Polycarbonate / Nylon',
                null,
                ['lens_material' => 'Polycarbonate', 'uv_protection' => '2C-1.2', 'standard' => 'EN166:2002'],
            ],
        ];

        foreach ($physicals as $row) {
            [
                $key,
                $code,
                [$en, $fr, $ar],
                $catKey,
                $brandKey,
                $uomCode,
                $tags,
                $certs,
                $status,
                $warranty,
                $physType,
                $capVal,
                $capUnit,
                $material,
                $expiryYrs,
                $techSpecs
            ] = $row;

            $id = $this->ulid();
            DB::table('catalog_items')->insert([
                'id' => $id,
                'code' => $code,
                'barcode' => null,
                'item_type' => 'product',
                'name' => $this->i18n($en, $fr, $ar),
                'short_description' => $this->i18n(
                    substr($en, 0, 80),
                    substr($fr, 0, 80),
                    substr($ar, 0, 80),
                ),
                'category_id' => $this->cats[$catKey],
                'brand_id' => $this->brands[$brandKey],
                'uom_id' => $this->uoms[$uomCode],
                'is_stockable' => true,
                'is_purchasable' => true,
                'is_sellable' => true,
                'tags' => json_encode($tags),
                'certifications' => json_encode($certs),
                'preferred_supplier_id' => null,
                'warranty_months' => $warranty,
                'internal_notes' => null,
                'status' => $status,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);

            DB::table('catalog_products')->insert([
                'catalog_item_id' => $id,
                'product_type' => 'physical',
                'physical_type' => $physType,
                'capacity_value' => $capVal,
                'capacity_unit' => $capUnit,
                'material' => $material,
                'expiry_years' => $expiryYrs,
                'technical_specs' => json_encode($techSpecs, JSON_UNESCAPED_UNICODE),
                'digital_type' => null,
                'delivery_method' => null,
                'is_downloadable' => false,
                'service_type' => null,
                'billable_unit' => null,
                'duration_minutes' => null,
                'max_participants' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->items[$key] = $id;
        }

        // ── Digital products ──────────────────────────────────────────────────
        $digitals = [
            // key, code, en/fr/ar, cat_key, status, digital_type, delivery, specs
            [
                'cert_conformite',
                'DIG-CERT-CONF',
                ['Compliance Certificate (Digital)', 'Certificat de Conformité Numérique', 'شهادة المطابقة الرقمية'],
                'fire',
                'active',
                'compliance_certificate',
                'portal_download',
                ['format' => 'PDF/A', 'validity_months' => 12, 'includes_qr' => true],
            ],
            [
                'rapport_inspection',
                'DIG-RAPP-INSP',
                ['Digital Inspection Report', 'Rapport d\'Inspection Numérique', 'تقرير الفحص الرقمي'],
                'fire',
                'active',
                'inspection_report',
                'portal_download',
                ['format' => 'PDF', 'auto_generated' => true],
            ],
            [
                'formation_video',
                'DIG-FORM-VIDEO',
                ['Fire Safety Training Video', 'Vidéo Formation Sécurité Incendie', 'فيديو تدريب السلامة من الحريق'],
                'fire',
                'active',
                'training_video',
                'portal_download',
                ['format' => 'MP4', 'duration_minutes' => 45, 'language' => 'fr'],
            ],
        ];

        foreach ($digitals as [$key, $code, [$en, $fr, $ar], $catKey, $status, $digType, $delivery, $specs]) {
            $id = $this->ulid();
            DB::table('catalog_items')->insert([
                'id' => $id,
                'code' => $code,
                'barcode' => null,
                'item_type' => 'product',
                'name' => $this->i18n($en, $fr, $ar),
                'short_description' => null,
                'category_id' => $this->cats[$catKey],
                'brand_id' => null,
                'uom_id' => $this->uoms['PCE'],
                'is_stockable' => false,
                'is_purchasable' => false,
                'is_sellable' => true,
                'tags' => json_encode(['digital', 'document']),
                'certifications' => null,
                'preferred_supplier_id' => null,
                'warranty_months' => null,
                'internal_notes' => null,
                'status' => $status,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
            DB::table('catalog_products')->insert([
                'catalog_item_id' => $id,
                'product_type' => 'digital',
                'physical_type' => null,
                'capacity_value' => null,
                'capacity_unit' => null,
                'material' => null,
                'expiry_years' => null,
                'technical_specs' => json_encode($specs),
                'digital_type' => $digType,
                'delivery_method' => $delivery,
                'is_downloadable' => true,
                'service_type' => null,
                'billable_unit' => null,
                'duration_minutes' => null,
                'max_participants' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->items[$key] = $id;
        }

        // ── Services (generic) ────────────────────────────────────────────────
        $services = [
            // key, code, en/fr/ar, cat_key, service_type, billable_unit, duration_min, max_part
            [
                'form_incendie',
                'SVC-FORM-INC',
                ['Fire Safety Training Session', 'Formation Sécurité Incendie', 'دورة تدريب السلامة من الحريق'],
                'fire',
                'training',
                'per_session',
                180,
                20,
            ],
            [
                'audit_securite',
                'SVC-AUDIT-SEC',
                ['Fire Safety Audit', 'Audit de Sécurité Incendie', 'تدقيق السلامة من الحريق'],
                'fire',
                'safety_audit',
                'per_day',
                480,
                null,
            ],
            [
                'consultation',
                'SVC-CONSULT',
                ['Safety Consultation', 'Consultation Sécurité', 'استشارة السلامة'],
                'fire',
                'consultation',
                'per_hour',
                60,
                null,
            ],
        ];

        foreach ($services as [$key, $code, [$en, $fr, $ar], $catKey, $svcType, $billUnit, $duration, $maxPart]) {
            $id = $this->ulid();
            DB::table('catalog_items')->insert([
                'id' => $id,
                'code' => $code,
                'barcode' => null,
                'item_type' => 'product',
                'name' => $this->i18n($en, $fr, $ar),
                'short_description' => null,
                'category_id' => $this->cats[$catKey],
                'brand_id' => null,
                'uom_id' => $this->uoms['SESSION'],
                'is_stockable' => false,
                'is_purchasable' => false,
                'is_sellable' => true,
                'tags' => json_encode(['service', 'formation']),
                'certifications' => null,
                'preferred_supplier_id' => null,
                'warranty_months' => null,
                'internal_notes' => null,
                'status' => 'active',
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
            DB::table('catalog_products')->insert([
                'catalog_item_id' => $id,
                'product_type' => 'service',
                'physical_type' => null,
                'capacity_value' => null,
                'capacity_unit' => null,
                'material' => null,
                'expiry_years' => null,
                'technical_specs' => null,
                'digital_type' => null,
                'delivery_method' => null,
                'is_downloadable' => false,
                'service_type' => $svcType,
                'billable_unit' => $billUnit,
                'duration_minutes' => $duration,
                'max_participants' => $maxPart,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->items[$key] = $id;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. CATALOG ITEMS — type: consumable  +  catalog_consumables extension
    // ──────────────────────────────────────────────────────────────────────────
    private function seedConsumables(): void
    {
        $now = $this->now();

        $consumables = [
            // key, code, en/fr/ar, cat_key, brand_key, uom, tags, certs, cons_type,
            //   is_consumable, shelf_life_months, storage_conditions
            [
                'poudre_abc_6',
                'CONS-PD-ABC-6KG',
                ['ABC Dry Powder 6kg', 'Poudre Sèche ABC 6kg', 'مسحوق جاف ABC 6كغ'],
                'agents',
                'sicli',
                'KG',
                ['poudre', 'abc', 'agent', 'refill'],
                ['EN615'],
                'agents_and_disposables',
                true,
                60,
                'Lieu sec et frais, température 0°C–50°C, humidité < 60%',
            ],
            [
                'poudre_abc_9',
                'CONS-PD-ABC-9KG',
                ['ABC Dry Powder 9kg', 'Poudre Sèche ABC 9kg', 'مسحوق جاف ABC 9كغ'],
                'agents',
                'sicli',
                'KG',
                ['poudre', 'abc', 'agent', 'refill'],
                ['EN615'],
                'agents_and_disposables',
                true,
                60,
                'Lieu sec et frais, température 0°C–50°C, humidité < 60%',
            ],
            [
                'co2_recharge_2',
                'CONS-CO2-2KG',
                ['CO₂ Refill 2kg', 'CO₂ Recharge 2kg', 'إعادة شحن CO₂ 2كغ'],
                'agents',
                null,
                'KG',
                ['co2', 'agent', 'gaz', 'refill'],
                ['EN13745'],
                'agents_and_disposables',
                true,
                null,
                'Stocker debout, loin des sources de chaleur (< 50°C).',
            ],
            [
                'co2_recharge_5',
                'CONS-CO2-5KG',
                ['CO₂ Refill 5kg', 'CO₂ Recharge 5kg', 'إعادة شحن CO₂ 5كغ'],
                'agents',
                null,
                'KG',
                ['co2', 'agent', 'gaz', 'refill'],
                ['EN13745'],
                'agents_and_disposables',
                true,
                null,
                'Stocker debout, loin des sources de chaleur (< 50°C).',
            ],
            [
                'joint_orng',
                'CONS-JOINT-ORNG',
                ['O-Ring Seal Kit (Universal)', 'Kit Joints Toriques (Universel)', 'طقم حلقات مانعة للتسرب (عالمي)'],
                'parts',
                null,
                'SET',
                ['joint', 'orng', 'etancheite', 'spare-part'],
                [],
                'replacement_parts',
                true,
                24,
                'À l\'abri de la lumière UV, dans un sachet hermétique.',
            ],
            [
                'goupille_inox',
                'CONS-GOUPILLE-INX',
                ['Stainless Steel Safety Pin', 'Goupille de Sécurité Inox', 'مسمار الأمان الفولاذي'],
                'parts',
                null,
                'PCE',
                ['goupille', 'securite', 'inox', 'spare-part'],
                [],
                'replacement_parts',
                true,
                null,
                'Standard.',
            ],
            [
                'etiq_extincteur',
                'CONS-ETIQ-EXT',
                ['Extinguisher Service Label', 'Étiquette de Visite Extincteur', 'ملصق خدمة الطفاية'],
                'signage',
                null,
                'PCE',
                ['etiquette', 'signalisation', 'label'],
                [],
                'safety_signage',
                true,
                12,
                'À l\'abri de la chaleur et de l\'humidité.',
            ],
            [
                'mousse_afff',
                'CONS-MOUSSE-AFFF-20L',
                ['AFFF Foam Concentrate 20L', 'Concentré Mousse AFFF 20L', 'مركز رغوة AFFF 20 لتر'],
                'agents',
                null,
                'L',
                ['mousse', 'afff', 'agent', 'foam'],
                ['EN1568-3'],
                'agents_and_disposables',
                true,
                60,
                'Stocker entre 5°C et 30°C, dans un lieu sec et ventilé.',
            ],
            [
                'pile_9v_det',
                'CONS-PILE-9V',
                ['9V Alkaline Battery (Detector)', 'Pile Alcaline 9V (Détecteur)', 'بطارية قلوية 9 فولت (كاشف)'],
                'parts',
                null,
                'PCE',
                ['pile', 'batterie', 'detecteur', 'accessoire'],
                [],
                'accessories',
                true,
                36,
                'Stocker à température ambiante.',
            ],
        ];

        foreach ($consumables as $row) {
            [
                $key,
                $code,
                [$en, $fr, $ar],
                $catKey,
                $brandKey,
                $uomCode,
                $tags,
                $certs,
                $consType,
                $isCons,
                $shelfLife,
                $storage
            ] = $row;

            $id = $this->ulid();
            DB::table('catalog_items')->insert([
                'id' => $id,
                'code' => $code,
                'barcode' => null,
                'item_type' => 'consumable',
                'name' => $this->i18n($en, $fr, $ar),
                'short_description' => null,
                'category_id' => $this->cats[$catKey],
                'brand_id' => $brandKey ? $this->brands[$brandKey] : null,
                'uom_id' => $this->uoms[$uomCode],
                'is_stockable' => true,
                'is_purchasable' => true,
                'is_sellable' => false,
                'tags' => json_encode($tags),
                'certifications' => json_encode($certs),
                'preferred_supplier_id' => null,
                'warranty_months' => null,
                'internal_notes' => null,
                'status' => 'active',
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);

            DB::table('catalog_consumables')->insert([
                'catalog_item_id' => $id,
                'consumable_type' => $consType,
                'is_consumable' => $isCons,
                'shelf_life_months' => $shelfLife,
                'storage_conditions' => $storage,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->items[$key] = $id;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. CATALOG ITEMS — type: eq_servicing  +  catalog_eq_servicing extension
    // ──────────────────────────────────────────────────────────────────────────
    private function seedEqServicing(): void
    {
        $now = $this->now();

        $servicing = [
            // key, code, en/fr/ar, cat_key, uom, tags, eq_svc_type, delivery_mode, billable_unit,
            //   duration_min, is_recurring, required_certs
            [
                'svc_inspection_ext',
                'SVC-INSP-EXT',
                ['Annual Extinguisher Inspection', 'Inspection Annuelle Extincteur', 'فحص الطفاية السنوي'],
                'insp_test',
                'PCE',
                ['inspection', 'extincteur', 'annuelle', 'maintenance'],
                'inspection_and_testing',
                'on_site',
                'per_unit',
                20,
                true,
                ['Technicien Certifié EN3', 'Carte professionnelle'],
            ],
            [
                'svc_recharge_abc',
                'SVC-RECH-ABC',
                ['ABC Powder Refill Service', 'Recharge Poudre ABC', 'خدمة إعادة شحن مسحوق ABC'],
                'refill_svc',
                'PCE',
                ['recharge', 'poudre', 'abc', 'workshop'],
                'refill_recharge',
                'workshop',
                'per_unit',
                30,
                false,
                ['Technicien Certifié Recharge'],
            ],
            [
                'svc_recharge_co2',
                'SVC-RECH-CO2',
                ['CO₂ Recharge Service', 'Recharge CO₂', 'خدمة إعادة شحن CO₂'],
                'refill_svc',
                'PCE',
                ['recharge', 'co2', 'gaz', 'workshop'],
                'refill_recharge',
                'workshop',
                'per_unit',
                25,
                false,
                ['Technicien Certifié Recharge', 'Habilitation gaz sous pression'],
            ],
            [
                'svc_hydro_test',
                'SVC-HYDRO-TEST',
                ['Hydrostatic Pressure Test', 'Test Hydrostatique Corps Extincteur', 'اختبار الضغط الهيدروستاتيكي'],
                'insp_test',
                'PCE',
                ['hydrostatique', 'test', 'pression', 'workshop'],
                'inspection_and_testing',
                'workshop',
                'per_unit',
                45,
                false,
                ['Technicien certifié épreuve hydraulique', 'Banc d\'essai homologué'],
            ],
            [
                'svc_maint_cdi',
                'SVC-MAINT-CDI',
                ['Fire Detection Panel Maintenance', 'Maintenance Centrale Détection Incendie', 'صيانة مركزية كشف الحريق'],
                'maintenance',
                'SESSION',
                ['maintenance', 'centrale', 'detection', 'electronique'],
                'maintenance_and_repair',
                'on_site',
                'per_session',
                120,
                true,
                ['Technicien SIEMENS / Notifier agréé'],
            ],
            [
                'svc_calib_det',
                'SVC-CALIB-DET',
                ['Detector Calibration & Certification', 'Calibration & Certification Détecteur', 'معايرة وشهادة الكاشف'],
                'insp_test',
                'PCE',
                ['calibration', 'certification', 'detecteur'],
                'calibration_and_certification',
                'on_site',
                'per_unit',
                15,
                true,
                ['Technicien certifié EN54'],
            ],
            [
                'svc_maint_ria',
                'SVC-MAINT-RIA',
                ['Fire Hose Reel Maintenance', 'Maintenance Robinet Incendie Armé', 'صيانة حنفية الحريق المسلحة'],
                'maintenance',
                'PCE',
                ['maintenance', 'ria', 'robinet', 'tuyau'],
                'maintenance_and_repair',
                'on_site',
                'per_unit',
                30,
                true,
                ['Technicien certifié'],
            ],
            [
                'svc_install_ext',
                'SVC-INST-EXT',
                ['Extinguisher Installation', 'Installation Extincteur (avec support)', 'تركيب الطفاية (مع حامل)'],
                'maintenance',
                'PCE',
                ['installation', 'extincteur', 'support'],
                'installation_decommissioning',
                'on_site',
                'per_unit',
                20,
                false,
                [],
            ],
        ];

        foreach ($servicing as $row) {
            [
                $key,
                $code,
                [$en, $fr, $ar],
                $catKey,
                $uomCode,
                $tags,
                $eqSvcType,
                $deliveryMode,
                $billUnit,
                $duration,
                $isRecurring,
                $requiredCerts
            ] = $row;

            $id = $this->ulid();
            DB::table('catalog_items')->insert([
                'id' => $id,
                'code' => $code,
                'barcode' => null,
                'item_type' => 'eq_servicing',
                'name' => $this->i18n($en, $fr, $ar),
                'short_description' => null,
                'category_id' => $this->cats[$catKey],
                'brand_id' => null,
                'uom_id' => $this->uoms[$uomCode],
                'is_stockable' => false,
                'is_purchasable' => false,
                'is_sellable' => true,
                'tags' => json_encode($tags),
                'certifications' => null,
                'preferred_supplier_id' => null,
                'warranty_months' => null,
                'internal_notes' => null,
                'status' => 'active',
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);

            DB::table('catalog_eq_servicing')->insert([
                'catalog_item_id' => $id,
                'eq_servicing_type' => $eqSvcType,
                'delivery_mode' => $deliveryMode,
                'billable_unit' => $billUnit,
                'duration_minutes' => $duration,
                'is_recurring' => $isRecurring,
                'required_certifications' => json_encode($requiredCerts),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->items[$key] = $id;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. OPTIONS, OPTION VALUES, VARIANTS, VARIANT↔OPTION_VALUE PIVOT
    // ──────────────────────────────────────────────────────────────────────────
    private function seedOptionsAndVariants(): void
    {
        $now = $this->now();

        // Define options per product: item_key => [ option_label => [values...] ]
        $optionDefs = [
            'ext_abc_6kg' => [
                'Capacité / Capacity / السعة' => ['6 kg'],
                'Type Pression / Pressure / الضغط' => ['Pression Stockée / Stored Pressure / ضغط مخزن'],
            ],
            'ext_abc_9kg' => [
                'Capacité / Capacity / السعة' => ['9 kg'],
                'Type Pression / Pressure / الضغط' => ['Pression Stockée / Stored Pressure / ضغط مخزن'],
            ],
            'ext_co2_2kg' => [
                'Capacité / Capacity / السعة' => ['2 kg', '5 kg'],
            ],
            'det_fumee' => [
                'Alimentation / Power / التغذية' => ['Pile 9V / Battery 9V / بطارية 9 فولت', '230V Filaire / Wired / سلكي 230 فولت'],
            ],
            'casque_sec' => [
                'Couleur / Colour / اللون' => ['Blanc / White / أبيض', 'Jaune / Yellow / أصفر', 'Rouge / Red / أحمر', 'Bleu / Blue / أزرق'],
                'Taille / Size / الحجم' => ['Universelle / Universal / عالمي'],
            ],
            'gants_coupure' => [
                'Taille / Size / الحجم' => ['S (7)', 'M (8)', 'L (9)', 'XL (10)'],
            ],
        ];

        // Build options and their value IDs
        $optionValueMap = []; // item_key => [ option_label => [ value_label => ulid ] ]

        foreach ($optionDefs as $itemKey => $options) {
            $itemId = $this->items[$itemKey];
            $sort = 0;

            foreach ($options as $optLabel => $values) {
                $parts = explode(' / ', $optLabel);
                $optId = $this->ulid();
                DB::table('catalog_options')->insert([
                    'id' => $optId,
                    'catalog_item_id' => $itemId,
                    'name' => $this->i18n($parts[1] ?? $parts[0], $parts[0], $parts[2] ?? $parts[0]),
                    'sort_order' => $sort++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $vSort = 0;
                foreach ($values as $val) {
                    $vParts = explode(' / ', $val);
                    $valId = $this->ulid();
                    DB::table('catalog_option_values')->insert([
                        'id' => $valId,
                        'option_id' => $optId,
                        'value' => $this->i18n($vParts[1] ?? $vParts[0], $vParts[0], $vParts[2] ?? $vParts[0]),
                        'sort_order' => $vSort++,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $optionValueMap[$itemKey][$optLabel][$val] = $valId;
                }
            }
        }

        // ── Variants ──────────────────────────────────────────────────────────
        //  item_key => [ [variant_name, sku, price, cost, stock, min_stock, status, is_default, optionValueKeys[]] ]
        $variantDefs = [
            'ext_abc_6kg' => [
                [
                    'ABC 6kg Standard',
                    'EXT-ABC-6KG-STD',
                    185.000,
                    110.000,
                    50,
                    10,
                    'active',
                    true,
                    ['Capacité / Capacity / السعة' => '6 kg', 'Type Pression / Pressure / الضغط' => 'Pression Stockée / Stored Pressure / ضغط مخزن']
                ],
            ],
            'ext_abc_9kg' => [
                [
                    'ABC 9kg Standard',
                    'EXT-ABC-9KG-STD',
                    245.000,
                    145.000,
                    30,
                    5,
                    'active',
                    true,
                    ['Capacité / Capacity / السعة' => '9 kg', 'Type Pression / Pressure / الضغط' => 'Pression Stockée / Stored Pressure / ضغط مخزن']
                ],
            ],
            'ext_co2_2kg' => [
                [
                    'CO₂ 2kg',
                    'EXT-CO2-2KG-VAR',
                    210.000,
                    125.000,
                    25,
                    5,
                    'active',
                    true,
                    ['Capacité / Capacity / السعة' => '2 kg']
                ],
                [
                    'CO₂ 5kg',
                    'EXT-CO2-5KG-VAR',
                    385.000,
                    230.000,
                    15,
                    3,
                    'active',
                    false,
                    ['Capacité / Capacity / السعة' => '5 kg']
                ],
            ],
            'det_fumee' => [
                [
                    'Détecteur Pile 9V',
                    'DET-FUM-PILE',
                    45.000,
                    22.000,
                    80,
                    20,
                    'active',
                    true,
                    ['Alimentation / Power / التغذية' => 'Pile 9V / Battery 9V / بطارية 9 فولت']
                ],
                [
                    'Détecteur 230V',
                    'DET-FUM-230V',
                    68.000,
                    35.000,
                    60,
                    15,
                    'active',
                    false,
                    ['Alimentation / Power / التغذية' => '230V Filaire / Wired / سلكي 230 فولت']
                ],
            ],
            'casque_sec' => [
                [
                    'Casque Blanc',
                    'CASQUE-BLA',
                    18.500,
                    9.000,
                    100,
                    20,
                    'active',
                    true,
                    ['Couleur / Colour / اللون' => 'Blanc / White / أبيض', 'Taille / Size / الحجم' => 'Universelle / Universal / عالمي']
                ],
                [
                    'Casque Jaune',
                    'CASQUE-JAU',
                    18.500,
                    9.000,
                    80,
                    20,
                    'active',
                    false,
                    ['Couleur / Colour / اللون' => 'Jaune / Yellow / أصفر', 'Taille / Size / الحجم' => 'Universelle / Universal / عالمي']
                ],
                [
                    'Casque Rouge',
                    'CASQUE-RGE',
                    18.500,
                    9.000,
                    50,
                    10,
                    'active',
                    false,
                    ['Couleur / Colour / اللون' => 'Rouge / Red / أحمر', 'Taille / Size / الحجم' => 'Universelle / Universal / عالمي']
                ],
                [
                    'Casque Bleu',
                    'CASQUE-BLE',
                    18.500,
                    9.000,
                    60,
                    10,
                    'active',
                    false,
                    ['Couleur / Colour / اللون' => 'Bleu / Blue / أزرق', 'Taille / Size / الحجم' => 'Universelle / Universal / عالمي']
                ],
            ],
            'gants_coupure' => [
                [
                    'Gants Anti-Coupure S',
                    'GANTS-C-S',
                    12.000,
                    6.000,
                    40,
                    10,
                    'active',
                    false,
                    ['Taille / Size / الحجم' => 'S (7)']
                ],
                [
                    'Gants Anti-Coupure M',
                    'GANTS-C-M',
                    12.000,
                    6.000,
                    60,
                    15,
                    'active',
                    true,
                    ['Taille / Size / الحجم' => 'M (8)']
                ],
                [
                    'Gants Anti-Coupure L',
                    'GANTS-C-L',
                    12.000,
                    6.000,
                    60,
                    15,
                    'active',
                    false,
                    ['Taille / Size / الحجم' => 'L (9)']
                ],
                [
                    'Gants Anti-Coupure XL',
                    'GANTS-C-XL',
                    12.000,
                    6.000,
                    30,
                    10,
                    'active',
                    false,
                    ['Taille / Size / الحجم' => 'XL (10)']
                ],
            ],
        ];

        // Items WITHOUT options — insert a single default variant
        $simpleVariants = [
            // item_key, sku, price, cost, stock, min_stock
            ['ext_eau_9l',      'EXT-EAU-9L-STD',   220.000, 130.000, 20,  5],
            ['ext_roue_50kg',   'EXT-ROUE-50KG-STD', 1850.000, 1100.000, 5, 2],
            ['cdi_8z',          'CDI-8Z-STD',        1200.000, 700.000, 8,  2],
            ['rob_incendie',    'ROB-INC-STD',        380.000, 210.000, 12, 3],
            ['det_chaleur',     'DET-CHAL-STD',        55.000,  28.000, 40, 10],
            ['masque_ffp2',     'MASQUE-FFP2-STD',     2.500,   1.200, 500, 100],
            ['lunettes_sec',    'LUN-SEC-STD',          8.900,   4.200, 120, 30],
            ['poudre_abc_6',    'PD-ABC-6KG-STD',      12.000,   6.000, 200, 50],
            ['poudre_abc_9',    'PD-ABC-9KG-STD',      16.000,   8.000, 150, 40],
            ['co2_recharge_2',  'CO2-2KG-STD',         18.000,   9.000, 80,  20],
            ['co2_recharge_5',  'CO2-5KG-STD',         38.000,  19.000, 40,  10],
            ['joint_orng',      'JOINT-ORNG-STD',       3.500,   1.500, 500, 100],
            ['goupille_inox',   'GOUPIN-INX-STD',       1.200,   0.500, 1000, 200],
            ['etiq_extincteur', 'ETIQ-EXT-STD',         0.350,   0.100, 2000, 500],
            ['mousse_afff',     'MOUSSE-AFFF-20L-STD', 85.000,  45.000, 30,  5],
            ['pile_9v_det',     'PILE-9V-STD',           1.800,   0.800, 300, 50],
        ];

        foreach ($simpleVariants as [$itemKey, $sku, $price, $cost, $stock, $minStock]) {
            if (! isset($this->items[$itemKey])) {
                continue;
            }
            $varId = $this->ulid();
            DB::table('catalog_variants')->insert([
                'id' => $varId,
                'catalog_item_id' => $this->items[$itemKey],
                'is_default' => true,
                'name' => null,
                'sku' => $sku,
                'barcode' => null,
                'price' => $price,
                'cost_price' => $cost,
                'markup' => round(($price - $cost) / $cost, 4),
                'tax_class' => 'standard_19pct',
                'min_stock_level' => $minStock,
                'reorder_point' => $minStock * 1.5,
                'reorder_qty' => $minStock * 2,
                'lead_time_days' => 7,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->variants[$itemKey . '.default'] = $varId;
        }

        // Variants WITH options
        foreach ($variantDefs as $itemKey => $variants) {
            foreach ($variants as $varDef) {
                [$varName, $sku, $price, $cost, $stock, $minStock, $status, $isDefault, $selectedOptions] = $varDef;
                $varId = $this->ulid();
                DB::table('catalog_variants')->insert([
                    'id' => $varId,
                    'catalog_item_id' => $this->items[$itemKey],
                    'is_default' => $isDefault,
                    'name' => $varName,
                    'sku' => $sku,
                    'barcode' => null,
                    'price' => $price,
                    'cost_price' => $cost,
                    'markup' => round(($price - $cost) / $cost, 4),
                    'tax_class' => 'standard_19pct',
                    'min_stock_level' => $minStock,
                    'reorder_point' => $minStock * 1.5,
                    'reorder_qty' => $minStock * 2,
                    'lead_time_days' => 7,
                    'status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Link variant to its option values
                foreach ($selectedOptions as $optLabel => $valLabel) {
                    $valId = $optionValueMap[$itemKey][$optLabel][$valLabel] ?? null;
                    if ($valId) {
                        DB::table('catalog_variant_option_values')->insert([
                            'variant_id' => $varId,
                            'option_value_id' => $valId,
                        ]);
                    }
                }

                $this->variants[$itemKey . '.' . $sku] = $varId;
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8. SERVICING_CONSUMABLES  (eq_servicing ↔ consumable catalog_items)
    // ──────────────────────────────────────────────────────────────────────────
    private function seedServicingConsumables(): void
    {
        $now = $this->now();

        // [servicing_key, consumable_key, quantity, unit]
        $links = [
            ['svc_recharge_abc',  'poudre_abc_6',    6.0,  'kg'],
            ['svc_recharge_abc',  'joint_orng',       1.0,  'set'],
            ['svc_recharge_abc',  'goupille_inox',    1.0,  'pce'],
            ['svc_recharge_abc',  'etiq_extincteur',  1.0,  'pce'],
            ['svc_recharge_co2',  'co2_recharge_2',   2.0,  'kg'],
            ['svc_recharge_co2',  'joint_orng',       1.0,  'set'],
            ['svc_recharge_co2',  'etiq_extincteur',  1.0,  'pce'],
            ['svc_inspection_ext', 'etiq_extincteur',  1.0,  'pce'],
            ['svc_inspection_ext', 'joint_orng',       1.0,  'set'],
            ['svc_inspection_ext', 'goupille_inox',    1.0,  'pce'],
            ['svc_hydro_test',    'joint_orng',       1.0,  'set'],
            ['svc_hydro_test',    'etiq_extincteur',  1.0,  'pce'],
            ['svc_calib_det',     'pile_9v_det',      1.0,  'pce'],
        ];

        foreach ($links as [$svcKey, $consKey, $qty, $unit]) {
            DB::table('servicing_consumables')->insert([
                'id' => $this->ulid(),
                'servicing_id' => $this->items[$svcKey],
                'catalog_item_id' => $this->items[$consKey],
                'quantity' => $qty,
                'unit' => $unit,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 9. GUIDELINES + GUIDELINE STEPS
    // ──────────────────────────────────────────────────────────────────────────
    private function seedGuidelines(): void
    {
        $now = $this->now();

        $guidelines = [
            [
                'guide_insp_ext',
                'GL-INSP-EXT-001',
                ['Annual Extinguisher Inspection Procedure', 'Procédure Inspection Annuelle Extincteur', 'إجراء الفحص السنوي للطفاية'],
                ['Step-by-step inspection guideline per EN3 and local regulations.', 'Procédure de vérification pas-à-pas conforme EN3 et réglementation locale.', 'دليل الفحص خطوة بخطوة وفق EN3 والأنظمة المحلية.'],
                true,
                [
                    // [sort, en_inst, fr_inst, ar_inst, step_type, is_required, expected_value]
                    [1,  'Check the extinguisher location is accessible and clearly marked.',  'Vérifier l\'accessibilité et le marquage de l\'emplacement.',  'التحقق من إمكانية الوصول إلى موقع الطفاية وعلاماتها.',  'boolean', true,  'true'],
                    [2,  'Inspect the body for corrosion, dents or visible damage.',          'Inspecter le corps pour corrosion, bosses ou dommages visibles.', 'فحص الجسم للتآكل والخدوش والأضرار الظاهرة.',              'boolean', true,  'true'],
                    [3,  'Check the safety pin is in place and tamper seal is intact.',       'Vérifier la goupille de sécurité et l\'indicateur anti-effraction.', 'التحقق من مسمار الأمان وسلامة ختم التلاعب.',           'boolean', true,  'true'],
                    [4,  'Read the pressure gauge — must be in green zone.',                  'Lire le manomètre — doit être dans la zone verte.',               'قراءة مقياس الضغط — يجب أن يكون في المنطقة الخضراء.',    'boolean', true,  'true'],
                    [5,  'Weigh the extinguisher and record the reading (kg).',               'Peser l\'extincteur et noter la lecture (kg).',                   'وزن الطفاية وتسجيل القراءة (كغ).',                       'numeric', true,  null],
                    [6,  'Check the nozzle / hose for blockage or damage.',                  'Vérifier la buse / tuyau pour obstruction ou dommages.',          'فحص الفوهة/الخرطوم للانسداد أو الأضرار.',                'boolean', true,  'true'],
                    [7,  'Inspect the operation label and replace if unreadable.',            'Inspecter l\'étiquette opératoire et remplacer si illisible.',    'فحص ملصق التشغيل واستبداله إذا كان غير مقروء.',           'boolean', true,  'true'],
                    [8,  'Affix new service sticker with date, technician name and next due.', 'Apposer un nouvel autocollant de visite avec date, nom tech et prochaine échéance.', 'لصق ملصق خدمة جديد بالتاريخ واسم الفني وموعد الزيارة القادمة.', 'boolean', true, 'true'],
                    [9,  'Take a photo of the extinguisher after inspection.',                'Prendre une photo de l\'extincteur après vérification.',           'التقاط صورة للطفاية بعد الفحص.',                          'photo',   true,  null],
                    [10, 'Enter any remarks or observations.',                                'Saisir les remarques ou observations.',                            'إدخال أي ملاحظات أو مشاهدات.',                           'text',    false, null],
                ],
            ],
            [
                'guide_rech_abc',
                'GL-RECH-ABC-001',
                ['ABC Powder Refill Procedure', 'Procédure de Recharge Poudre ABC', 'إجراء إعادة شحن مسحوق ABC'],
                ['Workshop refill procedure for ABC dry powder extinguishers.', 'Procédure de recharge atelier pour extincteurs poudre ABC.', 'إجراء إعادة الشحن في الورشة لطفايات مسحوق ABC.'],
                true,
                [
                    [1,  'Confirm extinguisher type and agent type before starting.',       'Confirmer le type d\'extincteur et d\'agent avant de commencer.',  'تأكيد نوع الطفاية ونوع العامل قبل البدء.',                   'boolean', true,  'true'],
                    [2,  'Depressurise the cylinder completely in a safe area.',            'Dépressuriser complètement le cylindre dans une zone sécurisée.',  'تفريغ ضغط الأسطوانة بالكامل في منطقة آمنة.',                'boolean', true,  'true'],
                    [3,  'Disassemble: remove valve, siphon tube and dip tube.',           'Démonter : retirer la vanne, le siphon et le tube plongeur.',      'تفكيك: إزالة الصمام وأنبوب الشفط وأنبوب الغوص.',            'boolean', true,  'true'],
                    [4,  'Clean and inspect the body interior and all components.',         'Nettoyer et inspecter l\'intérieur du corps et tous les composants.', 'تنظيف وفحص الجسم الداخلي وجميع المكونات.',               'boolean', true,  'true'],
                    [5,  'Replace O-rings and seals (use new parts from kit).',             'Remplacer les joints toriques (utiliser les pièces neuves du kit).', 'استبدال الحلقات المانعة (استخدام قطع جديدة من الطقم).',   'boolean', true,  'true'],
                    [6,  'Weigh the correct quantity of powder and fill the cylinder.',     'Peser la quantité correcte de poudre et remplir le cylindre.',     'وزن الكمية الصحيحة من المسحوق وملء الأسطوانة.',             'numeric', true,  null],
                    [7,  'Reassemble the valve and tighten to the correct torque (Nm).',   'Remonter la vanne et serrer au couple correct (Nm).',              'إعادة تركيب الصمام وتثبيته بالعزم الصحيح (نيوتن.متر).',    'numeric', true,  null],
                    [8,  'Pressurise with dry nitrogen to specified working pressure.',     'Pressuriser à l\'azote sec à la pression de travail spécifiée.',   'ضغط بالنيتروجين الجاف إلى ضغط العمل المحدد.',               'numeric', true,  null],
                    [9,  'Leak test: apply soapy water to all joints. No bubbles.',        'Test d\'étanchéité : appliquer de l\'eau savonneuse. Aucune bulle.', 'اختبار التسريب: تطبيق الماء الصابوني. لا فقاعات.',          'boolean', true,  'true'],
                    [10, 'Install new safety pin and tamper seal.',                         'Installer la nouvelle goupille de sécurité et le scellé anti-effraction.', 'تركيب مسمار الأمان الجديد وختم التلاعب.',           'boolean', true,  'true'],
                    [11, 'Affix new service label with date, agent weight and next refill.', 'Apposer nouvelle étiquette service avec date, masse agent et prochaine recharge.', 'لصق ملصق خدمة جديد مع التاريخ ووزن العامل.',  'boolean', true,  'true'],
                    [12, 'Photograph the completed extinguisher.',                          'Photographier l\'extincteur terminé.',                             'التقاط صورة للطفاية بعد الإنجاز.',                          'photo',   true,  null],
                ],
            ],
            [
                'guide_insp_det',
                'GL-INSP-DET-001',
                ['Smoke Detector Inspection Procedure', 'Procédure Vérification Détecteur de Fumée', 'إجراء فحص كاشف الدخان'],
                ['Field inspection and functional test for optical smoke detectors.', 'Inspection terrain et test fonctionnel pour détecteurs optiques.', 'الفحص الميداني والاختبار الوظيفي لكواشف الدخان الضوئية.'],
                true,
                [
                    [1, 'Visually inspect the detector for physical damage or discolouration.', 'Inspecter visuellement le détecteur pour dommages ou décoloration.', 'فحص الكاشف بصرياً للأضرار أو تغير اللون.',           'boolean', true,  'true'],
                    [2, 'Check the LED indicator status (should be in normal standby mode).', 'Vérifier l\'état du voyant LED (doit être en veille normale).', 'فحص حالة مؤشر LED (يجب أن يكون في وضع الاستعداد).',      'boolean', true,  'true'],
                    [3, 'Clean the detector chamber with compressed air.',                    'Nettoyer la chambre du détecteur à l\'air comprimé.',             'تنظيف غرفة الكاشف بالهواء المضغوط.',                     'boolean', true,  'true'],
                    [4, 'Perform functional test using aerosol smoke or test gas.',           'Effectuer le test fonctionnel avec aérosol fumée ou gaz test.',   'إجراء اختبار وظيفي باستخدام رذاذ الدخان أو غاز الاختبار.', 'boolean', true, 'true'],
                    [5, 'Confirm alarm received at the detection panel.',                     'Confirmer la réception de l\'alarme à la centrale.',              'تأكيد استقبال الإنذار في المركزية.',                      'boolean', true,  'true'],
                    [6, 'Reset the detector and confirm return to standby.',                  'Réinitialiser le détecteur et confirmer le retour en veille.',    'إعادة ضبط الكاشف وتأكيد العودة إلى وضع الاستعداد.',       'boolean', true,  'true'],
                    [7, 'Record detector zone, address and test result.',                     'Enregistrer la zone, l\'adresse et le résultat du test.',         'تسجيل منطقة الكاشف وعنوانه ونتيجة الاختبار.',             'text',    true,  null],
                    [8, 'Photograph detector and panel confirmation screen.',                 'Photographier le détecteur et l\'écran de confirmation centrale.', 'التقاط صورة للكاشف وشاشة تأكيد المركزية.',               'photo',   false, null],
                ],
            ],
        ];

        foreach ($guidelines as [$key, $code, [$en, $fr, $ar], [$descEn, $descFr, $descAr], $isActive, $steps]) {
            $id = $this->ulid();
            DB::table('guidelines')->insert([
                'id' => $id,
                'code' => $code,
                'name' => $this->i18n($en, $fr, $ar),
                'description' => $this->i18n($descEn, $descFr, $descAr),
                'is_active' => $isActive,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->guides[$key] = $id;

            foreach ($steps as [$sort, $instrEn, $instrFr, $instrAr, $stepType, $isRequired, $expectedVal]) {
                DB::table('guideline_steps')->insert([
                    'id' => $this->ulid(),
                    'guideline_id' => $id,
                    'sort_order' => $sort,
                    'instruction' => $this->i18n($instrEn, $instrFr, $instrAr),
                    'step_type' => $stepType,
                    'is_required' => $isRequired,
                    'expected_value' => $expectedVal,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 10. EQUIPMENT BLUEPRINTS + OPTIONS + INTERVENTION CYCLES
    //      + BLUEPRINT_SERVICING + BLUEPRINT_SERVICING_CONSUMABLES
    //      + BLUEPRINT_GUIDELINE
    // ──────────────────────────────────────────────────────────────────────────
    private function seedEquipmentBlueprints(): void
    {
        $now = $this->now();

        $blueprints = [
            // ── Portable extinguisher ABC ─────────────────────────────────────
            [
                'bp_ext_abc',
                'BP-EXT-ABC-001',
                ['Portable Dry Powder Extinguisher ABC', 'Extincteur Portable Poudre ABC', 'طفاية يدوية مسحوق جاف ABC'],
                ['Blueprint for all ABC powder extinguishers 1–12 kg.', 'Blueprint pour extincteurs poudre ABC 1–12 kg.', 'نموذج لجميع طفايات المسحوق ABC 1-12 كغ.'],
                'extinguishers',
                'active',
                'ext_abc_6kg',     // legacy source key retained only inside seed data for traceability; not written to DB
                'portable_extinguisher',
                14.00,
                '1A 5B:C to 27A 183B:C',
                20,
                // blueprint_options: [opt_en/fr/ar, sort, values...]
                [
                    ['Capacity / Capacité / السعة', 0, ['1 kg', '2 kg', '4 kg', '6 kg', '9 kg', '12 kg']],
                    ['Pressure Type / Type Pression / نوع الضغط', 1, ['Stored Pressure / Pression Stockée / ضغط مخزن', 'Cartridge / Cartouche / خرطوشة']],
                    ['Agent / Agent / العامل', 2, ['ABC Powder / Poudre ABC / مسحوق ABC', 'BC Powder / Poudre BC / مسحوق BC']],
                ],
                // intervention_cycles: [type, first_interval_months, recurring_interval_months, notes]
                [
                    ['inspection',   12, 12,   'Inspection visuelle et vérification pression annuelle.'],
                    ['refill',       60, 60,   'Recharge poudre tous les 5 ans ou après usage.'],
                    ['hydro_test',   60, 60,   'Épreuve hydraulique du corps tous les 5 ans.'],
                    ['replacement', 240, null, 'Remplacement recommandé après 20 ans.'],
                ],
                // blueprint_servicing: [svc_key, consumable_links: [[cons_key, method, qty_mult]] ]
                [
                    ['svc_inspection_ext', [
                        ['etiq_extincteur', 'fixed',    1.0],
                        ['joint_orng',      'fixed',    1.0],
                        ['goupille_inox',   'fixed',    1.0],
                    ]],
                    ['svc_recharge_abc', [
                        ['poudre_abc_6',    'multiply', 1.0],
                        ['joint_orng',      'fixed',    1.0],
                        ['goupille_inox',   'fixed',    1.0],
                        ['etiq_extincteur', 'fixed',    1.0],
                    ]],
                    ['svc_hydro_test', [
                        ['joint_orng',      'fixed',    1.0],
                        ['etiq_extincteur', 'fixed',    1.0],
                    ]],
                    ['svc_install_ext', []],
                ],
                // guideline_keys to link
                ['guide_insp_ext', 'guide_rech_abc'],
            ],

            // ── Portable extinguisher CO₂ ─────────────────────────────────────
            [
                'bp_ext_co2',
                'BP-EXT-CO2-001',
                ['Portable CO₂ Extinguisher', 'Extincteur Portable CO₂', 'طفاية يدوية CO₂'],
                ['Blueprint for CO₂ extinguishers 2–9 kg.', 'Blueprint pour extincteurs CO₂ 2–9 kg.', 'نموذج لطفايات CO₂ 2-9 كغ.'],
                'extinguishers',
                'active',
                'ext_co2_2kg',
                'portable_extinguisher',
                null,
                '1B:C to 34B:C',
                15,
                [
                    ['Capacity / Capacité / السعة', 0, ['2 kg', '5 kg', '9 kg']],
                ],
                [
                    ['inspection',  12, 12, 'Vérification poids et pression annuelle (balance certifiée).'],
                    ['refill',      60, 60, 'Recharge CO₂ tous les 5 ans ou après usage partiel > 10%.'],
                    ['hydro_test',  60, 60, 'Épreuve hydraulique tous les 5 ans.'],
                ],
                [
                    ['svc_inspection_ext', [
                        ['etiq_extincteur', 'fixed', 1.0],
                        ['joint_orng',      'fixed', 1.0],
                    ]],
                    ['svc_recharge_co2', [
                        ['co2_recharge_2',  'multiply', 1.0],
                        ['joint_orng',      'fixed',    1.0],
                        ['etiq_extincteur', 'fixed',    1.0],
                    ]],
                    ['svc_hydro_test', [
                        ['joint_orng',      'fixed', 1.0],
                        ['etiq_extincteur', 'fixed', 1.0],
                    ]],
                ],
                ['guide_insp_ext'],
            ],

            // ── Wheeled extinguisher ABC ──────────────────────────────────────
            [
                'bp_ext_roue',
                'BP-EXT-ROUE-001',
                ['Wheeled Extinguisher ABC', 'Extincteur sur Roues ABC', 'طفاية ABC على عجلات'],
                ['Blueprint for wheeled ABC powder extinguishers 25–100 kg.', 'Blueprint pour extincteurs sur roues poudre ABC 25–100 kg.', 'نموذج لطفايات ABC على عجلات 25-100 كغ.'],
                'extinguishers',
                'active',
                'ext_roue_50kg',
                'wheeled_extinguisher',
                12.00,
                'A/B/C',
                20,
                [
                    ['Capacity / Capacité / السعة', 0, ['25 kg', '50 kg', '75 kg', '100 kg']],
                    ['Pressure Type / Type Pression / نوع الضغط', 1, ['Stored Pressure / Pression Stockée / ضغط مخزن', 'Cartridge / Cartouche / خرطوشة']],
                ],
                [
                    ['inspection',  12, 12,   'Inspection annuelle.'],
                    ['refill',      60, 60,   'Recharge poudre tous les 5 ans.'],
                    ['hydro_test',  60, 60,   'Épreuve hydraulique corps tous les 5 ans.'],
                    ['replacement', 240, null, 'Remplacement après 20 ans.'],
                ],
                [
                    ['svc_inspection_ext', [
                        ['etiq_extincteur', 'fixed', 1.0],
                        ['joint_orng',      'fixed', 2.0],
                    ]],
                    ['svc_recharge_abc', [
                        ['poudre_abc_9',    'multiply', 1.0],
                        ['joint_orng',      'fixed',    2.0],
                        ['etiq_extincteur', 'fixed',    1.0],
                    ]],
                    ['svc_hydro_test', [
                        ['joint_orng',      'fixed', 2.0],
                        ['etiq_extincteur', 'fixed', 1.0],
                    ]],
                ],
                ['guide_insp_ext', 'guide_rech_abc'],
            ],

            // ── Optical smoke detector ────────────────────────────────────────
            [
                'bp_det_fumee',
                'BP-DET-FUM-001',
                ['Optical Smoke Detector', 'Détecteur de Fumée Optique', 'كاشف الدخان الضوئي'],
                ['Blueprint for addressable optical smoke detectors.', 'Blueprint pour détecteurs de fumée optiques adressables.', 'نموذج لكواشف الدخان الضوئية القابلة للعنونة.'],
                'detection',
                'active',
                'det_fumee',
                'detector',
                null,
                null,
                null,
                [
                    ['Power Supply / Alimentation / التغذية', 0, ['Battery 9V / Pile 9V / بطارية 9 فولت', 'Wired 230V / Filaire 230V / سلكي 230 فولت']],
                    ['Protocol / Protocole / البروتوكول', 1, ['Addressable / Adressable / قابل للعنونة', 'Conventional / Conventionnel / تقليدي']],
                ],
                [
                    ['inspection',   12, 12, 'Test fonctionnel et nettoyage de chambre annuels.'],
                    ['calibration',  36, 36, 'Calibration et vérification de sensibilité tous les 3 ans.'],
                    ['replacement', 120, null, 'Remplacement après 10 ans.'],
                ],
                [
                    ['svc_inspection_ext', [
                        ['pile_9v_det', 'fixed', 1.0],
                    ]],
                    ['svc_calib_det', [
                        ['pile_9v_det', 'fixed', 1.0],
                    ]],
                ],
                ['guide_insp_det'],
            ],

            // ── Fire hose reel ────────────────────────────────────────────────
            [
                'bp_ria',
                'BP-RIA-19MM-001',
                ['19mm Fire Hose Reel', 'Robinet Incendie Armé Ø19mm', 'حنفية حريق مسلحة قطر 19ملم'],
                ['Blueprint for 19mm semi-rigid fire hose reels.', 'Blueprint pour RIA Ø19mm semi-rigide.', 'نموذج لحنفيات الحريق المسلحة قطر 19ملم.'],
                'suppression',
                'active',
                'rob_incendie',
                'hose_reel',
                null,
                null,
                null,
                [
                    ['Hose Length / Longueur Tuyau / طول الخرطوم', 0, ['20 m', '25 m', '30 m']],
                    ['Cabinet Type / Type Armoire / نوع الخزانة',   1, ['Flush / Encastrée / مدمجة', 'Surface / Saillie / بارزة']],
                ],
                [
                    ['inspection',   12, 12, 'Inspection annuelle tuyau, robinet et diffuseur.'],
                    ['maintenance',  36, 36, 'Remplacement tuyau semi-rigide tous les 3 ans.'],
                    ['replacement', 120, null, 'Remplacement complet après 10 ans.'],
                ],
                [
                    ['svc_maint_ria', []],
                ],
                [],
            ],

            // ── Detection panel ───────────────────────────────────────────────
            [
                'bp_cdi',
                'BP-CDI-CONV-001',
                ['Conventional Fire Detection Panel', 'Centrale Détection Incendie Conventionnelle', 'مركزية كشف الحريق التقليدية'],
                ['Blueprint for conventional fire detection and alarm control panels.', 'Blueprint pour centrales de détection incendie conventionnelles.', 'نموذج لمركزيات كشف الحريق التقليدية.'],
                'detection',
                'active',
                'cdi_8z',
                'detection_panel',
                null,
                null,
                null,
                [
                    ['Zones', 0, ['4', '8', '16', '32']],
                    ['Supply / Alimentation / التغذية', 1, ['230V AC', '24V DC']],
                ],
                [
                    ['inspection',   12, 12, 'Vérification et test de la centrale annuels.'],
                    ['maintenance',  36, 36, 'Maintenance préventive tous les 3 ans, remplacement batteries.'],
                    ['replacement', 180, null, 'Remplacement recommandé après 15 ans.'],
                ],
                [
                    ['svc_maint_cdi', []],
                ],
                [],
            ],
        ];

        $eqClassMapping = [
            'portable_extinguisher' => 'portable_fire_extinguisher',
            'wheeled_extinguisher' => 'wheeled_fire_extinguisher',
            'detection_panel' => 'fire_alarm_panel',
            'detector' => 'fire_alarm_panel',
            'hose_reel' => 'fire_hose_reel',
            'fixed_suppression_system' => 'pump',
            'fire_blanket' => 'other',
            'other' => 'other',
        ];

        $equipmentClasses = DB::table('equipment_classes')->pluck('id', 'code');

        foreach (
            $blueprints as [
                $bpKey,
                $code,
                [$nameEn, $nameFr, $nameAr],
                [$descEn, $descFr, $descAr],
                $catKey,
                $status,
                $_linkedProductKey,
                $eqClass,
                $workingPressure,
                $fireClassRating,
                $shelfLifeYears,
                $bpOptions,
                $cycles,
                $servicing,
                $guideKeys
            ]
        ) {
            $mappedCode = $eqClassMapping[$eqClass] ?? $eqClass;
            $eqClassId = $equipmentClasses[$mappedCode] ?? ($equipmentClasses['other'] ?? null);

            $bpId = $this->ulid();
            DB::table('equipment_blueprints')->insert([
                'id' => $bpId,
                'code' => $code,
                'name' => $this->i18n($nameEn, $nameFr, $nameAr),
                'description' => $this->i18n($descEn, $descFr, $descAr),
                'status' => $status,
                'equipment_class_id' => $eqClassId,
                'working_pressure_bar' => $workingPressure,
                'fire_class_rating' => $fireClassRating,
                'shelf_life_years' => $shelfLifeYears,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->bps[$bpKey] = $bpId;

            // ── Blueprint options ─────────────────────────────────────────────
            foreach ($bpOptions as [$optLabel, $optSort, $optValues]) {
                $parts = explode(' / ', $optLabel);
                $bpOptId = $this->ulid();
                $optCode = $this->codeFromLabel($parts[0]);
                DB::table('blueprint_options')->insert([
                    'id' => $bpOptId,
                    'blueprint_id' => $bpId,
                    'name' => $this->i18n($parts[0], $parts[1] ?? $parts[0], $parts[2] ?? $parts[0]),
                    'code' => $optCode,
                    'data_type' => 'select',
                    'sort_order' => $optSort,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->bpOptionIds[$bpKey . '.' . $optCode] = $bpOptId;

                $vSort = 0;
                foreach ($optValues as $val) {
                    [$display, $numericValue, $unit] = $this->blueprintValueMeta($val);
                    $valCode = $this->codeFromLabel($val);
                    $bpOvId = $this->ulid();
                    DB::table('blueprint_option_values')->insert([
                        'id' => $bpOvId,
                        'option_id' => $bpOptId,
                        'value' => json_encode($display, JSON_UNESCAPED_UNICODE),
                        'code' => $valCode,
                        'numeric_value' => $numericValue,
                        'unit' => $unit,
                        'sort_order' => $vSort++,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $this->bpOvIds[$bpKey . '.' . $optCode . '.' . $valCode] = $bpOvId;
                }
            }

            // ── Intervention cycles ───────────────────────────────────────────
            foreach ($cycles as [$intType, $firstInterval, $recurringInterval, $cycleNotes]) {
                DB::table('blueprint_intervention_cycles')->insert([
                    'id' => $this->ulid(),
                    'blueprint_id' => $bpId,
                    'intervention_type' => $intType,
                    'first_interval_months' => $firstInterval,
                    'recurring_interval_months' => $recurringInterval,
                    'notes' => $cycleNotes,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // ── Blueprint servicing + servicing consumables ───────────────────
            foreach ($servicing as [$svcKey, $consLinks]) {
                $bpSvcId = $this->ulid();
                $serviceConditions = [];
                // Blueprint-level applicability is now modeled here, not in catalog service records.
                if ($svcKey === 'svc_recharge_abc' && isset($this->bpOptionIds[$bpKey . '.AGENT'])) {
                    $serviceConditions[] = $this->condition('AGENT', 'equals', 'ABC_POWDER');
                }
                if ($svcKey === 'svc_recharge_co2' && isset($this->bpOptionIds[$bpKey . '.AGENT'])) {
                    $serviceConditions[] = $this->condition('AGENT', 'equals', 'CO2');
                }

                DB::table('blueprint_servicing')->insert([
                    'id' => $bpSvcId,
                    'blueprint_id' => $bpId,
                    'servicing_item_id' => $this->items[$svcKey],
                    'applicability_conditions' => $serviceConditions ? json_encode(['match' => 'all', 'conditions' => $serviceConditions], JSON_UNESCAPED_UNICODE) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $bpSvcKey = $bpKey . '.' . $svcKey;
                $this->bpSvcs[$bpSvcKey] = $bpSvcId;

                foreach ($consLinks as $link) {
                    [$consKey, $calcMethod, $qtyMult] = $link;
                    // 'multiply' = option-value-based (blueprint_option type), 'fixed' = static quantity
                    $isOptionBased = $calcMethod === 'multiply';
                    $calculationType = $isOptionBased ? 'blueprint_option' : 'fixed';
                    $normalizedMethod = $isOptionBased ? 'multiply' : 'fixed';
                    $sourceOptionId = null;
                    $calcParameter = $isOptionBased ? (float) $qtyMult : 1.0;
                    $fixedQuantity = $isOptionBased ? 1.0 : (float) $qtyMult;

                    if ($isOptionBased) {
                        // Capacity is the first-class numeric option for the seeded refill examples.
                        // Resolve it from the database using the current Blueprint ID as a safety net,
                        // rather than relying only on the in-memory map.
                        $sourceOptionId = $this->bpOptionIds[$bpKey . '.CAPACITY'] ?? null;

                        if (! $sourceOptionId) {
                            $sourceOptionId = DB::table('blueprint_options')
                                ->where('blueprint_id', $bpId)
                                ->where('code', 'CAPACITY')
                                ->value('id');
                        }

                        if (! $sourceOptionId) {
                            throw new \RuntimeException("Blueprint {$bpKey} requires a CAPACITY option for option-based calculation, but no CAPACITY option was found.");
                        }
                    }

                    DB::table('blueprint_servicing_consumables')->insert([
                        'id' => $this->ulid(),
                        'blueprint_servicing_id' => $bpSvcId,
                        'consumable_item_id' => $this->items[$consKey],
                        'calculation_method' => $normalizedMethod,
                        'quantity_multiplier' => (float) $qtyMult,
                        'calculation_type' => $calculationType,
                        'calculation_source_option_id' => $sourceOptionId,
                        'calculation_parameter' => $calcParameter,
                        'fixed_quantity' => $fixedQuantity,
                        'applicability_conditions' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            // ── Blueprint ↔ Guidelines ────────────────────────────────────────
            foreach ($guideKeys as $guideKey) {
                DB::table('blueprint_guideline')->insert([
                    'id' => $this->ulid(),
                    'equipment_blueprint_id' => $bpId,
                    'guideline_id' => $this->guides[$guideKey],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /** Generate a stable machine-friendly code from a human label. */
    private function codeFromLabel(string $label): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
        $ascii = $ascii === false ? $label : $ascii;
        $ascii = strtoupper($ascii);
        $ascii = preg_replace('/[^A-Z0-9]+/', '_', $ascii) ?? '';

        return trim($ascii, '_') ?: 'VALUE';
    }

    /** Parse a Blueprint value into display text + optional numeric value/unit. */
    private function blueprintValueMeta(string $raw): array
    {
        $parts = array_map('trim', explode(' / ', $raw));
        $display = [
            'en' => $parts[0] ?? $raw,
            'fr' => $parts[1] ?? ($parts[0] ?? $raw),
            'ar' => $parts[2] ?? ($parts[0] ?? $raw),
        ];

        if (preg_match('/(-?\d+(?:[.,]\d+)?)\s*(kg|g|l|ml|bar|v|mm|m|%)?/iu', $parts[0] ?? $raw, $m)) {
            $numeric = (float) str_replace(',', '.', $m[1]);
            $unit = isset($m[2]) && $m[2] !== '' ? strtolower($m[2]) : null;
            return [$display, $numeric, $unit];
        }

        return [$display, null, null];
    }

    private function condition(string $optionCode, string $operator, string|array $value): array
    {
        return [
            'option_code' => $optionCode,
            'operator' => $operator,
            'value' => $value,
        ];
    }

    /** Guarantee at least one default variant for every catalog item. */
    private function ensureDefaultVariants(): void
    {
        $now = $this->now();
        $items = DB::table('catalog_items')->select('id', 'code', 'uom_id')->get();

        foreach ($items as $item) {
            $variantCount = DB::table('catalog_variants')
                ->where('catalog_item_id', $item->id)
                ->count();

            if ($variantCount === 0) {
                $variantId = $this->ulid();
                DB::table('catalog_variants')->insert([
                    'id' => $variantId,
                    'catalog_item_id' => $item->id,
                    'is_default' => true,
                    'name' => 'Default',
                    'sku' => $item->code . '-DEFAULT',
                    'barcode' => null,
                    'price' => null,
                    'cost_price' => null,
                    'markup' => null,
                    'tax_class' => 'standard_19pct',
                    'min_stock_level' => null,
                    'reorder_point' => null,
                    'reorder_qty' => null,
                    'lead_time_days' => null,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->variants[$item->code . '.default'] = $variantId;
                continue;
            }

            $hasDefault = DB::table('catalog_variants')
                ->where('catalog_item_id', $item->id)
                ->where('is_default', true)
                ->exists();

            if (! $hasDefault) {
                DB::table('catalog_variants')
                    ->where('catalog_item_id', $item->id)
                    ->orderBy('created_at')
                    ->limit(1)
                    ->update(['is_default' => true, 'updated_at' => $now]);
            }
        }
    }
}
