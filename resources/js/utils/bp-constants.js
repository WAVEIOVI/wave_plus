/**
 * Business Partners — Shared Constants & Dropdown Options
 *
 * Centralised source of truth for all dropdown/select options and table headers
 * used across the Business Partners module.  Every function returns fresh arrays
 * with i18n-translated titles so they stay reactive to locale changes.
 */

import { getI18n } from '@/plugins/i18n'

const i18n = { get global() { return getI18n().global } }

// ────────────────────────────────────────────────────────────────────────────
// CLIENT — Status / Category / Legal
// ────────────────────────────────────────────────────────────────────────────

export function getClientStatuses() {
  return [
    { title: i18n.global.t('clients.status.prospect'), value: 'prospect' },
    { title: i18n.global.t('clients.status.active'), value: 'active' },
    { title: i18n.global.t('clients.status.inactive'), value: 'inactive' },
    { title: i18n.global.t('clients.status.suspended'), value: 'suspended' },
    { title: i18n.global.t('clients.status.blacklisted'), value: 'blacklisted' },
    { title: i18n.global.t('clients.status.archived'), value: 'archived' },
  ]
}

export function getClientCategories() {
  return [
    { title: i18n.global.t('clients.category.unclassified'), value: 'unclassified' },
    { title: i18n.global.t('clients.category.individual'), value: 'individual' },
    { title: i18n.global.t('clients.category.standard'), value: 'standard' },
    { title: i18n.global.t('clients.category.enterprise'), value: 'enterprise' },
    { title: i18n.global.t('clients.category.premium'), value: 'premium' },
    { title: i18n.global.t('clients.category.group'), value: 'group' },
  ]
}

export function getLegalStatuses() {
  return [
    { title: i18n.global.t('SARL'), value: 'sarl' },
    { title: i18n.global.t('SA'), value: 'sa' },
    { title: i18n.global.t('SUARL'), value: 'suarl' },
    { title: i18n.global.t('SNC'), value: 'snc' },
    { title: i18n.global.t('Individual'), value: 'individual' },
    { title: i18n.global.t('Public Entity'), value: 'public_entity' },
    { title: i18n.global.t('Association'), value: 'association' },
    { title: i18n.global.t('NGO'), value: 'ngo' },
    { title: i18n.global.t('Other'), value: 'other' },
  ]
}

// ────────────────────────────────────────────────────────────────────────────
// COMPANY SIZE
// ────────────────────────────────────────────────────────────────────────────

export function getCompaniesSize() {
  return [
    { title: i18n.global.t('1-10 employees'), value: '1-10' },
    { title: i18n.global.t('11-50 employees'), value: '11-50' },
    { title: i18n.global.t('51-200 employees'), value: '51-200' },
    { title: i18n.global.t('201-500 employees'), value: '201-500' },
    { title: i18n.global.t('501-1000 employees'), value: '501-1000' },
    { title: i18n.global.t('1001-5000 employees'), value: '1001-5000' },
    { title: i18n.global.t('5001-10,000 employees'), value: '5001-10000' },
    { title: i18n.global.t('10,001+ employees'), value: '10001+' },
  ]
}

// ────────────────────────────────────────────────────────────────────────────
// INDUSTRIES
// ────────────────────────────────────────────────────────────────────────────

