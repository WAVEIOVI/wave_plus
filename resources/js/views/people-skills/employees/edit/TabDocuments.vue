<script setup>
import { WpSectionCard, WpSimpleTable } from '@/components/shared'
import WpDocumentDialog from '@/components/shared/documents/WpDocumentDialog.vue'
import WpMediaViewerDialog from '@/components/shared/media/WpMediaViewerDialog.vue'
import EmployeeEditEmptyState from '@/views/people-skills/employees/edit/EmployeeEditEmptyState.vue'
import { useEmployeeStore } from '@/stores/useEmployeeStore'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  documents: {
    type: Array,
    default: () => [],
  },
  employeeId: {
    type: String,
    required: true,
  },
  readOnly: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['refresh'])

const { t } = useI18n()
const employeeStore = useEmployeeStore()
const { showMessage } = useAppSnackbar?.() ?? { showMessage: () => {} }

const isDocDialogOpen = ref(false)
const isUploading = ref(false)
const isViewerOpen = ref(false)
const viewerFile = ref(null)

const triggerUpload = () => {
  isDocDialogOpen.value = true
}

const handlePreview = item => {
  viewerFile.value = {
    title: item.name || item.file_name || 'Document',
    url: item.url,
    downloadUrl: item.url,
    mimeType: item.mime_type,
  }
  isViewerOpen.value = true
}

defineExpose({ triggerUpload })

const handleDocumentSaved = async payload => {
  isUploading.value = true
  try {
    await employeeStore.uploadDocument(props.employeeId, payload)
    showMessage(t('documents.upload_success', 'Document uploaded successfully'), 'success')
    isDocDialogOpen.value = false
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || err?.message || t('documents.upload_error', 'Failed to upload document'), 'error')
  } finally {
    isUploading.value = false
  }
}

const handleDocumentError = err => {
  showMessage(err?.message || 'Document processing error', 'error')
}

const formatBytes = (bytes, decimals = 2) => {
  if (!bytes) return '0 Bytes'
  const k = 1024
  const dm = decimals < 0 ? 0 : decimals
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))

  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i]
}

const columns = computed(() => [
  { key: 'name', title: t('hr.employees.fields.documentName', 'Document Name') },
  { key: 'mime_type', title: t('hr.employees.fields.type', 'Type') },
  { key: 'size', title: t('hr.employees.fields.fileSize', 'File Size') },
  { key: 'created_at', title: t('hr.employees.fields.uploadedDate', 'Uploaded Date') },
  { key: 'actions', title: t('common.actions', 'Action'), align: 'center' },
])
</script>

<template>
  <WpSectionCard
    :title="$t('hr.employees.sections.documents', 'Employee Document Vault')"
    icon="tabler-files"
    :subtitle="$t('hr.employees.documents.subtitle', 'Contracts, ID scans, administrative records, and compliance attachments.')"
  >
    <template
      v-if="!readOnly"
      #actions
    >
      <VBtn
        color="primary"
        size="small"
        prepend-icon="tabler-file-upload"
        class="d-none d-md-inline-flex"
        :loading="isUploading"
        @click="triggerUpload"
      >
        {{ $t('hr.employees.documents.upload', 'Upload / Scan Document') }}
      </VBtn>
    </template>

    <WpSimpleTable
      :columns="columns"
      :items="documents"
    >
      <template #cell.name="{ item }">
        <div
          class="d-flex align-center gap-2 justify-end justify-sm-start"
          :class="{ 'cursor-pointer text-primary': item.url }"
          @click="item.url && handlePreview(item)"
        >
          <VIcon
            icon="tabler-file-text"
            color="primary"
            size="20"
            class="d-none d-sm-inline-flex"
          />
          <span class="font-weight-medium">{{ item.name || item.file_name }}</span>
        </div>
      </template>
      <template #cell.mime_type="{ item }">
        <span class="text-caption text-medium-emphasis">{{ item.mime_type || 'Document' }}</span>
      </template>
      <template #cell.size="{ item }">
        <span class="text-body-2 font-monospace">{{ formatBytes(item.size) }}</span>
      </template>
      <template #cell.created_at="{ item }">
        {{ item.created_at ? new Date(item.created_at).toLocaleDateString() : '—' }}
      </template>
      <template #cell.actions="{ item }">
        <div class="d-flex align-center justify-center gap-1">
          <VBtn
            v-if="item.url"
            icon="tabler-eye"
            variant="text"
            size="small"
            color="primary"
            :aria-label="$t('common.preview', 'Preview')"
            @click="handlePreview(item)"
          />
          <VBtn
            v-if="item.url"
            icon="tabler-download"
            variant="text"
            size="small"
            color="default"
            :href="item.url"
            target="_blank"
            rel="noopener noreferrer"
            download
            :aria-label="$t('common.download', 'Download')"
          />
        </div>
      </template>

      <template #empty>
        <EmployeeEditEmptyState
          icon="tabler-file-off"
          :title="$t('hr.employees.empty.documentsTitle', 'No Documents Stored')"
          :description="$t('hr.employees.empty.documentsDescription', 'Store contracts, identity cards, resumes, and certification scans.')"
          :action-label="!readOnly ? $t('hr.employees.documents.upload', 'Upload / Scan Document') : null"
          action-icon="tabler-file-upload"
          @action="triggerUpload"
        />
      </template>
    </WpSimpleTable>

    <!-- Document Upload / Scan Dialog -->
    <WpDocumentDialog
      v-model="isDocDialogOpen"
      :title="$t('hr.employees.documents.dialogTitle', 'Upload or Scan Employee Document')"
      preset="employee"
      :document-types="[
        { value: 'id_scan', title: 'ID / Passport' },
        { value: 'contract', title: 'Employment Contract' },
        { value: 'resume', title: 'Resume / CV' },
        { value: 'certificate', title: 'Certificate' },
        { value: 'other', title: 'Other Record' },
      ]"
      @save="handleDocumentSaved"
      @error="handleDocumentError"
    />

    <!-- Media Viewer Dialog -->
    <WpMediaViewerDialog
      v-model:model-value="isViewerOpen"
      :file="viewerFile"
    />
  </WpSectionCard>
</template>
