<script setup>
import { getInterventionTypes } from '@/utils/catalog-constants'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  cycles: {
    type: Array,
    required: true,
  },
  readonly: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'addCycle',
  'editCycle',
  'removeCycle',
])

const { t } = useI18n()

const getInterventionTitle = val => getInterventionTypes().find(item => item.value === val)?.title || val

const getInterventionIcon = type => {
  const map = {
    inspection: 'tabler-eye-check',
    refill: 'tabler-charging-pile',
    hydro_test: 'tabler-droplet-bolt',
    maintenance: 'tabler-tool',
    calibration: 'tabler-clipboard-check',
    replacement: 'tabler-refresh',
  }

  return map[type] || 'tabler-calendar-repeat'
}

const getInterventionColor = type => {
  const map = {
    inspection: 'info',
    refill: 'success',
    hydro_test: 'primary',
    maintenance: 'warning',
    calibration: 'secondary',
    replacement: 'error',
  }

  return map[type] || 'primary'
}
</script>

<template>
  <VCardText class="pa-4 pa-sm-6">
    <!-- ── Tab Header ── -->
    <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-5">
      <div class="d-flex align-center gap-3">
        <VAvatar
          color="info"
          variant="tonal"
          size="40"
          rounded="lg"
        >
          <VIcon
            icon="tabler-calendar-repeat"
            size="22"
          />
        </VAvatar>
        <div>
          <h6 class="text-subtitle-1 font-weight-bold mb-0">
            {{ t('Maintenance & Intervention Cycles') }}
          </h6>
          <span class="text-caption text-medium-emphasis">
            {{ t('Schedule inspections, periodic recharges, and hydrostatic tests') }}
          </span>
        </div>
      </div>
      <div class="d-flex align-center gap-2">
        <VChip
          v-if="cycles.length"
          size="small"
          color="info"
          variant="tonal"
        >
          {{ cycles.length }} {{ t('scheduled') }}
        </VChip>
        <VBtn
          v-if="!props.readonly"
          color="info"
          size="small"
          prepend-icon="tabler-plus"
          class="d-none d-md-inline-flex"
          @click="emit('addCycle')"
        >
          {{ t('Add Interval') }}
        </VBtn>
      </div>
    </div>

    <!-- ── Empty state ── -->
    <div
      v-if="!cycles.length"
      class="cycles-empty-state pa-6 pa-sm-10 rounded-lg border border-dashed text-center"
    >
      <VAvatar
        color="info"
        variant="tonal"
        size="64"
        class="mb-4"
        rounded="lg"
      >
        <VIcon
          icon="tabler-calendar-time"
          size="32"
        />
      </VAvatar>
      <h6 class="text-h6 font-weight-bold mb-2">
        {{ t('No maintenance cycles defined') }}
      </h6>
      <p
        class="text-body-2 text-medium-emphasis mb-5 mx-auto"
        style="max-inline-size: 400px;"
      >
        {{ t('Define when technicians must perform annual checks, recharges, or hydrostatic overhaul cycles. These schedules are applied to every equipment asset using this blueprint.') }}
      </p>
      <VBtn
        v-if="!props.readonly"
        color="info"
        prepend-icon="tabler-plus"
        @click="emit('addCycle')"
      >
        {{ t('Add First Interval') }}
      </VBtn>
    </div>

    <!-- ── Cycles timeline-style list ── -->
    <div
      v-else
      class="cycles-timeline d-flex flex-column"
    >
      <div
        v-for="(cycle, idx) in cycles"
        :key="idx"
        class="cycle-item d-flex gap-3"
      >
        <!-- Timeline rail -->
        <div class="cycle-rail d-flex flex-column align-center flex-shrink-0">
          <VAvatar
            :color="getInterventionColor(cycle.intervention_type)"
            variant="tonal"
            size="38"
            rounded="lg"
          >
            <VIcon
              :icon="getInterventionIcon(cycle.intervention_type)"
              size="20"
            />
          </VAvatar>
          <div
            v-if="idx < cycles.length - 1"
            class="cycle-rail-line"
          />
        </div>

        <!-- Card -->
        <VCard
          variant="outlined"
          class="cycle-card rounded-lg flex-grow-1 mb-3"
        >
          <div class="pa-3 pa-sm-4 d-flex align-center justify-space-between flex-wrap gap-3">
            <div class="min-w-0">
              <div class="d-flex align-center gap-2 flex-wrap mb-1">
                <h6 class="text-subtitle-2 text-sm-subtitle-1 font-weight-bold mb-0">
                  {{ getInterventionTitle(cycle.intervention_type) }}
                </h6>
                <VChip
                  size="x-small"
                  :color="getInterventionColor(cycle.intervention_type)"
                  variant="tonal"
                  prepend-icon="tabler-flag"
                >
                  {{ t('1st:') }} {{ cycle.first_interval_months }} {{ t('mo') }}
                </VChip>
                <VChip
                  v-if="cycle.recurring_interval_months"
                  size="x-small"
                  color="secondary"
                  variant="tonal"
                  prepend-icon="tabler-refresh"
                >
                  {{ t('every') }} {{ cycle.recurring_interval_months }} {{ t('mo') }}
                </VChip>
                <VChip
                  v-else
                  size="x-small"
                  color="warning"
                  variant="tonal"
                  prepend-icon="tabler-player-stop"
                >
                  {{ t('one-time') }}
                </VChip>
              </div>
              <span
                v-if="cycle.notes"
                class="text-caption text-medium-emphasis d-block"
              >
                <VIcon
                  icon="tabler-note"
                  size="12"
                  class="me-1"
                />
                {{ cycle.notes }}
              </span>
              <span
                v-else
                class="text-caption text-disabled d-block font-italic"
              >
                {{ t('No technician notes') }}
              </span>
            </div>

            <div
              v-if="!props.readonly"
              class="d-flex align-center gap-1 ms-auto"
            >
              <VBtn
                size="small"
                variant="tonal"
                color="primary"
                prepend-icon="tabler-edit"
                @click="emit('editCycle', idx)"
              >
                {{ t('Edit') }}
              </VBtn>
              <IconBtn
                size="small"
                color="error"
                variant="text"
                @click="emit('removeCycle', idx)"
              >
                <VIcon icon="tabler-trash" />
                <VTooltip activator="parent">
                  {{ t('Remove cycle') }}
                </VTooltip>
              </IconBtn>
            </div>
          </div>
        </VCard>
      </div>

      <!-- Mobile add button -->
      <div
        v-if="!props.readonly"
        class="d-flex justify-center d-md-none mt-1"
      >
        <VBtn
          color="info"
          size="small"
          variant="tonal"
          prepend-icon="tabler-plus"
          @click="emit('addCycle')"
        >
          {{ t('Add Interval') }}
        </VBtn>
      </div>
    </div>
  </VCardText>
</template>

<style lang="scss" scoped>
.cycles-empty-state {
  background: rgba(var(--v-theme-on-surface), 0.02);
}

.cycle-card {
  border-radius: 10px !important;
  transition: box-shadow 0.2s ease, transform 0.15s ease;

  &:hover {
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08) !important;
    transform: translateY(-1px);
  }
}

.cycle-rail {
  inline-size: 38px;
}

.cycle-rail-line {
  inline-size: 2px;
  flex: 1 1 auto;
  min-block-size: 12px;
  margin-block: 4px;
  border-radius: 2px;
  background: rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
