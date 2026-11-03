<script setup>
import WpEntityLogo from '@/components/shared/branding/WpEntityLogo.vue'
import { computed, ref, watch } from 'vue'
import 'swiper/css'
import { register } from 'swiper/element/bundle'

register()

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  modelValue: { type: [String, Number], default: null },
  brands: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'update:modelValue',
  'select',
])

const search = ref('')
const selectedId = ref(props.modelValue)

watch(() => props.isDialogVisible, visible => {
  if (visible) {
    selectedId.value = props.modelValue
    search.value = ''
  }
})

watch(() => props.modelValue, val => {
  selectedId.value = val
})

const filteredBrands = computed(() => {
  if (!search.value.trim()) return props.brands

  const q = search.value.toLowerCase().trim()

  return props.brands.filter(b =>
    (b.name && b.name.toLowerCase().includes(q)) ||
    (b.code && b.code.toLowerCase().includes(q)) ||
    (b.country_of_origin && b.country_of_origin.toLowerCase().includes(q)),
  )
})

const selectBrand = brand => {
  selectedId.value = brand.id
  emit('update:modelValue', brand.id)
  emit('select', brand)
  emit('update:isDialogVisible', false)
}

const clearBrand = () => {
  selectedId.value = null
  emit('update:modelValue', null)
  emit('select', null)
  emit('update:isDialogVisible', false)
}

const closeDialog = () => {
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="720"
    scrollable
    @update:model-value="val => { if (!val) closeDialog() }"
  >
    <VCard class="brand-select-modal rounded-lg">
      <VCardTitle class="pa-5 d-flex align-center justify-space-between border-b">
        <div class="d-flex align-center gap-2">
          <VAvatar
            color="primary"
            variant="tonal"
            size="36"
            rounded
          >
            <VIcon
              icon="tabler-brand-firebase"
              size="20"
            />
          </VAvatar>
          <div>
            <h6 class="text-h6 font-weight-bold">
              Select Brand
            </h6>
            <span class="text-caption text-disabled">Choose from registered catalog brands</span>
          </div>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="closeDialog"
        />
      </VCardTitle>

      <div class="pa-4 bg-surface border-b">
        <AppTextField
          v-model="search"
          placeholder="Search brand by name, code or origin..."
          prepend-inner-icon="tabler-search"
          density="comfortable"
          variant="outlined"
          clearable
          hide-details
        />
      </div>

      <VCardText class="pa-5">
        <!-- Loading -->
        <div
          v-if="loading"
          class="d-flex justify-center align-center py-10"
        >
          <VProgressCircular
            indeterminate
            color="primary"
            size="40"
          />
        </div>

        <!-- Empty -->
        <div
          v-else-if="filteredBrands.length === 0"
          class="text-center py-10 text-disabled"
        >
          <VIcon
            icon="tabler-brand-off"
            size="48"
            class="mb-2 opacity-50"
          />
          <div class="text-body-1 font-weight-medium">
            No brands found
          </div>
          <span class="text-caption">Try adjusting your search query</span>
        </div>

        <div v-else>
          <!-- 👉 Swiper Carousel for quick visual logo picking -->
          <div class="mb-4">
            <div class="text-caption text-disabled text-uppercase font-weight-bold mb-2">
              Featured Brands Carousel
            </div>
            <swiper-container
              slides-per-view="auto"
              space-between="12"
              navigation
              class="brand-swiper py-2"
            >
              <swiper-slide
                v-for="b in filteredBrands"
                :key="`slide-${b.id}`"
                style="inline-size: 150px;"
              >
                <VCard
                  variant="outlined"
                  class="brand-card pa-3 text-center cursor-pointer h-100"
                  :class="{
                    'brand-card--selected': selectedId === b.id,
                  }"
                  @click="selectBrand(b)"
                >
                  <div class="d-flex justify-center mb-2">
                    <WpEntityLogo
                      :src="b.logo_url"
                      :name="b.name"
                      :domain="b.logo_domain"
                      :source="b.logo_source"
                      size="52"
                      border-radius="10px"
                    />
                  </div>
                  <div
                    class="font-weight-bold text-truncate text-body-2 mb-1"
                    :title="b.name"
                  >
                    {{ b.name }}
                  </div>
                  <div class="d-flex align-center justify-center gap-1">
                    <VChip
                      v-if="b.country_of_origin"
                      size="x-small"
                      variant="tonal"
                      color="secondary"
                    >
                      {{ b.country_of_origin }}
                    </VChip>
                    <span class="text-caption text-disabled font-mono">{{ b.code }}</span>
                  </div>
                  <VIcon
                    v-if="selectedId === b.id"
                    icon="tabler-circle-check-filled"
                    color="primary"
                    size="18"
                    class="brand-card__check"
                  />
                </VCard>
              </swiper-slide>
            </swiper-container>
          </div>

          <VDivider class="my-4" />

          <!-- 👉 Grid list for all brands -->
          <div class="text-caption text-disabled text-uppercase font-weight-bold mb-2">
            All Brands ({{ filteredBrands.length }})
          </div>
          <VRow dense>
            <VCol
              v-for="b in filteredBrands"
              :key="`grid-${b.id}`"
              cols="12"
              sm="6"
              md="4"
            >
              <VCard
                variant="outlined"
                class="brand-grid-card pa-3 cursor-pointer d-flex align-center gap-3"
                :class="{ 'brand-grid-card--selected': selectedId === b.id }"
                @click="selectBrand(b)"
              >
                <WpEntityLogo
                  :src="b.logo_url"
                  :name="b.name"
                  :domain="b.logo_domain"
                  :source="b.logo_source"
                  size="40"
                  border-radius="8px"
                />
                <div class="flex-grow-1 overflow-hidden">
                  <div class="font-weight-medium text-body-2 text-truncate">
                    {{ b.name }}
                  </div>
                  <div class="text-caption text-disabled font-mono">
                    {{ b.code }} <span v-if="b.country_of_origin">· {{ b.country_of_origin }}</span>
                  </div>
                </div>
                <VIcon
                  v-if="selectedId === b.id"
                  icon="tabler-check"
                  color="primary"
                  size="20"
                />
              </VCard>
            </VCol>
          </VRow>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 justify-space-between">
        <VBtn
          v-if="selectedId"
          color="error"
          variant="tonal"
          size="small"
          prepend-icon="tabler-x"
          @click="clearBrand"
        >
          Clear Brand
        </VBtn>
        <span v-else />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="closeDialog"
        >
          Close
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.brand-card {
  position: relative;
  transition: all 0.2s ease;
  border-radius: 12px;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.5);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  }

  &--selected {
    border-color: rgb(var(--v-theme-primary)) !important;
    background: rgba(var(--v-theme-primary), 0.05);
  }

  &__check {
    position: absolute;
    inset-block-start: 8px;
    inset-inline-end: 8px;
  }
}

.brand-grid-card {
  border-radius: 10px;
  transition: all 0.15s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.5);
  }

  &--selected {
    border-color: rgb(var(--v-theme-primary)) !important;
    background: rgba(var(--v-theme-primary), 0.06);
  }
}
</style>
