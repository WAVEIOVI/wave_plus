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
  employeeOptions: { type: Array, default: () => [] },
  teamTypeOptions: { type: Array, default: () => [] },
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
  department_id: null,
  team_leader_id: null,
  team_type: 'operational',
  is_active: true,
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
      name: ensureTranslatable(props.form?.name),
      code: props.form?.code || '',
      description: ensureTranslatable(props.form?.description),
      department_id: props.form?.department_id || null,
      team_leader_id: props.form?.team_leader_id || null,
      team_type: props.form?.team_type || 'operational',
      is_active: props.form?.is_active ?? true,
    })
  }
}, { immediate: true })
</script>

<template>
  <WpFormDialog
    :model-value="modelValue"
    :title="itemId ? $t('teams.edit') : $t('teams.add')"
    :subtitle="$t('teams.form.subtitle')"
    icon="tabler-users-group"
    :loading="saving"
    :submit-label="$t(itemId ? 'teams.form.save' : 'teams.form.create')"
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
          <span class="form-section__title">{{ $t('teams.form.sections.identity') }}</span>
        </div>
        <VRow>
          <VCol
            cols="12"
            md="8"
          >
            <MultiLangField
              v-model="formState.name"
              :label="$t('teams.form.name')"
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
              :label="$t('teams.form.code')"
              :rules="[rules.required]"
              autocomplete="off"
              prepend-inner-icon="tabler-hash"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12">
            <MultiLangField
              v-model="formState.description"
              :label="$t('teams.form.description')"
              multiline
            />
          </VCol>
        </VRow>
      </section>

      <VDivider />

      <!-- Organization & Composition -->
      <section>
        <div class="form-section__header">
          <VIcon
            icon="tabler-sitemap"
            size="18"
            class="form-section__icon"
          />
          <span class="form-section__title">{{ $t('teams.form.sections.organization') }}</span>
        </div>
        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="formState.team_type"
              :label="$t('teams.form.type')"
              :items="teamTypeOptions"
              variant="outlined"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="formState.department_id"
              :label="$t('teams.form.department')"
              :items="departmentOptions"
              variant="outlined"
              clearable
            />
          </VCol>
          <VCol cols="12">
            <AppSelect
              v-model="formState.team_leader_id"
              :label="$t('teams.form.leader')"
              :items="employeeOptions"
              variant="outlined"
              clearable
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
          <VCol cols="12">
            <VSwitch
              v-model="formState.is_active"
              :label="formState.is_active ? $t('Active') : $t('Inactive')"
              color="primary"
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
