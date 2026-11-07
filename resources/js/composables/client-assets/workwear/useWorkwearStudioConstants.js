/**
 * Shared constants, taxonomy catalog (from workwear-ppe-catalog.md), and helpers for the
 * Workwear Model Studio, 2D Garment Illustration, Technical Construction & Care Profile,
 * EN/ISO Standards, Certifications, and Customization Placements.
 */
/* eslint-disable camelcase */
import { localizedValue } from '@/composables/useLocaleField'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

export const WORKWEAR_SILHOUETTES = [
  { value: 'coverall', title: 'Coverall / Boilersuit', titleFr: 'Combinaison de travail', icon: 'tabler-stretching' },
  { value: 'overalls', title: 'Bib & Brace Overalls', titleFr: 'Salopette / Cotte à bretelles', icon: 'tabler-hanger' },
  { value: 'jacket', title: 'Work Jacket / Blouson', titleFr: 'Veste de travail / Blouson', icon: 'tabler-shirt' },
  { value: 'parka', title: 'Weatherproof Parka / Coat', titleFr: 'Parka / Manteau imperméable', icon: 'tabler-snowflake' },
  { value: 'vest', title: 'Sleeveless Vest / Gilet', titleFr: 'Gilet sans manches', icon: 'tabler-shield-checkered' },
  { value: 'polo', title: 'Polo Shirt', titleFr: 'Polo de travail', icon: 'tabler-shirt-sport' },
  { value: 'tshirt', title: 'Work T-Shirt (Crew-Neck)', titleFr: 'T-shirt de travail', icon: 'tabler-shirt' },
  { value: 'shirt', title: 'Button-Down Work Shirt', titleFr: 'Chemise / Surchemise de travail', icon: 'tabler-shirt-filled' },
  { value: 'sweatshirt', title: 'Sweatshirt / Hoodie / Fleece', titleFr: 'Sweat-shirt / Polaire', icon: 'tabler-jacket' },
  { value: 'lab_coat', title: 'Lab Coat / Hygiene Smock', titleFr: 'Blouse / Tunique médicale', icon: 'tabler-medical-cross' },
  { value: 'trousers', title: 'Work / Cargo Trousers', titleFr: 'Pantalon de travail', icon: 'tabler-hanger-2' },
  { value: 'shorts', title: 'Work Shorts / Bermuda', titleFr: 'Bermuda / Short de travail', icon: 'tabler-hanger-2' },
  { value: 'apron', title: 'Apron / Tabard', titleFr: 'Tablier / Chasuble', icon: 'tabler-user-shield' },
  { value: 'boots', title: 'Safety Boots / Footwear', titleFr: 'Chaussures & bottes de sécurité', icon: 'tabler-shoe' },
  { value: 'gloves', title: 'Protective Gloves / Gauntlets', titleFr: 'Gants de protection', icon: 'tabler-hand-stop' },
  { value: 'helmet', title: 'Safety Helmet / Headwear', titleFr: 'Casque & casquette de sécurité', icon: 'tabler-helmet' },
  { value: 'glasses', title: 'Eye, Face, Hearing & Respiratory PPE', titleFr: 'Protection oculaire, auditive & respiratoire', icon: 'tabler-eyeglass-2' },
  { value: 'harness', title: 'Fall Arrest Harness & Belt', titleFr: 'Harnais antichute & ceinture', icon: 'tabler-parachute' },
]

/**
 * Organized, workwear-focused Silhouette Classification Catalog.
 * Keeps categories and subcategories crisp, practical, and easy to browse.
 */
export const WORKWEAR_SILHOUETTE_CATALOG = [
  {
    value: 'clothing',
    title: 'Workwear Clothing',
    titleFr: 'Vêtements de travail',
    icon: 'tabler-shirt',
    defaultRenderer: 'jacket',
    subcategories: [
      {
        value: 'one_piece',
        title: 'Coveralls & Overalls',
        titleFr: 'Combinaisons & Salopettes',
        defaultRenderer: 'coverall',
        items: [
          { value: 'single_zip_coverall', title: 'Standard Single-Zip Coverall / Boiler Suit', titleFr: 'Combinaison de travail simple zip', renderer: 'coverall' },
          { value: 'double_zip_coverall', title: 'Double-Zip Coverall (Neck-to-Ankle Dual Zip)', titleFr: 'Combinaison double zip', renderer: 'coverall' },
          { value: 'insulated_winter_coverall', title: 'Insulated / Winter Coverall', titleFr: 'Combinaison thermique / grand froid', renderer: 'coverall' },
          { value: 'short_sleeve_summer_coverall', title: 'Short-Sleeve / Summer Coverall', titleFr: 'Combinaison manches courtes', renderer: 'coverall' },
          { value: 'bib_brace_overalls', title: 'Bib & Brace Overalls / Dungarees', titleFr: 'Salopette / Cotte à bretelles', renderer: 'overalls' },
        ],
      },
      {
        value: 'jackets_vests',
        title: 'Jackets, Parkas & Vests',
        titleFr: 'Vestes, Parkas & Gilets',
        defaultRenderer: 'jacket',
        items: [
          { value: 'standard_work_jacket', title: 'Standard Work Jacket / Blouson', titleFr: 'Veste de travail / Blouson', renderer: 'jacket' },
          { value: 'softshell_jacket', title: 'Softshell Jacket (3-Layer Windproof)', titleFr: 'Veste softshell', renderer: 'jacket' },
          { value: 'bomber_pilot_jacket', title: 'Bomber / Pilot Jacket', titleFr: 'Blouson pilote / Bomber', renderer: 'jacket' },
          { value: 'parka_3in1_4in1', title: 'Parka (Insulated / 3-in-1 / 4-in-1)', titleFr: 'Parka de travail', renderer: 'parka' },
          { value: 'hardshell_rain_jacket', title: 'Waterproof Rain Jacket / Hardshell', titleFr: 'Veste de pluie imperméable', renderer: 'parka' },
          { value: 'trench_rain_coat', title: 'Long Rain Coat / Trench Coat', titleFr: 'Manteau de pluie long / Ciré', renderer: 'parka' },
          { value: 'puffer_insulated_jacket', title: 'Insulated Down / Puffer Jacket', titleFr: 'Doudoune de travail', renderer: 'parka' },
          { value: 'multipocket_reporter_vest', title: 'Multi-Pocket Work Vest / Hi-Vis Gilet', titleFr: 'Gilet multipoches / Gilet HV', renderer: 'vest' },
        ],
      },
      {
        value: 'tshirts_polos',
        title: 'T-Shirts & Polo Shirts',
        titleFr: 'T-Shirts & Polos',
        defaultRenderer: 'polo',
        items: [
          { value: 'tshirt_short_crew', title: 'Short-Sleeve Work T-Shirt', titleFr: 'T-shirt manches courtes', renderer: 'tshirt' },
          { value: 'tshirt_long_sleeve', title: 'Long-Sleeve Work T-Shirt', titleFr: 'T-shirt manches longues', renderer: 'tshirt' },
          { value: 'polo_short_pique', title: 'Short-Sleeve Piqué Polo Shirt', titleFr: 'Polo piqué manches courtes', renderer: 'polo' },
          { value: 'polo_long_sleeve', title: 'Long-Sleeve Polo Shirt', titleFr: 'Polo manches longues', renderer: 'polo' },
        ],
      },
      {
        value: 'shirts_tunics',
        title: 'Work Shirts & Tunics',
        titleFr: 'Chemises & Tuniques',
        defaultRenderer: 'shirt',
        items: [
          { value: 'work_shirt_short', title: 'Short-Sleeve Work Shirt', titleFr: 'Chemise de travail manches courtes', renderer: 'shirt' },
          { value: 'work_shirt_long', title: 'Long-Sleeve Work Shirt', titleFr: 'Chemise de travail manches longues', renderer: 'shirt' },
          { value: 'flannel_overshirt', title: 'Flannel / Heavy Cotton Overshirt', titleFr: 'Surchemise de travail', renderer: 'shirt' },
          { value: 'chef_tunic', title: 'Stand-Collar / Mandarin-Collar Tunic', titleFr: 'Veste de cuisine / Tunique médicale', renderer: 'lab_coat' },
        ],
      },
      {
        value: 'sweatshirts_fleeces',
        title: 'Sweatshirts, Hoodies & Fleeces',
        titleFr: 'Sweats, Capuches & Polaires',
        defaultRenderer: 'sweatshirt',
        items: [
          { value: 'crewneck_sweatshirt', title: 'Crew-Neck Work Sweatshirt', titleFr: 'Sweat-shirt col rond', renderer: 'sweatshirt' },
          { value: 'quarter_zip_sweatshirt', title: 'Quarter-Zip / Half-Zip Sweatshirt', titleFr: 'Sweat-shirt col camionneur zippé', renderer: 'sweatshirt' },
          { value: 'pullover_hoodie', title: 'Work Pullover Hoodie', titleFr: 'Sweat à capuche', renderer: 'sweatshirt' },
          { value: 'microfleece_jacket', title: 'Microfleece Work Jacket', titleFr: 'Veste polaire', renderer: 'sweatshirt' },
          { value: 'knitted_work_sweater', title: 'Knitted Work Sweater / Commando Pullover', titleFr: 'Pull de travail / Pull commando', renderer: 'sweatshirt' },
        ],
      },
      {
        value: 'bottoms',
        title: 'Trousers & Work Shorts',
        titleFr: 'Pantalons & Bermudas',
        defaultRenderer: 'trousers',
        items: [
          { value: 'standard_work_trousers', title: 'Standard Work Trousers', titleFr: 'Pantalon de travail classique', renderer: 'trousers' },
          { value: 'cargo_multipocket_trousers', title: 'Cargo / Multi-Pocket Work Trousers', titleFr: 'Pantalon multipoches / Cargo', renderer: 'trousers' },
          { value: 'holster_pocket_trousers', title: 'Holster-Pocket Craftsman Trousers', titleFr: 'Pantalon à poches flottantes', renderer: 'trousers' },
          { value: 'elasticated_kitchen_medical_trousers', title: 'Elasticated Waist Kitchen / Medical Trousers', titleFr: 'Pantalon médical / de cuisine', renderer: 'trousers' },
          { value: 'work_cargo_shorts', title: 'Work Cargo Shorts / Bermuda', titleFr: 'Bermuda de travail multipoches', renderer: 'shorts' },
        ],
      },
      {
        value: 'protective_garments',
        title: 'Lab Coats, Smocks & Aprons',
        titleFr: 'Blouses & Tabliers',
        defaultRenderer: 'lab_coat',
        items: [
          { value: 'lab_coat_warehouse_smock', title: 'Lab Coat / Warehouse Smock', titleFr: 'Blouse de travail / Blouse blanche', renderer: 'lab_coat' },
          { value: 'isolation_surgical_gown', title: 'Isolation / Hygiene Gown', titleFr: 'Casaque / Blouse d’isolement', renderer: 'lab_coat' },
          { value: 'butcher_waterproof_apron', title: 'Industrial / Waterproof Apron', titleFr: 'Tablier industriel / imperméable', renderer: 'apron' },
        ],
      },
    ],
  },
  {
    value: 'footwear',
    title: 'Safety Footwear',
    titleFr: 'Chaussures & bottes de sécurité',
    icon: 'tabler-shoe',
    defaultRenderer: 'boots',
    subcategories: [
      {
        value: 'safety_shoes',
        title: 'Safety Shoes & Boots (S1–S3)',
        titleFr: 'Chaussures de sécurité (S1–S3)',
        defaultRenderer: 'boots',
        items: [
          { value: 'low_cut_safety_shoe', title: 'Low-Cut Safety Shoe (S1 / S1P / S2 / S3)', titleFr: 'Chaussure de sécurité basse', renderer: 'boots' },
          { value: 'ankle_safety_boot', title: 'Mid-Cut / Ankle Safety Boot (S3)', titleFr: 'Chaussure de sécurité montante / Brodequin', renderer: 'boots' },
          { value: 'high_leg_combat_boot', title: 'High-Leg Combat / Ranger Safety Boot', titleFr: 'Botte d’intervention / Rangers de sécurité', renderer: 'boots' },
          { value: 'rigger_pullon_boot', title: 'Rigger Boot (Pull-On Fur-Lined / Unlined)', titleFr: 'Botte fourrée / Botte de chantier', renderer: 'boots' },
          { value: 'safety_trainer_sneaker', title: 'Safety Trainer / Sport Sneaker', titleFr: 'Basket de sécurité', renderer: 'boots' },
          { value: 'esd_cleanroom_shoe', title: 'ESD / Cleanroom Safety Shoe', titleFr: 'Chaussure de sécurité ESD / Salle blanche', renderer: 'boots' },
          { value: 'executive_dress_safety_shoe', title: 'Executive / Dress Safety Shoe', titleFr: 'Chaussure de sécurité de ville', renderer: 'boots' },
        ],
      },
      {
        value: 'wellington_gumboots',
        title: 'Wellington / Gumboots (S4 / S5)',
        titleFr: 'Bottes étanches (S4 / S5)',
        defaultRenderer: 'boots',
        items: [
          { value: 'pvc_nitrile_safety_wellington', title: 'PVC / Polyurethane Safety Wellington (S4 / S5)', titleFr: 'Botte de sécurité étanche S4/S5', renderer: 'boots' },
          { value: 'food_white_wellington', title: 'Food-Industry White Hygiene Wellington (S4)', titleFr: 'Botte blanche agroalimentaire', renderer: 'boots' },
          { value: 'dielectric_insulating_boot', title: 'Dielectric / Electrically Insulating Boot', titleFr: 'Botte isolante électricien', renderer: 'boots' },
        ],
      },
    ],
  },
  {
    value: 'hand_protection',
    title: 'Hand Protection',
    titleFr: 'Protection des mains',
    icon: 'tabler-hand-stop',
    defaultRenderer: 'gloves',
    subcategories: [
      {
        value: 'gloves',
        title: 'Safety & Technical Gloves',
        titleFr: 'Gants de sécurité',
        defaultRenderer: 'gloves',
        items: [
          { value: 'pu_nitrile_handling_glove', title: 'Precision Handling Glove (PU / Nitrile Dipped)', titleFr: 'Gant de manutention fine enduit PU/Nitrile', renderer: 'gloves' },
          { value: 'cut_resistant_glove', title: 'Cut-Resistant Glove (ISO Cut Level A to F)', titleFr: 'Gant anti-coupure', renderer: 'gloves' },
          { value: 'chemical_gauntlet_glove', title: 'Chemical-Resistant Gauntlet (Nitrile / Butyl / Neoprene)', titleFr: 'Gant de protection chimique manchette longue', renderer: 'gloves' },
          { value: 'welding_gauntlet_glove', title: 'Welding Gauntlet (EN 12477 Type A / Type B)', titleFr: 'Gant de soudeur', renderer: 'gloves' },
          { value: 'cryogenic_cold_glove', title: 'Cold-Storage / Cryogenic Glove (EN 511)', titleFr: 'Gant grand froid / cryogénique', renderer: 'gloves' },
          { value: 'dielectric_insulating_glove', title: 'Electrically Insulating Latex Glove (Class 00 to 4)', titleFr: 'Gant isolant électricien', renderer: 'gloves' },
          { value: 'disposable_nitrile_glove', title: 'Disposable Nitrile / Latex Examination Glove', titleFr: 'Gant jetable nitrile / latex', renderer: 'gloves' },
        ],
      },
    ],
  },
  {
    value: 'head_protection',
    title: 'Head, Eye & Fall PPE',
    titleFr: 'Tête, Yeux & Antichute',
    icon: 'tabler-helmet',
    defaultRenderer: 'helmet',
    subcategories: [
      {
        value: 'head_protection',
        title: 'Hard Hats & Headwear',
        titleFr: 'Casques & casquettes de sécurité',
        defaultRenderer: 'helmet',
        items: [
          { value: 'industrial_safety_helmet', title: 'Industrial Safety Helmet / Hard Hat (EN 397)', titleFr: 'Casque de chantier', renderer: 'helmet' },
          { value: 'bump_cap', title: 'Bump Cap / Baseball Safety Cap (EN 812)', titleFr: 'Casquette anti-heurts', renderer: 'helmet' },
        ],
      },
      {
        value: 'eye_face_fall',
        title: 'Eye, Respiratory & Fall Arrest PPE',
        titleFr: 'Lunettes, Masques & Harnais',
        defaultRenderer: 'glasses',
        items: [
          { value: 'safety_glasses_goggles', title: 'Safety Spectacles / Goggles (EN 166)', titleFr: 'Lunettes de sécurité', renderer: 'glasses' },
          { value: 'fall_arrest_harness', title: 'Full-Body Fall Arrest Harness (EN 361)', titleFr: 'Harnais antichute complet', renderer: 'harness' },
        ],
      },
    ],
  },
]

