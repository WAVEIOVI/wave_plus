<script setup>
/* eslint-disable camelcase */
import { WpJournalNav, WpMediaViewerDialog } from '@/components/shared'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { useCatalogItemStore } from '@/stores/useCatalogItemStore'
import { useCatalogSupportStore } from '@/stores/useCatalogSupportStore'
import { useGuidelineStore } from '@/stores/useGuidelineStore'
import { useSupplierStore } from '@/stores/useSupplierStore'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import { Navigation } from 'swiper/modules'
import { Swiper, SwiperSlide } from 'swiper/vue'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'

// --- Wizard Components ---
import CatalogMediaManager from '@/views/catalog/items/form/CatalogMediaManager.vue'
import VariantsBuilder from '@/views/catalog/items/form/VariantsBuilder.vue'
import WizardStepClassification from '@/views/catalog/items/form/WizardStepClassification.vue'
import WizardStepCommerce from '@/views/catalog/items/form/WizardStepCommerce.vue'
import WizardStepGeneralInfo from '@/views/catalog/items/form/WizardStepGeneralInfo.vue'
import WizardStepSpecifications from '@/views/catalog/items/form/WizardStepSpecifications.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  isDialogVisible: { type: Boolean, default: false },
  item: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  error: { type: [String, Object], default: null },
})

const emit = defineEmits([
  'update:modelValue',
  'update:isDialogVisible',
  'openEmail',
  'retry',
])

const catalogStore = useCatalogItemStore()
const supportStore = useCatalogSupportStore()
const supplierStore = useSupplierStore()
const guidelineStore = useGuidelineStore()
const { showMessage } = useAppSnackbar()

const { smAndDown } = useDisplay()

// ── State ───────────────────────────────────────────────────────────────
const activeSection = ref('classification')
const isEditMode = false

// PDF Export & Email
const isExportingPdf = ref(false)
const isSendingEmail = ref(false)
const isSendEmailDialogVisible = ref(false)
const isTopMediaViewerOpen = ref(false)
const observer = ref(null)
const isManualSelection = ref(false)

const formData = ref({
  id: null,
  code: '',
  sku: '',
  barcode: '',
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
  product: {},
  consumable: {},
  eq_servicing: {},
  tags: [],
  certifications: [],
  suppliers: [],
  media_images: [],
  media_documents: [],
  media_certificates: [],
})

const activeMediaImageIndex = ref(0)

const activeMediaImage = computed(() => {
  const images = formData.value.media_images || []
  if (images.length === 0) return null

  const img = images[activeMediaImageIndex.value] || images[0]

  if (!img) return null
  if (typeof img === 'string') return img

  // Backend returns 'url' field from Spatie MediaLibrary
  // Support both 'url' and 'original_url' for compatibility
  return img.url || img.original_url || ''
})

const visible = computed({
  get: () => props.modelValue || props.isDialogVisible,
  set: val => {
    emit('update:modelValue', val)
    emit('update:isDialogVisible', val)
  },
})

// ── Dropdown Options (Needed by child steps) ────────────────────────────
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

// Sub-type proxy
const subTypeComputed = computed({
  get: () => {
    if (formData.value.item_type === 'product') return formData.value.product?.product_type
    if (formData.value.item_type === 'consumable') return formData.value.consumable?.consumable_type
    if (formData.value.item_type === 'eq_servicing') return formData.value.eq_servicing?.eq_servicing_type
    
    return ''
  },
  set: val => {
    // No-op for View mode, but needed for the computed to not break
  },
})

// ── Navigation Sections ─────────────────────────────────────────────────
const sections = computed(() => {
  const arr = [
    { id: 'classification', label: 'Classification', icon: 'tabler-category' },
    { id: 'general', label: 'General Info', icon: 'tabler-id' },
    { id: 'specifications', label: 'Specifications', icon: 'tabler-adjustments-horizontal' },
  ]

  arr.push(
    { id: 'commerce', label: 'Commerce', icon: 'tabler-coin' },
    { id: 'media', label: 'Media & Files', icon: 'tabler-photo-video' },
  )

  arr.push({ id: 'variants', label: 'Variants', icon: 'tabler-grid-dots' })

  return arr
})

const mobileNavItems = computed(() => sections.value.map(s => ({
  title: s.label,
  icon: s.icon,
  key: s.id,
  value: s.id,
})))

