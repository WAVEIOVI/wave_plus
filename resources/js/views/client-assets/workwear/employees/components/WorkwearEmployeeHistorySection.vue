<script setup>
/* eslint-disable camelcase */
import { AppHybridTabs, WpEmptyState, WpSectionCard } from '@/components/shared'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  assignmentTab: { type: String, default: 'current' },
  currentAssignments: { type: Array, default: () => [] },
  returnedAssignments: { type: Array, default: () => [] },
  returnActionColor: { type: Object, default: () => ({}) },
})

const emit = defineEmits([
  'update:assignmentTab',
  'issueGarment',
  'returnAssignment',
])

const { t, locale } = useI18n()

const currentTab = computed({
  get: () => props.assignmentTab,
  set: val => emit('update:assignmentTab', val),
})

const historyTabs = computed(() => [
  {
    value: 'current',
    title: t('Currently Issued Items'),
    icon: 'tabler-hanger',
    badge: props.currentAssignments.length || undefined,
  },
  {
    value: 'past',
    title: t('Returned / Past History'),
    icon: 'tabler-history',
    badge: props.returnedAssignments.length || undefined,
  },
])

const formatDate = d => {
  if (!d) return '—'
  try {
    return new Date(d).toLocaleDateString(locale.value)
  } catch {
    return String(d)
  }
}

const resolveItemModelLabel = assignment => {
  const item = assignment?.item
  const model = item?.model || item?.workwear_model
  if (model?.name) {
    return model.model_ref ? `${model.model_ref} — ${model.name}` : model.name
  }

  return item?.model_name || item?.garment_type || '—'
}
</script>

<template>
  <section
    id="section-history"
    class="journal-section mb-6"
  >
    <WpSectionCard
      icon="tabler-hanger"
      :title="$t('Issued Workwear Items & Handover History')"
      :subtitle="$t('Employees are attached directly to serialized Workwear Items (each linked to its parent Workwear Model).')"
    >
      <template #actions>
        <VBtn
          size="small"
          color="primary"
          prepend-icon="tabler-package-export"
          @click="emit('issueGarment', null)"
        >
          {{ $t('Assign Workwear Item') }}
        </VBtn>
      </template>

      <div class="mb-4">
        <AppHybridTabs
          v-model="currentTab"
          :tabs="historyTabs"
          density="compact"
        />
      </div>

      <div v-if="currentTab === 'current'">
        <div
          v-if="currentAssignments.length"
          class="d-flex flex-column gap-3"
        >
          <div
            v-for="a in currentAssignments"
            :key="a.id"
            class="assignment-row"
          >
            <div class="assignment-main">
              <div class="d-flex align-center flex-wrap gap-2 mb-1">
                <RouterLink
                  class="font-weight-bold font-mono text-primary text-decoration-none"
                  :to="{ name: 'client-assets-workwear-edit-id', params: { id: a.item_id }, query: { type: 'item' } }"
                >
                  {{ a.item?.item_code || '—' }}
                </RouterLink>
                <VChip
                  size="x-small"
                  color="primary"
                  variant="tonal"
                >
                  <VIcon
                    start
                    icon="tabler-shirt"
                    size="12"
                  />
                  {{ resolveItemModelLabel(a) }}
                </VChip>
                <VChip
                  v-if="a.item?.size"
                  size="x-small"
                  variant="outlined"
                  class="font-mono font-weight-bold"
                >
                  {{ a.item.size }}
                </VChip>
                <VChip
                  v-if="a.item?.color"
                  size="x-small"
                  variant="tonal"
                  color="secondary"
                  class="text-capitalize"
                >
                  {{ a.item.color }}
                </VChip>
              </div>
              <div class="text-caption text-disabled">
                {{ $t('Assigned') }} {{ formatDate(a.assigned_at) }}
                <template v-if="a.assigned_by_user">
                  · {{ a.assigned_by_user.name }}
                </template>
              </div>
            </div>
            <VBtn
              size="small"
              color="warning"
              variant="tonal"
              prepend-icon="tabler-arrow-back-up"
              @click="emit('returnAssignment', a)"
            >
              {{ $t('Return / Inspect') }}
            </VBtn>
          </div>
        </div>

        <WpEmptyState
          v-else
          icon="tabler-hanger"
          :title="$t('No active workwear items assigned')"
          :subtitle="$t('Click Assign Workwear Item above to attach an available garment item from a workwear model to this employee.')"
        />
      </div>

      <div v-else>
        <div
          v-if="returnedAssignments.length"
          class="d-flex flex-column gap-3"
        >
          <div
            v-for="a in returnedAssignments"
            :key="a.id"
            class="assignment-row assignment-row--past"
          >
            <div class="assignment-main">
              <div class="d-flex align-center flex-wrap gap-2 mb-1">
                <RouterLink
                  class="font-weight-bold font-mono text-primary text-decoration-none"
                  :to="{ name: 'client-assets-workwear-edit-id', params: { id: a.item_id }, query: { type: 'item' } }"
                >
                  {{ a.item?.item_code || '—' }}
                </RouterLink>
                <VChip
                  size="x-small"
                  color="secondary"
                  variant="tonal"
                >
                  {{ resolveItemModelLabel(a) }}
                </VChip>
                <VChip
                  v-if="a.item?.size"
                  size="x-small"
                  variant="outlined"
                  class="font-mono"
                >
                  {{ a.item.size }}
                </VChip>
              </div>
              <div class="text-caption text-disabled">
                {{ formatDate(a.assigned_at) }} → {{ formatDate(a.returned_at) }}
                <template v-if="a.returned_by_user">
                  · {{ a.returned_by_user.name }}
                </template>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <VChip
                v-if="a.return_action"
                size="small"
                label
                :color="returnActionColor[a.return_action] || 'secondary'"
                class="text-capitalize"
              >
                {{ a.return_action }}
              </VChip>
              <VChip
                v-if="a.condition_at_return"
                size="small"
                label
                variant="tonal"
                class="text-capitalize"
              >
                {{ a.condition_at_return }}
              </VChip>
            </div>
          </div>
        </div>

        <WpEmptyState
          v-else
          icon="tabler-history"
          :title="$t('No past assignments')"
          :subtitle="$t('No historical return records found for this staff member.')"
        />
      </div>
    </WpSectionCard>
  </section>
</template>

<style lang="scss" scoped>
.assignment-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 10px;
  background: rgba(var(--v-theme-surface), 0.5);
  gap: 0.75rem;
  padding: 0.75rem 1rem;

  &--past {
    opacity: 0.85;
  }
}
</style>
