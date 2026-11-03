import { useBusinessPartnerDialogs } from '@/composables/business-partners/useBusinessPartnerDialogs'
import { useOrganisationStore } from '@/stores/useOrganisationStore'

export function useOrganisationDialogs() {
  const dialogs = useBusinessPartnerDialogs({
    store: useOrganisationStore(),
    fetchMethod: 'fetchOrganisation',
    idKey: 'orgId',
    entityNameKey: 'Organisations',
  })

  return {
    ...dialogs,
    viewOrganisation: dialogs.viewPartner,
  }
}
