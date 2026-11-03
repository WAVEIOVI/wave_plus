<script setup>
/* eslint-disable camelcase */
import {
  AppBottomNav,
  AppFabSpeedDial,
  AppHybridTabs,
  MultiLangField,
  WpUnsavedChangesDialog,
} from '@/components/shared'
import AppBreadcrumb from '@/components/shared/AppBreadcrumb.vue'
import { useUnsavedChangesGuard } from '@/composables/dialog/useUnsavedChangesGuard'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { useGuidelineStore } from '@/stores/useGuidelineStore'
import AddEditGuidelineStepDialog from '@/views/catalog/guidelines/dialogs/AddEditGuidelineStepDialog.vue'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

definePage({
  path: '/catalog/guidelines/edit/:id',
  name: 'catalog-guidelines-edit-id',
  meta: {
    layout: 'default',
    module: 'catalog',
    action: 'read',
    subject: 'Catalog',
  },
})

const { t } = useI18n()
const route = useRoute('catalog-guidelines-edit-id')
const router = useRouter()
const { smAndDown } = useDisplay()
const { showMessage } = useAppSnackbar()

const guidelineStore = useGuidelineStore()

// ── State ───────────────────────────────────────────────────────────────────
const loading = ref(true)
const saving = ref(false)
const isNewMode = computed(() => route.params.id === 'new')
const formRefGeneral = ref(null)

// ── Data Model ──────────────────────────────────────────────────────────────
const formData = ref({
  id: null,
  code: '',
  name: { en: '', fr: '', ar: '' },
  description: { en: '', fr: '', ar: '' },
  is_active: true,
})

const steps = ref([])

// ── Breadcrumbs ─────────────────────────────────────────────────────────────
const breadcrumbItems = computed(() => [
  { title: t('Home'), to: '/', icon: 'tabler-home' },
  { title: t('Catalog'), to: '/catalog/guidelines/list', icon: 'tabler-packages' },
  { title: t('Guidelines'), to: '/catalog/guidelines/list', icon: 'tabler-checklist' },
  {
    title: isNewMode.value ? t('New Guideline') : (formData.value.code || t('Edit Guideline')),
    icon: isNewMode.value ? 'tabler-plus' : 'tabler-edit',
  },
])

// ── Tabs Navigation with AppHybridTabs ──────────────────────────────────────
const activeTab = ref('base')

const tabs = computed(() => [
  { value: 'base', title: t('catalog.guidelines.edit.tabs.base', 'Identification'), icon: 'tabler-info-circle' },
  { value: 'steps', title: t('catalog.guidelines.edit.tabs.steps', 'Steps & Instructions'), icon: 'tabler-list-numbers', badge: steps.value.length || undefined },
])

const mobileNavItems = computed(() => tabs.value.map(tab => ({
  title: tab.title,
  icon: tab.icon,
  key: tab.value,
  value: tab.value,
})))

// ── Steps Dialog & Management ───────────────────────────────────────────────
const isStepDialogOpen = ref(false)
const isStepEditMode = ref(false)
const activeStepIndex = ref(null)
const selectedStepForDialog = ref(null)
const savingStep = ref(false)

const openAddStepDialog = () => {
  isStepEditMode.value = false
  activeStepIndex.value = null
  selectedStepForDialog.value = null
  isStepDialogOpen.value = true
}

const openEditStepDialog = (step, index) => {
  isStepEditMode.value = true
  activeStepIndex.value = index
  selectedStepForDialog.value = step
  isStepDialogOpen.value = true
}

const handleSaveStep = async stepData => {
  if (isNewMode.value) {
    // In new mode, store steps in memory until guideline is saved
    if (isStepEditMode.value && activeStepIndex.value !== null) {
      steps.value[activeStepIndex.value] = stepData
    } else {
      steps.value.push({ ...stepData, _key: Date.now() })
    }
    isStepDialogOpen.value = false
    showMessage(t('Step saved locally'), 'success')
    
    return
  }

  // Edit mode: save directly via API
  savingStep.value = true
  try {
    if (isStepEditMode.value && selectedStepForDialog.value?.id) {
      await guidelineStore.updateStep(selectedStepForDialog.value.id, stepData)
      showMessage(t('Step updated successfully'), 'success')
    } else {
      await guidelineStore.createStep({
        ...stepData,
        guideline_id: route.params.id,
        sort_order: steps.value.length + 1,
      })
      showMessage(t('Step added successfully'), 'success')
    }
    isStepDialogOpen.value = false
    await loadGuideline()
  } catch (err) {
    console.error(err)
    showMessage(err?.data?.message || 'Failed to save step', 'error')
  } finally {
    savingStep.value = false
  }
}

