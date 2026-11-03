<!--
  WpJournalNav
  ─────────────────────────────────────────────────────────────────────────────
  Reusable navigation sidebar for WAVE PLUS journal edit pages and view dialogs.
  - Desktop (≥960px): Elevated/glassmorphic sticky navigation sidebar.
  - Mobile (<960px): Floating/docked bottom navigation bar (using AppBottomNav).
-->
<script setup>
import AppBottomNav from '@/components/shared/nav/AppBottomNav.vue'
import { useJournalNav } from '@/composables/useJournalNav'
import { avatarText as defaultAvatarText } from '@core/utils/formatters'
import { computed, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  active: {
    type: String,
    default: '',
  },
  sections: {
    type: Array,
    required: true,
  },
  idPrefix: {
    type: [String, Array],
    default: 'section-',
  },
  container: {
    type: [String, Object],
    default: null,
  },
  isDialog: {
    type: Boolean,
    default: false,
  },
  showMobileNav: {
    type: Boolean,
    default: true,
  },
  title: {
    type: String,
    default: '',
  },
  subtitle: {
    type: String,
    default: '',
  },
  avatar: {
    type: String,
    default: '',
  },
  avatarText: {
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
  summaryTitle: {
    type: String,
    default: '',
  },
  summaryItems: {
    type: Array,
    default: () => [],
  },
  autoScroll: {
    type: Boolean,
    default: true,
  },
  readonly: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'update:active', 'change', 'navigate'])

const { t } = useI18n()
const { smAndDown } = useDisplay()

const sectionsRef = toRef(props, 'sections')

const {
  activeSection,
  isGrouped,
  groups,
  flatSections,
  mobileNavItems,
  scrollToSection,
  setupObserver,
  cleanupObserver,
} = useJournalNav(sectionsRef, {
  defaultSection: props.modelValue || props.active || undefined,
  idPrefix: props.idPrefix,
  container: computed(() => props.container),
  autoSetupObserver: true,
})

// Sync internal active with props
const currentActive = computed(() => props.modelValue || props.active || activeSection.value)

watch(() => props.modelValue, val => {
  if (val && val !== activeSection.value) activeSection.value = val
})

watch(() => props.active, val => {
  if (val && val !== activeSection.value) activeSection.value = val
})

watch(activeSection, val => {
  emit('update:modelValue', val)
  emit('update:active', val)
  emit('change', val)
})

const resolvedAvatarText = computed(() => {
  if (props.avatarText) return props.avatarText
  if (props.title) return defaultAvatarText(props.title)

  return ''
})

const handleNavClick = sectionId => {
  activeSection.value = sectionId
  emit('navigate', sectionId)
  if (props.autoScroll) {
    scrollToSection(sectionId)
  }
}

defineExpose({
  activeSection,
  flatSections,
  mobileNavItems,
  scrollToSection,
  setupObserver,
  cleanupObserver,
})
</script>

