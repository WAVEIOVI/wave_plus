<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { useInventoryStore } from '@/stores/useInventoryStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import AddEditLocationDialog from '@/views/inventory-warehouse/warehouse/locations/list/AddEditLocationDialog.vue'
import { $api } from '@/utils/api'
import { refDebounced } from '@vueuse/core'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  zone: { type: Object, default: null },
  sitesForFilter: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'refresh'])
const { t } = useI18n()
const inventoryStore = useInventoryStore()
const snackbarStore = useSnackbarStore()

const visible = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

// ── Table state ──
const items = ref([])
const totalItems = ref(0)
const loading = ref(false)
const locSearch = ref('')
const debouncedSearch = refDebounced(locSearch, 500)
const selectedType = ref(null)
const itemsPerPage = ref(10)
const page = ref(1)

const headers = [
  { title: t('Barcode'), key: 'barcode', sortable: true },
  { title: t('Path'), key: 'full_path', sortable: false },
  { title: t('Type'), key: 'location_type', sortable: true },
  { title: t('Max Weight'), key: 'max_weight_kg', align: 'end' },
  { title: t('Active'), key: 'is_active', align: 'center' },
  { title: t('Actions'), key: 'actions', align: 'center', sortable: false },
]

const typeOptions = ['shelf', 'bin', 'floor', 'pallet', 'rack', 'cold_storage', 'hazmat'].map(v => ({
  title: v.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()), value: v,
}))

const typeColorMap = { shelf: 'primary', bin: 'info', floor: 'secondary', pallet: 'warning', rack: 'success', cold_storage: 'info', hazmat: 'error' }

const zoneTypeColorMap = {
  storage: 'primary', picking: 'warning', receiving: 'success',
  shipping: 'info', qc: 'secondary', returns: 'error', buffer: 'secondary',
}

const fetchLocations = async () => {
  if (!props.zone?.id) return
  loading.value = true
  try {
    const res = await $api('/inventory/locations', {
      method: 'GET',
      params: {
        'filter[zone_id]': props.zone.id,
        'filter[location_type]': selectedType.value || undefined,
        q: debouncedSearch.value || undefined,
        itemsPerPage: itemsPerPage.value,
        page: page.value,
      },
    })

    items.value = res.data || []
    totalItems.value = res.meta?.total || 0
  } catch (e) {
    console.error('Error fetching locations:', e)
  } finally {
    loading.value = false
  }
}

watch([debouncedSearch, selectedType, itemsPerPage, page], () => fetchLocations())
watch(() => props.modelValue, val => {
  if (val && props.zone?.id) {
    page.value = 1
    locSearch.value = ''
    selectedType.value = null
    fetchLocations()
  }
})

const handleToggleActive = async item => {
  try {
    await inventoryStore.toggleLocationActive(item.id)
    fetchLocations()
  } catch {}
}

const paginationMeta = computed(() => {
  const s = (page.value - 1) * itemsPerPage.value + 1
  const e = Math.min(page.value * itemsPerPage.value, totalItems.value)
  
  return t('Showing {start} to {end} of {total} entries', { start: s, end: e, total: totalItems.value })
})

const totalPages = computed(() => Math.ceil(totalItems.value / itemsPerPage.value))

// ── Location CRUD ──
const isLocDialogVisible = ref(false)
const editingLoc = ref(null)
const isLocSubmitting = ref(false)

const openCreateLoc = () => { editingLoc.value = null; isLocDialogVisible.value = true }
const openEditLoc = item => { editingLoc.value = item; isLocDialogVisible.value = true }

const handleSaveLoc = async data => {
  isLocSubmitting.value = true
  try {
    if (editingLoc.value) {
      await inventoryStore.updateLocation(editingLoc.value.id, data)
      snackbarStore.showMessage(t('Location updated successfully'), 'success')
    } else {
      await inventoryStore.createLocation(data)
      snackbarStore.showMessage(t('Location created successfully'), 'success')
    }
    isLocDialogVisible.value = false
    fetchLocations()
    emit('refresh')
  } catch (e) {
    snackbarStore.showMessage(e?.response?.data?.message || t('Failed to save location'), 'error')
  } finally {
    isLocSubmitting.value = false
  }
}
</script>

