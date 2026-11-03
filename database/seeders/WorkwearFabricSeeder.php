<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\ClientAssets\Models\WorkwearFabric;
use Illuminate\Database\Seeder;

class WorkwearFabricSeeder extends Seeder
{
    public static function defaultFabrics(): array
    {
        return [
            [
                'code' => 'FAB-TWILL-245',
                'name' => 'Poly-Cotton Twill 245 g/m²',
                'composition' => '65% Polyester / 35% Cotton',
                'weight_gsm' => 245,
                'weave_type' => 'Twill 2/1',
                'properties' => ['Industrial Wash ISO 15797', 'Abrasion Resistant', 'Breathable'],
                'description' => 'Standard industrial workwear twill fabric suitable for coveralls, jackets, and trousers.',
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'FAB-TWILL-300',
                'name' => 'Heavy Cotton-Rich Twill 300 g/m²',
                'composition' => '60% Cotton / 40% Polyester',
                'weight_gsm' => 300,
                'weave_type' => 'Twill 3/1',
                'properties' => ['Heavy Duty', 'Industrial Wash ISO 15797', 'High Tear Strength'],
                'description' => 'Heavyweight twill designed for rugged mechanical and construction environments.',
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'code' => 'FAB-RIPSTOP-260',
                'name' => 'Stretch Ripstop 260 g/m²',
                'composition' => '64% Polyester / 34% Cotton / 2% Elastane',
                'weight_gsm' => 260,
                'weave_type' => 'Ripstop',
                'properties' => ['2-Way Stretch', 'Anti-Tear Grid', 'Water Repellent'],
                'description' => 'Ergonomic ripstop weave preventing tear propagation with mechanical stretch comfort.',
                'is_active' => true,
                'sort_order' => 30,
            ],
            [
                'code' => 'FAB-FR-PROBAN-350',
                'name' => 'Proban® FR Treated Cotton 350 g/m²',
                'composition' => '99% FR Cotton / 1% Antistatic Carbon Fiber',
                'weight_gsm' => 350,
                'weave_type' => 'Satin 4/1',
                'properties' => ['Flame Retardant', 'Antistatic EN 1149-5', 'Welding EN ISO 11611', 'Heat & Flame EN ISO 11612'],
                'description' => 'Multi-norm flame retardant and antistatic cotton satin for welding, petrochemical, and foundry operations.',
                'is_active' => true,
                'sort_order' => 40,
            ],
            [
                'code' => 'FAB-FR-MODACRYLIC-310',
                'name' => 'Inherent FR Modacrylic Blend 310 g/m²',
                'composition' => '54% Modacrylic / 44% Cotton / 2% Antistatic Carbon',
                'weight_gsm' => 310,
                'weave_type' => 'Twill',
                'properties' => ['Inherent FR', 'Electric Arc IEC 61482-2', 'Antistatic EN 1149-5', 'Chemical Splash EN 13034'],
                'description' => 'Inherently flame-resistant multi-risk fabric whose FR properties cannot wash out.',
                'is_active' => true,
                'sort_order' => 50,
            ],
            [
                'code' => 'FAB-NOMEX-220',
                'name' => 'Nomex® IIIA Inherent Aramid 220 g/m²',
                'composition' => '93% Meta-Aramid (Nomex) / 5% Para-Aramid (Kevlar) / 2% Carbon Fiber',
                'weight_gsm' => 220,
                'weave_type' => 'Plain Weave / Ripstop',
                'properties' => ['Inherent FR', 'Lightweight High Thermal', 'Antistatic EN 1149-5', 'Oil & Gas Offshore'],
                'description' => 'Premium lightweight inherent aramid fabric for offshore oil & gas, utilities, and petrochemical hazards.',
                'is_active' => true,
                'sort_order' => 60,
            ],
            [
                'code' => 'FAB-HIVIS-OXFORD-300D',
                'name' => 'Hi-Vis 300D Oxford PU Coated 190 g/m²',
                'composition' => '100% Polyester 300D Oxford with Breathable PU Coating',
                'weight_gsm' => 190,
                'weave_type' => 'Oxford 300D',
                'properties' => ['High Visibility EN ISO 20471', 'Waterproof EN 343 Class 3', 'Windproof', 'Stain Resistant'],
                'description' => 'Weatherproof fluorescent outer shell fabric for high-visibility parkas, rain jackets, and overtrousers.',
                'is_active' => true,
                'sort_order' => 70,
            ],
            [
                'code' => 'FAB-SOFTSHELL-3L-320',
                'name' => '3-Layer Technical Softshell 320 g/m²',
                'composition' => '92% Polyester / 8% Elastane + TPU Membrane + Microfleece Backing',
                'weight_gsm' => 320,
                'weave_type' => '3-Layer Laminated',
                'properties' => ['Windproof', 'Water Repellent (8000mm)', 'Breathable (3000 MVP)', '4-Way Stretch'],
                'description' => 'Flexible weather-resistant laminated softshell with thermal microfleece interior.',
                'is_active' => true,
                'sort_order' => 80,
            ],
            [
                'code' => 'FAB-PIQUE-210',
                'name' => 'Combed Cotton-Poly Piqué Knit 210 g/m²',
                'composition' => '50% Combed Cotton / 50% Polyester',
                'weight_gsm' => 210,
                'weave_type' => 'Piqué Knit',
                'properties' => ['Moisture Wicking', 'Colorfast', 'Shape Retention', 'UV UPF 40+'],
                'description' => 'Breathable knitted piqué fabric for work polo shirts and t-shirts.',
                'is_active' => true,
                'sort_order' => 90,
            ],
            [
                'code' => 'FAB-CORDURA-500D',
                'name' => 'Cordura® 500D Polyamide 240 g/m²',
                'composition' => '100% High-Tenacity Polyamide 6.6 (Cordura®)',
                'weight_gsm' => 240,
                'weave_type' => 'Canvas / Textured Nylon',
                'properties' => ['Extreme Abrasion Resistance', 'Knee & Holster Reinforcement', 'Water Repellent'],
                'description' => 'High-tenacity reinforcement fabric for knee pad pockets, cuffs, and holster pouches.',
                'is_active' => true,
                'sort_order' => 100,
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::defaultFabrics() as $fabric) {
            WorkwearFabric::updateOrCreate(
                ['code' => $fabric['code']],
                $fabric
            );
        }
    }
}
