<script setup>
/**
 * WpEditPageLayout
 * ─────────────────────────────────────────────────────────────────────────────
 * Fixed-window edit page shell distilled from the Client Edit "journal" page.
 * The window never grows: breadcrumb + identity header on top, section
 * navigation, ONE native-scrolling content region, and an action footer.
 *
 * Presets (`preset`): standard (tabs) · developed (sidebar + scroll-spy)
 *                     · deeper (developed + grouped secondary sections + badges)
 *
 * <WpEditPageLayout
 *   v-model="section"
 *   preset="developed"
 *   :breadcrumbs="[{ title: $t('Home'), to: '/', icon: 'tabler-home' }, { title: client.name }]"
 *   :title="client.name" :subtitle="client.code" status="active" status-type="client"
 *   :back-to="{ name: 'business-partners-clients-list' }"
 *   :sections="[{ id: 'general', title: $t('…'), icon: 'tabler-user' },
 *               { id: 'documents', title: $t('…'), group: 'secondary', badge: 3 }]"
 *   :section-errors="{ general: 2 }"
 *   :loading="loading" :error="error" :forbidden="!$can('read', 'Clients')"
 *   :dirty="isDirty" :saving="saving"
 *   @save="save" @discard="discard" @cancel="router.back()" @retry="load"
 * >
 *   <template #section-general> …WpSectionCard / form… </template>
 *   <template #actions> …header action buttons… </template>
 * </WpEditPageLayout>
 *
 * Responsive: ≥960px sidebar (PerfectScrollbar on the nav only); below that the
 * section nav becomes scrollable tabs, the footer actions stretch and the
 * content region keeps native touch scrolling (no nested scrollers).
 *
 * Slots   section-<id> · section-actions-<id> · actions · meta · identity · sidebar-footer
 *         footer-actions · footer-start · banner
 * Emits   update:modelValue · save · discard · cancel · retry
 *
 * Leaving the route (or closing the tab) while `dirty` asks for confirmation.
 * Ctrl/⌘+S emits `save`. Authorization is the backend's job: hide/show of
 * sections is only a presentation hint (`section.hidden`, `forbidden`).
 */
import { useUnsavedChangesGuard } from '@/composables/dialog/useUnsavedChangesGuard'
import { resolveEditPreset } from '@/composables/shared/editLayoutPresets'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'

const props = defineProps({
  preset: { type: String, default: 'standard' },
  /** Active section id (v-model). */
  modelValue: { type: String, default: '' },
  sections: { type: Array, default: () => [] },
  /** { [sectionId]: number of validation problems } */
  sectionErrors: { type: Object, default: () => ({}) },
  breadcrumbs: { type: Array, default: () => [] },

  // Identity (forwarded to WpEntityHeader)
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  avatar: { type: String, default: '' },
  initials: { type: String, default: '' },
  icon: { type: String, default: 'tabler-user' },
  status: { type: [String, Number], default: '' },
  statusType: { type: String, default: '' },
  statusLabel: { type: String, default: '' },
  backTo: { type: [String, Object], default: undefined },

  // Page state
  loading: { type: Boolean, default: false },
  error: { type: [String, Object], default: null },
  empty: { type: Boolean, default: false },
  forbidden: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },

  // Editing
  dirty: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
  canSave: { type: Boolean, default: true },
  saveLabel: { type: String, default: '' },
  guardUnsaved: { type: Boolean, default: true },

  // Geometry
  /** Render inside a bordered frame of fixed `height` (docs/playground, dialogs). */
  embedded: { type: Boolean, default: false },
  height: { type: [Number, String], default: 640 },
  /** Space (px) above the window used by navbar + page padding. */
  offset: { type: Number, default: undefined },

  // Overrides of the preset
  nav: { type: String, default: undefined, validator: v => v === undefined || ['tabs', 'sidebar'].includes(v) },
  footer: { type: String, default: undefined },
  sectionHeaders: { type: Boolean, default: undefined },
})

const emit = defineEmits(['update:modelValue', 'save', 'discard', 'cancel', 'retry'])

const { t } = useI18n()
const { smAndDown, mdAndUp } = useDisplay()
const uid = useId()

const preset = computed(() => resolveEditPreset(props.preset, {
  nav: props.nav,
  footer: props.footer,
  sectionHeaders: props.sectionHeaders,
}))

// ── Sections ─────────────────────────────────────────────────────────────────
const visibleSections = computed(() => props.sections.filter(s => !s.hidden))
const internalActive = ref('')

const active = computed(() => {
  const wanted = props.modelValue || internalActive.value

  return visibleSections.value.some(s => s.id === wanted) ? wanted : visibleSections.value[0]?.id || ''
})

