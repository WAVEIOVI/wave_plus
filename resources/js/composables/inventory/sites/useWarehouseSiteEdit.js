import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useInventoryStore } from '@/stores/useInventoryStore'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

/* eslint-disable camelcase */

/**
 * useWarehouseSiteEdit
 * ─────────────────────────────────────────────────────────────────────────────
 * Edit page composable for a single warehouse site.
 * Owns site data loading, form state, isDirty tracking, and save/delete.
 *
 * Usage:
 *   const { form, isDirty, loading, saving, fetchSite, handleSave } = useWarehouseSiteEdit(siteId)
 *
 * @param {string|Ref<string>|Function} targetSiteId - ULID of the site to edit
 */
export function useWarehouseSiteEdit(targetSiteId) {
  const store = useInventoryStore()
  const { success: showSuccess, error: showError, showApiError } = useAppSnackbar()
  const router = useRouter()
  const { t } = useI18n()

  const loading = ref(true)
  const saving = ref(false)
  const pageError = ref(null)
  const fetchedSite = ref(null)
  const originalForm = ref(null)

  const form = ref({
    code: '',
    name: { en: '', fr: '', ar: '' },
    address: '',
    city: '',
    country: '',
    phone: '',
    email: '',
    status: 'active',
    notes: '',

    // Mobile warehouse fields
    site_type: 'fixed',
    mobile_status: '',
    current_location: '',
    location_notes: '',
    manager_id: null,
  })

  /**
   * Tracks whether the form has been modified since the last load/save.
   * Used by useUnsavedChangesGuard to block accidental navigation.
   */
  const isDirty = computed(() => {
    if (!originalForm.value) return false

    return JSON.stringify(form.value) !== JSON.stringify(originalForm.value)
  })

  // ── Resolve ID from various ref/function forms ────────────────────────────
  const resolveId = () =>
    typeof targetSiteId === 'function'
      ? targetSiteId()
      : (targetSiteId?.value ?? targetSiteId)

  // ── Fetch Site ────────────────────────────────────────────────────────────
  const fetchSite = async () => {
    const id = resolveId()

    if (!id) return

    loading.value = true
    pageError.value = null

    try {
      const site = await store.fetchSite(id)

      fetchedSite.value = site

      form.value = {
        code: site.code ?? '',
        name: typeof site.name === 'object' ? { en: '', fr: '', ar: '', ...site.name } : { en: site.name ?? '' },
        address: site.address ?? '',
        city: site.city ?? '',
        country: site.country ?? '',
        phone: site.phone ?? '',
        email: site.email ?? '',
        status: site.status ?? 'active',
        notes: site.notes ?? '',
        site_type: site.site_type ?? 'fixed',
        mobile_status: site.mobile_status ?? '',
        current_location: site.current_location ?? '',
        location_notes: site.location_notes ?? '',
        manager_id: site.manager?.id ?? site.manager_id ?? null,
      }

      originalForm.value = JSON.parse(JSON.stringify(form.value))

      return site
    } catch (err) {
      console.error('[useWarehouseSiteEdit] Error fetching site:', err)
      pageError.value = err?.data?.message || t('inventory.sites.messages.load_failed')

      return null
    } finally {
      loading.value = false
    }
  }

  // ── Save ──────────────────────────────────────────────────────────────────
  const handleSave = async () => {
    const id = resolveId()

    if (!id) return false

    saving.value = true
    try {
      const payload = {
        code: form.value.code,
        name: form.value.name,
        address: form.value.address || null,
        city: form.value.city || null,
        country: form.value.country || null,
        phone: form.value.phone || null,
        email: form.value.email || null,
        status: form.value.status,
        notes: form.value.notes || null,
        site_type: form.value.site_type || 'fixed',
        mobile_status: form.value.mobile_status || null,
        current_location: form.value.current_location || null,
        location_notes: form.value.location_notes || null,
        manager_id: form.value.manager_id || null,
      }

      await store.updateSite(id, payload)
      originalForm.value = JSON.parse(JSON.stringify(form.value))
      showSuccess(t('inventory.sites.messages.updated'))
      await fetchSite()

      return true
    } catch (err) {
      console.error('[useWarehouseSiteEdit] Error saving site:', err)
      showApiError(err, t('inventory.sites.messages.update_failed'))

      return false
    } finally {
      saving.value = false
    }
  }

  // ── Delete ────────────────────────────────────────────────────────────────
  const handleDelete = async () => {
    const id = resolveId()

    if (!id) return false

    try {
      await store.deleteSite(id)
      showSuccess(t('inventory.sites.messages.deleted'))
      await router.push({ name: 'inventory-warehouse-sites-list' })

      return true
    } catch (err) {
      console.error('[useWarehouseSiteEdit] Error deleting site:', err)
      showError(t('inventory.sites.messages.delete_failed'))

      return false
    }
  }

  return {
    loading,
    saving,
    pageError,
    fetchedSite,
    form,
    isDirty,
    fetchSite,
    handleSave,
    handleDelete,
  }
}
