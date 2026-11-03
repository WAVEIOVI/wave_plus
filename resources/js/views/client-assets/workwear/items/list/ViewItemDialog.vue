<script setup>
/* eslint-disable camelcase */
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import WorkwearGarmentIllustration from '@/views/client-assets/workwear/models/components/WorkwearGarmentIllustration.vue'
import { computed, ref, watch } from 'vue'
import { useDisplay } from 'vuetify'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  itemId: { type: [String, Number], default: null },
  item: { type: Object, default: null },
})

const emit = defineEmits(['update:isDialogVisible'])

const store = useClientAssetStore()
const { smAndDown } = useDisplay()
const loading = ref(false)
const itemDetails = ref(null)
const stockSummary = ref([])

const statusMap = {
  in_stock: 'success',
  available: 'success',
  assigned: 'info',
  returned: 'secondary',
  retired: 'warning',
  lost: 'error',
  stolen: 'error',
}

const conditionMap = {
  new: 'success',
  good: 'info',
  fair: 'warning',
  worn: 'secondary',
  damaged: 'error',
}

const targetId = computed(() => props.itemId || props.item?.id)
const resolvedItem = computed(() => itemDetails.value || props.item)

watch(
  () => [props.isDialogVisible, targetId.value],
  async ([visible, id]) => {
    if (visible && id) {
      loading.value = true
      try {
        const full = await store.fetchWorkwearItem(id)

        itemDetails.value = full
        if (full?.model_id) {
          stockSummary.value = await store.fetchWorkwearStock({ model_id: full.model_id }).catch(() => [])
        }
      } catch (err) {
        console.error('Error loading item preview:', err)
      } finally {
        loading.value = false
      }
    } else if (!visible) {
      itemDetails.value = null
      stockSummary.value = []
    }
  },
  { immediate: true },
)
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    :max-width="smAndDown ? undefined : 900"
    :fullscreen="smAndDown"
    scrollable
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard
      class="view-dialog-card"
      :class="{ 'rounded-0': smAndDown }"
    >
      <!-- Header -->
      <VCardTitle class="d-flex flex-wrap align-center justify-space-between gap-3 pa-4 pa-sm-5">
        <div class="d-flex align-center gap-3">
          <VAvatar
            rounded
            size="44"
            :color="resolvedItem?.is_stolen ? 'error' : 'primary'"
            variant="tonal"
          >
            <VIcon
              :icon="resolvedItem?.is_stolen ? 'tabler-alert-octagon' : 'tabler-hanger'"
              size="24"
            />
          </VAvatar>
          <div>
            <div class="d-flex align-center gap-2 flex-wrap">
              <span class="text-h5 font-weight-bold font-mono">{{ resolvedItem?.item_code || $t('Item Preview') }}</span>
              <VChip
                v-if="resolvedItem?.sku"
                size="small"
                variant="tonal"
                color="secondary"
                class="font-mono"
              >
                SKU: {{ resolvedItem.sku }}
              </VChip>
              <VChip
                v-if="resolvedItem?.is_stolen"
                label
                size="small"
                color="error"
              >
                STOLEN
              </VChip>
              <VChip
                v-if="resolvedItem?.status"
                label
                size="small"
                :color="statusMap[resolvedItem.status] || 'secondary'"
                class="text-capitalize font-weight-medium"
              >
                {{ resolvedItem.status.replace(/_/g, ' ') }}
              </VChip>
              <VChip
                v-if="resolvedItem?.item_condition"
                size="small"
                variant="tonal"
                :color="conditionMap[resolvedItem.item_condition] || 'secondary'"
                class="text-capitalize"
              >
                {{ resolvedItem.item_condition }}
              </VChip>
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
              <span v-if="resolvedItem?.client?.legal_name">{{ resolvedItem.client.legal_name }}</span>
              <span v-if="resolvedItem?.model?.name"> • {{ resolvedItem.model.name }}</span>
              <span v-if="resolvedItem?.supplier?.legal_name"> • {{ $t('Supplier:') }} {{ resolvedItem.supplier.legal_name }}</span>
            </div>
          </div>
        </div>

        <VBtn
          icon
          variant="text"
          size="small"
          @click="emit('update:isDialogVisible', false)"
        >
          <VIcon
            icon="tabler-x"
            size="20"
          />
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText
        class="pa-0"
        :style="smAndDown ? undefined : 'max-block-size: 76vh; overflow-y: auto;'"
      >
        <div
          v-if="loading && !resolvedItem"
          class="d-flex justify-center align-center py-16"
        >
          <VProgressCircular
            indeterminate
            size="48"
            color="primary"
          />
        </div>

        <div
          v-else-if="resolvedItem"
          class="pa-4 pa-sm-6"
        >
          <VRow>
            <!-- Left Column: Garment Model Illustration & Photos -->
            <VCol
              cols="12"
              md="4"
            >
              <WorkwearGarmentIllustration
                :silhouette="resolvedItem.model?.silhouette || resolvedItem.garment_type || 'coverall'"
                :colors="resolvedItem.model?.colors?.length ? resolvedItem.model.colors : [resolvedItem.color || 'navy', 'hi-vis-yellow']"
                :placements="resolvedItem.model?.placements || []"
                :logos="resolvedItem.model?.logos || []"
                readonly
                compact
              />

              <!-- Product Photos -->
              <div
                v-if="resolvedItem.photos?.length"
                class="mt-3"
              >
                <div class="text-caption font-weight-bold text-uppercase text-disabled mb-2">
                  {{ $t('Product Media') }} ({{ resolvedItem.photos.length }})
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <VImg
                    v-for="photo in resolvedItem.photos"
                    :key="photo.id"
                    :src="photo.thumb || photo.url"
                    width="72"
                    height="72"
                    cover
                    class="rounded border"
                  />
                </div>
              </div>
            </VCol>

            <!-- Right Column: Variant Details, Stock & Handover History -->
            <VCol
              cols="12"
              md="8"
            >
              <!-- Variant Summary Strip -->
              <VRow
                dense
                class="mb-4"
              >
                <VCol
                  cols="6"
                  sm="3"
                >
                  <VCard
                    variant="tonal"
                    color="primary"
                    class="pa-3 text-center"
                  >
                    <div class="text-caption text-uppercase">
                      {{ $t('Size') }}
                    </div>
                    <div class="text-h6 font-weight-bold">
                      {{ resolvedItem.size || '—' }}
                    </div>
                  </VCard>
                </VCol>
                <VCol
                  cols="6"
                  sm="3"
                >
                  <VCard
                    variant="tonal"
                    color="info"
                    class="pa-3 text-center"
                  >
                    <div class="text-caption text-uppercase">
                      {{ $t('Color') }}
                    </div>
                    <div class="text-subtitle-1 font-weight-bold text-capitalize text-truncate">
                      {{ resolvedItem.color || '—' }}
                    </div>
                  </VCard>
                </VCol>
                <VCol
                  cols="6"
                  sm="3"
                >
                  <VCard
                    variant="tonal"
                    color="success"
                    class="pa-3 text-center"
                  >
                    <div class="text-caption text-uppercase">
                      {{ $t('Unit Cost') }}
                    </div>
                    <div class="text-subtitle-1 font-weight-bold font-mono">
                      {{ resolvedItem.unit_cost !== null && resolvedItem.unit_cost !== undefined ? `${Number(resolvedItem.unit_cost).toFixed(2)} TND` : '—' }}
                    </div>
                  </VCard>
                </VCol>
                <VCol
                  cols="6"
                  sm="3"
                >
                  <VCard
                    variant="tonal"
                    color="secondary"
                    class="pa-3 text-center"
                  >
                    <div class="text-caption text-uppercase">
                      {{ $t('Category') }}
                    </div>
                    <div class="text-subtitle-1 font-weight-bold text-capitalize">
                      {{ resolvedItem.garment_type || '—' }}
                    </div>
                  </VCard>
                </VCol>
              </VRow>

              <!-- References & Traceability -->
              <VCard
                variant="outlined"
                class="pa-3 mb-4"
              >
                <div class="text-caption font-weight-bold text-uppercase text-disabled mb-2">
                  {{ $t('Specification & Optional Commercial References') }}
                </div>
                <VRow dense>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <span class="text-caption text-disabled">{{ $t('Linked Model') }}</span>
                    <div class="text-body-2 font-weight-medium">
                      {{ resolvedItem.model?.name || resolvedItem.model_name || '—' }}
                      <span
                        v-if="resolvedItem.model?.model_ref"
                        class="text-caption font-mono text-disabled"
                      >({{ resolvedItem.model.model_ref }})</span>
                    </div>
                  </VCol>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <span class="text-caption text-disabled">{{ $t('Supplier / Manufacturer') }}</span>
                    <div class="text-body-2 font-weight-medium">
                      {{ resolvedItem.supplier?.legal_name || '—' }}
                    </div>
                  </VCol>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <span class="text-caption text-disabled">{{ $t('Sales Order (Optional)') }}</span>
                    <div class="text-body-2 font-weight-medium">
                      {{ resolvedItem.sales_order?.order_number || resolvedItem.sales_order?.reference || '—' }}
                    </div>
                  </VCol>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <span class="text-caption text-disabled">{{ $t('Delivery Note (Optional)') }}</span>
                    <div class="text-body-2 font-weight-medium">
                      {{ resolvedItem.delivery_note?.dn_number || resolvedItem.delivery_note?.reference || '—' }}
                    </div>
                  </VCol>
                </VRow>
              </VCard>

              <!-- Current Assignment & History -->
              <VCard
                variant="outlined"
                class="pa-3 mb-4"
              >
                <div class="text-caption font-weight-bold text-uppercase text-disabled mb-2">
                  {{ $t('Current Custodian & Assignment History') }}
                </div>
                <div
                  v-if="resolvedItem.current_assignment?.employee"
                  class="d-flex justify-space-between align-center pa-2 rounded bg-var-theme-background mb-2"
                >
                  <div>
                    <div class="font-weight-bold text-body-2">
                      {{ resolvedItem.current_assignment.employee.full_name }}
                    </div>
                    <div class="text-caption text-disabled">
                      {{ $t('Issued:') }} {{ resolvedItem.current_assignment.assigned_at ? new Date(resolvedItem.current_assignment.assigned_at).toLocaleDateString() : '—' }}
                    </div>
                  </div>
                  <VChip
                    size="x-small"
                    color="info"
                    label
                  >
                    {{ $t('Active Issue') }}
                  </VChip>
                </div>

                <div
                  v-if="resolvedItem.assignments?.length"
                  class="d-flex flex-column gap-1 mt-2"
                >
                  <div
                    v-for="a in resolvedItem.assignments.slice(0, 5)"
                    :key="a.id"
                    class="d-flex justify-space-between align-center text-caption py-1 border-b"
                  >
                    <span>{{ a.employee?.full_name || '—' }}</span>
                    <span class="text-disabled">
                      {{ a.assigned_at ? new Date(a.assigned_at).toLocaleDateString() : '—' }}
                      <template v-if="a.returned_at"> → {{ new Date(a.returned_at).toLocaleDateString() }}</template>
                    </span>
                    <VChip
                      size="x-small"
                      :color="a.returned_at ? 'secondary' : 'success'"
                      variant="tonal"
                    >
                      {{ a.returned_at ? (a.return_action || 'returned') : 'active' }}
                    </VChip>
                  </div>
                </div>
                <div
                  v-else
                  class="text-caption text-disabled"
                >
                  {{ $t('Never issued — item is in stock.') }}
                </div>
              </VCard>

              <!-- Notes -->
              <VCard
                v-if="resolvedItem.notes"
                variant="outlined"
                class="pa-3"
              >
                <div class="text-caption font-weight-bold text-uppercase text-disabled mb-1">
                  {{ $t('Notes') }}
                </div>
                <p class="text-body-2 mb-0">
                  {{ resolvedItem.notes }}
                </p>
              </VCard>
            </VCol>
          </VRow>
        </div>
      </VCardText>

      <VDivider />
      <VCardActions class="pa-4">
        <VBtn
          v-if="resolvedItem?.id"
          variant="tonal"
          color="info"
          prepend-icon="tabler-qrcode"
          @click="store.downloadItemQr(resolvedItem.id)"
        >
          {{ $t('Download QR') }}
        </VBtn>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:isDialogVisible', false)"
        >
          {{ $t('Close') }}
        </VBtn>
        <VBtn
          v-if="resolvedItem?.id"
          color="primary"
          prepend-icon="tabler-pencil"
          :to="{ name: 'client-assets-workwear-edit-id', params: { id: resolvedItem.id }, query: { type: 'item' } }"
          @click="emit('update:isDialogVisible', false)"
        >
          {{ $t('Edit Item') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
