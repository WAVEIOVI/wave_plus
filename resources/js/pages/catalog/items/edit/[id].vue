<script setup>
/* eslint-disable camelcase */
import { AppBottomNav, AppQuickActionsSheet, WpDeleteDialog, WpUnsavedChangesDialog } from '@/components/shared'
import AppBreadcrumb from '@/components/shared/AppBreadcrumb.vue'
import { useUnsavedChangesGuard } from '@/composables/dialog/useUnsavedChangesGuard'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { scrubCatalogItemExtensionPayload } from '@/composables/useCatalogSelection'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { useCatalogItemStore } from '@/stores/useCatalogItemStore'
import { useCatalogSupportStore } from '@/stores/useCatalogSupportStore'
import { useGuidelineStore } from '@/stores/useGuidelineStore'
import { useSupplierStore } from '@/stores/useSupplierStore'
import { computed, nextTick, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'
import { VForm } from 'vuetify/components/VForm'

import CatalogMediaManager from '@/views/catalog/items/form/CatalogMediaManager.vue'
import VariantsBuilder from '@/views/catalog/items/form/VariantsBuilder.vue'
import WizardStepClassification from '@/views/catalog/items/form/WizardStepClassification.vue'
import WizardStepCommerce from '@/views/catalog/items/form/WizardStepCommerce.vue'
import WizardStepGeneralInfo from '@/views/catalog/items/form/WizardStepGeneralInfo.vue'
import WizardStepSpecifications from '@/views/catalog/items/form/WizardStepSpecifications.vue'

definePage({
  path: '/catalog/items/edit/:id',
  name: 'catalog-items-edit-id',
  meta: {
    layout: 'default',
    module: 'catalog',
    action: 'read',
    subject: 'Catalog',
  },
})

const { t } = useI18n()
const { showMessage } = useAppSnackbar()
const route = useRoute('catalog-items-edit-id')
const router = useRouter()
const catalogStore = useCatalogItemStore()
const supportStore = useCatalogSupportStore()
const supplierStore = useSupplierStore()
const guidelineStore = useGuidelineStore()

const { smAndDown } = useDisplay()

// ── State ───────────────────────────────────────────────────────────────
const loading = ref(true)
const saving = ref(false)
const isNewMode = computed(() => route.params.id === 'new')
const formRefGeneral = ref(null)
const mediaManagerRef = ref(null)
const variantsBuilderRef = ref(null)

// Key used to force WizardStepClassification to remount after fetchItem(),
// ensuring VItemGroup re-initialises its internal selected state (Task #4 fix).
const classificationKey = ref(0)

// Computed key to track media changes for forcing MediaManager re-render if needed
const mediaKey = computed(() => 
  `${formData.value.media_images?.length || 0}-${formData.value.media_documents?.length || 0}-${formData.value.media_certificates?.length || 0}`,
)

// ── Breadcrumbs ─────────────────────────────────────────────────────────────
const breadcrumbItems = computed(() => [
  { title: t('catalog.items.edit.breadcrumb_home', 'Home'), to: '/', icon: 'tabler-home' },
  { title: t('catalog.items.edit.breadcrumb_catalog', 'Catalog'), to: '/catalog/items/list', icon: 'tabler-packages' },
  { title: t('catalog.items.edit.breadcrumb_items', 'Items'), to: '/catalog/items/list', icon: 'tabler-list' },
  {
    title: isNewMode.value ? t('catalog.items.edit.title_new', 'New Item') : (formData.value.code || t('catalog.items.edit.title_edit', 'Edit Item')),
    icon: isNewMode.value ? 'tabler-plus' : 'tabler-edit',
  },
])

// ── Section Navigation ──────────────────────────────────────────────────────
const activeSection = ref('classification')

const sections = computed(() => {
  const base = [
    { id: 'classification', label: t('catalog.items.edit.sections.classification', 'Classification'), icon: 'tabler-category' },
    { id: 'general', label: t('catalog.items.edit.sections.general', 'General Info'), icon: 'tabler-id' },
    { id: 'specifications', label: t('catalog.items.edit.sections.specifications', 'Specifications'), icon: 'tabler-adjustments-horizontal' },
  ]

  base.push({ id: 'commerce', label: t('catalog.items.edit.sections.commerce', 'Commerce'), icon: 'tabler-coin' })

  if (!isNewMode.value) {
    base.push({ id: 'media', label: t('catalog.items.edit.sections.media', 'Media & Files'), icon: 'tabler-photo-video' })
  }

  base.push({ id: 'variants', label: t('catalog.items.edit.sections.variants', 'Variants'), icon: 'tabler-grid-dots' })

  return base
})

const mobileNavItems = computed(() => sections.value.map(s => ({
  title: s.label,
  icon: s.icon,
  key: s.id,
  value: s.id,
})))

const scrollToSection = sectionId => {
  activeSection.value = sectionId

  const el = document.getElementById(`section-${sectionId}`)
  if (el)
    el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

// ── Dropdown Options ────────────────────────────────────────────────────
const supplierOptions = computed(() =>
  supplierStore.suppliers.map(s => ({
    value: s.id,
    title: s.company_name || s.commercial_name || `Supplier #${s.id}`,
  })),
)

const guidelineOptions = computed(() =>
  guidelineStore.guidelines.map(g => ({
    value: g.id,
    title: g.name || g.code,
  })),
)

// ── Data Model ──────────────────────────────────────────────────────────
const formData = ref({
  code: '',
  name: {},
  short_description: {},
  item_type: '',
  category_id: null,
  brand_id: null,
  uom_id: null,
  is_stockable: false,
  is_purchasable: false,
  is_sellable: false,
  variants: [
    {
      id: null,
      sku: '',
      barcode: '',
      price: null,
      cost_price: null,
      markup: 0,
      tax_class: 'standard_19pct',
      stock: 0,
      min_stock_level: 0,
      reorder_point: 0,
      reorder_qty: 0,
      lead_time_days: 0,
      name: 'Default',
      is_default: true,
      status: 'active',
    },
  ],
  status: 'draft',
  internal_notes: '',
  preferred_supplier_id: null,
  warranty_months: null,

  // Extension objects
  product: {},
  consumable: {},
  eq_servicing: {},

  // Relations & Media
  tags: [],
  certifications: [],
  suppliers: [],
  media_images: [],
  media_documents: [],
  media_certificates: [],
})

// Sub-type proxy
const subTypeComputed = computed({
  get: () => {
    if (formData.value.item_type === 'product') return formData.value.product?.product_type
    if (formData.value.item_type === 'consumable') return formData.value.consumable?.consumable_type
    if (formData.value.item_type === 'eq_servicing') return formData.value.eq_servicing?.eq_servicing_type
    
    return ''
  },
  set: val => {
    if (formData.value.item_type === 'product') {
      if (!formData.value.product) formData.value.product = {}
      formData.value.product.product_type = val
    }
    else if (formData.value.item_type === 'consumable') {
      if (!formData.value.consumable) formData.value.consumable = {}
      formData.value.consumable.consumable_type = val
    }
    else if (formData.value.item_type === 'eq_servicing') {
      if (!formData.value.eq_servicing) formData.value.eq_servicing = {}
      formData.value.eq_servicing.eq_servicing_type = val
    }
  },
})

// ── Non-Stockable Types ─────────────────────────────────────────────────
const isNonStockable = computed(() => {
  if (formData.value.item_type === 'eq_servicing') return true
  if (formData.value.item_type === 'product') {
    const subType = formData.value.product?.product_type
    if (subType === 'digital' || subType === 'service') return true
  }

  return false
})

// ── Type Helpers ────────────────────────────────────────────────────────
const resolveTypeIcon = type => ({
  product: 'tabler-box',
  consumable: 'tabler-flame',
  eq_servicing: 'tabler-tools',
}[type] || 'tabler-package')

const resolveTypeColor = type => ({
  product: 'info',
  consumable: 'warning',
  eq_servicing: 'secondary',
}[type] || 'primary')

// ── Actions ─────────────────────────────────────────────────────────────
const fetchItem = async () => {
  if (isNewMode.value) return
  try {
    const data = await catalogStore.fetchItem(route.params.id)

    formData.value = {
      ...formData.value,
      ...data,
      name: ensureTranslatable(data.name),
      short_description: ensureTranslatable(data.short_description || data.description),
      uom_id: data.uom_id || data.primary_uom_id || null, // legacy fallback

      variants: data.variants?.length ? data.variants : formData.value.variants,

      product: data.product || {},
      consumable: data.consumable || { is_consumable: true },
      eq_servicing: data.eq_servicing || {},

      tags: data.tags?.map(t => typeof t === 'object' ? t.tag : t) || [],
      certifications: data.certifications?.map(c => typeof c === 'object' ? c.certification : c) || [],
      suppliers: data.suppliers?.map(s => ({
        supplier_id: s.pivot?.supplier_id || s.id,
        supplier_reference: s.pivot?.supplier_reference || '',
        supplier_price: s.pivot?.supplier_price || null,
        currency: s.pivot?.currency || 'TND',
        lead_time_days: s.pivot?.lead_time_days || null,
        min_order_qty: s.pivot?.min_order_qty || null,
        is_preferred: s.pivot?.is_preferred || false,
      })) || [],
      
      media_images: data.media_images || [],
      media_documents: data.media_documents || [],
      media_certificates: data.media_certificates || [],
    }

    // Bump the key so WizardStepClassification remounts and VItemGroup
    // re-initialises its internal active state from the fresh prop values.
    classificationKey.value++

    originalItem.value = JSON.parse(JSON.stringify(formData.value))
  } catch (err) {
    console.error(err)
    showMessage(t('catalog.items.edit.validation.save_error', 'Failed to load item.'), 'error')
  }
}

// ── Unsaved changes guard ───────────────────────────────────────────────────
const originalItem = ref(null)

const isDirty = computed(() => {
  if (!originalItem.value) return false

  return JSON.stringify(formData.value) !== JSON.stringify(originalItem.value)
})

const {
  isDialogVisible: isUnsavedDialogVisible,
  confirmLeave,
  cancelLeave,
} = useUnsavedChangesGuard(isDirty)

const handleSave = async () => {
  if (!formData.value.category_id) {
    return showMessage(t('catalog.items.edit.validation.select_category', 'Please select a category.'), 'warning')
  }

  // Validate General Info form
  if (formRefGeneral.value) {
    const { valid } = await formRefGeneral.value.validate()
    if (!valid) return showMessage(t('catalog.items.edit.validation.general_errors', 'Please fix errors in General Information.'), 'warning')
  }

  saving.value = true
  try {
    let payload = JSON.parse(JSON.stringify(formData.value))

    payload = scrubCatalogItemExtensionPayload(payload)

    // Scrub empty relation payloads
    if (payload.item_type === 'product') {
      delete payload.consumable
      delete payload.eq_servicing
    } else if (payload.item_type === 'consumable') {
      delete payload.product
      delete payload.eq_servicing
    } else if (payload.item_type === 'eq_servicing') {
      delete payload.product
      delete payload.consumable
    }

    // Make sure the default variant sku defaults to the code if empty
    if (payload.variants && payload.variants[0] && !payload.variants[0].sku) {
      payload.variants[0].sku = payload.code ? `${payload.code}-DEFAULT` : ''
    }

    if (isNewMode.value) {
      const res = await catalogStore.createItem(payload)

      originalItem.value = JSON.parse(JSON.stringify(formData.value))
      showMessage(t('catalog.items.list.messages.create_success', { ref: payload.code || '' }), 'success')
      router.push({ name: 'catalog-items-edit-id', params: { id: res.data.id } })
    } else {
      await catalogStore.updateItem(route.params.id, payload)
      originalItem.value = JSON.parse(JSON.stringify(formData.value))
      showMessage(t('catalog.items.list.messages.update_success', { ref: payload.code || '' }), 'success')
      await fetchItem()
    }
  } catch (err) {
    console.error(err)

    const msg = err?.data?.message || err?.data?.errors?.[0] || t('catalog.items.edit.validation.save_error', 'Error saving item')

    showMessage(msg, 'error')
  } finally {
    saving.value = false
  }
}

// ── Item Actions & Quick Actions Sheet ───────────────────────────────────────
const isDeleteDialogVisible = ref(false)
const quickActionsOpen = ref(false)

const handleDelete = async () => {
  try {
    await catalogStore.deleteItem(route.params.id)
    showMessage(t('catalog.items.list.messages.delete_success', 'Item deleted successfully'), 'success')
    router.push('/catalog/items/list')
  } catch (err) {
    showMessage(err?.data?.message || 'Failed to delete item', 'error')
  }
}

const handleExportPdf = async () => {
  try {
    const filename = `${formData.value.code || 'catalog-item'}.pdf`
    await catalogStore.exportCatalogItemPdf(route.params.id, filename)
    showMessage(t('PDF exported successfully'), 'success')
  } catch (err) {
    showMessage(err?.data?.message || 'Failed to export PDF', 'error')
  }
}

const quickActions = computed(() => {
  const actions = [
    {
      id: 'save-item',
      title: isNewMode.value ? t('Create Item') : t('Save Changes'),
      icon: 'tabler-device-floppy',
      color: 'success',
      loading: saving.value,
      onClick: handleSave,
    },
  ]

  if (!isNewMode.value) {
    actions.push(
      {
        id: 'add-option',
        title: t('Add Option / Variant'),
        icon: 'tabler-plus',
        onClick: () => {
          scrollToSection('variants')
          variantsBuilderRef.value?.openAddOption()
        },
      },
      {
        id: 'generate-variants',
        title: t('Generate Variants'),
        icon: 'tabler-refresh',
        onClick: () => {
          scrollToSection('variants')
          variantsBuilderRef.value?.generateVariants()
        },
      },
      {
        id: 'add-image',
        title: t('Add Media Image'),
        icon: 'tabler-photo-plus',
        onClick: () => {
          scrollToSection('media')
          mediaManagerRef.value?.openAddImage()
        },
      },
      {
        id: 'add-document',
        title: t('Add Document'),
        icon: 'tabler-file-plus',
        onClick: () => {
          scrollToSection('media')
          mediaManagerRef.value?.openAddDocument()
        },
      },
      {
        id: 'export-pdf',
        title: t('Export PDF'),
        icon: 'tabler-file-download',
        onClick: handleExportPdf,
      },
      {
        id: 'delete-item',
        title: t('Delete Item'),
        icon: 'tabler-trash',
        color: 'error',
        onClick: () => { isDeleteDialogVisible.value = true },
      },
    )
  }

  actions.push({
    id: 'view-list',
    title: t('Back to Items'),
    icon: 'tabler-list',
    onClick: () => router.push('/catalog/items/list'),
  })

  return actions
})

// ── Intersection Observer ───────────────────────────────────────────────
onMounted(() => {
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const id = entry.target.id.replace('section-', '')

        activeSection.value = id
      }
    })
  }, { threshold: 0.2, rootMargin: '-80px 0px -50% 0px' })

  nextTick(() => {
    sections.value.forEach(s => {
      const el = document.getElementById(`section-${s.id}`)
      if (el) observer.observe(el)
    })
  })
})

