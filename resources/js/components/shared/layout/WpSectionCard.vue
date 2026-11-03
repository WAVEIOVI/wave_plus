<script setup>
/* eslint-disable lines-around-comment */
/**
 * WpSectionCard
 * ─────────────────────────────────────────────────────────────────────────────
 * A titled section container used to break long forms and detail pages into
 * readable groups (Personal Information, Employment, Contact…).
 *
 * Usage:
 *   <WpSectionCard icon="tabler-user-circle" :title="$t('hr.profile.bio')">
 *     <VRow>…</VRow>
 *   </WpSectionCard>
 *
 *   <WpSectionCard :title="…" :subtitle="…" flat>
 *     …
 *     <template #actions><VBtn …/></template>
 *   </WpSectionCard>
 */
const props = defineProps({
  title: {
    type: String,
    default: '',
  },
  subtitle: {
    type: String,
    default: '',
  },
  icon: {
    type: String,
    default: '',
  },
  iconColor: {
    type: String,
    default: 'primary',
  },
  /** Remove body padding (for tables/lists inside the section). */
  noPadding: {
    type: Boolean,
    default: false,
  },
})

const slots = useSlots()
const hasHeader = computed(() => Boolean(props.title || slots.header))
</script>

<template>
  <VCard
    class="wp-section-card"
    elevation="0"
    border
    rounded="lg"
  >
    <slot name="header">
      <VCardItem
        v-if="hasHeader"
        class="wp-section-card__header pb-2"
      >
        <template
          v-if="props.icon"
          #prepend
        >
          <VAvatar
            :color="props.iconColor"
            variant="tonal"
            rounded="lg"
            size="38"
          >
            <VIcon
              :icon="props.icon"
              size="20"
            />
          </VAvatar>
        </template>

        <VCardTitle class="text-subtitle-1 font-weight-bold">
          {{ props.title }}
        </VCardTitle>
        <VCardSubtitle v-if="props.subtitle">
          {{ props.subtitle }}
        </VCardSubtitle>

        <template
          v-if="$slots.actions"
          #append
        >
          <div class="wp-section-card__actions d-flex align-center gap-2 flex-wrap">
            <slot name="actions" />
          </div>
        </template>
      </VCardItem>
    </slot>

    <VCardText :class="props.noPadding ? 'pa-0 pt-0' : 'pt-3'">
      <slot />
    </VCardText>
  </VCard>
</template>

<style scoped>
.wp-section-card {
  border-radius: 8px !important;
}

.wp-section-card__header :deep(.v-card-item__content) {
  min-inline-size: 0;
}

@media (max-width: 600px) {
  .wp-section-card__header {
    flex-wrap: wrap;
    gap: 0.5rem;
  }

  .wp-section-card__header :deep(.v-card-item__append) {
    inline-size: 100%;
    margin-inline-start: 0 !important;
    padding-block-start: 0.25rem;
    justify-content: flex-start;
  }

  .wp-section-card__actions {
    inline-size: 100%;
    flex-wrap: wrap;
  }
}
</style>
