<script setup>
/* eslint-disable lines-around-comment, camelcase, vue/custom-event-name-casing */
/**
 * WpAddressViewDialog
 * ─────────────────────────────────────────────────────────────────────────────
 * Modern, mobile-first responsive shared dialog for displaying detailed address
 * information across WAVE PLUS modules.
 *
 * Clones the exact header structure, scroll container, responsive sizing, and
 * action footer from WpWizardDialog / WpContactViewDialog with full 320px+ mobile optimization.
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'
import WpActionFooter from '@/components/shared/layout/WpActionFooter.vue'
import LocationPicker from '@/components/shared/LocationPicker.vue'
import { localizedValue } from '@/composables/useLocaleField'
import { getAddressTypes } from '@/utils/bp-constants'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  address: { type: Object, default: null },
  title: { type: String, default: '' },
  canEdit: { type: Boolean, default: false },
  canSetPrimary: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'edit', 'set-primary', 'close'])

const { t } = useI18n()
const { smAndDown } = useDisplay()

const isOpen = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const addr = computed(() => props.address || {})

const label = computed(() => localizedValue(addr.value.label))
const street = computed(() => localizedValue(addr.value.address_street || addr.value.addressStreet))
const street2 = computed(() => localizedValue(addr.value.address_street2 || addr.value.addressStreet2))
const city = computed(() => localizedValue(addr.value.city))
const state = computed(() => localizedValue(addr.value.state))
const postcode = computed(() => addr.value.postcode || '')
const country = computed(() => localizedValue(addr.value.country))
const notes = computed(() => localizedValue(addr.value.notes))

const allAddressTypes = computed(() => getAddressTypes())
const typeTitle = computed(() => {
  if (!addr.value?.type) return ''
  const matched = allAddressTypes.value.find(a => a.value.toLowerCase() === addr.value.type.toLowerCase())
  return matched?.title || addr.value.type.replace(/_/g, ' ')
})

const dialogTitle = computed(() => {
  if (props.title) return props.title
  if (label.value) return label.value
  if (street.value) return street.value
  
  return t('Address Details')
})

const dialogSubtitle = computed(() => {
  const parts = [city.value, country.value].filter(Boolean)
  
  return parts.length ? parts.join(', ') : (typeTitle.value || '')
})

const typeVariant = computed(() => {
  const type = (addr.value.type || '').toLowerCase()
  
  return {
    headquarters: 'primary',
    office: 'info',
    branch: 'info',
    billing: 'secondary',
    shipping: 'success',
    factory: 'primary',
    workshop: 'warning',
    industrial: 'error',
    construction_site: 'warning',
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
    operational_site: 'warning',
    other: 'secondary',
  }[type] || 'secondary'
})

const typeIcon = computed(() => {
  const type = (addr.value.type || '').toLowerCase()
  
  return {
    headquarters: 'tabler-building-skyscraper',
    office: 'tabler-building',
    branch: 'tabler-building-community',
    billing: 'tabler-receipt',
    shipping: 'tabler-truck-delivery',
    factory: 'tabler-building-factory-2',
    workshop: 'tabler-tools',
    industrial: 'tabler-bolt',
    construction_site: 'tabler-cone',
    warehouse: 'tabler-building-warehouse',
    depot: 'tabler-car-garage',
    retail: 'tabler-building-store',
    hotel: 'tabler-bed',
    restaurant: 'tabler-tools-kitchen-2',
    government: 'tabler-building-monument',
    hospital: 'tabler-building-hospital',
    school: 'tabler-school',
    datacenter: 'tabler-server',
    residential: 'tabler-home',
    operational_site: 'tabler-map-pin',
    other: 'tabler-map-pin',
  }[type] || 'tabler-map-pin'
})

const hasGis = computed(() => {
  const lat = Number(addr.value.latitude)
  const lng = Number(addr.value.longitude)
  
  return !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0
})

const mapSearchUrl = computed(() => {
  if (hasGis.value) {
    return `https://www.google.com/maps/search/?api=1&query=${addr.value.latitude},${addr.value.longitude}`
  }
  const q = [street.value, city.value, postcode.value, country.value].filter(Boolean).join(', ')
  
  return q ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(q)}` : '#'
})

const workTeamList = computed(() => {
  const wt = addr.value.work_team || addr.value.workTeam
  if (!wt) return []
  if (Array.isArray(wt)) return wt
  
  return String(wt).split(',').map(s => s.trim()).filter(Boolean)
})

const responsibleName = computed(() => {
  const r = addr.value.responsible
  if (!r) return ''
  if (typeof r === 'object') {
    const fn = localizedValue(r.first_name)
    const ln = localizedValue(r.last_name)
    const full = [fn, ln].filter(Boolean).join(' ')
    
    return full || r.email || ''
  }
  
  return String(r)
})

const linkedSites = computed(() => addr.value.client_sites || addr.value.sites || [])

const handleEdit = () => {
  emit('edit', addr.value)
  isOpen.value = false
}

const handleSetPrimary = () => {
  emit('set-primary', addr.value)
}

const handleClose = () => {
  isOpen.value = false
  emit('close')
}
</script>

<template>
  <VDialog
    v-model="isOpen"
    :max-width="720"
    :fullscreen="smAndDown"
    scrollable
    class="wp-view-dialog"
  >
    <VCard class="wp-view-dialog__card d-flex flex-column h-100">
      <!-- ── Fixed Header (Cloned from WpWizardDialog) ────────────────── -->
      <header class="wp-view-dialog__header">
        <div class="d-flex align-center gap-2 gap-sm-3 min-w-0 w-100">
          <VAvatar
            :color="typeVariant"
            variant="elevated"
            rounded="lg"
            :size="smAndDown ? 36 : 48"
            class="wp-view-dialog__avatar flex-shrink-0"
          >
            <VIcon
              :icon="typeIcon"
              :size="smAndDown ? 20 : 26"
            />
          </VAvatar>

          <div class="flex-grow-1 min-w-0">
            <div class="d-flex align-center gap-1.5 flex-wrap min-w-0">
              <h2 class="text-subtitle-1 text-sm-h6 font-weight-bold text-break mb-0">
                {{ dialogTitle }}
              </h2>
              <VChip
                v-if="addr.is_primary"
                size="x-small"
                color="primary"
                variant="tonal"
                label
                class="font-weight-semibold flex-shrink-0"
              >
                <VIcon
                  icon="tabler-star-filled"
                  size="10"
                  start
                />
                {{ t('Main Address') }}
              </VChip>
            </div>

            <div class="d-flex align-center gap-1.5 flex-wrap mt-1 min-w-0">
              <VChip
                v-if="addr.type"
                :color="typeVariant"
                size="x-small"
                variant="tonal"
                label
                class="text-capitalize flex-shrink-0"
              >
                {{ typeTitle }}
              </VChip>

              <VChip
                v-if="addr.is_billing_default"
                size="x-small"
                color="info"
                variant="tonal"
                label
                class="flex-shrink-0"
              >
                <VIcon
                  icon="tabler-receipt"
                  size="10"
                  start
                />
                {{ t('Billing') }}
              </VChip>

              <VChip
                v-if="addr.is_shipping_default"
                size="x-small"
                color="success"
                variant="tonal"
                label
                class="flex-shrink-0"
              >
                <VIcon
                  icon="tabler-truck"
                  size="10"
                  start
                />
                {{ t('Shipping') }}
              </VChip>

              <span
                v-if="dialogSubtitle"
                class="text-caption text-medium-emphasis text-truncate"
              >
                {{ dialogSubtitle }}
              </span>
            </div>
          </div>

          <IconBtn
            variant="tonal"
            size="small"
            color="secondary"
            class="wp-view-dialog__close-btn rounded-lg flex-shrink-0 ms-auto"
            :aria-label="t('Close')"
            @click="handleClose"
          >
            <VIcon
              icon="tabler-x"
              size="20"
            />
          </IconBtn>
        </div>
      </header>

      <VDivider />

      <!-- ── Scrollable Body (Cloned from WpWizardDialog) ───────────────── -->
      <PerfectScrollbar
        class="wp-view-dialog__body"
        :options="{ wheelPropagation: false, suppressScrollX: true }"
      >
        <div
          v-if="address"
          class="d-flex flex-column gap-4"
        >
          <!-- Section Card 1: Classification & Operational Roles -->
          <VCard
            variant="flat"
            border
            rounded="lg"
            class="wp-view-dialog__section overflow-hidden"
          >
            <div class="wp-view-dialog__section-header d-flex align-center gap-3 px-4 px-sm-5 py-3">
              <VAvatar
                color="primary"
                variant="tonal"
                rounded="lg"
                :size="smAndDown ? 34 : 38"
                class="flex-shrink-0"
              >
                <VIcon
                  icon="tabler-category"
                  :size="smAndDown ? 18 : 20"
                />
              </VAvatar>
              <div class="flex-grow-1 min-w-0">
                <h3 class="text-subtitle-1 font-weight-bold mb-0 text-break">
                  {{ t('Classification & Roles') }}
                </h3>
                <div class="text-caption text-medium-emphasis text-break">
                  {{ t('Address classification, facility category and operational roles') }}
                </div>
              </div>
            </div>
            <VDivider />
            <div class="px-4 px-sm-5 py-3">
              <dl class="wp-view-dialog__list">
                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('Classification Type') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0">
                    <VChip
                      :color="typeVariant"
                      size="small"
                      label
                      class="text-capitalize font-weight-medium"
                    >
                      <VIcon
                        :icon="typeIcon"
                        size="14"
                        start
                      />
                      {{ typeTitle || t('Standard') }}
                    </VChip>
                  </dd>
                </div>

                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('Operational Roles') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0">
                    <div class="d-flex flex-wrap gap-1">
                      <VChip
                        v-if="addr.is_primary"
                        color="primary"
                        size="small"
                        label
                      >
                        <VIcon
                          icon="tabler-star-filled"
                          size="12"
                          start
                        />
                        {{ t('Main Address') }}
                      </VChip>
                      <VChip
                        v-if="addr.is_billing_default"
                        color="info"
                        size="small"
                        label
                      >
                        <VIcon
                          icon="tabler-receipt"
                          size="12"
                          start
                        />
                        {{ t('Billing') }}
                      </VChip>
                      <VChip
                        v-if="addr.is_shipping_default"
                        color="success"
                        size="small"
                        label
                      >
                        <VIcon
                          icon="tabler-truck"
                          size="12"
                          start
                        />
                        {{ t('Shipping') }}
                      </VChip>
                      <span
                        v-if="!addr.is_primary && !addr.is_billing_default && !addr.is_shipping_default"
                        class="text-body-2 text-medium-emphasis"
                      >
                        {{ t('Standard (No operational role)') }}
                      </span>
                    </div>
                  </dd>
                </div>

                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('Label / Site Name') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0 text-break">
                    {{ label || '—' }}
                  </dd>
                </div>
              </dl>
            </div>
          </VCard>

          <!-- Section Card 2: Postal Location & Physical Address -->
          <VCard
            variant="flat"
            border
            rounded="lg"
            class="wp-view-dialog__section overflow-hidden"
          >
            <div class="wp-view-dialog__section-header d-flex align-center gap-3 px-4 px-sm-5 py-3">
              <VAvatar
                color="primary"
                variant="tonal"
                rounded="lg"
                :size="smAndDown ? 34 : 38"
                class="flex-shrink-0"
              >
                <VIcon
                  icon="tabler-map-pin"
                  :size="smAndDown ? 18 : 20"
                />
              </VAvatar>
              <div class="flex-grow-1 min-w-0">
                <h3 class="text-subtitle-1 font-weight-bold mb-0 text-break">
                  {{ t('Postal Address') }}
                </h3>
                <div class="text-caption text-medium-emphasis text-break">
                  {{ t('Physical street address, postal code and territorial details') }}
                </div>
              </div>
            </div>
            <VDivider />
            <div class="px-4 px-sm-5 py-3">
              <dl class="wp-view-dialog__list">
                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('Street Address') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0 text-break">
                    {{ street || '—' }}
                    <div
                      v-if="street2"
                      class="text-caption text-medium-emphasis mt-1"
                    >
                      {{ street2 }}
                    </div>
                  </dd>
                </div>

                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('City') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0 text-break">
                    {{ city || '—' }}
                  </dd>
                </div>

                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('Postal Code') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0">
                    {{ postcode || '—' }}
                  </dd>
                </div>

                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('State / Province') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0 text-break">
                    {{ state || '—' }}
                  </dd>
                </div>

                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('Country') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0 text-break">
                    {{ country || '—' }}
                  </dd>
                </div>
              </dl>
            </div>
          </VCard>

          <!-- Section Card 3: Geographic Coordinates & Interactive Map -->
          <VCard
            v-if="hasGis"
            variant="flat"
            border
            rounded="lg"
            class="wp-view-dialog__section overflow-hidden"
          >
            <div class="wp-view-dialog__section-header d-flex align-center justify-space-between flex-wrap gap-2 px-4 px-sm-5 py-3">
              <div class="d-flex align-center gap-3 min-w-0">
                <VAvatar
                  color="primary"
                  variant="tonal"
                  rounded="lg"
                  :size="smAndDown ? 34 : 38"
                  class="flex-shrink-0"
                >
                  <VIcon
                    icon="tabler-compass"
                    :size="smAndDown ? 18 : 20"
                  />
                </VAvatar>
                <div class="min-w-0">
                  <h3 class="text-subtitle-1 font-weight-bold mb-0 text-break">
                    {{ t('Geographic Location & GIS') }}
                  </h3>
                  <div class="text-caption text-medium-emphasis text-break">
                    {{ t('Precise coordinates and live map visualization') }}
                  </div>
                </div>
              </div>

              <VBtn
                :href="mapSearchUrl"
                target="_blank"
                rel="noopener noreferrer"
                variant="tonal"
                size="small"
                color="primary"
                prepend-icon="tabler-external-link"
              >
                {{ t('Open in Maps') }}
              </VBtn>
            </div>
            <VDivider />
            <div class="px-4 px-sm-5 py-3">
              <div class="d-flex align-center justify-space-between mb-2 flex-wrap gap-2">
                <span class="text-caption text-medium-emphasis">{{ t('GPS Coordinates') }}</span>
                <VChip
                  size="x-small"
                  color="success"
                  variant="tonal"
                  prepend-icon="tabler-map-pin"
                  label
                >
                  {{ Number(addr.latitude).toFixed(5) }}, {{ Number(addr.longitude).toFixed(5) }}
                </VChip>
              </div>
              <div
                class="rounded-lg overflow-hidden border"
                style="min-height: 180px; height: 210px;"
              >
                <LocationPicker
                  :latitude="Number(addr.latitude)"
                  :longitude="Number(addr.longitude)"
                  :address-query="[street, city, postcode, country].filter(Boolean).join(', ')"
                  :is-visible="isOpen"
                  readonly
                  height="210"
                />
              </div>
            </div>
          </VCard>

          <!-- Section Card 4: Contact & Emergency Services -->
          <VCard
            variant="flat"
            border
            rounded="lg"
            class="wp-view-dialog__section overflow-hidden"
          >
            <div class="wp-view-dialog__section-header d-flex align-center gap-3 px-4 px-sm-5 py-3">
              <VAvatar
                color="primary"
                variant="tonal"
                rounded="lg"
                :size="smAndDown ? 34 : 38"
                class="flex-shrink-0"
              >
                <VIcon
                  icon="tabler-phone-call"
                  :size="smAndDown ? 18 : 20"
                />
              </VAvatar>
              <div class="flex-grow-1 min-w-0">
                <h3 class="text-subtitle-1 font-weight-bold mb-0 text-break">
                  {{ t('Contact & Emergency Services') }}
                </h3>
                <div class="text-caption text-medium-emphasis text-break">
                  {{ t('Direct contact channels, phone, email and emergency access') }}
                </div>
              </div>
            </div>
            <VDivider />
            <div class="px-4 px-sm-5 py-3">
              <dl class="wp-view-dialog__list">
                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('Phone') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0">
                    <a
                      v-if="addr.phone"
                      :href="`tel:${addr.phone}`"
                      class="text-decoration-none text-primary d-inline-flex align-center gap-1 text-break"
                    >
                      <VIcon
                        icon="tabler-phone"
                        size="14"
                        class="flex-shrink-0"
                      />
                      <span>{{ addr.phone }}</span>
                    </a>
                    <span
                      v-else
                      class="text-disabled font-italic"
                    >—</span>
                  </dd>
                </div>

                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('Email') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0">
                    <a
                      v-if="addr.email"
                      :href="`mailto:${addr.email}`"
                      class="text-decoration-none text-primary d-inline-flex align-center gap-1 text-break"
                    >
                      <VIcon
                        icon="tabler-mail"
                        size="14"
                        class="flex-shrink-0"
                      />
                      <span>{{ addr.email }}</span>
                    </a>
                    <span
                      v-else
                      class="text-disabled font-italic"
                    >—</span>
                  </dd>
                </div>

                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('Nearest Fire Station') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0">
                    <span
                      v-if="addr.nearest_fire_station || addr.nearestFireStation"
                      class="d-flex align-center gap-1 text-error text-break"
                    >
                      <VIcon
                        icon="tabler-flame"
                        size="15"
                      />
                      {{ addr.nearest_fire_station || addr.nearestFireStation }}
                    </span>
                    <span
                      v-else
                      class="text-disabled font-italic"
                    >—</span>
                  </dd>
                </div>
              </dl>
            </div>
          </VCard>

          <!-- Section Card 5: Responsibility, Team & Linked Sites -->
          <VCard
            variant="flat"
            border
            rounded="lg"
            class="wp-view-dialog__section overflow-hidden"
          >
            <div class="wp-view-dialog__section-header d-flex align-center gap-3 px-4 px-sm-5 py-3">
              <VAvatar
                color="primary"
                variant="tonal"
                rounded="lg"
                :size="smAndDown ? 34 : 38"
                class="flex-shrink-0"
              >
                <VIcon
                  icon="tabler-users"
                  :size="smAndDown ? 18 : 20"
                />
              </VAvatar>
              <div class="flex-grow-1 min-w-0">
                <h3 class="text-subtitle-1 font-weight-bold mb-0 text-break">
                  {{ t('Responsibility, Team & Linked Sites') }}
                </h3>
                <div class="text-caption text-medium-emphasis text-break">
                  {{ t('Assigned leadership, operating team and associated facilities') }}
                </div>
              </div>
            </div>
            <VDivider />
            <div class="px-4 px-sm-5 py-3">
              <dl class="wp-view-dialog__list">
                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('Main Responsible Person') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0">
                    <div
                      v-if="responsibleName"
                      class="d-flex align-center gap-2"
                    >
                      <VAvatar
                        size="26"
                        color="warning"
                        variant="tonal"
                      >
                        <VIcon
                          icon="tabler-star"
                          size="14"
                        />
                      </VAvatar>
                      <span class="text-break">{{ responsibleName }}</span>
                    </div>
                    <span
                      v-else
                      class="text-disabled font-italic"
                    >
                      {{ t('No responsible person assigned') }}
                    </span>
                  </dd>
                </div>

                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('Work Team Members') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0">
                    <div
                      v-if="workTeamList.length > 0"
                      class="d-flex flex-wrap gap-1"
                    >
                      <VChip
                        v-for="(member, idx) in workTeamList"
                        :key="idx"
                        size="small"
                        color="primary"
                        variant="tonal"
                        label
                      >
                        {{ member }}
                      </VChip>
                    </div>
                    <span
                      v-else
                      class="text-disabled font-italic"
                    >
                      {{ t('No team members assigned') }}
                    </span>
                  </dd>
                </div>

                <!-- Linked Client Sites -->
                <div
                  v-if="linkedSites.length > 0"
                  class="wp-view-dialog__row"
                  style="grid-column: 1 / -1;"
                >
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('Linked Client Sites') }} ({{ linkedSites.length }})
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0">
                    <div class="d-flex flex-wrap gap-2 mt-1">
                      <VChip
                        v-for="(site, sIdx) in linkedSites"
                        :key="site.id || sIdx"
                        size="small"
                        color="info"
                        variant="tonal"
                        class="font-weight-medium"
                      >
                        <VIcon
                          icon="tabler-building-warehouse"
                          size="14"
                          start
                        />
                        <span>{{ site.name }}</span>
                        <span
                          v-if="site.code"
                          class="text-medium-emphasis ms-1"
                        >({{ site.code }})</span>
                      </VChip>
                    </div>
                  </dd>
                </div>
              </dl>
            </div>
          </VCard>

          <!-- Section Card 6: Notes & Access Instructions -->
          <VCard
            v-if="notes"
            variant="flat"
            border
            rounded="lg"
            class="wp-view-dialog__section overflow-hidden"
          >
            <div class="wp-view-dialog__section-header d-flex align-center gap-3 px-4 px-sm-5 py-3">
              <VAvatar
                color="primary"
                variant="tonal"
                rounded="lg"
                :size="smAndDown ? 34 : 38"
                class="flex-shrink-0"
              >
                <VIcon
                  icon="tabler-notes"
                  :size="smAndDown ? 18 : 20"
                />
              </VAvatar>
              <div class="flex-grow-1 min-w-0">
                <h3 class="text-subtitle-1 font-weight-bold mb-0 text-break">
                  {{ t('Notes & Access Instructions') }}
                </h3>
                <div class="text-caption text-medium-emphasis text-break">
                  {{ t('Additional delivery instructions, building access and security notes') }}
                </div>
              </div>
            </div>
            <VDivider />
            <div class="px-4 px-sm-5 py-3 text-body-2 text-break">
              {{ notes }}
            </div>
          </VCard>
        </div>
      </PerfectScrollbar>

      <!-- ── Fixed Footer matching WpWizardDialog (WpActionFooter) ────── -->
      <WpActionFooter
        bordered
        class="wp-view-dialog__footer"
      >
        <template #start>
          <div
            v-if="!smAndDown && addr.id"
            class="d-flex align-center gap-2 text-caption text-medium-emphasis"
          >
            <VIcon
              icon="tabler-map-pin"
              size="16"
            />
            <span>{{ t('Address ID') }}: #{{ addr.id }}</span>
          </div>
        </template>

        <VBtn
          :icon="smAndDown"
          variant="tonal"
          color="secondary"
          :aria-label="t('Close')"
          @click="handleClose"
        >
          <VIcon icon="tabler-x" />
          <span
            v-if="!smAndDown"
            class="ms-1"
          >{{ t('Close') }}</span>
          <VTooltip
            activator="parent"
            location="top"
          >
            {{ t('Close') }}
          </VTooltip>
        </VBtn>

        <VBtn
          v-if="canSetPrimary && !addr.is_primary && !readonly"
          :icon="smAndDown"
          variant="tonal"
          color="warning"
          :aria-label="t('Set as Main Address')"
          @click="handleSetPrimary"
        >
          <VIcon icon="tabler-star" />
          <span
            v-if="!smAndDown"
            class="ms-1"
          >{{ t('Set as Main Address') }}</span>
          <VTooltip
            activator="parent"
            location="top"
          >
            {{ t('Set as Main Address') }}
          </VTooltip>
        </VBtn>

        <VBtn
          v-if="canEdit && !readonly"
          :icon="smAndDown"
          color="primary"
          :aria-label="t('Edit Address')"
          @click="handleEdit"
        >
          <VIcon icon="tabler-edit" />
          <span
            v-if="!smAndDown"
            class="ms-1"
          >{{ t('Edit Address') }}</span>
          <VTooltip
            activator="parent"
            location="top"
          >
            {{ t('Edit Address') }}
          </VTooltip>
        </VBtn>
      </WpActionFooter>
    </VCard>
  </VDialog>
</template>

<style scoped>
.wp-view-dialog__card {
  position: relative;
  overflow: hidden;
  max-block-size: min(92dvh, 840px);
}

.wp-view-dialog__header {
  flex: 0 0 auto;
  background:
    radial-gradient(ellipse at 100% 0%, rgba(var(--v-theme-primary), 0.12), transparent 60%),
    rgba(var(--v-theme-primary), 0.02);
  padding-block: 16px 14px;
  padding-inline: 20px;
}

.wp-view-dialog__avatar {
  box-shadow: 0 6px 18px rgba(var(--v-theme-primary), 0.25);
}

.wp-view-dialog__close-btn {
  transition: transform 0.2s ease, background-color 0.2s ease;
}

.wp-view-dialog__close-btn:hover {
  transform: scale(1.05);
}

.wp-view-dialog__body {
  --wp-edge: rgb(var(--v-theme-surface));
  --wp-edge-shade: rgba(var(--v-theme-on-surface), 0.14);

  overflow: hidden auto;
  flex: 1 1 auto;
  padding: 20px;
  background-attachment: local, local, scroll, scroll;
  background-image:
    linear-gradient(var(--wp-edge) 30%, transparent),
    linear-gradient(transparent, var(--wp-edge) 70%),
    radial-gradient(farthest-side at 50% 0, var(--wp-edge-shade), transparent),
    radial-gradient(farthest-side at 50% 100%, var(--wp-edge-shade), transparent);
  background-position: center top, center bottom, center top, center bottom;
  background-repeat: no-repeat;
  background-size: 100% 24px, 100% 24px, 100% 10px, 100% 10px;
  min-inline-size: 0;
  -webkit-overflow-scrolling: touch;
}

.wp-view-dialog__section {
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.wp-view-dialog__section-header {
  background:
    radial-gradient(ellipse at 100% 0%, rgba(var(--v-theme-primary), 0.08), transparent 60%),
    rgba(var(--v-theme-primary), 0.02);
}

/* Grid layout matching WpWizardReview: zero negative margin, responsive wrap */
.wp-view-dialog__list {
  display: grid;
  margin: 0;
  gap: 14px 20px;
  grid-template-columns: repeat(auto-fill, minmax(min(200px, 100%), 1fr));
}

