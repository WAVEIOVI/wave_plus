<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Support;

use App\Modules\ClientAssets\Enums\WorkwearSilhouette;

/**
 * Canonical 2D Garment Studio coordinate, scale, zone, colour-region and safe-boundary
 * specification shared between backend validation/normalization and the Vue 2D Studio.
 *
 * Coordinate system contract:
 * - SVG template viewBox is 320 x 420 units (center axis at x = 160).
 * - Each garment template defines a single uniform `units_per_mm` factor used for both
 *   physical placement dimensions (`width_mm`, `height_mm`) and offsets (`offset_x_mm`, `offset_y_mm`).
 * - Scale is a proportional schematic template scale (`scale_verified = false`), not a
 *   pattern-graded production CAD marker.
 */
final class WorkwearStudioTemplateCatalog
{
    public const VIEWBOX_WIDTH = 320;

    public const VIEWBOX_HEIGHT = 420;

    public const DEFAULT_TYPE_DIMENSIONS_MM = [
        'logo' => ['width_mm' => 80.0, 'height_mm' => 35.0],
        'text' => ['width_mm' => 220.0, 'height_mm' => 45.0],
        'flag' => ['width_mm' => 60.0, 'height_mm' => 40.0],
        'badge' => ['width_mm' => 90.0, 'height_mm' => 28.0],
        'reflective_band' => ['width_mm' => 240.0, 'height_mm' => 50.0],
        'fluo' => ['width_mm' => 240.0, 'height_mm' => 80.0],
        'pocket' => ['width_mm' => 120.0, 'height_mm' => 140.0],
        'custom' => ['width_mm' => 80.0, 'height_mm' => 40.0],
    ];

