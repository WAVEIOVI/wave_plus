export function buildBusinessPartnerListQuery(params, { filterKeys, sortFields }) {
  const allowedSortFields = new Set(sortFields)
  const sortField = allowedSortFields.has(params.sort_by) ? params.sort_by : 'id'

  const query = {
    page: params.page,
    itemsPerPage: params.per_page,
    q: params.search,
    sort: `${params.sort_order === 'desc' ? '-' : ''}${sortField}`,
  }

  filterKeys.forEach(key => {
    query[`filter[${key}]`] = params[key] || undefined
  })

  return query
}
