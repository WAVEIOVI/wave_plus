<script setup>
/* eslint-disable lines-around-comment */
import WpStatusChip from '@/components/shared/data/WpStatusChip.vue'

/**
 * WpEntityHeader
 * ─────────────────────────────────────────────────────────────────────────────
 * Identity header for detail / 360 pages (employee, vehicle, candidate…).
 * Presents: back action, avatar/initials, name, secondary identity line,
 * status chip, contextual meta slot and an actions slot.
 *
 * Usage:
 *   <WpEntityHeader
 *     :title="employee.full_name"
 *     :subtitle="$t('employees.edit.subtitle')"
 *     :initials="avatarText(name)"
 *     :avatar="employee.avatar_url"
 *     status="active"
 *     status-type="employee"
 *     :status-label="$t('Active')"
 *     :back-to="{ name: 'people-skills-employees-list' }"
 *   >
 *     <template #meta>…</template>
 *     <template #actions>…</template>
 *   </WpEntityHeader>
 */
const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
  /** Image URL for the avatar. */
  avatar: {
    type: String,
    default: '',
  },
  /** Initials shown when no image is available. */
  initials: {
    type: String,
    default: '',
  },
  /** Icon shown when there is neither image nor initials. */
  icon: {
    type: String,
    default: 'tabler-user',
  },
  avatarColor: {
    type: String,
    default: 'primary',
  },
  /** Raw status value for the status chip. */
  status: {
    type: [String, Number],
    default: '',
  },
  statusType: {
    type: String,
    default: '',
  },
  statusLabel: {
    type: String,
    default: '',
  },
  /** Router target for the back button. */
  backTo: {
    type: [String, Object],
    default: undefined,
  },
})

const router = useRouter()

const goBack = () => {
  if (props.backTo) router.push(props.backTo)
}
</script>

<template>
  <header class="wp-entity-header d-flex flex-column flex-md-row align-md-start gap-4">
    <!-- Identity -->
    <div class="d-flex align-start gap-3 min-w-0 grow">
      <VBtn
        v-if="props.backTo"
        variant="text"
        icon="tabler-arrow-left"
        class="wp-entity-header__back flex-shrink-0"
        :aria-label="$t('Back')"
        @click="goBack"
      />

      <VAvatar
        size="64"
        :color="props.avatarColor"
        variant="tonal"
        rounded="lg"
        class="flex-shrink-0"
      >
        <VImg
          v-if="props.avatar"
          :src="props.avatar"
        />
        <span
          v-else-if="props.initials"
          class="text-h6 font-weight-bold"
        >{{ props.initials }}</span>
        <VIcon
          v-else
          :icon="props.icon"
          size="30"
        />
      </VAvatar>

      <div class="min-w-0">
        <div class="d-flex align-center flex-wrap gap-2">
          <h1 class="text-h5 font-weight-bold text-high-emphasis mb-0 wp-entity-header__title">
            {{ props.title }}
          </h1>
          <WpStatusChip
            v-if="props.status"
            :status="props.status"
            :type="props.statusType"
            :label="props.statusLabel"
          />
        </div>

        <p
          v-if="props.subtitle"
          class="text-body-2 text-medium-emphasis mb-0 mt-1"
        >
          {{ props.subtitle }}
        </p>

        <div
          v-if="$slots.meta"
          class="wp-entity-header__meta d-flex align-center flex-wrap gap-x-4 gap-y-1 mt-2"
        >
          <slot name="meta" />
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div
      v-if="$slots.actions"
      class="wp-entity-header__actions d-flex align-center flex-wrap gap-2 flex-shrink-0"
    >
      <slot name="actions" />
    </div>
  </header>
</template>

<style scoped>
.wp-entity-header__title {
  overflow-wrap: anywhere;
}

.wp-entity-header__meta {
  font-size: 0.8125rem;
}

@media (max-width: 599px) {
  .wp-entity-header {
    padding-block-end: 4px;
  }

  .wp-entity-header__title {
    font-size: 1.25rem !important;
  }

  .wp-entity-header__actions {
    inline-size: 100%;
  }

  .wp-entity-header__actions :deep(.v-btn) {
    flex: 1 1 auto;
  }
}
</style>
