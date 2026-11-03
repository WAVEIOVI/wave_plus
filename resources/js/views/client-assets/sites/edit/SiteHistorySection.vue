<script setup>
import { WpSectionCard } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'

defineProps({
  site: { type: Object, default: null },
  form: { type: Object, required: true },
  siteAreasFlat: { type: Array, default: () => [] },
  siteEquipment: { type: Array, default: () => [] },
  operationalCount: { type: Number, default: 0 },
})
</script>

<template>
  <section
    id="section-history"
    class="journal-section mb-6"
  >
    <WpSectionCard
      :title="$t('Site Activity & Audit Timeline')"
      :subtitle="$t('Commissioning history, equipment installations, and structural configuration events')"
      icon="tabler-history"
      icon-color="primary"
    >
      <VTimeline
        density="compact"
        align="start"
        side="end"
        truncate-line="both"
        class="v-timeline--variant-outlined"
      >
        <VTimelineItem
          dot-color="primary"
          size="x-small"
        >
          <div class="d-flex justify-space-between align-center mb-1 flex-wrap">
            <span class="text-body-2 font-weight-bold">{{ $t('Facility Site Commissioned') }}</span>
            <span class="text-caption text-disabled">{{ form.opened_at || site?.created_at?.substring(0, 10) }}</span>
          </div>
          <p class="text-caption text-medium-emphasis mb-0">
            {{ $t('Site registered with code {code} under client {client}.', { code: site?.site_code, client: site?.client?.legal_name }) }}
          </p>
        </VTimelineItem>

        <VTimelineItem
          v-if="siteAreasFlat.length"
          dot-color="info"
          size="x-small"
        >
          <div class="d-flex justify-space-between align-center mb-1 flex-wrap">
            <span class="text-body-2 font-weight-bold">{{ $t('Building Zones Configured') }}</span>
            <span class="text-caption text-disabled">{{ siteAreasFlat.length }} {{ $t('Areas Registered') }}</span>
          </div>
          <p class="text-caption text-medium-emphasis mb-0">
            {{ $t('Hierarchical zones configured including {zones}{ellipsis}', { zones: siteAreasFlat.slice(0, 3).map(a => localizedValue(a.name)).join(', '), ellipsis: siteAreasFlat.length > 3 ? '...' : '.' }) }}
          </p>
        </VTimelineItem>

        <VTimelineItem
          v-if="siteEquipment.length"
          dot-color="warning"
          size="x-small"
        >
          <div class="d-flex justify-space-between align-center mb-1 flex-wrap">
            <span class="text-body-2 font-weight-bold">{{ $t('Equipment Assets Deployed') }}</span>
            <span class="text-caption text-disabled">{{ siteEquipment.length }} {{ $t('Active Assets') }}</span>
          </div>
          <p class="text-caption text-medium-emphasis mb-0">
            {{ $t('Currently servicing {count} installed assets with {operational} in operational status.', { count: siteEquipment.length, operational: operationalCount }) }}
          </p>
        </VTimelineItem>
      </VTimeline>
    </WpSectionCard>
  </section>
</template>
