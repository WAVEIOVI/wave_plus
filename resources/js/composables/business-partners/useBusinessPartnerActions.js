import { buildBatchExportPayload } from '@/composables/shared/exportFormats'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

export function useBusinessPartnerActions({
  store,
  entityNameKey,
  translationPrefix,
  routeName,
  referenceField,
  filenamePrefix,
  methods,
  emailHandler,
  onRefresh,
  onMetaRefresh,
}) {
  const router = useRouter()
  const { t, te } = useI18n()
  const { showMessage, success, error: showError, showApiError } = useAppSnackbar()
  const isCreating = ref(false)
  const isDeleting = ref(false)
  const isSendingEmail = ref(false)
  const isExportingListPdf = ref(false)
  const isExportingList = ref(false)
  const isExportingSinglePdf = ref(false)
  const exportError = ref('')

  const translateMessage = (key, fallbackKey = key, params = undefined) => {
    const fullKey = `${translationPrefix}.${key}`
    if (te(fullKey))
      return t(fullKey, params)

    const fallbackFullKey = `${translationPrefix}.${fallbackKey}`

    return te(fallbackFullKey) ? t(fallbackFullKey, params) : t(fullKey, params)
  }

  const getErrorMessage = (error, key) => error?.data?.message
    || (error?.data?.errors ? Object.values(error.data.errors)[0]?.[0] : null)
    || translateMessage(key)

  const resolveAsyncErrorMessage = async (error, key, fallbackKey = key) => {
    if (error?.data && typeof Blob !== 'undefined' && error.data instanceof Blob) {
      try {
        const text = await error.data.text()
        const parsed = JSON.parse(text)

        const parsedMsg = parsed?.message
          || (parsed?.errors ? Object.values(parsed.errors)[0]?.[0] : null)

        if (parsedMsg)
          return parsedMsg
      }
      catch {
        // Ignore JSON parse failure and fall back to translated message
      }
    }

    return error?.data?.message
      || (error?.data?.errors ? Object.values(error.data.errors)[0]?.[0] : null)
      || translateMessage(key, fallbackKey)
  }

  const create = async data => {
    isCreating.value = true
    try {
      const response = await store[methods.create](data)
      const id = response?.data?.id

      const reference = response?.data?.[referenceField]
        || response?.[referenceField]
        || t(entityNameKey)

      showMessage(t(`${translationPrefix}.create_success`, { ref: reference }), 'success')

      if (id && routeName)
        router.push({ name: routeName, params: { id } })
      else {
        await onRefresh?.()
        await onMetaRefresh?.()
      }

      return response
    }
    catch (error) {
      showMessage(getErrorMessage(error, 'create_error'), 'error')

      return null
    }
    finally {
      isCreating.value = false
    }
  }

  const remove = async id => {
    if (!id)
      return false

    isDeleting.value = true
    try {
      await store[methods.delete](id)
      showMessage(t(`${translationPrefix}.delete_success`), 'success')
      await onRefresh?.()
      await onMetaRefresh?.()

      return true
    }
    catch (error) {
      showMessage(getErrorMessage(error, 'delete_error'), 'error')

      return false
    }
    finally {
      isDeleting.value = false
    }
  }

  const sendEmail = async payload => {
    isSendingEmail.value = true
    try {
      if (emailHandler)
        await emailHandler(store, payload)
      else {
        const { id, emailData } = payload
        const method = id ? methods.sendSingleEmail : methods.sendListEmail

        await store[method](...(id ? [id, emailData] : [emailData]))
      }
      showMessage(t(`${translationPrefix}.email_success`), 'success')

      return true
    }
    catch (error) {
      showMessage(getErrorMessage(error, 'email_error'), 'error')

      return false
    }
    finally {
      isSendingEmail.value = false
    }
  }

  const exportListPdf = methods.exportListPdf
    ? async ids => {
      isExportingListPdf.value = true
      isExportingList.value = true
      exportError.value = ''
      try {
        await store[methods.exportListPdf](ids)
        showMessage(translateMessage('export_pdf_success'), 'success')

        return true
      }
      catch (error) {
        const msg = await resolveAsyncErrorMessage(error, 'export_pdf_error')

        exportError.value = msg
        showMessage(msg, 'error')

        return false
      }
      finally {
        isExportingListPdf.value = false
        isExportingList.value = false
      }
    }
    : undefined

  const exportSelected = (methods.exportListPdf || methods.exportListCsv)
    ? async ({ ids = [], format = 'pdf' } = {}) => {
      const supportedFormats = [
        ...(methods.exportListPdf ? ['pdf'] : []),
        ...(methods.exportListCsv ? ['csv'] : []),
      ]

      const payload = buildBatchExportPayload(ids, format, supportedFormats)
      if (!payload.valid) {
        const msg = payload.error || translateMessage('export_error', 'export_pdf_error')

        exportError.value = msg
        showMessage(msg, 'error')

        return false
      }

      isExportingList.value = true
      if (payload.format === 'pdf')
        isExportingListPdf.value = true

      exportError.value = ''

      try {
        if (payload.format === 'csv' && methods.exportListCsv)
          await store[methods.exportListCsv](payload.ids)
        else if (methods.exportListPdf)
          await store[methods.exportListPdf](payload.ids)

        const successKey = payload.format === 'csv' ? 'export_csv_success' : 'export_pdf_success'

        showMessage(translateMessage(successKey, 'export_pdf_success'), 'success')

        return true
      }
      catch (error) {
        const errorKey = payload.format === 'csv' ? 'export_csv_error' : 'export_pdf_error'
        const msg = await resolveAsyncErrorMessage(error, errorKey, 'export_pdf_error')

        exportError.value = msg
        showMessage(msg, 'error')

        return false
      }
      finally {
        isExportingList.value = false
        isExportingListPdf.value = false
      }
    }
    : undefined

  const exportSinglePdf = methods.exportSinglePdf
    ? async partner => {
      if (!partner?.id)
        return

      isExportingSinglePdf.value = true
      try {
        const reference = partner[referenceField] || partner.id
        const filename = `${filenamePrefix}-${reference}.pdf`

        await store[methods.exportSinglePdf](partner.id, filename)
        showMessage(t(`${translationPrefix}.export_pdf_success`), 'success')
      }
      catch (error) {
        showMessage(getErrorMessage(error, 'export_pdf_error'), 'error')
      }
      finally {
        isExportingSinglePdf.value = false
      }
    }
    : undefined

  return {
    isCreating,
    isDeleting,
    isSendingEmail,
    isExportingListPdf,
    isExportingList,
    isExportingSinglePdf,
    exportError,
    create,
    remove,
    sendEmail,
    exportListPdf,
    exportSelected,
    exportSinglePdf,
    showSuccess: success,
    showError,
    showApiError,
  }
}
