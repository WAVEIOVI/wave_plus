<script setup>
import { useDisplay } from 'vuetify'
/**
 * WpFilterDialog
 * ─────────────────────────────────────────────────────────────────────────────
 * Modern filter dialog — opens from the "Filters" button in the toolbar.
 * Filter fields are provided through the default slot.
 * Business-specific fields must NOT be placed in this component.
 *
 * Usage:
 *   <WpFilterDialog
 *     v-model="filterDialogOpen"
 *     :active-count="activeFilterCount"
 *     @apply="applyStaged"
 *     @reset="resetStaged"
 *   >
 *     <!-- Slot receives stagedFilters via v-model from parent -->
 *     <VSelect v-model="stagedFilters.status" :items="statusOptions" ... />
 *     <VSelect v-model="stagedFilters.industry" ... />
 *   </WpFilterDialog>
 *
 * @emits apply - When the user clicks "Apply Filters".
 * @emits reset - When the user clicks "Reset".
 */
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  /** Number of currently active filters (shown on the button badge). */
  activeCount: {
    type: Number,
    default: 0,
  },
  title: {
    type: String,
    default: '',
  },
  /** Loading state (e.g. while saving or fetching filter options). */
  loading: {
    type: Boolean,
    default: false,
  },
  maxWidth: {
    type: [String, Number],
    default: 540,
  },
})

const emit = defineEmits(['update:modelValue', 'apply', 'reset'])

const { t } = useI18n()
const { smAndDown } = useDisplay()

const dialogTitle = computed(() => props.title || t('wp.filters.dialog_title'))

const close = () => emit('update:modelValue', false)

const handleApply = () => {
  emit('apply')
  close()
}

const handleReset = () => {
  emit('reset')
}
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    :max-width="props.maxWidth"
    :fullscreen="smAndDown"
    scrollable
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <VCard class="wp-filter-dialog">
      <!-- Header -->
      <VCardTitle class="wp-filter-dialog__header d-flex align-center pa-4 pb-2">
        <VIcon
          icon="tabler-filter"
          size="20"
          class="me-2 text-primary"
        />
        <span class="text-h6">{{ dialogTitle }}</span>

        <VBadge
          v-if="props.activeCount > 0"
          :content="props.activeCount"
          color="primary"
          inline
          class="ms-2"
        />

        <VSpacer />

        <IconBtn
          size="small"
          @click="close"
        >
          <VIcon
            icon="tabler-x"
            size="20"
          />
        </IconBtn>
      </VCardTitle>

      <VDivider />

      <!-- Filter Fields (provided by consumer) -->
      <VCardText class="wp-filter-dialog__body pa-4">
        <slot />
      </VCardText>

      <VDivider />

      <!-- Actions -->
      <VCardActions class="wp-filter-dialog__footer pa-4 gap-2 justify-end">
        <VBtn
          variant="text"
          color="default"
          :disabled="props.loading"
          @click="handleReset"
        >
          {{ $t('wp.filters.reset') }}
        </VBtn>

        <VBtn
          color="primary"
          variant="elevated"
          :loading="props.loading"
          @click="handleApply"
        >
          {{ $t('wp.filters.apply') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
