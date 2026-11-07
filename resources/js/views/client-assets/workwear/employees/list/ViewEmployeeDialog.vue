<script setup>
/* eslint-disable camelcase */
import {
  WpDetailDialog,
  WpJournalNav,
  WpKpiCard,
  WpKpiGroup,
} from '@/components/shared'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  employeeId: { type: [String, Number], default: null },
  employee: { type: Object, default: null },
})

const emit = defineEmits(['update:isDialogVisible'])

const { t, locale } = useI18n()
const store = useClientAssetStore()

const loading = ref(false)
const employeeDetails = ref(null)
const activeSection = ref('bio')
const historySubTab = ref('active')

const statusMap = { active: 'success', inactive: 'secondary', terminated: 'error' }

const targetId = computed(() => props.employeeId || props.employee?.id)
const resolvedEmployee = computed(() => employeeDetails.value || props.employee)

const activeIssuances = computed(() =>
  (resolvedEmployee.value?.assignments || []).filter(a => !a.returned_at),
)

const pastIssuances = computed(() =>
  (resolvedEmployee.value?.assignments || []).filter(a => !!a.returned_at),
)

const requirementsList = computed(() =>
  resolvedEmployee.value?.workwear_requirements || [],
)

const sections = computed(() => [
  { id: 'bio', label: t('Profile & Client'), icon: 'tabler-info-circle' },
  { id: 'sizes', label: t('Sizes & Measures'), icon: 'tabler-ruler-measure' },
  { id: 'history', label: t('Issued Workwear Items'), icon: 'tabler-hanger', badge: activeIssuances.value.length || undefined },
  { id: 'requirements', label: t('Requirements'), icon: 'tabler-checklist', badge: requirementsList.value.length || undefined },
])

