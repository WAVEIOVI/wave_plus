<script setup>
/* eslint-disable camelcase */
import WpConfirmDialog from '@/components/shared/dialogs/WpConfirmDialog.vue'
import WpFormDialog from '@/components/shared/dialogs/WpFormDialog.vue'
import {
  WORKWEAR_COLOR_PALETTE,
  WORKWEAR_SILHOUETTE_CATALOG,
} from '@/composables/client-assets/workwear/useWorkwearStudioConstants'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const store = useClientAssetStore()
const snackbarStore = useSnackbarStore()

const notifySuccess = msg => snackbarStore.showSnackbar(msg, 'success')
const notifyError = msg => snackbarStore.showSnackbar(msg, 'error')

const loading = ref(false)
const seeding = ref(false)
const searchQuery = ref('')
const statusFilter = ref('all')

const isFormOpen = ref(false)
const isDeleteOpen = ref(false)
const saving = ref(false)
const deleting = ref(false)
const editingFabric = ref(null)
const fabricToDelete = ref(null)

const form = ref({
  code: '',
  name: '',
  composition: '',
  weight_gsm: null,
  weave_type: '',
  properties: [],
  description: '',
  is_active: true,
  sort_order: 10,
})

const weaveTypePresets = [
  'Twill 2/1',
  'Twill 3/1',
  'Ripstop',
  'Satin 4/1',
  'Plain Weave',
  'Oxford 300D',
  '3-Layer Laminated',
  'Piqué Knit',
  'Jersey Knit',
  'Canvas / Textured Nylon',
]

const propertyPresets = [
  'Industrial Wash ISO 15797',
  'Flame Retardant',
  'Inherent FR',
  'Antistatic EN 1149-5',
  'Welding EN ISO 11611',
  'Heat & Flame EN ISO 11612',
  'Electric Arc IEC 61482-2',
  'Chemical Splash EN 13034',
  'High Visibility EN ISO 20471',
  'Waterproof EN 343 Class 3',
  'Windproof',
  'Breathable',
  '2-Way Stretch',
  '4-Way Stretch',
  'Abrasion Resistant',
  'Moisture Wicking',
  'UV UPF 40+',
]

const filteredFabrics = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()

  return (store.workwearFabrics || []).filter(item => {
    if (statusFilter.value === 'active' && !item.is_active) return false
    if (statusFilter.value === 'inactive' && item.is_active) return false
    if (!q) return true

    return (
      item.code?.toLowerCase().includes(q) ||
      item.name?.toLowerCase().includes(q) ||
      item.composition?.toLowerCase().includes(q) ||
      item.weave_type?.toLowerCase().includes(q)
    )
  })
})

const headers = computed(() => [
  { title: t('Code'), key: 'code', width: '140px' },
  { title: t('Fabric Name & Composition'), key: 'name' },
  { title: t('Weave'), key: 'weave_type', width: '150px' },
  { title: t('Weight'), key: 'weight_gsm', width: '110px', align: 'end' },
  { title: t('Technical Properties'), key: 'properties' },
  { title: t('Models'), key: 'workwear_models_count', width: '90px', align: 'center' },
  { title: t('Status'), key: 'is_active', width: '100px' },
  { title: t('Actions'), key: 'actions', width: '110px', sortable: false, align: 'end' },
])

async function loadFabrics() {
  loading.value = true
  try {
    await store.fetchWorkwearFabrics({ per_page: 100, seed_if_empty: 1 })
  } catch (e) {
    notifyError(e?.data?.message || t('Failed to load workwear fabrics.'))
  } finally {
    loading.value = false
  }
}

async function handleSeedDefaults() {
  seeding.value = true
  try {
    await store.seedDefaultWorkwearFabrics()
    notifySuccess(t('Default technical workwear fabrics restored.'))
  } catch (e) {
    notifyError(e?.data?.message || t('Failed to seed default fabrics.'))
  } finally {
    seeding.value = false
  }
}

