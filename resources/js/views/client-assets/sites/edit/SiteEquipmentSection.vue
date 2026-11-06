<script setup>
import { WpSectionCard, WpSimpleTable } from '@/components/shared'
import { resolveSiteStatusVariant } from '@/composables/client-assets/sites/useSiteConstants'
import {
  useSiteEquipment,
} from '@/composables/client-assets/sites/useSiteEquipment'
import { localizedValue } from '@/composables/useLocaleField'
import SiteEquipmentAnalyticsPanel from '@/views/client-assets/sites/edit/SiteEquipmentAnalyticsPanel.vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

const props = defineProps({
  siteId: { type: [Number, String], default: null },
  site: { type: Object, default: null },
  siteEquipment: { type: Array, default: () => [] },
  siteAreasFlat: { type: Array, default: () => [] },
  selectedLocationAreaId: { type: [Number, String], default: null },
})

defineEmits(['addEquipment', 'viewEquipment', 'editEquipment', 'openZoom'])

const router = useRouter()
const { t } = useI18n()
const { smAndDown } = useDisplay()
const { equipmentTableColumns, equipmentTitle, equipmentStatusLabel } = useSiteEquipment()

const navigateToInventoryExplorer = () => {
  const id = props.siteId || props.site?.id
  if (id) {
    router.push({
      name: 'client-assets-sites-inventory-explorer-id',
      params: { id },
    })
  }
}
</script>

