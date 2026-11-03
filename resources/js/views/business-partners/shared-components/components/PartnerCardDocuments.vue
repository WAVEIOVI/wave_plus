<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { usePagination } from '@/composables/usePagination'
import { useI18n } from 'vue-i18n'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useClientStore } from '@/stores/useClientStore'
import { useOrganisationStore } from '@/stores/useOrganisationStore'
import { useServiceProviderStore } from '@/stores/useServiceProviderStore'
import { useSupplierStore } from '@/stores/useSupplierStore'
import WpDocumentDialog from '@/components/shared/documents/WpDocumentDialog.vue'
import WpMediaViewerDialog from '@/components/shared/media/WpMediaViewerDialog.vue'
import DeleteDocumentDialog from '@/views/business-partners/shared-components/dialogs/DeleteDocumentDialog.vue'

const props = defineProps({
  partnerId: { type: [String, Number], required: true },
  partnerType: { type: String, required: true, validator: v => ['client', 'supplier', 'serviceProvider', 'organisation'].includes(v) },
  documentData: { type: Array, default: () => [] },
  readOnly: { type: Boolean, default: false },
})

const emit = defineEmits(['refresh'])

const store = props.partnerType === 'supplier' ? useSupplierStore()
  : props.partnerType === 'serviceProvider' ? useServiceProviderStore()
    : props.partnerType === 'organisation' ? useOrganisationStore()
      : useClientStore()

const methodPrefix = props.partnerType === 'supplier' ? 'Supplier'
  : props.partnerType === 'serviceProvider' ? 'ServiceProvider'
    : props.partnerType === 'organisation' ? 'Organisation'
      : 'Client'

const { t } = useI18n()
const { success, info, error: showError, showApiError } = useAppSnackbar()

// ── Pagination ──────────────────────────────────────────────────────────
const itemsPerPage = ref(5)
const docsRef = computed(() => props.documentData)
const { page, totalPages, currentItems: currentDocs } = usePagination(docsRef, itemsPerPage)

// ── Dialog state ────────────────────────────────────────────────────────
const isDocDialogVisible = ref(false)
const isDeleteDialogVisible = ref(false)
const currentDocument = ref(null)
const docToDelete = ref(null)
const isEditMode = ref(false)
const isViewerOpen = ref(false)
const viewerFile = ref(null)

// ── Variant resolvers ───────────────────────────────────────────────────
const resolveDocStatusVariant = status => ({
  draft: 'secondary',
  active: 'success',
  'under_review': 'warning',
  expired: 'error',
  terminated: 'default',
}[status?.toLowerCase()] || 'primary')

const resolveDocTypeVariant = type => ({
  contract: 'primary',
  nda: 'warning',
  'framework_agreement': 'info',
  'purchase_order': 'success',
  'compliance_certificate': 'secondary',
  'insurance_policy': 'info',
  'tax_id': 'primary',
  rne: 'primary',
  other: 'default',
}[type?.toLowerCase()] || 'secondary')

const resolveFileIcon = mime => {
  if (!mime) return 'tabler-file'
  if (mime.includes('pdf')) return 'tabler-file-type-pdf'
  if (mime.includes('image')) return 'tabler-photo'
  if (mime.includes('spreadsheet') || mime.includes('excel')) return 'tabler-file-spreadsheet'
  if (mime.includes('word') || mime.includes('document')) return 'tabler-file-type-doc'

  return 'tabler-file'
}

// ── EMPTY template ──────────────────────────────────────────────────────
const EMPTY_DOC = {
  type: 'other',
  title: '',
  'reference_number': '',
  'issue_date': '',
  'effective_date': '',
  'expiration_date': '',
  'auto_renewable': false,
  status: 'active',
  notes: '',
  metadata: {},
}

// ── Handlers ────────────────────────────────────────────────────────────
const handleAdd = () => {
  currentDocument.value = { ...EMPTY_DOC }
  isEditMode.value = false
  isDocDialogVisible.value = true
}

