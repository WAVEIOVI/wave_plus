<script setup>
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useVehicleStore } from '@/stores/useVehicleStore'
import { useAppSnackbar } from '@/composables/useAppSnackbar'

const props = defineProps({
  vehicle: {
    type: Object,
    required: true,
  },
  fuelRecords: {
    type: Array,
    default: () => [],
  },
  userOptions: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['refresh'])

const { t } = useI18n()
const store = useVehicleStore()
const { showMessage } = useAppSnackbar()

const isAddOpen = ref(false)
const isSubmitting = ref(false)

const blankForm = () => ({
  fill_date: new Date().toISOString().split('T')[0],
  fill_time: new Date().toTimeString().split(' ')[0].substring(0, 5),
  fuel_type: props.vehicle.fuel_type || 'diesel',
  quantity: null,
  unit: 'L',
  unit_price: null,
  total_cost: null,
  currency: 'TND',
  odometer_km: props.vehicle.odometer_km || null,
  supplier: '',
  station: '',
  city: '',
  payment_method: 'fleet_card',
  filled_by_employee_id: null,
  driver_id: null,
  is_full_tank: true,
  notes: '',
})

const form = reactive(blankForm())

const fuelTypeOptions = [
  { title: 'Diesel', value: 'diesel' },
  { title: 'Petrol', value: 'petrol' },
  { title: 'Electric (kWh)', value: 'electric' },
  { title: 'Hybrid', value: 'hybrid' },
  { title: 'LPG', value: 'lpg' },
  { title: 'CNG', value: 'cng' },
  { title: 'Hydrogen', value: 'hydrogen' },
]

const paymentMethodOptions = [
  { title: 'Fleet Card', value: 'fleet_card' },
  { title: 'Corporate Credit Card', value: 'corporate_card' },
  { title: 'Cash', value: 'cash' },
  { title: 'Reimbursement Voucher', value: 'voucher' },
  { title: 'Direct Account Invoicing', value: 'account' },
]

const list = computed(() => {
  return props.fuelRecords.length ? props.fuelRecords : (props.vehicle.fuel_records || props.vehicle.fuelRecords || [])
})

// Metrics totals
const totalLiters = computed(() => {
  return list.value.reduce((acc, r) => acc + (Number(r.quantity) || 0), 0)
})

const totalCost = computed(() => {
  return list.value.reduce((acc, r) => acc + (Number(r.total_cost) || 0), 0)
})

const avgCostPerLiter = computed(() => {
  if (totalLiters.value === 0) return 0
  return totalCost.value / totalLiters.value
})

const formatDate = val => {
  if (!val) return '—'
  try {
    return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(val))
  } catch {
    return val
  }
}

// Auto calculate total cost if quantity and unit_price provided
const onPriceOrQtyChange = () => {
  const qty = Number(form.quantity)
  const price = Number(form.unit_price)
  if (qty > 0 && price > 0 && !form.total_cost) {
    form.total_cost = Number((qty * price).toFixed(2))
  }
}

const openAdd = () => {
  Object.assign(form, blankForm())
  isAddOpen.value = true
}

const saveFuelRecord = async () => {
  isSubmitting.value = true
  try {
    const payload = {
      ...form,
      quantity: Number(form.quantity),
      total_cost: Number(form.total_cost),
      unit_price: form.unit_price ? Number(form.unit_price) : null,
      odometer_km: form.odometer_km ? Number(form.odometer_km) : null,
    }
    await store.createFuelRecord(props.vehicle.id, payload)
    showMessage('Fuel record added successfully', 'success')
    isAddOpen.value = false
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || 'Failed to add fuel record', 'error')
  } finally {
    isSubmitting.value = false
  }
}

