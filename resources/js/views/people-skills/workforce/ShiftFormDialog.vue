<script setup>
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { nextTick, reactive, ref, watch } from 'vue'
import { useLocaleField } from '@/composables/useLocaleField'
import { useDisplay } from 'vuetify'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  itemId: { type: [String, Number], default: null },
  form: { type: Object, required: true },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'submit'])
const { smAndUp } = useDisplay()
const formId = 'workforce-shift-form'
const formEl = ref(null)
const formState = reactive({})
const formRef = ref(formState)
const nameField = useLocaleField(formRef, 'name')

const requiredRule = value => !!String(value ?? '').trim() || 'This field is required.'
const nonNegativeRule = value => value === null || value === undefined || value === '' || Number(value) >= 0 || 'Value must be zero or greater.'

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
      value && typeof value === 'object' ? { ...value } : value,
    ]),
  ))
}

watch(() => props.modelValue, async isOpen => {
  if (isOpen) {
    Object.assign(formState, Object.fromEntries(
      Object.entries(props.form).map(([key, value]) => [
        key,
        value && typeof value === 'object' ? { ...value } : value,
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
      <VCardItem class="dialog-header">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            size="44"
          >
            <VIcon
              icon="tabler-clock-2"
              size="24"
            />
          </VAvatar>
        </template>
        <VCardTitle>
          {{ itemId ? $t('workforce.edit_shift') : $t('workforce.add_shift') }}
        </VCardTitle>
        <VCardSubtitle class="text-wrap">
          {{ $t('workforce.form.subtitle', 'Configure shift times and scheduling rules.') }}
        </VCardSubtitle>
      </VCardItem>

      <VDivider />

      <VCardText class="dialog-body">
        <PerfectScrollbar
          class="dialog-scroll"
          :options="{ wheelPropagation: false }"
        >
          <div class="pa-4 pa-sm-6">
            <VForm
              :id="formId"
              ref="formEl"
              validate-on="blur"
              @submit.prevent="submit"
            >
              <div class="form-sections">
                <section>
                  <div class="form-section__header">
                    <VIcon
                      icon="tabler-clock"
                      size="18"
                      class="form-section__icon"
                    />
                    <span class="form-section__title">{{ $t('workforce.form.sections.timing', 'Shift details') }}</span>
                  </div>
                  <VRow>
                    <VCol cols="12">
                      <AppTextField
                        v-model="nameField"
                        :label="$t('workforce.form.name')"
                        :rules="[requiredRule]"
                        :autofocus="!itemId && smAndUp"
                        variant="outlined"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <AppTextField
                        v-model="formState.code"
                        :label="$t('workforce.form.code')"
                        placeholder="morning_a"
                        prepend-inner-icon="tabler-hash"
                        variant="outlined"
                        autocomplete="off"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <AppTextField
                        v-model="formState.break_duration_minutes"
                        :label="$t('workforce.form.break_minutes')"
                        type="number"
                        min="0"
                        step="1"
                        prepend-inner-icon="tabler-coffee"
                        :rules="[nonNegativeRule]"
                        variant="outlined"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <AppTextField
                        v-model="formState.start_time"
                        :label="$t('workforce.form.start_time')"
                        type="time"
                        :rules="[requiredRule]"
                        variant="outlined"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <AppTextField
                        v-model="formState.end_time"
                        :label="$t('workforce.form.end_time')"
                        type="time"
                        :rules="[requiredRule]"
                        variant="outlined"
                      />
                    </VCol>
                  </VRow>
                </section>

                <VDivider />

                <section>
                  <div class="form-section__header">
                    <VIcon
                      icon="tabler-settings"
                      size="18"
                      class="form-section__icon"
                    />
                    <span class="form-section__title">{{ $t('workforce.form.sections.settings', 'Settings') }}</span>
                  </div>
                  <VRow>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <VSwitch
                        v-model="formState.is_overnight"
                        :label="$t('workforce.form.overnight', 'Overnight shift')"
                        color="primary"
                        hide-details
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <VSwitch
                        v-model="formState.is_active"
                        :label="$t('workforce.form.active')"
                        color="success"
                        hide-details
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

      <VCardActions class="dialog-footer">
        <VBtn
          type="button"
          variant="tonal"
          color="secondary"
          class="flex-grow-1 flex-sm-grow-0"
          :disabled="saving"
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
        >
          {{ $t('Save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.dialog-header {
  padding-block: 1rem;
  padding-inline: 1rem 3.5rem;

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
