<script setup>
import { WpSearchSelectDialog } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { useGuidelineStore } from '@/stores/useGuidelineStore'
import { useSnackbarStore } from '@/stores/useSnackbarStore'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  blueprintId: {
    type: [String, Number],
    required: true,
  },
  readOnly: {
    type: Boolean,
    default: false,
  },
})

const { t } = useI18n()
const guidelineStore = useGuidelineStore()
const snackbarStore = useSnackbarStore()

const loading = ref(false)
const saving = ref(false)
const attachedGuidelines = ref([])
const expandedGuidelineId = ref(null)
const isSearchModalOpen = ref(false)

const fetchAttached = async () => {
  loading.value = true
  try {
    const res = await guidelineStore.fetchBlueprintGuidelines(props.blueprintId)
    attachedGuidelines.value = res?.data || []
  } catch (err) {
    console.error('Error fetching attached guidelines:', err)
  } finally {
    loading.value = false
  }
}

const attachedIds = computed(() => new Set(attachedGuidelines.value.map(g => g.id)))

const guidelineFetcher = async ({ page, perPage, search, signal }) => {
  const res = await guidelineStore.fetchGuidelines({
    page,
    itemsPerPage: perPage,
    q: search,
    'filter[is_active]': 1,
  }, { signal })

  return {
    items: res?.data || [],
    total: res?.total || 0,
  }
}

const handleAttach = async guideline => {
  if (!guideline?.id) return
  if (attachedIds.value.has(guideline.id)) {
    snackbarStore.showMessage(t('catalog.blueprints.edit.guidelines.already_attached', 'Guideline is already attached.'), 'info')
    isSearchModalOpen.value = false

    return
  }

  saving.value = true
  try {
    await guidelineStore.attachToBlueprint(props.blueprintId, guideline.id)
    snackbarStore.showMessage(t('catalog.blueprints.edit.guidelines.attach_success', 'Guideline attached successfully'), 'success')
    isSearchModalOpen.value = false
    await fetchAttached()
  } catch {
    snackbarStore.showMessage(t('catalog.blueprints.edit.guidelines.attach_error', 'Failed to attach guideline'), 'error')
  } finally {
    saving.value = false
  }
}

const handleDetach = async guidelineId => {
  saving.value = true
  try {
    await guidelineStore.detachFromBlueprint(props.blueprintId, guidelineId)
    snackbarStore.showMessage(t('catalog.blueprints.edit.guidelines.detach_success', 'Guideline detached'), 'success')
    await fetchAttached()
  } catch {
    snackbarStore.showMessage(t('catalog.blueprints.edit.guidelines.detach_error', 'Failed to detach guideline'), 'error')
  } finally {
    saving.value = false
  }
}

const toggleExpand = id => {
  expandedGuidelineId.value = expandedGuidelineId.value === id ? null : id
}

const openAttachDialog = () => {
  isSearchModalOpen.value = true
}

defineExpose({ openAttachDialog })

onMounted(async () => {
  await fetchAttached()
})
</script>

