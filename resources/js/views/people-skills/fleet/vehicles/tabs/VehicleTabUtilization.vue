<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useVehicleStore } from '@/stores/useVehicleStore'
import { useAppSnackbar } from '@/composables/useAppSnackbar'

const props = defineProps({
  vehicle: {
    type: Object,
    required: true,
  },
})

const { t } = useI18n()
const store = useVehicleStore()
const { showMessage } = useAppSnackbar()

const loading = ref(false)
const selectedPreset = ref('last_30_days')

// Default date range: Last 30 days
const now = new Date()
const thirtyDaysAgo = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000)

const startDate = ref(thirtyDaysAgo.toISOString().split('T')[0])
const endDate = ref(now.toISOString().split('T')[0])

const utilization = computed(() => store.utilizationReport)

const loadUtilization = async () => {
  if (!props.vehicle?.id || !startDate.value || !endDate.value) return
  loading.value = true
  try {
    await store.fetchUtilization(props.vehicle.id, {
      start_date: startDate.value,
      end_date: endDate.value,
    })
  } catch (err) {
    showMessage(err?.data?.message || 'Failed to fetch utilization data', 'error')
  } finally {
    loading.value = false
  }
}

const applyPreset = preset => {
  selectedPreset.value = preset
  const today = new Date()
  endDate.value = today.toISOString().split('T')[0]

  if (preset === 'last_7_days') {
    const d = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000)
    startDate.value = d.toISOString().split('T')[0]
  } else if (preset === 'last_30_days') {
    const d = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000)
    startDate.value = d.toISOString().split('T')[0]
  } else if (preset === 'this_month') {
    const d = new Date(today.getFullYear(), today.getMonth(), 1)
    startDate.value = d.toISOString().split('T')[0]
  } else if (preset === 'last_90_days') {
    const d = new Date(today.getTime() - 90 * 24 * 60 * 60 * 1000)
    startDate.value = d.toISOString().split('T')[0]
  }

  loadUtilization()
}

