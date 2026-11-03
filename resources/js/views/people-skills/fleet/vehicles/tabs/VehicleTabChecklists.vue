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
  checklists: {
    type: Array,
    default: () => [],
  },
  userOptions: {
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
  checklist_type: 'pre_trip',
  title: 'Daily Pre-Trip Inspection',
  completed_at: new Date().toISOString().split('T')[0],
  completed_by_employee_id: null,
  driver_id: null,
  odometer_km: props.vehicle.odometer_km || null,
  location: '',
  overall_status: 'passed',
  items_passed: 12,
  items_failed: 0,
  items_total: 12,
  has_issues: false,
  notes: '',
  recommendations: '',
  requires_maintenance: false,
})

const form = reactive(blankForm())

const checklistTypeOptions = [
  { title: 'Pre-Trip Inspection', value: 'pre_trip' },
  { title: 'Post-Trip Handover', value: 'post_trip' },
  { title: 'Weekly Maintenance Check', value: 'weekly' },
  { title: 'Monthly Safety Audit', value: 'monthly' },
  { title: 'Incident / Damage Report', value: 'incident' },
  { title: 'Return Verification', value: 'return' },
]

const statusOptions = [
  { title: 'Passed', value: 'passed' },
  { title: 'Passed with Issues', value: 'passed_with_issues' },
  { title: 'Failed', value: 'failed' },
  { title: 'Pending Review', value: 'pending' },
]

const list = computed(() => {
  return props.checklists.length ? props.checklists : (props.vehicle.checklists || [])
})

const formatDate = val => {
  if (!val) return '—'
  try {
    return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(val))
  } catch {
    return val
  }
}

const statusColor = val => ({
  passed: 'success',
  passed_with_issues: 'warning',
  failed: 'error',
  pending: 'info',
}[val] || 'default')

const openAdd = () => {
  Object.assign(form, blankForm())
  isAddOpen.value = true
}

const saveChecklist = async () => {
  isSubmitting.value = true
  try {
    const payload = {
      ...form,
      odometer_km: form.odometer_km ? Number(form.odometer_km) : null,
      items_passed: form.items_passed ? Number(form.items_passed) : 0,
      items_failed: form.items_failed ? Number(form.items_failed) : 0,
      items_total: form.items_total ? Number(form.items_total) : 0,
      has_issues: form.overall_status !== 'passed' || form.has_issues,
    }
    await store.createChecklist(props.vehicle.id, payload)
    showMessage('Checklist inspection recorded successfully', 'success')
    isAddOpen.value = false
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || 'Failed to save checklist', 'error')
  } finally {
    isSubmitting.value = false
  }
}

const deleteChecklist = async id => {
  if (!confirm('Are you sure you want to delete this checklist entry?')) return
  try {
    await store.deleteChecklist(id, props.vehicle.id)
    showMessage('Checklist deleted successfully', 'success')
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || 'Failed to delete checklist', 'error')
  }
}
</script>

