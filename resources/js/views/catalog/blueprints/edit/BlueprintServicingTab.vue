<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  servicingItems: {
    type: Array,
    required: true,
  },
  blueprintOptions: {
    type: Array,
    default: () => [],
  },
  readonly: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'addTask',
  'editTask',
  'removeTask',
])

const { t } = useI18n()

const getSourceOptionLabel = sourceId => {
  if (!sourceId) return t('Option')
  const opt = props.blueprintOptions.find(o => String(o.id) === String(sourceId) || String(o.code) === String(sourceId))
  if (opt) {
    return localizedValue(opt.name, opt.code) || opt.code
  }

  return sourceId
}

const calcMethodColor = method => {
  const map = { fixed: 'success', multiply: 'primary', add: 'info', subtract: 'warning', divide: 'secondary' }

  return map[method] || 'primary'
}

const calcMethodLabel = (con) => {
  if (con.calculation_method === 'fixed') {
    return `${t('Qty')}: ${con.fixed_quantity ?? 1}`
  }

  const optLabel = getSourceOptionLabel(con.calculation_source_option_id)
  const param = con.calculation_parameter ?? con.multiplier ?? 1

  const opMap = { multiply: '×', add: '+', subtract: '−', divide: '÷' }
  const op = opMap[con.calculation_method] || '×'

  return `${optLabel} ${op} ${param}`
}
</script>

