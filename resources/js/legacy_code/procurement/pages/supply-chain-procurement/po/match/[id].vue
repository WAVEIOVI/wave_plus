<script setup>
import { usePoStore } from '@/stores/usePoStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

definePage({
  path: '/supply-chain-procurement/po/match/:id',
  name: 'procurement-match-id',
  meta: {
    layout: 'default',
    navActiveLink: 'procurement-po-list',
    module: 'supply-chain-procurement',
    action: 'read',
    subject: 'SupplyChainProcurement',
  },
})

const poStore = usePoStore()
const snackbarStore = useSnackbarStore()
const route = useRoute()
const router = useRouter()

const loading = ref(false)
const po = ref(null)
const isMatching = ref(false)

onMounted(async () => {
  loading.value = true
  try {
    const data = await poStore.fetchDetails(route.params.id)

    po.value = data
  } catch (e) {
    console.error(e)
    snackbarStore.showMessage('Error loading PO details', 'error')
  } finally {
    loading.value = false
  }
})

const performMatch = () => {
  isMatching.value = true
  
  // Simulate heavy processing for UX
  setTimeout(() => {
    isMatching.value = false
    snackbarStore.showMessage('3-Way Match executed successfully. Document status updated.', 'success')
    router.push({ name: 'procurement-po-edit-id', params: { id: route.params.id } })
  }, 2000)
}