    /**
     * Return the full studio template catalog keyed by silhouette value.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $poloTemplate = [
            'silhouette' => 'polo',
            'label' => 'Polo Shirt',
            'view_box' => ['width' => self::VIEWBOX_WIDTH, 'height' => self::VIEWBOX_HEIGHT],
            'garment_bounds' => ['x' => 56, 'y' => 44, 'width' => 208, 'height' => 208],
            'scale' => [
                'units_per_mm' => 0.28,
                'reference_width_mm' => 520,
                'reference_height_mm' => 720,
                'scale_mode' => 'proportional_template',
                'scale_verified' => false,
            ],
            'color_regions' => [
                ['key' => 'body', 'label' => 'Main Torso & Sleeves', 'role' => 'primary', 'slot' => 0],
                ['key' => 'shoulder_panels', 'label' => 'Contrast Shoulder Panels', 'role' => 'contrast', 'slot' => 1],
                ['key' => 'collar_placket', 'label' => 'Ribbed Collar & Button Placket', 'role' => 'primary', 'slot' => 0],
                ['key' => 'cuff_trim', 'label' => 'Sleeve Cuff Trim', 'role' => 'contrast', 'slot' => 1],
            ],
            'zones' => [
                'chest_left' => self::zone('chest_left', 'Chest Left (Heart / Logo)', ['front'], 190, 122, [158, 88, 58, 68], [15, 180, 10, 160], [65, 70]),
                'chest_right' => self::zone('chest_right', 'Chest Right (Name / Badge)', ['front'], 130, 122, [104, 88, 58, 68], [15, 180, 10, 160], [65, 70]),
                'chest_center' => self::zone('chest_center', 'Chest Center (Below Placket)', ['front'], 160, 148, [104, 108, 112, 84], [15, 360, 10, 220], [125, 95]),
                'pocket_left' => self::zone('pocket_left', 'Left Breast Pocket', ['front'], 190, 140, [158, 106, 58, 64], [15, 170, 10, 150], [65, 65]),
                'pocket_right' => self::zone('pocket_right', 'Right Breast Pocket', ['front'], 130, 140, [104, 106, 58, 64], [15, 170, 10, 150], [65, 65]),
                'shoulder_left' => self::zone('shoulder_left', 'Left Shoulder Panel', ['front', 'back'], 204, 82, [168, 56, 66, 52], [15, 180, 10, 130], [65, 55]),
                'shoulder_right' => self::zone('shoulder_right', 'Right Shoulder Panel', ['front', 'back'], 116, 82, [86, 56, 66, 52], [15, 180, 10, 130], [65, 55]),
                'collar' => self::zone('collar', 'Collar / Nape', ['front', 'back'], 160, 58, [118, 42, 84, 42], [15, 200, 10, 100], [75, 45]),
                'sleeve_left' => self::zone('sleeve_left', 'Left Sleeve', ['front', 'back'], 238, 118, [210, 78, 56, 78], [15, 160, 10, 180], [60, 75]),
                'sleeve_right' => self::zone('sleeve_right', 'Right Sleeve', ['front', 'back'], 82, 118, [54, 78, 56, 78], [15, 160, 10, 180], [60, 75]),
                'waist' => self::zone('waist', 'Mid Torso / Waist', ['front', 'back'], 160, 198, [104, 164, 112, 66], [15, 360, 10, 180], [125, 75]),
                'hem' => self::zone('hem', 'Shirt Bottom Hem', ['front', 'back'], 160, 232, [104, 200, 112, 54], [15, 360, 10, 140], [125, 60]),
                'back_upper' => self::zone('back_upper', 'Upper Back (Shoulder Yoke)', ['back'], 160, 112, [104, 70, 112, 84], [15, 360, 10, 220], [125, 95]),
                'back_center' => self::zone('back_center', 'Center Back', ['back'], 160, 158, [104, 114, 112, 88], [15, 360, 10, 240], [125, 100]),
                'back_lower' => self::zone('back_lower', 'Lower Back', ['back'], 160, 208, [104, 168, 112, 80], [15, 360, 10, 200], [125, 85]),
            ],
        ];

        $trousersTemplate = [
            'silhouette' => 'trousers',
            'label' => 'Work / Cargo Trousers',
            'view_box' => ['width' => self::VIEWBOX_WIDTH, 'height' => self::VIEWBOX_HEIGHT],
            'garment_bounds' => ['x' => 94, 'y' => 78, 'width' => 132, 'height' => 304],
            'scale' => [
                'units_per_mm' => 0.28,
                'reference_width_mm' => 480,
                'reference_height_mm' => 1060,
                'scale_mode' => 'proportional_template',
                'scale_verified' => false,
            ],
            'color_regions' => [
                ['key' => 'main_fabric', 'label' => 'Main Legs & Waistband', 'role' => 'primary', 'slot' => 0],
                ['key' => 'cargo_accents', 'label' => 'Contrast Cargo & Hip Gussets', 'role' => 'contrast', 'slot' => 1],
                ['key' => 'knee_reinforcements', 'label' => 'Knee Pad Reinforcements', 'role' => 'primary', 'slot' => 0],
                ['key' => 'reflective_bands', 'label' => 'Ankle Reflective Bands', 'role' => 'reflective', 'slot' => null, 'default_color' => 'silver'],
            ],
            'zones' => [
                'waist' => self::zone('waist', 'Waistband / Belt Line', ['front', 'back'], 160, 90, [102, 74, 116, 48], [15, 380, 10, 120], [130, 50]),
                'pocket_left' => self::zone('pocket_left', 'Left Hip / Holster Pocket', ['front', 'back'], 192, 136, [158, 98, 62, 78], [15, 190, 10, 200], [70, 80]),
                'pocket_right' => self::zone('pocket_right', 'Right Hip / Holster Pocket', ['front', 'back'], 128, 136, [100, 98, 62, 78], [15, 190, 10, 200], [70, 80]),
                'back_lower' => self::zone('back_lower', 'Rear Yoke / Seat Panel', ['back'], 160, 128, [102, 94, 116, 72], [15, 360, 10, 180], [125, 75]),
                'thigh_left' => self::zone('thigh_left', 'Left Thigh / Cargo Pocket', ['front', 'back'], 198, 242, [164, 184, 62, 104], [15, 190, 10, 260], [70, 110]),
                'thigh_right' => self::zone('thigh_right', 'Right Thigh / Cargo Pocket', ['front', 'back'], 122, 242, [94, 184, 62, 104], [15, 190, 10, 260], [70, 110]),
                'knee_left' => self::zone('knee_left', 'Left Knee Pad Panel', ['front', 'back'], 198, 314, [166, 276, 60, 80], [15, 180, 10, 200], [65, 85]),
                'knee_right' => self::zone('knee_right', 'Right Knee Pad Panel', ['front', 'back'], 122, 314, [94, 276, 60, 80], [15, 180, 10, 200], [65, 85]),
                'hem' => self::zone('hem', 'Lower Leg / Ankle Cuff', ['front', 'back'], 160, 354, [94, 326, 132, 58], [15, 420, 10, 160], [140, 65]),
            ],
        ];

        return [
            'coverall' => [
                'silhouette' => 'coverall',
                'label' => 'Coverall / Boilersuit',
                'view_box' => ['width' => self::VIEWBOX_WIDTH, 'height' => self::VIEWBOX_HEIGHT],
                'garment_bounds' => ['x' => 46, 'y' => 42, 'width' => 228, 'height' => 342],
                'scale' => [
                    'units_per_mm' => 0.25,
                    'reference_width_mm' => 550,
                    'reference_height_mm' => 1600,
                    'scale_mode' => 'proportional_template',
                    'scale_verified' => false,
                ],
                'color_regions' => [
                    ['key' => 'body', 'label' => 'Main Torso & Legs', 'role' => 'primary', 'slot' => 0],
                    ['key' => 'yoke', 'label' => 'Upper Chest & Shoulder Yoke', 'role' => 'contrast', 'slot' => 1],
                    ['key' => 'collar', 'label' => 'Collar & Waistband', 'role' => 'primary', 'slot' => 0],
                    ['key' => 'pockets', 'label' => 'Chest Pocket Flaps', 'role' => 'contrast', 'slot' => 1],
                    ['key' => 'reflective', 'label' => 'Reflective Tape Strips', 'role' => 'reflective', 'slot' => null, 'default_color' => 'silver'],
                ],
                'zones' => [
                    'chest_left' => self::zone('chest_left', 'Chest Left (Pocket Flap)', ['front'], 190, 124, [156, 88, 66, 72], [15, 220, 10, 160], [75, 75]),
                    'chest_right' => self::zone('chest_right', 'Chest Right (Name Patch)', ['front'], 130, 124, [98, 88, 66, 72], [15, 220, 10, 160], [75, 75]),
                    'chest_center' => self::zone('chest_center', 'Chest Center', ['front'], 160, 136, [100, 88, 120, 96], [15, 420, 10, 260], [140, 110]),
                    'pocket_left' => self::zone('pocket_left', 'Left Chest Pocket', ['front'], 190, 142, [158, 106, 62, 72], [15, 200, 10, 180], [70, 75]),
                    'pocket_right' => self::zone('pocket_right', 'Right Chest Pocket', ['front'], 130, 142, [100, 106, 62, 72], [15, 200, 10, 180], [70, 75]),
                    'shoulder_left' => self::zone('shoulder_left', 'Left Shoulder', ['front', 'back'], 206, 84, [170, 54, 68, 58], [15, 200, 10, 140], [70, 60]),
                    'shoulder_right' => self::zone('shoulder_right', 'Right Shoulder', ['front', 'back'], 114, 84, [82, 54, 68, 58], [15, 200, 10, 140], [70, 60]),
                    'collar' => self::zone('collar', 'Collar / Neck', ['front', 'back'], 160, 60, [118, 38, 84, 46], [15, 220, 10, 110], [80, 50]),
                    'sleeve_left' => self::zone('sleeve_left', 'Left Sleeve', ['front', 'back'], 244, 138, [212, 82, 62, 106], [15, 180, 10, 220], [70, 100]),
                    'sleeve_right' => self::zone('sleeve_right', 'Right Sleeve', ['front', 'back'], 76, 138, [46, 82, 62, 106], [15, 180, 10, 220], [70, 100]),
                    'waist' => self::zone('waist', 'Waist / Belt Line', ['front', 'back'], 160, 205, [98, 170, 124, 70], [15, 440, 10, 180], [150, 80]),
                    'thigh_left' => self::zone('thigh_left', 'Left Thigh / Cargo', ['front', 'back'], 196, 278, [164, 232, 60, 92], [15, 200, 10, 240], [70, 100]),
                    'thigh_right' => self::zone('thigh_right', 'Right Thigh / Cargo', ['front', 'back'], 124, 278, [96, 232, 60, 92], [15, 200, 10, 240], [70, 100]),
                    'knee_left' => self::zone('knee_left', 'Left Knee / Lower Leg', ['front', 'back'], 196, 336, [166, 296, 58, 86], [15, 190, 10, 220], [65, 90]),
                    'knee_right' => self::zone('knee_right', 'Right Knee / Lower Leg', ['front', 'back'], 124, 336, [96, 296, 58, 86], [15, 190, 10, 220], [65, 90]),
                    'hem' => self::zone('hem', 'Lower Leg Hem', ['front', 'back'], 160, 368, [96, 340, 128, 46], [15, 440, 10, 140], [150, 55]),
                    'back_upper' => self::zone('back_upper', 'Upper Back (Shoulder Blade)', ['back'], 160, 112, [98, 68, 124, 88], [15, 440, 10, 240], [150, 100]),
                    'back_center' => self::zone('back_center', 'Center Back', ['back'], 160, 156, [100, 112, 120, 92], [15, 420, 10, 260], [140, 110]),
                    'back_lower' => self::zone('back_lower', 'Lower Back / Lumbar', ['back'], 160, 194, [100, 154, 120, 80], [15, 420, 10, 220], [140, 95]),
                ],
            ],

            'jacket' => self::jacketOrParkaTemplate('jacket', 'Work Jacket / Softshell', false),
            'parka' => self::jacketOrParkaTemplate('parka', 'Weatherproof Parka', true),
            'sweatshirt' => self::jacketOrParkaTemplate('sweatshirt', 'Work Sweatshirt / Hoodie / Fleece', false),
            'lab_coat' => self::jacketOrParkaTemplate('lab_coat', 'Lab Coat / Hygiene Smock / Tunic', true),

            'vest' => [
                'silhouette' => 'vest',
                'label' => 'Hi-Vis Safety Vest / Gilet',
                'view_box' => ['width' => self::VIEWBOX_WIDTH, 'height' => self::VIEWBOX_HEIGHT],
                'garment_bounds' => ['x' => 100, 'y' => 56, 'width' => 120, 'height' => 192],
                'scale' => [
                    'units_per_mm' => 0.28,
                    'reference_width_mm' => 540,
                    'reference_height_mm' => 680,
                    'scale_mode' => 'proportional_template',
                    'scale_verified' => false,
                ],
                'color_regions' => [
                    ['key' => 'hi_vis_body', 'label' => 'Fluorescent Main Body', 'role' => 'contrast', 'slot' => 1],
                    ['key' => 'contrast_panel', 'label' => 'Lower Contrast Dirt Panel', 'role' => 'primary', 'slot' => 0],
                    ['key' => 'binding_trim', 'label' => 'Edge Binding & Zip Trim', 'role' => 'primary', 'slot' => 0],
                    ['key' => 'reflective_tape', 'label' => 'EN ISO 20471 Reflective Harness', 'role' => 'reflective', 'slot' => null, 'default_color' => 'silver'],
                ],
                'zones' => [
                    'chest_left' => self::zone('chest_left', 'Chest Left (ID / Logo)', ['front'], 192, 128, [158, 90, 58, 70], [15, 180, 10, 160], [65, 70]),
                    'chest_right' => self::zone('chest_right', 'Chest Right', ['front'], 128, 128, [104, 90, 58, 70], [15, 180, 10, 160], [65, 70]),
                    'chest_center' => self::zone('chest_center', 'Chest Center', ['front'], 160, 138, [104, 92, 112, 86], [15, 360, 10, 220], [120, 95]),
                    'pocket_left' => self::zone('pocket_left', 'Left Lower Pocket', ['front'], 190, 176, [158, 144, 58, 66], [15, 180, 10, 160], [65, 65]),
                    'pocket_right' => self::zone('pocket_right', 'Right Lower Pocket', ['front'], 130, 176, [104, 144, 58, 66], [15, 180, 10, 160], [65, 65]),
                    'shoulder_left' => self::zone('shoulder_left', 'Left Shoulder Strap', ['front', 'back'], 196, 82, [170, 56, 48, 52], [15, 140, 10, 120], [50, 50]),
                    'shoulder_right' => self::zone('shoulder_right', 'Right Shoulder Strap', ['front', 'back'], 124, 82, [102, 56, 48, 52], [15, 140, 10, 120], [50, 50]),
                    'waist' => self::zone('waist', 'Waist Band / Torso Band', ['front', 'back'], 160, 196, [102, 160, 116, 68], [15, 380, 10, 180], [130, 75]),
                    'hem' => self::zone('hem', 'Lower Contrast Hem', ['front', 'back'], 160, 224, [102, 194, 116, 56], [15, 380, 10, 150], [130, 60]),
                    'back_upper' => self::zone('back_upper', 'Upper Back (Between Strips)', ['back'], 160, 114, [104, 72, 112, 84], [15, 360, 10, 220], [125, 95]),
                    'back_center' => self::zone('back_center', 'Center Back Panel', ['back'], 160, 152, [104, 106, 112, 92], [15, 360, 10, 240], [125, 100]),
                    'back_lower' => self::zone('back_lower', 'Lower Back Contrast Panel', ['back'], 160, 214, [102, 180, 116, 68], [15, 380, 10, 180], [130, 75]),
                ],
            ],

            'trousers' => $trousersTemplate,
            'shorts' => array_merge($trousersTemplate, [
                'silhouette' => 'shorts',
                'label' => 'Work Shorts / Cargo Bermuda',
                'garment_bounds' => ['x' => 94, 'y' => 78, 'width' => 132, 'height' => 190],
                'zones' => [
                    'waist' => self::zone('waist', 'Waistband / Belt Line', ['front', 'back'], 160, 90, [102, 74, 116, 48], [15, 380, 10, 120], [130, 50]),
                    'pocket_left' => self::zone('pocket_left', 'Left Hip / Holster Pocket', ['front', 'back'], 192, 136, [158, 98, 62, 78], [15, 190, 10, 200], [70, 80]),
                    'pocket_right' => self::zone('pocket_right', 'Right Hip / Holster Pocket', ['front', 'back'], 128, 136, [100, 98, 62, 78], [15, 190, 10, 200], [70, 80]),
                    'back_lower' => self::zone('back_lower', 'Rear Yoke / Seat Panel', ['back'], 160, 128, [102, 94, 116, 72], [15, 360, 10, 180], [125, 75]),
                    'thigh_left' => self::zone('thigh_left', 'Left Thigh / Cargo Pocket', ['front', 'back'], 196, 212, [162, 170, 62, 86], [15, 190, 10, 220], [70, 90]),
                    'thigh_right' => self::zone('thigh_right', 'Right Thigh / Cargo Pocket', ['front', 'back'], 124, 212, [96, 170, 62, 86], [15, 190, 10, 220], [70, 90]),
                    'hem' => self::zone('hem', 'Shorts Hem / Cuff', ['front', 'back'], 160, 248, [96, 224, 128, 44], [15, 400, 10, 120], [130, 50]),
                ],
            ]),

            'polo' => $poloTemplate,
            'tshirt' => array_merge($poloTemplate, [
                'silhouette' => 'tshirt',
                'label' => 'Work T-Shirt (Crew-Neck)',
            ]),
            'shirt' => array_merge($poloTemplate, [
                'silhouette' => 'shirt',
                'label' => 'Button-Down Work Shirt / Overshirt',
                'garment_bounds' => ['x' => 46, 'y' => 44, 'width' => 228, 'height' => 210],
            ]),

            'overalls' => [
                'silhouette' => 'overalls',
                'label' => 'Bib & Brace Overalls',
                'view_box' => ['width' => self::VIEWBOX_WIDTH, 'height' => self::VIEWBOX_HEIGHT],
                'garment_bounds' => ['x' => 94, 'y' => 56, 'width' => 132, 'height' => 326],
                'scale' => [
                    'units_per_mm' => 0.26,
                    'reference_width_mm' => 520,
                    'reference_height_mm' => 1450,
                    'scale_mode' => 'proportional_template',
                    'scale_verified' => false,
                ],
                'color_regions' => [
                    ['key' => 'body', 'label' => 'Bib & Trouser Legs', 'role' => 'primary', 'slot' => 0],
                    ['key' => 'straps_bib_pocket', 'label' => 'Shoulder Straps & Bib Pocket', 'role' => 'contrast', 'slot' => 1],
                    ['key' => 'reflective_bands', 'label' => 'Leg Reflective Bands', 'role' => 'reflective', 'slot' => null, 'default_color' => 'silver'],
                ],
                'zones' => [
                    'chest_center' => self::zone('chest_center', 'Center Bib Pocket', ['front'], 160, 145, [118, 110, 84, 72], [15, 280, 10, 200], [95, 80]),
                    'chest_left' => self::zone('chest_left', 'Left Bib', ['front'], 180, 145, [150, 110, 54, 70], [15, 180, 10, 180], [60, 75]),
                    'chest_right' => self::zone('chest_right', 'Right Bib', ['front'], 140, 145, [116, 110, 54, 70], [15, 180, 10, 180], [60, 75]),
                    'pocket_left' => self::zone('pocket_left', 'Left Hip Pocket', ['front', 'back'], 190, 210, [158, 175, 58, 70], [15, 190, 10, 180], [65, 75]),
                    'pocket_right' => self::zone('pocket_right', 'Right Hip Pocket', ['front', 'back'], 130, 210, [104, 175, 58, 70], [15, 190, 10, 180], [65, 75]),
                    'shoulder_left' => self::zone('shoulder_left', 'Left Brace Strap', ['front', 'back'], 187, 86, [170, 56, 36, 60], [10, 110, 10, 160], [40, 65]),
                    'shoulder_right' => self::zone('shoulder_right', 'Right Brace Strap', ['front', 'back'], 133, 86, [114, 56, 36, 60], [10, 110, 10, 160], [40, 65]),
                    'waist' => self::zone('waist', 'Waist Seam', ['front', 'back'], 160, 196, [102, 168, 116, 60], [15, 380, 10, 160], [130, 70]),
                    'thigh_left' => self::zone('thigh_left', 'Left Thigh / Cargo', ['front', 'back'], 196, 278, [164, 232, 60, 92], [15, 190, 10, 240], [65, 100]),
                    'thigh_right' => self::zone('thigh_right', 'Right Thigh / Cargo', ['front', 'back'], 124, 278, [96, 232, 60, 92], [15, 190, 10, 240], [65, 100]),
                    'knee_left' => self::zone('knee_left', 'Left Knee', ['front', 'back'], 196, 336, [166, 296, 58, 86], [15, 180, 10, 220], [65, 90]),
                    'knee_right' => self::zone('knee_right', 'Right Knee', ['front', 'back'], 124, 336, [96, 296, 58, 86], [15, 180, 10, 220], [65, 90]),
                    'hem' => self::zone('hem', 'Lower Leg Hem', ['front', 'back'], 160, 364, [94, 332, 132, 52], [15, 420, 10, 150], [140, 60]),
                    'back_upper' => self::zone('back_upper', 'Upper Back Bib', ['back'], 160, 138, [114, 104, 92, 72], [15, 300, 10, 200], [100, 80]),
                    'back_center' => self::zone('back_center', 'Center Back', ['back'], 160, 178, [104, 140, 112, 76], [15, 360, 10, 200], [125, 85]),
                    'back_lower' => self::zone('back_lower', 'Lower Back / Seat', ['back'], 160, 218, [102, 180, 116, 76], [15, 380, 10, 200], [130, 85]),
                ],
            ],

            'apron' => [
                'silhouette' => 'apron',
                'label' => 'Apron / Tabard / Tunic',
                'view_box' => ['width' => self::VIEWBOX_WIDTH, 'height' => self::VIEWBOX_HEIGHT],
                'garment_bounds' => ['x' => 102, 'y' => 56, 'width' => 116, 'height' => 286],
                'scale' => [
                    'units_per_mm' => 0.28,
                    'reference_width_mm' => 500,
                    'reference_height_mm' => 950,
                    'scale_mode' => 'proportional_template',
                    'scale_verified' => false,
                ],
                'color_regions' => [
                    ['key' => 'body', 'label' => 'Main Apron / Tabard Body', 'role' => 'primary', 'slot' => 0],
                    ['key' => 'front_pocket', 'label' => 'Center Pouch Pocket', 'role' => 'contrast', 'slot' => 1],
                ],
                'zones' => [
                    'chest_center' => self::zone('chest_center', 'Upper Bib Center', ['front'], 160, 136, [106, 96, 108, 84], [15, 340, 10, 220], [120, 90]),
                    'chest_left' => self::zone('chest_left', 'Upper Bib Left', ['front'], 186, 136, [154, 98, 60, 76], [15, 180, 10, 180], [65, 80]),
                    'chest_right' => self::zone('chest_right', 'Upper Bib Right', ['front'], 134, 136, [106, 98, 60, 76], [15, 180, 10, 180], [65, 80]),
                    'pocket_left' => self::zone('pocket_left', 'Left Pouch Pocket', ['front'], 178, 212, [148, 180, 62, 68], [15, 180, 10, 160], [65, 70]),
                    'pocket_right' => self::zone('pocket_right', 'Right Pouch Pocket', ['front'], 142, 212, [110, 180, 62, 68], [15, 180, 10, 160], [65, 70]),
                    'waist' => self::zone('waist', 'Center Waist / Pocket Area', ['front', 'back'], 160, 212, [104, 174, 112, 76], [15, 360, 10, 200], [125, 85]),
                    'hem' => self::zone('hem', 'Lower Apron Skirt', ['front', 'back'], 160, 296, [106, 248, 108, 92], [15, 340, 10, 240], [120, 100]),
                    'back_upper' => self::zone('back_upper', 'Upper Back Tabard', ['back'], 160, 136, [106, 96, 108, 84], [15, 340, 10, 220], [120, 90]),
                    'back_center' => self::zone('back_center', 'Center Back', ['back'], 160, 212, [104, 168, 112, 92], [15, 360, 10, 240], [125, 100]),
                ],
            ],

            'boots' => [
                'silhouette' => 'boots',
                'label' => 'Safety Boots / Footwear',
                'view_box' => ['width' => self::VIEWBOX_WIDTH, 'height' => self::VIEWBOX_HEIGHT],
                'garment_bounds' => ['x' => 60, 'y' => 116, 'width' => 200, 'height' => 204],
                'scale' => [
                    'units_per_mm' => 0.32,
                    'reference_width_mm' => 320,
                    'reference_height_mm' => 360,
                    'scale_mode' => 'proportional_template',
                    'scale_verified' => false,
                ],
                'color_regions' => [
                    ['key' => 'upper', 'label' => 'Boot Upper Leather / Shell', 'role' => 'primary', 'slot' => 0],
                    ['key' => 'sole_toecap', 'label' => 'Outsole & Protective Toe Cap', 'role' => 'contrast', 'slot' => 1],
                ],
                'zones' => [
                    'thigh_left' => self::zone('thigh_left', 'Right Boot Upper Shaft', ['front', 'back'], 212, 186, [176, 120, 72, 136], [10, 180, 10, 260], [70, 120]),
                    'thigh_right' => self::zone('thigh_right', 'Left Boot Upper Shaft', ['front', 'back'], 108, 186, [72, 120, 72, 136], [10, 180, 10, 260], [70, 120]),
                    'knee_left' => self::zone('knee_left', 'Right Boot Ankle / Heel', ['front', 'back'], 212, 268, [166, 232, 90, 76], [10, 200, 10, 160], [80, 75]),
                    'knee_right' => self::zone('knee_right', 'Left Boot Ankle / Heel', ['front', 'back'], 108, 268, [64, 232, 90, 76], [10, 200, 10, 160], [80, 75]),
                    'hem' => self::zone('hem', 'Boot Sole / Toe Tag', ['front', 'back'], 160, 304, [60, 276, 200, 44], [10, 300, 10, 100], [150, 50]),
                ],
            ],

            'gloves' => [
                'silhouette' => 'gloves',
                'label' => 'Protective Gloves / Gauntlets',
                'view_box' => ['width' => self::VIEWBOX_WIDTH, 'height' => self::VIEWBOX_HEIGHT],
                'garment_bounds' => ['x' => 74, 'y' => 88, 'width' => 172, 'height' => 206],
                'scale' => [
                    'units_per_mm' => 0.35,
                    'reference_width_mm' => 260,
                    'reference_height_mm' => 320,
                    'scale_mode' => 'proportional_template',
                    'scale_verified' => false,
                ],
                'color_regions' => [
                    ['key' => 'palm_back', 'label' => 'Glove Body & Fingers', 'role' => 'primary', 'slot' => 0],
                    ['key' => 'cuff_gauntlet', 'label' => 'Wrist Cuff / Gauntlet', 'role' => 'contrast', 'slot' => 1],
                ],
                'zones' => [
                    'sleeve_left' => self::zone('sleeve_left', 'Right Glove Back / Cuff', ['front', 'back'], 212, 220, [174, 150, 72, 144], [10, 160, 10, 240], [65, 110]),
                    'sleeve_right' => self::zone('sleeve_right', 'Left Glove Back / Cuff', ['front', 'back'], 108, 220, [74, 150, 72, 144], [10, 160, 10, 240], [65, 110]),
                    'pocket_left' => self::zone('pocket_left', 'Right Wrist Cuff', ['front', 'back'], 212, 268, [180, 240, 64, 54], [10, 150, 10, 120], [60, 55]),
                    'pocket_right' => self::zone('pocket_right', 'Left Wrist Cuff', ['front', 'back'], 108, 268, [76, 240, 64, 54], [10, 150, 10, 120], [60, 55]),
                ],
            ],

            'helmet' => [
                'silhouette' => 'helmet',
                'label' => 'Safety Helmet / Headwear',
                'view_box' => ['width' => self::VIEWBOX_WIDTH, 'height' => self::VIEWBOX_HEIGHT],
                'garment_bounds' => ['x' => 56, 'y' => 130, 'width' => 208, 'height' => 108],
                'scale' => [
                    'units_per_mm' => 0.35,
                    'reference_width_mm' => 280,
                    'reference_height_mm' => 220,
                    'scale_mode' => 'proportional_template',
                    'scale_verified' => false,
                ],
                'color_regions' => [
                    ['key' => 'shell', 'label' => 'Helmet Dome Shell', 'role' => 'contrast', 'slot' => 1],
                    ['key' => 'brim_ridge', 'label' => 'Peak Brim & Center Crest', 'role' => 'primary', 'slot' => 0],
                ],
                'zones' => [
                    'chest_center' => self::zone('chest_center', 'Front Forehead Badge', ['front'], 160, 188, [104, 142, 112, 82], [10, 220, 10, 160], [90, 75]),
                    'collar' => self::zone('collar', 'Front Brim / Crest', ['front', 'back'], 160, 210, [86, 160, 148, 76], [10, 260, 10, 150], [110, 70]),
                    'shoulder_left' => self::zone('shoulder_left', 'Left Side Shell', ['front', 'back'], 206, 194, [166, 150, 78, 74], [10, 180, 10, 140], [70, 65]),
                    'shoulder_right' => self::zone('shoulder_right', 'Right Side Shell', ['front', 'back'], 114, 194, [76, 150, 78, 74], [10, 180, 10, 140], [70, 65]),
                    'back_upper' => self::zone('back_upper', 'Rear Nape Shell', ['back'], 160, 194, [100, 146, 120, 82], [10, 220, 10, 160], [95, 75]),
                ],
            ],

            'glasses' => [
                'silhouette' => 'glasses',
                'label' => 'Eye, Face, Hearing & Respiratory PPE',
                'view_box' => ['width' => self::VIEWBOX_WIDTH, 'height' => self::VIEWBOX_HEIGHT],
                'garment_bounds' => ['x' => 58, 'y' => 156, 'width' => 204, 'height' => 56],
                'scale' => [
                    'units_per_mm' => 0.40,
                    'reference_width_mm' => 200,
                    'reference_height_mm' => 120,
                    'scale_mode' => 'proportional_template',
                    'scale_verified' => false,
                ],
                'color_regions' => [
                    ['key' => 'frame_lens', 'label' => 'Lens & Frame Assembly', 'role' => 'primary', 'slot' => 0],
                    ['key' => 'bridge_strap', 'label' => 'Nose Bridge & Temple Accent', 'role' => 'contrast', 'slot' => 1],
                ],
                'zones' => [
                    'chest_center' => self::zone('chest_center', 'Center Bridge / Frame Marking', ['front', 'back'], 160, 182, [80, 154, 160, 58], [10, 220, 8, 110], [110, 55]),
                    'shoulder_left' => self::zone('shoulder_left', 'Left Lens / Temple Arm', ['front', 'back'], 216, 184, [170, 156, 92, 56], [10, 160, 8, 100], [75, 50]),
                    'shoulder_right' => self::zone('shoulder_right', 'Right Lens / Temple Arm', ['front', 'back'], 104, 184, [58, 156, 92, 56], [10, 160, 8, 100], [75, 50]),
                ],
            ],

            'harness' => [
                'silhouette' => 'harness',
                'label' => 'Fall Arrest Harness / Belts',
                'view_box' => ['width' => self::VIEWBOX_WIDTH, 'height' => self::VIEWBOX_HEIGHT],
                'garment_bounds' => ['x' => 108, 'y' => 64, 'width' => 104, 'height' => 240],
                'scale' => [
                    'units_per_mm' => 0.26,
                    'reference_width_mm' => 500,
                    'reference_height_mm' => 900,
                    'scale_mode' => 'proportional_template',
                    'scale_verified' => false,
                ],
                'color_regions' => [
                    ['key' => 'webbing', 'label' => 'Primary Load Webbing Straps', 'role' => 'contrast', 'slot' => 1],
                    ['key' => 'sub_pelvic_belt', 'label' => 'Work Positioning Waist Belt', 'role' => 'primary', 'slot' => 0],
                ],
                'zones' => [
                    'chest_center' => self::zone('chest_center', 'Sternal D-Ring / Chest Strap', ['front'], 160, 150, [112, 108, 96, 76], [10, 280, 10, 180], [95, 75]),
                    'chest_left' => self::zone('chest_left', 'Left Chest Webbing', ['front'], 182, 130, [154, 86, 54, 76], [10, 160, 10, 180], [60, 75]),
                    'chest_right' => self::zone('chest_right', 'Right Chest Webbing', ['front'], 138, 130, [112, 86, 54, 76], [10, 160, 10, 180], [60, 75]),
                    'shoulder_left' => self::zone('shoulder_left', 'Left Shoulder Webbing', ['front', 'back'], 190, 92, [162, 62, 50, 62], [10, 150, 10, 160], [55, 65]),
                    'shoulder_right' => self::zone('shoulder_right', 'Right Shoulder Webbing', ['front', 'back'], 130, 92, [108, 62, 50, 62], [10, 150, 10, 160], [55, 65]),
                    'waist' => self::zone('waist', 'Waist Positioning Belt', ['front', 'back'], 160, 192, [106, 168, 108, 48], [15, 360, 10, 140], [120, 60]),
                    'thigh_left' => self::zone('thigh_left', 'Left Thigh Loop', ['front', 'back'], 188, 258, [158, 214, 54, 90], [10, 160, 10, 220], [60, 90]),
                    'thigh_right' => self::zone('thigh_right', 'Right Thigh Loop', ['front', 'back'], 132, 258, [108, 214, 54, 90], [10, 160, 10, 220], [60, 90]),
                    'back_upper' => self::zone('back_upper', 'Dorsal D-Ring / Back Plate', ['back'], 160, 140, [112, 92, 96, 86], [15, 280, 10, 200], [95, 85]),
                    'back_center' => self::zone('back_center', 'Rear Lumbar Belt', ['back'], 160, 192, [106, 162, 108, 60], [15, 360, 10, 160], [120, 70]),
                ],
            ],
        ];
    }

    /**
     * Build the Jacket or Parka template definition.
     *
     * @return array<string, mixed>
     */
    private static function jacketOrParkaTemplate(string $silhouette, string $label, bool $isParka): array
    {
        $hemY = $isParka ? 256 : 236;
        $hemBoundsY = $isParka ? 216 : 200;
        $boundsHeight = $isParka ? 248 : 206;
        $boundsY = $isParka ? 28 : 42;

        return [
            'silhouette' => $silhouette,
            'label' => $label,
            'view_box' => ['width' => self::VIEWBOX_WIDTH, 'height' => self::VIEWBOX_HEIGHT],
            'garment_bounds' => ['x' => 42, 'y' => $boundsY, 'width' => 236, 'height' => $boundsHeight],
            'scale' => [
                'units_per_mm' => 0.28,
                'reference_width_mm' => $isParka ? 580 : 560,
                'reference_height_mm' => $isParka ? 860 : 740,
                'scale_mode' => 'proportional_template',
                'scale_verified' => false,
            ],
            'color_regions' => [
                ['key' => 'body', 'label' => 'Main Body & Sleeves', 'role' => 'primary', 'slot' => 0],
                ['key' => 'yoke', 'label' => 'Upper Chest & Shoulder Yoke', 'role' => 'contrast', 'slot' => 1],
                ['key' => 'collar_hood', 'label' => $isParka ? 'Storm Hood & Chin Guard' : 'Stand Collar', 'role' => $isParka ? 'contrast' : 'primary', 'slot' => $isParka ? 1 : 0],
                ['key' => 'pockets', 'label' => 'Chest & Lower Pockets', 'role' => 'primary', 'slot' => 0],
                ['key' => 'hem_cuffs', 'label' => 'Hem & Cuff Ribbing', 'role' => 'primary', 'slot' => 0],
            ],
            'zones' => [
                'chest_left' => self::zone('chest_left', 'Chest Left (Pocket Flap)', ['front'], 190, 128, [156, 88, 66, 74], [15, 200, 10, 180], [70, 75]),
                'chest_right' => self::zone('chest_right', 'Chest Right (Name Patch)', ['front'], 130, 128, [98, 88, 66, 74], [15, 200, 10, 180], [70, 75]),
                'chest_center' => self::zone('chest_center', 'Chest Center', ['front'], 160, 140, [100, 88, 120, 100], [15, 380, 10, 260], [135, 110]),
                'pocket_left' => self::zone('pocket_left', 'Left Lower Pocket', ['front'], 190, 196, [156, 154, 66, 82], [15, 200, 10, 200], [70, 85]),
                'pocket_right' => self::zone('pocket_right', 'Right Lower Pocket', ['front'], 130, 196, [98, 154, 66, 82], [15, 200, 10, 200], [70, 85]),
                'shoulder_left' => self::zone('shoulder_left', 'Left Shoulder', ['front', 'back'], 206, 88, [168, 56, 70, 60], [15, 200, 10, 150], [70, 65]),
                'shoulder_right' => self::zone('shoulder_right', 'Right Shoulder', ['front', 'back'], 114, 88, [82, 56, 70, 60], [15, 200, 10, 150], [70, 65]),
                'collar' => self::zone('collar', $isParka ? 'Storm Hood / Collar' : 'Collar / Neck', ['front', 'back'], 160, 60, [116, $isParka ? 24 : 38, 88, $isParka ? 62 : 48], [15, 240, 10, 130], [85, 60]),
                'sleeve_left' => self::zone('sleeve_left', 'Left Sleeve', ['front', 'back'], 244, 145, [210, 82, 68, 120], [15, 190, 10, 260], [75, 115]),
                'sleeve_right' => self::zone('sleeve_right', 'Right Sleeve', ['front', 'back'], 76, 145, [42, 82, 68, 120], [15, 190, 10, 260], [75, 115]),
                'waist' => self::zone('waist', 'Waist / Mid Torso', ['front', 'back'], 160, 205, [98, 168, 124, 74], [15, 400, 10, 190], [140, 80]),
                'hem' => self::zone('hem', 'Lower Jacket Hem', ['front', 'back'], 160, $hemY, [96, $hemBoundsY, 128, 64], [15, 400, 10, 160], [140, 70]),
                'back_upper' => self::zone('back_upper', 'Upper Back (Shoulder Blade)', ['back'], 160, 116, [98, 70, 124, 92], [15, 400, 10, 240], [140, 100]),
                'back_center' => self::zone('back_center', 'Center Back', ['back'], 160, 164, [98, 114, 124, 98], [15, 400, 10, 260], [140, 110]),
                'back_lower' => self::zone('back_lower', 'Lower Back / Hem Panel', ['back'], 160, 214, [98, 168, 124, 88], [15, 400, 10, 220], [140, 95]),
            ],
        ];
    }

