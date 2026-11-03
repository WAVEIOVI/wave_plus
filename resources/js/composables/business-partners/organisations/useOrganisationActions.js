import { useBusinessPartnerActions } from '@/composables/business-partners/useBusinessPartnerActions'
import { useOrganisationStore } from '@/stores/useOrganisationStore'

export function useOrganisationActions(options = {}) {
  const actions = useBusinessPartnerActions({
    store: useOrganisationStore(),
    entityNameKey: 'Organisations',
    translationPrefix: 'organisations.list.messages',
    routeName: 'business-partners-organisations-edit-id',
    referenceField: 'organisation_id',
    filenamePrefix: 'organisation',
    methods: {
      create: 'createOrganisation',
      delete: 'deleteOrganisation',
      sendSingleEmail: 'sendOrganisationSingleEmail',
      sendListEmail: 'sendOrganisationListEmail',
      exportListPdf: 'exportOrganisationsPdf',
      exportSinglePdf: 'exportOrganisationPdf',
    },
    onRefresh: options.onRefresh,
    onMetaRefresh: options.onMetaRefresh,
  })

  return {
    ...actions,
    createOrganisation: actions.create,
    deleteOrganisation: actions.remove,
    sendEmail: ({ orgId, emailData }) => actions.sendEmail({ id: orgId, emailData }),
  }
}
