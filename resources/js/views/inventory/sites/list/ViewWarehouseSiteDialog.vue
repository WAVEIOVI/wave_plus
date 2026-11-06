<script setup>
import {
  WpDetailDialog,
  WpEmptyState,
  WpInfoItem,
  WpJournalNav,
  WpStatusChip,
} from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  site: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  error: {
    type: [String, Object],
    default: null,
  },
})

const emit = defineEmits(['update:modelValue', 'edit', 'retry'])

const { t } = useI18n()

const activeSection = ref('overview')

const isMobileSite = computed(() => props.site?.site_type === 'mobile')

const sections = computed(() => {
  const base = [
    { key: 'overview', label: t('inventory.sites.sections.overview'), icon: 'tabler-info-circle' },
    { key: 'hierarchy', label: t('inventory.sites.sections.zones'), icon: 'tabler-hierarchy' },
  ]

  if (isMobileSite.value) {
    base.push({ key: 'vehicle', label: t('inventory.sites.sections.vehicle'), icon: 'tabler-truck' })
  }

  return base
})

watch(
  () => props.modelValue,
  isOpen => {
    if (isOpen) {
      activeSection.value = 'overview'
    }
  },
)

const resolveStatusVariant = status => ({
  active: 'success',
  inactive: 'secondary',
  maintenance: 'warning',
}[status?.toLowerCase()] || 'primary')

const resolveSiteTypeVariant = type => ({
  fixed: 'primary',
  mobile: 'info',
}[type?.toLowerCase()] || 'secondary')

const resolveSiteTypeIcon = type => ({
  fixed: 'tabler-building-warehouse',
  mobile: 'tabler-truck',
}[type?.toLowerCase()] || 'tabler-building-warehouse')

const formatDate = val => {
  if (!val) return '—'

  return new Date(val).toLocaleString()
}
</script>

