<script setup>
/* eslint-disable camelcase */
import { WpJournalNav } from '@/components/shared'
import WpDetailDialog from '@/components/shared/dialogs/WpDetailDialog.vue'
import WpMediaViewerDialog from '@/components/shared/media/WpMediaViewerDialog.vue'
import {
  brandLabel,
  resolveComplianceVariant,
  resolveLifecycleVariant,
  useEquipmentHelpers,
} from '@/composables/client-assets/equipment/useEquipmentHelpers'
import { useEquipmentViewModal } from '@/composables/client-assets/equipment/useEquipmentViewModal'
import { localizedValue } from '@/composables/useLocaleField'
import ViewClientDialog from '@/views/business-partners/clients/list/ViewClientDialog.vue'
import EquipmentReplaceQrDialog from '@/views/client-assets/equipment/EquipmentReplaceQrDialog.vue'
import EquipmentTransferDialog from '@/views/client-assets/equipment/EquipmentTransferDialog.vue'
import EquipmentHistorySection from '@/views/client-assets/equipment/sections/EquipmentHistorySection.vue'
import EquipmentMediaGallery from '@/views/client-assets/equipment/sections/EquipmentMediaGallery.vue'
import EquipmentViewKpiRibbon from '@/views/client-assets/equipment/view/EquipmentViewKpiRibbon.vue'
import EquipmentViewLifecycleTab from '@/views/client-assets/equipment/view/EquipmentViewLifecycleTab.vue'
import EquipmentViewOverviewTab from '@/views/client-assets/equipment/view/EquipmentViewOverviewTab.vue'
import EquipmentViewSummaryPanel from '@/views/client-assets/equipment/view/EquipmentViewSummaryPanel.vue'
import { computed } from 'vue'
import { useDisplay } from 'vuetify'

const props = defineProps({
  isDialogVisible: { type: Boolean, default: undefined },
  modelValue: { type: Boolean, default: false },
  equipmentId: { type: [String, Number], default: null },
  equipment: { type: Object, default: null },
})

const emit = defineEmits(['update:isDialogVisible', 'update:modelValue', 'saved', 'updated', 'transferred'])

const { smAndDown } = useDisplay()
const { lifecycleLabel, complianceLabel } = useEquipmentHelpers()

const {
  isOpen,
  resolvedId,
  eq,
  loading,
  error,
  activeTab,
  loadEquipment,
  mainPhotoUrl,
  activeQrCode,
  nextInspectionMeta,
  timelineFormProxy,
  calculatedExpiryDate,
  lifeProgressPercentage,
  lifeRemainingLabel,
  timelineGroupedByYear,
  hardwareProfileItems,
  optionBadges,
  specEntries,
  normalizedCerts,
  dialogTabs,
  isTransferDialogOpen,
  isReplaceQrDialogOpen,
  isMediaViewerOpen,
  mediaViewerItems,
  mediaViewerInitialIndex,
  isClientViewDialogOpen,
  selectedClientForView,
  loadingClientView,
  copiedQr,
  goToEditPage,
  copyQrCode,
  handleDownloadQr,
  openPhotoViewer,
  handleViewClient,
  handleSubDialogSaved,
  equipmentTitle,
} = useEquipmentViewModal(props, emit)

const navSections = computed(() =>
  dialogTabs.value.map(t => ({
    id: t.value,
    title: t.title,
    icon: t.icon,
    badge: t.badge,
  })),
)
</script>

