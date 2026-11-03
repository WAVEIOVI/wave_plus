<script setup>
import WorkwearSectionCard from '@/views/client-assets/workwear/models/components/WorkwearSectionCard.vue'
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

const openItem = item => {
  router.push({ name: 'client-assets-workwear-edit-id', params: { id: item.id }, query: { type: 'item' } })
}
</script>

<template>
  <WorkwearSectionCard
    id="items"
    icon="tabler-hanger"
    :title="$t('Physical Items')"
    :subtitle="$t('Serialized physical workwear garments produced from this model specification.')"
  >
    <template #chips>
      <VChip
        size="small"
        color="primary"
        variant="tonal"
        class="font-mono"
      >
        {{ totalItems }} {{ $t('items') }}
      </VChip>
    </template>

    <template #actions>
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

      <!-- Desktop table -->
      <div class="d-none d-md-block">
        <VTable
          density="comfortable"
          hover
          class="text-no-wrap"
        >
          <thead>
            <tr>
              <th>{{ $t('Code') }}</th>
              <th>{{ $t('Size') }}</th>
              <th>{{ $t('Status') }}</th>
              <th>{{ $t('Condition') }}</th>
              <th>{{ $t('Assigned To') }}</th>
              <th class="text-end">
                <span class="sr-only">{{ $t('Actions') }}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in items"
              :key="item.id"
              class="cursor-pointer"
              tabindex="0"
              @click="openItem(item)"
              @keydown.enter="openItem(item)"
            >
              <td class="font-weight-bold text-primary font-mono">
                {{ item.item_code }}
              </td>
              <td>
                <VChip
                  size="small"
                  variant="outlined"
                  class="font-mono"
                >
                  {{ item.size || '—' }}
                </VChip>
              </td>
              <td>
                <VChip
                  size="small"
                  label
                  variant="tonal"
                  :color="statusColor(item.status)"
                  class="text-capitalize"
                >
                  {{ humanize(item.status) }}
                </VChip>
              </td>
              <td class="text-capitalize">
                {{ humanize(item.item_condition) }}
              </td>
              <td>{{ item.current_assignment?.employee?.full_name || '—' }}</td>
              <td class="text-end">
                <VIcon
                  icon="tabler-chevron-right"
                  size="18"
                  class="text-disabled"
                />
              </td>
            </tr>
          </tbody>
        </VTable>
      </div>

      <!-- Mobile cards -->
      <div class="d-flex d-md-none flex-column gap-2">
        <VCard
          v-for="item in items"
          :key="item.id"
          variant="outlined"
          class="pa-3"
          @click="openItem(item)"
        >
          <div class="d-flex justify-space-between align-center gap-2 mb-1">
            <span class="font-mono font-weight-bold text-primary">{{ item.item_code }}</span>
            <VChip
              size="small"
              label
              variant="tonal"
              :color="statusColor(item.status)"
              class="text-capitalize"
            >
              {{ humanize(item.status) }}
            </VChip>
          </div>
          <div class="text-body-2 text-medium-emphasis">
            {{ $t('Size:') }} {{ item.size || '—' }}
            · {{ item.current_assignment?.employee?.full_name || $t('Unassigned') }}
          </div>
        </VCard>
      </div>
    </template>

    <div
      v-else
      class="empty-state"
    >
      <VIcon
        icon="tabler-database-off"
        size="36"
        class="text-disabled"
      />
      <p class="text-body-1 mb-0">
        {{ $t('No physical items generated for this model yet.') }}
      </p>
    </div>
  </WorkwearSectionCard>
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

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  border: 1px dashed rgba(var(--v-border-color), 0.4);
  border-radius: 10px;
  gap: 0.5rem;
  padding: 2rem 1rem;
  text-align: center;
}

// Screen-reader-only text (header of the icon-only column)
.sr-only {
  position: absolute;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  block-size: 1px;
  inline-size: 1px;
  white-space: nowrap;
}

tbody tr:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
}
</style>
