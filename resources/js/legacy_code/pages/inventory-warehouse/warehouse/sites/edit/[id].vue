<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { useInventoryStore } from '@/stores/useInventoryStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { $api } from '@/utils/api'
import SiteZoneCards from '@/views/inventory-warehouse/warehouse/sites/edit/SiteZoneCards.vue'
import ZoneLocationsModal from '@/views/inventory-warehouse/warehouse/sites/edit/ZoneLocationsModal.vue'
import AddEditSiteDialog from '@/views/inventory-warehouse/warehouse/sites/list/AddEditSiteDialog.vue'
import AddEditZoneDialog from '@/views/inventory-warehouse/warehouse/zones/list/AddEditZoneDialog.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'

definePage({
  path: '/inventory-warehouse/warehouse/sites/edit/:id',
  name: 'inventory-warehouse-sites-edit-id',
  meta: {
    layout: 'default',
    module: 'inventory-warehouse',
    action: 'read',
    subject: 'InventoryWarehouse',
  },
})

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const inventoryStore = useInventoryStore()
const snackbarStore = useSnackbarStore()

// ── State ───────────────────────────────────────────────────────────────
const loading = ref(true)
const site = ref(null)
const originalData = ref(null)
const siteZones = ref([])
const zonesLoading = ref(false)
const allSites = ref([])

const statusColorMap = { active: 'success', inactive: 'secondary', maintenance: 'warning' }

// ── Site info fields (for display) ──────────────────────────────────────
const siteInfoFields = computed(() => {
  if (!site.value) return []
  const s = site.value
  
  return [
    { label: t('Code'), value: s.code, icon: 'tabler-hash', mono: true },
    { label: t('Name (EN)'), value: s.name?.en || '—', icon: 'tabler-language' },
    { label: t('Name (FR)'), value: s.name?.fr || '—', icon: 'tabler-language' },
    { label: t('Name (AR)'), value: s.name?.ar || '—', icon: 'tabler-language' },
    { label: t('City'), value: s.city || '—', icon: 'tabler-map-pin' },
    { label: t('Country'), value: s.country || '—', icon: 'tabler-world' },
    { label: t('Phone'), value: s.phone || '—', icon: 'tabler-phone' },
    { label: t('Email'), value: s.email || '—', icon: 'tabler-mail' },
    { label: t('Manager'), value: s.manager?.name || t('Unassigned'), icon: 'tabler-user' },
    { label: t('Status'), value: s.status, icon: 'tabler-activity', isStatus: true },
  ]
})

// Sites list for filter dropdowns (in dialogs)
const sitesForFilter = computed(() =>
  allSites.value.map(s => ({ title: `${s.code} — ${localizedValue(s.name)}`, value: s.id })),
)

// ── Fetch site ──────────────────────────────────────────────────────────
const fetchSite = async () => {
  loading.value = true
  try {
    const res = await $api(`/inventory/sites/${route.params.id}`, { method: 'GET' })

    site.value = res.data || res
    originalData.value = JSON.parse(JSON.stringify(site.value))
  } catch (err) {
    console.error('Failed to load site:', err)
    snackbarStore.showMessage(t('Failed to load site'), 'error')
    router.push({ name: 'inventory-warehouse-sites-list' })
  } finally {
    loading.value = false
  }
}

// ── Fetch zones for this site ───────────────────────────────────────────
const fetchZones = async () => {
  zonesLoading.value = true
  try {
    const res = await $api('/inventory/zones', {
      method: 'GET',
      params: { 'filter[site_id]': route.params.id, itemsPerPage: -1 },
    })

    siteZones.value = res.data || []
  } catch (e) {
    console.error('Error fetching zones:', e)
  } finally {
    zonesLoading.value = false
  }
}

const fetchAllSites = async () => {
  try {
    await inventoryStore.fetchSites({ itemsPerPage: -1 })
    allSites.value = inventoryStore.sites
  } catch {}
}

// ── Site edit (via dialog) ──────────────────────────────────────────────
const isSiteDialogVisible = ref(false)
const isSiteSubmitting = ref(false)

