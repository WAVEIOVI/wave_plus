/* eslint-disable camelcase */
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, ref, unref, watch } from 'vue'

/**
 * Optional Sales Order / Delivery Note options for a workwear record, scoped to one client.
 * SO/DN are never mandatory; the selects are clearable and only list documents of the client.
 *
 * @param {import('vue').Ref<string|null>|string|null} clientId   owning client of the workwear record
 * @param {import('vue').Ref<object|null>|object|null} selected   already linked { salesOrder, deliveryNote } summaries
 *                                                                ({ id, reference } / { id, dn_number }) so they stay selectable
 */
export function useWorkwearDocumentOptions(clientId, selected = null) {
  const store = useClientAssetStore()
  const loading = ref(false)
  const salesOrders = ref([])
  const deliveryNotes = ref([])
  let requestSeq = 0

  const withSelected = (list, current) => {
    if (current?.id && !list.some(o => o.id === current.id)) return [current, ...list]

    return list
  }

  const salesOrderItems = computed(() => {
    const so = unref(selected)?.salesOrder

    return withSelected(salesOrders.value, so ? { id: so.id, label: so.reference } : null)
      .map(o => ({ title: o.label, value: o.id }))
  })

  const deliveryNoteItems = computed(() => {
    const dn = unref(selected)?.deliveryNote

    return withSelected(deliveryNotes.value, dn ? { id: dn.id, label: dn.dn_number } : null)
      .map(o => ({ title: o.label, value: o.id }))
  })

  const load = async (search = '') => {
    const id = unref(clientId)

    if (!id) {
      salesOrders.value = []
      deliveryNotes.value = []

      return
    }

    // Ignore stale responses when the client changes quickly.
    const seq = ++requestSeq

    loading.value = true
    try {
      const data = await store.fetchWorkwearDocumentOptions({ client_id: id, q: search || undefined })

      if (seq !== requestSeq) return
      salesOrders.value = data?.sales_orders || []
      deliveryNotes.value = data?.delivery_notes || []
    }
    catch {
      if (seq === requestSeq) {
        salesOrders.value = []
        deliveryNotes.value = []
      }
    }
    finally {
      if (seq === requestSeq) loading.value = false
    }
  }

  watch(() => unref(clientId), () => load(), { immediate: true })

  return { loading, salesOrderItems, deliveryNoteItems, load }
}