const LEGACY_SILHOUETTE_ITEM_ALIASES = {
  hivis_trousers_c1: { item: 'standard_work_trousers', standards: ['EN ISO 20471 Class 1'] },
  hivis_harness_vest: { item: 'multipocket_reporter_vest', standards: ['EN ISO 20471 Class 1'] },
  hivis_safety_vest_c2: { item: 'multipocket_reporter_vest', standards: ['EN ISO 20471 Class 2'] },
  hivis_executive_vest_c2: { item: 'multipocket_reporter_vest', standards: ['EN ISO 20471 Class 2'] },
  hivis_tshirt_polo_c2: { item: 'polo_short_pique', standards: ['EN ISO 20471 Class 2'] },
  hivis_bib_overalls_c2: { item: 'bib_brace_overalls', standards: ['EN ISO 20471 Class 2'] },
  hivis_trousers_c2: { item: 'standard_work_trousers', standards: ['EN ISO 20471 Class 2'] },
  hivis_long_sleeve_polo_c3: { item: 'polo_long_sleeve', standards: ['EN ISO 20471 Class 3'] },
  hivis_sweatshirt_hoodie_c3: { item: 'crewneck_sweatshirt', standards: ['EN ISO 20471 Class 3'] },
  hivis_softshell_bomber_c3: { item: 'bomber_pilot_jacket', standards: ['EN ISO 20471 Class 3'] },
  hivis_parka_4in1_c3: { item: 'parka_3in1_4in1', standards: ['EN ISO 20471 Class 3'] },
  hivis_coverall_c3: { item: 'single_zip_coverall', standards: ['EN ISO 20471 Class 3'] },
  esd_lab_coat: { item: 'lab_coat_warehouse_smock', standards: ['EN 61340-5-1'] },
  cleanroom_bunny_suit: { item: 'single_zip_coverall', standards: ['EN 61340-5-1'] },
  scrub_tunic_top: { item: 'chef_tunic' },
  scrub_trousers: { item: 'elasticated_kitchen_medical_trousers' },
  haccp_pocketless_coat: {
    item: 'lab_coat_warehouse_smock',
    certifications: ['HACCP Food Safety Compliant (IFS / BRC)'],
  },
  chef_double_breasted_jacket: { item: 'chef_tunic' },
}

const LEGACY_SILHOUETTE_CATEGORY_ALIASES = {
  upper_body: { category: 'clothing', subcategory: 'jackets_vests' },
  lower_body: { category: 'clothing', subcategory: 'bottoms' },
  full_body: { category: 'clothing', subcategory: 'one_piece' },
  high_visibility: { category: 'clothing' },
  cleanroom_medical: { category: 'clothing' },
}

function findSilhouetteItem(itemValue) {
  for (const category of WORKWEAR_SILHOUETTE_CATALOG) {
    for (const subcategory of category.subcategories) {
      const item = subcategory.items.find(candidate => candidate.value === itemValue)
      if (item) return { category, subcategory, item }
    }
  }

  return null
}

export function normalizeSilhouetteTaxonomy(model) {
  if (!model) return model

  const alias = LEGACY_SILHOUETTE_ITEM_ALIASES[model.silhouette_item]
  const itemValue = alias?.item || model.silhouette_item
  const match = findSilhouetteItem(itemValue)

  if (!match) {
    const legacyCategory = LEGACY_SILHOUETTE_CATEGORY_ALIASES[model.silhouette_category]
    if (!legacyCategory && model.silhouette_subcategory !== 'tops') return model

    const subcategory = legacyCategory?.subcategory || ({
      trousers: 'bottoms',
      shorts: 'bottoms',
      coverall: 'one_piece',
      overalls: 'one_piece',
      apron: 'protective_garments',
      lab_coat: 'protective_garments',
      polo: 'tshirts_polos',
      tshirt: 'tshirts_polos',
      shirt: 'shirts_tunics',
      sweatshirt: 'sweatshirts_fleeces',
    }[model.silhouette] || 'jackets_vests')

    return {
      ...model,
      silhouette_category: legacyCategory?.category || model.silhouette_category || 'clothing',
      silhouette_subcategory: subcategory,
    }
  }

  const standards = Array.isArray(model.standards) ? model.standards : []
  const certifications = Array.isArray(model.certifications) ? model.certifications : []

  return {
    ...model,
    silhouette_category: match.category.value,
    silhouette_subcategory: match.subcategory.value,
    silhouette_item: match.item.value,
    silhouette: alias ? match.item.renderer : (model.silhouette || match.item.renderer),
    standards: [...new Set([...standards, ...(alias?.standards || [])])],
    certifications: [...new Set([...certifications, ...(alias?.certifications || [])])],
  }
}

export const WORKWEAR_COLOR_PALETTE = [
  { value: 'navy', label: 'Deep Navy', labelFr: 'Bleu marine foncé', hex: '#1e293b' },
  { value: 'royal', label: 'Cobalt Royal', labelFr: 'Bleu roi / Cobalt', hex: '#1d4ed8' },
  { value: 'charcoal', label: 'Anthracite Grey', labelFr: 'Gris anthracite', hex: '#374151' },
  { value: 'black', label: 'Carbon Black', labelFr: 'Noir carbone', hex: '#111827' },
  { value: 'hi-vis-yellow', label: 'Hi-Vis Yellow / Fluorescent', labelFr: 'Jaune fluo haute visibilité', hex: '#eab308' },
  { value: 'hi-vis-orange', label: 'Hi-Vis Orange / Railway', labelFr: 'Orange fluo ferroviaire', hex: '#ea580c' },
  { value: 'hi-vis-red', label: 'Hi-Vis Red', labelFr: 'Rouge fluo haute visibilité', hex: '#ef4444' },
  { value: 'red', label: 'Safety Red', labelFr: 'Rouge sécurité', hex: '#dc2626' },
  { value: 'forest', label: 'Forest Green', labelFr: 'Vert forêt', hex: '#15803d' },
  { value: 'khaki', label: 'Sand / Khaki / Coyote', labelFr: 'Sable / Kaki / Coyote', hex: '#a16207' },
  { value: 'sky', label: 'Medical / Cleanroom Blue', labelFr: 'Bleu médical / Salle blanche', hex: '#0284c7' },
  { value: 'white', label: 'Cleanroom / HACCP White', labelFr: 'Blanc agroalimentaire / HACCP', hex: '#f1f5f9' },
]

/**
 * Helper to extract Colors and Sizes from a linked Catalog Item's options.
 */
export function extractCatalogColorsAndSizes(catalogItem, locale = 'en') {
  const result = {
    colors: [],
    sizes: [],
    otherOptions: [],
  }
  if (!catalogItem || !Array.isArray(catalogItem.options)) return result

  for (const opt of catalogItem.options) {
    const rawName = (localizedValue(opt.name, locale) || opt.code || '').toLowerCase()
    const values = (opt.values || [])
      .map(v => localizedValue(v.value, locale) || v.value || '')
      .map(s => String(s).trim())
      .filter(Boolean)

    if (!values.length) continue

    const isColor = rawName.includes('color')
      || rawName.includes('colour')
      || rawName.includes('couleur')
      || rawName.includes('teinte')
      || rawName.includes('لون')

    const isSize = rawName.includes('size')
      || rawName.includes('taille')
      || rawName.includes('pointure')
      || rawName.includes('dimension')
      || rawName.includes('مقاس')

    if (isColor) {
      const normalizedColors = values.map(v => {
        const lower = v.toLowerCase()
        const match = WORKWEAR_COLOR_PALETTE.find(
          c => c.value === lower
            || c.label.toLowerCase() === lower
            || (c.labelFr && c.labelFr.toLowerCase() === lower)
            || lower.includes(c.value.replace('hi-vis-', '')),
        )

        return match ? match.value : v
      })

      result.colors.push(...normalizedColors)
    } else if (isSize) {
      result.sizes.push(...values)
    } else {
      result.otherOptions.push({
        id: opt.id,
        name: localizedValue(opt.name, locale) || opt.name,
        values,
      })
    }
  }

  result.colors = [...new Set(result.colors)]
  result.sizes = [...new Set(result.sizes)]

  return result
}

export const WORKWEAR_PLACEMENT_TYPES = [
  { value: 'logo', title: 'Logo / Artwork', icon: 'tabler-photo-hexagon', color: '#2563eb', defaultWidth: 90, defaultHeight: 45, defaultMethod: 'Direct Embroidery' },
  { value: 'text', title: 'Custom Text', icon: 'tabler-typography', color: '#0d9488', defaultWidth: 110, defaultHeight: 28, defaultMethod: 'High-Density Screen Print' },
  { value: 'fluo', title: 'Fluo / Hi-Vis Panel', icon: 'tabler-sun-high', color: '#eab308', defaultWidth: 160, defaultHeight: 55, defaultMethod: 'Fluorescent Contrast Yoke' },
  { value: 'reflective_band', title: 'Reflective Band', icon: 'tabler-ruler-2', color: '#ca8a04', defaultWidth: 180, defaultHeight: 50, defaultMethod: '3M Scotchlite Heat Seal' },
  { value: 'flag', title: 'National / Safety Flag', icon: 'tabler-flag-3', color: '#dc2626', defaultWidth: 65, defaultHeight: 42, defaultMethod: 'Woven Patch Stitch' },
  { value: 'badge', title: 'Name Badge / Velcro Patch', icon: 'tabler-id-badge-2', color: '#16a34a', defaultWidth: 85, defaultHeight: 25, defaultMethod: 'Velcro Hook & Loop' },
  { value: 'pocket', title: 'Pocket / Utility Mod', icon: 'tabler-box-padding', color: '#7c3aed', defaultWidth: 70, defaultHeight: 80, defaultMethod: 'Reinforced Bar-Tack' },
  { value: 'other', title: 'Other Element', icon: 'tabler-adjustments', color: '#64748b', defaultWidth: 60, defaultHeight: 40, defaultMethod: 'Heat Transfer Vinyl' },
]

