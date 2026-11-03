import { useBusinessPartnerDialogs } from '@/composables/business-partners/useBusinessPartnerDialogs'
import { useServiceProviderStore } from '@/stores/useServiceProviderStore'

export function useServiceProviderDialogs() {
  const dialogs = useBusinessPartnerDialogs({
    store: useServiceProviderStore(),
    fetchMethod: 'fetchServiceProvider',
    idKey: 'serviceProviderId',
    entityNameKey: 'Service Providers',
  })

  return {
    ...dialogs,
    viewServiceProvider: dialogs.viewPartner,
  }
}
