<script setup>
import { computed, useSlots } from 'vue'
import WpStatusChip from './WpStatusChip.vue'

/**
 * WpEntityCard
 * ─────────────────────────────────────────────────────────────────────────────
 * Generic entity card for grid/list views. Supports media banners, customizable
 * content via slots, equal-height column flex, and responsive typography.
 */
const props = defineProps({
  /** Entity object (passed to slots) */
  entity: {
    type: Object,
    default: null,
  },

  /** Card title */
  title: {
    type: String,
    default: '',
  },

  /** Card subtitle */
  subtitle: {
    type: String,
    default: '',
  },

  /** Optional media / image banner URL */
  image: {
    type: String,
    default: '',
  },

  /** Media banner height (default 160) */
  imageHeight: {
    type: [Number, String],
    default: 160,
  },

  /** Media banner object-fit */
  imageCover: {
    type: Boolean,
    default: true,
  },

  /** Status value for status chip */
  status: {
    type: String,
    default: null,
  },

  /** Status type for WpStatusChip (e.g., 'employee', 'vehicle', 'department', 'default') */
  statusType: {
    type: String,
    default: 'default',
  },

  /** Status label override (otherwise resolved from status) */
  statusLabel: {
    type: String,
    default: '',
  },

  /** Avatar image URL */
  avatar: {
    type: String,
    default: '',
  },

  /** Avatar fallback icon */
  avatarIcon: {
    type: String,
    default: '',
  },

  /** Avatar color when using icon */
  avatarColor: {
    type: String,
    default: 'primary',
  },

  /** Navigation href (makes card clickable) */
  href: {
    type: String,
    default: '',
  },

  /** Router link object (alternative to href) */
  to: {
    type: [String, Object],
    default: undefined,
  },

  /** Make card clickable (cursor pointer) */
  clickable: {
    type: Boolean,
    default: false,
  },

  /** Show selection checkbox */
  showSelect: {
    type: Boolean,
    default: false,
  },

  /** Current selection state */
  selected: {
    type: Boolean,
    default: false,
  },

  /** Card variant */
  variant: {
    type: String,
    default: 'outlined',
  },

  /** Card elevation */
  elevation: {
    type: [Number, String],
    default: 0,
  },

  /** Show a skeleton placeholder instead of content (e.g. while refreshing). */
  loading: {
    type: Boolean,
    default: false,
  },

  /** Additional CSS classes */
  class: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['click', 'update:selected'])

const slots = useSlots()
const hasAvatar = computed(() => Boolean(props.avatar || props.avatarIcon || slots.avatar))
const hasMedia = computed(() => Boolean(props.image || slots.media || slots.image))
const isInteractive = computed(() => Boolean(props.clickable || props.href || props.to))

const handleClick = event => {
  if (!isInteractive.value) return
  emit('click', event)
}

const handleSelectionChange = value => {
  emit('update:selected', value)
}

const handleKeyDown = event => {
  if (isInteractive.value && (event.key === 'Enter' || event.key === ' ')) {
    event.preventDefault()
    emit('click', event)
  }
}
</script>

<template>
  <VCard
    class="wp-entity-card d-flex flex-column"
    :class="[
      props.class,
      { 'wp-entity-card--interactive': isInteractive, 'wp-entity-card--selected': props.selected },
    ]"
    :variant="props.variant"
    :elevation="props.elevation"
    :to="props.to"
    :href="props.href"
    rounded="lg"
    tabindex="0"
    :role="isInteractive ? 'button' : undefined"
    :aria-pressed="props.showSelect ? props.selected : undefined"
    @click="handleClick"
    @keydown="handleKeyDown"
  >
    <!-- Skeleton placeholder -->
    <template v-if="props.loading">
      <VCardItem class="wp-entity-card__header pb-2">
        <template #prepend>
          <VSkeletonLoader type="avatar" />
        </template>
        <VSkeletonLoader type="heading" />
      </VCardItem>
      <VCardText class="pt-1 pb-2 grow">
        <VSkeletonLoader type="text@2" />
      </VCardText>
    </template>

    <template v-else>
      <!-- Media / Image Banner -->
      <div
        v-if="hasMedia"
        class="wp-entity-card__media position-relative overflow-hidden"
      >
        <slot
          name="media"
          :entity="props.entity"
        >
          <slot
            name="image"
            :entity="props.entity"
          >
            <VImg
              v-if="props.image"
              :src="props.image"
              :height="props.imageHeight"
              :cover="props.imageCover"
              class="wp-entity-card__img bg-grey-lighten-4"
            />
          </slot>
        </slot>
      </div>

      <!-- Card Header: Avatar, Title, Subtitle, Status, Actions -->
      <VCardItem class="wp-entity-card__header pb-2">
        <!-- Prepend: Avatar or Checkbox -->
        <template
          v-if="hasAvatar || $slots.avatar || props.showSelect"
          #prepend
        >
          <VCheckboxBtn
            v-if="props.showSelect && !hasAvatar"
            :model-value="props.selected"
            :aria-label="$t('Select')"
            density="compact"
            hide-details
            @update:model-value="handleSelectionChange"
            @click.stop
          />
          <slot
            v-else
            name="avatar"
            :entity="props.entity"
          >
            <VAvatar
              v-if="props.avatarIcon"
              :color="props.avatarColor"
              variant="tonal"
              size="40"
              rounded
            >
              <VIcon
                :icon="props.avatarIcon"
                size="20"
              />
            </VAvatar>
            <VAvatar
              v-else-if="props.avatar"
              :image="props.avatar"
              size="40"
              rounded
            />
          </slot>
        </template>

        <!-- Title & Subtitle -->
        <VCardTitle class="wp-entity-card__title text-body-1 font-weight-semibold">
          <slot
            name="title"
            :entity="props.entity"
          >
            {{ props.title }}
          </slot>
        </VCardTitle>

        <VCardSubtitle
          v-if="props.subtitle || $slots.subtitle"
          class="wp-entity-card__subtitle text-caption text-medium-emphasis mt-0.5"
        >
          <slot
            name="subtitle"
            :entity="props.entity"
          >
            {{ props.subtitle }}
          </slot>
        </VCardSubtitle>

        <!-- Header append: status chip and/or action buttons -->
        <template
          v-if="props.status || $slots.actions"
          #append
        >
          <div class="d-flex align-center gap-1.5">
            <WpStatusChip
              v-if="props.status"
              :type="props.statusType"
              :status="props.status"
              :label="props.statusLabel || props.status"
              size="x-small"
            />
            <div
              v-if="$slots.actions"
              @click.stop
            >
              <slot
                name="actions"
                :entity="props.entity"
              />
            </div>
          </div>
        </template>
      </VCardItem>

      <!-- Meta chips (department, type, tags, etc.) -->
      <template v-if="$slots.meta">
        <div class="wp-entity-card__meta px-4 pt-0 pb-2">
          <slot
            name="meta"
            :entity="props.entity"
          />
        </div>
      </template>

      <!-- Custom content body (stretches to fill remaining space) -->
      <div
        v-if="$slots.content || $slots.default"
        class="wp-entity-card__content px-4 pt-1 pb-2 grow min-w-0"
      >
        <slot
          name="content"
          :entity="props.entity"
        >
          <slot :entity="props.entity" />
        </slot>
      </div>

      <!-- Footer actions (pinned at bottom) -->
      <template v-if="$slots.footer">
        <VDivider class="mt-auto" />
        <VCardActions class="wp-entity-card__footer px-3 py-2 d-flex align-center justify-space-between flex-wrap gap-2">
          <slot
            name="footer"
            :entity="props.entity"
          />
        </VCardActions>
      </template>
    </template>
  </VCard>
