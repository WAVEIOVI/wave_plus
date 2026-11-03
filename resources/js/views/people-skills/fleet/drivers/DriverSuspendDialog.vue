<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  suspension: { type: Object, required: true },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'update:field', 'submit'])
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="500"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <DialogCloseBtn
      :disabled="loading"
      @click="emit('update:modelValue', false)"
    />
    <VCard :title="$t('hr.fleet.suspend_driver')">
      <VCardText>
        <AppTextField
          :model-value="suspension.suspended_until"
          label="Suspended until"
          type="date"
          @update:model-value="emit('update:field', 'suspended_until', $event)"
        />
        <AppTextField
          :model-value="suspension.suspension_reason"
          class="mt-3"
          label="Reason"
          @update:model-value="emit('update:field', 'suspension_reason', $event)"
        />
      </VCardText>
      <VCardActions class="justify-end pa-4 gap-2">
        <VBtn
          variant="tonal"
          :disabled="loading"
          @click="emit('update:modelValue', false)"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          color="warning"
          :loading="loading"
          @click="emit('submit')"
        >
          Suspend
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