const openEditSite = () => {
  isSiteDialogVisible.value = true
}

const handleSaveSite = async data => {
  isSiteSubmitting.value = true
  try {
    await inventoryStore.updateSite(route.params.id, data)
    snackbarStore.showMessage(t('Site updated successfully'), 'success')
    isSiteDialogVisible.value = false
    await fetchSite()
    originalData.value = JSON.parse(JSON.stringify(site.value))
  } catch (e) {
    snackbarStore.showMessage(e?.response?.data?.message || t('Failed to update site'), 'error')
  } finally {
    isSiteSubmitting.value = false
  }
}

// ── Zone CRUD ───────────────────────────────────────────────────────────
const isZoneDialogVisible = ref(false)
const editingZone = ref(null)
const isZoneSubmitting = ref(false)
const zoneDeleteTarget = ref(null)
const isZoneDeleteVisible = ref(false)

const openCreateZone = () => { editingZone.value = null; isZoneDialogVisible.value = true }
const openEditZone = zone => { editingZone.value = zone; isZoneDialogVisible.value = true }
const confirmDeleteZone = zone => { zoneDeleteTarget.value = zone; isZoneDeleteVisible.value = true }

const handleSaveZone = async data => {
  isZoneSubmitting.value = true
  try {
    if (editingZone.value) {
      await inventoryStore.updateZone(editingZone.value.id, data)
      snackbarStore.showMessage(t('Zone updated successfully'), 'success')
    } else {
      await inventoryStore.createZone(data)
      snackbarStore.showMessage(t('Zone created successfully'), 'success')
    }
    isZoneDialogVisible.value = false
    fetchZones()
    fetchSite()
  } catch (e) {
    snackbarStore.showMessage(e?.response?.data?.message || t('Failed to save zone'), 'error')
  } finally {
    isZoneSubmitting.value = false
  }
}

const handleDeleteZone = async () => {
  try {
    await inventoryStore.deleteZone(zoneDeleteTarget.value.id)
    snackbarStore.showMessage(t('Zone deleted successfully'), 'success')
    isZoneDeleteVisible.value = false
    fetchZones()
    fetchSite()
  } catch (e) {
    snackbarStore.showMessage(e?.response?.data?.message || t('Cannot delete zone'), 'error')
  }
}

// ── Zone Locations Modal ────────────────────────────────────────────────
const isLocationsModalVisible = ref(false)
const selectedZoneForLocations = ref(null)

const openLocationsModal = zone => {
  selectedZoneForLocations.value = zone
  isLocationsModalVisible.value = true
}

const handleLocationsRefresh = () => {
  fetchZones()
  fetchSite()
}

onBeforeRouteLeave((to, from, next) => {
  const hasChanges = JSON.stringify(site.value) !== JSON.stringify(originalData.value)
  if (hasChanges) {
    const confirmed = window.confirm('You have unsaved changes. Leave anyway?')

    confirmed ? next() : next(false)
  } else {
    next()
  }
})

// ── Lifecycle ───────────────────────────────────────────────────────────
onMounted(async () => {
  await Promise.allSettled([fetchSite(), fetchZones(), fetchAllSites()])
})
</script>

