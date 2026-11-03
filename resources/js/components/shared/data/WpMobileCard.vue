<script setup>
import { computed, useSlots } from 'vue'
import WpStatusChip from './WpStatusChip.vue'

/**
 * WpMobileCard
 * ─────────────────────────────────────────────────────────────────────────────
 * Compact, mobile-first record card for WpResponsiveList mobile slot.
 * Ensures readable typography, no horizontal clipping, responsive wrapping,
 * and structured metadata slots.
 */
const props = defineProps({
  title: {
    type: String,
    default: '',
  },
  subtitle: {
    type: String,
    default: '',
  },

  /** Left-side avatar/icon. */
  avatar: {
    type: String,
    default: '',
  },
  avatarColor: {
    type: String,
    default: 'primary',
  },
  avatarIcon: {
    type: String,
    default: '',
  },

  /** Alias for avatarIcon to support pages passing `icon` */
  icon: {
    type: String,
    default: '',
  },

  /** Status value for status chip */
  status: {
    type: String,
    default: null,
  },

  /** Status type for WpStatusChip (e.g., 'department', 'team', 'employee') */
  statusType: {
    type: String,
    default: 'default',
  },

  /** Status color override */
  statusColor: {
    type: String,
    default: '',
  },

  /** Status label override */
  statusLabel: {
    type: String,
    default: '',
  },

  /** Highlight the card as selected (used with list selection). */
  selected: {
    type: Boolean,
    default: false,
  },

  /** Makes the whole card feel tappable (cursor + press feedback). */
  clickable: {
    type: Boolean,
    default: false,
  },

  /** Navigation target (router-link) */
  to: {
    type: [String, Object],
    default: undefined,
  },

  /** Navigation target (URL href) */
  href: {
    type: String,
    default: '',
  },

  /** Enable selection checkbox in prepend area */
  showSelect: {
    type: Boolean,
    default: false,
  },

  /** Item object forwarded to slots */
  item: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['click', 'update:selected'])

const slots = useSlots()

const effectiveIcon = computed(() => props.avatarIcon || props.icon)
const hasPrepend = computed(() => Boolean(props.avatar || effectiveIcon.value || slots.avatar || slots.prepend || props.showSelect))
const hasBadgesOrStatus = computed(() => Boolean(props.status || slots.badges || slots.status))
const isLink = computed(() => Boolean(props.to || props.href))

const handleClick = event => {
  if (props.clickable || isLink.value)
    emit('click', event)
}
</script>

<template>
  <VCard
    class="wp-mobile-card mb-3"
    :class="{
      'wp-mobile-card--clickable': props.clickable || isLink,
      'wp-mobile-card--selected': props.selected,
    }"
    rounded="lg"
    elevation="0"
    border
    :to="props.to"
    :href="props.href"
    :ripple="props.clickable"
    tabindex="0"
    @click="handleClick"
  >
    <!-- Header: Avatar + Title/Subtitle + Actions -->
    <div class="wp-mobile-card__header d-flex align-start pa-3 pb-1 gap-3">
      <!-- Prepend / Avatar -->
      <div
        v-if="hasPrepend"
        class="wp-mobile-card__prepend shrink-0 pt-0.5"
      >
        <slot
          name="prepend"
          :item="props.item"
        >
          <div class="d-flex align-center gap-2">
            <VCheckboxBtn
              v-if="props.showSelect"
              :model-value="props.selected"
              density="compact"
              hide-details
              @click.stop
              @update:model-value="$emit('update:selected', $event)"
            />
            <slot
              name="avatar"
              :item="props.item"
            >
              <VAvatar
                v-if="effectiveIcon"
                :color="props.avatarColor"
                variant="tonal"
                size="40"
                rounded
              >
                <VIcon
                  :icon="effectiveIcon"
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
          </div>
        </slot>
      </div>

      <!-- Main Heading Block -->
      <div class="wp-mobile-card__heading d-flex flex-column flex-grow-1 min-w-0 w-100">
        <div class="d-flex align-start justify-space-between w-100 gap-2">
          <!-- Title & Subtitle -->
          <div class="wp-mobile-card__title-wrap flex-grow-1 min-w-0">
            <slot
              name="title"
              :item="props.item"
            >
              <div
                v-if="props.title"
                class="wp-mobile-card__title text-body-1 font-weight-semibold"
                :title="props.title"
              >
                {{ props.title }}
              </div>
            </slot>
            <slot
              name="subtitle"
              :item="props.item"
            >
              <div
                v-if="props.subtitle"
                class="wp-mobile-card__subtitle text-caption text-medium-emphasis mt-0.5"
                :title="props.subtitle"
              >
                {{ props.subtitle }}
              </div>
            </slot>
          </div>

          <!-- Actions Slot (three-dot menu / row actions) -->
          <div
            v-if="$slots.actions || $slots.append"
            class="wp-mobile-card__actions shrink-0 ms-auto"
            @click.stop
          >
            <slot
              name="actions"
              :item="props.item"
            >
              <slot
                name="append"
                :item="props.item"
              />
            </slot>
          </div>
        </div>
      </div>
    </div>

    <!-- Badges & Status Row (Starts at vertical line of avatar) -->
    <div
      v-if="hasBadgesOrStatus"
      class="wp-mobile-card__badges px-3 pt-0 pb-2 d-flex flex-wrap align-center gap-1.5 min-w-0 w-100"
    >
      <slot
        name="status"
        :item="props.item"
      >
        <WpStatusChip
          v-if="props.status"
          :status="props.status"
          :type="props.statusType"
          :color="props.statusColor"
          :label="props.statusLabel"
          size="x-small"
        />
      </slot>
      <slot
        name="badges"
        :item="props.item"
      />
    </div>

    <!-- Structured Details Slot -->
    <div
      v-if="$slots.details"
      class="wp-mobile-card__details px-3 py-2 border-t"
    >
      <slot
        name="details"
        :item="props.item"
      />
    </div>

    <!-- Custom Content Slot -->
    <VCardText
      v-if="$slots.content || $slots.default"
      class="wp-mobile-card__content px-3 py-2 pt-1"
    >
      <slot
        name="content"
        :item="props.item"
      >
        <slot :item="props.item" />
      </slot>
    </VCardText>

    <!-- Footer Slot -->
    <div
      v-if="$slots.footer"
      class="wp-mobile-card__footer px-3 py-2 border-t d-flex align-center justify-space-between flex-wrap gap-2"
    >
      <slot
        name="footer"
        :item="props.item"
      />
    </div>
  </VCard>
