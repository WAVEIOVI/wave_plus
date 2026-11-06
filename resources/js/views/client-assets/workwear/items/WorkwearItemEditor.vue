<script setup>
/* eslint-disable camelcase */
import {
  AppBottomNav,
  AppQuickActionsSheet,
  WpDeleteDialog,
  WpImageDialog,
  WpJournalNav,
  WpMediaViewerDialog,
  WpUnsavedChangesDialog,
} from '@/components/shared'
import AppBreadcrumb from '@/components/shared/AppBreadcrumb.vue'
import { useWorkwearItemEdit } from '@/composables/client-assets/workwear/useWorkwearItemEdit'
import { useUnsavedChangesGuard } from '@/composables/dialog/useUnsavedChangesGuard'
import AssignItemDialog from '@/views/client-assets/workwear/items/components/AssignItemDialog.vue'
import FlagStolenItemDialog from '@/views/client-assets/workwear/items/components/FlagStolenItemDialog.vue'
import WorkwearItemBioSection from '@/views/client-assets/workwear/items/components/WorkwearItemBioSection.vue'
import WorkwearItemHistorySection from '@/views/client-assets/workwear/items/components/WorkwearItemHistorySection.vue'
import WorkwearItemMediaSection from '@/views/client-assets/workwear/items/components/WorkwearItemMediaSection.vue'
import WorkwearItemNotesSection from '@/views/client-assets/workwear/items/components/WorkwearItemNotesSection.vue'
import { computed, nextTick, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

const props = defineProps({
  id: { type: [String, Number], default: null },
})

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const { smAndDown } = useDisplay()

const itemId = computed(() => props.id || route.params.id)
const listRoute = { name: 'client-assets-workwear-list', query: { tab: 'items' } }
const quickActionsSheetOpen = ref(false)

const {
  loading,
  saving,
  error,
  itemData,
  activeSection,
  supplierOptions,
  modelStockMatrix,
  employeeOptions,
  assignments,
  statusMap,
  conditionOptions,
  statusOptions,
  isDirty,
  fieldErrors,
  documentsLoading,
  salesOrderItems,
  deliveryNoteItems,

  // Dialogs
  isImageDialogOpen,
  isUploadingPhoto,
  isViewerOpen,
  viewerFile,
  isAssignDialogOpen,
  isStolenDialogOpen,
  isDeleteDialogVisible,
  assignForm,
  stolenForm,

  // Actions
  fetchItem,
  handleUpdate,
  handleDelete,
  handleUploadPhoto,
  handleDeletePhoto,
  handlePreviewPhoto,
  openAssignDialog,
  handleAssign,
  openStolenDialog,
  handleFlagStolen,
  handleQr,
  loadSupplierOptions,
} = useWorkwearItemEdit(itemId)

const { unsavedDialogVisible, confirmLeave } = useUnsavedChangesGuard(isDirty)

const sections = computed(() => [
  { id: 'bio', label: t('General Info & SKU'), icon: 'tabler-info-circle' },
  { id: 'media', label: t('Product Media & Model'), icon: 'tabler-photo', badge: itemData.value?.photos?.length || undefined },
  { id: 'history', label: t('Assignment History'), icon: 'tabler-history', badge: assignments.value.length || undefined },
  { id: 'notes', label: t('Notes'), icon: 'tabler-notes' },
])

const breadcrumbItems = computed(() => [
  { title: t('Home'), to: '/dashboard', icon: 'tabler-home' },
  { title: t('Client Assets'), to: listRoute, icon: 'tabler-shirt' },
  { title: t('Workwear Items'), to: listRoute, icon: 'tabler-hanger' },
  { title: itemData.value?.item_code || t('Edit Item'), icon: 'tabler-edit' },
])

const scrollToSection = sectionId => {
  activeSection.value = sectionId

  const el = document.getElementById(`section-${sectionId}`)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const quickActions = computed(() => [
  {
    id: 'save-item',
    title: t('Save Changes'),
    icon: 'tabler-device-floppy',
    color: 'primary',
    disabled: saving.value || !isDirty.value,
    onClick: handleUpdate,
  },
  {
    id: 'assign-item',
    title: t('Assign to Staff'),
    icon: 'tabler-user-plus',
    color: 'info',
    disabled: itemData.value?.status === 'assigned',
    onClick: openAssignDialog,
  },
  {
    id: 'qr-code',
    title: t('Download QR'),
    icon: 'tabler-qrcode',
    color: 'warning',
    onClick: handleQr,
  },
  {
    id: 'flag-stolen',
    title: t('Flag Stolen'),
    icon: 'tabler-alert-octagon',
    color: 'error',
    onClick: openStolenDialog,
  },
  {
    id: 'delete-item',
    title: t('Delete Item'),
    icon: 'tabler-trash',
    color: 'error',
    onClick: () => { isDeleteDialogVisible.value = true },
  },
])

onMounted(async () => {
  await fetchItem()
  loadSupplierOptions()

  const observer = new IntersectionObserver(
    entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          activeSection.value = entry.target.id.replace('section-', '')
        }
      })
    },
    { threshold: 0.2, rootMargin: '-80px 0px -50% 0px' },
  )

  nextTick(() => {
    sections.value.forEach(s => {
      const el = document.getElementById(`section-${s.id}`)
      if (el) observer.observe(el)
    })
  })
})
</script>