export function getBpIndustries() {
  return [
    { title: i18n.global.t("Agriculture"), value: "agriculture" },
    { title: i18n.global.t("Automotive"), value: "automotive" },
    { title: i18n.global.t("Banking And Finance"), value: "banking and finance" },
    { title: i18n.global.t("Construction"), value: "construction" },
    { title: i18n.global.t("Education"), value: "education" },
    { title: i18n.global.t("Energy"), value: "energy" },
    { title: i18n.global.t("Healthcare"), value: "healthcare" },
    { title: i18n.global.t("Hospitality And Tourism"), value: "hospitality and tourism" },
    { title: i18n.global.t("Information Technology (IT)"), value: "information technology" },
    { title: i18n.global.t("Manufacturing"), value: "manufacturing" },
    { title: i18n.global.t("Real Estate"), value: "real estate" },
    { title: i18n.global.t("Retail"), value: "retail" },
    { title: i18n.global.t("Telecommunications"), value: "telecommunications" },
    { title: i18n.global.t("Transportation And Logistics"), value: "transportation and logistics" },
    { title: i18n.global.t("Utilities"), value: "utilities" },
    { title: i18n.global.t("Wholesale Retail Trade"), value: "wholesale retail trade" },
    { title: i18n.global.t("Media And Entertainment"), value: "media and entertainment" },
    { title: i18n.global.t("Legal And Professional Services"), value: "legal and professional services" },
    { title: i18n.global.t("Aerospace"), value: "aerospace" },
    { title: i18n.global.t("Biotechnology"), value: "biotechnology" },
    { title: i18n.global.t("Chemicals"), value: "chemicals" },
    { title: i18n.global.t("Consulting"), value: "consulting" },
    { title: i18n.global.t("Consumer Goods"), value: "consumer goods" },
    { title: i18n.global.t("Defense"), value: "defense" },
    { title: i18n.global.t("Electronics"), value: "electronics" },
    { title: i18n.global.t("Environmental Services"), value: "environmental services" },
    { title: i18n.global.t("Food And Beverage"), value: "food and beverage" },
    { title: i18n.global.t("Insurance"), value: "insurance" },
    { title: i18n.global.t("Mining"), value: "mining" },
    { title: i18n.global.t("Pharmaceuticals"), value: "pharmaceuticals" },
    { title: i18n.global.t("Public Sector"), value: "public sector" },
    { title: i18n.global.t("Shipping"), value: "shipping" },
    { title: i18n.global.t("Textiles"), value: "textiles" },
    { title: i18n.global.t("Warehousing"), value: "warehousing" },
    { title: i18n.global.t("Oil & Gas"), value: "oil and gas" },
    { title: i18n.global.t("Heavy Machinery & Equipment"), value: "heavy machinery" },
    { title: i18n.global.t("Metallurgy & Steel"), value: "metallurgy and steel" },
    { title: i18n.global.t("Security & Surveillance"), value: "security and surveillance" },
    { title: i18n.global.t("Waste Management & Recycling"), value: "waste management" },
    { title: i18n.global.t("Marine & Maritime"), value: "maritime" },
    { title: i18n.global.t("Packaging & Printing"), value: "packaging and printing" },
    { title: i18n.global.t("Facilities Management"), value: "facilities management" },
    { title: i18n.global.t("Maintenance"), value: "maintenance" },
    { title: i18n.global.t("Technical Services"), value: "technical services" },
  ]
}

// ────────────────────────────────────────────────────────────────────────────
// DEPARTMENT ROLES  (grouped by department)
// ────────────────────────────────────────────────────────────────────────────

