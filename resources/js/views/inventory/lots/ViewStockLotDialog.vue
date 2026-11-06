<script setup>
import { WpDetailDialog, WpInfoItem, WpJournalNav, WpStatusChip } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  lot: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  error: { type: [String, Object], default: null },
})

const emit = defineEmits(['update:modelValue', 'retry'])

const { t } = useI18n()

const sections = [
  { key: 'info', label: t('inventory.lots.sections.info'), icon: 'tabler-info-circle' },
  { key: 'stock_items', label: t('inventory.lots.sections.stock_items'), icon: 'tabler-stack' },
]

const activeSection = ref('info')

const formatDate = value => {
  if (!value) return '—'

  return new Date(value).toLocaleDateString()
}

const daysUntilExpiry = computed(() => {
  if (!props.lot?.expiry_date) return null
  const diff = new Date(props.lot.expiry_date) - new Date()

  return Math.ceil(diff / (1000 * 60 * 60 * 24))
})

const expiryColor = computed(() => {
  if (daysUntilExpiry.value === null) return 'secondary'
  if (daysUntilExpiry.value <= 0) return 'error'
  if (daysUntilExpiry.value <= 30) return 'warning'
  if (daysUntilExpiry.value <= 90) return 'info'

  return 'success'
})
</script>

<template>
  <WpDetailDialog
    :model-value="modelValue"
    :title="lot?.lot_number || t('inventory.lots.detail_title')"
    :subtitle="localizedValue(lot?.catalog_item?.name)"
    size="large"
    :loading="loading"
    :error="error"
    show-close-footer
    @update:model-value="emit('update:modelValue', $event)"
    @retry="emit('retry')"
  >
    <template v-if="lot">
      <WpJournalNav
        v-model="activeSection"
        :sections="sections"
        is-dialog
      />

      <!-- Info Section -->
      <div
        v-show="activeSection === 'info'"
        class="pa-4"
      >
        <VRow dense>
          <VCol cols="12">
            <WpInfoItem
              icon="tabler-tag"
              :label="t('inventory.lots.columns.lot_number')"
              :value="lot.lot_number"
              mono
            />
          </VCol>
          <VCol cols="12">
            <WpInfoItem
              icon="tabler-packages"
              :label="t('inventory.lots.columns.catalog_item')"
              :value="localizedValue(lot.catalog_item?.name) || lot.catalog_item?.code"
            />
          </VCol>
          <VCol cols="12">
            <WpInfoItem
              icon="tabler-building-factory"
              :label="t('inventory.lots.columns.supplier')"
              :value="lot.supplier?.name"
              hide-when-empty
            />
          </VCol>

          <VDivider class="my-2" />

          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-calendar"
              :label="t('inventory.lots.columns.manufacture_date')"
              :value="formatDate(lot.manufacture_date)"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-calendar-off"
              :label="t('inventory.lots.columns.expiry_date')"
              :value="formatDate(lot.expiry_date)"
            />
          </VCol>

          <!-- Days until expiry indicator -->
          <VCol
            v-if="lot.expiry_date"
            cols="12"
          >
            <div class="d-flex flex-column gap-1">
              <span class="text-caption text-medium-emphasis">
                {{ t('inventory.lots.days_until_expiry') }}:
                <strong :class="`text-${expiryColor}`">
                  {{ daysUntilExpiry !== null ? daysUntilExpiry : '—' }}
                </strong>
              </span>
              <VProgressLinear
                :model-value="Math.min(Math.max(daysUntilExpiry ?? 0, 0), 365)"
                :max="365"
                :color="expiryColor"
                height="6"
                rounded
              />
            </div>
          </VCol>

          <VCol cols="12">
            <WpInfoItem
              icon="tabler-circle"
              :label="t('inventory.lots.columns.status')"
            >
              <WpStatusChip
                :status="lot.status"
                :label="t(`inventory.lots.statuses.${lot.status}`)"
                size="small"
              />
            </WpInfoItem>
          </VCol>

          <VCol
            v-if="lot.notes"
            cols="12"
          >
            <WpInfoItem
              icon="tabler-notes"
              :label="t('inventory.lots.columns.notes')"
              :value="lot.notes"
            />
          </VCol>
        </VRow>
      </div>

      <!-- Stock Items Section -->
      <div
        v-show="activeSection === 'stock_items'"
        class="pa-4"
      >
        <template v-if="lot.stock_items && lot.stock_items.length > 0">
          <VList density="compact">
            <VListItem
              v-for="si in lot.stock_items"
              :key="si.id"
              :subtitle="si.location?.full_path"
            >
              <VListItemTitle>
                {{ si.location?.zone?.site ? localizedValue(si.location.zone.site.name) : '—' }}
              </VListItemTitle>
              <template #append>
                <div class="d-flex flex-column align-end gap-1">
                  <VChip
                    size="x-small"
                    color="primary"
                  >
                    {{ si.qty_on_hand }}
                  </VChip>
                  <span class="text-caption text-medium-emphasis">{{ si.condition }}</span>
                </div>
              </template>
            </VListItem>
          </VList>
        </template>
        <template v-else>
          <p class="text-medium-emphasis text-body-2 pa-2">
            {{ t('inventory.lots.no_stock_items') }}
          </p>
        </template>
      </div>
    </template>
  </WpDetailDialog>
</template>
