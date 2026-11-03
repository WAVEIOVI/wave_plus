<script setup>
import MultiLangField from '@/components/shared/data/MultiLangField.vue'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { useHrStore } from '@/stores/hrStore'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  holiday: {
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

const form = ref({
  name: ensureTranslatable(),
  date: '',
  end_date: '',
  year: new Date().getFullYear(),
  is_recurring: false,
  is_half_day: false,
  applies_to_all: true,
  department_id: null,
  country_code: '',
  notes: '',
  is_active: true,
})

const departmentOptions = computed(() => [
  { title: t('common.all_departments', 'All Departments'), value: null },
  ...hrStore.departments.map(d => ({ title: localizedValue(d.name), value: d.id })),
])

watch(() => props.isDialogVisible, val => {
  if (val) {
    if (props.holiday) {
      form.value = {
        name: ensureTranslatable(props.holiday.name),
        date: props.holiday.date || '',
        end_date: props.holiday.end_date || '',
        year: props.holiday.year || new Date().getFullYear(),
        is_recurring: props.holiday.is_recurring ?? false,
        is_half_day: props.holiday.is_half_day ?? false,
        applies_to_all: props.holiday.applies_to_all ?? true,
        department_id: props.holiday.department_id || null,
        country_code: props.holiday.country_code || '',
        notes: props.holiday.notes || '',
        is_active: props.holiday.is_active ?? true,
      }
    } else {
      form.value = {
        name: ensureTranslatable(),
        date: '',
        end_date: '',
        year: new Date().getFullYear(),
        is_recurring: false,
        is_half_day: false,
        applies_to_all: true,
        department_id: null,
        country_code: '',
        notes: '',
        is_active: true,
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
            color="warning"
            variant="tonal"
            rounded
            class="me-3"
          >
            <VIcon icon="tabler-calendar-heart" />
          </VAvatar>
        </template>
        <VCardTitle>
          {{ props.holiday ? t('time_off.edit_holiday', 'Edit Public Holiday') : t('time_off.add_holiday', 'New Public Holiday') }}
        </VCardTitle>
        <VCardSubtitle>
          {{ t('time_off.holiday_desc', 'Official non-working days integrated into workforce schedules.') }}
        </VCardSubtitle>
      </VCardItem>

      <VCardText class="pt-4">
        <VRow>
          <VCol cols="12">
            <MultiLangField
              v-model="form.name"
              :label="t('common.name', 'Holiday Name')"
              required
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppDateTimePicker
              v-model="form.date"
              :label="t('common.date', 'Start Date')"
              required
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppDateTimePicker
              v-model="form.end_date"
              :label="t('time_off.end_date_optional', 'End Date (if multi-day)')"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <VSwitch
              v-model="form.is_recurring"
              :label="t('time_off.recurs_annually', 'Recurs Annually (every year)')"
              color="primary"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <VSwitch
              v-model="form.is_half_day"
              :label="t('time_off.half_day', 'Half Day Holiday')"
              color="warning"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <VSwitch
              v-model="form.applies_to_all"
              :label="t('time_off.applies_all_dept', 'Applies Company-wide')"
              color="success"
            />
          </VCol>
          <VCol cols="12" sm="6" v-if="!form.applies_to_all">
            <AppSelect
              v-model="form.department_id"
              :label="t('departments.department', 'Specific Department')"
              :items="departmentOptions"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="form.notes"
              :label="t('common.notes', 'Notes / Description')"
              rows="2"
            />
          </VCol>
          <VCol cols="12">
            <VSwitch
              v-model="form.is_active"
              :label="t('common.is_active', 'Active Holiday')"
              color="primary"
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
          :disabled="!form.date"
          @click="handleSubmit"
        >
          {{ props.holiday ? t('common.save', 'Save Changes') : t('common.create', 'Create Holiday') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