export const WORKWEAR_APPLICATION_METHODS = [
  'Direct Embroidery',
  'High-Density Screen Print',
  'Heat Transfer Vinyl',
  '3M Scotchlite Heat Seal',
  'Segmented Reflective Transfer',
  'Flame-Retardant Embroidery (Nomex Thread)',
  'Fluorescent Contrast Yoke',
  'Velcro Hook & Loop',
  'Woven Patch Stitch',
  'Sublimation Print',
  'Reinforced Bar-Tack',
]

export const WORKWEAR_STANDARD_SIZES = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL']

/**
 * European & International PPE Standards (from Section 15 of workwear-ppe-catalog.md)
 */
export const WORKWEAR_STANDARDS_CATALOG = [
  { code: 'EN ISO 13688', title: 'General Requirements for Protective Clothing', category: 'General' },
  { code: 'EN ISO 20471', title: 'High-Visibility Warning Clothing (Class 1 / 2 / 3)', category: 'Hi-Vis' },
  { code: 'EN 343', title: 'Protection Against Rain & Foul Weather (Class 1–4)', category: 'Weather' },
  { code: 'EN 342', title: 'Protection Against Cold Environments (< -5°C)', category: 'Weather' },
  { code: 'EN 14058', title: 'Protection Against Cool Environments (≥ -5°C)', category: 'Weather' },
  { code: 'EN ISO 11612', title: 'Protection Against Heat and Flame (A1/A2, B, C, D, E, F)', category: 'FR & Thermal' },
  { code: 'EN ISO 11611', title: 'Protective Clothing for Welding & Allied Processes (Class 1 / 2)', category: 'FR & Thermal' },
  { code: 'EN 1149-5', title: 'Electrostatic Dissipative Protective Clothing (ATEX)', category: 'ATEX & Electrical' },
  { code: 'IEC 61482-2', title: 'Protective Clothing Against Thermal Hazards of Electric Arc', category: 'ATEX & Electrical' },
  { code: 'EN 13034', title: 'Liquid Chemical Splash Protection (Type 6 / Type PB [6])', category: 'Chemical' },
  { code: 'EN ISO 13982-1', title: 'Solid Airborne Particulate Protection (Type 5)', category: 'Chemical' },
  { code: 'EN 14605', title: 'Liquid-Tight (Type 3) & Spray-Tight (Type 4) Chemical Suits', category: 'Chemical' },
  { code: 'EN 1073-2', title: 'Non-Ventilated Protection Against Radioactive Particulate Contamination', category: 'Chemical' },
  { code: 'EN 14126', title: 'Protection Against Infective / Biological Agents', category: 'Hygiene' },
  { code: 'EN 61340-5-1', title: 'ESD Protection of Electronic Devices from Electrostatic Phenomena', category: 'Cleanroom' },
  { code: 'EN 14404', title: 'Knee Protectors for Work in the Kneeling Position', category: 'Ergonomics' },
  { code: 'EN 381-5 / EN ISO 11393', title: 'Protective Clothing for Users of Hand-Held Chainsaws', category: 'Mechanical' },
  { code: 'EN ISO 20345', title: 'Safety Footwear (200 J Toe Cap — SB, S1, S1P, S2, S3, S4, S5)', category: 'Footwear' },
  { code: 'EN ISO 20347', title: 'Occupational Footwear (Without Toe Cap — OB, O1, O2, O4)', category: 'Footwear' },
  { code: 'EN 388', title: 'Protective Gloves Against Mechanical Risks', category: 'Hand PPE' },
  { code: 'EN ISO 374', title: 'Protective Gloves Against Dangerous Chemicals & Micro-Organisms', category: 'Hand PPE' },
  { code: 'EN 407', title: 'Protective Gloves Against Thermal Risks (Heat and/or Fire)', category: 'Hand PPE' },
  { code: 'EN 397', title: 'Industrial Safety Helmets', category: 'Head & Eye' },
  { code: 'EN 166 / EN ISO 16321', title: 'Personal Eye & Face Protection', category: 'Head & Eye' },
  { code: 'EN 352', title: 'Hearing Protectors (Earmuffs & Earplugs)', category: 'Head & Eye' },
  { code: 'EN 149', title: 'Filtering Half Masks to Protect Against Particles (FFP1/FFP2/FFP3)', category: 'Respiratory' },
  { code: 'EN 361', title: 'Personal Fall Protection Equipment — Full Body Harnesses', category: 'Fall Arrest' },
]

export const WORKWEAR_CERTIFICATIONS_PRESETS = [
  'CE Category I (Minimal Risk)',
  'CE Category II (Intermediate Risk — Notified Body Type-Examined)',
  'CE Category III (Mortal / Irreversible Risk — Module C2/D Audited)',
  'ISO 15797 Industrial Laundering Certified (75°C + Tunnel Dry)',
  'OEKO-TEX® Standard 100 Certified',
  'ATEX Zone 1 / Zone 2 Compliant (Metal-Free)',
  'HACCP Food Safety Compliant (IFS / BRC)',
  'REACH Compliant',
  'Global Recycled Standard (GRS)',
]

/**
 * Quick-answer presets for Technical Construction & Care Profile
 */
export const WORKWEAR_TECHNICAL_CONSTRUCTION_PRESETS = {
  fastening: {
    label: 'Closures & Fastening',
    title: 'Closures & Fastening',
    icon: 'tabler-zip',
    placeholder: 'e.g. Concealed YKK 2-way brass zipper with Velcro storm flap, metal-free dielectric snaps...',
    quickAnswers: [
      '2-Way Concealed YKK Zip + Storm Flap',
      'Metal-Free Non-Sparking Dielectric Closures (ATEX)',
      'Heavy-Duty Brass Zipper + Press-Stud Placket',
      'Quick-Release Panic Snaps (Foundry / Chemical)',
      'Concealed Scratch-Free Press Studs (Automotive)',
      'Hook-and-Loop (Velcro) Front Closure',
    ],
  },
  storage: {
    label: 'Pocket Configuration & Storage',
    title: 'Pocket Configuration & Storage',
    icon: 'tabler-box-padding',
    placeholder: 'e.g. 2 chest pockets with flaps, ruler pocket, thigh cargo pocket, internal phone pocket...',
    quickAnswers: [
      '2 Flapped Chest Pockets + Pen Slot + ID Loop',
      'Detachable Cordura® Holster Pockets',
      'Bellowed Thigh Cargo Pocket + Ruler & Hammer Loop',
      'Top-Loading / Bottom-Loading Knee Pad Pockets (EN 14404)',
      'Internal Tablet / Phone Pocket + Radio Loop',
      'HACCP Pocketless Exterior (Internal Pocket Only)',
    ],
  },
  reinforcements: {
    label: 'Reinforcements & Stress Zones',
    title: 'Reinforcements & Stress Zones',
    icon: 'tabler-shield-lock',
    placeholder: 'e.g. Triple-stitched inseam, Cordura 500D knee & ankle reinforcements, bar-tacked stress points...',
    quickAnswers: [
      'Triple-Needle Stitched Main & Inseam Seams',
      'Cordura® 500D Reinforced Knees & Hem Cuffs',
      'Aramid / Kevlar® Abrasion Panels',
      'Bar-Tack Reinforced Stress Points & Pocket Corners',
      'Fully Taped & Welded Waterproof Seams (EN 343)',
      'Double-Layer Reinforced Seat & Elbows',
    ],
  },
  ergonomics: {
    label: 'Ventilation & Ergonomics',
    title: 'Ventilation & Ergonomics',
    icon: 'tabler-wind',
    placeholder: 'e.g. Pre-bent articulated knees, gusseted crotch, action back pleats, underarm zip vents...',
    quickAnswers: [
      'Pre-Bent Articulated Knees & Elbows',
      'Diamond Gusseted Crotch Panel',
      '4-Way Stretch Gusset Panels (Back & Thighs)',
      'Bi-Swing Action Back Pleats',
      'Underarm Mesh Zippered Ventilation',
      'Extended Kidney-Warmer Drop Tail Back',
    ],
  },
  adjustability: {
    label: 'Adjustability (Waist, Cuffs & Hem)',
    title: 'Adjustability (Waist, Cuffs & Hem)',
    icon: 'tabler-adjustments-horizontal',
    placeholder: 'e.g. Side-elasticated waistband, +5 cm extendable hem allowance, Velcro adjustable cuffs...',
    quickAnswers: [
      'Side-Elasticated Waistband + High Belt Loops',
      '+5 cm Extendable Unstitched Hem Allowance',
      'Velcro Tab Adjustable Cuffs & Ankle Hems',
      'Internal Drawcord Hem & Detachable Hood',
      'Rib-Knit Storm Cuffs with Thumbholes',
    ],
  },
  visibility: {
    label: 'Visibility & Reflective Configuration',
    title: 'Visibility & Reflective Configuration',
    icon: 'tabler-sun-high',
    placeholder: 'e.g. 50mm 3M Scotchlite segmented reflective tape on torso, shoulders and double leg bands...',
    quickAnswers: [
      '50 mm Sewn Microprismatic Reflective Tape',
      '50 mm Segmented Heat-Applied Flexible Tape',
      'Flame-Retardant Aramid-Backed Reflective Tape',
      'Torso + Braces (Bio-Motion Shoulder Config)',
      'Contrast Dirt-Hiding Panels at Cuffs & Hem',
      'Reflective Piping Accents Only',
    ],
  },
  collar: {
    label: 'Collar & Hood Construction',
    title: 'Collar & Hood Construction',
    icon: 'tabler-shirt',
    placeholder: 'e.g. Fleece-lined stand/mandarin collar, helmet-compatible detachable storm hood...',
    quickAnswers: [
      'Stand / Mandarin Collar with Chin Guard',
      'Fleece-Lined Turn-Down Storm Collar',
      'Concealed Zip-In Hood in Collar',
      'Detachable Helmet-Compatible Storm Hood',
      'Rib-Knit Polo Collar with 3-Button Placket',
    ],
  },
  care: {
    label: 'Industrial Washing & Care Profile',
    title: 'Industrial Washing & Care Profile',
    icon: 'tabler-wash-machine',
    placeholder: 'e.g. Industrial wash 75°C (ISO 15797), max 50 wash cycles for reflective/FR treatments...',
    quickAnswers: [
      'Industrial Wash 75°C + Tunnel Dry (ISO 15797)',
      'Domestic Wash 60°C — Max 50 Cycles (Hi-Vis / FR)',
      'Wash 40°C — Do Not Bleach / Do Not Tumble Dry',
      'Re-Impregnate Fluorocarbon Finish After Wash (EN 13034)',
      'Close All Zippers & Velcro Before Laundering',
    ],
  },
}

/**
 * Fit & Sizing Variations
 */
export const WORKWEAR_FIT_VARIATIONS = [
  { value: 'unisex_regular', title: 'Unisex / Men’s Regular Fit (Coupe droite / Standard)' },
  { value: 'slim_ergonomic', title: 'Slim / Modern Ergonomic Fit (Coupe ajustée / Stretch)' },
  { value: 'womens_contoured', title: 'Women’s Specific / Contoured Fit (Coupe femme)' },
  { value: 'maternity', title: 'Maternity Workwear Fit (Vêtement de grossesse)' },
  { value: 'tall_short_inseam', title: 'Tall / Short Inseam Variations (+5 cm extendable hem)' },
  { value: 'big_plus_size', title: 'Big & Plus-Size Range (3XL to 6XL)' },
  { value: 'bespoke_measure', title: 'Bespoke / Made-to-Measure Individual Tailoring' },
]

export const WORKWEAR_CUSTOM_CONSTRUCTION_PRESETS = [
  'Client Corporate Pantone Dyed Fabric',
  'Custom Contrast Stitching Color',
  'RFID / Barcode Laundry Chip Pocket',
  'Custom Position Radio / Gas Detector Loops',
  'Name & Department Personalization Per Wearer',
  'Custom Sleeve / Inseam Length Alterations',
  'Metal-Free Trim Replacement for ATEX Zones',
]

/**
 * Anchor coordinates on a 320x420 SVG viewBox for front & back garment views.
 */
