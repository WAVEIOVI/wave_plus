<script setup>
/* eslint-disable lines-around-comment, vue/custom-event-name-casing */
/**
 * WpAddressSiteCard
 * ─────────────────────────────────────────────────────────────────────────────
 * Presentation of one selected address and the site(s) linked to it. Business
 * data stays outside: pass the objects, map their fields, pass a
 * `search-config` for the shared Search & Select dialog (or handle
 * `request-select` yourself).
 *
 * <WpAddressSiteCard
 *   :address="order.deliveryAddress"
 *   :sites="order.deliveryAddress?.sites"
 *   :search-config="addressPicker"        // props of WpSearchSelectDialog (fetcher, columns, display…)
 *   :can-edit="$can('update', 'Addresses')"
 *   mode="compact"
 *   @change="address => order.deliveryAddress = address"
 *   @view="openAddress" @edit="editAddress"
 * />
 *
 * Props   address · sites · fields · siteFields · title · mode ('compact'|'expanded') · expandable
 *         loading · searchConfig · canChange · canView · canEdit · readonly · showSites · format
 *         locked · lockedReason · maxSites · copyable · showMapLink · mapUrl
 * Emits   change(address) · request-select · view(address) · edit(address) · site-click(site)
 * Slots   address-extra · site({ site }) · actions · (empty-action via WpStateBlock)
 *
 * `fields` maps address attributes (dotted paths or functions):
 *   { label, line1, line2, postcode, city, region, country, phone, type }
 * `format(address) => string[]` overrides the formatted address lines.
 *
 * New in this version
 *  - compact  : address + site chips (first `maxSites`, then a "+N" chip)
 *  - expanded : a rail grows out of the address pin and branches into each linked site
 *  - Copy address, open in Maps (`show-map-link`, override with `map-url`), tap-to-call phone
 *  - `locked` / `locked-reason` like WpPartnerCard
 *  - `sites` may be null/undefined; sites without an id are keyed by position
 */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { resolveAccessor } from '@/composables/shared/useSearchSelect'
import { localizedValue } from '@/composables/useLocaleField'

const props = defineProps({
  address: { type: Object, default: null },
  sites: { type: Array, default: () => [] },
  fields: { type: Object, default: () => ({}) },
  siteFields: { type: Object, default: () => ({}) },
  title: { type: String, default: '' },
  mode: { type: String, default: 'expanded', validator: v => ['compact', 'expanded'].includes(v) },
  expandable: { type: Boolean, default: true },
  loading: { type: Boolean, default: false },
  searchConfig: { type: Object, default: null },
  canChange: { type: Boolean, default: true },
  canView: { type: Boolean, default: true },
  canEdit: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
  canSetPrimary: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  showSites: { type: Boolean, default: true },
  format: { type: Function, default: null },
  /** Selection is frozen by business rules (e.g. confirmed document). */
  locked: { type: Boolean, default: false },
  lockedReason: { type: String, default: '' },
  /** Sites shown before "Show N more". 0 or less shows everything. */
  maxSites: { type: Number, default: 4 },
  /** Show the "Copy address" action. */
  copyable: { type: Boolean, default: true },
  /** Show the "Open in Maps" action. */
  showMapLink: { type: Boolean, default: true },
  /** `(address, lines) => url` to replace the default Google Maps search link. */
  mapUrl: { type: Function, default: null },
})

const emit = defineEmits(['change', 'request-select', 'view', 'edit', 'delete', 'set-primary', 'site-click'])

const resolveTypeVariant = t => ({
  headquarters: 'primary',
  office: 'info',
  branch: 'info',
  billing: 'secondary',
  shipping: 'success',
  factory: 'primary',
  workshop: 'warning',
  industrial: 'error',
  'construction_site': 'warning',
  warehouse: 'success',
  depot: 'success',
  retail: 'secondary',
  hotel: 'primary',
  restaurant: 'warning',
  government: 'secondary',
  hospital: 'error',
  school: 'warning',
  datacenter: 'info',
  residential: 'info',
  'operational_site': 'warning',
  other: 'secondary',
}[(t || '').toLowerCase()] || 'secondary')