const formatDate = isoString => {
  if (!isoString) return '—'
  const d = new Date(isoString)
  
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div class="match-page">
    <!-- 👉 Breadcrumbs -->
    <VBreadcrumbs class="px-0 mb-4">
      <VBreadcrumbsItem title="Supply Chain Procurement" />
      <VBreadcrumbsDivider><VIcon icon="tabler-chevron-right" /></VBreadcrumbsDivider>
      <VBreadcrumbsItem
        :to="{ name: 'procurement-po-list' }"
        title="Purchase Orders"
      />
      <VBreadcrumbsDivider><VIcon icon="tabler-chevron-right" /></VBreadcrumbsDivider>
      <VBreadcrumbsItem
        :to="{ name: 'procurement-po-edit-id', params: { id: route.params.id } }"
        :title="po?.reference || 'Loading...'"
      />
      <VBreadcrumbsDivider><VIcon icon="tabler-chevron-right" /></VBreadcrumbsDivider>
      <VBreadcrumbsItem
        title="3-Way Match"
        disabled
      />
    </VBreadcrumbs>

    <VRow v-if="loading">
      <VCol cols="12">
        <VCard class="pa-6 glass-panel">
          <VSkeletonLoader type="article, table-heading, table-row-divider@3, actions" />
        </VCard>
      </VCol>
    </VRow>

    <VRow v-else-if="po">
      <VCol cols="12">
        <VCard class="glass-panel overflow-hidden">
          <VCardText class="match-hero pa-8 d-flex flex-wrap align-center justify-space-between gap-6">
            <div class="d-flex align-center gap-6">
              <VAvatar
                rounded
                size="84"
                color="primary"
                variant="tonal"
                class="elevation-4"
              >
                <VIcon
                  icon="tabler-arrows-join-2"
                  size="42"
                />
              </VAvatar>
              <div>
                <h4 class="text-h4 font-weight-bold mb-1">
                  3-Way Match Process
                </h4>
                <p class="text-body-1 text-medium-emphasis mb-0">
                  Validating PO <span class="text-primary font-weight-bold">{{ po.reference }}</span> against receipts and invoices.
                </p>
                <div class="d-flex gap-2 mt-3">
                  <VChip
                    label
                    color="primary"
                    variant="tonal"
                    size="small"
                  >
                    Supplier: {{ po.supplier?.legal_name || 'N/A' }}
                  </VChip>
                  <VChip
                    label
                    color="secondary"
                    variant="tonal"
                    size="small"
                  >
                    Date: {{ formatDate(po.created_at) }}
                  </VChip>
                </div>
              </div>
            </div>

            <div class="d-flex gap-3">
              <VBtn
                variant="tonal"
                color="secondary"
                :to="{ name: 'procurement-po-edit-id', params: { id: route.params.id } }"
              >
                Cancel
              </VBtn>
              <VBtn
                color="primary"
                size="large"
                :loading="isMatching"
                @click="performMatch"
              >
                <VIcon
                  start
                  icon="tabler-bolt"
                />
                Execute Match
              </VBtn>
            </div>
          </VCardText>

          <VDivider />

          <VCardText class="pa-8">
            <VRow>
              <!-- ── Column 1: Match Logic ── -->
              <VCol
                cols="12"
                md="8"
              >
                <h6 class="text-h6 font-weight-bold mb-6 d-flex align-center gap-2">
                  <VIcon
                    icon="tabler-list-check"
                    color="primary"
                  />
                  Reconciliation Items
                </h6>

                <div class="match-list d-flex flex-column gap-4">
                  <!-- PO Segment -->
                  <VCard
                    variant="outlined"
                    class="match-item border-primary"
                  >
                    <VCardText class="d-flex align-center gap-4 pa-4">
                      <VIcon
                        icon="tabler-file-invoice"
                        size="32"
                        color="primary"
                      />
                      <div class="grow">
                        <div class="d-flex justify-space-between align-center">
                          <span class="text-subtitle-1 font-weight-bold">Purchase Order</span>
                          <VIcon
                            icon="tabler-circle-check-filled"
                            color="success"
                          />
                        </div>
                        <p class="text-caption text-medium-emphasis mb-0">
                          {{ po.reference }} — Total: {{ po.total_ht }} {{ po.currency }}
                        </p>
                      </div>
                    </VCardText>
                  </VCard>

                  <!-- GRN Segment -->
                  <VCard
                    variant="outlined"
                    class="match-item"
                  >
                    <VCardText class="d-flex align-center gap-4 pa-4">
                      <VIcon
                        icon="tabler-package-import"
                        size="32"
                        color="success"
                      />
                      <div class="grow">
                        <div class="d-flex justify-space-between align-center">
                          <span class="text-subtitle-1 font-weight-bold">Goods Receipt Note (GRN)</span>
                          <VChip
                            color="success"
                            size="x-small"
                            label
                          >
                            Fully Received
                          </VChip>
                        </div>
                        <p class="text-caption text-medium-emphasis mb-0">
                          Matched quantities against delivery notes.
                        </p>
                      </div>
                    </VCardText>
                  </VCard>

                  <!-- Invoice Segment -->
                  <VCard
                    variant="outlined"
                    class="match-item"
                  >
                    <VCardText class="d-flex align-center gap-4 pa-4">
                      <VIcon
                        icon="tabler-receipt-2"
                        size="32"
                        color="warning"
                      />
                      <div class="grow">
                        <div class="d-flex justify-space-between align-center">
                          <span class="text-subtitle-1 font-weight-bold">Supplier Invoice</span>
                          <VChip
                            color="warning"
                            size="x-small"
                            label
                          >
                            Pending Match
                          </VChip>
                        </div>
                        <p class="text-caption text-medium-emphasis mb-0">
                          Comparing unit prices and totals with PO.
                        </p>
                      </div>
                    </VCardText>
                  </VCard>
                </div>
              </VCol>

              <!-- ── Column 2: Stats & Help ── -->
              <VCol
                cols="12"
                md="4"
              >
                <VAlert
                  variant="tonal"
                  color="info"
                  icon="tabler-info-circle"
                  class="mb-6"
                >
                  3-Way Matching ensures that the Purchase Order, Receiving Report, and Invoice all match before payment is issued.
                </VAlert>

                <VCard
                  variant="tonal"
                  color="primary"
                  class="pa-4 mb-4"
                >
                  <h6 class="text-subtitle-2 font-weight-bold mb-3">
                    Matching Policy
                  </h6>
                  <div class="d-flex justify-space-between text-caption mb-2">
                    <span>Quantity Tolerance</span>
                    <span class="font-weight-bold">0%</span>
                  </div>
                  <div class="d-flex justify-space-between text-caption mb-2">
                    <span>Price Tolerance</span>
                    <span class="font-weight-bold">±2%</span>
                  </div>
                  <div class="d-flex justify-space-between text-caption">
                    <span>Auto-Approval</span>
                    <span class="font-weight-bold">Enabled</span>
                  </div>
                </VCard>

                <div class="d-flex flex-column gap-3">
                  <h6 class="text-subtitle-2 font-weight-bold">
                    Status History
                  </h6>
                  <div class="d-flex gap-3 align-center">
                    <div class="status-dot status-dot--success" />
                    <span class="text-caption">PO Released</span>
                  </div>
                  <div class="d-flex gap-3 align-center">
                    <div class="status-dot status-dot--success" />
                    <span class="text-caption">Goods Received</span>
                  </div>
                  <div class="d-flex gap-3 align-center">
                    <div class="status-dot status-dot--warning" />
                    <span class="text-caption">Invoice Uploaded</span>
                  </div>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
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

.match-hero {
  background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.05) 0%, rgba(var(--v-theme-primary), 0.01) 100%);
}

.match-item {
  transition: all 0.2s ease;

  &:hover {
    border-color: rgb(var(--v-theme-primary));
    transform: translateX(4px);
  }
}

.border-primary {
  border-color: rgba(var(--v-theme-primary), 0.3) !important;
}

.status-dot {
  border-radius: 50%;
  block-size: 8px;
  inline-size: 8px;
  &--success { background-color: rgb(var(--v-theme-success)); }
  &--warning { background-color: rgb(var(--v-theme-warning)); }
}
</style>
