<script setup>
/* eslint-disable camelcase */
import { WpEmptyState, WpSectionCard } from '@/components/shared'
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

const formatDate = d => {
  if (!d) return '—'
  try {
    return new Date(d).toLocaleDateString(locale.value)
  } catch {
    return String(d)
  }
}
</script>

<template>
  <section
    id="section-history"
    class="journal-section mb-6"
  >
    <WpSectionCard
      icon="tabler-hanger"
      :title="$t('Issued Garments & Handover History')"
      :subtitle="$t('Actual physical garments handed over to this employee from inventory.')"
    >
      <template #actions>
        <VBtn
          size="small"
          color="primary"
          prepend-icon="tabler-package-export"
          @click="emit('issueGarment', null)"
        >
          {{ $t('Issue Garment') }}
        </VBtn>
      </template>

      <VTabs
        v-model="currentTab"
        class="mb-4"
      >
        <VTab value="current">
          {{ $t('Currently Issued') }} ({{ currentAssignments.length }})
        </VTab>
        <VTab value="past">
          {{ $t('Returned / Past History') }} ({{ returnedAssignments.length }})
        </VTab>
      </VTabs>

      <VWindow
        v-model="currentTab"
        :touch="false"
      >
        <VWindowItem value="current">
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
                <RouterLink
                  class="font-weight-bold text-primary text-decoration-none"
                  :to="{ name: 'client-assets-workwear-edit-id', params: { id: a.item_id }, query: { type: 'item' } }"
                >
                  {{ a.item?.item_code || '—' }}
                </RouterLink>
                <div class="text-body-2 text-medium-emphasis text-capitalize">
                  {{ [a.item?.garment_type, a.item?.model_name, a.item?.size].filter(Boolean).join(' · ') || '—' }}
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
                {{ $t('Return') }}
              </VBtn>
            </div>
          </div>

          <WpEmptyState
            v-else
            icon="tabler-hanger"
            :title="$t('No active assignments')"
            :subtitle="$t('This employee has no active garments issued from stock.')"
          />
        </VWindowItem>

        <VWindowItem value="past">
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
                <RouterLink
                  class="font-weight-bold text-primary text-decoration-none"
                  :to="{ name: 'client-assets-workwear-edit-id', params: { id: a.item_id }, query: { type: 'item' } }"
                >
                  {{ a.item?.item_code || '—' }}
                </RouterLink>
                <div class="text-body-2 text-medium-emphasis text-capitalize">
                  {{ [a.item?.garment_type, a.item?.model_name, a.item?.size].filter(Boolean).join(' · ') || '—' }}
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
        </VWindowItem>
      </VWindow>
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
