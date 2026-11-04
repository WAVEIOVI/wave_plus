<script setup>
/* eslint-disable camelcase */
import {
  AppFabSpeedDial,
  WpDeleteDialog,
  WpEmailDialog,
  WpFilterDialog,
  WpKpiCard,
  WpKpiGroup,
  WpMobileCard,
  WpPageHeader,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
} from '@/components/shared'
import HireWizardDialog from '@/components/people-skills/HireWizardDialog.vue'
import { useRecruitmentActions } from '@/composables/people-skills/recruitment/useRecruitmentActions'
import { useRecruitmentDialogs } from '@/composables/people-skills/recruitment/useRecruitmentDialogs'
import { useRecruitmentList } from '@/composables/people-skills/recruitment/useRecruitmentList'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import CandidateDetailDialog from '@/views/people-skills/recruitment/CandidateDetailDialog.vue'
import JobOpeningsTab from '@/views/people-skills/recruitment/JobOpeningsTab.vue'
import RecruitmentCandidateDialog from '@/views/people-skills/recruitment/RecruitmentCandidateDialog.vue'
import RecruitmentInterviewDialog from '@/views/people-skills/recruitment/RecruitmentInterviewDialog.vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

definePage({
  path: '/people-skills/recruitment/list',
  name: 'people-skills-recruitment-list',
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'read',
    subject: 'recruitment',
  },
})

const { t, locale } = useI18n()
const ability = useAbility()

// ── Active View Tab (Candidates vs Vacancies) ───────────────────────────────
const activeMainTab = ref('candidates')

// ── List Orchestration ──────────────────────────────────────────────────────
const {
  recruitmentStore,
  listPage,
  headers,
  stageOptions,
  stageColor,
  openings,
  openingOptions,
  kpis,
} = useRecruitmentList()

// ── Dialog State ────────────────────────────────────────────────────────────
const dialogs = useRecruitmentDialogs()

// ── Actions ─────────────────────────────────────────────────────────────────
const actions = useRecruitmentActions({
  listPage,
  dialogs,
})

// ── Date Formatter ──────────────────────────────────────────────────────────
const formatDate = date => {
  if (!date) return '—'
  try {
    return new Intl.DateTimeFormat(locale.value || undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    }).format(new Date(date))
  } catch {
    return String(date)
  }
}

// ── Next Stage Resolver ─────────────────────────────────────────────────────
const getNextStage = currentStage => {
  const map = {
    applied: 'screening',
    screening: 'interview',
    interview: 'evaluated',
    evaluated: 'selected',
    selected: 'offer',
  }

  return map[currentStage] || null
}

// ── Row Actions ─────────────────────────────────────────────────────────────
const getRowActions = item => {
  const nextStage = getNextStage(item.status)
  const canBeHired = ['interview', 'evaluated', 'selected', 'offer'].includes(item.status)

  const rowActions = [
    { label: t('common.view_details', 'View Profile'), icon: 'tabler-eye', emit: 'view' },
  ]

  if (nextStage && ability.can('manage', 'recruitment')) {
    rowActions.push({
      label: `${t('recruitment.advance_to', 'Advance to')} ${t(`recruitment.stage.${nextStage}`, nextStage)}`,
      icon: 'tabler-arrow-right',
      emit: 'advance',
      color: 'primary',
    })
  }

  if (ability.can('manage', 'recruitment')) {
    rowActions.push({
      label: t('recruitment.schedule_interview', 'Schedule Interview'),
      icon: 'tabler-calendar-plus',
      emit: 'interview',
      color: 'warning',
    })
  }

  if (canBeHired && ability.can('hire', 'recruitment')) {
    rowActions.push({
      label: t('hire_wizard.hire', 'Hire Candidate'),
      icon: 'tabler-user-plus',
      emit: 'hire',
      color: 'success',
    })
  }

  rowActions.push({
    label: t('common.email', 'Send Email'),
    icon: 'tabler-mail',
    emit: 'email',
  })

  if (item.status !== 'rejected' && item.status !== 'hired' && ability.can('manage', 'recruitment')) {
    rowActions.push({
      label: t('recruitment.reject', 'Reject Candidate'),
      icon: 'tabler-ban',
      emit: 'reject',
      color: 'error',
      dividerBefore: true,
    })
  }

  if (ability.can('manage', 'recruitment')) {
    rowActions.push({
      label: t('common.delete', 'Delete'),
      icon: 'tabler-trash',
      emit: 'delete',
      color: 'error',
      dividerBefore: true,
    })
  }

  return rowActions
}

