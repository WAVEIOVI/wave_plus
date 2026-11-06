<script setup>
import { useInventoryStore } from '@/stores/useInventoryStore'
import { ref, watch } from 'vue'

const props = defineProps({ modelValue: { type: Boolean, default: false } })
const emit = defineEmits(['update:modelValue', 'success'])
const inventoryStore = useInventoryStore()

const form = ref({ catalog_item_id: null, catalog_variant_id: null, from_location_id: null, to_location_id: null, stock_lot_id: null, quantity: null, reason: '' })
const isSubmitting = ref(false)
const errorMsg = ref('')

// Source cascading
const srcSiteId = ref(null)
const srcZoneId = ref(null)

// Dest cascading
const dstSiteId = ref(null)
const dstZoneId = ref(null)

const sitesForSelect = ref([])
const srcZones = ref([])
const srcLocations = ref([])
const dstZones = ref([])
const dstLocations = ref([])
const catalogItems = ref([])
const catalogVariants = ref([])
const catalogSearch = ref('')
const availableQty = ref(null)

const loadSites = async () => {
  try {
    await inventoryStore.fetchSites({ itemsPerPage: -1 })
    sitesForSelect.value = inventoryStore.sites.map(s => ({ title: `${s.code} — ${s.name?.en || s.name}`, value: s.id }))
  } catch {}
}

const loadZones = async (siteId, target) => {
  if (!siteId) { target.value = [] 

    return }
  try {
    const { $api } = await import('@/utils/api')
    const res = await $api('/inventory/zones', { method: 'GET', params: { 'filter[site_id]': siteId, itemsPerPage: -1 } })

    target.value = (res.data || []).map(z => ({ title: `${z.code} — ${z.name?.en || z.name}`, value: z.id }))
  } catch {}
}

const loadLocs = async (zoneId, target) => {
  if (!zoneId) { target.value = [] 

    return }
  try {
    const { $api } = await import('@/utils/api')
    const res = await $api('/inventory/locations', { method: 'GET', params: { 'filter[zone_id]': zoneId, itemsPerPage: -1 } })

    target.value = (res.data || []).map(l => ({ title: `${l.barcode || l.full_path}`, value: l.id }))
  } catch {}
}

watch(() => props.modelValue, val => { if (val) loadSites() })
watch(srcSiteId, () => { srcZoneId.value = null; form.value.from_location_id = null; loadZones(srcSiteId.value, srcZones) })
watch(srcZoneId, () => { form.value.from_location_id = null; loadLocs(srcZoneId.value, srcLocations) })
watch(dstSiteId, () => { dstZoneId.value = null; form.value.to_location_id = null; loadZones(dstSiteId.value, dstZones) })
watch(dstZoneId, () => { form.value.to_location_id = null; loadLocs(dstZoneId.value, dstLocations) })

// Check available qty at source
watch([() => form.value.catalog_variant_id, () => form.value.from_location_id], async () => {
  availableQty.value = null
  if (!form.value.catalog_variant_id || !form.value.from_location_id) return
  try {
    const { $api } = await import('@/utils/api')
    const res = await $api('/inventory/stock-items', { method: 'GET', params: { 'filter[catalog_variant_id]': form.value.catalog_variant_id, 'filter[location_id]': form.value.from_location_id, itemsPerPage: 1 } })
    const item = res.data?.[0]

    availableQty.value = item ? Number(item.qty_available) : 0
  } catch {}
})

const searchCatalog = async val => {
  if (!val || val.length < 2) return
  try {
    const { $api } = await import('@/utils/api')
    const res = await $api('/catalog/items', { method: 'GET', params: { q: val, itemsPerPage: 20 } })

    catalogItems.value = (res.data || []).map(i => ({ title: `${i.code} — ${i.name?.en || i.name}`, value: i.id }))
  } catch {}
}

watch(catalogSearch, val => searchCatalog(val))

watch(() => form.value.catalog_item_id, async val => {
  form.value.catalog_variant_id = null
  catalogVariants.value = []
  if (!val) return
  try {
    const { $api } = await import('@/utils/api')
    const res = await $api(`/catalog/items/${val}/variants`)

    catalogVariants.value = (res.data || []).map(v => ({ title: v.sku + (v.name && v.name !== 'Default' ? ` — ${v.name}` : ''), value: v.id }))
  } catch {}
})