export const WORKWEAR_PLACEMENT_ZONES = [
  { value: 'chest_left', title: 'Chest Left (Pocket Flap)', views: ['front'], x: 194, y: 128 },
  { value: 'chest_right', title: 'Chest Right (Name Patch)', views: ['front'], x: 126, y: 128 },
  { value: 'chest_center', title: 'Chest Center', views: ['front'], x: 160, y: 142 },
  { value: 'pocket_left', title: 'Left Pocket', views: ['front'], x: 194, y: 158 },
  { value: 'pocket_right', title: 'Right Pocket', views: ['front'], x: 126, y: 158 },
  { value: 'shoulder_left', title: 'Left Shoulder', views: ['front', 'back'], x: 208, y: 92 },
  { value: 'shoulder_right', title: 'Right Shoulder', views: ['front', 'back'], x: 112, y: 92 },
  { value: 'collar', title: 'Collar / Neck / Top', views: ['front', 'back'], x: 160, y: 68 },
  { value: 'sleeve_left', title: 'Left Sleeve / Side', views: ['front', 'back'], x: 244, y: 145 },
  { value: 'sleeve_right', title: 'Right Sleeve / Side', views: ['front', 'back'], x: 76, y: 145 },
  { value: 'waist', title: 'Waist / Belt Line', views: ['front', 'back'], x: 160, y: 205 },
  { value: 'hem', title: 'Lower Hem / Jacket Bottom', views: ['front', 'back'], x: 160, y: 235 },
  { value: 'thigh_left', title: 'Left Thigh / Cargo', views: ['front', 'back'], x: 190, y: 278 },
  { value: 'thigh_right', title: 'Right Thigh / Cargo', views: ['front', 'back'], x: 130, y: 278 },
  { value: 'knee_left', title: 'Left Knee / Lower Leg', views: ['front', 'back'], x: 190, y: 336 },
  { value: 'knee_right', title: 'Right Knee / Lower Leg', views: ['front', 'back'], x: 130, y: 336 },
  { value: 'back_upper', title: 'Upper Back (Shoulder Blade)', views: ['back'], x: 160, y: 118 },
  { value: 'back_center', title: 'Center Back', views: ['back'], x: 160, y: 158 },
  { value: 'back_lower', title: 'Lower Back / Lumbar', views: ['back'], x: 160, y: 198 },
]

export function getSilhouetteSubcategories(categoryValue) {
  const cat = WORKWEAR_SILHOUETTE_CATALOG.find(c => c.value === categoryValue)

  return cat ? cat.subcategories : []
}

export function getSilhouetteItems(categoryValue, subcategoryValue) {
  const subs = getSilhouetteSubcategories(categoryValue)
  const sub = subs.find(s => s.value === subcategoryValue)

  return sub ? sub.items : []
}

export function findSilhouetteItemMeta(categoryValue, subcategoryValue, itemValue) {
  const items = getSilhouetteItems(categoryValue, subcategoryValue)

  return items.find(i => i.value === itemValue) || null
}

export function resolveSilhouetteDisplay(model, options = {}) {
  if (!model) return { categoryTitle: null, subcategoryTitle: null, itemTitle: 'Coverall / Boilersuit', itemTitleFr: null, renderer: 'coverall' }

  const normalizedModel = normalizeSilhouetteTaxonomy(model)
  const cat = WORKWEAR_SILHOUETTE_CATALOG.find(c => c.value === normalizedModel.silhouette_category)
  const sub = cat?.subcategories?.find(s => s.value === normalizedModel.silhouette_subcategory)
  const item = sub?.items?.find(i => i.value === normalizedModel.silhouette_item)
  const baseRenderer = WORKWEAR_SILHOUETTES.find(s => s.value === (normalizedModel.silhouette || item?.renderer || 'coverall'))

  const locale = options.locale || (typeof window !== 'undefined' ? (localStorage.getItem('currentLanguage') || 'en') : 'en')
  const isFr = typeof locale === 'string' && locale.startsWith('fr')
  const t = typeof options.t === 'function' ? options.t : (key => key)

  const catTitle = isFr && cat?.titleFr ? cat.titleFr : (cat?.title ? t(cat.title.replace(/^\d+\.\s*/, '')) : null)
  const subTitle = isFr && sub?.titleFr ? sub.titleFr : (sub?.title ? t(sub.title.replace(/^\d+\.\d+\s*/, '')) : null)

  const itemTitle = isFr && (item?.titleFr || baseRenderer?.titleFr)
    ? (item?.titleFr || baseRenderer?.titleFr)
    : (item?.title || baseRenderer?.title || normalizedModel.silhouette || 'Coverall / Boilersuit')

  return {
    categoryTitle: catTitle,
    subcategoryTitle: subTitle,
    itemTitle: t(itemTitle),
    shortTitle: formatSilhouetteShortTitle(item || { title: itemTitle }, locale),
    specBadge: extractSilhouetteSpecBadge(item),
    itemTitleFr: item?.titleFr || baseRenderer?.titleFr || null,
    renderer: normalizedModel.silhouette || item?.renderer || sub?.defaultRenderer || cat?.defaultRenderer || 'coverall',
  }
}

export const SILHOUETTE_SHORT_TITLES = {
  // One-Piece / Coveralls
  single_zip_coverall: { en: 'Single-Zip Coverall', fr: 'Combinaison 1 zip' },
  double_zip_coverall: { en: 'Double-Zip Coverall', fr: 'Combinaison 2 zips' },
  insulated_winter_coverall: { en: 'Winter Coverall', fr: 'Combinaison hiver' },
  short_sleeve_summer_coverall: { en: 'Summer Coverall', fr: 'Combinaison été' },
  bib_brace_overalls: { en: 'Bib & Brace', fr: 'Salopette à bretelles' },

  // Tops & Jackets
  tshirt_short_crew: { en: 'Short-Sleeve T-Shirt', fr: 'T-shirt manches courtes' },
  tshirt_long_sleeve: { en: 'Long-Sleeve T-Shirt', fr: 'T-shirt manches longues' },
  polo_short_pique: { en: 'Piqué Polo Shirt', fr: 'Polo manches courtes' },
  polo_long_sleeve: { en: 'Long-Sleeve Polo', fr: 'Polo manches longues' },
  work_shirt_short: { en: 'Short-Sleeve Shirt', fr: 'Chemise manches courtes' },
  work_shirt_long: { en: 'Long-Sleeve Shirt', fr: 'Chemise manches longues' },
  flannel_overshirt: { en: 'Flannel Overshirt', fr: 'Surchemise' },
  chef_tunic: { en: 'Mandarin Tunic', fr: 'Tunique col officier' },
  crewneck_sweatshirt: { en: 'Crewneck Sweatshirt', fr: 'Sweat col rond' },
  quarter_zip_sweatshirt: { en: 'Quarter-Zip Sweat', fr: 'Sweat col zippé' },
  pullover_hoodie: { en: 'Pullover Hoodie', fr: 'Sweat à capuche' },
  microfleece_jacket: { en: 'Microfleece Jacket', fr: 'Veste polaire' },
  knitted_work_sweater: { en: 'Work Sweater', fr: 'Pull de travail' },
  standard_work_jacket: { en: 'Work Jacket', fr: 'Veste de travail' },
  softshell_jacket: { en: 'Softshell Jacket', fr: 'Veste softshell' },
  bomber_pilot_jacket: { en: 'Pilot Bomber', fr: 'Blouson pilote' },
  multipocket_reporter_vest: { en: 'Work / Hi-Vis Vest', fr: 'Gilet multipoches' },
  parka_3in1_4in1: { en: 'Work Parka', fr: 'Parka de travail' },
  hardshell_rain_jacket: { en: 'Rain Jacket', fr: 'Veste de pluie' },
  trench_rain_coat: { en: 'Rain Coat', fr: 'Manteau de pluie' },
  puffer_insulated_jacket: { en: 'Puffer Jacket', fr: 'Doudoune travail' },
  lab_coat_warehouse_smock: { en: 'Lab / Work Coat', fr: 'Blouse de travail' },

  // Trousers / Bottoms
  standard_work_trousers: { en: 'Standard Trousers', fr: 'Pantalon classique' },
  cargo_multipocket_trousers: { en: 'Cargo Trousers', fr: 'Pantalon cargo' },
  holster_pocket_trousers: { en: 'Craftsman Trousers', fr: 'Pantalon poches flottantes' },
  elasticated_kitchen_medical_trousers: { en: 'Medical Trousers', fr: 'Pantalon médical' },
  work_cargo_shorts: { en: 'Work Cargo Shorts', fr: 'Bermuda de travail' },

  // Footwear
  low_cut_safety_shoe: { en: 'Low-Cut Safety Shoe', fr: 'Chaussure basse' },
  ankle_safety_boot: { en: 'Mid-Cut Safety Boot', fr: 'Chaussure montante' },
  high_leg_combat_boot: { en: 'Ranger Safety Boot', fr: 'Rangers de sécurité' },
  rigger_pullon_boot: { en: 'Rigger Safety Boot', fr: 'Botte de chantier' },
  safety_trainer_sneaker: { en: 'Safety Trainer', fr: 'Basket de sécurité' },
  esd_cleanroom_shoe: { en: 'Cleanroom Shoe', fr: 'Chaussure ESD' },
  executive_dress_safety_shoe: { en: 'Executive Shoe', fr: 'Chaussure de ville' },
  pvc_nitrile_safety_wellington: { en: 'PVC Wellington', fr: 'Botte PVC étanche' },
  food_white_wellington: { en: 'Hygiene Wellington', fr: 'Botte blanche agro' },
  dielectric_insulating_boot: { en: 'Dielectric Boot', fr: 'Botte isolante' },

  // Aprons & Medical
  scrub_tunic_top: { en: 'Medical Scrub Top', fr: 'Tunique médicale' },
  scrub_trousers: { en: 'Scrub Trousers', fr: 'Pantalon médical' },
  isolation_surgical_gown: { en: 'Hygiene Gown', fr: 'Casaque chirurgicale' },
  haccp_pocketless_coat: { en: 'HACCP Food Coat', fr: 'Blouse agroalimentaire' },
  chef_double_breasted_jacket: { en: 'Chef Jacket', fr: 'Veste de cuisine' },
  butcher_waterproof_apron: { en: 'Industrial Apron', fr: 'Tablier industriel' },

  // Hand Protection
  pu_nitrile_handling_glove: { en: 'Precision Glove', fr: 'Gant manutention' },
  cut_resistant_glove: { en: 'Cut-Resistant Glove', fr: 'Gant anti-coupure' },
  chemical_gauntlet_glove: { en: 'Chemical Gauntlet', fr: 'Gant chimique' },
  welding_gauntlet_glove: { en: 'Welding Gauntlet', fr: 'Gant de soudeur' },
  cryogenic_cold_glove: { en: 'Thermal Cold Glove', fr: 'Gant grand froid' },
  dielectric_insulating_glove: { en: 'Dielectric Glove', fr: 'Gant isolant' },
  disposable_nitrile_glove: { en: 'Disposable Glove', fr: 'Gant jetable nitrile' },

  // Head & Fall Protection
  industrial_safety_helmet: { en: 'Safety Helmet', fr: 'Casque de chantier' },
  bump_cap: { en: 'Safety Bump Cap', fr: 'Casquette anti-heurt' },
  safety_glasses_goggles: { en: 'Safety Spectacles', fr: 'Lunettes de sécurité' },
  fall_arrest_harness: { en: 'Fall Arrest Harness', fr: 'Harnais antichute' },
}

export const SUBCATEGORY_SHORT_TITLES = {
  one_piece: { en: 'Coveralls & Overalls', fr: 'Combinaisons' },
  jackets_vests: { en: 'Jackets, Parkas & Vests', fr: 'Vestes & Gilets' },
  tshirts_polos: { en: 'T-Shirts & Polos', fr: 'T-Shirts & Polos' },
  shirts_tunics: { en: 'Shirts & Tunics', fr: 'Chemises & Tuniques' },
  sweatshirts_fleeces: { en: 'Sweatshirts & Fleeces', fr: 'Sweats & Polaires' },
  bottoms: { en: 'Trousers & Shorts', fr: 'Pantalons & Shorts' },
  protective_garments: { en: 'Lab Coats & Aprons', fr: 'Blouses & Tabliers' },
  safety_shoes: { en: 'Safety Shoes (S1–S3)', fr: 'Chaussures sécurité' },
  wellington_gumboots: { en: 'Boots & Wellingtons', fr: 'Bottes étanches' },
  gloves: { en: 'Safety Gloves', fr: 'Gants de sécurité' },
  head_protection: { en: 'Safety Helmets & Caps', fr: 'Casques & Casquettes' },
  eye_face_fall: { en: 'Eye & Fall Arrest PPE', fr: 'Lunettes & Harnais' },
}

export function formatSilhouetteShortTitle(item, locale = 'en') {
  if (!item) return ''

  const itemKey = typeof item === 'object' ? item.value : item
  const isFr = typeof locale === 'string' && locale.startsWith('fr')

  if (itemKey && SILHOUETTE_SHORT_TITLES[itemKey]) {
    return isFr ? SILHOUETTE_SHORT_TITLES[itemKey].fr : SILHOUETTE_SHORT_TITLES[itemKey].en
  }

  const rawTitle = typeof item === 'string' ? item : (item.title || item.value || '')
  const rawTitleFr = typeof item === 'object' ? item.titleFr : null

  if (isFr && rawTitleFr) {
    const cleanFr = rawTitleFr.replace(/\s*\([^)]*\)/g, '').split(' / ')[0].trim()
    if (cleanFr) return cleanFr
  }

  const withoutParens = rawTitle.replace(/\s*\([^)]*\)/g, '').split(' / ')[0].trim()

  return withoutParens || rawTitle
}