const useSidebar = computed(() => preset.value.nav === 'sidebar' && mdAndUp.value)
const isFeed = computed(() => preset.value.nav === 'sidebar') // all sections stacked (scroll-spy)
const showTabs = computed(() => !useSidebar.value && visibleSections.value.length > 1)

const groups = computed(() => {
  const primary = visibleSections.value.filter(s => s.group !== 'secondary')
  const secondary = visibleSections.value.filter(s => s.group === 'secondary')

  if (!preset.value.groupSecondary || !secondary.length) return [{ key: 'all', label: '', items: visibleSections.value }]

  return [
    { key: 'primary', label: t('wp.edit.main_sections'), items: primary },
    { key: 'secondary', label: t('wp.edit.related'), items: secondary },
  ]
})

const mountedIds = ref([])

watch(active, id => {
  if (id && !mountedIds.value.includes(id)) mountedIds.value.push(id)
}, { immediate: true })

const domId = id => `${uid}-section-${id}`

// ── Scrolling / scroll-spy ───────────────────────────────────────────────────
const scrollRef = ref(null)
let observer = null
let lockUntil = 0
const intersecting = new Set()

const reducedMotion = () => typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

const setActive = id => {
  internalActive.value = id
  emit('update:modelValue', id)
}

const scrollToSection = id => {
  const container = scrollRef.value
  const el = container?.querySelector(`#${CSS.escape(domId(id))}`)

  if (!container || !el) return

  const top = el.getBoundingClientRect().top - container.getBoundingClientRect().top + container.scrollTop - 4

  container.scrollTo({ top, behavior: reducedMotion() ? 'auto' : 'smooth' })
}

const goTo = id => {
  if (!id) return
  lockUntil = Date.now() + 700 // ignore the spy while the programmatic scroll runs
  setActive(id)
  if (isFeed.value) nextTick(() => scrollToSection(id))
  else scrollRef.value?.scrollTo({ top: 0 })
}

const teardownSpy = () => {
  observer?.disconnect()
  observer = null
  intersecting.clear()
}

const setupSpy = async () => {
  teardownSpy()
  await nextTick()
  if (!isFeed.value || !scrollRef.value || typeof IntersectionObserver === 'undefined') return

  observer = new IntersectionObserver(entries => {
    entries.forEach(e => {
      const id = e.target.dataset.sectionId

      if (e.isIntersecting) intersecting.add(id)
      else intersecting.delete(id)
    })

    if (Date.now() < lockUntil) return

    const first = visibleSections.value.find(s => intersecting.has(s.id))

    if (first && first.id !== active.value) setActive(first.id)
  }, { root: scrollRef.value, rootMargin: '0px 0px -65% 0px', threshold: 0 })

  scrollRef.value.querySelectorAll('[data-section-id]').forEach(el => observer.observe(el))
}

const pageState = computed(() => {
  if (props.loading) return 'loading'
  if (props.error) return 'error'
  if (props.empty) return 'empty'

  return 'ready'
})

watch([isFeed, () => visibleSections.value.map(s => s.id).join('|'), pageState], setupSpy, { flush: 'post' })
onMounted(setupSpy)
onBeforeUnmount(teardownSpy)

// ── Dirty handling ───────────────────────────────────────────────────────────
const guardActive = computed(() => props.guardUnsaved && !props.embedded && props.dirty)
const { isDialogVisible, confirmLeave, cancelLeave } = useUnsavedChangesGuard(guardActive)
const discardOpen = ref(false)

useEventListener(window, 'beforeunload', e => {
  if (guardActive.value) {
    e.preventDefault()
    e.returnValue = ''
  }
})

useEventListener(document, 'keydown', e => {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's' && props.dirty && props.canSave && !props.saving && !props.readonly) {
    e.preventDefault()
    emit('save')
  }
})

const onCancel = () => {
  if (props.dirty) discardOpen.value = true
  else emit('cancel')
}

const showFooter = computed(() => !props.readonly && !props.forbidden && pageState.value === 'ready'
  && (preset.value.footer === 'always' || props.dirty))

// ── Geometry ─────────────────────────────────────────────────────────────────
const rootStyle = computed(() => {
  if (props.embedded) return { blockSize: typeof props.height === 'number' ? `${props.height}px` : props.height }

  const offset = props.offset ?? (smAndDown.value ? 84 : 120)

  return { blockSize: `calc(100dvh - ${offset}px)` }
})

const contentStyle = computed(() => ({ maxInlineSize: `${preset.value.contentMaxWidth}px` }))
const errorOf = s => props.sectionErrors?.[s.id] || 0
const totalErrors = computed(() => Object.values(props.sectionErrors || {}).reduce((a, b) => a + (Number(b) || 0), 0))

