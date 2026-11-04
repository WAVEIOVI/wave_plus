<script setup>
/* eslint-disable camelcase */
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { useAttendanceStore } from '@/stores/useAttendanceStore'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const { showMessage } = useAppSnackbar()
const ability = useAbility()
const attendanceStore = useAttendanceStore()
const canManage = computed(() => ability.can('manage', 'attendance'))

// ── Form state ────────────────────────────────────────────────────────────
const form = ref({
  early_checkin_grace_minutes: 15,
  late_checkin_grace_minutes: 15,
  early_checkout_grace_minutes: 15,
  late_checkout_grace_minutes: 15,
  hourly_leave_tolerance_minutes: 15,
  min_hourly_leave_unit_minutes: 30,
})

const isSaving = ref(false)

// Last saved/loaded values. "Dirty" is a comparison against this snapshot, so loading
// the config never flags the form as edited.
const snapshot = ref(JSON.stringify(form.value))
const isDirty = computed(() => JSON.stringify(form.value) !== snapshot.value)

// ── Field layout (keys, labels and hints are unchanged) ───────────────────
const sections = [
  {
    key: 'checkin',
    icon: 'tabler-login',
    color: 'success',
    titleKey: 'attendance.config.checkin_tolerances',
    cols: 6,
    fields: [
      { model: 'late_checkin_grace_minutes', key: 'late_checkin_grace', min: 0, max: 120, step: 1 },
      { model: 'early_checkin_grace_minutes', key: 'early_checkin_grace', min: 0, max: 120, step: 1 },
    ],
  },
  {
    key: 'checkout',
    icon: 'tabler-logout',
    color: 'error',
    titleKey: 'attendance.config.checkout_tolerances',
    cols: 6,
    fields: [
      { model: 'early_checkout_grace_minutes', key: 'early_checkout_grace', min: 0, max: 120, step: 1 },
      { model: 'late_checkout_grace_minutes', key: 'late_checkout_grace', min: 0, max: 120, step: 1 },
    ],
  },
  {
    key: 'leave',
    icon: 'tabler-clock-pause',
    color: 'info',
    titleKey: 'attendance.config.leave_policy',
    cols: 12,
    fields: [
      { model: 'hourly_leave_tolerance_minutes', key: 'hourly_leave_tolerance', min: 0, max: 120, step: 1 },
      { model: 'min_hourly_leave_unit_minutes', key: 'min_hourly_leave_unit', min: 15, max: 240, step: 15 },
    ],
  },
]

const allFields = sections.flatMap(section => section.fields)

// ── Validation ────────────────────────────────────────────────────────────
const isInRange = (value, field) =>
  value !== '' && value !== null && Number.isFinite(Number(value))
  && Number(value) >= field.min && Number(value) <= field.max

const rangeMessage = field =>
  `${t('attendance.config.range_hint', 'Allowed range')}: ${field.min}–${field.max} ${t('attendance.config.unit_min', 'min')}`

const rulesFor = field => [value => isInRange(value, field) || rangeMessage(field)]

const isFormValid = computed(() =>
  allFields.every(field => isInRange(form.value[field.model], field)),
)

// ── Load config ───────────────────────────────────────────────────────────
const loadConfig = async () => {
  await attendanceStore.fetchConfig()
  if (attendanceStore.config) {
    form.value = {
      early_checkin_grace_minutes: attendanceStore.config.early_checkin_grace_minutes ?? 15,
      late_checkin_grace_minutes: attendanceStore.config.late_checkin_grace_minutes ?? 15,
      early_checkout_grace_minutes: attendanceStore.config.early_checkout_grace_minutes ?? 15,
      late_checkout_grace_minutes: attendanceStore.config.late_checkout_grace_minutes ?? 15,
      hourly_leave_tolerance_minutes: attendanceStore.config.hourly_leave_tolerance_minutes ?? 15,
      min_hourly_leave_unit_minutes: attendanceStore.config.min_hourly_leave_unit_minutes ?? 30,
    }
    snapshot.value = JSON.stringify(form.value)
  }
}

onMounted(() => loadConfig())

// ── Save config ───────────────────────────────────────────────────────────
const saveConfig = async () => {
  isSaving.value = true
  try {
    const payload = Object.fromEntries(
      Object.entries(form.value).map(([key, value]) => [key, Math.max(0, Number(value) || 0)]),
    )

    await attendanceStore.updateConfig(payload)
    showMessage(t('attendance.config.saved'), 'success')
    snapshot.value = JSON.stringify(form.value)
  } catch (err) {
    showMessage(err?.data?.message || t('attendance.config.save_error'), 'error')
  } finally {
    isSaving.value = false
  }
}

