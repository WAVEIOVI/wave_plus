<script setup>
import { documentCategories } from '@/composables/client-assets/sites/useSiteConstants'
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'upload'])

const docFile = ref(null)
const docTitle = ref('')
const docCategory = ref('general')
const docNotes = ref('')

watch(() => props.modelValue, val => {
  if (val) {
    docFile.value = null
    docTitle.value = ''
    docCategory.value = 'general'
    docNotes.value = ''
  }
})

const handleUpload = () => {
  emit('upload', {
    file: docFile.value,
    title: docTitle.value,
    category: docCategory.value,
    notes: docNotes.value,
  })
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="560"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <VCard class="pa-2">
      <VCardTitle class="d-flex align-center gap-2">
        <VAvatar
          color="primary"
          variant="tonal"
          size="36"
          rounded
        >
          <VIcon
            icon="tabler-file-upload"
            size="20"
          />
        </VAvatar>
        <span class="text-h6 font-weight-bold">{{ $t('Upload Site Document / Plan') }}</span>
      </VCardTitle>

      <VCardText class="pt-3">
        <VRow>
          <VCol cols="12">
            <AppSelect
              v-model="docCategory"
              :items="documentCategories"
              item-title="title"
              item-value="value"
              :label="$t('Document Category') + ' *'"
            />
          </VCol>

          <VCol cols="12">
            <AppTextField
              v-model="docTitle"
              :label="$t('Document Title / Display Name')"
              :placeholder="$t('e.g. Sousse Plant Level 1 Fire Evacuation Map')"
            />
          </VCol>

          <VCol cols="12">
            <VFileInput
              v-model="docFile"
              :label="$t('Select File (PDF, PNG, JPG, CAD, DOCX)') + ' *'"
              prepend-icon="tabler-paperclip"
              variant="outlined"
              density="compact"
              show-size
            />
          </VCol>

          <VCol cols="12">
            <AppTextarea
              v-model="docNotes"
              rows="3"
              :label="$t('Internal Notes / Version')"
              :placeholder="$t('e.g. Approved by Civil Protection Directorate Q3 2026')"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="justify-end gap-2">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="loading"
          @click="$emit('update:modelValue', false)"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="loading"
          :disabled="!docFile || loading"
          @click="handleUpload"
        >
          {{ $t('Upload Document') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
