<script setup>
/* eslint-disable camelcase, vue/no-mutating-props */
import { WpSectionCard } from '@/components/shared'
import {
  WORKWEAR_COLOR_PALETTE,
  WORKWEAR_STANDARD_SIZES,
  extractCatalogColorsAndSizes,
  resolveColorHex,
  resolveColorLabel,
  resolveSilhouetteDisplay,
} from '@/composables/client-assets/workwear/useWorkwearStudioConstants'
import { localizedValue } from '@/composables/useLocaleField'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  itemData: { type: Object, required: true },
  salesOrderItems: { type: Array, default: () => [] },
  deliveryNoteItems: { type: Array, default: () => [] },
  documentsLoading: { type: Boolean, default: false },
  statusOptions: { type: Array, required: true },
  conditionOptions: { type: Array, required: true },
  fieldErrors: { type: Function, default: () => [] },
})

const { t, locale } = useI18n()

const resolvedModel = computed(() => props.itemData?.model || props.itemData?.workwear_model || null)

const inheritedGarmentType = computed(() => {
  if (resolvedModel.value) {
    return resolveSilhouetteDisplay(resolvedModel.value, { locale: locale.value, t }).itemTitle
  }

  return (props.itemData?.garment_type || '').replace(/_/g, ' ') || '—'
})

const inheritedSupplierName = computed(() => {
  return (
    resolvedModel.value?.supplier?.legal_name
    || props.itemData?.supplier?.legal_name
    || resolvedModel.value?.catalog_item?.preferred_supplier?.legal_name
    || t('Standard Model Manufacturer')
  )
})

const catalogExtracted = computed(() =>
  extractCatalogColorsAndSizes(resolvedModel.value?.catalog_item, locale.value),
)

const availableSizeOptions = computed(() => {
  const fromModel = resolvedModel.value?.size_range || []
  const fromCatalog = catalogExtracted.value.sizes || []
  const merged = [...new Set([...fromModel, ...fromCatalog])]

  return merged.length > 0 ? merged : WORKWEAR_STANDARD_SIZES
})

const availableColorOptions = computed(() => {
  const fromModel = resolvedModel.value?.colors || []
  const fromCatalog = catalogExtracted.value.colors || []
  const merged = [...new Set([...fromModel, ...fromCatalog])].filter(Boolean)

  if (merged.length > 0) {
    return merged.map(c => ({
      title: resolveColorLabel(c, { t }),
      value: c,
      hex: resolveColorHex(c),
    }))
  }

  return WORKWEAR_COLOR_PALETTE.map(c => ({
    title: t(c.label),
    value: c.value,
    hex: c.hex,
  }))
})

const modelOptionsList = computed(() => resolvedModel.value?.options || [])

const extraCatalogOptions = computed(() => {
  const opts = resolvedModel.value?.catalog_item?.options || []
  const colorKeywords = ['color', 'colour', 'couleur', 'teinte', 'coloris', 'لون']
  const sizeKeywords = ['size', 'taille', 'pointure', 'dimension', 'مقاس', 'حجم']

  return opts.filter(opt => {
    const label = `${opt.code || ''} ${localizedValue(opt.name, locale.value) || ''}`.toLowerCase()

    return !colorKeywords.some(k => label.includes(k)) && !sizeKeywords.some(k => label.includes(k))
  })
})
</script>