    /**
     * Helper to construct a zone definition.
     *
     * @param  array<int, string>  $views
     * @param  array{0: int|float, 1: int|float, 2: int|float, 3: int|float}  $safeRect  [x, y, width, height] in SVG units
     * @param  array{0: int|float, 1: int|float, 2: int|float, 3: int|float}  $mmLimits  [minW, maxW, minH, maxH]
     * @param  array{0: int|float, 1: int|float}  $maxOffsetMm  [maxOffsetX, maxOffsetY]
     * @return array<string, mixed>
     */
    private static function zone(
        string $value,
        string $title,
        array $views,
        float|int $anchorX,
        float|int $anchorY,
        array $safeRect,
        array $mmLimits,
        array $maxOffsetMm,
    ): array {
        return [
            'value' => $value,
            'title' => $title,
            'views' => $views,
            'anchor' => ['x' => (float) $anchorX, 'y' => (float) $anchorY],
            'safe_bounds' => [
                'x' => (float) $safeRect[0],
                'y' => (float) $safeRect[1],
                'width' => (float) $safeRect[2],
                'height' => (float) $safeRect[3],
            ],
            'min_width_mm' => (float) $mmLimits[0],
            'max_width_mm' => (float) $mmLimits[1],
            'min_height_mm' => (float) $mmLimits[2],
            'max_height_mm' => (float) $mmLimits[3],
            'max_offset_x_mm' => (float) $maxOffsetMm[0],
            'max_offset_y_mm' => (float) $maxOffsetMm[1],
        ];
    }

