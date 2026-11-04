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

const formState = reactive({
  name: ensureTranslatable(),
  code: '',
  description: ensureTranslatable(),
  parent_department_id: null,
  manager_id: null,
  status: 'active',
  sort_order: 0,
  position_ids: [],
})

const close = () => {
  if (!props.saving)
    emit('update:modelValue', false)
}

const submit = () => {
  emit('submit', Object.fromEntries(
    Object.entries(formState).map(([key, value]) => [
      key,
      Array.isArray(value) ? [...value] : value && typeof value === 'object' ? { ...value } : value,
    ]),
  ))
}

watch(() => props.modelValue, isOpen => {
  if (isOpen) {
    Object.assign(formState, {
      name: ensureTranslatable(props.form?.name),
      code: props.form?.code || '',
      description: ensureTranslatable(props.form?.description),
      parent_department_id: props.form?.parent_department_id || null,
      manager_id: props.form?.manager_id || null,
      status: props.form?.status || 'active',
      sort_order: props.form?.sort_order ?? 0,
      position_ids: Array.isArray(props.form?.position_ids) ? [...props.form.position_ids] : [],
    })
  }
}, { immediate: true })
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="itemId ? $t('departments.edit') : $t('departments.add')"
    :subtitle="$t('departments.form.subtitle')"
    icon="tabler-building-community"
    :loading="saving"
    :submit-label="$t(itemId ? 'departments.form.save' : 'departments.form.create')"
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
            <MultiLangField
              v-model="formState.name"
              :label="$t('departments.form.name')"
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
              :label="$t('departments.form.code')"
              :rules="[rules.required]"
              autocomplete="off"
              prepend-inner-icon="tabler-hash"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12">
            <MultiLangField
              v-model="formState.description"
              :label="$t('departments.form.description')"
              multiline
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