const { t } = useI18n()
const { smAndDown } = useDisplay()

const F = computed(() => ({
  label: 'label',
  line1: 'address_street',
  line2: 'address_street2',
  postcode: 'postcode',
  city: 'city',
  region: 'region',
  country: 'country',
  phone: 'phone',
  type: 'type',
  ...props.fields,
}))

const S = computed(() => ({ key: 'id', title: 'name', subtitle: 'code', status: 'status', icon: 'tabler-building-warehouse', ...props.siteFields }))

const get = (spec, item) => resolveAccessor(spec, item)
const getLocalized = (spec, item) => localizedValue(get(spec, item))

const lines = computed(() => {
  const a = props.address

  if (!a) return []
  if (props.format) return props.format(a)

  const cityLine = [get(F.value.postcode, a), getLocalized(F.value.city, a)].filter(Boolean).join(' ')

  return [
    getLocalized(F.value.line1, a),
    getLocalized(F.value.line2, a),
    cityLine,
    getLocalized(F.value.region, a),
    getLocalized(F.value.country, a),
  ]
    .filter(Boolean)
})

const label = computed(() => (props.address ? getLocalized(F.value.label, props.address) : ''))
const type = computed(() => (props.address ? get(F.value.type, props.address) : ''))
const phone = computed(() => (props.address ? get(F.value.phone, props.address) : ''))
const phoneHref = computed(() => (phone.value ? `tel:${String(phone.value).replace(/[^\d+]/g, '')}` : ''))

const computedTitle = computed(() => {
  if (props.title) return props.title
  const tName = type.value
  if (tName) {
    return tName.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
  }

  return t('wp.cards.address.title')
})

const copyText = computed(() => lines.value.join('\n'))

