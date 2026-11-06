<script setup>
/* eslint-disable camelcase */
import { WpEmptyState, WpSectionCard, WpSimpleTable } from '@/components/shared'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const props = defineProps({
  items: { type: Array, default: () => [] },
})

const router = useRouter()
const { t } = useI18n()

const totalItems = computed(() => props.items.length)
const availableItems = computed(() => props.items.filter(i => i.status === 'in_stock' || i.status === 'available').length)
const assignedItems = computed(() => props.items.filter(i => i.current_assignment?.employee_id || i.status === 'assigned').length)

const kpis = computed(() => [
  { key: 'total', icon: 'tabler-packages', color: 'primary', label: t('Total Produced'), value: totalItems.value },
  { key: 'available', icon: 'tabler-circle-check', color: 'success', label: t('Available in Stock'), value: availableItems.value },
  { key: 'assigned', icon: 'tabler-user-check', color: 'info', label: t('Assigned to Staff'), value: assignedItems.value },
])

const STATUS_COLORS = {
  in_stock: 'success',
  available: 'success',
  assigned: 'info',
  maintenance: 'warning',
  repair: 'warning',
  lost: 'error',
  damaged: 'error',
}

const statusColor = status => STATUS_COLORS[status] || 'secondary'
const humanize = value => (value ? String(value).replace(/_/g, ' ') : '—')

const columns = computed(() => [
  { key: 'item_code', title: t('Code'), mobile: 'title' },
  { key: 'size', title: t('Size') },
  { key: 'status', title: t('Status') },
  { key: 'item_condition', title: t('Condition') },
  { key: 'assigned_to', title: t('Assigned To') },
  { key: 'actions', title: '', align: 'end', mobile: 'hide' },
])

const openItem = item => {
  router.push({ name: 'client-assets-workwear-edit-id', params: { id: item.id }, query: { type: 'item' } })
}
</script>

<template>
  <section
    id="section-items"
    class="journal-section mb-6"
  >
    <WpSectionCard
      icon="tabler-hanger"
      :title="$t('Physical Items')"
      :subtitle="$t('Serialized physical workwear garments produced from this model specification.')"
    >
      <template #actions>
        <VChip
          size="small"
          color="primary"
          variant="tonal"
          class="font-mono"
        >
          {{ totalItems }} {{ $t('items') }}
        </VChip>
        <VBtn
          variant="tonal"
          color="primary"
          prepend-icon="tabler-external-link"
          :to="{ name: 'client-assets-workwear-list', query: { tab: 'items' } }"
        >
          {{ $t('View in Items Tab') }}
        </VBtn>
      </template>

      <template v-if="items.length">
        <!-- KPIs -->
        <div class="kpi-row mb-4">
          <div
            v-for="kpi in kpis"
            :key="kpi.key"
            class="kpi-tile"
          >
            <VAvatar
              :color="kpi.color"
              variant="tonal"
              size="36"
              rounded
            >
              <VIcon
                :icon="kpi.icon"
                size="20"
              />
            </VAvatar>
            <div>
              <span class="text-body-2 text-medium-emphasis d-block">{{ kpi.label }}</span>
              <span class="text-h6 font-weight-bold">{{ kpi.value }}</span>
            </div>
          </div>
        </div>

        <!-- Responsive Table -->
        <WpSimpleTable
          :columns="columns"
          :items="items"
          item-key="id"
        >
          <template #cell.item_code="{ item }">
            <span
              class="font-weight-bold text-primary font-mono cursor-pointer text-link"
              @click="openItem(item)"
            >
              {{ item.item_code }}
            </span>
          </template>

          <template #cell.size="{ item }">
            <VChip
              size="small"
              variant="outlined"
              class="font-mono"
            >
              {{ item.size || '—' }}
            </VChip>
          </template>

          <template #cell.status="{ item }">
            <VChip
              size="small"
              label
              variant="tonal"
              :color="statusColor(item.status)"
              class="text-capitalize"
            >
              {{ humanize(item.status) }}
            </VChip>
          </template>

          <template #cell.item_condition="{ item }">
            <span class="text-capitalize">{{ humanize(item.item_condition) }}</span>
          </template>

          <template #cell.assigned_to="{ item }">
            <span>{{ item.current_assignment?.employee?.full_name || '—' }}</span>
          </template>

          <template #cell.actions="{ item }">
            <VBtn
              icon="tabler-chevron-right"
              variant="text"
              size="small"
              color="secondary"
              @click="openItem(item)"
            />
          </template>
        </WpSimpleTable>
      </template>

      <WpEmptyState
        v-else
        icon="tabler-hanger"
        :title="$t('No physical items generated for this model yet.')"
        :subtitle="$t('Intake serialized items from the inventory tab to link them to this specification.')"
      />
    </WpSectionCard>
  </section>
</template>

<style lang="scss" scoped>
.kpi-row {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: 1fr;

  @media (min-width: 600px) {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

.kpi-tile {
  display: flex;
  align-items: center;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
}
</style>