defineExpose({ goTo, scrollToSection })
</script>

<template>
  <div
    class="wp-edit"
    :class="[`wp-edit--${preset.name}`, { 'wp-edit--embedded': embedded, 'wp-edit--mobile': smAndDown }]"
    :style="rootStyle"
  >
    <AppBreadcrumb
      v-if="breadcrumbs.length"
      :items="breadcrumbs"
      class="wp-edit__crumbs"
    />

    <WpStateBlock
      v-if="forbidden"
      state="forbidden"
      class="wp-edit__full"
    >
      <template #forbidden-action>
        <VBtn
          v-if="backTo"
          variant="tonal"
          prepend-icon="tabler-arrow-left"
          :to="backTo"
        >
          {{ t('wp.states.back') }}
        </VBtn>
      </template>
    </WpStateBlock>

    <template v-else>
      <!-- ── Fixed header ─────────────────────────────────────────────── -->
      <div class="wp-edit__header">
        <slot name="identity">
          <WpEntityHeader
            :title="title || ' '"
            :subtitle="subtitle"
            :avatar="avatar"
            :initials="initials"
            :icon="icon"
            :status="status"
            :status-type="statusType"
            :status-label="statusLabel"
            :back-to="backTo"
          >
            <template
              v-if="$slots.meta"
              #meta
            >
              <slot name="meta" />
            </template>
            <template
              v-if="$slots.actions"
              #actions
            >
              <slot name="actions" />
            </template>
          </WpEntityHeader>
        </slot>
      </div>

      <!-- ── Section tabs (standard preset, or any preset on small screens) ── -->
      <VTabs
        v-if="showTabs"
        :model-value="active"
        class="wp-edit__tabs"
        show-arrows
        color="primary"
        density="comfortable"
        :aria-label="t('wp.edit.sections')"
        @update:model-value="goTo"
      >
        <VTab
          v-for="s in visibleSections"
          :key="s.id"
          :value="s.id"
          :disabled="s.disabled"
          class="text-none"
        >
          <VIcon
            v-if="s.icon"
            :icon="s.icon"
            size="18"
            start
          />
          {{ s.title }}
          <VBadge
            v-if="errorOf(s)"
            :content="errorOf(s)"
            color="error"
            inline
          />
          <VBadge
            v-else-if="s.badge !== undefined && s.badge !== null && s.badge !== ''"
            :content="s.badge"
            color="secondary"
            inline
          />
        </VTab>
      </VTabs>

      <!-- ── Shell: sidebar + the single scroll region ────────────────── -->
      <div class="wp-edit__shell">
        <aside
          v-if="useSidebar"
          class="wp-edit__sidebar"
          :style="{ inlineSize: `${preset.sidebarWidth}px` }"
        >
          <PerfectScrollbar
            :options="{ wheelPropagation: false }"
            class="wp-edit__sidebar-scroll"
          >
            <nav :aria-label="t('wp.edit.sections')">
              <template
                v-for="g in groups"
                :key="g.key"
              >
                <div
                  v-if="g.label"
                  class="text-overline text-disabled px-3 pt-3 pb-1"
                >
                  {{ g.label }}
                </div>
                <VList
                  nav
                  density="compact"
                  class="bg-transparent pa-0"
                >
                  <VListItem
                    v-for="s in g.items"
                    :key="s.id"
                    :active="active === s.id"
                    :disabled="s.disabled"
                    color="primary"
                    rounded="lg"
                    :aria-current="active === s.id ? 'true' : undefined"
                    @click="goTo(s.id)"
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
                    <VListItemTitle class="text-body-2">
                      {{ s.title }}
                    </VListItemTitle>
                    <template
                      v-if="errorOf(s) || (s.badge !== undefined && s.badge !== null && s.badge !== '')"
                      #append
                    >
                      <VChip
                        size="x-small"
                        label
                        :color="errorOf(s) ? 'error' : 'secondary'"
                        variant="tonal"
                      >
                        {{ errorOf(s) || s.badge }}
                      </VChip>
                    </template>
                  </VListItem>
                </VList>
              </template>
            </nav>
          </PerfectScrollbar>
          <div
            v-if="$slots['sidebar-footer']"
            class="wp-edit__sidebar-footer"
          >
            <slot name="sidebar-footer" />
          </div>
        </aside>

        <!-- Native scrolling: reliable on touch, no nested scroll traps -->
        <div
          ref="scrollRef"
          class="wp-edit__scroll"
        >
          <div
            class="wp-edit__content"
            :style="contentStyle"
          >
            <slot name="banner">
              <VAlert
                v-if="totalErrors && pageState === 'ready'"
                type="error"
                variant="tonal"
                density="comfortable"
                class="mb-4"
                role="alert"
              >
                {{ t('wp.edit.fix_errors', { count: totalErrors }) }}
              </VAlert>
            </slot>

            <WpStateBlock
              :state="pageState"
              :error="error"
              skeleton="article, list-item-avatar-two-line, table-row-divider, article"
              @retry="emit('retry')"
            >
              <section
                v-for="s in visibleSections"
                v-show="isFeed || s.id === active"
                :id="domId(s.id)"
                :key="s.id"
                :data-section-id="s.id"
                class="wp-edit__section"
                :aria-label="s.title"
              >
                <template v-if="isFeed || mountedIds.includes(s.id)">
                  <WpSectionHeader
                    v-if="preset.sectionHeaders"
                    :title="s.title"
                    :icon="s.icon"
                    :count="s.badge"
                    :level="2"
                    class="mb-3"
                  >
                    <template
                      v-if="$slots[`section-actions-${s.id}`]"
                      #actions
                    >
                      <slot
                        :name="`section-actions-${s.id}`"
                        :section="s"
                      />
                    </template>
                  </WpSectionHeader>

                  <slot
                    :name="`section-${s.id}`"
                    :section="s"
                    :active="s.id === active"
                  />
                </template>
              </section>
            </WpStateBlock>
          </div>
        </div>
      </div>

      <!-- ── Persistent action footer ─────────────────────────────────── -->
      <WpActionFooter
        v-if="showFooter"
        class="wp-edit__footer"
      >
        <template #start>
          <slot name="footer-start">
            <span
              v-if="dirty"
              class="d-inline-flex align-center gap-1 text-warning"
              role="status"
            >
              <VIcon
                icon="tabler-point-filled"
                size="16"
              />{{ t('wp.edit.unsaved') }}
            </span>
            <span
              v-else
              class="d-inline-flex align-center gap-1"
              role="status"
            >
              <VIcon
                icon="tabler-circle-check"
                size="16"
                color="success"
              />{{ t('wp.edit.saved') }}
            </span>
          </slot>
        </template>

        <slot name="footer-actions" />
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="saving"
          @click="onCancel"
        >
          {{ dirty ? t('wp.edit.discard') : t('wp.edit.close') }}
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-device-floppy"
          :loading="saving"
          :disabled="!dirty || !canSave || saving"
          @click="emit('save')"
        >
          {{ saveLabel || t('wp.edit.save') }}
        </VBtn>
      </WpActionFooter>
    </template>

    <WpConfirmDialog
      v-model="discardOpen"
      variant="discard"
      @confirm="emit('discard')"
    />

    <WpUnsavedChangesDialog
      v-model="isDialogVisible"
      @update:model-value="value => !value && cancelLeave()"
      @confirm="confirmLeave"
    />
  </div>
