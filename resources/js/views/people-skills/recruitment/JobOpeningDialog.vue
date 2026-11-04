<script setup>
/* eslint-disable camelcase */
import { localizedValue } from '@/composables/useLocaleField'
import { useHrStore } from '@/stores/hrStore'
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  opening: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'submit'])

const { t } = useI18n()
const hrStore = useHrStore()

const isEditing = computed(() => Boolean(props.opening?.id))

const form = reactive({
  title: '',
  department_id: null,
  position_id: null,
  openings_count: 1,
  status: 'draft',
  description: '',
  requirements: '',
})

const statusOptions = computed(() => [
  { title: t('common.draft', 'Draft'), value: 'draft' },
  { title: t('common.published', 'Published'), value: 'published' },
  { title: t('common.closed', 'Closed'), value: 'closed' },
  { title: t('common.cancelled', 'Cancelled'), value: 'cancelled' },
])

const departmentOptions = computed(() =>
  (hrStore.departments || []).map(d => ({
    title: localizedValue(d.name),
    value: d.id,
  })),
)

const positionOptions = computed(() =>
  (hrStore.positions || [])
    .filter(p => !form.department_id || p.department_id === form.department_id)
    .map(p => ({
      title: localizedValue(p.title ?? p.name),
      value: p.id,
    })),
)

const resetForm = () => {
  if (props.opening) {
    form.title = props.opening.title || ''
    form.department_id = props.opening.department_id || null
    form.position_id = props.opening.position_id || null
    form.openings_count = props.opening.openings_count || 1
    form.status = props.opening.status || 'draft'
    form.description = props.opening.description || ''
    form.requirements = props.opening.requirements || ''
  } else {
    form.title = ''
    form.department_id = null
    form.position_id = null
    form.openings_count = 1
    form.status = 'draft'
    form.description = ''
    form.requirements = ''
  }
}

watch(() => props.isDialogVisible, val => {
  if (val) {
    resetForm()
  }
})

watch(() => props.opening, () => {
  if (props.isDialogVisible) {
    resetForm()
  }
})

const handleSubmit = () => {
  emit('submit', { ...form })
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="640"
    persistent
    @update:model-value="emit('update:isDialogVisible', $event)"
  >
    <VCard>
      <VCardItem class="pb-2">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            class="me-3"
          >
            <VIcon :icon="isEditing ? 'tabler-edit' : 'tabler-briefcase'" />
          </VAvatar>
        </template>
        <VCardTitle>
          {{ isEditing ? t('recruitment.edit_opening', 'Edit Vacancy') : t('recruitment.add_opening', 'Add Vacancy') }}
        </VCardTitle>
        <VCardSubtitle>
          {{ isEditing ? t('recruitment.edit_opening_desc', 'Update job vacancy requirements and publication status.') : t('recruitment.add_opening_desc', 'Create a new job vacancy for candidate applications.') }}
        </VCardSubtitle>
      </VCardItem>

      <VCardText class="pt-4">
        <VRow>
          <VCol cols="12">
            <AppTextField
              v-model="form.title"
              :label="t('recruitment.job_title', 'Job Title')"
              placeholder="e.g. Senior Fire Protection Engineer"
              required
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="form.department_id"
              :label="t('common.department', 'Department')"
              :items="departmentOptions"
              clearable
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="form.position_id"
              :label="t('common.position', 'Position')"
              :items="positionOptions"
              clearable
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <AppTextField
              v-model.number="form.openings_count"
              :label="t('recruitment.openings_count', 'Openings Count')"
              type="number"
              min="1"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="form.status"
              :label="t('common.status', 'Status')"
              :items="statusOptions"
            />
          </VCol>

          <VCol cols="12">
            <AppTextarea
              v-model="form.description"
              :label="t('common.description', 'Role Description')"
              rows="3"
            />
          </VCol>

          <VCol cols="12">
            <AppTextarea
              v-model="form.requirements"
              :label="t('recruitment.requirements', 'Requirements & Certifications')"
              rows="3"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="justify-end px-6 pb-6">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:isDialogVisible', false)"
        >
          {{ t('common.cancel', 'Cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="props.loading"
          :disabled="!form.title"
          @click="handleSubmit"
        >
          {{ isEditing ? t('common.save_changes', 'Save Changes') : t('recruitment.add_opening', 'Create Vacancy') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