export function getDepartmentRoles() {
  return [
    {
      department: i18n.global.t('Sales & Business Development'),
      jobTitles: [
        { title: i18n.global.t('Account Manager'), value: 'account manager' },
        { title: i18n.global.t('Business Development Manager'), value: 'business Development manager' },
        { title: i18n.global.t('Sales Director'), value: 'sales director' },
        { title: i18n.global.t('Key Account Executive'), value: 'key account executive' },
        { title: i18n.global.t('Banking Relationship Manager'), value: 'banking relationship manager' },
        { title: i18n.global.t('Insurance Underwriter'), value: 'insurance underwriter' },
      ],
    },
    {
      department: i18n.global.t('Marketing & Communications'),
      jobTitles: [
        { title: i18n.global.t('Marketing Coordinator'), value: 'marketing coordinator' },
        { title: i18n.global.t('Product Manager'), value: 'product manager' },
        { title: i18n.global.t('E-Commerce Specialist'), value: 'e-commerce specialist' },
        { title: i18n.global.t('Digital Marketing Manager'), value: 'digital marketing manager' },
      ],
    },
    {
      department: i18n.global.t('Finance & Accounting'),
      jobTitles: [
        { title: i18n.global.t('Finance Controller'), value: 'finance controller' },
        { title: i18n.global.t('Chief Financial Officer (CFO)'), value: 'cfo' },
        { title: i18n.global.t('Risk Analyst'), value: 'risk analyst' },
        { title: i18n.global.t('Internal Auditor'), value: 'internal auditor' },
      ],
    },
    {
      department: i18n.global.t('Human Resources (HR)'),
      jobTitles: [
        { title: i18n.global.t('HR Business Partner'), value: 'hr business partner' },
        { title: i18n.global.t('Training & Development Manager'), value: 'training-development manager' },
        { title: i18n.global.t('Recruitment Specialist'), value: 'recruitment specialist' },
        { title: i18n.global.t('Diversity Officer'), value: 'diversity officer' },
      ],
    },
    {
      department: i18n.global.t('Information Technology (IT)'),
      jobTitles: [
        { title: i18n.global.t('IT Manager'), value: 'it manager' },
        { title: i18n.global.t('Chief Technology Officer (CTO)'), value: 'cto' },
        { title: i18n.global.t('Software Architect'), value: 'software architect' },
        { title: i18n.global.t('Technical Support Engineer'), value: 'technical support engineer' },
        { title: i18n.global.t('Telecom Engineer'), value: 'telecom engineer' },
        { title: i18n.global.t('Data Analyst'), value: 'data analyst' },
        { title: i18n.global.t('Cloud Solutions Architect'), value: 'cloud solutions architect' },
      ],
    },
    {
      department: i18n.global.t('Operations & Supply Chain'),
      jobTitles: [
        { title: i18n.global.t('Operations Manager'), value: 'operations manager' },
        { title: i18n.global.t('Chief Operating Officer (COO)'), value: 'Coo' },
        { title: i18n.global.t('Supply Chain Coordinator'), value: 'supply chain coordinator' },
        { title: i18n.global.t('Logistics Coordinator'), value: 'logistics coordinator' },
        { title: i18n.global.t('Manufacturing Supervisor'), value: 'manufacturing supervisor' },
        { title: i18n.global.t('Retail Store Manager'), value: 'retail store manager' },
      ],
    },
    {
      department: i18n.global.t('Legal & Compliance'),
      jobTitles: [
        { title: i18n.global.t('Legal Counsel'), value: 'legal counsel' },
        { title: i18n.global.t('Compliance Officer'), value: 'compliance officer' },
        { title: i18n.global.t('Regulatory Affairs Manager'), value: 'regulatory affairs manager' },
        { title: i18n.global.t('Corporate Governance Specialist'), value: 'corporate governance specialist' },
      ],
    },
    {
      department: i18n.global.t('Executive Leadership'),
      jobTitles: [
        { title: i18n.global.t('Chief Executive Officer (CEO)'), value: 'ceo' },
        { title: i18n.global.t('Board Member'), value: 'board member' },
        { title: i18n.global.t('Managing Director'), value: 'managing director' },
        { title: i18n.global.t('Regional Director'), value: 'regional director' },
      ],
    },
    {
      department: i18n.global.t('Healthcare & Pharmaceuticals'),
      jobTitles: [
        { title: i18n.global.t('Healthcare Administrator'), value: 'healthcare administrator' },
        { title: i18n.global.t('Pharmaceutical Liaison'), value: 'pharmaceutical liaison' },
        { title: i18n.global.t('Clinical Research Associate'), value: 'clinical research associate' },
        { title: i18n.global.t('Medical Affairs Director'), value: 'medical affairs director' },
      ],
    },
    {
      department: i18n.global.t('Engineering & Construction'),
      jobTitles: [
        { title: i18n.global.t('Construction Site Manager'), value: 'construction site manager' },
        { title: i18n.global.t('Industrial Engineer'), value: 'industrial engineer' },
        { title: i18n.global.t('Oil & Gas Field Operator'), value: 'oil and gas field operator' },
        { title: i18n.global.t('Quality Assurance Specialist'), value: 'quality assurance specialist' },
      ],
    },
    {
      department: i18n.global.t('Sustainability & ESG'),
      jobTitles: [
        { title: i18n.global.t('Sustainability Manager'), value: 'sustainability manager' },
        { title: i18n.global.t('ESG Reporting Specialist'), value: 'esg reporting specialist' },
        { title: i18n.global.t('Environmental Compliance Officer'), value: 'environmental compliance officer' },
      ],
    },
    {
      department: i18n.global.t('International Expansion'),
      jobTitles: [
        { title: i18n.global.t('Global Business Manager'), value: 'global business manager' },
        { title: i18n.global.t('Localization Specialist'), value: 'localization specialist' },
        { title: i18n.global.t('Export Compliance Coordinator'), value: 'export compliance coordinator' },
      ],
    },
    {
      department: i18n.global.t('Project Management'),
      jobTitles: [
        { title: i18n.global.t('Project Manager'), value: 'project manager' },
        { title: i18n.global.t('Program Director'), value: 'program director' },
        { title: i18n.global.t('Agile Scrum Master'), value: 'agile scrum master' },
      ],
    },
    {
      department: i18n.global.t('Customer Service'),
      jobTitles: [
        { title: i18n.global.t('Customer Success Manager'), value: 'customer success manager' },
        { title: i18n.global.t('Client Relations Specialist'), value: 'client relations specialist' },
        { title: i18n.global.t('Service Delivery Manager'), value: 'service delivery manager' },
      ],
    },
  ]
}

