<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveExportFormats } from '@/composables/shared/exportFormats'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: '',
  },
  subtitle: {
    type: String,
    default: '',
  },
  entityName: {
    type: String,
    default: '',
  },
  selectedCount: {
    type: Number,
    default: 0,
  },
  formats: {
    type: Array,
    default: () => ['pdf', 'csv'],
  },
  defaultFormat: {
    type: String,
    default: 'pdf',
  },
  loading: {
    type: Boolean,
    default: false,
  },
  error: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue', 'export', 'close'])

const { t, te } = useI18n()

const tr = (key, fallback = key, params = undefined) => {
  if (!key)
    return fallback

  return te(key) ? t(key, params) : fallback
}

const availableFormats = computed(() => resolveExportFormats(props.formats))

const selectedFormat = ref(props.defaultFormat || 'pdf')

const ensureValidSelectedFormat = () => {
  const list = availableFormats.value
  if (list.length === 0)
    return

  const preferred = String(props.defaultFormat || 'pdf').trim().toLowerCase()
  const exists = list.some(item => item.value === selectedFormat.value)
  if (!exists) {
    const preferredMatch = list.find(item => item.value === preferred)

    selectedFormat.value = preferredMatch ? preferredMatch.value : list[0].value
  }
}

watch(
  () => [props.modelValue, props.defaultFormat, props.formats],
  ([isOpen]) => {
    if (isOpen) {
      const preferred = String(props.defaultFormat || 'pdf').trim().toLowerCase()
      const match = availableFormats.value.find(item => item.value === preferred)

      selectedFormat.value = match ? match.value : (availableFormats.value[0]?.value || 'pdf')
    }
    else {
      ensureValidSelectedFormat()
    }
  },
  { immediate: true },
)

const resolvedTitle = computed(() => {
  if (props.title)
    return props.title

  if (props.entityName)
    return `${tr('Export', 'Export')} — ${props.entityName}`

  return tr('Export Selected Records', 'Export Selected Records')
})

const resolvedSubtitle = computed(() => {
  if (props.subtitle)
    return props.subtitle

  return tr(
    'Choose an export format for your selected records',
    'Choose an export format for your selected records',
  )
})

const canExport = computed(() => {
  return props.selectedCount > 0
    && !props.loading
    && availableFormats.value.some(item => item.value === selectedFormat.value)
})

const activeFormatMeta = computed(() => {
  return availableFormats.value.find(item => item.value === selectedFormat.value) || availableFormats.value[0] || null
})

const closeDialog = () => {
  if (props.loading)
    return

  emit('update:modelValue', false)
  emit('close')
}

