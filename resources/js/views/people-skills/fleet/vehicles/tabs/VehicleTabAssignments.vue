<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  vehicle: {
    type: Object,
    required: true,
  },
  assignments: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['assign', 'return'])

const { t } = useI18n()

const formatDate = val => {
  if (!val) return '—'
  try {
    return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(val))
  } catch {
    return val
  }
}

const list = computed(() => {
  return props.assignments.length ? props.assignments : (props.vehicle.assignments || [])
})

const hasActiveAssignment = computed(() => {
  return list.value.some(a => a.is_current || !a.end_date)
})
</script>

<template>
  <div class="d-flex flex-column gap-4">
    <!-- Toolbar -->
    <div class="d-flex justify-space-between align-center">
      <div>
        <h3 class="text-h6 font-weight-bold">
          {{ $t('fleet.assignments') || 'Custody & Assignment History' }}
        </h3>
        <p class="text-caption text-medium-emphasis mb-0">
          Track organizational responsibility, locations, custody changes, and start/end mileage readings.
        </p>
      </div>

      <VBtn
        color="primary"
        prepend-icon="tabler-user-plus"
        @click="emit('assign', vehicle)"
      >
        {{ $t('fleet.assign_vehicle') || 'New Assignment' }}
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
            <th>Responsible Employee</th>
            <th>Custody Type</th>
            <th>Site / Location</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Start km</th>
            <th>End km</th>
            <th>Reason</th>
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
                :color="item.is_current || !item.end_date ? 'success' : 'secondary'"
                size="small"
                label
              >
                {{ item.is_current || !item.end_date ? 'Active' : 'Returned' }}
              </VChip>
            </td>
            <td>
              <div class="font-weight-medium">
                {{ item.responsible_employee?.name || item.responsible_employee?.email || '—' }}
              </div>
            </td>
            <td>
              <span class="text-capitalize">{{ item.custody_type?.replaceAll('_', ' ') }}</span>
            </td>
            <td>
              <span class="text-body-2">{{ item.site?.name || item.location_notes || '—' }}</span>
            </td>
            <td>{{ formatDate(item.start_date) }}</td>
            <td>{{ formatDate(item.end_date) }}</td>
            <td>{{ item.odometer_at_start != null ? `${Number(item.odometer_at_start).toLocaleString()} km` : '—' }}</td>
            <td>{{ item.odometer_at_end != null ? `${Number(item.odometer_at_end).toLocaleString()} km` : '—' }}</td>
            <td>
              <span class="text-caption text-truncate d-inline-block" style="max-inline-size: 150px;">
                {{ item.reason || '—' }}
              </span>
            </td>
            <td class="text-end">
              <VBtn
                v-if="item.is_current || !item.end_date"
                variant="tonal"
                color="warning"
                size="small"
                prepend-icon="tabler-arrow-back-up"
                @click="emit('return', vehicle)"
              >
                Return
              </VBtn>
              <span v-else class="text-disabled text-caption">Completed</span>
            </td>
          </tr>

          <tr v-if="!list.length">
            <td
              colspan="10"
              class="text-center py-6 text-disabled"
            >
              <VIcon
                icon="tabler-user-off"
                size="32"
                class="mb-2"
              />
              <div>No vehicle assignments recorded yet.</div>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </div>
</template>
