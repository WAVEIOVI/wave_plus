<script setup>
import { WpDetailDialog, WpStatusChip } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'

defineProps({
  modelValue: { type: Boolean, default: false },
  department: { type: Object, default: null },
  fields: { type: Array, default: () => [] },
  children: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  error: { type: [String, Object], default: null },
  active: { type: Boolean, default: false },
  canEdit: { type: Boolean, default: false },
  canCreate: { type: Boolean, default: false },
})

const emit = defineEmits([
  'update:modelValue',
  'retry',
  'edit',
  'showPositions',
  'addPosition',
  'openPosition',
  'openDepartment',
])

const displayValue = value => (value === null || value === undefined || value === '' ? '—' : value)

const personName = person => {
  if (!person)
    return '—'
  if (person.full_name)
    return person.full_name

  return `${localizedValue(person.first_name, '')} ${localizedValue(person.last_name, '')}`.trim() || '—'
}

const personInitials = person => {
  if (person?.initials)
    return String(person.initials).slice(0, 2).toUpperCase()

  const initials = personName(person)
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(part => part.charAt(0))
    .join('')

  return initials.toUpperCase() || '?'
}
</script>

<template>
  <WpDetailDialog
    :model-value="modelValue"
    :title="localizedValue(department?.name, $t('departments.view.title'))"
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
        <VIcon icon="tabler-building-community" />
      </VAvatar>
    </template>
    <template #subtitle>
      <span v-if="department?.code">{{ department.code }}</span>
    </template>
    <template #actions>
      <VBtn
        v-if="department && canEdit"
        variant="tonal"
        size="small"
        prepend-icon="tabler-edit"
        @click="emit('edit')"
      >
        {{ $t('Edit') }}
      </VBtn>
      <VBtn
        v-if="department"
        variant="tonal"
        size="small"
        prepend-icon="tabler-briefcase"
        @click="emit('showPositions', department)"
      >
        {{ $t('departments.list.tab_positions') }}
      </VBtn>
    </template>

    <template v-if="department">
      <div class="d-flex align-center gap-2 mb-4">
        <WpStatusChip
          type="department"
          :status="active ? 'active' : 'inactive'"
          :label="active ? $t('Active') : $t('Inactive')"
          dot
          :pulse="active"
        />
      </div>

      <!-- Key facts -->
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
              {{ displayValue(field.value) }}
            </div>
          </VCol>
        </VRow>
      </div>

      <!-- Description -->
      <section
        v-if="localizedValue(department.description, '')"
        class="org-section"
      >
        <div class="org-section__header">
          <VIcon
            icon="tabler-align-left"
            size="18"
            class="org-section__icon"
          />
          <span class="org-section__title">{{ $t('departments.form.description') }}</span>
        </div>
        <div class="org-prose text-pre-wrap">
          {{ localizedValue(department.description, '') }}
        </div>
      </section>

      <!-- Positions (section stays visible when empty so a first position can be added) -->
      <section
        v-if="department.positions?.length || canCreate"
        class="org-section"
      >
        <div class="org-section__header">
          <VIcon
            icon="tabler-briefcase"
            size="18"
            class="org-section__icon"
          />
          <span class="org-section__title">{{ $t('departments.view.positions') }}</span>
          <VChip
            size="small"
            variant="tonal"
            color="primary"
          >
            {{ department.positions?.length || 0 }}
          </VChip>
          <VBtn
            v-if="canCreate"
            variant="text"
            size="small"
            class="ms-auto"
            prepend-icon="tabler-plus"
            @click="emit('addPosition', department.id)"
          >
            {{ $t('positions.add') }}
          </VBtn>
        </div>
        <VList
          v-if="department.positions?.length"
          class="org-relation-list org-relation-list--clickable"
          density="compact"
          border
          rounded
        >
          <VListItem
            v-for="position in department.positions"
            :key="position.id"
            prepend-icon="tabler-briefcase"
            :title="localizedValue(position.title)"
            :subtitle="position.code"
            @click="emit('openPosition', position.id)"
          >
            <template #append>
              <VIcon
                icon="tabler-chevron-right"
                size="18"
                class="flip-in-rtl text-disabled"
              />
            </template>
          </VListItem>
        </VList>
      </section>

      <!-- Child departments -->
      <section
        v-if="children.length"
        class="org-section"
      >
        <div class="org-section__header">
          <VIcon
            icon="tabler-sitemap"
            size="18"
            class="org-section__icon"
          />
          <span class="org-section__title">{{ $t('departments.view.child_departments') }}</span>
          <VChip
            size="small"
            variant="tonal"
            color="primary"
          >
            {{ children.length }}
          </VChip>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <VChip
            v-for="child in children"
            :key="child.id"
            variant="tonal"
            color="primary"
            prepend-icon="tabler-building-community"
            class="cursor-pointer"
            @click="emit('openDepartment', child.id)"
          >
            {{ localizedValue(child.name) }}
          </VChip>
        </div>
      </section>

      <!-- Employees -->
      <section
        v-if="department.employees?.length"
        class="org-section"
      >
        <div class="org-section__header">
          <VIcon
            icon="tabler-users"
            size="18"
            class="org-section__icon"
          />
          <span class="org-section__title">{{ $t('departments.view.employees') }}</span>
          <VChip
            size="small"
            variant="tonal"
            color="primary"
          >
            {{ department.employees.length }}
          </VChip>
        </div>
        <VList
          class="org-relation-list"
          density="compact"
          border
          rounded
        >
          <VListItem
            v-for="employee in department.employees"
            :key="employee.id"
            :title="personName(employee)"
            :subtitle="employee.employee_number"
          >
            <template #prepend>
              <VAvatar
                size="32"
                color="info"
                variant="tonal"
              >
                <span class="text-caption">{{ personInitials(employee) }}</span>
              </VAvatar>
            </template>
          </VListItem>
        </VList>
      </section>

      <!-- Work teams -->
      <section
        v-if="department.work_teams?.length"
        class="org-section"
      >
        <div class="org-section__header">
          <VIcon
            icon="tabler-users-group"
            size="18"
            class="org-section__icon"
          />
          <span class="org-section__title">{{ $t('departments.view.teams') }}</span>
          <VChip
            size="small"
            variant="tonal"
            color="primary"
          >
            {{ department.work_teams.length }}
          </VChip>
        </div>
        <VList
          class="org-relation-list"
          density="compact"
          border
          rounded
        >
          <VListItem
            v-for="team in department.work_teams"
            :key="team.id"
            :title="localizedValue(team.name)"
            :subtitle="[team.code, team.team_leader_name].filter(Boolean).join(' · ')"
          >
            <template #prepend>
              <VAvatar
                size="32"
                color="secondary"
                variant="tonal"
              >
                <VIcon
                  icon="tabler-users-group"
                  size="18"
                />
              </VAvatar>
            </template>
          </VListItem>
        </VList>
      </section>
    </template>
  </WpDetailDialog>
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

/* Only rows that actually open something look clickable. */
.org-relation-list--clickable :deep(.v-list-item) {
  cursor: pointer;
}
</style>
