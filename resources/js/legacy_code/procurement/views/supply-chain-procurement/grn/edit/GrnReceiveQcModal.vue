<script setup>
import {
  getGrnLineItemCode,
  getGrnLineItemName,
  getGrnLineVariantName,
  getGrnLineVariantSku,
  resolveCatalogItem,
  resolveVariant,
} from '@/composables/useGrnLineDisplay'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  line: { type: Object, default: null },
  lineIndex: { type: Number, default: null },
  locations: { type: Array, default: () => [] },
  zones: { type: Array, default: () => [] },
  isLocked: { type: Boolean, default: false },
})

const emit = defineEmits([
  'update:modelValue',
  'update-field',
  'qty-received',
  'qty-accepted',
  'qty-rejected',
  'zone-change',
  'remove-line',
])

const { t } = useI18n()

const isOpen = computed({
  get: () => props.modelValue,
  set: v => emit('update:modelValue', v),
})

const catalogItem = computed(() => resolveCatalogItem(props.line))
const variant = computed(() => resolveVariant(props.line))

const itemName = computed(() => getGrnLineItemName(props.line))
const itemCode = computed(() => getGrnLineItemCode(props.line))
const variantName = computed(() => getGrnLineVariantName(props.line))
const sku = computed(() => getGrnLineVariantSku(props.line))

const isStockable = computed(() => !catalogItem.value || catalogItem.value.is_stockable !== false)

const filteredLocations = computed(() => {
  const zoneId = props.line?.selected_zone_id
  if (!zoneId) return props.locations
  
  return props.locations.filter(loc => loc.zone_id === zoneId)
})

const qcOptions = computed(() => [
  { value: 'passed', title: t('scp.grn.qc_results.passed'), icon: 'tabler-circle-check', color: 'success' },
  { value: 'passed_with_remarks', title: t('scp.grn.qc_results.passed_with_remarks'), icon: 'tabler-alert-circle', color: 'warning' },
  { value: 'rejected', title: t('scp.grn.qc_results.rejected'), icon: 'tabler-circle-x', color: 'error' },
])

const showRejectionReason = computed(() =>
  ['passed_with_remarks', 'rejected'].includes(props.line?.qc_result),
)

const certificateItems = ['CoC', 'MSDS', 'Warranty', 'Calibration', 'Test Report', 'ISO']

const close = () => { isOpen.value = false }

