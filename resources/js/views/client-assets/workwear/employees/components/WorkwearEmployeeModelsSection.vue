<script setup>
/* eslint-disable vue/no-mutating-props */
import { WpEmptyState, WpSectionCard } from '@/components/shared'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  employeeData: { type: Object, required: true },
  clientModels: { type: Array, default: () => [] },
  issuedCountForModel: { type: Function, required: true },
})

const emit = defineEmits([
  'addModelEntitlement',
  'removeModelEntitlement',
  'openIssueGarment',
])

const { t } = useI18n()
</script>

<template>
  <section
    id="section-models"
    class="journal-section mb-6"
  >
    <WpSectionCard
      icon="tabler-shirt"
      :title="$t('Assigned Workwear Models (Entitlements)')"
      :subtitle="$t('Link specific client garment models this employee is entitled to wear, separate from physical garment issuance.')"
    >
      <template #actions>
        <VBtn
          size="small"
          variant="tonal"
          color="success"
          prepend-icon="tabler-hanger"
          @click="emit('openIssueGarment', null)"
        >
          {{ $t('Issue Physical Garment') }}
        </VBtn>
        <VBtn
          size="small"
          variant="tonal"
          color="primary"
          prepend-icon="tabler-plus"
          @click="emit('addModelEntitlement')"
        >
          {{ $t('Assign Model') }}
        </VBtn>
      </template>

      <div
        v-if="employeeData.model_assignments?.length"
        class="d-flex flex-column gap-3"
      >
        <div
          v-for="(ma, idx) in employeeData.model_assignments"
          :key="ma.id || idx"
          class="requirement-row"
        >
          <VRow
            dense
            align="center"
          >
            <VCol
              cols="12"
              md="5"
            >
              <AppAutocomplete
                v-model="ma.model_id"
                :items="clientModels.map(m => ({ title: `${m.model_ref} — ${m.name}`, value: m.id }))"
                :label="$t('Workwear Model')"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol
              cols="6"
              md="2"
            >
              <AppTextField
                v-model.number="ma.quantity"
                type="number"
                min="1"
                max="99"
                :label="$t('Quota Qty')"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol
              cols="6"
              md="2"
            >
              <AppTextField
                v-model="ma.size"
                :label="$t('Target Size')"
                :placeholder="$t('e.g. L, XL')"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol
              cols="12"
              md="3"
              class="d-flex align-center justify-end gap-2 pt-3"
            >
              <VChip
                size="small"
                label
                :color="issuedCountForModel(ma.model_id) >= (ma.quantity || 1) ? 'success' : (issuedCountForModel(ma.model_id) > 0 ? 'warning' : 'secondary')"
              >
                {{ issuedCountForModel(ma.model_id) }}/{{ ma.quantity || 1 }} {{ $t('issued') }}
              </VChip>
              <VBtn
                size="x-small"
                variant="tonal"
                color="primary"
                icon="tabler-package-export"
                :title="$t('Issue Physical Item for this Model')"
                @click="emit('openIssueGarment', ma.model_id)"
              />
              <VBtn
                size="x-small"
                variant="text"
                color="error"
                icon="tabler-trash"
                @click="emit('removeModelEntitlement', idx)"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="ma.notes"
                :label="$t('Entitlement Notes / Role Policy')"
                :placeholder="$t('e.g. Annual winter allocation, mandatory FR specification')"
                variant="outlined"
                density="compact"
              />
            </VCol>
          </VRow>
        </div>
      </div>

      <WpEmptyState
        v-else
        icon="tabler-shirt-off"
        :title="$t('No garment models assigned to this employee yet.')"
        :subtitle="$t('Assign model entitlements to track quota fulfilment for this employee.')"
      />
    </WpSectionCard>
  </section>
</template>

<style lang="scss" scoped>
.requirement-row {
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 10px;
  background: rgba(var(--v-theme-surface), 0.5);
  padding: 1rem;
}
</style>