</template>

<style scoped>
.wp-mobile-card {
  position: relative;
  inline-size: 100%;
  overflow: hidden;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.wp-mobile-card--clickable {
  cursor: pointer;
}

.wp-mobile-card--clickable:active {
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.wp-mobile-card--selected {
  border-color: rgb(var(--v-theme-primary)) !important;
  background: rgba(var(--v-theme-primary), 0.08) !important;
  box-shadow: 0 0 0 1px rgb(var(--v-theme-primary)) !important;
}

.wp-mobile-card__title {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-height: 1.35;
  overflow-wrap: anywhere;
  word-break: normal;
}

.wp-mobile-card__subtitle {
  overflow: hidden;
  overflow-wrap: anywhere;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wp-mobile-card__header {
  inline-size: 100%;
}

.wp-mobile-card__heading {
  flex: 1 1 auto;
  min-inline-size: 0;
  inline-size: 100%;
}

.wp-mobile-card__actions {
  margin-inline-start: auto;
  flex-shrink: 0;
}

.wp-mobile-card__badges {
  gap: 8px !important;
}

.wp-mobile-card__badges :deep(.v-chip) {
  max-inline-size: 100%;
  margin: 0 !important;
}

.wp-mobile-card__badges :deep(.v-chip__content) {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wp-mobile-card__actions :deep(.v-btn) {
  block-size: 36px;
  min-inline-size: 36px;
}

/* Details section: subtle top border for visual separation */
.wp-mobile-card__details {
  border-color: rgba(var(--v-border-color), calc(var(--v-border-opacity) * 0.5)) !important;
}

/* Key-value row helpers that child slots can use */
.wp-mobile-card :deep(.wp-mobile-row) {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8125rem;
  line-height: 1.4;
  min-inline-size: 0;
}

.wp-mobile-card :deep(.wp-mobile-row__icon) {
  flex-shrink: 0;
}

.wp-mobile-card :deep(.wp-mobile-row__label) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  flex-shrink: 0;
}

.wp-mobile-card :deep(.wp-mobile-row__value) {
  font-weight: 500;
  min-inline-size: 0;
  overflow-wrap: anywhere;
}

/* Footer section: consistent separator */
.wp-mobile-card__footer {
  border-color: rgba(var(--v-border-color), calc(var(--v-border-opacity) * 0.5)) !important;
}

/* Smartphone screen optimizations (<= 425px) */
@media (max-width: 425px) {
  .wp-mobile-card__header {
    gap: 8px !important;
    padding-block: 10px 4px !important;
    padding-inline: 10px !important;
  }

  .wp-mobile-card__badges {
    gap: 4px !important;
    padding-block: 0 6px !important;
    padding-inline: 10px !important;
  }

  .wp-mobile-card__title {
    font-size: 0.875rem !important;
    line-height: 1.3 !important;
  }

  .wp-mobile-card__subtitle {
    font-size: 0.75rem !important;
  }

  .wp-mobile-card__details,
  .wp-mobile-card__content,
  .wp-mobile-card__footer {
    padding-block: 8px !important;
    padding-inline: 10px !important;
  }
}
</style>
