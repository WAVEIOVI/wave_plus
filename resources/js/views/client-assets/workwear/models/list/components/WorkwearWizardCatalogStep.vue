<script setup>
/* eslint-disable camelcase */
import { WpSearchSelectDialog } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { useCatalogItemStore } from '@/stores/useCatalogItemStore'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  catalogItem: { type: Object, default: null },
  selectedItem: { type: Object, default: null },
  modelValue: { type: [String, Number, Object], default: null },
})

const emit = defineEmits([
  'update:catalogItem',
  'update:selectedItem',
  'update:modelValue',
  'selectItem',
  'viewVariants',
])

const { t, locale } = useI18n()
const catalogStore = useCatalogItemStore()

const isSearchDialogOpen = ref(false)

const currentCatalogItem = computed(() =>
  props.catalogItem || props.selectedItem || (typeof props.modelValue === 'object' ? props.modelValue : null),
)

const catalogSearchConfig = computed(() => ({
  title: t('Select Physical Catalog Product'),
  entityLabel: t('Catalog Product'),
  icon: 'tabler-package',
  fetcher: async ({ page, perPage, search, sortBy, sortDir, signal }) => {
    const res = await catalogStore.fetchItems({
      'filter[item_type]': 'product',
      'filter[product_type]': 'physical',
      'include': 'product,category,brand,preferredSupplier,suppliers,options.values,variants.optionValues',
      page,
      per_page: perPage,
      search: search || undefined,
      sort: sortBy ? `${sortDir === 'desc' ? '-' : ''}${sortBy}` : undefined,
    }, { signal })

    return {
      items: res?.data || [],
      total: res?.total || res?.meta?.total || 0,
    }
  },
  columns: [
    { key: 'code', title: t('Code'), sortable: true },
    { key: 'name', title: t('Product Name'), sortable: true },
    { key: 'category.name', title: t('Category') },
    { key: 'brand.name', title: t('Brand') },
    { key: 'preferred_supplier.legal_name', title: t('Preferred Supplier') },
  ],
  display: {
    title: item => localizedValue(item.name, locale.value) || item.code,
    subtitle: item => {
      const cat = localizedValue(item.category?.name, locale.value) || ''
      const sup = item.preferred_supplier?.legal_name ? ` • Supplier: ${item.preferred_supplier.legal_name}` : ''

      return `${item.code} • ${cat}${sup}`
    },
    avatar: item => item.primary_image_url || item.thumbnail_url,
    icon: 'tabler-package',
    chips: [item => ({ label: item.code, color: 'primary' })],
  },
}))

function onSelectProduct(item) {
  emit('update:catalogItem', item)
  emit('update:selectedItem', item)
  emit('update:modelValue', item?.id || null)
  emit('selectItem', item)
}

function onClearProduct() {
  emit('update:catalogItem', null)
  emit('update:selectedItem', null)
  emit('update:modelValue', null)
  emit('selectItem', null)
}
</script>

