<script setup>
import { WpEmptyState, WpSectionCard } from '@/components/shared'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  versions: { type: Array, default: () => [] },
})

const emit = defineEmits(['createSnapshot'])

const { locale } = useI18n()

const latestVersionNumber = computed(() =>
  props.versions.reduce((max, v) => Math.max(max, Number(v.version_number) || 0), 0),
)

// Follows the app language rather than the browser's.
const formatDate = value => {
  if (!value) return '—'

  try {
    return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
  } catch {
    return new Date(value).toLocaleString()
  }
}
</script>

<template>
  <section
    id="section-versions"
    class="journal-section mb-6"
  >
    <WpSectionCard
      icon="tabler-history"
      :title="$t('Version History')"
      :subtitle="$t('Immutable revision history tracking silhouette, fabric, standards, and placement changes over time.')"
    >
      <template #actions>
        <VChip
          size="small"
          color="info"
          variant="tonal"
          class="font-mono"
        >
          {{ versions.length }} {{ $t('revisions') }}
        </VChip>
        <VBtn
          variant="tonal"
          color="info"
          prepend-icon="tabler-camera"
          @click="emit('createSnapshot')"
        >
          {{ $t('Create Snapshot') }}
        </VBtn>
      </template>

      <VTimeline
        v-if="versions.length"
        side="end"
        density="compact"
        truncate-line="both"
      >
        <VTimelineItem
          v-for="v in versions"
          :key="v.id"
          :dot-color="Number(v.version_number) === latestVersionNumber ? 'primary' : 'secondary'"
          size="x-small"
        >
          <div class="d-flex justify-space-between align-center flex-wrap gap-2">
            <div class="d-flex align-center flex-wrap gap-2 min-w-0">
              <VChip
                size="small"
                color="primary"
                variant="flat"
                class="font-mono font-weight-bold"
              >
                v{{ v.version_number }}
              </VChip>
              <span class="text-body-2 font-weight-medium text-break">{{ v.title || $t('Model Revision') }}</span>
            </div>
            <time
              class="text-caption text-medium-emphasis"
              :datetime="v.created_at"
            >
              {{ formatDate(v.created_at) }}
            </time>
          </div>
          <p
            v-if="v.change_summary"
            class="change-summary text-body-2 mb-0 mt-2"
          >
            {{ v.change_summary }}
          </p>
        </VTimelineItem>
      </VTimeline>

      <WpEmptyState
        v-else
        icon="tabler-history-off"
        :title="$t('No snapshot versions recorded yet.')"
        :subtitle="$t('Click &quot;Create Snapshot&quot; to save a milestone version.')"
      >
        <template #action>
          <VBtn
            variant="tonal"
            color="info"
            prepend-icon="tabler-camera"
            @click="emit('createSnapshot')"
          >
            {{ $t('Create Snapshot') }}
          </VBtn>
        </template>
      </WpEmptyState>
    </WpSectionCard>
  </section>
</template>

<style lang="scss" scoped>
.change-summary {
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  padding: 0.625rem 0.875rem;
}
</style>
