<script setup>
/* eslint-disable camelcase */
import {
  WpConfirmDialog,
  WpEmptyState,
  WpInfoItem,
  WpSectionCard,
} from '@/components/shared'
import { useWarehouseSiteActions } from '@/composables/inventory/sites/useWarehouseSiteActions'
import LinkVehicleDialog from '@/views/inventory/sites/edit/LinkVehicleDialog.vue'
import { onMounted, ref, watch } from 'vue'
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
    await fetchHistory()
  },
})

// Dialog state
const isLinkDialogOpen = ref(false)
const isUnlinkConfirmOpen = ref(false)

// History state
const historyLoading = ref(false)
const assignmentHistory = ref([])

const fetchHistory = async () => {
  if (!props.site?.id) return

  historyLoading.value = true
  try {
    const res = await actions.fetchSiteAssignmentHistory(props.site.id)

    assignmentHistory.value = res?.data || []
  } catch (err) {
    console.error('Failed to load site assignment history:', err)
  } finally {
    historyLoading.value = false
  }
}

watch(
  () => props.site?.id,
  id => {
    if (id) fetchHistory()
  },
)

onMounted(fetchHistory)

const handleLinkSubmit = async payload => {
  const result = await actions.linkVehicle(props.site.id, payload)
  if (result) {
    isLinkDialogOpen.value = false
  }
}

const handleUnlinkConfirm = async () => {
  const result = await actions.unlinkVehicle(props.site.id)
  if (result) {
    isUnlinkConfirmOpen.value = false
  }
}

const formatDate = val => {
  if (!val) return '—'

  return new Date(val).toLocaleDateString()
}
</script>

