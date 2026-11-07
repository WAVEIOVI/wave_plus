<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCurrency } from '@/composables/useCurrency'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  catalogItem: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue', 'select'])

const { t } = useI18n()
const { formatCurrency } = useCurrency()
const { localizedValue } = useProcurementHelpers()

const isOpen = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const selectedOptions = ref({})
const selectedVariantId = ref(null)

const itemName = computed(() => {
  if (!props.catalogItem) return ''
  
  return localizedValue(props.catalogItem.name) || props.catalogItem.sku || t('Item')
})

const hasOptionsMatrix = computed(() => {
  return Array.isArray(props.catalogItem?.options) && props.catalogItem.options.length > 0
})

const variants = computed(() => {
  return Array.isArray(props.catalogItem?.variants) ? props.catalogItem.variants : []
})

watch(() => props.catalogItem, newItem => {
  selectedOptions.value = {}
  selectedVariantId.value = null

  if (!newItem) return

  if (Array.isArray(newItem.options) && newItem.options.length > 0) {
    newItem.options.forEach(opt => {
      if (Array.isArray(opt.values) && opt.values.length > 0) {
        selectedOptions.value[opt.id] = opt.values[0].id
      }
    })
  } else if (Array.isArray(newItem.variants) && newItem.variants.length > 0) {
    selectedVariantId.value = newItem.variants[0].id
  }
}, { immediate: true })

const resolvedVariant = computed(() => {
  if (!props.catalogItem || !variants.value.length) return null

  if (selectedVariantId.value) {
    const direct = variants.value.find(v => v.id === selectedVariantId.value)
    if (direct) return direct
  }

  if (hasOptionsMatrix.value) {
    const selectedValueIds = Object.values(selectedOptions.value).filter(Boolean)
    if (selectedValueIds.length === props.catalogItem.options.length) {
      const matched = variants.value.find(variant => {
        if (!Array.isArray(variant.option_values)) return false
        const variantValueIds = variant.option_values.map(ov => ov.id)

        return selectedValueIds.every(id => variantValueIds.includes(id))
      })

      if (matched) return matched
    }
  }

  return variants.value[0] || null
})

const resolveVariantLabel = variant => {
  if (!variant) return ''
  if (variant.name) return localizedValue(variant.name)
  if (Array.isArray(variant.option_values) && variant.option_values.length > 0) {
    return variant.option_values.map(ov => localizedValue(ov.name || ov.value)).filter(Boolean).join(' / ')
  }
  
  return variant.sku || `#${variant.id}`
}

const resolveVariantPrice = variant => {
  if (!variant) return Number(props.catalogItem?.cost_price ?? props.catalogItem?.base_price ?? 0)
  
  return Number(variant.cost_price ?? variant.price ?? props.catalogItem?.cost_price ?? props.catalogItem?.base_price ?? 0)
}

const handleSelectVariantRow = variant => {
  selectedVariantId.value = variant.id
}

