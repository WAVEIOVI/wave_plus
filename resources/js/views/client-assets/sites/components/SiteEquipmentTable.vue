<script setup>
import { WpMobileCard } from '@/components/shared'
import {
  equipmentPhoto,
  resolveComplianceVariant,
  resolveLifecycleVariant,
  useEquipmentHelpers,
} from '@/composables/client-assets/equipment/useEquipmentHelpers'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

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
const { smAndDown } = useDisplay()

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
  { title: t('Actions'), key: 'actions', sortable: false, align: 'end', width: '140px' },
])
</script>

<template>
  <div class="site-equipment-table-container">
    <!-- Mobile: Card-based list -->
    <div
      v-if="smAndDown"
      class="d-flex flex-column gap-2"
    >
      <WpMobileCard
        v-for="eq in items"
        :key="eq.id"
        :title="equipmentTitle(eq)"
        :subtitle="eq.equipment_code || `EQ #${eq.id}`"
        :status="eq.lifecycle_status || 'operational'"
        :status-map="LIFECYCLE_STATUS_MAP"
        :accent-color="resolveLifecycleVariant(eq.lifecycle_status)"
        :meta="[
          { label: t('Location'), value: resolveAreaLabel(eq) },
          { label: t('Serial / Tag'), value: eq.serial_number || eq.asset_tag || '—' },
          { label: t('Compliance'), value: complianceLabel(eq.compliance_status) || '—' },
        ]"
        @click="$emit('view', eq.id)"
      >
        <template #actions>
          <IconBtn
            size="small"
            @click="$emit('view', eq.id)"
          >
            <VIcon
              icon="tabler-eye"
              size="16"
            />
          </IconBtn>
          <IconBtn
            v-if="!readonly"
            size="small"
            color="info"
            @click="$emit('edit', eq)"
          >
            <VIcon
              icon="tabler-edit"
              size="16"
            />
          </IconBtn>
          <IconBtn
            v-if="!readonly"
            size="small"
            color="error"
            @click="$emit('delete', eq)"
          >
            <VIcon
              icon="tabler-trash"
              size="16"
            />
          </IconBtn>
        </template>
      </WpMobileCard>
    </div>

    <!-- Desktop: Data table -->
    <div
      v-else
      class="equipment-table-wrap"
    >
      <VDataTable
        :headers="tableHeaders"
        :items="items"
        item-value="id"
        :items-per-page="-1"
        hide-default-footer
        class="equipment-data-table"
      >
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
          <div class="d-flex align-center justify-end gap-1">
            <IconBtn
              size="small"
              :title="t('View')"
              @click="$emit('view', item.id)"
            >
              <VIcon
                icon="tabler-eye"
                size="18"
              />
            </IconBtn>
            <IconBtn
              v-if="!readonly"
              size="small"
              color="info"
              :title="t('Edit')"
              @click="$emit('edit', item)"
            >
              <VIcon
                icon="tabler-edit"
                size="18"
              />
            </IconBtn>
            <IconBtn
              v-if="!readonly"
              size="small"
              color="error"
              :title="t('Delete')"
              @click="$emit('delete', item)"
            >
              <VIcon
                icon="tabler-trash"
                size="18"
              />
            </IconBtn>
          </div>
        </template>
      </VDataTable>
    </div>
  </div>
</template>

<style scoped lang="scss">
.equipment-table-wrap {
  overflow-x: auto;
}

.equipment-data-table {
  min-inline-size: 760px;
}
</style>
