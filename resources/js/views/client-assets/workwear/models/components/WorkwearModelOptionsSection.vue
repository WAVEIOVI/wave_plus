<script setup>
import { WpSectionCard } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import CatalogZoomDialog from '@/views/catalog/shared/CatalogZoomDialog.vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelData: { type: Object, default: () => ({}) },
  options: { type: Array, default: () => [] },
})

const { locale } = useI18n()
const isVariantsZoomOpen = ref(false)

const catalogItem = computed(() => props.modelData?.catalog_item || null)
const catalogOptions = computed(() => catalogItem.value?.options || [])
const catalogVariants = computed(() => catalogItem.value?.variants || [])

// Options live on the catalog product; linking happens in the overview section.
const goToOverview = () => {
  document.getElementById('section-overview')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
</script>

<template>
  <section
    id="section-options"
    class="journal-section mb-6"
  >
    <WpSectionCard
      icon="tabler-adjustments"
      :title="$t('Catalog Options')"
      :subtitle="$t('Options and available values imported from the linked Physical Catalog Product.')"
    >
      <template #actions>
        <VChip
          v-if="catalogItem?.code"
          size="small"
          color="primary"
          variant="tonal"
          class="font-mono font-weight-bold"
        >
          {{ catalogItem.code }}
        </VChip>
        <VBtn
          v-if="catalogItem"
          color="primary"
          variant="tonal"
          prepend-icon="tabler-arrows-maximize"
          @click="isVariantsZoomOpen = true"
        >
          {{ $t('View Catalog Variants') }} ({{ catalogVariants.length }})
        </VBtn>
      </template>

      <!-- Linked product -->
      <div
        v-if="catalogItem"
        class="linked-product mb-4"
      >
        <VAvatar
          size="40"
          color="primary"
          variant="tonal"
          rounded
        >
          <VIcon
            icon="tabler-package"
            size="22"
          />
        </VAvatar>
        <div class="min-w-0 flex-grow-1">
          <div class="text-subtitle-2 font-weight-bold text-break">
            {{ localizedValue(catalogItem.name, locale) }}
          </div>
          <div class="text-caption text-medium-emphasis">
            <span>{{ catalogItem.code }}</span>
            <span v-if="catalogItem.category"> · {{ localizedValue(catalogItem.category.name, locale) }}</span>
            <span v-if="catalogItem.preferred_supplier?.legal_name">
              · {{ $t('Preferred Supplier:') }} {{ catalogItem.preferred_supplier.legal_name }}
            </span>
          </div>
        </div>
        <div class="d-flex gap-2">
          <VChip
            size="small"
            color="info"
            variant="tonal"
          >
            {{ catalogOptions.length }} {{ $t('Options') }}
          </VChip>
          <VChip
            size="small"
            color="primary"
            variant="tonal"
            @click="isVariantsZoomOpen = true"
          >
            {{ catalogVariants.length }} {{ $t('Variants') }}
          </VChip>
        </div>
      </div>

      <!-- Catalog options -->
      <div
        v-if="catalogOptions.length"
        class="option-grid"
      >
        <div
          v-for="opt in catalogOptions"
          :key="opt.id"
          class="option-card"
        >
          <div class="d-flex align-center justify-space-between gap-2 mb-3">
            <span class="text-subtitle-2 font-weight-bold text-break">
              {{ localizedValue(opt.name, locale) }}
            </span>
            <VChip
              size="x-small"
              color="secondary"
              variant="tonal"
            >
              {{ (opt.values || []).length }} {{ $t('values') }}
            </VChip>
          </div>

          <div class="d-flex flex-wrap gap-2">
            <VChip
              v-for="val in (opt.values || [])"
              :key="val.id"
              size="small"
              color="primary"
              variant="tonal"
            >
              {{ localizedValue(val.value, locale) }}
            </VChip>
            <span
              v-if="!opt.values?.length"
              class="text-body-2 text-disabled"
            >
              {{ $t('No values defined on catalog option.') }}
            </span>
          </div>
        </div>
      </div>

      <!-- Legacy model options (no catalog options available) -->
      <div
        v-else-if="options.length"
        class="option-grid"
      >
        <div
          v-for="opt in options"
          :key="opt.id"
          class="option-card"
        >
          <div class="d-flex align-center justify-space-between gap-2 mb-3">
            <span class="text-subtitle-2 font-weight-bold text-break">{{ opt.name }}</span>
            <VChip
              size="x-small"
              color="secondary"
              variant="tonal"
            >
              {{ (opt.values || []).length }} {{ $t('values') }}
            </VChip>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <VChip
              v-for="val in (opt.values || [])"
              :key="val.id"
              size="small"
              color="primary"
              variant="tonal"
            >
              {{ val.value }}
            </VChip>
          </div>
        </div>
      </div>

      <!-- Empty -->
      <div
        v-else
        class="empty-state"
      >
        <VIcon
          icon="tabler-package-off"
          size="36"
          class="text-disabled"
        />
        <p class="text-body-1 mb-1">
          {{ catalogItem ? $t('The linked catalog product has no options configured.') : $t('No Physical Catalog Product linked to this model yet.') }}
        </p>
        <p class="text-body-2 text-medium-emphasis mb-3">
          {{ $t('Link a physical product in General Information above to import its options and variants.') }}
        </p>
        <VBtn
          v-if="!catalogItem"
          variant="tonal"
          color="primary"
          prepend-icon="tabler-link"
          @click="goToOverview"
        >
          {{ $t('Overview & Assets') }}
        </VBtn>
      </div>

      <CatalogZoomDialog
        v-model="isVariantsZoomOpen"
        :variants="catalogVariants"
        :item-code="catalogItem?.code || ''"
      />
    </WpSectionCard>
  </section>
</template>

<style lang="scss" scoped>
.linked-product {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
}

.option-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr));
}

.option-card {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  padding: 1rem;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  border: 1px dashed rgba(var(--v-border-color), 0.4);
  border-radius: 10px;
  padding: 2rem 1rem;
  text-align: center;
}
</style>
