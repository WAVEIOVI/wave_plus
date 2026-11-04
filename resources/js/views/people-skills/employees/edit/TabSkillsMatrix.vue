<script setup>
/* eslint-disable camelcase */
import { WpDeleteDialog, WpFormDialog, WpSectionCard, WpSimpleTable } from '@/components/shared'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { localizedValue } from '@/composables/useLocaleField'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { useHrStore } from '@/stores/hrStore'
import { useEmployeeStore } from '@/stores/useEmployeeStore'
import { resolveSkillLevelColor } from '@/utils/hr-lookups'
import EmployeeEditEmptyState from '@/views/people-skills/employees/edit/EmployeeEditEmptyState.vue'
import { computed, defineEmits, defineProps, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  skills: { type: Array, default: () => [] },
  employeeId: { type: String, required: true },
  readOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['refresh'])

const hrStore = useHrStore()
const employeeStore = useEmployeeStore()
const { showMessage } = useAppSnackbar()
const { t } = useI18n()
const ability = useAbility()

const isAssignDialogVisible = ref(false)
const selectedSkillId = ref(null)
const selectedLevel = ref('basic')
const acquiredDate = ref(new Date().toISOString().split('T')[0])
const notes = ref('')
const isSubmitting = ref(false)
const verifyingId = ref(null)
const isDeleteDialogOpen = ref(false)
const itemToDelete = ref(null)
const isDeleting = ref(false)

const levelOptions = computed(() => [
  { title: t('hr.employees.skillLevel.basic', 'Basic'), value: 'basic' },
  { title: t('hr.employees.skillLevel.intermediate', 'Intermediate'), value: 'intermediate' },
  { title: t('hr.employees.skillLevel.advanced', 'Advanced'), value: 'advanced' },
  { title: t('hr.employees.skillLevel.expert', 'Expert'), value: 'expert' },
])

const columns = computed(() => {
  const cols = [
    { key: 'name', title: t('hr.employees.fields.skill') },
    { key: 'category', title: t('hr.employees.fields.category') },
    { key: 'level', title: t('hr.employees.fields.level') },
    { key: 'expiry_date', title: t('hr.employees.fields.expiryDate') },
    { key: 'is_verified', title: t('hr.employees.fields.verified') },
  ]

  if (!props.readOnly) cols.push({ key: 'actions', title: t('common.actions', 'Action'), align: 'center' })

  return cols
})

onMounted(async () => {
  if (!hrStore.skills.length) await hrStore.fetchSkills()
})

const resetDialog = () => {
  selectedSkillId.value = null
  selectedLevel.value = 'basic'
  acquiredDate.value = new Date().toISOString().split('T')[0]
  notes.value = ''
}

const openAssign = () => {
  resetDialog()
  isAssignDialogVisible.value = true
}

defineExpose({ openAssign })

const handleAssign = async () => {
  if (!selectedSkillId.value) return
  isSubmitting.value = true
  try {
    await employeeStore.assignSkill(props.employeeId, {
      skill_id: selectedSkillId.value,
      level: selectedLevel.value,
      acquired_date: acquiredDate.value,
      notes: notes.value,
    })
    isAssignDialogVisible.value = false
    resetDialog()
    showMessage(t('hr.employees.skills.assign_success', 'Skill assigned successfully'), 'success')
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || t('hr.employees.skills.assign_error', 'Failed to assign skill'), 'error')
  } finally {
    isSubmitting.value = false
  }
}

const handleVerify = async empSkillId => {
  verifyingId.value = empSkillId
  try {
    await employeeStore.verifySkill(empSkillId)
    showMessage(t('hr.employees.skills.verify_success', 'Skill verified successfully'), 'success')
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || t('hr.employees.skills.verify_error', 'Failed to verify skill'), 'error')
  } finally {
    verifyingId.value = null
  }
}

const openDelete = item => {
  itemToDelete.value = item
  isDeleteDialogOpen.value = true
}

const handleDelete = async () => {
  if (!itemToDelete.value?.id) return
  isDeleting.value = true
  try {
    await employeeStore.deleteSkill(itemToDelete.value.id)
    showMessage(t('hr.employees.skills.delete_success', 'Skill removed from employee'), 'success')
    isDeleteDialogOpen.value = false
    itemToDelete.value = null
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || t('common.delete_error', 'Failed to delete record'), 'error')
  } finally {
    isDeleting.value = false
  }
}
</script>