</template>

<style scoped>
.wp-edit {
  display: flex;
  flex-direction: column;
  min-block-size: 440px;
  gap: 12px;
}

.wp-edit--embedded {
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 14px;
  background: rgb(var(--v-theme-background));
}

.wp-edit__crumbs,
.wp-edit__header,
.wp-edit__tabs,
.wp-edit__footer {
  flex: 0 0 auto;
}

.wp-edit__header {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 14px;
  background: rgb(var(--v-theme-surface));
  padding-block: 12px;
  padding-inline: 16px;
}

.wp-edit__tabs {
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.wp-edit__shell {
  display: flex;
  flex: 1 1 auto;
  gap: 16px;
  min-block-size: 0;
}

.wp-edit__sidebar {
  display: flex;
  flex: 0 0 auto;
  flex-direction: column;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 14px;
  background: rgb(var(--v-theme-surface));
  min-block-size: 0;
  padding: 8px;
}

.wp-edit__sidebar-scroll {
  flex: 1 1 auto;
  min-block-size: 0;
}

.wp-edit__sidebar-footer {
  flex: 0 0 auto;
  padding: 8px;
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.wp-edit__scroll {
  flex: 1 1 auto;
  overflow-y: auto;
  min-inline-size: 0;
  overscroll-behavior: contain;
  padding-inline-end: 2px;
  -webkit-overflow-scrolling: touch;
}

.wp-edit__content {
  margin-inline: auto;
  padding-block-end: 8px;
}

.wp-edit__section + .wp-edit__section {
  margin-block-start: 24px;
}

.wp-edit__full {
  flex: 1 1 auto;
}

.wp-edit__footer {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 14px;
}

@media (max-width: 599px) {
  .wp-edit {
    gap: 8px;
  }

  .wp-edit__header {
    padding-block: 8px;
    padding-inline: 10px;
  }
}
</style>
