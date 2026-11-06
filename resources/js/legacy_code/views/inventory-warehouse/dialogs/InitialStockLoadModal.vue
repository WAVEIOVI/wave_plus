<script setup>
import { useInventoryStore } from '@/stores/useInventoryStore'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'loaded'])

const inventoryStore = useInventoryStore()

// State
const isSubmitting = ref(false)
const lines = ref([])
const snackbar = ref({ show: false, text: '', color: 'success' })

// Form validation
const isValid = computed(() => {
  if (lines.value.length === 0) return false

  return lines.value.every(line => 
    line.catalogItem && 
    line.catalogVariant &&
    line.locationId && 
    line.quantity > 0 && 
    line.unitCost >= 0,
  )
})

// Lifecycle/Watchers
watch(() => props.modelValue, val => {
  if (val && lines.value.length === 0) {
    addLine()
  }
})

// Methods
function addLine() {
  lines.value.push({
    id: Date.now() + Math.random(),
    catalogItem: null,
    catalogVariant: null,
    locationId: null,
    quantity: null,
    unitCost: null,
    currency: 'TND',
    condition: 'new',
  })
}

function removeLine(id) {
  lines.value = lines.value.filter(l => l.id !== id)
  if (lines.value.length === 0) addLine()
}

function close() {
  emit('update:modelValue', false)

  // Reset after transition
  setTimeout(() => {
    lines.value = []
    isSubmitting.value = false
  }, 300)
}

async function submit() {
  if (!isValid.value) return

  isSubmitting.value = true
  try {
    const payload = {
      items: lines.value.map(l => ({
        catalog_item_id: l.catalogItem?.id || l.catalogItem,
        catalog_variant_id: l.catalogVariant?.id || l.catalogVariant,
        location_id: l.locationId,
        quantity: parseFloat(l.quantity),
        unit_cost: parseFloat(l.unitCost),
        currency: l.currency,
        condition: l.condition,
      })),
    }

    await inventoryStore.createInitialLoad(payload)
    emit('loaded')
    close()
  } catch (err) {
    snackbar.value = {
      show: true,
      text: err?.response?.data?.message || 'Failed to process initial load',
      color: 'error',
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    persistent
    max-width="1200"
    scrollable
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <VCard class="initial-load-modal">
      <VCardTitle class="d-flex align-center justify-space-between px-6 py-4">
        <div class="d-flex align-center gap-2">
          <VAvatar
            color="secondary"
            variant="tonal"
            rounded
            size="40"
          >
            <VIcon
              icon="tabler-database-import"
              size="24"
            />
          </VAvatar>
          <div>
            <h3 class="text-h5 font-weight-bold mb-0">
              Initial Stock Load
            </h3>
            <p class="text-caption text-medium-emphasis mb-0">
              Enter opening balances for stock items
            </p>
          </div>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          size="small"
          @click="close"
        />
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-0 bg-var-theme-background">
        <VTable
          class="text-no-wrap initial-load-table"
          density="comfortable"
        >
          <thead>
            <tr>
              <th
                class="text-uppercase text-caption font-weight-bold"
                style="inline-size: 30%;"
              >
                Item & Variant *
              </th>
              <th
                class="text-uppercase text-caption font-weight-bold"
                style="inline-size: 25%;"
              >
                Location *
              </th>
              <th
                class="text-uppercase text-caption font-weight-bold"
                style="inline-size: 15%;"
              >
                Quantity *
              </th>
              <th
                class="text-uppercase text-caption font-weight-bold"
                style="inline-size: 15%;"
              >
                Unit Cost *
              </th>
              <th
                class="text-uppercase text-caption font-weight-bold"
                style="inline-size: 10%;"
              >
                Condition
              </th>
              <th
                class="text-uppercase text-caption font-weight-bold text-center"
                style="inline-size: 5%;"
              />
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(line, index) in lines"
              :key="line.id"
            >
              <td class="px-4 py-2">
                <div class="d-flex flex-column gap-2 py-2">
                  <AppAutocomplete
                    v-model="line.catalogItem"
                    api-endpoint="/catalog/items"
                    item-title="name"
                    item-value="id"
                    placeholder="Select item..."
                    density="compact"
                    variant="outlined"
                    hide-details
                    @update:model-value="line.catalogVariant = null"
                  />
                  <AppAutocomplete
                    v-if="line.catalogItem"
                    v-model="line.catalogVariant"
                    :api-endpoint="`/catalog/items/${line.catalogItem}/variants`"
                    item-title="sku"
                    item-value="id"
                    placeholder="Select variant..."
                    density="compact"
                    variant="outlined"
                    hide-details
                  />
                </div>
              </td>
              <td class="px-4 py-2">
                <AppAutocomplete
                  v-model="line.locationId"
                  api-endpoint="/inventory/locations"
                  item-title="name"
                  item-value="id"
                  placeholder="Select location..."
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </td>
              <td class="px-4 py-2">
                <VTextField
                  v-model.number="line.quantity"
                  type="number"
                  min="0"
                  step="0.01"
                  placeholder="0.00"
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </td>
              <td class="px-4 py-2">
                <div class="d-flex gap-2">
                  <VTextField
                    v-model.number="line.unitCost"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                    density="compact"
                    variant="outlined"
                    hide-details
                  />
                  <VSelect
                    v-model="line.currency"
                    :items="['EUR', 'USD', 'GBP']"
                    density="compact"
                    variant="outlined"
                    hide-details
                    style=" flex: none;inline-size: 90px;"
                  />
                </div>
              </td>
              <td class="px-4 py-2">
                <VSelect
                  v-model="line.condition"
                  :items="[
                    { title: 'New', value: 'new' },
                    { title: 'Used', value: 'used' },
                    { title: 'Refurbished', value: 'refurbished' },
                    { title: 'Damaged', value: 'damaged' }
                  ]"
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </td>
              <td class="px-4 py-2 text-center">
                <VBtn
                  icon="tabler-trash"
                  variant="text"
                  color="error"
                  size="small"
                  @click="removeLine(line.id)"
                />
              </td>
            </tr>
          </tbody>
        </VTable>

        <div class="pa-4 text-center border-t">
          <VBtn
            variant="tonal"
            color="primary"
            prepend-icon="tabler-plus"
            @click="addLine"
          >
            Add Line
          </VBtn>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="px-6 py-4 justify-end gap-3">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="isSubmitting"
          @click="close"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          :loading="isSubmitting"
          :disabled="!isValid"
          prepend-icon="tabler-check"
          @click="submit"
        >
          Submit Load
        </VBtn>
      </VCardActions>
    </VCard>

    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      timeout="3000"
      location="top right"
    >
      {{ snackbar.text }}
    </VSnackbar>
  </VDialog>
</template>

<style lang="scss" scoped>
.initial-load-modal {
  overflow: hidden;
}

.initial-load-table {
  background: transparent !important;

  th {
    position: sticky;
    z-index: 10;
    background: rgba(var(--v-theme-surface), 1) !important;
    inset-block-start: 0;
  }
}
</style>
