<script setup>
import { WpStatusCard } from '@/components/shared'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  lifecycleStatus: { type: String, default: 'operational' },
  conditionStatus: { type: String, default: 'good' },
  complianceStatus: { type: String, default: 'compliant' },
  criticality: { type: String, default: 'medium' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits([
  'update:lifecycleStatus',
  'update:conditionStatus',
  'update:complianceStatus',
  'update:criticality',
])

const { t } = useI18n()

// ── Status Options Definitions ───────────────────────────────────────────────
const complianceOptions = computed(() => [
  {
    value: 'compliant',
    title: t('Compliant'),
    color: 'success',
    icon: 'tabler-shield-check',
    description: t('All checks passed and regulatory standards met without deficiency.'),
  },
  {
    value: 'due_soon',
    title: t('Due Soon'),
    color: 'warning',
    icon: 'tabler-clock',
    description: t('Inspection or mandatory maintenance due within upcoming schedule window.'),
  },
  {
    value: 'overdue',
    title: t('Overdue'),
    color: 'error',
    icon: 'tabler-alarm',
    description: t('Service interval or regulatory inspection date has passed.'),
  },
  {
    value: 'non_compliant',
    title: t('Non-Compliant'),
    color: 'error',
    icon: 'tabler-shield-x',
    description: t('Safety violation or failed technical inspection requiring remediation.'),
  },
  {
    value: 'not_applicable',
    title: t('Not Applicable'),
    color: 'secondary',
    icon: 'tabler-minus',
    description: t('No mandatory compliance schedule applies to this equipment.'),
  },
])

const criticalityOptions = computed(() => [
  {
    value: 'low',
    title: t('Low Criticality'),
    color: 'info',
    icon: 'tabler-chevrons-down',
    description: t('Standard asset; low operational or safety impact if out of service.'),
  },
  {
    value: 'medium',
    title: t('Medium Criticality'),
    color: 'secondary',
    icon: 'tabler-equal',
    description: t('Important operational equipment with moderate facility impact.'),
  },
  {
    value: 'high',
    title: t('High Criticality'),
    color: 'warning',
    icon: 'tabler-chevrons-up',
    description: t('Vital facility asset required for emergency or critical workflow.'),
  },
  {
    value: 'critical',
    title: t('Critical (Zero Tolerance)'),
    color: 'error',
    icon: 'tabler-flame',
    description: t('Life safety or primary emergency asset; zero tolerance for failure.'),
  },
])

const conditionOptions = computed(() => [
  {
    value: 'excellent',
    title: t('Excellent'),
    color: 'success',
    icon: 'tabler-star',
    description: t('Like-new condition; no visible wear, optimal performance verified.'),
  },
  {
    value: 'good',
    title: t('Good'),
    color: 'success',
    icon: 'tabler-thumb-up',
    description: t('Normal operational wear; fully functional within manufacturer specs.'),
  },
  {
    value: 'fair',
    title: t('Fair'),
    color: 'warning',
    icon: 'tabler-alert-circle',
    description: t('Cosmetic wear or minor aging; operational but monitor in next cycle.'),
  },
  {
    value: 'poor',
    title: t('Poor'),
    color: 'error',
    icon: 'tabler-alert-triangle',
    description: t('Degraded physical condition; service or overhaul recommended soon.'),
  },
  {
    value: 'critical',
    title: t('Critical / Damaged'),
    color: 'error',
    icon: 'tabler-crash',
    description: t('Significant damage or defect; unsafe for active service until repaired.'),
  },
])
</script>

<template>
  <div class="equipment-status-cards">
    <!-- Point 6.2: Compliance Status as MAIN CARD -->
    <VRow class="mb-2">
      <VCol cols="12">
        <div class="main-status-card-wrap">
          <WpStatusCard
            :model-value="props.complianceStatus"
            :status-options="complianceOptions"
            :title="t('Compliance & Regulatory Rating')"
            :subtitle="t('Primary compliance classification based on inspection cycles and legal standards')"
            :readonly="props.disabled"
            dialog-icon="tabler-shield-check"
            :dialog-title="t('Change Compliance Status')"
            class="compliance-main-card"
            @update:model-value="v => emit('update:complianceStatus', v)"
          />
        </div>
      </VCol>
    </VRow>

    <!-- Point 6.2: Asset Criticality & Physical Condition Cards -->
    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <WpStatusCard
          :model-value="props.criticality"
          :status-options="criticalityOptions"
          :title="t('Asset Criticality Rating')"
          :subtitle="t('Operational importance and failure risk index')"
          :readonly="props.disabled"
          dialog-icon="tabler-flame"
          :dialog-title="t('Change Asset Criticality')"
          @update:model-value="v => emit('update:criticality', v)"
        />
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <WpStatusCard
          :model-value="props.conditionStatus"
          :status-options="conditionOptions"
          :title="t('Physical Condition Rating')"
          :subtitle="t('Visual and operational state assessed during inspection')"
          :readonly="props.disabled"
          dialog-icon="tabler-heartbeat"
          :dialog-title="t('Change Physical Condition')"
          @update:model-value="v => emit('update:conditionStatus', v)"
        />
      </VCol>
    </VRow>
  </div>
</template>

<style lang="scss" scoped>
.compliance-main-card {
  border-width: 1.5px;
}
</style>
