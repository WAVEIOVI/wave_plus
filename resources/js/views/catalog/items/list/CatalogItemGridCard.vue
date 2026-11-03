<script setup>
/* eslint-disable camelcase */
import { localizedValue } from '@/composables/useLocaleField'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import { Navigation, Pagination } from 'swiper/modules'
import { Swiper, SwiperSlide } from 'swiper/vue'
import { computed } from 'vue'

const props = defineProps({
  item: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['view', 'edit', 'exportPdf', 'sendEmail', 'delete'])

const resolveTypeVariant = type => {
  const map = {
    product: 'info',
    consumable: 'warning',
    eq_servicing: 'secondary',
  }

  return map[type?.toLowerCase()] || 'primary'
}

const resolveStatusVariant = stat => {
  const map = {
    active: 'success',
    draft: 'warning',
    discontinued: 'error',
  }

  return map[stat?.toLowerCase()] || 'primary'
}

const getStockColor = stock => {
  if (stock === null || stock === undefined) return 'grey'
  if (stock === 0) return 'error'
  if (stock > 0 && stock <= 10) return 'warning'
  
  return 'success'
}

const images = computed(() => {
  if (props.item.media_images?.length) return props.item.media_images
  if (props.item.image_urls?.length) return props.item.image_urls

  return []
})

const getImageUrl = img => {
  if (!img) return ''
  if (typeof img === 'string') return img
  
  return img.url || img.original_url || img.thumbnail_url || ''
}
</script>

<template>
  <VCard class="h-100 d-flex flex-column item-grid-card elevation-1">
    <!-- Image Header Section -->
    <div class="card-image-wrapper">
      <div
        class="card-badges d-flex justify-space-between position-absolute w-100 pa-2"
        style="z-index: 5; inset-block-start: 0;"
      >
        <VChip
          :color="resolveStatusVariant(item.status)"
          size="x-small"
          class="text-capitalize font-weight-bold shadow-sm"
          variant="elevated"
        >
          {{ item.status }}
        </VChip>
      </div>

      <!-- Multiple Images Swiper -->
      <template v-if="images.length > 1">
        <Swiper
          :modules="[Navigation, Pagination]"
          navigation
          pagination
          class="swiper-container"
        >
          <SwiperSlide
            v-for="(img, index) in images"
            :key="index"
          >
            <VImg
              :src="getImageUrl(img)"
              cover
              height="160"
              class="card-image-content"
            >
              <template #placeholder>
                <div class="d-flex align-center justify-center fill-height">
                  <VProgressCircular
                    indeterminate
                    color="primary"
                    size="24"
                  />
                </div>
              </template>
              <template #error>
                <div class="d-flex align-center justify-center fill-height text-center text-disabled">
                  <VIcon
                    icon="tabler-photo-off"
                    size="32"
                    class="opacity-50"
                  />
                </div>
              </template>
            </VImg>
          </SwiperSlide>
        </Swiper>
      </template>

      <!-- Single Image -->
      <template v-else-if="images.length === 1">
        <VImg
          :src="getImageUrl(images[0])"
          cover
          height="160"
          class="card-image-content"
        >
          <template #placeholder>
            <div class="d-flex align-center justify-center fill-height">
              <VProgressCircular
                indeterminate
                color="primary"
                size="24"
              />
            </div>
          </template>
          <template #error>
            <div class="d-flex align-center justify-center fill-height text-center text-disabled">
              <VIcon
                icon="tabler-photo-off"
                size="36"
                class="opacity-50"
              />
            </div>
          </template>
        </VImg>
      </template>

      <!-- No Image Fallback -->
      <template v-else>
        <div class="d-flex flex-column align-center justify-center fill-height text-disabled card-image-placeholder">
          <VAvatar
            color="primary"
            variant="tonal"
            size="48"
            class="mb-1"
          >
            <VIcon
              icon="tabler-package"
              size="24"
            />
          </VAvatar>
          <span class="text-caption text-medium-emphasis">{{ $t('catalog.items.no_image', 'No Image') }}</span>
        </div>
      </template>
    </div>

    <!-- Card Body -->
    <VCardText class="d-flex flex-column grow pa-3">
      <!-- Title & Price block -->
      <div class="d-flex justify-space-between align-start gap-2 mb-1">
        <div class="grow min-w-0">
          <div
            class="item-grid-card__title text-body-1 font-weight-bold"
            :title="localizedValue(item.name, '—')"
          >
            {{ localizedValue(item.name, '—') }}
          </div>
          <div class="text-caption text-medium-emphasis font-weight-medium text-uppercase tracking-wide mt-0.5">
            {{ item.code }}
          </div>
        </div>
        <div
          v-if="item.price_range != null"
          class="text-right shrink-0"
        >
          <div class="text-body-1 font-weight-bold text-primary">
            {{ item.price_range }}
          </div>
        </div>
      </div>

      <!-- Badges / Chips -->
      <div class="d-flex flex-wrap align-center gap-1.5 my-2">
        <VChip
          :color="resolveTypeVariant(item.item_type)"
          size="x-small"
          label
          variant="tonal"
          class="text-capitalize font-weight-medium"
        >
          <VIcon
            start
            icon="tabler-category-2"
            size="12"
          />
          {{ item.item_type }}
        </VChip>
        <VChip
          v-if="item.category"
          size="x-small"
          color="secondary"
          variant="tonal"
          label
          class="font-weight-medium"
        >
          {{ localizedValue(item.category.name, '') }}
        </VChip>
        <VChip
          v-if="item.is_stockable === false"
          size="x-small"
          color="warning"
          variant="tonal"
        >
          {{ $t('catalog.items.non_stockable', 'Non-Stockable') }}
        </VChip>
      </div>

      <!-- Description -->
      <p
        class="text-caption text-medium-emphasis mb-0 item-grid-card__desc"
        :title="item.short_description ? localizedValue(item.short_description) : localizedValue(item.description)"
      >
        {{ item.short_description ? localizedValue(item.short_description, '—') : localizedValue(item.description, '—') }}
      </p>
    </VCardText>

    <!-- Card Footer -->
    <VDivider class="mt-auto" />

    <VCardActions class="pa-2 px-3 d-flex justify-space-between align-center item-grid-card__footer">
      <div class="d-flex align-center">
        <div
          class="stock-indicator me-2 shrink-0"
          :class="`bg-${getStockColor(item.current_stock)}`"
        />
        <div class="d-flex flex-column min-w-0">
          <span class="text-xs text-medium-emphasis line-height-1">{{ $t('catalog.items.stock_level', 'Stock') }}</span>
          <span class="text-caption font-weight-bold line-height-1 mt-0.5">{{ item.current_stock ?? 'N/A' }}</span>
        </div>
      </div>

      <div class="d-flex gap-1 shrink-0">
        <VTooltip
          :text="$t('wp.actions.view', 'View Details')"
          location="top"
        >
          <template #activator="{ props: tooltipProps }">
            <IconBtn
              v-bind="tooltipProps"
              size="small"
              color="secondary"
              variant="text"
              @click="emit('view', item)"
            >
              <VIcon
                icon="tabler-eye"
                size="18"
              />
            </IconBtn>
          </template>
        </VTooltip>

        <VTooltip
          :text="$t('wp.actions.edit', 'Edit Item')"
          location="top"
        >
          <template #activator="{ props: tooltipProps }">
            <IconBtn
              v-bind="tooltipProps"
              size="small"
              color="primary"
              variant="text"
              @click="emit('edit', item)"
            >
              <VIcon
                icon="tabler-edit"
                size="18"
              />
            </IconBtn>
          </template>
        </VTooltip>

        <VMenu location="top end">
          <template #activator="{ props: menuProps }">
            <IconBtn
              v-bind="menuProps"
              size="small"
              color="secondary"
              variant="text"
            >
              <VIcon
                icon="tabler-dots-vertical"
                size="18"
              />
            </IconBtn>
          </template>

          <VList
            density="compact"
            class="py-1"
          >
            <VListItem
              prepend-icon="tabler-file-download"
              :title="$t('wp.actions.export_pdf', 'Export PDF')"
              @click="emit('exportPdf', item)"
            />
            <VListItem
              prepend-icon="tabler-mail"
              :title="$t('wp.actions.send_email', 'Send Email')"
              @click="emit('sendEmail', item)"
            />
            <VDivider class="my-1" />
            <VListItem
              prepend-icon="tabler-trash"
              :title="$t('wp.actions.delete', 'Delete Item')"
              class="text-error"
              @click="emit('delete', item)"
            />
          </VList>
        </VMenu>
      </div>
    </VCardActions>
  </VCard>
</template>

<style lang="scss" scoped>
.item-grid-card {
  position: relative;
  overflow: hidden;
  border-radius: 10px;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  block-size: 100%;

  &:hover {
    box-shadow: 0 6px 20px rgba(var(--v-theme-on-surface), 0.1) !important;
    transform: translateY(-2px);
  }
}

.card-image-wrapper {
  position: relative;
  overflow: hidden;
  block-size: 160px;
  inline-size: 100%;
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.card-image-content {
  block-size: 100%;
  inline-size: 100%;
}

.card-image-placeholder {
  block-size: 100%;
  inline-size: 100%;
  background: rgba(var(--v-theme-on-surface), 0.02);
}

.swiper-container {
  block-size: 100%;
  inline-size: 100%;

  :deep(.swiper-button-prev),
  :deep(.swiper-button-next) {
    border-radius: 50%;
    background: rgba(0, 0, 0, 35%);
    block-size: 28px;
    color: white;
    inline-size: 28px;
    opacity: 0;
    transition: opacity 0.2s ease;

    &::after {
      font-size: 12px;
    }

    &:hover {
      background: rgba(var(--v-theme-primary), 0.85);
    }
  }

  &:hover {
    :deep(.swiper-button-prev),
    :deep(.swiper-button-next) {
      opacity: 1;
    }
  }

  :deep(.swiper-pagination-bullet) {
    background: white;
    opacity: 0.7;
  }

  :deep(.swiper-pagination-bullet-active) {
    background: rgb(var(--v-theme-primary));
    opacity: 1;
  }
}

.item-grid-card__title {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-height: 1.35;
  overflow-wrap: anywhere;
  word-break: normal;
}

.item-grid-card__desc {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-height: 1.35;
}

.item-grid-card__footer {
  background: rgba(var(--v-theme-on-surface), 0.02);
  min-block-size: 44px;
}

.stock-indicator {
  border-radius: 50%;
  block-size: 8px;
  inline-size: 8px;
}

@media (max-width: 425px) {
  .card-image-wrapper {
    block-size: 130px;
  }
}
</style>
