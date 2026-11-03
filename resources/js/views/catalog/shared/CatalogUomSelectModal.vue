<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { computed, ref, watch } from 'vue'
import 'swiper/css'
import { register } from 'swiper/element/bundle'

register()

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  modelValue: { type: [String, Number], default: null },
  uomList: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'update:modelValue',
  'select',
])

const search = ref('')
const selectedType = ref('all')
const selectedId = ref(props.modelValue)

const typeCategories = [
  { value: 'all', title: 'All', icon: 'tabler-layout-grid' },
  { value: 'count', title: 'Count', icon: 'tabler-number' },
  { value: 'mass', title: 'Mass', icon: 'tabler-scale' },
  { value: 'volume', title: 'Volume', icon: 'tabler-droplet' },
  { value: 'length', title: 'Length', icon: 'tabler-ruler-measure' },
  { value: 'service', title: 'Service', icon: 'tabler-clock' },
]

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

watch(() => props.isDialogVisible, visible => {
  if (visible) {
    selectedId.value = props.modelValue
    search.value = ''
    selectedType.value = 'all'
  }
})

watch(() => props.modelValue, val => {
  selectedId.value = val
})

const filteredUom = computed(() => {
  return props.uomList.filter(u => {
    if (selectedType.value !== 'all' && u.type !== selectedType.value) {
      return false
    }

    if (search.value.trim()) {
      const q = search.value.toLowerCase().trim()
      const name = localizedValue(u.name, '').toLowerCase()
      const code = (u.code || '').toLowerCase()

      if (!name.includes(q) && !code.includes(q)) {
        return false
      }
    }

    return true
  })
})

const selectUom = uom => {
  selectedId.value = uom.id
  emit('update:modelValue', uom.id)
  emit('select', uom)
  emit('update:isDialogVisible', false)
}

const clearUom = () => {
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
    <VCard class="uom-select-modal rounded-lg">
      <VCardTitle class="pa-5 d-flex align-center justify-space-between border-b">
        <div class="d-flex align-center gap-2">
          <VAvatar
            color="success"
            variant="tonal"
            size="36"
            rounded
          >
            <VIcon
              icon="tabler-ruler-measure"
              size="20"
            />
          </VAvatar>
          <div>
            <h6 class="text-h6 font-weight-bold">
              Select Unit of Measure
            </h6>
            <span class="text-caption text-disabled">Choose standard units for inventory and pricing</span>
          </div>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="closeDialog"
        />
      </VCardTitle>

      <div class="pa-4 bg-surface border-b d-flex flex-column gap-3">
        <AppTextField
          v-model="search"
          placeholder="Search UOM by name or code (e.g. kg, L, pcs)..."
          prepend-inner-icon="tabler-search"
          density="comfortable"
          variant="outlined"
          clearable
          hide-details
        />

        <!-- Category Filters -->
        <div class="d-flex gap-2 flex-wrap">
          <VChip
            v-for="cat in typeCategories"
            :key="cat.value"
            size="small"
            :variant="selectedType === cat.value ? 'flat' : 'tonal'"
            :color="selectedType === cat.value ? 'primary' : 'default'"
            :prepend-icon="cat.icon"
            class="cursor-pointer"
            @click="selectedType = cat.value"
          >
            {{ cat.title }}
          </VChip>
        </div>
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
          v-else-if="filteredUom.length === 0"
          class="text-center py-10 text-disabled"
        >
          <VIcon
            icon="tabler-ruler-off"
            size="48"
            class="mb-2 opacity-50"
          />
          <div class="text-body-1 font-weight-medium">
            No units of measure found
          </div>
          <span class="text-caption">Try adjusting search or category filter</span>
        </div>

        <div v-else>
          <!-- 👉 Swiper Carousel for fast visual selection -->
          <div class="mb-4">
            <div class="text-caption text-disabled text-uppercase font-weight-bold mb-2">
              Units Carousel
            </div>
            <swiper-container
              slides-per-view="auto"
              space-between="12"
              navigation
              class="uom-swiper py-2"
            >
              <swiper-slide
                v-for="u in filteredUom"
                :key="`slide-${u.id}`"
                style="inline-size: 150px;"
              >
                <VCard
                  variant="outlined"
                  class="uom-card pa-3 text-center cursor-pointer h-100"
                  :class="{
                    'uom-card--selected': selectedId === u.id,
                  }"
                  @click="selectUom(u)"
                >
                  <div class="d-flex justify-center mb-2">
                    <VAvatar
                      size="48"
                      rounded
                      variant="tonal"
                      :color="resolveUomTypeColor(u.type)"
                    >
                      <VIcon
                        :icon="resolveUomTypeIcon(u.type)"
                        size="24"
                      />
                    </VAvatar>
                  </div>
                  <div class="font-weight-bold font-mono text-subtitle-1 mb-1 text-primary">
                    {{ u.code }}
                  </div>
                  <div
                    class="text-body-2 font-weight-medium text-truncate mb-1"
                    :title="localizedValue(u.name)"
                  >
                    {{ localizedValue(u.name, 'Untitled') }}
                  </div>
                  <VChip
                    size="x-small"
                    variant="tonal"
                    :color="resolveUomTypeColor(u.type)"
                  >
                    {{ u.type }}
                  </VChip>
                  <VIcon
                    v-if="selectedId === u.id"
                    icon="tabler-circle-check-filled"
                    color="primary"
                    size="18"
                    class="uom-card__check"
                  />
                </VCard>
              </swiper-slide>
            </swiper-container>
          </div>

          <VDivider class="my-4" />

          <!-- 👉 Grid View for all units -->
          <div class="text-caption text-disabled text-uppercase font-weight-bold mb-2">
            All Units ({{ filteredUom.length }})
          </div>
          <VRow dense>
            <VCol
              v-for="u in filteredUom"
              :key="`grid-${u.id}`"
              cols="12"
              sm="6"
              md="4"
            >
              <VCard
                variant="outlined"
                class="uom-grid-card pa-3 cursor-pointer d-flex align-center gap-3"
                :class="{ 'uom-grid-card--selected': selectedId === u.id }"
                @click="selectUom(u)"
              >
                <VAvatar
                  size="38"
                  rounded
                  variant="tonal"
                  :color="resolveUomTypeColor(u.type)"
                >
                  <VIcon
                    :icon="resolveUomTypeIcon(u.type)"
                    size="20"
                  />
                </VAvatar>
                <div class="flex-grow-1 overflow-hidden">
                  <div class="d-flex align-center justify-space-between gap-1">
                    <span class="font-weight-bold font-mono text-body-2 text-primary">{{ u.code }}</span>
                    <VChip
                      size="x-small"
                      variant="tonal"
                      :color="resolveUomTypeColor(u.type)"
                    >
                      {{ u.type }}
                    </VChip>
                  </div>
                  <div class="text-caption text-medium-emphasis text-truncate">
                    {{ localizedValue(u.name, 'Untitled') }}
                  </div>
                </div>
                <VIcon
                  v-if="selectedId === u.id"
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
          @click="clearUom"
        >
          Clear Unit
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
.uom-card {
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

.uom-grid-card {
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