// ── Lifecycle ───────────────────────────────────────────────────────────
onMounted(async () => {
  loading.value = true
  await Promise.allSettled([
    supportStore.fetchCategories({ roots_only: false, force: true }),
    supportStore.fetchBrands(),
    supportStore.fetchUom(),
    supplierStore.fetchSuppliers({ itemsPerPage: -1 }),
    guidelineStore.fetchGuidelines({ itemsPerPage: -1 }),
    fetchItem(),
  ])
  if (isNewMode.value && !originalItem.value) {
    originalItem.value = JSON.parse(JSON.stringify(formData.value))
  }
  loading.value = false
})
</script>

<template>
  <div>
    <!-- 👉 Breadcrumbs -->
    <AppBreadcrumb
      :items="breadcrumbItems"
      class="mb-4"
    />

    <!-- 👉 Skeleton Loading -->
    <VSkeletonLoader
      v-if="loading"
      type="card-avatar, article, list-item-two-line"
      class="mb-6 w-100 bg-transparent"
    />

    <!-- 👉 Main Journal Layout -->
    <div
      v-else
      class="journal-layout"
    >
      <!-- ═══ Desktop Sticky Sidebar ═══ -->
      <nav
        v-if="!smAndDown"
        class="journal-nav glass-panel"
      >
        <div class="d-flex align-center mb-4">
          <VAvatar
            rounded
            :size="44"
            :color="resolveTypeColor(formData.item_type)"
            variant="tonal"
          >
            <VIcon
              :icon="resolveTypeIcon(formData.item_type)"
              size="24"
            />
          </VAvatar>
          <div class="ms-3 overflow-hidden">
            <h6
              class="text-subtitle-1 font-weight-bold text-truncate"
              style="max-inline-size: 140px;"
            >
              {{ isNewMode ? 'New Item' : formData.code }}
            </h6>
            <span class="text-caption text-disabled">{{ localizedValue(formData.name) || 'Catalog Item' }}</span>
          </div>
        </div>

        <VDivider class="mb-3" />

        <PerfectScrollbar
          :options="{ wheelPropagation: false }"
          class="nav-scroll-area"
        >
          <VList
            density="compact"
            nav
            class="bg-transparent"
          >
            <VListItem
              v-for="s in sections"
              :key="s.id"
              :active="activeSection === s.id"
              color="primary"
              rounded="lg"
              @click="scrollToSection(s.id)"
            >
              <template #prepend>
                <VIcon
                  :icon="s.icon"
                  size="18"
                />
              </template>
              <VListItemTitle class="text-body-2">
                {{ s.label }}
              </VListItemTitle>
            </VListItem>
          </VList>
        </PerfectScrollbar>

        <div class="mt-auto pt-4">
          <VDivider class="mb-4" />
          <VBtn
            block
            color="primary"
            :loading="saving"
            class="mb-2"
            @click="handleSave"
          >
            <VIcon
              start
              icon="tabler-device-floppy"
            />
            {{ isNewMode ? 'Create Item' : 'Save Changes' }}
          </VBtn>
          <VBtn
            block
            variant="tonal"
            color="secondary"
            :to="{ name: 'catalog-items-list' }"
          >
            <VIcon
              start
              icon="tabler-arrow-left"
            />
            Back to List
          </VBtn>
        </div>
      </nav>

      <!-- ═══ Journal Feed ═══ -->
      <main class="journal-feed">
        <!-- ──── Mobile Header Bar ──── -->
        <div
          v-if="smAndDown"
          class="d-flex justify-space-between align-center mb-4"
        >
          <div class="d-flex align-center gap-3">
            <VAvatar
              rounded
              :size="36"
              :color="resolveTypeColor(formData.item_type)"
              variant="tonal"
            >
              <VIcon
                :icon="resolveTypeIcon(formData.item_type)"
                size="20"
              />
            </VAvatar>
            <div>
              <h6 class="text-subtitle-1 font-weight-bold">
                {{ isNewMode ? 'New Item' : formData.code }}
              </h6>
              <span class="text-caption text-disabled">{{ localizedValue(formData.name) || 'Catalog Item' }}</span>
            </div>
          </div>
        </div>

        <!-- ━━━ Classification ━━━ -->
        <section
          id="section-classification"
          class="journal-section"
        >
          <div class="glass-wrapper">
            <VCard>
              <VCardText>
                <WizardStepClassification
                  :key="classificationKey"
                  v-model:item-type="formData.item_type"
                  v-model:sub-type="subTypeComputed"
                  v-model:category-id="formData.category_id"
                  :categories="supportStore.categories"
                  :loading-categories="supportStore.loadingCategories"
                  :disabled="!isNewMode"
                />
              </VCardText>
            </VCard>
          </div>
        </section>

        <!-- ━━━ General Info ━━━ -->
        <section
          id="section-general"
          class="journal-section"
        >
          <div class="glass-wrapper">
            <VCard>
              <VCardText>
                <VForm ref="formRefGeneral">
                  <WizardStepGeneralInfo
                    v-model="formData"
                    :item-type="formData.item_type"
                    :sub-type="subTypeComputed"
                    :is-edit-mode="!isNewMode"
                    @update:item-type="formData.item_type = $event"
                    @update:sub-type="subTypeComputed = $event"
                  />
                </VForm>
              </VCardText>
            </VCard>
          </div>
        </section>

        <!-- ━━━ Specifications ━━━ -->
        <section
          id="section-specifications"
          class="journal-section"
        >
          <div class="glass-wrapper">
            <VCard>
              <VCardText>
                <WizardStepSpecifications
                  v-model:product="formData.product"
                  v-model:consumable="formData.consumable"
                  v-model:eq-servicing="formData.eq_servicing"
                  :item-type="formData.item_type"
                />
              </VCardText>
            </VCard>
          </div>
        </section>


        <!-- ━━━ Commerce ━━━ -->
        <section
          id="section-commerce"
          class="journal-section"
        >
          <div class="glass-wrapper">
            <VCard>
              <VCardText>
                <WizardStepCommerce
                  v-model="formData"
                  :item-type="formData.item_type"
                  :supplier-options="supplierOptions"
                />
              </VCardText>
            </VCard>
          </div>
        </section>

        <!-- ━━━ Media & Files (edit mode only) ━━━ -->
        <section
          v-if="!isNewMode"
          id="section-media"
          class="journal-section"
        >
          <div class="glass-wrapper">
            <VCard>
              <VCardItem>
                <VCardTitle class="d-flex align-center gap-2">
                  <VIcon
                    icon="tabler-photo-video"
                    size="22"
                  />
                  Media & Files
                </VCardTitle>
              </VCardItem>
              <VDivider />
              <VCardText>
                <CatalogMediaManager
                  ref="mediaManagerRef"
                  :key="mediaKey"
                  :catalog-item-id="route.params.id"
                  :media="{ images: formData.media_images, documents: formData.media_documents, certificates: formData.media_certificates }"
                  @refresh="fetchItem"
                />
              </VCardText>
            </VCard>
          </div>
        </section>

        <!-- ━━━ Variants (always visible) ━━━ -->
        <section
          id="section-variants"
          class="journal-section"
        >
          <div class="glass-wrapper">
            <!-- Edit mode: Full VariantsBuilder -->
            <VariantsBuilder
              ref="variantsBuilderRef"
              v-if="!isNewMode"
              :catalog-item-id="route.params.id"
              :item-code="formData.code"
              :hide-stock-fields="isNonStockable"
            />

            <!-- New mode: Default variant info -->
            <!-- New mode: Default variant info -->
            <VCard v-else>
              <VCardTitle class="text-subtitle-1 font-weight-bold d-flex align-center gap-2 py-3 px-4">
                <VIcon
                  icon="tabler-box"
                  size="20"
                  color="primary"
                />
                Default SKU Settings
              </VCardTitle>
              <VDivider />
              <VCardText>
                <VAlert
                  type="info"
                  variant="tonal"
                  density="compact"
                  class="mb-4"
                >
                  Configure the primary attributes for this item. You can add more complex variants (like sizes/colors) after saving.
                </VAlert>
                <VRow dense>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <AppTextField
                      v-model="formData.variants[0].sku"
                      label="SKU"
                      variant="outlined"
                      hint="Auto-generated if left blank"
                      persistent-hint
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="3"
                  >
                    <AppTextField
                      v-model="formData.variants[0].barcode"
                      label="Barcode"
                      variant="outlined"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="2"
                  >
                    <AppTextField
                      v-model="formData.variants[0].cost_price"
                      label="Cost Price"
                      type="number"
                      prefix="$"
                      variant="outlined"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="2"
                  >
                    <AppTextField
                      v-model="formData.variants[0].price"
                      label="Price"
                      type="number"
                      prefix="$"
                      variant="outlined"
                    />
                  </VCol>
                  <VCol
                    v-if="!isNonStockable"
                    cols="12"
                    md="2"
                  >
                    <AppTextField
                      v-model="formData.variants[0].min_stock_level"
                      label="Min Stock"
                      type="number"
                      variant="outlined"
                    />
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </div>
        </section>
      </main>
    </div>

    <!-- ═══ Mobile Bottom Navigation ═══ -->
    <AppBottomNav
      v-if="smAndDown && !loading"
      v-model="activeSection"
      :items="mobileNavItems"
      fit-content
      :show-badges="false"
      @change="item => scrollToSection(item.value)"
    />

    <VFab
      v-if="smAndDown && !loading"
      class="catalog-edit-quick-actions-launcher"
      color="primary"
      icon="tabler-bolt"
      rounded="lg"
      size="large"
      :aria-label="$t('Quick Actions')"
      @click="quickActionsOpen = true"
    />
    <AppQuickActionsSheet
      v-model="quickActionsOpen"
      :title="$t('Quick Actions')"
      :actions="quickActions"
    />

    <!-- 👉 Delete Dialog -->
    <WpDeleteDialog
      v-model="isDeleteDialogVisible"
      :title="$t('Are you sure you want to delete this catalog item?')"
      :description="$t('This action cannot be undone.')"
      :confirm-action="handleDelete"
    />

    <!-- 👉 Unsaved Changes Confirmation Dialog -->
    <WpUnsavedChangesDialog
      v-model="isUnsavedDialogVisible"
      @confirm="confirmLeave"
      @cancel="cancelLeave"
    />
  </div>
