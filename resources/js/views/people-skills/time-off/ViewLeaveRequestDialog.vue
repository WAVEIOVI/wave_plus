<script setup>
/* eslint-disable camelcase */
import { WpDetailDialog, WpLoadingState, WpStatusChip } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { avatarText } from '@core/utils/formatters'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  leaveRequest: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  canApprove: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['update:modelValue', 'approve', 'reject', 'cancel'])

const { t, locale } = useI18n()

const isOpen = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const formatDate = d => d
  ? new Intl.DateTimeFormat(locale.value, { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(d))
  : '—'

const formatDateTime = d => d
  ? new Intl.DateTimeFormat(locale.value, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(d))
  : '—'

const statusLabel = s => {
  if (!s) return '—'

  const map = {
    pending: t('time_off.status.pending'),
    submitted: t('time_off.status.submitted'),
    approved: t('time_off.status.approved'),
    rejected: t('time_off.status.rejected'),
    cancelled: t('time_off.status.cancelled'),
    draft: t('time_off.status.draft'),
    taken: t('time_off.status.taken'),
  }

  return map[s] || t(`time_off.status.${s}`, s.replaceAll('_', ' '))
}
</script>

<template>
  <WpDetailDialog
    v-model="isOpen"
    :title="$t('time_off.view.title')"
    :loading="loading"
    max-width="640"
  >
    <template v-if="leaveRequest">
      <!-- Top Overview Banner -->
      <div class="d-flex flex-wrap align-center justify-space-between gap-3 pa-4 rounded-lg bg-surface border mb-4">
        <div class="d-flex align-center gap-3">
          <VAvatar
            size="44"
            color="primary"
            variant="tonal"
          >
            <VImg
              v-if="leaveRequest.employee?.avatar_url"
              :src="leaveRequest.employee.avatar_url"
            />
            <span
              v-else
              class="font-weight-medium"
            >{{ avatarText(leaveRequest.employee?.full_name || '?') }}</span>
          </VAvatar>
          <div>
            <div class="text-subtitle-1 font-weight-bold">
              {{ leaveRequest.employee?.full_name || '—' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              #{{ leaveRequest.employee?.employee_number || '—' }} · {{ localizedValue(leaveRequest.employee?.department?.name || leaveRequest.employee?.department || '—') }}
            </div>
          </div>
        </div>

        <div class="d-flex align-center gap-2">
          <WpStatusChip
            type="leave"
            :status="leaveRequest.status"
            :label="statusLabel(leaveRequest.status)"
            show-icon
          />
        </div>
      </div>

      <!-- Detail Grid -->
      <VRow>
        <!-- Reference -->
        <VCol
          cols="6"
          sm="4"
        >
          <div class="d-flex flex-column gap-1">
            <span class="text-caption text-medium-emphasis">{{ $t('time_off.col.reference') }}</span>
            <span class="font-weight-medium text-body-2">{{ leaveRequest.reference || '—' }}</span>
          </div>
        </VCol>

        <!-- Leave Type -->
        <VCol
          cols="6"
          sm="4"
        >
          <div class="d-flex flex-column gap-1">
            <span class="text-caption text-medium-emphasis">{{ $t('time_off.col.type') }}</span>
            <span class="font-weight-medium text-body-2 text-primary">
              {{ localizedValue(leaveRequest.leave_type?.name) || '—' }}
            </span>
          </div>
        </VCol>

        <!-- Total Days -->
        <VCol
          cols="12"
          sm="4"
        >
          <div class="d-flex flex-column gap-1">
            <span class="text-caption text-medium-emphasis">{{ $t('time_off.col.duration') }}</span>
            <div>
              <VChip
                color="primary"
                variant="tonal"
                size="small"
                class="font-weight-bold"
              >
                {{ leaveRequest.total_days }} {{ $t('time_off.col.days') }}
              </VChip>
            </div>
          </div>
        </VCol>

        <!-- Start Date -->
        <VCol cols="6">
          <div class="d-flex flex-column gap-1">
            <span class="text-caption text-medium-emphasis">{{ $t('time_off.col.from') }}</span>
            <span class="font-weight-medium text-body-2">
              <VIcon
                icon="tabler-calendar"
                size="16"
                class="me-1 text-medium-emphasis"
              />
              {{ formatDate(leaveRequest.start_date) }}
              <span
                v-if="leaveRequest.start_time"
                class="text-caption text-disabled ms-1"
              >({{ leaveRequest.start_time }})</span>
            </span>
          </div>
        </VCol>

        <!-- End Date -->
        <VCol cols="6">
          <div class="d-flex flex-column gap-1">
            <span class="text-caption text-medium-emphasis">{{ $t('time_off.col.to') }}</span>
            <span class="font-weight-medium text-body-2">
              <VIcon
                icon="tabler-calendar"
                size="16"
                class="me-1 text-medium-emphasis"
              />
              {{ formatDate(leaveRequest.end_date) }}
              <span
                v-if="leaveRequest.end_time"
                class="text-caption text-disabled ms-1"
              >({{ leaveRequest.end_time }})</span>
            </span>
          </div>
        </VCol>

        <!-- Reason -->
        <VCol cols="12">
          <div class="d-flex flex-column gap-1 pa-3 rounded-lg bg-surface border">
            <span class="text-caption text-medium-emphasis">{{ $t('time_off.view.reason') }}</span>
            <span class="text-body-2 text-pre-wrap">{{ leaveRequest.reason || '—' }}</span>
          </div>
        </VCol>

        <!-- Decision Notes / Rejection Reason -->
        <VCol
          v-if="leaveRequest.rejection_reason"
          cols="12"
        >
          <VAlert
            type="error"
            variant="tonal"
            density="compact"
            icon="tabler-alert-circle"
          >
            <div class="font-weight-medium text-caption mb-1">
              {{ $t('time_off.view.rejection_reason') }}
            </div>
            <div class="text-body-2">
              {{ leaveRequest.rejection_reason }}
            </div>
          </VAlert>
        </VCol>

        <VCol
          v-if="leaveRequest.decision_note && leaveRequest.status === 'approved'"
          cols="12"
        >
          <VAlert
            type="success"
            variant="tonal"
            density="compact"
            icon="tabler-circle-check"
          >
            <div class="font-weight-medium text-caption mb-1">
              {{ $t('time_off.view.decision_note') }}
            </div>
            <div class="text-body-2">
              {{ leaveRequest.decision_note }}
            </div>
          </VAlert>
        </VCol>

        <!-- Approval / Submission Metadata -->
        <VCol cols="12">
          <div class="d-flex flex-wrap align-center justify-space-between text-caption text-medium-emphasis pt-2 border-t">
            <span>
              {{ $t('Created') }}: {{ formatDateTime(leaveRequest.created_at) }}
              <span
                v-if="leaveRequest.requested_by_name"
                class="ms-1"
              >({{ $t('By') }} {{ leaveRequest.requested_by_name }})</span>
            </span>
            <span v-if="leaveRequest.approver_name">
              {{ $t('time_off.view.approved_by') }} {{ leaveRequest.approver_name }} {{ $t('time_off.view.on_date') }} {{ formatDateTime(leaveRequest.approved_at) }}
            </span>
          </div>
        </VCol>
      </VRow>
    </template>

    <template #actions>
      <div class="d-flex flex-wrap align-center justify-end gap-2 w-100">
        <!-- Action Buttons when Pending -->
        <template v-if="leaveRequest && ['submitted', 'pending'].includes(leaveRequest.status) && canApprove">
          <VBtn
            color="error"
            variant="outlined"
            prepend-icon="tabler-x"
            @click="emit('reject', leaveRequest.id)"
          >
            {{ $t('time_off.reject') }}
          </VBtn>

          <VBtn
            color="success"
            prepend-icon="tabler-check"
            @click="emit('approve', leaveRequest.id)"
          >
            {{ $t('time_off.approve') }}
          </VBtn>
        </template>

        <!-- Cancel option -->
        <VBtn
          v-if="leaveRequest && ['submitted', 'pending', 'approved'].includes(leaveRequest.status)"
          color="warning"
          variant="tonal"
          prepend-icon="tabler-ban"
          @click="emit('cancel', leaveRequest.id)"
        >
          {{ $t('time_off.cancel') }}
        </VBtn>

        <VBtn
          variant="tonal"
          color="secondary"
          @click="isOpen = false"
        >
          {{ $t('Close') }}
        </VBtn>
      </div>
    </template>
  </WpDetailDialog>
</template>
