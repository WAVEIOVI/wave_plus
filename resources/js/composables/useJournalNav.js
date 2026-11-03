import { computed, isRef, nextTick, onBeforeUnmount, onMounted, ref, unref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * useJournalNav
 * ─────────────────────────────────────────────────────────────────────────────
 * Composable for standardizing multi-section journal/workspace navigation across
 * WAVE PLUS edit pages and view dialogs.
 *
 * Provides:
 * - Normalization for both flat and grouped sections.
 * - Reactive activeSection tracking with programmatic lock.
 * - mobileNavItems formatted specifically for AppBottomNav.
 * - Unified scrollToSection supporting both window and scrollable dialog containers.
 * - IntersectionObserver lifecycle management for scroll-spy.
 *
 * @param {Array|Ref} sectionsOrGroups
 * @param {Object} [options]
 * @param {string} [options.defaultSection] - Initial active section ID
 * @param {string|string[]} [options.idPrefix] - Element ID prefix (e.g. 'section-', 'dialog-section-')
 * @param {string|HTMLElement|Ref} [options.container] - Scroll container selector or element
 * @param {number} [options.threshold=0.2] - IntersectionObserver threshold
 * @param {string} [options.rootMargin='-80px 0px -40% 0px'] - IntersectionObserver rootMargin
 * @param {number} [options.scrollOffset=16] - Top offset in px for scroll
 * @param {boolean} [options.autoSetupObserver=true] - Setup observer on mount
 */
export function useJournalNav(sectionsOrGroups, options = {}) {
  let i18n = null
  try {
    i18n = useI18n()
  } catch {
    // Graceful fallback for non-Vue-I18n contexts / unit test environments
    i18n = null
  }

  const resolveLabel = s => {
    if (!s) return ''
    if (s.title) return s.title
    if (i18n?.t && s.label) {
      const translated = i18n.t(s.label, s.defaultLabel || s.label)
      if (translated && translated !== s.label) return translated
    }
    if (s.defaultLabel) return s.defaultLabel
    if (s.label) {
      // If it looks like an untranslated i18n key e.g. 'clients.edit.sections.bio', fall back to s.id
      if (s.label.includes('.')) {
        return s.defaultLabel || s.id
      }

      return s.label
    }

    return s.id || ''
  }

  const rawSections = computed(() => unref(sectionsOrGroups) || [])

  // Detect whether sections are grouped (e.g. HR employees: [{ id, label, sections: [...] }])
  const isGrouped = computed(() => {
    const list = rawSections.value

    return Array.isArray(list) && list.length > 0 && Array.isArray(list[0]?.sections)
  })

  // Normalized grouped structure
  const groups = computed(() => {
    if (!isGrouped.value) {
      return [{
        id: 'default',
        label: '',
        sections: flatSections.value,
      }]
    }

    return rawSections.value.map(group => ({
      id: group.id,
      label: resolveLabel(group),
      defaultLabel: group.defaultLabel || group.id,
      sections: (group.sections || []).map(s => ({
        id: s.id,
        title: resolveLabel(s),
        icon: s.icon || 'tabler-circle',
        badge: s.badge ?? null,
        badgeColor: s.badgeColor || 'primary',
        disabled: Boolean(s.disabled),
        group: group.id,
        raw: s,
      })),
    }))
  })

  // Normalized flat list of all sections
  const flatSections = computed(() => {
    if (isGrouped.value) {
      return groups.value.flatMap(g => g.sections)
    }

    return rawSections.value.map(s => ({
      id: s.id,
      title: resolveLabel(s),
      icon: s.icon || 'tabler-circle',
      badge: s.badge ?? null,
      badgeColor: s.badgeColor || 'primary',
      disabled: Boolean(s.disabled),
      raw: s,
    }))
  })

  // Formatted for AppBottomNav
  const mobileNavItems = computed(() => flatSections.value.map(s => ({
    key: s.id,
    value: s.id,
    title: s.title,
    icon: s.icon,
    badge: s.badge,
    disabled: s.disabled,
  })))

  // Active section tracking
  const initialId = options.defaultSection || flatSections.value[0]?.id || ''
  const activeSection = ref(initialId)
  const isManualScrolling = ref(false)
  let lockTimer = null
  let observer = null

  // Ensure activeSection is valid when sections change
  watch(flatSections, list => {
    if (list.length && (!activeSection.value || !list.some(s => s.id === activeSection.value))) {
      activeSection.value = list[0].id
    }
  }, { immediate: true })

  // Prefix handling: array of candidate prefixes to find elements across different conventions
  const prefixes = computed(() => {
    const custom = options.idPrefix

    const defaults = [
      'section-',
      'dialog-section-',
      'dialog-org-section-',
      'dialog-supplier-section-',
      'dialog-sp-section-',
      'view-',
      'guideline-view-',
    ]

    if (Array.isArray(custom)) return [...new Set([...custom, ...defaults])]
    if (typeof custom === 'string' && custom.length > 0) {
      return [...new Set([custom, ...defaults])]
    }

    return defaults
  })

  /**
   * Resolves the DOM container element if one is specified
   */
  const resolveContainer = () => {
    const target = unref(options.container)
    if (!target) return null
    if (typeof target === 'string') {
      return typeof document !== 'undefined' ? document.querySelector(target) : null
    }
    if (typeof target === 'object' && target instanceof HTMLElement) {
      return target
    }

    return null
  }

  /**
   * Finds the DOM element for a given section ID using candidate prefixes
   */
  const findSectionElement = sectionId => {
    if (typeof document === 'undefined' || !sectionId) return null

    const containerEl = resolveContainer()
    const scope = containerEl || document

    // 1. Try with specified prefixes
    for (const prefix of prefixes.value) {
      const fullId = `${prefix}${sectionId}`
      const el = scope.querySelector ? scope.querySelector(`#${CSS.escape(fullId)}`) : document.getElementById(fullId)
      if (el) return el
    }

    // 2. Try exact ID
    const exact = scope.querySelector ? scope.querySelector(`#${CSS.escape(sectionId)}`) : document.getElementById(sectionId)
    if (exact) return exact

    // 3. Try data-section-id attribute
    const byAttr = scope.querySelector ? scope.querySelector(`[data-section-id="${CSS.escape(sectionId)}"]`) : null
    if (byAttr) return byAttr

    return null
  }

  /**
   * Smoothly scroll to target section with lock to prevent observer flutter
   */
  const scrollToSection = (sectionId, customOffset) => {
    if (!sectionId) return
    activeSection.value = sectionId

    isManualScrolling.value = true
    if (lockTimer) clearTimeout(lockTimer)
    lockTimer = setTimeout(() => {
      isManualScrolling.value = false
    }, 750)

    if (typeof window === 'undefined') return

    nextTick(() => {
      const el = findSectionElement(sectionId)
      if (!el) return

      const offset = customOffset ?? options.scrollOffset ?? 16
      const containerEl = resolveContainer()

      // Always call scrollIntoView to guarantee native scrolling across dialogs and PerfectScrollbar
      if (typeof el.scrollIntoView === 'function') {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' })
      }

      if (containerEl) {
        try {
          const cRect = containerEl.getBoundingClientRect()
          const eRect = el.getBoundingClientRect()
          const top = Math.max(0, eRect.top - cRect.top + containerEl.scrollTop - offset)

          if (typeof containerEl.scrollTo === 'function') {
            containerEl.scrollTo({ top, behavior: 'smooth' })
          }

          // Direct scrollTop assignment works even when PerfectScrollbar has overflow: hidden
          containerEl.scrollTop = top
        } catch {
          // Ignore rect calculation errors in detached/mock environments
        }
      }
    })
  }

  /**
   * Setup IntersectionObserver on all visible sections
   */
  const setupObserver = customContainer => {
    cleanupObserver()
    if (typeof IntersectionObserver === 'undefined' || typeof document === 'undefined') return

    const containerEl = customContainer || resolveContainer()

    observer = new IntersectionObserver(entries => {
      if (isManualScrolling.value) return

      // Find the first intersecting entry
      const intersecting = entries.filter(e => e.isIntersecting)
      if (!intersecting.length) return

      // Pick the entry closest to top
      const sorted = [...intersecting].sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)
      const currentEntry = sorted[0]
      if (!currentEntry) return

      // Extract section ID from data-section-id or DOM id
      const targetEl = currentEntry.target
      let matchedId = targetEl.dataset?.sectionId || ''

      if (!matchedId) {
        const rawId = targetEl.id || ''
        for (const prefix of prefixes.value) {
          if (rawId.startsWith(prefix)) {
            matchedId = rawId.slice(prefix.length)
            break
          }
        }
        if (!matchedId) matchedId = rawId
      }

      if (matchedId && flatSections.value.some(s => s.id === matchedId)) {
        activeSection.value = matchedId
      }
    }, {
      root: containerEl || null,
      threshold: options.threshold ?? 0.2,
      rootMargin: options.rootMargin || '-80px 0px -40% 0px',
    })

    // Observe each section element found in DOM
    nextTick(() => {
      flatSections.value.forEach(s => {
        const el = findSectionElement(s.id)
        if (el) observer?.observe(el)
      })
    })
  }

  const cleanupObserver = () => {
    if (lockTimer) {
      clearTimeout(lockTimer)
      lockTimer = null
    }
    if (observer) {
      observer.disconnect()
      observer = null
    }
  }

  // Automatic component lifecycle registration
  if (options.autoSetupObserver !== false) {
    try {
      onMounted(() => {
        // Small delay to ensure all section DOM elements are rendered
        setTimeout(() => setupObserver(), 150)
      })
      onBeforeUnmount(cleanupObserver)
    } catch {
      // Ignore when called outside setup context (e.g. tests)
    }
  }

  return {
    activeSection,
    isGrouped,
    groups,
    flatSections,
    mobileNavItems,
    isManualScrolling,
    findSectionElement,
    scrollToSection,
    setupObserver,
    cleanupObserver,
  }
}
