<template>
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- AppBottomNav — compact floating bottom navigation                     -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- A custom floating variant (VBottomNavigation pins edge-to-edge, so the  -->
  <!-- floating look is achieved with a VCard + VBtn group instead). Sits 12px -->
  <!-- above the bottom edge, uses compact rounded corners, and is safe-area   -->
  <!-- aware. Inactive items show icons only; the active item shows its title. -->
  <!--                                                                        -->
  <!-- When paired with `AppFab`, give the FAB extra bottom clearance so the   -->
  <!-- two floating elements never overlap (see `--app-fab-bottom-offset`).   -->
  <!--                                                                        -->
  <!-- Usage:                                                                 -->
  <!--   <AppBottomNav                                                        -->
  <!--     v-model="active"                                                   -->
  <!--     :items="[                                                          -->
  <!--       { title: 'Home',   icon: 'tabler-home',  to: '/home' },           -->
  <!--       { title: 'Orders', icon: 'tabler-list',  to: '/orders', badge: 3 }, -->
  <!--     ]"                                                                  -->
  <!--     @update:model-value="onNavChange"                                   -->
  <!--   />                                                                   -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <nav
    v-show="!hidden && items.length"
    class="app-bottom-nav"
    :class="{ 'app-bottom-nav--hidden': hidden }"
    :style="{
      insetBlockEnd: `calc(${BOTTOM_NAV_FLOAT_GAP}px + ${safeAreaBottom}px)`,
      '--app-bottom-nav-bg': parsedBgColor,
    }"
    :aria-label="ariaLabel"
  >
    <VCard
      class="app-bottom-nav__pill"
      :class="{
        'app-bottom-nav__pill--slim': !grow,
        'app-bottom-nav__pill--fit-content': fitContent,
      }"
      elevation="8"
      rounded="lg"
    >
      <PerfectScrollbar
        tag="ul"
        class="app-bottom-nav__items"
        :options="{ wheelPropagation: false, suppressScrollY: true }"
      >
        <li
          v-for="item in items"
          :key="item.key"
          :ref="element => setItemElement(item.key, element)"
          :class="{ 'app-bottom-nav__item--active': item.key === activeKey }"
          :style="item.key === activeKey ? { '--app-bottom-nav-label-width': `${Math.min(item.title.length * 7 + 12, 160)}px` } : undefined"
          class="app-bottom-nav__item"
        >
          <VBtn
            :active="item.key === activeKey"
            active-class="app-bottom-nav__btn--active"
            :aria-current="item.key === activeKey ? 'page' : undefined"
            :aria-label="item.title"
            :color="item.key === activeKey ? color : undefined"
            :disabled="item.disabled"
            :href="item.href"
            :rel="item.href ? 'noopener noreferrer' : undefined"
            rounded="lg"
            :to="item.to"
            :variant="item.key === activeKey ? 'tonal' : 'text'"
            class="app-bottom-nav__btn"
            size="default"
            @click="onItemClick(item)"
          >
            <template #default>
              <div class="app-bottom-nav__btn-inner">
                <span class="app-bottom-nav__icon-wrapper">
                  <VIcon
                    :icon="item.icon"
                    size="22"
                  />

                  <VBadge
                    v-if="showBadges && item.badge"
                    :content="item.badge === true ? undefined : String(item.badge)"
                    :dot="item.badge === true"
                    color="error"
                    floating
                    class="app-bottom-nav__badge"
                  />
                </span>

                <Transition name="bottom-nav-label">
                  <span
                    v-if="item.key === activeKey"
                    class="app-bottom-nav__label text-caption font-weight-medium"
                  >
                    {{ item.title }}
                  </span>
                </Transition>
              </div>
            </template>
          </VBtn>
        </li>
      </PerfectScrollbar>
    </VCard>
  </nav>
</template>

<script setup>
// !SECTION AppBottomNav
// ─────────────────────────────────────────────────────────────────────────────
// Compact floating bottom navigation with horizontally scrollable icon items,
// an inline title on the active item, badge support, and safe-area awareness.
// ─────────────────────────────────────────────────────────────────────────────
import { BOTTOM_NAV_FLOAT_GAP } from '@/components/shared/nav/bottomNavTypes'
import {
  useBottomNavActive,
  useBottomNavItems,
  useBottomNavScroll,
} from '@/composables/useBottomNav'
import { useFabSafeArea } from '@/composables/useFabSystem'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'

// ─────────────────────────────────────────────────────────────────────────────
// Props & emits
// ─────────────────────────────────────────────────────────────────────────────
const props = defineProps({
  /** Nav items — see `BottomNavItem` in `bottomNavTypes.js` */
  items: {
    type: Array,
    required: true,
  },

  /** Active value (v-model). Router-matching is used when unset. */
  modelValue: {
    type: [String, Number],
    default: undefined,
  },

  /** Active item + accent color */
  color: {
    type: String,
    default: 'primary',
  },

  /** Bar background color — Vuetify color name, CSS color, or `surface` */
  bgColor: {
    type: String,
    default: 'surface',
  },

  /** Stretch items evenly across the full bar width */
  grow: {
    type: Boolean,
    default: true,
  },

  /** Size the pill to its items instead of stretching across the viewport. */
  fitContent: {
    type: Boolean,
    default: false,
  },

  /** Hide count/dot badges when the navigation is used as a compact tab bar. */
  showBadges: {
    type: Boolean,
    default: true,
  },

  /** Hide the bar while scrolling down (revealed on scroll up) */
  hideOnScroll: {
    type: Boolean,
    default: false,
  },

  /** Accessible name of the navigation landmark */
  ariaLabel: {
    type: String,
    default: 'Primary',
  },
})

