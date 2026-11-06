<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  item: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()

const visible = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const statusColorMap = { active: 'success', inactive: 'secondary', maintenance: 'warning' }

const infoFields = computed(() => {
  if (!props.item) return []
  const s = props.item
  
  return [
    { label: t('Code'), value: s.code, icon: 'tabler-hash', mono: true },
    { label: t('Name'), value: localizedValue(s.name), icon: 'tabler-id' },
    { label: t('City'), value: s.city || '—', icon: 'tabler-map-pin' },
    { label: t('Country'), value: s.country || '—', icon: 'tabler-world' },
    { label: t('Phone'), value: s.phone || '—', icon: 'tabler-phone' },
    { label: t('Email'), value: s.email || '—', icon: 'tabler-mail' },
    { label: t('Manager'), value: s.manager?.name || t('Unassigned'), icon: 'tabler-user' },
    { label: t('Status'), value: null, icon: 'tabler-activity', isStatus: true },
  ]
})
</script>

<template>
  <VDialog
    v-model="visible"
    max-width="700"
    scrollable
  >
    <VCard
      v-if="item"
      class="position-relative overflow-visible"
    >
      <!-- Header -->
      <div class="view-site-header pa-6 d-flex align-center justify-space-between">
        <div class="d-flex align-center gap-3">
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            size="48"
          >
            <VIcon
              icon="tabler-building-warehouse"
              size="28"
            />
          </VAvatar>
          <div>
            <h3 class="text-h5 font-weight-bold mb-0">
              {{ item.code }}
            </h3>
            <p class="text-sm text-medium-emphasis mb-0">
              {{ localizedValue(item.name) }}
            </p>
          </div>
        </div>
        <div class="d-flex align-center gap-2">
          <VChip
            :color="statusColorMap[item.status] || 'secondary'"
            size="small"
            label
            class="text-capitalize font-weight-medium"
          >
            {{ t(item.status) }}
          </VChip>
          <IconBtn @click="visible = false">
            <VIcon icon="tabler-x" />
          </IconBtn>
        </div>
      </div>

      <VDivider />

      <VCardText
        class="pa-6"
        style="max-block-size: calc(100vh - 220px); overflow-y: auto;"
      >
        <!-- Info Grid -->
        <div class="d-flex align-center gap-2 mb-4">
          <VIcon
            icon="tabler-info-circle"
            size="18"
            color="primary"
          />
          <span
            class="text-sm font-weight-bold text-uppercase"
            style="letter-spacing: 1px;"
          >{{ t('Site Details') }}</span>
        </div>

        <VRow dense>
          <VCol
            v-for="(f, idx) in infoFields"
            :key="idx"
            cols="12"
            sm="6"
          >
            <div
              class="d-flex align-center gap-3 pa-3 rounded-lg"
              style="background: rgba(var(--v-theme-on-surface), 0.03);"
            >
              <VAvatar
                size="32"
                variant="tonal"
                color="primary"
                rounded
              >
                <VIcon
                  :icon="f.icon"
                  size="16"
                />
              </VAvatar>
              <div>
                <span class="text-xs text-medium-emphasis d-block">{{ f.label }}</span>
                <VChip
                  v-if="f.isStatus"
                  :color="statusColorMap[item.status] || 'secondary'"
                  size="x-small"
                  label
                  class="text-capitalize mt-1"
                >
                  {{ t(item.status) }}
                </VChip>
                <span
                  v-else
                  class="text-sm font-weight-medium"
                  :style="f.mono ? 'font-family: monospace' : ''"
                >{{ f.value }}</span>
              </div>
            </div>
          </VCol>
        </VRow>

        <!-- Stats Summary -->
        <VDivider class="my-5" />
        <div class="d-flex align-center gap-2 mb-4">
          <VIcon
            icon="tabler-chart-bar"
            size="18"
            color="primary"
          />
          <span
            class="text-sm font-weight-bold text-uppercase"
            style="letter-spacing: 1px;"
          >{{ t('Summary') }}</span>
        </div>
        <VRow dense>
          <VCol
            cols="6"
            sm="4"
          >
            <VCard
              variant="tonal"
              color="info"
              class="text-center pa-3"
            >
              <h4 class="text-h5 font-weight-bold">
                {{ item.zones_count ?? 0 }}
              </h4>
              <span class="text-xs">{{ t('Zones') }}</span>
            </VCard>
          </VCol>
          <VCol
            cols="6"
            sm="4"
          >
            <VCard
              variant="tonal"
              color="secondary"
              class="text-center pa-3"
            >
              <h4 class="text-h5 font-weight-bold">
                {{ item.locations_count ?? 0 }}
              </h4>
              <span class="text-xs">{{ t('Locations') }}</span>
            </VCard>
          </VCol>
          <VCol
            cols="12"
            sm="4"
          >
            <VCard
              variant="tonal"
              color="success"
              class="text-center pa-3"
            >
              <h4 class="text-h5 font-weight-bold">
                {{ item.stock_items_count ?? 0 }}
              </h4>
              <span class="text-xs">{{ t('Stock Items') }}</span>
            </VCard>
          </VCol>
        </VRow>

        <!-- Notes -->
        <template v-if="item.notes">
          <VDivider class="my-5" />
          <div class="d-flex align-center gap-2 mb-3">
            <VIcon
              icon="tabler-notes"
              size="18"
              color="primary"
            />
            <span
              class="text-sm font-weight-bold text-uppercase"
              style="letter-spacing: 1px;"
            >{{ t('Notes') }}</span>
          </div>
          <p class="text-sm text-medium-emphasis mb-0">
            {{ item.notes }}
          </p>
        </template>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6">
        <VBtn
          variant="tonal"
          color="info"
          size="small"
          prepend-icon="tabler-packages"
          :to="{ name: 'inventory-stock-items-list', query: { site_id: item.id } }"
        >
          {{ t('View Stock') }}
        </VBtn>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="visible = false"
        >
          {{ t('Close') }}
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-pencil"
          :to="{ name: 'inventory-warehouse-sites-edit-id', params: { id: item.id } }"
        >
          {{ t('Edit Site') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.view-site-header {
  position: sticky;
  z-index: 1;
  backdrop-filter: blur(10px);
  background: rgba(var(--v-theme-surface), 0.8);
  inset-block-start: 0;
}
</style>
