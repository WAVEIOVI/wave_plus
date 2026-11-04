<script setup>
/* eslint-disable camelcase */
import { useCookie } from '@/@core/composable/useCookie'
import {
  AppFabSpeedDial,
  AppHybridTabs,
  WpDeleteDialog,
  WpDetailDialog,
  WpKpiCard,
  WpKpiGroup,
  WpMobileCard,
  WpPageHeader,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
} from '@/components/shared'
import { useAppraisalActions } from '@/composables/people-skills/appraisals/useAppraisalActions'
import { useAppraisalDialogs } from '@/composables/people-skills/appraisals/useAppraisalDialogs'
import { useAppraisalList } from '@/composables/people-skills/appraisals/useAppraisalList'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import AppraisalCycleDialog from '@/views/people-skills/performance/AppraisalCycleDialog.vue'
import AppraisalReviewDialog from '@/views/people-skills/performance/AppraisalReviewDialog.vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

definePage({
  path: '/people-skills/performance/appraisals',
  name: 'people-skills-performance-appraisals',
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'read',
    subject: 'appraisals',
  },
})

const { t } = useI18n()
const ability = useAbility()
const currentUser = useCookie('userData').value

const canManage = computed(() => ability.can('manage', 'appraisals') || ability.can('create', 'PeopleSkills') || ability.can('manage', 'all'))
const canDelete = computed(() => ability.can('delete', 'appraisals') || ability.can('manage', 'PeopleSkills') || ability.can('manage', 'all'))

// ── List Orchestration ──────────────────────────────────────────────────────
const {
  store,
  hrStore,
  employeeStore,
  currentTab,
  tabs,
  search,
  selectedCycleFilter,
  cycleHeaders,
  appraisalHeaders,
  filteredCycles,
  filteredAppraisals,
  kpis,
  formatDate,
  cycleColor,
  appraisalColor,
  loadData,
} = useAppraisalList()

// ── Dialog State ────────────────────────────────────────────────────────────
const dialogs = useAppraisalDialogs()

// ── Actions ─────────────────────────────────────────────────────────────────
const actions = useAppraisalActions({ dialogs })

// Acknowledgement in detail dialog
const acknowledgementFeedback = ref('')

// ── Options for Dropdowns ───────────────────────────────────────────────────
const cycleOptions = computed(() =>
  store.cycles.map(c => ({
    title: `${c.name} (${c.cycle_type})`,
    value: c.id,
  })),
)

// Active employees from employeeStore (id is hr_employees.id)
const employeeOptions = computed(() =>
  (employeeStore.list || []).map(e => ({
    title: e.full_name || `${e.first_name || ''} ${e.last_name || ''}`.trim() || e.id,
    value: e.id,
  })),
)

// Reviewers / Managers from hrStore availableUsers (id is users.id)
const reviewerOptions = computed(() =>
  (hrStore.availableUsers || []).map(u => ({
    title: u.name || u.email,
    value: u.id,
  })),
)

