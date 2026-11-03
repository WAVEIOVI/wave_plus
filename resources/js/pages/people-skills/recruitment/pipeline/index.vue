<script setup>
/* eslint-disable camelcase */
import { WpPageHeader } from '@/components/shared'
import HireWizardDialog from '@/components/people-skills/HireWizardDialog.vue'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useRecruitmentStore } from '@/stores/useRecruitmentStore'
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
    subject: 'PeopleSkills',
  },
})

const { t, locale } = useI18n()
const { showMessage } = useAppSnackbar()
const router = useRouter()
const recruitmentStore = useRecruitmentStore()

// ── State ─────────────────────────────────────────────────────────────────
const loading = ref(false)
const draggedCandidate = ref(null)
const hireWizardOpen = ref(false)
const candidateToHire = ref(null)

// ── Pipeline Stages ───────────────────────────────────────────────────────
const stages = ref([
  { id: 'applied', color: 'info', icon: 'tabler-send', candidates: [] },
  { id: 'screening', color: 'primary', icon: 'tabler-search', candidates: [] },
  { id: 'interview', color: 'warning', icon: 'tabler-user-check', candidates: [] },
  { id: 'offer', color: 'success', icon: 'tabler-file-text', candidates: [] },
  { id: 'hired', color: 'success', icon: 'tabler-check', candidates: [] },
  { id: 'rejected', color: 'error', icon: 'tabler-x', candidates: [] },
])

// ── Computed ──────────────────────────────────────────────────────────────
const totalCandidates = computed(() =>
  stages.value.reduce((sum, stage) => sum + stage.candidates.length, 0),
)

// ── Load Candidates ───────────────────────────────────────────────────────
const loadCandidates = async () => {
  loading.value = true
  try {
    await recruitmentStore.fetchList({ per_page: 200 })

    // Reset all stage candidates
    stages.value.forEach(stage => {
      stage.candidates = []
    })

    // Group candidates by stage/status
    recruitmentStore.list.forEach(candidate => {
      const stage = stages.value.find(s => s.id === candidate.status)
      if (stage) {
        stage.candidates.push(candidate)
      }
    })
  } catch (err) {
    showMessage(err?.data?.message || t('recruitment.load_error'), 'error')
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
  toStage.candidates.push(candidate)

  // Update backend
  try {
    await recruitmentStore.updateStage(candidate.id, toStage.id)
  } catch (err) {
    showMessage(err?.data?.message || t('recruitment.stage_error'), 'error')

    // Revert on error
    toStage.candidates = toStage.candidates.filter(c => c.id !== candidate.id)
    fromStage.candidates.push(candidate)
  }

  draggedCandidate.value = null
}

// ── Actions ───────────────────────────────────────────────────────────────
const viewCandidate = candidateId => {
  router.push({ name: 'people-skills-recruitment-list' })
}

const openHireWizard = candidate => {
  candidateToHire.value = candidate
  hireWizardOpen.value = true
}

const handleHired = () => {
  loadCandidates() // Refresh pipeline
}

// ── Lifecycle ─────────────────────────────────────────────────────────────
onMounted(() => loadCandidates())
</script>

<template>
  <div class="people-skills-recruitment-pipeline d-flex flex-column gap-6">
    <WpPageHeader
      :title="$t('recruitment.pipeline_title')"
      :subtitle="$t('recruitment.pipeline_subtitle')"
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
          {{ totalCandidates }} {{ $t('recruitment.candidates') }}
        </VChip>
        <VBtn
          variant="tonal"
          prepend-icon="tabler-refresh"
          :loading="loading"
          @click="loadCandidates"
        >
          {{ $t('Refresh') }}
        </VBtn>
        <VBtn
          variant="outlined"
          color="secondary"
          prepend-icon="tabler-arrow-left"
          :to="{ name: 'people-skills-recruitment-list' }"
        >
          {{ $t('recruitment.return_to_list') }}
        </VBtn>
      </template>
    </WpPageHeader>

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
            <span class="font-weight-bold">{{ $t(`recruitment.stage.${stage.id}`) }}</span>
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
          >
            <div class="d-flex align-center gap-2 mb-2">
              <VAvatar
                size="36"
                color="primary"
                variant="tonal"
              >
                <span class="text-caption">{{ avatarText(candidate.full_name || '?') }}</span>
              </VAvatar>
              <div class="flex-1-1-0">
                <div class="text-body-2 font-weight-semibold">
                  {{ candidate.full_name }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ candidate.email }}
                </div>
              </div>
            </div>

            <div
              v-if="candidate.position"
              class="text-caption mb-1"
            >
              <VIcon
                icon="tabler-briefcase"
                size="14"
                class="me-1"
              />
              {{ candidate.position }}
            </div>

            <div
              v-if="candidate.phone"
              class="text-caption mb-1"
            >
              <VIcon
                icon="tabler-phone"
                size="14"
                class="me-1"
              />
              {{ candidate.phone }}
            </div>

            <div
              v-if="candidate.application_date"
              class="text-caption text-medium-emphasis mt-2"
            >
              {{ new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'short', day: 'numeric' }).format(new Date(candidate.application_date)) }}
            </div>

            <!-- Hire Action Button -->
            <VBtn
              v-if="stage.id === 'offer' || stage.id === 'interview'"
              block
              size="small"
              color="success"
              variant="tonal"
              class="mt-2"
              @click.stop="openHireWizard(candidate)"
            >
              <VIcon
                icon="tabler-user-plus"
                start
              />
              {{ $t('hire_wizard.hire') }}
            </VBtn>
          </div>

          <div
            v-if="stage.candidates.length === 0 && !loading"
            class="pipeline-stage-empty"
          >
            <VIcon
              :icon="stage.icon"
              size="32"
              :color="stage.color"
              class="mb-2 opacity-50"
            />
            <div class="text-caption text-medium-emphasis">
              {{ $t('recruitment.no_candidates') }}
            </div>
          </div>
        </div>
      </div>
    </div>

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
  min-block-size: 500px;
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
  padding: 1rem;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.pipeline-card {
  background: rgba(var(--v-theme-surface), 1);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  padding: 1rem;
  cursor: move;
  transition: all 0.2s ease;
}

.pipeline-card:hover {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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

@media (max-width: 1200px) {
  .pipeline-board {
    grid-template-columns: repeat(3, minmax(240px, 1fr));
  }
}

@media (max-width: 960px) {
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