<template>
  <VCardText class="pa-4 pa-sm-6">
    <!-- ── Tab Header ── -->
    <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-5">
      <div class="d-flex align-center gap-3">
        <VAvatar
          color="success"
          variant="tonal"
          size="40"
          rounded="lg"
        >
          <VIcon
            icon="tabler-tool"
            size="22"
          />
        </VAvatar>
        <div>
          <h6 class="text-subtitle-1 font-weight-bold mb-0">
            {{ t('Servicing Tasks & Consumables') }}
          </h6>
          <span class="text-caption text-medium-emphasis">
            {{ t('Link maintenance services and specify automatic spare part calculation formulas') }}
          </span>
        </div>
      </div>
      <div class="d-flex align-center gap-2">
        <VChip
          v-if="servicingItems.length"
          size="small"
          color="success"
          variant="tonal"
        >
          {{ servicingItems.length }} {{ t('task(s)') }}
        </VChip>
        <VBtn
          v-if="!props.readonly"
          color="success"
          size="small"
          prepend-icon="tabler-plus"
          class="d-none d-md-inline-flex"
          @click="emit('addTask')"
        >
          {{ t('Add Service Task') }}
        </VBtn>
      </div>
    </div>

    <!-- ── Empty state ── -->
    <div
      v-if="!servicingItems.length"
      class="servicing-empty-state pa-6 pa-sm-10 rounded-lg border border-dashed text-center"
    >
      <VAvatar
        color="success"
        variant="tonal"
        size="64"
        class="mb-4"
        rounded="lg"
      >
        <VIcon
          icon="tabler-tool"
          size="32"
        />
      </VAvatar>
      <h6 class="text-h6 font-weight-bold mb-2">
        {{ t('No service tasks mapped') }}
      </h6>
      <p
        class="text-body-2 text-medium-emphasis mb-5 mx-auto"
        style="max-inline-size: 420px;"
      >
        {{ t('Map maintenance services from the catalog and configure formulas to automatically calculate required spare parts and consumables based on equipment options.') }}
      </p>
      <VBtn
        v-if="!props.readonly"
        color="success"
        prepend-icon="tabler-plus"
        @click="emit('addTask')"
      >
        {{ t('Add First Service Task') }}
      </VBtn>
    </div>

    <!-- ── Servicing Tasks List ── -->
    <div
      v-else
      class="d-flex flex-column gap-4"
    >
      <VCard
        v-for="(svc, sIdx) in servicingItems"
        :key="sIdx"
        variant="outlined"
        class="svc-card rounded-lg overflow-hidden"
      >
        <!-- Task Header -->
        <div class="svc-card-header pa-3 pa-sm-4 d-flex align-center justify-space-between flex-wrap gap-2">
          <div class="d-flex align-center gap-2 gap-sm-3 min-w-0">
            <VAvatar
              color="success"
              variant="flat"
              size="34"
              class="flex-shrink-0"
              rounded="lg"
            >
              <VIcon
                icon="tabler-tool"
                size="18"
              />
            </VAvatar>
            <div class="min-w-0">
              <div class="d-flex align-center gap-2 flex-wrap mb-0">
                <h6 class="text-subtitle-2 text-sm-subtitle-1 font-weight-bold mb-0">
                  {{ localizedValue(svc.servicing_item?.name, svc.servicing_item?.code) || `${t('Service Task')} #${sIdx + 1}` }}
                </h6>
                <VChip
                  v-if="svc.servicing_item?.code"
                  size="x-small"
                  color="secondary"
                  variant="tonal"
                  label
                  class="svc-code-chip"
                >
                  {{ svc.servicing_item.code }}
                </VChip>
                <!-- Applicability indicator -->
                <VChip
                  size="x-small"
                  :color="svc.applicability_conditions ? 'warning' : 'info'"
                  variant="tonal"
                  :prepend-icon="svc.applicability_conditions ? 'tabler-filter' : 'tabler-world'"
                >
                  {{ svc.applicability_conditions ? t('Conditional') : t('Always applies') }}
                </VChip>
              </div>
              <span class="text-caption text-medium-emphasis d-block">
                <VIcon
                  icon="tabler-box"
                  size="12"
                  class="me-1"
                />
                {{ svc.consumables?.length || 0 }} {{ t('consumable(s) configured') }}
              </span>
            </div>
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
              @click="emit('editTask', sIdx)"
            >
              <span class="d-none d-sm-inline">{{ t('Edit Task') }}</span>
              <span class="d-sm-none">{{ t('Edit') }}</span>
            </VBtn>
            <IconBtn
              size="small"
              color="error"
              variant="text"
              @click="emit('removeTask', sIdx)"
            >
              <VIcon icon="tabler-trash" />
              <VTooltip activator="parent">
                {{ t('Remove task') }}
              </VTooltip>
            </IconBtn>
          </div>
        </div>

        <!-- Consumables section -->
        <div class="svc-consumables-area pa-3 pa-sm-4 pt-2">
          <div class="text-caption font-weight-bold text-medium-emphasis mb-2 d-flex align-center gap-1">
            <VIcon
              icon="tabler-packages"
              size="14"
            />
            {{ t('Required Consumables & Spare Parts') }} ({{ svc.consumables?.length || 0 }}):
          </div>

          <div
            v-if="!svc.consumables?.length"
            class="pa-3 text-caption text-disabled border rounded-lg border-dashed text-center"
          >
            <VIcon
              icon="tabler-tools"
              size="16"
              class="me-1 opacity-50"
            />
            {{ t('No spare parts attached — labor-only task.') }}
          </div>

          <!-- Desktop table -->
          <VTable
            v-else
            density="compact"
            class="consumable-table border rounded-lg d-none d-sm-block"
          >
            <thead>
              <tr>
                <th>{{ t('Consumable Item') }}</th>
                <th>{{ t('Method') }}</th>
                <th>{{ t('Formula / Quantity') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(con, cIdx) in svc.consumables"
                :key="cIdx"
                class="consumable-row"
              >
                <td class="font-weight-medium">
                  {{ localizedValue(con.consumable_item?.name, con.consumable_item?.code) || `${t('Item')} #${con.consumable_item_id}` }}
                </td>
                <td>
                  <VChip
                    size="x-small"
                    :color="calcMethodColor(con.calculation_method)"
                    variant="tonal"
                  >
                    {{ con.calculation_method }}
                  </VChip>
                </td>
                <td class="text-caption font-weight-medium">
                  {{ calcMethodLabel(con) }}
                </td>
              </tr>
            </tbody>
          </VTable>

          <!-- Mobile stacked view -->
          <div class="d-sm-none d-flex flex-column gap-2">
            <div
              v-for="(con, cIdx) in svc.consumables"
              :key="cIdx"
              class="consumable-mobile-card pa-2 border rounded d-flex flex-column gap-1"
            >
              <div class="d-flex align-center justify-space-between flex-wrap gap-1">
                <span class="text-body-2 font-weight-bold">
                  {{ localizedValue(con.consumable_item?.name, con.consumable_item?.code) || `${t('Item')} #${con.consumable_item_id}` }}
                </span>
                <VChip
                  size="x-small"
                  :color="calcMethodColor(con.calculation_method)"
                  variant="tonal"
                >
                  {{ con.calculation_method }}
                </VChip>
              </div>
              <div class="text-caption text-medium-emphasis">
                <span class="font-weight-medium">{{ t('Formula') }}:</span>
                {{ calcMethodLabel(con) }}
              </div>
            </div>
          </div>
        </div>
      </VCard>

      <!-- Mobile add button -->
      <div
        v-if="!props.readonly"
        class="d-flex justify-center d-md-none mt-1"
      >
        <VBtn
          color="success"
          size="small"
          variant="tonal"
          prepend-icon="tabler-plus"
          @click="emit('addTask')"
        >
          {{ t('Add Service Task') }}
        </VBtn>
      </div>
    </div>
  </VCardText>
</template>

<style lang="scss" scoped>
.servicing-empty-state {
  background: rgba(var(--v-theme-on-surface), 0.02);
}

.svc-card {
  border-radius: 10px !important;
  transition: box-shadow 0.2s ease, transform 0.15s ease;

  &:hover {
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08) !important;
    transform: translateY(-1px);
  }
}

.svc-card-header {
  background: rgba(var(--v-theme-on-surface), 0.03);
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.svc-consumables-area {
  background: transparent;
}

.svc-code-chip {
  font-family: 'Roboto Mono', ui-monospace, monospace;
  font-size: 0.7rem !important;
}

.consumable-table {
  thead th {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: rgba(var(--v-theme-on-surface), 0.55);
  }
}

.consumable-row {
  transition: background 0.12s ease;

  &:hover {
    background: rgba(var(--v-theme-primary), 0.04);
  }
}

.consumable-mobile-card {
  background: rgba(var(--v-theme-on-surface), 0.02);
}
</style>
