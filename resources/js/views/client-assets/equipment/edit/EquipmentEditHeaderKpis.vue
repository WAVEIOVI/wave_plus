<script setup>
/* eslint-disable camelcase */
import { WpKpiCard, WpKpiGroup } from '@/components/shared'

defineProps({
  form: { type: Object, required: true },
  fetchedEquipment: { type: Object, default: () => ({}) },
  allEquipmentDocumentsCount: { type: Number, default: 0 },
  lifecycleStatusOptions: { type: Array, required: true },
  complianceStatusOptions: { type: Array, required: true },
  criticalityOptions: { type: Array, required: true },
  optionTitle: { type: Function, required: true },
  resolveLifecycleVariant: { type: Function, required: true },
  resolveComplianceVariant: { type: Function, required: true },
  resolveCriticalityVariant: { type: Function, required: true },
})

const emit = defineEmits(['scrollToSection'])
</script>

<template>
  <WpKpiGroup
    :cols="{ xs: 2, sm: 2, md: 4, lg: 4 }"
    class="mb-6 equipment-top-kpis"
  >
    <template #default="{ getColProps }">
      <VCol v-bind="getColProps(0)">
        <WpKpiCard
          :title="$t('Lifecycle Status')"
          :value="optionTitle(lifecycleStatusOptions, form.lifecycle_status)"
          icon="tabler-activity"
          :icon-color="resolveLifecycleVariant(form.lifecycle_status)"
          clickable
          @click="emit('scrollToSection', 'dates')"
        />
      </VCol>
      <VCol v-bind="getColProps(1)">
        <WpKpiCard
          :title="$t('Compliance Status')"
          :value="optionTitle(complianceStatusOptions, form.compliance_status)"
          icon="tabler-shield-check"
          :icon-color="resolveComplianceVariant(form.compliance_status)"
          clickable
          @click="emit('scrollToSection', 'dates')"
        />
      </VCol>
      <VCol v-bind="getColProps(2)">
        <WpKpiCard
          :title="$t('Asset Criticality')"
          :value="optionTitle(criticalityOptions, form.criticality)"
          icon="tabler-flame"
          :icon-color="resolveCriticalityVariant(form.criticality)"
          clickable
          @click="emit('scrollToSection', 'dates')"
        />
      </VCol>
      <VCol v-bind="getColProps(3)">
        <WpKpiCard
          :title="$t('Photos & Documents')"
          :value="(fetchedEquipment?.photos?.length || 0) + allEquipmentDocumentsCount"
          icon="tabler-photo"
          icon-color="info"
          clickable
          @click="emit('scrollToSection', 'media')"
        />
      </VCol>
    </template>
  </WpKpiGroup>
</template>

<style lang="scss" scoped>
@media (max-width: 425px) {
  .equipment-top-kpis {
    :deep(.v-col) {
      flex: 0 0 100% !important;
      max-width: 100% !important;
    }

    :deep(.wp-kpi-card) {
      .v-card-text {
        padding: 0.5rem 0.75rem !important;
      }

      .wp-kpi-card__title {
        font-size: 0.65rem !important;
        margin-bottom: 2px !important;
      }

      .wp-kpi-card__value,
      .text-h5 {
        font-size: 1.05rem !important;
      }

      .wp-kpi-card__icon {
        block-size: 32px !important;
        inline-size: 32px !important;

        .v-icon {
          font-size: 18px !important;
        }
      }
    }
  }
}
</style>