// ── Reset form ────────────────────────────────────────────────────────────
// Back to the last saved values, no network call needed.
const resetForm = () => {
  form.value = JSON.parse(snapshot.value)
}

defineExpose({
  saveConfig,
  resetForm,
  isDirty,
  isFormValid,
  isSaving,
})
</script>

<template>
  <div class="attendance-config-tab d-flex flex-column gap-4">
    <!-- Intro + action bar -->
    <div class="attendance-config-tab__toolbar d-flex flex-wrap align-center justify-space-between gap-4">
      <div class="d-flex align-center gap-2 text-medium-emphasis">
        <VIcon
          icon="tabler-info-circle"
          size="20"
        />
        <span class="text-body-2">{{ $t('attendance.config.info_message') }}</span>
      </div>

      <div class="attendance-config-tab__actions d-flex flex-wrap align-center gap-2 ms-auto">
        <VChip
          v-if="isDirty"
          color="warning"
          size="small"
          variant="tonal"
          prepend-icon="tabler-pencil"
        >
          {{ $t('attendance.config.unsaved', 'Unsaved changes') }}
        </VChip>
        <VBtn
          v-if="canManage && isDirty"
          variant="outlined"
          color="secondary"
          prepend-icon="tabler-rotate-clockwise"
          class="d-none d-sm-inline-flex"
          :disabled="isSaving"
          @click="resetForm"
        >
          {{ $t('Reset') }}
        </VBtn>
        <VBtn
          v-if="canManage"
          color="primary"
          prepend-icon="tabler-device-floppy"
          class="d-none d-sm-inline-flex"
          :loading="isSaving"
          :disabled="!isFormValid || !isDirty || attendanceStore.configLoading"
          @click="saveConfig"
        >
          {{ $t('Save') }}
        </VBtn>
      </div>
    </div>

    <VCard
      v-if="attendanceStore.configLoading && !attendanceStore.config"
      variant="outlined"
    >
      <VCardText class="pa-4">
        <VSkeletonLoader type="heading, paragraph, paragraph, paragraph" />
      </VCardText>
    </VCard>

    <VRow v-else>
      <VCol
        v-for="section in sections"
        :key="section.key"
        cols="12"
        :md="section.cols"
      >
        <VCard
          variant="outlined"
          class="h-100"
        >
          <VCardTitle class="d-flex align-center gap-3 pa-4">
            <VAvatar
              size="34"
              rounded
              :color="section.color"
              variant="tonal"
            >
              <VIcon
                :icon="section.icon"
                size="20"
              />
            </VAvatar>
            <span class="text-body-1 font-weight-medium">{{ $t(section.titleKey) }}</span>
          </VCardTitle>
          <VDivider />
          <VCardText class="pa-4">
            <VRow>
              <VCol
                v-for="field in section.fields"
                :key="field.model"
                cols="12"
                :md="section.cols === 12 ? 6 : 12"
              >
                <VTextField
                  v-model.number="form[field.model]"
                  type="number"
                  inputmode="numeric"
                  :min="field.min"
                  :max="field.max"
                  :step="field.step"
                  :label="$t(`attendance.config.${field.key}`)"
                  :suffix="$t('attendance.config.unit_min', 'min')"
                  density="comfortable"
                  :hint="$t(`attendance.config.${field.key}_hint`)"
                  :rules="rulesFor(field)"
                  :readonly="!canManage"
                  persistent-hint
                >
                  <template #prepend-inner>
                    <VBtn
                      v-if="canManage"
                      icon="tabler-minus"
                      variant="text"
                      density="compact"
                      size="x-small"
                      color="secondary"
                      :disabled="Number(form[field.model]) <= field.min"
                      :aria-label="`Decrease ${field.key}`"
                      @click="form[field.model] = Math.max(field.min, (Number(form[field.model]) || 0) - field.step)"
                    />
                  </template>
                  <template #append-inner>
                    <VBtn
                      v-if="canManage"
                      icon="tabler-plus"
                      variant="text"
                      density="compact"
                      size="x-small"
                      color="secondary"
                      :disabled="Number(form[field.model]) >= field.max"
                      :aria-label="`Increase ${field.key}`"
                      @click="form[field.model] = Math.min(field.max, (Number(form[field.model]) || 0) + field.step)"
                    />
                  </template>
                </VTextField>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
@media (max-width: 599px) {
  .attendance-config-tab__toolbar {
    align-items: stretch !important;
  }

  .attendance-config-tab__actions {
    inline-size: 100%;
    margin-inline-start: 0 !important;
  }

  .attendance-config-tab__actions :deep(.v-btn) {
    flex: 1 1 auto;
  }
}
</style>
