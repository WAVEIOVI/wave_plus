<script setup>
const props = defineProps({
  app: {
    type: Object,
    required: true,
  },
})

const { t } = useI18n({ useScope: 'global' })
const router = useRouter()
const isHovered = ref(false)

// Same key convention as applications.vue → applications.modules.<subject>
// Falls back to the original `app.name` when no translation exists.
const label = computed(() => t(`applications.modules.${props.app.subject}`, props.app.name))

const navigateToApp = () => {
  if (props.app.url) {
    router.push(props.app.url)
  }
}
</script>

<template>
  <div
    class="app-card-wrapper"
    role="button"
    tabindex="0"
    :aria-label="`${t('applications.open', 'Open')} ${label}`"
    :class="{ 'hovered': isHovered }"
    @mouseenter="isHovered = true"
    @mouseleave="isHovered = false"
    @click="navigateToApp"
    @keydown.enter="navigateToApp"
    @keydown.space.prevent="navigateToApp"
  >
    <!-- Icon tile -->
    <div class="icon-container">
      <VIcon
        :icon="app.icon"
        size="26"
        class="app-icon"
      />
    </div>

    <!-- Title -->
    <span class="app-title text-body-1 font-weight-medium">
      {{ label }}
    </span>

    <!-- Open affordance -->
    <VIcon
      icon="tabler-arrow-up-right"
      size="18"
      class="app-arrow"
      aria-hidden="true"
    />
  </div>
</template>

<style scoped lang="scss">
.app-card-wrapper {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: space-between;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  border-radius: 16px;

  /*
   * `backwards` (not `forwards`): the entrance only controls the "before" state.
   * With `forwards` the final transform stays locked and silently overrides
   * the :hover transform, so the lift never happened.
   */
  animation: slide-up-fade 0.6s cubic-bezier(0.16, 1, 0.3, 1) backwards;
  backdrop-filter: blur(16px) saturate(160%);
  background: rgba(var(--v-theme-surface), 0.6);
  block-size: 100%;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 4%);
  cursor: pointer;
  gap: 20px;
  inline-size: 100%;
  min-block-size: 136px;
  outline: none;
  padding: 16px;
  transition:
    transform 0.25s cubic-bezier(0.16, 1, 0.3, 1),
    border-color 0.25s ease,
    background-color 0.25s ease,
    box-shadow 0.25s ease;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
}

/* Hover / keyboard focus */
.app-card-wrapper:hover,
.app-card-wrapper.hovered,
.app-card-wrapper:focus-visible {
  border-color: rgba(var(--v-theme-primary), 0.35);
  background: rgba(var(--v-theme-surface), 0.85);
  box-shadow: 0 12px 24px -8px rgba(var(--v-theme-primary), 0.22);
  transform: translateY(-3px);
}

.app-card-wrapper:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.app-card-wrapper:active {
  transform: translateY(0) scale(0.98);
}

/* Icon tile */
.icon-container {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  background: rgba(var(--v-theme-primary), 0.1);
  block-size: 48px;
  inline-size: 48px;
  transition:
    background-color 0.25s ease,
    color 0.25s ease;
}

.app-icon {
  color: rgb(var(--v-theme-primary));
  transition: color 0.25s ease;
}

.app-card-wrapper:hover .icon-container,
.app-card-wrapper.hovered .icon-container,
.app-card-wrapper:focus-visible .icon-container {
  background: rgb(var(--v-theme-primary));
}

.app-card-wrapper:hover .app-icon,
.app-card-wrapper.hovered .app-icon,
.app-card-wrapper:focus-visible .app-icon {
  color: rgb(var(--v-theme-on-primary));
}

/* Title — long names ("Supply Chain Procurement") wrap on 2 lines max */
.app-title {
  display: -webkit-box;
  overflow: hidden;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.9375rem;
  letter-spacing: 0.01em;
  line-height: 1.3;
  overflow-wrap: anywhere;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

/* Arrow */
.app-arrow {
  position: absolute;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  inset-block-start: 14px;
  inset-inline-end: 14px;
  opacity: 0;
  transform: translate(-4px, 4px);
  transition:
    opacity 0.25s ease,
    transform 0.25s ease,
    color 0.25s ease;
}

.app-card-wrapper:hover .app-arrow,
.app-card-wrapper.hovered .app-arrow,
.app-card-wrapper:focus-visible .app-arrow {
  color: rgb(var(--v-theme-primary));
  opacity: 1;
  transform: translate(0, 0);
}

// No hover on touch screens → keep a quiet, always-visible hint
@media (hover: none) {
  .app-arrow {
    opacity: 0.5;
    transform: none;
  }
}

/* Keyframes */
@keyframes slide-up-fade {
  from {
    opacity: 0;
    transform: translateY(16px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (min-width: 600px) {
  .app-card-wrapper {
    min-block-size: 148px;
    padding: 18px;
  }

  .app-title {
    font-size: 1rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .app-card-wrapper {
    animation: none;
    transition: none;
  }

  .app-card-wrapper:hover,
  .app-card-wrapper.hovered,
  .app-card-wrapper:focus-visible {
    transform: none;
  }
}

/* Dark Mode Specifics */
:root[data-theme="dark"] .app-card-wrapper {
  border-color: rgba(255, 255, 255, 6%);
  background: rgba(30, 30, 40, 60%);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 3%);
}

:root[data-theme="dark"] .app-card-wrapper:hover,
:root[data-theme="dark"] .app-card-wrapper.hovered,
:root[data-theme="dark"] .app-card-wrapper:focus-visible {
  border-color: rgba(var(--v-theme-primary), 0.5);
  background: rgba(30, 30, 40, 90%);
  box-shadow: 0 12px 24px -8px rgba(var(--v-theme-primary), 0.35);
}
</style>