const deleteFuelRecord = async id => {
  if (!confirm('Are you sure you want to remove this fuel record?')) return
  try {
    await store.deleteFuelRecord(id, props.vehicle.id)
    showMessage('Fuel record deleted successfully', 'success')
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || 'Failed to delete fuel record', 'error')
  }
}
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- KPI Summary Row -->
    <VRow>
      <VCol
        cols="12"
        sm="4"
      >
        <VCard variant="tonal" color="primary">
          <VCardText class="d-flex align-center justify-space-between pa-4">
            <div>
              <div class="text-caption text-medium-emphasis">
                {{ $t('fleet.total_liters') || 'Total Fuel Dispensed' }}
              </div>
              <div class="text-h5 font-weight-bold">
                {{ totalLiters.toLocaleString(undefined, { maximumFractionDigits: 1 }) }} <span class="text-caption">L</span>
              </div>
            </div>
            <VAvatar color="primary" variant="tonal" size="44">
              <VIcon icon="tabler-gas-station" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        sm="4"
      >
        <VCard variant="tonal" color="success">
          <VCardText class="d-flex align-center justify-space-between pa-4">
            <div>
              <div class="text-caption text-medium-emphasis">
                {{ $t('fleet.total_fuel_cost') || 'Total Fuel Expenditure' }}
              </div>
              <div class="text-h5 font-weight-bold">
                {{ totalCost.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} <span class="text-caption">SAR</span>
              </div>
            </div>
            <VAvatar color="success" variant="tonal" size="44">
              <VIcon icon="tabler-currency-dollar" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        sm="4"
      >
        <VCard variant="tonal" color="info">
          <VCardText class="d-flex align-center justify-space-between pa-4">
            <div>
              <div class="text-caption text-medium-emphasis">
                {{ $t('fleet.avg_fuel_cost') || 'Average Cost / Liter' }}
              </div>
              <div class="text-h5 font-weight-bold">
                {{ avgCostPerLiter.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} <span class="text-caption">SAR / L</span>
              </div>
            </div>
            <VAvatar color="info" variant="tonal" size="44">
              <VIcon icon="tabler-calculator" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Toolbar -->
    <div class="d-flex justify-space-between align-center">
      <div>
        <h3 class="text-h6 font-weight-bold">
          {{ $t('fleet.fuel') || 'Fuel & Energy Logs' }}
        </h3>
        <p class="text-caption text-medium-emphasis mb-0">
          Refueling events, odometer readings, station locations, and expenditure logs.
        </p>
      </div>

      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="openAdd"
      >
        {{ $t('fleet.add_fuel_record') || 'Record Refuel' }}
      </VBtn>
    </div>

    <!-- Table -->
    <VCard variant="outlined">
      <VTable
        hover
        density="comfortable"
      >
        <thead>
          <tr>
            <th>Date & Time</th>
            <th>Quantity</th>
            <th>Total Cost</th>
            <th>Unit Price</th>
            <th>Odometer</th>
            <th>Station / Supplier</th>
            <th>Driver / Staff</th>
            <th>Payment</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in list"
            :key="item.id"
          >
            <td>
              <div class="font-weight-medium">{{ formatDate(item.fill_date) }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.fill_time || '' }}</div>
            </td>
            <td>
              <span class="font-weight-medium">{{ Number(item.quantity).toLocaleString() }}</span> {{ item.unit || 'L' }}
            </td>
            <td>
              <span class="font-weight-bold text-success">{{ Number(item.total_cost).toLocaleString() }}</span> {{ item.currency || 'SAR' }}
            </td>
            <td>
              {{ item.unit_price != null ? `${Number(item.unit_price).toFixed(2)} ${item.currency || 'SAR'}` : '—' }}
            </td>
            <td>
              {{ item.odometer_km != null ? `${Number(item.odometer_km).toLocaleString()} km` : '—' }}
            </td>
            <td>
              <div>{{ item.station || item.supplier || '—' }}</div>
              <div v-if="item.city" class="text-caption text-medium-emphasis">{{ item.city }}</div>
            </td>
            <td>
              {{ item.driver?.name || item.filled_by_employee?.name || '—' }}
            </td>
            <td>
              <VChip
                size="x-small"
                variant="tonal"
                class="text-capitalize"
              >
                {{ item.payment_method?.replaceAll('_', ' ') || 'Default' }}
              </VChip>
            </td>
            <td class="text-end">
              <VBtn
                variant="text"
                color="error"
                density="compact"
                icon="tabler-trash"
                size="small"
                @click="deleteFuelRecord(item.id)"
              />
            </td>
          </tr>

          <tr v-if="!list.length">
            <td
              colspan="9"
              class="text-center py-6 text-disabled"
            >
              <VIcon
                icon="tabler-gas-station-off"
                size="32"
                class="mb-2"
              />
              <div>No fuel records logged for this vehicle.</div>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- Add Fuel Dialog -->
    <VDialog
      v-model="isAddOpen"
      max-width="640"
      persistent
    >
      <DialogCloseBtn @click="isAddOpen = false" />
      <VCard :title="$t('fleet.add_fuel_record') || 'Log Vehicle Refueling'">
        <VCardText class="pt-2">
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.fill_date"
                label="Fill Date *"
                type="date"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.fill_time"
                label="Fill Time"
                type="time"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model.number="form.quantity"
                label="Quantity (Liters) *"
                type="number"
                step="0.01"
                min="0.1"
                suffix="L"
                required
                @input="onPriceOrQtyChange"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model.number="form.unit_price"
                label="Price / Liter"
                type="number"
                step="0.01"
                min="0"
                placeholder="Optional"
                @input="onPriceOrQtyChange"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <AppTextField
                v-model.number="form.total_cost"
                label="Total Cost *"
                type="number"
                step="0.01"
                min="0"
                suffix="SAR"
                required
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model.number="form.odometer_km"
                label="Odometer at Refuel (km)"
                type="number"
                min="0"
                suffix="km"
                placeholder="Current reading"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.fuel_type"
                label="Fuel Type"
                :items="fuelTypeOptions"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.supplier"
                label="Gas Station / Supplier"
                placeholder="e.g. Aramco, Sasco, Shell"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.station"
                label="Station Branch / City"
                placeholder="e.g. Airport Highway Branch, Riyadh"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.payment_method"
                label="Payment Method"
                :items="paymentMethodOptions"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.driver_id"
                label="Driver / Personnel"
                :items="userOptions"
                clearable
                placeholder="Select driver"
              />
            </VCol>
            <VCol cols="12">
              <VSwitch
                v-model="form.is_full_tank"
                label="Full Tank Fill"
                color="primary"
                density="compact"
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="form.notes"
                label="Remarks"
                rows="2"
              />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="justify-end pa-4 gap-2 border-t">
          <VBtn
            variant="tonal"
            :disabled="isSubmitting"
            @click="isAddOpen = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="isSubmitting"
            prepend-icon="tabler-device-floppy"
            @click="saveFuelRecord"
          >
            {{ $t('Save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
