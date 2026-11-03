<script setup>
/* eslint-disable lines-around-comment, camelcase, vue/custom-event-name-casing */
/**
 * AddEditAddressDialog
 * ─────────────────────────────────────────────────────────────────────────────
 * Wizard-based dialog (Standard mode) for creating and editing Business Partner
 * addresses.
 *
 * Steps:
 * 1. Type & Roles (Harmonized address types with PerfectScrollbar + clickable role cards)
 * 2. Address & Geolocation (Street, City, Postal Code, Country + Map with Locate buttons)
 * 3. Additional Data (Phone, Email, Fire Station, Notes)
 * 4. Attach Contacts (Unified role assignment: Main Responsible, Work Team, or Unassigned)
 * 5. Review (Standard review step matching WAVE PLUS design standards)
 */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { MultiLangField, WpWizardDialog } from '@/components/shared'
import LocationPicker from '@/components/shared/LocationPicker.vue'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { getAddressTypes, getFireStations } from '@/utils/bp-constants'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  addressData: { type: Object, default: () => null },
  contactData: { type: Array, default: () => [] },
  isEditMode: { type: Boolean, default: false },
})

const emit = defineEmits(['update:isDialogVisible', 'submit'])

const { t } = useI18n()

const isOpen = computed({
  get: () => props.isDialogVisible,
  set: val => emit('update:isDialogVisible', val),
})

const allAddressTypes = computed(() => getAddressTypes())
const fireStationOptions = computed(() => getFireStations())
const locationPickerRef = ref(null)

const contactOptions = computed(() => {
  return props.contactData.map(c => {
    const fn = localizedValue(c.first_name)
    const ln = localizedValue(c.last_name)
    const fullName = [fn, ln].filter(Boolean).join(' ') || c.name || c.email || `Contact #${c.id}`

    return {
      title: fullName,
      value: fullName,
      id: c.id,
      email: c.email,
      phone: c.phone || c.mobile,
      jobTitle: c.job_title,
    }
  })
})

const initialForm = ref({
  type: 'headquarters',
  label: { en: '', fr: '', ar: '' },
  is_primary: false,
  is_billing_default: false,
  is_shipping_default: false,
  addressStreet: { en: '', fr: '', ar: '' },
  addressStreet2: { en: '', fr: '', ar: '' },
  city: { en: '', fr: '', ar: '' },
  state: { en: '', fr: '', ar: '' },
  postcode: '',
  country: { en: 'Tunisia', fr: 'Tunisie', ar: 'تونس' },
  phone: '',
  email: '',
  nearest_fire_station: '',
  notes: { en: '', fr: '', ar: '' },
  latitude: null,
  longitude: null,
  responsible: '',
  workTeam: [],
  attachedContactIds: [],
})

watch(() => props.addressData, val => {
  if (val) {
    const rawWorkTeam = val.work_team || val.workTeam
    let parsedWorkTeam = []
    if (Array.isArray(rawWorkTeam)) {
      parsedWorkTeam = rawWorkTeam
    } else if (typeof rawWorkTeam === 'string' && rawWorkTeam.trim()) {
      parsedWorkTeam = rawWorkTeam.split(',').map(s => s.trim()).filter(Boolean)
    }

    let attached = []
    if (Array.isArray(val.contacts)) {
      attached = val.contacts.map(c => c.id)
    } else if (Array.isArray(val.contact_ids)) {
      attached = val.contact_ids
    }

    initialForm.value = {
      id: val.id,
      type: val.type || 'headquarters',
      label: ensureTranslatable(val.label),
      is_primary: Boolean(val.is_primary),
      is_billing_default: Boolean(val.is_billing_default),
      is_shipping_default: Boolean(val.is_shipping_default),
      addressStreet: ensureTranslatable(val.address_street ?? val.addressStreet),
      addressStreet2: ensureTranslatable(val.address_street2 ?? val.addressStreet2),
      city: ensureTranslatable(val.city),
      state: ensureTranslatable(val.state),
      postcode: val.postcode || '',
      country: ensureTranslatable(val.country ?? { en: 'Tunisia', fr: 'Tunisie', ar: 'تونس' }),
      phone: val.phone || '',
      email: val.email || '',
      nearest_fire_station: val.nearest_fire_station || val.nearestFireStation || '',
      notes: ensureTranslatable(val.notes),
      latitude: val.latitude != null ? Number(val.latitude) : null,
      longitude: val.longitude != null ? Number(val.longitude) : null,
      responsible: val.responsible ? (typeof val.responsible === 'object' ? `${localizedValue(val.responsible.first_name)} ${localizedValue(val.responsible.last_name)}`.trim() : String(val.responsible)) : '',
      workTeam: parsedWorkTeam,
      attachedContactIds: attached,
    }
  } else {
    initialForm.value = {
      type: 'headquarters',
      label: { en: '', fr: '', ar: '' },
      is_primary: false,
      is_billing_default: false,
      is_shipping_default: false,
      addressStreet: { en: '', fr: '', ar: '' },
      addressStreet2: { en: '', fr: '', ar: '' },
      city: { en: '', fr: '', ar: '' },
      state: { en: '', fr: '', ar: '' },
      postcode: '',
      country: { en: 'Tunisia', fr: 'Tunisie', ar: 'تونس' },
      phone: '',
      email: '',
      nearest_fire_station: '',
      notes: { en: '', fr: '', ar: '' },
      latitude: null,
      longitude: null,
      responsible: '',
      workTeam: [],
      attachedContactIds: [],
    }
  }
}, { immediate: true })

