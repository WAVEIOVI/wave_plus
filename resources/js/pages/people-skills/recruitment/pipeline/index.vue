<script setup>
/* eslint-disable camelcase */
import { WpEmailDialog, WpPageHeader } from '@/components/shared'
import HireWizardDialog from '@/components/people-skills/HireWizardDialog.vue'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { useRecruitmentStore } from '@/stores/useRecruitmentStore'
import { $api } from '@/utils/api'
import CandidateDetailDialog from '@/views/people-skills/recruitment/CandidateDetailDialog.vue'
import RecruitmentInterviewDialog from '@/views/people-skills/recruitment/RecruitmentInterviewDialog.vue'
import { avatarText } from '@core/utils/formatters'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

definePage({
  path: '/people-skills/recruitment/pipeline',
  name: 'people-skills-recruitment-pipeline',
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'read',
    subject: 'recruitment',
  },
})

const { t, locale } = useI18n()
const { showMessage } = useAppSnackbar()
const ability = useAbility()
const router = useRouter()
const recruitmentStore = useRecruitmentStore()

// ── State ─────────────────────────────────────────────────────────────────
const loading = ref(false)
const draggedCandidate = ref(null)
const selectedOpeningFilter = ref(null)
const searchQuery = ref('')
const openings = ref([])

// Dialog States
const hireWizardOpen = ref(false)
const candidateToHire = ref(null)

const isDetailOpen = ref(false)
const candidateForDetail = ref(null)

const isInterviewOpen = ref(false)
const candidateForInterview = ref(null)
const interviewForEvaluation = ref(null)
const isSubmittingInterview = ref(false)

const isEmailOpen = ref(false)
const candidateToEmail = ref(null)
const isSendingEmail = ref(false)

// ── Pipeline Stages ───────────────────────────────────────────────────────
const stages = ref([
  { id: 'applied', color: 'info', icon: 'tabler-send', candidates: [] },
  { id: 'screening', color: 'primary', icon: 'tabler-search', candidates: [] },
  { id: 'interview', color: 'warning', icon: 'tabler-calendar-event', candidates: [] },
  { id: 'evaluated', color: 'info', icon: 'tabler-star', candidates: [] },
  { id: 'selected', color: 'primary', icon: 'tabler-thumb-up', candidates: [] },
  { id: 'offer', color: 'success', icon: 'tabler-file-text', candidates: [] },
  { id: 'hired', color: 'success', icon: 'tabler-check', candidates: [] },
  { id: 'rejected', color: 'error', icon: 'tabler-x', candidates: [] },
])

const stageColor = s => ({
  applied: 'info',
  screening: 'primary',
  interview: 'warning',
  evaluated: 'info',
  selected: 'primary',
  offer: 'success',
  hired: 'success',
  rejected: 'error',
}[s] || 'default')

// ── Computed ──────────────────────────────────────────────────────────────
const totalCandidates = computed(() =>
  stages.value.reduce((sum, stage) => sum + stage.candidates.length, 0),
)

const openingOptions = computed(() =>
  openings.value.map(o => ({ title: o.title, value: o.id })),
)

// ── Load Data ─────────────────────────────────────────────────────────────
const loadOpenings = async () => {
  try {
    openings.value = await recruitmentStore.fetchOpenings()
  } catch (err) {
    console.warn('[pipeline] Failed to load job openings:', err?.message)
  }
}

const loadCandidates = async () => {
  loading.value = true
  try {
    const params = { per_page: 250 }
    if (selectedOpeningFilter.value) params.opening_id = selectedOpeningFilter.value
    if (searchQuery.value) params.search = searchQuery.value

    const res = await recruitmentStore.fetchList(params)
    const list = res?.data || recruitmentStore.list || []

    // Reset all stage candidates
    stages.value.forEach(stage => {
      stage.candidates = []
    })

    // Group candidates by stage/status
    list.forEach(candidate => {
      const stage = stages.value.find(s => s.id === candidate.status)
      if (stage) {
        stage.candidates.push(candidate)
      }
    })
  } catch (err) {
    showMessage(err?.data?.message || t('recruitment.load_error', 'Failed to load candidates.'), 'error')
  } finally {
    loading.value = false
  }
}