const handleDeleteStep = async (step, index) => {
  if (isNewMode.value) {
    steps.value.splice(index, 1)
    showMessage(t('Step removed'), 'info')
    
    return
  }

  try {
    await guidelineStore.deleteStep(step.id)
    showMessage(t('Step deleted successfully'), 'success')
    await loadGuideline()
  } catch {
    showMessage('Failed to delete step', 'error')
  }
}

const moveStep = async (index, direction) => {
  const newIndex = index + direction
  if (newIndex < 0 || newIndex >= steps.value.length) return

  const item = steps.value.splice(index, 1)[0]

  steps.value.splice(newIndex, 0, item)

  if (!isNewMode.value) {
    // Persist reorder if supported or refresh
    try {
      await guidelineStore.updateStep(item.id, { sort_order: newIndex + 1 })
    } catch {
      // Non-blocking
    }
  }
}

// ── Load Guideline ──────────────────────────────────────────────────────────
const loadGuideline = async () => {
  loading.value = true
  try {
    if (!isNewMode.value) {
      const data = await guidelineStore.fetchGuideline(route.params.id)

      formData.value = {
        id: data.id,
        code: data.code || '',
        name: ensureTranslatable(data.name),
        description: ensureTranslatable(data.description),
        is_active: Boolean(data.is_active),
      }

      steps.value = (data.steps || []).map(s => ({
        ...s,
        instruction: ensureTranslatable(s.instruction),
      }))
    }
  } catch (err) {
    console.error(err)
    showMessage('Failed to load guideline details', 'error')
  } finally {
    loading.value = false
  }
}

// ── Save Guideline ──────────────────────────────────────────────────────────
const handleSave = async () => {
  if (formRefGeneral.value) {
    const { valid } = await formRefGeneral.value.validate()
    if (!valid) {
      activeTab.value = 'base'
      showMessage(t('Please fix errors in Identification'), 'error')
      
      return
    }
  }

  saving.value = true
  try {
    const payload = {
      code: formData.value.code,
      name: formData.value.name,
      description: formData.value.description,
      is_active: formData.value.is_active,
    }

    let saved = null
    if (isNewMode.value) {
      saved = await guidelineStore.createGuideline(payload)

      const guidelineId = saved.data?.id || saved.id

      // Persist any steps added in memory
      for (let i = 0; i < steps.value.length; i++) {
        await guidelineStore.createStep({
          ...steps.value[i],
          guideline_id: guidelineId,
          sort_order: i + 1,
        })
      }

      showMessage(t('Guideline created successfully'), 'success')
      router.push({ name: 'catalog-guidelines-edit-id', params: { id: guidelineId } })
    } else {
      saved = await guidelineStore.updateGuideline(route.params.id, payload)
      showMessage(t('Guideline updated successfully'), 'success')
      await loadGuideline()
    }
  } catch (err) {
    console.error(err)
    showMessage(err?.data?.message || err?.message || 'Error saving guideline', 'error')
  } finally {
    saving.value = false
  }
}

// ── Mobile FAB Speed Dial Actions ──────────────────────────────────────────
const isMobileFabOpen = ref(false)

const dynamicFabActions = computed(() => {
  const actions = [
    {
      id: 'save-guideline',
      title: t('Save Guideline'),
      icon: 'tabler-device-floppy',
      color: 'primary',
      onClick: handleSave,
    },
  ]

  if (activeTab.value === 'steps') {
    actions.unshift({
      id: 'add-step',
      title: t('Add Step'),
      icon: 'tabler-plus',
      color: 'warning',
      onClick: openAddStepDialog,
    })
  }

  return actions
})

// Unsaved changes guard
const isDirty = ref(false)
const { isDialogVisible: isUnsavedDialogVisible, confirmLeave, cancelLeave } = useUnsavedChangesGuard(isDirty)

onMounted(() => {
  loadGuideline()
})
</script>

