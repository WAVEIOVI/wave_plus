/* eslint-disable camelcase */
import { localizedValue } from '@/composables/useLocaleField'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useClientStore } from '@/stores/useClientStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

export function useAddNewSiteWizard(isOpen, emit) {
  const store = useClientAssetStore()
  const clientStore = useClientStore()
  const snackbarStore = useSnackbarStore()
  const router = useRouter()
  const { t } = useI18n()

  const isSubmitting = ref(false)
  const addressesLoading = ref(false)
  const selectedClient = ref(null)
  const clientAddressesRaw = ref([])

  const form = ref({
    client_id: null,
    bp_address_id: null,
    name: '',
    site_type: 'office',
  })

  const addressTypeToSiteType = {
    headquarters: 'office',
    operational_site: 'office',
    billing: 'office',
    shipping: 'warehouse',
    warehouse: 'warehouse',
    factory: 'factory',
    plant: 'factory',
    retail: 'retail',
    store: 'retail',
    hospital: 'hospital',
    school: 'school',
    hotel: 'hotel',
    datacenter: 'datacenter',
    construction: 'construction_site',
  }

  const formatAddressLabel = addr => {
    if (!addr) return ''
    const street = localizedValue(addr.address_street) || ''
    const city = localizedValue(addr.city) || ''
    const label = localizedValue(addr.label) || ''
    const typeLabel = (addr.type || 'operational_site').replace(/_/g, ' ')

    if (label) return label
    if (street || city) return `${street}${street && city ? ', ' : ''}${city}`

    return typeLabel
  }

  const clientAddresses = computed(() => {
    if (!clientAddressesRaw.value?.length) return []

    return clientAddressesRaw.value.map(addr => ({
      id: addr.id,
      title: formatAddressLabel(addr),
      subtitle: [addr.postcode, addr.country].filter(Boolean).join(' · ') || addr.phone || '',
      type: addr.type || 'operational_site',
      raw: addr,
    }))
  })

  const loadClientAddresses = async clientId => {
    if (!clientId) {
      clientAddressesRaw.value = []

      return
    }

    addressesLoading.value = true
    try {
      const res = await clientStore.fetchClientAddresses(clientId)

      clientAddressesRaw.value = Array.isArray(res) ? res : (res?.data || [])
    }
    catch (err) {
      clientAddressesRaw.value = []
      console.error(`Error fetching addresses for client ${clientId}:`, err)
    }
    finally {
      addressesLoading.value = false
    }
  }

  const applyAddressDefaults = addr => {
    if (!addr) return
    const street = localizedValue(addr.address_street) || ''
    const city = localizedValue(addr.city) || ''
    const label = localizedValue(addr.label) || ''

    form.value.name = label || `${city || t('client_assets.common.site', 'Site')} - ${street || t('client_assets.sites.types.plant', 'Facility')}`
    form.value.site_type = addressTypeToSiteType[addr.type] || 'office'
  }

  const handleSelectClient = async client => {
    selectedClient.value = client
    form.value.client_id = client?.id || null
    form.value.bp_address_id = null

    if (client?.id) {
      await loadClientAddresses(client.id)
    } else {
      clientAddressesRaw.value = []
    }
  }

  const handleClearClient = () => {
    selectedClient.value = null
    form.value.client_id = null
    form.value.bp_address_id = null
    clientAddressesRaw.value = []
  }

  const handleSelectAddress = addressId => {
    form.value.bp_address_id = addressId
    if (addressId) {
      const match = clientAddresses.value.find(a => a.id === addressId)
      if (match?.raw) applyAddressDefaults(match.raw)
    }
  }

  const resetForm = () => {
    selectedClient.value = null
    clientAddressesRaw.value = []
    addressesLoading.value = false
    form.value = {
      client_id: null,
      bp_address_id: null,
      name: '',
      site_type: 'office',
    }
  }

  watch(
    () => (typeof isOpen === 'function' ? isOpen() : isOpen.value),
    visible => {
      if (visible) {
        resetForm()
      }
    },
  )

  const steps = computed(() => [
    {
      id: 'client',
      key: 'client',
      title: t('client_assets.common.client', 'Client'),
      subtitle: t('client_assets.sites.steps.client_desc', 'Select client organization'),
      icon: 'tabler-building-bank',
      validate: () => {
        if (!form.value.client_id) {
          return t('client_assets.common.select_client', 'Please select a client.')
        }

        return true
      },
    },
    {
      id: 'address',
      key: 'address',
      title: t('client_assets.common.address', 'Address'),
      subtitle: t('client_assets.sites.steps.address_desc', 'Link registered address'),
      icon: 'tabler-map-pin',
      validate: () => {
        if (clientAddresses.value.length > 0 && !form.value.bp_address_id) {
          return t('client_assets.common.select_area', 'Please select an address for this site.')
        }

        return true
      },
    },
    {
      id: 'details',
      key: 'details',
      title: t('client_assets.sites.site_details', 'Site Details'),
      subtitle: t('client_assets.sites.steps.details_desc', 'Name and site configuration'),
      icon: 'tabler-building',
      validate: formValues => {
        const siteName = (formValues?.name || form.value.name || '').trim()
        if (!siteName) {
          return { name: t('client_assets.sites.fields.site_name', 'Site name is required.') }
        }

        return true
      },
    },
  ])

  const handleSubmit = async () => {
    isSubmitting.value = true

    try {
      const payload = {
        client_id: form.value.client_id,
        bp_address_id: form.value.bp_address_id || null,
        name: (form.value.name || '').trim(),
        site_type: form.value.site_type || 'office',
      }

      const created = await store.createSite(payload)
      const newId = created?.data?.id || created?.id

      snackbarStore.showMessage(
        t('client_assets.sites.messages.created', 'Site created successfully'),
        'success',
      )

      emit('created', created)
      emit('addSite', created)
      emit('add-site', created)
      emit('update:isDialogVisible', false)
      emit('update:modelValue', false)

      if (newId) {
        router.push({ name: 'client-assets-sites-edit-id', params: { id: newId } })
      }

      return created
    }
    catch (err) {
      console.error('Error creating site:', err)
      throw err
    }
    finally {
      isSubmitting.value = false
    }
  }

  return {
    isSubmitting,
    addressesLoading,
    steps,
    form,
    selectedClient,
    clientAddresses,
    handleSelectClient,
    handleClearClient,
    handleSelectAddress,
    handleSubmit,
    resetForm,
  }
}