const handleExport = () => {
  if (!canExport.value)
    return

  emit('export', {
    format: selectedFormat.value,
  })
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    :fullscreen="$vuetify.display.smAndDown"
    max-width="540"
    scrollable
    :persistent="loading"
    @update:model-value="val => { if (!val) closeDialog() }"
  >
    <VCard class="wp-export-dialog">
      <!-- Header -->
      <div class="wp-export-dialog__header d-flex align-center justify-space-between px-5 py-4">
        <div class="d-flex align-center gap-3 min-w-0">
          <VAvatar
            color="primary"
            variant="tonal"
            size="40"
            rounded="lg"
          >
            <VIcon
              icon="tabler-file-export"
              size="22"
            />
          </VAvatar>
          <div class="min-w-0">
            <h5 class="text-h6 font-weight-semibold text-truncate mb-0">
              {{ resolvedTitle }}
            </h5>
            <p class="text-caption text-medium-emphasis text-truncate mb-0">
              {{ resolvedSubtitle }}
            </p>
          </div>
        </div>

        <VBtn
          icon
          variant="text"
          size="small"
          color="secondary"
          :disabled="loading"
          :aria-label="tr('Close', 'Close')"
          @click="closeDialog"
        >
          <VIcon
            icon="tabler-x"
            size="20"
          />
        </VBtn>
      </div>

      <VDivider />

      <!-- Body -->
      <VCardText class="pa-5">
        <!-- Selected Count Summary Banner -->
        <VSheet
          rounded="lg"
          border
          class="wp-export-dialog__summary d-flex align-center justify-space-between pa-3 mb-4"
        >
          <div class="d-flex align-center gap-3 min-w-0">
            <VAvatar
              :color="selectedCount > 0 ? 'primary' : 'warning'"
              variant="tonal"
              size="36"
              rounded="md"
            >
              <VIcon
                :icon="selectedCount > 0 ? 'tabler-checkbox' : 'tabler-alert-circle'"
                size="20"
              />
            </VAvatar>
            <div class="min-w-0">
              <div class="text-body-2 font-weight-semibold text-high-emphasis">
                <template v-if="selectedCount > 0">
                  {{ selectedCount }} {{ selectedCount === 1 ? tr('record selected', 'record selected') : tr('records selected', 'records selected') }}
                </template>
                <template v-else>
                  {{ tr('No records selected', 'No records selected') }}
                </template>
              </div>
              <div class="text-caption text-medium-emphasis">
                <template v-if="selectedCount > 0">
                  {{ tr('Only the selected records will be included in the exported file.', 'Only the selected records will be included in the exported file.') }}
                </template>
                <template v-else>
                  {{ tr('Please select at least one record from the list before exporting.', 'Please select at least one record from the list before exporting.') }}
                </template>
              </div>
            </div>
          </div>

          <VChip
            :color="selectedCount > 0 ? 'primary' : 'warning'"
            variant="tonal"
            size="small"
            label
            class="font-weight-semibold flex-shrink-0 ms-2"
          >
            {{ selectedCount }}
          </VChip>
        </VSheet>

        <!-- Error Alert -->
        <VAlert
          v-if="error"
          type="error"
          variant="tonal"
          density="comfortable"
          class="mb-4"
        >
          {{ error }}
        </VAlert>

        <!-- Format Selection -->
        <div class="text-caption font-weight-semibold text-uppercase text-medium-emphasis mb-2">
          {{ tr('Export Format', 'Export Format') }}
        </div>

        <div
          class="wp-export-dialog__formats d-flex flex-column gap-3"
          role="radiogroup"
          :aria-label="tr('Export Format', 'Export Format')"
        >
          <VSheet
            v-for="item in availableFormats"
            :key="item.value"
            rounded="lg"
            border
            role="radio"
            tabindex="0"
            :aria-checked="selectedFormat === item.value"
            class="wp-export-dialog__format-card d-flex align-center justify-space-between pa-3 cursor-pointer"
            :class="{
              'wp-export-dialog__format-card--active': selectedFormat === item.value,
              'wp-export-dialog__format-card--disabled': loading,
            }"
            @click="!loading && (selectedFormat = item.value)"
            @keydown.enter.prevent="!loading && (selectedFormat = item.value)"
            @keydown.space.prevent="!loading && (selectedFormat = item.value)"
          >
            <div class="d-flex align-center gap-3 min-w-0">
              <VAvatar
                :color="selectedFormat === item.value ? item.color : 'secondary'"
                variant="tonal"
                size="40"
                rounded="lg"
              >
                <VIcon
                  :icon="item.icon"
                  size="22"
                />
              </VAvatar>
              <div class="min-w-0">
                <div class="d-flex align-center gap-2">
                  <span class="text-body-1 font-weight-semibold text-high-emphasis">
                    {{ tr(item.titleKey, item.titleKey) }}
                  </span>
                  <VChip
                    size="x-small"
                    :color="selectedFormat === item.value ? item.color : 'secondary'"
                    variant="tonal"
                    label
                    class="text-uppercase font-weight-semibold"
                  >
                    .{{ item.extension }}
                  </VChip>
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ tr(item.descriptionKey, item.descriptionKey) }}
                </div>
              </div>
            </div>

            <VIcon
              :icon="selectedFormat === item.value ? 'tabler-circle-check-filled' : 'tabler-circle'"
              :color="selectedFormat === item.value ? 'primary' : 'disabled'"
              size="22"
              class="ms-3 flex-shrink-0"
            />
          </VSheet>
        </div>
      </VCardText>

      <VDivider />

      <!-- Footer -->
      <VCardActions class="wp-export-dialog__footer d-flex align-center justify-end gap-2 px-5 py-3">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="loading"
          @click="closeDialog"
        >
          {{ tr('Cancel', 'Cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          :loading="loading"
          :disabled="!canExport"
          :prepend-icon="activeFormatMeta?.icon || 'tabler-download'"
          @click="handleExport"
        >
          {{ tr('Export', 'Export') }} {{ activeFormatMeta ? activeFormatMeta.label : '' }}
          <template v-if="selectedCount > 0">
            ({{ selectedCount }})
          </template>
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped lang="scss">
.wp-export-dialog {
  &__summary {
    background-color: rgba(var(--v-theme-on-surface), 0.02);
  }

  &__format-card {
    transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;

    &:hover:not(.wp-export-dialog__format-card--disabled) {
      border-color: rgba(var(--v-theme-primary), 0.45);
      background-color: rgba(var(--v-theme-primary), 0.03);
    }

    &--active {
      border-color: rgb(var(--v-theme-primary)) !important;
      background-color: rgba(var(--v-theme-primary), 0.06);
      box-shadow: 0 0 0 1px rgba(var(--v-theme-primary), 0.2);
    }

    &--disabled {
      opacity: 0.65;
      pointer-events: none;
    }
  }
}
</style>
