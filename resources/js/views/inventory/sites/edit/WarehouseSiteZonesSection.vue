<script setup>
/* eslint-disable camelcase */
import {
  WpDeleteDialog,
  WpEmptyState,
  WpSectionCard,
  WpStatusChip,
} from '@/components/shared'
import { useWarehouseSiteActions } from '@/composables/inventory/sites/useWarehouseSiteActions'
import { localizedValue } from '@/composables/useLocaleField'
import AddEditZoneDialog from '@/views/inventory/sites/edit/AddEditZoneDialog.vue'
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
const selectedZone = ref(null)
const isDeleteOpen = ref(false)
const zoneToDelete = ref(null)

const zonesList = computed(() => props.site?.zones || [])

const openCreateZone = () => {
  selectedZone.value = null
  isAddEditOpen.value = true
}

const openEditZone = zone => {
  selectedZone.value = zone
  isAddEditOpen.value = true
}

const openDeleteZone = zone => {
  zoneToDelete.value = zone
  isDeleteOpen.value = true
}

const handleSaveZone = async payload => {
  if (selectedZone.value?.id) {
    const ok = await actions.updateZone(selectedZone.value.id, payload)
    if (ok) isAddEditOpen.value = false
  } else {
    const ok = await actions.createZone(payload)
    if (ok) isAddEditOpen.value = false
  }
}

const handleDeleteConfirm = async () => {
  if (!zoneToDelete.value?.id) return

  const ok = await actions.deleteZone(zoneToDelete.value.id)

  if (ok) {
    isDeleteOpen.value = false
    zoneToDelete.value = null
  }
}

const resolveStatusVariant = status => ({
  active: 'success',
  inactive: 'secondary',
  maintenance: 'warning',
}[status?.toLowerCase()] || 'primary')

const resolveZoneTypeVariant = type => ({
  receiving: 'info',
  storage: 'primary',
  picking: 'warning',
  packing: 'secondary',
  shipping: 'success',
  staging: 'info',
  quarantine: 'error',
  returns: 'warning',
  cold_storage: 'info',
}[type?.toLowerCase()] || 'secondary')
</script>

<template>
  <section
    id="section-zones"
    class="journal-section mb-6"
  >
    <WpSectionCard
      :title="t('inventory.zones.title', 'Warehouse Zones')"
      :subtitle="t('inventory.zones.subtitle', 'Functional areas defined within this facility for storage, dispatch, or staging')"
      icon="tabler-layers"
      icon-color="info"
    >
      <template #actions>
        <VBtn
          v-if="!isReadOnly"
          size="small"
          prepend-icon="tabler-plus"
          color="primary"
          @click="openCreateZone"
        >
          {{ t('inventory.zones.add', 'New Zone') }}
        </VBtn>
      </template>

      <!-- Zones List Table -->
      <template v-if="zonesList.length > 0">
        <VTable density="compact">
          <thead>
            <tr>
              <th>{{ t('inventory.zones.columns.code', 'Code') }}</th>
              <th>{{ t('inventory.zones.columns.name', 'Name') }}</th>
              <th>{{ t('inventory.zones.columns.type', 'Type') }}</th>
              <th>{{ t('inventory.zones.columns.status', 'Status') }}</th>
              <th class="text-center">
                {{ t('inventory.zones.columns.locations', 'Locations') }}
              </th>
              <th>{{ t('Notes') }}</th>
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
              v-for="zone in zonesList"
              :key="zone.id"
            >
              <td class="font-monospace font-weight-medium">
                {{ zone.code }}
              </td>
              <td>
                <span class="font-weight-medium">{{ localizedValue(zone.name) || zone.code }}</span>
              </td>
              <td>
                <VChip
                  size="x-small"
                  :color="resolveZoneTypeVariant(zone.zone_type)"
                  variant="tonal"
                >
                  {{ zone.zone_type }}
                </VChip>
              </td>
              <td>
                <WpStatusChip
                  :status="zone.status"
                  :color="resolveStatusVariant(zone.status)"
                  :label="t(`inventory.sites.statuses.${zone.status || 'active'}`)"
                  size="x-small"
                />
              </td>
              <td class="text-center">
                <VChip
                  size="x-small"
                  color="info"
                  variant="tonal"
                >
                  {{ zone.locations?.length ?? zone.locations_count ?? 0 }}
                </VChip>
              </td>
              <td class="text-caption text-medium-emphasis">
                {{ zone.notes || '—' }}
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
                    @click="openEditZone(zone)"
                  />
                  <VBtn
                    icon="tabler-trash"
                    size="x-small"
                    variant="text"
                    color="error"
                    :aria-label="t('Delete')"
                    @click="openDeleteZone(zone)"
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
          icon="tabler-layers"
          :title="t('inventory.sites.no_zones', 'No Zones Configured')"
          :description="t('inventory.sites.no_zones_sub', 'Add functional zones (receiving, picking, cold storage) to divide this site into organized sections.')"
          size="small"
        />
      </template>
    </WpSectionCard>

    <!-- Add/Edit Zone Dialog -->
    <AddEditZoneDialog
      v-model="isAddEditOpen"
      :zone="selectedZone"
      :site-id="site.id"
      :loading="actions.saving.value"
      @submit="handleSaveZone"
    />

    <!-- Delete Zone Dialog -->
    <WpDeleteDialog
      :is-open="isDeleteOpen"
      :title="t('Delete Zone')"
      :description="t('inventory.sites.delete_zone_confirm', { name: localizedValue(zoneToDelete?.name) || zoneToDelete?.code })"
      :loading="actions.isDeleting.value"
      :confirm-action="handleDeleteConfirm"
      @update:is-open="v => { if (!v) isDeleteOpen = false }"
    />
  </section>
</template>
