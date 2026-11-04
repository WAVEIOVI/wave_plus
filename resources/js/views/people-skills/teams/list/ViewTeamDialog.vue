<script setup>
/* eslint-disable camelcase */
import { WpDeleteDialog, WpDetailDialog, WpFormDialog, WpStatusChip } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { reactive, ref } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  team: { type: Object, default: null },
  fields: { type: Array, default: () => [] },
  employeeOptions: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  error: { type: [String, Object], default: null },
  active: { type: Boolean, default: false },
  canEdit: { type: Boolean, default: false },
  submittingMember: { type: Boolean, default: false },
})

const emit = defineEmits([
  'update:modelValue',
  'retry',
  'edit',
  'addMember',
  'removeMember',
])

const isAddMemberOpen = ref(false)

const memberForm = reactive({
  employee_id: null,
  role_in_team: 'Member',
  is_primary: true,
})

const isRemoveMemberOpen = ref(false)
const removingMember = ref(null)
const removingLoading = ref(false)

const openAddMember = () => {
  memberForm.employee_id = null
  memberForm.role_in_team = 'Member'
  memberForm.is_primary = true
  isAddMemberOpen.value = true
}

const submitAddMember = () => {
  if (!memberForm.employee_id) return
  emit('addMember', {
    team_id: props.team?.id,
    employee_id: memberForm.employee_id,
    role_in_team: memberForm.role_in_team,
    is_primary: memberForm.is_primary,
  })
  isAddMemberOpen.value = false
}

const askRemoveMember = member => {
  removingMember.value = member
  isRemoveMemberOpen.value = true
}

const confirmRemoveMember = () => {
  if (!removingMember.value || !props.team?.id) return
  removingLoading.value = true
  try {
    emit('removeMember', {
      team_id: props.team.id,
      member_id: removingMember.value.id,
    })
    isRemoveMemberOpen.value = false
  }
  finally {
    removingLoading.value = false
  }
}

const memberInitials = member => {
  if (member.full_name)
    return member.full_name.split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]).join('').toUpperCase()

  return `${localizedValue(member.first_name, '')[0] || ''}${localizedValue(member.last_name, '')[0] || ''}`.toUpperCase() || '?'
}

const formatDate = value => {
  if (!value)
    return ''

  const date = new Date(value)

  return Number.isNaN(date.getTime()) ? '' : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(date)
}
</script>