    /**
     * Resolve the template definition for a silhouette (falling back to coverall).
     *
     * @return array<string, mixed>
     */
    public static function getTemplate(?string $silhouette): array
    {
        $catalog = self::all();
        $key = $silhouette && isset($catalog[$silhouette]) ? $silhouette : WorkwearSilhouette::COVERALL->value;

        return $catalog[$key];
    }

    /**
     * Return default physical dimensions (in mm) for a placement type.
     *
     * @return array{width_mm: float, height_mm: float}
     */
    public static function defaultDimensionsForType(?string $type): array
    {
        return self::DEFAULT_TYPE_DIMENSIONS_MM[$type ?? 'logo'] ?? self::DEFAULT_TYPE_DIMENSIONS_MM['logo'];
    }

    /**
     * Compute the SVG bounding box and boundary containment for a placement on a silhouette.
     *
     * @param  array<string, mixed>  $placement
     * @return array<string, mixed>
     */
    public static function computePlacementGeometry(?string $silhouette, array $placement): array
    {
        $template = self::getTemplate($silhouette);
        $unitsPerMm = (float) $template['scale']['units_per_mm'];
        $zones = $template['zones'];

        $zoneKey = (string) ($placement['zone'] ?? 'chest_left');
        $zoneDef = $zones[$zoneKey] ?? reset($zones);

        $defaults = self::defaultDimensionsForType($placement['type'] ?? 'logo');
        $widthMm = isset($placement['width_mm']) && $placement['width_mm'] !== ''
            ? (float) $placement['width_mm']
            : $defaults['width_mm'];
        $heightMm = isset($placement['height_mm']) && $placement['height_mm'] !== ''
            ? (float) $placement['height_mm']
            : $defaults['height_mm'];
        $offsetXMm = isset($placement['offset_x_mm']) && $placement['offset_x_mm'] !== ''
            ? (float) $placement['offset_x_mm']
            : 0.0;
        $offsetYMm = isset($placement['offset_y_mm']) && $placement['offset_y_mm'] !== ''
            ? (float) $placement['offset_y_mm']
            : 0.0;

        $svgWidth = $widthMm * $unitsPerMm;
        $svgHeight = $heightMm * $unitsPerMm;
        $svgOffsetX = $offsetXMm * $unitsPerMm;
        $svgOffsetY = $offsetYMm * $unitsPerMm;

        $cx = $zoneDef['anchor']['x'] + $svgOffsetX;
        $cy = $zoneDef['anchor']['y'] + $svgOffsetY;
        $x = $cx - ($svgWidth / 2.0);
        $y = $cy - ($svgHeight / 2.0);

        $safe = $zoneDef['safe_bounds'];
        $epsilon = 0.5;

        $withinSafeX = $x >= ($safe['x'] - $epsilon)
            && ($x + $svgWidth) <= ($safe['x'] + $safe['width'] + $epsilon);
        $withinSafeY = $y >= ($safe['y'] - $epsilon)
            && ($y + $svgHeight) <= ($safe['y'] + $safe['height'] + $epsilon);

        return [
            'cx' => round($cx, 2),
            'cy' => round($cy, 2),
            'w' => round($svgWidth, 2),
            'h' => round($svgHeight, 2),
            'x' => round($x, 2),
            'y' => round($y, 2),
            'units_per_mm' => $unitsPerMm,
            'scale_verified' => (bool) $template['scale']['scale_verified'],
            'safe_bounds' => $safe,
            'within_safe_x' => $withinSafeX,
            'within_safe_y' => $withinSafeY,
            'within_safe_bounds' => $withinSafeX && $withinSafeY,
        ];
    }

