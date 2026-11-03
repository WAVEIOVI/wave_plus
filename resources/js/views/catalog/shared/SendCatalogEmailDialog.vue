<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  entityType: {
    type: String,
    default: 'Item',
  },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'submit',
])

const emailTo = ref([])
const emailCc = ref([])
const subject = ref('New Document from WAVE PLUS')
const message = ref('Dear Sir/Madam,\n\nPlease find attached the requested document.\n\nBest regards,\nWAVE PLUS')

// Reset form when dialog opens
watch(() => props.isDialogVisible, isVisible => {
  if (isVisible) {
    emailTo.value = []
    emailCc.value = []
    subject.value = `New ${props.entityType} Document from WAVE PLUS`
    message.value = 'Dear Sir/Madam,\n\nPlease find attached the requested document.\n\nBest regards,\nWAVE PLUS'
  }
})

const onSubmit = () => {
  // Map values to array of strings
  const finalEmailTo = emailTo.value.map(item => typeof item === 'object' && item?.email ? item.email : item).filter(Boolean)
  const finalEmailCc = emailCc.value.map(item => typeof item === 'object' && item?.email ? item.email : item).filter(Boolean)

  emit('submit', {
    emailTo: finalEmailTo,
    emailCc: finalEmailCc,
    subject: subject.value,
    message: message.value,
  })
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="500"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <!-- 👉 Dialog close btn -->
    <DialogCloseBtn @click="$emit('update:isDialogVisible', false)" />

    <VCard :title="$t('Send Email')">
      <VCardText>
        <VForm @submit.prevent="onSubmit">
          <VRow>
            <VCol cols="12">
              <VCombobox
                id="receiver-email"
                v-model="emailTo"
                hide-no-data
                multiple
                chips
                closable-chips
                :label="$t('To')"
                required
                clearable
              />
            </VCol>

            <VCol cols="12">
              <VCombobox
                id="receiver-email-cc"
                v-model="emailCc"
                hide-no-data
                multiple
                chips
                closable-chips
                :label="$t('Cc (Optional)')"
                clearable
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                id="email-subject"
                v-model="subject"
                :label="$t('Subject')"
                :placeholder="$t('Subject of the email')"
                required
              />
            </VCol>

            <VCol cols="12">
              <AppTextarea
                id="email-message"
                v-model="message"
                rows="8"
                :label="$t('Message')"
                :placeholder="$t('Type your message here...')"
                required
              />
            </VCol>

            <VCol cols="12">
              <div class="mb-6">
                <VChip
                  label
                  color="primary"
                  size="small"
                >
                  <VIcon
                    start
                    icon="tabler-file-type-pdf"
                  />
                  {{ $t('PDF Attached') }}
                </VChip>
              </div>
            </VCol>
          </VRow>

          <div class="d-flex align-center flex-wrap gap-4 mt-2">
            <VBtn
              type="submit"
              :loading="props.loading"
              :disabled="props.loading"
            >
              {{ $t('Send') }}
            </VBtn>

            <VBtn
              color="secondary"
              variant="tonal"
              :disabled="props.loading"
              @click="$emit('update:isDialogVisible', false)"
            >
              {{ $t('Cancel') }}
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
