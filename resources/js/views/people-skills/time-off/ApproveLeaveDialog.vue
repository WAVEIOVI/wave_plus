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

const decisionNote = ref('')

const isOpen = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

watch(isOpen, val => {
  if (val) {
    decisionNote.value = ''
  }
})

const handleConfirm = () => {
  emit('confirm', decisionNote.value.trim() || null)
}
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="480"
    persistent
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pa-4">
        <VAvatar
          color="success"
          variant="tonal"
          size="36"
        >
          <VIcon
            icon="tabler-circle-check"
            size="20"
          />
        </VAvatar>
        <span class="text-h6 font-weight-bold">{{ $t('time_off.approve_title', 'Approve Leave Request') }}</span>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-4">
        <p class="text-body-2 text-medium-emphasis mb-3">
          {{ $t('time_off.approve_confirm_text', 'Are you sure you want to approve this leave request? The reserved days will be deducted and shift coverage will be synchronized.') }}
        </p>

        <AppTextarea
          v-model="decisionNote"
          :label="$t('time_off.view.decision_note', 'Decision Note (Optional)')"
          :placeholder="$t('time_off.approve_note_placeholder', 'e.g. Approved, coverage assigned to team...')"
          rows="2"
          autofocus
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
          color="success"
          :loading="loading"
          @click="handleConfirm"
        >
          {{ $t('time_off.approve') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
