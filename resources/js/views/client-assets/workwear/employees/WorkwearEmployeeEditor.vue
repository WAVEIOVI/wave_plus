<script setup>
/* eslint-disable camelcase */
import {
  AppQuickActionsSheet,
  WpDeleteDialog,
  WpJournalNav,
  WpKpiCard,
  WpKpiGroup,
  WpUnsavedChangesDialog,
} from '@/components/shared'
import AppBreadcrumb from '@/components/shared/AppBreadcrumb.vue'
import { useWorkwearEmployeeEdit } from '@/composables/client-assets/workwear/useWorkwearEmployeeEdit'
import { useUnsavedChangesGuard } from '@/composables/dialog/useUnsavedChangesGuard'
import IssueEmployeeGarmentsDialog from '@/views/client-assets/workwear/employees/components/IssueEmployeeGarmentsDialog.vue'
import WorkwearEmployeeBioSection from '@/views/client-assets/workwear/employees/components/WorkwearEmployeeBioSection.vue'
import WorkwearEmployeeHistorySection from '@/views/client-assets/workwear/employees/components/WorkwearEmployeeHistorySection.vue'
import WorkwearEmployeeNotesSection from '@/views/client-assets/workwear/employees/components/WorkwearEmployeeNotesSection.vue'
import WorkwearEmployeeRequirementsSection from '@/views/client-assets/workwear/employees/components/WorkwearEmployeeRequirementsSection.vue'
import WorkwearEmployeeSizesSection from '@/views/client-assets/workwear/employees/components/WorkwearEmployeeSizesSection.vue'
import ReturnWorkwearDialog from '@/views/client-assets/workwear/items/list/ReturnWorkwearDialog.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useDisplay } from 'vuetify'

const props = defineProps({
  id: { type: [String, Number], default: null },
})

const route = useRoute()
const { t } = useI18n()
const { smAndDown } = useDisplay()

const employeeId = computed(() => props.id || route.params.id)
const listRoute = { name: 'client-assets-workwear-list', query: { tab: 'employees' } }
const quickActionsSheetOpen = ref(false)

const {
  loading,
  saving,
  error,
  employeeData,
  activeSection,
  assignmentTab,
  clientModels,
  isIssueDialogOpen,
  isIssuingItems,
  isReturnDialogOpen,
  selectedReturnItem,
  isDeleteDialogVisible,
  issueForm,
  garmentSizeFields,
  measurementFields,
  garmentTypeOptions,
  statusMap,
  statusOptions,
  returnActionColor,
  currentAssignments,
  returnedAssignments,
  isDirty,
  fieldErrors,
  coverageFor,
  filteredAvailableItems,

  // Actions
  fetchEmployee,
  addRequirement,
  removeRequirement,
  openIssueGarmentDialog,
  confirmIssueGarments,
  openReturn,
  onReturnSuccess,
  handleUpdate,
  handleDelete,
} = useWorkwearEmployeeEdit(employeeId)

const { unsavedDialogVisible, confirmLeave } = useUnsavedChangesGuard(isDirty)

const sections = computed(() => [
  { id: 'bio', label: t('Basic Information'), icon: 'tabler-info-circle' },
  { id: 'sizes', label: t('Sizes & Measurements'), icon: 'tabler-ruler-measure' },
  { id: 'history', label: t('Issued Workwear Items'), icon: 'tabler-hanger', badge: currentAssignments.value.length || undefined },
  { id: 'requirements', label: t('Workwear Requirements'), icon: 'tabler-checklist', badge: employeeData.value?.workwear_requirements?.length || undefined },
  { id: 'notes', label: t('Notes'), icon: 'tabler-notes' },
])

const breadcrumbItems = computed(() => [
  { title: t('Home'), to: '/dashboard', icon: 'tabler-home' },
  { title: t('Client Assets'), to: listRoute, icon: 'tabler-shirt' },
  { title: t('Client Employees'), to: listRoute, icon: 'tabler-users' },
  { title: employeeData.value?.first_name ? `${employeeData.value.first_name} ${employeeData.value.last_name || ''}` : t('Edit Employee'), icon: 'tabler-edit' },
])

