<script setup>
import { localizedValue } from '@/composables/useLocaleField'
import {
  operatingStatusOptions,
  riskClassOptions,
  resolveRiskVariant,
  resolveSiteStatusVariant,
  useSiteConstants,
} from '@/composables/client-assets/sites/useSiteConstants'
import { useDisplay } from 'vuetify'

defineProps({
  site: { type: Object, default: null },
  form: { type: Object, required: true },
  isDirty: { type: Boolean, default: false },
})

const { smAndDown } = useDisplay()
const { optionTitle } = useSiteConstants()
</script>

<template>
  <header class="site-edit-header">
    <div class="d-flex align-start gap-3 min-w-0 w-100">
      <VAvatar
        color="primary"
        variant="tonal"
        rounded="lg"
        :size="smAndDown ? 40 : 50"
        class="site-header-avatar"
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
          :size="smAndDown ? 22 : 28"
        />
      </VAvatar>

      <div class="min-w-0 flex-grow-1">
        <h1 class="site-header-title text-high-emphasis mb-0">
          {{ form.name || site?.site_code || $t('Edit Client Site') }}
        </h1>
        <p class="text-caption text-medium-emphasis mb-0 text-truncate">
          {{ site?.client?.legal_name || $t('Client Facility Location') }} • {{ localizedValue(site?.address?.city) || $t('Location') }}
        </p>
      </div>
    </div>

    <!-- Chips Row: Spans full width so chips never overflow on mobile (375px) -->
    <div class="d-flex align-center gap-1 gap-sm-2 flex-wrap mt-2">
      <span class="text-overline text-primary font-weight-bold d-none d-sm-inline">{{ $t('Client Assets') }}</span>
      <VChip
        v-if="site?.site_code"
        size="x-small"
        color="primary"
        variant="tonal"
        class="font-weight-semibold"
      >
        {{ site.site_code }}
      </VChip>
      <VChip
        size="x-small"
        :color="resolveSiteStatusVariant(form.status)"
        variant="tonal"
        class="text-capitalize"
      >
        {{ optionTitle(operatingStatusOptions, form.status) }}
      </VChip>
      <VChip
        size="x-small"
        :color="resolveRiskVariant(form.risk_class)"
        variant="tonal"
        class="text-capitalize"
      >
        {{ optionTitle(riskClassOptions, form.risk_class) }}
      </VChip>
      <VChip
        v-if="isDirty"
        size="x-small"
        color="warning"
        variant="tonal"
        prepend-icon="tabler-pencil"
        class="font-weight-medium"
      >
        <span>{{ $t('Unsaved changes') }}</span>
      </VChip>
    </div>
  </header>
</template>

<style lang="scss" scoped>
.site-edit-header {
  position: relative;
  background: rgba(var(--v-theme-surface), 0.96) !important;
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 14px !important;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
  padding: 1rem 1.25rem;
  margin-block-end: 1rem;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  overflow: hidden;

  @media (max-width: 600px) {
    padding: 0.75rem 0.875rem;
    border-radius: 12px !important;
  }
}

.site-header-avatar {
  flex-shrink: 0;
}

.site-header-title {
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.3;
  overflow-wrap: anywhere;
  word-break: break-word;
}

@media (max-width: 599px) {
  .site-header-title {
    font-size: 1.05rem;
    line-height: 1.25;
  }
}
</style>
