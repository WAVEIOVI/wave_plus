import { useBusinessPartnerActions } from '@/composables/business-partners/useBusinessPartnerActions'
import { useServiceProviderStore } from '@/stores/useServiceProviderStore'

export function useServiceProviderActions(options = {}) {
  const actions = useBusinessPartnerActions({
    store: useServiceProviderStore(),
    entityNameKey: 'Service Providers',
    translationPrefix: 'service_providers.list.messages',
    routeName: 'business-partners-service-providers-edit-id',
    referenceField: 'provider_id',
    filenamePrefix: 'service-provider',
    methods: {
      create: 'createServiceProvider',
      delete: 'deleteServiceProvider',
      sendSingleEmail: 'sendServiceProviderSingleEmail',
      sendListEmail: 'sendServiceProviderListEmail',
      exportListPdf: 'exportServiceProvidersPdf',
      exportSinglePdf: 'exportServiceProviderPdf',
    },
    onRefresh: options.onRefresh,
    onMetaRefresh: options.onMetaRefresh,
  })

  return {
    ...actions,
    createServiceProvider: actions.create,
    deleteServiceProvider: actions.remove,
    sendEmail: ({ serviceProviderId, emailData }) => actions.sendEmail({ id: serviceProviderId, emailData }),
  }
}
