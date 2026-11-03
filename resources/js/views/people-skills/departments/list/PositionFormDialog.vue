<script setup>
import { useLocaleField } from '@/composables/useLocaleField'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { nextTick, reactive, ref, watch } from 'vue'
import { useDisplay } from 'vuetify'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  itemId: { type: [String, Number], default: null },
  form: { type: Object, required: true },
  departmentOptions: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
  rules: { type: Object, required: true },
  loading: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'submit'])
const { smAndUp } = useDisplay()
const formId = 'position-form-dialog'
const formEl = ref(null)
const formState = reactive({})
const formRef = ref(formState)
const titleField = useLocaleField(formRef, 'title')
const descriptionField = useLocaleField(formRef, 'description')
const requirementsField = useLocaleField(formRef, 'requirements')

const close = () => {
  if (!props.saving)
    emit('update:modelValue', false)
}

const submit = async () => {
  const result = await formEl.value?.validate?.()
  if (result?.valid === false)
    return

  emit('submit', Object.fromEntries(
    Object.entries(formState).map(([key, value]) => [
      key,
      Array.isArray(value) ? [...value] : value && typeof value === 'object' ? { ...value } : value,
    ]),
  ))
}

watch(() => props.modelValue, async isOpen => {
  if (isOpen) {
    Object.assign(formState, Object.fromEntries(
      Object.entries(props.form).map(([key, value]) => [
        key,
        Array.isArray(value) ? [...value] : value && typeof value === 'object' ? { ...value } : value,
      ]),
    ))
  }
  await nextTick()
  formEl.value?.resetValidation?.()
}, { immediate: true })
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="600"
    :persistent="saving"
    @update:model-value="value => !value && close()"
  >
    <DialogCloseBtn
      :disabled="saving"
      @click="close"
    />
    <VCard class="dialog-card d-flex flex-column">
      <!-- Header -->
      <VCardItem class="dialog-header">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            size="44"
          >
            <VIcon
              icon="tabler-id-badge-2"
              size="24"
            />
          </VAvatar>
        </template>
        <VCardTitle>
          {{ itemId ? $t('positions.edit') : $t('positions.add') }}
        </VCardTitle>
        <VCardSubtitle class="text-wrap">
          {{ $t('positions.form.subtitle') }}
        </VCardSubtitle>
      </VCardItem>

      <VDivider />

      <!-- Body -->
      <VCardText class="dialog-body">
        <PerfectScrollbar
          class="dialog-scroll"
          :options="{ wheelPropagation: false }"
        >
          <div class="pa-4 pa-sm-6">
            <VSkeletonLoader
              v-if="loading"
              type="article, paragraph, paragraph"
            />
            <VForm
              v-else
              :id="formId"
              ref="formEl"
              validate-on="blur"
              @submit.prevent="submit"
            >
              <div class="form-sections">
                <!-- General -->
                <section>
                  <div class="form-section__header">
                    <VIcon
                      icon="tabler-id"
                      size="18"
                      class="form-section__icon"
                    />
                    <span class="form-section__title">{{ $t('positions.form.sections.general') }}</span>
                  </div>
                  <VRow>
                    <VCol
                      cols="12"
                      md="8"
                    >
                      <AppTextField
                        v-model="titleField"
                        :label="$t('positions.form.title')"
                        :rules="[rules.required]"
                        :autofocus="!itemId && smAndUp"
                        variant="outlined"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="4"
                    >
                      <AppTextField
                        v-model="formState.code"
                        :label="$t('positions.form.code')"
                        :rules="[rules.required]"
                        autocomplete="off"
                        prepend-inner-icon="tabler-hash"
                        variant="outlined"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <AppSelect
                        v-model="formState.department_id"
                        :label="$t('positions.form.department')"
                        :items="departmentOptions"
                        variant="outlined"
                        clearable
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <AppSelect
                        v-model="formState.status"
                        :label="$t('positions.form.status')"
                        :items="statusOptions"
                        variant="outlined"
                      />
                    </VCol>
                  </VRow>
                </section>

                <VDivider />

                <!-- Role details -->
                <section>
                  <div class="form-section__header">
                    <VIcon
                      icon="tabler-clipboard-text"
                      size="18"
                      class="form-section__icon"
                    />
                    <span class="form-section__title">{{ $t('positions.form.sections.details') }}</span>
                  </div>
                  <VRow>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <AppTextField
                        v-model="formState.standard_hourly_rate"
                        :label="$t('positions.form.standard_hourly_rate')"
                        variant="outlined"
                        type="number"
                        inputmode="decimal"
                        min="0"
                        step="0.01"
                        prepend-inner-icon="tabler-coin"
                        :rules="[rules.nonNegative]"
                      />
                    </VCol>
                    <VCol cols="12">
                      <AppTextarea
                        v-model="descriptionField"
                        :label="$t('positions.form.description')"
                        variant="outlined"
                        rows="3"
                        auto-grow
                        max-rows="6"
                      />
                    </VCol>
                    <VCol cols="12">
                      <AppTextarea
                        v-model="requirementsField"
                        :label="$t('positions.form.requirements')"
                        variant="outlined"
                        rows="3"
                        auto-grow
                        max-rows="8"
                      />
                    </VCol>
                  </VRow>
                </section>
              </div>
            </VForm>
          </div>
        </PerfectScrollbar>
      </VCardText>

      <VDivider />

      <!-- Footer (stays visible while the body scrolls) -->
      <VCardActions class="dialog-footer">
        <VBtn
          type="button"
          variant="tonal"
          color="secondary"
          class="flex-grow-1 flex-sm-grow-0"
          :disabled="saving || loading"
          @click="close"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          type="submit"
          :form="formId"
          color="primary"
          class="flex-grow-1 flex-sm-grow-0"
          :loading="saving"
          :disabled="loading"
        >
          {{ $t(itemId ? 'positions.form.save' : 'positions.form.create') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
// Mobile-first: compact spacing from 360px, roomier from `sm` up.
.dialog-header {
  padding-block: 1rem;
  padding-inline: 1rem 3.5rem; // end padding keeps the title clear of the close button

  @media (min-width: 600px) {
    padding-block: 1.25rem;
    padding-inline: 1.5rem 4rem;
  }
}

.dialog-body {
  flex: 1 1 auto;
  min-block-size: 0;
  overflow: hidden;
  padding: 0;
}

.dialog-card {
  max-block-size: 90vh;
}

.dialog-scroll {
  block-size: 100%;
  max-block-size: 65vh;
}

@media (max-width: 600px) {
  .dialog-card {
    block-size: 100vh;
    max-block-size: 100vh;
  }
}

.dialog-footer {
  justify-content: flex-end;
  gap: 0.75rem;
  padding-block: 0.75rem;
  padding-inline: 1rem;

  @media (min-width: 600px) {
    padding-block: 1rem;
    padding-inline: 1.5rem;
  }

  // Vuetify adds a start margin between adjacent buttons; the gap above replaces it.
  .v-btn + .v-btn {
    margin-inline-start: 0;
  }
}

.form-sections {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.form-section__header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-block-end: 0.75rem;
}

.form-section__icon {
  color: rgb(var(--v-theme-primary));
}

.form-section__title {
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.375rem;
}
</style>
