import { getI18n } from '@/plugins/i18n'

const normalizeStatus = status => {
  if (!status || typeof status !== 'object') return status || ''

  return status.value || status.key || status.status || ''
}

const translate = key => {
  if (!key || typeof key !== 'string') return ''

  return getI18n().global.t(key)
}

/**
 * logistics-constants.js
 * ─────────────────────────────────────────────────────────────────────────────
 * Single source of truth for all status/maps used across the Logistics module.
 */

// ── DWO Canonical Statuses ──────────────────────────────────────────────────
export const DWO_STATUS_MAP = {
  draft: { color: 'secondary', icon: 'tabler-file-text', label: 'logistics.status.dwo.draft' },
  planned: { color: 'info', icon: 'tabler-calendar', label: 'logistics.status.dwo.planned' },
  ready_to_pick: { color: 'primary', icon: 'tabler-package', label: 'logistics.status.dwo.ready_to_pick' },
  picking: { color: 'info', icon: 'tabler-clipboard-list', label: 'logistics.status.dwo.picking' },
  ready_to_load: { color: 'warning', icon: 'tabler-truck-loading', label: 'logistics.status.dwo.ready_to_load' },
  loaded: { color: 'warning', icon: 'tabler-truck', label: 'logistics.status.dwo.loaded' },
  dispatched: { color: 'deep-orange', icon: 'tabler-send', label: 'logistics.status.dwo.dispatched' },
  in_transit: { color: 'purple', icon: 'tabler-map-pin', label: 'logistics.status.dwo.in_transit' },
  delivered: { color: 'success', icon: 'tabler-package-check', label: 'logistics.status.dwo.delivered' },
  partially_delivered: { color: 'warning', icon: 'tabler-package-off', label: 'logistics.status.dwo.partially_delivered' },
  delivery_failed: { color: 'error', icon: 'tabler-alert-circle', label: 'logistics.status.dwo.delivery_failed' },
  exception: { color: 'error', icon: 'tabler-alert-triangle', label: 'logistics.status.dwo.exception' },
  closed: { color: 'success', icon: 'tabler-circle-check', label: 'logistics.status.dwo.closed' },
  cancelled: { color: 'default', icon: 'tabler-ban', label: 'logistics.status.dwo.cancelled' },
}

export const getDwoStatusChip = status => {
  const statusKey = normalizeStatus(status)

  return {
    text: translate(DWO_STATUS_MAP[statusKey]?.label || statusKey),
    color: DWO_STATUS_MAP[statusKey]?.color || 'secondary',
    icon: DWO_STATUS_MAP[statusKey]?.icon || 'tabler-help',
  }
}

// ── Delivery Wave Statuses ──────────────────────────────────────────────────
export const WAVE_STATUS_MAP = {
  draft: { color: 'secondary', icon: 'tabler-file-text', label: 'logistics.status.wave.draft' },
  planned: { color: 'info', icon: 'tabler-calendar', label: 'logistics.status.wave.planned' },
  dispatched: { color: 'warning', icon: 'tabler-send', label: 'logistics.status.wave.dispatched' },
  completed: { color: 'success', icon: 'tabler-circle-check', label: 'logistics.status.wave.completed' },
  cancelled: { color: 'default', icon: 'tabler-ban', label: 'logistics.status.wave.cancelled' },
}

export const getWaveStatusChip = status => {
  const statusKey = normalizeStatus(status)

  return {
    text: translate(WAVE_STATUS_MAP[statusKey]?.label || statusKey),
    color: WAVE_STATUS_MAP[statusKey]?.color || 'secondary',
    icon: WAVE_STATUS_MAP[statusKey]?.icon || 'tabler-help',
  }
}

// ── Return Order Statuses ───────────────────────────────────────────────────
export const RETURN_STATUS_MAP = {
  draft: { color: 'secondary', icon: 'tabler-file-text', label: 'logistics.status.return.draft' },
  validated: { color: 'info', icon: 'tabler-check', label: 'logistics.status.return.validated' },
  processing: { color: 'warning', icon: 'tabler-settings', label: 'logistics.status.return.processing' },
  completed: { color: 'success', icon: 'tabler-check-circle', label: 'logistics.status.return.completed' },
  cancelled: { color: 'default', icon: 'tabler-ban', label: 'logistics.status.return.cancelled' },
}

export const getReturnStatusChip = status => {
  const statusKey = normalizeStatus(status)

  return {
    text: translate(RETURN_STATUS_MAP[statusKey]?.label || statusKey),
    color: RETURN_STATUS_MAP[statusKey]?.color || 'secondary',
    icon: RETURN_STATUS_MAP[statusKey]?.icon || 'tabler-help',
  }
}