// ────────────────────────────────────────────────────────────────────────────
// DEPARTMENTS  (flat list)
// ────────────────────────────────────────────────────────────────────────────

export function getDepartments() {
  return [
    { title: i18n.global.t('Sales & Business Development'), value: 'sales' },
    { title: i18n.global.t('Information Technology (IT)'), value: 'it' },
    { title: i18n.global.t('Operations & Supply Chain'), value: 'operations' },
    { title: i18n.global.t('Marketing & Communications'), value: 'marketing' },
    { title: i18n.global.t('Finance & Accounting'), value: 'finance' },
    { title: i18n.global.t('Human Resources (HR)'), value: 'hr' },
    { title: i18n.global.t('Legal & Compliance'), value: 'legal' },
    { title: i18n.global.t('Executive Leadership'), value: 'executive' },
    { title: i18n.global.t('Healthcare & Pharmaceuticals'), value: 'healthcare' },
    { title: i18n.global.t('Engineering & Construction'), value: 'engineering' },
    { title: i18n.global.t('Sustainability & ESG'), value: 'sustainability' },
    { title: i18n.global.t('International Expansion'), value: 'international' },
    { title: i18n.global.t('Project Management'), value: 'project management' },
    { title: i18n.global.t('Customer Service'), value: 'customer service' },
  ]
}

// ────────────────────────────────────────────────────────────────────────────
// ADDRESS TYPES
// ────────────────────────────────────────────────────────────────────────────

export function getAddressTypes() {
  return [
    { title: i18n.global.t('Corporate Headquarters'), value: 'headquarters' },
    { title: i18n.global.t('Office / Administrative'), value: 'office' },
    { title: i18n.global.t('Branch Office'), value: 'branch' },
    { title: i18n.global.t('Billing Address'), value: 'billing' },
    { title: i18n.global.t('Shipping Address'), value: 'shipping' },
    { title: i18n.global.t('Factory / Plant'), value: 'factory' },
    { title: i18n.global.t('Workshop / Maintenance'), value: 'workshop' },
    { title: i18n.global.t('Heavy Industry / Utilities'), value: 'industrial' },
    { title: i18n.global.t('Construction Site'), value: 'construction_site' },
    { title: i18n.global.t('Warehouse / Depot'), value: 'warehouse' },
    { title: i18n.global.t('Fleet Depot / Terminal'), value: 'depot' },
    { title: i18n.global.t('Retail Store / Showroom'), value: 'retail' },
    { title: i18n.global.t('Hotel / Hospitality'), value: 'hotel' },
    { title: i18n.global.t('Restaurant / Food Service'), value: 'restaurant' },
    { title: i18n.global.t('Government / Public Institution'), value: 'government' },
    { title: i18n.global.t('Healthcare / Hospital'), value: 'hospital' },
    { title: i18n.global.t('Campus / School'), value: 'school' },
    { title: i18n.global.t('Data Center'), value: 'datacenter' },
    { title: i18n.global.t('Residential / Housing'), value: 'residential' },
    { title: i18n.global.t('Operational Site'), value: 'operational_site' },
    { title: i18n.global.t('Other Premises'), value: 'other' },
  ]
}

// ────────────────────────────────────────────────────────────────────────────
// FIRE STATIONS
// ────────────────────────────────────────────────────────────────────────────

