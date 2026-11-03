<script setup>
import { WpQrScannerDialog } from '@/components/shared'
import { extractEquipmentQrCode } from '@/composables/scanner/useQrScanner'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'scanned'])
const { t } = useI18n()

const isOpen = computed({
  get: () => props.isDialogVisible,
  set: val => emit('update:isDialogVisible', val),
})

const handleScanned = result => {
  const code = extractEquipmentQrCode(result)

  if (code) {
    emit('scanned', code)
    isOpen.value = false
  }
}
</script>

<template>
  <WpQrScannerDialog
    v-model="isOpen"
    :title="t('Scan Equipment QR Code')"
    :subtitle="t('Point camera at asset QR code or enter barcode')"
    @scanned="handleScanned"
    @close="isOpen = false"
  />
</template>