const steps = computed(() => [
  {
    id: 'type-role',
    title: t('Type & Roles'),
    description: t('Select address classification and operational roles'),
    icon: 'tabler-category',
    validate: form => {
      const errors = {}
      if (!form.type) errors.type = t('Address type is required')

      return errors
    },
  },
  {
    id: 'address-data',
    title: t('Address & Map'),
    description: t('Street location, postal code and GIS map positioning'),
    icon: 'tabler-map-pin',
    validate: form => {
      const errors = {}
      const st = form.addressStreet
      const hasStreet = typeof st === 'string' ? st.trim() : (st && (st.en || st.fr || st.ar))
      if (!hasStreet) errors.addressStreet = t('Street address is required')

      const ct = form.city
      const hasCity = typeof ct === 'string' ? ct.trim() : (ct && (ct.en || ct.fr || ct.ar))
      if (!hasCity) errors.city = t('City is required')

      return errors
    },
  },
  {
    id: 'additional-data',
    title: t('Additional Info'),
    description: t('Contact numbers, emergency station and access notes'),
    icon: 'tabler-info-circle',
  },
  {
    id: 'contacts',
    title: t('Contacts & Team'),
    description: t('Assign main responsible person and site team'),
    icon: 'tabler-users',
  },
])

const getTypeMeta = type => {
  const map = {
    headquarters: { icon: 'tabler-building-skyscraper', color: 'primary' },
    office: { icon: 'tabler-building', color: 'info' },
    branch: { icon: 'tabler-building-community', color: 'info' },
    billing: { icon: 'tabler-receipt', color: 'secondary' },
    shipping: { icon: 'tabler-truck-delivery', color: 'success' },
    factory: { icon: 'tabler-building-factory-2', color: 'primary' },
    workshop: { icon: 'tabler-tools', color: 'warning' },
    industrial: { icon: 'tabler-bolt', color: 'error' },
    'construction_site': { icon: 'tabler-cone', color: 'warning' },
    warehouse: { icon: 'tabler-building-warehouse', color: 'success' },
    depot: { icon: 'tabler-car-garage', color: 'success' },
    retail: { icon: 'tabler-building-store', color: 'secondary' },
    hotel: { icon: 'tabler-bed', color: 'primary' },
    restaurant: { icon: 'tabler-tools-kitchen-2', color: 'warning' },
    government: { icon: 'tabler-building-monument', color: 'secondary' },
    hospital: { icon: 'tabler-building-hospital', color: 'error' },
    school: { icon: 'tabler-school', color: 'warning' },
    datacenter: { icon: 'tabler-server', color: 'info' },
    residential: { icon: 'tabler-home', color: 'info' },
    'operational_site': { icon: 'tabler-map-pin', color: 'warning' },
    other: { icon: 'tabler-map-pin', color: 'secondary' },
  }

  return map[type] || { icon: 'tabler-map-pin', color: 'secondary' }
}

const getAddressQuery = form => {
  const street = localizedValue(form.addressStreet)
  const street2 = localizedValue(form.addressStreet2)
  const city = localizedValue(form.city)
  const state = localizedValue(form.state)
  const postcode = form.postcode
  const country = localizedValue(form.country)

  return [street, street2, city, state, postcode, country].filter(Boolean).join(', ')
}

const locateOnMap = form => {
  const query = getAddressQuery(form)
  if (!query) return
  locationPickerRef.value?.locateAddress()
}

const useCurrentLocation = () => {
  locationPickerRef.value?.useCurrentLocation()
}

// ── Unified Contact Assignment ──────────────────────────────────────────
const getContactRole = (cId, form) => {
  const contact = props.contactData.find(c => c.id === cId)
  if (!contact) return 'none'

  const fn = localizedValue(contact.first_name)
  const ln = localizedValue(contact.last_name)
  const name = [fn, ln].filter(Boolean).join(' ') || contact.name || contact.email

  if (form.responsible && form.responsible === name) return 'responsible'
  if (form.workTeam?.includes(name)) return 'team'
  if (form.attachedContactIds?.includes(cId)) return 'team'

  return 'none'
}