const quickActions = computed(() => [
  {
    id: 'save-employee',
    title: t('Save Changes'),
    icon: 'tabler-device-floppy',
    color: 'primary',
    disabled: saving.value || !isDirty.value,
    onClick: handleUpdate,
  },
  {
    id: 'issue-garment',
    title: t('Assign Workwear Item'),
    icon: 'tabler-package-export',
    color: 'success',
    onClick: () => openIssueGarmentDialog(null),
  },
  {
    id: 'delete-employee',
    title: t('Delete Employee'),
    icon: 'tabler-trash',
    color: 'error',
    onClick: () => { isDeleteDialogVisible.value = true },
  },
])

onMounted(async () => {
  await fetchEmployee()
})
</script>

<template>
  <div class="workwear-employee-editor-page">
    <!-- 👉 Breadcrumbs -->
    <AppBreadcrumb
      :items="breadcrumbItems"
      class="mb-4"
    />

    <!-- 👉 Loading state -->
    <VSkeletonLoader
      v-if="loading && !employeeData"
      type="card-avatar, article, list-item-two-line"
      class="mb-6 w-100 bg-transparent"
    />

    <!-- 👉 Error Banner -->
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
          @click="fetchEmployee"
        >
          {{ $t('Retry') }}
        </VBtn>
      </div>
    </VAlert>

    <template v-else-if="employeeData">
      <!-- 👉 Top KPI Strip (Standardized with Client Site & Equipment Edit) -->
      <WpKpiGroup
        :item-count="4"
        class="mb-6"
      >
        <template #default="{ getColProps }">
          <VCol v-bind="getColProps(0)">
            <WpKpiCard
              :title="$t('Employee Reference')"
              :value="employeeData.employee_ref || '—'"
              icon="tabler-id-badge-2"
              icon-color="primary"
            />
          </VCol>
          <VCol v-bind="getColProps(1)">
            <WpKpiCard
              :title="$t('Client Account')"
              :value="employeeData.client?.legal_name || employeeData.client?.name || '—'"
              icon="tabler-building"
              icon-color="info"
            />
          </VCol>
          <VCol v-bind="getColProps(2)">
            <WpKpiCard
              :title="$t('Department & Position')"
              :value="[employeeData.department, employeeData.position].filter(Boolean).join(' · ') || '—'"
              icon="tabler-briefcase"
              icon-color="secondary"
            />
          </VCol>
          <VCol v-bind="getColProps(3)">
            <WpKpiCard
              :title="$t('Issued Workwear Items')"
              :value="currentAssignments.length"
              icon="tabler-hanger"
              icon-color="success"
            />
          </VCol>
        </template>
      </WpKpiGroup>

      <!-- 👉 Main Workspace -->
      <div class="journal-layout">
        <!-- Navigation Sidebar + Mobile Drawer handled by WpJournalNav -->
        <WpJournalNav
          v-model="activeSection"
          :sections="sections"
          :title="`${employeeData.first_name || ''} ${employeeData.last_name || ''}`"
          :subtitle="employeeData.employee_ref || employeeData.position || employeeData.department || ''"
          id-prefix="section-"
        >
          <template #avatar>
            <VAvatar
              rounded="lg"
              :size="40"
              color="primary"
              variant="tonal"
            >
              <span class="text-subtitle-2 font-weight-bold">
                {{ (employeeData.first_name?.[0] || '') + (employeeData.last_name?.[0] || '') }}
              </span>
            </VAvatar>
          </template>

          <template #footer>
            <div class="pt-4">
              <div class="d-flex gap-1 mb-3 flex-wrap">
                <VChip
                  :color="statusMap[employeeData.status] || 'secondary'"
                  size="x-small"
                  label
                  class="text-capitalize"
                >
                  {{ $t(employeeData.status || 'active') }}
                </VChip>
                <VChip
                  size="x-small"
                  label
                  variant="tonal"
                  color="success"
                >
                  {{ currentAssignments.length }} {{ $t('items issued') }}
                </VChip>
              </div>

              <VDivider class="mb-4" />

              <!-- Actions Menu -->
              <div class="d-flex flex-column gap-2 mb-4">
                <VBtn
                  block
                  color="success"
                  variant="tonal"
                  prepend-icon="tabler-package-export"
                  @click="openIssueGarmentDialog(null)"
                >
                  {{ $t('Assign Workwear Item') }}
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
                {{ $t('Delete Employee') }}
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
              color="primary"
              variant="tonal"
            >
              <span class="text-subtitle-2 font-weight-bold">
                {{ (employeeData.first_name?.[0] || '') + (employeeData.last_name?.[0] || '') }}
              </span>
            </VAvatar>
            <div class="min-w-0 flex-grow-1">
              <h1 class="text-subtitle-1 font-weight-bold text-truncate mb-0">
                {{ employeeData.first_name }} {{ employeeData.last_name }}
              </h1>
              <span class="text-caption font-mono text-disabled">{{ employeeData.employee_ref || employeeData.department }}</span>
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

          <!-- 1. Basic Information -->
          <WorkwearEmployeeBioSection
            :employee-data="employeeData"
            :status-options="statusOptions"
            :field-errors="fieldErrors"
          />

          <!-- 2. Sizes & Measurements -->
          <WorkwearEmployeeSizesSection
            :employee-data="employeeData"
            :garment-size-fields="garmentSizeFields"
            :measurement-fields="measurementFields"
            :field-errors="fieldErrors"
          />

          <!-- 3. Issued Workwear Items & Handover History (Attached strictly via WorkwearItem -> WorkwearModel) -->
          <WorkwearEmployeeHistorySection
            v-model:assignment-tab="assignmentTab"
            :current-assignments="currentAssignments"
            :returned-assignments="returnedAssignments"
            :return-action-color="returnActionColor"
            @issue-garment="openIssueGarmentDialog"
            @return-assignment="openReturn"
          />

          <!-- 4. Workwear Requirements -->
          <WorkwearEmployeeRequirementsSection
            :employee-data="employeeData"
            :garment-type-options="garmentTypeOptions"
            :coverage-for="coverageFor"
            :field-errors="fieldErrors"
            @add-requirement="addRequirement"
            @remove-requirement="removeRequirement"
          />

          <!-- 5. Internal Notes -->
          <WorkwearEmployeeNotesSection
            :employee-data="employeeData"
            :field-errors="fieldErrors"
          />
        </main>
      </div>
    </template>

    <!-- Mobile Quick Actions Launcher -->
    <div
      v-if="smAndDown && employeeData"
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
    <IssueEmployeeGarmentsDialog
      v-model="isIssueDialogOpen"
      :issue-form="issueForm"
      :client-models="clientModels"
      :filtered-available-items="filteredAvailableItems"
      :loading="isIssuingItems"
      @submit="confirmIssueGarments"
    />

    <ReturnWorkwearDialog
      v-model:is-dialog-visible="isReturnDialogOpen"
      :item="selectedReturnItem"
      @returned="onReturnSuccess"
    />

    <WpDeleteDialog
      v-model:is-open="isDeleteDialogVisible"
      :title="$t('Delete Workwear Employee')"
      :message="$t('Are you sure you want to delete employee {name}? All issued garments and assignment history will be unlinked.', { name: `${employeeData?.first_name || ''} ${employeeData?.last_name || ''}` })"
      :item-name="employeeData?.first_name"
      @confirm="handleDelete"
    />

    <WpUnsavedChangesDialog
      v-model="unsavedDialogVisible"
      @confirm="confirmLeave"
      @cancel="unsavedDialogVisible = false"
    />
  </div>
</template>

<style lang="scss" scoped>
.workwear-employee-editor-page {
  inline-size: 100%;
  max-inline-size: 100%;
  overflow-x: clip;
}

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