<template>
  <WpSectionCard
    :title="t('hr.employees.sections.skills')"
    icon="tabler-award"
    :subtitle="t('hr.employees.skills.subtitle', 'Operational competencies, proficiency levels, and supervisor verifications.')"
  >
    <template
      v-if="!readOnly"
      #actions
    >
      <VBtn
        color="primary"
        size="small"
        prepend-icon="tabler-plus"
        class="d-none d-md-inline-flex"
        @click="openAssign"
      >
        {{ t('hr.employees.skills.assign', 'Assign Skill') }}
      </VBtn>
    </template>

    <WpSimpleTable
      :columns="columns"
      :items="skills"
      :breakpoint="760"
    >
      <template #cell.name="{ item }">
        <span class="font-weight-medium">{{ localizedValue(item.name) }}</span>
        <span
          v-if="item.code"
          class="text-caption text-medium-emphasis ms-2"
        >({{ item.code }})</span>
      </template>
      <template #cell.category="{ item }">
        <VChip
          size="small"
          variant="outlined"
        >
          {{ item.category || t('hr.employees.general', 'General') }}
        </VChip>
      </template>
      <template #cell.level="{ item }">
        <VChip
          size="small"
          :color="resolveSkillLevelColor(item.level)"
          class="text-uppercase font-weight-bold"
        >
          {{ t(`hr.employees.skillLevel.${item.level}`, item.level) }}
        </VChip>
      </template>
      <template #cell.expiry_date="{ item }">
        {{ item.expiry_date || '—' }}
      </template>
      <template #cell.is_verified="{ item }">
        <VChip
          v-if="item.is_verified"
          size="small"
          color="success"
          variant="tonal"
          prepend-icon="tabler-check"
        >
          {{ t('hr.employees.fields.verified') }}
        </VChip>
        <VChip
          v-else
          size="small"
          color="warning"
          variant="tonal"
        >
          {{ t('hr.employees.certStatus.pending_verification') }}
        </VChip>
      </template>
      <template #cell.actions="{ item }">
        <div class="d-flex align-center gap-1 justify-center">
          <VBtn
            v-if="!item.is_verified && ability.can('manage', 'skills')"
            size="x-small"
            variant="tonal"
            color="success"
            :loading="verifyingId === item.id"
            @click="handleVerify(item.id)"
          >
            {{ t('hr.employees.verify', 'Verify') }}
          </VBtn>
          <VBtn
            v-if="ability.can('manage', 'skills')"
            icon="tabler-trash"
            size="x-small"
            variant="text"
            color="error"
            @click="openDelete(item)"
          />
        </div>
      </template>

      <template #empty>
        <EmployeeEditEmptyState
          icon="tabler-award-off"
          :message="t('hr.employees.skills.empty', 'No operational skills assigned to this employee yet.')"
        />
      </template>
    </WpSimpleTable>

    <WpDeleteDialog
      v-model:is-dialog-visible="isDeleteDialogOpen"
      :title="t('common.delete', 'Delete Record')"
      :confirmation-text="t('common.delete_confirm', 'Are you sure you want to remove this skill from the employee?')"
      :item-name="localizedValue(itemToDelete?.name)"
      :loading="isDeleting"
      @confirm="handleDelete"
    />

    <WpFormDialog
      v-model="isAssignDialogVisible"
      :title="t('hr.employees.skills.assign_title', 'Assign Operational Skill')"
      icon="tabler-award"
      size="small"
      :loading="isSubmitting"
      :submit-label="t('hr.employees.skills.assign', 'Assign Skill')"
      submit-icon="tabler-plus"
      :submit-disabled="!selectedSkillId"
      @submit="handleAssign"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="selectedSkillId"
            :label="t('hr.employees.fields.skill')"
            :items="hrStore.skills"
            item-title="name"
            item-value="id"
            :rules="[v => !!v || t('common.required', 'Required')]"
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="selectedLevel"
            :label="t('hr.employees.fields.level')"
            :items="levelOptions"
          />
        </VCol>
        <VCol cols="12">
          <AppDateTimePicker
            v-model="acquiredDate"
            :label="t('hr.employees.fields.acquiredDate', 'Date Acquired')"
            :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y' }"
          />
        </VCol>
        <VCol cols="12">
          <AppTextarea
            v-model="notes"
            :label="t('hr.employees.fields.notes')"
            rows="2"
          />
        </VCol>
      </VRow>
    </WpFormDialog>
  </WpSectionCard>
</template>
