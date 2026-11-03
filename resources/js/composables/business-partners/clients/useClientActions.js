import { useBusinessPartnerActions } from '@/composables/business-partners/useBusinessPartnerActions'
import { useClientStore } from '@/stores/useClientStore'

export function useClientActions(options = {}) {
  const actions = useBusinessPartnerActions({
    store: useClientStore(),
    entityNameKey: 'Clients',
    translationPrefix: 'clients.list.messages',
    routeName: 'business-partners-clients-edit-id',
    referenceField: 'client_id',
    filenamePrefix: 'client',
    methods: {
      create: 'createClient',
      delete: 'deleteClient',
      sendSingleEmail: 'sendClientSingleEmail',
      sendListEmail: 'sendClientListEmail',
      exportListPdf: 'exportClientsPdf',
      exportListCsv: 'exportClientsCsv',
      exportSinglePdf: 'exportClientPdf',
    },
    onRefresh: options.onRefresh,
    onMetaRefresh: options.onMetaRefresh,
  })

  return {
    ...actions,
    createClient: actions.create,
    deleteClient: actions.remove,
    sendEmail: ({ clientId, emailData }) => actions.sendEmail({ id: clientId, emailData }),
  }
}
