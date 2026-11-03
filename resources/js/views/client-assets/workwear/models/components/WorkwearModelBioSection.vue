<script setup>
/* eslint-disable camelcase, vue/no-mutating-props */
import { WpFormDialog, WpStatusChip } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { $api } from '@/utils/api'
import WorkwearSectionCard from '@/views/client-assets/workwear/models/components/WorkwearSectionCard.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelData: { type: Object, required: true },
  statusOptions: { type: Array, required: true },
  itemsCount: { type: Number, default: 0 },
})

const { locale, t } = useI18n()
const store = useClientAssetStore()

const isEditDialogOpen = ref(false)
const clientOptions = ref([])
const supplierOptions = ref([])
const catalogItemOptions = ref([])
const loadingLookups = ref(false)
const copiedRef = ref(false)

const canChangeClient = computed(() => (props.modelData.items_count ?? props.itemsCount ?? 0) === 0)

const totalItems = computed(() => props.modelData.items_count ?? props.itemsCount ?? 0)
const availableItems = computed(() => props.modelData.available_items_count ?? 0)
const assignedItems = computed(() => Math.max(0, totalItems.value - availableItems.value))

const availabilityPercentage = computed(() => {
  if (totalItems.value <= 0) return 0

  return Math.round((availableItems.value / totalItems.value) * 100)
})

const availabilityColor = computed(() => {
  if (availabilityPercentage.value >= 50) return 'success'
  if (availabilityPercentage.value > 0) return 'warning'

  return 'error'
})

const selectedFabricObj = computed(() =>
  (store.workwearFabrics || []).find(f => f.id === props.modelData.fabric_id) || props.modelData.workwear_fabric || null,
)

const linkedCatalogItem = computed(() => props.modelData.catalog_item || null)

const preferredSupplierName = computed(() =>
  props.modelData.supplier?.legal_name
  || props.modelData.supplier?.name
  || linkedCatalogItem.value?.preferred_supplier?.legal_name
  || linkedCatalogItem.value?.preferred_supplier?.name
  || null,
)

const clientDisplayName = computed(() =>
  props.modelData.client?.commercial_name
  || props.modelData.client?.legal_name
  || props.modelData.client?.name
  || (props.modelData.client_id ? `${t('Client')} #${props.modelData.client_id}` : null),
)

function formatCatalogLabel(item) {
  if (!item) return ''
  const name = localizedValue(item.name, locale.value) || item.code

  return item.code ? `${item.code} — ${name}` : name
}

function handleFabricChange(fabricId) {
  props.modelData.fabric_id = fabricId

  const found = (store.workwearFabrics || []).find(f => f.id === fabricId)
  if (found) {
    props.modelData.fabric = found.name
    props.modelData.workwear_fabric = found
  }
}

async function handleCatalogItemChange(catalogItemId) {
  props.modelData.catalog_item_id = catalogItemId
  if (!catalogItemId) {
    props.modelData.catalog_item = null

    return
  }

  let item = catalogItemOptions.value.find(i => i.id === catalogItemId) || null
  try {
    const full = await store.fetchCatalogItemDetails(catalogItemId)
    if (full) item = full
  } catch {
    // fallback
  }

  if (item) {
    props.modelData.catalog_item = item

    const prefSupplierId = item.preferred_supplier_id || item.preferred_supplier?.id || item.suppliers?.[0]?.id
    if (prefSupplierId) {
      props.modelData.supplier_id = prefSupplierId

      const prefName = item.preferred_supplier?.legal_name || item.suppliers?.[0]?.legal_name
      if (prefName && !supplierOptions.value.some(s => s.value === prefSupplierId)) {
        supplierOptions.value.push({ title: prefName, value: prefSupplierId })
      }
    }
  }
}

async function copyRefToClipboard() {
  if (!props.modelData.model_ref) return

  try {
    await navigator.clipboard.writeText(props.modelData.model_ref)
    copiedRef.value = true
    setTimeout(() => {
      copiedRef.value = false
    }, 2000)
  } catch {
    // Clipboard API is unavailable on insecure origins; fail quietly.
  }
}

