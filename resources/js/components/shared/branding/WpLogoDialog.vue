<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import WpEntityLogo from '@/components/shared/branding/WpEntityLogo.vue'
import { useBrandLogo } from '@/composables/branding/useBrandLogo'
import { formatDocumentBytes as formatBytes } from '@/composables/documents/documentFiles'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
  initialName: { type: String, default: '' },
  initialDomain: { type: String, default: '' },
  initialLogoUrl: { type: String, default: '' },
  initialSource: { type: String, default: '' },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'save', 'error', 'close'])

const { t } = useI18n()

function i18nError(key, fallback) {
  const error = new Error(t(key) || fallback)

  error.i18nKey = key

  return error
}

const {
  logoUrlRef,
  logoSourceRef,
  brandNameRef,
  domainRef,
  loadingRef,
  errorRef,
  uploadedFileRef,
  resolveByDomain,
  searchBrand,
  selectResult,
  uploadLogo,
  useExternalLogo,
  clear,
  getInitials,
  getLogoPayload,
  LOGO_SOURCES,
} = useBrandLogo()

const localOpen = ref(props.modelValue)
const activeTab = ref('search') // 'search' | 'upload'
const searchQueryRef = ref('')
const searchResultsRef = ref([])
const selectedResultRef = ref(null)
const fileInputRef = ref(null)
const hasSearchedRef = ref(false)
const hasSelectionRef = ref(false)
const isDraggingFile = ref(false)
const validationNotice = ref('')

watch(() => props.modelValue, val => {
  localOpen.value = val
})

watch(localOpen, val => {
  emit('update:modelValue', val)
  if (!val) {
    emit('close')
  }
})

watch(() => props.initialDomain, val => {
  if (val && localOpen.value) {
    resolveDomain(val)
  }
})

watch(() => props.initialName, val => {
  if (val && localOpen.value && !searchQueryRef.value) {
    searchQueryRef.value = val
  }
})

watch(() => props.modelValue, isOpen => {
  if (!isOpen) return

  hasSelectionRef.value = false
  validationNotice.value = ''

  if (props.initialLogoUrl) {
    useExternalLogo(
      props.initialLogoUrl,
      props.initialSource || LOGO_SOURCES.BRANDFETCH,
      props.initialDomain || '',
      props.initialName || '',
    )
  }
  else if (props.initialDomain) {
    resolveDomain(props.initialDomain)
  }
  else {
    clear()
  }

  if (props.initialName && !searchQueryRef.value) {
    searchQueryRef.value = props.initialName
  }
})

const close = () => {
  clear()
  searchQueryRef.value = ''
  searchResultsRef.value = []
  selectedResultRef.value = null
  hasSearchedRef.value = false
  hasSelectionRef.value = false
  validationNotice.value = ''
  isDraggingFile.value = false
  localOpen.value = false
}

const currentEntityName = computed(() => brandNameRef.value || props.initialName || '')
const currentInitials = computed(() => getInitials(currentEntityName.value))

const sourceChipLabel = computed(() => {
  switch (logoSourceRef.value) {
  case LOGO_SOURCES.UPLOAD:
    return t('wp.logo.uploaded', 'Uploaded File')
  case LOGO_SOURCES.LOGO_DEV:
    return 'Logo.dev'
  case LOGO_SOURCES.BRANDFETCH:
    return 'Brandfetch'
  case LOGO_SOURCES.INITIALS:
    return t('wp.logo.auto_generated', 'Initials Fallback')
  default:
    return t('wp.logo.external', 'External Provider')
  }
})

const sourceChipColor = computed(() => {
  switch (logoSourceRef.value) {
  case LOGO_SOURCES.UPLOAD:
    return 'success'
  case LOGO_SOURCES.LOGO_DEV:
    return 'info'
  case LOGO_SOURCES.BRANDFETCH:
    return 'primary'
  default:
    return 'secondary'
  }
})

