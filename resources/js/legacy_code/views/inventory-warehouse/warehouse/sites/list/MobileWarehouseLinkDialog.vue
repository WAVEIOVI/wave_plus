<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useVehicleSelection } from '@/composables/useVehicleSelection'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  warehouseSite: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'submit'])

const { t } = useI18n()
const {
  vehicleOptions,
  fetchAvailableVehicles,
  formatVehicleDisplay,
  getStatusColor,
  getVehicleIcon,
} = useVehicleSelection()

// Local state
const loading = ref(false)
const submitting = ref(false)
const selectedVehicleId = ref(null)
const assignedFrom = ref(null)
const assignedUntil = ref(null)
const notes = ref('')

// Computed
const vehicleItems = computed(() => {
  return vehicleOptions.value.map(v => ({
    value: v.id,
    title: formatVehicleDisplay(v),
    subtitle: `${v.type} - ${v.ownership_type}`,
    raw: v,
  }))
})

const isFormValid = computed(() => {
  return selectedVehicleId.value && assignedFrom.value
})

const dialogTitle = computed(() => {
  return props.warehouseSite.vehicle_id
    ? t('inventory.mobile_warehouse.change_vehicle')
    : t('inventory.mobile_warehouse.link_vehicle')
})

// Methods
const loadVehicles = async () => {
  loading.value = true

  try {
    await fetchAvailableVehicles({
      'filter[type]': 'van,truck', // Only vehicles suitable for mobile warehouses
    })
  } catch (error) {
    console.error('Failed to load vehicles:', error)
  } finally {
    loading.value = false
  }
}

const onSubmit = () => {
  if (!isFormValid.value) return

  submitting.value = true

  const payload = {
    vehicle_id: selectedVehicleId.value,
    assigned_from: assignedFrom.value,
    assigned_until: assignedUntil.value || null,
    notes: notes.value,
  }

  $api(`/inventory/sites/${props.warehouseSite.id}/link-vehicle`, {
    method: 'POST',
    body: payload,
  })
    .then((res) => {
      emit('submit', res.data)
      closeDialog()
    })
    .catch((err) => {
      console.error('Failed to link vehicle:', err)
    })
    .finally(() => {
      submitting.value = false
    })
}

const onUnlink = () => {
  if (!props.warehouseSite.vehicle_id) return
  
  submitting.value = true
  
  $api(`/inventory/sites/${props.warehouseSite.id}/unlink-vehicle`, {
    method: 'POST',
  })
    .then((res) => {
      emit('submit', res.data)
      closeDialog()
    })
    .catch((err) => {
      console.error('Failed to unlink vehicle:', err)
    })
    .finally(() => {
      submitting.value = false
    })
}

const closeDialog = () => {
  emit('update:isDialogVisible', false)
  resetForm()
}

const resetForm = () => {
  selectedVehicleId.value = null
  assignedFrom.value = null
  assignedUntil.value = null
  notes.value = ''
  submitting.value = false
}

// Lifecycle
onMounted(() => {
  if (props.isDialogVisible) {
    loadVehicles()
    
    // Pre-fill current vehicle if already assigned
    if (props.warehouseSite.vehicle_id) {
      selectedVehicleId.value = props.warehouseSite.vehicle_id
    }
  }
})
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="600"
    persistent
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between pa-4">
        <div class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-truck"
            color="primary"
          />
          <span>{{ dialogTitle }}</span>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="closeDialog"
        />
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-4">
        <!-- Warehouse Info -->
        <VAlert
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          <div class="text-caption">
            {{ $t('inventory.mobile_warehouse.assigning_to') }}
          </div>
          <div class="font-weight-medium">
            {{ props.warehouseSite.name?.en || props.warehouseSite.code }}
          </div>
        </VAlert>

        <!-- Vehicle Selection -->
        <VSelect
          v-model="selectedVehicleId"
          :items="vehicleItems"
          :label="$t('inventory.mobile_warehouse.select_vehicle')"
          :loading="loading"
          :disabled="loading"
          prepend-inner-icon="tabler-truck"
          variant="outlined"
          clearable
          class="mb-4"
        >
          <template #item="{ item, props: itemProps }">
            <VListItem
              v-bind="itemProps"
              :subtitle="item.subtitle"
            >
              <template #prepend>
                <VIcon
                  :icon="getVehicleIcon(item.raw?.type)"
                  :color="getStatusColor(item.raw?.status)"
                />
              </template>
            </VListItem>
          </template>
        </VSelect>

        <!-- Date Range -->
        <VRow>
          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              v-model="assignedFrom"
              :label="$t('inventory.mobile_warehouse.assigned_from')"
              type="date"
              prepend-inner-icon="tabler-calendar"
              variant="outlined"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              v-model="assignedUntil"
              :label="$t('inventory.mobile_warehouse.assigned_until')"
              type="date"
              prepend-inner-icon="tabler-calendar"
              variant="outlined"
              hint="Leave empty for indefinite assignment"
            />
          </VCol>
        </VRow>

        <!-- Notes -->
        <VTextarea
          v-model="notes"
          :label="$t('common.notes')"
          rows="3"
          variant="outlined"
          class="mt-2"
        />

        <!-- Current Assignment Info (if exists) -->
        <VAlert
          v-if="props.warehouseSite.vehicle_id && props.warehouseSite.vehicle"
          type="warning"
          variant="tonal"
          density="compact"
          class="mt-4"
        >
          <div class="text-caption">
            {{ $t('inventory.mobile_warehouse.currently_assigned') }}
          </div>
          <div class="font-weight-medium">
            {{ props.warehouseSite.vehicle.plate_number }}
          </div>
          <div class="text-caption">
            {{ $t('inventory.mobile_warehouse.will_be_replaced') }}
          </div>
        </VAlert>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VSpacer />
        
        <!-- Unlink button when vehicle is already assigned -->
        <VBtn
          v-if="props.warehouseSite.vehicle_id"
          variant="text"
          color="error"
          :loading="submitting"
          @click="onUnlink"
        >
          {{ $t('inventory.mobile_warehouse.unlink_vehicle') }}
        </VBtn>
        
        <VBtn
          variant="text"
          @click="closeDialog"
        >
          {{ $t('common.cancel') }}
        </VBtn>
        <VBtn
          v-if="!props.warehouseSite.vehicle_id || props.warehouseSite.vehicle_id !== selectedVehicleId"
          color="primary"
          :loading="submitting"
          :disabled="!isFormValid"
          @click="onSubmit"
        >
          {{ $t('common.save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