const updateField = (field, value) => emit('update-field', field, value)
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="920"
    persistent
    scrollable
    class="grn-receive-qc-dialog"
  >
    <VCard
      v-if="line"
      class="grn-receive-qc-card"
      rounded="xl"
    >
      <!-- Header -->
      <VCardItem class="grn-modal-header pa-5 pb-4">
        <template #prepend>
          <VAvatar
            rounded="lg"
            size="48"
            color="primary"
            variant="tonal"
          >
            <VIcon
              icon="tabler-clipboard-check"
              size="26"
            />
          </VAvatar>
        </template>
        <VCardTitle class="text-h6 font-weight-bold pa-0">
          {{ t('scp.grn.modal.title_receive_qc') }}
        </VCardTitle>
        <VCardSubtitle class="pa-0 mt-1">
          <span class="text-body-1 font-weight-medium text-high-emphasis">{{ itemName }}</span>
        </VCardSubtitle>
        <div class="d-flex flex-wrap align-center ga-2 mt-2">
          <VChip
            size="small"
            label
            variant="tonal"
            color="secondary"
            prepend-icon="tabler-tag"
          >
            {{ itemCode }}
          </VChip>
          <VChip
            v-if="variantName"
            size="small"
            label
            variant="tonal"
            color="info"
            prepend-icon="tabler-versions"
          >
            {{ variantName }}
          </VChip>
          <VChip
            size="small"
            label
            variant="outlined"
            prepend-icon="tabler-barcode"
          >
            {{ sku }}
          </VChip>
        </div>
        <template #append>
          <IconBtn @click="close">
            <VIcon icon="tabler-x" />
          </IconBtn>
        </template>
      </VCardItem>

      <VDivider />

      <VCardText class="pa-5">
        <!-- Quantities -->
        <p class="section-label mb-3">
          {{ t('scp.grn.modal.section_quantities') }}
        </p>
        <VRow dense>
          <VCol
            cols="6"
            sm="3"
          >
            <VCard
              class="metric-card metric-card--readonly"
              variant="flat"
            >
              <VCardText class="pa-3">
                <span class="metric-card__label">{{ t('scp.grn.qty.ordered') }}</span>
                <div class="metric-card__value text-medium-emphasis">
                  {{ line.qty_ordered ?? 0 }}
                </div>
              </VCardText>
            </VCard>
          </VCol>
          <VCol
            cols="6"
            sm="3"
          >
            <VCard
              class="metric-card"
              variant="flat"
            >
              <VCardText class="pa-3">
                <span class="metric-card__label">{{ t('scp.grn.qty.received') }}</span>
                <AppTextField
                  :model-value="line.qty_received"
                  :disabled="isLocked"
                  type="number"
                  min="0"
                  step="0.01"
                  density="compact"
                  variant="plain"
                  hide-details
                  class="metric-card__input"
                  @update:model-value="v => emit('qty-received', v)"
                />
              </VCardText>
            </VCard>
          </VCol>
          <VCol
            cols="6"
            sm="3"
          >
            <VCard
              class="metric-card metric-card--success"
              variant="flat"
            >
              <VCardText class="pa-3">
                <span class="metric-card__label">{{ t('scp.grn.qty.accepted') }}</span>
                <AppTextField
                  :model-value="line.qty_accepted"
                  :disabled="isLocked"
                  type="number"
                  min="0"
                  step="0.01"
                  density="compact"
                  variant="plain"
                  hide-details
                  class="metric-card__input"
                  @update:model-value="v => emit('qty-accepted', v)"
                />
              </VCardText>
            </VCard>
          </VCol>
          <VCol
            cols="6"
            sm="3"
          >
            <VCard
              class="metric-card metric-card--error"
              variant="flat"
            >
              <VCardText class="pa-3">
                <span class="metric-card__label">{{ t('scp.grn.qty.rejected') }}</span>
                <AppTextField
                  :model-value="line.qty_rejected"
                  :disabled="isLocked"
                  type="number"
                  min="0"
                  step="0.01"
                  density="compact"
                  variant="plain"
                  hide-details
                  class="metric-card__input"
                  @update:model-value="v => emit('qty-rejected', v)"
                />
              </VCardText>
            </VCard>
          </VCol>
        </VRow>

        <!-- Quality Control -->
        <p class="section-label mt-6 mb-3">
          {{ t('scp.grn.tabs.qc') }}
        </p>
        <VCard
          class="glass-panel pa-4"
          variant="flat"
        >
          <span class="metric-card__label d-block mb-3">{{ t('scp.grn.fields.qc_result') }}</span>
          <div class="d-flex flex-column flex-sm-row ga-3">
            <VCard
              v-for="opt in qcOptions"
              :key="opt.value"
              class="qc-option-card flex-grow-1"
              :class="{ 'qc-option-card--active': line.qc_result === opt.value }"
              variant="flat"
              :disabled="isLocked"
              @click="!isLocked && updateField('qc_result', opt.value)"
            >
              <VCardText class="text-center pa-4">
                <VIcon
                  :icon="opt.icon"
                  :color="line.qc_result === opt.value ? opt.color : undefined"
                  size="28"
                  class="mb-2"
                />
                <div
                  class="text-body-2 font-weight-medium"
                  :class="line.qc_result === opt.value ? `text-${opt.color}` : ''"
                >
                  {{ opt.title }}
                </div>
              </VCardText>
            </VCard>
          </div>

          <VExpandTransition>
            <div v-if="showRejectionReason">
              <AppTextarea
                :model-value="line.rejection_reason"
                :label="t('scp.grn.fields.rejection_reason')"
                rows="2"
                class="mt-4"
                variant="solo-filled"
                flat
                density="comfortable"
                :disabled="isLocked"
                @update:model-value="v => updateField('rejection_reason', v)"
              />
            </div>
          </VExpandTransition>

          <VRow
            v-if="(parseFloat(line.qty_rejected) || 0) > 0"
            dense
            class="mt-2"
          >
            <VCol cols="12">
              <VFileInput
                :model-value="line.rejection_photos || []"
                :label="t('scp.grn.fields.rejection_photos')"
                multiple
                accept="image/*"
                prepend-inner-icon="tabler-camera"
                variant="solo-filled"
                flat
                density="comfortable"
                show-size
                chips
                :disabled="isLocked"
                @update:model-value="v => updateField('rejection_photos', v)"
              />
            </VCol>
          </VRow>
        </VCard>

        <!-- Logistics & Financials -->
        <p class="section-label mt-6 mb-3">
          {{ t('scp.grn.modal.section_logistics') }}
        </p>
        <VCard
          class="glass-panel pa-4"
          variant="flat"
        >
          <VRow dense>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                :model-value="line.certificates_received || []"
                :disabled="isLocked"
                multiple
                chips
                closable-chips
                :label="t('scp.grn.fields.certificates_received')"
                :items="certificateItems"
                variant="solo-filled"
                flat
                density="comfortable"
                @update:model-value="v => updateField('certificates_received', v)"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VFileInput
                :model-value="line.certificate_files || []"
                :label="t('scp.grn.fields.certificate_files')"
                multiple
                accept=".pdf,image/*"
                prepend-inner-icon="tabler-certificate"
                variant="solo-filled"
                flat
                density="comfortable"
                show-size
                chips
                :disabled="isLocked"
                @update:model-value="v => updateField('certificate_files', v)"
              />
            </VCol>
            <template v-if="isStockable">
              <VCol cols="12">
                <p class="section-label mb-2">
                  {{ t('scp.grn.modal.section_storage_routing') }}
                </p>
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <VCard
                  class="routing-card"
                  variant="flat"
                >
                  <VCardText class="pa-3">
                    <div class="d-flex align-center ga-2 mb-2">
                      <VAvatar
                        size="32"
                        rounded="lg"
                        color="info"
                        variant="tonal"
                      >
                        <VIcon
                          icon="tabler-map-pin"
                          size="18"
                        />
                      </VAvatar>
                      <span class="routing-card__label">{{ t('scp.grn.fields.zone') }}</span>
                    </div>
                    <AppSelect
                      :model-value="line.selected_zone_id"
                      :items="zones"
                      item-title="code"
                      item-value="id"
                      :placeholder="t('scp.grn.fields.zone')"
                      variant="solo-filled"
                      flat
                      density="comfortable"
                      clearable
                      hide-details
                      :disabled="isLocked"
                      @update:model-value="v => emit('zone-change', v)"
                    />
                  </VCardText>
                </VCard>
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <VCard
                  class="routing-card"
                  variant="flat"
                >
                  <VCardText class="pa-3">
                    <div class="d-flex align-center ga-2 mb-2">
                      <VAvatar
                        size="32"
                        rounded="lg"
                        color="primary"
                        variant="tonal"
                      >
                        <VIcon
                          icon="tabler-building-warehouse"
                          size="18"
                        />
                      </VAvatar>
                      <span class="routing-card__label">{{ t('scp.grn.fields.storage_location') }}</span>
                    </div>
                    <AppSelect
                      :model-value="line.storage_location_id"
                      :items="filteredLocations"
                      item-title="barcode"
                      item-value="id"
                      :placeholder="t('scp.grn.fields.storage_location')"
                      variant="solo-filled"
                      flat
                      density="comfortable"
                      hide-details
                      :disabled="isLocked"
                      @update:model-value="v => updateField('storage_location_id', v)"
                    >
                      <template #item="{ props: itemProps, item: locItem }">
                        <VListItem v-bind="itemProps">
                          <template #title>
                            <div class="d-flex align-center justify-space-between">
                              <span>{{ locItem.raw.barcode }}</span>
                              <VChip
                                v-if="!line.selected_zone_id && locItem.raw.zone?.code"
                                size="x-small"
                                label
                                color="secondary"
                                variant="tonal"
                                class="ms-2"
                              >
                                {{ locItem.raw.zone.code }}
                              </VChip>
                            </div>
                          </template>
                          <template
                            v-if="locItem.raw.full_path"
                            #subtitle
                          >
                            <span class="text-caption">{{ locItem.raw.full_path }}</span>
                          </template>
                        </VListItem>
                      </template>
                    </AppSelect>
                  </VCardText>
                </VCard>
              </VCol>
            </template>
            <VCol
              cols="12"
              :sm="isStockable ? 6 : 12"
            >
              <AppTextField
                :model-value="line.unit_cost_ht"
                :label="t('scp.common.unit_cost')"
                type="number"
                min="0"
                step="0.001"
                prefix="TND"
                variant="solo-filled"
                flat
                density="comfortable"
                :disabled="isLocked"
                @update:model-value="v => updateField('unit_cost_ht', v)"
              />
            </VCol>
            <VCol
              v-if="isStockable"
              cols="12"
              sm="6"
            >
              <AppTextField
                :model-value="line.batch_number"
                :label="t('scp.grn.fields.batch_number')"
                variant="solo-filled"
                flat
                density="comfortable"
                :disabled="isLocked"
                @update:model-value="v => updateField('batch_number', v)"
              />
            </VCol>
          </VRow>
        </VCard>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-5">
        <VBtn
          v-if="!isLocked"
          variant="text"
          color="error"
          size="small"
          prepend-icon="tabler-trash"
          @click="emit('remove-line')"
        >
          {{ t('scp.action.remove_line') }}
        </VBtn>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="close"
        >
          {{ isLocked ? t('scp.grn.action.close') : t('scp.grn.action.cancel') }}
        </VBtn>
        <VBtn
          v-if="!isLocked"
          variant="flat"
          color="primary"
          prepend-icon="tabler-check"
          @click="close"
        >
          {{ t('scp.grn.action.done') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.grn-modal-header {
  background: linear-gradient(
    135deg,
    rgba(var(--v-theme-primary), 0.06) 0%,
    rgba(var(--v-theme-surface), 0) 60%
  );
}

.glass-panel {
  border: 1px solid rgba(var(--v-border-color), 0.12);
  backdrop-filter: blur(16px) saturate(1.5);
  background: rgba(var(--v-theme-surface), 0.65) !important;
  border-radius: 12px;
}

.section-label {
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.6px;
  text-transform: uppercase;
}

.metric-card {
  border: 1px solid rgba(var(--v-border-color), 0.14);
  background: rgba(var(--v-theme-surface), 0.9);
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 4%);
  transition: box-shadow 0.2s ease, border-color 0.2s ease;

  &:hover:not(.metric-card--readonly) {
    border-color: rgba(var(--v-theme-primary), 0.25);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 6%);
  }

  &--readonly {
    background: rgba(var(--v-theme-on-surface), 0.04);
    opacity: 0.92;
  }

  &--success {
    border-color: rgba(var(--v-theme-success), 0.2);
    background: rgba(var(--v-theme-success), 0.04);
  }

  &--error {
    border-color: rgba(var(--v-theme-error), 0.2);
    background: rgba(var(--v-theme-error), 0.04);
  }

  &__label {
    display: block;
    color: rgba(var(--v-theme-on-surface), 0.55);
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.4px;
    margin-block-end: 4px;
    text-transform: uppercase;
  }

  &__value {
    font-size: 1.25rem;
    font-weight: 700;
    line-height: 1.2;
  }

  &__input {
    :deep(.v-field) {
      padding-inline: 0;
    }

    :deep(input) {
      font-size: 1.25rem;
      font-weight: 700;
    }
  }
}

.qc-option-card {
  border: 2px solid rgba(var(--v-border-color), 0.14);
  background: rgba(var(--v-theme-surface), 0.8);
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s ease;

  &:hover:not([disabled]) {
    border-color: rgba(var(--v-theme-primary), 0.35);
    transform: translateY(-1px);
  }

  &--active {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.06);
    box-shadow: 0 4px 20px rgba(var(--v-theme-primary), 0.12);
  }
}

.routing-card {
  border: 1px solid rgba(var(--v-border-color), 0.14);
  background: rgba(var(--v-theme-surface), 0.9);
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 4%);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.25);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 6%);
  }

  &__label {
    color: rgba(var(--v-theme-on-surface), 0.7);
    font-size: 0.8rem;
    font-weight: 600;
  }
}
</style>
