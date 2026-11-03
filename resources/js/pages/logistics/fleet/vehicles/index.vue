<script setup>
/* eslint-disable camelcase */
// Logistics consumes HR-owned fleet master data (read-only compatibility reads).
// Vehicle create / edit / lifecycle workflows live under People & Skills → Fleet.
// This page only surfaces the data Logistics needs for delivery planning (DWOs / waves).
import { WpPageHeader, WpPageToolbar, WpResponsiveList, WpStatusChip } from '@/components/shared'
import { useVehicleStore } from '@/stores/useVehicleStore'
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'

definePage({
  path: '/logistics/fleet/vehicles',
  name: 'logistics-fleet-vehicles',
  meta: {
    layout: 'default',
    module: 'logistics',
    action: 'read',
    subject: 'Logistics',
  },
})

const { t } = useI18n()
const router = useRouter()
const store = useVehicleStore()
const filterOpen = ref(false)
const search = ref('')

const statusFilter = ref(null)
const typeFilter = ref(null)
const ownershipFilter = ref(null)
const perPage = ref(15)

const page = ref(1)
const sortBy = ref([{ key: 'created_at', order: 'desc' }])

const activeFilterCount = computed(() => [statusFilter, typeFilter, ownershipFilter].filter(f => f.value).length)

const fetchVehicles = () => {
  const params = {
    page: page.value,
    per_page: perPage.value,
    sort_by: sortBy.value[0]?.key || 'created_at',
    sort_order: sortBy.value[0]?.order || 'desc',
  }

  if (search.value) params.search = search.value
  if (statusFilter.value) params.status = statusFilter.value
  if (typeFilter.value) params.type = typeFilter.value
  if (ownershipFilter.value) params.ownership_type = ownershipFilter.value

  return store.fetchList(params)
}

const refresh = () => fetchVehicles()

watch(search, () => {
  page.value = 1
  fetchVehicles()
})

onMounted(() => {
  refresh()
})

const headers = computed(() => [
  { title: t('fleet.plate_number') || 'Vehicle', key: 'vehicle', sortable: false, minWidth: '220px' },
  { title: t('fleet.type') || 'Type', key: 'type', sortable: false },
  { title: t('fleet.ownership') || 'Ownership', key: 'ownership_type', sortable: false },
  { title: t('fleet.capacity') || 'Payload (kg)', key: 'capacity_kg', sortable: false },
  { title: t('fleet.odometer') || 'Odometer', key: 'odometer_km', sortable: false },
  { title: t('fleet.status') || 'Status', key: 'status', sortable: false },
])

const dispatchable = vehicle => vehicle.status === 'available'

const statusLabel = status => {
  if (!status) return '—'

  return t(`fleet.status_${status}`, status.replaceAll('_', ' '))
}

