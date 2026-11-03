/**
 * Catalog module constants — ENUM option arrays and table headers.
 * Matches the backend ENUM values from the migration files.
 */

// ── Item Types ──────────────────────────────────────────────────────────
export const getCatalogItemTypes = () => [
  { value: 'product', title: 'Product' },
  { value: 'consumable', title: 'Consumable' },
  { value: 'eq_servicing', title: 'Equipment Servicing' },
]

// ── Item Statuses ───────────────────────────────────────────────────────
export const getCatalogItemStatuses = () => [
  { value: 'active', title: 'Active' },
  { value: 'draft', title: 'Draft' },
  { value: 'discontinued', title: 'Discontinued' },
]

// ── Tax Classes ─────────────────────────────────────────────────────────
export const getTaxClasses = () => [
  { value: 'standard_19pct', title: 'Standard 19%' },
  { value: 'reduced_7pct', title: 'Reduced 7%' },
  { value: 'exempt', title: 'Exempt' },
]

// ── Product Sub-Types ───────────────────────────────────────────────────
export const getProductTypes = () => [
  { value: 'physical', title: 'Physical' },
  { value: 'digital', title: 'Digital' },
  { value: 'service', title: 'Service' },
]

export const getPhysicalTypes = () => [
  { value: 'extinguisher', title: 'Extinguisher' },
  { value: 'wheeled_extinguisher', title: 'Wheeled Extinguisher' },
  { value: 'detector', title: 'Detector' },
  { value: 'detection_panel', title: 'Detection Panel' },
  { value: 'suppression_system', title: 'Suppression System' },
  { value: 'hose_reel', title: 'Hose Reel' },
  { value: 'fire_blanket', title: 'Fire Blanket' },
  { value: 'sprinkler_head', title: 'Sprinkler Head' },
  { value: 'alarm_device', title: 'Alarm Device' },
  { value: 'ppe', title: 'PPE' },
  { value: 'cabinet', title: 'Cabinet' },
  { value: 'other', title: 'Other' },
]

export const getAgentTypes = () => [
  { value: 'co2', title: 'CO₂' },
  { value: 'powder_abc', title: 'Powder ABC' },
  { value: 'powder_bc', title: 'Powder BC' },
  { value: 'powder_d', title: 'Powder D' },
  { value: 'foam_afff', title: 'Foam AFFF' },
  { value: 'foam_fffp', title: 'Foam FFFP' },
  { value: 'water_mist', title: 'Water Mist' },
  { value: 'clean_agent', title: 'Clean Agent' },
  { value: 'water_additive', title: 'Water Additive' },
  { value: 'none', title: 'None' },
]

// ── Digital Product Types ───────────────────────────────────────────────
export const getDigitalTypes = () => [
  { value: 'compliance_certificate', title: 'Compliance Certificate' },
  { value: 'compliance_report', title: 'Compliance Report' },
  { value: 'inspection_report', title: 'Inspection Report' },
  { value: 'training_material', title: 'Training Material' },
  { value: 'training_video', title: 'Training Video' },
  { value: 'software_license', title: 'Software License' },
  { value: 'module_subscription', title: 'Module Subscription' },
  { value: 'other', title: 'Other' },
]

export const getDeliveryMethods = () => [
  { value: 'auto_generated', title: 'Auto Generated' },
  { value: 'portal_download', title: 'Portal Download' },
  { value: 'email_attachment', title: 'Email Attachment' },
]

// ── Service Types ───────────────────────────────────────────────────────
export const getServiceTypes = () => [
  { value: 'training', title: 'Training' },
  { value: 'safety_audit', title: 'Safety Audit' },
  { value: 'consultation', title: 'Consultation' },
  { value: 'emergency_response', title: 'Emergency Response' },
  { value: 'installation_project', title: 'Installation Project' },
  { value: 'other', title: 'Other' },
]

export const getBillableUnitsProduct = () => [
  { value: 'per_session', title: 'Per Session' },
  { value: 'per_hour', title: 'Per Hour' },
  { value: 'per_day', title: 'Per Day' },
  { value: 'per_action', title: 'Per Action' },
  { value: 'flat_fee', title: 'Flat Fee' },
]

// ── Consumable Types ────────────────────────────────────────────────────
export const getConsumableTypes = () => [
  { value: 'agents_and_disposables', title: 'Agents & Disposables' },
  { value: 'replacement_parts', title: 'Replacement Parts' },
  { value: 'safety_signage', title: 'Safety Signage' },
  { value: 'accessories', title: 'Accessories' },
]

// ── Equipment Servicing Types ───────────────────────────────────────────
export const getEqServicingTypes = () => [
  { value: 'inspection_and_testing', title: 'Inspection & Testing' },
  { value: 'maintenance_and_repair', title: 'Maintenance & Repair' },
  { value: 'refill_recharge', title: 'Refill / Recharge' },
  { value: 'calibration_and_certification', title: 'Calibration & Certification' },
  { value: 'installation_decommissioning', title: 'Installation / Decommissioning' },
]

