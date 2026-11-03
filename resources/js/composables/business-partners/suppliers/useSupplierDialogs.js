import { useBusinessPartnerDialogs } from '@/composables/business-partners/useBusinessPartnerDialogs'
import { useSupplierStore } from '@/stores/useSupplierStore'

export function useSupplierDialogs() {
  const dialogs = useBusinessPartnerDialogs({
    store: useSupplierStore(),
    fetchMethod: 'fetchSupplier',
    idKey: 'supplierId',
    entityNameKey: 'Suppliers',
  })

  return {
    ...dialogs,
    viewSupplier: dialogs.viewPartner,
  }
}