<template>
  <div class="d-flex flex-column gap-4">
    <!-- Toolbar -->
    <div class="d-flex justify-space-between align-center">
      <div>
        <h3 class="text-h6 font-weight-bold">
          {{ $t('fleet.checklists') || 'Vehicle Inspection Checklists' }}
        </h3>
        <p class="text-caption text-medium-emphasis mb-0">
          Pre-trip inspections, daily safety checks, issue logs, and handover verification records.
        </p>
      </div>

      <VBtn
        color="primary"
        prepend-icon="tabler-clipboard-check"
        @click="openAdd"
      >
        {{ $t('fleet.add_checklist') || 'Record Inspection' }}
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
            <th>Checklist Title & Type</th>
            <th>Inspection Date</th>
            <th>Inspector / Driver</th>
            <th>Items Result</th>
            <th>Odometer</th>
            <th>Issues & Notes</th>
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
                :color="statusColor(item.overall_status)"
                size="small"
                label
                class="text-capitalize"
              >
                {{ item.overall_status?.replaceAll('_', ' ') }}
              </VChip>
            </td>
            <td>
              <div class="font-weight-medium">{{ item.title }}</div>
              <div class="text-caption text-medium-emphasis text-capitalize">
                {{ item.checklist_type?.replaceAll('_', ' ') }}
              </div>
            </td>
            <td>{{ formatDate(item.completed_at) }}</td>
            <td>
              <div>{{ item.completed_by_employee?.name || item.driver?.name || '—' }}</div>
              <div v-if="item.location" class="text-caption text-medium-emphasis">{{ item.location }}</div>
            </td>
            <td>
              <span class="text-success font-weight-medium">{{ item.items_passed ?? 0 }} passed</span>
              <span v-if="item.items_failed" class="text-error font-weight-medium"> · {{ item.items_failed }} failed</span>
            </td>
            <td>
              {{ item.odometer_km != null ? `${Number(item.odometer_km).toLocaleString()} km` : '—' }}
            </td>
            <td>
              <div v-if="item.has_issues" class="text-error font-weight-medium d-flex align-center gap-1">
                <VIcon icon="tabler-alert-triangle" size="14" />
                Issues Flagged
              </div>
              <div class="text-caption text-truncate d-inline-block" style="max-inline-size: 160px;">
                {{ item.notes || item.recommendations || '—' }}
              </div>
            </td>
            <td class="text-end">
              <VBtn
                variant="text"
                color="error"
                density="compact"
                icon="tabler-trash"
                size="small"
                @click="deleteChecklist(item.id)"
              />
            </td>
          </tr>

          <tr v-if="!list.length">
            <td
              colspan="8"
              class="text-center py-6 text-disabled"
            >
              <VIcon
                icon="tabler-clipboard-x"
                size="32"
                class="mb-2"
              />
              <div>No checklist inspection logs recorded yet.</div>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- Add Inspection Dialog -->
    <VDialog
      v-model="isAddOpen"
      max-width="640"
      persistent
    >
      <DialogCloseBtn @click="isAddOpen = false" />
      <VCard :title="$t('fleet.add_checklist') || 'Record Vehicle Inspection Checklist'">
        <VCardText class="pt-2">
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.checklist_type"
                label="Checklist Type *"
                :items="checklistTypeOptions"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.overall_status"
                label="Overall Status *"
                :items="statusOptions"
                required
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="form.title"
                label="Checklist Title *"
                placeholder="e.g. Daily Pre-Trip Checklist"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.completed_at"
                label="Completed Date *"
                type="date"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model.number="form.odometer_km"
                label="Odometer (km)"
                type="number"
                min="0"
                suffix="km"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.completed_by_employee_id"
                label="Inspected By (Staff)"
                :items="userOptions"
                clearable
                placeholder="Select staff"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.driver_id"
                label="Driver"
                :items="userOptions"
                clearable
                placeholder="Select driver"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model.number="form.items_total"
                label="Total Items"
                type="number"
                min="1"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model.number="form.items_passed"
                label="Items Passed"
                type="number"
                min="0"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model.number="form.items_failed"
                label="Items Failed"
                type="number"
                min="0"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="form.location"
                label="Inspection Location"
                placeholder="e.g. Depot Bay 2, Client Site Riyadh"
              />
            </VCol>
            <VCol cols="12">
              <div class="d-flex gap-4">
                <VSwitch
                  v-model="form.has_issues"
                  label="Critical Issues Found"
                  color="error"
                  density="compact"
                />
                <VSwitch
                  v-model="form.requires_maintenance"
                  label="Requires Immediate Workshop Maintenance"
                  color="warning"
                  density="compact"
                />
              </div>
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="form.notes"
                label="Inspection Observations & Remarks"
                rows="2"
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="form.recommendations"
                label="Corrective Recommendations"
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
            @click="saveChecklist"
          >
            {{ $t('Save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