<template>
  <div class="workwear-wizard-catalog-step d-flex flex-column gap-4">
    <!-- Selected Catalog Product Card -->
    <VCard
      v-if="currentCatalogItem"
      variant="outlined"
      class="border-primary pa-4 bg-var-theme-background"
    >
      <div class="d-flex flex-column gap-3">
        <div class="d-flex flex-wrap align-center justify-space-between gap-3">
          <div class="d-flex align-center gap-3 min-w-0">
            <VAvatar
              size="56"
              color="primary"
              variant="tonal"
              rounded
              class="elevation-1 flex-shrink-0"
            >
              <VImg
                v-if="currentCatalogItem.primary_image_url || currentCatalogItem.thumbnail_url"
                :src="currentCatalogItem.primary_image_url || currentCatalogItem.thumbnail_url"
                :alt="currentCatalogItem.code"
              />
              <VIcon
                v-else
                icon="tabler-package-import"
                size="30"
              />
            </VAvatar>

            <div class="min-w-0">
              <div class="d-flex align-center gap-2 flex-wrap">
                <VChip
                  size="small"
                  color="primary"
                  variant="flat"
                  label
                  class="font-mono font-weight-bold"
                >
                  {{ currentCatalogItem.code }}
                </VChip>
                <span class="text-subtitle-1 font-weight-bold text-high-emphasis">
                  {{ localizedValue(currentCatalogItem.name, locale) }}
                </span>
              </div>

              <div class="d-flex align-center gap-3 text-caption text-medium-emphasis flex-wrap mt-1">
                <span v-if="currentCatalogItem.category">
                  <VIcon
                    icon="tabler-category"
                    size="14"
                    class="me-1"
                  />
                  {{ localizedValue(currentCatalogItem.category.name, locale) }}
                </span>
                <span v-if="currentCatalogItem.brand">
                  <VIcon
                    icon="tabler-tag"
                    size="14"
                    class="me-1"
                  />
                  {{ localizedValue(currentCatalogItem.brand.name, locale) || currentCatalogItem.brand.name }}
                </span>
                <span
                  v-if="currentCatalogItem.preferred_supplier?.legal_name"
                  class="text-success font-weight-semibold"
                >
                  <VIcon
                    icon="tabler-truck-delivery"
                    size="14"
                    class="me-1"
                  />
                  {{ $t('Preferred Supplier:') }} {{ currentCatalogItem.preferred_supplier.legal_name }}
                </span>
              </div>
            </div>
          </div>

          <div class="d-flex align-center gap-2 flex-wrap">
            <VBtn
              v-if="currentCatalogItem.variants?.length"
              size="small"
              color="info"
              variant="tonal"
              prepend-icon="tabler-arrows-maximize"
              @click="$emit('viewVariants')"
            >
              {{ $t('View Variants') }} ({{ currentCatalogItem.variants.length }})
            </VBtn>
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-replace"
              @click="isSearchDialogOpen = true"
            >
              {{ $t('Change Product') }}
            </VBtn>
            <VBtn
              size="small"
              variant="text"
              color="secondary"
              icon="tabler-x"
              :title="$t('Unlink Product')"
              @click="onClearProduct"
            />
          </div>
        </div>

        <!-- Options Matrix Preview -->
        <VDivider />
        <div v-if="currentCatalogItem.options?.length">
          <div class="text-body-2 font-weight-medium mb-2">
            {{ $t('Inherited Configurable Options') }}
          </div>
          <VRow dense>
            <VCol
              v-for="opt in currentCatalogItem.options"
              :key="opt.id"
              cols="12"
              sm="6"
            >
              <div class="pa-2 px-3 rounded border bg-surface d-flex flex-column gap-1">
                <span class="text-caption font-weight-bold text-high-emphasis">
                  {{ localizedValue(opt.name, locale) }}
                </span>
                <div class="d-flex flex-wrap gap-1">
                  <VChip
                    v-for="val in (opt.values || [])"
                    :key="val.id"
                    size="x-small"
                    color="primary"
                    variant="tonal"
                  >
                    {{ localizedValue(val.value, locale) }}
                  </VChip>
                </div>
              </div>
            </VCol>
          </VRow>
        </div>
      </div>
    </VCard>

    <!-- Empty State: Clear, prominent search card -->
    <VCard
      v-else
      variant="outlined"
      class="pa-6 pa-sm-8 text-center border-dashed"
    >
      <div class="d-flex flex-column align-center justify-center gap-3 mx-auto catalog-empty-container">
        <VAvatar
          color="primary"
          variant="tonal"
          size="64"
          rounded="circle"
        >
          <VIcon
            icon="tabler-package-search"
            size="32"
          />
        </VAvatar>

        <div>
          <h4 class="text-subtitle-1 font-weight-bold mb-1">
            {{ $t('No Physical Catalog Product Linked') }}
          </h4>
          <p class="text-body-2 text-medium-emphasis mb-0">
            {{ $t('Search and attach a physical product from the catalog to import options, variants and preferred supplier, or skip this step to create an unlinked custom model.') }}
          </p>
        </div>

        <div class="d-flex align-center gap-3 mt-2 flex-wrap justify-center">
          <VBtn
            color="primary"
            variant="elevated"
            prepend-icon="tabler-search"
            @click="isSearchDialogOpen = true"
          >
            {{ $t('Search & Select Catalog Product') }}
          </VBtn>
        </div>
      </div>
    </VCard>

    <!-- Standard Catalog Search & Select Dialog -->
    <WpSearchSelectDialog
      v-model="isSearchDialogOpen"
      :selected="currentCatalogItem"
      v-bind="catalogSearchConfig"
      confirm-behavior="immediate"
      @select="onSelectProduct"
    />
  </div>
</template>

<style scoped>
.catalog-empty-container {
  max-inline-size: 520px;
}
</style>