</template>

<style lang="scss" scoped>
/* ── Journal Layout Grid ─────────────────────────────────────────────── */
.journal-layout {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: 1.5rem;
  min-block-size: 100vh;

  @media (max-width: 960px) {
    flex-direction: column;
    padding-block-end: calc(96px + env(safe-area-inset-bottom, 0px)); // space for bottom nav & FAB
  }
}

.catalog-edit-quick-actions-launcher {
  position: fixed;
  z-index: 1001;
  inset-block-end: calc(76px + env(safe-area-inset-bottom, 0px));
  inset-inline-end: 25px;
}

/* ── Glassmorphism Panel ─────────────────────────────────────────────── */
.glass-panel {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 16px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.7) !important;
  box-shadow:
    0 4px 32px rgba(0, 0, 0, 6%),
    0 1px 4px rgba(0, 0, 0, 4%),
    inset 0 1px 0 rgba(255, 255, 255, 6%);
  transition: box-shadow 0.3s ease;

  &:hover {
    box-shadow:
      0 8px 40px rgba(0, 0, 0, 10%),
      0 2px 8px rgba(0, 0, 0, 6%),
      inset 0 1px 0 rgba(255, 255, 255, 8%);
  }
}

/* stylelint-disable selector-pseudo-class-no-unknown */
.glass-wrapper :deep(.v-card) {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 16px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.7) !important;
  box-shadow:
    0 4px 32px rgba(0, 0, 0, 6%),
    0 1px 4px rgba(0, 0, 0, 4%),
    inset 0 1px 0 rgba(255, 255, 255, 6%);
  transition: box-shadow 0.3s ease;

  &:hover {
    box-shadow:
      0 8px 40px rgba(0, 0, 0, 10%),
      0 2px 8px rgba(0, 0, 0, 6%),
      inset 0 1px 0 rgba(255, 255, 255, 8%);
  }
}

/* ── Sticky Sidebar ──────────────────────────────────────────────────── */
.journal-nav {
  position: sticky;
  display: flex;
  flex: 0 0 240px;
  flex-direction: column;
  padding: 1.25rem;
  inline-size: 240px;
  inset-block-start: 80px;
  max-block-size: calc(100vh - 100px);
}

.nav-scroll-area {
  flex: 1;
  max-block-size: calc(100vh - 320px);
}

/* ── Journal Feed ────────────────────────────────────────────────────── */
.journal-feed {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 1.5rem;
  inline-size: 100%;
  min-inline-size: 0;
  padding-block-end: 2rem;
}

.journal-section {
  scroll-margin-block-start: 80px;

  @media (max-width: 960px) {
    scroll-margin-block-start: 16px;
  }
}

.catalog-edit-quick-actions-launcher {
  position: fixed;
  z-index: 1001;
  inset-block-end: calc(76px + env(safe-area-inset-bottom, 0px));
  inset-inline-end: 20px;
}
</style>
