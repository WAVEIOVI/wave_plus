<script setup>
/**
 * GrnRejectedItemsTab.vue
 * Tab panel showing lines where qty_rejected > 0 in a GRN.
 */
import {
  getGrnLineItemCode,
  getGrnLineItemName,
  getGrnLineVariantSku,
} from '@/composables/useGrnLineDisplay'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  lines: { type: Array, default: () => [] },
})

const { t } = useI18n()

const rejectedLines = computed(() =>
  (props.lines || []).filter(l => (l.qty_rejected || 0) > 0),
)

const reasonColor = reason => {
  const map = {
    damaged: 'error',
    wrong_item: 'warning',
    quality_issue: 'error',
    quantity_mismatch: 'info',
    expired: 'error',
    other: 'secondary',
  }

  return map[reason] || 'secondary'
}
</script>

<template>
  <div class="pa-4">
    <div
      v-if="rejectedLines.length === 0"
      class="d-flex flex-column align-center justify-center py-12 text-center"
    >
      <VAvatar
        color="success"
        variant="tonal"
        size="64"
        rounded="xl"
        class="mb-3"
      >
        <VIcon
          icon="tabler-circle-check"
          size="32"
        />
      </VAvatar>
      <p class="text-body-1 font-weight-medium mb-1">
        {{ t('scp.grn.rejected.empty_title') }}
      </p>
      <p class="text-caption text-medium-emphasis">
        {{ t('scp.grn.rejected.empty_subtitle') }}
      </p>
    </div>

    <VTable
      v-else
      density="comfortable"
      class="rejected-items-table"
    >
      <thead>
        <tr>
          <th>{{ t('scp.grn.rejected.col.item') }}</th>
          <th class="text-center">
            {{ t('scp.grn.qty.ordered') }}
          </th>
          <th class="text-center">
            {{ t('scp.grn.qty.received') }}
          </th>
          <th class="text-center text-error">
            {{ t('scp.grn.qty.rejected') }}
          </th>
          <th>{{ t('scp.grn.rejected.col.rejection_reason') }}</th>
          <th>{{ t('scp.grn.rejected.col.notes') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="line in rejectedLines"
          :key="line.id || line._key"
          class="rejected-row"
        >
          <td>
            <div class="d-flex flex-column">
              <span class="text-subtitle-2 font-weight-bold">{{ getGrnLineItemName(line) }}</span>
              <span class="text-caption text-disabled">{{ getGrnLineItemCode(line) }} • {{ getGrnLineVariantSku(line) }}</span>
            </div>
          </td>
          <td class="text-center font-weight-medium">
            {{ line.qty_ordered || '—' }}
          </td>
          <td class="text-center font-weight-medium">
            {{ line.qty_received || 0 }}
          </td>
          <td class="text-center">
            <VChip
              color="error"
              variant="elevated"
              size="small"
              label
            >
              {{ line.qty_rejected }}
            </VChip>
          </td>
          <td>
            <VChip
              v-if="line.rejection_reason"
              :color="reasonColor(line.rejection_reason)"
              size="x-small"
              label
              variant="tonal"
              class="text-capitalize"
            >
              {{ (line.rejection_reason || '').replace(/_/g, ' ') }}
            </VChip>
            <span
              v-else
              class="text-disabled"
            >—</span>
          </td>
          <td>
            <span class="text-caption">{{ line.rejection_notes || line.notes || '—' }}</span>
          </td>
        </tr>
      </tbody>
    </VTable>
  </div>
</template>

<style lang="scss" scoped>
.rejected-items-table {
  :deep(thead th) {
    background: rgba(var(--v-theme-error), 0.03) !important;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
  }
}

.rejected-row {
  border-inline-start: 3px solid rgb(var(--v-theme-error));
}
</style>