<template>
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- 1. Desktop Sticky Navigation Sidebar                                    -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <aside
    v-if="!smAndDown"
    class="wp-journal-nav glass-panel"
    :class="{ 'wp-journal-nav--dialog': isDialog }"
  >
    <!-- Header / Identity Slot -->
    <slot name="header">
      <div
        v-if="title || $slots.avatar"
        class="d-flex align-center mb-3"
      >
        <slot name="avatar">
          <VAvatar
            rounded="lg"
            :size="38"
            :color="!avatar ? avatarColor : undefined"
            :variant="!avatar ? 'tonal' : undefined"
          >
            <VImg
              v-if="avatar"
              :src="avatar"
              alt=""
            />
            <VIcon
              v-else-if="avatarIcon"
              :icon="avatarIcon"
              size="20"
            />
            <span
              v-else
              class="text-subtitle-2 font-weight-bold"
            >
              {{ resolvedAvatarText }}
            </span>
          </VAvatar>
        </slot>

        <div class="ms-3 overflow-hidden min-w-0">
          <h6
            class="text-subtitle-2 font-weight-bold text-truncate mb-0"
            style="max-inline-size: 140px;"
          >
            {{ title }}
          </h6>
          <span
            v-if="subtitle"
            class="text-caption text-disabled text-truncate d-block"
          >
            {{ subtitle }}
          </span>
        </div>
      </div>
    </slot>

    <VDivider
      v-if="$slots.header || title"
      class="mb-3"
    />

    <!-- Navigation Scroll Area -->
    <PerfectScrollbar
      :options="{ wheelPropagation: false }"
      class="wp-journal-nav__scroll"
    >
      <!-- Grouped Sections (e.g. HR / Employees) -->
      <template v-if="isGrouped">
        <div
          v-for="g in groups"
          :key="g.id"
          class="mb-3"
        >
          <div
            v-if="g.label"
            class="text-caption font-weight-bold text-uppercase text-medium-emphasis px-2 mb-1"
          >
            {{ g.label }}
          </div>
          <VList
            density="compact"
            nav
            class="bg-transparent pa-0"
          >
            <VListItem
              v-for="s in g.sections"
              :key="s.id"
              :active="currentActive === s.id"
              :disabled="s.disabled"
              color="primary"
              rounded="lg"
              class="mb-1"
              @click="handleNavClick(s.id)"
            >
              <template
                v-if="s.icon"
                #prepend
              >
                <VIcon
                  :icon="s.icon"
                  size="18"
                />
              </template>
              <VListItemTitle class="text-body-2 font-weight-medium">
                {{ s.title }}
              </VListItemTitle>
              <template
                v-if="s.badge !== null && s.badge !== undefined"
                #append
              >
                <VChip
                  size="x-small"
                  label
                  :color="s.badgeColor || 'primary'"
                  variant="tonal"
                >
                  {{ s.badge }}
                </VChip>
              </template>
            </VListItem>
          </VList>
        </div>
      </template>

      <!-- Flat Sections -->
      <VList
        v-else
        density="compact"
        nav
        class="bg-transparent pa-0"
      >
        <VListItem
          v-for="s in flatSections"
          :key="s.id"
          :active="currentActive === s.id"
          :disabled="s.disabled"
          color="primary"
          rounded="lg"
          class="mb-1"
          @click="handleNavClick(s.id)"
        >
          <template
            v-if="s.icon"
            #prepend
          >
            <VIcon
              :icon="s.icon"
              size="18"
            />
          </template>
          <VListItemTitle class="text-body-2 font-weight-medium">
            {{ s.title }}
          </VListItemTitle>
          <template
            v-if="s.badge !== null && s.badge !== undefined"
            #append
          >
            <VChip
              size="x-small"
              label
              :color="s.badgeColor || 'primary'"
              variant="tonal"
            >
              {{ s.badge }}
            </VChip>
          </template>
        </VListItem>
      </VList>

      <!-- Relations / Summary Counters -->
      <template v-if="summaryItems?.length || $slots.summary">
        <VDivider class="my-4" />
        <slot name="summary">
          <div class="px-2 pb-2">
            <span class="text-caption text-uppercase text-disabled d-block mb-2 font-weight-medium">
              {{ summaryTitle || t('Relations') }}
            </span>
            <div
              v-for="item in summaryItems"
              :key="item.label"
              class="d-flex align-center gap-x-2 mb-2"
            >
              <VIcon
                :icon="item.icon"
                size="16"
                color="primary"
              />
              <span class="text-caption">{{ item.label }}</span>
              <VSpacer />
              <VChip
                size="x-small"
                label
                color="primary"
                variant="tonal"
              >
                {{ item.count }}
              </VChip>
            </div>
          </div>
        </slot>
      </template>
    </PerfectScrollbar>

    <!-- Footer Actions Slot (Save, Delete, Edit buttons) -->
    <div
      v-if="$slots.footer"
      class="wp-journal-nav__footer mt-auto pt-3 border-t"
    >
      <slot name="footer" />
    </div>
  </aside>

  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <!-- 2. Mobile Responsive Bottom Navigation                                  -->
  <!-- ═══════════════════════════════════════════════════════════════════════ -->
  <AppBottomNav
    v-if="smAndDown && showMobileNav"
    :model-value="currentActive"
    :items="mobileNavItems"
    fit-content
    :show-badges="false"
    class="wp-journal-bottom-nav"
    :class="{ 'wp-journal-bottom-nav--dialog': isDialog }"
    aria-label="Section navigation"
    @change="item => handleNavClick(item.value)"
  />
</template>

<style lang="scss" scoped>
/* ── Desktop Sticky Sidebar ──────────────────────────────────────────── */
.wp-journal-nav {
  position: sticky;
  display: flex;
  flex: 0 0 220px;
  flex-direction: column;
  border-radius: 14px;
  inset-block-start: 80px;
  max-block-size: calc(100vh - 100px);
  padding-block: 1rem;
  padding-inline: 0.75rem;

  &--dialog {
    position: sticky;
    inset-block-start: 0;
    max-block-size: 100%;
  }
}

.wp-journal-nav__scroll {
  overflow-x: hidden;
  flex: 1;
  min-block-size: 0;
}

.wp-journal-nav__footer {
  inline-size: 100%;
}

/* ── Glassmorphism styling ───────────────────────────────────────────── */
.glass-panel {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 14px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.8) !important;
  box-shadow:
    0 4px 24px rgba(0, 0, 0, 4%),
    0 1px 4px rgba(0, 0, 0, 3%);
}

/* ── Mobile Bottom Navigation override for Dialogs ────────────────────── */
:deep(.wp-journal-bottom-nav--dialog.app-bottom-nav),
.wp-journal-bottom-nav--dialog :deep(.app-bottom-nav),
.wp-journal-bottom-nav--dialog {
  z-index: 2500 !important;
}
</style>
