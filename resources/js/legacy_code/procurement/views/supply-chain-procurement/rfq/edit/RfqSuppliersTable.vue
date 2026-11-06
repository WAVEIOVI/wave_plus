<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { computed, ref } from 'vue'

const props = defineProps({
  invitations: { type: Array, required: true },
  suppliersList: { type: Array, required: true },
  isReadOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:invitations'])

const showModal = ref(false)
const supplierSearch = ref('')

const filteredSuppliers = computed(() => {
  if (!props.suppliersList) return []
  if (!supplierSearch.value) return props.suppliersList
  const q = supplierSearch.value.toLowerCase()

  return props.suppliersList.filter(s =>
    (s.legal_name || '').toLowerCase().includes(q)
    || (s.supplier_id || '').toLowerCase().includes(q)
    || (localizedValue(s.commercial_name) || '').toLowerCase().includes(q),
  )
})

const isSupplierInvited = supplierId => props.invitations.some(inv => inv.supplierId == supplierId)

const toggleSupplierInvitation = supplierId => {
  if (props.isReadOnly) return
  const newInvs = [...props.invitations]
  const index = newInvs.findIndex(inv => inv.supplierId == supplierId)
  if (index >= 0) {
    newInvs.splice(index, 1)
  }
  else {
    const supplier = props.suppliersList.find(s => s.id == supplierId)

    newInvs.push({
      supplierId: supplierId,
      _supplier: supplier || null,
    })
  }
  emit('update:invitations', newInvs)
}

const invStatusColor = status => {
  const map = {
    sent: 'info',
    viewed: 'primary',
    responded: 'success',
    awarded: 'warning',
    declined: 'error',
    no_response: 'secondary',
  }
  
  return map[status] || 'secondary'
}

const formatDate = dateStr => {
  if (!dateStr) return '—'
  
  return new Date(dateStr).toLocaleString()
}
</script>

<template>
  <VCard class="glass-panel">
    <VCardItem>
      <template #append>
        <VBtn
          color="primary"
          variant="tonal"
          prepend-icon="tabler-user-plus"
          :disabled="isReadOnly"
          @click="showModal = true"
        >
          Invite Supplier(s)
        </VBtn>
      </template>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon
          icon="tabler-building-store"
          size="22"
        /> Invited Suppliers
        <VChip
          size="x-small"
          label
          color="info"
          variant="tonal"
          class="ms-2"
        >
          {{ invitations.length }} selected
        </VChip>
      </VCardTitle>
    </VCardItem>
    <VDivider />
    <VCardText class="pa-0">
      <VTable
        v-if="invitations.length > 0"
        density="comfortable"
        class="rfq-supplier-table"
      >
        <thead>
          <tr>
            <th>Supplier ID</th>
            <th>Legal Name</th>
            <th>Category</th>
            <th>Lead Time</th>
            <th>Supplier Status</th>
            <th>Inv. Status</th>
            <th>Sent At</th>
            <th>Viewed At</th>
            <th class="text-end">
              Actions
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="inv in invitations"
            :key="inv.supplierId"
          >
            <td class="font-weight-bold text-primary">
              {{ inv._supplier?.supplier_id || inv.supplierId }}
            </td>
            <td>{{ inv._supplier?.legal_name }}</td>
            <td>
              <VChip
                v-if="inv._supplier?.supplier_category"
                size="small"
                label
                variant="tonal"
                class="text-capitalize"
              >
                {{ inv._supplier.supplier_category }}
              </VChip>
            </td>
            <td>{{ inv._supplier?.lead_time_days ? `${inv._supplier.lead_time_days} days` : '—' }}</td>
            <td>
              <VChip
                :color="inv._supplier?.status === 'active' ? 'success' : 'secondary'"
                size="small"
                label
                class="text-capitalize"
              >
                {{ inv._supplier?.status }}
              </VChip>
            </td>
            <td>
              <VTooltip
                v-if="inv.status === 'declined' && inv.declineReason"
                :text="inv.declineReason"
              >
                <template #activator="{ props: tooltipProps }">
                  <VChip
                    v-bind="tooltipProps"
                    :color="invStatusColor(inv.status)"
                    size="small"
                    label
                    class="text-capitalize cursor-help"
                  >
                    {{ (inv.status || 'No Response').replace('_', ' ') }}
                  </VChip>
                </template>
              </VTooltip>
              <VChip
                v-else
                :color="invStatusColor(inv.status)"
                size="small"
                label
                class="text-capitalize"
              >
                {{ (inv.status || 'No Response').replace('_', ' ') }}
              </VChip>
            </td>
            <td class="text-caption text-medium-emphasis">
              {{ formatDate(inv.sentAt) }}
            </td>
            <td class="text-caption text-medium-emphasis">
              {{ formatDate(inv.viewedAt) }}
            </td>
            <td class="text-end">
              <IconBtn
                color="error"
                :disabled="isReadOnly"
                @click="toggleSupplierInvitation(inv.supplierId)"
              >
                <VIcon icon="tabler-trash" />
              </IconBtn>
            </td>
          </tr>
        </tbody>
      </VTable>
      <div
        v-else
        class="text-center text-disabled pa-8"
      >
        <VIcon
          icon="tabler-users-group"
          size="48"
          class="mb-3 opacity-50"
        />
        <p class="text-body-1 mb-0">
          No suppliers invited yet. Click "Invite Supplier(s)" to add some.
        </p>
      </div>
    </VCardText>
  </VCard>

  <VDialog
    v-model="showModal"
    max-width="800"
    scrollable
  >
    <VCard class="glass-panel">
      <VCardItem class="bg-surface">
        <VCardTitle>Select Suppliers</VCardTitle>
        <template #append>
          <IconBtn @click="showModal = false">
            <VIcon icon="tabler-x" />
          </IconBtn>
        </template>
      </VCardItem>
      <VDivider />
      <VCardText
        class="pa-0"
        style="max-block-size: 60vh;"
      >
        <div class="pa-4">
          <AppTextField
            v-model="supplierSearch"
            placeholder="Search suppliers..."
            density="compact"
            prepend-inner-icon="tabler-search"
          />
        </div>
        <VTable
          density="comfortable"
          class="rfq-supplier-table"
        >
          <thead>
            <tr>
              <th style="inline-size: 60px;">
                Invite
              </th>
              <th>Supplier ID</th>
              <th>Legal Name</th>
              <th>Category</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="supplier in filteredSuppliers"
              :key="supplier.id"
              :class="{ 'rfq-supplier--selected': isSupplierInvited(supplier.id) }"
              style="cursor: pointer;"
              @click="toggleSupplierInvitation(supplier.id)"
            >
              <td>
                <VCheckbox
                  :model-value="isSupplierInvited(supplier.id)"
                  hide-details
                  color="info"
                  density="compact"
                  @click.stop="toggleSupplierInvitation(supplier.id)"
                />
              </td>
              <td class="font-weight-bold text-primary">
                {{ supplier.supplier_id }}
              </td>
              <td>{{ supplier.legal_name }}</td>
              <td>
                <VChip
                  v-if="supplier.supplier_category"
                  size="small"
                  label
                  variant="tonal"
                  class="text-capitalize"
                >
                  {{ supplier.supplier_category }}
                </VChip>
              </td>
              <td>
                <VChip
                  :color="supplier.status === 'active' ? 'success' : 'secondary'"
                  size="small"
                  label
                  class="text-capitalize"
                >
                  {{ supplier.status }}
                </VChip>
              </td>
            </tr>
            <tr v-if="!filteredSuppliers || filteredSuppliers.length === 0">
              <td
                colspan="5"
                class="text-center text-disabled pa-6"
              >
                No suppliers found.
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
      <VDivider />
      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          color="primary"
          @click="showModal = false"
        >
          Done
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.glass-panel {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 16px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.7) !important;
  box-shadow:
    0 4px 32px rgba(0, 0, 0, 6%),
    0 1px 4px rgba(0, 0, 0, 4%),
    inset 0 1px 0 rgba(255, 255, 255, 6%);
}

.rfq-supplier-table {
  :deep(thead th) {
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    opacity: 0.85;
    text-transform: uppercase;
  }

  :deep(tbody tr) {
    transition: background-color 0.15s ease;

    &:hover {
      background: rgba(var(--v-theme-info), 0.04) !important;
    }
  }
}

.rfq-supplier--selected {
  background: rgba(var(--v-theme-info), 0.06) !important;
  border-inline-start: 3px solid rgb(var(--v-theme-info));
}
</style>
