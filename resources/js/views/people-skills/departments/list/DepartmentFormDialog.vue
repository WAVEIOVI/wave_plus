<script setup>
import { useLocaleField } from '@/composables/useLocaleField'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { nextTick, reactive, ref, watch } from 'vue'
import { useDisplay } from 'vuetify'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  itemId: { type: [String, Number], default: null },
  form: { type: Object, required: true },
  parentOptions: { type: Array, default: () => [] },
  managerOptions: { type: Array, default: () => [] },
  positionOptions: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
  rules: { type: Object, required: true },
  loading: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'submit'])
const { smAndUp } = useDisplay()
const formId = 'department-form-dialog'
const formEl = ref(null)
const formState = reactive({})
const formRef = ref(formState)
const nameField = useLocaleField(formRef, 'name')
const descriptionField = useLocaleField(formRef, 'description')

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
              icon="tabler-building-community"
              size="24"
            />
          </VAvatar>
        </template>
        <VCardTitle>
          {{ itemId ? $t('departments.edit') : $t('departments.add') }}
        </VCardTitle>
        <VCardSubtitle class="text-wrap">
          {{ $t('departments.form.subtitle') }}
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
                <!-- Identity -->
                <section>
                  <div class="form-section__header">
                    <VIcon
                      icon="tabler-id"
                      size="18"
                      class="form-section__icon"
                    />
                    <span class="form-section__title">{{ $t('departments.form.sections.identity') }}</span>
                  </div>
                  <VRow>
                    <VCol
                      cols="12"
                      md="8"
                    >
                      <AppTextField
                        v-model="nameField"
                        :label="$t('departments.form.name')"
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
                        :label="$t('departments.form.code')"
                        :rules="[rules.required]"
                        autocomplete="off"
                        prepend-inner-icon="tabler-hash"
                        variant="outlined"
                      />
                    </VCol>
                    <VCol cols="12">
                      <AppTextarea
                        v-model="descriptionField"
                        :label="$t('departments.form.description')"
                        variant="outlined"
                        rows="3"
                        auto-grow
                        max-rows="6"
                      />
                    </VCol>
                  </VRow>
                </section>

                <VDivider />

                <!-- Organization -->
                <section>
                  <div class="form-section__header">
                    <VIcon
                      icon="tabler-sitemap"
                      size="18"
                      class="form-section__icon"
                    />
                    <span class="form-section__title">{{ $t('departments.form.sections.organization') }}</span>
                  </div>
                  <VRow>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <AppSelect
                        v-model="formState.parent_department_id"
                        :label="$t('departments.form.parent')"
                        :items="parentOptions"
                        variant="outlined"
                        clearable
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <AppSelect
                        v-model="formState.manager_id"
                        :label="$t('departments.form.manager')"
                        :items="managerOptions"
                        variant="outlined"
                        clearable
                      />
                    </VCol>
                    <VCol cols="12">
                      <AppSelect
                        v-model="formState.position_ids"
                        :label="$t('departments.form.positions')"
                        :items="positionOptions"
                        variant="outlined"
                        multiple
                        chips
                        closable-chips
                        clearable
                      />
                    </VCol>
                  </VRow>
                </section>

                <VDivider />

                <!-- Settings -->
                <section>
                  <div class="form-section__header">
                    <VIcon
                      icon="tabler-settings"
                      size="18"
                      class="form-section__icon"
                    />
                    <span class="form-section__title">{{ $t('departments.form.sections.settings') }}</span>
                  </div>
                  <VRow>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <AppSelect
                        v-model="formState.status"
                        :label="$t('departments.form.status')"
                        :items="statusOptions"
                        variant="outlined"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <AppTextField
                        v-model.number="formState.sort_order"
                        :label="$t('departments.form.sort_order')"
                        variant="outlined"
                        type="number"
                        inputmode="numeric"
                        min="0"
                        :rules="[rules.nonNegative]"
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
          {{ $t(itemId ? 'departments.form.save' : 'departments.form.create') }}
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
