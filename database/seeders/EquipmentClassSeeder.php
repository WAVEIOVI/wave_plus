<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EquipmentClassSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $classes = [
            ['portable_fire_extinguisher', 'Portable Fire Extinguisher', 'Extincteur Portable', 'طفاية حريق يدوية', 'Portable dry powder, CO2, foam, or water fire extinguishers', 1],
            ['wheeled_fire_extinguisher', 'Wheeled Fire Extinguisher', 'Extincteur sur Roues', 'طفاية حريق متحركة', 'Heavy duty wheeled fire extinguishers 25-50kg', 2],
            ['fire_alarm_panel', 'Fire Alarm Panel', "Centrale d'Alarme Incendie", 'لوحة إنذار الحريق', 'Conventional and addressable fire detection control panels', 3],
            ['hydrant', 'Hydrant', "Poteau d'Incendie / Hydrant", 'صنبور إطفاء الحريق', 'Outdoor pillar hydrants and underground fire hydrants', 4],
            ['fire_hose_reel', 'Fire Hose Reel', 'Robinet d’Incendie Armé (RIA)', 'بكرة خراطيم الإطفاء', 'Wall-mounted and cabinet fire hose reels (RIA)', 5],
            ['emergency_light', 'Emergency Light', 'Bloc de Secours (BAES)', 'إضاءة الطوارئ', 'Emergency exit signs and self-contained escape lights', 6],
            ['pump', 'Pump', 'Motopompe / Pompe Incendie', 'مضخة إطفاء', 'Fire booster sets, electric and diesel fire pumps', 7],
            ['generator', 'Generator', 'Groupe Électrogène', 'مولد كهربائي', 'Emergency backup power generator sets', 8],
            ['vehicle', 'Vehicle', "Véhicule d'Intervention", 'مركبة تدخل', 'Fire response and rescue vehicles', 9],
            ['other', 'Other Equipment', 'Autre Équipement', 'معدات أخرى', 'Specialized equipment and accessories', 10],
        ];

        foreach ($classes as [$code, $en, $fr, $ar, $description, $sort]) {
            $id = DB::table('equipment_classes')->where('code', $code)->value('id') ?: (string) Str::ulid();

            DB::table('equipment_classes')->updateOrInsert(
                ['code' => $code],
                [
                    'id' => $id,
                    'name' => json_encode(['en' => $en, 'fr' => $fr, 'ar' => $ar], JSON_UNESCAPED_UNICODE),
                    'description' => json_encode(['en' => $description, 'fr' => $description, 'ar' => $description], JSON_UNESCAPED_UNICODE),
                    'parent_id' => null,
                    'status' => 'active',
                    'sort_order' => $sort,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
