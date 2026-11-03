<script setup>
import MultiLangField from '@/components/shared/data/MultiLangField.vue'
import { ensureTranslatable } from '@/composables/useLocaleField'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  leaveType: {
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
  name: ensureTranslatable(),
  code: '',
  description: ensureTranslatable(),
  is_paid: true,
  max_days_per_year: 30,
  requires_approval: true,
  allows_hourly: false,
  min_hourly_unit_minutes: 15,
  max_hours_per_request: null,
  is_active: true,
})

watch(() => props.isDialogVisible, val => {
  if (val) {
    if (props.leaveType) {
      form.value = {
        name: ensureTranslatable(props.leaveType.name),
        code: props.leaveType.code || '',
        description: ensureTranslatable(props.leaveType.description),
        is_paid: props.leaveType.is_paid ?? true,
        max_days_per_year: props.leaveType.max_days_per_year ?? 30,
        requires_approval: props.leaveType.requires_approval ?? true,
        allows_hourly: props.leaveType.allows_hourly ?? false,
        min_hourly_unit_minutes: props.leaveType.min_hourly_unit_minutes ?? 15,
        max_hours_per_request: props.leaveType.max_hours_per_request ?? null,
        is_active: props.leaveType.is_active ?? true,
      }
    } else {
      form.value = {
        name: ensureTranslatable(),
        code: '',
        description: ensureTranslatable(),
        is_paid: true,
        max_days_per_year: 30,
        requires_approval: true,
        allows_hourly: false,
        min_hourly_unit_minutes: 15,
        max_hours_per_request: null,
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
            color="primary"
            variant="tonal"
            rounded
            class="me-3"
          >
            <VIcon icon="tabler-calendar-time" />
          </VAvatar>
        </template>
        <VCardTitle>
          {{ props.leaveType ? t('time_off.edit_type', 'Edit Leave Type') : t('time_off.add_type', 'New Leave Type') }}
        </VCardTitle>
        <VCardSubtitle>
          {{ t('time_off.type_desc', 'Configure quota, approval rules, and hourly options.') }}
        </VCardSubtitle>
      </VCardItem>

      <VCardText class="pt-4">
        <VRow>
          <VCol cols="12">
            <MultiLangField
              v-model="form.name"
              :label="t('common.name', 'Type Name')"
              required
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppTextField
              v-model="form.code"
              :label="t('common.code', 'Code')"
              placeholder="e.g. ANNUAL, SICK, MATERNITY"
              required
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppTextField
              v-model.number="form.max_days_per_year"
              :label="t('time_off.max_days_per_year', 'Max Days Per Year')"
              type="number"
              min="0"
              required
            />
          </VCol>
          <VCol cols="12">
            <MultiLangField
              v-model="form.description"
              :label="t('common.description', 'Description')"
              type="textarea"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <VSwitch
              v-model="form.is_paid"
              :label="t('time_off.is_paid', 'Paid Leave')"
              color="success"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <VSwitch
              v-model="form.requires_approval"
              :label="t('time_off.requires_approval', 'Requires Approval')"
              color="primary"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <VSwitch
              v-model="form.allows_hourly"
              :label="t('time_off.allows_hourly', 'Allow Hourly Leave')"
              color="info"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <VSwitch
              v-model="form.is_active"
              :label="t('common.is_active', 'Active')"
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
          :disabled="!form.code"
          @click="handleSubmit"
        >
          {{ props.leaveType ? t('common.save', 'Save Changes') : t('common.create', 'Create Leave Type') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
