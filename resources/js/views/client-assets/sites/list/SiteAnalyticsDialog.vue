<script setup>
/* eslint-disable camelcase */
/**
 * SiteAnalyticsDialog
 * ─────────────────────────────────────────────────────────────────────────────
 * WAVE PLUS Standard Site Equipment Analytics Dialog.
 * Uses standard WpDetailDialog with PerfectScrollbar and standardized header/actions.
 * Displays site-level telemetry, KPI aggregates, breakdown charts (ApexCharts),
 * and zone/class/blueprint distributions by embedding SiteEquipmentAnalyticsPanel.
 */
import WpDetailDialog from '@/components/shared/dialogs/WpDetailDialog.vue'
import {
  operatingStatusOptions,
  resolveRiskVariant,
  resolveSiteStatusVariant,
  riskClassOptions,
  useSiteConstants,
} from '@/composables/client-assets/sites/useSiteConstants'
import { localizedValue } from '@/composables/useLocaleField'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import SiteEquipmentAnalyticsPanel from '@/views/client-assets/sites/edit/SiteEquipmentAnalyticsPanel.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  isDialogVisible: { type: Boolean, default: undefined },
  siteId: { type: [Number, String], default: null },
  site: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'update:isDialogVisible'])

const store = useClientAssetStore()
const router = useRouter()
const { t } = useI18n()
const { smAndDown } = useDisplay()
const { optionTitle } = useSiteConstants()

const loading = ref(false)
const error = ref(null)
const siteData = ref(null)
const areasFlat = ref([])
const equipmentList = ref([])

const visible = computed({
  get: () => (props.isDialogVisible !== undefined ? props.isDialogVisible : props.modelValue),
  set: val => {
    emit('update:modelValue', val)
    emit('update:isDialogVisible', val)
  },
})

const targetSiteId = computed(() => props.siteId || props.site?.id)

const siteName = computed(() => {
  return localizedValue(siteData.value?.name) || siteData.value?.name || localizedValue(props.site?.name) || props.site?.name || ''
})

const clientName = computed(() => {
  const c = siteData.value?.client || props.site?.client
  if (!c) return ''

  return localizedValue(c.legal_name || c.commercial_name || c.trade_name) || ''
})

const loadData = async id => {
  if (!id) return
  loading.value = true
  error.value = null
  try {
    const [sData, aData, eData] = await Promise.all([
      store.fetchSite(id),
      store.fetchSiteAreas(id).catch(() => ({ flat: [] })),
      store.fetchEquipment({ site_id: id, itemsPerPage: -1 }).catch(() => ({ data: [] })),
    ])

    siteData.value = sData
    areasFlat.value = aData?.flat || []
    equipmentList.value = eData?.data || []
  }
  catch (err) {
    console.error('Error loading site analytics:', err)
    error.value = err?.data?.message || err?.message || t('Failed to load site analytics.')
  }
  finally {
    loading.value = false
  }
}

watch(
  () => [props.modelValue, props.isDialogVisible, targetSiteId.value],
  ([mv, idv, sid]) => {
    const isShowing = idv !== undefined ? idv : mv
    if (isShowing && sid) {
      if (props.site && !siteData.value) {
        siteData.value = props.site
      }
      loadData(sid)
    }
    else if (!isShowing) {
      siteData.value = null
      areasFlat.value = []
      equipmentList.value = []
      error.value = null
    }
  },
  { immediate: true },
)

const navigateToEdit = () => {
  if (!targetSiteId.value) return
  visible.value = false
  router.push({ name: 'client-assets-sites-edit-id', params: { id: targetSiteId.value } })
}

const navigateToInventory = () => {
  if (!targetSiteId.value) return
  visible.value = false
  router.push({ name: 'client-assets-sites-inventory-explorer-id', params: { id: targetSiteId.value } })
}
</script>

<template>
  <WpDetailDialog
    :model-value="visible"
    :title="siteName || t('Site Analytics')"
    size="xlarge"
    :loading="loading && !siteData"
    :error="error"
    @update:model-value="visible = $event"
    @retry="loadData(targetSiteId)"
  >
    <template #avatar>
      <VAvatar
        rounded
        :size="smAndDown ? 36 : 42"
        color="primary"
        variant="tonal"
      >
        <VIcon
          icon="tabler-chart-pie-2"
          :size="smAndDown ? 20 : 24"
        />
      </VAvatar>
    </template>

    <template #subtitle>
      <div class="d-flex align-center flex-wrap gap-2 text-caption">
        <VChip
          v-if="siteData?.site_code"
          size="x-small"
          label
          color="secondary"
          variant="flat"
        >
          {{ siteData.site_code }}
        </VChip>
        <span
          v-if="clientName"
          class="text-medium-emphasis text-truncate"
        >
          {{ clientName }}
        </span>
        <VChip
          v-if="siteData?.status"
          size="x-small"
          label
          :color="resolveSiteStatusVariant(siteData.status)"
          class="text-capitalize"
        >
          {{ optionTitle(operatingStatusOptions, siteData.status) }}
        </VChip>
        <VChip
          v-if="siteData?.risk_class"
          size="x-small"
          label
          :color="resolveRiskVariant(siteData.risk_class)"
          class="text-capitalize"
        >
          {{ optionTitle(riskClassOptions, siteData.risk_class) }}
        </VChip>
      </div>
    </template>

    <template #actions>
      <VBtn
        v-if="targetSiteId"
        variant="tonal"
        size="small"
        color="primary"
        :icon="smAndDown"
        :prepend-icon="smAndDown ? undefined : 'tabler-edit'"
        :aria-label="t('Open Site Editor')"
        @click="navigateToEdit"
      >
        <VIcon
          v-if="smAndDown"
          icon="tabler-edit"
          size="18"
        />
        <span v-else>{{ t('Open Site Editor') }}</span>
      </VBtn>
    </template>

    <template #loading>
      <div
        class="d-flex flex-column gap-6 py-4"
        aria-busy="true"
        :aria-label="t('Computing site analytics & telemetry...')"
      >
        <VRow>
          <VCol
            v-for="n in 4"
            :key="`kpi-skeleton-${n}`"
            cols="12"
            sm="6"
            md="3"
          >
            <VSkeletonLoader
              type="list-item-avatar-two-line"
              class="border rounded-lg"
            />
          </VCol>
        </VRow>
        <VRow>
          <VCol
            cols="12"
            md="5"
          >
            <VSkeletonLoader
              type="image"
              class="border rounded-lg"
            />
          </VCol>
          <VCol
            cols="12"
            md="7"
          >
            <VSkeletonLoader
              type="image"
              class="border rounded-lg"
            />
          </VCol>
        </VRow>
        <VSkeletonLoader
          type="table-row@4"
          class="border rounded-lg"
        />
      </div>
    </template>

    <!-- 👉 Analytics Content -->
    <div
      v-if="siteData"
      class="d-flex flex-column gap-6 pa-1"
    >
      <SiteEquipmentAnalyticsPanel
        :equipment="equipmentList"
        :areas-flat="areasFlat"
        show-open-inventory
        @open-inventory="navigateToInventory"
      />
    </div>
  </WpDetailDialog>
</template>