<template>
  <section
    id="section-equipment"
    class="journal-section mb-6"
  >
    <WpSectionCard
      :title="`${$t('Installed Equipment Assets')} (${siteEquipment.length})`"
      :subtitle="$t('Asset inventory deployed at this site location, lifecycle status, and compliance tracking')"
      icon="tabler-fire-extinguisher"
      icon-color="warning"
    >
      <template #actions>
        <div class="d-flex align-center gap-2 flex-wrap">
          <!-- Button to Open Site Inventory Explorer -->
          <VBtn
            v-if="!smAndDown"
            size="small"
            variant="tonal"
            color="primary"
            prepend-icon="tabler-packages"
            @click="navigateToInventoryExplorer"
          >
            {{ $t('Inventory Explorer') }}
          </VBtn>
          <IconBtn
            v-else
            size="small"
            variant="tonal"
            color="primary"
            :title="$t('Inventory Explorer')"
            :aria-label="$t('Inventory Explorer')"
            @click="navigateToInventoryExplorer"
          >
            <VIcon icon="tabler-packages" />
          </IconBtn>

          <VBtn
            v-if="!smAndDown"
            size="small"
            color="warning"
            prepend-icon="tabler-plus"
            @click="$emit('addEquipment', selectedLocationAreaId)"
          >
            {{ $t('Add Equipment') }}
          </VBtn>

          <!-- Standard Zoom Dialog Card Button (Fullscreen/Big size) -->
          <IconBtn
            size="small"
            variant="tonal"
            color="secondary"
            :title="$t('Expand Fullscreen Zoom')"
            :aria-label="$t('Expand Fullscreen Zoom')"
            @click="$emit('openZoom')"
          >
            <VIcon icon="tabler-arrows-maximize" />
          </IconBtn>
        </div>
      </template>

      <!-- Equipment Analytics Panel -->
      <SiteEquipmentAnalyticsPanel
        v-if="siteEquipment.length"
        :equipment="siteEquipment"
        :areas-flat="siteAreasFlat"
        class="mb-4"
        @open-inventory="navigateToInventoryExplorer"
      />

      <!-- MOBILE-FIRST VIEW: Responsive cards for small screens -->
      <div
        v-if="smAndDown && siteEquipment.length"
        class="d-flex flex-column gap-3 equipment-mobile-list"
      >
        <VCard
          v-for="item in siteEquipment"
          :key="`mob-eq-${item.id}`"
          variant="outlined"
          rounded="lg"
          class="pa-3 cursor-pointer equipment-mobile-card"
          @click="$emit('viewEquipment', item.id)"
        >
          <div class="equipment-mobile-card__main">
            <div class="equipment-mobile-card__identity">
              <VAvatar
                size="36"
                color="warning"
                variant="tonal"
                rounded
                class="equipment-mobile-card__avatar"
              >
                <VIcon
                  icon="tabler-fire-extinguisher"
                  size="20"
                />
              </VAvatar>
              <div class="equipment-mobile-card__text">
                <span class="text-body-2 font-weight-bold d-block text-truncate">{{ equipmentTitle(item) }}</span>
                <span class="text-caption text-disabled font-monospace">{{ item.equipment_code || item.serial_number || '—' }}</span>
              </div>
            </div>
            <VChip
              size="x-small"
              :color="resolveSiteStatusVariant(item.lifecycle_status || item.status)"
              variant="tonal"
              class="text-capitalize equipment-mobile-card__status-chip"
            >
              {{ equipmentStatusLabel(item.lifecycle_status || item.status) }}
            </VChip>
          </div>

          <div class="equipment-mobile-card__footer pt-2 border-t gap-2">
            <VChip
              size="x-small"
              variant="tonal"
              color="info"
              prepend-icon="tabler-map-pin"
              class="equipment-mobile-card__location-chip"
            >
              {{ localizedValue(item.area?.name) || $t('Site Root') }}
            </VChip>

            <div
              class="d-flex align-center gap-1"
              @click.stop
            >
              <IconBtn
                size="small"
                variant="text"
                :title="$t('View Equipment')"
                @click="$emit('viewEquipment', item.id)"
              >
                <VIcon
                  icon="tabler-eye"
                  size="18"
                />
              </IconBtn>
              <IconBtn
                size="small"
                variant="text"
                color="primary"
                :title="$t('Edit Equipment')"
                @click="$emit('editEquipment', item.id)"
              >
                <VIcon
                  icon="tabler-edit"
                  size="18"
                />
              </IconBtn>
            </div>
          </div>
        </VCard>
      </div>

      <!-- DESKTOP VIEW: Table for medium & larger screens -->
      <WpSimpleTable
        v-else-if="siteEquipment.length"
        :columns="equipmentTableColumns"
        :items="siteEquipment"
        item-key="id"
      >
        <template #cell.name="{ item }">
          <div class="d-flex align-center gap-2">
            <VAvatar
              size="32"
              color="warning"
              variant="tonal"
              rounded
            >
              <VIcon
                icon="tabler-fire-extinguisher"
                size="18"
              />
            </VAvatar>
            <div>
              <span class="text-body-2 font-weight-bold d-block">{{ equipmentTitle(item) }}</span>
              <span class="text-caption text-disabled font-monospace">{{ item.equipment_code || item.serial_number || item.barcode || '—' }}</span>
            </div>
          </div>
        </template>

        <template #cell.type="{ item }">
          <span class="text-body-2">{{ localizedValue(item.equipment_class?.name) || item.equipment_type || item.model || '—' }}</span>
        </template>

        <template #cell.area="{ item }">
          <VChip
            size="x-small"
            variant="tonal"
            color="info"
            prepend-icon="tabler-map-pin"
          >
            {{ localizedValue(item.area?.name) || $t('Site Root') }}
          </VChip>
        </template>

        <template #cell.status="{ item }">
          <VChip
            size="x-small"
            :color="resolveSiteStatusVariant(item.lifecycle_status || item.status)"
            variant="tonal"
            class="text-capitalize"
          >
            {{ equipmentStatusLabel(item.lifecycle_status || item.status) }}
          </VChip>
        </template>

        <template #cell.actions="{ item }">
          <div class="d-flex align-center justify-end gap-1">
            <IconBtn
              size="small"
              variant="text"
              :title="$t('View Equipment')"
              @click="$emit('viewEquipment', item.id)"
            >
              <VIcon
                icon="tabler-eye"
                size="18"
              />
            </IconBtn>
            <IconBtn
              size="small"
              variant="text"
              color="primary"
              :title="$t('Edit Equipment')"
              @click="$emit('editEquipment', item.id)"
            >
              <VIcon
                icon="tabler-edit"
                size="18"
              />
            </IconBtn>
          </div>
        </template>
      </WpSimpleTable>

      <!-- Empty State when site has no equipment -->
      <div
        v-else
        class="py-8 text-center text-medium-emphasis border rounded-lg"
      >
        <VIcon
          icon="tabler-fire-extinguisher"
          size="38"
          class="mb-2 opacity-50"
        />
        <p class="text-body-2 mb-2">
          {{ $t('No equipment assets deployed at this site yet.') }}
        </p>
        <VBtn
          size="small"
          color="warning"
          variant="tonal"
          prepend-icon="tabler-plus"
          @click="$emit('addEquipment', selectedLocationAreaId)"
        >
          {{ $t('Add Equipment Asset') }}
        </VBtn>
      </div>
    </WpSectionCard>
  </section>
</template>

<style scoped lang="scss">
.equipment-mobile-card {
  overflow: hidden;

  &__main {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
    margin-block-end: 0.5rem;
  }

  &__identity {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-inline-size: 0;
    flex: 1;
  }

  &__text {
    min-inline-size: 0;
    flex: 1;
  }

  &__status-chip {
    flex-shrink: 0;
  }

  &__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  &__location-chip {
    flex: 1;
    min-inline-size: 0;
  }
}

