<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { $api } from '@/utils/api'
import { ref } from 'vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  rfqId: { type: [String, Number], default: null },
  rfqReference: { type: String, default: '' },
  invitations: { type: Array, default: () => [] }, // [{ supplierId, _supplier }]
})

const emit = defineEmits(['update:isDialogVisible', 'sent'])

const isSending   = ref(false)
const sentCount   = ref(0)
const errorCount  = ref(0)
const broadcastDone = ref(false)

// Per-supplier status: 'idle' | 'sending' | 'sent' | 'error'
const statuses = ref({})

const supplierName = inv => {
  const s = inv._supplier
  if (!s) return `Supplier #${inv.supplierId}`

  return localizedValue(s.commercial_name) || s.legal_name || `Supplier #${inv.supplierId}`
}

const supplierEmail = inv => inv._supplier?.primary_email || inv._supplier?.email || '—'

const sendAll = async () => {
  if (!props.rfqId) return
  isSending.value   = true
  broadcastDone.value = false
  sentCount.value   = 0
  errorCount.value  = 0

  // Mark all as "sending"
  props.invitations.forEach(inv => { statuses.value[inv.supplierId] = 'sending' })

  try {
    // Single endpoint — backend emails each supplier
    await $api(`/procurement/rfqs/${props.rfqId}/broadcast`, { method: 'POST' })

    // Mark all sent
    props.invitations.forEach(inv => { statuses.value[inv.supplierId] = 'sent' })
    sentCount.value = props.invitations.length
  } catch {
    // Fallback: mark all error
    props.invitations.forEach(inv => { statuses.value[inv.supplierId] = 'error' })
    errorCount.value = props.invitations.length
  } finally {
    isSending.value     = false
    broadcastDone.value = true
    emit('sent')
  }
}

const close = () => {
  statuses.value = {}
  broadcastDone.value = false
  sentCount.value  = 0
  errorCount.value = 0
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="640"
    scrollable
    @update:model-value="close"
  >
    <VCard class="bcast-card">
      <!-- Header -->
      <VCardItem class="bcast-header">
        <template #prepend>
          <VAvatar
            rounded
            size="44"
            color="info"
            variant="tonal"
          >
            <VIcon
              icon="tabler-send"
              size="24"
            />
          </VAvatar>
        </template>
        <VCardTitle class="text-h5 font-weight-bold">
          Broadcast RFQ
        </VCardTitle>
        <VCardSubtitle>
          {{ rfqReference }} — Email RFQ to invited suppliers
        </VCardSubtitle>
        <template #append>
          <IconBtn @click="close">
            <VIcon icon="tabler-x" />
          </IconBtn>
        </template>
      </VCardItem>

      <VDivider />

      <!-- Result banner -->
      <VAlert
        v-if="broadcastDone && sentCount > 0"
        type="success"
        variant="tonal"
        class="ma-4 mb-0"
        density="compact"
        prepend-icon="tabler-check"
      >
        RFQ sent to {{ sentCount }} supplier{{ sentCount > 1 ? 's' : '' }} successfully.
      </VAlert>
      <VAlert
        v-if="broadcastDone && errorCount > 0"
        type="error"
        variant="tonal"
        class="ma-4 mb-0"
        density="compact"
        prepend-icon="tabler-alert-triangle"
      >
        Failed to send to {{ errorCount }} supplier{{ errorCount > 1 ? 's' : '' }}.
      </VAlert>

      <VCardText
        class="pa-0"
        style="max-block-size: 55vh; overflow-y: auto;"
      >
        <!-- Empty -->
        <div
          v-if="invitations.length === 0"
          class="d-flex flex-column align-center pa-10 text-center"
        >
          <VIcon
            icon="tabler-building-off"
            size="52"
            class="text-medium-emphasis mb-3"
          />
          <p class="text-body-1 text-medium-emphasis mb-0">
            No suppliers have been invited to this RFQ yet.
          </p>
          <p class="text-caption text-disabled mt-1">
            Add suppliers in the "Suppliers" tab first.
          </p>
        </div>

        <!-- Supplier list -->
        <VList
          v-else
          lines="two"
          density="compact"
        >
          <VListItem
            v-for="inv in invitations"
            :key="inv.supplierId"
          >
            <template #prepend>
              <VAvatar
                rounded
                size="38"
                color="info"
                variant="tonal"
              >
                <VIcon
                  icon="tabler-building-store"
                  size="20"
                />
              </VAvatar>
            </template>

            <VListItemTitle class="font-weight-medium">
              {{ supplierName(inv) }}
            </VListItemTitle>
            <VListItemSubtitle>
              {{ supplierEmail(inv) }}
            </VListItemSubtitle>

            <template #append>
              <VChip
                v-if="statuses[inv.supplierId] === 'sent'"
                color="success"
                size="small"
                label
                prepend-icon="tabler-check"
              >
                Sent
              </VChip>
              <VChip
                v-else-if="statuses[inv.supplierId] === 'error'"
                color="error"
                size="small"
                label
                prepend-icon="tabler-x"
              >
                Failed
              </VChip>
              <VProgressCircular
                v-else-if="statuses[inv.supplierId] === 'sending'"
                indeterminate
                size="20"
                width="2"
                color="info"
              />
              <VChip
                v-else
                color="secondary"
                size="small"
                label
                variant="tonal"
              >
                Pending
              </VChip>
            </template>
          </VListItem>
        </VList>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 gap-3">
        <VBtn
          color="secondary"
          variant="tonal"
          @click="close"
        >
          {{ broadcastDone ? 'Close' : 'Cancel' }}
        </VBtn>
        <VSpacer />
        <VBtn
          v-if="!broadcastDone"
          color="info"
          :loading="isSending"
          :disabled="invitations.length === 0"
          prepend-icon="tabler-send"
          @click="sendAll"
        >
          Send All ({{ invitations.length }})
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.bcast-card { border-radius: 16px !important; }
</style>
