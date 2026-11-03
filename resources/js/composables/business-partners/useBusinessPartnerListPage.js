import { useListPage } from '@/composables/list'
import { useSelection } from '@/composables/list/useSelection'
import { buildBusinessPartnerListQuery } from '@/composables/business-partners/businessPartnerListQuery'
import { watch } from 'vue'

export function useBusinessPartnerListPage({
  fetchFn,
  filterKeys,
  sortFields,
  sortFieldMap,
}) {
  const initialFilters = Object.fromEntries(filterKeys.map(key => [key, null]))

  const listPage = useListPage({
    fetchFn: params => fetchFn(buildBusinessPartnerListQuery(params, { filterKeys, sortFields })),
    initialFilters,
    initialSortKey: 'id',
    initialSortOrder: 'desc',
    initialItemsPerPage: 10,
    sortFieldMap,
    immediate: true,
  })

  const { selected, clearSelection } = useSelection()

  // Clear selection when search or filters change, while preserving selections across pagination
  watch(
    [listPage.search, () => ({ ...listPage.filters })],
    clearSelection,
    { deep: true },
  )

  return {
    ...listPage,
    selected,
    clearSelection,
  }
}