<template>
  <div class="blueprint-guidelines-section">
    <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-4">
      <div class="d-flex align-center gap-3">
        <VAvatar
          color="primary"
          variant="tonal"
          size="40"
          rounded="lg"
        >
          <VIcon
            icon="tabler-checklist"
            size="22"
          />
        </VAvatar>
        <div>
          <div class="d-flex align-center gap-2">
            <h6 class="text-subtitle-1 font-weight-bold mb-0">
              {{ $t('catalog.blueprints.edit.guidelines.title', 'Maintenance Guidelines') }}
            </h6>
            <VChip
              size="x-small"
              color="primary"
              variant="tonal"
            >
              {{ attachedGuidelines.length }}
            </VChip>
          </div>
          <span class="text-caption text-medium-emphasis">
            {{ $t('catalog.blueprints.edit.guidelines.subtitle', 'Standard operating procedures linked to this blueprint') }}
          </span>
        </div>
      </div>

      <VBtn
        v-if="!readOnly && $can('update', 'Catalog')"
        color="primary"
        size="small"
        prepend-icon="tabler-link"
        class="d-none d-md-inline-flex"
        @click="openAttachDialog"
      >
        {{ $t('catalog.blueprints.edit.guidelines.attach', 'Attach Guideline') }}
      </VBtn>
    </div>

    <VSkeletonLoader
      v-if="loading"
      type="list-item@3"
    />

    <div
      v-else-if="attachedGuidelines.length === 0"
      class="guidelines-empty-state pa-8 border rounded-lg border-dashed text-center"
    >
      <VAvatar
        color="primary"
        variant="tonal"
        size="64"
        class="mb-4"
        rounded="lg"
      >
        <VIcon
          icon="tabler-checklist"
          size="32"
        />
      </VAvatar>
      <h6 class="text-h6 font-weight-bold mb-2">
        {{ $t('catalog.blueprints.edit.guidelines.empty_title', 'No guidelines attached') }}
      </h6>
      <p
        class="text-body-2 text-medium-emphasis mb-4 mx-auto"
        style="max-inline-size: 420px;"
      >
        {{ $t('catalog.blueprints.edit.guidelines.empty_text', 'Link maintenance checklists and verification protocols for technicians.') }}
      </p>
      <VBtn
        v-if="!readOnly && $can('update', 'Catalog')"
        color="primary"
        prepend-icon="tabler-link"
        class="d-none d-md-inline-flex"
        @click="openAttachDialog"
      >
        {{ $t('catalog.blueprints.edit.guidelines.attach_first', 'Attach First Guideline') }}
      </VBtn>
    </div>

    <div
      v-else
      class="d-flex flex-column gap-3"
    >
      <VCard
        v-for="g in attachedGuidelines"
        :key="g.id"
        variant="outlined"
        rounded="lg"
        class="guideline-card"
      >
        <VCardItem class="py-3 px-4 bg-var-theme-background">
          <template #prepend>
            <VAvatar
              color="primary"
              variant="flat"
              size="38"
              rounded="lg"
              class="me-2"
            >
              <VIcon
                icon="tabler-checklist"
                size="20"
              />
            </VAvatar>
          </template>

          <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-2">
            <div class="d-flex align-center gap-2 flex-wrap">
              <span class="text-subtitle-2 font-weight-bold">
                {{ localizedValue(g.name, 'Untitled Guideline') }}
              </span>
              <VChip
                size="x-small"
                label
                color="secondary"
                variant="outlined"
                class="guideline-code-chip"
              >
                {{ g.code }}
              </VChip>
              <VChip
                size="x-small"
                label
                color="info"
                variant="tonal"
                prepend-icon="tabler-list-numbers"
              >
                {{ g.steps?.length || 0 }} {{ $t('catalog.guidelines.list.table.steps') }}
              </VChip>
            </div>

            <div class="d-flex align-center gap-1">
              <VBtn
                size="x-small"
                variant="tonal"
                color="primary"
                :append-icon="expandedGuidelineId === g.id ? 'tabler-chevron-up' : 'tabler-chevron-down'"
                @click="toggleExpand(g.id)"
              >
                {{ expandedGuidelineId === g.id
                  ? $t('catalog.blueprints.edit.guidelines.hide_steps', 'Hide Steps')
                  : $t('catalog.blueprints.edit.guidelines.view_steps', 'View Steps') }}
              </VBtn>
              <IconBtn
                v-if="!readOnly && $can('update', 'Catalog')"
                size="small"
                color="error"
                variant="text"
                :title="$t('catalog.blueprints.edit.guidelines.detach', 'Detach Guideline')"
                @click="handleDetach(g.id)"
              >
                <VIcon
                  icon="tabler-unlink"
                  size="18"
                />
                <VTooltip activator="parent">
                  {{ $t('catalog.blueprints.edit.guidelines.detach', 'Detach Guideline') }}
                </VTooltip>
              </IconBtn>
            </div>
          </VCardTitle>

          <VCardSubtitle
            v-if="g.description"
            class="pt-1"
          >
            {{ localizedValue(g.description) }}
          </VCardSubtitle>
        </VCardItem>

        <VExpandTransition>
          <div v-if="expandedGuidelineId === g.id">
            <VDivider />
            <div class="pa-4 bg-surface">
              <div
                v-if="!g.steps?.length"
                class="text-caption text-disabled text-center py-2"
              >
                {{ $t('catalog.blueprints.edit.guidelines.no_steps', 'No steps registered in this guideline.') }}
              </div>
              <div
                v-else
                class="d-flex flex-column gap-2"
              >
                <div
                  v-for="(step, idx) in g.steps"
                  :key="step.id || idx"
                  class="guideline-step d-flex align-start gap-3 pa-3 border rounded-lg"
                >
                  <VAvatar
                    color="primary"
                    variant="tonal"
                    size="28"
                    rounded="lg"
                    class="flex-shrink-0"
                  >
                    <span class="text-caption font-weight-bold">{{ idx + 1 }}</span>
                  </VAvatar>
                  <div class="min-w-0 flex-grow-1">
                    <p class="text-body-2 font-weight-medium mb-1">
                      {{ localizedValue(step.instruction, '—') }}
                    </p>
                    <div class="d-flex flex-wrap gap-1">
                      <VChip
                        size="x-small"
                        color="primary"
                        variant="tonal"
                      >
                        {{ step.step_type }}
                      </VChip>
                      <VChip
                        v-if="step.expected_value"
                        size="x-small"
                        variant="outlined"
                      >
                        {{ step.expected_value }}
                      </VChip>
                      <VChip
                        size="x-small"
                        :color="step.is_required ? 'error' : 'secondary'"
                        variant="tonal"
                        :prepend-icon="step.is_required ? 'tabler-alert-circle' : 'tabler-circle-dashed'"
                      >
                        {{ step.is_required ? $t('catalog.guidelines.edit.step.mandatory', 'Mandatory') : $t('catalog.guidelines.edit.step.optional', 'Optional') }}
                      </VChip>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </VExpandTransition>
      </VCard>
    </div>

    <WpSearchSelectDialog
      v-if="!readOnly"
      v-model="isSearchModalOpen"
      :title="$t('catalog.blueprints.edit.guidelines.search_title', 'Attach Maintenance Guideline')"
      entity-label="guideline"
      icon="tabler-checklist"
      :fetcher="guidelineFetcher"
      :is-disabled="item => attachedIds.has(item.id)"
      confirm-behavior="immediate"
      default-view="list"
      :columns="[
        { key: 'code', title: $t('catalog.guidelines.list.table.code'), sortable: true },
        { key: 'name', title: $t('catalog.guidelines.list.table.name'), sortable: true },
        { key: 'steps_count', title: $t('catalog.guidelines.list.table.steps') },
      ]"
      :display="{
        title: item => localizedValue(item.name, item.code),
        subtitle: item => item.code,
        chips: [item => ({
          label: `${item.steps_count ?? item.steps?.length ?? 0} ${$t('catalog.guidelines.list.table.steps')}`,
          color: 'primary',
        })],
      }"
      @select="handleAttach"
    />
  </div>
</template>

<style lang="scss" scoped>
.guidelines-empty-state {
  background: rgba(var(--v-theme-on-surface), 0.02);
}

.guideline-card {
  transition: box-shadow 0.2s ease;

  &:hover {
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08) !important;
  }
}

.guideline-code-chip {
  font-family: 'Roboto Mono', ui-monospace, monospace;
  font-size: 0.7rem !important;
}

.guideline-step {
  background: rgba(var(--v-theme-on-surface), 0.02);
  transition: background 0.12s ease;

  &:hover {
    background: rgba(var(--v-theme-primary), 0.04);
  }
}
</style>