onMounted(async () => {
  loadingLookups.value = true
  try {
    const [clientsRes, suppliersRes, catalogItems] = await Promise.all([
      $api('/bp/clients/options', { params: { limit: 150 } }).catch(() => ({ data: [] })),
      $api('/bp/suppliers/options', { params: { limit: 150 } }).catch(() => ({ data: [] })),
      store.fetchPhysicalCatalogItems({ per_page: 100 }).catch(() => []),
      store.fetchWorkwearFabrics({ per_page: 100, seed_if_empty: 1 }).catch(() => null),
    ])

    clientOptions.value = (clientsRes?.data || []).map(c => ({
      title: c.commercial_name || c.legal_name || c.name,
      value: c.id,
    }))
    supplierOptions.value = (suppliersRes?.data || []).map(s => ({
      title: s.legal_name || s.name,
      value: s.id,
    }))
    catalogItemOptions.value = (catalogItems || []).filter(
      item => item.item_type === 'product' && (!item.sub_type || item.sub_type === 'physical'),
    )
    if (props.modelData.catalog_item && !catalogItemOptions.value.some(i => i.id === props.modelData.catalog_item.id)) {
      catalogItemOptions.value.unshift(props.modelData.catalog_item)
    }
  } catch (err) {
    console.error('Failed to load lookups for workwear bio section:', err)
  } finally {
    loadingLookups.value = false
  }
})
</script>

