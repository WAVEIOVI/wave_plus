<script setup>
/* eslint-disable camelcase */
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useHrPlannerStore } from '@/stores/useHrPlannerStore'
import { WpEmptyState, WpLoadingState } from '@/components/shared'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'selectBatch'])

const { t } = useI18n()
const { smAndDown } = useDisplay()
const { showMessage } = useAppSnackbar()
const planner = useHrPlannerStore()

const close = () => {
  emit('update:modelValue', false)
}

const loadHistory = async () => {
  try {
    await planner.fetchScheduleHistory()
  } catch (err) {
    showMessage(err?.data?.message || 'Unable to load schedule history', 'error')
  }
}

watch(() => props.modelValue, isOpen => {
  if (isOpen) {
    loadHistory()
  }
})

const viewInGrid = batch => {
  if (batch.start_date && batch.end_date) {
    planner.setDateRange(batch.start_date, batch.end_date)
    close()
  }
}

const publishBatch = async batch => {
  try {
    const res = await planner.publishSchedules({ batch_id: batch.plan_batch_id })

    showMessage(`${res?.published_count ?? 0} batch schedules published`, 'success')
    await loadHistory()
  } catch (err) {
    showMessage(err?.data?.message || 'Unable to publish batch schedules', 'error')
  }
}

const clearBatch = async batch => {
  try {
    const res = await planner.clearSchedules({ batch_id: batch.plan_batch_id })

    showMessage(`${res?.deleted_count ?? 0} batch schedules removed`, 'success')
    await loadHistory()
  } catch (err) {
    showMessage(err?.data?.message || 'Unable to clear batch schedules', 'error')
  }
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="840"
    :fullscreen="smAndDown"
    @update:model-value="value => !value && close()"
  >
    <DialogCloseBtn @click="close" />

    <VCard class="d-flex flex-column h-100 rounded-0 rounded-sm">
      <VCardItem class="pa-4 pa-sm-6 pb-3">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            size="42"
          >
            <VIcon
              icon="tabler-history"
              size="22"
            />
          </VAvatar>
        </template>
        <VCardTitle>
          {{ t('workforce.schedule_history', 'Schedule Batch History') }}
        </VCardTitle>
        <VCardSubtitle>
          {{ t('workforce.schedule_history_subtitle', 'Review and manage past bulk scheduling batches and draft publishing.') }}
        </VCardSubtitle>
      </VCardItem>

      <VDivider />

      <VCardText class="pa-4 pa-sm-6 flex-grow-1 overflow-y-auto">
        <WpLoadingState v-if="planner.isHistoryLoading" />

        <WpEmptyState
          v-else-if="!planner.historyList.length"
          icon="tabler-calendar-off"
          :title="t('workforce.no_history_found', 'No bulk scheduling batches found')"
          :description="t('workforce.no_history_desc', 'Run bulk schedule to generate planned rosters.')"
        />

        <div
          v-else
          class="d-flex flex-column gap-4"
        >
          <VCard
            v-for="batch in planner.historyList"
            :key="batch.plan_batch_id"
            variant="outlined"
            class="pa-4 batch-card"
          >
            <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between gap-3">
              <div class="d-flex flex-column gap-1">
                <div class="d-flex align-center gap-2 flex-wrap">
                  <span class="text-subtitle-1 font-weight-bold">
                    {{ batch.start_date }} → {{ batch.end_date }}
                  </span>
                  <VChip
                    size="small"
                    variant="tonal"
                    color="secondary"
                    class="font-mono"
                  >
                    {{ batch.plan_batch_id.slice(0, 8) }}…
                  </VChip>
                  <VChip
                    v-if="batch.draft_count > 0"
                    size="small"
                    color="warning"
                    variant="tonal"
                  >
                    {{ batch.draft_count }} {{ t('workforce.status.draft', 'Draft') }}
                  </VChip>
                  <VChip
                    v-if="batch.planned_count > 0"
                    size="small"
                    color="primary"
                    variant="tonal"
                  >
                    {{ batch.planned_count }} {{ t('workforce.status.planned', 'Planned') }}
                  </VChip>
                  <VChip
                    v-if="batch.completed_count > 0"
                    size="small"
                    color="success"
                    variant="tonal"
                  >
                    {{ batch.completed_count }} {{ t('workforce.status.completed', 'Completed') }}
                  </VChip>
                </div>

                <div
                  v-if="batch.notes"
                  class="text-caption text-medium-emphasis mt-1"
                >
                  <VIcon
                    icon="tabler-notes"
                    size="14"
                    class="me-1"
                  />
                  {{ batch.notes }}
                </div>

                <div class="text-caption text-disabled mt-1">
                  {{ t('workforce.created_at', 'Created') }}: {{ batch.created_at }} &bull; {{ batch.total_schedules }} {{ t('workforce.total_slots', 'total slots') }}
                </div>
              </div>

              <div class="d-flex align-center gap-2 flex-wrap justify-end">
                <VBtn
                  size="small"
                  variant="outlined"
                  color="primary"
                  prepend-icon="tabler-eye"
                  @click="viewInGrid(batch)"
                >
                  {{ t('workforce.view_in_grid', 'View in Grid') }}
                </VBtn>

                <VBtn
                  v-if="batch.draft_count > 0"
                  size="small"
                  color="warning"
                  variant="tonal"
                  prepend-icon="tabler-upload"
                  :disabled="planner.isSaving"
                  @click="publishBatch(batch)"
                >
                  {{ t('workforce.publish', 'Publish') }} ({{ batch.draft_count }})
                </VBtn>

                <VBtn
                  v-if="batch.draft_count > 0"
                  size="small"
                  color="error"
                  variant="text"
                  icon="tabler-trash"
                  :disabled="planner.isSaving"
                  :title="t('workforce.clear_batch', 'Clear batch drafts')"
                  @click="clearBatch(batch)"
                />
              </div>
            </div>
          </VCard>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 justify-end">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="close"
        >
          {{ t('Close') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.batch-card {
  border-radius: 10px;
  background-color: rgba(var(--v-theme-on-surface), 0.015);
}
.font-mono {
  font-family: monospace;
}
</style>
