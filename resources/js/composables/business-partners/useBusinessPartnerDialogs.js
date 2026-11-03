import { useDialog } from '@/composables/dialog/useDialog'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

export function useBusinessPartnerDialogs({
  store,
  fetchMethod,
  idKey,
  entityNameKey,
}) {
  const { t } = useI18n()
  const viewDialog = useDialog()
  const viewLoading = ref(false)
  const viewError = ref(null)
  const viewPartner = ref(null)
  const addDialog = useDialog()
  const deleteDialog = useDialog()

  const emailDialog = ref({
    isOpen: false,
    recipients: [],
    [idKey]: null,
    isSingle: false,
  })

  const openView = async partnerId => {
    viewPartner.value = null
    viewError.value = null
    viewLoading.value = true
    viewDialog.open(partnerId)

    try {
      viewPartner.value = await store[fetchMethod](partnerId)
    }
    catch (error) {
      viewError.value = error?.data?.message
        || error?.message
        || t('bp_common.dialogs.load_error', { entity: t(entityNameKey) })
    }
    finally {
      viewLoading.value = false
    }
  }

  const closeView = () => {
    viewDialog.close()
    viewPartner.value = null
    viewError.value = null
  }

  const openBulkEmail = (recipients = []) => {
    emailDialog.value = {
      isOpen: true,
      recipients,
      [idKey]: null,
      isSingle: false,
    }
  }

  const openSingleEmail = partner => {
    const recipients = []
    if (partner?.primary_email)
      recipients.push(partner.primary_email)

    partner?.contacts?.forEach(contact => {
      if (contact.email && !recipients.includes(contact.email))
        recipients.push(contact.email)
    })

    emailDialog.value = {
      isOpen: true,
      recipients,
      [idKey]: partner?.id,
      isSingle: true,
    }
  }

  const singleEmailRecipients = computed(() => {
    if (!viewPartner.value)
      return []

    const recipients = []
    if (viewPartner.value.primary_email)
      recipients.push(viewPartner.value.primary_email)

    viewPartner.value.contacts?.forEach(contact => {
      if (contact.email && !recipients.includes(contact.email))
        recipients.push(contact.email)
    })

    return recipients
  })

  return {
    viewDialog,
    viewLoading,
    viewError,
    viewPartner,
    openView,
    closeView,
    singleEmailRecipients,
    addDialog,
    deleteDialog,
    openDelete: partnerId => deleteDialog.open(partnerId),
    emailDialog,
    openBulkEmail,
    openSingleEmail,
    closeEmail: () => { emailDialog.value.isOpen = false },
  }
}
