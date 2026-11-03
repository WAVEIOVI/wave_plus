<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  isOpen: { type: Boolean, default: undefined },
  title: { type: String, default: '' },
  description: { type: String, default: '' },
  message: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  confirmAction: { type: Function, default: null },
})

const emit = defineEmits(['update:modelValue', 'update:isOpen', 'confirm', 'cancel'])

const { t } = useI18n()
const confirming = ref(false)

const dialogOpen = computed({
  get: () => (props.isOpen !== undefined ? props.isOpen : props.modelValue),
  set: val => {
    emit('update:modelValue', val)
    emit('update:isOpen', val)
  },
})

const displayTitle = computed(() => props.title || t('Confirm Delete'))
const displayDescription = computed(() => props.description || props.message || t('Are you sure you want to delete this item?'))

const handleClose = () => {
  if (props.loading || confirming.value)
    return
  dialogOpen.value = false
  emit('cancel')
}

const confirmDelete = async () => {
  if (props.loading || confirming.value)
    return

  if (props.confirmAction) {
    confirming.value = true
    try {
      const deleted = await props.confirmAction()
      if (deleted !== false)
        dialogOpen.value = false
    }
    finally {
      confirming.value = false
    }
  }
  else {
    emit('confirm')
  }
}
</script>

<template>
  <VDialog
    v-model="dialogOpen"
    max-width="450"
  >
    <VCard class="text-center pa-6">
      <VCardText class="d-flex flex-column align-center pa-0">
        <VAvatar
          color="error"
          variant="tonal"
          size="64"
          class="mb-4"
        >
          <VIcon
            icon="tabler-trash"
            size="32"
          />
        </VAvatar>

        <h5 class="text-h6 font-weight-semibold mb-2">
          {{ displayTitle }}
        </h5>
        <p class="text-body-2 text-medium-emphasis mb-6">
          {{ displayDescription }}
        </p>

        <div class="d-flex justify-center gap-3 w-100">
          <VBtn
            variant="outlined"
            color="secondary"
            :disabled="loading || confirming"
            @click="handleClose"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            color="error"
            :loading="loading || confirming"
            prepend-icon="tabler-trash"
            @click="confirmDelete"
          >
            {{ $t('Delete') }}
          </VBtn>
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>
