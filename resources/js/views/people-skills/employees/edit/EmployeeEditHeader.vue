<script setup>
import { WpStatusChip } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { resolveEmployeeStatusLabel } from '@/utils/hr-constants'
import { avatarText, formatDate } from '@core/utils/formatters'
import { computed } from 'vue'
import { useDisplay } from 'vuetify'

const props = defineProps({
  employee: { type: Object, required: true },
  saving: { type: Boolean, default: false },
  isDirty: { type: Boolean, default: false },
})

const emit = defineEmits(['save', 'delete', 'open-photo-dialog', 'open-badge-dialog'])

const { xs } = useDisplay()

const displayAvatar = computed(() => props.employee.avatar_url)

const displayName = computed(() =>
  props.employee.full_name
  || [
    localizedValue(props.employee.first_name),
    localizedValue(props.employee.middle_name || ''),
    localizedValue(props.employee.last_name),
  ].filter(Boolean).join(' ').trim(),
)

const displayInitials = computed(() => avatarText(displayName.value))
const statusLabel = computed(() => resolveEmployeeStatusLabel(props.employee.status))

const positionLabel = computed(() =>
  localizedValue(props.employee.position?.title) || props.employee.job_title || '',
)

const departmentLabel = computed(() => localizedValue(props.employee.department?.name) || '')

const metaLine = computed(() => [positionLabel.value, departmentLabel.value].filter(Boolean).join(' · '))

const lastUpdated = computed(() => {
  if (!props.employee.updated_at) return ''

  return formatDate(props.employee.updated_at, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
})
</script>

<template>
  <VCard
    class="employee-edit-header"
    elevation="0"
  >
    <div class="employee-edit-header__row">
      <!-- Identity -->
      <VBadge
        location="bottom end"
        :offset-x="xs ? 2 : 4"
        :offset-y="xs ? 2 : 4"
        class="employee-edit-header__avatar"
      >
        <template #badge>
          <VBtn
            icon
            size="x-small"
            color="primary"
            class="employee-edit-header__camera"
            :aria-label="$t('employees.edit.change_photo', 'Change Photo')"
            @click="emit('open-photo-dialog')"
          >
            <VIcon
              icon="tabler-camera"
              size="12"
            />
          </VBtn>
        </template>

        <VAvatar
          :size="xs ? 40 : 60"
          :color="displayAvatar ? undefined : 'primary'"
          variant="tonal"
        >
          <VImg
            v-if="displayAvatar"
            :src="displayAvatar"
            cover
          />
          <span
            v-else
            :class="xs ? 'text-subtitle-1' : 'text-h5'"
            class="font-weight-bold"
          >{{ displayInitials }}</span>
        </VAvatar>
      </VBadge>

      <div class="employee-edit-header__info">
        <div class="d-flex align-center gap-2 flex-wrap">
          <h1 class="employee-edit-header__name text-truncate">
            {{ displayName }}
          </h1>
          <VChip
            v-if="isDirty"
            size="x-small"
            color="warning"
            variant="tonal"
            prepend-icon="tabler-pencil"
            class="flex-shrink-0"
          >
            <span class="d-none d-sm-inline">{{ $t('employees.edit.unsaved_changes', 'Unsaved changes') }}</span>
          </VChip>
        </div>

        <div
          v-if="metaLine"
          class="text-body-2 text-medium-emphasis text-truncate"
        >
          {{ metaLine }}
        </div>

        <div class="employee-edit-header__meta">
          <WpStatusChip
            type="employee"
            :status="employee.status"
            :label="statusLabel"
            show-icon
            size="x-small"
          />
          <span class="text-caption text-disabled d-none d-sm-inline">
            {{ $t('employees.edit.employee_number_label', 'Employee No.') }} {{ employee.employee_number }}
          </span>
          <span
            v-if="lastUpdated"
            class="text-caption text-disabled d-none d-lg-inline"
          >
            {{ $t('employees.edit.last_updated', 'Last updated') }} {{ lastUpdated }}
          </span>
        </div>
      </div>

      <!-- Actions (desktop only — mobile uses the floating quick-actions sheet) -->
      <div class="employee-edit-header__actions d-none d-md-flex">
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-id-badge-2"
          @click="emit('open-badge-dialog')"
        >
          {{ $t('attendance.employee_badge', 'Badge') }}
        </VBtn>
        <VBtn
          variant="tonal"
          color="error"
          prepend-icon="tabler-trash"
          @click="emit('delete')"
        >
          {{ $t('common.delete', 'Delete') }}
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-device-floppy"
          :loading="saving"
          :disabled="!isDirty"
          @click="emit('save')"
        >
          {{ $t('common.save') }}
        </VBtn>
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.employee-edit-header {
  position: sticky;
  z-index: 5;
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 16px !important;
  background: rgba(var(--v-theme-surface), 0.92) !important;
  backdrop-filter: blur(16px) saturate(1.6);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 6%);
  inset-block-start: 72px;
}

.employee-edit-header__row {
  display: flex;
  align-items: center;
  padding: 14px 18px;
  gap: 16px;
}

.employee-edit-header__avatar {
  flex-shrink: 0;
}

.employee-edit-header__info {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  gap: 2px;
  min-inline-size: 0;
}

.employee-edit-header__name {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.3;
  min-inline-size: 0;
}

.employee-edit-header__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 12px;
}

.employee-edit-header__actions {
  flex-shrink: 0;
  align-items: center;
  gap: 8px;
}

@media (max-width: 959px) {
  .employee-edit-header {
    inset-block-start: 64px;
  }
}

@media (max-width: 599px) {
  .employee-edit-header {
    border-radius: 12px !important;
  }

  .employee-edit-header__row {
    padding: 8px 12px;
    gap: 10px;
  }

  .employee-edit-header__name {
    font-size: 0.9375rem;
  }

  .employee-edit-header__info {
    gap: 0;
  }
}

@media (min-width: 2200px) {
  .employee-edit-header__name {
    font-size: 1.5rem;
  }
}
</style>
