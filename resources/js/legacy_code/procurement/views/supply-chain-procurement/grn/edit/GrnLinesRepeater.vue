<script setup>
import GrnBatchReceiveModal from '@/views/supply-chain-procurement/grn/edit/GrnBatchReceiveModal.vue'
import GrnLineCardsList from '@/views/supply-chain-procurement/grn/edit/GrnLineCardsList.vue'
import GrnReceiveQcModal from '@/views/supply-chain-procurement/grn/edit/GrnReceiveQcModal.vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  lines: { type: Array, required: true },
  locations: { type: Array, default: () => [] },
  zones: { type: Array, default: () => [] },
  isLocked: { type: Boolean, default: false },
})

const emit = defineEmits(['update:lines', 'add-adhoc-items'])

const { t } = useI18n()

const expandedQcPanels = ref([])

const activeLineIndex = ref(null)
const isReceiveQcModalOpen = ref(false)
const isBatchReceiveModalOpen = ref(false)

const activeLine = computed(() =>
  activeLineIndex.value !== null ? props.lines[activeLineIndex.value] : null,
)


const openReceiveQcModal = index => {
  activeLineIndex.value = index
  isReceiveQcModalOpen.value = true
}

const closeReceiveQcModal = () => {
  isReceiveQcModalOpen.value = false
  activeLineIndex.value = null
}

const processedLinesCount = computed(() => {
  return (props.lines || []).filter(line => {
    return (parseFloat(line.qty_received) || 0) > 0 || line.qc_result
  }).length
})

const updateLine = (index, field, value) => {
  const newLines = [...props.lines]

  newLines[index][field] = value
  emit('update:lines', newLines)
}

const removeLine = index => {
  const newLines = [...props.lines]

  newLines.splice(index, 1)
  emit('update:lines', newLines)
}

// ── recalcLine — core reactive business logic ───────────────────────────
const recalcLine = (index, trigger) => {
  const newLines = [...props.lines]
  const line = { ...newLines[index] }
  const received = parseFloat(line.qty_received) || 0
  const accepted = parseFloat(line.qty_accepted) || 0
  const ordered = parseFloat(line.qty_ordered) || 0

  // Auto-calc rejected
  line.qty_rejected = Math.max(0, received - accepted)

  // Clamp accepted to not exceed received
  if (accepted > received) {
    line.qty_accepted = received
    line.qty_rejected = 0
  }

  // Auto-set QC Result
  const rejected = parseFloat(line.qty_rejected) || 0

  if (rejected === 0 && received === ordered && received > 0) {
    line.qc_result = 'passed'
  } else if (rejected > 0 && accepted > 0) {
    line.qc_result = 'passed_with_remarks'
  } else if (accepted === 0 && rejected > 0) {
    line.qc_result = 'rejected'
  }

  // Auto-expand QC panel if rejected > 0
  if (rejected > 0 && !expandedQcPanels.value.includes(index)) {
    expandedQcPanels.value = [...expandedQcPanels.value, index]
  }

  newLines[index] = line
  emit('update:lines', newLines)
}

const onQtyReceivedChange = (index, val) => {
  updateLine(index, 'qty_received', Number(val))
  recalcLine(index, 'received')
}

const onQtyAcceptedChange = (index, val) => {
  updateLine(index, 'qty_accepted', Number(val))
  recalcLine(index, 'accepted')
}

const onQtyRejectedChange = (index, val) => {
  const newLines = [...props.lines]
  const line = { ...newLines[index] }
  const received = parseFloat(line.qty_received) || 0
  let rejected = Math.max(0, parseFloat(val) || 0)

  if (rejected > received) rejected = received
  line.qty_rejected = rejected
  line.qty_accepted = Math.max(0, received - rejected)

  const ordered = parseFloat(line.qty_ordered) || 0

  if (rejected === 0 && received === ordered && received > 0) {
    line.qc_result = 'passed'
  } else if (rejected > 0 && line.qty_accepted > 0) {
    line.qc_result = 'passed_with_remarks'
  } else if (line.qty_accepted === 0 && rejected > 0) {
    line.qc_result = 'rejected'
  }

  if (rejected > 0 && !expandedQcPanels.value.includes(index)) {
    expandedQcPanels.value = [...expandedQcPanels.value, index]
  }

  newLines[index] = line
  emit('update:lines', newLines)
}

const handleModalFieldUpdate = (field, value) => {
  if (activeLineIndex.value === null) return
  updateLine(activeLineIndex.value, field, value)
}

const handleModalRemoveLine = () => {
  if (activeLineIndex.value === null) return
  removeLine(activeLineIndex.value)
  closeReceiveQcModal()
}

