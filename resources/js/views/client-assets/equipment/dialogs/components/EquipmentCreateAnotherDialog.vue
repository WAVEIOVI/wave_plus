<script setup>
import { WpConfirmDialog } from '@/components/shared'
import { useI18n } from 'vue-i18n'

defineProps({
  modelValue: { type: Boolean, default: false },
  createdItemLabel: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'confirm', 'done'])

const { t } = useI18n()
</script>

<template>
  <WpConfirmDialog
    :model-value="modelValue"
    variant="info"
    :title="t('Equipment Created Successfully!')"
    :confirm-label="t('Yes, Add Another')"
    :cancel-label="t('Done, Close')"
    :max-width="480"
    @update:model-value="emit('update:modelValue', $event)"
    @confirm="emit('confirm')"
    @cancel="emit('done')"
  >
    <div class="d-flex flex-column align-center">
      <VChip
        v-if="createdItemLabel"
        size="small"
        color="success"
        variant="tonal"
        class="mb-3 font-weight-bold"
      >
        {{ createdItemLabel }}
      </VChip>
      <span class="text-body-2 text-medium-emphasis">
        {{ $t('Would you like to add another piece of equipment to this site and area?') }}
      </span>
    </div>
  </WpConfirmDialog>
</template>
