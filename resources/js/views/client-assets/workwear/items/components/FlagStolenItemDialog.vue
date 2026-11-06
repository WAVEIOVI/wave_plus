<script setup>
/* eslint-disable vue/no-mutating-props */
import { WpFormDialog } from '@/components/shared'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  form: { type: Object, required: true },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const { t } = useI18n()

const visible = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})
</script>

<template>
  <WpFormDialog
    v-model="visible"
    :title="$t('Flag as Stolen')"
    :subtitle="$t('This will flag the item as stolen and update its inventory tracking status.')"
    icon="tabler-alert-octagon"
    icon-color="error"
    size="small"
    :loading="loading"
    submit-color="error"
    :submit-label="$t('Flag Stolen')"
    @submit="emit('submit')"
    @cancel="visible = false"
  >
    <VAlert
      type="error"
      variant="tonal"
      class="mb-4"
    >
      {{ $t('Flagging this garment will record an incident and withdraw it from available inventory.') }}
    </VAlert>

    <AppTextarea
      v-model="form.stolen_notes"
      :label="$t('Incident / Stolen Notes')"
      :placeholder="$t('Describe circumstances, date noted, or police report info...')"
      rows="3"
      auto-grow
      variant="outlined"
      autofocus
    />
  </WpFormDialog>
</template>
