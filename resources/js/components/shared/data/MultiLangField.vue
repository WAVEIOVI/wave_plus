<script setup>
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { computed, defineEmits, defineProps, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: [Object, String], default: () => ({}) },
  label: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  rules: { type: Array, default: () => [] },
  errorMessages: { type: [Array, String], default: () => [] },
  placeholder: { type: String, default: '' },
  hint: { type: String, default: '' },
  prependIcon: { type: String, default: '' },
  variant: { type: String, default: 'outlined' },
  density: { type: String, default: 'default' },
  hideDetails: { type: [Boolean, String], default: false },

  /** Render a <VTextarea> inside the dialog instead of a single-line input. */
  multiline: { type: Boolean, default: false },

  /** Max character count enforced per translation (0 = unlimited). */
  maxLength: { type: Number, default: 0 },

  /** Override the supported locales (defaults to en / fr / ar). */
  locales: {
    type: Array,
    default: () => ['en', 'fr', 'ar'],
  },
})

const emit = defineEmits(['update:modelValue'])
const { t, te, locale: appLocale } = useI18n()

const SUPPORTED_LOCALES = computed(() => props.locales)

const LOCALE_LABELS = {
  en: 'English',
  fr: 'Français',
  ar: 'العربية',
  es: 'Español',
  de: 'Deutsch',
  it: 'Italiano',
  pt: 'Português',
}

const LOCALE_FLAGS = {
  en: '🇬🇧',
  fr: '🇫🇷',
  ar: '🇸🇦',
  es: '🇪🇸',
  de: '🇩🇪',
  it: '🇮🇹',
  pt: '🇵🇹',
}

const localValue = ref({})
const dialogVisible = ref(false)
const editingLocale = ref('en')
const localeInputs = ref({})
const isSaving = ref(false)
const validationError = ref('')
const inputRef = ref(null)

const fieldErrors = computed(() => {
  const errors = Array.isArray(props.errorMessages)
    ? props.errorMessages
    : props.errorMessages
      ? [props.errorMessages]
      : []

  return validationError.value ? [...errors, validationError.value] : errors
})

// English copy is also the fallback when a translation key is not registered.
const translate = text => te(text) ? t(text) : text
const canEdit = computed(() => !props.disabled && !props.readonly)

const currentAppLocale = computed(() => {
  const language = String(appLocale.value).toLowerCase().split(/[-_]/)[0]

  return SUPPORTED_LOCALES.value.includes(language) ? language : SUPPORTED_LOCALES.value[0] ?? 'en'
})

watch(() => props.modelValue, newValue => {
  localValue.value = { ...ensureTranslatable(newValue) }

  // Parent updates must not erase an in-progress dialog draft.
  if (!dialogVisible.value)
    localeInputs.value = { ...localValue.value }
}, { deep: true, immediate: true })

watch(canEdit, editable => {
  if (!editable)
    dialogVisible.value = false
})

watch(localeInputs, () => {
  validationError.value = ''
}, { deep: true })

// Autofocus the input when the active tab changes.
watch(editingLocale, async () => {
  await nextTick()
  inputRef.value?.focus?.()
})

const displayValue = computed({
  get: () => localizedValue(localValue.value),
  set: value => {
    if (!canEdit.value)
      return

    localValue.value = {
      ...localValue.value,
      [currentAppLocale.value]: value ?? '',
    }
    emit('update:modelValue', { ...localValue.value })
  },
})

const currentLocaleValue = computed({
  get: () => localeInputs.value[editingLocale.value] ?? '',
  set: value => {
    localeInputs.value[editingLocale.value] = value ?? ''
  },
})

const hasTranslation = language => String(localeInputs.value[language] ?? '').trim().length > 0
const translatedCount = computed(() => SUPPORTED_LOCALES.value.filter(hasTranslation).length)

const completionPct = computed(() =>
  SUPPORTED_LOCALES.value.length === 0
    ? 0
    : Math.round((translatedCount.value / SUPPORTED_LOCALES.value.length) * 100),
)

const dialogTitle = computed(() => props.label ? translate(props.label) : translate('Translations'))
const inputDirection = computed(() => editingLocale.value === 'ar' ? 'rtl' : 'ltr')

const charactersUsed = computed(() => String(currentLocaleValue.value ?? '').length)

const hasChanges = computed(() =>
  SUPPORTED_LOCALES.value.some(lang => (localeInputs.value[lang] ?? '') !== (localValue.value[lang] ?? '')),
)