.wp-view-dialog__row {
  min-inline-size: 0;
  overflow-wrap: anywhere;
}

.wp-view-dialog__dt {
  font-size: 0.75rem;
  letter-spacing: 0.2px;
  line-height: 1.3;
  margin-block-end: 4px;
}

.wp-view-dialog__dd {
  color: rgb(var(--v-theme-on-surface));
  line-height: 1.45;
}

@media (max-width: 599.98px) {
  .wp-view-dialog__card {
    border-radius: 0 !important;
    block-size: 100dvh !important;
    max-block-size: 100dvh !important;
    padding-block-end: env(safe-area-inset-bottom);
  }

  .wp-view-dialog__header {
    padding-block: calc(12px + env(safe-area-inset-top)) 10px !important;
    padding-inline: 14px !important;
  }

  .wp-view-dialog__body {
    padding: 14px !important;
  }

  .wp-view-dialog__list {
    gap: 12px 14px;
  }

  .wp-view-dialog__footer :deep(.wp-action-footer__actions) {
    justify-content: flex-end !important;
    gap: 8px !important;
    flex-wrap: nowrap !important;
  }

  .wp-view-dialog__footer :deep(.v-btn--icon) {
    flex: 0 0 auto !important;
    inline-size: 38px !important;
    block-size: 38px !important;
    min-inline-size: 38px !important;
  }
}
</style>