onMounted(() => {
  loadUtilization()
})
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <!-- Header Controls -->
    <div class="d-flex flex-wrap justify-space-between align-center gap-4">
      <div>
        <h3 class="text-h6 font-weight-bold">
          {{ $t('fleet.utilization') || 'Fleet Asset Utilization Analysis' }}
        </h3>
        <p class="text-caption text-medium-emphasis mb-0">
          Measure operating efficiency, assignment duration, and idle capacity for this vehicle.
        </p>
      </div>

      <!-- Quick Preset Filter Buttons -->
      <div class="d-flex flex-wrap gap-2">
        <VBtn
          :variant="selectedPreset === 'last_7_days' ? 'flat' : 'tonal'"
          size="small"
          @click="applyPreset('last_7_days')"
        >
          7 Days
        </VBtn>
        <VBtn
          :variant="selectedPreset === 'last_30_days' ? 'flat' : 'tonal'"
          size="small"
          @click="applyPreset('last_30_days')"
        >
          30 Days
        </VBtn>
        <VBtn
          :variant="selectedPreset === 'this_month' ? 'flat' : 'tonal'"
          size="small"
          @click="applyPreset('this_month')"
        >
          This Month
        </VBtn>
        <VBtn
          :variant="selectedPreset === 'last_90_days' ? 'flat' : 'tonal'"
          size="small"
          @click="applyPreset('last_90_days')"
        >
          90 Days
        </VBtn>
      </div>
    </div>

    <!-- Date Range Selector Row -->
    <VCard variant="outlined">
      <VCardText class="pa-4">
        <VRow align="center">
          <VCol
            cols="12"
            sm="5"
          >
            <AppTextField
              v-model="startDate"
              :label="$t('fleet.start_date') || 'Period Start Date'"
              type="date"
              density="compact"
            />
          </VCol>
          <VCol
            cols="12"
            sm="5"
          >
            <AppTextField
              v-model="endDate"
              :label="$t('fleet.end_date') || 'Period End Date'"
              type="date"
              density="compact"
            />
          </VCol>
          <VCol
            cols="12"
            sm="2"
          >
            <VBtn
              color="primary"
              block
              density="comfortable"
              :loading="loading"
              prepend-icon="tabler-chart-bar"
              @click="loadUtilization"
            >
              Analyze
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Utilization Metrics -->
    <VProgressLinear
      v-if="loading"
      indeterminate
      color="primary"
    />

    <template v-else-if="utilization">
      <VRow>
        <!-- Main Rate Gauge / Card -->
        <VCol
          cols="12"
          md="4"
        >
          <VCard
            variant="tonal"
            :color="utilization.utilization_rate >= 70 ? 'success' : utilization.utilization_rate >= 40 ? 'primary' : 'warning'"
            class="h-100 text-center d-flex flex-column justify-center pa-6"
          >
            <div class="text-caption text-medium-emphasis mb-1 font-weight-medium">
              {{ $t('fleet.utilization_rate') || 'Asset Utilization Rate' }}
            </div>
            <div class="text-h2 font-weight-bold my-2">
              {{ utilization.utilization_rate }}%
            </div>
            <VProgressLinear
              :model-value="utilization.utilization_rate"
              height="10"
              rounded
              class="my-3"
            />
            <div class="text-caption text-medium-emphasis">
              Based on active organizational assignments and custody within selected range.
            </div>
          </VCard>
        </VCol>

        <!-- KPI Breakdown Cards -->
        <VCol
          cols="12"
          md="8"
        >
          <VRow>
            <VCol
              cols="12"
              sm="4"
            >
              <VCard variant="outlined">
                <VCardText class="pa-4">
                  <div class="text-caption text-medium-emphasis">
                    {{ $t('fleet.total_days') || 'Total Period Days' }}
                  </div>
                  <div class="text-h4 font-weight-bold mt-1">
                    {{ utilization.total_days }}
                  </div>
                  <div class="text-caption text-disabled mt-1">Calendar window</div>
                </VCardText>
              </VCard>
            </VCol>

            <VCol
              cols="12"
              sm="4"
            >
              <VCard variant="outlined">
                <VCardText class="pa-4">
                  <div class="text-caption text-success font-weight-medium">
                    {{ $t('fleet.days_in_use') || 'Days in Active Use' }}
                  </div>
                  <div class="text-h4 font-weight-bold text-success mt-1">
                    {{ utilization.used_days }}
                  </div>
                  <div class="text-caption text-medium-emphasis mt-1">Assigned / In custody</div>
                </VCardText>
              </VCard>
            </VCol>

            <VCol
              cols="12"
              sm="4"
            >
              <VCard variant="outlined">
                <VCardText class="pa-4">
                  <div class="text-caption text-medium-emphasis">
                    {{ $t('fleet.days_available') || 'Days Available / Idle' }}
                  </div>
                  <div class="text-h4 font-weight-bold text-secondary mt-1">
                    {{ utilization.available_days }}
                  </div>
                  <div class="text-caption text-medium-emphasis mt-1">Unassigned in depot</div>
                </VCardText>
              </VCard>
            </VCol>

            <VCol cols="12">
              <VCard variant="outlined">
                <VCardItem title="Operational Allocation Summary">
                  <template #append>
                    <VChip
                      size="small"
                      color="primary"
                      variant="tonal"
                    >
                      {{ utilization.period_start }} &rarr; {{ utilization.period_end }}
                    </VChip>
                  </template>
                </VCardItem>
                <VCardText>
                  <p class="text-body-2 text-medium-emphasis mb-2">
                    Of the {{ utilization.total_days }} days in this period, the vehicle was in active assignment custody for {{ utilization.used_days }} days ({{ utilization.utilization_rate }}%), leaving {{ utilization.available_days }} days available for other missions or depot maintenance.
                  </p>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VCol>
      </VRow>
    </template>

    <div
      v-else
      class="text-center py-8 text-disabled"
    >
      Click "Analyze" to compute utilization for the selected timeframe.
    </div>
  </div>
</template>
