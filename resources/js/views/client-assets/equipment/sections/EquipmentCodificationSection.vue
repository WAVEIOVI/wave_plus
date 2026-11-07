<script setup>
import { WpSectionCard } from '@/components/shared'
import WpEntityLogo from '@/components/shared/branding/WpEntityLogo.vue'
import { useCatalogSupportStore } from '@/stores/useCatalogSupportStore'
import CatalogBrandSelectModal from '@/views/catalog/shared/CatalogBrandSelectModal.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
  errors: {
    type: Object,
    default: () => ({}),
  },
  loading: {
    type: Boolean,
    default: false,
  },
  isNew: {
    type: Boolean,
    default: false,
  },
  isReadOnly: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'update:field',
  'openQrScanner',
  'openReplaceQr',
  'downloadQr',
])

const { t } = useI18n()
const supportStore = useCatalogSupportStore()
const isBrandModalVisible = ref(false)

onMounted(() => {
  if (!supportStore.brands?.length) {
    supportStore.fetchBrands().catch(() => {})
  }
})

const selectedBrand = computed(() => {
  if (props.form.brand_id) {
    const byId = supportStore.brands.find(b => b.id === props.form.brand_id)
    if (byId) return byId
  }
  if (props.form.brand) {
    const byName = supportStore.brands.find(
      b => (b.name || '').toLowerCase() === String(props.form.brand).toLowerCase(),
    )

    if (byName) return byName
  }

  return props.form.brand ? { name: props.form.brand } : null
})

const handleBrandSelect = brand => {
  emit('update:field', 'brand_id', brand?.id || null)
  emit('update:field', 'brand', brand?.name || '')
}
</script>

