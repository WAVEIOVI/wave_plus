<script setup>
import { WpStatusCard } from '@/components/shared'
import WpEntityLogo from '@/components/shared/branding/WpEntityLogo.vue'
import MultiLangField from '@/components/shared/data/MultiLangField.vue'
import { localizedValue } from '@/composables/useLocaleField'
import { useCatalogSupportStore } from '@/stores/useCatalogSupportStore'
import { getCatalogItemStatuses } from '@/utils/catalog-constants'
import CatalogBrandSelectModal from '@/views/catalog/shared/CatalogBrandSelectModal.vue'
import CatalogUomSelectModal from '@/views/catalog/shared/CatalogUomSelectModal.vue'
import { requiredValidator } from '@core/utils/validators'
import { computed, ref } from 'vue'

/**
 * Wizard Step 2 — General Info & Identification
 * Handles Item Code, Status, Multilingual Name & Description, Brand (with logos & swiper modal), and UOM (with type icons & modal).
 */

const props = defineProps({
  modelValue: { type: Object, required: true },
  itemType: { type: String, default: '' },
  subType: { type: String, default: '' },
  isEditMode: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const supportStore = useCatalogSupportStore()
const isBrandModalVisible = ref(false)
const isUomModalVisible = ref(false)

const isGeneralReadonly = computed(() => props.readonly)
const isLockedPostCreation = computed(() => props.readonly || props.isEditMode)

const form = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const update = (key, val) => {
  const current = props.modelValue || {}

  current[key] = val
  emit('update:modelValue', current)
}

const statusOptions = getCatalogItemStatuses()

const selectedBrand = computed(() =>
  supportStore.brands.find(b => b.id === form.value.brand_id) || null,
)

const selectedUom = computed(() =>
  supportStore.uom.find(u => u.id === form.value.uom_id) || null,
)

const resolveUomTypeIcon = type => {
  const map = {
    count: 'tabler-number',
    mass: 'tabler-scale',
    volume: 'tabler-droplet',
    length: 'tabler-ruler-measure',
    service: 'tabler-clock',
  }

  return map[type] || 'tabler-ruler'
}

const resolveUomTypeColor = type => {
  const map = {
    count: 'primary',
    mass: 'warning',
    volume: 'info',
    length: 'success',
    service: 'secondary',
  }

  return map[type] || 'primary'
}

const handleBrandSelect = brand => {
  update('brand_id', brand?.id || null)
}

const handleUomSelect = uom => {
  update('uom_id', uom?.id || null)
}
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <div>
      <h5 class="text-h5 mb-1">
        General Information & Identification
      </h5>
      <p class="text-body-2 text-medium-emphasis mb-6">
        Core details used to identify, price, and describe this catalog item across the system.
      </p>

      <VCard
        variant="outlined"
        rounded="lg"
      >
        <VCardTitle class="text-subtitle-1 font-weight-bold d-flex align-center gap-2 py-3 px-4">
          <VIcon
            icon="tabler-id"
            size="20"
          />
          Identification Data
        </VCardTitle>
        <VDivider />
        <VCardText>
          <!-- Item Status -->
          <VRow>
            <VCol
              cols="12"
              md="6"
              class="d-flex flex-column"
            >
              <WpStatusCard
                :model-value="form.status || 'draft'"
                :readonly="isGeneralReadonly"
                title="Item Status"
                @update:model-value="update('status', $event)"
              />
            </VCol>
          </VRow>

          <!-- Item Code & Name -->
          <VRow align="stretch">
            <VCol
              cols="12"
              md="4"
              class="d-flex flex-column justify-center"
            >
              <VTextField
                :model-value="form.code"
                label="Item Code *"
                :disabled="isLockedPostCreation"
                :rules="[requiredValidator]"
                variant="outlined"
                @update:model-value="update('code', $event)"
              />
            </VCol>
            <VCol
              cols="12"
              md="8"
            >
              <MultiLangField
                :model-value="form.name"
                label="Item Name *"
                required
                :rules="[requiredValidator]"
                :readonly="isGeneralReadonly"
                placeholder="e.g. Carbon Dioxide Fire Extinguisher 6kg"
                @update:model-value="update('name', $event)"
              />
            </VCol>
          </VRow>
          <!-- Multilingual Short Description (Point 3.1) -->
          <VRow>
            <VCol cols="12">
              <MultiLangField
                :model-value="form.short_description"
                label="Short Description"
                multiline
                :readonly="isGeneralReadonly"
                placeholder="Brief summary of item capabilities, application and specs..."
                @update:model-value="update('short_description', $event)"
              />
            </VCol>
          </VRow>

          <!-- Brand & Unit of Measure with Dialogs & Swiper (Points 3.3, 3.4, 3.5) -->
          <VRow
            class="mt-2"
            align="stretch"
          >
            <!-- Brand Selector Card -->
            <VCol
              cols="12"
              md="6"
              class="d-flex flex-column"
            >
              <div class="text-body-2 font-weight-medium mb-2 d-flex align-center gap-1">
                <VIcon
                  icon="tabler-brand-firebase"
                  size="16"
                  color="primary"
                />
                Brand
              </div>
              <VCard
                variant="outlined"
                class="brand-selector-card pa-3 flex-grow-1 w-100 d-flex flex-column justify-center"
                :class="{ 'cursor-pointer': !isGeneralReadonly }"
                rounded="lg"
                style="min-block-size: 84px;"
                @click="!isGeneralReadonly ? (isBrandModalVisible = true) : null"
              >
                <div class="d-flex align-center justify-space-between gap-3">
                  <div class="d-flex align-center gap-3 overflow-hidden">
                    <WpEntityLogo
                      :src="selectedBrand?.logo_url"
                      :name="selectedBrand?.name || 'Brand'"
                      :domain="selectedBrand?.logo_domain"
                      :source="selectedBrand?.logo_source"
                      size="44"
                      border-radius="8px"
                      class="flex-shrink-0"
                    />
                    <div class="overflow-hidden">
                      <template v-if="selectedBrand">
                        <div class="text-subtitle-1 font-weight-bold text-truncate">
                          {{ selectedBrand.name }}
                        </div>
                        <div class="text-caption text-disabled font-mono text-truncate">
                          {{ selectedBrand.code }} <span v-if="selectedBrand.country_of_origin">· {{ selectedBrand.country_of_origin }}</span>
                        </div>
                      </template>
                      <template v-else>
                        <div class="text-body-2 text-medium-emphasis">
                          No brand selected
                        </div>
                        <span class="text-caption text-disabled">Click to select brand with logos</span>
                      </template>
                    </div>
                  </div>

                  <div
                    v-if="!isGeneralReadonly"
                    class="d-flex align-center gap-1 flex-shrink-0"
                  >
                    <VBtn
                      v-if="selectedBrand"
                      icon="tabler-x"
                      size="small"
                      variant="text"
                      color="error"
                      @click.stop="handleBrandSelect(null)"
                    />
                    <VBtn
                      size="small"
                      variant="tonal"
                      color="primary"
                      @click.stop="isBrandModalVisible = true"
                    >
                      {{ selectedBrand ? 'Change' : 'Select' }}
                    </VBtn>
                  </div>
                </div>
              </VCard>
            </VCol>

            <!-- Unit of Measure Selector Card -->
            <VCol
              cols="12"
              md="6"
              class="d-flex flex-column"
            >
              <div class="text-body-2 font-weight-medium mb-2 d-flex align-center gap-1">
                <VIcon
                  icon="tabler-ruler-measure"
                  size="16"
                  color="success"
                />
                Unit of Measure *
              </div>
              <VCard
                variant="outlined"
                class="uom-selector-card pa-3 flex-grow-1 w-100 d-flex flex-column justify-center"
                :class="{
                  'cursor-pointer': !isGeneralReadonly,
                  'border-error': !form.uom_id && !isGeneralReadonly,
                }"
                rounded="lg"
                style="min-block-size: 84px;"
                @click="!isGeneralReadonly ? (isUomModalVisible = true) : null"
              >
                <div class="d-flex align-center justify-space-between gap-3">
                  <div class="d-flex align-center gap-3 overflow-hidden">
                    <VAvatar
                      size="44"
                      rounded
                      variant="tonal"
                      :color="selectedUom ? resolveUomTypeColor(selectedUom.type) : 'secondary'"
                      class="flex-shrink-0"
                    >
                      <VIcon
                        :icon="selectedUom ? resolveUomTypeIcon(selectedUom.type) : 'tabler-ruler'"
                        size="22"
                      />
                    </VAvatar>
                    <div class="overflow-hidden">
                      <template v-if="selectedUom">
                        <div class="d-flex align-center gap-2">
                          <span class="font-weight-bold font-mono text-subtitle-1 text-primary">{{ selectedUom.code }}</span>
                          <VChip
                            size="x-small"
                            variant="tonal"
                            :color="resolveUomTypeColor(selectedUom.type)"
                          >
                            {{ selectedUom.type }}
                          </VChip>
                        </div>
                        <div class="text-caption text-medium-emphasis text-truncate">
                          {{ localizedValue(selectedUom.name, 'Untitled') }}
                        </div>
                      </template>
                      <template v-else>
                        <div class="text-body-2 text-medium-emphasis">
                          Select Unit of Measure *
                        </div>
                        <span class="text-caption text-disabled">Click to choose unit with icons</span>
                      </template>
                    </div>
                  </div>

                  <div
                    v-if="!isGeneralReadonly"
                    class="d-flex align-center gap-1 flex-shrink-0"
                  >
                    <VBtn
                      size="small"
                      variant="tonal"
                      color="primary"
                      @click.stop="isUomModalVisible = true"
                    >
                      {{ selectedUom ? 'Change' : 'Select' }}
                    </VBtn>
                  </div>
                </div>
              </VCard>
              <div
                v-if="!form.uom_id && !isGeneralReadonly"
                class="text-caption text-error mt-1"
              >
                Unit of Measure is required.
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </div>

    <!-- 👉 Brand Select Modal Dialog -->
    <CatalogBrandSelectModal
      v-model:is-dialog-visible="isBrandModalVisible"
      :model-value="form.brand_id"
      :brands="supportStore.brands"
      :loading="supportStore.loadingBrands"
      @select="handleBrandSelect"
    />

    <!-- 👉 UOM Select Modal Dialog -->
    <CatalogUomSelectModal
      v-model:is-dialog-visible="isUomModalVisible"
      :model-value="form.uom_id"
      :uom-list="supportStore.uom"
      :loading="supportStore.loadingUom"
      @select="handleUomSelect"
    />
  </div>
</template>

<style lang="scss" scoped>
.brand-selector-card,
.uom-selector-card {
  transition: all 0.2s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.5);
    background: rgba(var(--v-theme-primary), 0.02);
  }
}
</style>