export function formatSubcategoryShortTitle(sub, locale = 'en') {
  if (!sub) return ''

  const subKey = typeof sub === 'object' ? sub.value : sub
  const isFr = typeof locale === 'string' && locale.startsWith('fr')

  if (subKey && SUBCATEGORY_SHORT_TITLES[subKey]) {
    return isFr ? SUBCATEGORY_SHORT_TITLES[subKey].fr : SUBCATEGORY_SHORT_TITLES[subKey].en
  }

  const rawTitle = typeof sub === 'object' ? (isFr && sub.titleFr ? sub.titleFr : sub.title) : sub

  return rawTitle || ''
}

export function extractSilhouetteSpecBadge(item) {
  const title = typeof item === 'string' ? item : item?.title
  if (!title) return null

  const match = title.match(/\(([^)]+)\)/)
  if (!match) return null

  const spec = match[1].trim()
  if (spec.length <= 25) return spec

  return null
}

export function resolveColorHex(colorNameOrHex, fallback = '#1e293b') {
  if (!colorNameOrHex || typeof colorNameOrHex !== 'string') return fallback
  const trimmed = colorNameOrHex.trim()
  if (trimmed.startsWith('#')) return trimmed

  const lower = trimmed.toLowerCase()

  const found = WORKWEAR_COLOR_PALETTE.find(
    c => c.value === lower || c.label.toLowerCase() === lower || lower.includes(c.value.replace('hi-vis-', '')),
  )

  return found ? found.hex : fallback
}

export function resolveColorLabel(colorValue, options = {}) {
  if (!colorValue || typeof colorValue !== 'string') return colorValue || ''
  
  const trimmed = colorValue.trim()
  const lower = trimmed.toLowerCase()
  const locale = options.locale || (typeof window !== 'undefined' ? (localStorage.getItem('currentLanguage') || 'en') : 'en')
  const isFr = typeof locale === 'string' && locale.startsWith('fr')
  const t = typeof options.t === 'function' ? options.t : (key => key)

  const found = WORKWEAR_COLOR_PALETTE.find(
    c => c.value === lower || c.label.toLowerCase() === lower || lower.includes(c.value.replace('hi-vis-', '')),
  )

  if (found) {
    return isFr && found.labelFr ? found.labelFr : t(found.label)
  }

  // Return original value if not found in palette
  return trimmed
}

export function resolveZoneMeta(zoneValue, silhouette = 'coverall') {
  const zones = getTemplateZones(silhouette)

  return zones.find(z => z.value === zoneValue)
    || WORKWEAR_PLACEMENT_ZONES.find(z => z.value === zoneValue)
    || zones[0]
    || WORKWEAR_PLACEMENT_ZONES[0]
}

export function resolvePlacementTypeMeta(typeValue) {
  return WORKWEAR_PLACEMENT_TYPES.find(t => t.value === typeValue) || WORKWEAR_PLACEMENT_TYPES[0]
}

export const WORKWEAR_STUDIO_VIEWBOX = { width: 320, height: 420 }

function makeZone(value, title, views, anchorX, anchorY, safeRect, mmLimits, maxOffsetMm) {
  return {
    value,
    title,
    views,
    x: anchorX,
    y: anchorY,
    anchor: { x: anchorX, y: anchorY },
    safeBounds: {
      x: safeRect[0],
      y: safeRect[1],
      width: safeRect[2],
      height: safeRect[3],
    },
    minWidthMm: mmLimits[0],
    maxWidthMm: mmLimits[1],
    minHeightMm: mmLimits[2],
    maxHeightMm: mmLimits[3],
    maxOffsetXMm: maxOffsetMm[0],
    maxOffsetYMm: maxOffsetMm[1],
  }
}

function makeJacketOrParkaTemplate(silhouette, label, isParka) {
  const hemY = isParka ? 256 : 236
  const hemBoundsY = isParka ? 216 : 200
  const boundsHeight = isParka ? 248 : 206
  const boundsY = isParka ? 28 : 42

  return {
    silhouette,
    label,
    viewBox: WORKWEAR_STUDIO_VIEWBOX,
    garmentBounds: { x: 42, y: boundsY, width: 236, height: boundsHeight },
    scale: {
      unitsPerMm: 0.28,
      referenceWidthMm: isParka ? 580 : 560,
      referenceHeightMm: isParka ? 860 : 740,
      scaleMode: 'proportional_template',
      scaleVerified: false,
    },
    colorRegions: [
      { key: 'body', label: 'Main Body & Sleeves', role: 'primary', slot: 0 },
      { key: 'yoke', label: 'Upper Chest & Shoulder Yoke', role: 'contrast', slot: 1 },
      { key: 'collar_hood', label: isParka ? 'Storm Hood & Chin Guard' : 'Stand Collar', role: isParka ? 'contrast' : 'primary', slot: isParka ? 1 : 0 },
      { key: 'pockets', label: 'Chest & Lower Pockets', role: 'primary', slot: 0 },
      { key: 'hem_cuffs', label: 'Hem & Cuff Ribbing', role: 'primary', slot: 0 },
    ],
    zones: [
      makeZone('chest_left', 'Chest Left (Pocket Flap)', ['front'], 190, 128, [156, 88, 66, 74], [15, 200, 10, 180], [70, 75]),
      makeZone('chest_right', 'Chest Right (Name Patch)', ['front'], 130, 128, [98, 88, 66, 74], [15, 200, 10, 180], [70, 75]),
      makeZone('chest_center', 'Chest Center', ['front'], 160, 140, [100, 88, 120, 100], [15, 380, 10, 260], [135, 110]),
      makeZone('pocket_left', 'Left Lower Pocket', ['front'], 190, 196, [156, 154, 66, 82], [15, 200, 10, 200], [70, 85]),
      makeZone('pocket_right', 'Right Lower Pocket', ['front'], 130, 196, [98, 154, 66, 82], [15, 200, 10, 200], [70, 85]),
      makeZone('shoulder_left', 'Left Shoulder', ['front', 'back'], 206, 88, [168, 56, 70, 60], [15, 200, 10, 150], [70, 65]),
      makeZone('shoulder_right', 'Right Shoulder', ['front', 'back'], 114, 88, [82, 56, 70, 60], [15, 200, 10, 150], [70, 65]),
      makeZone('collar', isParka ? 'Storm Hood / Collar' : 'Collar / Neck', ['front', 'back'], 160, 60, [116, isParka ? 24 : 38, 88, isParka ? 62 : 48], [15, 240, 10, 130], [85, 60]),
      makeZone('sleeve_left', 'Left Sleeve', ['front', 'back'], 244, 145, [210, 82, 68, 120], [15, 190, 10, 260], [75, 115]),
      makeZone('sleeve_right', 'Right Sleeve', ['front', 'back'], 76, 145, [42, 82, 68, 120], [15, 190, 10, 260], [75, 115]),
      makeZone('waist', 'Waist / Mid Torso', ['front', 'back'], 160, 205, [98, 168, 124, 74], [15, 400, 10, 190], [140, 80]),
      makeZone('hem', 'Lower Jacket Hem', ['front', 'back'], 160, hemY, [96, hemBoundsY, 128, 64], [15, 400, 10, 160], [140, 70]),
      makeZone('back_upper', 'Upper Back (Shoulder Blade)', ['back'], 160, 116, [98, 70, 124, 92], [15, 400, 10, 240], [140, 100]),
      makeZone('back_center', 'Center Back', ['back'], 160, 164, [98, 114, 124, 98], [15, 400, 10, 260], [140, 110]),
      makeZone('back_lower', 'Lower Back / Hem Panel', ['back'], 160, 214, [98, 168, 124, 88], [15, 400, 10, 220], [140, 95]),
    ],
  }
}

function makePoloOrTopTemplate(silhouette, label) {
  return {
    silhouette,
    label,
    viewBox: WORKWEAR_STUDIO_VIEWBOX,
    garmentBounds: { x: 56, y: 44, width: 208, height: 208 },
    scale: {
      unitsPerMm: 0.28,
      referenceWidthMm: 520,
      referenceHeightMm: 720,
      scaleMode: 'proportional_template',
      scaleVerified: false,
    },
    colorRegions: [
      { key: 'body', label: 'Main Torso & Sleeves', role: 'primary', slot: 0 },
      { key: 'shoulder_panels', label: 'Contrast Shoulder Panels', role: 'contrast', slot: 1 },
      { key: 'collar_placket', label: 'Collar & Placket Trim', role: 'primary', slot: 0 },
      { key: 'cuff_trim', label: 'Sleeve Cuff Trim', role: 'contrast', slot: 1 },
    ],
    zones: [
      makeZone('chest_left', 'Chest Left (Heart / Logo)', ['front'], 190, 122, [158, 88, 58, 68], [15, 180, 10, 160], [65, 70]),
      makeZone('chest_right', 'Chest Right (Name / Badge)', ['front'], 130, 122, [104, 88, 58, 68], [15, 180, 10, 160], [65, 70]),
      makeZone('chest_center', 'Chest Center', ['front'], 160, 148, [104, 108, 112, 84], [15, 360, 10, 220], [125, 95]),
      makeZone('pocket_left', 'Left Breast Pocket', ['front'], 190, 140, [158, 106, 58, 64], [15, 170, 10, 150], [65, 65]),
      makeZone('pocket_right', 'Right Breast Pocket', ['front'], 130, 140, [104, 106, 58, 64], [15, 170, 10, 150], [65, 65]),
      makeZone('shoulder_left', 'Left Shoulder Panel', ['front', 'back'], 204, 82, [168, 56, 66, 52], [15, 180, 10, 130], [65, 55]),
      makeZone('shoulder_right', 'Right Shoulder Panel', ['front', 'back'], 116, 82, [86, 56, 66, 52], [15, 180, 10, 130], [65, 55]),
      makeZone('collar', 'Collar / Nape', ['front', 'back'], 160, 58, [118, 42, 84, 42], [15, 200, 10, 100], [75, 45]),
      makeZone('sleeve_left', 'Left Sleeve', ['front', 'back'], 238, 118, [210, 78, 56, 78], [15, 160, 10, 180], [60, 75]),
      makeZone('sleeve_right', 'Right Sleeve', ['front', 'back'], 82, 118, [54, 78, 56, 78], [15, 160, 10, 180], [60, 75]),
      makeZone('waist', 'Mid Torso / Waist', ['front', 'back'], 160, 198, [104, 164, 112, 66], [15, 360, 10, 180], [125, 75]),
      makeZone('hem', 'Bottom Hem', ['front', 'back'], 160, 232, [104, 200, 112, 54], [15, 360, 10, 140], [125, 60]),
      makeZone('back_upper', 'Upper Back (Shoulder Yoke)', ['back'], 160, 112, [104, 70, 112, 84], [15, 360, 10, 220], [125, 95]),
      makeZone('back_center', 'Center Back', ['back'], 160, 158, [104, 114, 112, 88], [15, 360, 10, 240], [125, 100]),
      makeZone('back_lower', 'Lower Back', ['back'], 160, 208, [104, 168, 112, 80], [15, 360, 10, 200], [125, 85]),
    ],
  }
}

