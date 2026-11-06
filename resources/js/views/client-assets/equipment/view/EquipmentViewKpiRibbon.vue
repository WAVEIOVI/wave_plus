<script setup>
/* eslint-disable camelcase */
import { WpKpiCard, WpKpiGroup } from '@/components/shared'
import {
  conditionStatusOptions,
  useEquipmentConstants,
} from '@/composables/client-assets/equipment/useEquipmentConstants'
import {
  resolveCriticalityVariant,
  resolveLifecycleVariant,
  useEquipmentHelpers,
} from '@/composables/client-assets/equipment/useEquipmentHelpers'
import { localizedValue } from '@/composables/useLocaleField'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  equipment: { type: Object, required: true },
  nextInspectionMeta: { type: Object, default: null },
})

const { t } = useI18n()
const { optionTitle } = useEquipmentConstants()

const {
  lifecycleLabel,
  complianceLabel,
  criticalityLabel,
} = useEquipmentHelpers()

const nextInspectionDateText = computed(() => {
  return props.equipment.next_inspection_date
    ? props.equipment.next_inspection_date.substring(0, 10)
    : t('Not Scheduled')
})
</script>

<template>
  <WpKpiGroup class="mb-4">
    <!-- 1. Operational & Condition -->
    <WpKpiCard
      :title="$t('Status')"
      :value="lifecycleLabel(equipment.lifecycle_status)"
      :subtitle="equipment.condition_status ? optionTitle(conditionStatusOptions, equipment.condition_status) : ''"
      icon="tabler-activity"
      :icon-color="resolveLifecycleVariant(equipment.lifecycle_status)"
    />

    <!-- 2. Next Inspection -->
    <WpKpiCard
      :title="$t('Next Inspection')"
      :value="nextInspectionDateText"
      :subtitle="nextInspectionMeta?.text || ''"
      icon="tabler-calendar-event"
      :icon-color="nextInspectionMeta?.color || 'primary'"
    />

    <!-- 3. Physical Location -->
    <WpKpiCard
      :title="$t('Location')"
      :value="localizedValue(equipment.site?.name) || $t('Unassigned Site')"
      :subtitle="equipment.area?.hierarchy_path || localizedValue(equipment.area?.name) || ''"
      icon="tabler-map-pin"
      icon-color="info"
    />

    <!-- 4. Criticality & Compliance -->
    <WpKpiCard
      :title="$t('Criticality')"
      :value="criticalityLabel(equipment.criticality)"
      :subtitle="complianceLabel(equipment.compliance_status)"
      icon="tabler-flame"
      :icon-color="resolveCriticalityVariant(equipment.criticality)"
    />
  </WpKpiGroup>
</template>
