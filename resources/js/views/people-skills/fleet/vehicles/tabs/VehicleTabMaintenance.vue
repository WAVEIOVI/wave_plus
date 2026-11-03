<script setup>
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useVehicleStore } from '@/stores/useVehicleStore'
import { useAppSnackbar } from '@/composables/useAppSnackbar'

const props = defineProps({
  vehicle: {
    type: Object,
    required: true,
  },
  maintenanceRecords: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['refresh'])

const { t } = useI18n()
const store = useVehicleStore()
const { showMessage } = useAppSnackbar()

const isAddOpen = ref(false)
const isSubmitting = ref(false)

const blankForm = () => ({
  maintenance_type: 'preventive',
  priority: 'medium',
  scheduled_date: new Date().toISOString().split('T')[0],
  completed_date: null,
  odometer_km: props.vehicle.odometer_km || null,
  workshop_name: '',
  title: '',
  description: '',
  labor_cost: null,
  parts_cost: null,
  total_cost: null,
  currency: 'SAR',
  invoice_number: '',
  next_service_date: null,
  next_service_km: null,
  status: 'scheduled',
  notes: '',
})

const form = reactive(blankForm())

const maintenanceTypeOptions = [
  { title: 'Preventive / Regular Service', value: 'preventive' },
  { title: 'Corrective / Repair', value: 'corrective' },
  { title: 'Inspection / Certification', value: 'inspection' },
  { title: 'Manufacturer Recall', value: 'recall' },
  { title: 'Other Maintenance', value: 'other' },
]

const priorityOptions = [
  { title: 'Low', value: 'low' },
  { title: 'Medium', value: 'medium' },
  { title: 'High', value: 'high' },
  { title: 'Urgent', value: 'urgent' },
]

const statusOptions = [
  { title: 'Scheduled', value: 'scheduled' },
  { title: 'In Progress', value: 'in_progress' },
  { title: 'Completed', value: 'completed' },
  { title: 'Cancelled', value: 'cancelled' },
]

const list = computed(() => {
  return props.maintenanceRecords.length ? props.maintenanceRecords : (props.vehicle.maintenance_records || props.vehicle.maintenanceRecords || [])
})

const totalMaintenanceCost = computed(() => {
  return list.value.reduce((acc, r) => acc + (Number(r.total_cost) || 0), 0)
})

const completedCount = computed(() => {
  return list.value.filter(r => r.status === 'completed').length
})

const pendingCount = computed(() => {
  return list.value.filter(r => ['scheduled', 'in_progress'].includes(r.status)).length
})

const formatDate = val => {
  if (!val) return '—'
  try {
    return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(val))
  } catch {
    return val
  }
}

const statusColor = val => ({
  completed: 'success',
  in_progress: 'info',
  scheduled: 'warning',
  cancelled: 'secondary',
}[val] || 'default')

const priorityColor = val => ({
  low: 'secondary',
  medium: 'info',
  high: 'warning',
  urgent: 'error',
}[val] || 'default')

const onLaborOrPartsChange = () => {
  const labor = Number(form.labor_cost) || 0
  const parts = Number(form.parts_cost) || 0
  if ((labor > 0 || parts > 0) && !form.total_cost) {
    form.total_cost = Number((labor + parts).toFixed(2))
  }
}

const openAdd = () => {
  Object.assign(form, blankForm())
  isAddOpen.value = true
}

const saveMaintenanceRecord = async () => {
  isSubmitting.value = true
  try {
    const payload = {
      ...form,
      total_cost: form.total_cost ? Number(form.total_cost) : null,
      labor_cost: form.labor_cost ? Number(form.labor_cost) : null,
      parts_cost: form.parts_cost ? Number(form.parts_cost) : null,
      odometer_km: form.odometer_km ? Number(form.odometer_km) : null,
      next_service_km: form.next_service_km ? Number(form.next_service_km) : null,
    }
    await store.createMaintenanceRecord(props.vehicle.id, payload)
    showMessage('Maintenance record added successfully', 'success')
    isAddOpen.value = false
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || 'Failed to save maintenance record', 'error')
  } finally {
    isSubmitting.value = false
  }
}