const handleFilterByOpening = openingId => {
  listPage.filters.opening_id = openingId
  activeMainTab.value = 'candidates'
  listPage.refresh()
}

// ── Mobile FAB ──────────────────────────────────────────────────────────────
const mobileFabActions = computed(() => [
  {
    id: 'add-candidate',
    icon: 'tabler-user-plus',
    title: t('recruitment.add_candidate', 'Add Candidate'),
    onClick: () => dialogs.createDialog.open(),
  },
  {
    id: 'pipeline',
    icon: 'tabler-layout-kanban',
    title: t('recruitment.open_pipeline', 'Pipeline Board'),
    to: { name: 'people-skills-recruitment-pipeline' },
  },
  {
    id: 'filter-candidates',
    icon: 'tabler-filter',
    title: t('common.filter', 'Filter'),
    onClick: () => dialogs.filterDialog.open(),
  },
])
</script>

<template>
  <div class="people-skills-recruitment-list d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="$t('recruitment.list.title', 'Recruitment & Hiring')"
      :subtitle="$t('recruitment.list.subtitle', 'Candidate pipeline, vacancies, interviews and hiring decisions.')"
      icon="tabler-user-search"
    >
      <template #actions>
        <VBtn
          variant="outlined"
          color="secondary"
          prepend-icon="tabler-layout-kanban"
          :to="{ name: 'people-skills-recruitment-pipeline' }"
        >
          {{ $t('recruitment.open_pipeline', 'Pipeline Board') }}
        </VBtn>
        <VBtn
          v-if="ability.can('manage', 'recruitment') && activeMainTab === 'candidates'"
          color="primary"
          prepend-icon="tabler-plus"
          @click="dialogs.createDialog.open()"
        >
          {{ $t('recruitment.add_candidate', 'Add Candidate') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <!-- 👉 Hybrid Navigation Tabs -->
    <VTabs
      v-model="activeMainTab"
      class="border-b"
    >
      <VTab value="candidates">
        <VIcon
          icon="tabler-users"
          size="20"
          class="me-2"
        />
        {{ $t('recruitment.candidates', 'Candidates Directory') }}
      </VTab>
      <VTab value="openings">
        <VIcon
          icon="tabler-briefcase"
          size="20"
          class="me-2"
        />
        {{ $t('recruitment.vacancies', 'Job Vacancies') }}
      </VTab>
    </VTabs>

    <!-- TAB 1: CANDIDATES DIRECTORY -->
    <div
      v-if="activeMainTab === 'candidates'"
      class="d-flex flex-column gap-6"
    >
      <!-- 👉 KPI Cards -->
      <WpKpiGroup>
        <VRow>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <WpKpiCard
              :title="$t('recruitment.kpi.total_applicants', 'Total Applicants')"
              :value="kpis.total"
              icon="tabler-users"
              color="primary"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <WpKpiCard
              :title="$t('recruitment.kpi.in_screening', 'Screening')"
              :value="kpis.screeningCount"
              icon="tabler-user-check"
              color="info"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <WpKpiCard
              :title="$t('recruitment.kpi.interviews', 'Interviews')"
              :value="kpis.interviewCount"
              icon="tabler-calendar-event"
              color="warning"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <WpKpiCard
              :title="$t('recruitment.kpi.offers_selected', 'Offers / Selected')"
              :value="kpis.offerCount"
              icon="tabler-award"
              color="success"
            />
          </VCol>
        </VRow>
      </WpKpiGroup>

      <!-- 👉 Table Container -->
      <VCard>
        <WpPageToolbar
          v-model:search="listPage.search.value"
          :create-label="ability.can('manage', 'recruitment') ? $t('recruitment.add_candidate', 'Add Candidate') : ''"
          :loading="recruitmentStore.loading"
          :has-active-filters="Boolean(listPage.filters.stage || listPage.filters.opening_id)"
          @create="dialogs.createDialog.open()"
          @refresh="listPage.refresh()"
          @open-filter="dialogs.filterDialog.open()"
        >
          <template #append>
            <AppSelect
              v-model="listPage.filters.stage"
              :items="stageOptions"
              :placeholder="$t('recruitment.filter.stage', 'Stage')"
              variant="outlined"
              density="compact"
              clearable
              class="d-none d-sm-flex"
              style="min-inline-size: 150px; max-inline-size: 180px;"
            />
            <AppSelect
              v-model="listPage.filters.opening_id"
              :items="openingOptions"
              :placeholder="$t('recruitment.job_opening', 'Job Opening')"
              variant="outlined"
              density="compact"
              clearable
              class="d-none d-md-flex"
              style="min-inline-size: 180px; max-inline-size: 240px;"
            />
          </template>
        </WpPageToolbar>

        <WpResponsiveList
          v-model:page="listPage.page.value"
          v-model:items-per-page="listPage.itemsPerPage.value"
          v-model:sort-by="listPage.sortBy.value"
          :headers="headers"
          :items="recruitmentStore.list"
          :total-items="recruitmentStore.totalItems"
          :loading="recruitmentStore.loading"
          :error="recruitmentStore.error"
          @retry="listPage.refresh()"
        >
          <!-- Desktop Column Templates -->
          <template #item.applicant="{ item }">
            <div
              class="d-flex align-center gap-3 py-1 cursor-pointer"
              @click="dialogs.openDetail(item)"
            >
              <VAvatar
                color="primary"
                variant="tonal"
                size="38"
              >
                <VIcon
                  icon="tabler-user"
                  size="20"
                />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-body-2 font-weight-medium text-primary">
                  {{ item.full_name || `${item.first_name || ''} ${item.last_name || ''}`.trim() || '—' }}
                </span>
                <span
                  v-if="item.email"
                  class="text-caption text-medium-emphasis"
                >
                  {{ item.email }}
                </span>
              </div>
            </div>
          </template>

          <template #item.position="{ item }">
            <span class="text-body-2">
              {{ item.applications?.[0]?.job_title || item.applications?.[0]?.job_opening?.title || $t('recruitment.general_application', 'General Application') }}
            </span>
          </template>

          <template #item.created_at="{ item }">
            <span class="text-body-2">{{ formatDate(item.created_at) }}</span>
          </template>

          <template #item.status="{ item }">
            <VChip
              :color="stageColor(item.status)"
              size="small"
              label
              class="text-capitalize"
            >
              {{ item.status }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <WpRowActions
              :actions="getRowActions(item)"
              @view="dialogs.openDetail(item)"
              @advance="actions.handleStageChange(item.id, getNextStage(item.status))"
              @interview="dialogs.openInterview(item)"
              @hire="dialogs.openHire(item)"
              @email="dialogs.openEmail(item)"
              @reject="actions.handleStageChange(item.id, 'rejected')"
              @delete="dialogs.openDelete(item)"
            />
          </template>

          <!-- Mobile Card Template -->
          <template #mobile-card="{ item }">
            <WpMobileCard
              :title="item.full_name || `${item.first_name || ''} ${item.last_name || ''}`.trim() || '—'"
              :subtitle="item.email"
              :badge="item.status"
              :badge-color="stageColor(item.status)"
              :actions="getRowActions(item)"
              @view="dialogs.openDetail(item)"
              @advance="actions.handleStageChange(item.id, getNextStage(item.status))"
              @interview="dialogs.openInterview(item)"
              @hire="dialogs.openHire(item)"
              @email="dialogs.openEmail(item)"
              @reject="actions.handleStageChange(item.id, 'rejected')"
              @delete="dialogs.openDelete(item)"
            >
              <div class="d-flex flex-column gap-1 text-caption text-medium-emphasis mt-2">
                <div class="d-flex align-center gap-2">
                  <VIcon
                    icon="tabler-briefcase"
                    size="16"
                  />
                  <span>{{ item.applications?.[0]?.job_title || item.applications?.[0]?.job_opening?.title || $t('recruitment.general_application', 'General Application') }}</span>
                </div>
                <div
                  v-if="item.phone"
                  class="d-flex align-center gap-2"
                >
                  <VIcon
                    icon="tabler-phone"
                    size="16"
                  />
                  <span>{{ item.phone }}</span>
                </div>
                <div class="d-flex align-center gap-2">
                  <VIcon
                    icon="tabler-calendar"
                    size="16"
                  />
                  <span>{{ formatDate(item.created_at) }}</span>
                </div>
              </div>
            </WpMobileCard>
          </template>
        </WpResponsiveList>
      </VCard>
    </div>

    <!-- TAB 2: VACANCIES / JOB OPENINGS -->
    <div
      v-else-if="activeMainTab === 'openings'"
      class="d-flex flex-column gap-6"
    >
      <JobOpeningsTab @filter-by-opening="handleFilterByOpening" />
    </div>

    <!-- 👉 Add Candidate Dialog -->
    <RecruitmentCandidateDialog
      v-model:is-dialog-visible="dialogs.createDialog.isOpen.value"
      :opening-options="openingOptions"
      :loading="actions.isSubmitting.value"
      @submit="actions.handleCreateCandidate"
    />

    <!-- 👉 Candidate 360 Detail Dialog -->
    <CandidateDetailDialog
      v-model:is-dialog-visible="dialogs.detailDialog.isOpen.value"
      :candidate="dialogs.selectedCandidate.value"
      :stage-color="stageColor"
      @schedule-interview="dialogs.openInterview"
      @evaluate-interview="dialogs.openEvaluate"
      @advance-stage="actions.handleStageChange"
      @hire="dialogs.openHire"
      @send-email="dialogs.openEmail"
      @refresh="listPage.refresh"
    />

    <!-- 👉 Schedule / Evaluate Interview Dialog -->
    <RecruitmentInterviewDialog
      v-model:is-dialog-visible="dialogs.interviewDialog.isOpen.value"
      :candidate="dialogs.selectedCandidate.value"
      :interview="dialogs.selectedInterview.value"
      :opening-options="openingOptions"
      :loading="actions.isSubmitting.value"
      @submit="actions.handleInterviewSubmit"
    />

    <!-- 👉 Candidate Email Dialog (Reusing Shared WpEmailDialog) -->
    <WpEmailDialog
      v-model="dialogs.emailDialog.isOpen.value"
      :title="t('recruitment.email_candidate', 'Email Candidate')"
      :recipients="dialogs.candidateToEmail.value?.email ? [dialogs.candidateToEmail.value.email] : []"
      :subject="`[Wave Plus] ${t('recruitment.application_update', 'Regarding your application')}`"
      :loading="actions.isSendingEmail.value"
      @send="actions.handleSendCandidateEmail"
    />

    <!-- 👉 Hire Wizard Dialog -->
    <HireWizardDialog
      v-model="dialogs.hireDialog.isOpen.value"
      :candidate="dialogs.candidateToHire.value"
      @hired="listPage.refresh"
    />

    <!-- 👉 Delete Confirmation Dialog -->
    <WpDeleteDialog
      v-model:is-dialog-visible="dialogs.deleteDialog.isOpen.value"
      :title="$t('recruitment.delete_title', 'Delete Candidate')"
      :confirmation-text="$t('recruitment.delete_confirm', 'Are you sure you want to remove this candidate from the recruitment pipeline?')"
      :item-name="dialogs.selectedCandidate.value?.full_name"
      :loading="actions.isDeleting.value"
      @confirm="actions.handleDeleteCandidate"
    />

    <!-- 👉 Filter Dialog (Mobile & Advanced) -->
    <WpFilterDialog
      v-model:is-dialog-visible="dialogs.filterDialog.isOpen.value"
      :title="$t('common.filters', 'Filter Candidates')"
      @apply="dialogs.filterDialog.close()"
      @reset="() => { listPage.filters.stage = null; listPage.filters.opening_id = null; dialogs.filterDialog.close() }"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="listPage.filters.stage"
            :items="stageOptions"
            :label="$t('recruitment.filter.stage', 'Stage')"
            clearable
          />
        </VCol>
        <VCol cols="12">
          <AppSelect
            v-model="listPage.filters.opening_id"
            :items="openingOptions"
            :label="$t('recruitment.job_opening', 'Job Opening')"
            clearable
          />
        </VCol>
      </VRow>
    </WpFilterDialog>

    <!-- 👉 Mobile FAB Speed Dial -->
    <AppFabSpeedDial
      :actions="mobileFabActions"
      icon="tabler-dots-vertical"
      class="d-sm-none"
    />
  </div>
</template>
