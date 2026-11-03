<script setup>
import { computed } from 'vue'

/* eslint-disable camelcase */
const props = defineProps({
  certifications: {
    type: Object,
    default: () => ({
      valid: 0,
      expiring_30_days: 0,
      expiring_60_days: 0,
      expiring_90_days: 0,
      expired: 0,
    }),
  },
  loading: { type: Boolean, default: false },
})
/* eslint-enable camelcase */

const buckets = computed(() => [
  {
    key: 'valid',
    label: 'hr.dashboard.valid_certs',
    icon: 'tabler-shield-check',
    color: 'success',
    value: props.certifications.valid || 0,
  },
  {
    key: 'expiring_30',
    label: 'hr.dashboard.expiring_soon',
    icon: 'tabler-alert-circle',
    color: 'warning',
    value: props.certifications.expiring_30_days || 0,
  },
  {
    key: 'expiring_60',
    label: 'hr.dashboard.expiring_60',
    icon: 'tabler-clock-hour-4',
    color: 'warning',
    value: props.certifications.expiring_60_days || 0,
  },
  {
    key: 'expiring_90',
    label: 'hr.dashboard.expiring_90',
    icon: 'tabler-clock-hour-9',
    color: 'secondary',
    value: props.certifications.expiring_90_days || 0,
  },
  {
    key: 'expired',
    label: 'hr.dashboard.expired_certs',
    icon: 'tabler-shield-x',
    color: 'error',
    value: props.certifications.expired || 0,
  },
])
</script>

<template>
  <VCard class="h-100 border">
    <VCardItem>
      <template #prepend>
        <VAvatar
          color="warning"
          variant="tonal"
          rounded
        >
          <VIcon icon="tabler-certificate" />
        </VAvatar>
      </template>
      <VCardTitle class="text-h6 font-weight-semibold">
        {{ $t('hr.dashboard.certifications_radar') }}
      </VCardTitle>
      <VCardSubtitle>
        {{ $t('hr.dashboard.certifications_subtitle') }}
      </VCardSubtitle>
    </VCardItem>

    <VDivider />

    <VCardText>
      <VSkeletonLoader
        v-if="loading"
        type="list-item-two-line@3"
        class="bg-transparent"
      />

      <template v-else>
        <VAlert
          v-if="certifications.expired > 0"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          <span class="text-caption font-weight-bold">
            {{ $t('hr.dashboard.certs_expired_alert', { count: certifications.expired }) }}
          </span>
        </VAlert>

        <VAlert
          v-else-if="certifications.expiring_30_days > 0"
          type="warning"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          <span class="text-caption font-weight-medium">
            {{ $t('hr.dashboard.certs_expiring_alert', { count: certifications.expiring_30_days }) }}
          </span>
        </VAlert>

        <div class="d-flex flex-column gap-y-3">
          <div
            v-for="bucket in buckets"
            :key="bucket.key"
            class="d-flex align-center justify-space-between"
          >
            <span class="d-flex align-center gap-2">
              <VAvatar
                size="28"
                :color="bucket.color"
                variant="tonal"
              >
                <VIcon
                  :icon="bucket.icon"
                  size="16"
                />
              </VAvatar>
              <span class="text-body-2">{{ $t(bucket.label) }}</span>
            </span>
            <VChip
              :color="bucket.color"
              size="small"
              label
            >
              {{ bucket.value }}
            </VChip>
          </div>
        </div>

        <VBtn
          block
          variant="tonal"
          color="secondary"
          size="small"
          class="mt-4"
          :to="{ name: 'people-skills-skills-list' }"
        >
          <VIcon
            icon="tabler-eye"
            start
          />
          {{ $t('hr.dashboard.view_certifications_matrix') }}
        </VBtn>
      </template>
    </VCardText>
  </VCard>
</template>