const emit = defineEmits(['update:modelValue', 'change'])

// ─────────────────────────────────────────────────────────────────────────────
// Shared logic
// ─────────────────────────────────────────────────────────────────────────────
const items = useBottomNavItems(() => props.items)
const activeKey = useBottomNavActive({ modelValue: () => props.modelValue, items })
const { hidden } = useBottomNavScroll(() => props.hideOnScroll)
const { safeAreaBottom } = useFabSafeArea()
const itemElements = new Map()

const setItemElement = (key, element) => {
  if (element)
    itemElements.set(key, element)
  else
    itemElements.delete(key)
}

watch(activeKey, async key => {
  if (key == null)
    return

  await nextTick()
  itemElements.get(key)?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' })
})

/**
 * bgColor may be a Vuetify theme color (`surface`) or a raw CSS color
 * (`#fff`, `rgba(...)`). Theme colors resolve through rgb(var(...)) tokens.
 */
const isThemeColor = computed(() =>
  !/^#|rgb|hsl/.test(props.bgColor || ''))

const parsedBgColor = computed(() =>
  isThemeColor.value
    ? `rgb(var(--v-theme-${props.bgColor}))`
    : props.bgColor)

// ─────────────────────────────────────────────────────────────────────────────
// Interaction
// ─────────────────────────────────────────────────────────────────────────────
const onItemClick = item => {
  if (item.disabled)
    return

  emit('update:modelValue', item.key)
  emit('change', item)
}

/** Exposed so parents can read state / act programmatically. */
defineExpose({ hidden, activeKey })
</script>

<style lang="scss" scoped>
.app-bottom-nav {
  position: fixed;
  z-index: 900;
  display: flex;
  justify-content: center;
  inset-inline: 0;
  pointer-events: none;
  transition: transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1), opacity 0.25s ease;
}

/* Hidden state mirrors the FAB system's hide-on-scroll */
.app-bottom-nav--hidden {
  opacity: 0;
  transform: translateY(140%);
}

.app-bottom-nav__pill {
  padding: 3px;
  border-radius: 0.75rem !important;
  background-color: var(--app-bottom-nav-bg);
  box-shadow: 0 8px 28px rgba(0, 0, 0, 14%);
  inline-size: min(560px, calc(100vw - 32px));
  max-inline-size: calc(100vw - 32px);
  pointer-events: auto;
}

.app-bottom-nav__items {
  display: flex;
  align-items: center;
  padding: 0;
  block-size: 44px;
  gap: 2px;
  inline-size: 100%;
  list-style: none;
  min-inline-size: 0;
}

.app-bottom-nav__pill--slim {
  inline-size: auto !important;
}

.app-bottom-nav__pill--fit-content {
  inline-size: fit-content;
  max-inline-size: calc(100vw - 32px);
}

.app-bottom-nav__pill--fit-content .app-bottom-nav__items {
  inline-size: max-content;
  max-inline-size: calc(100vw - 44px);
}

.app-bottom-nav__item {
  display: flex;
  flex: 0 0 40px;
  inline-size: 40px;
  transition: flex-basis 0.24s cubic-bezier(0.2, 0.8, 0.2, 1), inline-size 0.24s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.app-bottom-nav__item--active {
  flex-basis: calc(40px + var(--app-bottom-nav-label-width));
  inline-size: calc(40px + var(--app-bottom-nav-label-width));
}

@media (min-width: 600px) and (max-width: 960px) {
  .app-bottom-nav__pill:not(.app-bottom-nav__pill--fit-content) {
    inline-size: min(640px, calc(100vw - 48px));
  }

  .app-bottom-nav__pill:not(.app-bottom-nav__pill--fit-content) .app-bottom-nav__item {
    flex: 1 0 40px;
    inline-size: auto;
  }
}

.app-bottom-nav__btn {
  align-self: stretch;
  inline-size: 100%;
  min-block-size: 38px;
  min-inline-size: 0 !important;
  padding-inline: 2px;

  /* Smooth active-state morph (color/background transitions come from VBtn) */
  transition: transform 0.18s cubic-bezier(0.34, 1.4, 0.64, 1);

  :deep(.v-btn__content) {
    inline-size: 100%;
  }

  &:active:not(:disabled) {
    transform: scale(0.96);
  }

  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: -2px;
  }
}

/* Active pill: soft colored rounded background behind icon + label */
.app-bottom-nav__btn--active {
  :deep(.v-btn__overlay),
  :deep(.v-btn__underlay) {
    opacity: 0;
  }
}

.app-bottom-nav__btn-inner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  inline-size: 100%;
}

.app-bottom-nav__icon-wrapper {
  position: relative;
  display: inline-flex;
}

.app-bottom-nav__badge {
  position: absolute;
  inset-block-start: -6px;
  inset-inline-end: -10px;
}

.app-bottom-nav__label {
  overflow: hidden;
  line-height: 1.2;
  max-inline-size: var(--app-bottom-nav-label-width);
  white-space: nowrap;
}

.bottom-nav-label-enter-active,
.bottom-nav-label-leave-active {
  transition: max-inline-size 0.24s ease, opacity 0.18s ease, transform 0.24s ease;
}

.bottom-nav-label-enter-from,
.bottom-nav-label-leave-to {
  max-inline-size: 0;
  opacity: 0;
  transform: translateX(-8px);
}

/* Accessibility: honor reduced-motion for the slide/fade transitions */
@media (prefers-reduced-motion: reduce) {
  .app-bottom-nav,
  .app-bottom-nav__pill,
  .app-bottom-nav__btn,
  .app-bottom-nav__item,
  .bottom-nav-label-enter-active,
  .bottom-nav-label-leave-active {
    transition-duration: 1ms;
  }
}
</style>
