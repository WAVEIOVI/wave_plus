import { resolveAreaTypeIcon } from '@/composables/client-assets/sites/useSiteConstants'
import { localizedValue } from '@/composables/useLocaleField'
import { computed, ref } from 'vue'

export const ROOT_KEY = '__root__'

export const isEmptyId = v => v == null || v === '' || v === 0 || v === '0'

export const idsEqual = (a, b) => {
  const isAEmpty = isEmptyId(a)
  const isBEmpty = isEmptyId(b)

  if (isAEmpty && isBEmpty) return true
  if (isAEmpty || isBEmpty) return false

  return String(a) === String(b)
}

export const safeString = val => {
  if (val == null) return ''
  if (typeof val === 'string') return val
  if (typeof val === 'number') return String(val)
  if (typeof val === 'object') {
    return Object.values(val)
      .map(v => (typeof v === 'string' ? v : (v != null ? String(v) : '')))
      .filter(Boolean)
      .join(' ')
  }

  return ''
}

export const resolveAreaName = (area, fallback = '') => {
  if (!area) return fallback
  const loc = localizedValue(area.name)

  if (loc && typeof loc === 'string') return loc
  if (typeof area.name === 'string') return area.name
  if (area.name && typeof area.name === 'object') {
    return area.name.en || area.name.fr || area.name.ar || Object.values(area.name)[0] || area.code || (area.id ? `Area #${area.id}` : fallback)
  }

  return area.code || (area.id ? `Area #${area.id}` : fallback)
}

export const resolveAreaDescription = area => {
  if (!area) return ''
  const loc = localizedValue(area.description)

  if (loc && typeof loc === 'string') return loc
  if (typeof area.description === 'string') return area.description
  if (area.description && typeof area.description === 'object') {
    return area.description.en || area.description.fr || area.description.ar || Object.values(area.description)[0] || ''
  }

  return ''
}

export const unwrapList = val => {
  if (!val) return []
  if (Array.isArray(val)) return val
  if (Array.isArray(val.flat_list)) return val.flat_list
  if (Array.isArray(val.flat)) return val.flat
  if (Array.isArray(val.data)) return val.data

  return []
}

export const childNodes = node => unwrapList(
  node?.children || node?.all_children || node?.allChildren || node?.areas || node?.sub_areas,
)

export const flattenTree = (nodes, parentId = null, acc = []) => {
  unwrapList(nodes).forEach(node => {
    const children = childNodes(node)

    acc.push({
      ...node,
      parent_area_id: node.parent_area_id ?? node.parent_id ?? parentId ?? null,
    })
    if (children.length) {
      flattenTree(children, node.id, acc)
    }
  })

  return acc
}

export const highlightParts = (text, query) => {
  const source = String(text ?? '')
  const q = query ? query.trim().toLowerCase() : ''

  if (!q) return [{ text: source, hit: false }]

  const parts = []
  const lower = source.toLowerCase()
  let cursor = 0

  while (cursor < source.length) {
    const idx = lower.indexOf(q, cursor)

    if (idx === -1) {
      parts.push({ text: source.slice(cursor), hit: false })
      break
    }
    if (idx > cursor) parts.push({ text: source.slice(cursor, idx), hit: false })
    parts.push({ text: source.slice(idx, idx + q.length), hit: true })
    cursor = idx + q.length
  }

  return parts
}

/**
 * Composable for managing hierarchical area tree state, breadcrumbs, search, and drill-down.
 *
 * @param {Object} options
 * @param {import('vue').Ref<Array>} options.areasRef
 * @param {import('vue').Ref<string>} options.searchQueryRef
 * @param {import('vue').Ref<string|number|null>} options.selectedAreaIdRef
 */
