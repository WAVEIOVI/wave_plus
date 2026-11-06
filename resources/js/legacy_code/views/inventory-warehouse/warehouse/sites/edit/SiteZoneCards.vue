<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { useI18n } from 'vue-i18n'

defineProps({
  zones: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['view-locations', 'edit', 'delete', 'create'])
const { t } = useI18n()

const typeColorMap = {
  storage: { color: 'primary', border: '#3b82f6' },
  picking: { color: 'warning', border: '#f59e0b' },
  receiving: { color: 'success', border: '#22c55e' },
  shipping: { color: 'info', border: '#06b6d4' },
  qc: { color: 'secondary', border: '#8b5cf6' },
  returns: { color: 'error', border: '#ec4899' },
  buffer: { color: 'secondary', border: '#64748b' },
}

const getTypeConfig = type => typeColorMap[type] || typeColorMap.storage
</script>

<template>
  <VCard
    variant="flat"
    class="zone-cards-section"
  >
    <VCardItem class="pa-5 pb-3">
      <template #prepend>
        <VAvatar
          color="info"
          variant="tonal"
          rounded
          size="40"
        >
          <VIcon
            icon="tabler-layers-intersect"
            size="22"
          />
        </VAvatar>
      </template>
      <VCardTitle class="text-subtitle-1 font-weight-bold">
        {{ t('Zones') }}
      </VCardTitle>
      <VCardSubtitle>{{ zones.length }} {{ t('functional areas in this site') }}</VCardSubtitle>
      <template #append>
        <VBtn
          color="info"
          size="small"
          prepend-icon="tabler-plus"
          variant="tonal"
          @click="emit('create')"
        >
          {{ t('Add Zone') }}
        </VBtn>
      </template>
    </VCardItem>

    <VDivider />

    <VCardText class="pa-5">
      <!-- Loading -->
      <div
        v-if="loading"
        class="d-flex justify-center py-8"
      >
        <VProgressCircular
          indeterminate
          color="info"
        />
      </div>

      <!-- Zone Cards Grid -->
      <VRow
        v-else-if="zones.length"
        dense
      >
        <VCol
          v-for="zone in zones"
          :key="zone.id"
          cols="12"
          sm="6"
          md="4"
          lg="3"
        >
          <VCard
            variant="flat"
            class="zone-card-item"
            :style="{ borderBlockStartColor: getTypeConfig(zone.zone_type).border }"
          >
            <VCardText class="pa-4">
              <!-- Type + Code -->
              <div class="d-flex align-center justify-space-between mb-2">
                <VChip
                  :color="getTypeConfig(zone.zone_type).color"
                  size="x-small"
                  label
                  class="text-capitalize font-weight-medium"
                >
                  {{ zone.zone_type }}
                </VChip>
                <span
                  class="text-caption text-disabled"
                  style="font-family: monospace;"
                >{{ zone.code }}</span>
              </div>

              <!-- Name -->
              <h6 class="text-subtitle-2 font-weight-semibold text-high-emphasis mb-2">
                {{ localizedValue(zone.name) }}
              </h6>

              <!-- Stats -->
              <div class="d-flex gap-3 mb-3">
                <div class="d-flex align-center gap-1">
                  <VIcon
                    icon="tabler-map-pin"
                    size="14"
                    class="text-medium-emphasis"
                  />
                  <span class="text-xs text-medium-emphasis">{{ zone.locations_count ?? 0 }} {{ t('locs') }}</span>
                </div>
                <div class="d-flex align-center gap-1">
                  <VIcon
                    icon="tabler-packages"
                    size="14"
                    class="text-medium-emphasis"
                  />
                  <span class="text-xs text-medium-emphasis">{{ zone.stock_items_count ?? 0 }} {{ t('items') }}</span>
                </div>
              </div>

              <VDivider class="mb-3" />

              <!-- Actions -->
              <div class="d-flex align-center justify-space-between">
                <div class="d-flex gap-1">
                  <IconBtn
                    size="x-small"
                    color="primary"
                    @click.stop="emit('edit', zone)"
                  >
                    <VIcon
                      icon="tabler-pencil"
                      size="14"
                    />
                    <VTooltip activator="parent">
                      {{ t('Edit Zone') }}
                    </VTooltip>
                  </IconBtn>
                  <IconBtn
                    size="x-small"
                    color="error"
                    @click.stop="emit('delete', zone)"
                  >
                    <VIcon
                      icon="tabler-trash"
                      size="14"
                    />
                    <VTooltip activator="parent">
                      {{ t('Delete Zone') }}
                    </VTooltip>
                  </IconBtn>
                </div>
                <VBtn
                  :color="getTypeConfig(zone.zone_type).color"
                  variant="tonal"
                  size="x-small"
                  append-icon="tabler-arrow-right"
                  @click.stop="emit('view-locations', zone)"
                >
                  {{ t('Locations') }}
                </VBtn>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Empty -->
      <div
        v-else
        class="d-flex flex-column align-center py-10 text-center"
      >
        <VIcon
          icon="tabler-layers-intersect"
          size="48"
          class="mb-3 text-disabled"
        />
        <p class="text-sm text-medium-emphasis mb-3">
          {{ t('No zones defined for this site') }}
        </p>
        <VBtn
          color="info"
          size="small"
          prepend-icon="tabler-plus"
          @click="emit('create')"
        >
          {{ t('Create First Zone') }}
        </VBtn>
      </div>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.zone-cards-section {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 12px;
}

.zone-card-item {
  border: 0.5px solid rgba(var(--v-border-color), 0.12);
  border-radius: 10px;
  background: rgba(var(--v-theme-surface), 0.75) !important;
  border-block-start: 3px solid;
  transition: transform 0.2s ease, box-shadow 0.2s ease;

  &:hover {
    box-shadow: 0 4px 18px rgba(0, 0, 0, 8%) !important;
    transform: translateY(-2px);
  }
}
</style>