export const WORKWEAR_STUDIO_TEMPLATES = {
  coverall: {
    silhouette: 'coverall',
    label: 'Coverall / Boilersuit',
    viewBox: WORKWEAR_STUDIO_VIEWBOX,
    garmentBounds: { x: 46, y: 42, width: 228, height: 342 },
    scale: {
      unitsPerMm: 0.25,
      referenceWidthMm: 550,
      referenceHeightMm: 1600,
      scaleMode: 'proportional_template',
      scaleVerified: false,
    },
    colorRegions: [
      { key: 'body', label: 'Main Torso & Legs', role: 'primary', slot: 0 },
      { key: 'yoke', label: 'Upper Chest & Shoulder Yoke', role: 'contrast', slot: 1 },
      { key: 'collar', label: 'Collar & Waistband', role: 'primary', slot: 0 },
      { key: 'pockets', label: 'Chest Pocket Flaps', role: 'contrast', slot: 1 },
      { key: 'reflective', label: 'Reflective Tape Strips', role: 'reflective', slot: null, defaultColor: 'silver' },
    ],
    zones: [
      makeZone('chest_left', 'Chest Left (Pocket Flap)', ['front'], 190, 124, [156, 88, 66, 72], [15, 220, 10, 160], [75, 75]),
      makeZone('chest_right', 'Chest Right (Name Patch)', ['front'], 130, 124, [98, 88, 66, 72], [15, 220, 10, 160], [75, 75]),
      makeZone('chest_center', 'Chest Center', ['front'], 160, 136, [100, 88, 120, 96], [15, 420, 10, 260], [140, 110]),
      makeZone('pocket_left', 'Left Chest Pocket', ['front'], 190, 142, [158, 106, 62, 72], [15, 200, 10, 180], [70, 75]),
      makeZone('pocket_right', 'Right Chest Pocket', ['front'], 130, 142, [100, 106, 62, 72], [15, 200, 10, 180], [70, 75]),
      makeZone('shoulder_left', 'Left Shoulder', ['front', 'back'], 206, 84, [170, 54, 68, 58], [15, 200, 10, 140], [70, 60]),
      makeZone('shoulder_right', 'Right Shoulder', ['front', 'back'], 114, 84, [82, 54, 68, 58], [15, 200, 10, 140], [70, 60]),
      makeZone('collar', 'Collar / Neck', ['front', 'back'], 160, 60, [118, 38, 84, 46], [15, 220, 10, 110], [80, 50]),
      makeZone('sleeve_left', 'Left Sleeve', ['front', 'back'], 244, 138, [212, 82, 62, 106], [15, 180, 10, 220], [70, 100]),
      makeZone('sleeve_right', 'Right Sleeve', ['front', 'back'], 76, 138, [46, 82, 62, 106], [15, 180, 10, 220], [70, 100]),
      makeZone('waist', 'Waist / Belt Line', ['front', 'back'], 160, 205, [98, 170, 124, 70], [15, 440, 10, 180], [150, 80]),
      makeZone('thigh_left', 'Left Thigh / Cargo', ['front', 'back'], 196, 278, [164, 232, 60, 92], [15, 200, 10, 240], [70, 100]),
      makeZone('thigh_right', 'Right Thigh / Cargo', ['front', 'back'], 124, 278, [96, 232, 60, 92], [15, 200, 10, 240], [70, 100]),
      makeZone('knee_left', 'Left Knee / Lower Leg', ['front', 'back'], 196, 336, [166, 296, 58, 86], [15, 190, 10, 220], [65, 90]),
      makeZone('knee_right', 'Right Knee / Lower Leg', ['front', 'back'], 124, 336, [96, 296, 58, 86], [15, 190, 10, 220], [65, 90]),
      makeZone('hem', 'Lower Leg Hem', ['front', 'back'], 160, 368, [96, 340, 128, 46], [15, 440, 10, 140], [150, 55]),
      makeZone('back_upper', 'Upper Back (Shoulder Blade)', ['back'], 160, 112, [98, 68, 124, 88], [15, 440, 10, 240], [150, 100]),
      makeZone('back_center', 'Center Back', ['back'], 160, 156, [100, 112, 120, 92], [15, 420, 10, 260], [140, 110]),
      makeZone('back_lower', 'Lower Back / Lumbar', ['back'], 160, 194, [100, 154, 120, 80], [15, 420, 10, 220], [140, 95]),
    ],
  },
  jacket: makeJacketOrParkaTemplate('jacket', 'Work Jacket / Softshell', false),
  parka: makeJacketOrParkaTemplate('parka', 'Weatherproof Parka', true),
  sweatshirt: makeJacketOrParkaTemplate('sweatshirt', 'Work Sweatshirt / Hoodie / Fleece', false),
  lab_coat: makeJacketOrParkaTemplate('lab_coat', 'Lab Coat / Hygiene Smock / Tunic', true),
  vest: {
    silhouette: 'vest',
    label: 'Hi-Vis Safety Vest / Gilet',
    viewBox: WORKWEAR_STUDIO_VIEWBOX,
    garmentBounds: { x: 100, y: 56, width: 120, height: 192 },
    scale: {
      unitsPerMm: 0.28,
      referenceWidthMm: 540,
      referenceHeightMm: 680,
      scaleMode: 'proportional_template',
      scaleVerified: false,
    },
    colorRegions: [
      { key: 'hi_vis_body', label: 'Fluorescent Main Body', role: 'contrast', slot: 1 },
      { key: 'contrast_panel', label: 'Lower Contrast Dirt Panel', role: 'primary', slot: 0 },
      { key: 'binding_trim', label: 'Edge Binding & Zip Trim', role: 'primary', slot: 0 },
      { key: 'reflective_tape', label: 'EN ISO 20471 Reflective Harness', role: 'reflective', slot: null, defaultColor: 'silver' },
    ],
    zones: [
      makeZone('chest_left', 'Chest Left (ID / Logo)', ['front'], 192, 128, [158, 90, 58, 70], [15, 180, 10, 160], [65, 70]),
      makeZone('chest_right', 'Chest Right', ['front'], 128, 128, [104, 90, 58, 70], [15, 180, 10, 160], [65, 70]),
      makeZone('chest_center', 'Chest Center', ['front'], 160, 138, [104, 92, 112, 86], [15, 360, 10, 220], [120, 95]),
      makeZone('pocket_left', 'Left Lower Pocket', ['front'], 190, 176, [158, 144, 58, 66], [15, 180, 10, 160], [65, 65]),
      makeZone('pocket_right', 'Right Lower Pocket', ['front'], 130, 176, [104, 144, 58, 66], [15, 180, 10, 160], [65, 65]),
      makeZone('shoulder_left', 'Left Shoulder Strap', ['front', 'back'], 196, 82, [170, 56, 48, 52], [15, 140, 10, 120], [50, 50]),
      makeZone('shoulder_right', 'Right Shoulder Strap', ['front', 'back'], 124, 82, [102, 56, 48, 52], [15, 140, 10, 120], [50, 50]),
      makeZone('waist', 'Waist Band / Torso Band', ['front', 'back'], 160, 196, [102, 160, 116, 68], [15, 380, 10, 180], [130, 75]),
      makeZone('hem', 'Lower Contrast Hem', ['front', 'back'], 160, 224, [102, 194, 116, 56], [15, 380, 10, 150], [130, 60]),
      makeZone('back_upper', 'Upper Back (Between Strips)', ['back'], 160, 114, [104, 72, 112, 84], [15, 360, 10, 220], [125, 95]),
      makeZone('back_center', 'Center Back Panel', ['back'], 160, 152, [104, 106, 112, 92], [15, 360, 10, 240], [125, 100]),
      makeZone('back_lower', 'Lower Back Contrast Panel', ['back'], 160, 214, [102, 180, 116, 68], [15, 380, 10, 180], [130, 75]),
    ],
  },
  trousers: {
    silhouette: 'trousers',
    label: 'Work / Cargo Trousers',
    viewBox: WORKWEAR_STUDIO_VIEWBOX,
    garmentBounds: { x: 94, y: 78, width: 132, height: 304 },
    scale: {
      unitsPerMm: 0.28,
      referenceWidthMm: 480,
      referenceHeightMm: 1060,
      scaleMode: 'proportional_template',
      scaleVerified: false,
    },
    colorRegions: [
      { key: 'main_fabric', label: 'Main Legs & Waistband', role: 'primary', slot: 0 },
      { key: 'cargo_accents', label: 'Contrast Cargo & Hip Gussets', role: 'contrast', slot: 1 },
      { key: 'knee_reinforcements', label: 'Knee Pad Reinforcements', role: 'primary', slot: 0 },
      { key: 'reflective_bands', label: 'Ankle Reflective Bands', role: 'reflective', slot: null, defaultColor: 'silver' },
    ],
    zones: [
      makeZone('waist', 'Waistband / Belt Line', ['front', 'back'], 160, 90, [102, 74, 116, 48], [15, 380, 10, 120], [130, 50]),
      makeZone('pocket_left', 'Left Hip / Holster Pocket', ['front', 'back'], 192, 136, [158, 98, 62, 78], [15, 190, 10, 200], [70, 80]),
      makeZone('pocket_right', 'Right Hip / Holster Pocket', ['front', 'back'], 128, 136, [100, 98, 62, 78], [15, 190, 10, 200], [70, 80]),
      makeZone('back_lower', 'Rear Yoke / Seat Panel', ['back'], 160, 128, [102, 94, 116, 72], [15, 360, 10, 180], [125, 75]),
      makeZone('thigh_left', 'Left Thigh / Cargo Pocket', ['front', 'back'], 198, 242, [164, 184, 62, 104], [15, 190, 10, 260], [70, 110]),
      makeZone('thigh_right', 'Right Thigh / Cargo Pocket', ['front', 'back'], 122, 242, [94, 184, 62, 104], [15, 190, 10, 260], [70, 110]),
      makeZone('knee_left', 'Left Knee Pad Panel', ['front', 'back'], 198, 314, [166, 276, 60, 80], [15, 180, 10, 200], [65, 85]),
      makeZone('knee_right', 'Right Knee Pad Panel', ['front', 'back'], 122, 314, [94, 276, 60, 80], [15, 180, 10, 200], [65, 85]),
      makeZone('hem', 'Lower Leg / Ankle Cuff', ['front', 'back'], 160, 354, [94, 326, 132, 58], [15, 420, 10, 160], [140, 65]),
    ],
  },
  shorts: {
    silhouette: 'shorts',
    label: 'Work Shorts / Cargo Bermuda',
    viewBox: WORKWEAR_STUDIO_VIEWBOX,
    garmentBounds: { x: 94, y: 78, width: 132, height: 190 },
    scale: {
      unitsPerMm: 0.28,
      referenceWidthMm: 480,
      referenceHeightMm: 560,
      scaleMode: 'proportional_template',
      scaleVerified: false,
    },
    colorRegions: [
      { key: 'main_fabric', label: 'Main Shorts & Waistband', role: 'primary', slot: 0 },
      { key: 'cargo_accents', label: 'Contrast Cargo Pockets & Trim', role: 'contrast', slot: 1 },
    ],
    zones: [
      makeZone('waist', 'Waistband / Belt Line', ['front', 'back'], 160, 90, [102, 74, 116, 48], [15, 380, 10, 120], [130, 50]),
      makeZone('pocket_left', 'Left Hip / Holster Pocket', ['front', 'back'], 192, 136, [158, 98, 62, 78], [15, 190, 10, 200], [70, 80]),
      makeZone('pocket_right', 'Right Hip / Holster Pocket', ['front', 'back'], 128, 136, [100, 98, 62, 78], [15, 190, 10, 200], [70, 80]),
      makeZone('back_lower', 'Rear Yoke / Seat Panel', ['back'], 160, 128, [102, 94, 116, 72], [15, 360, 10, 180], [125, 75]),
      makeZone('thigh_left', 'Left Thigh / Cargo Pocket', ['front', 'back'], 196, 212, [162, 170, 62, 86], [15, 190, 10, 220], [70, 90]),
      makeZone('thigh_right', 'Right Thigh / Cargo Pocket', ['front', 'back'], 124, 212, [96, 170, 62, 86], [15, 190, 10, 220], [70, 90]),
      makeZone('hem', 'Shorts Hem / Cuff', ['front', 'back'], 160, 248, [96, 224, 128, 44], [15, 400, 10, 120], [130, 50]),
    ],
  },
  polo: makePoloOrTopTemplate('polo', 'Polo Shirt'),
  tshirt: makePoloOrTopTemplate('tshirt', 'Work T-Shirt (Crew-Neck)'),
  shirt: makePoloOrTopTemplate('shirt', 'Button-Down Work Shirt / Overshirt'),
  overalls: {
    silhouette: 'overalls',
    label: 'Bib & Brace Overalls',
    viewBox: WORKWEAR_STUDIO_VIEWBOX,
    garmentBounds: { x: 94, y: 56, width: 132, height: 326 },
    scale: {
      unitsPerMm: 0.26,
      referenceWidthMm: 520,
      referenceHeightMm: 1450,
      scaleMode: 'proportional_template',
      scaleVerified: false,
    },
    colorRegions: [
      { key: 'body', label: 'Bib & Trouser Legs', role: 'primary', slot: 0 },
      { key: 'straps_bib_pocket', label: 'Shoulder Straps & Bib Pocket', role: 'contrast', slot: 1 },
      { key: 'reflective_bands', label: 'Leg Reflective Bands', role: 'reflective', slot: null, defaultColor: 'silver' },
    ],
    zones: [
      makeZone('chest_center', 'Center Bib Pocket', ['front'], 160, 145, [118, 110, 84, 72], [15, 280, 10, 200], [95, 80]),
      makeZone('chest_left', 'Left Bib', ['front'], 180, 145, [150, 110, 54, 70], [15, 180, 10, 180], [60, 75]),
      makeZone('chest_right', 'Right Bib', ['front'], 140, 145, [116, 110, 54, 70], [15, 180, 10, 180], [60, 75]),
      makeZone('pocket_left', 'Left Hip Pocket', ['front', 'back'], 190, 210, [158, 175, 58, 70], [15, 190, 10, 180], [65, 75]),
      makeZone('pocket_right', 'Right Hip Pocket', ['front', 'back'], 130, 210, [104, 175, 58, 70], [15, 190, 10, 180], [65, 75]),
      makeZone('shoulder_left', 'Left Brace Strap', ['front', 'back'], 187, 86, [170, 56, 36, 60], [10, 110, 10, 160], [40, 65]),
      makeZone('shoulder_right', 'Right Brace Strap', ['front', 'back'], 133, 86, [114, 56, 36, 60], [10, 110, 10, 160], [40, 65]),
      makeZone('waist', 'Waist Seam', ['front', 'back'], 160, 196, [102, 168, 116, 60], [15, 380, 10, 160], [130, 70]),
      makeZone('thigh_left', 'Left Thigh / Cargo', ['front', 'back'], 196, 278, [164, 232, 60, 92], [15, 190, 10, 240], [65, 100]),
      makeZone('thigh_right', 'Right Thigh / Cargo', ['front', 'back'], 124, 278, [96, 232, 60, 92], [15, 190, 10, 240], [65, 100]),
      makeZone('knee_left', 'Left Knee', ['front', 'back'], 196, 336, [166, 296, 58, 86], [15, 180, 10, 220], [65, 90]),
      makeZone('knee_right', 'Right Knee', ['front', 'back'], 124, 336, [96, 296, 58, 86], [15, 180, 10, 220], [65, 90]),
      makeZone('hem', 'Lower Leg Hem', ['front', 'back'], 160, 364, [94, 332, 132, 52], [15, 420, 10, 150], [140, 60]),
      makeZone('back_upper', 'Upper Back Bib', ['back'], 160, 138, [114, 104, 92, 72], [15, 300, 10, 200], [100, 80]),
      makeZone('back_center', 'Center Back', ['back'], 160, 178, [104, 140, 112, 76], [15, 360, 10, 200], [125, 85]),
      makeZone('back_lower', 'Lower Back / Seat', ['back'], 160, 218, [102, 180, 116, 76], [15, 380, 10, 200], [130, 85]),
    ],
  },
  apron: {
    silhouette: 'apron',
    label: 'Apron / Tabard / Tunic',
    viewBox: WORKWEAR_STUDIO_VIEWBOX,
    garmentBounds: { x: 102, y: 56, width: 116, height: 286 },
    scale: {
      unitsPerMm: 0.28,
      referenceWidthMm: 500,
      referenceHeightMm: 950,
      scaleMode: 'proportional_template',
      scaleVerified: false,
    },
    colorRegions: [
      { key: 'body', label: 'Main Apron / Tabard Body', role: 'primary', slot: 0 },
      { key: 'front_pocket', label: 'Center Pouch Pocket', role: 'contrast', slot: 1 },
    ],
    zones: [
      makeZone('chest_center', 'Upper Bib Center', ['front'], 160, 136, [106, 96, 108, 84], [15, 340, 10, 220], [120, 90]),
      makeZone('chest_left', 'Upper Bib Left', ['front'], 186, 136, [154, 98, 60, 76], [15, 180, 10, 180], [65, 80]),
      makeZone('chest_right', 'Upper Bib Right', ['front'], 134, 136, [106, 98, 60, 76], [15, 180, 10, 180], [65, 80]),
      makeZone('pocket_left', 'Left Pouch Pocket', ['front'], 178, 212, [148, 180, 62, 68], [15, 180, 10, 160], [65, 70]),
      makeZone('pocket_right', 'Right Pouch Pocket', ['front'], 142, 212, [110, 180, 62, 68], [15, 180, 10, 160], [65, 70]),
      makeZone('waist', 'Center Waist / Pocket Area', ['front', 'back'], 160, 212, [104, 174, 112, 76], [15, 360, 10, 200], [125, 85]),
      makeZone('hem', 'Lower Apron Skirt', ['front', 'back'], 160, 296, [106, 248, 108, 92], [15, 340, 10, 240], [120, 100]),
      makeZone('back_upper', 'Upper Back Tabard', ['back'], 160, 136, [106, 96, 108, 84], [15, 340, 10, 220], [120, 90]),
      makeZone('back_center', 'Center Back', ['back'], 160, 212, [104, 168, 112, 92], [15, 360, 10, 240], [125, 100]),
    ],
  },
  boots: {
    silhouette: 'boots',
    label: 'Safety Boots / Footwear',
    viewBox: WORKWEAR_STUDIO_VIEWBOX,
    garmentBounds: { x: 60, y: 116, width: 200, height: 204 },
    scale: {
      unitsPerMm: 0.32,
      referenceWidthMm: 320,
      referenceHeightMm: 360,
      scaleMode: 'proportional_template',
      scaleVerified: false,
    },
    colorRegions: [
      { key: 'upper', label: 'Boot Upper Leather / Shell', role: 'primary', slot: 0 },
      { key: 'sole_toecap', label: 'Outsole & Protective Toe Cap', role: 'contrast', slot: 1 },
    ],
    zones: [
      makeZone('thigh_left', 'Right Boot Upper Shaft', ['front', 'back'], 212, 186, [176, 120, 72, 136], [10, 180, 10, 260], [70, 120]),
      makeZone('thigh_right', 'Left Boot Upper Shaft', ['front', 'back'], 108, 186, [72, 120, 72, 136], [10, 180, 10, 260], [70, 120]),
      makeZone('knee_left', 'Right Boot Ankle / Heel', ['front', 'back'], 212, 268, [166, 232, 90, 76], [10, 200, 10, 160], [80, 75]),
      makeZone('knee_right', 'Left Boot Ankle / Heel', ['front', 'back'], 108, 268, [64, 232, 90, 76], [10, 200, 10, 160], [80, 75]),
      makeZone('hem', 'Boot Sole / Toe Tag', ['front', 'back'], 160, 304, [60, 276, 200, 44], [10, 300, 10, 100], [150, 50]),
    ],
  },
  gloves: {
    silhouette: 'gloves',
    label: 'Protective Gloves / Gauntlets',
    viewBox: WORKWEAR_STUDIO_VIEWBOX,
    garmentBounds: { x: 74, y: 88, width: 172, height: 206 },
    scale: {
      unitsPerMm: 0.35,
      referenceWidthMm: 260,
      referenceHeightMm: 320,
      scaleMode: 'proportional_template',
      scaleVerified: false,
    },
    colorRegions: [
      { key: 'palm_back', label: 'Glove Body & Fingers', role: 'primary', slot: 0 },
      { key: 'cuff_gauntlet', label: 'Wrist Cuff / Gauntlet', role: 'contrast', slot: 1 },
    ],
    zones: [
      makeZone('sleeve_left', 'Right Glove Back / Cuff', ['front', 'back'], 212, 220, [174, 150, 72, 144], [10, 160, 10, 240], [65, 110]),
      makeZone('sleeve_right', 'Left Glove Back / Cuff', ['front', 'back'], 108, 220, [74, 150, 72, 144], [10, 160, 10, 240], [65, 110]),
      makeZone('pocket_left', 'Right Wrist Cuff', ['front', 'back'], 212, 268, [180, 240, 64, 54], [10, 150, 10, 120], [60, 55]),
      makeZone('pocket_right', 'Left Wrist Cuff', ['front', 'back'], 108, 268, [76, 240, 64, 54], [10, 150, 10, 120], [60, 55]),
    ],
  },
  helmet: {
    silhouette: 'helmet',
    label: 'Safety Helmet / Headwear',
    viewBox: WORKWEAR_STUDIO_VIEWBOX,
    garmentBounds: { x: 56, y: 130, width: 208, height: 108 },
    scale: {
      unitsPerMm: 0.35,
      referenceWidthMm: 280,
      referenceHeightMm: 220,
      scaleMode: 'proportional_template',
      scaleVerified: false,
    },
    colorRegions: [
      { key: 'shell', label: 'Helmet Dome Shell', role: 'contrast', slot: 1 },
      { key: 'brim_ridge', label: 'Peak Brim & Center Crest', role: 'primary', slot: 0 },
    ],
    zones: [
      makeZone('chest_center', 'Front Forehead Badge', ['front'], 160, 188, [104, 142, 112, 82], [10, 220, 10, 160], [90, 75]),
      makeZone('collar', 'Front Brim / Crest', ['front', 'back'], 160, 210, [86, 160, 148, 76], [10, 260, 10, 150], [110, 70]),
      makeZone('shoulder_left', 'Left Side Shell', ['front', 'back'], 206, 194, [166, 150, 78, 74], [10, 180, 10, 140], [70, 65]),
      makeZone('shoulder_right', 'Right Side Shell', ['front', 'back'], 114, 194, [76, 150, 78, 74], [10, 180, 10, 140], [70, 65]),
      makeZone('back_upper', 'Rear Nape Shell', ['back'], 160, 194, [100, 146, 120, 82], [10, 220, 10, 160], [95, 75]),
    ],
  },
  glasses: {
    silhouette: 'glasses',
    label: 'Eye, Face, Hearing & Respiratory PPE',
    viewBox: WORKWEAR_STUDIO_VIEWBOX,
    garmentBounds: { x: 58, y: 156, width: 204, height: 56 },
    scale: {
      unitsPerMm: 0.40,
      referenceWidthMm: 200,
      referenceHeightMm: 120,
      scaleMode: 'proportional_template',
      scaleVerified: false,
    },
    colorRegions: [
      { key: 'frame_lens', label: 'Lens & Frame Assembly', role: 'primary', slot: 0 },
      { key: 'bridge_strap', label: 'Nose Bridge & Temple Accent', role: 'contrast', slot: 1 },
    ],
    zones: [
      makeZone('chest_center', 'Center Bridge / Frame Marking', ['front', 'back'], 160, 182, [80, 154, 160, 58], [10, 220, 8, 110], [110, 55]),
      makeZone('shoulder_left', 'Left Lens / Temple Arm', ['front', 'back'], 216, 184, [170, 156, 92, 56], [10, 160, 8, 100], [75, 50]),
      makeZone('shoulder_right', 'Right Lens / Temple Arm', ['front', 'back'], 104, 184, [58, 156, 92, 56], [10, 160, 8, 100], [75, 50]),
    ],
  },
  harness: {
    silhouette: 'harness',
    label: 'Fall Arrest Harness / Belts',
    viewBox: WORKWEAR_STUDIO_VIEWBOX,
    garmentBounds: { x: 108, y: 64, width: 104, height: 240 },
    scale: {
      unitsPerMm: 0.26,
      referenceWidthMm: 500,
      referenceHeightMm: 900,
      scaleMode: 'proportional_template',
      scaleVerified: false,
    },
    colorRegions: [
      { key: 'webbing', label: 'Primary Load Webbing Straps', role: 'contrast', slot: 1 },
      { key: 'sub_pelvic_belt', label: 'Work Positioning Waist Belt', role: 'primary', slot: 0 },
    ],
    zones: [
      makeZone('chest_center', 'Sternal D-Ring / Chest Strap', ['front'], 160, 150, [112, 108, 96, 76], [10, 280, 10, 180], [95, 75]),
      makeZone('chest_left', 'Left Chest Webbing', ['front'], 182, 130, [154, 86, 54, 76], [10, 160, 10, 180], [60, 75]),
      makeZone('chest_right', 'Right Chest Webbing', ['front'], 138, 130, [112, 86, 54, 76], [10, 160, 10, 180], [60, 75]),
      makeZone('shoulder_left', 'Left Shoulder Webbing', ['front', 'back'], 190, 92, [162, 62, 50, 62], [10, 150, 10, 160], [55, 65]),
      makeZone('shoulder_right', 'Right Shoulder Webbing', ['front', 'back'], 130, 92, [108, 62, 50, 62], [10, 150, 10, 160], [55, 65]),
      makeZone('waist', 'Waist Positioning Belt', ['front', 'back'], 160, 192, [106, 168, 108, 48], [15, 360, 10, 140], [120, 60]),
      makeZone('thigh_left', 'Left Thigh Loop', ['front', 'back'], 188, 258, [158, 214, 54, 90], [10, 160, 10, 220], [60, 90]),
      makeZone('thigh_right', 'Right Thigh Loop', ['front', 'back'], 132, 258, [108, 214, 54, 90], [10, 160, 10, 220], [60, 90]),
      makeZone('back_upper', 'Dorsal D-Ring / Back Plate', ['back'], 160, 140, [112, 92, 96, 86], [15, 280, 10, 200], [95, 85]),
      makeZone('back_center', 'Rear Lumbar Belt', ['back'], 160, 192, [106, 162, 108, 60], [15, 360, 10, 160], [120, 70]),
    ],
  },
}