export function useSiteAreaTree({ areasRef, searchQueryRef, selectedAreaIdRef }) {
  const expandedAreaIds = ref(new Set())
  const currentDrillAreaId = ref(null)

  const parentIdOf = area => area?.parent_area_id ?? area?.parent_id ?? null

  const areaMap = computed(() => new Map(areasRef.value.map(a => [String(a.id), a])))

  const getAreaFullPath = area => {
    if (!area) return ''
    const parts = []
    let curr = area
    const seen = new Set()

    while (curr && !seen.has(String(curr.id))) {
      seen.add(String(curr.id))
      parts.unshift(resolveAreaName(curr))

      const pId = parentIdOf(curr)

      curr = pId != null ? areaMap.value.get(String(pId)) : null
    }

    return parts.join(' › ')
  }

  const childrenMap = computed(() => {
    const map = new Map()

    areasRef.value.forEach(a => {
      const pId = parentIdOf(a)
      const key = !isEmptyId(pId) && areaMap.value.has(String(pId)) ? String(pId) : ROOT_KEY

      if (!map.has(key)) map.set(key, [])
      map.get(key).push(a)
    })

    return map
  })

  const childrenOf = id => childrenMap.value.get(id == null ? ROOT_KEY : String(id)) || []
  const childCountOf = id => childrenOf(id).length
  const rootAreas = computed(() => childrenOf(null))

  const matchesQuery = (area, query) => {
    if (!query) return true
    const q = query.trim().toLowerCase()
    if (!q) return true

    const nameText = (resolveAreaName(area) + ' ' + safeString(area.name)).toLowerCase()
    const codeText = safeString(area.code).toLowerCase()
    const typeText = safeString(area.area_type).toLowerCase()
    const descText = (resolveAreaDescription(area) + ' ' + safeString(area.description)).toLowerCase()
    const pathText = getAreaFullPath(area).toLowerCase()

    return nameText.includes(q) || codeText.includes(q) || typeText.includes(q) || descText.includes(q) || pathText.includes(q)
  }

  const flattenedHierarchy = computed(() => {
    const q = searchQueryRef.value ? searchQueryRef.value.trim() : ''

    if (q) {
      return areasRef.value.filter(a => matchesQuery(a, q)).map(a => ({
        ...a,
        depth: 0,
        hasChildren: false,
        isExpanded: false,
        fullPath: getAreaFullPath(a),
      }))
    }

    const result = []

    const traverse = (parentId, depth) => {
      childrenOf(parentId).forEach(item => {
        const childCount = childCountOf(item.id)
        const hasChildren = childCount > 0
        const isExpanded = expandedAreaIds.value.has(String(item.id))

        result.push({
          ...item,
          depth,
          hasChildren,
          isExpanded,
          childCount,
          fullPath: getAreaFullPath(item),
        })

        if (hasChildren && isExpanded) {
          traverse(item.id, depth + 1)
        }
      })
    }

    traverse(null, 0)

    return result
  })

  const cardsBreadcrumbs = computed(() => {
    if (currentDrillAreaId.value == null) return []

    const crumbs = []
    let cursor = areaMap.value.get(String(currentDrillAreaId.value))
    const visited = new Set()

    while (cursor && !visited.has(String(cursor.id))) {
      visited.add(String(cursor.id))
      crumbs.unshift(cursor)

      const pId = parentIdOf(cursor)

      cursor = pId != null ? areaMap.value.get(String(pId)) : null
    }

    return crumbs
  })

  const currentCardsAreas = computed(() => {
    const q = searchQueryRef.value ? searchQueryRef.value.trim() : ''

    if (q) {
      return areasRef.value.filter(a => matchesQuery(a, q)).map(a => ({
        ...a,
        childCount: childCountOf(a.id),
        fullPath: getAreaFullPath(a),
      }))
    }

    return childrenOf(currentDrillAreaId.value).map(a => ({
      ...a,
      childCount: childCountOf(a.id),
      fullPath: getAreaFullPath(a),
    }))
  })

  const initExpanded = () => {
    const next = new Set()

    childrenOf(null).forEach(a => next.add(String(a.id)))

    let cursor = selectedAreaIdRef.value != null ? areaMap.value.get(String(selectedAreaIdRef.value)) : null
    const seen = new Set()

    while (cursor && !seen.has(String(cursor.id))) {
      seen.add(String(cursor.id))

      const pId = parentIdOf(cursor)

      if (pId != null) next.add(String(pId))
      cursor = pId != null ? areaMap.value.get(String(pId)) : null
    }

    expandedAreaIds.value = next
  }

  const toggleExpand = id => {
    const k = String(id)

    if (expandedAreaIds.value.has(k)) {
      expandedAreaIds.value.delete(k)
    } else {
      expandedAreaIds.value.add(k)
    }
    expandedAreaIds.value = new Set(expandedAreaIds.value)
  }

  const expandAll = () => {
    areasRef.value.forEach(a => expandedAreaIds.value.add(String(a.id)))
    expandedAreaIds.value = new Set(expandedAreaIds.value)
  }

  const collapseAll = () => {
    expandedAreaIds.value = new Set()
  }

  return {
    ROOT_KEY,
    expandedAreaIds,
    currentDrillAreaId,
    areaMap,
    childrenMap,
    rootAreas,
    flattenedHierarchy,
    cardsBreadcrumbs,
    currentCardsAreas,
    parentIdOf,
    getAreaFullPath,
    childrenOf,
    childCountOf,
    matchesQuery,
    initExpanded,
    toggleExpand,
    expandAll,
    collapseAll,
    resolveAreaName,
    resolveAreaDescription,
    resolveAreaTypeIcon,
    highlightParts,
    idsEqual,
    isEmptyId,
  }
}