const applyBatchReceive = ({ zoneId, storageLocationId, drafts }) => {
  const newLines = [...props.lines]

  drafts.forEach(draft => {
    const line = { ...newLines[draft.__index] }

    line.qty_received = draft.qty_received
    line.qty_accepted = draft.qty_accepted
    line.qty_rejected = draft.qty_rejected
    line.qc_result = draft.qc_result
    if (zoneId) line.selected_zone_id = zoneId
    if (storageLocationId) line.storage_location_id = storageLocationId
    newLines[draft.__index] = line
  })

  emit('update:lines', newLines)
}

// ── Visual helpers ──────────────────────────────────────────────────────
const qcColor = v => ({ passed: 'success', passed_with_remarks: 'warning', rejected: 'error' }[v] || 'secondary')

const varianceType = line => {
  const ordered = parseFloat(line.qty_ordered) || 0
  const received = parseFloat(line.qty_received) || 0

  if (ordered === 0 || received === ordered) return null

  return received > ordered ? 'over' : 'under'
}

const normalizedLines = computed(() => (props.lines || []).map((l, idx) => ({ ...l, __index: idx })))

// ── Location filtering ──────────────────────────────────────────────────
const onZoneChange = (index, zoneId) => {
  updateLine(index, 'selected_zone_id', zoneId)

  const currentLocation = props.lines[index].storage_location_id

  if (currentLocation) {
    const loc = props.locations.find(l => l.id === currentLocation)

    if (loc && loc.zone_id !== zoneId) {
      updateLine(index, 'storage_location_id', null)
    }
  }
}
</script>

<template>
  <div class="grn-repeater">
    <!-- ── Toolbar ────────────────────────────────────────────────────────── -->
    <div class="d-flex align-center flex-wrap ga-2 pa-4">
      <VIcon
        icon="tabler-package-import"
        size="22"
        color="primary"
      />
      <span class="text-subtitle-1 font-weight-bold">{{ t('scp.grn.sections.receiving_qc') }}</span>
      <VChip
        size="x-small"
        label
        color="primary"
        variant="tonal"
        class="ms-1"
      >
        {{ processedLinesCount }} / {{ lines.length }}
      </VChip>
      <VSpacer />
      <div class="d-flex align-center ga-2">
        <VBtn
          v-if="!isLocked"
          size="small"
          variant="outlined"
          color="primary"
          prepend-icon="tabler-plus-box"
          @click="emit('add-adhoc-items')"
        >
          {{ t('scp.grn.action.add_adhoc_items') }}
        </VBtn>
        <VBtn
          v-if="!isLocked && lines.length"
          size="small"
          variant="flat"
          color="primary"
          prepend-icon="tabler-packages"
          @click="isBatchReceiveModalOpen = true"
        >
          {{ t('scp.grn.action.batch_receive') }}
        </VBtn>
      </div>
    </div>
    <VDivider />

    <!-- ── Empty State ───────────────────────────────────────────────────── -->
    <div
      v-if="lines.length === 0"
      class="d-flex flex-column align-center pa-10 text-center"
    >
      <VAvatar
        rounded
        size="72"
        color="secondary"
        variant="tonal"
        class="mb-4"
      >
        <VIcon
          icon="tabler-package-import"
          size="36"
        />
      </VAvatar>
      <h6 class="text-h6 text-disabled mb-1">
        {{ t('scp.grn.empty.no_lines') }}
      </h6>
      <p class="text-body-2 text-disabled mb-0">
        {{ t('scp.grn.empty.select_po') }}
      </p>
    </div>
    <GrnLineCardsList
      v-else
      :lines="normalizedLines"
      :is-locked="isLocked"
      :qc-color="qcColor"
      :variance-type="varianceType"
      class="pa-3 pa-sm-4"
      @open-line="openReceiveQcModal"
    />


    <GrnReceiveQcModal
      v-model="isReceiveQcModalOpen"
      :line="activeLine"
      :line-index="activeLineIndex"
      :locations="locations"
      :zones="zones"
      :is-locked="isLocked"
      @update-field="handleModalFieldUpdate"
      @qty-received="v => onQtyReceivedChange(activeLineIndex, v)"
      @qty-accepted="v => onQtyAcceptedChange(activeLineIndex, v)"
      @qty-rejected="v => onQtyRejectedChange(activeLineIndex, v)"
      @zone-change="v => onZoneChange(activeLineIndex, v)"
      @remove-line="handleModalRemoveLine"
    />

    <GrnBatchReceiveModal
      v-model="isBatchReceiveModalOpen"
      :lines="lines"
      :locations="locations"
      :zones="zones"
      :is-locked="isLocked"
      @apply="applyBatchReceive"
    />
  </div>
</template>

<style lang="scss" scoped>
/* Line card styles live in GrnLineCardsList.vue */
</style>
