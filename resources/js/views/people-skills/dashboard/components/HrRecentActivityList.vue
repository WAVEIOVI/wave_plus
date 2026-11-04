<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const props = defineProps({
  activities: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['refresh'])

const { t, locale } = useI18n()
const router = useRouter()

const formatTimeAgo = isoDate => {
  if (!isoDate) return '—'
  try {
    const date = new Date(isoDate)
    const now = new Date()
    const diffSec = Math.floor((now - date) / 1000)

    if (diffSec < 60) return t('common.just_now', 'Just now')
    if (diffSec < 3600) return `${Math.floor(diffSec / 60)}m ago`
    if (diffSec < 86400) return `${Math.floor(diffSec / 3600)}h ago`

    return new Intl.DateTimeFormat(locale.value, {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(date)
  } catch {
    return isoDate
  }
}

const navigate = item => {
  if (item?.route) {
    router.push(item.route)
  }
}
</script>

<template>
  <VCard
    border
    elevation="0"
    rounded="lg"
    class="hr-recent-activity-card h-100"
  >
    <VCardItem class="hr-dashboard-card-header">
      <template #prepend>
        <VAvatar
          color="primary"
          variant="tonal"
          rounded="lg"
          size="40"
        >
          <VIcon icon="tabler-history" />
        </VAvatar>
      </template>

      <VCardTitle class="text-h6 font-weight-bold">
        {{ $t('hr.dashboard.recent_activity', 'Recent HR Activity') }}
      </VCardTitle>
      <VCardSubtitle>
        {{ $t('hr.dashboard.recent_activity_subtitle', 'Latest events across employees, time off, attendance, and fleet') }}
      </VCardSubtitle>

      <template #append>
        <VBtn
          size="small"
          variant="text"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="emit('refresh')"
        >
          {{ $t('common.refresh', 'Refresh') }}
        </VBtn>
      </template>
    </VCardItem>

    <VDivider />

    <VCardText class="pa-0">
      <VSkeletonLoader
        v-if="loading"
        type="list-item-avatar-two-line@4"
        class="bg-transparent pa-4"
      />

      <div
        v-else-if="!activities.length"
        class="d-flex flex-column align-center justify-center py-8 text-medium-emphasis text-center"
      >
        <VIcon
          icon="tabler-history-off"
          size="36"
          class="mb-2 opacity-50"
        />
        <span class="text-body-2">{{ $t('hr.dashboard.no_recent_activity', 'No recent activity records found') }}</span>
      </div>

      <VList
        v-else
        lines="two"
        class="py-0"
      >
        <VListItem
          v-for="item in activities"
          :key="item.id"
          class="px-4 py-3 cursor-pointer hover-activity border-b"
          @click="navigate(item)"
        >
          <template #prepend>
            <VAvatar
              size="38"
              :color="item.color || 'primary'"
              variant="tonal"
              class="me-3"
            >
              <VIcon
                :icon="item.icon || 'tabler-bell'"
                size="20"
              />
            </VAvatar>
          </template>

          <VListItemTitle class="font-weight-semibold text-body-1">
            {{ item.title }}
          </VListItemTitle>
          <VListItemSubtitle class="text-caption text-medium-emphasis mt-0.5">
            {{ item.subtitle }}
          </VListItemSubtitle>

          <template #append>
            <div class="d-flex align-center gap-2">
              <span class="text-caption text-medium-emphasis">
                {{ formatTimeAgo(item.timestamp) }}
              </span>
              <VIcon
                icon="tabler-chevron-right"
                size="16"
                class="text-disabled"
              />
            </div>
          </template>
        </VListItem>
      </VList>
    </VCardText>
  </VCard>
</template>

<style scoped>
.hover-activity:hover {
  background-color: rgba(var(--v-theme-primary), 0.04);
  transition: background-color 0.15s ease-in-out;
}
</style>
