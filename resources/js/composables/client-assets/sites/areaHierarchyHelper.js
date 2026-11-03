/**
 * Area Hierarchy Utility Functions
 * Supports recursive traversal and validation for ClientSiteArea tree structures.
 */

/**
 * Get all descendant IDs of a given area at arbitrary recursive depth.
 * @param {string|number} areaId - Target parent area ID
 * @param {Array} flatAreas - Flat list of all areas in the site
 * @returns {Array<string|number>} - Array of all descendant area IDs
 */
export function getAllDescendantAreaIds(areaId, flatAreas = []) {
  if (!areaId || !Array.isArray(flatAreas)) return []

  const descendantIds = []
  const queue = [areaId]

  while (queue.length > 0) {
    const currentParentId = queue.shift()
    const directChildren = flatAreas.filter(a => a.parent_area_id === currentParentId)

    for (const child of directChildren) {
      if (!descendantIds.includes(child.id)) {
        descendantIds.push(child.id)
        queue.push(child.id)
      }
    }
  }

  return descendantIds
}

/**
 * Build ancestor breadcrumb path from root to target area.
 * @param {string|number} areaId - Target area ID
 * @param {Array} flatAreas - Flat list of all areas in the site
 * @returns {Array<Object>} - Array of area objects from root to current
 */
export function buildAreaBreadcrumb(areaId, flatAreas = []) {
  if (!areaId || !Array.isArray(flatAreas)) return []

  const path = []
  let current = flatAreas.find(a => a.id === areaId)
  const visited = new Set()

  while (current && !visited.has(current.id)) {
    visited.add(current.id)
    path.unshift(current)
    current = flatAreas.find(a => a.id === current.parent_area_id)
  }

  return path
}

/**
 * Checks if moving an area to a new parent would cause a circular hierarchy.
 * @param {string|number} areaId - Area being moved
 * @param {string|number} newParentId - Desired new parent area ID
 * @param {Array} flatAreas - Flat list of all areas in the site
 * @returns {boolean} - True if circular dependency detected
 */
export function hasCircularDependency(areaId, newParentId, flatAreas = []) {
  if (!areaId || !newParentId) return false
  if (areaId === newParentId) return true

  const descendants = getAllDescendantAreaIds(areaId, flatAreas)

  return descendants.includes(newParentId)
}

/**
 * Calculate equipment count for an area including all recursive descendant sub-areas.
 * @param {string|number} areaId - Target area ID
 * @param {Array} flatAreas - Flat list of all areas in the site
 * @returns {{ direct: number, nested: number, total: number, hasDescendants: boolean, descendantCount: number }}
 */
export function getAreaRecursiveEquipmentCount(areaId, flatAreas = []) {
  if (!areaId || !Array.isArray(flatAreas)) {
    return { direct: 0, nested: 0, total: 0, hasDescendants: false, descendantCount: 0 }
  }

  const directArea = flatAreas.find(a => String(a.id) === String(areaId))
  const directCount = Number(directArea?.equipment_count) || 0
  const descendantIds = getAllDescendantAreaIds(areaId, flatAreas)

  let nestedCount = 0

  for (const descId of descendantIds) {
    const descArea = flatAreas.find(a => String(a.id) === String(descId))

    nestedCount += Number(descArea?.equipment_count) || 0
  }

  return {
    direct: directCount,
    nested: nestedCount,
    total: directCount + nestedCount,
    hasDescendants: descendantIds.length > 0,
    descendantCount: descendantIds.length,
  }
}

/**
 * Auto-generate a simple and short area code (e.g. ZN-01, BLD-01, RM-01).
 * @param {string} areaType - Area type key (building, floor, zone, room, etc.)
 * @param {Array} flatAreas - Flat list of existing areas
 * @returns {string} - Generated code like ZN-01
 */
export function generateShortAreaCode(areaType = 'zone', flatAreas = []) {
  const prefixMap = {
    building: 'BLD',
    floor: 'FL',
    zone: 'ZN',
    room: 'RM',
    exterior: 'EXT',
    roof: 'RF',
    basement: 'BS',
    other: 'ZN',
  }

  const prefix = prefixMap[areaType?.toLowerCase()] || 'ZN'
  const regex = new RegExp(`^${prefix}-(\\d+)`, 'i')
  let maxSeq = 0

  if (Array.isArray(flatAreas)) {
    for (const a of flatAreas) {
      if (a?.code) {
        const match = String(a.code).match(regex)
        if (match) {
          const num = parseInt(match[1], 10)
          if (!isNaN(num) && num > maxSeq) {
            maxSeq = num
          }
        }
      }
    }
  }

  const nextSeq = maxSeq + 1

  return `${prefix}-${String(nextSeq).padStart(2, '0')}`
}
