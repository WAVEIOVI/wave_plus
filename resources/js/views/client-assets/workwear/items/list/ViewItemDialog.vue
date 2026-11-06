<script setup>
/* eslint-disable camelcase */
import {
  WpDetailDialog,
  WpJournalNav,
  WpKpiCard,
  WpKpiGroup,
} from '@/components/shared'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import WorkwearGarmentIllustration from '@/views/client-assets/workwear/models/components/WorkwearGarmentIllustration.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  itemId: { type: [String, Number], default: null },
  item: { type: Object, default: null },
})

const emit = defineEmits(['update:isDialogVisible'])

const { t, locale } = useI18n()
const store = useClientAssetStore()
const { smAndDown } = useDisplay()

const loading = ref(false)
const itemDetails = ref(null)
const stockSummary = ref([])
const activeSection = ref('bio')

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

const sections = computed(() => [
  { id: 'bio', label: t('General & Commercial'), icon: 'tabler-info-circle' },
  { id: 'media', label: t('Media & Stock Matrix'), icon: 'tabler-photo', badge: resolvedItem.value?.photos?.length || undefined },
  { id: 'history', label: t('Handover History'), icon: 'tabler-history', badge: resolvedItem.value?.assignments?.length || undefined },
])

const summaryItems = computed(() => [
  {
    label: t('Client'),
    value: resolvedItem.value?.client?.legal_name || resolvedItem.value?.client?.name || '—',
  },
  {
    label: t('Linked Model'),
    value: resolvedItem.value?.model?.name || resolvedItem.value?.workwear_model?.name || '—',
  },
  {
    label: t('Assigned Staff'),
    value: resolvedItem.value?.current_assignment?.employee?.full_name || t('In Stock / Unassigned'),
  },
  {
    label: t('Supplier'),
    value: resolvedItem.value?.supplier?.legal_name || '—',
  },
])

const formatDate = d => {
  if (!d) return '—'
  try {
    return new Date(d).toLocaleDateString(locale.value)
  } catch {
    return String(d)
  }
}