export function getStudioTemplate(silhouette = 'coverall') {
  return WORKWEAR_STUDIO_TEMPLATES[silhouette] || WORKWEAR_STUDIO_TEMPLATES.coverall
}

export function getTemplateZones(silhouette = 'coverall', view = null) {
  const template = getStudioTemplate(silhouette)
  const zones = template.zones || []
  if (!view) return zones

  return zones.filter(z => z.views.includes(view))
}

export function getTemplateColorRegions(silhouette = 'coverall') {
  return getStudioTemplate(silhouette).colorRegions || []
}

export function resolveModelColorRegions(colors = [], silhouette = 'coverall') {
  const template = getStudioTemplate(silhouette)
  const raw = Array.isArray(colors) ? colors.filter(c => typeof c === 'string' && c.trim() !== '') : []
  const positional = []
  const explicitRegions = {}

  for (const entry of raw) {
    if (entry.includes(':')) {
      const [rKey, rVal] = entry.split(':')
      if (rKey?.trim() && rVal?.trim()) {
        explicitRegions[rKey.trim()] = rVal.trim()
      }
    } else {
      positional.push(entry.trim())
    }
  }

  const primary = positional[0] || 'navy'
  const contrast = positional[1] || primary
  const resolved = {}

  for (const region of template.colorRegions) {
    if (explicitRegions[region.key]) {
      resolved[region.key] = explicitRegions[region.key]
    } else if (region.slot === 0) {
      resolved[region.key] = primary
    } else if (region.slot === 1) {
      resolved[region.key] = contrast
    } else {
      resolved[region.key] = region.defaultColor || 'silver'
    }
  }

  return resolved
}

