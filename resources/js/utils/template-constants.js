/**
 * Template Constants
 * 
 * Used as a placeholder for ListPageTemplate.vue.
 * Developers should replace these with actual constants for their specific module.
 */

/**
 * Status Chip Mapping for the Template
 * @param {string} status 
 */
export const getTemplateStatusChip = status => {
  const map = {
    draft: { label: 'Brouillon', color: 'secondary', icon: 'tabler-file-pencil' },
    pending: { label: 'En attente', color: 'warning', icon: 'tabler-clock' },
    active: { label: 'Actif', color: 'success', icon: 'tabler-circle-check' },
    closed: { label: 'Fermé', color: 'error', icon: 'tabler-circle-x' },
  }

  return map[status] || { label: status, color: 'grey', icon: 'tabler-help' }
}

/**
 * Status Filter Options
 */
export const templateStatusOptions = [
  { title: 'Tous les statuts', value: null },
  { title: 'Brouillon', value: 'draft' },
  { title: 'En attente', value: 'pending' },
  { title: 'Actif', value: 'active' },
  { title: 'Fermé', value: 'closed' },
]
