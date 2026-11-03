<script setup>
/* eslint-disable camelcase */
import { localizedValue } from '@/composables/useLocaleField'
import { computed, nextTick, reactive, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  employee: { type: Object, default: null },
  date: { type: String, default: '' },
  schedule: { type: Object, default: null },
  leave: { type: Object, default: null },
  attendance: { type: Object, default: null },
  shiftOptions: { type: Array, default: () => [] },
  defaultShiftId: { type: [String, Number], default: null },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'submit', 'remove'])

const { t, locale } = useI18n()
const { smAndDown } = useDisplay()
const formId = `workforce-schedule-${useId()}`
const formEl = ref(null)

const formState = reactive({ shift_id: null, lifecycle_status: 'planned', notes: '' })

// ── Options ───────────────────────────────────────────────────────────────
const statusOptions = computed(() => [
  { title: t('workforce.status.draft', 'Draft'), value: 'draft', icon: 'tabler-pencil', color: 'secondary' },
  { title: t('workforce.status.planned', 'Planned'), value: 'planned', icon: 'tabler-calendar-check', color: 'primary' },
  { title: t('workforce.status.cancelled', 'Cancelled'), value: 'cancelled', icon: 'tabler-calendar-off', color: 'error' },
])

const activeStatusColor = computed(() =>
  statusOptions.value.find(option => option.value === formState.lifecycle_status)?.color || 'primary')

// ── Display ───────────────────────────────────────────────────────────────
const employeeName = computed(() => localizedValue(props.employee?.full_name))