<template>
  <div>
    <!-- 👉 Breadcrumbs -->
    <VBreadcrumbs class="px-0 mb-4">
      <VBreadcrumbsItem
        to="/dashboard"
        :title="t('Home')"
      />
      <VBreadcrumbsDivider><VIcon icon="tabler-chevron-right" /></VBreadcrumbsDivider>
      <VBreadcrumbsItem
        :to="{ name: 'inventory-warehouse-sites-list' }"
        :title="t('Warehouse')"
      />
      <VBreadcrumbsDivider><VIcon icon="tabler-chevron-right" /></VBreadcrumbsDivider>
      <VBreadcrumbsItem
        :title="site?.code || t('Loading...')"
        disabled
      />
    </VBreadcrumbs>

    <!-- 👉 Skeleton Loading -->
    <VSkeletonLoader
      v-if="loading"
      type="card-avatar, article, list-item-two-line"
      class="mb-6 w-100 bg-transparent"
    />

    <template v-else-if="site">
      <!-- ═══ Site Header ═══ -->
      <VCard class="mb-6">
        <VCardText class="d-flex flex-wrap align-center justify-space-between gap-4 pa-6">
          <div class="d-flex align-center gap-4">
            <VAvatar
              color="primary"
              variant="tonal"
              rounded
              size="56"
            >
              <VIcon
                icon="tabler-building-warehouse"
                size="32"
              />
            </VAvatar>
            <div>
              <div class="d-flex align-center gap-3 mb-1">
                <h3 class="text-h5 font-weight-bold mb-0">
                  {{ site.code }}
                </h3>
                <VChip
                  :color="statusColorMap[site.status] || 'secondary'"
                  size="small"
                  label
                  class="text-capitalize"
                >
                  {{ t(site.status) }}
                </VChip>
              </div>
              <p class="text-sm text-medium-emphasis mb-0">
                {{ localizedValue(site.name) }}
              </p>
              <p
                v-if="site.city || site.country"
                class="text-xs text-disabled mb-0 d-flex align-center gap-1 mt-1"
              >
                <VIcon
                  icon="tabler-map-pin"
                  size="12"
                /> {{ [site.city, site.country].filter(Boolean).join(', ') }}
              </p>
            </div>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <VBtn
              color="info"
              variant="tonal"
              size="small"
              prepend-icon="tabler-packages"
              :to="{ name: 'inventory-stock-items-list', query: { site_id: site.id } }"
            >
              {{ t('Stock Items') }}
            </VBtn>
            <VBtn
              color="primary"
              prepend-icon="tabler-pencil"
              size="small"
              @click="openEditSite"
            >
              {{ t('Edit Site') }}
            </VBtn>
            <VBtn
              variant="tonal"
              color="secondary"
              size="small"
              :to="{ name: 'inventory-warehouse-sites-list' }"
            >
              <VIcon
                start
                icon="tabler-arrow-left"
              /> {{ t('Back to List') }}
            </VBtn>
          </div>
        </VCardText>
      </VCard>

      <!-- ═══ Site Info ═══ -->
      <VCard class="mb-6">
        <VCardItem>
          <template #prepend>
            <VAvatar
              color="primary"
              variant="tonal"
              rounded
              size="36"
            >
              <VIcon
                icon="tabler-info-circle"
                size="20"
              />
            </VAvatar>
          </template>
          <VCardTitle class="text-subtitle-1 font-weight-bold">
            {{ t('Site Details') }}
          </VCardTitle>
        </VCardItem>
        <VDivider />
        <VCardText>
          <VRow dense>
            <VCol
              v-for="(f, idx) in siteInfoFields"
              :key="idx"
              cols="12"
              sm="6"
              md="4"
            >
              <div
                class="d-flex align-center gap-3 pa-3 rounded-lg"
                style="background: rgba(var(--v-theme-on-surface), 0.03);"
              >
                <VAvatar
                  size="30"
                  variant="tonal"
                  color="primary"
                  rounded
                >
                  <VIcon
                    :icon="f.icon"
                    size="14"
                  />
                </VAvatar>
                <div>
                  <span class="text-xs text-medium-emphasis d-block">{{ f.label }}</span>
                  <VChip
                    v-if="f.isStatus"
                    :color="statusColorMap[f.value] || 'secondary'"
                    size="x-small"
                    label
                    class="text-capitalize mt-1"
                  >
                    {{ t(f.value) }}
                  </VChip>
                  <span
                    v-else
                    class="text-sm font-weight-medium"
                    :style="f.mono ? 'font-family: monospace' : ''"
                  >{{ f.value }}</span>
                </div>
              </div>
            </VCol>
          </VRow>
          <template v-if="site.notes">
            <VDivider class="my-4" />
            <div
              class="pa-3 rounded-lg"
              style="background: rgba(var(--v-theme-on-surface), 0.03);"
            >
              <span class="text-xs text-medium-emphasis d-block mb-1">{{ t('Notes') }}</span>
              <span class="text-sm">{{ site.notes }}</span>
            </div>
          </template>
        </VCardText>
      </VCard>

      <!-- ═══ Quick Stats ═══ -->
      <VRow
        class="mb-6"
        dense
      >
        <VCol
          cols="12"
          sm="4"
        >
          <VCard>
            <VCardText class="d-flex align-center gap-3">
              <VAvatar
                color="info"
                variant="tonal"
                rounded
                size="44"
              >
                <VIcon
                  icon="tabler-layers-intersect"
                  size="24"
                />
              </VAvatar>
              <div>
                <h4 class="text-h4 font-weight-bold">
                  {{ siteZones.length }}
                </h4>
                <span class="text-sm text-medium-emphasis">{{ t('Zones') }}</span>
              </div>
            </VCardText>
          </VCard>
        </VCol>
        <VCol
          cols="12"
          sm="4"
        >
          <VCard>
            <VCardText class="d-flex align-center gap-3">
              <VAvatar
                color="secondary"
                variant="tonal"
                rounded
                size="44"
              >
                <VIcon
                  icon="tabler-map-pin"
                  size="24"
                />
              </VAvatar>
              <div>
                <h4 class="text-h4 font-weight-bold">
                  {{ site.locations_count ?? 0 }}
                </h4>
                <span class="text-sm text-medium-emphasis">{{ t('Locations') }}</span>
              </div>
            </VCardText>
          </VCard>
        </VCol>
        <VCol
          cols="12"
          sm="4"
        >
          <VCard>
            <VCardText class="d-flex align-center gap-3">
              <VAvatar
                color="success"
                variant="tonal"
                rounded
                size="44"
              >
                <VIcon
                  icon="tabler-packages"
                  size="24"
                />
              </VAvatar>
              <div>
                <h4 class="text-h4 font-weight-bold">
                  {{ site.stock_items_count ?? 0 }}
                </h4>
                <span class="text-sm text-medium-emphasis">{{ t('Stock Items') }}</span>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- ═══ Zones Section ═══ -->
      <SiteZoneCards
        :zones="siteZones"
        :loading="zonesLoading"
        @create="openCreateZone"
        @edit="openEditZone"
        @delete="confirmDeleteZone"
        @view-locations="openLocationsModal"
      />
    </template>

    <!-- ═══ DIALOGS ═══ -->

    <!-- Edit Site Dialog -->
    <AddEditSiteDialog
      v-model:is-dialog-visible="isSiteDialogVisible"
      :editing-item="site"
      :is-submitting="isSiteSubmitting"
      @submit="handleSaveSite"
    />

    <!-- Zone Add/Edit Dialog -->
    <AddEditZoneDialog
      v-model:is-dialog-visible="isZoneDialogVisible"
      :editing-item="editingZone"
      :sites="allSites"
      :is-submitting="isZoneSubmitting"
      @submit="handleSaveZone"
    />

    <!-- Zone Delete Dialog -->
    <VDialog
      v-model="isZoneDeleteVisible"
      max-width="400"
    >
      <VCard class="pa-4">
        <VCardTitle class="text-h6 font-weight-bold">
          {{ t('Delete Zone') }}
        </VCardTitle>
        <VCardText>{{ t('Are you sure you want to delete zone') }} <strong>{{ zoneDeleteTarget?.code }}</strong>?</VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isZoneDeleteVisible = false"
          >
            {{ t('Cancel') }}
          </VBtn>
          <VBtn
            color="error"
            variant="flat"
            @click="handleDeleteZone"
          >
            {{ t('Delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Zone Locations Modal -->
    <ZoneLocationsModal
      v-model="isLocationsModalVisible"
      :zone="selectedZoneForLocations"
      :sites-for-filter="sitesForFilter"
      @refresh="handleLocationsRefresh"
    />
  </div>
</template>