function openDialog() {
  if (!canEdit.value)
    return

  localeInputs.value = { ...localValue.value }
  editingLocale.value = currentAppLocale.value
  validationError.value = ''
  dialogVisible.value = true
}

function closeDialog() {
  if (!isSaving.value)
    dialogVisible.value = false
}

function copyFromLocale(sourceLang) {
  if (!canEdit.value || sourceLang === editingLocale.value) return
  localeInputs.value = {
    ...localeInputs.value,
    [editingLocale.value]: localeInputs.value[sourceLang] ?? '',
  }
}

function clearCurrent() {
  if (!canEdit.value) return
  localeInputs.value = { ...localeInputs.value, [editingLocale.value]: '' }
}

async function handleSave() {
  if (!canEdit.value || isSaving.value)
    return

  isSaving.value = true
  validationError.value = ''

  try {
    const candidate = { ...localeInputs.value }
    const value = localizedValue(candidate)

    // Keep the original rule contract: validate the localized display value,
    // not every optional translation. Async rules are supported as well.
    for (const rule of props.rules) {
      const result = await (typeof rule === 'function' ? rule(value) : rule)

      if (result !== true) {
        validationError.value = typeof result === 'string' && result
          ? result
          : translate('Please check this field.')

        return
      }
    }

    if (!canEdit.value || !dialogVisible.value)
      return

    localValue.value = candidate
    emit('update:modelValue', { ...candidate })
    dialogVisible.value = false
  }
  catch {
    validationError.value = translate('Validation could not be completed. Please try again.')
  }
  finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div class="multi-lang-field">
    <VTextarea
      v-if="multiline"
      v-model="displayValue"
      :label="label"
      :required="required"
      :disabled="disabled"
      :readonly="readonly"
      :rules="rules"
      :error-messages="fieldErrors"
      :placeholder="placeholder"
      :hint="hint"
      :prepend-icon="prependIcon"
      :variant="variant"
      :density="density"
      :hide-details="hideDetails"
      auto-grow
      rows="2"
      @keydown.alt.enter.prevent="openDialog"
    >
      <template
        v-if="canEdit"
        #append-inner
      >
        <VTooltip
          :text="`${translate('Edit translations')} (Alt+Enter)`"
          location="top"
        >
          <template #activator="{ props: activatorProps }">
            <VBtn
              v-bind="activatorProps"
              icon
              variant="text"
              color="primary"
              size="small"
              :aria-label="translate('Edit translations')"
              @click.stop="openDialog"
            >
              <VBadge
                :content="translatedCount"
                :color="translatedCount === SUPPORTED_LOCALES.length ? 'success' : 'warning'"
                offset-x="-4"
                offset-y="-4"
                floating
              >
                <VIcon icon="tabler-language" />
              </VBadge>
            </VBtn>
          </template>
        </VTooltip>
      </template>
    </VTextarea>
    <VTextField
      v-else
      v-model="displayValue"
      :label="label"
      :required="required"
      :disabled="disabled"
      :readonly="readonly"
      :rules="rules"
      :error-messages="fieldErrors"
      :placeholder="variant === 'outlined' && label ? undefined : placeholder"
      :hint="hint"
      :prepend-icon="prependIcon"
      :variant="variant"
      :density="density"
      :hide-details="hideDetails"
      @keydown.alt.enter.prevent="openDialog"
    >
      <template
        v-if="canEdit"
        #append-inner
      >
        <VTooltip
          :text="`${translate('Edit translations')} (Alt+Enter)`"
          location="top"
        >
          <template #activator="{ props: activatorProps }">
            <VBtn
              v-bind="activatorProps"
              icon
              variant="text"
              color="primary"
              size="small"
              :aria-label="translate('Edit translations')"
              @click.stop="openDialog"
            >
              <VBadge
                :content="translatedCount"
                :color="translatedCount === SUPPORTED_LOCALES.length ? 'success' : 'warning'"
                offset-x="-4"
                offset-y="-4"
                floating
              >
                <VIcon icon="tabler-language" />
              </VBadge>
            </VBtn>
          </template>
        </VTooltip>
      </template>
    </VTextField>

    <VDialog
      v-model="dialogVisible"
      :fullscreen="$vuetify.display.smAndDown"
      :persistent="isSaving"
      :aria-label="dialogTitle"
      max-width="640"
      scrollable
      transition="dialog-bottom-transition"
    >
      <VCard
        class="multi-lang-dialog"
        rounded="lg"
      >
        <VCardTitle class="d-flex align-center gap-3 pa-5">
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            size="42"
          >
            <VIcon
              icon="tabler-language"
              size="24"
            />
          </VAvatar>

          <div class="multi-lang-dialog__heading flex-grow-1">
            <div class="text-h6 text-wrap">
              {{ dialogTitle }}
            </div>
            <div class="text-body-2 text-medium-emphasis mt-1">
              {{ translate('Manage translations in one place') }}
            </div>
          </div>

          <VBtn
            icon="tabler-x"
            variant="text"
            color="secondary"
            size="small"
            :disabled="isSaving"
            :aria-label="translate('Close')"
            @click="closeDialog"
          />
        </VCardTitle>

        <VDivider />

        <!-- Progress bar -->
        <VProgressLinear
          :model-value="completionPct"
          :color="completionPct === 100 ? 'success' : 'primary'"
          height="4"
          rounded
          :aria-label="`${completionPct}% ${translate('translated')}`"
        />

        <VCardText class="pa-5">
          <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-4">
            <span class="text-body-2 text-medium-emphasis">
              {{ translate('Choose a language') }}
            </span>
            <VChip
              size="small"
              :color="completionPct === 100 ? 'success' : 'primary'"
              variant="tonal"
              :prepend-icon="completionPct === 100 ? 'tabler-circle-check' : 'tabler-progress'"
            >
              {{ translatedCount }} / {{ SUPPORTED_LOCALES.length }}
              <span class="ms-1">{{ translate('translated') }}</span>
            </VChip>
          </div>

          <VTabs
            v-model="editingLocale"
            color="primary"
            grow
            class="multi-lang-dialog__tabs mb-5"
            :aria-label="translate('Translation language')"
          >
            <VTab
              v-for="language in SUPPORTED_LOCALES"
              :key="language"
              :value="language"
              :disabled="isSaving"
            >
              <span
                v-if="LOCALE_FLAGS[language]"
                class="me-2"
                aria-hidden="true"
              >{{ LOCALE_FLAGS[language] }}</span>
              <span
                :lang="language"
                class="text-body-2 font-weight-medium"
              >
                {{ LOCALE_LABELS[language] ?? language.toUpperCase() }}
              </span>
              <VIcon
                v-if="hasTranslation(language)"
                icon="tabler-circle-check-filled"
                color="success"
                size="16"
                class="ms-2"
                :aria-label="translate('Translated')"
                role="img"
              />
            </VTab>
          </VTabs>

          <div
            role="tabpanel"
            :aria-label="LOCALE_LABELS[editingLocale] ?? editingLocale"
          >
            <VTextarea
              v-if="props.multiline"
              :key="`ta-${editingLocale}`"
              ref="inputRef"
              v-model="currentLocaleValue"
              :label="`${translate('Translation')} · ${LOCALE_LABELS[editingLocale] ?? editingLocale}`"
              :lang="editingLocale"
              :dir="inputDirection"
              :disabled="isSaving || !canEdit"
              :counter="props.maxLength || undefined"
              :maxlength="props.maxLength || undefined"
              variant="outlined"
              density="comfortable"
              auto-grow
              rows="3"
              hide-details="auto"
            />
            <VTextField
              v-else
              :key="`tf-${editingLocale}`"
              ref="inputRef"
              v-model="currentLocaleValue"
              :label="`${translate('Translation')} · ${LOCALE_LABELS[editingLocale] ?? editingLocale}`"
              :lang="editingLocale"
              :dir="inputDirection"
              :disabled="isSaving || !canEdit"
              :counter="props.maxLength || undefined"
              :maxlength="props.maxLength || undefined"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
            />

            <!-- Quick actions row -->
            <div class="d-flex align-center flex-wrap gap-2 mt-3">
              <VMenu v-if="canEdit">
                <template #activator="{ props: menuProps }">
                  <VBtn
                    v-bind="menuProps"
                    size="x-small"
                    variant="tonal"
                    prepend-icon="tabler-copy"
                    :disabled="isSaving"
                  >
                    {{ translate('Copy from') }}
                  </VBtn>
                </template>
                <VList density="compact">
                  <VListItem
                    v-for="lang in SUPPORTED_LOCALES.filter(l => l !== editingLocale && hasTranslation(l))"
                    :key="lang"
                    @click="copyFromLocale(lang)"
                  >
                    <template #prepend>
                      <span class="me-2">{{ LOCALE_FLAGS[lang] ?? '' }}</span>
                    </template>
                    <VListItemTitle>{{ LOCALE_LABELS[lang] ?? lang.toUpperCase() }}</VListItemTitle>
                  </VListItem>
                  <VListItem
                    v-if="!SUPPORTED_LOCALES.some(l => l !== editingLocale && hasTranslation(l))"
                    disabled
                  >
                    <VListItemTitle class="text-caption text-medium-emphasis">
                      {{ translate('No other translations yet') }}
                    </VListItemTitle>
                  </VListItem>
                </VList>
              </VMenu>

              <VBtn
                v-if="canEdit && hasTranslation(editingLocale)"
                size="x-small"
                variant="text"
                color="error"
                prepend-icon="tabler-eraser"
                :disabled="isSaving"
                @click="clearCurrent"
              >
                {{ translate('Clear') }}
              </VBtn>

              <VSpacer />

              <span
                v-if="props.maxLength > 0"
                class="text-caption text-medium-emphasis"
              >
                {{ charactersUsed }} / {{ props.maxLength }}
              </span>
            </div>
          </div>

          <VAlert
            v-if="validationError"
            type="error"
            variant="tonal"
            density="compact"
            class="mt-4"
            role="alert"
          >
            {{ validationError }}
          </VAlert>

          <VExpansionPanels
            class="mt-5"
            variant="accordion"
          >
            <VExpansionPanel
              elevation="0"
              class="multi-lang-dialog__preview"
            >
              <VExpansionPanelTitle>
                <VIcon
                  icon="tabler-eye"
                  size="20"
                  class="me-2"
                />
                <span class="text-body-2 font-weight-medium">
                  {{ translate('Preview all translations') }}
                </span>
              </VExpansionPanelTitle>
              <VExpansionPanelText>
                <div
                  v-for="language in SUPPORTED_LOCALES"
                  :key="language"
                  class="multi-lang-dialog__preview-row py-3"
                >
                  <div class="d-flex align-center gap-2 mb-2">
                    <span
                      v-if="LOCALE_FLAGS[language]"
                      aria-hidden="true"
                    >{{ LOCALE_FLAGS[language] }}</span>
                    <VChip
                      size="x-small"
                      variant="tonal"
                      color="secondary"
                    >
                      {{ language.toUpperCase() }}
                    </VChip>
                    <span
                      :lang="language"
                      class="text-caption font-weight-medium"
                    >
                      {{ LOCALE_LABELS[language] ?? language.toUpperCase() }}
                    </span>
                    <VSpacer />
                    <VIcon
                      v-if="hasTranslation(language)"
                      icon="tabler-circle-check-filled"
                      color="success"
                      size="14"
                      :aria-label="translate('Translated')"
                    />
                  </div>
                  <div
                    :dir="language === 'ar' ? 'rtl' : 'ltr'"
                    :lang="language"
                    class="multi-lang-dialog__preview-value text-body-2"
                    :class="{ 'text-disabled': !hasTranslation(language) }"
                  >
                    {{ hasTranslation(language) ? localeInputs[language] : translate('No translation yet') }}
                  </div>
                </div>
              </VExpansionPanelText>
            </VExpansionPanel>
          </VExpansionPanels>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4 justify-end gap-2">
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="isSaving"
            @click="closeDialog"
          >
            {{ translate('Cancel') }}
          </VBtn>
          <VBtn
            variant="flat"
            color="primary"
            prepend-icon="tabler-check"
            :loading="isSaving"
            :disabled="!canEdit || !hasChanges"
            @click="handleSave"
          >
            {{ translate('Save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.multi-lang-field,
.multi-lang-dialog__heading {
  min-inline-size: 0;
}

.multi-lang-dialog__tabs,
.multi-lang-dialog__preview {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}

.multi-lang-dialog__tabs :deep(.v-tab) {
  min-inline-size: 0;
  padding-inline: 12px;
  transition: background-color 0.15s ease;
}

.multi-lang-dialog__tabs :deep(.v-tab:hover) {
  background: rgba(var(--v-theme-primary), 0.04);
}

.multi-lang-dialog__preview-row + .multi-lang-dialog__preview-row {
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.multi-lang-dialog__preview-value {
  overflow-wrap: anywhere;
  text-align: start;
  white-space: pre-wrap;
}
</style>
