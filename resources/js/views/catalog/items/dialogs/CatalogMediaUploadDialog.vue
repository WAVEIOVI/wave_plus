<script setup>
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { VDateInput } from 'vuetify/labs/VDateInput'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  title: { type: String, default: 'Upload File' },
})

const emit = defineEmits(['update:isDialogVisible', 'submit'])

const isFormValid = ref(false)
const refForm = ref()
const localData = ref({})
const selectedFile = ref(null)



const resetForm = () => {
  refForm.value?.resetValidation()
  localData.value = {}
  selectedFile.value = null
}

const onSubmit = () => {
  refForm.value?.validate().then(({ valid }) => {
    if (valid) {
      if (!selectedFile.value) {
        // Optional validation if you want to require a file
      }

      const payload = { ...localData.value, _file: selectedFile.value }

      emit('submit', payload)
      emit('update:isDialogVisible', false)
      resetForm()
    }
  })
}

const closeDialog = () => {
  emit('update:isDialogVisible', false)
  resetForm()
}
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="600"
    scrollable
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard :title="title">
      <VCardText class="pa-0">
        <PerfectScrollbar
          :options="{ wheelPropagation: false }"
          style="max-block-size: 65vh;"
        >
          <div class="pa-6">
            <VForm
              ref="refForm"
              v-model="isFormValid"
              @submit.prevent="onSubmit"
            >
              <VRow>
                <VCol cols="12">
                  <VFileInput
                    v-model="selectedFile"
                    :rules="[requiredValidator]"
                    :label="$t('Attach File *')"
                    prepend-icon="tabler-upload"
                    show-size
                  />
                </VCol>

                <!-- Metadata Options -->
                <VCol cols="12">
                  <VDivider class="my-2" />
                  <span class="text-caption text-disabled text-uppercase">{{ $t('Optional Metadata') }}</span>
                </VCol>

                <VCol cols="12">
                  <AppTextField
                    v-model="localData.title"
                    :label="$t('Title / Display Name')"
                  />
                </VCol>
                
                <VCol
                  cols="12"
                  class="d-flex gap-4 justify-end mt-4"
                >
                  <VBtn
                    variant="tonal"
                    color="secondary"
                    @click="closeDialog"
                  >
                    {{ $t('Cancel') }}
                  </VBtn>
                  <VBtn
                    type="submit"
                    :disabled="!selectedFile"
                  >
                    {{ $t('Upload') }}
                  </VBtn>
                </VCol>
              </VRow>
            </VForm>
          </div>
        </PerfectScrollbar>
      </VCardText>
    </VCard>
  </VDialog>
</template>
