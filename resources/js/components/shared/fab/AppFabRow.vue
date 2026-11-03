<template>
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- AppFabRow — shared single-line row for the FAB action sheets            -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- One row = tonal avatar + title (+ description) + optional badge +       -->
  <!-- optional chevron. Renders a router link when `to` is set (per the       -->
  <!-- `FabAction.to` contract in `fabTypes.js`), otherwise a keyboard-        -->
  <!-- accessible button row. Shared by `AppNestedActionsSheet`.               -->
  <!--                                                                        -->
  <!-- Usage:                                                                 -->
  <!--   <AppFabRow                                                           -->
  <!--     v-for="action in actions"                                          -->
  <!--     :key="action.id"                                                   -->
  <!--     :icon="action.icon"                                                -->
  <!--     :title="action.title"                                              -->
  <!--     :description="action.description"                                  -->
  <!--     :badge="action.badge"                                              -->
  <!--     @click="onActionClick(action)"                                     -->
  <!--   />                                                                   -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <Component
    :is="tag"
    class="app-fab-row"
    :class="{ 'app-fab-row--disabled': disabled }"
    :to="isLink ? to : undefined"
    :aria-label="resolvedAriaLabel"
    :aria-disabled="disabled || undefined"
    :role="isLink ? undefined : 'button'"
    :tabindex="isLink || disabled ? undefined : 0"
    @click="onClick"
    @keydown.enter.prevent="onClick"
    @keydown.space.prevent="onClick"
  >
    <VAvatar
      :color="color ?? 'primary'"
      variant="tonal"
      size="36"
      rounded="lg"
    >
      <VIcon
        :icon="icon ?? 'tabler-point'"
        size="20"
      />
    </VAvatar>

    <div class="flex-grow-1 ms-3">
      <div class="text-body-1 font-weight-medium">
        {{ title }}
      </div>
      <span
        v-if="description"
        class="text-body-2 text-medium-emphasis"
      >
        {{ description }}
      </span>
    </div>

    <VChip
      v-if="badge"
      size="x-small"
      label
      :color="badgeColor ?? 'secondary'"
    >
      {{ badge }}
    </VChip>

    <VIcon
      v-if="chevron"
      icon="tabler-chevron-right"
      size="20"
      color="secondary"
      class="ms-2"
    />
  </Component>
</template>

<script setup>
// ─────────────────────────────────────────────────────────────────────────────
// AppFabRow — single building-block row shared across the FAB action sheets
// so the avatar + title + description + badge + chevron markup lives in
// exactly one place (previously duplicated 3× in `AppNestedActionsSheet`).
// Also owns the `to` → router-link rendering contract and the disabled state.
// ─────────────────────────────────────────────────────────────────────────────
import { RouterLink } from 'vue-router'

const props = defineProps({
  /** Tabler / MDI icon name (falls back to `tabler-point`) */
  icon: {
    type: String,
    default: undefined,
  },

  /** Row title */
  title: {
    type: String,
    required: true,
  },

  /** Optional short description, rendered under the title */
  description: {
    type: String,
    default: undefined,
  },

  /** Vuetify color for the tonal avatar */
  color: {
    type: String,
    default: undefined,
  },

  /** Optional badge text / count */
  badge: {
    type: [String, Number],
    default: undefined,
  },

  /** Vuetify color for the badge chip */
  badgeColor: {
    type: String,
    default: undefined,
  },

  /** Render a trailing chevron (drill-down affordance) */
  chevron: {
    type: Boolean,
    default: false,
  },

  /** Router location — renders the row as a router link instead of a button */
  to: {
    type: [String, Object],
    default: undefined,
  },

  /** Disable the row */
  disabled: {
    type: Boolean,
    default: false,
  },

  /** Accessible name (falls back to `title`) */
  ariaLabel: {
    type: String,
    default: undefined,
  },
})

const emit = defineEmits(['click'])

// ── Render shape ─────────────────────────────────────────────────────────────
const isLink = computed(() => Boolean(props.to) && !props.disabled)
const tag = computed(() => (isLink.value ? RouterLink : 'div'))

const resolvedAriaLabel = computed(() => props.ariaLabel ?? props.title)

// ── Interaction ──────────────────────────────────────────────────────────────
const onClick = () => {
  if (props.disabled)
    return

  emit('click')
}
</script>

<style lang="scss" scoped>
.app-fab-row {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 10px 12px;
  border-radius: 12px;
  cursor: pointer;
  transition:
    background-color 0.18s ease,
    transform 0.18s cubic-bezier(0.34, 1.4, 0.64, 1);

  &:hover:not(.app-fab-row--disabled) {
    background: rgba(var(--v-theme-on-surface), 0.05);
    transform: translateX(2px);
  }

  &:active:not(.app-fab-row--disabled) {
    transform: scale(0.98);
  }

  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: -2px;
  }
}

.app-fab-row--disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
</style>
