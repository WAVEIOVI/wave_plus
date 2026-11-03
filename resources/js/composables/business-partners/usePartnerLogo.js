import { ref, unref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppSnackbar } from '@/composables/useAppSnackbar'

export function usePartnerLogo({
  partnerId,
  store,
  uploadMethod,
  updateMethod,
  onSaved,
}) {
  const { t } = useI18n()
  const { success, error, showApiError } = useAppSnackbar()
  const dialogOpen = ref(false)
  const saving = ref(false)

  const save = async payload => {
    const id = unref(partnerId)

    if (!id) {
      error(t('business_partners.logo.missing_id', 'Missing business partner identifier'))

      return false
    }

    saving.value = true
    try {
      if (payload.source === 'upload' && payload.file) {
        await store[uploadMethod](id, payload.file)
      }
      else {
        await store[updateMethod](id, {
          'logo_source': payload.source,
          'logo_domain': payload.source === 'initials' ? null : payload.domain || null,
          'logo_external_url': payload.source === 'initials' ? null : payload.url || null,
        })
      }
    }
    catch (err) {
      showApiError(err, t('business_partners.logo.update_error', 'Failed to update the logo'))

      return false
    }
    finally {
      saving.value = false
    }

    dialogOpen.value = false
    success(t('business_partners.logo.updated_success', 'Logo updated successfully'))
    await onSaved?.(payload)

    return true
  }

  return {
    dialogOpen,
    saving,
    save,
  }
}
