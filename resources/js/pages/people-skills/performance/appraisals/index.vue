<script setup>
/* eslint-disable camelcase */
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
import AppraisalCycleDialog from '@/views/people-skills/performance/AppraisalCycleDialog.vue'
import AppraisalReviewDialog from '@/views/people-skills/performance/AppraisalReviewDialog.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

definePage({
  path: '/people-skills/performance/appraisals',
  name: 'people-skills-performance-appraisals',
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'read',
    subject: 'PeopleSkills',
  },
})

const { t } = useI18n()

// ── List Orchestration ──────────────────────────────────────────────────────
const {
  store,
  hrStore,
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

// ── Options for Dropdowns ───────────────────────────────────────────────────
const cycleOptions = computed(() =>
  store.cycles.map(c => ({
    title: `${c.name} (${c.cycle_type})`,
    value: c.id,
  })),
)

const employeeOptions = computed(() =>
  (hrStore.availableUsers || []).map(u => ({
    title: u.name || u.email,
    value: u.id,
  })),
)

// ── Row Actions ─────────────────────────────────────────────────────────────
const getCycleRowActions = item => [
  { label: t('common.edit', 'Edit'), icon: 'tabler-edit', emit: 'edit' },
  { label: t('appraisals.record_review', 'Record Evaluation'), icon: 'tabler-file-plus', emit: 'add-review' },
  { label: t('common.delete', 'Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
]

const getAppraisalRowActions = item => [
  { label: t('common.view', 'View Details'), icon: 'tabler-eye', emit: 'view' },
  { label: t('common.edit', 'Edit'), icon: 'tabler-edit', emit: 'edit' },
  { label: t('common.delete', 'Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
]

// ── Mobile FAB ──────────────────────────────────────────────────────────────
const mobileFabActions = computed(() => [
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
])
</script>

<template>
  <div class="people-skills-performance-appraisals d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="$t('appraisals.title', 'Performance & Appraisals')"
      :subtitle="$t('appraisals.subtitle', 'Continuous evaluations, periodic appraisal cycles, and team reviews.')"
      icon="tabler-chart-bar"
    >
      <template #actions>
        <VBtn
          variant="outlined"
          color="secondary"
          prepend-icon="tabler-calendar-plus"
          @click="dialogs.openCycle()"
        >
          {{ $t('appraisals.create_cycle', 'New Cycle') }}
        </VBtn>
        <VBtn
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
            :create-label="$t('appraisals.create_cycle', 'New Cycle')"
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
                  <VIcon icon="tabler-file-analytics" size="16" />
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
            :create-label="$t('appraisals.create_appraisal', 'Record Appraisal')"
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
                <VAvatar color="primary" variant="tonal" size="36">
                  <VIcon icon="tabler-user" size="18" />
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
      :reviewer-options="employeeOptions"
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
        <VCol cols="12" md="6">
          <div class="text-caption text-medium-emphasis">{{ $t('appraisals.reviewer', 'Reviewer') }}</div>
          <div class="font-weight-medium">{{ dialogs.selectedItem.value.reviewer?.name || '—' }}</div>
        </VCol>

        <VCol cols="12" md="6">
          <div class="text-caption text-medium-emphasis">{{ $t('common.status', 'Status') }}</div>
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
          <div class="text-caption text-medium-emphasis mb-1">{{ $t('appraisals.score', 'Overall Rating') }}</div>
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

        <VCol cols="12">
          <div class="text-subtitle-2 font-weight-bold mb-1">{{ $t('appraisals.manager_feedback', 'Manager Feedback') }}</div>
          <p class="text-body-2 border rounded pa-3 bg-surface">
            {{ dialogs.selectedItem.value.manager_feedback || $t('appraisals.no_feedback', 'No manager feedback provided.') }}
          </p>
        </VCol>

        <VCol cols="12" v-if="dialogs.selectedItem.value.employee_feedback">
          <div class="text-subtitle-2 font-weight-bold mb-1">{{ $t('appraisals.employee_feedback', 'Employee Self-Assessment') }}</div>
          <p class="text-body-2 border rounded pa-3 bg-surface">
            {{ dialogs.selectedItem.value.employee_feedback }}
          </p>
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
      :actions="mobileFabActions"
      icon="tabler-dots-vertical"
      class="d-sm-none"
    />
  </div>
</template>
