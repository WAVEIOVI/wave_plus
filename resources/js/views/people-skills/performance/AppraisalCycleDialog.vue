<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  cycle: {
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

const form = ref({
  name: '',
  cycle_type: 'annual',
  start_date: '',
  end_date: '',
  status: 'draft',
})

const cycleTypeOptions = computed(() => [
  { title: t('appraisals.annual', 'Annual Review'), value: 'annual' },
  { title: t('appraisals.quarterly', 'Quarterly Review'), value: 'quarterly' },
  { title: t('appraisals.probation', 'Probation Review'), value: 'probation' },
  { title: t('appraisals.project_based', 'Project Based'), value: 'project' },
])

const statusOptions = computed(() => [
  { title: t('appraisals.draft', 'Draft'), value: 'draft' },
  { title: t('appraisals.active', 'Active'), value: 'active' },
  { title: t('appraisals.in_review', 'In Review'), value: 'review' },
  { title: t('appraisals.completed', 'Completed'), value: 'completed' },
])

watch(() => props.isDialogVisible, val => {
  if (val) {
    if (props.cycle) {
      form.value = {
        name: props.cycle.name || '',
        cycle_type: props.cycle.cycle_type || 'annual',
        start_date: props.cycle.start_date || '',
        end_date: props.cycle.end_date || '',
        status: props.cycle.status || 'draft',
      }
    } else {
      form.value = {
        name: '',
        cycle_type: 'annual',
        start_date: '',
        end_date: '',
        status: 'draft',
      }
    }
  }
})

const handleSubmit = () => {
  emit('submit', { ...form.value })
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="560"
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
            <VIcon icon="tabler-calendar-event" />
          </VAvatar>
        </template>
        <VCardTitle>
          {{ props.cycle ? t('appraisals.edit_cycle', 'Edit Appraisal Cycle') : t('appraisals.create_cycle', 'New Appraisal Cycle') }}
        </VCardTitle>
        <VCardSubtitle>
          {{ t('appraisals.cycle_desc', 'Define performance review period and timeline.') }}
        </VCardSubtitle>
      </VCardItem>

      <VCardText class="pt-4">
        <VRow>
          <VCol cols="12">
            <AppTextField
              v-model="form.name"
              :label="t('appraisals.cycle_name', 'Cycle Name')"
              placeholder="e.g. FY 2026 Annual Performance Review"
              required
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppSelect
              v-model="form.cycle_type"
              :label="t('appraisals.cycle_type', 'Cycle Type')"
              :items="cycleTypeOptions"
              required
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppSelect
              v-model="form.status"
              :label="t('common.status', 'Status')"
              :items="statusOptions"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppDateTimePicker
              v-model="form.start_date"
              :label="t('common.start_date', 'Start Date')"
              required
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppDateTimePicker
              v-model="form.end_date"
              :label="t('common.end_date', 'End Date')"
              required
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
          :disabled="!form.name || !form.start_date || !form.end_date"
          @click="handleSubmit"
        >
          {{ props.cycle ? t('common.save', 'Save Changes') : t('common.create', 'Create Cycle') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
