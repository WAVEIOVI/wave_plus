<script setup>
import { $api } from '@/utils/api'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  supplierId: { type: [String, Number], required: true },
})

const { t } = useI18n()

const isLoading = ref(true)
const scorecard = ref(null)

// Fallback demo data when API not yet implemented
const fallback = {
  overall_score: 0,
  quality_score: 0,
  on_time_score: 0,
  price_score: 0,
  responsiveness: 0,
  total_pos: 0,
  total_grns: 0,
  total_rejected_qty: 0,
  avg_days_late: 0,
  last_evaluated_at: null,
}

onMounted(async () => {
  isLoading.value = true
  try {
    const res = await $api(`/procurement/suppliers/${props.supplierId}/scorecard`, { method: 'GET' })

    scorecard.value = res.data || fallback
  } catch {
    scorecard.value = fallback
  } finally {
    isLoading.value = false
  }
})

const gradeColor = score => {
  if (score >= 80) return 'success'
  if (score >= 60) return 'warning'
  if (score >= 40) return 'orange'
  
  return 'error'
}

const gradeLetter = score => {
  if (score >= 90) return 'A+'
  if (score >= 80) return 'A'
  if (score >= 70) return 'B'
  if (score >= 60) return 'C'
  if (score >= 40) return 'D'
  
  return 'F'
}

const metrics = computed(() => [
  { label: t('suppliers.scorecard.metrics.quality_score.label'),    key: 'quality_score',   icon: 'tabler-shield-check',    desc: t('suppliers.scorecard.metrics.quality_score.desc') },
  { label: t('suppliers.scorecard.metrics.on_time_delivery.label'), key: 'on_time_score',   icon: 'tabler-clock-check',     desc: t('suppliers.scorecard.metrics.on_time_delivery.desc') },
  { label: t('suppliers.scorecard.metrics.price_accuracy.label'),   key: 'price_score',     icon: 'tabler-coin',            desc: t('suppliers.scorecard.metrics.price_accuracy.desc') },
  { label: t('suppliers.scorecard.metrics.responsiveness.label'),   key: 'responsiveness',  icon: 'tabler-message-check',   desc: t('suppliers.scorecard.metrics.responsiveness.desc') },
])
</script>