function openCreateDialog() {
  editingFabric.value = null
  form.value = {
    code: '',
    name: '',
    composition: '',
    weight_gsm: 245,
    weave_type: 'Twill 2/1',
    properties: ['Industrial Wash ISO 15797'],
    description: '',
    is_active: true,
    sort_order: ((store.workwearFabrics?.length || 0) + 1) * 10,
  }
  isFormOpen.value = true
}

function openEditDialog(item) {
  editingFabric.value = item
  form.value = {
    code: item.code || '',
    name: item.name || '',
    composition: item.composition || '',
    weight_gsm: item.weight_gsm ?? null,
    weave_type: item.weave_type || '',
    properties: Array.isArray(item.properties) ? [...item.properties] : [],
    description: item.description || '',
    is_active: Boolean(item.is_active),
    sort_order: item.sort_order ?? 10,
  }
  isFormOpen.value = true
}

async function handleSaveFabric() {
  if (!form.value.code?.trim() || !form.value.name?.trim()) return

  saving.value = true
  try {
    const payload = {
      ...form.value,
      code: form.value.code.trim().toUpperCase(),
      name: form.value.name.trim(),
      weight_gsm: form.value.weight_gsm ? Number(form.value.weight_gsm) : null,
    }

    if (editingFabric.value?.id) {
      await store.updateWorkwearFabric(editingFabric.value.id, payload)
      notifySuccess(t('Workwear fabric updated.'))
    } else {
      await store.createWorkwearFabric(payload)
      notifySuccess(t('Workwear fabric created.'))
    }
    isFormOpen.value = false
    await loadFabrics()
  } catch (e) {
    notifyError(e?.data?.message || t('Failed to save workwear fabric.'))
  } finally {
    saving.value = false
  }
}

function confirmDelete(item) {
  fabricToDelete.value = item
  isDeleteOpen.value = true
}

async function handleDeleteFabric() {
  if (!fabricToDelete.value?.id) return
  deleting.value = true
  try {
    await store.deleteWorkwearFabric(fabricToDelete.value.id)
    notifySuccess(t('Workwear fabric deleted.'))
    isDeleteOpen.value = false
    fabricToDelete.value = null
    await loadFabrics()
  } catch (e) {
    notifyError(e?.data?.message || t('Cannot delete fabric.'))
  } finally {
    deleting.value = false
  }
}

onMounted(loadFabrics)
</script>