// ── Drag & Drop ───────────────────────────────────────────────────────────
const handleDragStart = (event, candidate, fromStage) => {
  draggedCandidate.value = { candidate, fromStage }
  event.dataTransfer.effectAllowed = 'move'
}

const handleDragOver = event => {
  event.preventDefault()
  event.dataTransfer.dropEffect = 'move'
}

const handleDrop = async (event, toStage) => {
  event.preventDefault()

  if (!draggedCandidate.value) return

  const { candidate, fromStage } = draggedCandidate.value

  // Don't do anything if dropped in same stage
  if (fromStage.id === toStage.id) {
    draggedCandidate.value = null

    return
  }

  // Remove from old stage
  const fromIndex = fromStage.candidates.findIndex(c => c.id === candidate.id)
  if (fromIndex > -1) {
    fromStage.candidates.splice(fromIndex, 1)
  }

  // Add to new stage
  candidate.status = toStage.id
  toStage.candidates.push(candidate)

  // Update backend
  try {
    await recruitmentStore.updateStage(candidate.id, toStage.id)
    showMessage(t('recruitment.stage_updated', 'Stage updated.'), 'success')
  } catch (err) {
    showMessage(err?.data?.message || t('recruitment.stage_error', 'Failed to update stage.'), 'error')

    // Revert on error
    toStage.candidates = toStage.candidates.filter(c => c.id !== candidate.id)
    fromStage.candidates.push(candidate)
  }

  draggedCandidate.value = null
}

// ── Actions ───────────────────────────────────────────────────────────────
const openDetail = candidate => {
  candidateForDetail.value = candidate
  isDetailOpen.value = true
}

const openInterviewDialog = (candidate, interview = null) => {
  candidateForInterview.value = candidate
  interviewForEvaluation.value = interview
  isInterviewOpen.value = true
}

const handleInterviewSubmit = async payload => {
  isSubmittingInterview.value = true
  try {
    if (interviewForEvaluation.value?.id) {
      await recruitmentStore.updateInterview(interviewForEvaluation.value.id, payload)
      showMessage(t('recruitment.interview_evaluated', 'Interview evaluation saved.'), 'success')
    } else if (candidateForInterview.value?.id) {
      await recruitmentStore.scheduleInterview(candidateForInterview.value.id, payload)
      showMessage(t('recruitment.interview_scheduled', 'Interview scheduled and candidate advanced.'), 'success')
    }
    isInterviewOpen.value = false
    await loadCandidates()
  } catch (err) {
    showMessage(err?.data?.message || t('recruitment.interview_schedule_error', 'Failed to save interview.'), 'error')
  } finally {
    isSubmittingInterview.value = false
  }
}

const openEmailDialog = candidate => {
  candidateToEmail.value = candidate
  isEmailOpen.value = true
}

const handleSendCandidateEmail = async ({ to, cc, subject, message }) => {
  isSendingEmail.value = true
  try {
    await $api('/communications/email/messages', {
      method: 'POST',
      body: {
        to: Array.isArray(to) ? to : [to],
        cc: Array.isArray(cc) ? cc : (cc ? [cc] : []),
        subject,
        body: message,
        business_record_type: 'hr_candidate',
        business_record_id: candidateToEmail.value?.id,
      },
    })
    showMessage(t('recruitment.email_sent', 'Email dispatched successfully.'), 'success')
    isEmailOpen.value = false
  } catch (err) {
    showMessage(err?.data?.message || t('recruitment.email_failed', 'Failed to send email.'), 'error')
  } finally {
    isSendingEmail.value = false
  }
}

const openHireWizard = candidate => {
  candidateToHire.value = candidate
  hireWizardOpen.value = true
}

const handleHired = () => {
  loadCandidates()
}

// ── Lifecycle ─────────────────────────────────────────────────────────────
onMounted(() => {
  loadOpenings()
  loadCandidates()
})
</script>

