<script setup>
/* eslint-disable lines-around-comment */
/**
 * WpDocumentHeader
 * ─────────────────────────────────────────────────────────────────────────────
 * Header of a business document page (IPR, RFQ, PO, GRN…): breadcrumbs,
 * copyable reference, status / version / priority badges, one primary workflow
 * action and an overflow menu of secondary actions.
 *
 * Presentational only: actions are plain objects and are emitted back.
 *
 *   actions: [{ key, label, icon, color, disabled, loading, hidden }]
 *   primaryAction: same shape — rendered as the main button
 *
 * Emits  action(key, action)   for the primary and every secondary action.
 * On `smAndDown` the secondary actions open in the shared AppQuickActionsSheet.
 */
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'

const props = defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  reference: { type: [String, Number], default: '' },
  breadcrumbs: { type: Array, default: () => [] },
  status: { type: [String, Number], default: '' },
  statusType: { type: String, default: '' },
  statusLabel: { type: String, default: '' },
  version: { type: [String, Number], default: '' },

  /** Raw priority / urgency value (e.g. `urgent`). */
  priority: { type: String, default: '' },
  priorityLabel: { type: String, default: '' },

  /** Vuetify colour per priority value. */
  priorityColors: {
    type: Object,
    default: () => ({
      low: 'secondary',
      normal: 'info',
      medium: 'info',
      high: 'warning',
      urgent: 'warning',
      critical: 'error',
    }),
  },
  primaryAction: { type: Object, default: null },
  actions: { type: Array, default: () => [] },
  sheetTitle: { type: String, default: '' },
})

const emit = defineEmits(['action'])

const { t } = useI18n()
const { smAndDown } = useDisplay()

const sheetOpen = ref(false)

const visibleActions = computed(() => (props.actions || []).filter(a => a && !a.hidden))
const primary = computed(() => (props.primaryAction && !props.primaryAction.hidden ? props.primaryAction : null))
const priorityColor = computed(() => props.priorityColors?.[props.priority] || 'secondary')
const hasDanger = action => action.color === 'error'

const trigger = action => {
  if (!action || action.disabled || action.loading) return
  emit('action', action.key, action)
}

const sheetActions = computed(() => visibleActions.value.map(a => ({
  id: a.key,
  title: a.label,
  icon: a.icon || 'tabler-dots',
  color: a.color || 'primary',
  disabled: Boolean(a.disabled || a.loading),
})))

const onSheetAction = sheetAction => {
  const id = sheetAction?.id ?? sheetAction
  const action = visibleActions.value.find(a => a.key === id)

  sheetOpen.value = false
  trigger(action)
}
</script>

<template>
  <header class="wp-document-header">
    <AppBreadcrumb
      v-if="breadcrumbs.length"
      :items="breadcrumbs"
      class="mb-1"
    />

    <div class="wp-document-header__row">
      <div class="wp-document-header__main">
        <div class="d-flex align-center flex-wrap gap-2">
          <h1 class="text-h5 font-weight-bold mb-0 wp-document-header__title">
            {{ title || reference }}
          </h1>

          <VChip
            v-if="reference && title"
            size="small"
            label
            variant="tonal"
            color="primary"
            class="wp-document-header__reference"
          >
            <span class="text-truncate">{{ reference }}</span>
            <WpCopyButton
              :value="reference"
              :label="t('wp.docs.header.copy_reference')"
            />
          </VChip>
          <WpCopyButton
            v-else-if="reference"
            :value="reference"
            :label="t('wp.docs.header.copy_reference')"
          />
        </div>

        <div class="d-flex align-center flex-wrap gap-2 mt-2">
          <WpStatusChip
            v-if="status"
            :status="status"
            :type="statusType"
            :label="statusLabel"
            dot
          />
          <VChip
            v-if="version !== '' && version !== null && version !== undefined"
            size="small"
            label
            variant="outlined"
            color="secondary"
          >
            {{ t('wp.docs.header.version', { version }) }}
          </VChip>
          <VChip
            v-if="priority"
            size="small"
            label
            variant="tonal"
            :color="priorityColor"
            prepend-icon="tabler-flag"
          >
            {{ priorityLabel || priority }}
          </VChip>
          <slot name="badges" />
        </div>

        <p
          v-if="subtitle"
          class="text-body-2 text-medium-emphasis mt-1 mb-0"
        >
          {{ subtitle }}
        </p>
      </div>

      <div class="wp-document-header__actions">
        <slot name="actions-prepend" />

        <VBtn
          v-if="primary"
          :color="primary.color || 'primary'"
          :prepend-icon="primary.icon"
          :disabled="primary.disabled"
          :loading="primary.loading"
          :block="smAndDown"
          min-height="44"
          @click="trigger(primary)"
        >
          {{ primary.label }}
        </VBtn>

        <template v-if="visibleActions.length">
          <VBtn
            v-if="smAndDown"
            variant="tonal"
            color="secondary"
            icon="tabler-dots"
            min-height="44"
            min-width="44"
            :aria-label="sheetTitle || t('wp.docs.header.more_actions')"
            @click="sheetOpen = true"
          />

          <VMenu
            v-else
            location="bottom end"
          >
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                variant="tonal"
                color="secondary"
                icon="tabler-dots-vertical"
                :aria-label="sheetTitle || t('wp.docs.header.more_actions')"
              />
            </template>
            <VList density="comfortable">
              <VListItem
                v-for="action in visibleActions"
                :key="action.key"
                :disabled="action.disabled || action.loading"
                :class="{ 'text-error': hasDanger(action) }"
                @click="trigger(action)"
              >
                <template
                  v-if="action.icon"
                  #prepend
                >
                  <VIcon
                    :icon="action.icon"
                    size="20"
                    :color="action.color"
                  />
                </template>
                <VListItemTitle>{{ action.label }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </template>
      </div>
    </div>

    <AppQuickActionsSheet
      v-if="smAndDown && visibleActions.length"
      v-model="sheetOpen"
      :title="sheetTitle || t('wp.docs.header.more_actions')"
      :actions="sheetActions"
      @action-click="onSheetAction"
    />
  </header>
</template>

<style scoped>
.wp-document-header {
  min-inline-size: 0;
}

.wp-document-header__row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px 16px;
}

.wp-document-header__main {
  flex: 1 1 280px;
  min-inline-size: 0;
}

.wp-document-header__title {
  overflow-wrap: anywhere;
}

.wp-document-header__reference {
  max-inline-size: 100%;
}

.wp-document-header__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

@media (max-width: 959px) {
  .wp-document-header__actions {
    flex: 1 1 100%;
    flex-wrap: nowrap;
  }

  .wp-document-header__actions > .v-btn:first-of-type:not([aria-label]) {
    flex: 1 1 auto;
  }
}
</style>
