import { useBusinessPartnerActions } from '@/composables/business-partners/useBusinessPartnerActions'
import { useSupplierStore } from '@/stores/useSupplierStore'

export function useSupplierActions(options = {}) {
  const actions = useBusinessPartnerActions({
    store: useSupplierStore(),
    entityNameKey: 'Suppliers',
    translationPrefix: 'suppliers.list.messages',
    routeName: 'business-partners-suppliers-edit-id',
    referenceField: 'supplier_id',
    filenamePrefix: 'supplier',
    methods: {
      create: 'createSupplier',
      delete: 'deleteSupplier',
      sendSingleEmail: 'sendSupplierSingleEmail',
      sendListEmail: 'sendSupplierListEmail',
      exportListPdf: 'exportSuppliersPdf',
      exportSinglePdf: 'exportSupplierPdf',
    },
    onRefresh: options.onRefresh,
    onMetaRefresh: options.onMetaRefresh,
  })

  return {
    ...actions,
    createSupplier: actions.create,
    deleteSupplier: actions.remove,
    sendEmail: ({ supplierId, emailData }) => actions.sendEmail({ id: supplierId, emailData }),
  }
}
