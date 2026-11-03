<script setup>
/* eslint-disable camelcase */
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { $api } from '@/utils/api'
import { useAppSnackbar } from '@/composables/useAppSnackbar'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  partnerType: { type: String, required: true },
  partnerIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'completed', 'close'])

const { t } = useI18n()
const { success: notifySuccess, error: notifyError } = useAppSnackbar()

// ── State ────────────────────────────────────────────────────────────────────
const isPreviewLoading = ref(false)
const isSubmitting = ref(false)
const isExecuting = ref(false)
const previewData = ref(null)
const batchData = ref(null)
const pollTimer = ref(null)
const isSamplePreviewOpen = ref(false)

// Config options
const selectedSenderProfileId = ref(null)
const selectedLanguage = ref('fr')
const selectedRule = ref('primary_email_first')
const includePdf = ref(true)

// Rule options
const ruleOptions = computed(() => [
  {
    value: 'primary_email_first',
    title: t('bp.bulk_email.rule_primary_email_first', 'Email principal en priorité (recommandé)'),
    desc: t('bp.bulk_email.rule_primary_email_first_desc', 'Utilise l’email du partenaire, ou le premier contact si absent.'),
  },
  {
    value: 'primary_contact_first',
    title: t('bp.bulk_email.rule_primary_contact_first', 'Premier contact en priorité'),
    desc: t('bp.bulk_email.rule_primary_contact_first_desc', 'Utilise le contact principal si disponible, sinon l’email général.'),
  },
  {
    value: 'partner_email_only',
    title: t('bp.bulk_email.rule_partner_email_only', 'Email principal de l’entité uniquement'),
    desc: t('bp.bulk_email.rule_partner_email_only_desc', 'Ignore les contacts, requiert l’email général de l’entité.'),
  },
  {
    value: 'contacts_only',
    title: t('bp.bulk_email.rule_contacts_only', 'Contacts uniquement'),
    desc: t('bp.bulk_email.rule_contacts_only_desc', 'Envoie uniquement aux contacts déclarés du partenaire.'),
  },
])

// Partner category label
const categoryLabel = computed(() => {
  return matchCategory(props.partnerType)
})

function matchCategory(type) {
  switch (type) {
  case 'client':
    return t('bp.clients', 'Clients')
  case 'supplier':
    return t('bp.suppliers', 'Fournisseurs')
  case 'organisation':
    return t('bp.organisations', 'Organisations')
  case 'service_provider':
    return t('bp.service_providers', 'Prestataires')
  default:
    return t('bp.partners', 'Partenaires')
  }
}

// Normalized endpoint route segment
const routeType = computed(() => {
  switch (props.partnerType) {
  case 'client':
    return 'clients'
  case 'supplier':
    return 'suppliers'
  case 'organisation':
    return 'organisations'
  case 'service_provider':
    return 'service-providers'
  default:
    return `${props.partnerType}s`
  }
})

// ── Pre-flight Preview Loader ───────────────────────────────────────────────
async function fetchPreview() {
  if (!props.partnerIds || props.partnerIds.length === 0) {
    previewData.value = null

    return
  }

  isPreviewLoading.value = true
  try {
    const payload = {
      partner_ids: props.partnerIds,
      recipient_rule: selectedRule.value,
      language: selectedLanguage.value,
      sender_profile_id: selectedSenderProfileId.value || undefined,
    }

    const res = await $api(`/bp/${routeType.value}/bulk-email/preview`, {
      method: 'POST',
      body: payload,
    })

    previewData.value = res.data

    if (!selectedSenderProfileId.value && res.data.default_sender_profile_id) {
      selectedSenderProfileId.value = res.data.default_sender_profile_id
    }
  } catch (err) {
    notifyError(err.message || t('bp.bulk_email.preview_error', 'Erreur lors du calcul du pré-envoi.'))
  } finally {
    isPreviewLoading.value = false
  }
}