const deleteRecord = async id => {
  if (!confirm('Are you sure you want to delete this maintenance record?')) return
  try {
    await store.deleteMaintenanceRecord(id, props.vehicle.id)
    showMessage('Maintenance record deleted successfully', 'success')
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || 'Failed to delete maintenance record', 'error')
  }
}
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Summary KPIs Row -->
    <VRow>
      <VCol
        cols="12"
        sm="4"
      >
        <VCard variant="tonal" color="primary">
          <VCardText class="d-flex align-center justify-space-between pa-4">
            <div>
              <div class="text-caption text-medium-emphasis">
                {{ $t('fleet.total_maintenance_cost') || 'Total Maintenance Spend' }}
              </div>
              <div class="text-h5 font-weight-bold">
                {{ totalMaintenanceCost.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} <span class="text-caption">SAR</span>
              </div>
            </div>
            <VAvatar color="primary" variant="tonal" size="44">
              <VIcon icon="tabler-currency-dollar" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        sm="4"
      >
        <VCard variant="tonal" color="success">
          <VCardText class="d-flex align-center justify-space-between pa-4">
            <div>
              <div class="text-caption text-medium-emphasis">Completed Interventions</div>
              <div class="text-h5 font-weight-bold">{{ completedCount }}</div>
            </div>
            <VAvatar color="success" variant="tonal" size="44">
              <VIcon icon="tabler-circle-check" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        sm="4"
      >
        <VCard variant="tonal" color="warning">
          <VCardText class="d-flex align-center justify-space-between pa-4">
            <div>
              <div class="text-caption text-medium-emphasis">Pending / In-Progress</div>
              <div class="text-h5 font-weight-bold">{{ pendingCount }}</div>
            </div>
            <VAvatar color="warning" variant="tonal" size="44">
              <VIcon icon="tabler-clock" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Toolbar -->
    <div class="d-flex justify-space-between align-center">
      <div>
        <h3 class="text-h6 font-weight-bold">
          {{ $t('fleet.maintenance') || 'Maintenance & Workshop History' }}
        </h3>
        <p class="text-caption text-medium-emphasis mb-0">
          Scheduled services, repairs, workshop orders, parts expenditure, and technical certifications.
        </p>
      </div>

      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="openAdd"
      >
        {{ $t('fleet.add_maintenance_record') || 'Schedule Maintenance' }}
      </VBtn>
    </div>

    <!-- Table -->
    <VCard variant="outlined">
      <VTable
        hover
        density="comfortable"
      >
        <thead>
          <tr>
            <th>Status</th>
            <th>Title & Type</th>
            <th>Scheduled / Completed</th>
            <th>Priority</th>
            <th>Workshop</th>
            <th>Total Cost</th>
            <th>Odometer</th>
            <th>Next Due</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in list"
            :key="item.id"
          >
            <td>
              <VChip
                :color="statusColor(item.status)"
                size="small"
                label
                class="text-capitalize"
              >
                {{ item.status?.replaceAll('_', ' ') }}
              </VChip>
            </td>
            <td>
              <div class="font-weight-medium">{{ item.title }}</div>
              <div class="text-caption text-medium-emphasis text-capitalize">
                {{ item.maintenance_type?.replaceAll('_', ' ') }}
              </div>
            </td>
            <td>
              <div>{{ formatDate(item.scheduled_date) }}</div>
              <div v-if="item.completed_date" class="text-caption text-success">
                Done: {{ formatDate(item.completed_date) }}
              </div>
            </td>
            <td>
              <VChip
                :color="priorityColor(item.priority)"
                size="x-small"
                label
                class="text-capitalize"
              >
                {{ item.priority }}
              </VChip>
            </td>
            <td>
              <div>{{ item.workshop_name || item.workshop_provider?.name || '—' }}</div>
              <div v-if="item.invoice_number" class="text-caption text-medium-emphasis">
                Inv: {{ item.invoice_number }}
              </div>
            </td>
            <td>
              <span class="font-weight-bold">
                {{ item.total_cost != null ? `${Number(item.total_cost).toLocaleString()} ${item.currency || 'SAR'}` : '—' }}
              </span>
            </td>
            <td>
              {{ item.odometer_km != null ? `${Number(item.odometer_km).toLocaleString()} km` : '—' }}
            </td>
            <td>
              <div>{{ formatDate(item.next_service_date) }}</div>
              <div v-if="item.next_service_km" class="text-caption text-medium-emphasis">
                @ {{ Number(item.next_service_km).toLocaleString() }} km
              </div>
            </td>
            <td class="text-end">
              <VBtn
                variant="text"
                color="error"
                density="compact"
                icon="tabler-trash"
                size="small"
                @click="deleteRecord(item.id)"
              />
            </td>
          </tr>

          <tr v-if="!list.length">
            <td
              colspan="9"
              class="text-center py-6 text-disabled"
            >
              <VIcon
                icon="tabler-tool"
                size="32"
                class="mb-2"
              />
              <div>No maintenance records logged for this vehicle.</div>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- Add Maintenance Dialog -->
    <VDialog
      v-model="isAddOpen"
      max-width="700"
      persistent
    >
      <DialogCloseBtn @click="isAddOpen = false" />
      <VCard :title="$t('fleet.add_maintenance_record') || 'Record Vehicle Maintenance'">
        <VCardText class="pt-2">
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.maintenance_type"
                label="Maintenance Type *"
                :items="maintenanceTypeOptions"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="3"
            >
              <AppSelect
                v-model="form.priority"
                label="Priority"
                :items="priorityOptions"
              />
            </VCol>
            <VCol
              cols="12"
              md="3"
            >
              <AppSelect
                v-model="form.status"
                label="Status *"
                :items="statusOptions"
                required
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="form.title"
                label="Service Title / Subject *"
                placeholder="e.g. 50,000 km Major Service & Brake Pad Replacement"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.scheduled_date"
                label="Scheduled Date *"
                type="date"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.completed_date"
                label="Completed Date"
                type="date"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.workshop_name"
                label="Workshop / Provider Name"
                placeholder="e.g. Al-Futtaim Auto Center"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model.number="form.odometer_km"
                label="Odometer at Service (km)"
                type="number"
                min="0"
                suffix="km"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model.number="form.labor_cost"
                label="Labor Cost"
                type="number"
                step="0.01"
                min="0"
                @input="onLaborOrPartsChange"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model.number="form.parts_cost"
                label="Parts Cost"
                type="number"
                step="0.01"
                min="0"
                @input="onLaborOrPartsChange"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model.number="form.total_cost"
                label="Total Cost"
                type="number"
                step="0.01"
                min="0"
                suffix="SAR"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.invoice_number"
                label="Invoice / WO Reference"
                placeholder="e.g. INV-2026-881"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.next_service_date"
                label="Next Recommended Service Date"
                type="date"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model.number="form.next_service_km"
                label="Next Recommended Service Mileage (km)"
                type="number"
                min="0"
                suffix="km"
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="form.description"
                label="Work Description & Items Performed"
                rows="2"
              />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="justify-end pa-4 gap-2 border-t">
          <VBtn
            variant="tonal"
            :disabled="isSubmitting"
            @click="isAddOpen = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="isSubmitting"
            prepend-icon="tabler-device-floppy"
            @click="saveMaintenanceRecord"
          >
            {{ $t('Save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
