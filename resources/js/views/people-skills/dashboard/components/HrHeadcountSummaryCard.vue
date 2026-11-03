<script setup>
import { computed } from 'vue'

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({
      total: 0,
      active: 0,
      // eslint-disable-next-line camelcase
      on_leave: 0,
      suspended: 0,
      terminated: 0,
    }),
  },
  loading: { type: Boolean, default: false },
})

const breakdown = computed(() => [
  {
    key: 'active',
    label: 'hr.employees.active',
    icon: 'tabler-user-check',
    color: 'success',
    value: props.stats.active || 0,
  },
  {
    key: 'on_leave',
    label: 'hr.dashboard.on_leave',
    icon: 'tabler-calendar-time',
    color: 'info',
    value: props.stats.on_leave || 0,
  },
  {
    key: 'suspended',
    label: 'hr.dashboard.suspended',
    icon: 'tabler-user-pause',
    color: 'warning',
    value: props.stats.suspended || 0,
  },
  {
    key: 'terminated',
    label: 'hr.dashboard.terminated',
    icon: 'tabler-user-x',
    color: 'error',
    value: props.stats.terminated || 0,
  },
])
</script>

<template>
  <VCard class="border">
    <VCardItem>
      <template #prepend>
        <VAvatar
          color="primary"
          variant="tonal"
          rounded
        >
          <VIcon icon="tabler-users" />
        </VAvatar>
      </template>
      <VCardTitle class="text-h6 font-weight-semibold">
        {{ $t('hr.dashboard.total_headcount') }}
      </VCardTitle>
      <VCardSubtitle>
        {{ $t('hr.dashboard.headcount_subtitle') }}
      </VCardSubtitle>
    </VCardItem>

    <VDivider />

    <VCardText>
      <VSkeletonLoader
        v-if="loading"
        type="list-item-two-line@2"
        class="bg-transparent"
      />

      <template v-else>
        <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-4">
          <div>
            <h2 class="text-h3 font-weight-bold text-primary mb-0">
              {{ stats.total }}
            </h2>
            <span class="text-body-2 text-medium-emphasis">
              {{ $t('hr.dashboard.total_staff_records') }}
            </span>
          </div>
          <VChip
            color="success"
            size="large"
            variant="tonal"
            class="font-weight-medium"
          >
            {{ stats.active }} {{ $t('hr.dashboard.active_employees') }}
          </VChip>
        </div>

        <VDivider class="mb-4" />

        <VRow dense>
          <VCol
            v-for="item in breakdown"
            :key="item.key"
            cols="6"
            sm="3"
          >
            <div class="d-flex align-center gap-2">
              <VAvatar
                size="32"
                :color="item.color"
                variant="tonal"
              >
                <VIcon
                  :icon="item.icon"
                  size="18"
                />
              </VAvatar>
              <div>
                <div class="text-caption text-medium-emphasis text-truncate">
                  {{ $t(item.label) }}
                </div>
                <div class="text-subtitle-1 font-weight-bold">
                  {{ item.value }}
                </div>
              </div>
            </div>
          </VCol>
        </VRow>
      </template>
    </VCardText>
  </VCard>
</template>