const setContactRole = (cId, role, form, setValue) => {
  const contact = props.contactData.find(c => c.id === cId)
  if (!contact) return

  const fn = localizedValue(contact.first_name)
  const ln = localizedValue(contact.last_name)
  const name = [fn, ln].filter(Boolean).join(' ') || contact.name || contact.email

  let attached = [...(form.attachedContactIds || [])]
  let workTeam = [...(form.workTeam || [])]
  let responsible = form.responsible || ''

  if (role === 'none') {
    attached = attached.filter(id => id !== cId)
    workTeam = workTeam.filter(n => n !== name)
    if (responsible === name) responsible = ''
  } else if (role === 'team') {
    if (!attached.includes(cId)) attached.push(cId)
    if (!workTeam.includes(name)) workTeam.push(name)
    if (responsible === name) responsible = ''
  } else if (role === 'responsible') {
    if (!attached.includes(cId)) attached.push(cId)
    // If someone else was responsible, demote them to team
    if (responsible && responsible !== name && !workTeam.includes(responsible)) {
      workTeam.push(responsible)
    }
    responsible = name
    workTeam = workTeam.filter(n => n !== name)
  }

  setValue('attachedContactIds', attached)
  setValue('workTeam', workTeam)
  setValue('responsible', responsible)
}

const handleSubmit = async (payload, formValues) => {
  const form = formValues || payload

  const result = {
    id: form.id,
    type: form.type,
    label: form.label,
    address_street: form.addressStreet,
    address_street2: form.addressStreet2,
    city: form.city,
    state: form.state,
    postcode: form.postcode,
    country: form.country,
    phone: form.phone,
    email: form.email,
    nearest_fire_station: form.nearest_fire_station,
    notes: form.notes,
    is_primary: Boolean(form.is_primary),
    is_billing_default: Boolean(form.is_billing_default),
    is_shipping_default: Boolean(form.is_shipping_default),
    latitude: form.latitude != null ? Number(form.latitude) : null,
    longitude: form.longitude != null ? Number(form.longitude) : null,
    responsible: form.responsible || null,
    work_team: Array.isArray(form.workTeam) ? (form.workTeam.length ? form.workTeam.join(', ') : null) : (form.workTeam || null),
    contact_ids: Array.isArray(form.attachedContactIds) ? form.attachedContactIds : [],
  }

  emit('submit', result)
  isOpen.value = false
}
</script>

