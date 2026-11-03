import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useVehicleStore } from '@/stores/useVehicleStore'
import { useCurrency } from '@/composables/useCurrency'
import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

export const useVehicleWorkflow = (onSuccess = () => {}) => {
  const { t } = useI18n()
  const { showMessage } = useAppSnackbar()
  const { currency: defaultCurrency } = useCurrency()
  const store = useVehicleStore()

  // Dialog visibilities
  const isFormOpen = ref(false)
  const isAssignOpen = ref(false)
  const isReturnOpen = ref(false)
  const isStatusOpen = ref(false)
  const isOdometerOpen = ref(false)
  const isRentalOpen = ref(false)
  const isClientUsageOpen = ref(false)
  const isDeleteOpen = ref(false)
  const isSubmitting = ref(false)

  // Target vehicle
  const targetVehicle = ref(null)

  // Form blanks
  const blankForm = () => ({
    id: null,
    fleet_code: '',
    plate_number: '',
    label: '',
    make: '',
    model: '',
    year: null,
    vin: '',
    registration_number: '',
    registration_date: null,
    color: '',
    type: 'van',
    vehicle_class: 'light',
    ownership_type: 'company',
    status: 'available',
    capacity_kg: null,
    capacity_m3: null,
    seats: null,
    fuel_type: 'diesel',
    fuel_tank_capacity: null,
    battery_capacity_kwh: null,
    odometer_km: null,
    next_service_date: null,
    next_inspection_date: null,
    insurance_expiry_date: null,
    registration_expiry_date: null,
    last_inspection_date: null,
    last_service_date: null,
    default_driver_id: null,
    notes: '',
  })

  const form = reactive(blankForm())

  const assignment = reactive({
    responsible_employee_id: null,
    custody_type: 'company_site',
    site_id: null,
    location_notes: '',
    start_date: new Date().toISOString().split('T')[0],
    reason: '',
    notes: '',
    odometer_at_start: null,
  })

  const returnData = reactive({
    end_date: new Date().toISOString().split('T')[0],
    odometer_at_end: null,
    vehicle_status: 'available',
    notes: '',
  })

  const statusData = reactive({
    status: 'available',
    reason: '',
  })

  const odometerData = reactive({
    current_odometer: 0,
    odometer_km: null,
  })

  const rentalData = reactive({
    isEnding: false,
    rental_provider_id: null,
    rental_start_date: new Date().toISOString().split('T')[0],
    rental_end_date: null,
    rental_contract_reference: '',
    rental_daily_rate: null,
    rental_currency: defaultCurrency.value,
    rental_return_date: new Date().toISOString().split('T')[0],
  })

  const clientUsageData = reactive({
    isEnding: false,
    client_id: null,
    client_supplies_driver: false,
    client_usage_start: new Date().toISOString().split('T')[0],
    client_usage_end: null,
  })

  // Options
  const statusOptions = [
    { title: 'Available', value: 'available' },
    { title: 'In Use', value: 'in_use' },
    { title: 'Maintenance', value: 'maintenance' },
    { title: 'Out of Service', value: 'out_of_service' },
    { title: 'Rental Active', value: 'rental_active' },
    { title: 'Client Use', value: 'client_use' },
    { title: 'Retired', value: 'retired' },
    { title: 'Sold', value: 'sold' },
  ]

  const typeOptions = [
    { title: 'Van', value: 'van' },
    { title: 'Truck', value: 'truck' },
    { title: 'Car', value: 'car' },
    { title: 'Pickup', value: 'pickup' },
    { title: 'Motorcycle', value: 'motorcycle' },
    { title: 'Bus', value: 'bus' },
    { title: 'Forklift', value: 'forklift' },
    { title: 'Trailer', value: 'trailer' },
    { title: 'Other', value: 'other' },
  ]

  const ownershipOptions = [
    { title: 'Company', value: 'company' },
    { title: 'Rental', value: 'rental' },
    { title: 'Client', value: 'client' },
  ]

  const custodyOptions = [
    { title: 'Company Site', value: 'company_site' },
    { title: 'Employee Home', value: 'employee_home' },
    { title: 'Mission', value: 'mission' },
    { title: 'Maintenance', value: 'maintenance' },
    { title: 'Rental', value: 'rental' },
    { title: 'Client', value: 'client' },
    { title: 'Storage', value: 'storage' },
    { title: 'Other', value: 'other' },
  ]

  const fuelTypeOptions = [
    { title: 'Diesel', value: 'diesel' },
    { title: 'Petrol', value: 'petrol' },
    { title: 'Electric', value: 'electric' },
    { title: 'Hybrid', value: 'hybrid' },
    { title: 'LPG', value: 'lpg' },
    { title: 'CNG', value: 'cng' },
    { title: 'Hydrogen', value: 'hydrogen' },
  ]

  const returnStatusOptions = [
    { title: 'Available', value: 'available' },
    { title: 'Maintenance Required', value: 'maintenance' },
    { title: 'Out of Service', value: 'out_of_service' },
  ]

  // Helpers
  const statusColor = val => ({
    available: 'success',
    in_use: 'info',
    maintenance: 'warning',
    out_of_service: 'error',
    rental_active: 'primary',
    client_use: 'secondary',
    retired: 'secondary',
    sold: 'secondary',
  }[val] || 'default')

  const formatDate = val => {
    if (!val) return '—'
    try {
      return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(val))
    } catch {
      return val
    }
  }

  // Action openers
  const openCreate = () => {
    Object.assign(form, blankForm())
    targetVehicle.value = null
    isFormOpen.value = true
  }

  const openEdit = vehicle => {
    targetVehicle.value = vehicle
    Object.assign(form, blankForm(), vehicle || {})
    isFormOpen.value = true
  }

  const openAssign = vehicle => {
    targetVehicle.value = vehicle
    Object.assign(assignment, {
      responsible_employee_id: vehicle.current_responsible_employee_id || vehicle.current_assignment?.responsible_employee_id || null,
      custody_type: vehicle.custody_type || 'company_site',
      site_id: vehicle.current_site_id || null,
      location_notes: vehicle.current_location_notes || '',
      start_date: new Date().toISOString().split('T')[0],
      reason: '',
      notes: '',
      odometer_at_start: vehicle.odometer_km || null,
    })
    isAssignOpen.value = true
  }

  const openReturn = vehicle => {
    targetVehicle.value = vehicle
    Object.assign(returnData, {
      end_date: new Date().toISOString().split('T')[0],
      odometer_at_end: vehicle.odometer_km || null,
      vehicle_status: 'available',
      notes: '',
    })
    isReturnOpen.value = true
  }

  const openStatus = vehicle => {
    targetVehicle.value = vehicle
    Object.assign(statusData, {
      status: vehicle.status || 'available',
      reason: '',
    })
    isStatusOpen.value = true
  }

  const openOdometer = vehicle => {
    targetVehicle.value = vehicle
    Object.assign(odometerData, {
      current_odometer: vehicle.odometer_km ?? 0,
      odometer_km: vehicle.odometer_km ?? 0,
    })
    isOdometerOpen.value = true
  }

  const openRental = (vehicle, isEnding = false) => {
    targetVehicle.value = vehicle
    rentalData.isEnding = isEnding
    if (isEnding) {
      rentalData.rental_return_date = new Date().toISOString().split('T')[0]
    } else {
      rentalData.rental_provider_id = vehicle.rental_provider_id || null
      rentalData.rental_start_date = vehicle.rental_start_date || new Date().toISOString().split('T')[0]
      rentalData.rental_end_date = vehicle.rental_end_date || null
      rentalData.rental_contract_reference = vehicle.rental_contract_reference || ''
      rentalData.rental_daily_rate = vehicle.rental_daily_rate || null
      rentalData.rental_currency = vehicle.rental_currency || defaultCurrency.value
    }
    isRentalOpen.value = true
  }

  const openClientUsage = (vehicle, isEnding = false) => {
    targetVehicle.value = vehicle
    clientUsageData.isEnding = isEnding
    if (!isEnding) {
      clientUsageData.client_id = vehicle.client_id || null
      clientUsageData.client_supplies_driver = Boolean(vehicle.client_supplies_driver)
      clientUsageData.client_usage_start = vehicle.client_usage_start || new Date().toISOString().split('T')[0]
      clientUsageData.client_usage_end = vehicle.client_usage_end || null
    }
    isClientUsageOpen.value = true
  }

  const openDelete = vehicle => {
    targetVehicle.value = vehicle
    isDeleteOpen.value = true
  }

  // Action submitters
  const submitForm = async () => {
    isSubmitting.value = true
    try {
      const payload = { ...form }
      if (form.id) {
        await store.update(form.id, payload)
        showMessage(t('Saved') || 'Vehicle updated successfully', 'success')
      } else {
        await store.create(payload)
        showMessage(t('Created') || 'Vehicle created successfully', 'success')
      }
      isFormOpen.value = false
      await onSuccess()
    } catch (err) {
      showMessage(err?.data?.message || err?.message || 'Failed to save vehicle', 'error')
      throw err
    } finally {
      isSubmitting.value = false
    }
  }

  const submitAssign = async () => {
    if (!targetVehicle.value?.id) return
    isSubmitting.value = true
    try {
      await store.assignVehicle(targetVehicle.value.id, { ...assignment })
      showMessage(t('fleet.assign_success') || 'Vehicle assigned successfully', 'success')
      isAssignOpen.value = false
      await onSuccess()
    } catch (err) {
      showMessage(err?.data?.message || err?.message || 'Failed to assign vehicle', 'error')
      throw err
    } finally {
      isSubmitting.value = false
    }
  }

  const submitReturn = async () => {
    if (!targetVehicle.value?.id) return
    isSubmitting.value = true
    try {
      await store.returnVehicle(targetVehicle.value.id, { ...returnData })
      showMessage(t('fleet.return_success') || 'Vehicle returned successfully', 'success')
      isReturnOpen.value = false
      await onSuccess()
    } catch (err) {
      showMessage(err?.data?.message || err?.message || 'Failed to return vehicle', 'error')
      throw err
    } finally {
      isSubmitting.value = false
    }
  }

  const submitStatus = async () => {
    if (!targetVehicle.value?.id) return
    isSubmitting.value = true
    try {
      await store.updateStatus(targetVehicle.value.id, { ...statusData })
      showMessage(t('fleet.status_updated') || 'Status updated successfully', 'success')
      isStatusOpen.value = false
      await onSuccess()
    } catch (err) {
      showMessage(err?.data?.message || err?.message || 'Failed to update status', 'error')
      throw err
    } finally {
      isSubmitting.value = false
    }
  }

  const submitOdometer = async () => {
    if (!targetVehicle.value?.id) return
    isSubmitting.value = true
    try {
      await store.updateOdometer(targetVehicle.value.id, { odometer_km: Number(odometerData.odometer_km) })
      showMessage(t('fleet.odometer_updated') || 'Odometer updated successfully', 'success')
      isOdometerOpen.value = false
      await onSuccess()
    } catch (err) {
      showMessage(err?.data?.message || err?.message || 'Failed to update odometer', 'error')
      throw err
    } finally {
      isSubmitting.value = false
    }
  }

  const submitRental = async () => {
    if (!targetVehicle.value?.id) return
    isSubmitting.value = true
    try {
      if (rentalData.isEnding) {
        await store.endRental(targetVehicle.value.id, { rental_return_date: rentalData.rental_return_date })
        showMessage('Rental period ended successfully', 'success')
      } else {
        await store.startRental(targetVehicle.value.id, {
          rental_provider_id: rentalData.rental_provider_id,
          rental_start_date: rentalData.rental_start_date,
          rental_end_date: rentalData.rental_end_date,
          rental_contract_reference: rentalData.rental_contract_reference || null,
          rental_daily_rate: rentalData.rental_daily_rate ? Number(rentalData.rental_daily_rate) : null,
          rental_currency: rentalData.rental_currency || defaultCurrency.value,
        })
        showMessage('Rental period started successfully', 'success')
      }
      isRentalOpen.value = false
      await onSuccess()
    } catch (err) {
      showMessage(err?.data?.message || err?.message || 'Failed to update rental status', 'error')
      throw err
    } finally {
      isSubmitting.value = false
    }
  }

  const submitClientUsage = async () => {
    if (!targetVehicle.value?.id) return
    isSubmitting.value = true
    try {
      if (clientUsageData.isEnding) {
        await store.endClientUsage(targetVehicle.value.id)
        showMessage('Client vehicle usage ended successfully', 'success')
      } else {
        await store.startClientUsage(targetVehicle.value.id, {
          client_id: clientUsageData.client_id,
          client_supplies_driver: clientUsageData.client_supplies_driver,
          client_usage_start: clientUsageData.client_usage_start,
          client_usage_end: clientUsageData.client_usage_end || null,
        })
        showMessage('Client vehicle usage started successfully', 'success')
      }
      isClientUsageOpen.value = false
      await onSuccess()
    } catch (err) {
      showMessage(err?.data?.message || err?.message || 'Failed to update client usage', 'error')
      throw err
    } finally {
      isSubmitting.value = false
    }
  }

  const submitDelete = async () => {
    if (!targetVehicle.value?.id) return false
    isSubmitting.value = true
    try {
      await store.remove(targetVehicle.value.id)
      showMessage(t('fleet.deleted') || 'Vehicle deleted successfully', 'success')
      isDeleteOpen.value = false
      await onSuccess()

      return true
    } catch (err) {
      showMessage(err?.data?.message || err?.message || 'Failed to delete vehicle', 'error')

      return false
    } finally {
      isSubmitting.value = false
    }
  }

  return {
    // States
    isFormOpen,
    isAssignOpen,
    isReturnOpen,
    isStatusOpen,
    isOdometerOpen,
    isRentalOpen,
    isClientUsageOpen,
    isDeleteOpen,
    isSubmitting,
    targetVehicle,

    // Form states
    form,
    assignment,
    returnData,
    statusData,
    odometerData,
    rentalData,
    clientUsageData,

    // Options
    statusOptions,
    typeOptions,
    ownershipOptions,
    custodyOptions,
    fuelTypeOptions,
    returnStatusOptions,

    // Formatters
    statusColor,
    formatDate,

    // Openers
    openCreate,
    openEdit,
    openAssign,
    openReturn,
    openStatus,
    openOdometer,
    openRental,
    openClientUsage,
    openDelete,

    // Submitters
    submitForm,
    submitAssign,
    submitReturn,
    submitStatus,
    submitOdometer,
    submitRental,
    submitClientUsage,
    submitDelete,
  }
}
