<script setup>
/* eslint-disable camelcase */
import { WpFormDialog } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import SiteAreaSelectModal from '@/views/client-assets/equipment/dialogs/SiteAreaSelectModal.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    default: undefined,
  },
  modelValue: {
    type: Boolean,
    default: false,
  },
  equipment: {
    type: Object,
    default: null,
  },
  equipmentId: {
    type: [String, Number],
    default: null,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'update:modelValue', 'transferred', 'saved', 'close'])

const store = useClientAssetStore()
const snackbarStore = useSnackbarStore()
const { t } = useI18n()
const { smAndDown } = useDisplay()

const isOpen = computed({
  get: () => (props.isDialogVisible !== undefined ? props.isDialogVisible : props.modelValue),
  set: val => {
    emit('update:isDialogVisible', val)
    emit('update:modelValue', val)
    if (!val) emit('close')
  },
})

const fetchedEquipmentRecord = ref(null)
const resolvedEquipment = computed(() => props.equipment || fetchedEquipmentRecord.value)

const isSubmitting = ref(false)
const errorMessage = ref('')
const clientSites = ref([])
const sitesLoading = ref(false)
const isSiteAreaModalVisible = ref(false)
const selectedSite = ref(null)
const selectedArea = ref(null)

const form = ref({
  to_site_id: null,
  to_area_id: null,
  movement_type: 'transfer',
  reason: '',
  condition_at_transfer: 'good',
  notes: '',
})

const movementTypes = computed(() => [
  { title: t('Relocation / Site Transfer'), value: 'transfer', icon: 'tabler-truck', color: 'primary' },
  { title: t('Internal Area Re-assignment'), value: 'relocation', icon: 'tabler-arrows-move', color: 'info' },
  { title: t('Removal to Storage / Reserve'), value: 'removal_to_storage', icon: 'tabler-archive', color: 'warning' },
  { title: t('Decommissioning / Removal'), value: 'decommission', icon: 'tabler-trash-x', color: 'error' },
])

const conditionOptions = computed(() => [
  { title: t('Excellent'), value: 'excellent', icon: 'tabler-star', color: 'success' },
  { title: t('Good'), value: 'good', icon: 'tabler-thumb-up', color: 'success' },
  { title: t('Fair'), value: 'fair', icon: 'tabler-minus', color: 'warning' },
  { title: t('Poor'), value: 'poor', icon: 'tabler-thumb-down', color: 'error' },
  { title: t('Critical'), value: 'critical', icon: 'tabler-alert-triangle', color: 'error' },
])

const selectedMovementType = computed(() =>
  movementTypes.value.find(m => m.value === form.value.movement_type) || movementTypes.value[0],
)

const selectedCondition = computed(() =>
  conditionOptions.value.find(c => c.value === form.value.condition_at_transfer) || conditionOptions.value[1],
)

const isLocationChanged = computed(() => {
  if (!resolvedEquipment.value) return false

  return form.value.to_site_id !== resolvedEquipment.value.site_id
    || form.value.to_area_id !== resolvedEquipment.value.area_id
})

const loadClientSites = async clientId => {
  if (!clientId) return
  sitesLoading.value = true
  try {
    const res = await store.fetchSites({ clientId, itemsPerPage: -1 })

    clientSites.value = res.data || []
  } catch (err) {
    console.error('Error fetching client sites:', err)
  } finally {
    sitesLoading.value = false
  }
}

watch(
  isOpen,
  async val => {
    if (!val) return
    errorMessage.value = ''
    let eq = props.equipment
    if (!eq && props.equipmentId) {
      try {
        eq = await store.fetchEquipmentItem(props.equipmentId)
        fetchedEquipmentRecord.value = eq
      }
      catch (err) {
        console.error('Error loading equipment for transfer:', err)
      }
    }
    if (eq) {
      form.value = {
        to_site_id: eq.site_id || null,
        to_area_id: eq.area_id || null,
        movement_type: 'transfer',
        reason: '',
        condition_at_transfer: eq.condition_status || 'good',
        notes: '',
      }
      selectedSite.value = eq.site || null
      selectedArea.value = eq.area || null
      await loadClientSites(eq.client_id || eq.client?.id)
    }
  },
  { immediate: true },
)

const handleLocationConfirm = result => {
  form.value.to_site_id = result.site_id
  form.value.to_area_id = result.area_id
  selectedSite.value = result.site
  selectedArea.value = result.area
}

const handleClose = () => {
  isOpen.value = false
}