<template>
  <div>
    <!-- 👉 Breadcrumb -->
    <AppBreadcrumb
      :items="breadcrumbItems"
      class="mb-4"
    />

    <!-- 👉 Loading state -->
    <VSkeletonLoader
      v-if="loading && !itemData"
      type="card-avatar, article, list-item-two-line"
      class="mb-6 w-100 bg-transparent"
    />

    <!-- 👉 Error banner -->
    <VAlert
      v-else-if="error"
      type="error"
      variant="tonal"
      class="mb-6"
    >
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <span>{{ error }}</span>
        <VBtn
          size="small"
          variant="tonal"
          color="error"
          prepend-icon="tabler-refresh"
          @click="fetchItem"
        >
          {{ $t('Retry') }}
        </VBtn>
      </div>
    </VAlert>

    <!-- 👉 Journal Layout -->
    <div
      v-else-if="itemData"
      class="journal-layout"
    >
      <!-- Desktop Navigation Sidebar -->
      <WpJournalNav
        v-if="!smAndDown"
        v-model="activeSection"
        :sections="sections"
        :title="itemData.item_code"
        :subtitle="itemData.sku || itemData.model?.name || ''"
        id-prefix="section-"
        @update:model-value="scrollToSection"
      >
        <template #avatar>
          <VAvatar
            rounded="lg"
            :size="40"
            :color="itemData.is_stolen ? 'error' : 'primary'"
            variant="tonal"
          >
            <VIcon
              :icon="itemData.is_stolen ? 'tabler-alert-octagon' : 'tabler-hanger'"
              size="22"
            />
          </VAvatar>
        </template>

        <template #footer>
          <div class="pt-4">
            <div class="d-flex gap-1 mb-3 flex-wrap">
              <VChip
                v-if="itemData.is_stolen"
                color="error"
                label
                size="x-small"
              >
                STOLEN
              </VChip>
              <VChip
                :color="statusMap[itemData.status] || 'secondary'"
                size="x-small"
                label
                class="text-capitalize"
              >
                {{ itemData.status?.replace(/_/g, ' ') }}
              </VChip>
            </div>

            <VDivider class="mb-4" />

            <!-- Secondary Actions -->
            <div class="d-flex flex-column gap-2 mb-4">
              <VBtn
                block
                color="info"
                variant="tonal"
                prepend-icon="tabler-user-plus"
                :disabled="itemData.status === 'assigned'"
                @click="openAssignDialog"
              >
                {{ $t('Assign to Staff') }}
              </VBtn>
              <VBtn
                block
                color="warning"
                variant="tonal"
                prepend-icon="tabler-qrcode"
                @click="handleQr"
              >
                {{ $t('Download QR') }}
              </VBtn>
              <VBtn
                v-if="!itemData.is_stolen"
                block
                color="error"
                variant="tonal"
                prepend-icon="tabler-alert-octagon"
                @click="openStolenDialog"
              >
                {{ $t('Flag Stolen') }}
              </VBtn>
            </div>

            <div
              v-if="isDirty"
              class="d-flex align-center gap-2 mb-3 text-caption text-warning"
              role="status"
            >
              <VIcon
                icon="tabler-point-filled"
                size="16"
              />
              {{ $t('Unsaved changes') }}
            </div>

            <!-- Primary Commit Actions -->
            <VBtn
              block
              color="primary"
              :disabled="saving || !isDirty"
              :loading="saving"
              class="mb-2 font-weight-semibold"
              @click="handleUpdate"
            >
              <VIcon
                start
                icon="tabler-device-floppy"
              />
              {{ $t('Save Changes') }}
            </VBtn>

            <VBtn
              block
              variant="text"
              color="error"
              size="small"
              @click="isDeleteDialogVisible = true"
            >
              <VIcon
                start
                icon="tabler-trash"
              />
              {{ $t('Delete Item') }}
            </VBtn>
          </div>
        </template>
      </WpJournalNav>

      <!-- Main Feed -->
      <main class="journal-feed">
        <!-- Mobile Header Bar -->
        <div
          v-if="smAndDown"
          class="d-flex align-center gap-3 w-100 mb-2"
        >
          <VAvatar
            rounded="lg"
            :size="40"
            :color="itemData.is_stolen ? 'error' : 'primary'"
            variant="tonal"
          >
            <VIcon
              :icon="itemData.is_stolen ? 'tabler-alert-octagon' : 'tabler-hanger'"
              size="22"
            />
          </VAvatar>
          <div class="min-w-0 flex-grow-1">
            <h1 class="text-subtitle-1 font-weight-bold text-truncate mb-0">
              {{ itemData.item_code }}
            </h1>
            <span class="text-caption font-mono text-disabled">{{ itemData.sku || itemData.model?.name }}</span>
          </div>
          <VBtn
            color="primary"
            size="small"
            :loading="saving"
            :disabled="saving || !isDirty"
            @click="handleUpdate"
          >
            {{ $t('Save') }}
          </VBtn>
        </div>

        <!-- Section 1: General Info & SKU -->
        <WorkwearItemBioSection
          :item-data="itemData"
          :supplier-options="supplierOptions"
          :sales-order-items="salesOrderItems"
          :delivery-note-items="deliveryNoteItems"
          :documents-loading="documentsLoading"
          :status-options="statusOptions"
          :condition-options="conditionOptions"
          :field-errors="fieldErrors"
        />

        <!-- Section 2: Product Media & Linked Model -->
        <WorkwearItemMediaSection
          :item-data="itemData"
          :model-stock-matrix="modelStockMatrix"
          :is-uploading-photo="isUploadingPhoto"
          @open-photo-dialog="isImageDialogOpen = true"
          @preview-photo="handlePreviewPhoto"
          @delete-photo="handleDeletePhoto"
        />

        <!-- Section 3: Assignment History -->
        <WorkwearItemHistorySection
          :assignments="assignments"
          :item-data="itemData"
          @assign="openAssignDialog"
        />

        <!-- Section 4: Notes -->
        <WorkwearItemNotesSection :item-data="itemData" />
      </main>
    </div>

    <!-- Mobile Navigation Bar -->
    <AppBottomNav
      v-if="smAndDown && itemData"
      v-model="activeSection"
      :items="sections"
      @update:model-value="scrollToSection"
    />

    <!-- Mobile Quick Actions FAB -->
    <div
      v-if="smAndDown && itemData"
      class="workwear-edit-quick-actions-launcher"
    >
      <VBtn
        icon="tabler-bolt"
        color="primary"
        elevation="6"
        size="large"
        @click="quickActionsSheetOpen = true"
      />
    </div>

    <AppQuickActionsSheet
      v-model="quickActionsSheetOpen"
      :title="$t('Quick Actions')"
      :actions="quickActions"
    />

    <!-- Dialogs -->
    <AssignItemDialog
      v-model="isAssignDialogOpen"
      :form="assignForm"
      :employee-options="employeeOptions"
      :loading="saving"
      @submit="handleAssign"
    />

    <FlagStolenItemDialog
      v-model="isStolenDialogOpen"
      :form="stolenForm"
      :loading="saving"
      @submit="handleFlagStolen"
    />

    <WpDeleteDialog
      v-model:is-open="isDeleteDialogVisible"
      :title="$t('Delete Workwear Item')"
      :message="$t('Are you sure you want to delete item {code}? This action cannot be undone.', { code: itemData?.item_code || '' })"
      :item-name="itemData?.item_code"
      @confirm="handleDelete"
    />

    <WpUnsavedChangesDialog
      v-model="unsavedDialogVisible"
      @confirm="confirmLeave"
      @cancel="unsavedDialogVisible = false"
    />

    <WpImageDialog
      v-model="isImageDialogOpen"
      :title="$t('Upload Garment Product Photo')"
      show-title-input
      @save="handleUploadPhoto"
    />

    <WpMediaViewerDialog
      v-model:model-value="isViewerOpen"
      :file="viewerFile"
    />
  </div>
</template>

<style lang="scss" scoped>
.journal-layout {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: 1.5rem;
  min-block-size: 100vh;

  @media (max-width: 960px) {
    flex-direction: column;
    padding-block-end: calc(96px + env(safe-area-inset-bottom, 0px));
  }
}

.journal-feed {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 1.5rem;
  inline-size: 100%;
  min-inline-size: 0;
  padding-block-end: 2rem;

  @media (max-width: 600px) {
    gap: 1rem;
  }
}

.workwear-edit-quick-actions-launcher {
  position: fixed;
  z-index: 1001;
  inset-block-end: calc(76px + env(safe-area-inset-bottom, 0px));
  inset-inline-end: 20px;
}
</style>
