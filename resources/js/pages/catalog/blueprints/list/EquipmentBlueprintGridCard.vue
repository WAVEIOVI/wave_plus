<script setup>
/* eslint-disable camelcase */
import { localizedValue } from '@/composables/useLocaleField'
import { getBlueprintEquipmentClassLabel } from '@/composables/useCatalogSelection'
import { getBlueprintStatuses } from '@/utils/catalog-constants'

const props = defineProps({
  blueprint: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['view', 'edit', 'delete', 'testBench'])

const resolveStatusVariant = stat => {
  const map = {
    active: 'success',
    draft: 'warning',
    inactive: 'error',
  }

  return map[stat?.toLowerCase()] || 'primary'
}

const resolveClassVariant = cls => {
  const map = {
    portableExtinguisher: 'info',
    portable_extinguisher: 'info',
    wheeledExtinguisher: 'warning',
    wheeled_extinguisher: 'warning',
    fixedSuppressionSystem: 'error',
    fixed_suppression_system: 'error',
    detectionPanel: 'success',
    detection_panel: 'success',
    detector: 'primary',
    hoseReel: 'secondary',
    hose_reel: 'secondary',
    fireBlanket: 'info',
    fire_blanket: 'info',
    other: 'default',
  }

  const key = typeof cls === 'object' && cls !== null ? (cls.code || '') : (cls || '')

  return map[String(key).toLowerCase()] || 'primary'
}

const getStatusTitle = val => getBlueprintStatuses().find(s => s.value === val)?.title || val
</script>

<template>
  <VCard
    class="h-100 hover-card blueprint-grid-card elevation-1"
    rounded="lg"
    @click="emit('view', blueprint)"
  >
    <VCardText class="d-flex flex-column h-100 pa-3">
      <!-- 1. Header with Badge & Actions -->
      <div class="d-flex justify-space-between align-start mb-2 gap-2">
        <VChip
          :color="resolveClassVariant(blueprint.equipment_class)"
          size="x-small"
          label
          class="shrink-0 text-truncate"
          style="max-inline-size: 160px;"
        >
          {{ getBlueprintEquipmentClassLabel(blueprint) }}
        </VChip>
        <div
          class="d-flex gap-0.5 shrink-0"
          @click.stop
        >
          <IconBtn
            size="small"
            :title="$t('catalog.blueprints.list.actions.view')"
            @click="emit('view', blueprint)"
          >
            <VIcon
              icon="tabler-eye"
              size="18"
            />
          </IconBtn>
          <IconBtn
            v-if="$can('update', 'Catalog')"
            size="small"
            :title="$t('catalog.blueprints.list.actions.edit')"
            @click="emit('edit', blueprint)"
          >
            <VIcon
              icon="tabler-edit"
              size="18"
            />
          </IconBtn>
          <MoreBtn
            size="small"
            :menu-list="[
              { title: $t('catalog.blueprints.list.actions.test_bench'), value: 'test-bench', prependIcon: 'tabler-device-desktop-analytics' },
              { title: $t('catalog.blueprints.list.actions.edit'), value: 'edit', prependIcon: 'tabler-pencil' },
              { title: $t('catalog.blueprints.list.actions.delete'), value: 'delete', prependIcon: 'tabler-trash', color: 'error' },
            ]"
            @click:list="val => {
              if (val === 'test-bench') emit('testBench', blueprint)
              if (val === 'edit') emit('edit', blueprint)
              if (val === 'delete') emit('delete', blueprint)
            }"
          />
        </div>
      </div>

      <!-- 2. Main Title (Centered Avatar) -->
      <div class="d-flex flex-column align-center text-center mt-1 mb-3">
        <VAvatar
          size="48"
          variant="tonal"
          :color="resolveClassVariant(blueprint.equipment_class)"
          class="mb-2 custom-avatar"
        >
          <VIcon
            size="24"
            icon="tabler-file-settings"
          />
        </VAvatar>
        <h5
          class="text-body-1 font-weight-bold mb-0 line-clamp-2"
          :title="localizedValue(blueprint.name)"
        >
          {{ localizedValue(blueprint.name, '—') }}
        </h5>
        <span
          class="text-caption text-primary font-weight-medium text-uppercase cursor-pointer mt-0.5"
          @click.stop="emit('edit', blueprint)"
        >
          {{ blueprint.code }}
        </span>
      </div>

      <!-- 3. Key Details -->
      <div class="blueprint-grid-card__stats d-grid py-2 px-1 rounded-lg mb-1 mt-auto border">
        <div class="text-center px-1 min-w-0">
          <p class="text-xs text-medium-emphasis mb-0 text-truncate">
            {{ $t('catalog.blueprints.status', 'Status') }}
          </p>
          <div
            class="text-caption font-weight-bold mt-0.5 text-uppercase text-truncate"
            :class="`text-${resolveStatusVariant(blueprint.status)}`"
          >
            {{ getStatusTitle(blueprint.status) }}
          </div>
        </div>
        <div class="text-center px-1 border-s border-e min-w-0">
          <p class="text-xs text-medium-emphasis mb-0 text-truncate">
            {{ $t('catalog.blueprints.pressure', 'Pressure') }}
          </p>
          <div class="text-caption font-weight-bold mt-0.5 text-truncate">
            {{ blueprint.working_pressure_bar ? `${blueprint.working_pressure_bar} bar` : '—' }}
          </div>
        </div>
        <div class="text-center px-1 min-w-0">
          <p class="text-xs text-medium-emphasis mb-0 text-truncate">
            {{ $t('catalog.blueprints.shelf_life', 'Shelf Life') }}
          </p>
          <div class="text-caption font-weight-bold mt-0.5 text-truncate">
            {{ blueprint.shelf_life_years ? `${blueprint.shelf_life_years}y` : '—' }}
          </div>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.blueprint-grid-card {
  block-size: 100%;
}

.hover-card {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  cursor: pointer;
  transition: all 0.2s ease-in-out;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.5);
    box-shadow: 0 4px 18px -4px rgba(var(--v-shadow-key-umbra-opacity), 12%) !important;
    transform: translateY(-2px);
  }
}

.blueprint-grid-card__stats {
  background: rgba(var(--v-theme-on-surface), 0.02);
  grid-template-columns: repeat(3, 1fr);
}

.line-clamp-2 {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-height: 1.35;
  overflow-wrap: anywhere;
}

.custom-avatar {
  border: 3px solid rgb(var(--v-theme-surface));
  box-shadow: 0 0 0 2px rgba(var(--v-theme-primary), 0.1);
}
</style>
