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
import { useTrainingActions } from '@/composables/people-skills/training/useTrainingActions'
import { useTrainingDialogs } from '@/composables/people-skills/training/useTrainingDialogs'
import { useTrainingList } from '@/composables/people-skills/training/useTrainingList'
import { localizedValue } from '@/composables/useLocaleField'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import TrainingEnrollDialog from '@/views/people-skills/training/TrainingEnrollDialog.vue'
import TrainingProgramDialog from '@/views/people-skills/training/TrainingProgramDialog.vue'
import TrainingSessionDialog from '@/views/people-skills/training/TrainingSessionDialog.vue'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

definePage({
  path: '/people-skills/training/list',
  name: 'people-skills-training-list',
  meta: {
    layout: 'default',
    module: 'people-skills',
    action: 'read',
    subject: 'training',
  },
})

const { t } = useI18n()
const ability = useAbility()

// ── List Orchestration ──────────────────────────────────────────────────────
const {
  store,
  currentTab,
  tabs,
  search,
  sessionHeaders,
  programHeaders,
  filteredSessions,
  filteredPrograms,
  kpis,
  formatDate,
  statusColor,
  loadData,
} = useTrainingList()

// ── Dialog State ────────────────────────────────────────────────────────────
const dialogs = useTrainingDialogs()

// ── Actions ─────────────────────────────────────────────────────────────────
const actions = useTrainingActions({ dialogs })

// ── Program Options for Session Dialog ──────────────────────────────────────
const programOptions = computed(() =>
  store.programs.map(item => ({
    title: `${localizedValue(item.name)} (${item.code})`,
    value: item.id,
  })),
)