// "2026-10-12" → "Monday, 12 October 2026" in the active app language.
const dateLabel = computed(() => {
  if (!props.date || !/^\d{4}-\d{2}-\d{2}$/.test(props.date))
    return props.date || ''

  const [year, month, day] = props.date.split('-').map(Number)
  const parsed = new Date(year, month - 1, day)

  // Reject impossible calendar dates rather than displaying a normalized date.
  if (
    Number.isNaN(parsed.getTime())
    || parsed.getFullYear() !== year
    || parsed.getMonth() !== month - 1
    || parsed.getDate() !== day
  )
    return props.date

  return parsed.toLocaleDateString(locale.value, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
})

// ── Validation ────────────────────────────────────────────────────────────
const requiredRule = value =>
  (value !== null && value !== undefined && value !== '') || t('workforce.validation.required', 'This field is required.')

// ── Actions ───────────────────────────────────────────────────────────────
const close = () => {
  if (!props.saving)
    emit('update:modelValue', false)
}

const submit = async () => {
  if (props.saving)
    return

  const result = await formEl.value?.validate?.()
  if (!result?.valid)
    return

  emit('submit', { ...formState })
}

watch(() => props.modelValue, async isOpen => {
  if (isOpen) {
    Object.assign(formState, {
      shift_id: props.schedule?.shift_id ?? props.defaultShiftId ?? null,
      lifecycle_status: props.schedule?.lifecycle_status ?? 'planned',
      notes: props.schedule?.notes || '',
    })
  }

  await nextTick()
  formEl.value?.resetValidation?.()
}, { immediate: true })
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="600"
    :fullscreen="smAndDown"
    :persistent="saving"
    @update:model-value="value => !value && close()"
  >
    <DialogCloseBtn
      :disabled="saving"
      @click="close"
    />
    <VCard class="dialog-card d-flex flex-column h-100 rounded-0 rounded-sm">
      <VCardItem class="dialog-header">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            size="44"
          >
            <VIcon
              icon="tabler-calendar-plus"
              size="24"
            />
          </VAvatar>
        </template>
        <VCardTitle>
          {{ schedule ? $t('workforce.edit_schedule', 'Edit schedule') : $t('workforce.add_schedule', 'Assign schedule') }}
        </VCardTitle>
        <VCardSubtitle class="dialog-subtitle text-wrap">
          <span class="dialog-subtitle__name">{{ employeeName }}</span>
          <span class="dialog-subtitle__date">{{ dateLabel }}</span>
        </VCardSubtitle>
      </VCardItem>

      <VDivider />

      <VCardText class="dialog-body">
        <PerfectScrollbar
          class="dialog-scroll"
          :options="{ wheelPropagation: false }"
        >
          <div class="pa-4 pa-sm-6">
            <!-- Context: what already exists on this day -->
            <div
              v-if="leave || attendance"
              class="context-notices"
            >
              <VAlert
                v-if="leave"
                type="info"
                variant="tonal"
                density="comfortable"
                icon="tabler-beach"
              >
                {{ $t(
                  'workforce.leave_notice',
                  { type: localizedValue(leave.leave_type_name, $t('workforce.leave', 'Leave')) },
                  'Approved leave: {type}. The planner preserves leave coverage.',
                ) }}
              </VAlert>

              <VAlert
                v-if="attendance"
                type="success"
                variant="tonal"
                density="comfortable"
                icon="tabler-clock-check"
              >
                <div class="text-body-2">
                  {{ $t('workforce.actual_attendance', 'Actual attendance') }}
                </div>
                <div class="attendance-times">
                  <span>{{ attendance.actual_in || '—' }}</span>
                  <VIcon
                    icon="tabler-arrow-right"
                    size="16"
                    class="flip-in-rtl"
                  />
                  <span>{{ attendance.actual_out || '—' }}</span>
                </div>
              </VAlert>
            </div>

            <VForm
              :id="formId"
              ref="formEl"
              validate-on="blur"
              @submit.prevent="submit"
            >
              <div class="form-sections">
                <section>
                  <div class="form-section__header">
                    <VAvatar
                      color="primary"
                      variant="tonal"
                      rounded
                      size="28"
                      class="form-section__avatar"
                    >
                      <VIcon
                        icon="tabler-calendar-clock"
                        size="16"
                      />
                    </VAvatar>
                    <span class="form-section__title">{{ $t('workforce.form.sections.timing', 'Schedule details') }}</span>
                  </div>
                  <VRow>
                    <VCol cols="12">
                      <AppSelect
                        v-model="formState.shift_id"
                        :disabled="saving"
                        :items="shiftOptions"
                        item-title="title"
                        item-value="id"
                        :label="$t('workforce.col.shift', 'Shift')"
                        :placeholder="$t('workforce.select_shift', 'Select a shift')"
                        prepend-inner-icon="tabler-clock"
                        :rules="[requiredRule]"
                      />
                    </VCol>

                    <VCol cols="12">
                      <VLabel class="mb-2 text-body-2 text-wrap text-high-emphasis">
                        {{ $t('workforce.col.sch_status', 'Status') }}
                      </VLabel>
                      <VBtnToggle
                        v-model="formState.lifecycle_status"
                        :disabled="saving"
                        mandatory
                        divided
                        variant="outlined"
                        :color="activeStatusColor"
                        class="w-100"
                        :aria-label="$t('workforce.col.sch_status', 'Status')"
                      >
                        <VBtn
                          v-for="option in statusOptions"
                          :key="option.value"
                          :value="option.value"
                          :prepend-icon="option.icon"
                          class="flex-grow-1"
                        >
                          {{ option.title }}
                        </VBtn>
                      </VBtnToggle>
                    </VCol>

                    <VCol cols="12">
                      <AppTextarea
                        v-model="formState.notes"
                        :disabled="saving"
                        :label="$t('workforce.col.notes', 'Notes')"
                        :placeholder="$t('workforce.notes_placeholder', 'Add a note for this day (optional)')"
                        rows="3"
                        auto-grow
                        @keydown.ctrl.enter.prevent="submit"
                        @keydown.meta.enter.prevent="submit"
                      />
                    </VCol>
                  </VRow>
                </section>
              </div>
            </VForm>
          </div>
        </PerfectScrollbar>
      </VCardText>

      <VDivider />

      <VCardActions class="dialog-footer">
        <VBtn
          v-if="schedule"
          color="error"
          variant="text"
          class="me-auto"
          prepend-icon="tabler-trash"
          :disabled="saving"
          @click="emit('remove')"
        >
          {{ $t('Remove') }}
        </VBtn>
        <VBtn
          type="button"
          variant="tonal"
          color="secondary"
          class="flex-grow-1 flex-sm-grow-0"
          :disabled="saving"
          @click="close"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          type="submit"
          :form="formId"
          color="primary"
          class="flex-grow-1 flex-sm-grow-0"
          :loading="saving"
        >
          {{ $t('Save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.dialog-header {
  flex-shrink: 0;
  padding-block: 1rem;
  padding-inline: 1rem 3.5rem;

  @media (min-width: 600px) {
    padding-block: 1.25rem;
    padding-inline: 1.5rem 4rem;
  }
}

.dialog-subtitle {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  opacity: 1;

  &__name {
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
    font-weight: 500;
  }

  &__date {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  }
}

.dialog-body {
  overflow: hidden;
  flex: 1 1 auto;
  padding: 0;
  min-block-size: 0;
}

.dialog-card {
  max-block-size: 90vh;
}

.dialog-scroll {
  block-size: 100%;
  max-block-size: 65vh;
}

// Fullscreen on mobile is driven by the `fullscreen` dialog prop (useDisplay),
// so on small screens the scroll area simply fills the remaining card height.
@media (max-width: 600px) {
  .dialog-scroll {
    max-block-size: none;
  }
}

.dialog-footer {
  flex-shrink: 0;
  justify-content: flex-end;
  gap: 0.75rem;
  padding-block: 0.75rem;
  padding-inline: 1rem;

  @media (min-width: 600px) {
    padding-block: 1rem;
    padding-inline: 1.5rem;
  }

  .v-btn + .v-btn {
    margin-inline-start: 0;
  }
}

.context-notices {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-block-end: 1.25rem;
}

.attendance-times {
  display: flex;
  align-items: center;
  font-size: 1.125rem;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  gap: 0.5rem;
}

.form-sections {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.form-section__header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-block-end: 0.75rem;
}

.form-section__avatar {
  flex-shrink: 0;
}

.form-section__title {
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.375rem;
}
</style>
