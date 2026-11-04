<script setup>
/* eslint-disable camelcase */
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useRecruitmentStore } from '@/stores/useRecruitmentStore'
import { avatarText } from '@core/utils/formatters'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  candidate: {
    type: Object,
    default: null,
  },
  stageColor: {
    type: Function,
    default: () => 'default',
  },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'scheduleInterview',
  'evaluateInterview',
  'advanceStage',
  'hire',
  'sendEmail',
  'refresh',
])

const { t, locale } = useI18n()
const { showMessage } = useAppSnackbar()
const recruitmentStore = useRecruitmentStore()

const activeTab = ref('overview')
const isUploading = ref(false)
const fileInput = ref(null)

const formatDate = date => {
  if (!date) return '—'
  try {
    return new Intl.DateTimeFormat(locale.value || undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(new Date(date))
  } catch {
    return String(date)
  }
}

const formatDateOnly = date => {
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

const canBeHired = computed(() => {
  const s = props.candidate?.status

  return ['interview', 'evaluated', 'selected', 'offer'].includes(s)
})

const handleFileUpload = async event => {
  const file = event.target.files?.[0]
  if (!file || !props.candidate?.id) return

  isUploading.value = true
  try {
    await recruitmentStore.uploadMedia(props.candidate.id, file, 'documents')
    showMessage(t('recruitment.document_uploaded', 'Document uploaded successfully.'), 'success')
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || t('recruitment.upload_failed', 'Failed to upload document.'), 'error')
  } finally {
    isUploading.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

const handleDeleteMedia = async mediaId => {
  if (!props.candidate?.id) return
  try {
    await recruitmentStore.deleteMedia(props.candidate.id, mediaId)
    showMessage(t('recruitment.document_deleted', 'Document removed.'), 'success')
    emit('refresh')
  } catch (err) {
    showMessage(err?.data?.message || t('recruitment.delete_failed', 'Failed to delete document.'), 'error')
  }
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="840"
    scrollable
    @update:model-value="emit('update:isDialogVisible', $event)"
  >
    <VCard v-if="props.candidate">
      <!-- 👉 Header -->
      <VCardItem class="pb-3 border-b">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            size="54"
            class="me-3"
          >
            <span class="text-h6 font-weight-bold">{{ avatarText(props.candidate.full_name || '?') }}</span>
          </VAvatar>
        </template>

        <div class="d-flex flex-column">
          <div class="d-flex align-center gap-2 flex-wrap">
            <span class="text-h5 font-weight-bold">{{ props.candidate.full_name }}</span>
            <VChip
              :color="props.stageColor(props.candidate.status)"
              size="small"
              label
              class="text-capitalize"
            >
              {{ props.candidate.status }}
            </VChip>
          </div>
          <span class="text-caption text-medium-emphasis">
            {{ props.candidate.reference }} · {{ props.candidate.email }}
          </span>
        </div>

        <template #append>
          <div class="d-flex align-center gap-2">
            <VBtn
              size="small"
              variant="tonal"
              color="info"
              prepend-icon="tabler-mail"
              @click="emit('sendEmail', props.candidate)"
            >
              {{ t('common.email', 'Email') }}
            </VBtn>

            <VBtn
              v-if="canBeHired"
              size="small"
              color="success"
              prepend-icon="tabler-user-plus"
              @click="emit('hire', props.candidate)"
            >
              {{ t('hire_wizard.hire', 'Hire') }}
            </VBtn>

            <VBtn
              icon="tabler-x"
              variant="text"
              density="compact"
              @click="emit('update:isDialogVisible', false)"
            />
          </div>
        </template>
      </VCardItem>

      <!-- 👉 Tabs Navigation -->
      <VTabs
        v-model="activeTab"
        density="compact"
        class="border-b px-4"
      >
        <VTab value="overview">
          <VIcon
            icon="tabler-user"
            size="18"
            class="me-1"
          />
          {{ t('common.overview', 'Overview') }}
        </VTab>
        <VTab value="applications">
          <VIcon
            icon="tabler-briefcase"
            size="18"
            class="me-1"
          />
          {{ t('recruitment.applications', 'Applications') }}
          <VBadge
            v-if="props.candidate.applications?.length"
            :content="props.candidate.applications.length"
            inline
            color="primary"
            class="ms-2"
          />
        </VTab>
        <VTab value="interviews">
          <VIcon
            icon="tabler-calendar-event"
            size="18"
            class="me-1"
          />
          {{ t('recruitment.interviews', 'Interviews & Evaluations') }}
          <VBadge
            v-if="props.candidate.interviews?.length"
            :content="props.candidate.interviews.length"
            inline
            color="warning"
            class="ms-2"
          />
        </VTab>
        <VTab value="documents">
          <VIcon
            icon="tabler-file-text"
            size="18"
            class="me-1"
          />
          {{ t('recruitment.documents', 'Resumes & Attachments') }}
        </VTab>
      </VTabs>

      <!-- 👉 Content -->
      <VCardText
        class="pa-6"
        style="min-block-size: 340px;"
      >
        <VWindow v-model="activeTab">
          <!-- 1. Overview Tab -->
          <VWindowItem value="overview">
            <VRow>
              <VCol
                cols="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis">
                  {{ t('common.email', 'Email Address') }}
                </div>
                <div class="font-weight-medium">
                  {{ props.candidate.email }}
                </div>
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis">
                  {{ t('common.phone', 'Phone') }}
                </div>
                <div class="font-weight-medium">
                  {{ props.candidate.phone || '—' }}
                </div>
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis">
                  {{ t('common.created_at', 'Registered On') }}
                </div>
                <div class="text-body-2">
                  {{ formatDate(props.candidate.created_at) }}
                </div>
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis">
                  {{ t('recruitment.applied_position', 'Target Role') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ props.candidate.applications?.[0]?.job_title || t('recruitment.general_application', 'General Application') }}
                </div>
              </VCol>

              <VCol cols="12">
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('common.notes', 'Notes & History') }}
                </div>
                <div
                  v-if="props.candidate.notes"
                  class="rounded bg-var-theme-background pa-3 text-body-2 font-mono"
                  style="white-space: pre-wrap;"
                >
                  {{ props.candidate.notes }}
                </div>
                <span
                  v-else
                  class="text-body-2 text-medium-emphasis"
                >—</span>
              </VCol>
            </VRow>
          </VWindowItem>

          <!-- 2. Applications Tab -->
          <VWindowItem value="applications">
            <div
              v-if="props.candidate.applications?.length"
              class="d-flex flex-column gap-3"
            >
              <div
                v-for="app in props.candidate.applications"
                :key="app.id"
                class="rounded border pa-4 d-flex align-center justify-space-between"
              >
                <div>
                  <div class="font-weight-medium text-body-1">
                    {{ app.job_title || t('recruitment.general_application', 'General Application') }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ t('recruitment.applied_on', 'Applied on') }} {{ formatDateOnly(app.application_date) }}
                  </div>
                </div>
                <VChip
                  size="small"
                  color="primary"
                  label
                >
                  {{ app.status }}
                </VChip>
              </div>
            </div>
            <div
              v-else
              class="text-center py-8 text-medium-emphasis"
            >
              <VIcon
                icon="tabler-briefcase"
                size="36"
                class="mb-2 opacity-50"
              />
              <div>{{ t('recruitment.no_applications', 'No specific vacancy linked.') }}</div>
            </div>
          </VWindowItem>

          <!-- 3. Interviews Tab -->
          <VWindowItem value="interviews">
            <div class="d-flex align-center justify-space-between mb-4">
              <span class="text-subtitle-2 font-weight-bold">{{ t('recruitment.scheduled_interviews', 'Scheduled & Completed Interviews') }}</span>
              <VBtn
                size="small"
                variant="tonal"
                color="primary"
                prepend-icon="tabler-calendar-plus"
                @click="emit('scheduleInterview', props.candidate)"
              >
                {{ t('recruitment.schedule_interview', 'Schedule Interview') }}
              </VBtn>
            </div>

            <div
              v-if="props.candidate.interviews?.length"
              class="d-flex flex-column gap-3"
            >
              <div
                v-for="interview in props.candidate.interviews"
                :key="interview.id"
                class="rounded border pa-4"
              >
                <div class="d-flex align-center justify-space-between mb-2">
                  <div class="d-flex align-center gap-2">
                    <VIcon
                      icon="tabler-calendar"
                      size="18"
                      color="warning"
                    />
                    <span class="font-weight-medium">{{ formatDate(interview.scheduled_at) }}</span>
                    <span class="text-caption text-medium-emphasis">({{ interview.duration_minutes }} min)</span>
                  </div>
                  <div class="d-flex align-center gap-2">
                    <VChip
                      size="small"
                      :color="interview.status === 'completed' ? 'success' : (interview.status === 'cancelled' ? 'error' : 'warning')"
                      label
                    >
                      {{ interview.status }}
                    </VChip>
                    <VBtn
                      size="x-small"
                      variant="outlined"
                      color="primary"
                      @click="emit('evaluateInterview', props.candidate, interview)"
                    >
                      {{ interview.status === 'completed' ? t('common.edit', 'Edit') : t('recruitment.evaluate', 'Evaluate') }}
                    </VBtn>
                  </div>
                </div>

                <div class="text-body-2 text-medium-emphasis mb-1">
                  {{ t('recruitment.interviewer', 'Interviewer') }}: <span class="text-high-emphasis font-weight-medium">{{ interview.interviewer_name || t('recruitment.unassigned', 'Unassigned') }}</span>
                </div>

                <!-- Rating -->
                <div
                  v-if="interview.rating"
                  class="d-flex align-center gap-2 my-2"
                >
                  <VRating
                    :model-value="interview.rating"
                    readonly
                    length="5"
                    density="compact"
                    color="warning"
                    active-color="warning"
                  />
                  <span class="text-body-2 font-weight-medium">{{ interview.rating }} / 5</span>
                </div>

                <!-- Feedback -->
                <div
                  v-if="interview.feedback"
                  class="mt-2 text-body-2 rounded bg-var-theme-background pa-2"
                >
                  <span class="text-caption font-weight-bold text-medium-emphasis d-block mb-1">{{ t('recruitment.evaluation_feedback', 'Feedback & Notes') }}:</span>
                  {{ interview.feedback }}
                </div>
              </div>
            </div>
            <div
              v-else
              class="text-center py-8 text-medium-emphasis"
            >
              <VIcon
                icon="tabler-calendar-off"
                size="36"
                class="mb-2 opacity-50"
              />
              <div>{{ t('recruitment.no_interviews', 'No interviews recorded yet.') }}</div>
            </div>
          </VWindowItem>

          <!-- 4. Documents & Resumes Tab -->
          <VWindowItem value="documents">
            <div class="d-flex align-center justify-space-between mb-4">
              <span class="text-subtitle-2 font-weight-bold">{{ t('recruitment.candidate_attachments', 'Candidate Documents & Resumes') }}</span>
              <div>
                <input
                  ref="fileInput"
                  type="file"
                  class="d-none"
                  accept=".pdf,.doc,.docx,.png,.jpg,.jpeg"
                  @change="handleFileUpload"
                >
                <VBtn
                  size="small"
                  variant="tonal"
                  color="primary"
                  prepend-icon="tabler-upload"
                  :loading="isUploading"
                  @click="fileInput?.click()"
                >
                  {{ t('common.upload_file', 'Upload Document') }}
                </VBtn>
              </div>
            </div>

            <!-- Resumes List -->
            <div class="mb-4">
              <div class="text-caption font-weight-bold text-medium-emphasis mb-2">
                {{ t('recruitment.resume_cv', 'Résumé / Curriculum Vitae') }}
              </div>
              <div
                v-if="props.candidate.resumes?.length"
                class="d-flex flex-column gap-2"
              >
                <div
                  v-for="file in props.candidate.resumes"
                  :key="file.id"
                  class="rounded border pa-3 d-flex align-center justify-space-between"
                >
                  <div class="d-flex align-center gap-3">
                    <VIcon
                      icon="tabler-file-text"
                      size="24"
                      color="primary"
                    />
                    <div>
                      <div class="font-weight-medium text-body-2">
                        {{ file.name }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ file.size ? `${Math.round(file.size / 1024)} KB` : '' }}
                      </div>
                    </div>
                  </div>
                  <div class="d-flex align-center gap-1">
                    <VBtn
                      icon="tabler-download"
                      variant="text"
                      density="compact"
                      color="primary"
                      :href="file.download_url"
                      target="_blank"
                      rel="noopener noreferrer"
                    />
                    <VBtn
                      icon="tabler-trash"
                      variant="text"
                      density="compact"
                      color="error"
                      @click="handleDeleteMedia(file.id)"
                    />
                  </div>
                </div>
              </div>
              <div
                v-else
                class="text-caption text-medium-emphasis"
              >
                {{ t('recruitment.no_resume', 'No résumé uploaded yet.') }}
              </div>
            </div>

            <!-- Other Documents List -->
            <div>
              <div class="text-caption font-weight-bold text-medium-emphasis mb-2">
                {{ t('recruitment.other_documents', 'Supporting Documents & Certifications') }}
              </div>
              <div
                v-if="props.candidate.documents?.length"
                class="d-flex flex-column gap-2"
              >
                <div
                  v-for="file in props.candidate.documents"
                  :key="file.id"
                  class="rounded border pa-3 d-flex align-center justify-space-between"
                >
                  <div class="d-flex align-center gap-3">
                    <VIcon
                      icon="tabler-file"
                      size="24"
                      color="secondary"
                    />
                    <div>
                      <div class="font-weight-medium text-body-2">
                        {{ file.name }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ file.size ? `${Math.round(file.size / 1024)} KB` : '' }}
                      </div>
                    </div>
                  </div>
                  <div class="d-flex align-center gap-1">
                    <VBtn
                      icon="tabler-download"
                      variant="text"
                      density="compact"
                      color="primary"
                      :href="file.download_url"
                      target="_blank"
                      rel="noopener noreferrer"
                    />
                    <VBtn
                      icon="tabler-trash"
                      variant="text"
                      density="compact"
                      color="error"
                      @click="handleDeleteMedia(file.id)"
                    />
                  </div>
                </div>
              </div>
              <div
                v-else
                class="text-caption text-medium-emphasis"
              >
                {{ t('recruitment.no_additional_docs', 'No additional documents uploaded.') }}
              </div>
            </div>
          </VWindowItem>
        </VWindow>
      </VCardText>

      <VCardActions class="justify-end border-t px-6 py-3">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:isDialogVisible', false)"
        >
          {{ t('common.close', 'Close') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