<template>
  <section
    id="section-vehicle"
    class="journal-section mb-6"
  >
    <WpSectionCard
      :title="t('inventory.sites.mobile.section_title', 'Mobile Warehouse Vehicle Carrier')"
      :subtitle="t('inventory.sites.mobile.section_sub', 'Fleet vehicle linked to this mobile site with live dispatch status and assignment history')"
      icon="tabler-truck"
      icon-color="warning"
    >
      <template #actions>
        <template v-if="!isReadOnly">
          <VBtn
            v-if="!site.vehicle"
            size="small"
            prepend-icon="tabler-link"
            color="primary"
            @click="isLinkDialogOpen = true"
          >
            {{ t('inventory.sites.mobile.link_vehicle', 'Link Vehicle') }}
          </VBtn>
          <VBtn
            v-else
            size="small"
            variant="tonal"
            prepend-icon="tabler-unlink"
            color="error"
            @click="isUnlinkConfirmOpen = true"
          >
            {{ t('inventory.sites.mobile.unlink_vehicle', 'Unlink Vehicle') }}
          </VBtn>
        </template>
      </template>

      <!-- Current Linked Vehicle Display -->
      <div
        v-if="site.vehicle"
        class="mb-6 pb-4 border-b"
      >
        <p class="text-subtitle-2 mb-3 text-medium-emphasis">
          {{ t('inventory.sites.mobile.current_assignment', 'Active Assigned Vehicle') }}
        </p>

        <VRow dense>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <WpInfoItem
              icon="tabler-car"
              :label="t('inventory.sites.columns.vehicle', 'Plate Number')"
              :value="site.vehicle.plate_number || site.vehicle.registration_number || site.vehicle.id"
              mono
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <WpInfoItem
              icon="tabler-id"
              :label="t('Label / Call Sign')"
              :value="site.vehicle.label || '—'"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <WpInfoItem
              icon="tabler-truck"
              :label="t('Type')"
              :value="site.vehicle.type || '—'"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <WpInfoItem
              icon="tabler-activity"
              :label="t('inventory.sites.columns.mobile_status', 'Mobile Status')"
              :value="site.mobile_status || '—'"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-map-pin"
              :label="t('inventory.sites.columns.current_location', 'Live Location')"
              :value="site.current_location || '—'"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-calendar-time"
              :label="t('inventory.sites.mobile.assigned_from', 'Assigned Since')"
              :value="formatDate(site.current_assignment?.assigned_from || site.activated_at)"
            />
          </VCol>

          <VCol
            v-if="site.location_notes"
            cols="12"
          >
            <WpInfoItem
              icon="tabler-notes"
              :label="t('inventory.sites.columns.location_notes', 'Location Notes')"
              :value="site.location_notes"
            />
          </VCol>
        </VRow>
      </div>

      <!-- No Vehicle Empty State -->
      <div
        v-else
        class="mb-6 pb-4 border-b"
      >
        <WpEmptyState
          icon="tabler-truck-off"
          :title="t('inventory.sites.mobile.no_vehicle', 'No Vehicle Linked')"
          :description="t('inventory.sites.mobile.no_vehicle_sub', 'This mobile warehouse currently has no assigned fleet carrier. Link a service van or truck to enable mobile inventory tracking.')"
          size="small"
        />
      </div>

      <!-- Assignment History Table -->
      <div>
        <div class="d-flex align-center justify-space-between mb-3">
          <p class="text-subtitle-2 font-weight-medium mb-0">
            {{ t('inventory.sites.mobile.assignment_history', 'Vehicle Assignment History') }}
          </p>

          <VBtn
            icon="tabler-refresh"
            size="x-small"
            variant="text"
            :loading="historyLoading"
            :aria-label="t('Refresh')"
            @click="fetchHistory"
          />
        </div>

        <template v-if="assignmentHistory.length > 0">
          <VTable density="compact">
            <thead>
              <tr>
                <th>{{ t('Vehicle') }}</th>
                <th>{{ t('inventory.sites.mobile.assigned_from', 'From') }}</th>
                <th>{{ t('inventory.sites.mobile.assigned_until', 'Until') }}</th>
                <th class="text-center">
                  {{ t('Current') }}
                </th>
                <th>{{ t('Notes') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in assignmentHistory"
                :key="item.id"
              >
                <td>
                  <span class="font-monospace font-weight-medium">
                    {{ item.vehicle?.plate_number || item.vehicle?.registration_number || item.vehicle_id }}
                  </span>
                  <span
                    v-if="item.vehicle?.label"
                    class="text-caption text-medium-emphasis ms-2"
                  >
                    ({{ item.vehicle.label }})
                  </span>
                </td>
                <td class="text-caption">
                  {{ formatDate(item.assigned_from) }}
                </td>
                <td class="text-caption">
                  {{ formatDate(item.assigned_until) }}
                </td>
                <td class="text-center">
                  <VChip
                    size="x-small"
                    :color="item.is_current ? 'success' : 'secondary'"
                    variant="tonal"
                  >
                    {{ item.is_current ? t('Active') : t('Past') }}
                  </VChip>
                </td>
                <td class="text-caption text-medium-emphasis">
                  {{ item.assignment_notes || item.notes || '—' }}
                </td>
              </tr>
            </tbody>
          </VTable>
        </template>
        <template v-else-if="!historyLoading">
          <p class="text-caption text-medium-emphasis mb-0">
            {{ t('No previous vehicle assignment history recorded for this site.') }}
          </p>
        </template>
      </div>
    </WpSectionCard>

    <!-- Link Vehicle Dialog -->
    <LinkVehicleDialog
      v-model="isLinkDialogOpen"
      :site-id="site.id"
      :loading="actions.isLinking.value"
      @submit="handleLinkSubmit"
    />

    <!-- Unlink Vehicle Confirmation Dialog -->
    <WpConfirmDialog
      v-model="isUnlinkConfirmOpen"
      :title="t('inventory.sites.mobile.unlink_vehicle', 'Unlink Vehicle')"
      :message="t('Are you sure you want to decouple the current vehicle from this mobile warehouse site?')"
      :confirm-label="t('Unlink')"
      confirm-color="error"
      :loading="actions.isLinking.value"
      @confirm="handleUnlinkConfirm"
    />
  </section>
</template>