// ── Submit Bulk Batch ────────────────────────────────────────────────────────
async function startBulkSend() {
  if (!previewData.value || previewData.value.ready_count === 0) return

  isSubmitting.value = true
  try {
    const payload = {
      partner_ids: props.partnerIds,
      recipient_rule: selectedRule.value,
      language: selectedLanguage.value,
      sender_profile_id: selectedSenderProfileId.value,
      include_pdf: includePdf.value,
      idempotency_key: `bulk-${props.partnerType}-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    }

    const res = await $api(`/bp/${routeType.value}/bulk-email`, {
      method: 'POST',
      body: payload,
    })

    batchData.value = res.data
    isExecuting.value = true
    startPolling(res.data.id)
    notifySuccess(t('bp.bulk_email.batch_started', 'Traitement par lot démarré.'))
  } catch (err) {
    notifyError(err.message || t('bp.bulk_email.send_error', 'Échec du lancement de l’envoi groupé.'))
  } finally {
    isSubmitting.value = false
  }
}

// ── Polling Batch Status ────────────────────────────────────────────────────
function startPolling(batchId) {
  stopPolling()

  pollTimer.value = setInterval(async () => {
    try {
      const res = await $api(`/bp/bulk-email/batches/${batchId}`)

      batchData.value = res.data

      const isTerminal = ['completed', 'partial_failure', 'failed'].includes(res.data.status)
      if (isTerminal) {
        stopPolling()
        emit('completed', res.data)
      }
    } catch {
      stopPolling()
    }
  }, 1500)
}

function stopPolling() {
  if (pollTimer.value) {
    clearInterval(pollTimer.value)
    pollTimer.value = null
  }
}

// ── Progress Computation ────────────────────────────────────────────────────
const executionProgress = computed(() => {
  if (!batchData.value || !batchData.value.total_count) return 0
  const processed = (batchData.value.sent_count || 0) + (batchData.value.failed_count || 0) + (batchData.value.skipped_count || 0)

  return Math.min(100, Math.round((processed / batchData.value.total_count) * 100))
})

const isBatchFinished = computed(() => {
  return batchData.value && ['completed', 'partial_failure', 'failed'].includes(batchData.value.status)
})

function closeDialog() {
  stopPolling()
  emit('update:modelValue', false)
  emit('close')

  // Reset execution view after closing
  setTimeout(() => {
    isExecuting.value = false
    batchData.value = null
    previewData.value = null
  }, 300)
}

// Watchers
watch(
  () => props.modelValue,
  isOpen => {
    if (isOpen) {
      isExecuting.value = false
      batchData.value = null
      fetchPreview()
    } else {
      stopPolling()
    }
  },
)

watch([selectedRule, selectedLanguage], () => {
  if (props.modelValue && !isExecuting.value) {
    fetchPreview()
  }
})

onBeforeUnmount(() => {
  stopPolling()
})
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="840"
    persistent
    scrollable
    @update:model-value="closeDialog"
  >
    <VCard class="bp-bulk-email-dialog">
      <!-- ── Dialog Header ────────────────────────────────────────────────── -->
      <VCardItem class="border-b py-3 px-4">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            rounded="lg"
            size="38"
            class="me-2"
          >
            <VIcon
              icon="tabler-mail-fast"
              size="22"
            />
          </VAvatar>
        </template>
        <VCardTitle class="text-h6 font-weight-bold">
          {{ t('bp.bulk_email.dialog_title', 'Envoi Groupé Contextuel d\'Emails') }}
        </VCardTitle>
        <VCardSubtitle class="text-caption">
          {{ categoryLabel }} &bull; {{ partnerIds.length }} {{ t('bp.bulk_email.records_selected', 'enregistrements sélectionnés') }}
        </VCardSubtitle>
        <template #append>
          <VBtn
            icon="tabler-x"
            variant="text"
            density="compact"
            size="small"
            :disabled="isSubmitting || (isExecuting && !isBatchFinished)"
            @click="closeDialog"
          />
        </template>
      </VCardItem>

      <!-- ── Dialog Body ──────────────────────────────────────────────────── -->
      <VCardText
        class="pa-4"
        style="max-block-size: 600px;"
      >
        <!-- ── In-Flight Execution & Live Progress ────────────────────────── -->
        <div
          v-if="isExecuting"
          class="execution-view py-2"
        >
          <VAlert
            v-if="isBatchFinished"
            :type="batchData?.status === 'failed' ? 'error' : batchData?.status === 'partial_failure' ? 'warning' : 'success'"
            variant="tonal"
            class="mb-4"
          >
            <div class="font-weight-bold text-subtitle-2">
              {{
                batchData?.status === 'completed'
                  ? t('bp.bulk_email.success_title', 'Tous les emails ont été traités avec succès !')
                  : batchData?.status === 'partial_failure'
                    ? t('bp.bulk_email.partial_title', 'Traitement terminé avec certains échecs.')
                    : t('bp.bulk_email.failed_title', 'Le traitement a échoué.')
              }}
            </div>
            <div class="text-caption">
              {{ t('bp.bulk_email.batch_ref', 'Référence du lot :') }} <span class="font-mono">{{ batchData?.id }}</span>
            </div>
          </VAlert>

          <!-- Overall Progress Bar -->
          <div class="mb-4 bg-var-theme-background pa-3 rounded border">
            <div class="d-flex justify-space-between align-center mb-1">
              <span class="text-caption font-weight-bold text-uppercase">
                {{ isBatchFinished ? t('bp.bulk_email.done', 'Terminé') : t('bp.bulk_email.processing', 'En cours de traitement...') }}
              </span>
              <span class="text-caption font-weight-bold">{{ executionProgress }}%</span>
            </div>
            <VProgressLinear
              :model-value="executionProgress"
              color="primary"
              height="8"
              rounded
              :indeterminate="!isBatchFinished && executionProgress === 0"
            />
            <div class="d-flex gap-2 flex-wrap mt-3">
              <VChip
                size="small"
                color="success"
                variant="tonal"
                prepend-icon="tabler-check"
              >
                {{ batchData?.sent_count || 0 }} {{ t('bp.bulk_email.sent', 'Envoyés') }}
              </VChip>
              <VChip
                size="small"
                color="warning"
                variant="tonal"
                prepend-icon="tabler-alert-triangle"
              >
                {{ batchData?.skipped_count || 0 }} {{ t('bp.bulk_email.skipped', 'Ignorés') }}
              </VChip>
              <VChip
                size="small"
                color="error"
                variant="tonal"
                prepend-icon="tabler-x"
              >
                {{ batchData?.failed_count || 0 }} {{ t('bp.bulk_email.failed', 'Échoués') }}
              </VChip>
            </div>
          </div>

          <!-- Per-Record Status List -->
          <div class="text-caption font-weight-bold mb-2 text-uppercase text-medium-emphasis">
            {{ t('bp.bulk_email.details_per_record', 'Détails par enregistrement') }}
          </div>
          <PerfectScrollbar
            style="max-block-size: 280px;"
            class="border rounded overflow-hidden"
          >
            <VTable
              density="compact"
              class="text-caption"
            >
              <thead>
                <tr>
                  <th>{{ t('bp.bulk_email.partner', 'Partenaire') }}</th>
                  <th>{{ t('bp.bulk_email.recipient', 'Destinataire') }}</th>
                  <th class="text-end">
                    {{ t('bp.bulk_email.status', 'État') }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="item in batchData?.items || []"
                  :key="item.id"
                >
                  <td class="font-weight-medium">
                    <div
                      class="text-truncate"
                      style="max-inline-size: 180px;"
                    >
                      {{ item.partner_name || item.partner_code || item.partner_id }}
                    </div>
                    <div class="text-caption text-medium-emphasis font-mono">
                      {{ item.partner_code }}
                    </div>
                  </td>
                  <td>
                    <div v-if="item.resolved_email">
                      <span class="text-high-emphasis font-weight-medium">{{ item.resolved_email }}</span>
                      <span
                        v-if="item.email_source === 'contact'"
                        class="text-caption text-medium-emphasis ms-1"
                      >(Contact)</span>
                    </div>
                    <div
                      v-else
                      class="text-caption text-medium-emphasis fst-italic"
                    >
                      &mdash;
                    </div>
                  </td>
                  <td class="text-end">
                    <VChip
                      v-if="item.status === 'sent'"
                      size="x-small"
                      color="success"
                      variant="tonal"
                    >
                      {{ t('bp.bulk_email.status_sent', 'Envoyé') }}
                    </VChip>
                    <VChip
                      v-else-if="item.status === 'skipped'"
                      size="x-small"
                      color="warning"
                      variant="tonal"
                    >
                      {{ t('bp.bulk_email.status_skipped', 'Ignoré') }}
                    </VChip>
                    <VChip
                      v-else-if="item.status === 'failed'"
                      size="x-small"
                      color="error"
                      variant="tonal"
                      :title="item.error_message"
                    >
                      {{ t('bp.bulk_email.status_failed', 'Échec') }}
                    </VChip>
                    <VProgressCircular
                      v-else
                      indeterminate
                      size="16"
                      width="2"
                      color="primary"
                    />
                  </td>
                </tr>
              </tbody>
            </VTable>
          </PerfectScrollbar>
        </div>

        <!-- ── Configuration & Pre-Flight Review ────────────────────────── -->
        <div v-else>
          <!-- Notice: Individual email assurance -->
          <VAlert
            type="info"
            variant="tonal"
            density="compact"
            class="mb-4"
            icon="tabler-shield-check"
          >
            <div class="text-caption font-weight-medium">
              {{ t('bp.bulk_email.isolated_notice', 'Chaque partenaire sélectionné recevra un email individuel et personnalisé contenant uniquement ses propres informations et sa fiche PDF dédiée.') }}
            </div>
          </VAlert>

          <!-- 1. Sender & Template Row -->
          <VRow class="mb-2">
            <VCol
              cols="12"
              md="7"
            >
              <VLabel class="text-caption font-weight-bold mb-1">
                {{ t('bp.bulk_email.sender_identity', 'Identité d’expédition') }} *
              </VLabel>
              <VSelect
                v-model="selectedSenderProfileId"
                :items="previewData?.sender_profiles || []"
                item-title="name"
                item-value="id"
                density="compact"
                variant="outlined"
                prepend-inner-icon="tabler-user-circle"
                :loading="isPreviewLoading"
                :disabled="isPreviewLoading"
              >
                <template #item="{ props: itemProps, item }">
                  <VListItem
                    v-bind="itemProps"
                    :subtitle="`${item.raw.email} &bull; ${item.raw.department || 'Wave Plus'}`"
                  />
                </template>
              </VSelect>
            </VCol>

            <VCol
              cols="12"
              md="5"
            >
              <VLabel class="text-caption font-weight-bold mb-1">
                {{ t('bp.bulk_email.language', 'Langue du modèle') }}
              </VLabel>
              <div class="d-flex align-center">
                <VBtnToggle
                  v-model="selectedLanguage"
                  mandatory
                  density="compact"
                  color="primary"
                  variant="outlined"
                  divided
                  class="wp-toggle-group"
                  :disabled="isPreviewLoading"
                >
                  <VBtn
                    value="fr"
                    size="small"
                  >
                    FR
                  </VBtn>
                  <VBtn
                    value="en"
                    size="small"
                  >
                    EN
                  </VBtn>
                  <VBtn
                    value="ar"
                    size="small"
                  >
                    العربية
                  </VBtn>
                </VBtnToggle>
              </div>
            </VCol>
          </VRow>

          <!-- 2. Recipient Selection Rule -->
          <div class="mb-4">
            <VLabel class="text-caption font-weight-bold mb-1">
              {{ t('bp.bulk_email.recipient_policy', 'Règle de résolution des destinataires') }}
            </VLabel>
            <VSelect
              v-model="selectedRule"
              :items="ruleOptions"
              item-title="title"
              item-value="value"
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-address-book"
              :disabled="isPreviewLoading"
            >
              <template #item="{ props: itemProps, item }">
                <VListItem
                  v-bind="itemProps"
                  :subtitle="item.raw.desc"
                />
              </template>
            </VSelect>
          </div>

          <!-- 3. Pre-flight Summary Chips -->
          <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-3 pa-2 bg-var-theme-background rounded border">
            <div class="d-flex align-center gap-2">
              <span class="text-caption font-weight-medium">{{ t('bp.bulk_email.summary', 'Bilan :') }}</span>
              <VChip
                size="small"
                color="primary"
                variant="tonal"
              >
                {{ previewData?.total_count || partnerIds.length }} {{ t('bp.bulk_email.selected', 'sélectionnés') }}
              </VChip>
              <VChip
                size="small"
                color="success"
                variant="tonal"
                prepend-icon="tabler-check"
              >
                {{ previewData?.ready_count || 0 }} {{ t('bp.bulk_email.ready', 'prêts à envoyer') }}
              </VChip>
              <VChip
                v-if="previewData?.skipped_count > 0"
                size="small"
                color="warning"
                variant="tonal"
                prepend-icon="tabler-alert-triangle"
              >
                {{ previewData?.skipped_count }} {{ t('bp.bulk_email.will_skip', 'seront ignorés') }}
              </VChip>
            </div>

            <!-- PDF Switch -->
            <VSwitch
              v-model="includePdf"
              color="primary"
              density="compact"
              hide-details
              :label="t('bp.bulk_email.attach_pdf', 'Fiche PDF individuelle')"
            />
          </div>

          <!-- 4. Review Table of Selected Partners -->
          <div class="mb-3">
            <div class="d-flex align-center justify-space-between mb-1">
              <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                {{ t('bp.bulk_email.recipients_review', 'Vérification des destinataires') }}
              </span>
              <VProgressCircular
                v-if="isPreviewLoading"
                indeterminate
                size="16"
                width="2"
                color="primary"
              />
            </div>

            <PerfectScrollbar
              style="max-block-size: 220px;"
              class="border rounded overflow-hidden"
            >
              <VTable
                density="compact"
                class="text-caption"
              >
                <thead>
                  <tr>
                    <th>{{ t('bp.bulk_email.code_partner', 'Code & Partenaire') }}</th>
                    <th>{{ t('bp.bulk_email.resolved_recipient', 'Destinataire résolu') }}</th>
                    <th class="text-end">
                      {{ t('bp.bulk_email.status', 'Statut') }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="item in previewData?.items || []"
                    :key="item.partner_id"
                  >
                    <td>
                      <div
                        class="font-weight-medium text-truncate"
                        style="max-inline-size: 180px;"
                      >
                        {{ item.partner_name }}
                      </div>
                      <div class="text-caption text-medium-emphasis font-mono">
                        {{ item.partner_code }}
                      </div>
                    </td>
                    <td>
                      <div v-if="item.recipient_email">
                        <span class="font-weight-medium">{{ item.recipient_email }}</span>
                        <div
                          v-if="item.recipient_name"
                          class="text-caption text-medium-emphasis"
                        >
                          {{ item.recipient_name }}
                        </div>
                      </div>
                      <div
                        v-else
                        class="text-warning text-caption font-weight-medium"
                      >
                        {{ item.warning || t('bp.bulk_email.missing_email', 'Email manquant') }}
                      </div>
                    </td>
                    <td class="text-end">
                      <VChip
                        v-if="item.status === 'ready'"
                        size="x-small"
                        color="success"
                        variant="tonal"
                      >
                        {{ t('bp.bulk_email.ready_badge', 'Prêt') }}
                      </VChip>
                      <VChip
                        v-else
                        size="x-small"
                        color="warning"
                        variant="tonal"
                        :title="item.warning"
                      >
                        {{ t('bp.bulk_email.skipped_badge', 'Ignoré') }}
                      </VChip>
                    </td>
                  </tr>
                  <tr v-if="!isPreviewLoading && (!previewData || previewData.items.length === 0)">
                    <td
                      colspan="3"
                      class="text-center py-4 text-medium-emphasis"
                    >
                      {{ t('bp.bulk_email.no_records', 'Aucun enregistrement à prévisualiser.') }}
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </PerfectScrollbar>
          </div>

          <!-- 5. Sample Preview Toggle (Expander) -->
          <div
            v-if="previewData?.sample_preview"
            class="border rounded pa-3 bg-var-theme-background"
          >
            <div
              class="d-flex align-center justify-space-between cursor-pointer"
              @click="isSamplePreviewOpen = !isSamplePreviewOpen"
            >
              <div class="d-flex align-center gap-2">
                <VIcon
                  icon="tabler-eye"
                  size="18"
                  color="primary"
                />
                <span class="text-caption font-weight-bold">
                  {{ t('bp.bulk_email.sample_preview_title', 'Aperçu d’un email type :') }}
                  <span class="text-primary">{{ previewData.sample_preview.partner_name }}</span>
                </span>
              </div>
              <VIcon :icon="isSamplePreviewOpen ? 'tabler-chevron-up' : 'tabler-chevron-down'" />
            </div>

            <VExpandTransition>
              <div
                v-if="isSamplePreviewOpen"
                class="mt-3 pt-2 border-t"
              >
                <div class="text-caption font-weight-bold mb-1">
                  {{ t('bp.bulk_email.subject', 'Objet :') }} <span class="font-weight-regular">{{ previewData.sample_preview.subject }}</span>
                </div>
                <PerfectScrollbar
                  style="max-block-size: 150px;"
                  class="bg-surface rounded border pa-2 text-caption mt-1"
                >
                  <!-- eslint-disable-next-line vue/no-v-html -->
                  <div v-html="previewData.sample_preview.body_html" />
                </PerfectScrollbar>
              </div>
            </VExpandTransition>
          </div>
        </div>
      </VCardText>

      <!-- ── Dialog Footer ────────────────────────────────────────────────── -->
      <VCardActions class="border-t pa-3 d-flex justify-space-between">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="isSubmitting || (isExecuting && !isBatchFinished)"
          @click="closeDialog"
        >
          {{ isBatchFinished ? t('bp.bulk_email.close', 'Fermer') : t('bp.bulk_email.cancel', 'Annuler') }}
        </VBtn>

        <VBtn
          v-if="!isExecuting"
          color="primary"
          variant="elevated"
          prepend-icon="tabler-send"
          :loading="isSubmitting"
          :disabled="isPreviewLoading || !previewData || previewData.ready_count === 0"
          @click="startBulkSend"
        >
          {{ t('bp.bulk_email.send_btn', { count: previewData?.ready_count || 0 }, `Envoyer ${previewData?.ready_count || 0} emails`) }}
        </VBtn>

        <VBtn
          v-else-if="isBatchFinished"
          color="primary"
          variant="elevated"
          prepend-icon="tabler-check"
          @click="closeDialog"
        >
          {{ t('bp.bulk_email.done', 'Terminé') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.bp-bulk-email-dialog {
  border-radius: 12px;
}
.font-mono {
  font-family: monospace;
}
</style>
