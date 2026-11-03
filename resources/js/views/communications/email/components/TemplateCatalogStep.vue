<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'

const props = defineProps({
  modelValue: { type: [Number, String], default: null },
  templates: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  hasRecordContext: { type: Boolean, default: false },
  recordContextLabel: { type: String, default: null },
  initialLanguage: { type: String, default: 'fr' },
})

const emit = defineEmits(['update:modelValue', 'select'])

const { t } = useI18n()

// Filters state
const selectedCategory = ref('all')
const selectedLanguage = ref(props.initialLanguage || 'fr')
const searchQuery = ref('')

// Preview dialog state
const isPreviewOpen = ref(false)
const previewTemplate = ref(null)

watch(() => props.initialLanguage, newLang => {
  if (newLang && ['fr', 'en', 'ar'].includes(newLang)) {
    selectedLanguage.value = newLang
  }
})

const CATEGORY_META = {
  all: { label: 'Tous', icon: 'tabler-layout-grid', color: 'primary' },
  general_communication: { label: 'Général', icon: 'tabler-message-dots', color: 'primary' },
  quotation_submission: { label: 'Devis', icon: 'tabler-file-dollar', color: 'info' },
  sales_order_communication: { label: 'Commandes', icon: 'tabler-shopping-cart', color: 'success' },
  purchase_order_submission: { label: 'Achats', icon: 'tabler-truck-delivery', color: 'warning' },
  invoice_delivery: { label: 'Factures', icon: 'tabler-file-invoice', color: 'error' },
  payment_reminder: { label: 'Relances', icon: 'tabler-alarm', color: 'secondary' },
  supplier_request: { label: 'Consultations', icon: 'tabler-search', color: 'info' },
  delivery_confirmation: { label: 'Livraisons', icon: 'tabler-package', color: 'success' },
  mission_confirmation: { label: 'Missions', icon: 'tabler-tool', color: 'primary' },
  maintenance_notification: { label: 'Maintenance', icon: 'tabler-adjustments-check', color: 'warning' },
  system_notification: { label: 'Système', icon: 'tabler-bell', color: 'secondary' },
}

function getCategoryIcon(cat) {
  return CATEGORY_META[cat]?.icon || 'tabler-template'
}

function getCategoryColor(cat) {
  return CATEGORY_META[cat]?.color || 'primary'
}

function getCategoryLabel(cat) {
  return CATEGORY_META[cat]?.label || cat
}

// Category tabs with counts
const categoriesWithCounts = computed(() => {
  const currentLangTemplates = props.templates.filter(
    tmpl => !selectedLanguage.value || tmpl.language === selectedLanguage.value,
  )

  const counts = { all: currentLangTemplates.length }

  currentLangTemplates.forEach(t => {
    if (t.category) {
      counts[t.category] = (counts[t.category] || 0) + 1
    }
  })

  const list = [{ value: 'all', label: 'Tous', icon: 'tabler-layout-grid', count: counts.all || 0 }]

  Object.keys(CATEGORY_META).forEach(key => {
    if (key !== 'all' && (counts[key] || 0) > 0) {
      list.push({
        value: key,
        label: CATEGORY_META[key].label,
        icon: CATEGORY_META[key].icon,
        count: counts[key] || 0,
      })
    }
  })

  return list
})

// Filtered templates
const filteredTemplates = computed(() => {
  return props.templates.filter(tmpl => {
    const matchCat = selectedCategory.value === 'all' || tmpl.category === selectedCategory.value
    const matchLang = !selectedLanguage.value || tmpl.language === selectedLanguage.value
    const q = searchQuery.value.trim().toLowerCase()

    const matchQuery = !q
      || (tmpl.name && tmpl.name.toLowerCase().includes(q))
      || (tmpl.subject && tmpl.subject.toLowerCase().includes(q))
      || (tmpl.category && tmpl.category.toLowerCase().includes(q))
      || (tmpl.description && tmpl.description.toLowerCase().includes(q))

    return matchCat && matchLang && matchQuery
  })
})

function selectTemplate(tmpl) {
  emit('update:modelValue', tmpl?.id || null)
  emit('select', tmpl || null)
}