const openInHr = id => {
  // Fleet master data management is done in the authoritative People & Skills module.
  router.push(`/people-skills/fleet/vehicles/${id}`)
}
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <WpPageHeader
      :title="$t('fleet.vehicles_title') || 'Fleet Vehicles'"
      :subtitle="$t('fleet.vehicles_subtitle_logistics') || 'Fleet master data is managed by People & Skills — read-only view for delivery planning'"
      icon="tabler-car"
    >
      <template #actions>
        <VAlert
          type="info"
          variant="tonal"
          density="compact"
          max-width="420"
        >
          {{ $t('fleet.read_only_notice') || 'Read-only: manage vehicles in People & Skills → Fleet.' }}
        </VAlert>
      </template>
    </WpPageHeader>

    <VCard>
      <WpPageToolbar
        v-model:search="search"
        show-filter
        :active-filter-count="activeFilterCount"
        :loading="store.loading"
        @refresh="refresh"
        @open-filters="filterOpen = true"
      />

      <WpResponsiveList
        v-model:page="page"
        v-model:items-per-page="perPage"
        v-model:sort-by="sortBy"
        :headers="headers"
        :items="store.list"
        :total-items="store.totalItems"
        :loading="store.loading"
        :error="store.error"
        :empty-title="$t('fleet.no_vehicles') || 'No vehicles found'"
        :empty-subtitle="$t('fleet.no_vehicles_hint') || 'Fleet vehicles created in People & Skills will appear here.'"
        empty-icon="tabler-car-off"
        @update:page="refresh"
        @update:items-per-page="refresh"
        @retry="refresh"
      >
        <template #item.vehicle="{ item }">
          <div class="d-flex align-center gap-3 py-2">
            <VAvatar
              color="primary"
              variant="tonal"
            >
              <VIcon icon="tabler-car" />
            </VAvatar>
            <div>
              <div class="font-weight-medium">
                {{ item.plate_number }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.label }}
                <span v-if="item.make || item.model">
                  · {{ [item.make, item.model].filter(Boolean).join(' ') }}
                </span>
              </div>
            </div>
          </div>
        </template>

        <template #item.type="{ item }">
          <VChip
            size="small"
            label
            class="text-capitalize"
          >
            {{ item.type }}
          </VChip>
        </template>

        <template #item.ownership_type="{ item }">
          <span class="text-capitalize">{{ item.ownership_type }}</span>
        </template>

        <template #item.capacity_kg="{ item }">
          {{ item.capacity_kg != null ? `${Number(item.capacity_kg).toLocaleString()} kg` : '—' }}
        </template>

        <template #item.odometer_km="{ item }">
          {{ item.odometer_km != null ? `${Number(item.odometer_km).toLocaleString()} km` : '—' }}
        </template>

        <template #item.status="{ item }">
          <div class="d-flex align-center gap-2">
            <WpStatusChip
              type="vehicle"
              :status="item.status"
              :label="statusLabel(item.status)"
            />
            <VChip
              v-if="dispatchable(item)"
              size="x-small"
              color="success"
              variant="tonal"
            >
              {{ $t('fleet.dispatchable') || 'Dispatchable' }}
            </VChip>
          </div>
        </template>

        <template #mobile-card="{ item }">
          <div
            class="d-flex align-center gap-3 py-2 cursor-pointer"
            @click="openInHr(item.id)"
          >
            <VAvatar
              color="primary"
              variant="tonal"
            >
              <VIcon icon="tabler-car" />
            </VAvatar>
            <div>
              <div class="font-weight-medium">
                {{ item.plate_number }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.label }} · {{ item.type }}
              </div>
            </div>
          </div>
        </template>
      </WpResponsiveList>
    </VCard>

    <!-- Filters -->
    <VDialog
      v-model="filterOpen"
      max-width="480"
    >
      <VCard :title="$t('Filters') || 'Filters'">
        <VCardText>
          <VRow>
            <VCol cols="12">
              <AppSelect
                v-model="statusFilter"
                :label="$t('fleet.status') || 'Status'"
                :items="[
                  { title: 'Available', value: 'available' },
                  { title: 'In Use', value: 'in_use' },
                  { title: 'Maintenance', value: 'maintenance' },
                  { title: 'Out of Service', value: 'out_of_service' },
                  { title: 'Rental Active', value: 'rental_active' },
                  { title: 'Client Use', value: 'client_use' },
                  { title: 'Retired', value: 'retired' },
                  { title: 'Sold', value: 'sold' },
                ]"
                clearable
              />
            </VCol>
            <VCol cols="12">
              <AppSelect
                v-model="typeFilter"
                :label="$t('fleet.type') || 'Vehicle Type'"
                :items="[
                  { title: 'Van', value: 'van' },
                  { title: 'Truck', value: 'truck' },
                  { title: 'Car', value: 'car' },
                  { title: 'Pickup', value: 'pickup' },
                  { title: 'Motorcycle', value: 'motorcycle' },
                  { title: 'Bus', value: 'bus' },
                  { title: 'Forklift', value: 'forklift' },
                  { title: 'Trailer', value: 'trailer' },
                  { title: 'Other', value: 'other' },
                ]"
                clearable
              />
            </VCol>
            <VCol cols="12">
              <AppSelect
                v-model="ownershipFilter"
                :label="$t('fleet.ownership') || 'Ownership Type'"
                :items="[
                  { title: 'Company', value: 'company' },
                  { title: 'Rental', value: 'rental' },
                  { title: 'Client', value: 'client' },
                ]"
                clearable
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="justify-end pa-4 gap-2 border-t">
          <VBtn
            variant="tonal"
            @click="() => { statusFilter = null; typeFilter = null; ownershipFilter = null; refresh(); filterOpen = false }"
          >
            {{ $t('Reset') || 'Reset' }}
          </VBtn>
          <VBtn
            color="primary"
            @click="() => { refresh(); filterOpen = false }"
          >
            {{ $t('Apply') || 'Apply' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