/* Small mobile responsive equipment list */
@media (max-width: 430px) {
  .equipment-mobile-list {
    gap: 0.625rem !important;
  }

  .equipment-mobile-card {
    padding: 0.625rem !important;

    &__main {
      flex-direction: column;
      align-items: center;
      gap: 0.5rem;
      margin-block-end: 0.75rem;
    }

    &__identity {
      flex-direction: column;
      align-items: center;
      text-align: center;
      inline-size: 100%;
      gap: 0.375rem;
    }

    &__text {
      inline-size: 100%;

      .text-body-2,
      .text-caption {
        text-align: center;
      }
    }

    &__status-chip {
      inline-size: 100%;
      justify-content: center;
    }

    &__footer {
      flex-direction: column;
      align-items: stretch;
      gap: 0.5rem;
    }

    &__location-chip {
      inline-size: 100%;
      justify-content: center;
    }

    .v-avatar {
      block-size: 48px !important;
      inline-size: 48px !important;

      .v-icon {
        font-size: 24px !important;
      }
    }

    .text-body-2 {
      font-size: 0.8125rem !important;
    }

    .text-caption {
      font-size: 0.6875rem !important;
    }

    :deep(.v-chip) {
      font-size: 0.65rem !important;
      padding-inline: 0.5rem !important;
      block-size: auto !important;
    }

    .d-flex.align-center.gap-1 {
      inline-size: 100%;
      justify-content: center;
    }
  }
}

@media (max-width: 414px) {
  .equipment-mobile-card {
    .v-avatar {
      block-size: 46px !important;
      inline-size: 46px !important;

      .v-icon {
        font-size: 23px !important;
      }
    }

    .text-body-2 {
      font-size: 0.79rem !important;
    }

    .text-caption {
      font-size: 0.675rem !important;
    }
  }
}

@media (max-width: 393px) {
  .equipment-mobile-card {
    .v-avatar {
      block-size: 45px !important;
      inline-size: 45px !important;

      .v-icon {
        font-size: 22.5px !important;
      }
    }

    .text-body-2 {
      font-size: 0.77rem !important;
    }
  }
}

@media (max-width: 390px) {
  .equipment-mobile-card {
    .v-avatar {
      block-size: 44px !important;
      inline-size: 44px !important;

      .v-icon {
        font-size: 22px !important;
      }
    }

    .text-body-2 {
      font-size: 0.76rem !important;
    }

    .text-caption {
      font-size: 0.67rem !important;
    }
  }
}

@media (max-width: 375px) {
  .equipment-mobile-card {
    padding: 0.5rem !important;

    &__main {
      gap: 0.375rem;
      margin-block-end: 0.625rem;
    }

    &__identity {
      gap: 0.25rem;
    }

    &__footer {
      gap: 0.375rem;
    }

    .v-avatar {
      block-size: 44px !important;
      inline-size: 44px !important;

      .v-icon {
        font-size: 22px !important;
      }
    }

    .text-body-2 {
      font-size: 0.75rem !important;
    }

    .text-caption {
      font-size: 0.65rem !important;
    }

    :deep(.v-chip) {
      font-size: 0.625rem !important;
      padding-inline: 0.375rem !important;
    }
  }
}

@media (max-width: 360px) {
  .equipment-mobile-card {
    padding: 0.4375rem !important;

    &__main {
      gap: 0.3125rem;
      margin-block-end: 0.5625rem;
    }

    &__identity {
      gap: 0.1875rem;
    }

    .v-avatar {
      block-size: 42px !important;
      inline-size: 42px !important;

      .v-icon {
        font-size: 21px !important;
      }
    }

    .text-body-2 {
      font-size: 0.725rem !important;
    }

    .text-caption {
      font-size: 0.6375rem !important;
    }

    :deep(.v-chip) {
      font-size: 0.6125rem !important;
    }
  }
}

@media (max-width: 340px) {
  .equipment-mobile-card {
    padding: 0.4375rem !important;

    .v-avatar {
      block-size: 41px !important;
      inline-size: 41px !important;

      .v-icon {
        font-size: 20.5px !important;
      }
    }

    .text-body-2 {
      font-size: 0.7125rem !important;
    }

    .text-caption {
      font-size: 0.6325rem !important;
    }
  }
}

@media (max-width: 320px) {
  .equipment-mobile-card {
    padding: 0.375rem !important;

    &__main {
      gap: 0.25rem;
      margin-block-end: 0.5rem;
    }

    .v-avatar {
      block-size: 40px !important;
      inline-size: 40px !important;

      .v-icon {
        font-size: 20px !important;
      }
    }

    .text-body-2 {
      font-size: 0.7rem !important;
    }

    .text-caption {
      font-size: 0.625rem !important;
    }

    :deep(.v-chip) {
      font-size: 0.6rem !important;
      padding-inline: 0.25rem !important;
    }
  }
}

@media (max-width: 310px) {
  .equipment-mobile-card {
    padding: 0.3125rem !important;

    &__main {
      gap: 0.1875rem;
      margin-block-end: 0.4375rem;
    }

    .v-avatar {
      block-size: 38px !important;
      inline-size: 38px !important;

      .v-icon {
        font-size: 19px !important;
      }
    }

    .text-body-2 {
      font-size: 0.675rem !important;
    }

    .text-caption {
      font-size: 0.6rem !important;
    }

    :deep(.v-chip) {
      font-size: 0.575rem !important;
      padding-inline: 0.1875rem !important;
    }
  }
}
</style>