const handleSubmit = async () => {
  const eqId = resolvedEquipment.value?.id || props.equipmentId
  if (!eqId) return
  if (!form.value.reason?.trim()) {
    errorMessage.value = t('Please provide a reason for the transfer.')

    return
  }

  isSubmitting.value = true
  errorMessage.value = ''

  try {
    await store.transferEquipment(eqId, form.value)
    snackbarStore.showMessage(t('Equipment transferred successfully'), 'success')
    emit('transferred')
    emit('saved')
    handleClose()
  } catch (err) {
    console.error('Transfer error:', err)
    errorMessage.value = err?.data?.message || t('Failed to transfer equipment.')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <WpFormDialog
    v-model="isOpen"
    :title="t('Transfer Equipment')"
    icon="tabler-arrows-exchange"
    icon-color="primary"
    :max-width="760"
    :loading="isSubmitting"
    :submit-label="t('Confirm Transfer')"
    submit-icon="tabler-arrows-exchange"
    submit-color="primary"
    :submit-disabled="!form.reason?.trim()"
    @submit="handleSubmit"
    @cancel="handleClose"
    @close="handleClose"
  >
    <template #subtitle>
      <div class="d-flex align-center gap-2 flex-wrap mt-0.5">
        <VChip
          size="x-small"
          color="primary"
          label
          variant="tonal"
        >
          <VIcon
            icon="tabler-fire-extinguisher"
            size="11"
            start
          />
          {{ resolvedEquipment?.equipment_code }}
        </VChip>
        <span class="text-caption text-medium-emphasis">
          {{ t('Log asset relocation & maintain audit history') }}
        </span>
      </div>
    </template>

    <div class="transfer-dialog-content">
      <VAlert
        v-if="errorMessage"
        type="error"
        variant="tonal"
        closable
        class="ma-4 mb-2 rounded-lg"
        @click:close="errorMessage = ''"
      >
        {{ errorMessage }}
      </VAlert>

      <!-- ── FROM → TO Location Comparison ── -->
      <div class="location-comparison pa-4 pa-sm-5">
        <!-- FROM -->
        <div class="location-card location-card--from">
          <div class="location-card__label">
            <VIcon
              icon="tabler-map-pin"
              size="12"
              class="me-1"
            />
            {{ t('Current Location') }}
          </div>
          <div class="location-card__site">
            {{ localizedValue(resolvedEquipment?.site?.name) || resolvedEquipment?.site?.name || t('No Site (Storage / Unassigned)') }}
          </div>
          <div
            v-if="resolvedEquipment?.area"
            class="location-card__area"
          >
            <VIcon
              icon="tabler-hierarchy"
              size="11"
              class="me-1"
            />
            {{ resolvedEquipment.area.hierarchy_path || localizedValue(resolvedEquipment.area.name) || resolvedEquipment.area.name }}
          </div>
          <div class="location-card__client text-caption text-medium-emphasis mt-1">
            {{ localizedValue(resolvedEquipment?.client?.legal_name) || resolvedEquipment?.client?.legal_name }}
          </div>
        </div>

        <!-- Arrow -->
        <div class="location-arrow">
          <VIcon
            :icon="smAndDown ? 'tabler-arrow-down' : 'tabler-arrow-right'"
            size="20"
            color="primary"
          />
        </div>

        <!-- TO -->
        <div
          class="location-card location-card--to cursor-pointer"
          :class="{ 'location-card--selected': form.to_site_id, 'location-card--changed': isLocationChanged }"
          @click="isSiteAreaModalVisible = true"
        >
          <div class="location-card__label">
            <VIcon
              icon="tabler-map-pin-filled"
              size="12"
              class="me-1"
            />
            {{ t('Destination') }}
          </div>
          <template v-if="selectedSite || form.to_site_id">
            <div class="location-card__site">
              {{ localizedValue(selectedSite?.name) || selectedSite?.name || `Site #${form.to_site_id}` }}
            </div>
            <div
              v-if="selectedArea"
              class="location-card__area"
            >
              <VIcon
                icon="tabler-hierarchy"
                size="11"
                class="me-1"
              />
              {{ selectedArea.fullPath || selectedArea.hierarchy_path || localizedValue(selectedArea.name) || selectedArea.name }}
            </div>
            <div
              v-else
              class="location-card__area text-medium-emphasis font-italic"
            >
              {{ t('Site-wide (no specific area)') }}
            </div>
          </template>
          <template v-else>
            <div class="location-card__placeholder">
              <VIcon
                icon="tabler-plus"
                size="14"
                class="me-1"
              />
              {{ t('Select destination…') }}
            </div>
          </template>
          <VBtn
            size="x-small"
            variant="tonal"
            color="info"
            prepend-icon="tabler-map-pin"
            class="mt-2"
            @click.stop="isSiteAreaModalVisible = true"
          >
            {{ form.to_site_id ? t('Change') : t('Choose Location') }}
          </VBtn>
        </div>
      </div>

      <VDivider />

      <!-- ── Transfer Details ── -->
      <div class="pa-4 pa-sm-5">
        <!-- Movement Type -->
        <div class="mb-4">
          <div class="field-label mb-2">
            {{ t('Movement / Transfer Type') }} *
          </div>
          <div class="movement-type-grid">
            <button
              v-for="mt in movementTypes"
              :key="mt.value"
              class="movement-type-btn"
              :class="{ 'movement-type-btn--active': form.movement_type === mt.value, [`movement-type-btn--${mt.color}`]: form.movement_type === mt.value }"
              type="button"
              @click="form.movement_type = mt.value"
            >
              <VIcon
                :icon="mt.icon"
                :color="form.movement_type === mt.value ? mt.color : 'default'"
                size="18"
              />
              <span class="text-caption font-weight-medium">{{ mt.title }}</span>
            </button>
          </div>
        </div>

        <VRow dense>
          <!-- Condition at Transfer -->
          <VCol
            cols="12"
            sm="6"
          >
            <div class="field-label mb-2">
              {{ t('Condition at Transfer') }}
            </div>
            <AppSelect
              v-model="form.condition_at_transfer"
              :items="conditionOptions"
              variant="outlined"
              density="compact"
            >
              <template #prepend-inner>
                <VIcon
                  :icon="selectedCondition.icon"
                  :color="selectedCondition.color"
                  size="18"
                />
              </template>
            </AppSelect>
          </VCol>

          <!-- Reason -->
          <VCol
            cols="12"
            sm="6"
          >
            <div class="field-label mb-2">
              {{ t('Reason for Relocation') }} *
            </div>
            <AppTextField
              v-model="form.reason"
              :placeholder="t('e.g. Facility restructuring, HSE requirement')"
              variant="outlined"
              density="compact"
            />
          </VCol>

          <!-- Notes -->
          <VCol cols="12">
            <div class="field-label mb-2">
              {{ t('Transfer Notes / Logistics Details') }}
            </div>
            <AppTextarea
              v-model="form.notes"
              :placeholder="t('Transport vehicle, personnel involved, special handling notes...')"
              rows="2"
              variant="outlined"
              density="compact"
              auto-grow
            />
          </VCol>
        </VRow>
      </div>
    </div>
  </WpFormDialog>

  <SiteAreaSelectModal
    v-model:is-dialog-visible="isSiteAreaModalVisible"
    :sites="clientSites"
    :current-site-id="form.to_site_id"
    :current-area-id="form.to_area_id"
    @confirm="handleLocationConfirm"
  />
</template>

<style lang="scss" scoped>
.transfer-dialog-content {
  display: flex;
  flex-direction: column;
}

/* ── Location comparison ── */
.location-comparison {
  display: flex;
  align-items: center;
  background: rgba(var(--v-theme-surface), 0.6);
  gap: 1rem;

  @media (max-width: 599.98px) {
    flex-direction: column;
  }
}

.location-card {
  flex: 1;
  padding: 1rem;
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), 0.16);
  transition: all 0.2s ease;
  min-inline-size: 0;

  &--from {
    border-inline-start: 3px solid rgb(var(--v-theme-secondary));
  }

  &--to {
    border-inline-start: 3px solid rgb(var(--v-theme-info));

    &:hover {
      border-color: rgb(var(--v-theme-info));
      background: rgba(var(--v-theme-info), 0.03);
      box-shadow: 0 4px 14px rgba(var(--v-theme-info), 0.08);
    }
  }

  &--selected {
    border-color: rgba(var(--v-theme-info), 0.4);
    background: rgba(var(--v-theme-info), 0.03);
  }

  &--changed {
    border-inline-start-color: rgb(var(--v-theme-success)) !important;
  }

  &__label {
    display: flex;
    align-items: center;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: rgba(var(--v-theme-on-surface), 0.55);
    margin-block-end: 0.35rem;
  }

  &__site {
    font-size: 0.9375rem;
    font-weight: 600;
    color: rgb(var(--v-theme-on-surface));
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__area {
    font-size: 0.78rem;
    color: rgb(var(--v-theme-info));
    display: flex;
    align-items: center;
    margin-block-top: 0.2rem;
  }

  &__placeholder {
    font-size: 0.84rem;
    font-weight: 500;
    color: rgba(var(--v-theme-on-surface), 0.5);
    display: flex;
    align-items: center;
  }
}

.location-arrow {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

/* ── Field labels ── */
.field-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: rgba(var(--v-theme-on-surface), 0.7);
  letter-spacing: 0.02em;
}

/* ── Movement type grid ── */
.movement-type-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 0.5rem;
}

.movement-type-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  padding: 0.7rem 0.5rem;
  border-radius: 10px;
  border: 1px solid rgba(var(--v-border-color), 0.18);
  background: rgb(var(--v-theme-surface));
  cursor: pointer;
  outline: none;
  text-align: center;
  transition: all 0.18s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.4);
    background: rgba(var(--v-theme-primary), 0.04);
  }

  &--active {
    border-width: 2px;
    font-weight: 600;
  }

  &--primary {
    border-color: rgb(var(--v-theme-primary)) !important;
    background: rgba(var(--v-theme-primary), 0.08) !important;
  }

  &--info {
    border-color: rgb(var(--v-theme-info)) !important;
    background: rgba(var(--v-theme-info), 0.08) !important;
  }

  &--warning {
    border-color: rgb(var(--v-theme-warning)) !important;
    background: rgba(var(--v-theme-warning), 0.08) !important;
  }

  &--error {
    border-color: rgb(var(--v-theme-error)) !important;
    background: rgba(var(--v-theme-error), 0.08) !important;
  }
}
</style>