const confirmSelection = () => {
  if (!props.catalogItem) return

  emit('select', {
    item: props.catalogItem,
    variant: resolvedVariant.value,
  })
  isOpen.value = false
}
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="560"
    scrollable
  >
    <VCard v-if="catalogItem">
      <VCardTitle class="pa-4 d-flex align-center justify-space-between bg-var-theme-background">
        <div class="d-flex align-center gap-2">
          <VAvatar
            color="primary"
            variant="tonal"
            size="34"
          >
            <VIcon
              icon="tabler-layers-Linked"
              size="18"
            />
          </VAvatar>
          <div>
            <div class="text-subtitle-1 font-weight-bold line-height-1">
              {{ t('Select Product Variant') }}
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
              {{ itemName }}
            </div>
          </div>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="isOpen = false"
        />
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-5">
        <!-- Option Matrix Chips (when options array is present on catalogItem) -->
        <template v-if="hasOptionsMatrix">
          <div
            v-for="option in catalogItem.options"
            :key="option.id"
            class="mb-4"
          >
            <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2">
              {{ localizedValue(option.name) }}
            </div>
            <VChipGroup
              v-model="selectedOptions[option.id]"
              mandatory
              selected-class="bg-primary text-white"
              @update:model-value="selectedVariantId = null"
            >
              <VChip
                v-for="val in option.values"
                :key="val.id"
                :value="val.id"
                variant="outlined"
                size="small"
                label
              >
                {{ localizedValue(val.name || val.value) }}
              </VChip>
            </VChipGroup>
          </div>
        </template>

        <!-- Direct Variant List (always available so user can pick directly by SKU/Variant) -->
        <div
          v-if="variants.length > 0"
          class="mb-4"
        >
          <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2">
            {{ t('Available Variants') }} ({{ variants.length }})
          </div>
          <VList
            density="compact"
            class="border rounded py-0"
            max-height="220"
          >
            <template
              v-for="(variant, idx) in variants"
              :key="variant.id"
            >
              <VListItem
                :active="resolvedVariant?.id === variant.id"
                color="primary"
                class="py-2"
                @click="handleSelectVariantRow(variant)"
              >
                <template #prepend>
                  <VIcon
                    :icon="resolvedVariant?.id === variant.id ? 'tabler-circle-check-filled' : 'tabler-circle'"
                    :color="resolvedVariant?.id === variant.id ? 'primary' : 'disabled'"
                    size="18"
                    class="me-2"
                  />
                </template>
                <VListItemTitle class="font-weight-medium text-body-2">
                  {{ resolveVariantLabel(variant) }}
                </VListItemTitle>
                <VListItemSubtitle class="text-caption font-monospace">
                  {{ variant.sku || catalogItem.sku || '—' }}
                </VListItemSubtitle>
                <template #append>
                  <div class="d-flex flex-column align-end">
                    <span class="text-body-2 font-weight-bold font-monospace">
                      {{ formatCurrency(resolveVariantPrice(variant)) }}
                    </span>
                    <span
                      v-if="variant.current_stock !== undefined"
                      class="text-caption"
                      :class="Number(variant.current_stock) > 0 ? 'text-success' : 'text-medium-emphasis'"
                    >
                      {{ t('Stock') }}: {{ variant.current_stock }}
                    </span>
                  </div>
                </template>
              </VListItem>
              <VDivider v-if="idx < variants.length - 1" />
            </template>
          </VList>
        </div>

        <!-- Resolved Variant Summary Box -->
        <VCard
          variant="tonal"
          :color="resolvedVariant ? 'primary' : 'warning'"
          class="pa-3"
        >
          <template v-if="resolvedVariant">
            <div class="d-flex justify-space-between align-center mb-1">
              <span class="text-caption text-medium-emphasis">{{ t('Selected SKU') }}:</span>
              <span class="font-weight-bold font-monospace text-body-2">
                {{ resolvedVariant.sku || catalogItem.sku || '—' }}
              </span>
            </div>
            <div class="d-flex justify-space-between align-center mb-1">
              <span class="text-caption text-medium-emphasis">{{ t('Unit Cost / Price') }}:</span>
              <span class="font-weight-bold font-monospace text-body-2">
                {{ formatCurrency(resolveVariantPrice(resolvedVariant)) }}
              </span>
            </div>
            <div
              v-if="resolvedVariant.current_stock !== undefined"
              class="d-flex justify-space-between align-center"
            >
              <span class="text-caption text-medium-emphasis">{{ t('Available Stock') }}:</span>
              <VChip
                size="x-small"
                :color="Number(resolvedVariant.current_stock) > 0 ? 'success' : 'secondary'"
                variant="flat"
              >
                {{ resolvedVariant.current_stock }} {{ catalogItem.unit || t('Units') }}
              </VChip>
            </div>
          </template>
          <template v-else>
            <div class="text-center text-body-2 py-1">
              <VIcon
                icon="tabler-alert-triangle"
                size="18"
                class="me-1"
              />
              {{ t('This combination is not available.') }}
            </div>
          </template>
        </VCard>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 justify-end gap-2">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="isOpen = false"
        >
          {{ t('Cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          prepend-icon="tabler-plus"
          :disabled="!resolvedVariant && variants.length > 0"
          @click="confirmSelection"
        >
          {{ t('Add to Document') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
