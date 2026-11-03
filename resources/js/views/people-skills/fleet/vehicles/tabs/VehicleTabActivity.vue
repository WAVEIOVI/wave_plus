<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  vehicle: {
    type: Object,
    required: true,
  },
  activityLog: {
    type: Array,
    default: () => [],
  },
})

const { t } = useI18n()

const list = computed(() => {
  return props.activityLog.length ? props.activityLog : (props.vehicle.activity_log || props.vehicle.activityLog || [])
})

const formatDate = val => {
  if (!val) return '—'
  try {
    return new Intl.DateTimeFormat(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(new Date(val))
  } catch {
    return val
  }
}

const eventIcon = type => ({
  assignment: 'tabler-user-plus',
  reassignment: 'tabler-user-check',
  return: 'tabler-arrow-back-up',
  status_change: 'tabler-refresh',
  odometer_update: 'tabler-dashboard',
  rental_start: 'tabler-car',
  rental_end: 'tabler-car-off',
  client_use_start: 'tabler-building-estate',
  client_use_end: 'tabler-building-bank',
  maintenance: 'tabler-tool',
  checklist: 'tabler-clipboard-check',
}[type] || 'tabler-history')

const eventColor = type => ({
  assignment: 'info',
  reassignment: 'primary',
  return: 'warning',
  status_change: 'primary',
  odometer_update: 'success',
  rental_start: 'info',
  rental_end: 'secondary',
  client_use_start: 'primary',
  client_use_end: 'secondary',
  maintenance: 'warning',
  checklist: 'success',
}[type] || 'primary')
</script>

<template>
  <div class="d-flex flex-column gap-4">
    <div>
      <h3 class="text-h6 font-weight-bold">
        {{ $t('fleet.activity') || 'Vehicle Activity & Audit Log' }}
      </h3>
      <p class="text-caption text-medium-emphasis mb-0">
        Complete timeline of organizational actions, mileage updates, custody handovers, and operational events.
      </p>
    </div>

    <VCard variant="outlined">
      <VCardText class="pa-6">
        <VTimeline
          v-if="list.length"
          side="end"
          density="comfortable"
          truncate-line="both"
        >
          <VTimelineItem
            v-for="event in list"
            :key="event.id"
            :dot-color="eventColor(event.event_type)"
            size="small"
          >
            <template #icon>
              <VIcon
                :icon="eventIcon(event.event_type)"
                size="16"
              />
            </template>

            <div class="d-flex flex-column gap-1">
              <div class="d-flex justify-space-between align-center">
                <span class="font-weight-bold text-body-1">{{ event.event_title }}</span>
                <span class="text-caption text-medium-emphasis">{{ formatDate(event.event_date || event.created_at) }}</span>
              </div>

              <div class="text-body-2 text-medium-emphasis">
                {{ event.event_description || 'No additional remarks' }}
              </div>

              <div class="d-flex flex-wrap align-center gap-2 mt-1">
                <VChip
                  v-if="event.actor_employee?.name || event.actor_name"
                  size="x-small"
                  variant="tonal"
                  color="primary"
                  prepend-icon="tabler-user"
                >
                  Actor: {{ event.actor_employee?.name || event.actor_name }}
                </VChip>

                <VChip
                  v-if="event.odometer_km != null"
                  size="x-small"
                  variant="tonal"
                  color="success"
                  prepend-icon="tabler-dashboard"
                >
                  {{ Number(event.odometer_km).toLocaleString() }} km
                </VChip>

                <VChip
                  v-if="event.old_value && event.new_value"
                  size="x-small"
                  variant="outlined"
                >
                  {{ event.old_value }} &rarr; {{ event.new_value }}
                </VChip>
              </div>
            </div>
          </VTimelineItem>
        </VTimeline>

        <div
          v-else
          class="d-flex flex-column align-center justify-center py-10 text-center text-disabled"
        >
          <VIcon
            icon="tabler-history-off"
            size="36"
            class="mb-2"
          />
          <div>No activity events logged for this vehicle yet.</div>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>