export function getFireStations() {
  return [
    { title: i18n.global.t('Protection Civile Ech-Charguia: Ech-Charguia, Cité El Khadhra, Tunis Governorate. Phone: Not available.'), value: 'fire_station_tunis_01' },
    { title: i18n.global.t('Protection Civile Ben Arous: P6MG+Q49, A1, Ben Arous Governorate. Phone: +216 71 38 32 33.'), value: 'fire_station_ben_arous_01' },
    { title: i18n.global.t('Protection Civile SFAX : Avenue du Commandant Mohamed Béjaoui. Phone: +216 74 22 83 84.'), value: 'fire_station_sfax_01' },
    { title: i18n.global.t('Protection Civile SFAX Sakiet Ezzit: Rte de Tunis Km 10. Phone: +216 74 86 07 22.'), value: 'fire_station_sfax_02' },
    { title: i18n.global.t('Protection Civile SFAX Kerkennah: Ouled Bou Ali. Phone: +216 74 48 09 99.'), value: 'fire_station_sfax_03' },
    { title: i18n.global.t('Protection Civile Médenine: 8FQR+MV3, P19, Médenine Governorate. Phone: +216 75 64 04 81.'), value: 'fire_station_medenine_01' },
    { title: i18n.global.t('Protection Civile Djerba Midoun: Midoun, Djerba Midoun 4116, Médenine Governorate. Phone: +216 75 73 34 43.'), value: 'fire_station_medenine_02' },
    { title: i18n.global.t('Protection Civile Nabeul: Rue de Biélorussie, Nabeul 8000. Phone: +216 72 22 37 66.'), value: 'fire_station_nabeul_01' },
    { title: i18n.global.t('Protection Civile El Haouaria: Intersection MC 26 and MC 27, Al Huwariyah 8045. Phone: +216 72 26 95 75.'), value: 'fire_station_nabeul_02' },
    { title: i18n.global.t('Protection Civile Tataouine: XF48+GJ6, C111, Tataouine. Phone: +216 75 84 42 68.'), value: 'fire_station_tataouine_01' },
    { title: i18n.global.t('Protection Civile Jendouba: GQ46+GV6, P17, Jendouba 8100. Phone: +216 78 60 05 67.'), value: 'fire_station_jendouba_01' },
    { title: i18n.global.t('Protection Civile Ghardimaou: CCVR+36P, Ghardimaou. Phone: +216 78 66 22 00.'), value: 'fire_station_jendouba_02' },
    { title: i18n.global.t('Protection Civile Cebalat: V5RJ+5XM, Av. Fethi Zouhir - En Nekhilet, Cebalat. Phone: +216 23 84 34 56.'), value: 'fire_station_ariana_01' },
    { title: i18n.global.t('Protection Civile Monastir: QR6J+P3F, Monastir. Phone: +216 73 46 14 55.'), value: 'fire_station_monastir_01' },
    { title: i18n.global.t('Protection Civile Sousse: RJ6V+C7P, Sousse. Phone: +216 73 38 36 32.'), value: 'fire_station_sousse_01' },
    { title: i18n.global.t('Protection Civile La Manouba: Q3XJ+252, Manouba 2010. Phone: +216 71 60 06 04.'), value: 'fire_station_manouba_01' },
    { title: i18n.global.t('Protection Civile Bizerte: 7V95+5RQ, Rue Bourguiba, Bizerte. Phone: +216 72 43 10 22.'), value: 'fire_station_bizerte_01' },
    { title: i18n.global.t('Protection Civile Kairouan: M4C4+73H, Av. de Fès - Sidi Saad, Kairouan. Phone: +216 77 22 87 62.'), value: 'fire_station_kairouan_01' },
    { title: i18n.global.t('Protection Civile Kasserine: 5RHW+25X, P13, Kasserine. Phone: +216 77 47 44 90.'), value: 'fire_station_kasserine_01' },
    { title: i18n.global.t('Protection Civile Sbeïtla: 64MF+RFW, Sbeïtla. Phone: +216 71 00 01 98.'), value: 'fire_station_kasserine_02' },
    { title: i18n.global.t('Protection Civile Mahdia: G28G+XFF, C82, Hiboun. Phone: +216 73 67 05 51.'), value: 'fire_station_mahdia_01' },
    { title: i18n.global.t('Protection Civile Sidi Bouzid: 2FVX+84X, Bd Mohamed Bouazizi, Sidi Bouzid. Phone: +216 76 63 27 66.'), value: 'fire_station_sidi_bouzid_01' },
    { title: i18n.global.t('Protection Civile Regueb: VQ5Q+GGM, Regueb. Phone: Not available.'), value: 'fire_station_sidi_bouzid_02' },
    { title: i18n.global.t('Protection Civile Gaâfour: Gaâfour, Siliana. Phone: Not available.'), value: 'fire_station_siliana_01' },
    { title: i18n.global.t('Protection Civile Kébili: PX2M+MM2, Route Tourist, Kebili  Phone: +216 75 49 08 80.'), value: 'fire_station_kebili_01' },
    { title: i18n.global.t('Protection Civile Kef: 5M3R+MMG, Le Kef. Phone: Not available.'), value: 'fire_station_kef_01' },
    { title: i18n.global.t('Protection Civile Tozeur: PX2M+MM2, Route Tourist, Kebili. Phone: +216 75 49 08 80'), value: 'fire_station_tozeur_01' },
    { title: i18n.global.t('Protection Civile Zaghouan: C46X+WJJ, Zaghouan. Phone: +216 72 67 52 73.'), value: 'fire_station_zaghouan_01' },
  ]
}

