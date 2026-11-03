<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: { type: Boolean, default: false },
  action: { type: String, default: 'activate' },  // activate | suspend | terminate
  loading: { type: Boolean, default: false },
  initialEffectiveDate: { type: String, default: null },
})

const emit = defineEmits(['update:isDialogVisible', 'changeStatus'])

const { t } = useI18n()

const reason        = ref('')
const effectiveDate = ref(null)

const localDate = () => {
  const date = new Date()

  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

watch(() => props.isDialogVisible, open => {
  if (open) {
    reason.value        = ''
    effectiveDate.value = props.initialEffectiveDate || (props.action === 'terminate'
      ? localDate()
      : null)
  }
})

const config = computed(() => {
  const map = {
    activate: { icon: 'tabler-user-check', color: 'success', titleKey: 'employees.status.activate_title' },
    'on_leave': { icon: 'tabler-calendar-pause', color: 'info', titleKey: 'hr.employees.status.on_leave' },
    suspend: { icon: 'tabler-user-pause', color: 'warning', titleKey: 'employees.status.suspend_title' },
    terminate: { icon: 'tabler-user-x',     color: 'error',   titleKey: 'employees.status.terminate_title' },
  }

  return map[props.action] || map.activate
})

const handleConfirm = () => {
  emit('changeStatus', {
    status: props.action === 'activate'
      ? 'active'
      : props.action === 'suspend'
        ? 'suspended'
        : props.action === 'terminate'
          ? 'terminated'
          : 'on_leave',
    reason: reason.value || null,
    effectiveDate: effectiveDate.value || null,
  })
}
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="480"
    @update:model-value="$emit('update:isDialogVisible', $event)"
  >
    <VCard>
      <VCardText class="d-flex flex-column align-center pa-6 text-center gap-4">
        <VAvatar
          size="64"
          :color="config.color"
          variant="tonal"
        >
          <VIcon
            :icon="config.icon"
            size="28"
          />
        </VAvatar>

        <div class="text-h6 font-weight-bold">
          {{ $t(config.titleKey) }}
        </div>
      </VCardText>

      <VCardText class="pt-0">
        <VRow>
          <VCol
            v-if="action === 'terminate'"
            cols="12"
          >
            <AppDateTimePicker
              v-model="effectiveDate"
              :label="$t('employees.status.termination_date')"
              :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y' }"
            />
          </VCol>

          <VCol
            v-if="action !== 'activate'"
            cols="12"
          >
            <AppTextField
              v-model="reason"
              :label="$t('employees.status.reason')"
              :placeholder="$t('employees.status.reason_ph')"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="pa-4 gap-3 justify-center">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="loading"
          @click="$emit('update:isDialogVisible', false)"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          :color="config.color"
          :loading="loading"
          :prepend-icon="config.icon"
          @click="handleConfirm"
        >
          {{ $t('Confirm') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
