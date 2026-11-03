<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * WarehouseSeeder — Wave Plus / SS Plus
 *
 * Covers the full warehouse spatial hierarchy:
 *   warehouse_sites → warehouse_zones → warehouse_locations
 *
 * Seeds 2 realistic sites tailored to SS Plus (fire safety / PPE / workwear):
 *   WH-SFAX  — Main warehouse, Sfax (multi-zone, full rack system)
 *   WH-TUN   — Transit depot, Tunis (leaner, mostly staging + picking)
 *
 * Usage:
 *   php artisan db:seed --class=WarehouseSeeder
 */
class WarehouseSeeder extends Seeder
{
    private array $sites = [];   // code → ulid

    private array $zones = [];   // "site_code.zone_code" → ulid

    // ──────────────────────────────────────────────────────────────────────────
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('warehouse_locations')->truncate();
        DB::table('warehouse_zones')->truncate();
        DB::table('warehouse_sites')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->seedSites();
        $this->seedZones();
        $this->seedLocations();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────────────────
    private function ulid(): string
    {
        return (string) Str::ulid();
    }

    private function now(): string
    {
        return Carbon::now()->toDateTimeString();
    }

    private function i18n(string $en, string $fr, string $ar): string
    {
        return json_encode(['en' => $en, 'fr' => $fr, 'ar' => $ar], JSON_UNESCAPED_UNICODE);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. SITES
    // ──────────────────────────────────────────────────────────────────────────
    private function seedSites(): void
    {
        $now = $this->now();

        $sites = [
            [
                'code' => 'WH-SFAX',
                'name' => $this->i18n('Main Warehouse — Sfax', 'Entrepôt Principal — Sfax', 'المستودع الرئيسي — صفاقس'),
                'address' => json_encode([
                    'line1' => 'Zone Industrielle Thyna',
                    'line2' => 'Rue des Artisans, Lot 14',
                    'zip' => '3041',
                ]),
                'city' => 'Sfax',
                'country' => 'Tunisie',
                'phone' => '+216 74 000 100',
                'email' => 'wh-sfax@ssplus.tn',
                'status' => 'active',
                'notes' => 'Entrepôt principal SS Plus. Stockage extincteurs, EPI, consommables et pièces de rechange. Atelier de recharge sur place.',
            ],
            [
                'code' => 'WH-TUN',
                'name' => $this->i18n('Transit Depot — Tunis', 'Dépôt Transit — Tunis', 'مستودع العبور — تونس'),
                'address' => json_encode([
                    'line1' => 'Zone Industrielle Ben Arous',
                    'line2' => 'Rue de l\'Industrie, Bât. B2',
                    'zip' => '2013',
                ]),
                'city' => 'Ben Arous',
                'country' => 'Tunisie',
                'phone' => '+216 71 000 200',
                'email' => 'wh-tunis@ssplus.tn',
                'status' => 'active',
                'notes' => 'Dépôt transit pour livraisons Grand Tunis. Picking et expédition uniquement. Pas de recharge sur place.',
            ],
            [
                'code' => 'WH-SFAX-ARCH',
                'name' => $this->i18n('Archive Store — Sfax', 'Local Archivage — Sfax', 'مخزن الأرشيف — صفاقس'),
                'address' => json_encode([
                    'line1' => 'Zone Industrielle Thyna',
                    'line2' => 'Annexe Bâtiment C',
                    'zip' => '3041',
                ]),
                'city' => 'Sfax',
                'country' => 'Tunisie',
                'phone' => null,
                'email' => null,
                'status' => 'inactive',
                'notes' => 'Local désaffecté, en cours de réaménagement. Ne pas utiliser pour nouveaux stocks.',
            ],
        ];

        foreach ($sites as $data) {
            $id = $this->ulid();
            DB::table('warehouse_sites')->insert([
                'id' => $id,
                'code' => $data['code'],
                'name' => $data['name'],
                'address' => $data['address'],
                'city' => $data['city'],
                'country' => $data['country'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'manager_id' => null,
                'status' => $data['status'],
                'notes' => $data['notes'],
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
            $this->sites[$data['code']] = $id;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. ZONES
    // ──────────────────────────────────────────────────────────────────────────
    private function seedZones(): void
    {
        $now = $this->now();

        // [site_code, zone_code, en, fr, ar, zone_type, status, notes]
        $zones = [

            // ── WH-SFAX — Main warehouse ──────────────────────────────────────
            ['WH-SFAX', 'RECV',    'Receiving Dock',              'Quai de Réception',              'رصيف الاستقبال',              'receiving',    'active',
                'Zone de déchargement et contrôle réception marchandises.'],

            ['WH-SFAX', 'QUARAN',  'Quarantine Zone',             'Zone Quarantaine',               'منطقة العزل',                 'quarantine',   'active',
                'Marchandises en attente de contrôle qualité ou retours fournisseur.'],

            ['WH-SFAX', 'RACK-A',  'Rack A — Extinguishers',      'Rack A — Extincteurs',           'رف A — الطفايات',             'storage',      'active',
                'Stockage extincteurs portables ABC et CO₂. Capacité 480 emplacements palette.'],

            ['WH-SFAX', 'RACK-B',  'Rack B — PPE & Workwear',     'Rack B — EPI & Vêtements',       'رف B — معدات الحماية والملابس', 'storage',      'active',
                'EPI (casques, gants, lunettes, masques) et vêtements de travail.'],

            ['WH-SFAX', 'RACK-C',  'Rack C — Spare Parts',        'Rack C — Pièces de Rechange',    'رف C — قطع الغيار',           'storage',      'active',
                'Pièces de rechange, joints, goupilles, tuyaux et petits consommables.'],

            ['WH-SFAX', 'BULK',    'Bulk Storage — Agents',       'Vrac — Agents Extincteurs',      'التخزين السائب — عوامل الإطفاء', 'storage',     'active',
                'Stockage vrac : poudre ABC, fûts CO₂, concentré mousse AFFF. Sol renforcé.'],

            ['WH-SFAX', 'ATELIER', 'Workshop & Recharge',         'Atelier Recharge',               'ورشة إعادة الشحن',            'staging',      'active',
                'Atelier de recharge extincteurs, test hydrostatique et reconditionnement.'],

            ['WH-SFAX', 'PICK',    'Picking Zone',                'Zone de Picking',                'منطقة الانتقاء',              'picking',      'active',
                'Articles fréquents et commandes en cours de préparation.'],

            ['WH-SFAX', 'PACK',    'Packing & Dispatch',          'Emballage & Expédition',         'التعبئة والإرسال',            'packing',      'active',
                'Emballage commandes, génération BL et expédition.'],

            ['WH-SFAX', 'SHIP',    'Shipping Dock',               'Quai d\'Expédition',             'رصيف الشحن',                  'shipping',     'active',
                'Chargement camions et transfert dépôts régionaux.'],

            ['WH-SFAX', 'RETURN',  'Returns Processing',          'Traitement des Retours',         'معالجة المرتجعات',            'returns',      'active',
                'Retours clients et fournisseurs. Tri : remise en stock / réparation / rebut.'],

            // ── WH-TUN — Transit depot ────────────────────────────────────────
            ['WH-TUN',  'RECV',    'Receiving Dock',              'Quai de Réception',              'رصيف الاستقبال',              'receiving',    'active',
                'Réception transferts depuis WH-SFAX.'],

            ['WH-TUN',  'STAG-A',  'Staging Area A',              'Zone de Staging A',              'منطقة التجميع A',             'staging',      'active',
                'Préparation commandes Grand Tunis Nord et Centre.'],

            ['WH-TUN',  'STAG-B',  'Staging Area B',              'Zone de Staging B',              'منطقة التجميع B',             'staging',      'active',
                'Préparation commandes Grand Tunis Sud et Banlieue.'],

            ['WH-TUN',  'PICK',    'Fast-Moving Pick Zone',       'Zone Picking Articles Rapides',  'منطقة انتقاء سريع',           'picking',      'active',
                'Articles à forte rotation : extincteurs 6kg, EPI standard, étiquettes.'],

            ['WH-TUN',  'SHIP',    'Shipping Dock',               'Quai d\'Expédition',             'رصيف الشحن',                  'shipping',     'active',
                'Expédition vers les clients et techniciens terrain Tunis.'],

            ['WH-TUN',  'RETURN',  'Returns Bay',                 'Baie de Retours',                'رصيف المرتجعات',              'returns',      'active',
                'Retours clients locaux. Transférer vers WH-SFAX pour traitement.'],

            ['WH-TUN',  'QUARAN',  'Quarantine Bay',              'Baie Quarantaine',               'رصيف العزل',                  'quarantine',   'inactive',
                'Baie inactive — transfert systématique vers WH-SFAX quarantaine.'],
        ];

        foreach ($zones as [$siteCode, $zoneCode, $en, $fr, $ar, $zoneType, $status, $notes]) {
            $id = $this->ulid();
            DB::table('warehouse_zones')->insert([
                'id' => $id,
                'site_id' => $this->sites[$siteCode],
                'code' => $zoneCode,
                'name' => $this->i18n($en, $fr, $ar),
                'zone_type' => $zoneType,
                'status' => $status,
                'notes' => $notes,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
            $this->zones[$siteCode.'.'.$zoneCode] = $id;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. LOCATIONS
    // ──────────────────────────────────────────────────────────────────────────
    private function seedLocations(): void
    {
        $now = $this->now();

        // ── Helper: generate a barcode ────────────────────────────────────────
        $barcode = fn (string $site, string $zone, string $suffix): string => strtoupper("{$site}-{$zone}-{$suffix}");

        // ── WH-SFAX / RECV — 4 floor positions ───────────────────────────────
        $this->insertLocations($now, 'WH-SFAX', 'RECV', [
            // [barcode_suffix, aisle, rack, shelf, bin, type, max_kg, max_m3, active, notes]
            ['R01', 'R', null, null, null, 'floor',   2000, 10.0, true, 'Quai porte 1 — véhicules légers'],
            ['R02', 'R', null, null, null, 'floor',   5000, 20.0, true, 'Quai porte 2 — camions'],
            ['R03', 'R', null, null, null, 'pallet',  1000,  4.0, true, 'Palette contrôle qualité réception'],
            ['R04', 'R', null, null, null, 'staging', 500,   2.0, true, 'Poste saisie informatique réception'],
        ]);

        // ── WH-SFAX / QUARAN — 6 pallet positions ────────────────────────────
        $this->insertLocations($now, 'WH-SFAX', 'QUARAN', [
            ['Q01', 'Q', null, null, null, 'quarantine', 500, 2.0, true,  'Attente contrôle qualité'],
            ['Q02', 'Q', null, null, null, 'quarantine', 500, 2.0, true,  'Attente validation fournisseur'],
            ['Q03', 'Q', null, null, null, 'quarantine', 500, 2.0, true,  'Non-conformité détectée'],
            ['Q04', 'Q', null, null, null, 'quarantine', 500, 2.0, true,  'Retour fournisseur en attente BL'],
            ['Q05', 'Q', null, null, null, 'quarantine', 500, 2.0, false, 'Hors service — inspection à planifier'],
            ['Q06', 'Q', null, null, null, 'quarantine', 200, 1.0, true,  'Petits articles en attente décision'],
        ]);

        // ── WH-SFAX / RACK-A — Rack system for extinguishers
        //    4 aisles (A1-A4) × 5 racks (01-05) × 4 shelves (S1-S4) × 2 bins (L/R)
        // ─────────────────────────────────────────────────────────────────────
        foreach (['A1', 'A2', 'A3', 'A4'] as $aisle) {
            foreach (['01', '02', '03', '04', '05'] as $rack) {
                foreach (['S1', 'S2', 'S3', 'S4'] as $shelf) {
                    foreach (['L', 'R'] as $bin) {
                        $suffix = "{$aisle}-{$rack}-{$shelf}-{$bin}";
                        $bc = $barcode('SFAX', 'RACKA', $suffix);
                        // Ground shelf (S4) can hold heavier items (full pallets of ext.)
                        $maxKg = $shelf === 'S4' ? 500 : 200;
                        $maxM3 = $shelf === 'S4' ? 2.0 : 1.0;
                        $notes = $shelf === 'S1' ? 'Niveau haut — accès échelle requis' : null;

                        DB::table('warehouse_locations')->insert([
                            'id' => $this->ulid(),
                            'zone_id' => $this->zones['WH-SFAX.RACK-A'],
                            'barcode' => $bc,
                            'aisle' => $aisle,
                            'rack' => $rack,
                            'shelf' => $shelf,
                            'bin' => $bin,
                            'location_type' => 'rack',
                            'max_weight_kg' => $maxKg,
                            'max_volume_m3' => $maxM3,
                            'is_active' => true,
                            'notes' => $notes,
                            'created_at' => $now,
                            'updated_at' => $now,
                            'deleted_at' => null,
                        ]);
                    }
                }
            }
        }

        // ── WH-SFAX / RACK-B — PPE & Workwear
        //    3 aisles (B1-B3) × 4 racks × 5 shelves × 2 bins
        // ─────────────────────────────────────────────────────────────────────
        foreach (['B1', 'B2', 'B3'] as $aisle) {
            foreach (['01', '02', '03', '04'] as $rack) {
                foreach (['S1', 'S2', 'S3', 'S4', 'S5'] as $shelf) {
                    foreach (['L', 'R'] as $bin) {
                        $suffix = "{$aisle}-{$rack}-{$shelf}-{$bin}";
                        $bc = $barcode('SFAX', 'RACKB', $suffix);

                        DB::table('warehouse_locations')->insert([
                            'id' => $this->ulid(),
                            'zone_id' => $this->zones['WH-SFAX.RACK-B'],
                            'barcode' => $bc,
                            'aisle' => $aisle,
                            'rack' => $rack,
                            'shelf' => $shelf,
                            'bin' => $bin,
                            'location_type' => 'shelf',
                            'max_weight_kg' => 150,
                            'max_volume_m3' => 0.8,
                            'is_active' => true,
                            'notes' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                            'deleted_at' => null,
                        ]);
                    }
                }
            }
        }

        // ── WH-SFAX / RACK-C — Spare Parts & Consumables
        //    2 aisles (C1-C2) × 6 racks × 6 shelves × 4 bins (fine-grain bins)
        // ─────────────────────────────────────────────────────────────────────
        foreach (['C1', 'C2'] as $aisle) {
            foreach (['01', '02', '03', '04', '05', '06'] as $rack) {
                foreach (['S1', 'S2', 'S3', 'S4', 'S5', 'S6'] as $shelf) {
                    foreach (['B1', 'B2', 'B3', 'B4'] as $bin) {
                        $suffix = "{$aisle}-{$rack}-{$shelf}-{$bin}";
                        $bc = $barcode('SFAX', 'RACKC', $suffix);

                        DB::table('warehouse_locations')->insert([
                            'id' => $this->ulid(),
                            'zone_id' => $this->zones['WH-SFAX.RACK-C'],
                            'barcode' => $bc,
                            'aisle' => $aisle,
                            'rack' => $rack,
                            'shelf' => $shelf,
                            'bin' => $bin,
                            'location_type' => 'bin',
                            'max_weight_kg' => 50,
                            'max_volume_m3' => 0.2,
                            'is_active' => true,
                            'notes' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                            'deleted_at' => null,
                        ]);
                    }
                }
            }
        }

        // ── WH-SFAX / BULK — 12 floor bulk positions ─────────────────────────
        $bulkPositions = [
            ['BLK-01', null, null, null, null, 'bulk', 3000, 12.0, true,  'Fûts CO₂ 50kg — zone ventilée obligatoire'],
            ['BLK-02', null, null, null, null, 'bulk', 3000, 12.0, true,  'Fûts CO₂ 50kg — zone ventilée obligatoire'],
            ['BLK-03', null, null, null, null, 'bulk', 2000,  8.0, true,  'Sacs poudre ABC 25kg — palette bois'],
            ['BLK-04', null, null, null, null, 'bulk', 2000,  8.0, true,  'Sacs poudre ABC 25kg — palette bois'],
            ['BLK-05', null, null, null, null, 'bulk', 2000,  8.0, true,  'Sacs poudre ABC 25kg — palette bois'],
            ['BLK-06', null, null, null, null, 'bulk', 1500,  6.0, true,  'Concentré mousse AFFF — fûts 25L'],
            ['BLK-07', null, null, null, null, 'bulk', 1500,  6.0, true,  'Concentré mousse AFFF — fûts 25L'],
            ['BLK-08', null, null, null, null, 'pallet', 800, 3.2, true,  'Palettes mixtes agents en attente rangement'],
            ['BLK-09', null, null, null, null, 'floor', 5000, 20.0, true, 'Zone gros matériels — extincteurs sur roues'],
            ['BLK-10', null, null, null, null, 'floor', 5000, 20.0, true, 'Zone gros matériels — armoires RIA palettisées'],
            ['BLK-11', null, null, null, null, 'floor', 2000,  8.0, false, 'Hors service — fuite détectée, en cours de nettoyage'],
            ['BLK-12', null, null, null, null, 'hazmat', 1000, 4.0, true, 'Produits dangereux — accès restreint, ventilation forcée'],
        ];
        $this->insertLocations($now, 'WH-SFAX', 'BULK', $bulkPositions);

        // ── WH-SFAX / ATELIER — Workshop bench positions ──────────────────────
        $this->insertLocations($now, 'WH-SFAX', 'ATELIER', [
            ['ATL-01', 'W', '01', null, null, 'floor',   200,  1.0, true, 'Banc de recharge poudre — poste 1'],
            ['ATL-02', 'W', '01', null, null, 'floor',   200,  1.0, true, 'Banc de recharge poudre — poste 2'],
            ['ATL-03', 'W', '02', null, null, 'floor',   200,  1.0, true, 'Banc recharge CO₂ — manomètre certifié'],
            ['ATL-04', 'W', '02', null, null, 'floor',   200,  1.0, true, 'Banc recharge CO₂ — manomètre certifié'],
            ['ATL-05', 'W', '03', null, null, 'floor',   500,  2.0, true, 'Bati test hydrostatique — extincteurs corps'],
            ['ATL-06', 'W', '03', null, null, 'floor',   100,  0.5, true, 'Poste dépose / repose vanne'],
            ['ATL-07', 'W', '04', null, null, 'shelf',   100,  0.5, true, 'Consommables atelier — joints, goupilles, étiquettes'],
            ['ATL-08', 'W', '04', null, null, 'staging', 300,  1.5, true, 'Zone en attente reconditionnement'],
            ['ATL-09', 'W', '05', null, null, 'staging', 300,  1.5, true, 'Zone reconditionnés — prêts à retourner stock'],
            ['ATL-10', 'W', '05', null, null, 'floor',   50,   0.3, false, 'Banc hors service — maintenance équipements'],
        ]);

        // ── WH-SFAX / PICK — 20 dedicated pick bins ───────────────────────────
        $pickItems = [
            'EXT-ABC-6KG', 'EXT-ABC-9KG', 'EXT-CO2-2KG', 'EXT-CO2-5KG',
            'EXT-EAU-9L',  'DET-FUMEE',   'DET-CHALEUR',  'CASQUE-BLA',
            'CASQUE-JAU',  'GANTS-S',     'GANTS-M',      'GANTS-L',
            'MASQUE-FFP2', 'LUNETTES',    'ETIQ-EXT',     'JOINT-ORNG',
            'GOUPILLE',    'PILE-9V',     'PD-ABC-6KG',   'CO2-2KG',
        ];
        $pickRows = [];
        foreach ($pickItems as $i => $item) {
            $pickRows[] = [
                'PK-'.str_pad($i + 1, 2, '0', STR_PAD_LEFT),
                'P', str_pad(intdiv($i, 4) + 1, 2, '0', STR_PAD_LEFT),
                'S'.(($i % 4) + 1), null,
                'pick', 100, 0.4, true,
                "Emplacement picking fixe — {$item}",
            ];
        }
        $this->insertLocations($now, 'WH-SFAX', 'PICK', $pickRows);

        // ── WH-SFAX / PACK — 6 packing benches ───────────────────────────────
        $this->insertLocations($now, 'WH-SFAX', 'PACK', [
            ['PKG-01', 'P', null, null, null, 'staging', 200, 1.0, true, 'Poste emballage 1 — petits colis'],
            ['PKG-02', 'P', null, null, null, 'staging', 200, 1.0, true, 'Poste emballage 2 — petits colis'],
            ['PKG-03', 'P', null, null, null, 'staging', 500, 2.0, true, 'Poste emballage 3 — colis moyens'],
            ['PKG-04', 'P', null, null, null, 'staging', 500, 2.0, true, 'Poste emballage 4 — colis moyens'],
            ['PKG-05', 'P', null, null, null, 'pallet',  1000, 4.0, true, 'Poste palettisation — grosses commandes'],
            ['PKG-06', 'P', null, null, null, 'staging', 200, 1.0, true, 'Zone consommables emballage (scotch, bulles)'],
        ]);

        // ── WH-SFAX / SHIP — 4 shipping bays ─────────────────────────────────
        $this->insertLocations($now, 'WH-SFAX', 'SHIP', [
            ['SHP-01', 'S', null, null, null, 'floor',   5000, 20.0, true, 'Quai chargement — camion grande tournée'],
            ['SHP-02', 'S', null, null, null, 'floor',   2000,  8.0, true, 'Quai chargement — véhicule technicien'],
            ['SHP-03', 'S', null, null, null, 'staging', 1000,  4.0, true, 'Zone commandes urgentes / express'],
            ['SHP-04', 'S', null, null, null, 'staging', 500,   2.0, true, 'Zone transfert WH-TUN'],
        ]);

        // ── WH-SFAX / RETURN ──────────────────────────────────────────────────
        $this->insertLocations($now, 'WH-SFAX', 'RETURN', [
            ['RET-01', 'RT', null, null, null, 'returns', 500, 2.0, true,  'Retours clients — à trier'],
            ['RET-02', 'RT', null, null, null, 'returns', 500, 2.0, true,  'Retours clients — remise en stock validée'],
            ['RET-03', 'RT', null, null, null, 'returns', 500, 2.0, true,  'Retours clients — à réparer / reconditionner'],
            ['RET-04', 'RT', null, null, null, 'returns', 500, 2.0, true,  'Retours fournisseurs — en attente RMA'],
            ['RET-05', 'RT', null, null, null, 'returns', 200, 1.0, true,  'Rebuts — articles à détruire'],
        ]);

        // ── WH-TUN / RECV ─────────────────────────────────────────────────────
        $this->insertLocations($now, 'WH-TUN', 'RECV', [
            ['R01', 'R', null, null, null, 'floor',   3000, 12.0, true, 'Réception transferts WH-SFAX'],
            ['R02', 'R', null, null, null, 'pallet',  1000,  4.0, true, 'Contrôle et comptage réception'],
            ['R03', 'R', null, null, null, 'staging',  500,  2.0, true, 'Saisie informatique réception'],
        ]);

        // ── WH-TUN / STAG-A ───────────────────────────────────────────────────
        $this->insertLocations($now, 'WH-TUN', 'STAG-A', [
            ['SA-01', 'A', '01', 'S1', null, 'rack',    200, 0.8, true, 'Extincteurs ABC 6kg — stock Tunis Nord'],
            ['SA-02', 'A', '01', 'S2', null, 'rack',    200, 0.8, true, 'Extincteurs ABC 9kg — stock Tunis Nord'],
            ['SA-03', 'A', '01', 'S3', null, 'rack',    200, 0.8, true, 'Extincteurs CO₂ 2kg — stock Tunis Nord'],
            ['SA-04', 'A', '02', 'S1', null, 'rack',    150, 0.6, true, 'EPI casques — stock Tunis Nord'],
            ['SA-05', 'A', '02', 'S2', null, 'shelf',   100, 0.4, true, 'EPI gants — stock Tunis Nord'],
            ['SA-06', 'A', '02', 'S3', null, 'shelf',   100, 0.4, true, 'EPI masques FFP2 — stock Tunis Nord'],
            ['SA-07', 'A', '03', 'S1', null, 'pallet', 1000, 4.0, true, 'Palette mixte commandes Tunis Nord en cours'],
            ['SA-08', 'A', '03', 'S2', null, 'pallet', 1000, 4.0, true, 'Palette mixte commandes Tunis Nord en cours'],
            ['SA-09', 'A', '03', 'S3', null, 'staging', 500, 2.0, true, 'Commandes urgentes Tunis Nord'],
        ]);

        // ── WH-TUN / STAG-B ───────────────────────────────────────────────────
        $this->insertLocations($now, 'WH-TUN', 'STAG-B', [
            ['SB-01', 'B', '01', 'S1', null, 'rack',    200, 0.8, true, 'Extincteurs ABC 6kg — stock Tunis Sud'],
            ['SB-02', 'B', '01', 'S2', null, 'rack',    200, 0.8, true, 'Extincteurs ABC 9kg — stock Tunis Sud'],
            ['SB-03', 'B', '01', 'S3', null, 'rack',    200, 0.8, true, 'Extincteurs CO₂ 2kg — stock Tunis Sud'],
            ['SB-04', 'B', '02', 'S1', null, 'shelf',   150, 0.6, true, 'EPI mixte — stock Tunis Sud'],
            ['SB-05', 'B', '02', 'S2', null, 'pallet', 1000, 4.0, true, 'Palette mixte commandes Tunis Sud'],
            ['SB-06', 'B', '02', 'S3', null, 'staging', 500, 2.0, true, 'Commandes urgentes Tunis Sud'],
        ]);

        // ── WH-TUN / PICK ─────────────────────────────────────────────────────
        $this->insertLocations($now, 'WH-TUN', 'PICK', [
            ['PK-01', 'P', '01', 'S1', null, 'pick', 100, 0.4, true, 'Fast-pick — Extincteurs ABC 6kg'],
            ['PK-02', 'P', '01', 'S2', null, 'pick', 100, 0.4, true, 'Fast-pick — Extincteurs CO₂ 2kg'],
            ['PK-03', 'P', '01', 'S3', null, 'pick', 100, 0.4, true, 'Fast-pick — Casques sécurité'],
            ['PK-04', 'P', '02', 'S1', null, 'pick',  50, 0.2, true, 'Fast-pick — Gants protection'],
            ['PK-05', 'P', '02', 'S2', null, 'pick',  50, 0.2, true, 'Fast-pick — Masques FFP2'],
            ['PK-06', 'P', '02', 'S3', null, 'pick',  50, 0.2, true, 'Fast-pick — Étiquettes + consommables'],
            ['PK-07', 'P', '03', 'S1', null, 'pick', 200, 0.8, true, 'Pick standard — Extincteurs ABC 9kg'],
            ['PK-08', 'P', '03', 'S2', null, 'pick', 200, 0.8, true, 'Pick standard — Extincteurs eau 9L'],
        ]);

        // ── WH-TUN / SHIP ─────────────────────────────────────────────────────
        $this->insertLocations($now, 'WH-TUN', 'SHIP', [
            ['SHP-01', 'S', null, null, null, 'floor',   3000, 12.0, true, 'Chargement livraisons clients Grand Tunis'],
            ['SHP-02', 'S', null, null, null, 'floor',   1500,  6.0, true, 'Véhicules techniciens terrain'],
            ['SHP-03', 'S', null, null, null, 'staging',  500,  2.0, true, 'Commandes express — départ dans l\'heure'],
        ]);

        // ── WH-TUN / RETURN ───────────────────────────────────────────────────
        $this->insertLocations($now, 'WH-TUN', 'RETURN', [
            ['RET-01', 'RT', null, null, null, 'returns', 500, 2.0, true, 'Retours clients Tunis — à transférer SFAX'],
            ['RET-02', 'RT', null, null, null, 'returns', 200, 1.0, true, 'Matériels défectueux — étiquetés pour atelier'],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // INSERT HELPER — avoids repeating the full insert block
    // ──────────────────────────────────────────────────────────────────────────
    private function insertLocations(
        string $now,
        string $siteCode,
        string $zoneCode,
        array $rows
    ): void {
        $zoneId = $this->zones[$siteCode.'.'.$zoneCode];

        foreach ($rows as [$suffix, $aisle, $rack, $shelf, $bin, $locType, $maxKg, $maxM3, $active, $notes]) {
            DB::table('warehouse_locations')->insert([
                'id' => $this->ulid(),
                'zone_id' => $zoneId,
                'barcode' => strtoupper("{$siteCode}-{$zoneCode}-{$suffix}"),
                'aisle' => $aisle,
                'rack' => $rack,
                'shelf' => $shelf,
                'bin' => $bin,
                'location_type' => $locType,
                'max_weight_kg' => $maxKg,
                'max_volume_m3' => $maxM3,
                'is_active' => $active,
                'notes' => $notes,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        }
    }
}