// ────────────────────────────────────────────────────────────────────────────
// FINANCIAL — Payment Terms / Credit Limits / Currencies
// ────────────────────────────────────────────────────────────────────────────

export function getPaymentTerms() {
  return [
    { title: i18n.global.t('Immediate'), value: 'immediate' },
    { title: i18n.global.t('Net 15'), value: 'net_15' },
    { title: i18n.global.t('Net 30'), value: 'net_30' },
    { title: i18n.global.t('Net 45'), value: 'net_45' },
    { title: i18n.global.t('Net 60'), value: 'net_60' },
    { title: i18n.global.t('Net 90'), value: 'net_90' },
    { title: i18n.global.t('End of Month'), value: 'end_of_month' },
    { title: i18n.global.t('Cash on Delivery'), value: 'cod' },
    { title: i18n.global.t('Prepaid'), value: 'prepaid' },
    { title: i18n.global.t('Custom'), value: 'custom' },
  ]
}

export function getCreditLimits() {
  return [
    { title: i18n.global.t('0'), value: 0 },
    { title: i18n.global.t('5,000'), value: 5000 },
    { title: i18n.global.t('10,000'), value: 10000 },
    { title: i18n.global.t('25,000'), value: 25000 },
    { title: i18n.global.t('50,000'), value: 50000 },
    { title: i18n.global.t('100,000'), value: 100000 },
    { title: i18n.global.t('250,000'), value: 250000 },
    { title: i18n.global.t('500,000'), value: 500000 },
    { title: i18n.global.t('1,000,000'), value: 1000000 },
  ]
}

export function getCurrencies() {
  return [
    { title: i18n.global.t('TND — Tunisian Dinar'), value: 'TND' },
    { title: i18n.global.t('USD — US Dollar'), value: 'USD' },
    { title: i18n.global.t('EUR — Euro'), value: 'EUR' },
    { title: i18n.global.t('GBP — British Pound'), value: 'GBP' },
    { title: i18n.global.t('SAR — Saudi Riyal'), value: 'SAR' },
    { title: i18n.global.t('AED — UAE Dirham'), value: 'AED' },
    { title: i18n.global.t('QAR — Qatari Riyal'), value: 'QAR' },
    { title: i18n.global.t('KWD — Kuwaiti Dinar'), value: 'KWD' },
    { title: i18n.global.t('MAD — Moroccan Dirham'), value: 'MAD' },
    { title: i18n.global.t('DZD — Algerian Dinar'), value: 'DZD' },
    { title: i18n.global.t('LYD — Libyan Dinar'), value: 'LYD' },
    { title: i18n.global.t('EGP — Egyptian Pound'), value: 'EGP' },
    { title: i18n.global.t('CHF — Swiss Franc'), value: 'CHF' },
    { title: i18n.global.t('CAD — Canadian Dollar'), value: 'CAD' },
    { title: i18n.global.t('JPY — Japanese Yen'), value: 'JPY' },
    { title: i18n.global.t('CNY — Chinese Yuan'), value: 'CNY' },
    { title: i18n.global.t('TRY — Turkish Lira'), value: 'TRY' },
  ]
}

// ────────────────────────────────────────────────────────────────────────────
// CONTACT — Gender / Contact Status
// ────────────────────────────────────────────────────────────────────────────

export function getGenderOptions() {
  return [
    { title: i18n.global.t('Male'), value: 'male' },
    { title: i18n.global.t('Female'), value: 'female' },
  ]
}

export function getContactStatuses() {
  return [
    { title: i18n.global.t('Active'), value: 'active' },
    { title: i18n.global.t('Inactive'), value: 'inactive' },
  ]
}

// ────────────────────────────────────────────────────────────────────────────
// BANK ACCOUNT — Usage
// ────────────────────────────────────────────────────────────────────────────

export function getBankUsageOptions() {
  return [
    { title: i18n.global.t('Payments'), value: 'payments' },
    { title: i18n.global.t('Receipts'), value: 'receipts' },
    { title: i18n.global.t('Both'), value: 'both' },
  ]
}

// ────────────────────────────────────────────────────────────────────────────
// DOCUMENT — Type / Status
// ────────────────────────────────────────────────────────────────────────────