<template>
  <WpDetailDialog
    :model-value="modelValue"
    :title="localizedValue(team?.name, $t('teams.view.title'))"
    size="large"
    :loading="loading"
    :error="error"
    @update:model-value="emit('update:modelValue', $event)"
    @retry="emit('retry')"
  >
    <template #loading>
      <div class="d-flex flex-column gap-4">
        <VSkeletonLoader type="chip" />
        <VSkeletonLoader type="paragraph, paragraph" />
        <VSkeletonLoader type="article" />
        <VSkeletonLoader type="list-item-three-line, list-item-three-line" />
      </div>
    </template>
    <template #avatar>
      <VAvatar
        color="primary"
        variant="tonal"
        rounded
        size="42"
      >
        <VIcon icon="tabler-users-group" />
      </VAvatar>
    </template>
    <template #subtitle>
      <span v-if="team?.code">{{ team.code }}</span>
    </template>
    <template #actions>
      <VBtn
        v-if="team && canEdit"
        variant="tonal"
        size="small"
        prepend-icon="tabler-edit"
        @click="emit('edit')"
      >
        {{ $t('Edit') }}
      </VBtn>
    </template>

    <template v-if="team">
      <div class="d-flex align-center gap-2 mb-4">
        <WpStatusChip
          type="team"
          :status="active ? 'active' : 'inactive'"
          :label="active ? $t('Active') : $t('Inactive')"
          dot
          :pulse="active"
        />
      </div>

      <div
        v-if="fields.length"
        class="org-info-panel"
      >
        <VRow>
          <VCol
            v-for="field in fields"
            :key="field.label"
            cols="12"
            sm="6"
          >
            <div class="org-detail-label">
              {{ field.label }}
            </div>
            <div class="text-body-1 font-weight-medium text-break">
              {{ field.value }}
            </div>
          </VCol>
        </VRow>
      </div>

      <section
        v-if="localizedValue(team.description, '')"
        class="org-section"
      >
        <div class="org-section__header">
          <VIcon
            icon="tabler-align-left"
            size="18"
            class="org-section__icon"
          />
          <span class="org-section__title">{{ $t('teams.form.description') }}</span>
        </div>
        <div class="org-prose text-pre-wrap">
          {{ localizedValue(team.description, '') }}
        </div>
      </section>

      <section class="org-section">
        <div class="org-section__header">
          <VIcon
            icon="tabler-users"
            size="18"
            class="org-section__icon"
          />
          <span class="org-section__title">{{ $t('teams.view.members') }}</span>
          <VChip
            size="small"
            variant="tonal"
            color="primary"
          >
            {{ team.members?.length ?? 0 }}
          </VChip>
          <VBtn
            v-if="canEdit"
            variant="text"
            size="small"
            class="ms-auto"
            prepend-icon="tabler-user-plus"
            @click="openAddMember"
          >
            {{ $t('teams.add') }}
          </VBtn>
        </div>

        <PerfectScrollbar
          v-if="team.members?.length"
          class="org-relation-list-scroll"
          :options="{ wheelPropagation: false }"
        >
          <VList
            class="org-relation-list"
            density="compact"
            border
            rounded
          >
            <VListItem
              v-for="member in team.members"
              :key="member.id"
              :title="member.full_name || `${localizedValue(member.first_name, '')} ${localizedValue(member.last_name, '')}`.trim()"
              :subtitle="[
                member.employee_number ? `#${member.employee_number}` : '',
                localizedValue(member.position, ''),
                member.role_in_team || '',
                member.joined_at ? `${$t('teams.view.joined_at')}: ${formatDate(member.joined_at)}` : '',
              ].filter(Boolean).join(' · ')"
            >
              <template #prepend>
                <VAvatar
                  size="32"
                  color="primary"
                  variant="tonal"
                >
                  <span class="text-caption">{{ memberInitials(member) }}</span>
                </VAvatar>
              </template>
              <template #append>
                <div class="d-flex align-center gap-2">
                  <VChip
                    v-if="member.is_primary"
                    size="x-small"
                    color="info"
                    variant="tonal"
                  >
                    {{ $t('teams.view.primary_member') }}
                  </VChip>
                  <VChip
                    v-if="member.employee_id === team.team_leader_id"
                    size="x-small"
                    color="primary"
                    variant="tonal"
                  >
                    {{ $t('teams.view.team_lead') }}
                  </VChip>
                  <VBtn
                    v-if="canEdit"
                    icon
                    variant="text"
                    color="error"
                    size="x-small"
                    :title="$t('Delete')"
                    @click="askRemoveMember(member)"
                  >
                    <VIcon
                      icon="tabler-x"
                      size="16"
                    />
                  </VBtn>
                </div>
              </template>
            </VListItem>
          </VList>
        </PerfectScrollbar>
        <p
          v-else
          class="text-caption text-disabled mb-0"
        >
          {{ $t('teams.view.no_members') }}
        </p>
      </section>
    </template>
  </WpDetailDialog>

  <!-- Add Member Dialog -->
  <WpFormDialog
    v-model="isAddMemberOpen"
    :title="$t('teams.add')"
    icon="tabler-user-plus"
    size="small"
    :loading="submittingMember"
    @submit="submitAddMember"
  >
    <VRow>
      <VCol cols="12">
        <AppSelect
          v-model="memberForm.employee_id"
          :label="$t('teams.col.leader')"
          :items="employeeOptions"
          :rules="[v => !!v || $t('teams.validation.required')]"
          required
          variant="outlined"
        />
      </VCol>
      <VCol cols="12">
        <AppTextField
          v-model="memberForm.role_in_team"
          :label="$t('teams.col.type')"
          variant="outlined"
        />
      </VCol>
      <VCol cols="12">
        <VSwitch
          v-model="memberForm.is_primary"
          :label="$t('teams.view.primary_member')"
          color="primary"
        />
      </VCol>
    </VRow>
  </WpFormDialog>

  <!-- Remove Member Dialog -->
  <WpDeleteDialog
    v-model="isRemoveMemberOpen"
    :title="$t('Delete')"
    :description="removingMember ? `${removingMember.full_name || 'Member'} - ${$t('teams.delete_confirm')}` : ''"
    :loading="removingLoading"
    :confirm-action="confirmRemoveMember"
  />
</template>

<style scoped>
.org-detail-label {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
  margin-block-end: 2px;
}

.org-info-panel {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.5rem;
  background: rgba(var(--v-theme-on-surface), 0.02);
  padding-block: 0.5rem;
  padding-inline: 1rem;
}

.org-section {
  margin-block-start: 1.5rem;
}

.org-section__header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-block-end: 0.75rem;
  min-block-size: 2rem;
}

.org-section__icon {
  color: rgb(var(--v-theme-primary));
}

.org-section__title {
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.375rem;
}

.org-prose {
  font-size: 0.9375rem;
  line-height: 1.6;
  max-inline-size: 70ch;
}

.text-pre-wrap {
  white-space: pre-wrap;
}

.org-relation-list-scroll {
  max-block-size: 18rem;
}
</style>