<template>
  <WpDetailDialog
    :model-value="modelValue"
    :title="localizedValue(site?.name) || site?.code || t('inventory.sites.title_singular')"
    :subtitle="site?.code"
    size="large"
    :loading="loading"
    :error="error"
    show-close-footer
    @update:model-value="emit('update:modelValue', $event)"
    @retry="emit('retry')"
  >
    <template #actions>
      <VBtn
        v-if="site"
        color="primary"
        variant="tonal"
        size="small"
        prepend-icon="tabler-edit"
        @click="emit('edit', site)"
      >
        {{ t('Edit') }}
      </VBtn>
    </template>

    <template v-if="site">
      <WpJournalNav
        v-model="activeSection"
        :sections="sections"
        is-dialog
      />

      <!-- Overview Section -->
      <div
        v-show="activeSection === 'overview'"
        class="pa-4"
      >
        <VRow dense>
          <!-- Code -->
          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-barcode"
              :label="t('inventory.sites.columns.code')"
              :value="site.code"
              mono
            />
          </VCol>

          <!-- Type -->
          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              :icon="resolveSiteTypeIcon(site.site_type)"
              :label="t('inventory.sites.columns.type')"
            >
              <WpStatusChip
                :status="site.site_type"
                :color="resolveSiteTypeVariant(site.site_type)"
                :label="t(`inventory.sites.types.${site.site_type ?? 'fixed'}`)"
                size="small"
              />
            </WpInfoItem>
          </VCol>

          <!-- Status -->
          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-toggle-left"
              :label="t('inventory.sites.columns.status')"
            >
              <WpStatusChip
                :status="site.status"
                :color="resolveStatusVariant(site.status)"
                :label="t(`inventory.sites.statuses.${site.status}`)"
                size="small"
              />
            </WpInfoItem>
          </VCol>

          <!-- Manager -->
          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-user"
              :label="t('inventory.sites.columns.manager')"
              :value="site.manager?.name || '—'"
            />
          </VCol>

          <!-- Contact info -->
          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-phone"
              :label="t('inventory.sites.columns.phone')"
              :value="site.phone"
              hide-when-empty
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-mail"
              :label="t('inventory.sites.columns.email')"
              :value="site.email"
              hide-when-empty
            />
          </VCol>

          <!-- Address -->
          <VCol cols="12">
            <WpInfoItem
              icon="tabler-map-pin"
              :label="t('inventory.sites.columns.address')"
              :value="site.address"
              hide-when-empty
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-building-community"
              :label="t('inventory.sites.columns.city')"
              :value="site.city"
              hide-when-empty
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-world"
              :label="t('inventory.sites.columns.country')"
              :value="site.country"
              hide-when-empty
            />
          </VCol>

          <!-- Counts -->
          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-layers"
              :label="t('inventory.sites.columns.zones')"
              :value="site.zones_count ?? site.zones?.length ?? 0"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-map-pin"
              :label="t('inventory.sites.columns.locations')"
              :value="site.locations_count ?? 0"
            />
          </VCol>

          <!-- Notes -->
          <VCol
            v-if="site.notes"
            cols="12"
          >
            <WpInfoItem
              icon="tabler-notes"
              :label="t('inventory.sites.columns.notes')"
              :value="site.notes"
            />
          </VCol>

          <!-- Created & Updated -->
          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-clock"
              :label="t('Created At')"
              :value="formatDate(site.created_at)"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <WpInfoItem
              icon="tabler-clock-edit"
              :label="t('Updated At')"
              :value="formatDate(site.updated_at)"
            />
          </VCol>
        </VRow>
      </div>

      <!-- Hierarchy Section (Zones & Locations) -->
      <div
        v-show="activeSection === 'hierarchy'"
        class="pa-4"
      >
        <template v-if="site.zones && site.zones.length > 0">
          <VExpansionPanels variant="accordion">
            <VExpansionPanel
              v-for="zone in site.zones"
              :key="zone.id"
            >
              <VExpansionPanelTitle>
                <div class="d-flex align-center gap-2 flex-grow-1">
                  <VIcon
                    icon="tabler-layers"
                    size="18"
                    color="primary"
                  />
                  <span class="font-weight-medium font-monospace">{{ zone.code }}</span>
                  <span>—</span>
                  <span>{{ localizedValue(zone.name) || zone.code }}</span>
                  <VChip
                    size="x-small"
                    color="info"
                    variant="tonal"
                    class="ms-2"
                  >
                    {{ zone.zone_type }}
                  </VChip>
                  <VChip
                    size="x-small"
                    color="secondary"
                    variant="tonal"
                    class="ms-auto me-2"
                  >
                    {{ zone.locations?.length ?? zone.locations_count ?? 0 }} {{ t('inventory.sites.locations') }}
                  </VChip>
                </div>
              </VExpansionPanelTitle>

              <VExpansionPanelText>
                <template v-if="zone.locations && zone.locations.length > 0">
                  <VTable density="compact">
                    <thead>
                      <tr>
                        <th>{{ t('inventory.stock_items.columns.barcode') }}</th>
                        <th>{{ t('inventory.stock_items.columns.full_path') }}</th>
                        <th>{{ t('inventory.sites.columns.type') }}</th>
                        <th>{{ t('Status') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="loc in zone.locations"
                        :key="loc.id"
                      >
                        <td class="font-monospace text-caption">
                          {{ loc.barcode || '—' }}
                        </td>
                        <td class="font-weight-medium">
                          {{ loc.full_path || '—' }}
                        </td>
                        <td>
                          <VChip
                            size="x-small"
                            variant="tonal"
                          >
                            {{ loc.location_type }}
                          </VChip>
                        </td>
                        <td>
                          <VChip
                            size="x-small"
                            :color="loc.is_active ? 'success' : 'secondary'"
                          >
                            {{ loc.is_active ? t('Active') : t('Inactive') }}
                          </VChip>
                        </td>
                      </tr>
                    </tbody>
                  </VTable>
                </template>
                <template v-else>
                  <p class="text-caption text-medium-emphasis pa-2">
                    {{ t('inventory.sites.no_locations') }}
                  </p>
                </template>
              </VExpansionPanelText>
            </VExpansionPanel>
          </VExpansionPanels>
        </template>
        <template v-else>
          <WpEmptyState
            icon="tabler-layers"
            :title="t('inventory.sites.no_zones')"
            size="small"
          />
        </template>
      </div>

      <!-- Vehicle Section (Mobile Sites) -->
      <div
        v-if="isMobileSite"
        v-show="activeSection === 'vehicle'"
        class="pa-4"
      >
        <template v-if="site.vehicle">
          <VRow dense>
            <VCol
              cols="12"
              sm="6"
            >
              <WpInfoItem
                icon="tabler-car"
                :label="t('inventory.sites.columns.vehicle')"
                :value="site.vehicle.plate_number || site.vehicle.label || site.vehicle.id"
                mono
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <WpInfoItem
                icon="tabler-id"
                :label="t('Label')"
                :value="site.vehicle.label || '—'"
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <WpInfoItem
                icon="tabler-truck"
                :label="t('Type')"
                :value="site.vehicle.type || '—'"
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <WpInfoItem
                icon="tabler-activity"
                :label="t('inventory.sites.columns.mobile_status')"
                :value="site.mobile_status || '—'"
              />
            </VCol>

            <VCol cols="12">
              <WpInfoItem
                icon="tabler-map-pin"
                :label="t('inventory.sites.columns.current_location')"
                :value="site.current_location || '—'"
              />
            </VCol>

            <VCol
              v-if="site.location_notes"
              cols="12"
            >
              <WpInfoItem
                icon="tabler-notes"
                :label="t('inventory.sites.columns.location_notes')"
                :value="site.location_notes"
              />
            </VCol>
          </VRow>
        </template>
        <template v-else>
          <WpEmptyState
            icon="tabler-truck-off"
            :title="t('inventory.sites.no_vehicle')"
            size="small"
          />
        </template>
      </div>
    </template>
  </WpDetailDialog>
</template>