// ── Row Actions ─────────────────────────────────────────────────────────────
const getCycleRowActions = item => {
  const acts = []
  if (canManage.value) {
    acts.push({ label: t('common.edit', 'Edit'), icon: 'tabler-edit', emit: 'edit' })
    acts.push({ label: t('appraisals.record_review', 'Record Evaluation'), icon: 'tabler-file-plus', emit: 'add-review' })
  }
  if (canDelete.value) {
    acts.push({ label: t('common.delete', 'Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: acts.length > 0 })
  }

  return acts
}

const getAppraisalRowActions = item => {
  const acts = [
    { label: t('common.view', 'View Details'), icon: 'tabler-eye', emit: 'view' },
  ]

  const isFinalized = item.status === 'acknowledged'
  if (!isFinalized && canManage.value) {
    acts.push({ label: t('common.edit', 'Edit'), icon: 'tabler-edit', emit: 'edit' })
  }

  const canAck = !isFinalized && ['submitted', 'reviewed'].includes(item.status) && (
    canManage.value || currentUser?.id === item.employee?.user_id
  )

  if (canAck) {
    acts.push({ label: t('appraisals.acknowledge', 'Acknowledge'), icon: 'tabler-circle-check', emit: 'view' })
  }

  if (!isFinalized && canDelete.value) {
    acts.push({ label: t('common.delete', 'Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true })
  }

  return acts
}

const canAcknowledgeCurrent = computed(() => {
  const item = dialogs.selectedItem.value
  if (!item || item.status === 'acknowledged') return false
  if (!['submitted', 'reviewed'].includes(item.status)) return false

  return canManage.value || currentUser?.id === item.employee?.user_id
})

const handleDetailAcknowledge = async () => {
  const item = dialogs.selectedItem.value
  if (!item) return
  await actions.handleAcknowledgeAppraisal(item.id, {
    employee_feedback: acknowledgementFeedback.value || undefined,
  })
  acknowledgementFeedback.value = ''
  await loadData()
}

// ── Mobile FAB ──────────────────────────────────────────────────────────────
const mobileFabActions = computed(() => {
  if (!canManage.value) return []

  return [
    {
      id: 'create-appraisal',
      icon: 'tabler-file-plus',
      title: t('appraisals.record_appraisal', 'Record Appraisal'),
      onClick: () => dialogs.openReview(),
    },
    {
      id: 'create-cycle',
      icon: 'tabler-calendar-plus',
      title: t('appraisals.create_cycle', 'New Cycle'),
      onClick: () => dialogs.openCycle(),
    },
  ]
})
</script>

<template>
  <div class="people-skills-performance-appraisals d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="$t('appraisals.title', 'Performance & Appraisals')"
      :subtitle="$t('appraisals.subtitle', 'Continuous evaluations, periodic appraisal cycles, and employee performance reviews.')"
      icon="tabler-chart-bar"
    >
      <template #actions>
        <VBtn
          v-if="canManage"
          variant="outlined"
          color="secondary"
          prepend-icon="tabler-calendar-plus"
          @click="dialogs.openCycle()"
        >
          {{ $t('appraisals.create_cycle', 'New Cycle') }}
        </VBtn>
        <VBtn
          v-if="canManage"
          color="primary"
          prepend-icon="tabler-plus"
          @click="dialogs.openReview()"
        >
          {{ $t('appraisals.create_appraisal', 'Record Appraisal') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <!-- 👉 KPI Cards -->
    <WpKpiGroup>
      <VRow>
        <VCol
          v-for="kpi in kpis"
          :key="kpi.title"
          cols="12"
          sm="6"
          md="3"
        >
          <WpKpiCard
            :title="kpi.title"
            :value="kpi.value"
            :icon="kpi.icon"
            :color="kpi.color"
          />
        </VCol>
      </VRow>
    </WpKpiGroup>

    <!-- 👉 Hybrid Tabs -->
    <AppHybridTabs
      v-model="currentTab"
      :tabs="tabs"
      class="appraisal-list__tabs"
    >
      <!-- ── Tab: Review Cycles ──────────────────────────────────────────── -->
      <template #tab-cycles>
        <VCard>
          <WpPageToolbar
            v-model:search="search"
            :create-label="canManage ? $t('appraisals.create_cycle', 'New Cycle') : null"
            :loading="store.loading"
            @create="dialogs.openCycle()"
            @refresh="loadData"
          />

          <WpResponsiveList
            :headers="cycleHeaders"
            :items="filteredCycles"
            :total-items="filteredCycles.length"
            :loading="store.loading"
            hide-pagination
            @retry="loadData"
          >
            <template #item.name="{ item }">
              <div class="d-flex flex-column py-1">
                <span class="text-body-2 font-weight-medium">{{ item.name }}</span>
                <span class="text-caption text-medium-emphasis">
                  {{ formatDate(item.start_date) }} – {{ formatDate(item.end_date) }}
                </span>
              </div>
            </template>

            <template #item.cycle_type="{ item }">
              <VChip
                color="info"
                size="small"
                label
                class="text-capitalize"
              >
                {{ item.cycle_type }}
              </VChip>
            </template>

            <template #item.period="{ item }">
              <span class="text-body-2">{{ formatDate(item.start_date) }} – {{ formatDate(item.end_date) }}</span>
            </template>

            <template #item.appraisals_count="{ item }">
              <VChip
                color="primary"
                size="small"
                variant="tonal"
              >
                {{ item.appraisals_count || 0 }} {{ $t('appraisals.reviews', 'reviews') }}
              </VChip>
            </template>

            <template #item.status="{ item }">
              <VChip
                :color="cycleColor(item.status)"
                size="small"
                label
                class="text-capitalize"
              >
                {{ item.status }}
              </VChip>
            </template>

            <template #item.actions="{ item }">
              <WpRowActions
                :actions="getCycleRowActions(item)"
                @edit="dialogs.openCycle(item)"
                @add-review="dialogs.openReview({ appraisal_cycle_id: item.id })"
                @delete="dialogs.openDelete(item, 'cycle')"
              />
            </template>

            <template #mobile-card="{ item }">
              <WpMobileCard
                :title="item.name"
                :subtitle="`${item.cycle_type} · ${formatDate(item.start_date)}`"
                :badge="item.status"
                :badge-color="cycleColor(item.status)"
                :actions="getCycleRowActions(item)"
                @edit="dialogs.openCycle(item)"
                @add-review="dialogs.openReview({ appraisal_cycle_id: item.id })"
                @delete="dialogs.openDelete(item, 'cycle')"
              >
                <div class="d-flex align-center gap-2 text-caption text-medium-emphasis mt-2">
                  <VIcon
                    icon="tabler-file-analytics"
                    size="16"
                  />
                  <span>{{ item.appraisals_count || 0 }} {{ $t('appraisals.reviews', 'reviews') }}</span>
                </div>
              </WpMobileCard>
            </template>
          </WpResponsiveList>
        </VCard>
      </template>

      <!-- ── Tab: Employee Appraisals ────────────────────────────────────── -->
      <template #tab-appraisals>
        <VCard>
          <WpPageToolbar
            v-model:search="search"
            :create-label="canManage ? $t('appraisals.create_appraisal', 'Record Appraisal') : null"
            :loading="store.loading"
            @create="dialogs.openReview()"
            @refresh="loadData"
          >
            <template #append>
              <AppSelect
                v-model="selectedCycleFilter"
                :items="cycleOptions"
                :placeholder="$t('appraisals.filter_cycle', 'Filter by Cycle')"
                variant="outlined"
                density="compact"
                clearable
                class="d-none d-sm-flex"
                style="min-inline-size: 200px; max-inline-size: 260px;"
              />
            </template>
          </WpPageToolbar>

          <WpResponsiveList
            :headers="appraisalHeaders"
            :items="filteredAppraisals"
            :total-items="filteredAppraisals.length"
            :loading="store.loading"
            hide-pagination
            @retry="loadData"
          >
            <template #item.employee="{ item }">
              <div class="d-flex align-center gap-3 py-1">
                <VAvatar
                  color="primary"
                  variant="tonal"
                  size="36"
                >
                  <VIcon
                    icon="tabler-user"
                    size="18"
                  />
                </VAvatar>
                <div class="d-flex flex-column">
                  <span class="text-body-2 font-weight-medium">
                    {{ item.employee?.full_name || `${item.employee?.first_name || ''} ${item.employee?.last_name || ''}`.trim() || '—' }}
                  </span>
                  <span class="text-caption text-medium-emphasis">
                    {{ item.employee?.department?.name || '—' }}
                  </span>
                </div>
              </div>
            </template>

            <template #item.cycle="{ item }">
              <span class="text-body-2">{{ item.cycle?.name || '—' }}</span>
            </template>

            <template #item.reviewer="{ item }">
              <span class="text-body-2">{{ item.reviewer?.name || $t('appraisals.unassigned', 'Unassigned') }}</span>
            </template>

            <template #item.overall_score="{ item }">
              <div class="d-flex align-center gap-1">
                <VRating
                  :model-value="item.overall_score || 0"
                  readonly
                  density="compact"
                  size="18"
                  color="warning"
                />
                <span class="text-caption font-weight-medium ms-1">({{ item.overall_score || 0 }}/5)</span>
              </div>
            </template>

            <template #item.status="{ item }">
              <VChip
                :color="appraisalColor(item.status)"
                size="small"
                label
                class="text-capitalize"
              >
                {{ item.status }}
              </VChip>
            </template>

            <template #item.actions="{ item }">
              <WpRowActions
                :actions="getAppraisalRowActions(item)"
                @view="dialogs.openDetail(item)"
                @edit="dialogs.openReview(item)"
                @delete="dialogs.openDelete(item, 'appraisal')"
              />
            </template>

            <template #mobile-card="{ item }">
              <WpMobileCard
                :title="item.employee?.full_name || '—'"
                :subtitle="item.cycle?.name"
                :badge="item.status"
                :badge-color="appraisalColor(item.status)"
                :actions="getAppraisalRowActions(item)"
                @view="dialogs.openDetail(item)"
                @edit="dialogs.openReview(item)"
                @delete="dialogs.openDelete(item, 'appraisal')"
              >
                <div class="d-flex align-center justify-space-between mt-2">
                  <span class="text-caption text-medium-emphasis">
                    {{ $t('appraisals.reviewer', 'Reviewer') }}: {{ item.reviewer?.name || '—' }}
                  </span>
                  <VRating
                    :model-value="item.overall_score || 0"
                    readonly
                    density="compact"
                    size="16"
                    color="warning"
                  />
                </div>
              </WpMobileCard>
            </template>
          </WpResponsiveList>
        </VCard>
      </template>
    </AppHybridTabs>

    <!-- 👉 Cycle Dialog -->
    <AppraisalCycleDialog
      v-model:is-dialog-visible="dialogs.cycleDialog.isOpen.value"
      :cycle="dialogs.selectedItem.value"
      :loading="actions.isSaving.value"
      @submit="actions.handleSaveCycle"
    />

    <!-- 👉 Review Dialog -->
    <AppraisalReviewDialog
      v-model:is-dialog-visible="dialogs.reviewDialog.isOpen.value"
      :appraisal="dialogs.selectedItem.value"
      :cycle-options="cycleOptions"
      :employee-options="employeeOptions"
      :reviewer-options="reviewerOptions"
      :loading="actions.isSaving.value"
      @submit="actions.handleSaveAppraisal"
    />

    <!-- 👉 Detail Dialog -->
    <WpDetailDialog
      :model-value="dialogs.detailDialog.isOpen.value"
      :title="dialogs.selectedItem.value?.employee?.full_name"
      :subtitle="dialogs.selectedItem.value?.cycle?.name"
      size="medium"
      @update:model-value="dialogs.closeDetail"
    >
      <VRow v-if="dialogs.selectedItem.value">
        <VCol
          cols="12"
          sm="6"
        >
          <div class="text-caption text-medium-emphasis">
            {{ $t('appraisals.reviewer', 'Reviewer') }}
          </div>
          <div class="font-weight-medium">
            {{ dialogs.selectedItem.value.reviewer?.name || $t('appraisals.unassigned', 'Unassigned') }}
          </div>
        </VCol>

        <VCol
          cols="12"
          sm="6"
        >
          <div class="text-caption text-medium-emphasis">
            {{ $t('common.status', 'Status') }}
          </div>
          <VChip
            :color="appraisalColor(dialogs.selectedItem.value.status)"
            size="small"
            label
            class="text-capitalize mt-1"
          >
            {{ dialogs.selectedItem.value.status }}
          </VChip>
        </VCol>

        <VCol cols="12">
          <div class="text-caption text-medium-emphasis mb-1">
            {{ $t('appraisals.score', 'Overall Rating') }}
          </div>
          <div class="d-flex align-center gap-2">
            <VRating
              :model-value="dialogs.selectedItem.value.overall_score || 0"
              readonly
              density="compact"
              size="24"
              color="warning"
            />
            <span class="text-body-1 font-weight-bold">({{ dialogs.selectedItem.value.overall_score || 0 }}/5)</span>
          </div>
        </VCol>

        <!-- Evaluation Criteria Ratings Breakdown -->
        <VCol
          v-if="dialogs.selectedItem.value.ratings"
          cols="12"
        >
          <div class="text-subtitle-2 font-weight-bold mb-2">
            {{ $t('appraisals.evaluation_criteria', 'Competency Breakdown') }}
          </div>
          <VCard
            variant="outlined"
            class="pa-3"
          >
            <div
              v-for="(rating, criterion) in dialogs.selectedItem.value.ratings"
              :key="criterion"
              class="d-flex justify-space-between align-center py-1 border-b"
            >
              <span class="text-body-2 text-capitalize">{{ criterion.replace('_', ' ') }}</span>
              <div class="d-flex align-center gap-2">
                <VRating
                  :model-value="rating"
                  readonly
                  density="compact"
                  size="18"
                  color="warning"
                />
                <span class="text-caption font-weight-medium">({{ rating }}/5)</span>
              </div>
            </div>
          </VCard>
        </VCol>

        <VCol cols="12">
          <div class="text-subtitle-2 font-weight-bold mb-1">
            {{ $t('appraisals.manager_feedback', 'Manager Feedback') }}
          </div>
          <p class="text-body-2 border rounded pa-3 bg-surface">
            {{ dialogs.selectedItem.value.manager_feedback || $t('appraisals.no_feedback', 'No manager feedback provided.') }}
          </p>
        </VCol>

        <VCol
          v-if="dialogs.selectedItem.value.employee_feedback"
          cols="12"
        >
          <div class="text-subtitle-2 font-weight-bold mb-1">
            {{ $t('appraisals.employee_feedback', 'Employee Self-Assessment') }}
          </div>
          <p class="text-body-2 border rounded pa-3 bg-surface">
            {{ dialogs.selectedItem.value.employee_feedback }}
          </p>
        </VCol>

        <!-- Workflow Audit Trail -->
        <VCol
          v-if="dialogs.selectedItem.value.reviewed_at || dialogs.selectedItem.value.acknowledged_at"
          cols="12"
        >
          <div class="d-flex flex-column gap-1 text-caption text-medium-emphasis border-t pt-3">
            <div v-if="dialogs.selectedItem.value.reviewed_at">
              <VIcon
                icon="tabler-check"
                size="14"
                class="me-1 text-info"
              />
              <span>{{ $t('appraisals.reviewed_at', 'Reviewed on') }}: {{ formatDate(dialogs.selectedItem.value.reviewed_at) }}</span>
            </div>
            <div v-if="dialogs.selectedItem.value.acknowledged_at">
              <VIcon
                icon="tabler-circle-check"
                size="14"
                class="me-1 text-success"
              />
              <span>
                {{ $t('appraisals.acknowledged_at', 'Acknowledged and finalized on') }}: {{ formatDate(dialogs.selectedItem.value.acknowledged_at) }}
                <template v-if="dialogs.selectedItem.value.acknowledger?.name">
                  ({{ dialogs.selectedItem.value.acknowledger.name }})
                </template>
              </span>
            </div>
          </div>
        </VCol>

        <!-- Employee Acknowledgement Action Card -->
        <VCol
          v-if="canAcknowledgeCurrent"
          cols="12"
        >
          <VDivider class="my-2" />
          <VCard
            variant="tonal"
            color="primary"
            class="pa-4"
          >
            <h6 class="text-subtitle-1 font-weight-bold mb-1 text-primary">
              {{ $t('appraisals.sign_off_title', 'Employee Sign-off & Acknowledgement') }}
            </h6>
            <p class="text-caption mb-3">
              {{ $t('appraisals.sign_off_desc', 'Please review the manager feedback above. By acknowledging, you confirm you have discussed this evaluation. Completed appraisals become permanently finalized.') }}
            </p>
            <AppTextarea
              v-model="acknowledgementFeedback"
              :label="$t('appraisals.employee_comments', 'Optional Employee Comments / Response')"
              rows="2"
              class="mb-3"
            />
            <VBtn
              color="primary"
              prepend-icon="tabler-circle-check"
              :loading="actions.isAcknowledging.value"
              @click="handleDetailAcknowledge"
            >
              {{ $t('appraisals.acknowledge_button', 'Acknowledge & Finalize Appraisal') }}
            </VBtn>
          </VCard>
        </VCol>
      </VRow>
    </WpDetailDialog>

    <!-- 👉 Delete Confirmation Dialog -->
    <WpDeleteDialog
      v-model:is-dialog-visible="dialogs.deleteDialog.isOpen.value"
      :title="$t('common.delete', 'Delete Record')"
      :confirmation-text="$t('common.delete_confirm', 'Are you sure you want to proceed? This action cannot be undone.')"
      :item-name="dialogs.selectedItem.value?.name || dialogs.selectedItem.value?.employee?.full_name"
      :loading="actions.isDeleting.value"
      @confirm="actions.handleDelete"
    />

    <!-- 👉 Mobile FAB Speed Dial -->
    <AppFabSpeedDial
      v-if="mobileFabActions.length > 0"
      :actions="mobileFabActions"
      icon="tabler-dots-vertical"
      class="d-sm-none"
    />
  </div>
</template>