function selectBlank() {
  emit('update:modelValue', null)
  emit('select', null)
}

function openPreview(tmpl) {
  previewTemplate.value = tmpl
  isPreviewOpen.value = true
}

function useFromPreview() {
  if (previewTemplate.value) {
    selectTemplate(previewTemplate.value)
  }
  isPreviewOpen.value = false
}
</script>

<template>
  <div class="template-catalog-step py-2">
    <!-- ── Context Information Banner ──────────────────────────────────────── -->
    <VAlert
      v-if="hasRecordContext"
      type="success"
      variant="tonal"
      density="compact"
      class="mb-4"
      icon="tabler-circle-check"
    >
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <div>
          <span class="font-weight-bold">
            {{ t('communications.email.context_active_title', 'Contexte métier actif :') }}
          </span>
          <span class="ms-1 font-weight-medium text-high-emphasis">
            {{ recordContextLabel || t('communications.email.record_linked', 'Enregistrement lié') }}
          </span>
          <div class="text-caption text-medium-emphasis mt-1">
            {{ t('communications.email.context_active_desc', 'Les données réelles de cet enregistrement (références, dates, montants) seront automatiquement injectées dans les modèles correspondants.') }}
          </div>
        </div>
        <VChip
          size="x-small"
          color="success"
          variant="elevated"
        >
          {{ t('communications.email.variables_ready', 'Données résolues') }}
        </VChip>
      </div>
    </VAlert>

    <VAlert
      v-else
      type="info"
      variant="tonal"
      density="compact"
      class="mb-4"
      icon="tabler-info-circle"
    >
      <div class="text-caption">
        <strong>{{ t('communications.email.general_composer_mode', 'Rédaction générale (Centre d’emails)') }} :</strong>
        {{ t('communications.email.general_composer_desc', 'Les modèles de communication générale sont prêts à l’envoi. Les modèles nécessitant un enregistrement (devis, factures, ordres) afficheront des variables qui devront être complétées avant l’envoi.') }}
      </div>
    </VAlert>

    <!-- ── Filter & Search Controls Bar ───────────────────────────────────── -->
    <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-3">
      <!-- Search Input -->
      <div
        class="flex-grow-1"
        style="min-inline-size: 240px; max-inline-size: 380px;"
      >
        <VTextField
          v-model="searchQuery"
          density="compact"
          variant="outlined"
          hide-details
          prepend-inner-icon="tabler-search"
          :placeholder="t('communications.email.search_templates_placeholder', 'Rechercher par titre, objet ou mot-clé...')"
          clearable
        />
      </div>

      <!-- Language Selector Toggle -->
      <div class="d-flex align-center gap-2">
        <span class="text-caption font-weight-medium text-medium-emphasis">
          {{ t('communications.email.language_label', 'Langue :') }}
        </span>
        <VBtnToggle
          v-model="selectedLanguage"
          mandatory
          density="compact"
          color="primary"
          variant="outlined"
          divided
          size="small"
          class="wp-toggle-group"
        >
          <VBtn
            value="fr"
            size="small"
            class="px-3"
          >
            <VIcon
              start
              icon="tabler-language"
              size="14"
            />
            FR
          </VBtn>
          <VBtn
            value="en"
            size="small"
            class="px-3"
          >
            <VIcon
              start
              icon="tabler-language"
              size="14"
            />
            EN
          </VBtn>
          <VBtn
            value="ar"
            size="small"
            class="px-3"
          >
            <VIcon
              start
              icon="tabler-language"
              size="14"
            />
            العربية
          </VBtn>
        </VBtnToggle>
      </div>
    </div>

    <!-- ── Category Filter Chips ──────────────────────────────────────────── -->
    <div class="d-flex flex-wrap gap-1 align-center mb-4 pb-1">
      <VChip
        v-for="cat in categoriesWithCounts"
        :key="cat.value"
        size="small"
        :variant="selectedCategory === cat.value ? 'elevated' : 'tonal'"
        :color="selectedCategory === cat.value ? 'primary' : 'default'"
        clickable
        @click="selectedCategory = cat.value"
      >
        <VIcon
          start
          :icon="cat.icon"
          size="14"
        />
        {{ cat.label }}
        <VChip
          size="x-small"
          class="ms-1 px-1"
          :color="selectedCategory === cat.value ? 'surface' : 'default'"
          variant="elevated"
          style="min-inline-size: 18px; block-size: 16px; font-size: 10px;"
        >
          {{ cat.count }}
        </VChip>
      </VChip>
    </div>

    <!-- ── Loading State ──────────────────────────────────────────────────── -->
    <div
      v-if="loading"
      class="text-center py-8"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="36"
      />
      <div class="text-caption text-medium-emphasis mt-2">
        {{ t('communications.email.loading_templates', 'Chargement des modèles prédéfinis...') }}
      </div>
    </div>

    <!-- ── Template Cards Grid ────────────────────────────────────────────── -->
    <div
      v-else
      class="template-grid"
    >
      <PerfectScrollbar
        :options="{ wheelPropagation: false }"
        style="max-block-size: 460px; position: relative; overflow: hidden !important;"
        class="pe-1"
      >
        <VRow dense>
          <!-- ── Card 0: Blank Message (Option sans modèle) ──────────────────── -->
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <VCard
              variant="outlined"
              class="h-100 transition-all border-dashed"
              :class="[modelValue === null ? 'border-primary border-opacity-100 bg-primary-subtle' : '']"
              style="border-width: 2px;"
              hover
              @click="selectBlank"
            >
              <VCardItem class="pb-1">
                <template #prepend>
                  <div class="rounded-circle pa-2 bg-var-theme-background">
                    <VIcon
                      icon="tabler-pencil"
                      size="20"
                      color="primary"
                    />
                  </div>
                </template>
                <VCardTitle class="text-subtitle-2 font-weight-bold">
                  {{ t('communications.email.blank_template_title', 'Message Libre / Sans Modèle') }}
                </VCardTitle>
                <template #append>
                  <VChip
                    size="x-small"
                    variant="tonal"
                    color="secondary"
                  >
                    {{ t('communications.email.custom', 'Libre') }}
                  </VChip>
                </template>
              </VCardItem>

              <VCardText class="pt-1 pb-3">
                <div class="text-caption text-medium-emphasis mb-2">
                  {{ t('communications.email.blank_template_desc', 'Rédigez directement un email entièrement personnalisé avec l’éditeur riche sans appliquer de structure prédéfinie.') }}
                </div>
                <div class="d-flex align-center gap-1 text-caption text-success font-weight-medium">
                  <VIcon
                    icon="tabler-sparkles"
                    size="14"
                  />
                  {{ t('communications.email.all_languages_ready', 'Compatible toutes langues') }}
                </div>
              </VCardText>

              <VSpacer />
              <VDivider />

              <VCardActions class="pa-2 d-flex justify-end">
                <VBtn
                  size="small"
                  :variant="modelValue === null ? 'elevated' : 'tonal'"
                  :color="modelValue === null ? 'primary' : 'default'"
                  prepend-icon="tabler-check"
                  @click.stop="selectBlank"
                >
                  {{ modelValue === null ? t('communications.email.selected', 'Sélectionné') : t('communications.email.choose_blank', 'Choisir') }}
                </VBtn>
              </VCardActions>
            </VCard>
          </VCol>

          <!-- ── Cards 1..N: Predefined System Templates ─────────────────────── -->
          <VCol
            v-for="tmpl in filteredTemplates"
            :key="tmpl.id"
            cols="12"
            sm="6"
            md="4"
          >
            <VCard
              variant="outlined"
              class="h-100 transition-all d-flex flex-column"
              :class="[
                modelValue === tmpl.id ? 'border-primary border-opacity-100 bg-primary-subtle elevation-1' : '',
                tmpl.language === 'ar' ? 'rtl-card' : ''
              ]"
              hover
              @click="selectTemplate(tmpl)"
            >
              <VCardItem class="pb-1">
                <template #prepend>
                  <VIcon
                    :icon="getCategoryIcon(tmpl.category)"
                    size="20"
                    :color="getCategoryColor(tmpl.category)"
                  />
                </template>
                <VCardTitle class="text-subtitle-2 font-weight-bold text-truncate">
                  {{ tmpl.name }}
                </VCardTitle>
                <template #append>
                  <VChip
                    size="x-small"
                    variant="tonal"
                    :color="tmpl.language === 'ar' ? 'success' : (tmpl.language === 'en' ? 'info' : 'primary')"
                  >
                    {{ tmpl.language.toUpperCase() }}
                  </VChip>
                </template>
              </VCardItem>

              <VCardText class="pt-1 pb-2 flex-grow-1">
                <!-- Category and Context requirement chips -->
                <div class="d-flex align-center flex-wrap gap-1 mb-2">
                  <VChip
                    size="x-small"
                    variant="outlined"
                    :color="getCategoryColor(tmpl.category)"
                  >
                    {{ getCategoryLabel(tmpl.category) }}
                  </VChip>

                  <!-- Context requirement badge -->
                  <VTooltip
                    v-if="tmpl.requires_record_context"
                    location="top"
                  >
                    <template #activator="{ props: tipProps }">
                      <VChip
                        v-bind="tipProps"
                        size="x-small"
                        :variant="hasRecordContext ? 'tonal' : 'outlined'"
                        :color="hasRecordContext ? 'success' : 'warning'"
                      >
                        <VIcon
                          start
                          :icon="hasRecordContext ? 'tabler-check' : 'tabler-alert-triangle'"
                          size="12"
                        />
                        {{ hasRecordContext ? t('communications.email.context_ready', 'Données prêtes') : t('communications.email.context_required', 'Nécessite enregistrement') }}
                      </VChip>
                    </template>
                    <span>
                      {{ hasRecordContext
                        ? t('communications.email.context_ready_tooltip', 'Les variables de document seront résolues automatiquement.')
                        : t('communications.email.context_required_tooltip', 'Ce modèle contient des variables de document qui devront être éditées manuellement à l’étape suivante.') }}
                    </span>
                  </VTooltip>

                  <VChip
                    v-else
                    size="x-small"
                    variant="tonal"
                    color="secondary"
                  >
                    <VIcon
                      start
                      icon="tabler-sparkles"
                      size="12"
                    />
                    {{ t('communications.email.ready_to_send', 'Général') }}
                  </VChip>
                </div>

                <!-- Subject Preview -->
                <div
                  class="text-caption font-weight-medium text-truncate mb-1 text-high-emphasis"
                  :dir="tmpl.language === 'ar' ? 'rtl' : 'ltr'"
                >
                  <VIcon
                    icon="tabler-heading"
                    size="14"
                    class="me-1 text-medium-emphasis"
                  />
                  {{ tmpl.subject }}
                </div>

                <!-- Description / Content Excerpt -->
                <div
                  class="text-caption text-medium-emphasis text-truncate-2"
                  style="max-block-size: 34px; overflow: hidden; line-height: 1.4;"
                  :dir="tmpl.language === 'ar' ? 'rtl' : 'ltr'"
                >
                  {{ tmpl.description || tmpl.body_text?.slice(0, 110) || '' }}
                </div>
              </VCardText>

              <VDivider />

              <VCardActions class="pa-2 d-flex justify-space-between align-center">
                <VBtn
                  size="x-small"
                  variant="text"
                  color="secondary"
                  prepend-icon="tabler-eye"
                  @click.stop="openPreview(tmpl)"
                >
                  {{ t('communications.email.preview', 'Aperçu') }}
                </VBtn>

                <VBtn
                  size="x-small"
                  :variant="modelValue === tmpl.id ? 'elevated' : 'tonal'"
                  color="primary"
                  prepend-icon="tabler-check"
                  @click.stop="selectTemplate(tmpl)"
                >
                  {{ modelValue === tmpl.id ? t('communications.email.selected', 'Sélectionné') : t('communications.email.use_template', 'Sélectionner') }}
                </VBtn>
              </VCardActions>
            </VCard>
          </VCol>
        </VRow>
      </PerfectScrollbar>

      <!-- Empty state when no templates match search/filters -->
      <div
        v-if="filteredTemplates.length === 0"
        class="text-center py-6 px-4"
      >
        <VIcon
          icon="tabler-search-off"
          size="40"
          color="secondary"
          class="mb-2"
        />
        <div class="text-subtitle-2 font-weight-medium">
          {{ t('communications.email.no_templates_found', 'Aucun modèle ne correspond à vos critères') }}
        </div>
        <div class="text-caption text-medium-emphasis mt-1">
          {{ t('communications.email.try_adjusting_filters', 'Essayez d’ajuster la langue ou le terme de recherche.') }}
        </div>
      </div>
    </div>

    <!-- ── Template Preview Dialog ────────────────────────────────────────── -->
    <VDialog
      v-model="isPreviewOpen"
      max-width="700"
      scrollable
    >
      <VCard v-if="previewTemplate">
        <VCardItem class="border-b py-3">
          <template #prepend>
            <VIcon
              :icon="getCategoryIcon(previewTemplate.category)"
              :color="getCategoryColor(previewTemplate.category)"
              size="24"
            />
          </template>
          <VCardTitle class="text-h6">
            {{ previewTemplate.name }}
          </VCardTitle>
          <template #append>
            <div class="d-flex align-center gap-2">
              <VChip
                size="small"
                variant="tonal"
                :color="previewTemplate.language === 'ar' ? 'success' : 'primary'"
              >
                {{ previewTemplate.language.toUpperCase() }}
              </VChip>
              <VBtn
                icon="tabler-x"
                variant="text"
                size="small"
                @click="isPreviewOpen = false"
              />
            </div>
          </template>
        </VCardItem>

        <VCardText class="pa-4">
          <!-- Subject preview header -->
          <div class="mb-3 pa-3 rounded bg-var-theme-background border">
            <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-1">
              {{ t('communications.email.subject', 'Objet') }} :
            </div>
            <div
              class="text-body-2 font-weight-medium"
              :dir="previewTemplate.language === 'ar' ? 'rtl' : 'ltr'"
            >
              {{ previewTemplate.subject }}
            </div>
          </div>

          <!-- Notice if template requires record context and none is provided -->
          <VAlert
            v-if="previewTemplate.requires_record_context && !hasRecordContext"
            type="warning"
            variant="tonal"
            density="compact"
            class="mb-3"
            icon="tabler-alert-triangle"
          >
            <div class="text-caption">
              {{ t('communications.email.preview_context_warning', 'Ce modèle intègre des variables de document qui apparaîtront sous forme de balises (ex. document.reference). Vous pourrez les personnaliser directement à l’étape suivante.') }}
            </div>
          </VAlert>

          <!-- Rendered HTML frame -->
          <div
            class="border rounded overflow-hidden bg-white"
            style="min-block-size: 320px; max-block-size: 460px;"
          >
            <iframe
              :srcdoc="previewTemplate.body_html"
              style="inline-size: 100%; block-size: 440px; border: none;"
              sandbox="allow-same-origin"
              title="Template Preview"
            />
          </div>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-3 d-flex justify-space-between">
          <VBtn
            variant="text"
            color="secondary"
            @click="isPreviewOpen = false"
          >
            {{ t('common.close', 'Fermer') }}
          </VBtn>

          <VBtn
            color="primary"
            variant="elevated"
            prepend-icon="tabler-check"
            @click="useFromPreview"
          >
            {{ t('communications.email.use_this_template', 'Utiliser ce modèle') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.template-grid {
  min-block-size: 240px;
}

.text-truncate-2 {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
}

.rtl-card {
  text-align: right;
}

:deep(.v-btn-group.v-btn-toggle),
:deep(.wp-toggle-group) {
  overflow: visible !important;

  .v-btn {
    inline-size: auto !important;
    min-inline-size: fit-content !important;
    max-inline-size: none !important;
    padding-inline: 12px !important;
    white-space: nowrap !important;

    &.v-btn--density-compact,
    &.v-btn--density-comfortable,
    &.v-btn--density-default {
      inline-size: auto !important;
      min-inline-size: fit-content !important;
      max-inline-size: none !important;
      padding-inline: 12px !important;
    }
  }
}
</style>
