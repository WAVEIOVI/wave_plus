import { useBusinessPartnerDialogs } from '@/composables/business-partners/useBusinessPartnerDialogs'
import { useClientStore } from '@/stores/useClientStore'

export function useClientDialogs() {
  const dialogs = useBusinessPartnerDialogs({
    store: useClientStore(),
    fetchMethod: 'fetchClient',
    idKey: 'clientId',
    entityNameKey: 'Clients',
  })

  return {
    ...dialogs,
    viewClient: dialogs.viewPartner,
  }
}
