<script setup>
/* eslint-disable camelcase */
import {
  WpDeleteDialog,
  WpEmptyState,
  WpSectionCard,
} from '@/components/shared'
import { useWarehouseSiteActions } from '@/composables/inventory/sites/useWarehouseSiteActions'
import { localizedValue } from '@/composables/useLocaleField'
import AddEditLocationDialog from '@/views/inventory/sites/edit/AddEditLocationDialog.vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  site: {
    type: Object,
    required: true,
  },
  isReadOnly: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['refresh'])

const { t } = useI18n()

const actions = useWarehouseSiteActions({
  onRefresh: async () => {
    emit('refresh')
  },
})

// Dialog state
const isAddEditOpen = ref(false)
const selectedLocation = ref(null)
const isDeleteOpen = ref(false)
const locationToDelete = ref(null)

// Local filter state
const selectedZoneFilter = ref(null)
const searchQuery = ref('')

const siteZones = computed(() => props.site?.zones || [])

// Collect all locations across all zones
const allLocations = computed(() => {
  const result = []

  siteZones.value.forEach(zone => {
    const locs = zone.locations || []

    locs.forEach(loc => {
      result.push({
        ...loc,
        zoneCode: zone.code,
        zoneName: localizedValue(zone.name) || zone.code,
      })
    })
  })

  return result
})

// Filtered locations
const filteredLocations = computed(() => {
  return allLocations.value.filter(loc => {
    if (selectedZoneFilter.value && loc.zone_id !== selectedZoneFilter.value) {
      return false
    }

    if (searchQuery.value) {
      const q = searchQuery.value.toLowerCase()
      const matchPath = loc.full_path?.toLowerCase().includes(q)
      const matchBarcode = loc.barcode?.toLowerCase().includes(q)
      const matchZone = loc.zoneCode?.toLowerCase().includes(q)

      if (!matchPath && !matchBarcode && !matchZone) {
        return false
      }
    }

    return true
  })
})

const openCreateLocation = () => {
  selectedLocation.value = null
  isAddEditOpen.value = true
}

const openEditLocation = loc => {
  selectedLocation.value = loc
  isAddEditOpen.value = true
}

const openDeleteLocation = loc => {
  locationToDelete.value = loc
  isDeleteOpen.value = true
}

const handleSaveLocation = async payload => {
  if (selectedLocation.value?.id) {
    const ok = await actions.updateLocation(selectedLocation.value.id, payload)
    if (ok) isAddEditOpen.value = false
  } else {
    const ok = await actions.createLocation(payload)
    if (ok) isAddEditOpen.value = false
  }
}

const handleDeleteConfirm = async () => {
  if (!locationToDelete.value?.id) return

  const ok = await actions.deleteLocation(locationToDelete.value.id)

  if (ok) {
    isDeleteOpen.value = false
    locationToDelete.value = null
  }
}

const handleToggleActive = async loc => {
  await actions.toggleLocationActive(loc.id)
}
</script>

