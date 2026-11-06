<script setup>
import { useInventoryStore } from '@/stores/useInventoryStore'
import { ref, watch } from 'vue'

const props = defineProps({ modelValue: { type: Boolean, default: false } })
const emit = defineEmits(['update:modelValue', 'success'])
const inventoryStore = useInventoryStore()

const form = ref({ catalog_item_id: null, catalog_variant_id: null, adjustment_type: 'plus', location_id: null, stock_lot_id: null, quantity: null, reason: '', unit_cost: null })
const isSubmitting = ref(false)
const errorMsg = ref('')
const selectedSiteId = ref(null)
const selectedZoneId = ref(null)
const sitesForSelect = ref([])
const zonesForSelect = ref([])
const locationsForSelect = ref([])
const catalogItems = ref([])
const catalogVariants = ref([])
const catalogSearch = ref('')

const loadSites = async () => {
  try {
    await inventoryStore.fetchSites({ itemsPerPage: -1 })
    sitesForSelect.value = inventoryStore.sites.map(s => ({ title: `${s.code} — ${s.name?.en || s.name}`, value: s.id }))
  } catch {}
}

const loadZones = async () => {
  if (!selectedSiteId.value) { zonesForSelect.value = [] 

    return }
  try {
    await inventoryStore.fetchZones({ 'filter[site_id]': selectedSiteId.value, itemsPerPage: -1 })
    zonesForSelect.value = inventoryStore.zones.map(z => ({ title: `${z.code} — ${z.name?.en || z.name}`, value: z.id }))
  } catch {}
}

const loadLocations = async () => {
  if (!selectedZoneId.value) { locationsForSelect.value = [] 

    return }
  try {
    await inventoryStore.fetchLocations({ 'filter[zone_id]': selectedZoneId.value, itemsPerPage: -1 })
    locationsForSelect.value = inventoryStore.locations.map(l => ({ title: `${l.barcode || l.full_path}`, value: l.id }))
  } catch {}
}

watch(() => props.modelValue, val => { if (val) loadSites() })
watch(selectedSiteId, () => { selectedZoneId.value = null; form.value.location_id = null; loadZones() })
watch(selectedZoneId, () => { form.value.location_id = null; loadLocations() })

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
  if (!form.value.catalog_item_id || !form.value.catalog_variant_id || !form.value.location_id || !form.value.quantity || !form.value.reason) { errorMsg.value = 'Please fill all required fields.' 

    return }
  isSubmitting.value = true
  try {
    await inventoryStore.createAdjustment({ catalog_item_id: form.value.catalog_item_id, catalog_variant_id: form.value.catalog_variant_id, location_id: form.value.location_id, stock_lot_id: form.value.stock_lot_id || undefined, quantity: Number(form.value.quantity), adjustment_type: form.value.adjustment_type, reason: form.value.reason, unit_cost: form.value.unit_cost ? Number(form.value.unit_cost) : undefined })
    emit('success')
    closeDialog()
  } catch (error) { errorMsg.value = error?.response?.data?.message || 'Failed to create adjustment.' } finally { isSubmitting.value = false }
}

const closeDialog = () => { emit('update:modelValue', false); resetForm() }
const resetForm = () => { form.value = { catalog_item_id: null, catalog_variant_id: null, adjustment_type: 'plus', location_id: null, stock_lot_id: null, quantity: null, reason: '', unit_cost: null }; selectedSiteId.value = null; selectedZoneId.value = null; errorMsg.value = '' }
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="650"
    persistent
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pa-5">
        <VIcon
          icon="tabler-plus-minus"
          color="primary"
        />
        Stock Adjustment
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
              placeholder="Search by code or name..."
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
          <VCol cols="12">
            <VBtnToggle
              v-model="form.adjustment_type"
              mandatory
              color="primary"
              variant="outlined"
              density="compact"
              class="w-100"
            >
              <VBtn
                value="plus"
                class="flex-1"
              >
                <VIcon
                  icon="tabler-plus"
                  start
                /> Increase (+)
              </VBtn>
              <VBtn
                value="minus"
                class="flex-1"
              >
                <VIcon
                  icon="tabler-minus"
                  start
                /> Decrease (−)
              </VBtn>
            </VBtnToggle>
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppSelect
              v-model="selectedSiteId"
              label="Site *"
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
              v-model="selectedZoneId"
              label="Zone *"
              :items="zonesForSelect"
              variant="outlined"
              clearable
              :disabled="!selectedSiteId"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppSelect
              v-model="form.location_id"
              label="Location *"
              :items="locationsForSelect"
              variant="outlined"
              clearable
              :disabled="!selectedZoneId"
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
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="form.unit_cost"
              label="Unit Cost (optional)"
              type="number"
              min="0"
              step="0.001"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12">
            <VTextarea
              v-model="form.reason"
              label="Reason *"
              variant="outlined"
              rows="3"
              placeholder="Describe the reason..."
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
          color="primary"
          :loading="isSubmitting"
          :disabled="isSubmitting"
          @click="handleSubmit"
        >
          Record Adjustment
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