const summaryItems = computed(() => [
  {
    label: t('Client'),
    value: resolvedEmployee.value?.client?.legal_name || resolvedEmployee.value?.client?.name || '—',
  },
  {
    label: t('Department'),
    value: resolvedEmployee.value?.department || '—',
  },
  {
    label: t('Position'),
    value: resolvedEmployee.value?.position || resolvedEmployee.value?.role || '—',
  },
  {
    label: t('Active Workwear Items'),
    value: `${activeIssuances.value.length} ${t('issued')}`,
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

watch(
  () => [props.isDialogVisible, targetId.value],
  async ([visible, id]) => {
    if (visible && id) {
      activeSection.value = 'bio'
      historySubTab.value = 'active'
      loading.value = true
      try {
        employeeDetails.value = await store.fetchWorkwearEmployee(id)
      } catch (err) {
        console.error('Error loading employee dossier:', err)
      } finally {
        loading.value = false
      }
    } else if (!visible) {
      employeeDetails.value = null
    }
  },
  { immediate: true },
)
</script>

<template>
  <WpDetailDialog
    :model-value="isDialogVisible"
    :title="resolvedEmployee?.full_name || `${resolvedEmployee?.first_name || ''} ${resolvedEmployee?.last_name || ''}` || $t('Employee Dossier')"
    :subtitle="resolvedEmployee?.employee_ref ? `${$t('Ref:')} ${resolvedEmployee.employee_ref}` : ''"
    icon="tabler-user"
    :loading="loading && !resolvedEmployee"
    max-width="1120"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <!-- 👉 Badges -->
    <template #badges>
      <div class="d-flex align-center gap-2 flex-wrap">
        <VChip
          v-if="resolvedEmployee?.status"
          size="x-small"
          label
          :color="statusMap[resolvedEmployee.status] || 'secondary'"
          class="text-capitalize font-weight-bold"
        >
          {{ resolvedEmployee.status }}
        </VChip>
        <VChip
          v-if="resolvedEmployee?.department"
          size="x-small"
          variant="tonal"
          color="primary"
        >
          {{ resolvedEmployee.department }}
        </VChip>
      </div>
    </template>

    <!-- 👉 Actions -->
    <template #actions>
      <VBtn
        v-if="resolvedEmployee?.id"
        variant="tonal"
        size="small"
        color="primary"
        prepend-icon="tabler-edit"
        :to="{ name: 'client-assets-workwear-edit-id', params: { id: resolvedEmployee.id }, query: { type: 'employee' } }"
        @click="emit('update:isDialogVisible', false)"
      >
        {{ $t('Edit Profile') }}
      </VBtn>
    </template>

    <!-- 👉 Main Journal Mirror Layout -->
    <div
      v-if="resolvedEmployee"
      class="dialog-journal-layout"
    >
      <WpJournalNav
        v-model="activeSection"
        :sections="sections"
        :summary-items="summaryItems"
        is-dialog
        id-prefix="dialog-section-"
        container=".wp-detail-dialog__scroll"
      />

      <main class="dialog-journal-feed">
        <!-- KPI Strip -->
        <WpKpiGroup
          :item-count="4"
          :cols="{ xs: 6, sm: 6, md: 3 }"
          class="mb-4"
        >
          <template #default="{ getColProps }">
            <VCol v-bind="getColProps(0)">
              <WpKpiCard
                :title="$t('Issued Items')"
                :value="activeIssuances.length"
                icon="tabler-hanger"
                icon-color="success"
              />
            </VCol>
            <VCol v-bind="getColProps(1)">
              <WpKpiCard
                :title="$t('Top Size')"
                :value="resolvedEmployee.sizes?.top || '—'"
                icon="tabler-shirt"
                icon-color="primary"
              />
            </VCol>
            <VCol v-bind="getColProps(2)">
              <WpKpiCard
                :title="$t('Requirements')"
                :value="requirementsList.length"
                icon="tabler-checklist"
                icon-color="info"
              />
            </VCol>
            <VCol v-bind="getColProps(3)">
              <WpKpiCard
                :title="$t('Past Handovers')"
                :value="pastIssuances.length"
                icon="tabler-history"
                icon-color="secondary"
              />
            </VCol>
          </template>
        </WpKpiGroup>

        <!-- ━━━ 1. Profile & Client ━━━ -->
        <section
          id="dialog-section-bio"
          class="dialog-journal-section mb-6"
        >
          <VCard
            variant="outlined"
            class="pa-4 pa-sm-5 rounded-lg"
          >
            <h6 class="text-subtitle-1 font-weight-bold mb-3">
              <VIcon
                icon="tabler-info-circle"
                size="18"
                color="primary"
                class="me-1"
              />
              {{ $t('Employee Profile & Operational Role') }}
            </h6>

            <VRow dense>
              <VCol
                cols="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis">
                  {{ $t('Full Name') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ resolvedEmployee.first_name }} {{ resolvedEmployee.last_name }}
                </div>
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis">
                  {{ $t('Client Affiliation') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ resolvedEmployee.client?.legal_name || resolvedEmployee.client?.name || '—' }}
                </div>
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis">
                  {{ $t('Email Address') }}
                </div>
                <div class="text-body-2">
                  {{ resolvedEmployee.email || '—' }}
                </div>
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis">
                  {{ $t('Phone Number') }}
                </div>
                <div class="text-body-2">
                  {{ resolvedEmployee.phone || '—' }}
                </div>
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis">
                  {{ $t('Department') }}
                </div>
                <div class="text-body-2">
                  {{ resolvedEmployee.department || '—' }}
                </div>
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis">
                  {{ $t('Position / Role') }}
                </div>
                <div class="text-body-2">
                  {{ resolvedEmployee.position || resolvedEmployee.role || '—' }}
                </div>
              </VCol>
              <VCol
                v-if="resolvedEmployee.notes"
                cols="12"
                class="mt-2"
              >
                <div class="text-caption text-medium-emphasis">
                  {{ $t('Notes') }}
                </div>
                <div class="text-body-2 text-pre-wrap">
                  {{ resolvedEmployee.notes }}
                </div>
              </VCol>
            </VRow>
          </VCard>
        </section>

        <!-- ━━━ 2. Sizes & Measurements ━━━ -->
        <section
          id="dialog-section-sizes"
          class="dialog-journal-section mb-6"
        >
          <VCard
            variant="outlined"
            class="pa-4 pa-sm-5 rounded-lg"
          >
            <h6 class="text-subtitle-1 font-weight-bold mb-3">
              <VIcon
                icon="tabler-ruler-measure"
                size="18"
                color="primary"
                class="me-1"
              />
              {{ $t('Garment Sizes & Body Measurements') }}
            </h6>

            <div class="text-caption font-weight-bold text-uppercase text-disabled mb-2">
              {{ $t('Garment Sizes') }}
            </div>
            <div class="d-flex flex-wrap gap-2 mb-4">
              <VChip
                v-for="(val, key) in (resolvedEmployee.sizes || {})"
                :key="key"
                size="small"
                variant="tonal"
                class="text-capitalize"
              >
                {{ key }}: <strong class="ms-1">{{ val || '—' }}</strong>
              </VChip>
              <span
                v-if="!Object.keys(resolvedEmployee.sizes || {}).length"
                class="text-caption text-disabled"
              >
                {{ $t('No sizes recorded') }}
              </span>
            </div>

            <div class="text-caption font-weight-bold text-uppercase text-disabled mb-2">
              {{ $t('Body Measurements') }}
            </div>
            <div class="d-flex flex-wrap gap-2">
              <VChip
                v-for="(val, key) in (resolvedEmployee.measurements || {})"
                :key="key"
                size="small"
                variant="outlined"
                class="text-capitalize"
              >
                {{ key }}: <span class="ms-1 font-weight-medium">{{ val }} cm</span>
              </VChip>
              <span
                v-if="!Object.keys(resolvedEmployee.measurements || {}).length"
                class="text-caption text-disabled"
              >
                {{ $t('No measurements recorded') }}
              </span>
            </div>
          </VCard>
        </section>

        <!-- ━━━ 3. Issued Workwear Items ━━━ -->
        <section
          id="dialog-section-history"
          class="dialog-journal-section mb-6"
        >
          <VCard
            variant="outlined"
            class="pa-4 pa-sm-5 rounded-lg"
          >
            <div class="d-flex align-center justify-space-between mb-3">
              <h6 class="text-subtitle-1 font-weight-bold mb-0">
                <VIcon
                  icon="tabler-hanger"
                  size="18"
                  color="primary"
                  class="me-1"
                />
                {{ $t('Issued Workwear Items (Linked to Workwear Models)') }}
              </h6>
              <VBtnToggle
                v-model="historySubTab"
                mandatory
                density="compact"
                color="primary"
                variant="outlined"
              >
                <VBtn
                  value="active"
                  size="x-small"
                >
                  {{ $t('Active') }} ({{ activeIssuances.length }})
                </VBtn>
                <VBtn
                  value="past"
                  size="x-small"
                >
                  {{ $t('Past') }} ({{ pastIssuances.length }})
                </VBtn>
              </VBtnToggle>
            </div>

            <VTable
              v-if="(historySubTab === 'active' ? activeIssuances : pastIssuances).length"
              density="compact"
              class="border rounded text-no-wrap"
            >
              <thead>
                <tr>
                  <th>{{ $t('Item Code') }}</th>
                  <th>{{ $t('Workwear Model') }}</th>
                  <th>{{ $t('Size / Color') }}</th>
                  <th>{{ $t('Assigned Date') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="a in (historySubTab === 'active' ? activeIssuances : pastIssuances)"
                  :key="a.id"
                >
                  <td class="font-mono font-weight-medium">
                    {{ a.item?.item_code || '—' }}
                  </td>
                  <td>
                    {{ a.item?.model?.name || a.item?.workwear_model?.name || a.item?.model_name || a.item?.garment_type || '—' }}
                  </td>
                  <td>
                    <VChip
                      v-if="a.item?.size"
                      size="x-small"
                      variant="outlined"
                      class="font-mono me-1"
                    >
                      {{ a.item.size }}
                    </VChip>
                    <span
                      v-if="a.item?.color"
                      class="text-caption text-capitalize"
                    >{{ a.item.color }}</span>
                  </td>
                  <td>{{ formatDate(a.assigned_at) }}</td>
                </tr>
              </tbody>
            </VTable>

            <div
              v-else
              class="text-caption text-disabled text-center py-4"
            >
              {{ historySubTab === 'active' ? $t('No active workwear items issued.') : $t('No past return history recorded.') }}
            </div>
          </VCard>
        </section>

        <!-- ━━━ 4. Workwear Requirements ━━━ -->
        <section
          id="dialog-section-requirements"
          class="dialog-journal-section mb-6"
        >
          <VCard
            variant="outlined"
            class="pa-4 pa-sm-5 rounded-lg"
          >
            <h6 class="text-subtitle-1 font-weight-bold mb-3">
              <VIcon
                icon="tabler-checklist"
                size="18"
                color="primary"
                class="me-1"
              />
              {{ $t('Role PPE & Garment Requirements') }}
            </h6>

            <div
              v-if="requirementsList.length"
              class="d-flex flex-wrap gap-2"
            >
              <VChip
                v-for="(req, rIdx) in requirementsList"
                :key="rIdx"
                size="small"
                variant="tonal"
                color="info"
                class="text-capitalize"
              >
                {{ req.garment_type }}: {{ req.quantity }} {{ $t('pcs') }}
                <span
                  v-if="req.notes"
                  class="ms-1 text-caption"
                >({{ req.notes }})</span>
              </VChip>
            </div>

            <div
              v-else
              class="text-caption text-disabled text-center py-4"
            >
              {{ $t('No requirements configured.') }}
            </div>
          </VCard>
        </section>
      </main>
    </div>
  </WpDetailDialog>
</template>

<style lang="scss" scoped>
.dialog-journal-layout {
  display: flex;
  align-items: flex-start;
  gap: 1.25rem;

  @media (max-width: 960px) {
    flex-direction: column;
  }
}

.dialog-journal-feed {
  flex: 1;
  min-inline-size: 0;
}
</style>