<template>
  <div class="d-flex flex-column gap-5">
    <!-- Technical Workwear Fabrics Card -->
    <VCard>
      <VCardText class="d-flex flex-wrap align-center justify-space-between gap-4 pb-4">
        <div>
          <div class="d-flex align-center gap-2">
            <VAvatar
              size="34"
              color="primary"
              variant="tonal"
              rounded
            >
              <VIcon
                icon="tabler-Manual-gearbox"
                size="18"
              />
            </VAvatar>
            <div>
              <h5 class="text-h6 font-weight-bold mb-0">
                {{ $t('Technical Workwear Fabrics') }}
              </h5>
              <p class="text-body-2 text-medium-emphasis mb-0">
                {{ $t('Manage textile compositions, GSM weights, weaves, and normative properties used across client workwear models.') }}
              </p>
            </div>
          </div>
        </div>

        <div class="d-flex flex-wrap align-center gap-2">
          <VBtn
            variant="tonal"
            color="secondary"
            size="small"
            prepend-icon="tabler-Sparkles"
            :loading="seeding"
            @click="handleSeedDefaults"
          >
            {{ $t('Restore Defaults') }}
          </VBtn>
          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            @click="openCreateDialog"
          >
            {{ $t('Add Fabric') }}
          </VBtn>
        </div>
      </VCardText>

      <VDivider />

      <!-- Search & Filter Bar -->
      <VCardText class="py-3">
        <VRow dense>
          <VCol
            cols="12"
            sm="8"
            md="6"
          >
            <AppTextField
              v-model="searchQuery"
              :placeholder="$t('Search by code, name, composition, or weave...')"
              prepend-inner-icon="tabler-search"
              clearable
              density="compact"
            />
          </VCol>
          <VCol
            cols="12"
            sm="4"
            md="3"
          >
            <AppSelect
              v-model="statusFilter"
              :items="[
                { title: $t('All Statuses'), value: 'all' },
                { title: $t('Active Only'), value: 'active' },
                { title: $t('Inactive Only'), value: 'inactive' },
              ]"
              density="compact"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <!-- Data Table -->
      <VDataTable
        :headers="headers"
        :items="filteredFabrics"
        :loading="loading"
        item-value="id"
        class="text-no-wrap"
      >
        <template #item.code="{ item }">
          <VChip
            size="small"
            color="primary"
            variant="tonal"
            label
            class="font-mono font-weight-bold"
          >
            {{ item.code }}
          </VChip>
        </template>

        <template #item.name="{ item }">
          <div class="py-2">
            <div class="font-weight-semibold text-body-2 text-high-emphasis">
              {{ item.name }}
            </div>
            <div
              v-if="item.composition"
              class="text-caption text-medium-emphasis"
            >
              {{ item.composition }}
            </div>
          </div>
        </template>

        <template #item.weave_type="{ item }">
          <span class="text-body-2">{{ item.weave_type || '—' }}</span>
        </template>

        <template #item.weight_gsm="{ item }">
          <VChip
            v-if="item.weight_gsm"
            size="x-small"
            color="info"
            variant="tonal"
            class="font-mono"
          >
            {{ item.weight_gsm }} g/m²
          </VChip>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <template #item.properties="{ item }">
          <div
            class="d-flex flex-wrap gap-1 py-1"
            style="max-inline-size: 320px; white-space: normal;"
          >
            <VChip
              v-for="prop in (item.properties || []).slice(0, 3)"
              :key="prop"
              size="x-small"
              color="secondary"
              variant="tonal"
            >
              {{ prop }}
            </VChip>
            <VChip
              v-if="(item.properties || []).length > 3"
              size="x-small"
              color="default"
              variant="outlined"
            >
              +{{ item.properties.length - 3 }}
            </VChip>
          </div>
        </template>

        <template #item.workwear_models_count="{ item }">
          <VChip
            size="x-small"
            :color="item.workwear_models_count > 0 ? 'primary' : 'default'"
            variant="tonal"
          >
            {{ item.workwear_models_count || 0 }}
          </VChip>
        </template>

        <template #item.is_active="{ item }">
          <VChip
            size="small"
            :color="item.is_active ? 'success' : 'secondary'"
            variant="tonal"
          >
            {{ item.is_active ? $t('Active') : $t('Inactive') }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-end gap-1">
            <IconBtn
              size="small"
              @click="openEditDialog(item)"
            >
              <VIcon
                icon="tabler-edit"
                size="18"
              />
            </IconBtn>
            <IconBtn
              size="small"
              color="error"
              :disabled="item.workwear_models_count > 0"
              @click="confirmDelete(item)"
            >
              <VIcon
                icon="tabler-trash"
                size="18"
              />
            </IconBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Standardized Color Palette & Silhouette Taxonomy Reference -->
    <VRow>
      <VCol
        cols="12"
        md="5"
      >
        <VCard class="h-100">
          <VCardItem>
            <template #prepend>
              <VAvatar
                size="32"
                color="info"
                variant="tonal"
                rounded
              >
                <VIcon
                  icon="tabler-palette"
                  size="18"
                />
              </VAvatar>
            </template>
            <VCardTitle class="text-subtitle-1 font-weight-bold">
              {{ $t('Standard Workwear Color Options') }}
            </VCardTitle>
            <VCardSubtitle class="text-caption">
              {{ $t('Built-in high-visibility and industrial garment color swatches') }}
            </VCardSubtitle>
          </VCardItem>
          <VDivider />
          <VCardText>
            <div class="d-flex flex-wrap gap-2">
              <div
                v-for="sw in WORKWEAR_COLOR_PALETTE"
                :key="sw.value"
                class="d-flex align-center gap-2 px-3 py-1 rounded border"
              >
                <span
                  class="rounded-circle d-inline-block"
                  :style="{ width: '14px', height: '14px', backgroundColor: sw.hex, border: '1px solid rgba(0,0,0,0.2)' }"
                />
                <span class="text-caption font-weight-medium">{{ sw.label }}</span>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="7"
      >
        <VCard class="h-100">
          <VCardItem>
            <template #prepend>
              <VAvatar
                size="32"
                color="success"
                variant="tonal"
                rounded
              >
                <VIcon
                  icon="tabler-hierarchy-2"
                  size="18"
                />
              </VAvatar>
            </template>
            <VCardTitle class="text-subtitle-1 font-weight-bold">
              {{ $t('Workwear & PPE Silhouette Taxonomy') }}
            </VCardTitle>
            <VCardSubtitle class="text-caption">
              {{ $t('14 Categories & 41 Sub-categories mapped to 2D CAD Studio Illustrations') }}
            </VCardSubtitle>
          </VCardItem>
          <VDivider />
          <VCardText>
            <div class="d-flex flex-wrap gap-2">
              <VChip
                v-for="cat in WORKWEAR_SILHOUETTE_CATALOG"
                :key="cat.value"
                size="small"
                color="primary"
                variant="tonal"
                :prepend-icon="cat.icon"
              >
                {{ cat.title }}
                <span class="ms-1 text-caption opacity-75">({{ cat.subcategories.length }})</span>
              </VChip>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Create / Edit Fabric Dialog -->
    <WpFormDialog
      v-model="isFormOpen"
      :title="editingFabric ? $t('Edit Technical Fabric') : $t('Add Technical Fabric')"
      :subtitle="$t('Specify textile composition, GSM weight, weave structure, and normative properties')"
      width="680"
      :loading="saving"
      :disabled="!form.code || !form.name"
      @submit="handleSaveFabric"
    >
      <VRow>
        <VCol
          cols="12"
          sm="4"
        >
          <AppTextField
            v-model="form.code"
            :label="$t('Fabric Code')"
            placeholder="FAB-TWILL-245"
            required
          />
        </VCol>
        <VCol
          cols="12"
          sm="8"
        >
          <AppTextField
            v-model="form.name"
            :label="$t('Fabric Name')"
            placeholder="Poly-Cotton Twill 245 g/m²"
            required
          />
        </VCol>
        <VCol
          cols="12"
          sm="8"
        >
          <AppTextField
            v-model="form.composition"
            :label="$t('Fiber Composition')"
            placeholder="65% Polyester / 35% Cotton"
          />
        </VCol>
        <VCol
          cols="12"
          sm="4"
        >
          <AppTextField
            v-model.number="form.weight_gsm"
            type="number"
            :label="$t('Weight (g/m²)')"
            placeholder="245"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <AppCombobox
            v-model="form.weave_type"
            :items="weaveTypePresets"
            :label="$t('Weave / Knit Structure')"
            placeholder="Twill 2/1, Ripstop, Satin..."
          />
        </VCol>
        <VCol
          cols="12"
          sm="3"
        >
          <AppTextField
            v-model.number="form.sort_order"
            type="number"
            :label="$t('Sort Order')"
          />
        </VCol>
        <VCol
          cols="12"
          sm="3"
          class="d-flex align-center pt-6"
        >
          <VSwitch
            v-model="form.is_active"
            :label="$t('Active')"
            color="success"
            hide-details
          />
        </VCol>
        <VCol cols="12">
          <AppCombobox
            v-model="form.properties"
            :items="propertyPresets"
            :label="$t('Technical & Normative Properties')"
            :placeholder="$t('Select or type properties...')"
            multiple
            chips
            closable-chips
          />
        </VCol>
        <VCol cols="12">
          <AppTextarea
            v-model="form.description"
            :label="$t('Technical Description / Application Notes')"
            rows="2"
            auto-grow
          />
        </VCol>
      </VRow>
    </WpFormDialog>

    <!-- Delete Confirmation -->
    <WpConfirmDialog
      v-model="isDeleteOpen"
      :title="$t('Delete Workwear Fabric')"
      :message="$t('Are you sure you want to delete this technical fabric?')"
      :item-name="fabricToDelete?.name"
      variant="error"
      :confirm-text="$t('Delete')"
      :loading="deleting"
      @confirm="handleDeleteFabric"
    />
  </div>
</template>