    /**
     * Resolve named colour regions for a silhouette from a `colors` array.
     * Supports both legacy `[primary, contrast]` arrays and optional `'region:color'` tokens.
     *
     * @param  array<int, mixed>|null  $colors
     * @return array<string, string>
     */
    public static function resolveColorRegions(?string $silhouette, ?array $colors): array
    {
        $template = self::getTemplate($silhouette);
        $rawColors = array_values(array_filter((array) $colors, fn ($c) => is_string($c) && trim($c) !== ''));

        $positional = [];
        $explicitRegions = [];

        foreach ($rawColors as $entry) {
            if (str_contains($entry, ':')) {
                [$regionKey, $colorVal] = explode(':', $entry, 2);
                $regionKey = trim($regionKey);
                $colorVal = trim($colorVal);
                if ($regionKey !== '' && $colorVal !== '') {
                    $explicitRegions[$regionKey] = $colorVal;
                }
            } else {
                $positional[] = trim($entry);
            }
        }

        $primary = $positional[0] ?? 'navy';
        $contrast = $positional[1] ?? $primary;

        $resolved = [];
        foreach ($template['color_regions'] as $region) {
            $key = $region['key'];
            if (isset($explicitRegions[$key])) {
                $resolved[$key] = $explicitRegions[$key];
            } elseif ($region['slot'] === 0) {
                $resolved[$key] = $primary;
            } elseif ($region['slot'] === 1) {
                $resolved[$key] = $contrast;
            } else {
                $resolved[$key] = $region['default_color'] ?? 'silver';
            }
        }

        return $resolved;
    }