<template>
  <WpDetailDialog
    v-model="isOpen"
    size="xlarge"
    :title="eq ? equipmentTitle(eq) : $t('Equipment Details')"
    :loading="loading && !eq"
    :error="error && !eq"
    scrollable
    @retry="loadEquipment"
    @close="isOpen = false"
  >
    <!-- Custom Header Avatar Slot -->
    <template #avatar>
      <div class="position-relative flex-shrink-0">
        <VAvatar
          rounded="lg"
          :size="smAndDown ? 44 : 52"
          color="primary"
          variant="tonal"
          class="border elevation-1"
          :class="{ 'cursor-pointer': Boolean(mainPhotoUrl) }"
          @click="mainPhotoUrl && openPhotoViewer()"
        >
          <VImg
            v-if="mainPhotoUrl"
            :src="mainPhotoUrl"
            cover
          />
          <VIcon
            v-else
            icon="tabler-fire-extinguisher"
            size="26"
          />
        </VAvatar>
        <span
          v-if="eq?.lifecycle_status"
          class="status-dot"
          :class="`bg-${resolveLifecycleVariant(eq.lifecycle_status)}`"
        />
      </div>
    </template>

    <!-- Custom Subtitle Slot with Status Pills & Context -->
    <template #subtitle>
      <div class="d-flex align-center gap-1.5 flex-wrap">
        <VChip
          v-if="eq?.equipment_code"
          size="x-small"
          color="primary"
          variant="tonal"
          class="font-weight-bold font-mono"
        >
          {{ eq.equipment_code }}
        </VChip>
        <VChip
          v-if="eq?.lifecycle_status"
          size="x-small"
          :color="resolveLifecycleVariant(eq.lifecycle_status)"
          variant="tonal"
          class="font-weight-semibold text-capitalize"
        >
          {{ lifecycleLabel(eq.lifecycle_status) }}
        </VChip>
        <VChip
          v-if="eq?.compliance_status"
          size="x-small"
          :color="resolveComplianceVariant(eq.compliance_status)"
          variant="tonal"
          class="font-weight-semibold text-capitalize"
        >
          {{ complianceLabel(eq.compliance_status) }}
        </VChip>
        <span
          v-if="eq"
          class="text-caption text-medium-emphasis ms-1 text-truncate"
        >
          <span v-if="brandLabel(eq)">{{ brandLabel(eq) }} • </span>
          <span>{{ localizedValue(eq.client?.legal_name) || $t('Client') }}</span>
          <span v-if="eq.site"> • {{ localizedValue(eq.site.name) }}</span>
        </span>
      </div>
    </template>

    <!-- Custom Header Actions Slot -->
    <template #actions>
      <div class="d-flex align-center gap-2">
        <VBtn
          v-if="eq && !smAndDown"
          size="small"
          variant="tonal"
          color="info"
          prepend-icon="tabler-arrows-exchange"
          @click="isTransferDialogOpen = true"
        >
          {{ $t('Transfer') }}
        </VBtn>
        <VBtn
          v-if="eq && !smAndDown"
          size="small"
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-qrcode"
          @click="handleDownloadQr"
        >
          {{ $t('Download QR') }}
        </VBtn>
        <VBtn
          v-if="resolvedId"
          size="small"
          color="primary"
          prepend-icon="tabler-edit"
          @click="goToEditPage"
        >
          {{ $t('Edit Asset') }}
        </VBtn>
      </div>
    </template>

    <!-- Main Content Slot -->
    <div
      v-if="eq"
      class="equipment-view-content pa-4"
    >
      <!-- Key Attributes Ribbon -->
      <EquipmentViewKpiRibbon
        :equipment="eq"
        :next-inspection-meta="nextInspectionMeta"
      />

      <div class="dialog-journal-layout">
        <WpJournalNav
          v-model="activeTab"
          :sections="navSections"
          is-dialog
          id-prefix="dialog-equipment-tab-"
          container=".wp-detail-dialog__scroll"
        />

        <div class="dialog-journal-content flex-grow-1 min-w-0">
          <VRow>
            <!-- Left Column: Visual Showcase, QR Tag, Assignment & Health -->
            <VCol
              cols="12"
              lg="4"
            >
              <EquipmentViewSummaryPanel
                :equipment="eq"
                :main-photo-url="mainPhotoUrl"
                :active-qr-code="activeQrCode"
                :copied-qr="copiedQr"
                :timeline-form-proxy="timelineFormProxy"
                :calculated-expiry-date="calculatedExpiryDate"
                :life-progress-percentage="lifeProgressPercentage"
                :life-remaining-label="lifeRemainingLabel"
                @open-photo-viewer="openPhotoViewer"
                @copy-qr-code="copyQrCode"
                @download-qr="handleDownloadQr"
                @open-replace-qr="isReplaceQrDialogOpen = true"
                @view-client="handleViewClient"
              />
            </VCol>

            <!-- Right Column: Active Tab Content -->
            <VCol
              cols="12"
              lg="8"
            >
              <div v-show="activeTab === 'overview'">
                <EquipmentViewOverviewTab
                  :equipment="eq"
                  :hardware-profile-items="hardwareProfileItems"
                  :option-badges="optionBadges"
                  :spec-entries="specEntries"
                  :normalized-certs="normalizedCerts"
                />
              </div>

              <div v-show="activeTab === 'lifecycle'">
                <EquipmentViewLifecycleTab
                  :timeline-form-proxy="timelineFormProxy"
                  :calculated-expiry-date="calculatedExpiryDate"
                  :timeline-grouped-by-year="timelineGroupedByYear"
                />
              </div>

              <div v-show="activeTab === 'media'">
                <EquipmentMediaGallery
                  :equipment-id="resolvedId"
                  :photos="eq.photos || []"
                  :documents="eq.documents || []"
                  :certificates="eq.certificates || []"
                  read-only
                  @refresh="loadEquipment"
                />
              </div>

              <div v-show="activeTab === 'history'">
                <EquipmentHistorySection
                  :status-histories="eq.status_histories || []"
                  :location-histories="eq.location_histories || []"
                  :transfer-histories="eq.transfers || eq.transfer_histories || []"
                  :qr-histories="eq.qr_codes || eq.qr_histories || []"
                />
              </div>
            </VCol>
          </VRow>
        </div>
      </div>
    </div>

    <!-- Dialog Footer Slot -->
    <template #footer>
      <div class="d-flex align-center justify-space-between flex-wrap gap-2 w-100">
        <div class="text-caption text-disabled">
          <span v-if="eq?.updated_at">
            {{ $t('Last updated') }}: {{ new Date(eq.updated_at).toLocaleString() }}
          </span>
        </div>

        <div class="d-flex align-center gap-2 flex-wrap">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isOpen = false"
          >
            {{ $t('Close') }}
          </VBtn>
          <VBtn
            v-if="eq"
            variant="tonal"
            color="info"
            prepend-icon="tabler-arrows-exchange"
            @click="isTransferDialogOpen = true"
          >
            {{ $t('Transfer Asset') }}
          </VBtn>
          <VBtn
            v-if="resolvedId"
            color="primary"
            variant="elevated"
            prepend-icon="tabler-edit"
            @click="goToEditPage"
          >
            {{ $t('Edit Equipment Record') }}
          </VBtn>
        </div>
      </div>
    </template>
  </WpDetailDialog>

  <!-- Contextual Sub-Dialogs -->
  <EquipmentTransferDialog
    v-model="isTransferDialogOpen"
    :equipment="eq"
    @saved="handleSubDialogSaved"
  />

  <EquipmentReplaceQrDialog
    v-model="isReplaceQrDialogOpen"
    :equipment="eq"
    @saved="handleSubDialogSaved"
  />

  <ViewClientDialog
    v-model:is-dialog-visible="isClientViewDialogOpen"
    :client-id="eq?.client_id || eq?.client?.id"
    :client="selectedClientForView"
    :loading="loadingClientView"
  />

  <WpMediaViewerDialog
    v-model="isMediaViewerOpen"
    :items="mediaViewerItems"
    :initial-index="mediaViewerInitialIndex"
    :title="eq ? equipmentTitle(eq) : $t('Equipment Media')"
  />
</template>

<style lang="scss" scoped>
.dialog-journal-layout {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
  gap: 1.25rem;

  @media (max-width: 959px) {
    flex-direction: column;
  }
}

.status-dot {
  position: absolute;
  inset-block-end: -2px;
  inset-inline-end: -2px;
  inline-size: 12px;
  block-size: 12px;
  border-radius: 50%;
  border: 2px solid rgb(var(--v-theme-surface));
}

.font-mono {
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
}
</style>