<template>
  <div class="scorecard-wrapper">
    <!-- Loading -->
    <VSkeletonLoader
      v-if="isLoading"
      type="card, list-item-two-line@4"
    />

    <template v-else-if="scorecard">
      <!-- Overall grade hero -->
      <VCard class="scorecard-hero mb-6">
        <VCardText class="d-flex flex-column flex-sm-row align-center gap-6 pa-6">
          <!-- Circular grade -->
          <div
            class="grade-circle"
            :class="`grade-circle--${gradeColor(scorecard.overall_score)}`"
          >
            <span class="grade-letter">{{ gradeLetter(scorecard.overall_score) }}</span>
            <span class="grade-num">{{ (scorecard.overall_score || 0).toFixed(0) }}/100</span>
          </div>

          <div class="flex-1">
            <h5 class="text-h5 font-weight-bold mb-1">
              {{ $t('suppliers.scorecard.title') }}
            </h5>
            <p class="text-body-2 text-medium-emphasis mb-4">
              {{ $t('suppliers.scorecard.subtitle') }}
              <template v-if="scorecard.last_evaluated_at">
                {{ $t('suppliers.scorecard.last_updated') }}: <strong>{{ new Date(scorecard.last_evaluated_at).toLocaleDateString() }}</strong>
              </template>
            </p>

            <!-- Overall progress bar -->
            <div class="d-flex align-center gap-3">
              <VProgressLinear
                :model-value="scorecard.overall_score || 0"
                :color="gradeColor(scorecard.overall_score)"
                rounded
                height="10"
                class="flex-1"
              />
              <span
                class="font-weight-bold text-body-1"
                :class="`text-${gradeColor(scorecard.overall_score)}`"
              >
                {{ (scorecard.overall_score || 0).toFixed(1) }}%
              </span>
            </div>
          </div>

          <!-- Quick stats -->
          <div class="d-flex flex-wrap gap-4">
            <div class="stat-box text-center">
              <h5 class="text-h5 font-weight-bold">
                {{ scorecard.total_pos || 0 }}
              </h5>
              <p class="text-caption text-disabled mb-0">
                {{ $t('suppliers.scorecard.total_pos') }}
              </p>
            </div>
            <div class="stat-box text-center">
              <h5 class="text-h5 font-weight-bold">
                {{ scorecard.total_grns || 0 }}
              </h5>
              <p class="text-caption text-disabled mb-0">
                {{ $t('suppliers.scorecard.grns') }}
              </p>
            </div>
            <div class="stat-box text-center">
              <h5 class="text-h5 font-weight-bold text-error">
                {{ scorecard.total_rejected_qty || 0 }}
              </h5>
              <p class="text-caption text-disabled mb-0">
                {{ $t('suppliers.scorecard.rejected_units') }}
              </p>
            </div>
            <div class="stat-box text-center">
              <h5
                class="text-h5 font-weight-bold"
                :class="(scorecard.avg_days_late || 0) > 0 ? 'text-warning' : 'text-success'"
              >
                {{ (scorecard.avg_days_late || 0).toFixed(1) }}d
              </h5>
              <p class="text-caption text-disabled mb-0">
                {{ $t('suppliers.scorecard.avg_days_late') }}
              </p>
            </div>
          </div>
        </VCardText>
      </VCard>

      <!-- Metric breakdown cards -->
      <VRow>
        <VCol
          v-for="metric in metrics"
          :key="metric.key"
          cols="12"
          sm="6"
        >
          <VCard class="metric-card h-100">
            <VCardText class="pa-5">
              <div class="d-flex align-center gap-3 mb-4">
                <VAvatar
                  rounded
                  size="44"
                  :color="gradeColor(scorecard[metric.key] || 0)"
                  variant="tonal"
                >
                  <VIcon
                    :icon="metric.icon"
                    size="22"
                  />
                </VAvatar>
                <div class="flex-1">
                  <p class="text-body-2 font-weight-bold mb-0">
                    {{ metric.label }}
                  </p>
                  <p class="text-caption text-medium-emphasis mb-0">
                    {{ metric.desc }}
                  </p>
                </div>
                <div class="text-end">
                  <span
                    class="text-h5 font-weight-bold"
                    :class="`text-${gradeColor(scorecard[metric.key] || 0)}`"
                  >
                    {{ (scorecard[metric.key] || 0).toFixed(0) }}
                  </span>
                  <span class="text-caption text-disabled">/100</span>
                </div>
              </div>

              <VProgressLinear
                :model-value="scorecard[metric.key] || 0"
                :color="gradeColor(scorecard[metric.key] || 0)"
                rounded
                height="8"
                bg-color="rgba(var(--v-border-color), var(--v-border-opacity))"
              />

              <!-- Grade badge -->
              <div class="d-flex justify-end mt-2">
                <VChip
                  :color="gradeColor(scorecard[metric.key] || 0)"
                  size="x-small"
                  label
                  variant="tonal"
                >
                  {{ $t('suppliers.scorecard.grade') }}: {{ gradeLetter(scorecard[metric.key] || 0) }}
                </VChip>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- No data alert -->
      <VAlert
        v-if="!scorecard.total_pos"
        type="info"
        variant="tonal"
        density="compact"
        class="mt-4"
        prepend-icon="tabler-info-circle"
      >
        {{ $t('suppliers.scorecard.no_transactions') }}
      </VAlert>
    </template>
  </div>
</template>

<style lang="scss" scoped>
.scorecard-hero {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.06);
  background: rgba(var(--v-theme-surface), 0.85);
}

.metric-card {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.06);
  transition: box-shadow 0.2s, transform 0.2s;
  &:hover { box-shadow: 0 4px 20px rgba(0,0,0,8%); transform: translateY(-2px); }
}

/* Circular grade badge */
.grade-circle {
  display: flex;
  flex-shrink: 0;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border: 4px solid transparent;
  border-radius: 50%;
  block-size: 100px;
  inline-size: 100px;
  gap: 2px;

  &--success { border-color: rgb(var(--v-theme-success)); background: rgba(var(--v-theme-success), 0.1); }
  &--warning { border-color: rgb(var(--v-theme-warning)); background: rgba(var(--v-theme-warning), 0.1); }
  &--orange  { border-color: #f57c00;                    background: rgba(245, 124, 0, 0.1); }
  &--error   { border-color: rgb(var(--v-theme-error));   background: rgba(var(--v-theme-error), 0.1); }
}

.grade-letter {
  font-size: 1.75rem;
  font-weight: 800;
  line-height: 1;
}

.grade-num {
  font-size: 0.65rem;
  opacity: 0.65;
}

.stat-box {
  min-inline-size: 60px;
}

.flex-1 { flex: 1; }
.h-100  { block-size: 100%; }
</style>
