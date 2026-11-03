<script setup>
/* eslint-disable camelcase */
import {
  conditionStatusOptions,
  resolveConditionVariant,
  useEquipmentConstants,
} from '@/composables/client-assets/equipment/useEquipmentConstants'
import {
  resolveComplianceVariant,
  resolveCriticalityVariant,
  resolveLifecycleVariant,
  useEquipmentHelpers,
} from '@/composables/client-assets/equipment/useEquipmentHelpers'
import { localizedValue } from '@/composables/useLocaleField'

defineProps({
  equipment: { type: Object, required: true },
  nextInspectionMeta: { type: Object, default: null },
})

const { optionTitle } = useEquipmentConstants()
const { lifecycleLabel, complianceLabel, criticalityLabel } = useEquipmentHelpers()
</script>

<template>
  <div class="kpi-ribbon px-4 py-2 border rounded-lg mb-4 bg-surface-variant-subtle">
    <div class="kpi-ribbon-grid">
      <!-- 1. Operational & Condition -->
      <div class="kpi-ribbon-tile">
        <span class="kpi-ribbon-tile__label">{{ $t('Status') }}</span>
        <div class="d-flex align-center gap-1.5 flex-wrap">
          <VChip
            size="x-small"
            :color="resolveLifecycleVariant(equipment.lifecycle_status)"
            variant="flat"
            class="font-weight-bold text-uppercase"
          >
            {{ lifecycleLabel(equipment.lifecycle_status) }}
          </VChip>
          <VChip
            v-if="equipment.condition_status"
            size="x-small"
            :color="resolveConditionVariant(equipment.condition_status)"
            variant="tonal"
          >
            {{ optionTitle(conditionStatusOptions, equipment.condition_status) }}
          </VChip>
        </div>
      </div>

      <!-- 2. Next Inspection -->
      <div class="kpi-ribbon-tile">
        <span class="kpi-ribbon-tile__label">{{ $t('Next Inspection') }}</span>
        <div class="d-flex align-center gap-1.5 flex-wrap">
          <span class="text-caption font-weight-bold">
            {{ equipment.next_inspection_date ? equipment.next_inspection_date.substring(0, 10) : $t('Not Scheduled') }}
          </span>
          <VChip
            v-if="nextInspectionMeta"
            size="x-small"
            :color="nextInspectionMeta.color"
            variant="tonal"
            class="font-weight-semibold"
          >
            {{ nextInspectionMeta.text }}
          </VChip>
        </div>
      </div>

      <!-- 3. Physical Location -->
      <div class="kpi-ribbon-tile">
        <span class="kpi-ribbon-tile__label">{{ $t('Location') }}</span>
        <div class="text-caption font-weight-bold text-truncate">
          {{ localizedValue(equipment.site?.name) || $t('Unassigned Site') }}
          <span
            v-if="equipment.area?.hierarchy_path || equipment.area?.name"
            class="text-info font-weight-medium ms-1"
          >
            ({{ equipment.area?.hierarchy_path || localizedValue(equipment.area?.name) }})
          </span>
        </div>
      </div>

      <!-- 4. Criticality & Compliance -->
      <div class="kpi-ribbon-tile">
        <span class="kpi-ribbon-tile__label">{{ $t('Criticality') }}</span>
        <div class="d-flex align-center gap-1.5 flex-wrap">
          <VChip
            size="x-small"
            :color="resolveCriticalityVariant(equipment.criticality)"
            variant="tonal"
            class="font-weight-bold"
          >
            <VIcon
              start
              icon="tabler-flame"
              size="12"
            />
            {{ criticalityLabel(equipment.criticality) }}
          </VChip>
          <VChip
            size="x-small"
            :color="resolveComplianceVariant(equipment.compliance_status)"
            variant="tonal"
            class="font-weight-semibold"
          >
            {{ complianceLabel(equipment.compliance_status) }}
          </VChip>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.kpi-ribbon {
  background: rgba(var(--v-theme-on-surface), 0.02);
  border-color: rgba(var(--v-border-color), 0.1) !important;
}

.kpi-ribbon-grid {
  display: grid;
  gap: 0.75rem 1.25rem;
  grid-template-columns: repeat(4, 1fr);
  align-items: center;

  @media (max-width: 959px) {
    grid-template-columns: repeat(2, 1fr);
  }

  @media (max-width: 500px) {
    grid-template-columns: 1fr;
  }
}

.kpi-ribbon-tile {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-inline-size: 0;

  &__label {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: rgba(var(--v-theme-on-surface), 0.5);
    white-space: nowrap;
    flex-shrink: 0;
  }
}

.bg-surface-variant-subtle {
  background: rgba(var(--v-theme-on-surface), 0.025);
}
</style>
