<script setup>
/* eslint-disable camelcase */
import { WpMobileCard, WpResponsiveList, WpRowActions } from '@/components/shared'
import {
  equipmentPhoto,
  resolveComplianceVariant,
  resolveLifecycleVariant,
  useEquipmentHelpers,
} from '@/composables/client-assets/equipment/useEquipmentHelpers'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  items: { type: Array, default: () => [] },
  readonly: { type: Boolean, default: false },
  resolveAreaLabel: { type: Function, default: () => '' },
})

const emit = defineEmits([
  'view',
  'edit',
  'delete',
])

const { t } = useI18n()

const {
  equipmentTitle,
  lifecycleLabel,
  complianceLabel,
  inspectionCountdown,
} = useEquipmentHelpers()

const LIFECYCLE_STATUS_MAP = {
  operational: { label: t('Operational'), color: 'success' },
  active: { label: t('Active'), color: 'success' },
  under_maintenance: { label: t('Under Maintenance'), color: 'warning' },
  stored: { label: t('In Storage'), color: 'secondary' },
  draft: { label: t('Draft'), color: 'secondary' },
  decommissioned: { label: t('Decommissioned'), color: 'error' },
}

const tableHeaders = computed(() => [
  { title: t('Asset'), key: 'asset', sortable: false },
  { title: t('Location'), key: 'location', sortable: false },
  { title: t('Lifecycle'), key: 'lifecycle', sortable: false },
  { title: t('Compliance'), key: 'compliance', sortable: false },
  { title: t('Serial / Tag'), key: 'serial', sortable: false },
  { title: t('Next Inspection'), key: 'inspection', sortable: false },
  { title: t('Actions'), key: 'actions', sortable: false, align: 'end', width: '120px' },
])

const getRowActions = item => {
  const actions = [
    { label: t('View'), icon: 'tabler-eye', emit: 'view' },
  ]

  if (!props.readonly) {
    actions.push(
      { label: t('Edit'), icon: 'tabler-edit', emit: 'edit' },
      { label: t('Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error' },
    )
  }

  return actions
}
</script>

<template>
  <div class="site-equipment-table-container">
    <WpResponsiveList
      :headers="tableHeaders"
      :items="items"
      :total-items="items.length"
      hide-pagination
      item-value="id"
      density="compact"
    >
      <!-- Mobile: Card layout via mobile-card slot -->
      <template #mobile-card="{ item }">
        <WpMobileCard
          :title="equipmentTitle(item)"
          :subtitle="item.equipment_code || `EQ #${item.id}`"
          :status="item.lifecycle_status || 'operational'"
          :status-map="LIFECYCLE_STATUS_MAP"
          :accent-color="resolveLifecycleVariant(item.lifecycle_status)"
          :meta="[
            { label: t('Location'), value: resolveAreaLabel(item) },
            { label: t('Serial / Tag'), value: item.serial_number || item.asset_tag || '—' },
            { label: t('Compliance'), value: complianceLabel(item.compliance_status) || '—' },
          ]"
          @click="$emit('view', item.id)"
        >
          <template #actions>
            <WpRowActions
              :actions="getRowActions(item)"
              @view="$emit('view', item.id)"
              @edit="$emit('edit', item)"
              @delete="$emit('delete', item)"
            />
          </template>
        </WpMobileCard>
      </template>

      <!-- Desktop Table Column Slots -->
      <template #item.asset="{ item }">
        <div class="d-flex align-center gap-3 py-1">
          <VAvatar
            size="36"
            rounded="lg"
            color="primary"
            variant="tonal"
          >
            <VImg
              v-if="equipmentPhoto(item)"
              :src="equipmentPhoto(item)"
            />
            <VIcon
              v-else
              icon="tabler-fire-extinguisher"
              size="18"
            />
          </VAvatar>
          <div class="min-w-0">
            <span
              class="text-body-2 font-weight-bold d-block text-truncate cursor-pointer text-primary"
              @click="$emit('view', item.id)"
            >
              {{ equipmentTitle(item) }}
            </span>
            <span class="text-caption font-weight-medium">
              {{ item.equipment_code }}
            </span>
          </div>
        </div>
      </template>

      <template #item.location="{ item }">
        <span class="text-body-2">{{ resolveAreaLabel(item) }}</span>
      </template>

      <template #item.lifecycle="{ item }">
        <VChip
          size="small"
          :color="resolveLifecycleVariant(item.lifecycle_status)"
          label
          class="text-capitalize"
        >
          {{ lifecycleLabel(item.lifecycle_status) }}
        </VChip>
      </template>

      <template #item.compliance="{ item }">
        <VChip
          size="small"
          :color="resolveComplianceVariant(item.compliance_status)"
          variant="tonal"
          class="text-capitalize"
        >
          {{ complianceLabel(item.compliance_status) || '—' }}
        </VChip>
      </template>

      <template #item.serial="{ item }">
        <span class="text-caption">{{ item.serial_number || item.asset_tag || '—' }}</span>
      </template>

      <template #item.inspection="{ item }">
        <VChip
          v-if="inspectionCountdown(item.next_inspection_date)"
          size="x-small"
          :color="inspectionCountdown(item.next_inspection_date).color"
          variant="tonal"
          :prepend-icon="inspectionCountdown(item.next_inspection_date).icon"
        >
          {{ inspectionCountdown(item.next_inspection_date).text }}
        </VChip>
        <span
          v-else
          class="text-disabled text-caption"
        >—</span>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex align-center justify-end">
          <WpRowActions
            :actions="getRowActions(item)"
            @view="$emit('view', item.id)"
            @edit="$emit('edit', item)"
            @delete="$emit('delete', item)"
          />
        </div>
      </template>
    </WpResponsiveList>
  </div>
</template>
