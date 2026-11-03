<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\OperationalHub\Models\ChecklistTemplate;
use Illuminate\Database\Seeder;

class ChecklistTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'code'            => 'CKL-FLD-01',
                'name'            => ['en' => 'Field Service Safety & Pre-Operation', 'fr' => 'Sécurité et Pré-opération sur Terrain'],
                'description'     => ['en' => 'Standard inspection before starting field interventions', 'fr' => 'Inspection standard avant de démarrer les interventions sur site'],
                'applicable_mode' => 'field_service',
                'category'        => 'pre_operation',
                'is_active'       => true,
                'sort_order'      => 1,
                'items'           => [
                    ['title' => ['en' => 'Personal Protective Equipment (PPE) checked', 'fr' => 'Équipement de Protection Individuelle (EPI) vérifié'], 'answer_type' => 'pass_fail', 'is_required' => true],
                    ['title' => ['en' => 'Site safety briefing completed with client', 'fr' => 'Briefing sécurité réalisé avec le client'], 'answer_type' => 'yes_no', 'is_required' => true],
                    ['title' => ['en' => 'Tools and diagnostic instruments calibrated', 'fr' => 'Outils et instruments de diagnostic étalonnés'], 'answer_type' => 'pass_fail', 'is_required' => false],
                    ['title' => ['en' => 'Work perimeter secured and cordoned', 'fr' => 'Périmètre de travail sécurisé et balisé'], 'answer_type' => 'yes_no', 'is_required' => true],
                ],
            ],
            [
                'code'            => 'CKL-WKS-01',
                'name'            => ['en' => 'Workshop Equipment Reception & Diagnostic', 'fr' => 'Réception et Diagnostic d’Équipement Atelier'],
                'description'     => ['en' => 'Checklist for received equipment in workshop', 'fr' => 'Contrôle à la réception des matériels en atelier'],
                'applicable_mode' => 'workshop',
                'category'        => 'reception',
                'is_active'       => true,
                'sort_order'      => 2,
                'items'           => [
                    ['title' => ['en' => 'Visual inspection for external physical damage', 'fr' => 'Inspection visuelle des dommages physiques externes'], 'answer_type' => 'pass_fail', 'is_required' => true],
                    ['title' => ['en' => 'Serial number and asset tag verified against pass', 'fr' => 'Numéro de série et plaque vérifiés avec le bon'], 'answer_type' => 'yes_no', 'is_required' => true],
                    ['title' => ['en' => 'Accessories and cables accounted for', 'fr' => 'Accessoires et câbles inventoriés'], 'answer_type' => 'yes_no', 'is_required' => true],
                    ['title' => ['en' => 'Initial electrical / mechanical diagnostic run', 'fr' => 'Test de diagnostic initial électrique / mécanique'], 'answer_type' => 'pass_fail', 'is_required' => false],
                ],
            ],
            [
                'code'            => 'CKL-MOB-01',
                'name'            => ['en' => 'Mobile Workshop Vehicle & Rig Check', 'fr' => 'Contrôle Véhicule Atelier Mobile'],
                'description'     => ['en' => 'Field-service vehicle configured as a mobile workshop: vehicle and onboard unit inspection', 'fr' => 'Véhicule de service configuré en atelier mobile : inspection du véhicule et de l’unité embarquée'],
                'applicable_mode' => 'field_service',
                'category'        => 'pre_operation',
                'is_active'       => true,
                'sort_order'      => 3,
                'items'           => [
                    ['title' => ['en' => 'Vehicle fuel and engine fluids level verified', 'fr' => 'Niveau de carburant et fluides vérifiés'], 'answer_type' => 'pass_fail', 'is_required' => true],
                    ['title' => ['en' => 'Generator and compressor operational', 'fr' => 'Générateur et compresseur opérationnels'], 'answer_type' => 'pass_fail', 'is_required' => true],
                    ['title' => ['en' => 'Spare parts and mobile tooling inventory complete', 'fr' => 'Inventaire pièces détachées et outillage mobile complet'], 'answer_type' => 'yes_no', 'is_required' => true],
                    ['title' => ['en' => 'Emergency fire extinguisher and first aid kit present', 'fr' => 'Extincteur et trousse de premiers secours présents'], 'answer_type' => 'yes_no', 'is_required' => true],
                ],
            ],
        ];

        foreach ($templates as $data) {
            $items = $data['items'];
            unset($data['items']);

            $template = ChecklistTemplate::firstOrCreate(['code' => $data['code']], $data);
            if ($template->wasRecentlyCreated) {
                foreach ($items as $idx => $item) {
                    $template->items()->create([
                        'title'       => $item['title'],
                        'answer_type' => $item['answer_type'],
                        'is_required' => $item['is_required'],
                        'is_active'   => true,
                        'sort_order'  => $idx,
                    ]);
                }
            }
        }
    }
}
