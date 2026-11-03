<script setup>
/**
 * WorkwearModelNavSidebar
 * ─────────────────────────────────────────────────────────────────────────────
 * Desktop sticky navigation for the Workwear Model journal page.
 *
 * Contract is unchanged (props + navigate / save / createSnapshot / delete).
 * Additions are optional: a `back` emit and a per-section `badge` count.
 * Labels may be raw i18n keys or already translated strings.
 */
import { useI18n } from 'vue-i18n'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'

defineProps({
  modelData: { type: Object, required: true },
  sections: { type: Array, required: true }, // [{ id, label, icon, badge? }]
  activeSection: { type: String, default: 'bio' },
  saving: { type: Boolean, default: false },
  isDirty: { type: Boolean, default: false },
})

const emit = defineEmits([
  'navigate',
  'save',
  'createSnapshot',
  'delete',
  'back',
])

const { t, te } = useI18n()

const labelOf = label => (te(label) ? t(label) : label)
</script>

<template>
  <nav
    class="journal-nav glass-panel"
    :aria-label="$t('Model sections')"
  >
    <!-- Identity -->
    <div class="d-flex align-center mb-4">
      <VAvatar
        rounded
        :size="44"
        color="primary"
        variant="tonal"
        class="me-3 flex-shrink-0"
      >
        <VIcon
          icon="tabler-shirt"
          size="24"
        />
      </VAvatar>
      <div class="min-w-0">
        <h6 class="text-subtitle-1 font-weight-bold text-truncate mb-0">
          {{ modelData.name || modelData.model_ref }}
        </h6>
        <span class="text-caption text-disabled font-mono text-truncate d-block">
          {{ modelData.model_ref }}
        </span>
      </div>
    </div>

    <VDivider class="mb-3" />

    <!-- Sections -->
    <PerfectScrollbar
      :options="{ wheelPropagation: false }"
      class="nav-scroll-area"
    >
      <VList
        density="compact"
        nav
        class="pa-0 bg-transparent"
      >
        <VListItem
          v-for="s in sections"
          :key="s.id"
          :active="activeSection === s.id"
          :aria-current="activeSection === s.id ? 'true' : undefined"
          color="primary"
          rounded="lg"
          @click="emit('navigate', s.id)"
        >
          <template #prepend>
            <VIcon
              :icon="s.icon"
              size="18"
            />
          </template>
          <VListItemTitle class="text-body-2 font-weight-medium">
            {{ labelOf(s.label) }}
          </VListItemTitle>
          <template
            v-if="s.badge"
            #append
          >
            <VChip
              size="x-small"
              variant="tonal"
              :color="activeSection === s.id ? 'primary' : 'secondary'"
              class="font-mono"
            >
              {{ s.badge }}
            </VChip>
          </template>
        </VListItem>
      </VList>
    </PerfectScrollbar>

    <!-- Actions -->
    <div class="mt-auto pt-4">
      <VDivider class="mb-4" />

      <div
        v-if="isDirty"
        class="d-flex align-center gap-2 mb-3 text-caption text-warning"
        role="status"
      >
        <VIcon
          icon="tabler-point-filled"
          size="16"
        />
        {{ $t('Unsaved changes') }}
      </div>

      <VBtn
        block
        color="primary"
        :loading="saving"
        :disabled="saving || !isDirty"
        class="mb-2"
        @click="emit('save')"
      >
        <VIcon
          start
          icon="tabler-device-floppy"
        />
        {{ $t('Save Changes') }}
      </VBtn>
      <VBtn
        block
        variant="tonal"
        color="info"
        class="mb-2"
        @click="emit('createSnapshot')"
      >
        <VIcon
          start
          icon="tabler-camera"
        />
        {{ $t('Create Snapshot') }}
      </VBtn>
      <VBtn
        block
        variant="tonal"
        color="secondary"
        class="mb-2"
        @click="emit('back')"
      >
        <VIcon
          start
          icon="tabler-arrow-left"
        />
        {{ $t('Back to List') }}
      </VBtn>
      <VBtn
        block
        variant="text"
        color="error"
        size="small"
        @click="emit('delete')"
      >
        <VIcon
          start
          icon="tabler-trash"
        />
        {{ $t('Delete Model') }}
      </VBtn>
    </div>
  </nav>
</template>

<style lang="scss" scoped>
.glass-panel {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 16px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.7) !important;
  box-shadow:
    0 4px 32px rgba(0, 0, 0, 6%),
    0 1px 4px rgba(0, 0, 0, 4%),
    inset 0 1px 0 rgba(255, 255, 255, 6%);
}

.journal-nav {
  position: sticky;
  display: flex;
  flex: 0 0 240px;
  flex-direction: column;
  padding: 1.25rem;
  inline-size: 240px;
  inset-block-start: 80px;
  max-block-size: calc(100vh - 100px);
}

.nav-scroll-area {
  flex: 1;
  max-block-size: calc(100vh - 380px);
  min-block-size: 120px;
}
</style>
