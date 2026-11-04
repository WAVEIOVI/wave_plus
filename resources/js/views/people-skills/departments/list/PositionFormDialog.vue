<script setup>
/* eslint-disable camelcase */
import { MultiLangField, WpFormDialog } from '@/components/shared'
import { ensureTranslatable } from '@/composables/useLocaleField'
import { reactive, watch } from 'vue'
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

const formState = reactive({
  title: ensureTranslatable(),
  code: '',
  department_id: null,
  description: ensureTranslatable(),
  standard_hourly_rate: null,
  requirements: ensureTranslatable(),
  status: 'active',
})

const close = () => {
  if (!props.saving)
    emit('update:modelValue', false)
}

const submit = () => {
  emit('submit', Object.fromEntries(
    Object.entries(formState).map(([key, value]) => [
      key,
      value && typeof value === 'object' ? { ...value } : value,
    ]),
  ))
}

watch(() => props.modelValue, isOpen => {
  if (isOpen) {
    Object.assign(formState, {
      title: ensureTranslatable(props.form?.title),
      code: props.form?.code || '',
      department_id: props.form?.department_id || null,
      description: ensureTranslatable(props.form?.description),
      standard_hourly_rate: props.form?.standard_hourly_rate ?? null,
      requirements: ensureTranslatable(props.form?.requirements),
      status: props.form?.status || 'active',
    })
  }
}, { immediate: true })
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="itemId ? $t('positions.edit') : $t('positions.add')"
    :subtitle="$t('positions.form.subtitle')"
    icon="tabler-briefcase"
    :loading="saving"
    :submit-label="$t(itemId ? 'positions.form.save' : 'positions.form.create')"
    size="medium"
    @update:model-value="emit('update:modelValue', $event)"
    @submit="submit"
    @cancel="close"
  >
    <VSkeletonLoader
      v-if="loading"
      type="article, paragraph, paragraph"
    />
    <div
      v-else
      class="form-sections"
    >
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
            <MultiLangField
              v-model="formState.title"
              :label="$t('positions.form.title')"
              :rules="[rules.required]"
              required
              :autofocus="!itemId && smAndUp"
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
          <VCol cols="12">
            <MultiLangField
              v-model="formState.description"
              :label="$t('positions.form.description')"
              multiline
            />
          </VCol>
        </VRow>
      </section>

      <VDivider />

      <!-- Assignment & Compensation -->
      <section>
        <div class="form-section__header">
          <VIcon
            icon="tabler-building-community"
            size="18"
            class="form-section__icon"
          />
          <span class="form-section__title">{{ $t('positions.form.sections.department') }}</span>
        </div>
        <VRow>
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
            <AppTextField
              v-model.number="formState.standard_hourly_rate"
              :label="$t('positions.form.rate')"
              variant="outlined"
              type="number"
              inputmode="decimal"
              step="0.01"
              min="0"
              prepend-inner-icon="tabler-currency-euro"
              clearable
            />
          </VCol>
          <VCol cols="12">
            <MultiLangField
              v-model="formState.requirements"
              :label="$t('positions.form.requirements')"
              multiline
            />
          </VCol>
        </VRow>
      </section>

      <VDivider />

      <!-- Status -->
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
              :label="$t('positions.form.status')"
              :items="statusOptions"
              variant="outlined"
            />
          </VCol>
        </VRow>
      </section>
    </div>
  </WpFormDialog>
</template>

<style lang="scss" scoped>
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
