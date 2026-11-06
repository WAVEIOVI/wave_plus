<script setup>
/* eslint-disable camelcase */
import { WpFormDialog } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  location: {
    type: Object,
    default: null,
  },
  zones: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const { t } = useI18n()

const isEdit = computed(() => !!props.location?.id)

const locationTypeOptions = [
  { title: t('inventory.locations.types.shelf', 'Shelf'), value: 'shelf' },
  { title: t('inventory.locations.types.bin', 'Bin'), value: 'bin' },
  { title: t('inventory.locations.types.floor', 'Floor'), value: 'floor' },
  { title: t('inventory.locations.types.pallet', 'Pallet Rack'), value: 'pallet' },
  { title: t('Rack'), value: 'rack' },
  { title: t('Bulk Storage'), value: 'bulk' },
  { title: t('Pick Face'), value: 'pick' },
  { title: t('Overstock'), value: 'overstock' },
  { title: t('Staging Area'), value: 'staging' },
  { title: t('Quarantine'), value: 'quarantine' },
  { title: t('Returns Buffer'), value: 'returns' },
  { title: t('Cross Dock'), value: 'cross_dock' },
  { title: t('Cold Storage'), value: 'cold_storage' },
  { title: t('Hazmat Storage'), value: 'hazmat' },
]

const zoneOptions = computed(() => {
  return props.zones.map(z => ({
    title: `${z.code} — ${localizedValue(z.name) || z.code}`,
    value: z.id,
  }))
})

const formRef = ref(null)

const initialForm = () => ({
  zone_id: null,
  barcode: '',
  aisle: '',
  rack: '',
  shelf: '',
  bin: '',
  location_type: 'shelf',
  max_weight_kg: null,
  max_volume_m3: null,
  is_active: true,
  notes: '',
})

const formData = reactive(initialForm())

const populateForm = () => {
  if (props.location) {
    formData.zone_id = props.location.zone_id ?? null
    formData.barcode = props.location.barcode ?? ''
    formData.aisle = props.location.aisle ?? ''
    formData.rack = props.location.rack ?? ''
    formData.shelf = props.location.shelf ?? ''
    formData.bin = props.location.bin ?? ''
    formData.location_type = props.location.location_type ?? 'shelf'
    formData.max_weight_kg = props.location.max_weight_kg ?? null
    formData.max_volume_m3 = props.location.max_volume_m3 ?? null
    formData.is_active = props.location.is_active ?? true
    formData.notes = props.location.notes ?? ''
  } else {
    Object.assign(formData, initialForm())
    if (props.zones.length === 1) {
      formData.zone_id = props.zones[0].id
    }
  }
}

watch(
  () => props.modelValue,
  isOpen => {
    if (isOpen) {
      populateForm()
    }
  },
)

const generatedFullPath = computed(() => {
  const parts = [formData.aisle, formData.rack, formData.shelf, formData.bin].filter(Boolean)

  return parts.join('-')
})

const handleSubmit = () => {
  const payload = {
    zone_id: formData.zone_id,
    barcode: formData.barcode?.trim() || null,
    aisle: formData.aisle?.trim() || null,
    rack: formData.rack?.trim() || null,
    shelf: formData.shelf?.trim() || null,
    bin: formData.bin?.trim() || null,
    location_type: formData.location_type,
    max_weight_kg: formData.max_weight_kg !== null && formData.max_weight_kg !== '' ? Number(formData.max_weight_kg) : null,
    max_volume_m3: formData.max_volume_m3 !== null && formData.max_volume_m3 !== '' ? Number(formData.max_volume_m3) : null,
    is_active: Boolean(formData.is_active),
    notes: formData.notes?.trim() || null,
  }

  emit('submit', payload)
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="isEdit ? t('inventory.locations.edit', 'Edit Bin Location') : t('inventory.locations.add', 'New Bin Location')"
    icon="tabler-map-pin"
    icon-color="info"
    size="large"
    :loading="loading"
    :submit-label="isEdit ? t('Save Changes') : t('Create')"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="handleSubmit"
  >
    <VForm ref="formRef">
      <VRow>
        <!-- Parent Zone -->
        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="formData.zone_id"
            :items="zoneOptions"
            :label="t('inventory.sites.zone', 'Warehouse Zone')"
            :rules="[v => !!v || t('validation.required')]"
            required
          />
        </VCol>

        <!-- Location Type -->
        <VCol
          cols="12"
          sm="6"
        >
          <VSelect
            v-model="formData.location_type"
            :items="locationTypeOptions"
            :label="t('inventory.locations.columns.type', 'Location Type')"
            :rules="[v => !!v || t('validation.required')]"
            required
          />
        </VCol>

        <!-- Barcode -->
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="formData.barcode"
            :label="t('inventory.stock_items.columns.barcode', 'Barcode / Tag')"
            placeholder="LOC-A01-01"
          />
        </VCol>

        <!-- Preview Path -->
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            :model-value="generatedFullPath || '—'"
            :label="t('inventory.stock_items.columns.full_path', 'Full Coordinate Path')"
            readonly
            variant="filled"
          />
        </VCol>

        <!-- Coordinate Parts: Aisle, Rack, Shelf, Bin -->
        <VCol
          cols="6"
          sm="3"
        >
          <VTextField
            v-model="formData.aisle"
            :label="t('Aisle')"
            placeholder="A"
          />
        </VCol>

        <VCol
          cols="6"
          sm="3"
        >
          <VTextField
            v-model="formData.rack"
            :label="t('Rack')"
            placeholder="01"
          />
        </VCol>

        <VCol
          cols="6"
          sm="3"
        >
          <VTextField
            v-model="formData.shelf"
            :label="t('Shelf')"
            placeholder="02"
          />
        </VCol>

        <VCol
          cols="6"
          sm="3"
        >
          <VTextField
            v-model="formData.bin"
            :label="t('Bin')"
            placeholder="B"
          />
        </VCol>

        <!-- Capacity limits -->
        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="formData.max_weight_kg"
            :label="t('inventory.locations.columns.max_weight', 'Max Weight (kg)')"
            type="number"
            min="0"
            step="0.1"
          />
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <VTextField
            v-model="formData.max_volume_m3"
            :label="t('inventory.locations.columns.max_volume', 'Max Volume (m³)')"
            type="number"
            min="0"
            step="0.01"
          />
        </VCol>

        <!-- Active Toggle Switch -->
        <VCol cols="12">
          <VSwitch
            v-model="formData.is_active"
            :label="t('inventory.locations.columns.is_active', 'Active Location (Available for Stock Putaway)')"
            color="success"
          />
        </VCol>

        <!-- Notes -->
        <VCol cols="12">
          <VTextarea
            v-model="formData.notes"
            :label="t('Notes')"
            rows="2"
          />
        </VCol>
      </VRow>
    </VForm>
  </WpFormDialog>
</template>
