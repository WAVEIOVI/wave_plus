import { computed } from 'vue'
import { useRoute } from 'vue-router'

/**
 * Composable that filters navigation items based on the current route's `meta.module`.
 *
 * @param {Array} allNavItems - The full, unfiltered navigation array.
 * @returns {{ filteredNavItems: import('vue').ComputedRef<Array> }}
 */
export function useModuleNavigation(allNavItems) {
  const route = useRoute()

  const filteredNavItems = computed(() => {
    const currentModule = route.meta?.module || route.matched.find(m => m.meta?.module)?.meta?.module

    if (!currentModule)
      return allNavItems

    const items = allNavItems.filter(item => item.module === currentModule)
    
    return items.length ? items : allNavItems
  })

  return { filteredNavItems }
}