<template>
  <VDialog
    v-model="visible"
    max-width="950"
    scrollable
  >
    <VCard v-if="zone">
      <!-- Header -->
      <VCardTitle class="d-flex align-center justify-space-between pa-5">
        <div class="d-flex align-center gap-3">
          <VAvatar
            :color="zoneTypeColorMap[zone.zone_type] || 'secondary'"
            variant="tonal"
            rounded
            size="42"
          >
            <VIcon
              icon="tabler-map-pin"
              size="24"
            />
          </VAvatar>
          <div>
            <div class="d-flex align-center gap-2">
              <h5 class="text-h6 font-weight-bold mb-0">
                {{ localizedValue(zone.name) }}
              </h5>
              <VChip
                :color="zoneTypeColorMap[zone.zone_type] || 'secondary'"
                size="x-small"
                label
                class="text-capitalize"
              >
                {{ zone.zone_type }}
              </VChip>
            </div>
            <span class="text-caption text-disabled">{{ zone.code }} · {{ totalItems }} {{ t('locations') }}</span>
          </div>
        </div>
        <div class="d-flex align-center gap-2">
          <VBtn
            color="secondary"
            size="small"
            prepend-icon="tabler-plus"
            @click="openCreateLoc"
          >
            {{ t('Add Location') }}
          </VBtn>
          <IconBtn @click="visible = false">
            <VIcon icon="tabler-x" />
          </IconBtn>
        </div>
      </VCardTitle>

      <VDivider />

      <!-- Filters -->
      <VCardText class="pa-5 pb-3">
        <VRow dense>
          <VCol
            cols="12"
            sm="5"
          >
            <AppTextField
              v-model="locSearch"
              :placeholder="t('Search barcode...')"
              prepend-inner-icon="tabler-search"
              variant="outlined"
              density="compact"
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            sm="4"
          >
            <AppSelect
              v-model="selectedType"
              :label="t('Type')"
              :items="typeOptions"
              variant="outlined"
              density="compact"
              clearable
              hide-details
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <!-- Data Table -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items="items"
        :items-length="totalItems"
        :headers="headers"
        :loading="loading"
        class="text-no-wrap"
        style="max-block-size: 55vh; overflow-y: auto;"
      >
        <template #item.barcode="{ item }">
          <span
            style="font-family: monospace;"
            class="font-weight-bold text-secondary"
          >{{ item.barcode || '—' }}</span>
        </template>
        <template #item.full_path="{ item }">
          <span class="text-caption text-medium-emphasis">{{ item.full_path || '—' }}</span>
        </template>
        <template #item.location_type="{ item }">
          <VChip
            :color="typeColorMap[item.location_type] || 'secondary'"
            size="x-small"
            label
            class="text-capitalize"
          >
            {{ item.location_type?.replace(/_/g, ' ') }}
          </VChip>
        </template>
        <template #item.max_weight_kg="{ item }">
          {{ item.max_weight_kg ? `${item.max_weight_kg} kg` : '—' }}
        </template>
        <template #item.is_active="{ item }">
          <VSwitch
            :model-value="item.is_active"
            color="success"
            density="compact"
            hide-details
            @update:model-value="handleToggleActive(item)"
          />
        </template>
        <template #item.actions="{ item }">
          <IconBtn
            size="small"
            color="primary"
            @click="openEditLoc(item)"
          >
            <VIcon
              icon="tabler-pencil"
              size="18"
            />
            <VTooltip activator="parent">
              {{ t('Edit') }}
            </VTooltip>
          </IconBtn>
        </template>
        <template #bottom>
          <VDivider />
          <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-4">
            <p class="text-sm text-disabled mb-0">
              {{ paginationMeta }}
            </p>
            <VPagination
              v-model="page"
              :length="totalPages"
              :total-visible="$vuetify.display.xs ? 1 : 5"
              active-color="secondary"
              size="small"
            />
          </div>
        </template>
        <template #no-data>
          <div class="d-flex flex-column align-center py-10 text-center">
            <VIcon
              icon="tabler-map-pin"
              size="48"
              class="mb-3 text-disabled"
            />
            <p class="text-sm text-medium-emphasis mb-3">
              {{ t('No locations in this zone yet') }}
            </p>
            <VBtn
              color="secondary"
              size="small"
              prepend-icon="tabler-plus"
              @click="openCreateLoc"
            >
              {{ t('Create Location') }}
            </VBtn>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Nested Location Dialog -->
    <AddEditLocationDialog
      v-model:is-dialog-visible="isLocDialogVisible"
      :editing-item="editingLoc"
      :sites-for-filter="sitesForFilter"
      :is-submitting="isLocSubmitting"
      @submit="handleSaveLoc"
    />
  </VDialog>
</template>
