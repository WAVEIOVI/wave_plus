<script setup>
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'

defineProps({
  site: { type: Object, default: null },
  form: { type: Object, required: true },
  sections: { type: Array, required: true },
  activeSection: { type: String, default: 'bio' },
  saving: { type: Boolean, default: false },
  isDirty: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
})

defineEmits(['scrollToSection', 'save', 'delete'])
</script>

<template>
  <nav class="journal-nav glass-panel">
    <div class="d-flex align-center mb-3">
      <VAvatar
        rounded="lg"
        :size="38"
        color="primary"
        variant="tonal"
      >
        <img
          v-if="site?.main_photo_url || site?.photos?.[0]?.url"
          :src="site.main_photo_url || site.photos[0].url"
          alt=""
          style="inline-size: 100%; block-size: 100%; object-fit: cover;"
        >
        <VIcon
          v-else
          icon="tabler-building-estate"
          size="20"
        />
      </VAvatar>
      <div class="ms-3 overflow-hidden">
        <h6
          class="text-subtitle-2 font-weight-bold text-truncate mb-0"
          style="max-inline-size: 135px;"
        >
          {{ form.name || site?.site_code }}
        </h6>
        <span class="text-caption text-disabled text-truncate d-block">
          {{ site?.site_code }}
        </span>
      </div>
    </div>

    <VDivider class="mb-3" />

    <PerfectScrollbar
      :options="{ wheelPropagation: false }"
      class="nav-scroll-area"
    >
      <VList
        density="compact"
        nav
        class="bg-transparent pa-0"
      >
        <VListItem
          v-for="s in sections"
          :key="s.id"
          :active="activeSection === s.id"
          color="primary"
          rounded="lg"
          class="mb-1"
          @click="$emit('scrollToSection', s.id)"
        >
          <template #prepend>
            <VIcon
              :icon="s.icon"
              size="18"
            />
          </template>
          <VListItemTitle class="text-body-2 font-weight-medium">
            {{ $t(s.label) }}
          </VListItemTitle>
        </VListItem>
      </VList>
    </PerfectScrollbar>

    <div
      v-if="!readonly"
      class="mt-auto pt-4 border-t"
    >
      <VBtn
        block
        color="primary"
        :disabled="saving || !isDirty"
        :loading="saving"
        prepend-icon="tabler-device-floppy"
        :aria-label="$t('Save Changes')"
        class="mb-2 font-weight-semibold"
        @click="$emit('save')"
      >
        {{ $t('Save Changes') }}
      </VBtn>
      <VBtn
        block
        variant="tonal"
        color="error"
        prepend-icon="tabler-trash"
        :aria-label="$t('Delete Site')"
        class="font-weight-medium"
        @click="$emit('delete')"
      >
        {{ $t('Delete Site') }}
      </VBtn>
    </div>
  </nav>
</template>

<style lang="scss" scoped>
.journal-nav {
  position: sticky;
  display: flex;
  flex: 0 0 240px;
  flex-direction: column;
  inline-size: 240px;
  inset-block-start: 72px;
  max-block-size: calc(100vh - 92px);
  padding-block: 1rem;
  padding-inline: 0.75rem;
}

@media (min-width: 1920px) {
  .journal-nav {
    flex-basis: 260px;
    inline-size: 260px;
  }
}

.nav-scroll-area {
  flex: 1;
  max-block-size: calc(100vh - 300px);
  overflow-y: auto;
}

.glass-panel {
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 16px !important;
  backdrop-filter: blur(20px) saturate(1.8);
  background: rgba(var(--v-theme-surface), 0.7) !important;
  box-shadow:
    0 4px 32px rgba(0, 0, 0, 6%),
    0 1px 4px rgba(0, 0, 0, 4%);
}
</style>