export function getDocumentTypes() {
  return [
    { title: i18n.global.t('Contract'), value: 'contract' },
    { title: i18n.global.t('NDA'), value: 'nda' },
    { title: i18n.global.t('Framework Agreement'), value: 'framework_agreement' },
    { title: i18n.global.t('Purchase Order'), value: 'purchase_order' },
    { title: i18n.global.t('Compliance Certificate'), value: 'compliance_certificate' },
    { title: i18n.global.t('Insurance Policy'), value: 'insurance_policy' },
    { title: i18n.global.t('Tax ID'), value: 'tax_id' },
    { title: i18n.global.t('RNE'), value: 'rne' },
    { title: i18n.global.t('Other'), value: 'other' },
  ]
}

export function getDocumentStatuses() {
  return [
    { title: i18n.global.t('Draft'), value: 'draft' },
    { title: i18n.global.t('Active'), value: 'active' },
    { title: i18n.global.t('Under Review'), value: 'under_review' },
    { title: i18n.global.t('Expired'), value: 'expired' },
    { title: i18n.global.t('Terminated'), value: 'terminated' },
  ]
}

// ────────────────────────────────────────────────────────────────────────────
// VAT CERTIFICATE — Status
// ────────────────────────────────────────────────────────────────────────────

export function getVatCertStatuses() {
  return [
    { title: i18n.global.t('Active'), value: 'active' },
    { title: i18n.global.t('Expired'), value: 'expired' },
    { title: i18n.global.t('Revoked'), value: 'revoked' },
    { title: i18n.global.t('Pending Renewal'), value: 'pending_renewal' },
  ]
}

// ────────────────────────────────────────────────────────────────────────────
// TABLE HEADERS — Client List
// ────────────────────────────────────────────────────────────────────────────

export function getSupplierTableHeaders() {
  return [
    { title: i18n.global.t('suppliers.list.header.supplier'), key: 'supplier' },
    { title: i18n.global.t('suppliers.list.header.contact_location'), key: 'contact_location', sortable: false },
    { title: i18n.global.t('suppliers.list.header.industry'), key: 'industry', sortable: false },
    { title: i18n.global.t('suppliers.list.header.category'), key: 'supplier_category' },
    { title: i18n.global.t('suppliers.list.header.status'), key: 'status' },
    { title: i18n.global.t('suppliers.list.header.actions'), key: 'actions', sortable: false },
  ]
}

// ────────────────────────────────────────────────────────────────────────────
// SUPPLIER — Status / Category / Legal
// ────────────────────────────────────────────────────────────────────────────

export function getSupplierCategories() {
  return [
    { title: i18n.global.t('suppliers.category.general'), value: 'general' },
    { title: i18n.global.t('suppliers.category.manufacturer'), value: 'manufacturer' },
    { title: i18n.global.t('suppliers.category.wholesaler'), value: 'wholesaler' },
    { title: i18n.global.t('suppliers.category.distributor'), value: 'distributor' },
  ]
}

// ────────────────────────────────────────────────────────────────────────────
// SERVICE PROVIDER — Tiers / Availability / Contracts
// ────────────────────────────────────────────────────────────────────────────

export function getProviderTiers() {
  return [
    { title: i18n.global.t('service_providers.tiers.standard'), value: 'standard' },
    { title: i18n.global.t('service_providers.tiers.preferred'), value: 'preferred' },
    { title: i18n.global.t('service_providers.tiers.certified_partner'), value: 'certified_partner' },
    { title: i18n.global.t('service_providers.tiers.tier_1'), value: 'tier_1' },
    { title: i18n.global.t('service_providers.tiers.tier_2'), value: 'tier_2' },
    { title: i18n.global.t('service_providers.tiers.tier_3'), value: 'tier_3' },
    { title: i18n.global.t('service_providers.tiers.backup'), value: 'backup' },
  ]
}

export function getAvailabilityStatuses() {
  return [
    { title: i18n.global.t('service_providers.availability.available'), value: 'available' },
    { title: i18n.global.t('service_providers.availability.partially_available'), value: 'partially_available' },
    { title: i18n.global.t('service_providers.availability.busy'), value: 'busy' },
    { title: i18n.global.t('service_providers.availability.unavailable'), value: 'unavailable' },
    { title: i18n.global.t('service_providers.availability.on_leave'), value: 'on_leave' },
  ]
}

export function getContractTypes() {
  return [
    { title: i18n.global.t('service_providers.contracts.freelance'), value: 'freelance' },
    { title: i18n.global.t('service_providers.contracts.full_time'), value: 'full_time' },
    { title: i18n.global.t('service_providers.contracts.part_time'), value: 'part_time' },
    { title: i18n.global.t('service_providers.contracts.contract'), value: 'contract' },
    { title: i18n.global.t('service_providers.contracts.retainer'), value: 'retainer' },
  ]
}

