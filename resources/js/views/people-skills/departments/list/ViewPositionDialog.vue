<script setup>
import { WpDetailDialog, WpStatusChip } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'

defineProps({
  modelValue: { type: Boolean, default: false },
  position: { type: Object, default: null },
  fields: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  error: { type: [String, Object], default: null },
  active: { type: Boolean, default: false },
  canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'retry', 'edit'])

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
    :title="localizedValue(position?.title, $t('positions.view.title'))"
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
        <VIcon icon="tabler-briefcase" />
      </VAvatar>
    </template>
    <template #subtitle>
      <span v-if="position?.code">{{ position.code }}</span>
    </template>
    <template #actions>
      <VBtn
        v-if="position && canEdit"
        variant="tonal"
        size="small"
        prepend-icon="tabler-edit"
        @click="emit('edit')"
      >
        {{ $t('Edit') }}
      </VBtn>
    </template>

    <template v-if="position">
      <div class="d-flex align-center gap-2 mb-4">
        <WpStatusChip
          type="position"
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
        v-if="localizedValue(position.description, '')"
        class="org-section"
      >
        <div class="org-section__header">
          <VIcon
            icon="tabler-align-left"
            size="18"
            class="org-section__icon"
          />
          <span class="org-section__title">{{ $t('positions.form.description') }}</span>
        </div>
        <div class="org-prose text-pre-wrap">
          {{ localizedValue(position.description, '') }}
        </div>
      </section>

      <!-- Requirements -->
      <section
        v-if="localizedValue(position.requirements, '')"
        class="org-section"
      >
        <div class="org-section__header">
          <VIcon
            icon="tabler-checklist"
            size="18"
            class="org-section__icon"
          />
          <span class="org-section__title">{{ $t('positions.form.requirements') }}</span>
        </div>
        <div class="org-prose text-pre-wrap">
          {{ localizedValue(position.requirements, '') }}
        </div>
      </section>

      <!-- Employees -->
      <section
        v-if="position.employees?.length"
        class="org-section"
      >
        <div class="org-section__header">
          <VIcon
            icon="tabler-users"
            size="18"
            class="org-section__icon"
          />
          <span class="org-section__title">{{ $t('positions.view.employees') }}</span>
          <VChip
            size="small"
            variant="tonal"
            color="primary"
          >
            {{ position.employees.length }}
          </VChip>
        </div>
        <VList
          class="org-relation-list"
          density="compact"
          border
          rounded
        >
          <VListItem
            v-for="employee in position.employees"
            :key="employee.id"
            :title="personName(employee)"
          >
            <template #prepend>
              <VAvatar
                size="32"
                color="primary"
                variant="tonal"
              >
                <span class="text-caption">{{ personInitials(employee) }}</span>
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
</style>
