<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * WpRowActions
 * ─────────────────────────────────────────────────────────────────────────────
 * Standardized per-row action menu (three-dot "more" menu) for data tables.
 *
 * Usage:
 *   <WpRowActions
 *     :actions="[
 *       { label: $t('...'), icon: 'tabler-eye', emit: 'view' },
 *       { label: $t('...'), icon: 'tabler-edit', emit: 'edit' },
 *       { label: $t('...'), icon: 'tabler-trash', emit: 'delete', color: 'error' },
 *     ]"
 *     @view="onView(item)"
 *     @edit="onEdit(item)"
 *     @delete="onDelete(item)"
 *   />
 *
 *  Each action object shape:
 *  {
 *    label: string,
 *    icon: string,
 *    emit: string,      -- the event name emitted when clicked
 *    color?: string,    -- Vuetify color token (e.g. 'error')
 *    disabled?: boolean,
 *    dividerBefore?: boolean,  -- render a VDivider before this action
 *  }
 */
const props = defineProps({
  actions: {
    type: Array,
    required: true,
  },

  /** Icon for the trigger button */
  icon: {
    type: String,
    default: 'tabler-dots-vertical',
  },

  /** Size for the trigger button */
  size: {
    type: String,
    default: 'small',
  },

  /** Tooltip for the trigger button. Defaults to a generic "Actions" label. */
  tooltip: {
    type: String,
    default: '',
  },

  /** Whether to display a quick-access button for the edit action alongside the trigger */
  quickEdit: {
    type: Boolean,
    default: true,
  },
})

/* eslint-disable vue/valid-define-emits */
const emit = defineEmits(['view', 'edit', 'delete'])

const { t } = useI18n()

const triggerTooltip = computed(() => props.tooltip || t('wp.actions.more', 'Actions'))
const editAction = computed(() => props.actions.find(a => a.emit === 'edit'))
</script>

<template>
  <div class="wp-row-actions-container d-inline-flex align-center gap-1">
    <!-- Quick Edit Action -->
    <VTooltip
      v-if="props.quickEdit && editAction && !editAction.disabled"
      :text="editAction.label || $t('Edit')"
      location="top"
    >
      <template #activator="{ props: quickEditTooltipProps }">
        <VBtn
          v-bind="quickEditTooltipProps"
          :size="props.size"
          variant="text"
          color="default"
          class="wp-row-actions"
          :aria-label="editAction.label || $t('Edit')"
          @click.stop="$emit('edit')"
        >
          <VIcon
            :icon="editAction.icon || 'tabler-edit'"
            size="20"
          />
        </VBtn>
      </template>
    </VTooltip>

    <!-- 3-Dots More Menu -->
    <VTooltip
      :text="triggerTooltip"
      location="top"
    >
      <template #activator="{ props: tooltipProps }">
        <VBtn
          v-bind="tooltipProps"
          :size="props.size"
          variant="text"
          color="default"
          class="wp-row-actions"
          :aria-label="triggerTooltip"
          aria-haspopup="menu"
        >
          <VIcon :icon="props.icon" />

          <VMenu
            activator="parent"
            close-on-content-click
            location="bottom end"
            transition="scale-transition"
          >
            <VList
              density="compact"
              class="wp-row-actions__menu"
              elevation="4"
              rounded="lg"
              role="menu"
            >
              <template
                v-for="(action, idx) in props.actions"
                :key="idx"
              >
                <VDivider
                  v-if="action.dividerBefore"
                  class="my-1"
                />

                <VListItem
                  :disabled="action.disabled"
                  :class="action.color ? `text-${action.color}` : ''"
                  class="wp-row-actions__item"
                  role="menuitem"
                  @click="$emit(action.emit)"
                >
                  <template #prepend>
                    <VIcon
                      :icon="action.icon"
                      size="18"
                      class="me-2"
                      :color="action.color || undefined"
                    />
                  </template>
                  <VListItemTitle class="text-body-2">
                    {{ action.label }}
                  </VListItemTitle>
                </VListItem>
              </template>
            </VList>
          </VMenu>
        </VBtn>
      </template>
    </VTooltip>
  </div>
</template>

<style scoped>
.wp-row-actions {
  transition: background-color 0.15s ease, transform 0.15s ease;
}

.wp-row-actions:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.wp-row-actions__menu {
  min-inline-size: 168px;
}

.wp-row-actions__item {
  min-block-size: 36px;
}
</style>