export function getServiceProviderTableHeaders() {
  return [
    { title: i18n.global.t('service_providers.list.header.provider'), key: 'service_provider' },
    { title: i18n.global.t('service_providers.list.header.contact_location'), key: 'contact_location', sortable: false },
    { title: i18n.global.t('service_providers.list.header.industry'), key: 'industry', sortable: false },
    { title: i18n.global.t('service_providers.list.header.tier'), key: 'provider_tier' },
    { title: i18n.global.t('service_providers.list.header.availability'), key: 'availability_status', sortable: false },
    { title: i18n.global.t('service_providers.list.header.status'), key: 'status' },
    { title: i18n.global.t('service_providers.list.header.actions'), key: 'actions', sortable: false },
  ]
}

// ────────────────────────────────────────────────────────────────────────────
// ORGANISATION — Status / Type
// ────────────────────────────────────────────────────────────────────────────

export function getOrganisationStatuses() {
  return [
    { title: i18n.global.t('organisations.status.prospect'), value: 'prospect' },
    { title: i18n.global.t('organisations.status.active'), value: 'active' },
    { title: i18n.global.t('organisations.status.inactive'), value: 'inactive' },
    { title: i18n.global.t('organisations.status.archived'), value: 'archived' },
  ]
}

export function getOrganisationTypes() {
  return [
    { title: i18n.global.t('organisations.type.government_agency'), value: 'government_agency' },
    { title: i18n.global.t('organisations.type.municipality'), value: 'municipality' },
    { title: i18n.global.t('organisations.type.public_institution'), value: 'public_institution' },
    { title: i18n.global.t('organisations.type.ngo'), value: 'ngo' },
    { title: i18n.global.t('organisations.type.association'), value: 'association' },
    { title: i18n.global.t('organisations.type.professional_body'), value: 'professional_body' },
    { title: i18n.global.t('organisations.type.trade_union'), value: 'trade_union' },
    { title: i18n.global.t('organisations.type.international_org'), value: 'international_org' },
    { title: i18n.global.t('organisations.type.other'), value: 'other' },
  ]
}

export function getOrganisationScopes() {
  return [
    { title: i18n.global.t('organisations.scope.local'), value: 'local' },
    { title: i18n.global.t('organisations.scope.regional'), value: 'regional' },
    { title: i18n.global.t('organisations.scope.national'), value: 'national' },
    { title: i18n.global.t('organisations.scope.international'), value: 'international' },
  ]
}

// ────────────────────────────────────────────────────────────────────────────
// TABLE HEADERS — Organisation List
// ────────────────────────────────────────────────────────────────────────────

export function getOrganisationTableHeaders() {
  return [
    { title: i18n.global.t('organisations.list.header.organisation'), key: 'organisation' },
    { title: i18n.global.t('organisations.list.header.contact_location'), key: 'contact_location', sortable: false },
    { title: i18n.global.t('organisations.list.header.organisation_type'), key: 'organisation_type' },
    { title: i18n.global.t('organisations.list.header.member_count'), key: 'member_count', sortable: false },
    { title: i18n.global.t('organisations.list.header.status'), key: 'status' },
    { title: i18n.global.t('organisations.list.header.actions'), key: 'actions', sortable: false },
  ]
}

// ────────────────────────────────────────────────────────────────────────────
// TABLE HEADERS — Client List
//
// The `sortField` property decouples the UI column key from the backend sort
// parameter. The UI column key ('client') is for slot naming and display,
// while `sortField` ('legal_name') is what gets sent to the API.
// If `sortField` is omitted, the `key` is used as-is.
// ────────────────────────────────────────────────────────────────────────────

export function getClientTableHeaders() {
  return [
    {
      title: i18n.global.t('clients.list.header.client'),
      key: 'client',
      sortable: true,
      sortField: 'legal_name', // Backend allowed sort: legal_name (not 'client')
    },
    {
      title: i18n.global.t('clients.list.header.contact_location'),
      key: 'contact_location',
      sortable: false,
    },
    {
      title: i18n.global.t('clients.list.header.industry'),
      key: 'industry',
      sortable: false,
    },
    {
      title: i18n.global.t('clients.list.header.category'),
      key: 'client_category',
      sortable: true,
    },
    {
      title: i18n.global.t('clients.list.header.status'),
      key: 'status',
      sortable: true,
    },
    {
      title: i18n.global.t('clients.list.header.actions'),
      key: 'actions',
      sortable: false,
    },
  ]
}