export const getCategorySubtypeOptions = () => [
  { value: 'physical', title: 'Product: Physical', itemType: 'product' },
  { value: 'digital', title: 'Product: Digital', itemType: 'product' },
  { value: 'service', title: 'Product: Service', itemType: 'product' },
  { value: 'agents_and_disposables', title: 'Consumable: Agents & Disposables', itemType: 'consumable' },
  { value: 'replacement_parts', title: 'Consumable: Replacement Parts', itemType: 'consumable' },
  { value: 'safety_signage', title: 'Consumable: Safety Signage', itemType: 'consumable' },
  { value: 'accessories', title: 'Consumable: Accessories', itemType: 'consumable' },
  { value: 'inspection_and_testing', title: 'Equipment Servicing: Inspection & Testing', itemType: 'eq_servicing' },
  { value: 'maintenance_and_repair', title: 'Equipment Servicing: Maintenance & Repair', itemType: 'eq_servicing' },
  { value: 'refill_recharge', title: 'Equipment Servicing: Refill / Recharge', itemType: 'eq_servicing' },
  { value: 'calibration_and_certification', title: 'Equipment Servicing: Calibration & Certification', itemType: 'eq_servicing' },
  { value: 'installation_decommissioning', title: 'Equipment Servicing: Installation / Decommissioning', itemType: 'eq_servicing' },
]

export const getServicingDeliveryModes = () => [
  { value: 'on_site', title: 'On Site' },
  { value: 'workshop', title: 'Workshop' },
  { value: 'hybrid', title: 'Hybrid' },
]

export const getBillableUnitsServicing = () => [
  { value: 'per_unit', title: 'Per Unit' },
  { value: 'per_session', title: 'Per Session' },
  { value: 'per_site', title: 'Per Site' },
  { value: 'flat_fee', title: 'Flat Fee' },
]

// ── UOM Types ───────────────────────────────────────────────────────────
export const getUomTypes = () => [
  { value: 'count', title: 'Count' },
  { value: 'mass', title: 'Mass' },
  { value: 'volume', title: 'Volume' },
  { value: 'length', title: 'Length' },
  { value: 'service', title: 'Service' },
]

// ── Equipment Class (Blueprints) ────────────────────────────────────────
export const getEquipmentClasses = () => [
  { value: 'portable_extinguisher', title: 'Portable Extinguisher' },
  { value: 'wheeled_extinguisher', title: 'Wheeled Extinguisher' },
  { value: 'fixed_suppression_system', title: 'Fixed Suppression System' },
  { value: 'detection_panel', title: 'Detection Panel' },
  { value: 'detector', title: 'Detector' },
  { value: 'hose_reel', title: 'Hose Reel' },
  { value: 'fire_blanket', title: 'Fire Blanket' },
  { value: 'other', title: 'Other' },
]

// ── Blueprint Statuses ──────────────────────────────────────────────────
export const getBlueprintStatuses = () => [
  { value: 'active', title: 'Active' },
  { value: 'draft', title: 'Draft' },
  { value: 'inactive', title: 'Inactive' },
]

// ── Intervention Types (Blueprint Cycles) ───────────────────────────────
export const getInterventionTypes = () => [
  { value: 'inspection', title: 'Inspection' },
  { value: 'refill', title: 'Refill / Recharge' },
  { value: 'hydro_test', title: 'Hydro Test' },
  { value: 'maintenance', title: 'Maintenance' },
  { value: 'calibration', title: 'Calibration' },
  { value: 'replacement', title: 'Replacement' },
]

// ── Calculation Methods (Blueprint Servicing Consumables) ───────────────
export const getCalculationMethods = () => [
  { value: 'fixed',    title: 'Fixed Quantity' },
  { value: 'multiply', title: 'Option Value × Parameter' },
  { value: 'add',      title: 'Option Value + Parameter' },
  { value: 'subtract', title: 'Option Value − Parameter' },
  { value: 'divide',   title: 'Option Value ÷ Parameter' },
]

// ── Checklist Step Types ────────────────────────────────────────────────
export const getStepTypes = () => [
  { value: 'boolean', title: 'Yes / No' },
  { value: 'measure', title: 'Measurement' },
  { value: 'text', title: 'Text' },
  { value: 'photo', title: 'Photo' },
]

// ── Variant Statuses ────────────────────────────────────────────────────
export const getVariantStatuses = () => [
  { value: 'active', title: 'Active' },
  { value: 'inactive', title: 'Inactive' },
]

// ══════════════════════════════════════════════════════════════
//  TABLE HEADERS
// ══════════════════════════════════════════════════════════════

export const getCatalogItemTableHeaders = () => [
  { title: 'Code', key: 'code', sortable: true },
  { title: 'Name', key: 'name', sortable: false },
  { title: 'Type', key: 'item_type', sortable: true },
  { title: 'Subtype', key: 'sub_type', sortable: false },
  { title: 'Category', key: 'category', sortable: false },
  { title: 'Price', key: 'price_range', sortable: true },
  { title: 'Stock', key: 'current_stock', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' },
]

export const getBlueprintTableHeaders = () => [
  { title: 'Code', key: 'code', sortable: true },
  { title: 'Name', key: 'name', sortable: false },
  { title: 'Equipment Class', key: 'equipment_class', sortable: true },
  { title: 'Shelf Life', key: 'shelf_life_years', sortable: false },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' },
]

// ── Guideline Step Types ────────────────────────────────────────────────
export const getGuidelineStepTypes = () => [
  { value: 'boolean', title: 'Yes / No' },
  { value: 'numeric', title: 'Numeric' },
  { value: 'text', title: 'Text' },
]

// ── Guideline Table Headers ─────────────────────────────────────────────
export const getGuidelineTableHeaders = () => [
  { title: 'Code', key: 'code', sortable: true },
  { title: 'Name', key: 'name', sortable: false },
  { title: 'Steps', key: 'steps_count', sortable: true },
  { title: 'Status', key: 'is_active', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' },
]
