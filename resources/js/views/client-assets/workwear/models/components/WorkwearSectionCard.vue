<script setup>
/**
 * WorkwearSectionCard
 * ─────────────────────────────────────────────────────────────────────────────
 * Single wrapper for every section of the Workwear Model journal page.
 * Owns the anchor (`section-<id>`), the scroll margin and the header pattern
 * so the eight sections stay visually identical and the editor's
 * IntersectionObserver / scrollToSection keep working unchanged.
 *
 * Slots: default (body) · chips (next to the title) · actions (header buttons)
 */
defineProps({
  id: { type: String, required: true }, // without the `section-` prefix
  icon: { type: String, required: true },
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
})
</script>

<template>
  <section
    :id="`section-${id}`"
    class="journal-section"
  >
    <VCard>
      <VCardText class="pa-4 pa-sm-6">
        <header class="section-head">
          <VAvatar
            rounded
            size="40"
            color="primary"
            variant="tonal"
            class="section-head__icon"
          >
            <VIcon
              :icon="icon"
              size="22"
            />
          </VAvatar>

          <div class="section-head__text">
            <div class="d-flex flex-wrap align-center gap-2">
              <h2 class="text-h6 mb-0">
                {{ title }}
              </h2>
              <slot name="chips" />
            </div>
            <p
              v-if="subtitle"
              class="text-body-2 text-medium-emphasis mb-0 mt-1"
            >
              {{ subtitle }}
            </p>
          </div>

          <div
            v-if="$slots.actions"
            class="section-head__actions"
          >
            <slot name="actions" />
          </div>
        </header>

        <slot />
      </VCardText>
    </VCard>
  </section>
</template>

<style lang="scss" scoped>
.journal-section {
  scroll-margin-block-start: 80px;

  @media (max-width: 960px) {
    scroll-margin-block-start: 16px;
  }
}

.section-head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  margin-block-end: 1.25rem;
  gap: 0.75rem 1rem;

  &__icon {
    flex-shrink: 0;
  }

  &__text {
    flex: 1 1 200px;
    min-inline-size: 0;
  }

  // 360px first: actions take a full row and share the width
  &__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    inline-size: 100%;

    :deep(.v-btn) {
      flex: 1 1 auto;
    }

    @media (min-width: 600px) {
      inline-size: auto;

      :deep(.v-btn) {
        flex: 0 0 auto;
      }
    }
  }
}
</style>