const handleEdit = doc => {
  currentDocument.value = { ...doc }
  isEditMode.value = true
  isDocDialogVisible.value = true
}

const handleSubmit = async docInput => {
  try {
    const { file, ...docData } = docInput

    if (isEditMode.value && docData.id)
      await store[`update${methodPrefix}Document`](props.partnerId, docData.id, docData, file || null)
    else
      await store[`add${methodPrefix}Document`](props.partnerId, docData, file || null)
    isDocDialogVisible.value = false
    success(t('business_partners.documents.saved_success', 'Document saved successfully'))
    emit('refresh')
  }
  catch (error) {
    showApiError(error, t('business_partners.documents.save_error', 'Failed to save document'))
  }
}

const handleDelete = async docId => {
  try {
    await store[`delete${methodPrefix}Document`](props.partnerId, docId)
    success(t('business_partners.documents.deleted_success', 'Document deleted successfully'))
    emit('refresh')
  }
  catch (error) {
    showApiError(error, t('business_partners.documents.delete_error', 'Failed to delete document'))
  }
}

const openDeleteDialog = doc => {
  docToDelete.value = doc.id
  isDeleteDialogVisible.value = true
}

const handleDownload = async doc => {
  try {
    const filename = doc.file_name || doc.title || 'downloaded-document'

    await store[`download${methodPrefix}Document`](doc.download_url, filename)
    info(t('business_partners.documents.download_started', 'Download started'))
  } catch (error) {
    showError(t('business_partners.documents.download_error', 'Failed to download document'))
  }
}

const handlePreview = doc => {
  viewerFile.value = {
    title: localizedValue(doc.title) || doc.file_name || 'Document',
    url: doc.file_url,
    downloadUrl: doc.download_url,
    mimeType: doc.mime_type,
  }
  isViewerOpen.value = true
}