<template>
  <section
    id="section-bio"
    class="journal-section mb-6"
  >
    <WpSectionCard
      icon="tabler-info-circle"
      :title="$t('General Information & Model Inheritance')"
      :subtitle="$t('Client account, workwear model, garment type, and supplier are inherited from the parent model. Select size, color, and options below.')"
    >
      <!-- ━━━ Inherited Read-Only Context (Conflict-Free with Model) ━━━ -->
      <VRow
        dense
        class="mb-4"
      >
        <VCol
          cols="12"
          sm="6"
          lg="3"
        >
          <div class="inherited-card">
            <div class="d-flex align-center justify-space-between mb-1">
              <span class="text-caption text-medium-emphasis">{{ $t('Client (Owner)') }}</span>
              <VIcon
                icon="tabler-lock"
                size="14"
                class="text-disabled"
              />
            </div>
            <div class="text-body-2 font-weight-bold text-break">
              {{ itemData.client?.legal_name || itemData.client?.name || '—' }}
            </div>
            <span class="text-caption text-disabled">{{ $t('Locked after creation') }}</span>
          </div>
        </VCol>

        <VCol
          cols="12"
          sm="6"
          lg="3"
        >
          <div class="inherited-card">
            <div class="d-flex align-center justify-space-between mb-1">
              <span class="text-caption text-medium-emphasis">{{ $t('Workwear Model') }}</span>
              <VIcon
                icon="tabler-shirt"
                size="14"
                color="primary"
              />
            </div>
            <div class="text-body-2 font-weight-bold text-break">
              {{ resolvedModel?.name || '—' }}
            </div>
            <RouterLink
              v-if="itemData.model_id"
              :to="{ name: 'client-assets-workwear-edit-id', params: { id: itemData.model_id }, query: { type: 'model' } }"
              class="text-caption text-primary text-decoration-none font-mono"
            >
              {{ resolvedModel?.model_ref || $t('View Model') }}
            </RouterLink>
          </div>
        </VCol>

        <VCol
          cols="12"
          sm="6"
          lg="3"
        >
          <div class="inherited-card">
            <div class="d-flex align-center justify-space-between mb-1">
              <span class="text-caption text-medium-emphasis">{{ $t('Garment Type') }}</span>
              <VChip
                size="x-small"
                color="primary"
                variant="tonal"
              >
                {{ $t('From Model') }}
              </VChip>
            </div>
            <div class="text-body-2 font-weight-bold text-primary text-break">
              {{ inheritedGarmentType }}
            </div>
            <span class="text-caption text-disabled">{{ $t('Inherited from model silhouette') }}</span>
          </div>
        </VCol>

        <VCol
          cols="12"
          sm="6"
          lg="3"
        >
          <div class="inherited-card">
            <div class="d-flex align-center justify-space-between mb-1">
              <span class="text-caption text-medium-emphasis">{{ $t('Supplier / Manufacturer') }}</span>
              <VChip
                size="x-small"
                color="warning"
                variant="tonal"
              >
                {{ $t('From Model') }}
              </VChip>
            </div>
            <div class="text-body-2 font-weight-bold text-break">
              {{ inheritedSupplierName }}
            </div>
            <span class="text-caption text-disabled">{{ $t('Inherited from model supplier') }}</span>
          </div>
        </VCol>
      </VRow>

      <!-- ━━━ Editable Item Variant & Lifecycle Attributes ━━━ -->
      <VRow>
        <!-- Size from Model / Catalog Options -->
        <VCol
          cols="12"
          md="6"
        >
          <AppCombobox
            v-model="itemData.size"
            :items="availableSizeOptions"
            :label="$t('Size (From Model Options)')"
            :error-messages="fieldErrors('size')"
            variant="outlined"
          />
          <div
            v-if="availableSizeOptions.length"
            class="d-flex flex-wrap gap-1 mt-2"
          >
            <VChip
              v-for="sz in availableSizeOptions"
              :key="sz"
              size="x-small"
              :color="itemData.size === sz ? 'primary' : 'secondary'"
              :variant="itemData.size === sz ? 'flat' : 'tonal'"
              class="font-mono font-weight-bold cursor-pointer"
              @click="itemData.size = sz"
            >
              {{ sz }}
            </VChip>
          </div>
        </VCol>

        <!-- Color from Model / Catalog Options -->
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            v-model="itemData.color"
            :items="availableColorOptions"
            item-title="title"
            item-value="value"
            :label="$t('Color (From Model Options)')"
            :error-messages="fieldErrors('color')"
            variant="outlined"
          />
          <div
            v-if="availableColorOptions.length"
            class="d-flex flex-wrap gap-1 mt-2"
          >
            <VChip
              v-for="col in availableColorOptions.slice(0, 8)"
              :key="col.value"
              size="x-small"
              :color="itemData.color === col.value ? 'primary' : 'secondary'"
              :variant="itemData.color === col.value ? 'flat' : 'tonal'"
              class="cursor-pointer"
              @click="itemData.color = col.value"
            >
              <span
                class="d-inline-block rounded-circle border me-1"
                :style="{ inlineSize: '10px', blockSize: '10px', backgroundColor: col.hex }"
              />
              {{ col.title }}
            </VChip>
          </div>
        </VCol>

        <!-- Model Option Selections -->
        <template v-if="modelOptionsList.length && itemData.selected_options">
          <VCol
            v-for="opt in modelOptionsList"
            :key="opt.id"
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="itemData.selected_options[opt.id]"
              :items="(opt.values || []).map(v => ({ title: v.value, value: v.id }))"
              :label="opt.name"
              clearable
              variant="outlined"
            />
          </VCol>
        </template>

        <!-- Additional Catalog Option Selections -->
        <template v-else-if="extraCatalogOptions.length">
          <VCol
            v-for="opt in extraCatalogOptions"
            :key="opt.id"
            cols="12"
            md="6"
          >
            <AppSelect
              :items="(opt.values || []).map(v => ({ title: localizedValue(v.value, locale), value: localizedValue(v.value, locale) }))"
              :label="localizedValue(opt.name, locale)"
              clearable
              variant="outlined"
            />
          </VCol>
        </template>

        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            v-model="itemData.item_condition"
            :label="$t('Physical Condition')"
            :items="conditionOptions"
            variant="outlined"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            v-model="itemData.status"
            :label="$t('Lifecycle Status')"
            :items="statusOptions"
            variant="outlined"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <AppAutocomplete
            v-model="itemData.sales_order_id"
            :label="$t('Sales Order (optional)')"
            :items="salesOrderItems"
            :loading="documentsLoading"
            :error-messages="fieldErrors('sales_order_id')"
            :placeholder="$t('No sales order')"
            :no-data-text="$t('No sales orders for this client')"
            clearable
            variant="outlined"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <AppAutocomplete
            v-model="itemData.delivery_note_id"
            :label="$t('Delivery Note (optional)')"
            :items="deliveryNoteItems"
            :loading="documentsLoading"
            :error-messages="fieldErrors('delivery_note_id')"
            :placeholder="$t('No delivery note')"
            :no-data-text="$t('No delivery notes for this client')"
            clearable
            variant="outlined"
          />
        </VCol>
      </VRow>
    </WpSectionCard>
  </section>
</template>

<style lang="scss" scoped>
.inherited-card {
  display: flex;
  flex-direction: column;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.02);
  block-size: 100%;
  gap: 0.25rem;
  padding: 0.875rem 1rem;
}
</style>
