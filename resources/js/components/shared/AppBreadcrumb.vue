<script setup>
import { computed, defineProps } from 'vue'

const props = defineProps({
  items: {
    type: Array,
    required: true,
  },
  color: {
    type: String,
    default: 'primary',
  },
  density: {
    type: String,
    default: 'compact',
    validator: value =>
      ['default', 'comfortable', 'compact'].includes(value),
  },
})

const breadcrumbs = computed(() => props.items)
</script>

<template>
  <nav aria-label="Breadcrumb">
    <VBreadcrumbs
      :items="breadcrumbs"
      :color="color"
      :density="density"
      class="app-breadcrumb"
    >
      <template #item="{ item, index }">
        <VBreadcrumbsItem
          :to="
            index === breadcrumbs.length - 1 || item.disabled
              ? undefined
              : item.to
          "
          :disabled="item.disabled"
          :active="index === breadcrumbs.length - 1"
          :aria-current="
            index === breadcrumbs.length - 1 ? 'page' : undefined
          "
          :class="{
            'app-breadcrumb__item--current':
              index === breadcrumbs.length - 1,
          }"
          class="app-breadcrumb__item"
        >
          <VIcon
            v-if="item.icon"
            :icon="item.icon"
            size="16"
            aria-hidden="true"
            class="app-breadcrumb__icon"
          />

          <span class="app-breadcrumb__title">
            {{ item.title }}
          </span>
        </VBreadcrumbsItem>
      </template>

      <template #divider>
        <VIcon
          icon="tabler-chevron-right"
          size="14"
          aria-hidden="true"
          class="app-breadcrumb__divider"
        />
      </template>
    </VBreadcrumbs>
  </nav>
</template>

<style scoped>
/* Floating breadcrumb container */
.app-breadcrumb {
  display: inline-flex;
  overflow: auto hidden;
  align-items: center;
  border:
    1px solid
    rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  margin: 0;
  background: rgb(var(--v-theme-surface));
  box-shadow:
    0 2px 6px rgba(var(--v-theme-on-surface), 0.05),
    0 1px 2px rgba(var(--v-theme-on-surface), 0.03);
  max-inline-size: 100%;
  min-inline-size: 0;
  overscroll-behavior-inline: contain;
  padding-block: 3px;
  padding-inline: 5px;
  scrollbar-width: thin;
  white-space: nowrap;
}

/* Individual breadcrumb badges */
.app-breadcrumb__item {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  border-radius: 5px;
  color:
    rgba(
      var(--v-theme-on-surface),
      var(--v-medium-emphasis-opacity)
    );
  font-size: 0.8125rem;
  gap: 5px;
  line-height: 1.25rem;
  min-block-size: 27px;
  padding-block: 3px;
  padding-inline: 8px;
  text-decoration: none;
  transition:
    background-color 150ms ease,
    color 150ms ease;
  white-space: nowrap;
}

/* Subtle badge hover */
.app-breadcrumb__item:not(.v-breadcrumbs-item--disabled, .app-breadcrumb__item--current):hover {
  background-color: rgba(var(--v-theme-primary), 0.06);
  color: rgb(var(--v-theme-primary));
}

/* Active page badge */
.app-breadcrumb__item--current {
  background-color: rgba(var(--v-theme-primary), 0.1);
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
}

/* Icons and separators */
.app-breadcrumb__icon,
.app-breadcrumb__divider {
  flex: 0 0 auto;
}

.app-breadcrumb__divider {
  opacity: 0.55;
}

/* Long titles */
.app-breadcrumb__title {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Keyboard accessibility */
.app-breadcrumb__item:focus-visible {
  outline: 2px solid rgba(var(--v-theme-primary), 0.7);
  outline-offset: 1px;
}

/* Compact mobile layout */
@media (max-width: 600px) {
  .app-breadcrumb {
    border-radius: 7px;
    max-inline-size: 100%;
    padding-block: 2px;
    padding-inline: 4px;
  }

  .app-breadcrumb__item {
    font-size: 0.75rem;
    gap: 4px;
    min-block-size: 25px;
    padding-block: 2px;
    padding-inline: 6px;
  }

  .app-breadcrumb__title {
    max-inline-size: 12rem;
  }
}

/* Respect reduced-motion preferences */
@media (prefers-reduced-motion: reduce) {
  .app-breadcrumb__item {
    transition: none;
  }
}
</style>