// ── Delivery Note Statuses ──────────────────────────────────────────────────
export const DELIVERY_NOTE_STATUS_MAP = {
  draft: { color: 'secondary', icon: 'tabler-file-text', label: 'logistics.status.delivery_note.draft' },
  confirmed: { color: 'info', icon: 'tabler-check', label: 'logistics.status.delivery_note.confirmed' },
  signed: { color: 'success', icon: 'tabler-signature', label: 'logistics.status.delivery_note.signed' },
}

// ── Picking List Statuses ───────────────────────────────────────────────────
export const PICKING_LIST_STATUS_MAP = {
  pending: { color: 'secondary', icon: 'tabler-clock', label: 'logistics.status.picking.pending' },
  in_progress: { color: 'warning', icon: 'tabler-loader', label: 'logistics.status.picking.in_progress' },
  completed: { color: 'success', icon: 'tabler-check', label: 'logistics.status.picking.completed' },
  cancelled: { color: 'default', icon: 'tabler-ban', label: 'logistics.status.picking.cancelled' },
}

// ── Delivery Conditions ─────────────────────────────────────────────────────
export const CONDITION_MAP = {
  good: { color: 'success', label: 'Bon état' },
  damaged: { color: 'error', label: 'Endommagé' },
  partial: { color: 'warning', label: 'Partiel' },
  refused: { color: 'error', label: 'Refusé' },
}

// ── Exception Reasons ───────────────────────────────────────────────────────
export const EXCEPTION_REASONS = [
  { value: 'customer_unavailable', label: 'Client absent / inaccessible' },
  { value: 'customer_refused', label: 'Refusé par le client' },
  { value: 'damaged_goods', label: 'Marchandises endommagées' },
  { value: 'short_delivery', label: 'Quantité incomplète' },
  { value: 'wrong_item', label: 'Article non conforme' },
  { value: 'partial_acceptance', label: 'Acceptation partielle' },
  { value: 'address_problem', label: 'Adresse introuvable / incorrecte' },
  { value: 'vehicle_issue', label: 'Panne véhicule / aléa logistique' },
  { value: 'other', label: 'Autre motif' },
]

// ── Select Options (for filter dropdowns) ───────────────────────────────────
export const dwoStatusOptions = Object.entries(DWO_STATUS_MAP).map(([value, { label }]) => ({
  title: translate(label),
  value,
}))

export const waveStatusOptions = Object.entries(WAVE_STATUS_MAP).map(([value, { label }]) => ({
  title: translate(label),
  value,
}))

export const returnStatusOptions = Object.entries(RETURN_STATUS_MAP).map(([value, { label }]) => ({
  title: translate(label),
  value,
}))

export const deliveryNoteStatusOptions = Object.entries(DELIVERY_NOTE_STATUS_MAP).map(([value, { label }]) => ({
  title: translate(label),
  value,
}))

export const pickingListStatusOptions = Object.entries(PICKING_LIST_STATUS_MAP).map(([value, { label }]) => ({
  title: translate(label),
  value,
}))

// ── Resolvers ───────────────────────────────────────────────────────────────
export const resolveDwoStatusVariant = status => DWO_STATUS_MAP[normalizeStatus(status)]?.color || 'secondary'
export const resolveDwoStatusColor = resolveDwoStatusVariant
export const resolveDwoStatusIcon = status => DWO_STATUS_MAP[normalizeStatus(status)]?.icon || 'tabler-help'
export const resolveDwoStatusLabel = status => {
  const statusKey = normalizeStatus(status)

  return translate(DWO_STATUS_MAP[statusKey]?.label || statusKey)
}

export const resolveLogisticsStatusIcon = status => {
  const statusKey = normalizeStatus(status)

  return DWO_STATUS_MAP[statusKey]?.icon 
    || DELIVERY_NOTE_STATUS_MAP[statusKey]?.icon 
    || RETURN_STATUS_MAP[statusKey]?.icon 
    || PICKING_LIST_STATUS_MAP[statusKey]?.icon 
    || 'tabler-help'
}

export const resolveLogisticsStatusLabel = status => {
  const statusKey = normalizeStatus(status)

  const label = DWO_STATUS_MAP[statusKey]?.label
    || DELIVERY_NOTE_STATUS_MAP[statusKey]?.label
    || RETURN_STATUS_MAP[statusKey]?.label
    || PICKING_LIST_STATUS_MAP[statusKey]?.label
    || statusKey

  return translate(label)
}

export const resolveLogisticsStatusVariant = status => {
  const statusKey = normalizeStatus(status)

  return DWO_STATUS_MAP[statusKey]?.color 
    || DELIVERY_NOTE_STATUS_MAP[statusKey]?.color 
    || RETURN_STATUS_MAP[statusKey]?.color 
    || PICKING_LIST_STATUS_MAP[statusKey]?.color 
    || 'secondary'
}

export const priorityOptions = [
  { title: 'Normal', value: 'normal' },
  { title: 'Haute', value: 'high' },
  { title: 'Urgente', value: 'urgent' },
]

