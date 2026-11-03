/* eslint-disable camelcase */
import { buildAreaBreadcrumb, getAreaRecursiveEquipmentCount } from '@/composables/client-assets/sites/areaHierarchyHelper'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, ref } from 'vue'

export function useSiteAreas(targetSiteId) {
  const store = useClientAssetStore()

  const loadingAreas = ref(false)
  const siteAreasTree = ref([])
  const siteAreasFlat = ref([])
  const areasViewMode = ref('tree')
  const currentDrillAreaId = ref(null)
  const expandedAreaIds = ref(new Set())

  const toggleAreaExpand = id => {
    if (expandedAreaIds.value.has(id)) expandedAreaIds.value.delete(id)
    else expandedAreaIds.value.add(id)
  }

  const expandAllAreas = () => {
    siteAreasFlat.value.forEach(a => expandedAreaIds.value.add(a.id))
  }

  const collapseAllAreas = () => {
    expandedAreaIds.value.clear()
  }

  const fetchAreas = async () => {
    const siteId = typeof targetSiteId === 'function' ? targetSiteId() : (targetSiteId?.value ?? targetSiteId)
    if (!siteId) return

    loadingAreas.value = true
    try {
      const res = await store.fetchSiteAreas(siteId)

      siteAreasTree.value = res.tree || []
      siteAreasFlat.value = res.flat || []

      siteAreasFlat.value.forEach(a => {
        if (!a.parent_area_id) expandedAreaIds.value.add(a.id)
      })
    }
    catch (e) {
      console.error('Error fetching site areas:', e)
    }
    finally {
      loadingAreas.value = false
    }
  }

  const flattenedHierarchy = computed(() => {
    const result = []

    const traverse = (items, depth = 0) => {
      for (const item of items) {
        const isExpanded = expandedAreaIds.value.has(item.id)
        const directChildren = siteAreasFlat.value.filter(a => a.parent_area_id === item.id)
        const hasChildren = directChildren.length > 0
        const counts = getAreaRecursiveEquipmentCount(item.id, siteAreasFlat.value)

        result.push({
          ...item,
          depth,
          hasChildren,
          isExpanded,
          childCount: directChildren.length,
          direct_equipment_count: counts.direct,
          nested_equipment_count: counts.nested,
          total_equipment_count: counts.total,
        })

        if (hasChildren && isExpanded) {
          traverse(directChildren, depth + 1)
        }
      }
    }

    const rootItems = siteAreasFlat.value.filter(a => !a.parent_area_id)

    traverse(rootItems, 0)

    return result
  })

  const currentCardsAreas = computed(() => {
    return siteAreasFlat.value
      .filter(a => a.parent_area_id === currentDrillAreaId.value)
      .map(item => {
        const directChildren = siteAreasFlat.value.filter(a => a.parent_area_id === item.id)
        const counts = getAreaRecursiveEquipmentCount(item.id, siteAreasFlat.value)

        return {
          ...item,
          hasChildren: directChildren.length > 0,
          childCount: directChildren.length,
          direct_equipment_count: counts.direct,
          nested_equipment_count: counts.nested,
          total_equipment_count: counts.total,
        }
      })
  })

  const cardsBreadcrumbs = computed(() => {
    if (!currentDrillAreaId.value) return []

    return buildAreaBreadcrumb(currentDrillAreaId.value, siteAreasFlat.value)
  })

  return {
    loadingAreas,
    siteAreasTree,
    siteAreasFlat,
    areasViewMode,
    currentDrillAreaId,
    expandedAreaIds,
    flattenedHierarchy,
    currentCardsAreas,
    cardsBreadcrumbs,
    fetchAreas,
    toggleAreaExpand,
    expandAllAreas,
    collapseAllAreas,
  }
}