    /**
     * Backward-compatible normalization for an existing saved placement on a silhouette.
     * Ensures valid zone, view, default dimensions and clamped coordinates.
     *
     * @param  array<string, mixed>  $placement
     * @return array<string, mixed>
     */
    public static function normalizePlacementForTemplate(?string $silhouette, array $placement): array
    {
        $template = self::getTemplate($silhouette);
        $zones = $template['zones'];
        $unitsPerMm = (float) $template['scale']['units_per_mm'];

        $zoneKey = (string) ($placement['zone'] ?? 'chest_left');
        if (! isset($zones[$zoneKey])) {
            $zoneKey = self::remapIncompatibleZone($zoneKey, $zones, (string) ($placement['view'] ?? 'front'));
        }

        $zoneDef = $zones[$zoneKey];
        $view = (string) ($placement['view'] ?? 'front');
        if (! in_array($view, $zoneDef['views'], true)) {
            $view = $zoneDef['views'][0] ?? 'front';
        }

        $defaults = self::defaultDimensionsForType($placement['type'] ?? 'logo');
        $rawW = isset($placement['width_mm']) && $placement['width_mm'] !== null && $placement['width_mm'] !== ''
            ? (float) $placement['width_mm']
            : $defaults['width_mm'];
        $rawH = isset($placement['height_mm']) && $placement['height_mm'] !== null && $placement['height_mm'] !== ''
            ? (float) $placement['height_mm']
            : $defaults['height_mm'];

        $maxAllowedW = min($zoneDef['max_width_mm'], floor($zoneDef['safe_bounds']['width'] / $unitsPerMm));
        $maxAllowedH = min($zoneDef['max_height_mm'], floor($zoneDef['safe_bounds']['height'] / $unitsPerMm));

        $widthMm = max($zoneDef['min_width_mm'], min($maxAllowedW, $rawW));
        $heightMm = max($zoneDef['min_height_mm'], min($maxAllowedH, $rawH));

        $svgW = $widthMm * $unitsPerMm;
        $svgH = $heightMm * $unitsPerMm;
        $safe = $zoneDef['safe_bounds'];
        $anchor = $zoneDef['anchor'];

        $minCenterX = $safe['x'] + ($svgW / 2.0);
        $maxCenterX = $safe['x'] + $safe['width'] - ($svgW / 2.0);
        $minOffsetXMm = ($minCenterX - $anchor['x']) / $unitsPerMm;
        $maxOffsetXMm = ($maxCenterX - $anchor['x']) / $unitsPerMm;

        $minCenterY = $safe['y'] + ($svgH / 2.0);
        $maxCenterY = $safe['y'] + $safe['height'] - ($svgH / 2.0);
        $minOffsetYMm = ($minCenterY - $anchor['y']) / $unitsPerMm;
        $maxOffsetYMm = ($maxCenterY - $anchor['y']) / $unitsPerMm;

        $rawOx = (float) ($placement['offset_x_mm'] ?? 0.0);
        $rawOy = (float) ($placement['offset_y_mm'] ?? 0.0);

        $clampedOx = max(-$zoneDef['max_offset_x_mm'], min($zoneDef['max_offset_x_mm'], $rawOx));
        $clampedOx = max($minOffsetXMm, min($maxOffsetXMm, $clampedOx));

        $clampedOy = max(-$zoneDef['max_offset_y_mm'], min($zoneDef['max_offset_y_mm'], $rawOy));
        $clampedOy = max($minOffsetYMm, min($maxOffsetYMm, $clampedOy));

        return array_merge($placement, [
            'view' => $view,
            'zone' => $zoneKey,
            'width_mm' => round($widthMm, 2),
            'height_mm' => round($heightMm, 2),
            'offset_x_mm' => round($clampedOx, 2),
            'offset_y_mm' => round($clampedOy, 2),
        ]);
    }