const mapHref = computed(() => {
  if (!props.address || !props.showMapLink) return ''
  if (props.mapUrl) return props.mapUrl(props.address, lines.value) || ''
  if (!lines.value.length) return ''

  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(lines.value.join(', '))}`
})

const hasQuickActions = computed(() => (props.copyable && lines.value.length > 0) || !!mapHref.value)

// ── Sites ───────────────────────────────────────────────────────────────────
const siteList = computed(() => props.sites ?? [])
const siteKey = (site, i) => get(S.value.key, site) ?? `site-${i}`

const sitesOpen = ref(false)
const limit = computed(() => (props.maxSites > 0 ? props.maxSites : Infinity))
const visibleSites = computed(() => (sitesOpen.value ? siteList.value : siteList.value.slice(0, limit.value)))
const hiddenCount = computed(() => Math.max(0, siteList.value.length - limit.value))

// ── Layout state ────────────────────────────────────────────────────────────
const expanded = ref(props.mode === 'expanded')

watch(() => props.mode, m => { expanded.value = m === 'expanded' })

const showTree = computed(() => props.showSites && expanded.value && siteList.value.length > 0)

const pickerOpen = ref(false)
const state = computed(() => (props.loading ? 'loading' : props.address ? 'ready' : 'empty'))
const changeable = computed(() => props.canChange && !props.readonly && !props.locked)

const requestSelect = () => {
  emit('request-select')
  if (props.searchConfig) pickerOpen.value = true
}

const onPicked = address => {
  if (address) emit('change', address)
}
</script>

<template>
  <VCard
    class="wp-address-card"
    :class="{
      'wp-address-card--locked': locked,
      'wp-address-card--clickable': canView && address,
    }"
    variant="flat"
    border
    rounded="lg"
    role="group"
    :aria-label="computedTitle"
    :aria-busy="loading"
    @click="canView && address ? emit('view', address) : null"
  >
    <VCardText class="pb-2">
      <WpSectionHeader
        :title="computedTitle"
        icon="tabler-map-pin"
        :level="3"
      >
        <template #actions>
          <div
            class="wp-address-card__actions"
            @click.stop
          >
            <VChip
              v-if="locked"
              size="small"
              variant="tonal"
              color="warning"
              prepend-icon="tabler-lock"
            >
              {{ t('wp.cards.locked') }}
            </VChip>

            <slot name="actions" />

            <VBtn
              v-if="address && canSetPrimary && !address.is_primary && !readonly"
              icon
              size="small"
              variant="text"
              color="warning"
              :aria-label="t('Set as Main Address')"
              @click.stop="emit('set-primary', address)"
            >
              <VIcon icon="tabler-star" />
              <VTooltip
                activator="parent"
                location="top"
              >
                {{ t('Set as Main Address') }}
              </VTooltip>
            </VBtn>

            <VBtn
              v-if="address && canEdit && !readonly"
              icon
              size="small"
              variant="text"
              :aria-label="t('wp.cards.edit')"
              @click.stop="emit('edit', address)"
            >
              <VIcon icon="tabler-edit" />
              <VTooltip
                activator="parent"
                location="top"
              >
                {{ t('wp.cards.edit') }}
              </VTooltip>
            </VBtn>

            <VBtn
              v-if="address && canDelete && !readonly"
              icon
              size="small"
              variant="text"
              color="error"
              :aria-label="t('Delete')"
              @click.stop="emit('delete', address)"
            >
              <VIcon icon="tabler-trash" />
              <VTooltip
                activator="parent"
                location="top"
              >
                {{ t('Delete') }}
              </VTooltip>
            </VBtn>

            <VBtn
              v-if="address && expandable"
              icon
              size="small"
              variant="text"
              :aria-expanded="expanded"
              :aria-label="t(expanded ? 'wp.cards.collapse' : 'wp.cards.expand')"
              @click.stop="expanded = !expanded"
            >
              <VIcon
                icon="tabler-chevron-down"
                class="wp-address-card__chevron"
                :class="{ 'wp-address-card__chevron--open': expanded }"
              />
            </VBtn>

            <VBtn
              v-if="address && changeable"
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-replace"
              @click.stop="requestSelect"
            >
              {{ t('wp.cards.change') }}
            </VBtn>
          </div>
        </template>
      </WpSectionHeader>
    </VCardText>

    <VCardText class="pt-0">
      <WpStateBlock
        :state="state"
        skeleton="list-item-three-line"
        compact
      >
        <template #default>
          <!-- Address -->
          <div
            class="wp-address-card__node"
            :class="{ 'wp-address-card__node--no-rail': !showTree || smAndDown }"
          >
            <div
              v-if="showTree && !smAndDown"
              class="wp-address-card__rail"
            >
              <VAvatar
                size="40"
                rounded="lg"
                color="primary"
                variant="tonal"
                class="wp-address-card__pin-avatar"
              >
                <VIcon
                  icon="tabler-map-pin"
                  size="20"
                />
              </VAvatar>
              <span
                class="wp-address-card__stem"
                aria-hidden="true"
              />
            </div>

            <div class="wp-address-card__main">
              <div
                v-if="label || type || address?.is_primary || address?.is_billing_default || address?.is_shipping_default"
                class="d-flex align-center flex-wrap gap-2 mb-1"
              >
                <span
                  v-if="label"
                  class="wp-address-card__label text-subtitle-1 font-weight-bold"
                >{{ label }}</span>
                <VChip
                  v-if="type"
                  size="x-small"
                  label
                  variant="tonal"
                  :color="resolveTypeVariant(type)"
                  class="text-capitalize font-weight-medium"
                >
                  {{ type?.replace('_', ' ') }}
                </VChip>
                <VChip
                  v-if="address?.is_primary"
                  size="x-small"
                  label
                  color="primary"
                  variant="tonal"
                  class="font-weight-medium"
                >
                  <VIcon
                    icon="tabler-star-filled"
                    size="11"
                    start
                  />
                  {{ t('Main Address') }}
                </VChip>
                <VChip
                  v-if="address?.is_billing_default"
                  size="x-small"
                  label
                  color="info"
                  variant="tonal"
                  class="font-weight-medium"
                >
                  <VIcon
                    icon="tabler-receipt"
                    size="11"
                    start
                  />
                  {{ t('Billing') }}
                </VChip>
                <VChip
                  v-if="address?.is_shipping_default"
                  size="x-small"
                  label
                  color="success"
                  variant="tonal"
                  class="font-weight-medium"
                >
                  <VIcon
                    icon="tabler-truck"
                    size="11"
                    start
                  />
                  {{ t('Shipping') }}
                </VChip>
              </div>

              <address class="wp-address-card__address text-body-2">
                <div
                  v-for="(line, i) in lines"
                  :key="i"
                >
                  {{ line }}
                </div>
              </address>

              <!-- Standard Contact & Quick Actions Panel (matching WpPartnerCard) -->
              <div
                v-if="phone || hasQuickActions"
                class="wp-address-card__details"
                @click.stop
              >
                <div
                  v-if="phone"
                  class="d-flex align-center flex-wrap gap-2 text-caption mb-1"
                >
                  <span class="d-inline-flex align-center gap-1 text-medium-emphasis">
                    <VIcon
                      icon="tabler-phone"
                      size="15"
                    />
                    <span>{{ t('wp.cards.partner.phone') }}:</span>
                  </span>
                  <a
                    :href="phoneHref"
                    class="wp-address-card__link text-body-2 font-weight-medium text-high-emphasis text-decoration-none"
                    @click.stop
                  >{{ phone }}</a>
                  <WpCopyButton
                    class="wp-address-card__copy ms-1"
                    :value="phone"
                    :label="t('wp.cards.copy_value', { label: t('wp.cards.partner.phone') })"
                  />
                </div>

                <div
                  v-if="hasQuickActions"
                  class="wp-address-card__quick-actions d-flex align-center flex-wrap gap-2"
                  :class="{ 'pt-1 border-t': phone }"
                >
                  <WpCopyButton
                    v-if="copyable && lines.length"
                    text
                    :value="copyText"
                    :label="t('wp.cards.address.copy')"
                  />
                  <VBtn
                    v-if="mapHref"
                    size="small"
                    variant="text"
                    density="comfortable"
                    prepend-icon="tabler-map-2"
                    :href="mapHref"
                    target="_blank"
                    rel="noopener noreferrer"
                    @click.stop
                  >
                    {{ t('wp.cards.address.open_map') }}
                  </VBtn>
                </div>
              </div>

              <VExpandTransition>
                <div v-if="expanded && $slots['address-extra']">
                  <div class="wp-address-card__extra">
                    <slot
                      name="address-extra"
                      :address="address"
                    />
                  </div>
                </div>
              </VExpandTransition>

              <p
                v-if="locked && lockedReason"
                class="wp-address-card__note text-body-2"
                role="note"
              >
                <VIcon
                  icon="tabler-lock"
                  size="16"
                />
                <span>{{ lockedReason }}</span>
              </p>

              <p
                v-if="showSites && expanded && !siteList.length"
                class="wp-address-card__no-sites text-body-2 text-disabled"
              >
                <VIcon
                  :icon="S.icon"
                  size="16"
                />
                <span>{{ t('wp.cards.address.no_sites') }}</span>
              </p>
            </div>
          </div>

          <!-- Linked sites -->
          <div
            v-if="showSites && siteList.length"
            class="wp-address-card__branch"
            :class="{ 'wp-address-card__branch--tree': showTree && !smAndDown }"
          >
            <div class="wp-address-card__branch-head text-body-2 font-weight-medium text-medium-emphasis">
              {{ t('wp.cards.address.linked_sites') }}
              <VChip
                size="x-small"
                variant="tonal"
                class="ms-1"
              >
                {{ siteList.length }}
              </VChip>
            </div>

            <!-- compact: chips -->
            <div
              v-if="!expanded"
              class="wp-address-card__chips"
            >
              <VChip
                v-for="(site, i) in visibleSites"
                :key="siteKey(site, i)"
                size="small"
                variant="tonal"
                color="primary"
                :prepend-icon="S.icon"
                @click="emit('site-click', site)"
              >
                <slot
                  name="site"
                  :site="site"
                >
                  {{ get(S.title, site) }}
                </slot>
              </VChip>
              <VChip
                v-if="hiddenCount && !sitesOpen"
                size="small"
                variant="outlined"
                :aria-label="t('wp.cards.address.show_more', { count: hiddenCount })"
                @click="sitesOpen = true"
              >
                +{{ hiddenCount }}
              </VChip>
              <VChip
                v-else-if="hiddenCount && sitesOpen"
                size="small"
                variant="outlined"
                prepend-icon="tabler-chevron-up"
                @click="sitesOpen = false"
              >
                {{ t('wp.cards.address.show_less') }}
              </VChip>
            </div>

            <!-- expanded: rows on the rail -->
            <ul
              v-else
              class="wp-address-card__sites"
              :aria-label="t('wp.cards.address.linked_sites')"
            >
              <li
                v-for="(site, i) in visibleSites"
                :key="siteKey(site, i)"
                class="wp-address-card__leaf"
              >
                <button
                  type="button"
                  class="wp-address-card__site"
                  :class="{ 'wp-address-card__site--mobile': smAndDown }"
                  @click="emit('site-click', site)"
                >
                  <VAvatar
                    v-if="!smAndDown"
                    size="34"
                    rounded="lg"
                    color="primary"
                    variant="tonal"
                  >
                    <VIcon
                      :icon="S.icon"
                      size="18"
                    />
                  </VAvatar>
                  <slot
                    name="site"
                    :site="site"
                  >
                    <span class="wp-address-card__site-text">
                      <span class="text-body-2 font-weight-medium text-break">{{ get(S.title, site) }}</span>
                      <span
                        v-if="get(S.subtitle, site)"
                        class="text-caption text-medium-emphasis text-break"
                      >{{ get(S.subtitle, site) }}</span>
                    </span>
                    <WpStatusChip
                      v-if="get(S.status, site)"
                      :status="get(S.status, site)"
                      :class="{ 'mt-1 align-self-start': smAndDown }"
                    />
                  </slot>
                </button>
              </li>

              <li
                v-if="hiddenCount"
                class="wp-address-card__leaf wp-address-card__leaf--more"
              >
                <VBtn
                  size="small"
                  variant="text"
                  density="comfortable"
                  :prepend-icon="sitesOpen ? 'tabler-chevron-up' : 'tabler-chevron-down'"
                  @click="sitesOpen = !sitesOpen"
                >
                  {{ sitesOpen ? t('wp.cards.address.show_less') : t('wp.cards.address.show_more', { count: hiddenCount }) }}
                </VBtn>
              </li>
            </ul>
          </div>
        </template>

        <template #empty-action>
          <VBtn
            v-if="changeable"
            color="primary"
            variant="tonal"
            prepend-icon="tabler-map-pin-search"
            @click="requestSelect"
          >
            {{ t('wp.cards.address.select') }}
          </VBtn>
        </template>
      </WpStateBlock>
    </VCardText>

    <WpSearchSelectDialog
      v-if="searchConfig"
      v-model="pickerOpen"
      :entity-label="t('wp.cards.address.entity')"
      icon="tabler-map-pin-search"
      :selected="address"
      v-bind="searchConfig"
      @select="onPicked"
    />
  </VCard>
</template>

<style scoped>
.wp-address-card {
  /* Rail geometry: the line runs through the centre of the 40px pin plate. */
  --wp-rail-color: rgba(var(--v-theme-primary), 0.35);
  --wp-rail-x: 19px;
  --wp-rail-gap: 8px;
  --wp-indent: 52px;
}

.wp-address-card--clickable {
  cursor: pointer;
  transition: border-color 0.18s ease, box-shadow 0.18s ease;
}

.wp-address-card--clickable:hover {
  border-color: rgba(var(--v-theme-primary), 0.5) !important;
  box-shadow: 0 4px 16px rgba(var(--v-theme-primary), 0.08);
}

/* Header actions */
:deep(.wp-section-header) {
  flex-wrap: nowrap !important;
  align-items: center !important;
}

:deep(.wp-section-header__text) {
  flex: 1 1 auto !important;
  min-width: 0 !important;
}

:deep(.wp-section-header__actions) {
  flex-shrink: 0 !important;
  margin-inline-start: auto !important;
}

.wp-address-card__actions {
  display: inline-flex;
  flex-wrap: nowrap !important;
  align-items: center;
  justify-content: flex-end;
  gap: 2px;
  flex-shrink: 0;
}

.wp-address-card__actions :deep(.v-btn--icon) {
  inline-size: 30px;
  block-size: 30px;
  min-inline-size: 30px;
}

.wp-address-card__chevron {
  transition: transform 0.2s ease;
}

.wp-address-card__chevron--open {
  transform: rotate(180deg);
}

/* Address node: pin plate + text */
.wp-address-card__node {
  display: grid;
  column-gap: 12px;
  grid-template-columns: 40px minmax(0, 1fr);
}

.wp-address-card__node--no-rail {
  display: block;
}

.wp-address-card__rail {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.wp-address-card__stem {
  flex: 1 1 auto;
  background: var(--wp-rail-color);
  border-radius: 1px;
  inline-size: 2px;
  margin-block-start: 4px;
}

.wp-address-card__main {
  min-inline-size: 0;
  padding-block-end: 4px;
}

.wp-address-card__label,
.wp-address-card__address {
  overflow-wrap: anywhere;
}

.wp-address-card__address {
  font-style: normal;
  line-height: 1.55;
}

/* Contact & actions details container (mirroring WpPartnerCard) */
.wp-address-card__details {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px 14px;
  margin: 16px 0 0;
  background: rgba(var(--v-theme-on-surface), 0.035);
  border-radius: 12px;
}

.wp-address-card__row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  min-inline-size: 0;
}

.wp-address-card__row-icon {
  display: grid;
  flex: none;
  background: rgba(var(--v-theme-primary), 0.1);
  block-size: 28px;
  border-radius: 8px;
  color: rgb(var(--v-theme-primary));
  inline-size: 28px;
  place-items: center;
}

.wp-address-card__row-body {
  min-inline-size: 0;
}

.wp-address-card__link {
  border-radius: 3px;
  color: inherit;
  overflow-wrap: anywhere;
  text-decoration: none;

  &:hover {
    color: rgb(var(--v-theme-primary));
    text-decoration: underline;
    text-underline-offset: 3px;
  }

  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }
}

.wp-address-card__copy {
  opacity: 0;
  transition: opacity 0.15s ease;
}

.wp-address-card__row:hover .wp-address-card__copy,
.wp-address-card__row:focus-within .wp-address-card__copy {
  opacity: 1;
}

.wp-address-card__quick-actions {
  margin-inline-start: -6px;
}

.wp-address-card__extra {
  padding-block-start: 12px;
}

.wp-address-card__no-sites,
.wp-address-card__note {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 12px 0 0;
}

.wp-address-card__note {
  align-items: flex-start;
  padding: 10px 12px;
  background: rgba(var(--v-theme-warning), 0.1);
  border-radius: 10px;
  color: rgb(var(--v-theme-warning));
}

.wp-address-card__note > span {
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
}

/* Linked sites */
.wp-address-card__branch {
  margin-block-start: 8px;
}

.wp-address-card__branch-head {
  display: flex;
  align-items: center;
  min-block-size: 24px;
}

.wp-address-card__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-block-start: 8px;
}

/* Expanded: rail + branches */
.wp-address-card__branch--tree {
  margin-block-start: 0;
}

.wp-address-card__branch--tree .wp-address-card__branch-head {
  position: relative;
  margin-inline-start: var(--wp-rail-x);
  margin-block-end: var(--wp-rail-gap);
  padding-inline-start: calc(var(--wp-indent) - var(--wp-rail-x));
}

.wp-address-card__branch--tree .wp-address-card__branch-head::before {
  position: absolute;
  border-inline-start: 2px solid var(--wp-rail-color);
  content: "";
  inset-block: 0 calc(var(--wp-rail-gap) * -1);
  inset-inline-start: 0;
}

.wp-address-card__sites {
  display: grid;
  padding: 0;
  margin: 0;
  gap: var(--wp-rail-gap);
  list-style: none;
  margin-inline: var(--wp-rail-x) 0;
}

.wp-address-card__leaf {
  position: relative;
  padding-inline-start: calc(var(--wp-indent) - var(--wp-rail-x));
}

/* elbow into the row */
.wp-address-card__leaf::before {
  position: absolute;
  block-size: calc(50% + var(--wp-rail-gap));
  border-block-end: 2px solid var(--wp-rail-color);
  border-end-start-radius: 10px;
  border-inline-start: 2px solid var(--wp-rail-color);
  content: "";
  inline-size: calc(var(--wp-indent) - var(--wp-rail-x));
  inset-block-start: calc(var(--wp-rail-gap) * -1);
  inset-inline-start: 0;
}

/* rail continues past every leaf except the last */
.wp-address-card__leaf:not(:last-child)::before {
  border-end-start-radius: 0;
}

.wp-address-card__leaf:not(:last-child)::after {
  position: absolute;
  border-inline-start: 2px solid var(--wp-rail-color);
  content: "";
  inset-block: 50% calc(var(--wp-rail-gap) * -1);
  inset-inline-start: 0;
}

.wp-address-card__leaf--more {
  display: flex;
  align-items: center;
}

.wp-address-card__site {
  display: flex;
  align-items: center;
  padding: 8px 10px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
  border-radius: 10px;
  color: inherit;
  cursor: pointer;
  font: inherit;
  gap: 10px;
  inline-size: 100%;
  min-block-size: 48px;
  text-align: start;
  transition: background-color 0.15s ease, border-color 0.15s ease;
}

.wp-address-card__site:hover {
  background: rgba(var(--v-theme-primary), 0.05);
  border-color: rgba(var(--v-theme-primary), 0.45);
}

.wp-address-card__site:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.wp-address-card__site-text {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  min-inline-size: 0;
  overflow-wrap: anywhere;
}

.wp-address-card__site--mobile {
  flex-direction: column !important;
  align-items: flex-start !important;
  gap: 6px !important;
  padding: 10px 12px !important;
}

@media (max-width: 599.98px) {
  :deep(.wp-section-header) {
    gap: 6px !important;
  }

  :deep(.wp-section-header .v-avatar) {
    inline-size: 28px !important;
    block-size: 28px !important;
    min-inline-size: 28px !important;
  }

  :deep(.wp-section-header__text) {
    flex: 1 1 auto !important;
    min-inline-size: 0 !important;
  }

  :deep(.wp-section-header__text h3),
  :deep(.wp-section-header__text .text-subtitle-1),
  :deep(.wp-section-header__title) {
    font-size: 0.875rem !important;
    line-height: 1.25 !important;
    white-space: normal !important;
    overflow-wrap: anywhere !important;
    word-break: break-word !important;
  }

  .wp-address-card__actions {
    gap: 1px !important;
  }

  .wp-address-card__actions :deep(.v-btn--icon) {
    inline-size: 24px !important;
    block-size: 24px !important;
    min-inline-size: 24px !important;
  }

  /* Hide redundant body avatar & stem on mobile; avatar is in card header */
  .wp-address-card__rail {
    display: none !important;
  }

  .wp-address-card__node {
    display: block !important;
  }

  .wp-address-card__main {
    inline-size: 100% !important;
    width: 100% !important;
  }

  /* Remove tree line connecting to sites on mobile, span full width */
  .wp-address-card__stem,
  .wp-address-card__branch--tree .wp-address-card__branch-head::before,
  .wp-address-card__leaf::before,
  .wp-address-card__leaf::after {
    display: none !important;
  }

  .wp-address-card__branch--tree .wp-address-card__branch-head {
    margin-inline-start: 0 !important;
    padding-inline-start: 0 !important;
  }

  .wp-address-card__sites {
    margin-inline: 0 !important;
    gap: 8px !important;
  }

  .wp-address-card__leaf {
    padding-inline-start: 0 !important;
  }

  /* Attached client site card on mobile: hide avatar, place status badge at bottom */
  .wp-address-card__site > .v-avatar {
    display: none !important;
  }

  .wp-address-card__site {
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 6px !important;
    padding: 10px 12px !important;
  }

  .wp-address-card__site-text {
    inline-size: 100% !important;
    width: 100% !important;
  }

  .wp-address-card__site :deep(.v-chip) {
    margin-block-start: 4px !important;
    align-self: flex-start !important;
  }
}

@media (prefers-reduced-motion: reduce) {
  .wp-address-card__chevron,
  .wp-address-card__site {
    transition: none;
  }
}
</style>