watch(
  () => [props.isDialogVisible, targetId.value],
  async ([visible, id]) => {
    if (visible && id) {
      activeSection.value = 'bio'
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
  <WpDetailDialog
    :model-value="isDialogVisible"
    :title="resolvedItem?.item_code || $t('Workwear Item Preview')"
    :subtitle="resolvedItem?.sku ? `SKU: ${resolvedItem.sku}` : (resolvedItem?.model?.name || '')"
    icon="tabler-hanger"
    :loading="loading && !resolvedItem"
    max-width="1100"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <!-- 👉 Badges -->
    <template #badges>
      <div class="d-flex align-center gap-2 flex-wrap">
        <VChip
          v-if="resolvedItem?.is_stolen"
          size="x-small"
          label
          color="error"
          class="font-weight-bold"
        >
          STOLEN
        </VChip>
        <VChip
          v-if="resolvedItem?.status"
          size="x-small"
          label
          :color="statusMap[resolvedItem.status] || 'secondary'"
          class="text-capitalize font-weight-bold"
        >
          {{ (resolvedItem.status || '').replace(/_/g, ' ') }}
        </VChip>
        <VChip
          v-if="resolvedItem?.item_condition"
          size="x-small"
          label
          variant="tonal"
          :color="conditionMap[resolvedItem.item_condition] || 'secondary'"
          class="text-capitalize"
        >
          {{ resolvedItem.item_condition }}
        </VChip>
      </div>
    </template>

    <!-- 👉 Actions -->
    <template #actions>
      <div class="d-flex align-center gap-2">
        <VBtn
          v-if="resolvedItem?.id"
          variant="tonal"
          size="small"
          color="info"
          prepend-icon="tabler-qrcode"
          @click="store.downloadItemQr(resolvedItem.id)"
        >
          {{ $t('Download QR') }}
        </VBtn>

        <VBtn
          v-if="resolvedItem?.id"
          variant="tonal"
          size="small"
          color="primary"
          prepend-icon="tabler-edit"
          :to="{ name: 'client-assets-workwear-edit-id', params: { id: resolvedItem.id }, query: { type: 'item' } }"
          @click="emit('update:isDialogVisible', false)"
        >
          {{ $t('Edit Item') }}
        </VBtn>
      </div>
    </template>

    <!-- 👉 Main Journal Mirror Layout -->
    <div
      v-if="resolvedItem"
      class="dialog-journal-layout"
    >
      <WpJournalNav
        v-model="activeSection"
        :sections="sections"
        :summary-items="summaryItems"
        is-dialog
        id-prefix="dialog-section-"
        container=".wp-detail-dialog__scroll"
      />

      <main class="dialog-journal-feed">
        <!-- KPI Strip -->
        <WpKpiGroup
          :item-count="4"
          :cols="{ xs: 6, sm: 6, md: 3 }"
          class="mb-4"
        >
          <template #default="{ getColProps }">
            <VCol v-bind="getColProps(0)">
              <WpKpiCard
                :title="$t('Size')"
                :value="resolvedItem.size || '—'"
                icon="tabler-ruler-measure"
                icon-color="primary"
              />
            </VCol>
            <VCol v-bind="getColProps(1)">
              <WpKpiCard
                :title="$t('Color')"
                :value="resolvedItem.color || '—'"
                icon="tabler-palette"
                icon-color="info"
              />
            </VCol>
            <VCol v-bind="getColProps(2)">
              <WpKpiCard
                :title="$t('Unit Cost')"
                :value="resolvedItem.unit_cost !== null && resolvedItem.unit_cost !== undefined ? `${Number(resolvedItem.unit_cost).toFixed(2)} TND` : '—'"
                icon="tabler-coin"
                icon-color="success"
              />
            </VCol>
            <VCol v-bind="getColProps(3)">
              <WpKpiCard
                :title="$t('Category')"
                :value="resolvedItem.garment_type || '—'"
                icon="tabler-hanger"
                icon-color="secondary"
              />
            </VCol>
          </template>
        </WpKpiGroup>

        <!-- ━━━ 1. General Info & Commercial ━━━ -->
        <section
          id="dialog-section-bio"
          class="dialog-journal-section mb-6"
        >
          <VCard
            variant="outlined"
            class="pa-4 pa-sm-5 rounded-lg"
          >
            <h6 class="text-subtitle-1 font-weight-bold mb-3">
              <VIcon
                icon="tabler-info-circle"
                size="18"
                color="primary"
                class="me-1"
              />
              {{ $t('General & Commercial References') }}
            </h6>

            <VRow dense>
              <VCol
                cols="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis">
                  {{ $t('Client Account') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ resolvedItem.client?.legal_name || resolvedItem.client?.name || '—' }}
                </div>
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis">
                  {{ $t('Model Reference') }}
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ resolvedItem.model?.model_ref || resolvedItem.model?.name || '—' }}
                </div>
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis">
                  {{ $t('Sales Order Reference') }}
                </div>
                <div class="text-body-2 font-mono">
                  {{ resolvedItem.sales_order?.reference || $t('Not linked') }}
                </div>
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis">
                  {{ $t('Delivery Note (DN)') }}
                </div>
                <div class="text-body-2 font-mono">
                  {{ resolvedItem.delivery_note?.dn_number || $t('Not linked') }}
                </div>
              </VCol>
              <VCol
                v-if="resolvedItem.notes"
                cols="12"
                class="mt-2"
              >
                <div class="text-caption text-medium-emphasis">
                  {{ $t('Internal Item Notes') }}
                </div>
                <div class="text-body-2 text-pre-wrap">
                  {{ resolvedItem.notes }}
                </div>
              </VCol>
            </VRow>
          </VCard>
        </section>

        <!-- ━━━ 2. Media & Stock Matrix ━━━ -->
        <section
          id="dialog-section-media"
          class="dialog-journal-section mb-6"
        >
          <VCard
            variant="outlined"
            class="pa-4 pa-sm-5 rounded-lg"
          >
            <h6 class="text-subtitle-1 font-weight-bold mb-3">
              <VIcon
                icon="tabler-photo"
                size="18"
                color="primary"
                class="me-1"
              />
              {{ $t('Garment Visuals & Sibling Availability') }}
            </h6>

            <VRow>
              <VCol
                cols="12"
                md="5"
              >
                <WorkwearGarmentIllustration
                  :silhouette="resolvedItem.model?.silhouette || resolvedItem.garment_type || 'coverall'"
                  :colors="resolvedItem.model?.colors?.length ? resolvedItem.model.colors : [resolvedItem.color || 'navy', 'hi-vis-yellow']"
                  :placements="resolvedItem.model?.placements || []"
                  :logos="resolvedItem.model?.logos || []"
                  readonly
                  compact
                />
              </VCol>

              <VCol
                cols="12"
                md="7"
              >
                <div
                  v-if="resolvedItem.photos?.length"
                  class="mb-4"
                >
                  <div class="text-caption font-weight-bold text-uppercase text-disabled mb-2">
                    {{ $t('Inspection Photos') }} ({{ resolvedItem.photos.length }})
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

                <div v-if="stockSummary.length">
                  <div class="text-caption font-weight-bold text-uppercase text-disabled mb-2">
                    {{ $t('Sibling Variant Stock') }}
                  </div>
                  <div class="d-flex flex-wrap gap-1">
                    <VChip
                      v-for="(st, idx) in stockSummary.slice(0, 8)"
                      :key="idx"
                      size="x-small"
                      :color="st.available > 0 ? 'success' : 'secondary'"
                      variant="tonal"
                    >
                      {{ st.size || 'STD' }}{{ st.color ? ` (${st.color})` : '' }}: {{ st.available }}/{{ st.total }}
                    </VChip>
                  </div>
                </div>
              </VCol>
            </VRow>
          </VCard>
        </section>

        <!-- ━━━ 3. Handover History ━━━ -->
        <section
          id="dialog-section-history"
          class="dialog-journal-section mb-6"
        >
          <VCard
            variant="outlined"
            class="pa-4 pa-sm-5 rounded-lg"
          >
            <h6 class="text-subtitle-1 font-weight-bold mb-3">
              <VIcon
                icon="tabler-history"
                size="18"
                color="primary"
                class="me-1"
              />
              {{ $t('Handover History & Staff Allocations') }}
            </h6>

            <VTable
              v-if="resolvedItem.assignments?.length"
              density="compact"
              class="border rounded text-no-wrap"
            >
              <thead>
                <tr>
                  <th>{{ $t('Employee') }}</th>
                  <th>{{ $t('Assigned') }}</th>
                  <th>{{ $t('Returned') }}</th>
                  <th>{{ $t('Status') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="a in resolvedItem.assignments"
                  :key="a.id"
                >
                  <td class="font-weight-medium">
                    {{ a.employee?.full_name || '—' }}
                  </td>
                  <td>{{ formatDate(a.assigned_at) }}</td>
                  <td>{{ formatDate(a.returned_at) }}</td>
                  <td>
                    <VChip
                      size="x-small"
                      label
                      variant="tonal"
                      :color="a.returned_at ? 'secondary' : 'success'"
                    >
                      {{ a.returned_at ? $t('Returned') : $t('Active') }}
                    </VChip>
                  </td>
                </tr>
              </tbody>
            </VTable>

            <div
              v-else
              class="text-caption text-disabled text-center py-4"
            >
              {{ $t('No handover records found for this garment.') }}
            </div>
          </VCard>
        </section>
      </main>
    </div>
  </WpDetailDialog>
</template>

<style lang="scss" scoped>
.dialog-journal-layout {
  display: flex;
  align-items: flex-start;
  gap: 1.25rem;

  @media (max-width: 960px) {
    flex-direction: column;
  }
}

.dialog-journal-feed {
  flex: 1;
  min-inline-size: 0;
}
</style>