const scrollToSection = sid => {
  isManualSelection.value = true
  activeSection.value = sid

  const el = document.getElementById(`view-${sid}`)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  
  // Reset manual selection after animation
  setTimeout(() => {
    isManualSelection.value = false
  }, 1000)
}

// ── Intersection Observer Logic ─────────────────────────────────────────
const setupObserver = () => {
  const options = {
    root: document.querySelector('.view-dialog-card .v-card-text'),
    threshold: 0.3,
    rootMargin: '0px 0px -50% 0px',
  }

  observer.value = new IntersectionObserver(entries => {
    if (isManualSelection.value) return

    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const id = entry.target.id.replace('view-', '')

        activeSection.value = id
      }
    })
  }, options)

  // Observe all sections
  const observedSections = document.querySelectorAll('.view-section[id^="view-"]')

  observedSections.forEach(section => observer.value.observe(section))
}

const cleanupObserver = () => {
  if (observer.value) {
    observer.value.disconnect()
    observer.value = null
  }
}

onMounted(() => {
  // We'll setup when the dialog opens
})

onUnmounted(() => {
  cleanupObserver()
})

// ── Fetch Logic ────────────────────────────────────────────────────────
const fetchItemDetails = async id => {
  loading.value = true
  try {
    const data = await catalogStore.fetchItem(id)

    formData.value = {
      ...formData.value,
      ...data,
      name: ensureTranslatable(data.name),
      short_description: ensureTranslatable(data.short_description || data.description),
      uom_id: data.uom_id || data.primary_uom_id || null, 
      
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
  }
  catch (err) {
    console.error(err)
    showMessage('Failed to load item details.', 'error')
  } finally {
    loading.value = false
  }
}

const populateItemData = data => {
  if (!data) return
  formData.value = {
    ...formData.value,
    ...data,
    name: ensureTranslatable(data.name),
    short_description: ensureTranslatable(data.short_description || data.description),
    uom_id: data.uom_id || data.primary_uom_id || null,
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
}

watch([visible, () => props.item], async ([isOpen, item]) => {
  if (isOpen && item) {
    activeMediaImageIndex.value = 0

    // Basic loading of dependencies first
    await Promise.allSettled([
      supportStore.fetchCategories({ roots_only: false }),
      supportStore.fetchBrands(),
      supportStore.fetchUom(),
      supplierStore.fetchSuppliers({ itemsPerPage: -1 }),
      guidelineStore.fetchGuidelines({ itemsPerPage: -1 }),
    ])

    if (item.variants && item.category) {
      populateItemData(item)
    }
    else if (item.id) {
      await fetchItemDetails(item.id)
    }

    setTimeout(setupObserver, 300)
  } else if (!isOpen) {
    activeSection.value = 'classification'
    cleanupObserver()
  }
}, { immediate: true })

// ── PDF Export handler ──────────────────────────────────────────────────
const handleExportPdf = async () => {
  if (!formData.value.id) return
  isExportingPdf.value = true
  try {
    const filename = `catalog-item-${formData.value.code || formData.value.id}.pdf`

    await catalogStore.exportCatalogItemPdf(formData.value.id, filename)
    showMessage('PDF exported successfully', 'success')
  } catch (err) {
    console.error('Failed to export PDF:', err)
    showMessage('Failed to export PDF', 'error')
  } finally {
    isExportingPdf.value = false
  }
}
</script>

<template>
  <VDialog
    v-model="visible"
    :max-width="smAndDown ? undefined : 1150"
    :fullscreen="smAndDown"
    scrollable
  >
    <VCard
      class="view-dialog-card"
      :class="{ 'rounded-0': smAndDown }"
    >
      <!-- 👉 Header -->
      <VCardTitle class="d-flex justify-space-between align-center pa-4 pa-sm-5 bg-surface border-b flex-wrap gap-2">
        <div
          class="d-flex align-center gap-3 overflow-hidden"
          style="max-inline-size: calc(100% - 150px); min-inline-size: 0;"
        >
          <VAvatar
            rounded
            size="38"
            color="primary"
            variant="tonal"
            class="flex-shrink-0"
          >
            <VIcon
              :icon="formData.item_type === 'product' ? 'tabler-box' : (formData.item_type === 'consumable' ? 'tabler-flame' : 'tabler-tools')"
              size="20"
            />
          </VAvatar>
          <div class="overflow-hidden">
            <h5 class="text-subtitle-1 font-weight-bold mb-0 text-truncate">
              {{ localizedValue(formData.name) || formData.code || 'Item Preview' }}
            </h5>
            <span class="text-caption text-disabled text-truncate d-block">{{ formData.code ? `${formData.code} · ` : '' }}{{ formData.item_type ? $t(formData.item_type) : 'Catalog' }}</span>
          </div>
        </div>

        <div class="d-flex align-center gap-1 gap-sm-2 flex-shrink-0">
          <VBtn
            v-if="formData.id"
            variant="tonal"
            size="small"
            color="info"
            :icon="smAndDown ? 'tabler-file-download' : undefined"
            :prepend-icon="!smAndDown ? 'tabler-file-download' : undefined"
            :loading="isExportingPdf"
            :title="$t('Export PDF')"
            @click="handleExportPdf"
          >
            <template v-if="!smAndDown">
              PDF
            </template>
          </VBtn>
          <VBtn
            v-if="formData.id"
            variant="tonal"
            size="small"
            color="success"
            :icon="smAndDown ? 'tabler-mail' : undefined"
            :prepend-icon="!smAndDown ? 'tabler-mail' : undefined"
            :title="$t('Send Email')"
            @click="emit('openEmail', formData)"
          >
            <template v-if="!smAndDown">
              Email
            </template>
          </VBtn>
          <VBtn
            v-if="formData.id"
            variant="tonal"
            size="small"
            color="secondary"
            :icon="smAndDown ? 'tabler-edit' : undefined"
            :prepend-icon="!smAndDown ? 'tabler-edit' : undefined"
            :title="$t('Edit Item')"
            :to="{ name: 'catalog-items-edit-id', params: { id: formData.id } }"
          >
            <template v-if="!smAndDown">
              Edit
            </template>
          </VBtn>
          <VBtn
            icon
            variant="text"
            size="small"
            @click="visible = false"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </div>
      </VCardTitle>

      <VDivider />

      <!-- 👉 Content -->
      <VCardText
        class="pa-0"
        :style="smAndDown ? undefined : 'max-block-size: 85vh; overflow-y: auto;'"
      >
        <div
          v-if="loading"
          class="d-flex justify-center align-center py-16 w-100 h-100"
        >
          <div class="text-center">
            <VProgressCircular
              indeterminate
              size="48"
              color="primary"
              class="mb-4"
            />
            <p class="text-body-2 text-disabled">
              Loading item details...
            </p>
          </div>
        </div>

        <div
          v-else
          class="view-layout"
        >
          <!-- ═══ Navigation: Desktop Sticky Sidebar + Mobile Bottom Navigation ═══ -->
          <WpJournalNav
            v-model="activeSection"
            :sections="sections"
            is-dialog
            id-prefix="view-"
            container=".v-card-text"
          />

          <!-- 🔹 Content Feed -->
          <main class="view-feed">
            <!-- 👉 Top Image Gallery -->
            <div
              v-if="formData.media_images?.length"
              class="view-section mb-6"
            >
              <VCard
                class="glass-panel"
                variant="flat"
              >
                <VCardText>
                  <!-- Main Feature Image -->
                  <div class="mb-3">
                    <VImg
                      :src="activeMediaImage"
                      height="350"
                      cover
                      class="rounded-lg bg-grey-lighten-2 cursor-pointer"
                      @click="isTopMediaViewerOpen = true"
                    >
                      <template #placeholder>
                        <div class="d-flex align-center justify-center fill-height">
                          <VProgressCircular
                            indeterminate
                            color="grey-lighten-4"
                          />
                        </div>
                      </template>
                      <template #error>
                        <div class="d-flex align-center justify-center fill-height bg-grey-lighten-3">
                          <VIcon
                            icon="tabler-photo-off"
                            size="48"
                            color="disabled"
                          />
                        </div>
                      </template>
                    </VImg>
                  </div>
                  
                  <!-- Thumbnail Row -->
                  <div v-if="formData.media_images.length > 1">
                    <Swiper
                      :modules="[Navigation]"
                      slides-per-view="auto"
                      :space-between="12"
                      navigation
                      class="swiper-thumbnails"
                    >
                      <SwiperSlide
                        v-for="(img, idx) in formData.media_images"
                        :key="idx"
                        class="thumbnail-slide"
                      >
                        <VImg
                          :src="img.url || img.original_url || (typeof img === 'string' ? img : '')"
                          height="80"
                          cover
                          class="thumbnail-img rounded cursor-pointer transition-all"
                          :class="{ 'active-thumbnail border-primary': activeMediaImageIndex === idx }"
                          @click="activeMediaImageIndex = idx"
                        >
                          <template #placeholder>
                            <div class="d-flex align-center justify-center fill-height bg-grey-lighten-3">
                              <VIcon
                                icon="tabler-photo"
                                size="24"
                                color="disabled"
                              />
                            </div>
                          </template>
                          <template #error>
                            <div class="d-flex align-center justify-center fill-height bg-grey-lighten-3">
                              <VIcon
                                icon="tabler-photo-off"
                                size="24"
                                color="disabled"
                              />
                            </div>
                          </template>
                        </VImg>
                      </SwiperSlide>
                    </Swiper>
                  </div>
                </VCardText>
              </VCard>
            </div>

            <!-- Image Placeholder -->
            <div
              v-else
              class="view-section mb-6"
            >
              <VCard
                class="glass-panel"
                variant="flat"
              >
                <VCardText class="d-flex flex-column align-center justify-center py-12 bg-grey-lighten-4 rounded-lg">
                  <VAvatar
                    size="80"
                    color="primary"
                    variant="tonal"
                    class="mb-4"
                  >
                    <VIcon
                      icon="tabler-package"
                      size="40"
                    />
                  </VAvatar>
                  <div class="text-center">
                    <h6 class="text-h6 font-weight-bold mb-1">
                      {{ formData.code || 'Item' }}
                    </h6>
                    <VChip
                      size="small"
                      label
                      color="primary"
                      variant="tonal"
                    >
                      {{ formData.item_type ? $t(formData.item_type) : 'Catalog Item' }}
                    </VChip>
                  </div>
                </VCardText>
              </VCard>
            </div>

            <VForm readonly>
              <!-- 0: Classification -->
              <section
                id="view-classification"
                class="view-section"
              >
                <VCard
                  class="glass-panel"
                  variant="flat"
                >
                  <VCardText>
                    <h6 class="text-h6 mb-4 d-flex align-center gap-2">
                      <VIcon
                        icon="tabler-category"
                        size="20"
                        color="primary"
                      /> Classification
                    </h6>
                    <WizardStepClassification
                      v-model:item-type="formData.item_type"
                      v-model:sub-type="subTypeComputed"
                      disabled
                    />
                  </VCardText>
                </VCard>
              </section>

              <!-- 1: General Info -->
              <section
                id="view-general"
                class="view-section"
              >
                <VCard
                  class="glass-panel"
                  variant="flat"
                >
                  <VCardText>
                    <h6 class="text-h6 mb-4 d-flex align-center gap-2">
                      <VIcon
                        icon="tabler-id"
                        size="20"
                        color="primary"
                      /> Identification
                    </h6>
                    <WizardStepGeneralInfo
                      v-model="formData"
                      :item-type="formData.item_type"
                      :sub-type="subTypeComputed"
                      readonly
                    />
                  </VCardText>
                </VCard>
              </section>

              <!-- 2: Specifications -->
              <section
                id="view-specifications"
                class="view-section"
              >
                <VCard
                  class="glass-panel"
                  variant="flat"
                >
                  <VCardText>
                    <h6 class="text-h6 mb-4 d-flex align-center gap-2">
                      <VIcon
                        icon="tabler-adjustments-horizontal"
                        size="20"
                        color="primary"
                      /> Technical Details
                    </h6>
                    <WizardStepSpecifications
                      v-model:product="formData.product"
                      v-model:consumable="formData.consumable"
                      v-model:eq-servicing="formData.eq_servicing"
                      :item-type="formData.item_type"
                      readonly
                    />
                  </VCardText>
                </VCard>
              </section>


              <!-- 4: Commerce -->
              <section
                id="view-commerce"
                class="view-section"
              >
                <VCard
                  class="glass-panel"
                  variant="flat"
                >
                  <VCardText>
                    <h6 class="text-h6 mb-4 d-flex align-center gap-2">
                      <VIcon
                        icon="tabler-coin"
                        size="20"
                        color="primary"
                      /> Commerce & Pricing
                    </h6>
                    <WizardStepCommerce
                      v-model="formData"
                      :item-type="formData.item_type"
                      :supplier-options="supplierOptions"
                      readonly
                    />
                  </VCardText>
                </VCard>
              </section>

              <!-- 5: Media & Files -->
              <section
                id="view-media"
                class="view-section"
              >
                <VCard
                  class="glass-panel"
                  variant="flat"
                >
                  <VCardText>
                    <h6 class="text-h6 mb-4 d-flex align-center gap-2">
                      <VIcon
                        icon="tabler-photo-video"
                        size="20"
                        color="primary"
                      /> Media & Attachments
                    </h6>
                    <CatalogMediaManager
                      v-if="formData.id"
                      :catalog-item-id="String(formData.id)"
                      :media="{ images: formData.media_images, documents: formData.media_documents, certificates: formData.media_certificates }"
                      read-only
                    />
                  </VCardText>
                </VCard>
              </section>

              <!-- 6: Variants -->
              <section
                id="view-variants"
                class="view-section"
              >
                <VCard
                  class="glass-panel"
                  variant="flat"
                >
                  <VCardText>
                    <h6 class="text-h6 mb-4 d-flex align-center gap-2">
                      <VIcon
                        icon="tabler-grid-dots"
                        size="20"
                        color="primary"
                      /> Variants
                    </h6>
                    <VariantsBuilder
                      v-if="formData.id"
                      :catalog-item-id="String(formData.id)"
                      :item-code="formData.code"
                      read-only
                    />
                  </VCardText>
                </VCard>
              </section>
            </VForm>
          </main>
        </div>
      </VCardText>
    </VCard>
  </VDialog>

  <WpMediaViewerDialog
    v-model="isTopMediaViewerOpen"
    :items="formData.media_images || []"
    :initial-index="activeMediaImageIndex"
  />

  <!-- 👉 Send Email Drawer -->
</template>

<style lang="scss" scoped>
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

/* ── Layout ──────────────────────────────────────────────────────────── */
.view-dialog-card {
  position: relative;
  display: flex;
  overflow: hidden;
  flex-direction: column;
  border-radius: 16px !important;
  background: rgba(var(--v-theme-surface), 0.9) !important;
}

.view-layout {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  padding: 1.5rem;
  gap: 1.5rem;
  min-block-size: 600px;

  @media (max-width: 960px) {
    flex-direction: column;
    padding: 1rem;
    padding-block-end: 80px;
  }
}

.view-nav {
  position: sticky;
  display: flex;
  flex: 0 0 220px;
  flex-direction: column;
  padding: 1rem;
  inline-size: 220px;
  inset-block-start: 1rem;
}

.view-feed {
  display: flex;
  flex: 1;
  flex-direction: column;
  padding: 0;
  gap: 1.5rem;
  inline-size: 100%;
  min-inline-size: 0;
}

.view-section {
  margin-block-end: 1rem;
  scroll-margin-block-start: 16px;
}

/* ── Mobile Navigation Bar ───────────────────────────────────────────── */
.mobile-section-bar {
  position: fixed;
  z-index: 999;
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.95);
  border-block-start: 1px solid rgba(var(--v-border-color), 0.12);
  box-shadow: 0 -4px 24px rgba(0, 0, 0, 8%);
  gap: 8px;
  inline-size: 100%;
  inset-block-end: 0;
  inset-inline: 0;
  max-inline-size: 100%;
  padding-block: 12px;
  padding-inline: 16px;

  /* Allow perfect scrollbar to show */
  :deep(.ps__rail-y) {
    z-index: 1000;
  }
}

.mobile-section-btn {
  flex: 0 0 auto;
}

/* Ensure form controls look okay when readonly */
:deep(.v-field--variant-outlined.v-field--disabled .v-field__outline) {
  --v-field-border-opacity: 0.05;
}

/* ── Swiper Thumbnails ───────────────────────────────────────────────── */
.thumbnail-slide {
  inline-size: 100px;
  max-inline-size: 100px;
}

.thumbnail-img {
  border: 2px solid transparent;
  opacity: 0.6;
  transition: opacity 0.3s ease, border-color 0.3s ease;
}

.thumbnail-img:hover {
  opacity: 0.8;
}

.active-thumbnail {
  border: 2px solid rgb(var(--v-theme-primary)) !important;
  opacity: 1 !important;
}

:deep(.swiper-thumbnails .swiper-button-prev),
:deep(.swiper-thumbnails .swiper-button-next) {
  border-radius: 50%;
  background: rgba(0, 0, 0, 30%);
  block-size: 28px;
  color: white;
  inline-size: 28px;
  opacity: 0;
  transition: opacity 0.3s ease;

  &::after {
    font-size: 12px;
  }

  &:hover {
    background: rgba(var(--v-theme-primary), 0.8);
  }
}

.swiper-thumbnails:hover {
  :deep(.swiper-button-prev),
  :deep(.swiper-button-next) {
    opacity: 1;
  }
}
</style>