// ── Session Row Actions ─────────────────────────────────────────────────────
const getSessionRowActions = item => [
  { label: t('training.view_participants', 'View Participants'), icon: 'tabler-eye', emit: 'view' },
  { label: t('training.enroll_employee', 'Enroll Employee'), icon: 'tabler-user-plus', emit: 'enroll' },
  { label: t('common.edit', 'Edit'), icon: 'tabler-edit', emit: 'edit' },
  { label: t('common.delete', 'Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
]

// ── Program Row Actions ─────────────────────────────────────────────────────
const getProgramRowActions = item => [
  { label: t('common.edit', 'Edit'), icon: 'tabler-edit', emit: 'edit' },
  { label: t('training.schedule_session', 'Schedule Session'), icon: 'tabler-calendar-plus', emit: 'schedule' },
  { label: t('common.delete', 'Delete'), icon: 'tabler-trash', emit: 'delete', color: 'error', dividerBefore: true },
]

// ── Mobile FAB ──────────────────────────────────────────────────────────────
const mobileFabActions = computed(() => [
  {
    id: 'schedule-session',
    icon: 'tabler-calendar-plus',
    title: t('training.schedule_session', 'Schedule Session'),
    onClick: () => dialogs.openSession(),
  },
  {
    id: 'new-program',
    icon: 'tabler-school',
    title: t('training.new_program', 'New Program'),
    onClick: () => dialogs.openProgram(),
  },
])
</script>

<template>
  <div class="people-skills-training-list d-flex flex-column gap-6">
    <!-- 👉 Page Header -->
    <WpPageHeader
      :title="$t('training.title', 'Training & Development')"
      :subtitle="$t('training.subtitle', 'Plan learning programs, schedule sessions, enroll employees, and certify skills.')"
      icon="tabler-school"
    >
      <template
        v-if="ability.can('manage', 'training')"
        #actions
      >
        <VBtn
          variant="outlined"
          color="secondary"
          prepend-icon="tabler-school"
          @click="dialogs.openProgram()"
        >
          {{ $t('training.new_program', 'New Program') }}
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-calendar-plus"
          @click="dialogs.openSession()"
        >
          {{ $t('training.schedule_session', 'Schedule Session') }}
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
      class="training-list__tabs"
    >
      <!-- ── Tab: Sessions ───────────────────────────────────────────────── -->
      <template #tab-sessions>
        <VCard>
          <WpPageToolbar
            v-model:search="search"
            :create-label="ability.can('manage', 'training') ? $t('training.schedule_session', 'Schedule Session') : ''"
            :loading="store.loading"
            @create="dialogs.openSession()"
            @refresh="loadData"
          />

          <WpResponsiveList
            :headers="sessionHeaders"
            :items="filteredSessions"
            :total-items="filteredSessions.length"
            :loading="store.loading"
            hide-pagination
            @retry="loadData"
          >
            <template #item.session="{ item }">
              <div class="d-flex flex-column py-1">
                <span class="text-body-2 font-weight-medium">{{ item.session_code }}</span>
                <span class="text-caption text-medium-emphasis">
                  {{ localizedValue(item.trainer, $t('training.trainer_unassigned', 'Trainer not assigned')) }}
                </span>
              </div>
            </template>

            <template #item.program="{ item }">
              <span class="text-body-2">{{ localizedValue(item.program?.name, '—') }}</span>
            </template>

            <template #item.dates="{ item }">
              <span class="text-body-2">{{ formatDate(item.start_date) }} – {{ formatDate(item.end_date) }}</span>
            </template>

            <template #item.participants="{ item }">
              <span class="text-body-2">
                {{ item.participants?.length || 0 }}{{ item.max_participants ? ` / ${item.max_participants}` : '' }}
              </span>
            </template>

            <template #item.status="{ item }">
              <VChip
                :color="statusColor(item.status)"
                size="small"
                label
                class="text-capitalize"
              >
                {{ item.status }}
              </VChip>
            </template>

            <template #item.actions="{ item }">
              <WpRowActions
                :actions="getSessionRowActions(item)"
                @view="dialogs.openDetail(item)"
                @enroll="dialogs.openEnroll(item)"
                @edit="dialogs.openSession(item)"
                @delete="dialogs.openDelete(item, 'session')"
              />
            </template>

            <template #mobile-card="{ item }">
              <WpMobileCard
                :title="item.session_code"
                :subtitle="localizedValue(item.program?.name, '—')"
                :badge="item.status"
                :badge-color="statusColor(item.status)"
                :actions="getSessionRowActions(item)"
                @view="dialogs.openDetail(item)"
                @enroll="dialogs.openEnroll(item)"
                @edit="dialogs.openSession(item)"
                @delete="dialogs.openDelete(item, 'session')"
              >
                <div class="d-flex flex-column gap-1 text-caption text-medium-emphasis mt-2">
                  <div class="d-flex align-center gap-2">
                    <VIcon icon="tabler-calendar" size="16" />
                    <span>{{ formatDate(item.start_date) }} – {{ formatDate(item.end_date) }}</span>
                  </div>
                  <div class="d-flex align-center gap-2">
                    <VIcon icon="tabler-users" size="16" />
                    <span>{{ item.participants?.length || 0 }} {{ $t('training.col.participants', 'Participants') }}</span>
                  </div>
                </div>
              </WpMobileCard>
            </template>
          </WpResponsiveList>
        </VCard>
      </template>

      <!-- ── Tab: Programs ───────────────────────────────────────────────── -->
      <template #tab-programs>
        <VCard>
          <WpPageToolbar
            v-model:search="search"
            :create-label="ability.can('manage', 'training') ? $t('training.new_program', 'New Program') : ''"
            :loading="store.loading"
            @create="dialogs.openProgram()"
            @refresh="loadData"
          />

          <WpResponsiveList
            :headers="programHeaders"
            :items="filteredPrograms"
            :total-items="filteredPrograms.length"
            :loading="store.loading"
            hide-pagination
            @retry="loadData"
          >
            <template #item.name="{ item }">
              <div class="d-flex flex-column py-1">
                <span class="text-body-2 font-weight-medium">{{ localizedValue(item.name) }}</span>
                <span class="text-caption text-medium-emphasis">
                  {{ item.code }} · {{ item.category || $t('common.general', 'General') }}
                </span>
              </div>
            </template>

            <template #item.provider="{ item }">
              <span class="text-body-2">{{ localizedValue(item.provider, $t('training.internal', 'Internal')) }}</span>
            </template>

            <template #item.duration_hours="{ item }">
              <span class="text-body-2">{{ item.duration_hours }}h</span>
            </template>

            <template #item.sessions_count="{ item }">
              <span class="text-body-2">{{ item.sessions_count || item.sessions?.length || 0 }}</span>
            </template>

            <template #item.is_active="{ item }">
              <VChip
                :color="item.is_active ? 'success' : 'secondary'"
                size="small"
                label
              >
                {{ item.is_active ? $t('common.active', 'Active') : $t('common.inactive', 'Inactive') }}
              </VChip>
            </template>

            <template #item.actions="{ item }">
              <WpRowActions
                :actions="getProgramRowActions(item)"
                @edit="dialogs.openProgram(item)"
                @schedule="dialogs.openSession({ program: item, training_program_id: item.id })"
                @delete="dialogs.openDelete(item, 'program')"
              />
            </template>

            <template #mobile-card="{ item }">
              <WpMobileCard
                :title="localizedValue(item.name)"
                :subtitle="`${item.code} · ${item.duration_hours}h`"
                :badge="item.is_active ? 'Active' : 'Inactive'"
                :badge-color="item.is_active ? 'success' : 'secondary'"
                :actions="getProgramRowActions(item)"
                @edit="dialogs.openProgram(item)"
                @schedule="dialogs.openSession({ program: item, training_program_id: item.id })"
                @delete="dialogs.openDelete(item, 'program')"
              >
                <div class="text-caption text-medium-emphasis mt-2">
                  {{ localizedValue(item.provider, $t('training.internal', 'Internal')) }}
                </div>
              </WpMobileCard>
            </template>
          </WpResponsiveList>
        </VCard>
      </template>
    </AppHybridTabs>

    <!-- 👉 Program Create/Edit Dialog -->
    <TrainingProgramDialog
      v-model:is-dialog-visible="dialogs.programDialog.isOpen.value"
      :program="dialogs.selectedItem.value"
      :loading="actions.isSaving.value"
      @submit="actions.handleSaveProgram"
    />

    <!-- 👉 Session Create/Edit Dialog -->
    <TrainingSessionDialog
      v-model:is-dialog-visible="dialogs.sessionDialog.isOpen.value"
      :session="dialogs.selectedItem.value"
      :program-options="programOptions"
      :loading="actions.isSaving.value"
      @submit="actions.handleSaveSession"
    />

    <!-- 👉 Enroll Employee Dialog -->
    <TrainingEnrollDialog
      v-model:is-dialog-visible="dialogs.enrollDialog.isOpen.value"
      :session="dialogs.selectedItem.value"
      :loading="actions.isSaving.value"
      @submit="actions.handleEnroll($event.sessionId, $event.employeeId)"
    />

    <!-- 👉 Session Detail Dialog (Participants & Completion) -->
    <WpDetailDialog
      :model-value="dialogs.detailDialog.isOpen.value"
      :title="dialogs.selectedItem.value?.session_code"
      :subtitle="localizedValue(dialogs.selectedItem.value?.program?.name)"
      size="medium"
      @update:model-value="dialogs.closeDetail"
    >
      <div v-if="dialogs.selectedItem.value">
        <div class="d-flex justify-space-between align-center mb-4">
          <span class="text-subtitle-1 font-weight-bold">
            {{ $t('training.participants_list', 'Enrolled Participants') }} ({{ dialogs.selectedItem.value.participants?.length || 0 }})
          </span>
          <VBtn
            color="primary"
            size="small"
            prepend-icon="tabler-user-plus"
            @click="dialogs.openEnroll(dialogs.selectedItem.value)"
          >
            {{ $t('training.enroll_employee', 'Enroll Employee') }}
          </VBtn>
        </div>

        <div
          v-if="dialogs.selectedItem.value.participants?.length"
          class="d-flex flex-column gap-3"
        >
          <div
            v-for="participant in dialogs.selectedItem.value.participants"
            :key="participant.id"
            class="d-flex justify-space-between align-center rounded border pa-3"
          >
            <div class="d-flex align-center gap-3">
              <VAvatar color="primary" variant="tonal" size="36">
                <VIcon icon="tabler-user" size="18" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="font-weight-medium text-body-2">
                  {{ participant.employee?.full_name || participant.employee_name || '—' }}
                </span>
                <span class="text-caption text-medium-emphasis">
                  {{ participant.employee?.employee_number || '—' }}
                </span>
              </div>
            </div>

            <div class="d-flex align-center gap-2">
              <VChip
                :color="statusColor(participant.status)"
                size="small"
                label
                class="text-capitalize"
              >
                {{ participant.status }}
              </VChip>

              <VBtn
                v-if="participant.status !== 'completed' && ability.can('manage', 'training')"
                size="small"
                color="success"
                variant="tonal"
                prepend-icon="tabler-circle-check"
                :loading="actions.isSaving.value"
                @click="actions.handleCompleteParticipant(participant.id, { status: 'completed' })"
              >
                {{ $t('training.complete', 'Complete') }}
              </VBtn>

              <VBtn
                v-if="ability.can('manage', 'training')"
                icon="tabler-trash"
                size="x-small"
                variant="text"
                color="error"
                @click="dialogs.openDelete(participant, 'participant')"
              />
            </div>
          </div>
        </div>
        <div
          v-else
          class="text-body-2 text-medium-emphasis py-4 text-center border rounded"
        >
          {{ $t('training.no_participants', 'No employees currently enrolled in this session.') }}
        </div>
      </div>
    </WpDetailDialog>

    <!-- 👉 Delete Confirmation Dialog -->
    <WpDeleteDialog
      v-model:is-dialog-visible="dialogs.deleteDialog.isOpen.value"
      :title="$t('common.delete', 'Delete Record')"
      :confirmation-text="$t('common.delete_confirm', 'Are you sure you want to proceed? This action cannot be undone.')"
      :item-name="dialogs.selectedItem.value?.session_code || localizedValue(dialogs.selectedItem.value?.name)"
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
