<script setup>
/* eslint-disable camelcase */
import { WpDetailDialog } from '@/components/shared'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  driver: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const color = status => ({ approved: 'success', suspended: 'warning', expired: 'error', revoked: 'error', under_review: 'info' }[status] || 'secondary')
const date = value => value ? new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(value)) : '—'
</script>

<template>
  <WpDetailDialog
    :model-value="props.modelValue"
    :title="props.driver?.employee?.full_name || 'Driver authorization'"
    :subtitle="props.driver?.driver_authorization_number"
    max-width="900"
    :loading="props.loading"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <template #loading>
      <VProgressLinear indeterminate />
    </template>

    <template v-if="props.driver">
      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <h3 class="text-subtitle-1 mb-3">
            Authorization
          </h3>
          <div class="d-flex flex-column gap-2">
            <div>
              <span class="text-caption text-medium-emphasis">License</span>
              <div>{{ props.driver.license_number }} · {{ props.driver.license_category || '—' }}</div>
            </div>
            <div>
              <span class="text-caption text-medium-emphasis">Status</span>
              <div>
                <VChip
                  :color="color(props.driver.authorization_status)"
                  size="small"
                  label
                >
                  {{ props.driver.authorization_status }}
                </VChip>
              </div>
            </div>
            <div>
              <span class="text-caption text-medium-emphasis">Expiry / review</span>
              <div>{{ date(props.driver.expiry_date) }} / {{ date(props.driver.review_date) }}</div>
            </div>
          </div>
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <h3 class="text-subtitle-1 mb-3">
            Eligibility
          </h3>
          <div class="d-flex flex-column gap-2">
            <div>
              <span class="text-caption text-medium-emphasis">Permitted vehicle types</span>
              <div>{{ props.driver.permitted_vehicle_types?.join(', ') || 'Not specified' }}</div>
            </div>
            <div>
              <span class="text-caption text-medium-emphasis">Score / level</span>
              <div>{{ props.driver.driver_score ?? '—' }} / {{ props.driver.driver_level }}</div>
            </div>
            <div>
              <span class="text-caption text-medium-emphasis">Restrictions</span>
              <div>{{ props.driver.restriction_notes || 'None recorded' }}</div>
            </div>
          </div>
        </VCol>
        <VCol cols="12">
          <VDivider class="mb-3" />
          <h3 class="text-subtitle-1 mb-2">
            History & incidents
          </h3>
          <VList
            v-if="props.driver.activity_log?.length"
            density="compact"
          >
            <VListItem
              v-for="event in props.driver.activity_log"
              :key="event.id"
              :title="event.event_title"
              :subtitle="event.event_description"
            />
          </VList>
          <span
            v-else
            class="text-medium-emphasis"
          >No activity recorded.</span>
        </VCol>
      </VRow>
    </template>
  </WpDetailDialog>
</template>