export function setModelRegionColor(colors = [], silhouette = 'coverall', regionKey, colorValue) {
  const template = getStudioTemplate(silhouette)
  const raw = Array.isArray(colors) ? colors.filter(c => typeof c === 'string' && c.trim() !== '') : []
  const positional = []
  const explicitRegions = {}

  for (const entry of raw) {
    if (entry.includes(':')) {
      const [rKey, rVal] = entry.split(':')
      if (rKey?.trim() && rVal?.trim()) {
        explicitRegions[rKey.trim()] = rVal.trim()
      }
    } else {
      positional.push(entry.trim())
    }
  }

  if (!positional[0]) positional[0] = 'navy'
  if (!positional[1]) positional[1] = 'hi-vis-yellow'

  const regionDef = template.colorRegions.find(r => r.key === regionKey)
  if (regionDef && regionDef.slot === 0 && template.colorRegions.filter(r => r.slot === 0)[0]?.key === regionKey) {
    positional[0] = colorValue
    delete explicitRegions[regionKey]
  } else if (regionDef && regionDef.slot === 1 && template.colorRegions.filter(r => r.slot === 1)[0]?.key === regionKey) {
    positional[1] = colorValue
    delete explicitRegions[regionKey]
  } else if (regionKey) {
    explicitRegions[regionKey] = colorValue
  }

  const overrides = Object.entries(explicitRegions).map(([k, v]) => `${k}:${v}`)

  return [...positional.slice(0, 2), ...overrides]
}

export function computePlacementGeometry(placement = {}, silhouette = 'coverall') {
  const template = getStudioTemplate(silhouette)
  const unitsPerMm = template.scale.unitsPerMm
  const zoneMeta = resolveZoneMeta(placement.zone, silhouette)
  const typeMeta = resolvePlacementTypeMeta(placement.type)

  const widthMm = Number(placement.width_mm ?? typeMeta.defaultWidth ?? 80)
  const heightMm = Number(placement.height_mm ?? typeMeta.defaultHeight ?? 35)
  const offsetXMm = Number(placement.offset_x_mm ?? 0)
  const offsetYMm = Number(placement.offset_y_mm ?? 0)

  const w = Math.round(widthMm * unitsPerMm * 100) / 100
  const h = Math.round(heightMm * unitsPerMm * 100) / 100
  const ox = offsetXMm * unitsPerMm
  const oy = offsetYMm * unitsPerMm

  const anchorX = zoneMeta.anchor?.x ?? zoneMeta.x
  const anchorY = zoneMeta.anchor?.y ?? zoneMeta.y
  const cx = Math.round((anchorX + ox) * 100) / 100
  const cy = Math.round((anchorY + oy) * 100) / 100
  const x = Math.round((cx - w / 2) * 100) / 100
  const y = Math.round((cy - h / 2) * 100) / 100

  const safeBounds = zoneMeta.safeBounds || template.garmentBounds
  const epsilon = 0.5
  const withinSafeX = x >= safeBounds.x - epsilon && x + w <= safeBounds.x + safeBounds.width + epsilon
  const withinSafeY = y >= safeBounds.y - epsilon && y + h <= safeBounds.y + safeBounds.height + epsilon

  return {
    cx,
    cy,
    w,
    h,
    x,
    y,
    anchorX,
    anchorY,
    unitsPerMm,
    scaleVerified: Boolean(template.scale.scaleVerified),
    safeBounds,
    withinSafeX,
    withinSafeY,
    withinSafeBounds: withinSafeX && withinSafeY,
    zoneMeta,
  }
}

const ZONE_REMAP_FALLBACKS = {
  chest_left: ['pocket_left', 'thigh_left', 'chest_center', 'waist'],
  chest_right: ['pocket_right', 'thigh_right', 'chest_center', 'waist'],
  chest_center: ['waist', 'chest_left', 'pocket_left'],
  sleeve_left: ['shoulder_left', 'chest_left', 'pocket_left', 'thigh_left'],
  sleeve_right: ['shoulder_right', 'chest_right', 'pocket_right', 'thigh_right'],
  collar: ['shoulder_left', 'chest_center', 'waist'],
  thigh_left: ['pocket_left', 'chest_left', 'waist', 'hem'],
  thigh_right: ['pocket_right', 'chest_right', 'waist', 'hem'],
  knee_left: ['thigh_left', 'hem', 'pocket_left', 'waist'],
  knee_right: ['thigh_right', 'hem', 'pocket_right', 'waist'],
  back_upper: ['back_center', 'back_lower', 'waist'],
  back_center: ['back_upper', 'back_lower', 'waist'],
  back_lower: ['back_center', 'back_upper', 'waist', 'hem'],
}

export function normalizePlacementForTemplate(placement = {}, silhouette = 'coverall') {
  const template = getStudioTemplate(silhouette)
  const unitsPerMm = template.scale.unitsPerMm
  const zones = template.zones || []
  const zoneByKey = Object.fromEntries(zones.map(z => [z.value, z]))

  let zoneKey = placement.zone || 'chest_left'
  const preferredView = placement.view || 'front'

  if (!zoneByKey[zoneKey]) {
    const candidates = ZONE_REMAP_FALLBACKS[zoneKey] || []
    const matched = candidates.find(c => Boolean(zoneByKey[c]))
    if (matched) {
      zoneKey = matched
    } else {
      const viewZone = zones.find(z => z.views.includes(preferredView))

      zoneKey = viewZone ? viewZone.value : (zones[0]?.value || 'chest_left')
    }
  }

  const zoneDef = zoneByKey[zoneKey] || zones[0]
  let view = preferredView
  if (!zoneDef.views.includes(view)) {
    view = zoneDef.views[0] || 'front'
  }

  const typeMeta = resolvePlacementTypeMeta(placement.type)

  const rawW = placement.width_mm != null && placement.width_mm !== ''
    ? Number(placement.width_mm)
    : Number(typeMeta.defaultWidth || 80)

  const rawH = placement.height_mm != null && placement.height_mm !== ''
    ? Number(placement.height_mm)
    : Number(typeMeta.defaultHeight || 35)

  const maxAllowedW = Math.min(zoneDef.maxWidthMm, Math.floor(zoneDef.safeBounds.width / unitsPerMm))
  const maxAllowedH = Math.min(zoneDef.maxHeightMm, Math.floor(zoneDef.safeBounds.height / unitsPerMm))

  const widthMm = Math.max(zoneDef.minWidthMm, Math.min(maxAllowedW, rawW))
  const heightMm = Math.max(zoneDef.minHeightMm, Math.min(maxAllowedH, rawH))

  const svgW = widthMm * unitsPerMm
  const svgH = heightMm * unitsPerMm
  const safe = zoneDef.safeBounds
  const anchorX = zoneDef.anchor?.x ?? zoneDef.x
  const anchorY = zoneDef.anchor?.y ?? zoneDef.y

  const minOffsetXMm = (safe.x + svgW / 2 - anchorX) / unitsPerMm
  const maxOffsetXMm = (safe.x + safe.width - svgW / 2 - anchorX) / unitsPerMm
  const minOffsetYMm = (safe.y + svgH / 2 - anchorY) / unitsPerMm
  const maxOffsetYMm = (safe.y + safe.height - svgH / 2 - anchorY) / unitsPerMm

  const rawOx = Number(placement.offset_x_mm ?? 0)
  const rawOy = Number(placement.offset_y_mm ?? 0)

  let clampedOx = Math.max(-zoneDef.maxOffsetXMm, Math.min(zoneDef.maxOffsetXMm, rawOx))
  clampedOx = Math.max(minOffsetXMm, Math.min(maxOffsetXMm, clampedOx))

  let clampedOy = Math.max(-zoneDef.maxOffsetYMm, Math.min(zoneDef.maxOffsetYMm, rawOy))
  clampedOy = Math.max(minOffsetYMm, Math.min(maxOffsetYMm, clampedOy))

  return {
    ...placement,
    view,
    zone: zoneKey,
    width_mm: Math.round(widthMm * 100) / 100,
    height_mm: Math.round(heightMm * 100) / 100,
    offset_x_mm: Math.round(clampedOx * 100) / 100,
    offset_y_mm: Math.round(clampedOy * 100) / 100,
  }
}

export function clampPlacementToZoneBounds(placement = {}, silhouette = 'coverall') {
  return normalizePlacementForTemplate(placement, silhouette)
}

export function normalizeStudioPlacements(placements = [], silhouette = 'coverall') {
  if (!Array.isArray(placements)) return []

  return placements.map(p => normalizePlacementForTemplate(p, silhouette))
}

/**
 * Reactive composable for Workwear Studio constants with proper i18n support.
 */
export function useWorkwearStudioConstants() {
  const { t, te, locale } = useI18n()

  const isFr = computed(() => (locale.value || '').startsWith('fr'))

  const localizedText = (enText, frText) => {
    if (isFr.value && frText) return frText
    if (enText && te(enText)) return t(enText)

    return enText ? t(enText) : ''
  }

  const localizedSilhouettes = computed(() =>
    WORKWEAR_SILHOUETTES.map(s => ({
      ...s,
      title: localizedText(s.title, s.titleFr),
    })),
  )

  const localizedColorPalette = computed(() =>
    WORKWEAR_COLOR_PALETTE.map(c => ({
      ...c,
      label: localizedText(c.label, c.labelFr),
    })),
  )

  const localizedPlacementTypes = computed(() =>
    WORKWEAR_PLACEMENT_TYPES.map(p => ({
      ...p,
      title: localizedText(p.title, p.titleFr),
      defaultMethod: localizedText(p.defaultMethod, p.defaultMethodFr),
    })),
  )

  const localizedStandards = computed(() =>
    WORKWEAR_STANDARDS_CATALOG.map(std => ({
      ...std,
      title: localizedText(std.title, std.titleFr),
      category: localizedText(std.category, std.categoryFr),
    })),
  )

  const localizedCertifications = computed(() =>
    WORKWEAR_CERTIFICATIONS_PRESETS.map(cert => ({
      value: cert,
      title: typeof cert === 'string' ? localizedText(cert, null) : (cert.title || cert.value),
    })),
  )

  const localizedSilhouetteCatalog = computed(() =>
    WORKWEAR_SILHOUETTE_CATALOG.map(cat => ({
      ...cat,
      title: localizedText(cat.title, cat.titleFr),
      subcategories: (cat.subcategories || []).map(sub => ({
        ...sub,
        title: localizedText(sub.title, sub.titleFr),
        shortTitle: formatSubcategoryShortTitle(sub, locale.value),
        items: (sub.items || []).map(item => ({
          ...item,
          title: localizedText(item.title, item.titleFr),
          shortTitle: formatSilhouetteShortTitle(item, locale.value),
          specBadge: extractSilhouetteSpecBadge(item),
        })),
      })),
    })),
  )

  const getSubcategories = catValue => {
    const cat = localizedSilhouetteCatalog.value.find(c => c.value === catValue)

    return cat ? cat.subcategories : []
  }

  const getItems = (catValue, subValue) => {
    const subs = getSubcategories(catValue)
    const sub = subs.find(s => s.value === subValue)

    return sub ? sub.items : []
  }

  const resolveModelSilhouette = model => resolveSilhouetteDisplay(model, { locale: locale.value, t })

  const resolveColorName = colorValue => {
    const found = localizedColorPalette.value.find(c => c.value === colorValue)

    return found ? found.label : colorValue
  }

  return {
    localizedSilhouettes,
    localizedSilhouetteCatalog,
    localizedColorPalette,
    localizedPlacementTypes,
    localizedStandards,
    localizedCertifications,
    getSubcategories,
    getItems,
    resolveModelSilhouette,
    resolveColorName,
    resolveColorHex,
    resolveZoneMeta,
    resolvePlacementTypeMeta,
    getStudioTemplate,
    getTemplateZones,
    getTemplateColorRegions,
    resolveModelColorRegions,
    setModelRegionColor,
    computePlacementGeometry,
    clampPlacementToZoneBounds,
    normalizePlacementForTemplate,
    normalizeStudioPlacements,
    normalizeSilhouetteTaxonomy,
    formatSilhouetteShortTitle,
    formatSubcategoryShortTitle,
    extractSilhouetteSpecBadge,
    extractCatalogColorsAndSizes,
    WORKWEAR_STUDIO_VIEWBOX,
    WORKWEAR_STUDIO_TEMPLATES,
    WORKWEAR_SILHOUETTES,
    WORKWEAR_SILHOUETTE_CATALOG,
    WORKWEAR_COLOR_PALETTE,
    WORKWEAR_PLACEMENT_TYPES,
    WORKWEAR_APPLICATION_METHODS,
    WORKWEAR_STANDARD_SIZES,
    WORKWEAR_STANDARDS_CATALOG,
    WORKWEAR_CERTIFICATIONS_PRESETS,
    WORKWEAR_TECHNICAL_CONSTRUCTION_PRESETS,
    WORKWEAR_FIT_VARIATIONS,
    WORKWEAR_CUSTOM_CONSTRUCTION_PRESETS,
    WORKWEAR_PLACEMENT_ZONES,
  }
}
