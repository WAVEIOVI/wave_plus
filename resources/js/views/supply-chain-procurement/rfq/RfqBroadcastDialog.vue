<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRfqStore } from '@/stores/useRfqStore'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useProcurementHelpers } from '@/composables/supply-chain-procurement/useProcurementHelpers'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  rfqId: {
    type: String,
    default: null,
  },
  invitations: {
    type: Array,
    default: () => [],
  },
  suppliers: {
    type: Array,
    default: () => [],
  },
  invitedSupplierIds: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'completed'])

const { t } = useI18n()
const rfqStore = useRfqStore()
const { success, showApiError } = useAppSnackbar()
const { resolveSupplierName } = useProcurementHelpers()

const isSending = ref(false)
const isDone = ref(false)
const statusMap = ref({})

const targetInvitations = computed(() => {
  if (props.invitations && props.invitations.length > 0) {
    return props.invitations
  }

  return (props.invitedSupplierIds || []).map(sid => {
    const sup = (props.suppliers || []).find(s => s.id === sid) || { id: sid }

    return {
      id: sid,
      supplier_id: sid,
      supplier: sup,
    }
  })
})

watch(
  () => props.modelValue,
  open => {
    if (open) {
      isSending.value = false
      isDone.value = false
      statusMap.value = {}
      targetInvitations.value.forEach(inv => {
        statusMap.value[inv.id || inv.supplier_id] = 'pending'
      })
    }
  },
)

const closeDialog = () => {
  if (isSending.value) return
  emit('update:modelValue', false)
}

const startBroadcast = async () => {
  if (!props.rfqId || isSending.value) return
  isSending.value = true

  targetInvitations.value.forEach(inv => {
    statusMap.value[inv.id || inv.supplier_id] = 'sending'
  })

  try {
    const updated = await rfqStore.updateStatus(props.rfqId, { status: 'sent' })

    targetInvitations.value.forEach(inv => {
      statusMap.value[inv.id || inv.supplier_id] = 'sent'
    })

    isDone.value = true
    success(t('scp.rfq.snackbar.broadcast_success', 'RFQ broadcast sent to invited suppliers.'))
    emit('completed', updated)
  }
  catch (err) {
    targetInvitations.value.forEach(inv => {
      statusMap.value[inv.id || inv.supplier_id] = 'failed'
    })
    showApiError(err, t('scp.rfq.snackbar.error_status'))
  }
  finally {
    isSending.value = false
  }
}
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="t('scp.rfq.broadcast.title', 'Broadcast RFQ to Suppliers')"
    :subtitle="t('scp.rfq.broadcast.subtitle', 'Dispatch this Request for Quotation to all invited suppliers and transition status to Sent.')"
    icon="tabler-send"
    size="medium"
    :loading="isSending"
    :submit-label="isDone ? t('scp.action.close', 'Close') : t('scp.rfq.actions.send', 'Send Now')"
    :submit-disabled="targetInvitations.length === 0"
    @update:model-value="closeDialog"
    @cancel="closeDialog"
    @submit="isDone ? closeDialog() : startBroadcast()"
  >
    <VAlert
      v-if="targetInvitations.length === 0"
      type="warning"
      variant="tonal"
      icon="tabler-alert-triangle"
      class="mb-4"
    >
      {{ t('scp.rfq.broadcast.no_suppliers_warning', 'Please invite at least one supplier before broadcasting this RFQ.') }}
    </VAlert>

    <template v-else>
      <p class="text-body-2 text-medium-emphasis mb-4">
        {{ t('scp.rfq.broadcast.confirm_message', { count: targetInvitations.length }, `This RFQ will be dispatched to ${targetInvitations.length} invited supplier(s):`) }}
      </p>

      <VList
        lines="two"
        border
        rounded="lg"
        class="py-0"
      >
        <template
          v-for="(inv, index) in targetInvitations"
          :key="inv.id || inv.supplier_id"
        >
          <VListItem>
            <template #prepend>
              <VAvatar
                color="primary"
                variant="tonal"
                size="36"
              >
                <VIcon
                  icon="tabler-building-store"
                  size="18"
                />
              </VAvatar>
            </template>

            <VListItemTitle class="font-weight-semibold">
              {{ resolveSupplierName(inv.supplier) }}
            </VListItemTitle>
            <VListItemSubtitle class="text-caption">
              {{ inv.supplier?.primary_contact_email || inv.supplier?.email || inv.supplier?.code || '—' }}
            </VListItemSubtitle>

            <template #append>
              <div class="d-flex align-center">
                <VChip
                  v-if="statusMap[inv.id || inv.supplier_id] === 'pending'"
                  size="small"
                  color="secondary"
                  variant="tonal"
                  label
                >
                  {{ t('scp.rfq.broadcast.status_pending', 'Pending') }}
                </VChip>

                <div
                  v-else-if="statusMap[inv.id || inv.supplier_id] === 'sending'"
                  class="d-flex align-center text-info text-caption font-weight-medium"
                >
                  <VProgressCircular
                    indeterminate
                    size="16"
                    width="2"
                    color="info"
                    class="me-2"
                  />
                  {{ t('scp.rfq.broadcast.status_sending', 'Sending...') }}
                </div>

                <VChip
                  v-else-if="statusMap[inv.id || inv.supplier_id] === 'sent'"
                  size="small"
                  color="success"
                  variant="tonal"
                  prepend-icon="tabler-check"
                  label
                >
                  {{ t('scp.rfq.broadcast.status_sent', 'Sent') }}
                </VChip>

                <VChip
                  v-else-if="statusMap[inv.id || inv.supplier_id] === 'failed'"
                  size="small"
                  color="error"
                  variant="tonal"
                  prepend-icon="tabler-alert-triangle"
                  label
                >
                  {{ t('scp.rfq.broadcast.status_failed', 'Failed') }}
                </VChip>
              </div>
            </template>
          </VListItem>
          <VDivider v-if="index < targetInvitations.length - 1" />
        </template>
      </VList>
    </template>
  </WpFormDialog>
</template>
