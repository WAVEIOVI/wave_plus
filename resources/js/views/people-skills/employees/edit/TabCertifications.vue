<script setup>
/* eslint-disable camelcase */
import { WpFormDialog, WpSectionCard, WpSimpleTable } from '@/components/shared'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { localizedValue } from '@/composables/useLocaleField'
import { useHrStore } from '@/stores/hrStore'
import { useEmployeeStore } from '@/stores/useEmployeeStore'
import { resolveStatusColor } from '@/utils/hr-lookups'
import EmployeeEditEmptyState from '@/views/people-skills/employees/edit/EmployeeEditEmptyState.vue'
import { computed, defineEmits, defineProps, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  certifications: { type: Array, default: () => [] },
  employeeId: { type: String, required: true },
  readOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['refresh'])

const hrStore = useHrStore()
const employeeStore = useEmployeeStore()
const { showMessage } = useAppSnackbar()
const { t } = useI18n()

const isAssignDialogVisible = ref(false)
const selectedCertId = ref(null)
const certificateNumber = ref('')
const issuingOrg = ref('')
const issueDate = ref(new Date().toISOString().split('T')[0])
const expiryDate = ref('')
const certFile = ref(null)
const notes = ref('')
const isSubmitting = ref(false)
const verifyingId = ref(null)

const columns = computed(() => {
  const cols = [
    { key: 'name', title: t('hr.employees.fields.certification') },
    { key: 'certificate_number', title: t('hr.employees.fields.certificateNumber') },
    { key: 'issuing_organization', title: t('hr.employees.fields.issuingBody', 'Issuing Body') },
    { key: 'issue_date', title: t('hr.employees.fields.issueDate') },
    { key: 'expiry_date', title: t('hr.employees.fields.expiryDate') },
    { key: 'status', title: t('hr.employees.fields.status') },
    { key: 'files', title: t('hr.employees.fields.files', 'Files') },
  ]
  if (!props.readOnly) cols.push({ key: 'actions', title: t('common.actions', 'Action'), align: 'center' })

  return cols
})

const certStatus = cert => cert.status || (cert.is_valid ? 'valid' : 'expired')

onMounted(async () => {
  if (!hrStore.certifications.length) await hrStore.fetchCertifications()
})

const handleFileChange = value => {
  // VFileInput v-model yields File | File[] | null depending on `multiple`
  certFile.value = Array.isArray(value) ? (value[0] || null) : (value || null)
}

const resetDialog = () => {
  selectedCertId.value = null
  certificateNumber.value = ''
  issuingOrg.value = ''
  issueDate.value = new Date().toISOString().split('T')[0]
  expiryDate.value = ''
  certFile.value = null
  notes.value = ''
}

const openAssign = () => {
  resetDialog()
  isAssignDialogVisible.value = true
}

defineExpose({ openAssign })

const handleAssign = async () => {
  if (!selectedCertId.value) return
  isSubmitting.value = true
  try {
    const formData = new FormData()

    formData.append('certification_id', selectedCertId.value)
    if (certificateNumber.value) formData.append('certificate_number', certificateNumber.value)
    if (issuingOrg.value) formData.append('issuing_organization', issuingOrg.value)
    formData.append('issue_date', issueDate.value)
    if (expiryDate.value) formData.append('expiry_date', expiryDate.value)
    if (notes.value) formData.append('notes', notes.value)
    if (certFile.value) formData.append('file', certFile.value)

    await employeeStore.assignCertification(props.employeeId, formData)

    isAssignDialogVisible.value = false
    resetDialog()
    showMessage(t('hr.employees.certifications.assign_success', 'Certification assigned successfully'), 'success')
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || t('hr.employees.certifications.assign_error', 'Failed to assign certification'), 'error')
  } finally {
    isSubmitting.value = false
  }
}

const handleVerify = async certRecordId => {
  verifyingId.value = certRecordId
  try {
    await employeeStore.verifyCertification(certRecordId)
    showMessage(t('hr.employees.certifications.verify_success', 'Certification verified successfully'), 'success')
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || t('hr.employees.certifications.verify_error', 'Failed to verify certification'), 'error')
  } finally {
    verifyingId.value = null
  }
}
</script>

