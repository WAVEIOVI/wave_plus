<script setup>
/* eslint-disable camelcase */
import {
  AppFabSpeedDial,
  WpDeleteDialog,
  WpDetailDialog,
  WpFilterDialog,
  WpKpiCard,
  WpKpiGroup,
  WpMobileCard,
  WpPageHeader,
  WpPageToolbar,
  WpResponsiveList,
  WpRowActions,
} from '@/components/shared'
import { useRecruitmentActions } from '@/composables/people-skills/recruitment/useRecruitmentActions'
import { useRecruitmentDialogs } from '@/composables/people-skills/recruitment/useRecruitmentDialogs'
import { useRecruitmentList } from '@/composables/people-skills/recruitment/useRecruitmentList'
import RecruitmentCandidateDialog from '@/views/people-skills/recruitment/RecruitmentCandidateDialog.vue'
import RecruitmentInterviewDialog from '@/views/people-skills/recruitment/RecruitmentInterviewDialog.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

definePage({
  path: '/people-skills/recruitment/list',
  name: 'people-skills-recruitment-list',
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'read',
    subject: 'PeopleSkills',
  },
})

const { t, locale } = useI18n()

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
  return new Intl.DateTimeFormat(locale.value || undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  }).format(new Date(date))
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
  const rowActions = [
    { label: t('common.view', 'View Details'), icon: 'tabler-eye', emit: 'view' },
  ]

  if (nextStage) {
    rowActions.push({
      label: `${t('recruitment.advance_to', 'Advance to')} ${nextStage}`,
      icon: 'tabler-arrow-right',
      emit: 'advance',
      color: 'primary',
    })
  }

  rowActions.push({
    label: t('recruitment.schedule_interview', 'Schedule Interview'),
    icon: 'tabler-calendar-plus',
    emit: 'interview',
    color: 'warning',
  })

  if (item.status !== 'rejected') {
    rowActions.push({
      label: t('recruitment.reject', 'Reject Candidate'),
      icon: 'tabler-ban',
      emit: 'reject',
      color: 'error',
      dividerBefore: true,
    })
  }

  rowActions.push({
    label: t('common.delete', 'Delete'),
    icon: 'tabler-trash',
    emit: 'delete',
    color: 'error',
    dividerBefore: true,
  })

  return rowActions
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
      :subtitle="$t('recruitment.list.subtitle', 'Candidate pipeline, interviews and hiring decisions.')"
      icon="tabler-user-search"
    >
      <template #actions>
        <VBtn
          variant="outlined"
          color="secondary"
          prepend-icon="tabler-layout-kanban"
          :to="{ name: 'people-skills-recruitment-pipeline' }"
        >
          {{ $t('recruitment.open_pipeline', 'Open Pipeline') }}
        </VBtn>
        <VBtn
          v-if="$can('create', 'PeopleSkills')"
          color="primary"
          prepend-icon="tabler-plus"
          @click="dialogs.createDialog.open()"
        >
          {{ $t('recruitment.add_candidate', 'Add Candidate') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <!-- 👉 KPI Cards -->
    <WpKpiGroup>
      <VRow>
        <VCol cols="12" sm="6" md="3">
          <WpKpiCard
            :title="$t('recruitment.kpi.total_applicants', 'Total Applicants')"
            :value="kpis.total"
            icon="tabler-users"
            color="primary"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <WpKpiCard
            :title="$t('recruitment.kpi.in_screening', 'Screening')"
            :value="kpis.screeningCount"
            icon="tabler-user-check"
            color="info"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <WpKpiCard
            :title="$t('recruitment.kpi.interviews', 'Interviews')"
            :value="kpis.interviewCount"
            icon="tabler-calendar-event"
            color="warning"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
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
        :create-label="$can('create', 'PeopleSkills') ? $t('recruitment.add_candidate', 'Add Candidate') : ''"
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
          <div class="d-flex align-center gap-3 py-1">
            <VAvatar
              color="primary"
              variant="tonal"
              size="38"
            >
              <VIcon icon="tabler-user" size="20" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-body-2 font-weight-medium">
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
            {{ item.applications?.[0]?.job_title || item.applications?.[0]?.job_opening?.title || $t('recruitment.general_application', 'General application') }}
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
            @reject="actions.handleStageChange(item.id, 'rejected')"
            @delete="dialogs.openDelete(item)"
          >
            <div class="d-flex flex-column gap-1 text-caption text-medium-emphasis mt-2">
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-briefcase" size="16" />
                <span>{{ item.applications?.[0]?.job_title || item.applications?.[0]?.job_opening?.title || $t('recruitment.general_application', 'General application') }}</span>
              </div>
              <div v-if="item.phone" class="d-flex align-center gap-2">
                <VIcon icon="tabler-phone" size="16" />
                <span>{{ item.phone }}</span>
              </div>
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-calendar" size="16" />
                <span>{{ formatDate(item.created_at) }}</span>
              </div>
            </div>
          </WpMobileCard>
        </template>
      </WpResponsiveList>
    </VCard>

    <!-- 👉 Add Candidate Dialog -->
    <RecruitmentCandidateDialog
      v-model:is-dialog-visible="dialogs.createDialog.isOpen.value"
      :opening-options="openingOptions"
      :loading="actions.isSubmitting.value"
      @submit="actions.handleCreateCandidate"
    />

    <!-- 👉 Schedule Interview Dialog -->
    <RecruitmentInterviewDialog
      v-model:is-dialog-visible="dialogs.interviewDialog.isOpen.value"
      :candidate="dialogs.selectedCandidate.value"
      :opening-options="openingOptions"
      :loading="actions.isSubmitting.value"
      @submit="actions.handleScheduleInterview(dialogs.selectedCandidate.value?.id, $event)"
    />

    <!-- 👉 Detail Dialog -->
    <WpDetailDialog
      :model-value="dialogs.detailDialog.isOpen.value"
      :title="dialogs.selectedCandidate.value?.full_name"
      :subtitle="dialogs.selectedCandidate.value?.reference"
      size="medium"
      @update:model-value="dialogs.closeDetail"
    >
      <VRow v-if="dialogs.selectedCandidate.value">
        <VCol cols="12" md="6">
          <div class="text-caption text-medium-emphasis">
            {{ $t('common.contact', 'Contact') }}
          </div>
          <div class="font-weight-medium">
            {{ dialogs.selectedCandidate.value.email }}
          </div>
          <div class="text-body-2">
            {{ dialogs.selectedCandidate.value.phone || '—' }}
          </div>
        </VCol>

        <VCol cols="12" md="6">
          <div class="text-caption text-medium-emphasis">
            {{ $t('recruitment.current_stage', 'Current Stage') }}
          </div>
          <VChip
            :color="stageColor(dialogs.selectedCandidate.value.status)"
            size="small"
            label
            class="text-capitalize mt-1"
          >
            {{ dialogs.selectedCandidate.value.status }}
          </VChip>
        </VCol>

        <VCol cols="12">
          <div class="text-subtitle-2 font-weight-bold mb-2">
            {{ $t('recruitment.applications', 'Applications') }}
          </div>
          <div
            v-if="dialogs.selectedCandidate.value.applications?.length"
            class="d-flex flex-column gap-2"
          >
            <div
              v-for="application in dialogs.selectedCandidate.value.applications"
              :key="application.id"
              class="rounded border pa-3"
            >
              <div class="font-weight-medium">
                {{ application.job_title || application.job_opening?.title || $t('recruitment.general_application', 'General application') }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ formatDate(application.application_date) }}
              </div>
            </div>
          </div>
          <span
            v-else
            class="text-body-2 text-medium-emphasis"
          >
            {{ $t('recruitment.general_application', 'General application') }}
          </span>
        </VCol>

        <VCol cols="12">
          <div class="d-flex align-center justify-space-between mb-2">
            <div class="text-subtitle-2 font-weight-bold">
              {{ $t('recruitment.interviews', 'Interviews') }}
            </div>
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-calendar-plus"
              @click="dialogs.openInterview(dialogs.selectedCandidate.value)"
            >
              {{ $t('recruitment.schedule', 'Schedule') }}
            </VBtn>
          </div>

          <div
            v-if="dialogs.selectedCandidate.value.interviews?.length"
            class="d-flex flex-column gap-2"
          >
            <div
              v-for="interview in dialogs.selectedCandidate.value.interviews"
              :key="interview.id"
              class="rounded border pa-3"
            >
              <div class="font-weight-medium">
                {{ formatDate(interview.scheduled_at) }} · {{ interview.interviewer_name || interview.interviewer?.name || $t('recruitment.unassigned', 'Unassigned') }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ interview.status }}<span v-if="interview.rating"> · {{ interview.rating }}/5</span>
              </div>
            </div>
          </div>
          <span
            v-else
            class="text-body-2 text-medium-emphasis"
          >
            {{ $t('recruitment.no_interviews', 'No interviews scheduled.') }}
          </span>
        </VCol>
      </VRow>
    </WpDetailDialog>

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
