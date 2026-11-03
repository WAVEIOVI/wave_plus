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
        class="d-flex flex-column gap-3"
      >
        <VCard
          v-for="item in siteEquipment"
          :key="`mob-eq-${item.id}`"
          variant="outlined"
          rounded="lg"
          class="pa-3 cursor-pointer"
          @click="$emit('viewEquipment', item.id)"
        >
          <div class="d-flex align-start justify-space-between gap-2 mb-2">
            <div class="d-flex align-center gap-2 min-w-0">
              <VAvatar
                size="36"
                color="warning"
                variant="tonal"
                rounded
              >
                <VIcon
                  icon="tabler-fire-extinguisher"
                  size="20"
                />
              </VAvatar>
              <div class="min-w-0">
                <span class="text-body-2 font-weight-bold d-block text-truncate">{{ equipmentTitle(item) }}</span>
                <span class="text-caption text-disabled font-monospace">{{ item.equipment_code || item.serial_number || '—' }}</span>
              </div>
            </div>
            <VChip
              size="x-small"
              :color="resolveSiteStatusVariant(item.lifecycle_status || item.status)"
              variant="tonal"
              class="text-capitalize shrink-0"
            >
              {{ equipmentStatusLabel(item.lifecycle_status || item.status) }}
            </VChip>
          </div>

          <div class="d-flex align-center justify-space-between pt-2 border-t gap-2 flex-wrap">
            <VChip
              size="x-small"
              variant="tonal"
              color="info"
              prepend-icon="tabler-map-pin"
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