<template>
  <WpSectionCard
    :title="t('hr.employees.sections.certifications')"
    icon="tabler-certificate"
    icon-color="warning"
    :subtitle="t('hr.employees.certifications.subtitle', 'Accreditations, licenses, and validity tracking.')"
  >
    <template
      v-if="!readOnly"
      #actions
    >
      <VBtn
        color="warning"
        size="small"
        prepend-icon="tabler-plus"
        class="d-none d-md-inline-flex"
        @click="openAssign"
      >
        {{ t('hr.employees.certifications.assign', 'Assign Certification') }}
      </VBtn>
    </template>

    <WpSimpleTable
      :columns="columns"
      :items="certifications"
      :breakpoint="900"
    >
      <template #cell.name="{ item }">
        <span class="font-weight-medium">{{ localizedValue(item.name) }}</span>
        <span
          v-if="item.code"
          class="text-caption text-medium-emphasis ms-2"
        >({{ item.code }})</span>
      </template>
      <template #cell.certificate_number="{ item }">
        <span class="font-monospace text-body-2">{{ item.certificate_number || '—' }}</span>
      </template>
      <template #cell.issuing_organization="{ item }">
        {{ item.issuing_organization || '—' }}
      </template>
      <template #cell.issue_date="{ item }">
        {{ item.issue_date || '—' }}
      </template>
      <template #cell.expiry_date="{ item }">
        <span
          class="font-weight-medium"
          :class="{ 'text-error font-weight-bold': item.status === 'expired', 'text-warning font-weight-bold': item.status === 'expiring' }"
        >
          {{ item.expiry_date || t('hr.employees.noExpiry', 'No Expiry') }}
        </span>
      </template>
      <template #cell.status="{ item }">
        <VChip
          size="small"
          :color="resolveStatusColor(certStatus(item))"
          class="text-uppercase font-weight-bold"
        >
          {{ t(`hr.employees.certStatus.${certStatus(item)}`, certStatus(item)) }}
        </VChip>
      </template>
      <template #cell.files="{ item }">
        <div
          v-if="item.files?.length"
          class="d-flex gap-2 flex-wrap justify-end justify-md-start"
        >
          <a
            v-for="f in item.files"
            :key="f.id"
            :href="f.url"
            target="_blank"
            rel="noopener"
            class="text-caption text-primary d-inline-flex align-center gap-1"
          >
            <VIcon
              icon="tabler-paperclip"
              size="14"
            />
            {{ f.name }}
          </a>
        </div>
        <span
          v-else
          class="text-caption text-disabled"
        >—</span>
      </template>
      <template #cell.actions="{ item }">
        <VBtn
          v-if="!item.verified_at"
          size="x-small"
          variant="tonal"
          color="success"
          :loading="verifyingId === item.id"
          @click="handleVerify(item.id)"
        >
          {{ t('hr.employees.verify', 'Verify') }}
        </VBtn>
        <span
          v-else
          class="text-caption text-success font-weight-medium"
        >{{ t('hr.employees.fields.verified') }}</span>
      </template>

      <template #empty>
        <EmployeeEditEmptyState
          icon="tabler-certificate-off"
          :message="t('hr.employees.certifications.empty', 'No certifications or licenses recorded for this employee.')"
        />
      </template>
    </WpSimpleTable>

    <WpFormDialog
      v-model="isAssignDialogVisible"
      :title="t('hr.employees.certifications.assign_title', 'Assign Employee Certification')"
      icon="tabler-certificate"
      icon-color="warning"
      :loading="isSubmitting"
      :submit-label="t('hr.employees.certifications.assign', 'Assign Certification')"
      submit-icon="tabler-certificate"
      submit-color="warning"
      @submit="handleAssign"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="selectedCertId"
            :label="t('hr.employees.fields.certificationType', 'Certification Type')"
            :items="hrStore.certifications"
            item-title="name"
            item-value="id"
            :rules="[v => !!v || t('common.required', 'Required')]"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <AppTextField
            v-model="certificateNumber"
            :label="t('hr.employees.fields.certificateNumber')"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <AppTextField
            v-model="issuingOrg"
            :label="t('hr.employees.fields.issuingBody', 'Issuing Organization')"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <AppDateTimePicker
            v-model="issueDate"
            :label="t('hr.employees.fields.issueDate')"
            :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y' }"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <AppDateTimePicker
            v-model="expiryDate"
            :label="t('hr.employees.fields.expiryDateHint', 'Expiry Date (blank = permanent)')"
            :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y' }"
          />
        </VCol>
        <VCol cols="12">
          <VFileInput
            :label="t('hr.employees.fields.certificateFile', 'Certificate Document / Scan')"
            prepend-icon="tabler-paperclip"
            density="comfortable"
            @update:model-value="handleFileChange"
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
