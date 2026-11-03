/* eslint-disable camelcase */
import { localizedValue } from '@/composables/useLocaleField'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

export function useSiteEdit(targetSiteId) {
  const store = useClientAssetStore()
  const snackbarStore = useSnackbarStore()
  const router = useRouter()
  const { t } = useI18n()

  const loading = ref(true)
  const saving = ref(false)
  const pageError = ref(null)
  const fetchedSite = ref(null)
  const originalSite = ref(null)

  const form = ref({
    name: '',
    description: '',
    site_type: 'office',
    status: 'active',
    risk_class: 'low',
    operating_hours: '',
    access_instructions: '',
    safety_instructions: '',
    special_instructions: '',
    opened_at: null,
    closed_at: null,
    notes: '',
  })

  const isDirty = computed(() => {
    if (!originalSite.value) return false

    return JSON.stringify(form.value) !== JSON.stringify(originalSite.value)
  })

  const fetchSite = async () => {
    const siteId = typeof targetSiteId === 'function' ? targetSiteId() : (targetSiteId?.value ?? targetSiteId)
    if (!siteId) return

    loading.value = true
    pageError.value = null
    try {
      const site = await store.fetchSite(siteId)

      fetchedSite.value = site

      form.value = {
        name: localizedValue(site.name),
        description: localizedValue(site.description),
        site_type: site.site_type || 'office',
        status: site.status || 'active',
        risk_class: site.risk_class || 'low',
        operating_hours: localizedValue(site.operating_hours),
        access_instructions: localizedValue(site.access_instructions),
        safety_instructions: localizedValue(site.safety_instructions),
        special_instructions: localizedValue(site.special_instructions),
        opened_at: site.opened_at ? site.opened_at.substring(0, 10) : null,
        closed_at: site.closed_at ? site.closed_at.substring(0, 10) : null,
        notes: localizedValue(site.notes),
      }

      originalSite.value = JSON.parse(JSON.stringify(form.value))

      return site
    }
    catch (err) {
      console.error('Error fetching site:', err)
      pageError.value = err?.data?.message || t('Failed to load site data.')

      return null
    }
    finally {
      loading.value = false
    }
  }

  const handleSave = async () => {
    const siteId = typeof targetSiteId === 'function' ? targetSiteId() : (targetSiteId?.value ?? targetSiteId)
    if (!siteId) return false

    saving.value = true
    try {
      const payload = {
        name: form.value.name,
        description: form.value.description || null,
        site_type: form.value.site_type,
        status: form.value.status,
        risk_class: form.value.risk_class,
        operating_hours: form.value.operating_hours || null,
        access_instructions: form.value.access_instructions || null,
        safety_instructions: form.value.safety_instructions || null,
        special_instructions: form.value.special_instructions || null,
        opened_at: form.value.opened_at || null,
        closed_at: form.value.closed_at || null,
        notes: form.value.notes || null,
      }

      await store.updateSite(siteId, payload)
      originalSite.value = JSON.parse(JSON.stringify(form.value))
      snackbarStore.showMessage(t('Site updated successfully'), 'success')
      await fetchSite()

      return true
    }
    catch (err) {
      console.error('Error saving site:', err)

      const apiError = err?.data?.message || (err?.data?.errors ? Object.values(err.data.errors)[0][0] : t('Error saving site'))

      snackbarStore.showMessage(apiError, 'error')

      return false
    }
    finally {
      saving.value = false
    }
  }

  const handleDelete = async () => {
    const siteId = typeof targetSiteId === 'function' ? targetSiteId() : (targetSiteId?.value ?? targetSiteId)
    if (!siteId) return false

    try {
      await store.deleteSite(siteId)
      snackbarStore.showMessage(t('Site deleted successfully'), 'success')
      await router.push({ name: 'client-assets-sites-list' })

      return true
    }
    catch (err) {
      console.error('Error deleting site:', err)
      snackbarStore.showMessage(t('Failed to delete site'), 'error')

      return false
    }
  }

  const allSiteDocuments = computed(() => {
    const docs = fetchedSite.value?.documents || []
    const plans = fetchedSite.value?.site_plans || []

    return [...docs, ...plans]
  })

  return {
    loading,
    saving,
    pageError,
    fetchedSite,
    originalSite,
    form,
    isDirty,
    allSiteDocuments,
    fetchSite,
    handleSave,
    handleDelete,
  }
}