<template>
  <div class="people-skills-recruitment-pipeline d-flex flex-column gap-6">
    <WpPageHeader
      :title="$t('recruitment.pipeline_title', 'Talent Pipeline')"
      :subtitle="$t('recruitment.pipeline_subtitle', 'Track candidates across every hiring stage.')"
      icon="tabler-layout-kanban"
    >
      <template #actions>
        <VChip
          color="primary"
          variant="tonal"
        >
          <VIcon
            icon="tabler-users"
            start
          />
          {{ totalCandidates }} {{ $t('recruitment.candidates', 'Candidates') }}
        </VChip>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="loadCandidates"
        >
          {{ $t('common.refresh', 'Refresh') }}
        </VBtn>
        <VBtn
          variant="outlined"
          color="secondary"
          prepend-icon="tabler-arrow-left"
          :to="{ name: 'people-skills-recruitment-list' }"
        >
          {{ $t('recruitment.return_to_list', 'Return to Recruitment') }}
        </VBtn>
      </template>
    </WpPageHeader>

    <!-- Pipeline Toolbar / Filters -->
    <VCard class="pa-4">
      <div class="d-flex align-center gap-4 flex-wrap">
        <AppTextField
          v-model="searchQuery"
          :placeholder="$t('common.search', 'Search candidate name, email, ref...')"
          prepend-inner-icon="tabler-search"
          density="compact"
          variant="outlined"
          clearable
          style="min-inline-size: 240px; max-inline-size: 360px;"
          @update:model-value="loadCandidates"
        />

        <AppSelect
          v-model="selectedOpeningFilter"
          :items="openingOptions"
          :placeholder="$t('recruitment.filter_by_opening', 'Filter by Job Opening')"
          density="compact"
          variant="outlined"
          clearable
          style="min-inline-size: 220px; max-inline-size: 320px;"
          @update:model-value="loadCandidates"
        />

        <VSpacer />

        <div class="text-caption text-medium-emphasis">
          <VIcon
            icon="tabler-info-circle"
            size="16"
            class="me-1"
          />
          {{ $t('recruitment.drag_hint', 'Drag cards between stages to advance candidates.') }}
        </div>
      </div>
    </VCard>

    <!-- Kanban Board -->
    <div class="pipeline-board">
      <div
        v-for="stage in stages"
        :key="stage.id"
        class="pipeline-stage"
        @dragover="handleDragOver"
        @drop="handleDrop($event, stage)"
      >
        <!-- Stage Header -->
        <div class="pipeline-stage-header">
          <div class="d-flex align-center gap-2">
            <VIcon
              :icon="stage.icon"
              :color="stage.color"
              size="20"
            />
            <span class="font-weight-bold">{{ $t(`recruitment.stage.${stage.id}`, stage.id) }}</span>
          </div>
          <VChip
            :color="stage.color"
            size="small"
            variant="flat"
          >
            {{ stage.candidates.length }}
          </VChip>
        </div>

        <!-- Candidate Cards -->
        <div class="pipeline-stage-content">
          <VProgressLinear
            v-if="loading"
            indeterminate
            color="primary"
            class="mb-2"
          />

          <div
            v-for="candidate in stage.candidates"
            :key="candidate.id"
            class="pipeline-card"
            draggable="true"
            @dragstart="handleDragStart($event, candidate, stage)"
            @click="openDetail(candidate)"
          >
            <!-- Card Header -->
            <div class="d-flex align-center justify-space-between mb-2">
              <div class="d-flex align-center gap-2">
                <VAvatar
                  size="34"
                  color="primary"
                  variant="tonal"
                >
                  <span class="text-caption font-weight-bold">{{ avatarText(candidate.full_name || '?') }}</span>
                </VAvatar>
                <div>
                  <div class="text-body-2 font-weight-semibold text-primary">
                    {{ candidate.full_name }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ candidate.reference }}
                  </div>
                </div>
              </div>

              <!-- Quick action dots -->
              <VBtn
                icon="tabler-mail"
                size="x-small"
                variant="text"
                color="secondary"
                @click.stop="openEmailDialog(candidate)"
              />
            </div>

            <!-- Position / Target Role -->
            <div class="text-caption text-medium-emphasis mb-1">
              <VIcon
                icon="tabler-briefcase"
                size="14"
                class="me-1"
              />
              {{ candidate.applications?.[0]?.job_title || candidate.position || $t('recruitment.general_application', 'General Application') }}
            </div>

            <!-- Phone -->
            <div
              v-if="candidate.phone"
              class="text-caption text-medium-emphasis mb-1"
            >
              <VIcon
                icon="tabler-phone"
                size="14"
                class="me-1"
              />
              {{ candidate.phone }}
            </div>

            <!-- Interview indicator if scheduled -->
            <div
              v-if="candidate.interviews?.length"
              class="d-flex align-center gap-1 text-caption text-warning my-1"
            >
              <VIcon
                icon="tabler-calendar-event"
                size="14"
              />
              <span>{{ candidate.interviews.length }} {{ $t('recruitment.interviews', 'interview(s)') }}</span>
              <span v-if="candidate.interviews[0]?.rating">· ★ {{ candidate.interviews[0].rating }}/5</span>
            </div>

            <!-- Card Actions -->
            <div class="d-flex align-center gap-1 mt-3 pt-2 border-t">
              <VBtn
                size="x-small"
                variant="tonal"
                color="primary"
                prepend-icon="tabler-calendar-plus"
                class="flex-grow-1"
                @click.stop="openInterviewDialog(candidate)"
              >
                {{ $t('recruitment.interview', 'Interview') }}
              </VBtn>

              <!-- Hire Button -->
              <VBtn
                v-if="['interview', 'evaluated', 'selected', 'offer'].includes(stage.id) && ability.can('hire', 'recruitment')"
                size="x-small"
                color="success"
                variant="tonal"
                prepend-icon="tabler-user-plus"
                @click.stop="openHireWizard(candidate)"
              >
                {{ $t('hire_wizard.hire', 'Hire') }}
              </VBtn>
            </div>
          </div>

          <!-- Empty State -->
          <div
            v-if="stage.candidates.length === 0 && !loading"
            class="pipeline-stage-empty"
          >
            <VIcon
              :icon="stage.icon"
              size="28"
              :color="stage.color"
              class="mb-2 opacity-40"
            />
            <div class="text-caption text-medium-emphasis">
              {{ $t('recruitment.no_candidates', 'No candidates') }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Candidate Detail Dialog -->
    <CandidateDetailDialog
      v-model:is-dialog-visible="isDetailOpen"
      :candidate="candidateForDetail"
      :stage-color="stageColor"
      @schedule-interview="openInterviewDialog"
      @evaluate-interview="openInterviewDialog"
      @hire="openHireWizard"
      @send-email="openEmailDialog"
      @refresh="loadCandidates"
    />

    <!-- Interview Dialog -->
    <RecruitmentInterviewDialog
      v-model:is-dialog-visible="isInterviewOpen"
      :candidate="candidateForInterview"
      :interview="interviewForEvaluation"
      :opening-options="openingOptions"
      :loading="isSubmittingInterview"
      @submit="handleInterviewSubmit"
    />

    <!-- Contextual Email Dialog -->
    <WpEmailDialog
      v-model="isEmailOpen"
      :title="t('recruitment.email_candidate', 'Email Candidate')"
      :recipients="candidateToEmail?.email ? [candidateToEmail.email] : []"
      :subject="`[Wave Plus] ${t('recruitment.application_update', 'Regarding your application')}`"
      :loading="isSendingEmail"
      @send="handleSendCandidateEmail"
    />

    <!-- Hire Wizard Dialog -->
    <HireWizardDialog
      v-model="hireWizardOpen"
      :candidate="candidateToHire"
      @hired="handleHired"
    />
  </div>
</template>

<style scoped>
.pipeline-board {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1rem;
  overflow-x: auto;
  padding-block-end: 1rem;
}

.pipeline-stage {
  background: rgba(var(--v-theme-surface), 1);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  min-block-size: 520px;
  display: flex;
  flex-direction: column;
}

.pipeline-stage-header {
  padding: 1rem;
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.pipeline-stage-content {
  flex: 1;
  padding: 0.75rem;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.pipeline-card {
  background: rgba(var(--v-theme-surface), 1);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  padding: 0.875rem;
  cursor: move;
  transition: all 0.2s ease;
}

.pipeline-card:hover {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

.pipeline-card:active {
  cursor: grabbing;
  opacity: 0.8;
}

.pipeline-stage-empty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  text-align: center;
}

@media (max-width: 1400px) {
  .pipeline-board {
    grid-template-columns: repeat(4, minmax(260px, 1fr));
  }
}

@media (max-width: 1100px) {
  .pipeline-board {
    grid-template-columns: repeat(2, minmax(240px, 1fr));
  }
}

@media (max-width: 600px) {
  .pipeline-board {
    grid-template-columns: 1fr;
  }
}
</style>