const sourceChipIcon = computed(() => {
  switch (logoSourceRef.value) {
  case LOGO_SOURCES.UPLOAD:
    return 'tabler-upload'
  case LOGO_SOURCES.LOGO_DEV:
    return 'tabler-world'
  case LOGO_SOURCES.BRANDFETCH:
    return 'tabler-brand-abstract'
  default:
    return 'tabler-letter-case'
  }
})

async function performSearch(query) {
  const normalizedQuery = query?.trim()

  if (!normalizedQuery || normalizedQuery.length < 2) return

  hasSearchedRef.value = true
  validationNotice.value = ''

  const domain = normalizedQuery.replace(/^https?:\/\//i, '').replace(/^www\./i, '').split('/')[0]
  if (/^[a-z0-9.-]+\.[a-z]{2,}$/i.test(domain)) {
    const result = await resolveByDomain(domain)

    searchResultsRef.value = result ? [result] : []

    return
  }

  searchResultsRef.value = await searchBrand(normalizedQuery)
}

function triggerFileInput() {
  fileInputRef.value?.click()
}

function isResultSelected(result) {
  if (!result) return false
  if (selectedResultRef.value && selectedResultRef.value.domain === result.domain && selectedResultRef.value.url === result.url) {
    return true
  }

  return Boolean(logoUrlRef.value && (logoUrlRef.value === result.url || logoUrlRef.value === result.logo_url))
}

function selectSearchResult(result) {
  selectedResultRef.value = result
  selectResult(result)
  hasSelectionRef.value = true
  validationNotice.value = ''
}

function validateAndProcessFile(file) {
  if (!file) return false

  const isImageMime = file.type?.startsWith('image/') || /\.(?:png|jpe?g|svg|webp|gif|bmp)$/i.test(file.name)
  if (!isImageMime) {
    const err = i18nError('wp.logo.error_invalid_type', 'Unsupported image format. Please select a PNG, SVG, JPG, or WebP.')

    validationNotice.value = err.message
    emit('error', err)

    return false
  }

  if (file.size > 2 * 1024 * 1024) {
    const err = i18nError('wp.logo.error_too_large', 'The chosen logo exceeds the 2 MB limit.')

    validationNotice.value = err.message
    emit('error', err)

    return false
  }

  validationNotice.value = ''
  uploadLogo(file)
  hasSelectionRef.value = true

  return true
}

function handleFileSelect(event) {
  const file = event.target.files?.[0]
  if (file) {
    validateAndProcessFile(file)
  }
  if (fileInputRef.value) {
    fileInputRef.value.value = ''
  }
}

function handleFileDrop(event) {
  isDraggingFile.value = false

  const file = event.dataTransfer?.files?.[0]
  if (file) {
    validateAndProcessFile(file)
  }
}

function useInitials() {
  clear()
  hasSelectionRef.value = true
  validationNotice.value = ''
}

async function resolveDomain(domain) {
  if (!domain) return
  const result = await resolveByDomain(domain)
  if (result) {
    selectedResultRef.value = result
    searchResultsRef.value = [result]
  }
}

function handleSave() {
  const payload = getLogoPayload()

  if (payload.source === LOGO_SOURCES.UPLOAD && !payload.file) {
    const err = i18nError('wp.logo.error_file_missing', 'The logo file is missing. Please select it again.')

    validationNotice.value = err.message
    emit('error', err)

    return
  }

  emit('save', payload)
  close()
}

const canSave = computed(() => {
  return hasSelectionRef.value || Boolean(logoUrlRef.value) || logoSourceRef.value === LOGO_SOURCES.INITIALS
})

onMounted(() => {
  if (props.initialName && !searchQueryRef.value) {
    searchQueryRef.value = props.initialName
  }
})
</script>

<template>
  <VDialog
    v-model="localOpen"
    :fullscreen="$vuetify.display.smAndDown"
    max-width="640"
    scrollable
    class="wp-logo-dialog"
  >
    <VCard class="wp-logo-card d-flex flex-column h-100">
      <!-- Standardized Dialog Header -->
      <VCardTitle class="d-flex align-center justify-space-between px-6 py-4 border-b">
        <div class="d-flex align-center gap-3 min-w-0">
          <VAvatar
            size="40"
            color="primary"
            variant="tonal"
            rounded="lg"
            class="flex-shrink-0"
          >
            <VIcon
              icon="tabler-photo-star"
              size="22"
            />
          </VAvatar>
          <div class="min-w-0">
            <h6 class="text-h6 font-weight-bold text-truncate mb-0">
              {{ title || $t('wp.logo.title', 'Brand & Entity Logo') }}
            </h6>
            <span class="text-caption text-medium-emphasis d-block text-truncate">
              {{ $t('wp.logo.subtitle', 'Search provider logos, resolve company domain, or upload a custom image.') }}
            </span>
          </div>
        </div>

        <IconBtn
          variant="text"
          size="small"
          :title="$t('wp.dialog.close', 'Close')"
          class="flex-shrink-0 ms-2"
          @click="close"
        >
          <VIcon
            icon="tabler-x"
            size="20"
          />
        </IconBtn>
      </VCardTitle>

      <!-- Dialog Body -->
      <VCardText class="px-6 py-5 grow overflow-y-auto d-flex flex-column gap-5">
        <!-- Error & Validation Alerts -->
        <VAlert
          v-if="errorRef"
          type="error"
          variant="tonal"
          density="comfortable"
          closable
          @click:close="errorRef = null"
        >
          {{ errorRef }}
        </VAlert>

        <VAlert
          v-if="validationNotice"
          type="warning"
          variant="tonal"
          density="comfortable"
          closable
          @click:close="validationNotice = ''"
        >
          {{ validationNotice }}
        </VAlert>

        <!-- ━━━ HERO PREVIEW & SOURCE SHOWCASE ━━━ -->
        <div class="wp-logo-showcase pa-4 rounded-lg border bg-surface">
          <div class="d-flex align-center gap-4 flex-wrap">
            <!-- Transparent checkerboard preview avatar -->
            <div class="wp-logo-checkerboard rounded-lg pa-2 d-flex align-center justify-center flex-shrink-0">
              <WpEntityLogo
                :src="logoUrlRef"
                :name="currentEntityName"
                :size="64"
                :source="logoSourceRef"
                :domain="domainRef || initialDomain"
                border-radius="10px"
              />
            </div>

            <!-- Metadata Details Block -->
            <div class="min-w-0 flex-grow-1">
              <div class="d-flex align-center gap-2 flex-wrap mb-1">
                <span class="text-subtitle-1 font-weight-bold text-truncate">
                  {{ currentEntityName || $t('Brand Logo') }}
                </span>
                <VChip
                  size="x-small"
                  :color="sourceChipColor"
                  variant="tonal"
                  label
                  class="text-capitalize font-weight-medium"
                >
                  <VIcon
                    start
                    :icon="sourceChipIcon"
                    size="13"
                  />
                  {{ sourceChipLabel }}
                </VChip>
              </div>

              <div class="d-flex align-center gap-2 flex-wrap text-caption text-medium-emphasis">
                <span
                  v-if="domainRef"
                  class="d-flex align-center gap-1 font-mono"
                >
                  <VIcon
                    icon="tabler-world"
                    size="14"
                  />
                  {{ domainRef }}
                </span>
                <span
                  v-if="uploadedFileRef"
                  class="d-flex align-center gap-1"
                >
                  <VIcon
                    icon="tabler-file-check"
                    size="14"
                    color="success"
                  />
                  {{ uploadedFileRef.name }} ({{ formatBytes(uploadedFileRef.size) }})
                </span>
                <span
                  v-else-if="!logoUrlRef"
                  class="text-disabled"
                >
                  {{ $t('Using fallback initials avatar') }} ({{ currentInitials }})
                </span>
              </div>
            </div>

            <!-- Use Initials Action -->
            <div class="d-flex align-center gap-2 flex-shrink-0">
              <VBtn
                v-if="logoUrlRef"
                variant="tonal"
                color="secondary"
                size="small"
                prepend-icon="tabler-letter-case"
                @click="useInitials"
              >
                {{ $t('wp.logo.use_initials', 'Use initials') }}
              </VBtn>
            </div>
          </div>
        </div>

        <!-- ━━━ MODE TOGGLE ━━━ -->
        <div class="d-flex align-center justify-space-between flex-wrap gap-2">
          <VBtnToggle
            v-model="activeTab"
            mandatory
            density="compact"
            color="primary"
            variant="outlined"
            divided
          >
            <VBtn
              value="search"
              size="small"
              prepend-icon="tabler-search"
            >
              {{ $t('wp.logo.search_brand', 'Search Brand / Domain') }}
            </VBtn>
            <VBtn
              value="upload"
              size="small"
              prepend-icon="tabler-upload"
            >
              {{ $t('wp.logo.upload_logo', 'Upload File') }}
            </VBtn>
          </VBtnToggle>

          <span class="text-caption text-disabled">
            {{ activeTab === 'search' ? $t('Brandfetch & Logo.dev') : $t('PNG, SVG, JPG, WebP ≤ 2 MB') }}
          </span>
        </div>

        <!-- ━━━ TAB 1: BRAND & DOMAIN SEARCH ━━━ -->
        <div
          v-if="activeTab === 'search'"
          class="d-flex flex-column gap-3"
        >
          <!-- Search input field with action button -->
          <div class="d-flex align-center gap-2">
            <VTextField
              v-model="searchQueryRef"
              :label="$t('wp.logo.search_label', 'Brand Name or Website Domain')"
              :placeholder="$t('wp.logo.search_placeholder', 'e.g. Acme Corp, google.com, stripe.com')"
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-search"
              clearable
              hide-details
              :loading="loadingRef"
              class="flex-grow-1"
              @keydown.enter.prevent="performSearch(searchQueryRef)"
            />
            <VBtn
              variant="tonal"
              color="primary"
              :disabled="!searchQueryRef || searchQueryRef.trim().length < 2 || loadingRef"
              :loading="loadingRef"
              prepend-icon="tabler-search"
              @click="performSearch(searchQueryRef)"
            >
              {{ $t('wp.logo.search', 'Search') }}
            </VBtn>
          </div>

          <!-- Loading state -->
          <div
            v-if="loadingRef"
            class="py-8 text-center"
          >
            <VProgressCircular
              indeterminate
              color="primary"
              size="38"
              class="mb-3"
            />
            <p class="text-body-2 font-weight-medium mb-1">
              {{ $t('wp.logo.searching', 'Searching brand providers...') }}
            </p>
            <span class="text-caption text-disabled">
              {{ $t('Querying Brandfetch and Logo.dev databases') }}
            </span>
          </div>

          <!-- Search results list -->
          <div
            v-else-if="searchResultsRef.length"
            class="wp-logo-results-card rounded-lg border overflow-hidden"
          >
            <div class="px-3 py-2 bg-var-theme-background border-b d-flex align-center justify-space-between flex-wrap gap-2">
              <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                {{ $t('Search Results') }} ({{ searchResultsRef.length }})
              </span>
              <span class="text-caption text-disabled">{{ $t('Click a brand to select logo') }}</span>
            </div>

            <VList
              density="compact"
              class="pa-1 wp-logo-search-results"
            >
              <VListItem
                v-for="result in searchResultsRef"
                :key="result.domain || result.name || result.url"
                class="rounded mb-1 cursor-pointer wp-logo-result-item"
                :class="{ 'wp-logo-result-item--selected': isResultSelected(result) }"
                @click="selectSearchResult(result)"
              >
                <template #prepend>
                  <div class="wp-logo-result-avatar rounded me-3 pa-1 flex-shrink-0">
                    <WpEntityLogo
                      :src="result.url || result.icon || result.logo_url"
                      :name="result.name"
                      :size="36"
                      :source="result.source || 'brandfetch'"
                      :domain="result.domain"
                      border-radius="6px"
                    />
                  </div>
                </template>

                <VListItemTitle class="font-weight-medium text-body-2">
                  {{ result.name || result.domain }}
                </VListItemTitle>

                <VListItemSubtitle class="text-caption font-mono text-disabled">
                  {{ result.domain || result.name }}
                </VListItemSubtitle>

                <template #append>
                  <div class="d-flex align-center gap-2">
                    <VChip
                      size="x-small"
                      variant="tonal"
                      :color="result.source === 'logo_dev' ? 'info' : 'primary'"
                      label
                      class="text-uppercase font-weight-bold"
                    >
                      {{ result.source === 'logo_dev' ? 'Logo.dev' : 'Brandfetch' }}
                    </VChip>
                    <VIcon
                      v-if="isResultSelected(result)"
                      icon="tabler-circle-check-filled"
                      color="primary"
                      size="20"
                    />
                  </div>
                </template>
              </VListItem>
            </VList>
          </div>

          <!-- Empty state after searching -->
          <div
            v-else-if="hasSearchedRef && !loadingRef && !errorRef"
            class="py-8 text-center rounded-lg border border-dashed pa-4"
          >
            <VIcon
              icon="tabler-search-off"
              size="40"
              class="mb-2 text-disabled opacity-60"
            />
            <p class="text-body-2 font-weight-medium mb-1">
              {{ $t('wp.logo.no_results', 'No logos found for this query.') }}
            </p>
            <p class="text-caption text-medium-emphasis mb-3">
              {{ $t('Try searching with a direct domain name (e.g. domain.com) or upload your custom logo file.') }}
            </p>
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-upload"
              @click="activeTab = 'upload'"
            >
              {{ $t('wp.logo.upload_logo', 'Upload Logo File') }}
            </VBtn>
          </div>

          <!-- Helpful initial search hint -->
          <div
            v-else-if="!hasSearchedRef"
            class="pa-4 rounded-lg border bg-surface text-center"
          >
            <VIcon
              icon="tabler-world-search"
              size="32"
              color="primary"
              class="mb-2 opacity-75"
            />
            <p class="text-body-2 font-weight-medium mb-1">
              {{ $t('Lookup Brand or Domain') }}
            </p>
            <p class="text-caption text-medium-emphasis mb-0">
              {{ $t('Enter a company name or website domain (e.g. google.com, stripe.com, bosch.com) to automatically resolve high-resolution branding logos.') }}
            </p>
          </div>
        </div>

        <!-- ━━━ TAB 2: DIRECT FILE UPLOAD ━━━ -->
        <div
          v-else
          class="d-flex flex-column gap-3"
        >
          <!-- Drag & drop zone -->
          <div
            class="wp-logo-dropzone pa-6 rounded-lg text-center cursor-pointer"
            :class="{
              'wp-logo-dropzone--drag': isDraggingFile,
              'wp-logo-dropzone--has-file': Boolean(uploadedFileRef),
            }"
            tabindex="0"
            @click="triggerFileInput"
            @keydown.enter.prevent="triggerFileInput"
            @dragover.prevent="isDraggingFile = true"
            @dragleave.prevent="isDraggingFile = false"
            @drop.prevent="handleFileDrop"
          >
            <VIcon
              :icon="uploadedFileRef ? 'tabler-file-check' : 'tabler-cloud-upload'"
              size="48"
              :color="uploadedFileRef ? 'success' : 'primary'"
              class="mb-2"
            />

            <p class="text-body-2 font-weight-bold mb-1">
              {{ uploadedFileRef ? uploadedFileRef.name : $t('wp.logo.upload_hint', 'Click or drag a logo file to upload') }}
            </p>

            <p class="text-caption text-medium-emphasis mb-3">
              {{ uploadedFileRef
                ? `${formatBytes(uploadedFileRef.size)} • ${uploadedFileRef.type || 'image'}`
                : $t('wp.logo.uploaded_file_hint', 'PNG, SVG, JPG, or WebP up to 2MB (transparent PNG or vector SVG recommended)')
              }}
            </p>

            <div
              v-if="uploadedFileRef"
              class="d-flex justify-center gap-2"
            >
              <VBtn
                size="small"
                variant="tonal"
                color="primary"
                prepend-icon="tabler-refresh"
                @click.stop="triggerFileInput"
              >
                {{ $t('Replace File') }}
              </VBtn>
            </div>
            <div v-else>
              <VBtn
                size="small"
                variant="tonal"
                color="primary"
                prepend-icon="tabler-folder-open"
                @click.stop="triggerFileInput"
              >
                {{ $t('Browse Files') }}
              </VBtn>
            </div>
          </div>

          <!-- Hidden native file input -->
          <input
            ref="fileInputRef"
            type="file"
            accept="image/png,image/jpeg,image/svg+xml,image/webp,image/gif"
            style="display: none;"
            @change="handleFileSelect"
          >
        </div>
      </VCardText>

      <VDivider />

      <!-- Standardized Dialog Actions -->
      <VCardActions class="px-6 py-4 d-flex align-center justify-space-between flex-wrap gap-2">
        <div class="d-flex align-center gap-2">
          <span class="text-caption text-medium-emphasis">
            {{ $t('Selected') }}:
          </span>
          <VChip
            size="x-small"
            :color="sourceChipColor"
            variant="tonal"
            label
            class="font-weight-medium"
          >
            {{ sourceChipLabel }}
          </VChip>
        </div>

        <div class="d-flex align-center gap-2">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="close"
          >
            {{ $t('wp.dialog.close', 'Cancel') }}
          </VBtn>

          <VBtn
            color="primary"
            variant="elevated"
            prepend-icon="tabler-check"
            :disabled="!canSave"
            :loading="saving"
            @click="handleSave"
          >
            {{ $t('wp.logo.save', 'Save Logo') }}
          </VBtn>
        </div>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.wp-logo-dialog .wp-logo-card {
  max-block-size: 88vh;
}