<template>
  <section
    id="section-locations"
    class="journal-section mb-6"
  >
    <WpSectionCard
      :title="t('inventory.locations.title', 'Bin Locations')"
      :subtitle="t('inventory.locations.subtitle', 'Precise physical coordinate bins (Aisle-Rack-Shelf-Bin) mapped to warehouse zones')"
      icon="tabler-map-pin"
      icon-color="success"
    >
      <template #actions>
        <VBtn
          v-if="!isReadOnly && siteZones.length > 0"
          size="small"
          prepend-icon="tabler-plus"
          color="primary"
          @click="openCreateLocation"
        >
          {{ t('inventory.locations.add', 'New Location') }}
        </VBtn>
      </template>

      <!-- Zone Filter & Search Controls -->
      <div
        v-if="allLocations.length > 0 || siteZones.length > 0"
        class="d-flex flex-wrap align-center gap-3 mb-4"
      >
        <div style="inline-size: 220px;">
          <VSelect
            v-model="selectedZoneFilter"
            :items="siteZones.map(z => ({ title: `${z.code} — ${localizedValue(z.name) || z.code}`, value: z.id }))"
            :label="t('inventory.sites.zone', 'Filter by Zone')"
            density="compact"
            clearable
            hide-details
          />
        </div>

        <div style="inline-size: 260px;">
          <VTextField
            v-model="searchQuery"
            :placeholder="t('Search barcode or path...')"
            prepend-inner-icon="tabler-search"
            density="compact"
            clearable
            hide-details
          />
        </div>

        <div class="ms-auto text-caption text-medium-emphasis">
          {{ filteredLocations.length }} {{ t('inventory.sites.columns.locations', 'Locations') }}
        </div>
      </div>

      <!-- Locations Table -->
      <template v-if="filteredLocations.length > 0">
        <VTable density="compact">
          <thead>
            <tr>
              <th>{{ t('inventory.sites.zone', 'Zone') }}</th>
              <th>{{ t('inventory.stock_items.columns.full_path', 'Coordinate Path') }}</th>
              <th>{{ t('inventory.stock_items.columns.barcode', 'Barcode') }}</th>
              <th>{{ t('inventory.locations.columns.type', 'Type') }}</th>
              <th>{{ t('inventory.locations.columns.max_weight', 'Max Weight') }}</th>
              <th>{{ t('inventory.locations.columns.max_volume', 'Max Vol') }}</th>
              <th class="text-center">
                {{ t('Status') }}
              </th>
              <th
                v-if="!isReadOnly"
                class="text-end"
              >
                {{ t('Actions') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="loc in filteredLocations"
              :key="loc.id"
            >
              <td>
                <span class="font-weight-medium font-monospace">{{ loc.zoneCode }}</span>
              </td>
              <td>
                <span class="font-weight-semibold text-primary font-monospace">{{ loc.full_path || '—' }}</span>
              </td>
              <td class="font-monospace text-caption">
                {{ loc.barcode || '—' }}
              </td>
              <td>
                <VChip
                  size="x-small"
                  variant="tonal"
                >
                  {{ loc.location_type }}
                </VChip>
              </td>
              <td class="text-caption">
                {{ loc.max_weight_kg ? `${loc.max_weight_kg} kg` : '—' }}
              </td>
              <td class="text-caption">
                {{ loc.max_volume_m3 ? `${loc.max_volume_m3} m³` : '—' }}
              </td>
              <td class="text-center">
                <VSwitch
                  :model-value="Boolean(loc.is_active)"
                  :disabled="isReadOnly"
                  color="success"
                  density="compact"
                  hide-details
                  class="d-inline-flex"
                  @update:model-value="handleToggleActive(loc)"
                />
              </td>
              <td
                v-if="!isReadOnly"
                class="text-end"
              >
                <div class="d-inline-flex gap-1">
                  <VBtn
                    icon="tabler-edit"
                    size="x-small"
                    variant="text"
                    color="primary"
                    :aria-label="t('Edit')"
                    @click="openEditLocation(loc)"
                  />
                  <VBtn
                    icon="tabler-trash"
                    size="x-small"
                    variant="text"
                    color="error"
                    :aria-label="t('Delete')"
                    @click="openDeleteLocation(loc)"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </VTable>
      </template>

      <!-- Empty State -->
      <template v-else>
        <WpEmptyState
          icon="tabler-map-pin"
          :title="t('inventory.sites.no_locations', 'No Bin Locations Found')"
          :description="siteZones.length === 0 ? t('Please create a warehouse zone first before adding bin locations.') : t('Create your first coordinate bin location in this warehouse.')"
          size="small"
        />
      </template>
    </WpSectionCard>

    <!-- Add/Edit Location Dialog -->
    <AddEditLocationDialog
      v-model="isAddEditOpen"
      :location="selectedLocation"
      :zones="siteZones"
      :loading="actions.saving.value"
      @submit="handleSaveLocation"
    />

    <!-- Delete Location Dialog -->
    <WpDeleteDialog
      :is-open="isDeleteOpen"
      :title="t('Delete Location')"
      :description="t('inventory.sites.delete_location_confirm', { name: locationToDelete?.full_path || locationToDelete?.barcode || 'location' })"
      :loading="actions.isDeleting.value"
      :confirm-action="handleDeleteConfirm"
      @update:is-open="v => { if (!v) isDeleteOpen = false }"
    />
  </section>
</template>
