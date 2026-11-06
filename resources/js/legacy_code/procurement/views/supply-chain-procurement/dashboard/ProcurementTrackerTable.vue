<script setup>
/* eslint-disable camelcase */
import { usePoStore } from '@/stores/usePoStore'
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const poStore = usePoStore()

const loading = ref(false)
const items = ref([])

onMounted(async () => {
  loading.value = true

  try {
    const res = await poStore.fetchList({ itemsPerPage: 50 })

    items.value = res.data || []
  } catch (err) {
    console.error('Failed to load POs for tracker', err)

  } finally {
    loading.value = false
  }
})

const getStatusColor = status => {
  const map = {
    draft: 'secondary',
    pending_approval: 'warning',
    approved: 'success',
    sent: 'info',
    acknowledged: 'primary',
    in_delivery: 'purple',
    partially_received: 'orange',
    fully_received: 'teal',
    cancelled: 'error',
  }

  return map[status] || 'primary'
}
</script>

<template>
  <VCard class="neo-raised">
    <VCardItem>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon
          icon="tabler-route"
          color="primary"
        />
        Procurement Tracker
      </VCardTitle>
      <VCardSubtitle>Track the full lifecycle of procurement documents (IPR → RFQ → PO → GRN)</VCardSubtitle>
    </VCardItem>
    <VDivider />
    <VTable class="text-no-wrap">
      <thead>
        <tr>
          <th class="text-uppercase font-weight-bold">
            Purchase Order
          </th>
          <th class="text-uppercase font-weight-bold">
            Supplier
          </th>
          <th class="text-uppercase font-weight-bold text-center">
            Status
          </th>
          <th class="text-uppercase font-weight-bold">
            Amount (HT)
          </th>
          <th class="text-uppercase font-weight-bold text-center">
            Related IPRs
          </th>
          <th class="text-uppercase font-weight-bold text-center">
            Related RFQ
          </th>
          <th class="text-uppercase font-weight-bold text-center">
            Actions
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading">
          <td
            colspan="7"
            class="text-center py-8"
          >
            <VProgressCircular
              indeterminate
              color="primary"
            />
          </td>
        </tr>
        <tr v-else-if="items.length === 0">
          <td
            colspan="7"
            class="text-center py-8 text-medium-emphasis"
          >
            No active procurement tracks found.
          </td>
        </tr>
        <tr
          v-for="item in items"
          v-else
          :key="item.id"
        >
          <td
            class="font-weight-bold text-primary cursor-pointer"
            @click="router.push({ name: 'procurement-po-edit-id', params: { id: item.id } })"
          >
            {{ item.reference || 'Draft PO' }}
          </td>
          <td>
            {{ item.supplier?.legal_name || 'N/A' }}
          </td>
          <td class="text-center">
            <VChip
              size="small"
              :color="getStatusColor(item.status)"
              class="text-capitalize"
              label
              variant="tonal"
            >
              {{ (item.status || '').replace(/_/g, ' ') }}
            </VChip>
          </td>
          <td class="font-weight-medium">
            {{ item.total_ht ? parseFloat(item.total_ht).toFixed(3) : '0.000' }} {{ item.currency || 'TND' }}
          </td>
          <td class="text-center">
            <VChip
              v-if="item.ipr_ids && item.ipr_ids.length > 0"
              size="small"
              color="info"
              variant="flat"
            >
              {{ item.ipr_ids.length }} IPR(s)
            </VChip>
            <span
              v-else
              class="text-disabled"
            >—</span>
          </td>
          <td class="text-center">
            <VChip
              v-if="item.rfq_id"
              size="small"
              color="warning"
              variant="flat"
              @click="router.push({ name: 'procurement-rfq-edit-id', params: { id: item.rfq_id } })"
            >
              View RFQ
            </VChip>
            <span
              v-else
              class="text-disabled"
            >—</span>
          </td>
          <td class="text-center">
            <VBtn
              size="small"
              variant="text"
              color="primary"
              icon="tabler-chevron-right"
              @click="router.push({ name: 'procurement-po-edit-id', params: { id: item.id } })"
            />
          </td>
        </tr>
      </tbody>
    </VTable>
  </VCard>
</template>
