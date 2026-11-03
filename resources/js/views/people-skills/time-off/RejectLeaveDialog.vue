<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'confirm'])

const { t } = useI18n()

const reason = ref('')
const error = ref('')

const isOpen = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

watch(isOpen, val => {
  if (val) {
    reason.value = ''
    error.value = ''
  }
})

const handleConfirm = () => {
  if (!reason.value.trim()) {
    error.value = t('time_off.reject_reason_required')

    return
  }
  emit('confirm', reason.value.trim())
}
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="460"
    persistent
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pa-4">
        <VAvatar
          color="error"
          variant="tonal"
          size="36"
        >
          <VIcon
            icon="tabler-alert-triangle"
            size="20"
          />
        </VAvatar>
        <span class="text-h6 font-weight-bold">{{ $t('time_off.reject_title') }}</span>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-4">
        <AppTextarea
          v-model="reason"
          :label="$t('time_off.reject_reason')"
          :placeholder="$t('time_off.reject_reason')"
          :error-messages="error"
          rows="3"
          autofocus
          @update:model-value="error = ''"
        />
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 justify-end gap-3">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="loading"
          @click="isOpen = false"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          color="error"
          :loading="loading"
          @click="handleConfirm"
        >
          {{ $t('time_off.reject') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