<template>
  <WpSectionCard
    icon="tabler-numbers"
    icon-color="primary"
    :title="t('Physical Identification, QR Code & Brand')"
    :subtitle="t('Optical QR code identifier, brand manufacturer, serial numbers, and client asset tags')"
  >
    <!-- Active QR Code / Equipment Code Card (Managed strictly via Replace QR Dialog on saved records) -->
    <div
      v-if="!props.isNew"
      class="qr-code-banner pa-3 rounded-lg border mb-4 d-flex align-center justify-space-between flex-wrap gap-3"
    >
      <div class="d-flex align-center gap-3 min-w-0">
        <VAvatar
          size="42"
          color="primary"
          variant="tonal"
          rounded="lg"
        >
          <VIcon
            icon="tabler-qrcode"
            size="22"
          />
        </VAvatar>
        <div class="min-w-0">
          <div class="d-flex align-center gap-2 flex-wrap">
            <span class="text-caption text-medium-emphasis text-uppercase font-weight-semibold">
              {{ t('Active QR / Equipment Code') }}
            </span>
            <VChip
              size="x-small"
              color="success"
              variant="tonal"
              prepend-icon="tabler-lock"
            >
              {{ t('Protected') }}
            </VChip>
          </div>
          <div class="text-subtitle-1 font-weight-bold font-mono text-primary text-truncate">
            {{ props.form.equipment_code || '—' }}
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ t('Optical QR tag changes are logged via the Replace QR Code workflow.') }}
          </div>
        </div>
      </div>

      <div class="d-flex align-center gap-2 flex-wrap">
        <VBtn
          size="small"
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-download"
          @click="emit('downloadQr')"
        >
          {{ t('Download QR') }}
        </VBtn>
        <VBtn
          v-if="!props.isReadOnly"
          size="small"
          variant="tonal"
          color="warning"
          prepend-icon="tabler-qrcode"
          @click="emit('openReplaceQr')"
        >
          {{ t('Replace QR Code') }}
        </VBtn>
      </div>
    </div>

    <!-- Brand Selector Card -->
    <div class="mb-4">
      <div class="text-body-2 font-weight-medium mb-2 d-flex align-center gap-1">
        <VIcon
          icon="tabler-brand-firebase"
          size="16"
          color="primary"
        />
        {{ t('Manufacturer / Brand') }}
      </div>
      <VCard
        variant="outlined"
        class="brand-selector-card pa-3 flex-grow-1 w-100 d-flex flex-column justify-center"
        :class="{ 'cursor-pointer': !props.isReadOnly }"
        rounded="lg"
        style="min-block-size: 84px;"
        @click="!props.isReadOnly ? (isBrandModalVisible = true) : null"
      >
        <div class="d-flex align-center justify-space-between gap-3">
          <div class="d-flex align-center gap-3 overflow-hidden">
            <WpEntityLogo
              :src="selectedBrand?.logo_url"
              :name="selectedBrand?.name || t('Brand')"
              :domain="selectedBrand?.logo_domain"
              :source="selectedBrand?.logo_source"
              size="44"
              border-radius="8px"
              class="flex-shrink-0"
            />
            <div class="overflow-hidden">
              <template v-if="selectedBrand?.name">
                <div class="text-subtitle-1 font-weight-bold text-truncate">
                  {{ selectedBrand.name }}
                </div>
                <div class="text-caption text-disabled font-mono text-truncate">
                  {{ selectedBrand.code || t('Registered Brand') }}
                  <span v-if="selectedBrand.country_of_origin">· {{ selectedBrand.country_of_origin }}</span>
                </div>
              </template>
              <template v-else>
                <div class="text-body-2 text-medium-emphasis">
                  {{ t('No brand selected') }}
                </div>
                <span class="text-caption text-disabled">{{ t('Click to select brand with logos') }}</span>
              </template>
            </div>
          </div>

          <div
            v-if="!props.isReadOnly"
            class="d-flex align-center gap-1 flex-shrink-0"
          >
            <VBtn
              v-if="selectedBrand?.name"
              icon="tabler-x"
              size="small"
              variant="text"
              color="error"
              :aria-label="t('Clear Brand')"
              @click.stop="handleBrandSelect(null)"
            />
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              @click.stop="isBrandModalVisible = true"
            >
              {{ selectedBrand?.name ? t('Change') : t('Select') }}
            </VBtn>
          </div>
        </div>
      </VCard>
    </div>

    <!-- Identification Inputs -->
    <VRow class="mb-1">
      <VCol
        cols="12"
        md="6"
      >
        <AppTextField
          :model-value="props.form.model_name"
          :label="t('Model / Designation')"
          :placeholder="t('Equipment model name or designation')"
          prepend-inner-icon="tabler-tag"
          :readonly="props.isReadOnly"
          clearable
          @update:model-value="v => emit('update:field', 'model_name', v)"
        />
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <AppTextField
          :model-value="props.form.serial_number"
          :label="t('Serial Number')"
          :placeholder="t('Manufacturer serial number')"
          prepend-inner-icon="tabler-numbers"
          :readonly="props.isReadOnly"
          clearable
          @update:model-value="v => emit('update:field', 'serial_number', v)"
        />
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <AppTextField
          :model-value="props.form.manufacturer_part_number"
          :label="t('Part Number (MPN)')"
          :placeholder="t('Manufacturer Part #')"
          prepend-inner-icon="tabler-cpu"
          :readonly="props.isReadOnly"
          clearable
          @update:model-value="v => emit('update:field', 'manufacturer_part_number', v)"
        />
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <AppTextField
          :model-value="props.form.internal_number"
          :label="t('Internal ERP Reference')"
          :placeholder="t('Internal ID')"
          prepend-inner-icon="tabler-hash"
          :readonly="props.isReadOnly"
          clearable
          @update:model-value="v => emit('update:field', 'internal_number', v)"
        />
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <AppTextField
          :model-value="props.form.asset_tag"
          :label="t('Client Asset Tag')"
          :placeholder="t('Client barcode/tag #')"
          prepend-inner-icon="tabler-tag-starred"
          :readonly="props.isReadOnly"
          clearable
          @update:model-value="v => emit('update:field', 'asset_tag', v)"
        />
      </VCol>
    </VRow>

    <!-- Brand Select Modal -->
    <CatalogBrandSelectModal
      v-model:is-dialog-visible="isBrandModalVisible"
      :model-value="props.form.brand_id"
      :brands="supportStore.brands"
      :loading="supportStore.loadingBrands"
      @select="handleBrandSelect"
    />
  </WpSectionCard>
</template>

<style lang="scss" scoped>
.qr-code-banner {
  background: rgba(var(--v-theme-primary), 0.035);
  border-color: rgba(var(--v-theme-primary), 0.2) !important;
}

.font-mono {
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
}

.brand-selector-card {
  transition: all 0.2s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.5);
    background: rgba(var(--v-theme-primary), 0.02);
  }
}
</style>
