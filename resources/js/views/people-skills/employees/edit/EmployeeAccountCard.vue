<script setup>
/* eslint-disable camelcase */
import { WpFormDialog, WpInfoItem, WpSectionCard } from '@/components/shared'
import { avatarText } from '@core/utils/formatters'
import { computed, reactive, ref } from 'vue'

const props = defineProps({
  employee: { type: Object, required: true },
  form: { type: Object, required: true },
  availableUserOptions: { type: Array, default: () => [] },
  readonly: { type: Boolean, default: false },
})

const isEditOpen = ref(false)
const accountForm = reactive({ user_id: null, notes: '' })

const openEdit = () => {
  Object.assign(accountForm, { user_id: props.form.user_id, notes: props.form.notes || '' })
  isEditOpen.value = true
}

const save = () => {
  Object.assign(props.form, { user_id: accountForm.user_id, notes: accountForm.notes })
  isEditOpen.value = false
}

defineExpose({ openEdit })

const user = computed(() => props.employee?.user || null)
const badgeId = computed(() => props.employee?.employee_number ? `WP:EMP:${props.employee.employee_number}` : '')

const formatDateTime = d => {
  if (!d) return ''
  try {
    return new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(d))
  } catch { return d }
}
</script>

<template>
  <WpSectionCard
    :title="$t('hr.employees.sections.confidential', 'Confidential & Account')"
    :subtitle="$t('hr.employees.edit.confidential_subtitle', 'System access, badge and internal notes')"
    icon="tabler-lock"
    icon-color="error"
  >
    <template
      v-if="!readonly"
      #actions
    >
      <VBtn
        variant="tonal"
        color="primary"
        size="small"
        prepend-icon="tabler-edit"
        class="d-none d-md-inline-flex"
        @click="openEdit"
      >
        {{ $t('common.edit') }}
      </VBtn>
    </template>

    <VAlert
      type="warning"
      variant="tonal"
      density="compact"
      icon="tabler-eye-off"
      class="mb-4"
    >
      {{ $t('hr.employees.edit.confidential_notice', 'This section is visible to HR and authorised managers only.') }}
    </VAlert>

    <!-- System access -->
    <div class="account-group-label">
      <VIcon
        icon="tabler-key"
        size="14"
      />
      {{ $t('hr.employees.edit.group_access', 'System access') }}
    </div>
    <VRow dense>
      <VCol
        cols="12"
        md="6"
      >
        <WpInfoItem
          icon="tabler-user-circle"
          color="info"
          :label="$t('hr.employees.fields.userAccount')"
        >
          <div
            v-if="user"
            class="d-flex align-center gap-2"
          >
            <VAvatar
              size="28"
              color="info"
              variant="tonal"
            >
              <span class="text-caption">{{ avatarText(user.name) }}</span>
            </VAvatar>
            <div class="min-w-0">
              <div class="text-body-2 font-weight-medium text-truncate">
                {{ user.name }}
              </div>
              <div class="text-caption text-medium-emphasis text-truncate">
                {{ user.email }}
              </div>
            </div>
          </div>
          <span
            v-else
            class="text-disabled"
          >{{ $t('employees.edit.no_linked_user', 'No linked account') }}</span>
        </WpInfoItem>
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <WpInfoItem
          icon="tabler-shield-check"
          :color="user ? 'success' : 'secondary'"
          :label="$t('hr.employees.edit.login_access', 'Login access')"
        >
          <VChip
            size="small"
            label
            :color="user ? 'success' : 'secondary'"
            :prepend-icon="user ? 'tabler-link' : 'tabler-link-off'"
          >
            {{ user ? $t('hr.employees.edit.account_linked', 'Account linked') : $t('hr.employees.edit.account_not_linked', 'Not linked') }}
          </VChip>
        </WpInfoItem>
      </VCol>
      <VCol
        v-if="user?.role || user?.roles?.length"
        cols="12"
        md="6"
      >
        <WpInfoItem
          icon="tabler-shield"
          color="warning"
          :label="$t('hr.employees.edit.account_role', 'Role')"
          :value="user.role || user.roles.map(r => r.name || r).join(', ')"
        />
      </VCol>
      <VCol
        v-if="user?.last_login_at"
        cols="12"
        md="6"
      >
        <WpInfoItem
          icon="tabler-login"
          color="primary"
          :label="$t('hr.employees.edit.last_login', 'Last login')"
          :value="formatDateTime(user.last_login_at)"
        />
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <WpInfoItem
          icon="tabler-qrcode"
          color="secondary"
          :label="$t('hr.employees.edit.badge_id', 'Attendance badge ID')"
          :value="badgeId"
          mono
        />
      </VCol>
    </VRow>

    <!-- Record -->
    <div class="account-group-label mt-3">
      <VIcon
        icon="tabler-database"
        size="14"
      />
      {{ $t('hr.employees.edit.group_record', 'Record') }}
    </div>
    <VRow dense>
      <VCol
        cols="12"
        sm="6"
      >
        <WpInfoItem
          icon="tabler-calendar-plus"
          color="success"
          :label="$t('hr.employees.edit.created_at', 'Record created')"
          :value="formatDateTime(employee?.created_at)"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <WpInfoItem
          icon="tabler-calendar-time"
          color="info"
          :label="$t('hr.employees.edit.updated_at', 'Last modified')"
          :value="formatDateTime(employee?.updated_at)"
        />
      </VCol>
    </VRow>

    <!-- Notes -->
    <div class="account-group-label mt-3">
      <VIcon
        icon="tabler-notes"
        size="14"
      />
      {{ $t('hr.employees.fields.notes') }}
    </div>
    <div
      v-if="form.notes"
      class="account-notes"
    >
      {{ form.notes }}
    </div>
    <div
      v-else
      class="text-caption text-disabled px-2"
    >
      {{ $t('hr.employees.edit.no_notes', 'No internal notes yet.') }}
    </div>

    <WpFormDialog
      v-model="isEditOpen"
      :title="$t('hr.employees.sections.confidential', 'Confidential & Account')"
      :subtitle="$t('hr.employees.edit.confidential_hint', 'Linked login account and internal notes')"
      icon="tabler-lock"
      icon-color="error"
      @submit="save"
    >
      <VRow>
        <VCol cols="12">
          <AppSelect
            v-model="accountForm.user_id"
            :label="$t('hr.employees.fields.userAccount')"
            :items="availableUserOptions"
            clearable
            :hint="$t('employees.edit.linked_user_hint', 'Link to a system user for login access')"
            persistent-hint
          />
        </VCol>
        <VCol cols="12">
          <AppTextarea
            v-model="accountForm.notes"
            :label="$t('hr.employees.fields.notes')"
            rows="5"
            :counter="1000"
            :hint="$t('employees.edit.notes_hint', 'Internal notes (visible to HR and managers only)')"
            persistent-hint
          />
        </VCol>
      </VRow>
    </WpFormDialog>
  </WpSectionCard>
</template>

<style scoped>
.account-group-label {
  display: flex;
  align-items: center;
  color: rgba(var(--v-theme-on-surface), 0.5);
  font-size: 0.6875rem;
  font-weight: 700;
  gap: 6px;
  letter-spacing: 0.08em;
  margin-block-end: 4px;
  padding-inline: 8px;
  text-transform: uppercase;
}

.account-notes {
  padding: 12px;
  border: 1px dashed rgba(var(--v-border-color), 0.3);
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.03);
  font-size: 0.875rem;
  line-height: 1.6;
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}
</style>
