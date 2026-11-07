/* eslint-disable camelcase */
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCatalogItemStore } from '@/stores/useCatalogItemStore'
import { useSupplierStore } from '@/stores/useSupplierStore'
import { useInventoryStore } from '@/stores/useInventoryStore'
import { useUserStore } from '@/stores/useUserStore'
import { localizedValue } from '@/composables/useLocaleField'
import {
  resolveStatusVariant,
  resolveStatusIcon,
  resolveUrgencyVariant,
  resolveUrgencyIcon,
} from '@/utils/procurement-constants'

/**
 * Shared helpers, lookups, catalog fetchers, and localized option lists for
 * Supply Chain & Procurement (IPR, RFQ, PO, GRN, 3-Way Match, Dashboard).
 */
export function useProcurementHelpers() {
  const { t, locale } = useI18n()
  const catalogStore = useCatalogItemStore()
  const supplierStore = useSupplierStore()
  const inventoryStore = useInventoryStore()
  const userStore = useUserStore()

  const userDataCookie = useCookie('userData')
  const currentUser = computed(() => userDataCookie.value || null)
  const currentUserId = computed(() => currentUser.value?.id || null)

  const loadingLookups = ref(false)

  // ── Localized Option Lists (matching backend FormRequest validation) ──────
  const statusLabel = status => {
    if (!status) return '—'
    const key = `scp.status.${status}`
    const translated = t(key)

    return translated !== key ? translated : String(status).replace(/_/g, ' ')
  }

  const urgencyLabel = urgency => {
    if (!urgency) return '—'
    const key = `scp.urgency.${urgency}`
    const translated = t(key)

    return translated !== key ? translated : String(urgency)
  }

  const triggerSourceLabel = src => {
    if (!src) return '—'
    const key = `scp.ipr.trigger_sources.${src}`
    const translated = t(key)

    return translated !== key ? translated : String(src).replace(/_/g, ' ')
  }

  const paymentTermsLabel = term => {
    if (!term) return '—'
    const key = `scp.payment_terms.${term}`
    const translated = t(key)

    return translated !== key ? translated : String(term).replace(/_/g, ' ')
  }

  const iprStatusSelectOptions = computed(() => [
    { value: 'draft', title: statusLabel('draft') },
    { value: 'pending_approval', title: statusLabel('pending_approval') },
    { value: 'approved', title: statusLabel('approved') },
    { value: 'rejected', title: statusLabel('rejected') },
    { value: 'merged', title: statusLabel('merged') },
    { value: 'converted_to_po', title: statusLabel('converted_to_po') },
    { value: 'cancelled', title: statusLabel('cancelled') },
  ])

  const rfqStatusSelectOptions = computed(() => [
    { value: 'draft', title: statusLabel('draft') },
    { value: 'sent', title: statusLabel('sent') },
    { value: 'awaiting_responses', title: statusLabel('awaiting_responses') },
    { value: 'under_evaluation', title: statusLabel('under_evaluation') },
    { value: 'awarded', title: statusLabel('awarded') },
    { value: 'cancelled', title: statusLabel('cancelled') },
  ])

  const poStatusSelectOptions = computed(() => [
    { value: 'draft', title: statusLabel('draft') },
    { value: 'pending_approval', title: statusLabel('pending_approval') },
    { value: 'approved', title: statusLabel('approved') },
    { value: 'sent', title: statusLabel('sent') },
    { value: 'acknowledged', title: statusLabel('acknowledged') },
    { value: 'in_delivery', title: statusLabel('in_delivery') },
    { value: 'partially_received', title: statusLabel('partially_received') },
    { value: 'fully_received', title: statusLabel('fully_received') },
    { value: 'closed', title: statusLabel('closed') },
    { value: 'cancelled', title: statusLabel('cancelled') },
  ])

  const grnStatusSelectOptions = computed(() => [
    { value: 'draft', title: statusLabel('draft') },
    { value: 'under_qc', title: statusLabel('under_qc') },
    { value: 'confirmed', title: statusLabel('confirmed') },
    { value: 'partially_rejected', title: statusLabel('partially_rejected') },
    { value: 'fully_rejected', title: statusLabel('fully_rejected') },
    { value: 'stocked', title: statusLabel('stocked') },
  ])

  const urgencySelectOptions = computed(() => [
    { value: 'low', title: urgencyLabel('low') },
    { value: 'normal', title: urgencyLabel('normal') },
    { value: 'urgent', title: urgencyLabel('urgent') },
    { value: 'critical', title: urgencyLabel('critical') },
  ])

  // Matches StoreIprRequest: in:auto_stock_alert,auto_workshop,manual_user,planned_project
  const triggerSourceOptions = computed(() => [
    { value: 'manual_user', title: triggerSourceLabel('manual_user') },
    { value: 'auto_stock_alert', title: triggerSourceLabel('auto_stock_alert') },
    { value: 'auto_workshop', title: triggerSourceLabel('auto_workshop') },
    { value: 'planned_project', title: triggerSourceLabel('planned_project') },
  ])

  // Matches StorePurchaseOrderRequest: in:immediate,net_15,net_30,net_60,net_90,advance_100,split_50_50
  const poPaymentTermsOptions = computed(() => [
    { value: 'immediate', title: paymentTermsLabel('immediate') },
    { value: 'net_15', title: paymentTermsLabel('net_15') },
    { value: 'net_30', title: paymentTermsLabel('net_30') },
    { value: 'net_60', title: paymentTermsLabel('net_60') },
    { value: 'net_90', title: paymentTermsLabel('net_90') },
    { value: 'advance_100', title: paymentTermsLabel('advance_100') },
    { value: 'split_50_50', title: paymentTermsLabel('split_50_50') },
  ])

  // Matches StoreRfqResponseRequest: in:net_7,net_15,net_30,net_45,net_60,net_90,cod,upfront
  const rfqPaymentTermsOptions = computed(() => [
    { value: 'cod', title: paymentTermsLabel('cod') },
    { value: 'upfront', title: paymentTermsLabel('upfront') },
    { value: 'net_7', title: paymentTermsLabel('net_7') },
    { value: 'net_15', title: paymentTermsLabel('net_15') },
    { value: 'net_30', title: paymentTermsLabel('net_30') },
    { value: 'net_45', title: paymentTermsLabel('net_45') },
    { value: 'net_60', title: paymentTermsLabel('net_60') },
    { value: 'net_90', title: paymentTermsLabel('net_90') },
  ])

  const incotermsOptions = computed(() => [
    { value: 'EXW', title: 'EXW — Ex Works' },
    { value: 'FCA', title: 'FCA — Free Carrier' },
    { value: 'CPT', title: 'CPT — Carriage Paid To' },
    { value: 'CIP', title: 'CIP — Carriage & Insurance Paid' },
    { value: 'DAP', title: 'DAP — Delivered at Place' },
    { value: 'DDP', title: 'DDP — Delivered Duty Paid' },
    { value: 'FOB', title: 'FOB — Free on Board' },
    { value: 'CIF', title: 'CIF — Cost, Insurance & Freight' },
  ])

  const currencySelectOptions = computed(() => [
    { value: 'TND', title: 'TND — Tunisian Dinar' },
    { value: 'EUR', title: 'EUR — Euro' },
    { value: 'USD', title: 'USD — US Dollar' },
  ])

  const qcResultOptions = computed(() => [
    { value: 'passed', title: t('scp.grn.qc_results.passed') },
    { value: 'passed_with_remarks', title: t('scp.grn.qc_results.passed_with_remarks') },
    { value: 'rejected', title: t('scp.grn.qc_results.rejected') },
  ])

  const overallQcResultOptions = computed(() => [
    { value: 'passed', title: t('scp.grn.qc_results.passed') },
    { value: 'passed_with_remarks', title: t('scp.grn.qc_results.passed_with_remarks') },
    { value: 'partially_rejected', title: statusLabel('partially_rejected') },
    { value: 'fully_rejected', title: statusLabel('fully_rejected') },
  ])

  // ── Supplier name helper ─────────────────────────────────────────────────
  /**
   * Resolve a human-readable display name from a SupplierResource object.
   * SupplierResource returns legal_name / commercial_name as translatable JSON
   * objects ({ en, fr, ar }) and uses supplier_id (not supplier_code).
   *
   * @param {object|null} supplier
   * @returns {string}
   */
  const resolveSupplierName = supplier => {
    if (!supplier) return '—'

    return (
      localizedValue(supplier.legal_name)
      || localizedValue(supplier.commercial_name)
      || supplier.name
      || supplier.supplier_id
      || '—'
    )
  }

  // ── Catalog Item Picker Adapter ──────────────────────────────────────────
  /**
   * Fetch catalog items for the picker, mapping CatalogItemResource fields.
   *
   * CatalogItemController uses Spatie QueryBuilder:
   *   - Allowed filters: filter[category_id], filter[item_type], filter[status]
   *   - Search: `q` or `search` param (NOT filter[search] — that causes HTTP 400)
   *   - Includes: uom, variants.optionValues, variants.stockItems, preferred_supplier
   *
   * @param {object} params
   * @returns {Promise<{ items: object[], total: number }>}
   */
  const fetchCatalogItemsForPicker = async (params = {}) => {
    const query = {
      per_page: params.per_page || 20,
      page: params.page || 1,
      include: 'uom,variants.optionValues,variants.stockItems,preferred_supplier',
    }

    if (params.q) query.q = params.q
    if (params.category) query['filter[category_id]'] = params.category
    if (params.item_type) query['filter[item_type]'] = params.item_type

    const res = await catalogStore.fetchItems(query)
    const rawItems = Array.isArray(res?.data) ? res.data : catalogStore.items

    const items = rawItems.map(item => {
      const defaultVariant = item.variants?.find(v => v.is_default) || item.variants?.[0] || null

      return {
        ...item,
        id: item.id,
        catalog_item_id: item.id,
        name: item.name,
        code: item.code || '',
        item_name: localizedValue(item.name, item.code || ''),
        defaultVariant,
        variant_id: defaultVariant?.id || null,
        sku: defaultVariant?.sku || item.code || '',
        barcode: defaultVariant?.barcode || item.barcode || item.ean || '',
        image_url: item.image || '',
        unit: localizedValue(item.uom?.name, item.uom?.code || ''),
        unit_id: item.uom_id || item.uom?.id || null,
        unit_price: Number(defaultVariant?.cost_price ?? defaultVariant?.price ?? 0) || 0,
        tax_rate: Number(item.tax_rate?.rate ?? item.tax_rate_pct ?? 19) || 0,
        current_stock: Number(item.current_stock ?? 0) || 0,
        variants: item.variants || [],
      }
    })

    return {
      items,
      total: Number(res?.meta?.total ?? items.length),
    }
  }

  // ── Lookups (Users, Suppliers, Warehouses/Sites) ─────────────────────────

  /**
   * Ensure users are loaded. Falls back gracefully if the calling user does not
   * have access to /admin/users (403), and always includes currentUser in
   * userOptions so forms can resolve the current user's name.
   */
  const ensureUsers = async () => {
    if (userStore.users.length) return userStore.users
    try {
      await userStore.fetchUsers({ per_page: 100 })
    }
    catch {
      // Non-admin users may get 403 on /admin/users.
      // Fall back: attempt /bp/users via supplierStore.fetchUsers()
      try {
        const bpUsers = await supplierStore.fetchUsers()

        if (Array.isArray(bpUsers) && bpUsers.length) {
          userStore.users = bpUsers
        }
      }
      catch {
        // Silently ignore — currentUser will still be surfaced via userOptions
      }
    }

    return userStore.users
  }

  const ensureSuppliers = async () => {
    if (supplierStore.suppliers.length) return supplierStore.suppliers
    try {
      await supplierStore.fetchSuppliers({ per_page: 100 })
    }
    catch {
      // Fall back to lightweight options endpoint
      try {
        await supplierStore.fetchSupplierOptions({ limit: 150 })
      }
      catch {
        // Ignore if already handled
      }
    }

    return supplierStore.suppliers
  }

  /**
   * Ensure warehouse sites are loaded via `inventoryStore.fetchSites`.
   */
  const ensureWarehouses = async () => {
    if (inventoryStore.sites.length) return inventoryStore.sites
    try {
      await inventoryStore.fetchSites({ per_page: 100 })
    }
    catch {
      // Ignore
    }

    return inventoryStore.sites
  }

  // Alias: ensureSites is the same as ensureWarehouses
  const ensureSites = ensureWarehouses

  /**
   * Ensure zones and locations for a specific site are loaded.
   * GRN pages need valid WarehouseLocation IDs (grn_lines.storage_location_id)
   * filtered by WarehouseZone, not raw site_id.
   *
   * @param {number|string} siteId
   */
  const ensureSiteLocations = async siteId => {
    if (!siteId) return

    try {
      await Promise.all([
        inventoryStore.fetchZones({ site_id: siteId, per_page: 100 }),
        inventoryStore.fetchLocations({ site_id: siteId, per_page: 200 }),
      ])
    }
    catch {
      // Ignore — UI will show empty options
    }
  }

  // ── Computed option lists ────────────────────────────────────────────────

  const userOptions = computed(() => {
    const list = userStore.users.map(u => ({
      value: u.id,
      title: u.name || `${u.first_name || ''} ${u.last_name || ''}`.trim() || u.email || String(u.id),
      subtitle: u.email || '',
    }))

    if (currentUser.value?.id && !list.some(u => u.value === currentUser.value.id)) {
      list.unshift({
        value: currentUser.value.id,
        title: currentUser.value.name || currentUser.value.fullName || currentUser.value.email || 'Current User',
        subtitle: currentUser.value.email || '',
      })
    }

    return list
  })

  /**
   * Supplier options mapped from the real SupplierResource contract.
   * SupplierResource returns legal_name / commercial_name as translatable JSON,
   * supplier_id (not supplier_code), primary_email (not email).
   */
  const supplierOptions = computed(() =>
    (supplierStore.suppliers || []).map(s => ({
      value: s.id,
      title: resolveSupplierName(s),
      subtitle: s.supplier_id || s.primary_email || '',
      raw: s,
    })),
  )

  /**
   * Warehouse site options. useInventoryStore exposes `sites`, not `warehouses`.
   */
  const warehouseOptions = computed(() =>
    (inventoryStore.sites || []).map(w => ({
      value: w.id,
      title: localizedValue(w.name, w.code || String(w.id)),
      subtitle: w.code || '',
      raw: w,
    })),
  )

  // Alias: siteOptions = warehouseOptions
  const siteOptions = warehouseOptions

  /**
   * Zone options (optionally filtered by site_id from the loaded zones list).
   *
   * @param {number|string|null} [siteId]
   */
  const zoneOptions = computed(() =>
    (inventoryStore.zones || []).map(z => ({
      value: z.id,
      title: localizedValue(z.name, z.code || String(z.id)),
      subtitle: z.code || '',
      raw: z,
    })),
  )

  /**
   * Location options, optionally filtered by zone.
   *
   * @param {number|string|null} [zoneId]
   * @returns {object[]}
   */
  const locationOptions = (zoneId = null) => {
    const locations = inventoryStore.locations || []

    return locations
      .filter(l => zoneId == null || l.zone_id === zoneId || l.warehouse_zone_id === zoneId)
      .map(l => ({
        value: l.id,
        title: localizedValue(l.name, l.code || l.barcode || String(l.id)),
        subtitle: l.code || l.barcode || '',
        raw: l,
      }))
  }

  // ── Formatting helpers ──────────────────────────────────────────────────
  const formatDate = value => {
    if (!value) return '—'
    const d = new Date(value)
    if (Number.isNaN(d.getTime())) return String(value).slice(0, 10)

    return new Intl.DateTimeFormat(locale.value || 'fr', { dateStyle: 'medium' }).format(d)
  }

  const formatDateTime = value => {
    if (!value) return '—'
    const d = new Date(value)
    if (Number.isNaN(d.getTime())) return String(value)

    return new Intl.DateTimeFormat(locale.value || 'fr', { dateStyle: 'medium', timeStyle: 'short' }).format(d)
  }

  /**
   * Format a supplier (SupplierResource) for display in a card/header.
   * Maps supplier_id (not supplier_code), primary_email, primary_phone.
   */
  const formatPartnerForCard = partner => {
    if (!partner) return null

    return {
      id: partner.id,
      name: resolveSupplierName(partner),
      code: partner.supplier_id || partner.code || '',
      email: partner.primary_email || partner.email || partner.primary_contact?.email || '',
      phone: partner.primary_phone || partner.phone || partner.mobile || partner.primary_contact?.phone || '',
      tax_id: partner.tax_id || partner.matricule_fiscal || '',
      status: partner.status || 'active',
      raw: partner,
    }
  }

  /**
   * Resolve a human-readable item name from a document line.
   * Handles catalog_item.name (translatable JSON), variant, ad-hoc fields,
   * description snapshots, and plain SKU fallbacks.
   */
  const resolveLineItemName = line => {
    if (!line) return '—'
    if (line.item_name) return localizedValue(line.item_name, line.sku || line.ad_hoc_sku || '')
    if (line.catalog_item?.name) return localizedValue(line.catalog_item.name, line.catalog_item.code || line.catalog_item.sku || '')
    if (line.ad_hoc_name) return line.ad_hoc_name
    if (line.description_snapshot) return line.description_snapshot
    if (line.description_for_supplier) return line.description_for_supplier
    if (line.description) return line.description

    return line.sku || line.ad_hoc_sku || '—'
  }

  /**
   * Resolve the SKU/code for a document line.
   * Handles variant SKU, catalog_item code, ad-hoc SKU, supplier reference.
   */
  const resolveLineItemSku = line => {
    if (!line) return ''

    return (
      line.sku
      || line.variant?.sku
      || line.catalog_variant?.sku
      || line.catalog_item?.sku
      || line.catalog_item?.code
      || line.ad_hoc_sku
      || line.supplier_reference
      || ''
    )
  }

  return {
    currentUser,
    currentUserId,
    loadingLookups,
    statusLabel,
    urgencyLabel,
    triggerSourceLabel,
    paymentTermsLabel,
    paymentTermLabel: paymentTermsLabel,
    paymentTermsOptions: rfqPaymentTermsOptions,
    statusColor: resolveStatusVariant,
    urgencyColor: resolveUrgencyVariant,
    resolveStatusVariant,
    resolveStatusIcon,
    resolveUrgencyVariant,
    resolveUrgencyIcon,
    iprStatusSelectOptions,
    rfqStatusSelectOptions,
    poStatusSelectOptions,
    grnStatusSelectOptions,
    urgencySelectOptions,
    triggerSourceOptions,
    poPaymentTermsOptions,
    rfqPaymentTermsOptions,
    incotermsOptions,
    currencySelectOptions,
    qcResultOptions,
    overallQcResultOptions,
    resolveSupplierName,
    fetchCatalogItemsForPicker,
    ensureUsers,
    ensureSuppliers,
    ensureWarehouses,
    ensureSites,
    ensureSiteLocations,
    userOptions,
    supplierOptions,
    warehouseOptions,
    siteOptions,
    zoneOptions,
    locationOptions,
    formatDate,
    formatDateTime,
    formatPartnerForCard,
    resolveLineItemName,
    resolveLineItemSku,
  }
}
