<script setup>
import WpPartnerCard from '@/components/shared/cards/WpPartnerCard.vue'
import { useSupplierStore } from '@/stores/useSupplierStore'
import { getTaxClasses } from '@/utils/catalog-constants'
import { computed } from 'vue'

/**
 * Wizard Step 4 — Commerce, SLA & Compliance
 * Handles pricing, SLA rules, stockable/sellable toggles, inventory defaults, and preferred supplier.
 * Note: Linked suppliers list is removed here; it will be populated automatically via Supply Chain module.
 */

const props = defineProps({
  modelValue: { type: Object, required: true },
  supplierOptions: { type: Array, default: () => [] },
  itemType: { type: String, default: '' },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const supplierStore = useSupplierStore()

const form = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const update = (key, val) => {
  const current = props.modelValue || {}

  current[key] = val
  emit('update:modelValue', current)
}

const taxClassOptions = getTaxClasses()

// Determine if this item type/sub-type supports inventory tracking
const showInventory = computed(() => {
  // eq_servicing items are services — no stock
  if (props.itemType === 'eq_servicing') return false

  // Product sub-types 'service' and 'digital' don't carry physical stock
  if (props.itemType === 'product') {
    const subType = form.value.product?.product_type
    if (subType === 'service' || subType === 'digital') return false
  }

  return true
})

// Preferred Supplier resolution via store
const selectedSupplier = computed(() =>
  supplierStore.suppliers.find(s => s.id === form.value.preferred_supplier_id) || null,
)

const supplierPickerConfig = computed(() => ({
  title: 'Select Preferred Supplier',
  entityLabel: 'Supplier',
  icon: 'tabler-truck-delivery',
  fetcher: async ({ page, perPage, search }) => {
    await supplierStore.fetchSuppliers({
      page,
      itemsPerPage: perPage,
      search,
    })

    return {
      items: supplierStore.suppliers,
      total: supplierStore.totalSuppliers,
    }
  },
  columns: [
    { key: 'company_name', title: 'Supplier Name', sortable: true },
    { key: 'supplier_id', title: 'Code', sortable: true },
    { key: 'primary_email', title: 'Email' },
    { key: 'primary_phone', title: 'Phone' },
  ],
  display: {
    title: item => item.company_name || item.commercial_name || `Supplier #${item.id}`,
    subtitle: item => item.supplier_id || item.primary_email,
    avatar: item => item.logo_url,
    chips: [item => ({ label: item.status, color: item.status === 'active' ? 'success' : 'secondary' })],
  },
}))
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <div>
      <h5 class="text-h5 mb-1">
        Commerce, SLA & Compliance
      </h5>
      <p class="text-body-2 text-medium-emphasis mb-6">
        Configure pricing, metadata tagging, SLA terms, and supplier relationships.
      </p>

      <!-- COMMERCE & PRICING (Price Range as clean display stat card, not input form) -->
      <VCard
        variant="outlined"
        rounded="lg"
        class="mb-6"
      >
        <VCardTitle class="text-subtitle-1 font-weight-bold d-flex align-center gap-2 py-3 px-4">
          <VIcon
            icon="tabler-coin"
            size="20"
            color="success"
          /> Commerce & Pricing
        </VCardTitle>
        <VDivider />
        <VCardText>
          <VRow>
            <!-- Price Range: Clean read-only stat tile (Point 3.5) -->
            <VCol
              cols="12"
              md="6"
            >
              <div class="pa-4 rounded-lg border bg-surface d-flex align-center gap-3">
                <VAvatar
                  size="48"
                  rounded
                  color="success"
                  variant="tonal"
                >
                  <VIcon
                    icon="tabler-currency-dollar"
                    size="26"
                  />
                </VAvatar>
                <div>
                  <div class="text-caption text-disabled text-uppercase font-weight-bold">
                    Price Range (Calculated from Variants)
                  </div>
                  <div class="text-h6 font-weight-bold text-success">
                    {{ form.price_range || '0.000' }} <span class="text-body-2 font-weight-medium">TND</span>
                  </div>
                </div>
              </div>
            </VCol>
          </VRow>

          <VDivider class="my-4" />

          <!-- Switches -->
          <VRow dense>
            <VCol
              cols="12"
              sm="4"
            >
              <VSwitch
                :model-value="form.is_stockable"
                label="Stockable Item"
                density="compact"
                color="primary"
                :readonly="readonly"
                @update:model-value="update('is_stockable', $event)"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <VSwitch
                :model-value="form.is_purchasable"
                label="Purchasable (Procurement)"
                density="compact"
                color="info"
                :readonly="readonly"
                @update:model-value="update('is_purchasable', $event)"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <VSwitch
                :model-value="form.is_sellable"
                label="Sellable (Sales & Invoicing)"
                density="compact"
                color="success"
                :readonly="readonly"
                @update:model-value="update('is_sellable', $event)"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- INVENTORY DEFAULTS (hidden for non-stockable types; displayed as clean stat tile, Point 3.5) -->
      <VCard
        v-if="showInventory"
        variant="outlined"
        rounded="lg"
        class="mb-6"
      >
        <VCardTitle class="text-subtitle-1 font-weight-bold d-flex align-center gap-2 py-3 px-4">
          <VIcon
            icon="tabler-package"
            size="20"
            color="warning"
          /> Inventory Defaults
        </VCardTitle>
        <VDivider />
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <div class="pa-4 rounded-lg border bg-surface d-flex align-center gap-3">
                <VAvatar
                  size="48"
                  rounded
                  color="warning"
                  variant="tonal"
                >
                  <VIcon
                    icon="tabler-packages"
                    size="26"
                  />
                </VAvatar>
                <div>
                  <div class="text-caption text-disabled text-uppercase font-weight-bold">
                    Aggregated Stock (All Variants)
                  </div>
                  <div class="text-h6 font-weight-bold text-warning">
                    {{ form.current_stock || '0' }} <span class="text-body-2 font-weight-medium">units</span>
                  </div>
                </div>
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- META: TAGS & CERTIFICATIONS -->
      <VCard
        variant="outlined"
        rounded="lg"
        class="mb-6"
      >
        <VCardTitle class="text-subtitle-1 font-weight-bold d-flex align-center gap-2 py-3 px-4">
          <VIcon
            icon="tabler-tags"
            size="20"
            color="primary"
          /> Metadata & Classification
        </VCardTitle>
        <VDivider />
        <VCardText>
          <VRow dense>
            <VCol
              cols="12"
              md="6"
            >
              <VCombobox
                :model-value="form.tags || []"
                label="Tags"
                placeholder="Type and press Enter to add tags..."
                variant="outlined"
                multiple
                chips
                :closable-chips="!readonly"
                :readonly="readonly"
                @update:model-value="update('tags', $event)"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VCombobox
                :model-value="form.certifications || []"
                label="Certifications"
                placeholder="Type and press Enter to add certifications..."
                variant="outlined"
                multiple
                chips
                :closable-chips="!readonly"
                :readonly="readonly"
                @update:model-value="update('certifications', $event)"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- LOGISTICS & PREFERRED SUPPLIER (WpPartnerCard + Search Select Dialog, Point 3.5) -->
      <VCard
        variant="outlined"
        rounded="lg"
      >
        <VCardTitle class="text-subtitle-1 font-weight-bold d-flex align-center gap-2 py-3 px-4">
          <VIcon
            icon="tabler-truck-delivery"
            size="20"
            color="info"
          /> Logistics & Supplier
        </VCardTitle>
        <VDivider />
        <VCardText>
          <VRow class="mb-4">
            <!-- Preferred Supplier Partner Card -->
            <VCol
              cols="12"
              md="7"
            >
              <WpPartnerCard
                :partner="selectedSupplier"
                partner-label="Preferred Supplier"
                :fields="{
                  name: 'company_name',
                  tradeName: 'commercial_name',
                  code: 'supplier_id',
                  category: 'supplier_category',
                  logo: 'logo_url',
                  logoSource: 'logo_source',
                  logoDomain: 'logo_domain',
                }"
                :details="[
                  { label: 'Tax ID', value: 'tax_id', icon: 'tabler-id' },
                ]"
                :search-config="supplierPickerConfig"
                status-type="supplier"
                :readonly="readonly"
                clearable
                @change="s => update('preferred_supplier_id', s?.id || null)"
                @clear="update('preferred_supplier_id', null)"
              />
            </VCol>

            <!-- Warranty & Lead Settings -->
            <VCol
              cols="12"
              md="5"
            >
              <VCard
                variant="flat"
                border
                class="pa-4 rounded-lg h-100 bg-surface"
              >
                <div class="text-subtitle-2 font-weight-bold mb-3 d-flex align-center gap-2">
                  <VIcon
                    icon="tabler-shield-check"
                    size="18"
                    color="primary"
                  />
                  SLA & Warranty Terms
                </div>
                <AppTextField
                  :model-value="form.warranty_months"
                  label="Warranty Duration (Months)"
                  type="number"
                  variant="outlined"
                  density="comfortable"
                  placeholder="e.g. 24"
                  class="mb-3"
                  :readonly="readonly"
                  @update:model-value="update('warranty_months', $event)"
                />
                <span class="text-caption text-disabled">Standard warranty period covered by the manufacturer or preferred partner.</span>
              </VCard>
            </VCol>
          </VRow>

          <VRow>
            <VCol cols="12">
              <AppTextarea
                :model-value="form.internal_notes"
                label="Internal Logistics & Compliance Notes"
                placeholder="Special handling instructions, hazardous storage notes or compliance requirements..."
                rows="3"
                variant="outlined"
                :readonly="readonly"
                @update:model-value="update('internal_notes', $event)"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </div>
  </div>
</template>