<template>
  <div class="guidelines-edit-page pb-16">
    <!-- 👉 Loading state -->
    <div
      v-if="loading"
      class="d-flex justify-center align-center py-16"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="48"
      />
    </div>

    <div
      v-else
      class="d-flex flex-column gap-6"
    >
      <!-- ━━━ 1. Breadcrumbs ━━━ -->
      <AppBreadcrumb :items="breadcrumbItems" />

      <!-- ━━━ 2. Entity Identity Header ━━━ -->
      <VCard class="guideline-identity-header pa-2 pa-sm-4 border">
        <div class="d-flex align-center justify-space-between flex-wrap gap-3">
          <!-- Identity -->
          <div class="d-flex align-center gap-3">
            <VAvatar
              color="primary"
              variant="tonal"
              :size="smAndDown ? 32 : 40"
              rounded
            >
              <VIcon
                icon="tabler-clipboard-check"
                size="22"
              />
            </VAvatar>
            <div>
              <div class="d-flex align-center gap-2 flex-wrap">
                <h4 class="text-subtitle-1 text-sm-h5 font-weight-bold mb-0">
                  {{ isNewMode ? $t('New Guideline') : (formData.code || 'Guideline') }}
                </h4>
                <VChip
                  :color="formData.is_active ? 'success' : 'warning'"
                  size="small"
                  label
                >
                  {{ formData.is_active ? 'Active' : 'Inactive' }}
                </VChip>
              </div>
              <span class="text-body-2 text-medium-emphasis">
                {{ localizedValue(formData.name) || 'Standard Operating Procedure & Verification Checklist' }}
              </span>
            </div>
          </div>

          <!-- Header Actions (desktop only) -->
          <div class="d-none d-md-flex align-center gap-2">
            <VBtn
              variant="tonal"
              color="secondary"
              :to="{ name: 'catalog-guidelines-list' }"
            >
              {{ $t('Back') }}
            </VBtn>

            <VBtn
              color="primary"
              variant="flat"
              prepend-icon="tabler-device-floppy"
              :loading="saving"
              @click="handleSave"
            >
              {{ isNewMode ? $t('Create Guideline') : $t('Save Changes') }}
            </VBtn>
          </div>
        </div>
      </VCard>

      <!-- ━━━ 3. Wizard Content with AppHybridTabs ━━━ -->
      <AppHybridTabs
        v-model="activeTab"
        :tabs="tabs"
        header-class="d-none d-md-flex"
        class="guidelines-tabs"
      >
        <!-- ── TAB 1: Base Identification ── -->
        <template #tab-base>
          <VCardText class="pa-4 pa-sm-6">
            <VForm ref="formRefGeneral">
              <VRow>
                <!-- Code -->
                <VCol
                  cols="12"
                  sm="6"
                  md="4"
                >
                  <AppTextField
                    v-model="formData.code"
                    :label="$t('Guideline Code *')"
                    placeholder="e.g. GL-EXT-ANNUAL-01"
                    density="comfortable"
                    hint="Unique procedure code"
                    persistent-hint
                    :rules="[v => !!v || 'Guideline code is required']"
                  />
                </VCol>

                <!-- Active Switch -->
                <VCol
                  cols="12"
                  sm="6"
                  md="4"
                  class="d-flex align-center"
                >
                  <VSwitch
                    v-model="formData.is_active"
                    color="success"
                    :label="formData.is_active ? 'Active Guideline' : 'Inactive Guideline'"
                    density="comfortable"
                  />
                </VCol>

                <!-- Name (MultiLangField) -->
                <VCol cols="12">
                  <MultiLangField
                    v-model="formData.name"
                    :label="$t('Guideline Name *')"
                    placeholder="e.g. Annual Fire Extinguisher Verification Standard"
                    required
                  />
                </VCol>

                <!-- Description (MultiLangField) -->
                <VCol cols="12">
                  <MultiLangField
                    v-model="formData.description"
                    :label="$t('Scope & Description')"
                    placeholder="Detailed specifications, directives, and scope"
                    multiline
                  />
                </VCol>
              </VRow>
            </VForm>
          </VCardText>
        </template>

        <!-- ── TAB 2: Steps & Instructions ── -->
        <template #tab-steps>
          <VCardText class="pa-4 pa-sm-6">
            <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-4">
              <div>
                <h6 class="text-subtitle-1 font-weight-bold mb-0">
                  Verification Procedure Steps
                </h6>
                <span class="text-caption text-medium-emphasis">
                  Ordered instructions displayed to field technicians during on-site inspections
                </span>
              </div>

              <VBtn
                color="primary"
                size="small"
                prepend-icon="tabler-plus"
                class="d-none d-md-inline-flex"
                @click="openAddStepDialog"
              >
                {{ $t('catalog.guidelines.edit.actions.add_step', 'Add Step') }}
              </VBtn>
            </div>

            <!-- Empty state -->
            <div
              v-if="!steps.length"
              class="pa-8 border rounded border-dashed text-center"
            >
              <VAvatar
                color="primary"
                variant="tonal"
                size="48"
                class="mb-3"
              >
                <VIcon
                  icon="tabler-list-numbers"
                  size="24"
                />
              </VAvatar>
              <h6 class="text-subtitle-1 font-weight-bold mb-1">
                No procedure steps defined
              </h6>
              <p class="text-body-2 text-medium-emphasis mb-4">
                Add verification questions, measurements, or physical checks for technicians to execute.
              </p>
              <VBtn
                color="primary"
                size="small"
                variant="tonal"
                prepend-icon="tabler-plus"
                class="d-none d-md-inline-flex"
                @click="openAddStepDialog"
              >
                Add First Step
              </VBtn>
            </div>

            <!-- Steps List -->
            <div
              v-else
              class="d-flex flex-column gap-3"
            >
              <VCard
                v-for="(step, idx) in steps"
                :key="step.id || step._key || idx"
                variant="outlined"
                class="pa-4 rounded"
              >
                <div class="d-flex align-center justify-space-between flex-wrap gap-3">
                  <div class="d-flex align-start gap-3 grow-1 overflow-hidden">
                    <!-- Step order number -->
                    <VAvatar
                      color="primary"
                      variant="tonal"
                      size="36"
                      rounded
                      class="shrink-0 mt-1"
                    >
                      <span class="text-caption font-weight-bold">{{ idx + 1 }}</span>
                    </VAvatar>

                    <div class="overflow-hidden">
                      <p class="text-body-1 font-weight-medium mb-1">
                        {{ localizedValue(step.instruction, '—') }}
                      </p>

                      <div class="d-flex align-center gap-2 flex-wrap">
                        <VChip
                          size="x-small"
                          color="primary"
                          variant="tonal"
                        >
                          Type: {{ step.step_type }}
                        </VChip>

                        <VChip
                          v-if="step.expected_value"
                          size="x-small"
                          variant="outlined"
                        >
                          Expected: {{ step.expected_value }}
                        </VChip>

                        <VChip
                          :color="step.is_required ? 'error' : 'secondary'"
                          size="x-small"
                        >
                          {{ step.is_required ? 'Mandatory' : 'Optional' }}
                        </VChip>
                      </div>
                    </div>
                  </div>

                  <!-- Reorder & Action buttons -->
                  <div class="d-flex align-center gap-1 flex-shrink-0">
                    <IconBtn
                      size="small"
                      :disabled="idx === 0"
                      @click="moveStep(idx, -1)"
                    >
                      <VIcon icon="tabler-chevron-up" />
                    </IconBtn>
                    <IconBtn
                      size="small"
                      :disabled="idx === steps.length - 1"
                      @click="moveStep(idx, 1)"
                    >
                      <VIcon icon="tabler-chevron-down" />
                    </IconBtn>

                    <IconBtn
                      size="small"
                      color="primary"
                      @click="openEditStepDialog(step, idx)"
                    >
                      <VIcon icon="tabler-edit" />
                    </IconBtn>

                    <IconBtn
                      size="small"
                      color="error"
                      variant="text"
                      @click="handleDeleteStep(step, idx)"
                    >
                      <VIcon icon="tabler-trash" />
                    </IconBtn>
                  </div>
                </div>
              </VCard>
            </div>
          </VCardText>
        </template>
      </AppHybridTabs>
    </div>

    <!-- ═══ Mobile Bottom Navigation ═══ -->
    <AppBottomNav
      v-if="smAndDown && !loading"
      v-model="activeTab"
      :items="mobileNavItems"
      fit-content
      :show-badges="false"
      @change="item => { activeTab = item.value }"
    />

    <!-- ═══ Mobile FAB Speed Dial ═══ -->
    <AppFabSpeedDial
      v-if="smAndDown && !loading"
      v-model="isMobileFabOpen"
      :actions="dynamicFabActions"
      icon="tabler-apps"
      activator-rounded="lg"
      class="d-md-none guidelines-fab-quick-actions"
    />

    <!-- ═══ Add/Edit Step Dialog ═══ -->
    <AddEditGuidelineStepDialog
      v-model="isStepDialogOpen"
      :step="selectedStepForDialog"
      :is-edit="isStepEditMode"
      :saving="savingStep"
      @save="handleSaveStep"
    />

    <!-- ═══ Unsaved Changes Guard Dialog ═══ -->
    <WpUnsavedChangesDialog
      v-model="isUnsavedDialogVisible"
      @confirm="confirmLeave"
      @cancel="cancelLeave"
    />
  </div>
</template>

<style lang="scss" scoped>
.guidelines-edit-page {
  @media (max-width: 960px) {
    padding-block-end: calc(96px + env(safe-area-inset-bottom, 0px));
  }
}

.guideline-identity-header {
  border-radius: 12px !important;
}

.guidelines-fab-quick-actions {
  inset-block-end: calc(76px + env(safe-area-inset-bottom, 0px));
  inset-inline-end: 25px;
}
</style>