<template>
  <WpWizardDialog
    v-model="isOpen"
    variant="deeper"
    :title="isEditMode ? t('Edit Address') : t('Add New Address')"
    :icon="isEditMode ? 'tabler-edit' : 'tabler-map-pin-plus'"
    :steps="steps"
    :initial-values="initialForm"
    review
    :max-width="960"
    :submit="handleSubmit"
    :submit-label="isEditMode ? t('Update Address') : t('Save Address')"
  >
    <!-- ── Step 1: Type & Roles ────────────────────────────────────────── -->
    <template #step-type-role="{ form, setValue }">
      <div class="py-2">
        <div class="mb-4">
          <label class="text-subtitle-2 font-weight-bold d-block mb-1">
            {{ t('Select Address Classification') }} *
          </label>
          <div class="text-caption text-medium-emphasis mb-3">
            {{ t('Choose the type that best describes the facility or premises.') }}
          </div>

          <!-- PerfectScrollbar for smooth, flawless scrolling -->
          <PerfectScrollbar
            class="address-type-scrollbar"
            :options="{ wheelPropagation: false, suppressScrollX: true }"
          >
            <div class="address-type-grid">
              <div
                v-for="item in allAddressTypes"
                :key="item.value"
                class="address-type-card cursor-pointer"
                :class="{ 'is-selected': form.type === item.value }"
                @click="setValue('type', item.value)"
              >
                <VAvatar
                  :color="getTypeMeta(item.value).color"
                  :variant="form.type === item.value ? 'elevated' : 'tonal'"
                  size="36"
                  rounded="lg"
                  class="flex-shrink-0"
                >
                  <VIcon
                    :icon="getTypeMeta(item.value).icon"
                    size="20"
                  />
                </VAvatar>
                <span class="text-body-2 font-weight-medium text-truncate">
                  {{ item.title }}
                </span>
              </div>
            </div>
          </PerfectScrollbar>
        </div>

        <VDivider class="my-5" />

        <!-- Roles: Clean Clickable Cards (No Checkbox) -->
        <div class="mb-4">
          <label class="text-subtitle-2 font-weight-bold d-block mb-2">
            {{ t('Operational Roles') }}
          </label>
          <VRow>
            <!-- Main Address Card -->
            <VCol
              cols="12"
              sm="4"
            >
              <VCard
                variant="flat"
                border
                rounded="lg"
                class="pa-3 cursor-pointer role-card"
                :class="{ 'role-card--active': form.is_primary }"
                @click="setValue('is_primary', !form.is_primary)"
              >
                <div class="d-flex align-center justify-space-between mb-2">
                  <VAvatar
                    size="34"
                    color="primary"
                    :variant="form.is_primary ? 'elevated' : 'tonal'"
                    rounded="lg"
                  >
                    <VIcon
                      icon="tabler-star"
                      size="18"
                    />
                  </VAvatar>
                  <VIcon
                    v-if="form.is_primary"
                    icon="tabler-circle-check-filled"
                    color="primary"
                    size="20"
                  />
                </div>
                <div class="text-body-2 font-weight-bold">
                  {{ t('Main Address') }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ t('Headquarters / HQ') }}
                </div>
              </VCard>
            </VCol>

            <!-- Billing Default Card -->
            <VCol
              cols="12"
              sm="4"
            >
              <VCard
                variant="flat"
                border
                rounded="lg"
                class="pa-3 cursor-pointer role-card"
                :class="{ 'role-card--active': form.is_billing_default }"
                @click="setValue('is_billing_default', !form.is_billing_default)"
              >
                <div class="d-flex align-center justify-space-between mb-2">
                  <VAvatar
                    size="34"
                    color="info"
                    :variant="form.is_billing_default ? 'elevated' : 'tonal'"
                    rounded="lg"
                  >
                    <VIcon
                      icon="tabler-receipt"
                      size="18"
                    />
                  </VAvatar>
                  <VIcon
                    v-if="form.is_billing_default"
                    icon="tabler-circle-check-filled"
                    color="info"
                    size="20"
                  />
                </div>
                <div class="text-body-2 font-weight-bold">
                  {{ t('Billing Default') }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ t('Invoicing target') }}
                </div>
              </VCard>
            </VCol>

            <!-- Shipping Default Card -->
            <VCol
              cols="12"
              sm="4"
            >
              <VCard
                variant="flat"
                border
                rounded="lg"
                class="pa-3 cursor-pointer role-card"
                :class="{ 'role-card--active': form.is_shipping_default }"
                @click="setValue('is_shipping_default', !form.is_shipping_default)"
              >
                <div class="d-flex align-center justify-space-between mb-2">
                  <VAvatar
                    size="34"
                    color="success"
                    :variant="form.is_shipping_default ? 'elevated' : 'tonal'"
                    rounded="lg"
                  >
                    <VIcon
                      icon="tabler-truck"
                      size="18"
                    />
                  </VAvatar>
                  <VIcon
                    v-if="form.is_shipping_default"
                    icon="tabler-circle-check-filled"
                    color="success"
                    size="20"
                  />
                </div>
                <div class="text-body-2 font-weight-bold">
                  {{ t('Shipping Default') }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ t('Delivery target') }}
                </div>
              </VCard>
            </VCol>
          </VRow>
        </div>

        <!-- Optional Label -->
        <div class="mt-4">
          <MultiLangField
            :model-value="form.label"
            :label="t('Address Label / Site Name (Optional)')"
            variant="outlined"
            @update:model-value="val => setValue('label', val)"
          />
        </div>
      </div>
    </template>

    <!-- ── Step 2: Address Data & GIS Map ──────────────────────────────── -->
    <template #step-address-data="{ form, errors, setValue }">
      <div class="py-2">
        <VRow>
          <VCol cols="12">
            <MultiLangField
              :model-value="form.addressStreet"
              :label="t('Street Address') + ' *'"
              :error-messages="errors.addressStreet"
              variant="outlined"
              @update:model-value="val => setValue('addressStreet', val)"
            />
          </VCol>

          <VCol cols="12">
            <MultiLangField
              :model-value="form.addressStreet2"
              :label="t('Street Address 2 (Optional)')"
              variant="outlined"
              @update:model-value="val => setValue('addressStreet2', val)"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <MultiLangField
              :model-value="form.city"
              :label="t('City') + ' *'"
              :error-messages="errors.city"
              variant="outlined"
              @update:model-value="val => setValue('city', val)"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              :model-value="form.postcode"
              :label="t('Postal Code')"
              variant="outlined"
              prepend-inner-icon="tabler-mailbox"
              @update:model-value="val => setValue('postcode', val)"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <MultiLangField
              :model-value="form.state"
              :label="t('State / Province / Region')"
              variant="outlined"
              @update:model-value="val => setValue('state', val)"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <MultiLangField
              :model-value="form.country"
              :label="t('Country')"
              variant="outlined"
              @update:model-value="val => setValue('country', val)"
            />
          </VCol>

          <!-- GIS Map & Positioning (Integrated in Address Details Step) -->
          <VCol cols="12">
            <VCard
              variant="flat"
              border
              rounded="lg"
              class="pa-3 mt-2"
            >
              <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3">
                <div class="d-flex align-center gap-2">
                  <VIcon
                    icon="tabler-map-pin"
                    color="primary"
                    size="20"
                  />
                  <span class="text-subtitle-2 font-weight-bold">
                    {{ t('GIS Map Coordinates') }}
                  </span>
                  <VChip
                    v-if="form.latitude && form.longitude"
                    size="x-small"
                    color="success"
                    variant="tonal"
                    label
                  >
                    {{ Number(form.latitude).toFixed(4) }}, {{ Number(form.longitude).toFixed(4) }}
                  </VChip>
                </div>

                <div class="d-flex align-center gap-2">
                  <VBtn
                    size="small"
                    variant="tonal"
                    color="primary"
                    prepend-icon="tabler-map-search"
                    @click="locateOnMap(form)"
                  >
                    {{ t('Locate on Map') }}
                  </VBtn>

                  <VBtn
                    size="small"
                    variant="tonal"
                    color="secondary"
                    prepend-icon="tabler-current-location"
                    @click="useCurrentLocation"
                  >
                    {{ t('My Location') }}
                  </VBtn>
                </div>
              </div>

              <div
                class="rounded-lg overflow-hidden border"
                style="height: 240px;"
              >
                <LocationPicker
                  ref="locationPickerRef"
                  :latitude="form.latitude"
                  :longitude="form.longitude"
                  :address-query="getAddressQuery(form)"
                  height="240"
                  @update:latitude="lat => setValue('latitude', lat)"
                  @update:longitude="lng => setValue('longitude', lng)"
                />
              </div>
            </VCard>
          </VCol>
        </VRow>
      </div>
    </template>

    <!-- ── Step 3: Additional Data ─────────────────────────────────────── -->
    <template #step-additional-data="{ form, setValue }">
      <div class="py-2">
        <VRow>
          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              :model-value="form.phone"
              :label="t('Phone')"
              variant="outlined"
              prepend-inner-icon="tabler-phone"
              @update:model-value="val => setValue('phone', val)"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              :model-value="form.email"
              :label="t('Email')"
              variant="outlined"
              prepend-inner-icon="tabler-mail"
              @update:model-value="val => setValue('email', val)"
            />
          </VCol>

          <VCol cols="12">
            <AppSelect
              :model-value="form.nearest_fire_station"
              :label="t('Nearest Fire Station')"
              :items="fireStationOptions"
              clearable
              variant="outlined"
              prepend-inner-icon="tabler-flame"
              @update:model-value="val => setValue('nearest_fire_station', val)"
            />
          </VCol>

          <VCol cols="12">
            <MultiLangField
              :model-value="form.notes"
              :label="t('Notes & Access Instructions')"
              variant="outlined"
              multiline
              @update:model-value="val => setValue('notes', val)"
            />
          </VCol>
        </VRow>
      </div>
    </template>

    <!-- ── Step 4: Attach Contacts (Unified Role Assignment) ───────────── -->
    <template #step-contacts="{ form, setValue }">
      <div class="py-2">
        <div class="mb-4">
          <h4 class="text-subtitle-1 font-weight-bold mb-1">
            {{ t('Contact & Team Assignment') }}
          </h4>
          <p class="text-caption text-medium-emphasis mb-3">
            {{ t('Assign partner contacts directly to this location. Designate a Main Responsible person and/or operational Work Team members.') }}
          </p>

          <div
            v-if="contactOptions.length === 0"
            class="text-center py-8 text-medium-emphasis"
          >
            <VIcon
              icon="tabler-user-off"
              size="40"
              class="mb-2"
            />
            <div class="text-body-2">
              {{ t('No partner contacts available yet.') }}
            </div>
          </div>

          <div
            v-else
            class="contact-assignment-list d-flex flex-column gap-2"
          >
            <div
              v-for="c in contactOptions"
              :key="c.id"
              class="contact-assignment-card pa-3 border rounded-lg d-flex align-center justify-space-between flex-wrap gap-2"
              :class="{
                'contact-assignment-card--responsible': getContactRole(c.id, form) === 'responsible',
                'contact-assignment-card--team': getContactRole(c.id, form) === 'team',
              }"
            >
              <div class="d-flex align-center gap-3 min-w-0">
                <VAvatar
                  size="40"
                  rounded="lg"
                  :color="getContactRole(c.id, form) === 'responsible' ? 'warning' : getContactRole(c.id, form) === 'team' ? 'primary' : 'secondary'"
                  :variant="getContactRole(c.id, form) !== 'none' ? 'elevated' : 'tonal'"
                >
                  <VIcon
                    :icon="getContactRole(c.id, form) === 'responsible' ? 'tabler-star' : 'tabler-user'"
                    size="20"
                  />
                </VAvatar>
                <div class="min-w-0">
                  <div class="text-body-2 font-weight-bold text-truncate">
                    {{ c.title }}
                  </div>
                  <div class="text-caption text-medium-emphasis text-truncate">
                    {{ c.jobTitle || c.email || '—' }}
                  </div>
                </div>
              </div>

              <!-- Segmented Role Selector -->
              <div class="d-flex align-center gap-1">
                <VChip
                  size="small"
                  :color="getContactRole(c.id, form) === 'responsible' ? 'warning' : 'secondary'"
                  :variant="getContactRole(c.id, form) === 'responsible' ? 'elevated' : 'tonal'"
                  class="cursor-pointer"
                  label
                  @click="setContactRole(c.id, getContactRole(c.id, form) === 'responsible' ? 'none' : 'responsible', form, setValue)"
                >
                  <VIcon
                    icon="tabler-star-filled"
                    size="13"
                    start
                  />
                  {{ t('Responsible') }}
                </VChip>

                <VChip
                  size="small"
                  :color="getContactRole(c.id, form) === 'team' ? 'primary' : 'secondary'"
                  :variant="getContactRole(c.id, form) === 'team' ? 'elevated' : 'tonal'"
                  class="cursor-pointer"
                  label
                  @click="setContactRole(c.id, getContactRole(c.id, form) === 'team' ? 'none' : 'team', form, setValue)"
                >
                  <VIcon
                    icon="tabler-users"
                    size="13"
                    start
                  />
                  {{ t('Team Member') }}
                </VChip>

                <VBtn
                  v-if="getContactRole(c.id, form) !== 'none'"
                  icon="tabler-x"
                  size="x-small"
                  variant="text"
                  color="secondary"
                  :title="t('Unassign')"
                  @click="setContactRole(c.id, 'none', form, setValue)"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- ── Step 5: Standard Review Step (Matching EquipmentWizardDialog & WpWizardReview) ── -->
    <template #review="{ form, goToStep }">
      <div class="py-1 d-flex flex-column gap-4">
        <!-- Ready Summary Banner -->
        <div
          class="wp-wizard-review__summary is-ready d-flex align-center gap-3 pa-4 rounded-lg border bg-surface"
          role="status"
        >
          <VAvatar
            color="success"
            variant="tonal"
            rounded="lg"
            size="42"
            class="flex-shrink-0"
          >
            <VIcon
              icon="tabler-circle-check"
              size="24"
            />
          </VAvatar>
          <div class="flex-grow-1 min-w-0">
            <div class="text-subtitle-1 font-weight-bold">
              {{ t('wp.wizard.review_ready') }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ t('wp.wizard.review_hint') }}
            </div>
          </div>
        </div>

        <!-- Step 1 Card: Classification & Purpose -->
        <VCard
          variant="flat"
          border
          rounded="lg"
          class="overflow-hidden"
        >
          <div class="d-flex align-center gap-3 px-4 px-sm-5 py-3">
            <VAvatar
              color="primary"
              variant="tonal"
              rounded="lg"
              size="38"
              class="flex-shrink-0"
            >
              <VIcon
                icon="tabler-category"
                size="20"
              />
            </VAvatar>
            <div class="flex-grow-1 min-w-0">
              <h3 class="text-subtitle-1 font-weight-bold mb-0 text-truncate">
                {{ t('Type & Roles') }}
              </h3>
              <div class="text-caption text-medium-emphasis text-truncate">
                {{ t('Address classification and operational roles') }}
              </div>
            </div>
            <VChip
              size="x-small"
              variant="tonal"
              color="primary"
              class="font-weight-medium flex-shrink-0"
            >
              {{ t('Step 1 of 4') }}
            </VChip>
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-edit"
              class="flex-shrink-0"
              @click="goToStep(0)"
            >
              {{ t('wp.wizard.edit_step') }}
            </VBtn>
          </div>
          <VDivider />
          <div class="px-4 px-sm-5 py-3">
            <VRow density="comfortable">
              <VCol
                cols="12"
                sm="4"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Classification Type') }}
                </div>
                <VChip
                  :color="getTypeMeta(form.type).color"
                  size="small"
                  label
                  class="text-capitalize font-weight-medium"
                >
                  <VIcon
                    :icon="getTypeMeta(form.type).icon"
                    size="14"
                    start
                  />
                  {{ allAddressTypes.find(a => a.value === form.type)?.title || form.type }}
                </VChip>
              </VCol>

              <VCol
                cols="12"
                sm="4"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Operational Roles') }}
                </div>
                <div class="d-flex flex-wrap gap-1">
                  <VChip
                    v-if="form.is_primary"
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
                    v-if="form.is_billing_default"
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
                    v-if="form.is_shipping_default"
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
                    v-if="!form.is_primary && !form.is_billing_default && !form.is_shipping_default"
                    class="text-body-2 text-medium-emphasis"
                  >
                    {{ t('Standard (No operational role)') }}
                  </span>
                </div>
              </VCol>

              <VCol
                cols="12"
                sm="4"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Label / Site Name') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ localizedValue(form.label) || '—' }}
                </div>
              </VCol>
            </VRow>
          </div>
        </VCard>

        <!-- Step 2 Card: Address Location & GIS Map -->
        <VCard
          variant="flat"
          border
          rounded="lg"
          class="overflow-hidden"
        >
          <div class="d-flex align-center gap-3 px-4 px-sm-5 py-3">
            <VAvatar
              color="primary"
              variant="tonal"
              rounded="lg"
              size="38"
              class="flex-shrink-0"
            >
              <VIcon
                icon="tabler-map-pin"
                size="20"
              />
            </VAvatar>
            <div class="flex-grow-1 min-w-0">
              <h3 class="text-subtitle-1 font-weight-bold mb-0 text-truncate">
                {{ t('Address & Map') }}
              </h3>
              <div class="text-caption text-medium-emphasis text-truncate">
                {{ t('Street location, postal code and GIS coordinates') }}
              </div>
            </div>
            <VChip
              size="x-small"
              variant="tonal"
              color="primary"
              class="font-weight-medium flex-shrink-0"
            >
              {{ t('Step 2 of 4') }}
            </VChip>
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-edit"
              class="flex-shrink-0"
              @click="goToStep(1)"
            >
              {{ t('wp.wizard.edit_step') }}
            </VBtn>
          </div>
          <VDivider />
          <div class="px-4 px-sm-5 py-3">
            <VRow density="comfortable">
              <VCol
                cols="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Street Address') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ localizedValue(form.addressStreet) || '—' }}
                </div>
                <div
                  v-if="localizedValue(form.addressStreet2)"
                  class="text-caption text-medium-emphasis mt-1"
                >
                  {{ localizedValue(form.addressStreet2) }}
                </div>
              </VCol>

              <VCol
                cols="12"
                sm="3"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('City & Postcode') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ [localizedValue(form.city), form.postcode].filter(Boolean).join(' ') || '—' }}
                </div>
              </VCol>

              <VCol
                cols="12"
                sm="3"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('State & Country') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ [localizedValue(form.state), localizedValue(form.country)].filter(Boolean).join(', ') || '—' }}
                </div>
              </VCol>

              <VCol
                v-if="form.latitude && form.longitude"
                cols="12"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('GIS Coordinates') }}
                </div>
                <div class="d-flex align-center gap-2">
                  <VChip
                    size="small"
                    variant="tonal"
                    color="primary"
                    prepend-icon="tabler-map-pin"
                    label
                  >
                    {{ Number(form.latitude).toFixed(5) }}, {{ Number(form.longitude).toFixed(5) }}
                  </VChip>
                </div>
              </VCol>
            </VRow>
          </div>
        </VCard>

        <!-- Step 3 Card: Additional Info -->
        <VCard
          variant="flat"
          border
          rounded="lg"
          class="overflow-hidden"
        >
          <div class="d-flex align-center gap-3 px-4 px-sm-5 py-3">
            <VAvatar
              color="primary"
              variant="tonal"
              rounded="lg"
              size="38"
              class="flex-shrink-0"
            >
              <VIcon
                icon="tabler-info-circle"
                size="20"
              />
            </VAvatar>
            <div class="flex-grow-1 min-w-0">
              <h3 class="text-subtitle-1 font-weight-bold mb-0 text-truncate">
                {{ t('Additional Info') }}
              </h3>
              <div class="text-caption text-medium-emphasis text-truncate">
                {{ t('Contact numbers, emergency station and access notes') }}
              </div>
            </div>
            <VChip
              size="x-small"
              variant="tonal"
              color="primary"
              class="font-weight-medium flex-shrink-0"
            >
              {{ t('Step 3 of 4') }}
            </VChip>
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-edit"
              class="flex-shrink-0"
              @click="goToStep(2)"
            >
              {{ t('wp.wizard.edit_step') }}
            </VBtn>
          </div>
          <VDivider />
          <div class="px-4 px-sm-5 py-3">
            <VRow density="comfortable">
              <VCol
                cols="12"
                sm="4"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Phone') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ form.phone || '—' }}
                </div>
              </VCol>

              <VCol
                cols="12"
                sm="4"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Email') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ form.email || '—' }}
                </div>
              </VCol>

              <VCol
                cols="12"
                sm="4"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Nearest Fire Station') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ form.nearest_fire_station || '—' }}
                </div>
              </VCol>

              <VCol
                v-if="localizedValue(form.notes)"
                cols="12"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Notes & Access Instructions') }}
                </div>
                <div class="text-body-2">
                  {{ localizedValue(form.notes) }}
                </div>
              </VCol>
            </VRow>
          </div>
        </VCard>

        <!-- Step 4 Card: Contacts & Responsibility -->
        <VCard
          variant="flat"
          border
          rounded="lg"
          class="overflow-hidden"
        >
          <div class="d-flex align-center gap-3 px-4 px-sm-5 py-3">
            <VAvatar
              color="primary"
              variant="tonal"
              rounded="lg"
              size="38"
              class="flex-shrink-0"
            >
              <VIcon
                icon="tabler-users"
                size="20"
              />
            </VAvatar>
            <div class="flex-grow-1 min-w-0">
              <h3 class="text-subtitle-1 font-weight-bold mb-0 text-truncate">
                {{ t('Contacts & Team') }}
              </h3>
              <div class="text-caption text-medium-emphasis text-truncate">
                {{ t('Assigned main responsible person and site team') }}
              </div>
            </div>
            <VChip
              size="x-small"
              variant="tonal"
              color="primary"
              class="font-weight-medium flex-shrink-0"
            >
              {{ t('Step 4 of 4') }}
            </VChip>
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-edit"
              class="flex-shrink-0"
              @click="goToStep(3)"
            >
              {{ t('wp.wizard.edit_step') }}
            </VBtn>
          </div>
          <VDivider />
          <div class="px-4 px-sm-5 py-3">
            <VRow density="comfortable">
              <VCol
                cols="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Main Responsible Person') }}
                </div>
                <div
                  v-if="form.responsible"
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
                  <span class="text-body-2 font-weight-medium">{{ form.responsible }}</span>
                </div>
                <div
                  v-else
                  class="text-body-2 text-medium-emphasis"
                >
                  {{ t('No responsible person assigned') }}
                </div>
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('Work Team Members') }}
                </div>
                <div
                  v-if="form.workTeam?.length"
                  class="d-flex flex-wrap gap-1"
                >
                  <VChip
                    v-for="(member, mIdx) in form.workTeam"
                    :key="mIdx"
                    size="small"
                    color="primary"
                    variant="tonal"
                    label
                  >
                    {{ member }}
                  </VChip>
                </div>
                <div
                  v-else
                  class="text-body-2 text-medium-emphasis"
                >
                  {{ t('No team members assigned') }}
                </div>
              </VCol>
            </VRow>
          </div>
        </VCard>

        <!-- Bottom Address Summary Tonal Card (Matching Image 3 Equipment Review Summary) -->
        <VCard
          variant="tonal"
          color="primary"
          class="review-summary pa-4 rounded-lg mt-2"
        >
          <div class="d-flex align-center justify-space-between flex-wrap gap-3">
            <div class="d-flex align-center gap-3 min-w-0">
              <VAvatar
                size="46"
                color="primary"
                variant="elevated"
                rounded="lg"
              >
                <VIcon
                  :icon="getTypeMeta(form.type).icon"
                  size="26"
                />
              </VAvatar>
              <div class="min-w-0">
                <div class="d-flex align-center gap-2 flex-wrap">
                  <span class="text-subtitle-1 font-weight-bold text-break">
                    {{ localizedValue(form.label) || localizedValue(form.addressStreet) || t('New Address') }}
                  </span>
                  <VChip
                    size="x-small"
                    :color="getTypeMeta(form.type).color"
                    variant="flat"
                    class="font-weight-medium text-capitalize"
                  >
                    {{ allAddressTypes.find(a => a.value === form.type)?.title || form.type }}
                  </VChip>
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ [localizedValue(form.city), localizedValue(form.country)].filter(Boolean).join(', ') || t('Standard Location') }}
                </div>
              </div>
            </div>

            <div class="d-flex flex-wrap gap-1">
              <VChip
                v-if="form.is_primary"
                size="x-small"
                color="warning"
                variant="tonal"
              >
                {{ t('Main Address') }}
              </VChip>
              <VChip
                v-if="form.phone"
                size="x-small"
                color="info"
                variant="tonal"
              >
                {{ form.phone }}
              </VChip>
              <VChip
                v-if="form.responsible"
                size="x-small"
                color="success"
                variant="tonal"
              >
                {{ form.responsible }}
              </VChip>
            </div>
          </div>
        </VCard>
      </div>
    </template>
  </WpWizardDialog>
</template>

<style scoped>
.address-type-scrollbar {
  max-height: 240px;
}

.address-type-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 8px;
  padding: 4px;
}

.address-type-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: rgba(var(--v-theme-surface), 0.6);
  transition: all 0.15s ease;
}

.address-type-card:hover {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.04);
}

.address-type-card.is-selected {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.1);
  box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
}

.role-card {
  transition: all 0.15s ease;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.role-card:hover {
  border-color: rgba(var(--v-theme-primary), 0.5);
}

.role-card--active {
  border-color: rgb(var(--v-theme-primary)) !important;
  background: rgba(var(--v-theme-primary), 0.05);
}

.contact-assignment-card {
  transition: all 0.15s ease;
  background: rgba(var(--v-theme-surface), 0.6);
}

.contact-assignment-card:hover {
  background: rgba(var(--v-theme-surface), 1);
}

.contact-assignment-card--responsible {
  border-color: rgba(var(--v-theme-warning), 0.5) !important;
  background: rgba(var(--v-theme-warning), 0.04) !important;
}

.contact-assignment-card--team {
  border-color: rgba(var(--v-theme-primary), 0.5) !important;
  background: rgba(var(--v-theme-primary), 0.04) !important;
}
</style>
