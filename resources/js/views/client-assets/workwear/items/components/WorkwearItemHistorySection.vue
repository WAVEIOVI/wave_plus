<script setup>
/* eslint-disable camelcase */
import { WpEmptyState, WpSectionCard } from '@/components/shared'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  assignments: { type: Array, default: () => [] },
  itemData: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['assign'])

const { t } = useI18n()
</script>

<template>
  <section
    id="section-history"
    class="journal-section mb-6"
  >
    <WpSectionCard
      icon="tabler-history"
      :title="$t('Assignment History')"
      :subtitle="$t('Historical audit log of staff allocations, returns, and inspection condition records.')"
    >
      <template #actions>
        <VBtn
          v-if="itemData.status !== 'assigned'"
          size="small"
          variant="tonal"
          color="info"
          prepend-icon="tabler-user-plus"
          @click="emit('assign')"
        >
          {{ $t('Assign to Staff') }}
        </VBtn>
      </template>

      <VTable
        v-if="assignments.length"
        density="compact"
        class="text-no-wrap bg-transparent border rounded"
      >
        <thead>
          <tr>
            <th>{{ $t('Employee') }}</th>
            <th>{{ $t('Assigned') }}</th>
            <th>{{ $t('Returned') }}</th>
            <th>{{ $t('Status') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="a in assignments"
            :key="a.id"
          >
            <td class="font-weight-medium">
              {{ a.employee?.full_name || '—' }}
            </td>
            <td>{{ a.assigned_at ? new Date(a.assigned_at).toLocaleDateString() : '—' }}</td>
            <td>{{ a.returned_at ? new Date(a.returned_at).toLocaleDateString() : '—' }}</td>
            <td>
              <VChip
                size="small"
                label
                variant="tonal"
                :color="a.returned_at ? 'secondary' : 'success'"
              >
                {{ a.returned_at ? $t('Returned') : $t('Active') }}
              </VChip>
            </td>
          </tr>
        </tbody>
      </VTable>

      <WpEmptyState
        v-else
        icon="tabler-history"
        :title="$t('No assignment history')"
        :subtitle="$t('This garment has not been issued to any staff member yet.')"
      />
    </WpSectionCard>
  </section>
</template>