<template>
  <WorkwearSectionCard
    id="overview"
    icon="tabler-id"
    :title="$t('Overview & Assets')"
    :subtitle="$t('Read-only linked context, client account, physical catalog item, and stock availability.')"
  >
    <template #chips>
      <WpStatusChip
        :status="modelData.status"
        size="small"
      />
      <VChip
        size="small"
        variant="outlined"
        class="font-mono"
        :title="$t('Copy model reference')"
        @click="copyRefToClipboard"
      >
        <VIcon
          start
          :icon="copiedRef ? 'tabler-check' : 'tabler-copy'"
          size="14"
          :color="copiedRef ? 'success' : undefined"
        />
        {{ modelData.model_ref }}
      </VChip>
    </template>

    <template #actions>
      <VBtn
        variant="tonal"
        color="primary"
        prepend-icon="tabler-edit"
        @click="isEditDialogOpen = true"
      >
        {{ $t('Edit Basic Info') }}
      </VBtn>
    </template>

    <!-- ━━━ Linked context ━━━ -->
    <VRow>
      <!-- Catalog product -->
      <VCol
        cols="12"
        sm="6"
        lg="3"
      >
        <div class="context-card">
          <div class="context-card__label">
            <VAvatar
              color="primary"
              variant="tonal"
              size="32"
              rounded
            >
              <VIcon
                icon="tabler-package"
                size="18"
              />
            </VAvatar>
            <span>{{ $t('Linked Catalog Product') }}</span>
          </div>

          <div class="context-card__body">
            <template v-if="linkedCatalogItem">
              <h3 class="text-subtitle-1 font-weight-bold text-break mb-1">
                {{ localizedValue(linkedCatalogItem.name, locale) || linkedCatalogItem.code }}
              </h3>
              <div class="text-caption text-medium-emphasis">
                <span class="font-mono text-primary font-weight-medium">{{ linkedCatalogItem.code }}</span>
                <template v-if="linkedCatalogItem.category">
                  · {{ localizedValue(linkedCatalogItem.category.name, locale) }}
                </template>
              </div>
            </template>
            <span
              v-else
              class="text-body-2 text-disabled"
            >
              {{ $t('No linked catalog product') }}
            </span>
          </div>

          <div
            v-if="linkedCatalogItem"
            class="context-card__foot"
          >
            <RouterLink
              :to="`/catalog/items/edit/${linkedCatalogItem.id}`"
              target="_blank"
              class="text-caption text-primary text-decoration-none d-inline-flex align-center gap-1"
            >
              {{ $t('View in Catalog') }}
              <VIcon
                icon="tabler-external-link"
                size="14"
              />
            </RouterLink>
          </div>
        </div>
      </VCol>

      <!-- Client -->
      <VCol
        cols="12"
        sm="6"
        lg="3"
      >
        <div class="context-card">
          <div class="context-card__label">
            <VAvatar
              color="info"
              variant="tonal"
              size="32"
              rounded
            >
              <VIcon
                icon="tabler-building"
                size="18"
              />
            </VAvatar>
            <span>{{ $t('Client (Owner)') }}</span>
          </div>

          <div class="context-card__body">
            <h3 class="text-subtitle-1 font-weight-bold text-break mb-1">
              {{ clientDisplayName || $t('Unassigned Client') }}
            </h3>
            <span
              v-if="modelData.client?.code"
              class="font-mono text-caption text-info font-weight-medium"
            >
              {{ modelData.client.code }}
            </span>
            <span
              v-else-if="modelData.client_id"
              class="text-caption text-disabled font-mono"
            >
              #{{ modelData.client_id }}
            </span>
          </div>

          <div class="context-card__foot">
            <VIcon
              :icon="canChangeClient ? 'tabler-lock-open' : 'tabler-lock'"
              size="14"
              class="me-1"
            />
            <span class="text-caption text-medium-emphasis">
              {{ canChangeClient ? $t('Editable (No items)') : $t('Locked by physical items') }}
            </span>
          </div>
        </div>
      </VCol>

      <!-- Supplier -->
      <VCol
        cols="12"
        sm="6"
        lg="3"
      >
        <div class="context-card">
          <div class="context-card__label">
            <VAvatar
              color="warning"
              variant="tonal"
              size="32"
              rounded
            >
              <VIcon
                icon="tabler-building-factory-2"
                size="18"
              />
            </VAvatar>
            <span>{{ $t('Preferred Supplier') }}</span>
          </div>

          <div class="context-card__body">
            <h3 class="text-subtitle-1 font-weight-bold text-break mb-1">
              {{ preferredSupplierName || $t('No preferred supplier') }}
            </h3>
            <span
              v-if="linkedCatalogItem?.preferred_supplier?.legal_name"
              class="text-caption text-medium-emphasis d-block text-break"
            >
              {{ $t('Catalog Default:') }} {{ linkedCatalogItem.preferred_supplier.legal_name }}
            </span>
          </div>

          <div class="context-card__foot">
            <span class="text-caption text-medium-emphasis">
              {{ modelData.supplier_id ? $t('Supplier profile active') : $t('Unlinked supplier') }}
            </span>
          </div>
        </div>
      </VCol>

      <!-- Stock -->
      <VCol
        cols="12"
        sm="6"
        lg="3"
      >
        <div class="context-card">
          <div class="context-card__label">
            <VAvatar
              :color="availabilityColor"
              variant="tonal"
              size="32"
              rounded
            >
              <VIcon
                icon="tabler-packages"
                size="18"
              />
            </VAvatar>
            <span>{{ $t('Client Stock Availability') }}</span>
          </div>

          <div class="context-card__body">
            <div class="d-flex align-baseline gap-2 mb-2">
              <span class="text-h5 font-weight-bold">{{ availableItems }}</span>
              <span class="text-body-2 text-medium-emphasis">/ {{ totalItems }} {{ $t('items') }}</span>
              <VChip
                size="x-small"
                :color="availabilityColor"
                variant="tonal"
                class="ms-auto font-mono"
              >
                {{ availabilityPercentage }}% {{ $t('Available') }}
              </VChip>
            </div>
            <VProgressLinear
              :model-value="availabilityPercentage"
              :color="availabilityColor"
              rounded
              height="6"
              :aria-label="$t('Client Stock Availability')"
            />
          </div>

          <div class="context-card__foot justify-space-between">
            <span class="text-caption text-medium-emphasis">
              {{ assignedItems }} {{ $t('assigned') }}
            </span>
            <RouterLink
              :to="{ name: 'client-assets-workwear-list', query: { tab: 'items' } }"
              class="text-caption text-primary text-decoration-none"
            >
              {{ $t('Manage Stock') }}
            </RouterLink>
          </div>
        </div>
      </VCol>
    </VRow>

    <!-- ━━━ Identity strip ━━━ -->
    <VRow
      dense
      class="mt-2"
    >
      <VCol
        cols="12"
        sm="6"
      >
        <div class="identity-tile">
          <VIcon
            icon="tabler-shirt"
            size="22"
            color="primary"
          />
          <div class="min-w-0">
            <span class="text-caption text-medium-emphasis d-block">{{ $t('Model Name') }}</span>
            <span class="text-body-2 font-weight-bold text-break">{{ modelData.name }}</span>
          </div>
        </div>
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <div class="identity-tile">
          <VIcon
            icon="tabler-sewing-machine"
            size="22"
            color="info"
          />
          <div class="min-w-0">
            <span class="text-caption text-medium-emphasis d-block">{{ $t('Technical Fabric') }}</span>
            <span class="text-body-2 font-weight-bold text-break">
              {{ selectedFabricObj?.name || modelData.fabric || $t('Standard Technical Fabric') }}
              <span
                v-if="selectedFabricObj?.weight_gsm"
                class="text-caption text-medium-emphasis font-weight-regular"
              >
                · {{ selectedFabricObj.weight_gsm }} g/m²
              </span>
            </span>
          </div>
        </div>
      </VCol>
    </VRow>

    <!-- ━━━ Edit basic information ━━━ -->
    <WpFormDialog
      v-model="isEditDialogOpen"
      :title="$t('Edit Model Basic Information')"
      :subtitle="$t('Update core model attributes, status, client account, and linked catalog product.')"
      icon="tabler-edit"
      icon-color="primary"
      size="medium"
      :submit-label="$t('Done')"
      :cancel-label="$t('Close')"
      @submit="isEditDialogOpen = false"
      @cancel="isEditDialogOpen = false"
    >
      <VRow>
        <VCol
          cols="12"
          md="8"
        >
          <AppTextField
            v-model="modelData.name"
            :label="$t('Model Name')"
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <AppSelect
            v-model="modelData.status"
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
            v-model="modelData.client_id"
            :items="clientOptions"
            :disabled="!canChangeClient"
            :label="$t('Client Account')"
            :hint="!canChangeClient ? $t('Client cannot be changed when physical items exist.') : ''"
            persistent-hint
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppAutocomplete
            :model-value="modelData.catalog_item_id"
            :items="catalogItemOptions"
            :item-title="formatCatalogLabel"
            item-value="id"
            :loading="loadingLookups"
            :label="$t('Linked Catalog Product (Physical)')"
            :placeholder="$t('Select physical product from Catalog...')"
            :hint="$t('Linking a product imports its options and preferred supplier.')"
            persistent-hint
            clearable
            variant="outlined"
            @update:model-value="handleCatalogItemChange"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppAutocomplete
            v-model="modelData.supplier_id"
            :items="supplierOptions"
            :label="$t('Preferred Manufacturer / Supplier')"
            :hint="modelData.catalog_item?.preferred_supplier?.legal_name ? `${t('Catalog Preferred:')} ${modelData.catalog_item.preferred_supplier.legal_name}` : ''"
            persistent-hint
            clearable
            variant="outlined"
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppAutocomplete
            :model-value="modelData.fabric_id"
            :items="store.workwearFabrics"
            item-title="name"
            item-value="id"
            :label="$t('Technical Workwear Fabric')"
            :placeholder="$t('Select technical fabric...')"
            :hint="selectedFabricObj?.composition ? `${selectedFabricObj.composition}${selectedFabricObj.weight_gsm ? ` (${selectedFabricObj.weight_gsm} g/m²)` : ''}` : ''"
            persistent-hint
            clearable
            variant="outlined"
            @update:model-value="handleFabricChange"
          />
        </VCol>
      </VRow>
    </WpFormDialog>
  </WorkwearSectionCard>
</template>

<style lang="scss" scoped>
.context-card {
  display: flex;
  flex-direction: column;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  block-size: 100%;
  gap: 0.75rem;
  padding: 1rem;

  &__label {
    display: flex;
    align-items: center;
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.8125rem;
    gap: 0.625rem;
  }

  &__body {
    flex: 1;
    min-inline-size: 0;
  }

  &__foot {
    display: flex;
    align-items: center;
    border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    min-block-size: 32px;
    padding-block-start: 0.625rem;
  }
}

.identity-tile {
  display: flex;
  align-items: center;
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  gap: 0.75rem;
  padding: 0.75rem 1rem;
}
</style>
