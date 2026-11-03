<script setup>
import { WpJournalNav } from '@/components/shared'
import { localizedValue } from '@/composables/useLocaleField'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  isDialogVisible: { type: Boolean, default: false },
  guideline: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'update:isDialogVisible'])

const { t } = useI18n()
const { smAndDown } = useDisplay()

const visible = computed({
  get: () => props.modelValue || props.isDialogVisible,
  set: val => {
    emit('update:modelValue', val)
    emit('update:isDialogVisible', val)
  },
})

const activeSection = ref('base')

const sections = computed(() => [
  { id: 'base', label: t('catalog.guidelines.edit.tabs.base'), icon: 'tabler-info-circle' },
  { id: 'steps', label: t('catalog.guidelines.edit.tabs.steps'), icon: 'tabler-list-numbers' },
])

watch(visible, val => {
  if (val) activeSection.value = 'base'
})
</script>

<template>
  <VDialog
    v-model="visible"
    :max-width="smAndDown ? undefined : 960"
    :fullscreen="smAndDown"
    scrollable
  >
    <VCard
      v-if="guideline"
      class="guideline-view-dialog"
      :class="{ 'rounded-0': smAndDown }"
    >
      <VCardTitle class="d-flex justify-space-between align-center pa-3 pa-sm-4 bg-surface border-b flex-wrap gap-2">
        <div
          class="d-flex align-center gap-3 overflow-hidden"
          style="min-inline-size: 0;"
        >
          <VAvatar
            rounded
            size="38"
            variant="tonal"
            color="primary"
            class="flex-shrink-0"
          >
            <VIcon
              icon="tabler-clipboard-check"
              size="20"
            />
          </VAvatar>
          <div class="overflow-hidden">
            <h5 class="text-subtitle-1 font-weight-bold mb-0 text-truncate">
              {{ localizedValue(guideline.name, t('catalog.guidelines.list.title')) }}
            </h5>
            <span class="text-caption text-disabled d-block">
              {{ guideline.code }}
            </span>
          </div>
        </div>

        <div class="d-flex align-center gap-1 flex-shrink-0">
          <VBtn
            v-if="guideline.id"
            variant="tonal"
            size="small"
            color="secondary"
            :icon="smAndDown ? 'tabler-edit' : undefined"
            :prepend-icon="!smAndDown ? 'tabler-edit' : undefined"
            :to="{ name: 'catalog-guidelines-edit-id', params: { id: guideline.id } }"
            :title="t('catalog.guidelines.list.actions.edit')"
          >
            <template v-if="!smAndDown">
              {{ t('catalog.guidelines.list.actions.edit') }}
            </template>
          </VBtn>
          <VBtn
            icon
            variant="text"
            size="small"
            @click="visible = false"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </div>
      </VCardTitle>

      <VDivider />

      <VCardText
        class="pa-0"
        :style="smAndDown ? undefined : 'max-block-size: 80vh; overflow-y: auto;'"
      >
        <div class="view-layout">
          <!-- ═══ Navigation: Desktop Sticky Sidebar + Mobile Bottom Navigation ═══ -->
          <WpJournalNav
            v-model="activeSection"
            :sections="sections"
            is-dialog
            id-prefix="guideline-view-"
            container=".v-card-text"
          />

          <main class="view-feed">
            <section
              id="guideline-view-base"
              class="guideline-view-section"
            >
              <VCard variant="outlined" rounded="md">
                <VCardText>
                  <h6 class="text-subtitle-1 font-weight-bold mb-4 d-flex align-center gap-2">
                    <VIcon
                      icon="tabler-info-circle"
                      size="20"
                      color="primary"
                    />
                    {{ t('catalog.guidelines.edit.tabs.base') }}
                  </h6>
                  <VRow dense>
                    <VCol cols="12" sm="6">
                      <label class="text-caption text-disabled">{{ t('catalog.guidelines.list.table.code') }}</label>
                      <p class="text-body-1 font-weight-medium mb-0">
                        {{ guideline.code || '—' }}
                      </p>
                    </VCol>
                    <VCol cols="12" sm="6">
                      <label class="text-caption text-disabled">{{ t('catalog.guidelines.list.table.status') }}</label>
                      <div>
                        <VChip
                          :color="guideline.is_active ? 'success' : 'warning'"
                          size="small"
                          label
                        >
                          {{ guideline.is_active ? t('catalog.guidelines.list.filter.active', 'Active') : t('catalog.guidelines.list.filter.inactive', 'Inactive') }}
                        </VChip>
                      </div>
                    </VCol>
                    <VCol cols="12">
                      <label class="text-caption text-disabled">{{ t('catalog.guidelines.list.table.name') }}</label>
                      <p class="text-body-1 mb-0">
                        {{ localizedValue(guideline.name, '—') }}
                      </p>
                    </VCol>
                    <VCol cols="12">
                      <label class="text-caption text-disabled">{{ t('catalog.guidelines.edit.scope', 'Scope & Description') }}</label>
                      <p class="text-body-2 mb-0">
                        {{ localizedValue(guideline.description, '—') }}
                      </p>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </section>

            <section
              id="guideline-view-steps"
              class="guideline-view-section"
            >
              <VCard variant="outlined" rounded="md">
                <VCardText>
                  <h6 class="text-subtitle-1 font-weight-bold mb-4 d-flex align-center gap-2">
                    <VIcon
                      icon="tabler-list-numbers"
                      size="20"
                      color="primary"
                    />
                    {{ t('catalog.guidelines.edit.tabs.steps') }}
                    <VChip size="x-small" color="primary" variant="tonal">
                      {{ guideline.steps?.length || 0 }}
                    </VChip>
                  </h6>

                  <div
                    v-if="!guideline.steps?.length"
                    class="pa-6 border rounded border-dashed text-center text-body-2 text-medium-emphasis"
                  >
                    {{ t('catalog.guidelines.edit.empty_steps', 'No steps defined in this guideline.') }}
                  </div>

                  <div
                    v-else
                    class="d-flex flex-column gap-2"
                  >
                    <div
                      v-for="(step, idx) in guideline.steps"
                      :key="step.id || idx"
                      class="d-flex align-start gap-3 pa-3 border rounded"
                    >
                      <VAvatar
                        color="primary"
                        variant="tonal"
                        size="28"
                        rounded
                      >
                        <span class="text-caption font-weight-bold">{{ idx + 1 }}</span>
                      </VAvatar>
                      <div class="min-w-0 flex-grow-1">
                        <p class="text-body-2 font-weight-medium mb-1">
                          {{ localizedValue(step.instruction, '—') }}
                        </p>
                        <div class="d-flex flex-wrap gap-1">
                          <VChip size="x-small" color="primary" variant="tonal">
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
                          >
                            {{ step.is_required ? t('catalog.guidelines.edit.step.mandatory', 'Mandatory') : t('catalog.guidelines.edit.step.optional', 'Optional') }}
                          </VChip>
                        </div>
                      </div>
                    </div>
                  </div>
                </VCardText>
              </VCard>
            </section>
          </main>
        </div>
      </VCardText>

    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.view-layout {
  display: flex;
  align-items: flex-start;
  gap: 1.25rem;
  padding: 1.25rem;

  @media (max-width: 960px) {
    flex-direction: column;
    padding: 1rem;
    padding-block-end: calc(76px + env(safe-area-inset-bottom, 0px));
  }
}

.view-nav {
  position: sticky;
  flex: 0 0 220px;
  padding: 0.75rem;
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 12px;
  inset-block-start: 16px;
  background: rgba(var(--v-theme-surface), 0.85);
}

.view-feed {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 1.25rem;
  min-inline-size: 0;
}

.guideline-view-section {
  scroll-margin-top: 16px;
}

:deep(.app-bottom-nav) {
  z-index: 2500 !important;
}
</style>
