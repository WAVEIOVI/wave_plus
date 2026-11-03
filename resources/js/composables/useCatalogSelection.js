import { localizedValue } from '@/composables/useLocaleField'

const flattenCategories = (categories, depth = 0, parentPath = '') => {
  const rows = []

  categories.forEach(category => {
    const name = localizedValue(category.name, 'Untitled')
    const path = parentPath ? `${parentPath} / ${name}` : name

    rows.push({ category, depth, path })
    if (category.children?.length) {
      rows.push(...flattenCategories(category.children, depth + 1, path))
    }
  })

  return rows
}

export const categoryMatchesScope = (category, itemType, subType) => {
  if (!category?.is_active) return false

  const typeScope = category.item_type_scope || []
  const subtypeScope = category.item_subtype_scope || []

  if (itemType && typeScope.length && !typeScope.includes(itemType)) return false
  if (subType && subtypeScope.length && !subtypeScope.includes(subType)) return false

  return true
}

export const filterCategoriesByScope = (categories, itemType, subType) => {
  const filterTree = nodes => nodes
    .map(node => ({
      ...node,
      children: filterTree(node.children || []),
    }))
    .filter(node => categoryMatchesScope(node, itemType, subType) || node.children?.length)

  return filterTree(categories)
}

export const flattenScopedCategories = (categories, itemType, subType) => flattenCategories(
  filterCategoriesByScope(categories, itemType, subType),
)

export const findCategoryById = (categories, id) => {
  for (const category of categories) {
    if (category.id === id) return category
    const child = findCategoryById(category.children || [], id)
    if (child) return child
  }

  return null
}

export const flattenEquipmentClassTree = (items, depth = 0, parentPath = '') => {
  const rows = []

  items.forEach(item => {
    const name = localizedValue(item.name, item.code || 'Untitled')
    const path = parentPath ? `${parentPath} / ${name}` : name

    rows.push({ item, depth, path })
    if (item.children?.length) {
      rows.push(...flattenEquipmentClassTree(item.children, depth + 1, path))
    }
  })

  return rows
}

export const findEquipmentClassById = (items, id) => {
  for (const item of items) {
    if (item.id === id) return item
    const child = findEquipmentClassById(item.children || [], id)
    if (child) return child
  }

  return null
}

export const getEquipmentClassLabel = (classes, id) => {
  const match = findEquipmentClassById(classes, id)

  return match ? localizedValue(match.name, match.code || 'Untitled') : ''
}

export const getBlueprintEquipmentClassLabel = blueprint => {
  if (blueprint?.equipment_class && typeof blueprint.equipment_class === 'object') {
    if (blueprint.equipment_class.name) {
      return localizedValue(blueprint.equipment_class.name, blueprint.equipment_class.code || 'Untitled')
    }
    if (blueprint.equipment_class.code) {
      return blueprint.equipment_class.code
    }
  }

  if (blueprint?.equipment_class_rel?.name) {
    return localizedValue(blueprint.equipment_class_rel.name, blueprint.equipment_class_rel.code || 'Untitled')
  }

  if (typeof blueprint?.equipment_class === 'string' && blueprint.equipment_class) {
    return blueprint.equipment_class.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')
  }

  return '—'
}

export const scrubCatalogItemExtensionPayload = payload => {
  const next = JSON.parse(JSON.stringify(payload))
  const itemType = next.item_type

  if (itemType === 'product' && next.product) {
    const productType = next.product.product_type
    const allowed = ['product_type', 'technical_specs']

    next.product = Object.fromEntries(
      Object.entries(next.product).filter(([key]) => allowed.includes(key)),
    )

    if (productType === 'physical') {
      delete next.product.capacity_value
      delete next.product.capacity_unit
      delete next.product.material
      delete next.product.expiry_years
      delete next.product.physical_type
    }

    if (productType === 'digital') {
      delete next.product.delivery_method
      delete next.product.is_downloadable
      delete next.product.digital_type
    }

    if (productType === 'service') {
      delete next.product.billable_unit
      delete next.product.duration_minutes
      delete next.product.max_participants
      delete next.product.service_type
    }
  }

  if (itemType === 'consumable' && next.consumable) {
    next.consumable = {
      consumable_type: next.consumable.consumable_type,
      technical_specs: next.consumable.technical_specs || {},
    }
  }

  if (itemType === 'eq_servicing' && next.eq_servicing) {
    const consumables = next.eq_servicing.consumables

    next.eq_servicing = {
      eq_servicing_type: next.eq_servicing.eq_servicing_type,
      technical_specs: next.eq_servicing.technical_specs || {},
      ...(consumables ? { consumables } : {}),
    }
  }

  return next
}