export const VEHICLE_TYPES = [
  { value: 'truck', label: 'Camion', icon: 'tabler-truck' },
  { value: 'van', label: 'Fourgonnette', icon: 'tabler-car' },
  { value: 'motorcycle', label: 'Moto', icon: 'tabler-motorbike' },
  { value: 'other', label: 'Autre', icon: 'tabler-steering-wheel' },
]

// ── Item Statuses ────────────────────────────────────────────────────────────
export const ITEM_STATUS_MAP = {
  pending: { color: 'secondary', label: 'logistics.status.item.pending', icon: 'tabler-clock' },
  picked: { color: 'info', label: 'logistics.status.item.picked', icon: 'tabler-clipboard-check' },
  loaded: { color: 'warning', label: 'logistics.status.item.loaded', icon: 'tabler-truck' },
  delivered: { color: 'success', label: 'logistics.status.item.delivered', icon: 'tabler-package-check' },
  failed: { color: 'error', label: 'logistics.status.item.failed', icon: 'tabler-alert-circle' },
  cancelled: { color: 'default', label: 'logistics.status.item.cancelled', icon: 'tabler-ban' },
}

export const getItemStatusChip = status => {
  const statusKey = normalizeStatus(status)

  return {
    text: translate(ITEM_STATUS_MAP[statusKey]?.label || statusKey),
    color: ITEM_STATUS_MAP[statusKey]?.color || 'secondary',
    icon: ITEM_STATUS_MAP[statusKey]?.icon || 'tabler-help',
  }
}

// ── Tracking Event Map ───────────────────────────────────────────────────────
export const TRACKING_EVENT_MAP = {
  created: { label: 'Création', icon: 'tabler-file-plus', color: 'secondary' },
  planned: { label: 'Planification', icon: 'tabler-calendar', color: 'info' },
  picking_started: { label: 'Picking démarré', icon: 'tabler-clipboard-list', color: 'info' },
  picking_completed: { label: 'Picking terminé', icon: 'tabler-clipboard-check', color: 'primary' },
  loaded: { label: 'Chargement véhicule', icon: 'tabler-truck-loading', color: 'warning' },
  dispatched: { label: 'Expédition tournée', icon: 'tabler-send', color: 'deep-orange' },
  arrived: { label: 'Arrivée sur site', icon: 'tabler-map-pin', color: 'purple' },
  delivered: { label: 'Livré', icon: 'tabler-package-check', color: 'success' },
  exception: { label: 'Incident signalé', icon: 'tabler-alert-triangle', color: 'error' },
  cancelled: { label: 'Annulé', icon: 'tabler-ban', color: 'default' },
}

// ── Compatibility Aliases ───────────────────────────────────────────────────
export const DN_STATUS_MAP = DELIVERY_NOTE_STATUS_MAP
export const dnStatusOptions = deliveryNoteStatusOptions
export const getDnStatusChip = status => ({
  text: translate(DELIVERY_NOTE_STATUS_MAP[normalizeStatus(status)]?.label || normalizeStatus(status)),
  color: DELIVERY_NOTE_STATUS_MAP[normalizeStatus(status)]?.color || 'secondary',
  icon: DELIVERY_NOTE_STATUS_MAP[normalizeStatus(status)]?.icon || 'tabler-help',
})

export const PICKING_STATUS_MAP = PICKING_LIST_STATUS_MAP
export const pickingStatusOptions = pickingListStatusOptions
export const getPickingStatusChip = status => ({
  text: translate(PICKING_LIST_STATUS_MAP[normalizeStatus(status)]?.label || normalizeStatus(status)),
  color: PICKING_LIST_STATUS_MAP[normalizeStatus(status)]?.color || 'secondary',
  icon: PICKING_LIST_STATUS_MAP[normalizeStatus(status)]?.icon || 'tabler-help',
})

export const conditionOptions = [
  { title: 'Bon état', value: 'good' },
  { title: 'Endommagé', value: 'damaged' },
  { title: 'Partiel', value: 'partial' },
  { title: 'Refusé', value: 'refused' },
]

export const stockDestinationOptions = [
  { title: 'Remise en stock', value: 'restock' },
  { title: 'Zone de quarantaine', value: 'quarantine' },
  { title: 'Rebut / Destruction', value: 'scrap' },
  { title: 'Retour fournisseur', value: 'vendor_return' },
]

export const returnReasonOptions = [
  { title: 'Produit défectueux / non fonctionnel', value: 'defective' },
  { title: 'Article non conforme à la commande', value: 'wrong_item' },
  { title: 'Marchandise endommagée durant transport', value: 'damaged_transport' },
  { title: 'Surplus ou erreur de quantité', value: 'surplus' },
  { title: 'Annulation ou refus client', value: 'client_refusal' },
  { title: 'Autre motif', value: 'other' },
]