const handleSubmit = async () => {
  errorMsg.value = ''
  if (!form.value.catalog_item_id || !form.value.catalog_variant_id || !form.value.from_location_id || !form.value.to_location_id || !form.value.quantity) { errorMsg.value = 'Please fill all required fields.' 

    return }
  if (form.value.from_location_id === form.value.to_location_id) { errorMsg.value = 'Source and destination must be different.' 

    return }
  if (availableQty.value !== null && Number(form.value.quantity) > availableQty.value) { errorMsg.value = `Quantity exceeds available stock (${availableQty.value}).` 

    return }
  isSubmitting.value = true
  try {
    await inventoryStore.createTransfer({ catalog_item_id: form.value.catalog_item_id, catalog_variant_id: form.value.catalog_variant_id, from_location_id: form.value.from_location_id, to_location_id: form.value.to_location_id, stock_lot_id: form.value.stock_lot_id || undefined, quantity: Number(form.value.quantity), reason: form.value.reason || undefined })
    emit('success')
    closeDialog()
  } catch (error) { errorMsg.value = error?.response?.data?.message || 'Failed to create transfer.' } finally { isSubmitting.value = false }
}

const closeDialog = () => { emit('update:modelValue', false); form.value = { catalog_item_id: null, catalog_variant_id: null, from_location_id: null, to_location_id: null, stock_lot_id: null, quantity: null, reason: '' }; srcSiteId.value = null; srcZoneId.value = null; dstSiteId.value = null; dstZoneId.value = null; errorMsg.value = ''; availableQty.value = null }
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="750"
    persistent
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pa-5">
        <VIcon
          icon="tabler-transfer"
          color="info"
        />
        Stock Transfer
      </VCardTitle>
      <VDivider />
      <VCardText class="pa-5">
        <VAlert
          v-if="errorMsg"
          type="error"
          variant="tonal"
          class="mb-4"
          closable
          @click:close="errorMsg = ''"
        >
          {{ errorMsg }}
        </VAlert>
        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <VAutocomplete
              v-model="form.catalog_item_id"
              v-model:search="catalogSearch"
              label="Catalog Item *"
              :items="catalogItems"
              variant="outlined"
              density="compact"
              no-filter
              placeholder="Search..."
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <VAutocomplete
              v-model="form.catalog_variant_id"
              label="Variant/SKU *"
              :items="catalogVariants"
              variant="outlined"
              density="compact"
              placeholder="Select variant..."
              :disabled="!form.catalog_item_id"
            />
          </VCol>

          <!-- Source -->
          <VCol cols="12">
            <p class="text-subtitle-2 font-weight-bold text-primary mb-0">
              Source Location
            </p>
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppSelect
              v-model="srcSiteId"
              label="Site"
              :items="sitesForSelect"
              variant="outlined"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppSelect
              v-model="srcZoneId"
              label="Zone"
              :items="srcZones"
              variant="outlined"
              clearable
              :disabled="!srcSiteId"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppSelect
              v-model="form.from_location_id"
              label="Location *"
              :items="srcLocations"
              variant="outlined"
              clearable
              :disabled="!srcZoneId"
            />
          </VCol>

          <VCol
            v-if="availableQty !== null"
            cols="12"
          >
            <VChip
              :color="availableQty > 0 ? 'success' : 'error'"
              size="small"
              label
            >
              Available: {{ availableQty }}
            </VChip>
          </VCol>

          <!-- Destination -->
          <VCol cols="12">
            <p class="text-subtitle-2 font-weight-bold text-info mb-0">
              Destination Location
            </p>
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppSelect
              v-model="dstSiteId"
              label="Site"
              :items="sitesForSelect"
              variant="outlined"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppSelect
              v-model="dstZoneId"
              label="Zone"
              :items="dstZones"
              variant="outlined"
              clearable
              :disabled="!dstSiteId"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppSelect
              v-model="form.to_location_id"
              label="Location *"
              :items="dstLocations"
              variant="outlined"
              clearable
              :disabled="!dstZoneId"
            />
          </VCol>

          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="form.quantity"
              label="Quantity *"
              type="number"
              min="0.0001"
              step="0.01"
              variant="outlined"
              :max="availableQty"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <VTextarea
              v-model="form.reason"
              label="Reason (optional)"
              variant="outlined"
              rows="2"
            />
          </VCol>
        </VRow>
      </VCardText>
      <VDivider />
      <VCardActions class="pa-5">
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          @click="closeDialog"
        >
          Cancel
        </VBtn>
        <VBtn
          color="info"
          :loading="isSubmitting"
          :disabled="isSubmitting"
          @click="handleSubmit"
        >
          Transfer Stock
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
