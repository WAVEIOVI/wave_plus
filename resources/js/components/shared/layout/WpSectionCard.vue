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
    display: flex !important;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 0.5rem 0.75rem;
  }

  .wp-section-card__header :deep(.v-card-item__prepend) {
    flex: 0 0 auto;
    padding-inline-end: 0;
  }

  .wp-section-card__header :deep(.v-card-item__content) {
    flex: 1 1 0%;
    min-inline-size: 0;
  }

  .wp-section-card__header :deep(.v-card-title),
  .wp-section-card__header :deep(.v-card-subtitle) {
    white-space: normal;
    overflow-wrap: anywhere;
  }

  .wp-section-card__header :deep(.v-card-item__append) {
    flex: 0 0 100%;
    inline-size: 100%;
    margin-inline-start: 0 !important;
    padding-inline-start: 0 !important;
    padding-block-start: 0.375rem;
    justify-content: flex-start;
  }

  .wp-section-card__actions {
    inline-size: 100%;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
  }
}

/* Hide subtitles on small mobile devices (430px and below) */
@media (max-width: 430px) {
  .wp-section-card__header :deep(.v-card-subtitle) {
    display: none !important;
  }
  
  .wp-section-card__header :deep(.v-card-title) {
    font-size: 0.9375rem !important;
    line-height: 1.3;
  }
}

@media (max-width: 390px) {
  .wp-section-card__header :deep(.v-avatar) {
    block-size: 34px !important;
    inline-size: 34px !important;
    
    .v-icon {
      font-size: 19px !important;
    }
  }
  
  .wp-section-card__header :deep(.v-card-title) {
    font-size: 0.9rem !important;
  }
}

/* Further optimization for very small devices (375px and below) */
@media (max-width: 375px) {
  .wp-section-card__header :deep(.v-avatar) {
    block-size: 32px !important;
    inline-size: 32px !important;
    
    .v-icon {
      font-size: 18px !important;
    }
  }
  
  .wp-section-card__header :deep(.v-card-title) {
    font-size: 0.875rem !important;
  }
}

@media (max-width: 360px) {
  .wp-section-card__header :deep(.v-avatar) {
    block-size: 30px !important;
    inline-size: 30px !important;
    
    .v-icon {
      font-size: 17px !important;
    }
  }
  
  .wp-section-card__header :deep(.v-card-title) {
    font-size: 0.85rem !important;
  }
}

@media (max-width: 340px) {
  .wp-section-card__header :deep(.v-avatar) {
    block-size: 28px !important;
    inline-size: 28px !important;
    
    .v-icon {
      font-size: 16px !important;
    }
  }
  
  .wp-section-card__header :deep(.v-card-title) {
    font-size: 0.8125rem !important;
  }
}

/* Maximum optimization for ultra-small devices (320px and below) */
@media (max-width: 320px) {
  .wp-section-card__header {
    gap: 0.375rem 0.5rem;
  }
  
  .wp-section-card__header :deep(.v-avatar) {
    block-size: 26px !important;
    inline-size: 26px !important;
    
    .v-icon {
      font-size: 15px !important;
    }
  }
  
  .wp-section-card__header :deep(.v-card-title) {
    font-size: 0.8rem !important;
  }
}

@media (max-width: 310px) {
  .wp-section-card__header {
    gap: 0.25rem 0.375rem;
  }
  
  .wp-section-card__header :deep(.v-avatar) {
    block-size: 24px !important;
    inline-size: 24px !important;
    
    .v-icon {
      font-size: 14px !important;
    }
  }
  
  .wp-section-card__header :deep(.v-card-title) {
    font-size: 0.75rem !important;
  }
}
</style>