defineExpose({ openAdd: handleAdd })
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <div class="d-flex justify-space-between mb-6 flex-wrap align-center gap-y-4 gap-x-6">
        <h5 class="text-h5">
          {{ $t('Documents') }}
        </h5>
        <VBtn
          v-if="!readOnly"
          variant="tonal"
          size="small"
          class="d-none d-md-inline-flex"
          @click="handleAdd"
        >
          {{ $t('Add New Document') }}
        </VBtn>
      </div>

      <!-- 👉 Document Cards -->
      <VList
        v-if="currentDocs.length"
        lines="two"
        class="bg-transparent"
      >
        <VListItem
          v-for="(doc, index) in currentDocs"
          :key="doc.id || index"
          rounded="lg"
          class="mb-3 item-card"
        >
          <template #prepend>
            <VAvatar
              rounded
              color="primary"
              variant="tonal"
              size="42"
            >
              <VIcon
                :icon="resolveFileIcon(doc.mime_type)"
                size="22"
              />
            </VAvatar>
          </template>

          <VListItemTitle class="font-weight-medium text-body-1">
            {{ localizedValue(doc.title) || doc.file_name || 'Untitled' }}
          </VListItemTitle>
          <VListItemSubtitle class="mt-1">
            <span v-if="doc.reference_number">{{ doc.reference_number }} · </span>
            <span v-if="doc.file_size_bytes">{{ (doc.file_size_bytes / 1024).toFixed(1) }} KB</span>
            <span v-if="doc.page_count"> · {{ doc.page_count }} {{ $t('wp.document.pages_count') }}</span>
            <span v-if="doc.expiration_date"> · {{ $t('Expires') }}: {{ doc.expiration_date }}</span>
            <span v-if="doc.source"> · {{ $t('wp.document.source_' + doc.source) || doc.source }}</span>

            <!-- Mobile Chips -->
            <div class="d-flex d-md-none flex-wrap align-center gap-2 mt-2">
              <VChip
                :color="resolveDocTypeVariant(doc.type)"
                size="small"
                label
                class="text-capitalize"
              >
                {{ doc.type?.replace('_', ' ') }}
              </VChip>
              <VChip
                :color="resolveDocStatusVariant(doc.status)"
                size="small"
                label
                class="text-capitalize"
              >
                {{ doc.status?.replace('_', ' ') }}
              </VChip>
              <VChip
                v-if="doc.is_verified"
                color="success"
                size="small"
                label
              >
                <VIcon
                  icon="tabler-circle-check"
                  size="14"
                  start
                />
                {{ $t('Verified') }}
              </VChip>
            </div>
          </VListItemSubtitle>

          <template #append>
            <div class="d-flex align-center gap-1">
              <!-- Desktop Chips -->
              <div class="d-none d-md-flex align-center gap-1">
                <VChip
                  :color="resolveDocTypeVariant(doc.type)"
                  size="small"
                  label
                  class="text-capitalize"
                >
                  {{ doc.type?.replace('_', ' ') }}
                </VChip>
                <VChip
                  :color="resolveDocStatusVariant(doc.status)"
                  size="small"
                  label
                  class="text-capitalize"
                >
                  {{ doc.status?.replace('_', ' ') }}
                </VChip>
                <VChip
                  v-if="doc.is_verified"
                  color="success"
                  size="small"
                  label
                >
                  <VIcon
                    icon="tabler-circle-check"
                    size="14"
                    start
                  />
                  {{ $t('Verified') }}
                </VChip>
              </div>

              <!-- Action Menu -->
              <VMenu location="bottom end">
                <template #activator="{ props: menuProps }">
                  <IconBtn v-bind="menuProps">
                    <VIcon icon="tabler-dots-vertical" />
                  </IconBtn>
                </template>
                <VList density="compact">
                  <VListItem
                    v-if="doc.file_url"
                    prepend-icon="tabler-eye"
                    :title="$t('Preview')"
                    @click="handlePreview(doc)"
                  />
                  <VListItem
                    v-if="doc.file_url"
                    :href="doc.file_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    prepend-icon="tabler-external-link"
                    :title="$t('Open in New Tab')"
                  />
                  <VListItem
                    v-if="doc.download_url"
                    prepend-icon="tabler-download"
                    :title="$t('Download')"
                    @click="handleDownload(doc)"
                  />
                  <VDivider
                    v-if="!readOnly"
                    class="my-1"
                  />
                  <VListItem
                    v-if="!readOnly"
                    prepend-icon="tabler-edit"
                    :title="$t('Edit')"
                    @click="handleEdit(doc)"
                  />
                  <VListItem
                    v-if="!readOnly"
                    prepend-icon="tabler-trash"
                    :title="$t('Delete')"
                    class="text-error"
                    @click="openDeleteDialog(doc)"
                  />
                </VList>
              </VMenu>
            </div>
          </template>
        </VListItem>
      </VList>

      <!-- 👉 Empty state -->
      <div
        v-if="documentData.length === 0"
        class="text-center py-8"
      >
        <VIcon
          icon="tabler-file-off"
          size="48"
          color="disabled"
          class="mb-4"
        />
        <h6 class="text-h6 text-disabled">
          {{ $t('No documents uploaded yet') }}
        </h6>
      </div>

      <!-- 👉 Pagination -->
      <div class="d-flex justify-center mt-6">
        <VPagination
          v-if="totalPages > 0"
          v-model="page"
          size="small"
          :length="totalPages"
          :total-visible="5"
        />
      </div>
    </VCardText>
  </VCard>

  <!-- 👉 Dialogs -->
  <WpDocumentDialog
    v-if="!readOnly"
    v-model:model-value="isDocDialogVisible"
    :title="isEditMode ? $t('Edit Document') : $t('Add New Document')"
    :initial-data="currentDocument"
    preset="bp"
    @save="handleSubmit"
    @error="error => showError(error?.message || t('business_partners.documents.process_error', 'Unable to process document'))"
  />
  <WpMediaViewerDialog
    v-model:model-value="isViewerOpen"
    :file="viewerFile"
  />
  <DeleteDocumentDialog
    v-if="!readOnly"
    v-model:is-dialog-visible="isDeleteDialogVisible"
    :document-id="docToDelete"
    @delete-document="handleDelete"
  />
</template>