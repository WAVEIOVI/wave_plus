<script setup>
/* eslint-disable camelcase */
import { WpDetailDialog } from '@/components/shared'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  driver: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()

const statusColor = status => ({
  approved: 'success',
  suspended: 'warning',
  expired: 'error',
  revoked: 'error',
  under_review: 'info',
  pending: 'secondary',
}[status] || 'secondary')

const severityColor = severity => ({
  minor: 'info',
  moderate: 'warning',
  major: 'error',
  critical: 'error',
}[severity] || 'secondary')

const levelColor = level => ({
  expert: 'success',
  senior: 'primary',
  standard: 'info',
  junior: 'secondary',
  trainee: 'warning',
}[level] || 'default')

const date = value => {
  if (!value) return '—'
  try {
    return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(value))
  } catch {
    return value
  }
}

const incidentsList = computed(() => {
  return props.driver?.incidents || []
})

const activityList = computed(() => {
  return props.driver?.activity_log || props.driver?.activityLog || []
})
</script>

<template>
  <WpDetailDialog
    :model-value="props.modelValue"
    :title="props.driver?.employee?.full_name || t('fleet.driver_authorization', 'Driver Authorization')"
    :subtitle="`${props.driver?.driver_authorization_number || ''} ${props.driver?.employee?.employee_number ? '· ' + props.driver.employee.employee_number : ''}`"
    size="large"
    :loading="props.loading"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <template #loading>
      <VProgressLinear indeterminate />
    </template>

    <template v-if="props.driver">
      <VRow>
        <!-- ── Driver Score & Summary Banner ─────────────────────────────── -->
        <VCol cols="12">
          <VCard variant="tonal" color="primary" class="pa-4">
            <div class="d-flex justify-space-between align-center flex-wrap gap-4">
              <div class="d-flex align-center gap-3">
                <VAvatar color="primary" size="48">
                  <VIcon icon="tabler-steering-wheel" size="28" />
                </VAvatar>
                <div>
                  <h5 class="text-h6 font-weight-bold mb-0">
                    {{ props.driver.employee?.full_name }}
                  </h5>
                  <span class="text-caption text-medium-emphasis">
                    {{ props.driver.employee?.department?.name || 'Department Unassigned' }} · {{ props.driver.employee?.position?.title || 'Position' }}
                  </span>
                </div>
              </div>

              <div class="d-flex align-center gap-3">
                <div class="text-end">
                  <div class="text-caption text-medium-emphasis">{{ t('fleet.safety_score', 'Safety Score') }}</div>
                  <div class="text-h6 font-weight-bold" :class="Number(props.driver.driver_score || 0) >= 80 ? 'text-success' : 'text-warning'">
                    {{ props.driver.driver_score != null ? `${props.driver.driver_score}/100` : '—' }}
                  </div>
                </div>
                <VChip
                  :color="levelColor(props.driver.driver_level)"
                  label
                  size="small"
                  class="text-capitalize font-weight-bold"
                >
                  {{ props.driver.driver_level }} Level
                </VChip>
                <VChip
                  :color="statusColor(props.driver.authorization_status)"
                  label
                  size="small"
                  class="text-capitalize"
                >
                  {{ props.driver.authorization_status }}
                </VChip>
              </div>
            </div>
          </VCard>
        </VCol>

        <!-- Suspension Alert if Suspended -->
        <VCol v-if="props.driver.authorization_status === 'suspended'" cols="12">
          <VAlert
            type="warning"
            variant="tonal"
            icon="tabler-alert-circle"
            class="mb-0"
          >
            <strong>{{ t('fleet.driver_suspended_alert', 'Driver Authorization Suspended') }}</strong>
            <div class="text-caption mt-1">
              {{ t('fleet.suspended_until', 'Suspended until') }}: {{ date(props.driver.suspended_until) }}
              <span v-if="props.driver.suspension_reason">· Reason: {{ props.driver.suspension_reason }}</span>
            </div>
          </VAlert>
        </VCol>

        <!-- ── License & Authorization Details ────────────────────────────── -->
        <VCol cols="12" md="6">
          <VCard variant="outlined" class="h-100 pa-4">
            <h6 class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center gap-2">
              <VIcon icon="tabler-license" size="20" class="text-primary" />
              {{ t('fleet.license_details', 'License & Validity') }}
            </h6>
            <div class="d-flex flex-column gap-2 text-body-2">
              <div class="d-flex justify-space-between align-center border-b pb-1">
                <span class="text-medium-emphasis">{{ t('fleet.license_number', 'License Number') }}:</span>
                <span class="font-mono font-weight-medium">{{ props.driver.license_number }}</span>
              </div>
              <div class="d-flex justify-space-between align-center border-b pb-1">
                <span class="text-medium-emphasis">{{ t('fleet.license_category', 'Category') }}:</span>
                <span class="font-weight-medium">
                  {{ props.driver.license_category || '—' }}
                  <span v-if="props.driver.license_categories?.length" class="text-caption text-medium-emphasis">
                    ({{ props.driver.license_categories.join(', ') }})
                  </span>
                </span>
              </div>
              <div class="d-flex justify-space-between align-center border-b pb-1">
                <span class="text-medium-emphasis">{{ t('fleet.license_issuer', 'Issuer / Authority') }}:</span>
                <span>{{ props.driver.license_issuer || '—' }}</span>
              </div>
              <div class="d-flex justify-space-between align-center border-b pb-1">
                <span class="text-medium-emphasis">{{ t('fleet.license_expiry', 'License Expiry') }}:</span>
                <span :class="new Date(props.driver.license_expiry_date) < new Date() ? 'text-error font-weight-bold' : ''">
                  {{ date(props.driver.license_expiry_date) }}
                </span>
              </div>
              <div class="d-flex justify-space-between align-center border-b pb-1">
                <span class="text-medium-emphasis">{{ t('fleet.authorization_expiry', 'Authorization Expiry') }}:</span>
                <span>{{ date(props.driver.expiry_date) }}</span>
              </div>
              <div class="d-flex justify-space-between align-center">
                <span class="text-medium-emphasis">{{ t('fleet.review_date', 'Next Review Due') }}:</span>
                <span>{{ date(props.driver.review_date) }}</span>
              </div>
            </div>
          </VCard>
        </VCol>

        <!-- ── Driving Eligibility & Restrictions ─────────────────────────── -->
        <VCol cols="12" md="6">
          <VCard variant="outlined" class="h-100 pa-4">
            <h6 class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center gap-2">
              <VIcon icon="tabler-car" size="20" class="text-info" />
              {{ t('fleet.eligibility', 'Vehicle Eligibility & Limits') }}
            </h6>
            <div class="d-flex flex-column gap-2 text-body-2">
              <div class="d-flex justify-space-between align-center border-b pb-1">
                <span class="text-medium-emphasis">{{ t('fleet.permitted_types', 'Permitted Vehicle Types') }}:</span>
                <span class="font-weight-medium text-capitalize">
                  {{ props.driver.permitted_vehicle_types?.join(', ') || t('fleet.all_standard', 'All Standard') }}
                </span>
              </div>
              <div class="d-flex justify-space-between align-center border-b pb-1">
                <span class="text-medium-emphasis">{{ t('fleet.drive_rental', 'Can Drive Rental Vehicles') }}:</span>
                <VChip size="x-small" :color="props.driver.can_drive_rental ? 'success' : 'secondary'">
                  {{ props.driver.can_drive_rental ? 'Yes' : 'No' }}
                </VChip>
              </div>
              <div class="d-flex justify-space-between align-center border-b pb-1">
                <span class="text-medium-emphasis">{{ t('fleet.drive_client', 'Can Drive Client Vehicles') }}:</span>
                <VChip size="x-small" :color="props.driver.can_drive_client_vehicles ? 'success' : 'secondary'">
                  {{ props.driver.can_drive_client_vehicles ? 'Yes' : 'No' }}
                </VChip>
              </div>
              <div class="d-flex justify-space-between align-center border-b pb-1">
                <span class="text-medium-emphasis">{{ t('fleet.max_passengers', 'Passenger Limit') }}:</span>
                <span>{{ props.driver.max_passengers ? `${props.driver.max_passengers} passengers` : 'Standard' }}</span>
              </div>
              <div class="d-flex justify-space-between align-center border-b pb-1">
                <span class="text-medium-emphasis">{{ t('fleet.max_cargo', 'Max Cargo Payload') }}:</span>
                <span>{{ props.driver.max_cargo_kg ? `${props.driver.max_cargo_kg} kg` : 'Standard' }}</span>
              </div>
              <div class="d-flex justify-space-between align-center">
                <span class="text-medium-emphasis">{{ t('fleet.restrictions', 'Operating Restrictions') }}:</span>
                <span class="text-caption">{{ props.driver.restriction_notes || t('fleet.none_recorded', 'None recorded') }}</span>
              </div>
            </div>
          </VCard>
        </VCol>

        <!-- ── Incidents & Safety Record ──────────────────────────────────── -->
        <VCol cols="12">
          <VCard variant="outlined" class="pa-4">
            <div class="d-flex justify-space-between align-center mb-3">
              <h6 class="text-subtitle-1 font-weight-bold d-flex align-center gap-2 mb-0">
                <VIcon icon="tabler-alert-triangle" size="20" class="text-warning" />
                {{ t('fleet.incidents_history', 'Safety Incidents & Violations') }}
                <VChip size="small" variant="tonal" :color="props.driver.incident_count > 0 ? 'warning' : 'success'">
                  {{ props.driver.incident_count || 0 }} {{ t('fleet.incidents', 'incidents') }}
                </VChip>
              </h6>
            </div>

            <div v-if="incidentsList.length > 0" class="d-flex flex-column gap-2">
              <VCard
                v-for="inc in incidentsList"
                :key="inc.id"
                variant="outlined"
                class="pa-3"
              >
                <div class="d-flex justify-space-between align-center flex-wrap gap-2">
                  <div class="d-flex align-center gap-2">
                    <VChip :color="severityColor(inc.severity)" size="x-small" label class="text-uppercase font-weight-bold">
                      {{ inc.severity }}
                    </VChip>
                    <span class="font-weight-medium text-body-2">{{ inc.title }}</span>
                  </div>
                  <span class="text-caption text-medium-emphasis">{{ date(inc.incident_date) }}</span>
                </div>
                <p v-if="inc.description" class="text-caption text-medium-emphasis mb-1 mt-1">
                  {{ inc.description }}
                </p>
                <div class="d-flex align-center gap-3 text-caption text-disabled flex-wrap">
                  <span v-if="inc.vehicle?.plate_number">
                    <VIcon icon="tabler-car" size="14" class="me-1" />{{ inc.vehicle.plate_number }}
                  </span>
                  <span v-if="inc.location">
                    <VIcon icon="tabler-map-pin" size="14" class="me-1" />{{ inc.location }}
                  </span>
                  <span v-if="inc.estimated_cost">
                    <VIcon icon="tabler-cash" size="14" class="me-1" />Cost: {{ inc.estimated_cost }} TND
                  </span>
                  <span v-if="inc.police_report_filed">
                    <VIcon icon="tabler-file-text" size="14" class="me-1" />Police Report: {{ inc.police_report_number || 'Filed' }}
                  </span>
                </div>
              </VCard>
            </div>
            <div v-else class="text-caption text-medium-emphasis py-2 text-center">
              {{ t('fleet.no_incidents', 'Clean record. No safety incidents or traffic violations recorded.') }}
            </div>
          </VCard>
        </VCol>

        <!-- ── Activity & Audit Log ───────────────────────────────────────── -->
        <VCol cols="12">
          <VCard variant="outlined" class="pa-4">
            <h6 class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center gap-2">
              <VIcon icon="tabler-history" size="20" class="text-secondary" />
              {{ t('fleet.activity_log', 'Authorization & Activity Audit Trail') }}
            </h6>

            <VList v-if="activityList.length > 0" density="compact" class="py-0">
              <VListItem
                v-for="event in activityList"
                :key="event.id"
                class="px-0 border-b"
              >
                <template #prepend>
                  <VAvatar size="28" color="primary" variant="tonal" class="me-2">
                    <VIcon icon="tabler-check" size="16" />
                  </VAvatar>
                </template>
                <VListItemTitle class="text-body-2 font-weight-medium">
                  {{ event.event_title }}
                </VListItemTitle>
                <VListItemSubtitle class="text-caption">
                  {{ event.event_description || '—' }} · {{ date(event.event_date || event.created_at) }}
                </VListItemSubtitle>
              </VListItem>
            </VList>
            <div v-else class="text-caption text-medium-emphasis py-2 text-center">
              {{ t('fleet.no_activity', 'No audit activity recorded yet.') }}
            </div>
          </VCard>
        </VCol>
      </VRow>
    </template>
  </WpDetailDialog>
</template>