</template>

<style scoped>
.wp-entity-card {
  block-size: 100%;
  overflow: hidden;
  transition: border-color 0.2s ease, box-shadow 0.25s ease, transform 0.25s ease;
}

.wp-entity-card--interactive {
  cursor: pointer;
}

.wp-entity-card--interactive:hover {
  border-color: rgba(var(--v-theme-primary), 0.5) !important;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 9%);
  transform: translateY(-2px);
}

.wp-entity-card--interactive:active {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 6%);
  transform: translateY(0);
}

.wp-entity-card:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.wp-entity-card--selected {
  border-color: rgb(var(--v-theme-primary)) !important;
  background: rgba(var(--v-theme-primary), 0.04);
  box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
}

.wp-entity-card__media {
  inline-size: 100%;
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.wp-entity-card__header :deep(.v-card-item__content) {
  min-inline-size: 0;
}

.wp-entity-card__title {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-height: 1.35;
  overflow-wrap: anywhere;
  word-break: normal;
}

.wp-entity-card__subtitle {
  overflow: hidden;
  overflow-wrap: anywhere;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wp-entity-card__meta {
  min-inline-size: 0;
}

.wp-entity-card__content {
  min-inline-size: 0;
  font-size: 0.8125rem;
}

.wp-entity-card__footer {
  min-block-size: 42px;
}

@media (prefers-reduced-motion: reduce) {
  .wp-entity-card {
    transition: none;
  }

  .wp-entity-card--interactive:hover {
    transform: none;
  }
}

/* Smartphone screen adjustments (<= 425px) */
@media (max-width: 425px) {
  .wp-entity-card :deep(.v-card-item) {
    padding-block: 0.625rem 0.375rem !important;
    padding-inline: 0.75rem !important;
  }

  .wp-entity-card__title {
    font-size: 0.875rem !important;
    line-height: 1.3 !important;
  }

  .wp-entity-card__subtitle {
    font-size: 0.75rem !important;
  }

  .wp-entity-card__meta,
  .wp-entity-card__content {
    padding-inline: 0.75rem !important;
    padding-block: 0.25rem 0.5rem !important;
  }

  .wp-entity-card__footer {
    padding-inline: 0.75rem !important;
    padding-block: 0.375rem 0.5rem !important;
  }
}
</style>
