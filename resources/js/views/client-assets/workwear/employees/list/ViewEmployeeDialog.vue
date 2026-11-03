<script setup>
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, ref, watch } from 'vue'
import { useDisplay } from 'vuetify'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  employeeId: { type: [String, Number], default: null },
  employee: { type: Object, default: null },
})

const emit = defineEmits(['update:isDialogVisible'])

const store = useClientAssetStore()
const { smAndDown } = useDisplay()
const loading = ref(false)
const employeeDetails = ref(null)
const activeTab = ref('entitlements')

const statusMap = { active: 'success', inactive: 'secondary', terminated: 'error' }

const targetId = computed(() => props.employeeId || props.employee?.id)
const resolvedEmployee = computed(() => employeeDetails.value || props.employee)

const activeIssuances = computed(() =>
  (resolvedEmployee.value?.assignments || []).filter(a => !a.returned_at),
)

const pastIssuances = computed(() =>
  (resolvedEmployee.value?.assignments || []).filter(a => !!a.returned_at),
)

const modelEntitlements = computed(() =>
  (resolvedEmployee.value?.model_assignments || []).filter(m => !m.ended_at),
)

watch(
  () => [props.isDialogVisible, targetId.value],
  async ([visible, id]) => {
    if (visible && id) {
      activeTab.value = 'entitlements'
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
  <VDialog
    :model-value="isDialogVisible"
    :max-width="smAndDown ? undefined : 920"
    :fullscreen="smAndDown"
    scrollable
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard
      class="view-dialog-card"
      :class="{ 'rounded-0': smAndDown }"
    >
      <!-- Header -->
      <VCardTitle class="d-flex flex-wrap align-center justify-space-between gap-3 pa-4 pa-sm-5">
        <div class="d-flex align-center gap-3">
          <VAvatar
            rounded
            size="48"
            color="primary"
            variant="tonal"
          >
            <span class="text-h6 font-weight-bold">
              {{ (resolvedEmployee?.first_name?.[0] || '') + (resolvedEmployee?.last_name?.[0] || '') }}
            </span>
          </VAvatar>
          <div>
            <div class="d-flex align-center gap-2 flex-wrap">
              <span class="text-h5 font-weight-bold">{{ resolvedEmployee?.full_name || $t('Employee Dossier') }}</span>
              <VChip
                v-if="resolvedEmployee?.status"
                label
                size="small"
                :color="statusMap[resolvedEmployee.status] || 'secondary'"
                class="text-capitalize font-weight-medium"
              >
                {{ resolvedEmployee.status }}
              </VChip>
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
              <span class="font-mono font-weight-bold">{{ resolvedEmployee?.employee_ref }}</span>
              <span v-if="resolvedEmployee?.client?.legal_name"> • {{ resolvedEmployee.client.legal_name }}</span>
              <span v-if="resolvedEmployee?.department"> • {{ resolvedEmployee.department }}</span>
              <span v-if="resolvedEmployee?.position || resolvedEmployee?.role"> ({{ resolvedEmployee.position || resolvedEmployee.role }})</span>
            </div>
          </div>
        </div>

        <VBtn
          icon
          variant="text"
          size="small"
          @click="emit('update:isDialogVisible', false)"
        >
          <VIcon
            icon="tabler-x"
            size="20"
          />
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText
        class="pa-0"
        :style="smAndDown ? undefined : 'max-block-size: 76vh; overflow-y: auto;'"
      >
        <div
          v-if="loading && !resolvedEmployee"
          class="d-flex justify-center align-center py-16"
        >
          <VProgressCircular
            indeterminate
            size="48"
            color="primary"
          />
        </div>

        <div
          v-else-if="resolvedEmployee"
          class="pa-4 pa-sm-6"
        >
          <!-- KPI Summary Strip -->
          <VRow
            dense
            class="mb-4"
          >
            <VCol
              cols="6"
              sm="3"
            >
              <VCard
                variant="tonal"
                color="info"
                class="pa-3 text-center"
              >
                <div class="text-caption text-uppercase">
                  {{ $t('Assigned Models') }}
                </div>
                <div class="text-h6 font-weight-bold">
                  {{ modelEntitlements.length }}
                </div>
              </VCard>
            </VCol>
            <VCol
              cols="6"
              sm="3"
            >
              <VCard
                variant="tonal"
                color="success"
                class="pa-3 text-center"
              >
                <div class="text-caption text-uppercase">
                  {{ $t('Issued Garments') }}
                </div>
                <div class="text-h6 font-weight-bold">
                  {{ activeIssuances.length }}
                </div>
              </VCard>
            </VCol>
            <VCol
              cols="6"
              sm="3"
            >
              <VCard
                variant="tonal"
                color="primary"
                class="pa-3 text-center"
              >
                <div class="text-caption text-uppercase">
                  {{ $t('Requirements') }}
                </div>
                <div class="text-h6 font-weight-bold">
                  {{ resolvedEmployee.workwear_requirements?.length || 0 }}
                </div>
              </VCard>
            </VCol>
            <VCol
              cols="6"
              sm="3"
            >
              <VCard
                variant="tonal"
                color="secondary"
                class="pa-3 text-center"
              >
                <div class="text-caption text-uppercase">
                  {{ $t('Past Returns') }}
                </div>
                <div class="text-h6 font-weight-bold">
                  {{ pastIssuances.length }}
                </div>
              </VCard>
            </VCol>
          </VRow>

          <VRow>
            <!-- Left Column: Anthropometric Profile & Garment Sizing -->
            <VCol
              cols="12"
              md="4"
            >
              <VCard
                variant="outlined"
                class="pa-3 mb-4"
              >
                <div class="text-caption font-weight-bold text-uppercase text-disabled mb-2">
                  {{ $t('Garment Size Matrix') }}
                </div>
                <div
                  v-if="resolvedEmployee.sizes && Object.values(resolvedEmployee.sizes).some(Boolean)"
                  class="d-flex flex-wrap gap-2"
                >
                  <VChip
                    v-for="(val, key) in resolvedEmployee.sizes"
                    v-show="val"
                    :key="key"
                    size="small"
                    color="primary"
                    variant="tonal"
                    class="text-capitalize"
                  >
                    <strong class="me-1">{{ key }}:</strong> {{ val }}
                  </VChip>
                </div>
                <span
                  v-else
                  class="text-caption text-disabled"
                >{{ $t('No garment sizes recorded.') }}</span>
              </VCard>

              <VCard
                variant="outlined"
                class="pa-3 mb-4"
              >
                <div class="text-caption font-weight-bold text-uppercase text-disabled mb-2">
                  {{ $t('Body Measurements') }}
                </div>
                <div
                  v-if="resolvedEmployee.measurements && Object.values(resolvedEmployee.measurements).some(Boolean)"
                  class="d-flex flex-wrap gap-2"
                >
                  <VChip
                    v-for="(val, key) in resolvedEmployee.measurements"
                    v-show="val"
                    :key="key"
                    size="small"
                    color="secondary"
                    variant="tonal"
                    class="text-capitalize"
                  >
                    <strong class="me-1">{{ key }}:</strong> {{ val }}{{ key === 'weight' ? 'kg' : 'cm' }}
                  </VChip>
                </div>
                <span
                  v-else
                  class="text-caption text-disabled"
                >{{ $t('No body measurements recorded.') }}</span>
              </VCard>

              <VCard
                variant="outlined"
                class="pa-3"
              >
                <div class="text-caption font-weight-bold text-uppercase text-disabled mb-2">
                  {{ $t('Contact & Role Details') }}
                </div>
                <div class="d-flex flex-column gap-1 text-body-2">
                  <div><strong>{{ $t('Email:') }}</strong> {{ resolvedEmployee.email || '—' }}</div>
                  <div><strong>{{ $t('Phone:') }}</strong> {{ resolvedEmployee.phone || '—' }}</div>
                  <div><strong>{{ $t('Department:') }}</strong> {{ resolvedEmployee.department || '—' }}</div>
                  <div><strong>{{ $t('Position:') }}</strong> {{ resolvedEmployee.position || resolvedEmployee.role || '—' }}</div>
                </div>
              </VCard>
            </VCol>

            <!-- Right Column: Model Entitlements vs Issued Garments -->
            <VCol
              cols="12"
              md="8"
            >
              <VTabs
                v-model="activeTab"
                density="compact"
                color="primary"
                class="mb-4"
              >
                <VTab value="entitlements">
                  <VIcon
                    start
                    icon="tabler-shirt"
                    size="16"
                  />
                  {{ $t('Model Entitlements') }} ({{ modelEntitlements.length }})
                </VTab>
                <VTab value="issued">
                  <VIcon
                    start
                    icon="tabler-hanger"
                    size="16"
                  />
                  {{ $t('Issued Garments') }} ({{ activeIssuances.length }})
                </VTab>
                <VTab value="history">
                  <VIcon
                    start
                    icon="tabler-history"
                    size="16"
                  />
                  {{ $t('Handover History') }} ({{ pastIssuances.length }})
                </VTab>
              </VTabs>

              <VWindow v-model="activeTab">
                <!-- TAB 1: Model Entitlements -->
                <VWindowItem value="entitlements">
                  <div
                    v-if="modelEntitlements.length"
                    class="d-flex flex-column gap-2"
                  >
                    <VCard
                      v-for="ma in modelEntitlements"
                      :key="ma.id"
                      variant="outlined"
                      class="pa-3"
                    >
                      <div class="d-flex justify-space-between align-center">
                        <div>
                          <div class="font-weight-bold text-body-2">
                            {{ ma.model?.name || $t('Workwear Model') }}
                            <span
                              v-if="ma.model?.model_ref"
                              class="text-caption font-mono text-disabled ms-1"
                            >({{ ma.model.model_ref }})</span>
                          </div>
                          <div class="text-caption text-medium-emphasis">
                            {{ $t('Target Size:') }} <strong>{{ ma.size || '—' }}</strong>
                            <span v-if="ma.notes"> • {{ ma.notes }}</span>
                          </div>
                        </div>
                        <VChip
                          size="small"
                          label
                          :color="(ma.issued_count ?? 0) >= (ma.quantity || 1) ? 'success' : 'warning'"
                        >
                          {{ ma.issued_count ?? 0 }} / {{ ma.quantity || 1 }} {{ $t('issued') }}
                        </VChip>
                      </div>
                    </VCard>
                  </div>
                  <div
                    v-else
                    class="text-center py-8 text-disabled"
                  >
                    <p class="mb-0">
                      {{ $t('No workwear models assigned to this employee.') }}
                    </p>
                  </div>
                </VWindowItem>

                <!-- TAB 2: Issued Physical Garments -->
                <VWindowItem value="issued">
                  <div
                    v-if="activeIssuances.length"
                    class="d-flex flex-column gap-2"
                  >
                    <VCard
                      v-for="asgn in activeIssuances"
                      :key="asgn.id"
                      variant="outlined"
                      class="pa-3"
                    >
                      <div class="d-flex justify-space-between align-center">
                        <div>
                          <div class="font-weight-bold text-primary font-mono">
                            {{ asgn.item?.item_code }}
                            <span
                              v-if="asgn.item?.sku"
                              class="text-caption text-disabled ms-1"
                            >[{{ asgn.item.sku }}]</span>
                          </div>
                          <div class="text-body-2">
                            {{ [asgn.item?.model_name, asgn.item?.garment_type, asgn.item?.size, asgn.item?.color].filter(Boolean).join(' • ') }}
                          </div>
                          <div class="text-caption text-disabled">
                            {{ $t('Issued:') }} {{ asgn.assigned_at ? new Date(asgn.assigned_at).toLocaleDateString() : '—' }}
                          </div>
                        </div>
                        <VChip
                          size="x-small"
                          color="success"
                          variant="tonal"
                          class="text-capitalize"
                        >
                          {{ asgn.condition_at_assign || 'good' }}
                        </VChip>
                      </div>
                    </VCard>
                  </div>
                  <div
                    v-else
                    class="text-center py-8 text-disabled"
                  >
                    <p class="mb-0">
                      {{ $t('No physical garments currently issued.') }}
                    </p>
                  </div>
                </VWindowItem>

                <!-- TAB 3: Past Handover History -->
                <VWindowItem value="history">
                  <div
                    v-if="pastIssuances.length"
                    class="d-flex flex-column gap-2"
                  >
                    <VCard
                      v-for="asgn in pastIssuances"
                      :key="asgn.id"
                      variant="outlined"
                      class="pa-3"
                    >
                      <div class="d-flex justify-space-between align-center">
                        <div>
                          <div class="font-weight-bold font-mono">
                            {{ asgn.item?.item_code }}
                          </div>
                          <div class="text-caption text-medium-emphasis">
                            {{ asgn.assigned_at ? new Date(asgn.assigned_at).toLocaleDateString() : '—' }}
                            → {{ asgn.returned_at ? new Date(asgn.returned_at).toLocaleDateString() : '—' }}
                          </div>
                        </div>
                        <VChip
                          size="x-small"
                          variant="tonal"
                          class="text-capitalize"
                        >
                          {{ asgn.return_action || 'returned' }}
                        </VChip>
                      </div>
                    </VCard>
                  </div>
                  <div
                    v-else
                    class="text-center py-8 text-disabled"
                  >
                    <p class="mb-0">
                      {{ $t('No past return history.') }}
                    </p>
                  </div>
                </VWindowItem>
              </VWindow>
            </VCol>
          </VRow>
        </div>
      </VCardText>

      <VDivider />
      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:isDialogVisible', false)"
        >
          {{ $t('Close') }}
        </VBtn>
        <VBtn
          v-if="resolvedEmployee?.id"
          color="primary"
          prepend-icon="tabler-pencil"
          :to="{ name: 'client-assets-workwear-edit-id', params: { id: resolvedEmployee.id }, query: { type: 'employee' } }"
          @click="emit('update:isDialogVisible', false)"
        >
          {{ $t('Edit Employee Profile') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