.wp-logo-checkerboard {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background-color: rgb(var(--v-theme-surface));
  background-image:
    linear-gradient(45deg, rgba(var(--v-theme-on-surface), 0.05) 25%, transparent 25%),
    linear-gradient(-45deg, rgba(var(--v-theme-on-surface), 0.05) 25%, transparent 25%),
    linear-gradient(45deg, transparent 75%, rgba(var(--v-theme-on-surface), 0.05) 75%),
    linear-gradient(-45deg, transparent 75%, rgba(var(--v-theme-on-surface), 0.05) 75%);
  background-position: 0 0, 0 8px, 8px -8px, -8px 0;
  background-size: 16px 16px;
}

.wp-logo-dropzone {
  border: 2px dashed rgba(var(--v-border-color), 0.5);
  border-radius: 10px;
  background: rgba(var(--v-theme-surface), 0.6);
  transition: border-color 0.18s ease, background-color 0.18s ease;

  &:hover,
  &--drag {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.04);
  }

  &--has-file {
    border-color: rgba(var(--v-theme-success), 0.6);
    background: rgba(var(--v-theme-success), 0.03);
  }
}

.wp-logo-search-results {
  max-block-size: 260px;
  overflow-y: auto;
}

.wp-logo-result-item {
  border: 1px solid transparent;
  transition: background-color 0.15s ease, border-color 0.15s ease;

  &:hover {
    background-color: rgba(var(--v-theme-primary), 0.05);
  }

  &--selected {
    border-color: rgba(var(--v-theme-primary), 0.35);
    background-color: rgba(var(--v-theme-primary), 0.08) !important;
  }
}

.wp-logo-result-avatar {
  border: 1px solid rgba(var(--v-border-color), 0.5);
  background: rgb(var(--v-theme-surface));
}

@media (max-width: 600px) {
  .wp-logo-dialog .wp-logo-card {
    max-block-size: 100vh;
    block-size: 100vh;
  }
}
</style>
