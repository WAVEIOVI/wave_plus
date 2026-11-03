<script setup>
/* eslint-disable camelcase */
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useVehicleStore } from '@/stores/useVehicleStore'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import WpDocumentDialog from '@/components/shared/documents/WpDocumentDialog.vue'
import WpMediaViewerDialog from '@/components/shared/media/WpMediaViewerDialog.vue'

const props = defineProps({
  vehicle: {
    type: Object,
    required: true,
  },
  documents: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['refresh'])

const { t } = useI18n()
const store = useVehicleStore()
const { showMessage } = useAppSnackbar()

const isAddOpen = ref(false)
const isSubmitting = ref(false)
const isViewerOpen = ref(false)
const viewerFile = ref(null)

const list = computed(() => {
  return props.documents.length ? props.documents : (props.vehicle.documents || [])
})

const formatDate = val => {
  if (!val) return '—'
  try {
    return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(val))
  } catch {
    return val
  }
}

const statusColor = val => ({
  active: 'success',
  pending_renewal: 'warning',
  expired: 'error',
  archived: 'secondary',
}[val] || 'default')

const isExpired = dateStr => {
  if (!dateStr) return false

  return new Date(dateStr) < new Date()
}

const openAdd = () => {
  isAddOpen.value = true
}

const handlePreview = item => {
  const url = item.media?.url || item.download_url

  viewerFile.value = {
    title: item.title || item.file_name || 'Vehicle Document',
    url,
    downloadUrl: item.download_url || url,
    mimeType: item.mime_type || item.media?.mime_type,
  }
  isViewerOpen.value = true
}

const saveDocument = async docData => {
  isSubmitting.value = true
  try {
    const formData = new FormData()

    formData.append('document_type', docData.document_type || 'insurance')
    formData.append('title', docData.title)
    if (docData.document_number || docData.reference_number)
      formData.append('document_number', docData.document_number || docData.reference_number)
    if (docData.issuer) formData.append('issuer', docData.issuer)
    if (docData.issue_date) formData.append('issue_date', docData.issue_date)
    if (docData.expiry_date || docData.expiration_date)
      formData.append('expiry_date', docData.expiry_date || docData.expiration_date)
    if (docData.reminder_date) formData.append('reminder_date', docData.reminder_date)
    formData.append('status', docData.status || 'active')
    if (docData.notes) formData.append('notes', docData.notes)

    const rawFile = docData.file instanceof File || docData.file instanceof Blob ? docData.file : null
    if (rawFile) {
      const fileName = rawFile.name || `${docData.title || 'document'}.pdf`

      formData.append('file', rawFile, fileName)
    }

    await store.createDocument(props.vehicle.id, formData)
    showMessage(t('Saved') || 'Document added successfully', 'success')
    isAddOpen.value = false
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || err?.message || 'Failed to save document', 'error')
  } finally {
    isSubmitting.value = false
  }
}

const deleteDocument = async id => {
  if (!confirm('Are you sure you want to remove this document?')) return
  try {
    await store.deleteDocument(id, props.vehicle.id)
    showMessage('Document deleted successfully', 'success')
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || 'Failed to delete document', 'error')
  }
}
</script>

<template>
  <div class="d-flex flex-column gap-4">
    <!-- Toolbar -->
    <div class="d-flex justify-space-between align-center">
      <div>
        <h3 class="text-h6 font-weight-bold">
          {{ $t('fleet.documents_compliance') || 'Compliance Documents & Certificates' }}
        </h3>
        <p class="text-caption text-medium-emphasis mb-0">
          Manage mandatory vehicle files, registration, insurance, permits, and inspection certificates.
        </p>
      </div>

      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="openAdd"
      >
        {{ $t('fleet.add_document') || 'Add Document' }}
      </VBtn>
    </div>

    <!-- Table -->
    <VCard variant="outlined">
      <VTable
        hover
        density="comfortable"
      >
        <thead>
          <tr>
            <th>Type</th>
            <th>Title & Reference</th>
            <th>Issuer</th>
            <th>Issue Date</th>
            <th>Expiry Date</th>
            <th>Compliance Status</th>
            <th>Notes</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in list"
            :key="item.id"
          >
            <td>
              <VChip
                size="small"
                variant="tonal"
                class="text-capitalize"
              >
                {{ item.document_type?.replaceAll('_', ' ') }}
              </VChip>
            </td>
            <td>
              <div
                class="font-weight-medium"
                :class="{ 'cursor-pointer text-primary': item.has_file || item.download_url }"
                @click="(item.has_file || item.download_url) && handlePreview(item)"
              >
                {{ item.title }}
              </div>
              <div class="text-caption text-medium-emphasis">{{ item.document_number || '—' }}</div>
              <div
                v-if="item.has_file || item.file_name"
                class="text-caption text-primary d-flex align-center gap-1 mt-0.5 cursor-pointer"
                @click="handlePreview(item)"
              >
                <VIcon
                  icon="tabler-paperclip"
                  size="13"
                />
                <span class="text-truncate" style="max-inline-size: 150px;">{{ item.file_name || 'Attached file' }}</span>
              </div>
            </td>
            <td>{{ item.issuer || '—' }}</td>
            <td>{{ formatDate(item.issue_date) }}</td>
            <td>
              <div class="d-flex align-center gap-1">
                <span :class="{ 'text-error font-weight-bold': isExpired(item.expiry_date) }">
                  {{ formatDate(item.expiry_date) }}
                </span>
                <VIcon
                  v-if="isExpired(item.expiry_date)"
                  icon="tabler-alert-circle"
                  color="error"
                  size="16"
                />
              </div>
            </td>
            <td>
              <VChip
                :color="isExpired(item.expiry_date) ? 'error' : statusColor(item.status)"
                size="small"
                label
                class="text-capitalize"
              >
                {{ isExpired(item.expiry_date) ? 'Expired' : item.status?.replaceAll('_', ' ') }}
              </VChip>
            </td>
            <td>
              <span class="text-caption text-truncate d-inline-block" style="max-inline-size: 140px;">
                {{ item.notes || '—' }}
              </span>
            </td>
            <td class="text-end">
              <div class="d-flex justify-end gap-1">
                <VBtn
                  v-if="item.has_file || item.download_url"
                  variant="text"
                  color="primary"
                  density="compact"
                  icon="tabler-eye"
                  size="small"
                  :title="$t('Preview') || 'Preview'"
                  @click="handlePreview(item)"
                />
                <VBtn
                  v-if="item.has_file || item.download_url"
                  variant="text"
                  color="default"
                  density="compact"
                  icon="tabler-download"
                  size="small"
                  :title="$t('Download') || 'Download'"
                  @click="store.downloadDocument(item.id)"
                />
                <VBtn
                  variant="text"
                  color="error"
                  density="compact"
                  icon="tabler-trash"
                  size="small"
                  :title="$t('Delete') || 'Delete'"
                  @click="deleteDocument(item.id)"
                />
              </div>
            </td>
          </tr>

          <tr v-if="!list.length">
            <td
              colspan="8"
              class="text-center py-6 text-disabled"
            >
              <VIcon
                icon="tabler-file-off"
                size="32"
                class="mb-2"
              />
              <div>No compliance documents attached to this vehicle.</div>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- Document Upload / Scan Dialog -->
    <WpDocumentDialog
      v-model="isAddOpen"
      :title="$t('fleet.add_document', 'Attach New Compliance Document')"
      preset="fleet"
      @save="saveDocument"
    />

    <!-- Media Viewer Dialog -->
    <WpMediaViewerDialog
      v-model:model-value="isViewerOpen"
      :file="viewerFile"
    />
  </div>
</template>
