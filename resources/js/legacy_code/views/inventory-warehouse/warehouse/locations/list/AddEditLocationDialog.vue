<script setup>
import { $api } from '@/utils/api'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  editingItem: { type: Object, default: null },
  sitesForFilter: { type: Array, default: () => [] },
  isSubmitting: { type: Boolean, default: false },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'submit',
])

const { t } = useI18n()

const locTypeOptions = ['shelf', 'bin', 'floor', 'pallet', 'rack', 'cold_storage', 'hazmat'].map(v => ({
  title: v.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
  value: v,
}))

const initialForm = () => ({
  zone_id: null,
  barcode: '',
  aisle: '',
  rack: '',
  shelf: '',
  bin: '',
  location_type: 'shelf',
  max_weight_kg: null,
  is_active: true,
})

const locForm = ref(initialForm())
const formSiteId = ref(null)
const formZones = ref([])
const formError = ref('')

const loadFormZones = async () => {
  if (!formSiteId.value) { formZones.value = [] 

    return }
  try {
    const res = await $api('/inventory/zones', { method: 'GET', params: { 'filter[site_id]': formSiteId.value, itemsPerPage: -1 } })

    formZones.value = (res.data || []).map(z => ({ title: `${z.code} — ${z.name?.en || z.name}`, value: z.id }))
  } catch {}
}

watch(formSiteId, () => { locForm.value.zone_id = null; loadFormZones() })

watch(() => props.isDialogVisible, val => {
  if (val) {
    formError.value = ''
    if (props.editingItem) {
      locForm.value = {
        zone_id: props.editingItem.zone_id,
        barcode: props.editingItem.barcode || '',
        aisle: props.editingItem.aisle || '',
        rack: props.editingItem.rack || '',
        shelf: props.editingItem.shelf || '',
        bin: props.editingItem.bin || '',
        location_type: props.editingItem.location_type || 'shelf',
        max_weight_kg: props.editingItem.max_weight_kg ?? null,
        is_active: props.editingItem.is_active ?? true,
      }
      formSiteId.value = props.editingItem.zone?.site?.id || null
      loadFormZones()
    } else {
      locForm.value = initialForm()
      formSiteId.value = null
      formZones.value = []
    }
  }
})

const onSubmit = () => {
  formError.value = ''
  if (!locForm.value.zone_id || !locForm.value.location_type) {
    formError.value = t('Zone and type are required.')

    return
  }
  emit('submit', { ...locForm.value })
}

const closeDialog = () => {
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="680"
    persistent
    scrollable
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard class="position-relative overflow-visible">
      <!-- Premium Glassmorphism Header -->
      <div class="loc-dialog-header pa-6 d-flex align-center justify-space-between">
        <div class="d-flex align-center gap-3">
          <VAvatar
            color="secondary"
            variant="tonal"
            rounded
            size="48"
          >
            <VIcon
              :icon="props.editingItem ? 'tabler-pencil' : 'tabler-map-pin'"
              size="28"
            />
          </VAvatar>
          <div>
            <h3 class="text-h5 font-weight-bold mb-0">
              {{ props.editingItem ? $t('Edit Location') : $t('New Location') }}
            </h3>
            <p class="text-sm text-medium-emphasis mb-0">
              {{ props.editingItem ? $t('Update location details and coordinates') : $t('Define a new storage position in a zone') }}
            </p>
          </div>
        </div>
        <IconBtn @click="closeDialog">
          <VIcon icon="tabler-x" />
        </IconBtn>
      </div>

      <VDivider />

      <VCardText
        class="pa-6"
        style="max-block-size: calc(100vh - 200px); overflow-y: auto;"
      >
        <VAlert
          v-if="formError"
          type="error"
          variant="tonal"
          class="mb-5"
          closable
          @click:close="formError = ''"
        >
          {{ formError }}
        </VAlert>

        <VRow>
          <!-- Section: Location Context -->
          <VCol cols="12">
            <div class="d-flex align-center gap-2 mb-4">
              <VIcon
                icon="tabler-layers-intersect"
                size="18"
                color="secondary"
              />
              <span class="text-sm font-weight-bold text-uppercase letter-spacing-1">{{ $t('Zone Assignment') }}</span>
            </div>
          </VCol>

          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="formSiteId"
              :label="$t('Site *')"
              :items="props.sitesForFilter"
              variant="outlined"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="locForm.zone_id"
              :label="$t('Zone *')"
              :items="formZones"
              variant="outlined"
              :disabled="!formSiteId"
              clearable
            />
          </VCol>

          <!-- Section: Position -->
          <VCol cols="12">
            <VDivider class="mb-4" />
            <div class="d-flex align-center gap-2 mb-4">
              <VIcon
                icon="tabler-map-pin"
                size="18"
                color="secondary"
              />
              <span class="text-sm font-weight-bold text-uppercase letter-spacing-1">{{ $t('Position & Identification') }}</span>
            </div>
          </VCol>

          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="locForm.barcode"
              :label="$t('Barcode')"
              variant="outlined"
              persistent-placeholder
              placeholder="LOC-A1-R2-S3"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="locForm.location_type"
              :label="$t('Location Type *')"
              :items="locTypeOptions"
              variant="outlined"
            />
          </VCol>

          <VCol
            cols="6"
            sm="3"
          >
            <AppTextField
              v-model="locForm.aisle"
              :label="$t('Aisle')"
              variant="outlined"
              placeholder="A"
            />
          </VCol>
          <VCol
            cols="6"
            sm="3"
          >
            <AppTextField
              v-model="locForm.rack"
              :label="$t('Rack')"
              variant="outlined"
              placeholder="R1"
            />
          </VCol>
          <VCol
            cols="6"
            sm="3"
          >
            <AppTextField
              v-model="locForm.shelf"
              :label="$t('Shelf')"
              variant="outlined"
              placeholder="S2"
            />
          </VCol>
          <VCol
            cols="6"
            sm="3"
          >
            <AppTextField
              v-model="locForm.bin"
              :label="$t('Bin')"
              variant="outlined"
              placeholder="B3"
            />
          </VCol>

          <!-- Section: Constraints -->
          <VCol cols="12">
            <VDivider class="mb-4" />
            <div class="d-flex align-center gap-2 mb-4">
              <VIcon
                icon="tabler-settings"
                size="18"
                color="secondary"
              />
              <span class="text-sm font-weight-bold text-uppercase letter-spacing-1">{{ $t('Constraints & Status') }}</span>
            </div>
          </VCol>

          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="locForm.max_weight_kg"
              :label="$t('Max Weight (kg)')"
              type="number"
              variant="outlined"
              placeholder="500"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
            class="d-flex align-center"
          >
            <VSwitch
              v-model="locForm.is_active"
              :label="$t('Active')"
              color="success"
              hide-details
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6">
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="closeDialog"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          color="secondary"
          :loading="props.isSubmitting"
          class="px-6 shadow-secondary"
          @click="onSubmit"
        >
          {{ props.editingItem ? $t('Update Location') : $t('Create Location') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.loc-dialog-header {
  position: sticky;
  z-index: 1;
  backdrop-filter: blur(10px);
  background: rgba(var(--v-theme-surface), 0.8);
  inset-block-start: 0;
}

.letter-spacing-1 {
  letter-spacing: 1px;
}

.shadow-secondary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-secondary), 0.4) !important;
}
</style>