    /**
     * Remap a zone from another silhouette to the closest valid zone on the target template.
     *
     * @param  array<string, array<string, mixed>>  $zones
     */
    private static function remapIncompatibleZone(string $zoneKey, array $zones, string $preferredView): string
    {
        $fallbacks = [
            'chest_left' => ['pocket_left', 'thigh_left', 'chest_center', 'waist'],
            'chest_right' => ['pocket_right', 'thigh_right', 'chest_center', 'waist'],
            'chest_center' => ['waist', 'chest_left', 'pocket_left'],
            'sleeve_left' => ['shoulder_left', 'chest_left', 'pocket_left', 'thigh_left'],
            'sleeve_right' => ['shoulder_right', 'chest_right', 'pocket_right', 'thigh_right'],
            'collar' => ['shoulder_left', 'chest_center', 'waist'],
            'thigh_left' => ['pocket_left', 'chest_left', 'waist', 'hem'],
            'thigh_right' => ['pocket_right', 'chest_right', 'waist', 'hem'],
            'knee_left' => ['thigh_left', 'hem', 'pocket_left', 'waist'],
            'knee_right' => ['thigh_right', 'hem', 'pocket_right', 'waist'],
            'back_upper' => ['back_center', 'back_lower', 'waist'],
            'back_center' => ['back_upper', 'back_lower', 'waist'],
            'back_lower' => ['back_center', 'back_upper', 'waist', 'hem'],
        ];

        foreach ($fallbacks[$zoneKey] ?? [] as $candidate) {
            if (isset($zones[$candidate])) {
                return $candidate;
            }
        }

        foreach ($zones as $key => $def) {
            if (in_array($preferredView, $def['views'], true)) {
                return $key;
            }
        }

        return (string) array_key_first($zones);
    }
}
