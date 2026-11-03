<script setup>
/* eslint-disable vue/no-v-html */
import TiptapEditor from '@core/components/TiptapEditor.vue'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { $api } from '@/utils/api'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  partnerType: {
    type: String,
    required: true,
  },
  partnerId: {
    type: [String, Number],
    default: null,
  },
  partner: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['update:modelValue', 'sent'])

const { t } = useI18n()
const { smAndDown } = useDisplay()
const { success: notifySuccess, error: notifyError, showApiError } = useAppSnackbar()

// ── State ────────────────────────────────────────────────────────────────────
const isLoading = ref(false)
const isSending = ref(false)
const loadError = ref(null)

const activeTab = ref('editor') // 'editor' | 'preview'
const previewViewport = ref('desktop') // 'desktop' | 'mobile'

const senderProfiles = ref([])
const senderProfileId = ref(null)

const recipientsList = ref([])
const selectedRecipientKey = ref(null)

const showCc = ref(false)
const showBcc = ref(false)
const ccInput = ref('')
const bccInput = ref('')
const ccList = ref([])
const bccList = ref([])

const subject = ref('')
const bodyHtml = ref('')
const templateMeta = ref(null)

const includePdf = ref(true)
const pdfFilename = ref('')

// ── Computed ─────────────────────────────────────────────────────────────────
const normalizedRouteType = computed(() => {
  const p = (props.partnerType || '').toLowerCase()
  if (p.includes('client')) return 'clients'
  if (p.includes('supplier')) return 'suppliers'
  if (p.includes('service')) return 'service-providers'
  if (p.includes('organis') || p.includes('organiz')) return 'organisations'

  return `${p}s`
})

const activeSenderProfile = computed(() => {
  return senderProfiles.value.find(p => p.id === senderProfileId.value) || senderProfiles.value[0] || null
})

const selectedRecipientObject = computed(() => {
  return recipientsList.value.find(r => r.id === selectedRecipientKey.value) || null
})

// ── Load Contextual Email Data ───────────────────────────────────────────────
async function fetchContextualData() {
  if (!props.partnerId) return

  isLoading.value = true
  loadError.value = null

  try {
    const res = await $api(`/bp/${normalizedRouteType.value}/${props.partnerId}/contextual-email`)
    const data = res.data || {}

    senderProfiles.value = data.sender_profiles || []
    senderProfileId.value = data.default_sender_profile_id || senderProfiles.value[0]?.id || null

    recipientsList.value = data.recipients_list || []
    if (data.selected_recipient) {
      selectedRecipientKey.value = data.selected_recipient.id
    } else if (recipientsList.value[0]) {
      selectedRecipientKey.value = recipientsList.value[0].id
    }

    templateMeta.value = data.template || null
    subject.value = data.subject || ''
    bodyHtml.value = data.body_html || ''

    if (data.pdf_attachment) {
      pdfFilename.value = data.pdf_attachment.filename || 'partner-record.pdf'
      includePdf.value = true
    }

    ccList.value = []
    bccList.value = []
    showCc.value = false
    showBcc.value = false
    activeTab.value = 'editor'
  } catch (err) {
    console.error('Failed to load contextual email data:', err)
    loadError.value = err.data?.message || err.message || t('communications.email.load_error', 'Impossible de charger les données du courrier.')
    showApiError(err, loadError.value)
  } finally {
    isLoading.value = false
  }
}

watch(() => props.modelValue, open => {
  if (open) {
    fetchContextualData()
  }
})

// ── Tag Helpers (CC & BCC) ───────────────────────────────────────────────────
function addCcTag() {
  const val = ccInput.value.trim()
  if (val && !ccList.value.includes(val)) {
    ccList.value.push(val)
  }
  ccInput.value = ''
}

function removeCcTag(index) {
  ccList.value.splice(index, 1)
}

function addBccTag() {
  const val = bccInput.value.trim()
  if (val && !bccList.value.includes(val)) {
    bccList.value.push(val)
  }
  bccInput.value = ''
}

function removeBccTag(index) {
  bccList.value.splice(index, 1)
}

// ── Submit / Send ────────────────────────────────────────────────────────────
async function handleSend() {
  if (isSending.value) return

  const toRec = selectedRecipientObject.value
  if (!toRec || !toRec.email) {
    notifyError(t('communications.email.missing_to', 'Veuillez sélectionner une adresse destinataire valide.'))

    return
  }

  if (!subject.value.trim()) {
    notifyError(t('communications.email.missing_subject', 'Veuillez renseigner un objet pour ce message.'))

    return
  }

  if (!bodyHtml.value.trim()) {
    notifyError(t('communications.email.missing_body', 'Le corps du message ne peut pas être vide.'))

    return
  }

  // Pre-flight check: validate unresolved template placeholders
  const unresolvedInSubject = (subject.value || '').match(/\{\{\s*[\w.\-]+\s*\}\}/g)
  const unresolvedInBody = (bodyHtml.value || '').match(/\{\{\s*[\w.\-]+\s*\}\}/g)
  if (unresolvedInSubject || unresolvedInBody) {
    const tokens = [...(unresolvedInSubject || []), ...(unresolvedInBody || [])].slice(0, 3).join(', ')

    notifyError(t('communications.email.unresolved_variables', `Veuillez compléter ou supprimer les variables : ${tokens}`))

    return
  }

  isSending.value = true

  try {
    /* eslint-disable camelcase */
    const payload = {
      sender_profile_id: senderProfileId.value,
      to: [
        {
          email: toRec.email,
          name: toRec.name || toRec.contact_name,
          recipient_type: toRec.recipient_type,
          recipient_id: toRec.recipient_id,
          contact_id: toRec.contact_id,
          email_source: toRec.email_source,
        },
      ],
      cc: ccList.value.map(email => ({ email })),
      bcc: bccList.value.map(email => ({ email })),
      subject: subject.value,
      body_html: bodyHtml.value,
      include_pdf: includePdf.value,
    }
    /* eslint-enable camelcase */

    const res = await $api(`/bp/${normalizedRouteType.value}/${props.partnerId}/send-email`, {
      method: 'POST',
      body: payload,
    })

    notifySuccess(t('communications.email.quick_send_success', 'Email contextualisé envoyé avec succès.'))
    emit('sent', res.data)
    emit('update:modelValue', false)
  } catch (err) {
    console.error('Failed to send contextual email:', err)
    showApiError(err, t('communications.email.send_failed', 'Échec de l\'envoi de l\'email.'))
  } finally {
    isSending.value = false
  }
}

function handleClose() {
  emit('update:modelValue', false)
}
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="820"
    :fullscreen="smAndDown"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="bp-contextual-email-dialog d-flex flex-column">
      <!-- ── Dialog Header ─────────────────────────────────────────────── -->
      <VCardItem class="border-b py-3 px-4">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            size="36"
            class="me-2"
          >
            <VIcon
              icon="tabler-mail-fast"
              size="20"
            />
          </VAvatar>
        </template>

        <VCardTitle class="text-h6 font-weight-bold d-flex align-center flex-wrap gap-2">
          <span>{{ t('communications.email.contextual_send_title', 'Envoyer un Email') }}</span>
          <VChip
            v-if="props.partner?.legal_name || props.partner?.name"
            size="small"
            color="primary"
            variant="tonal"
            class="font-weight-medium"
          >
            {{ props.partner.legal_name || props.partner.name }}
          </VChip>
        </VCardTitle>

        <VCardSubtitle class="text-caption text-medium-emphasis">
          {{ t('communications.email.contextual_send_subtitle', 'Envoi direct avec fiche PDF jointe et variables résolues.') }}
        </VCardSubtitle>

        <template #append>
          <VBtn
            icon
            variant="text"
            size="small"
            @click="handleClose"
          >
            <VIcon
              icon="tabler-x"
              size="20"
            />
          </VBtn>
        </template>
      </VCardItem>

      <!-- ── Loading / Error State ─────────────────────────────────────── -->
      <VCardText
        v-if="isLoading"
        class="py-12 text-center"
      >
        <VProgressCircular
          indeterminate
          color="primary"
          size="44"
          class="mb-3"
        />
        <div class="text-body-2 text-medium-emphasis">
          {{ t('communications.email.preparing_contextual_email', 'Préparation du message et de la fiche partenaire...') }}
        </div>
      </VCardText>

      <VCardText
        v-else-if="loadError"
        class="py-8"
      >
        <VAlert
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ loadError }}
        </VAlert>
        <div class="text-center">
          <VBtn
            color="primary"
            variant="tonal"
            prepend-icon="tabler-refresh"
            @click="fetchContextualData"
          >
            {{ t('common.retry', 'Réessayer') }}
          </VBtn>
        </div>
      </VCardText>

      <!-- ── Main Single-Screen Form ───────────────────────────────────── -->
      <VCardText
        v-else
        class="pa-4 flex-grow-1 overflow-y-auto"
        style="max-block-size: calc(90vh - 140px);"
      >
        <!-- Row 1: Sender & Recipient -->
        <VRow dense>
          <!-- Sender Identity -->
          <VCol
            cols="12"
            sm="6"
          >
            <div class="text-caption font-weight-bold text-medium-emphasis mb-1">
              {{ t('communications.email.sender_field', 'Expéditeur autorisé') }}
            </div>
            <VSelect
              v-model="senderProfileId"
              :items="senderProfiles"
              item-title="name"
              item-value="id"
              density="compact"
              variant="outlined"
              hide-details
            >
              <template #selection="{ item }">
                <div class="d-flex align-center gap-1 text-truncate">
                  <VIcon
                    icon="tabler-badge-check"
                    size="15"
                    color="primary"
                  />
                  <span class="font-weight-medium text-truncate">{{ item.raw.name }}</span>
                  <span class="text-caption text-medium-emphasis">&lt;{{ item.raw.email }}&gt;</span>
                </div>
              </template>
              <template #item="{ props: itemProps, item }">
                <VListItem
                  v-bind="itemProps"
                  :subtitle="item.raw.email"
                >
                  <template #prepend>
                    <VIcon
                      icon="tabler-mail"
                      size="18"
                      class="me-2"
                    />
                  </template>
                </VListItem>
              </template>
            </VSelect>
          </VCol>

          <!-- Recipient Selector -->
          <VCol
            cols="12"
            sm="6"
          >
            <div class="d-flex align-center justify-space-between mb-1">
              <span class="text-caption font-weight-bold text-medium-emphasis">
                {{ t('communications.email.to_field', 'Destinataire (Partenaire / Contact)') }} *
              </span>
              <div class="d-flex gap-1">
                <VBtn
                  variant="text"
                  size="x-small"
                  color="primary"
                  class="px-1 text-none"
                  @click="showCc = !showCc"
                >
                  Cc
                </VBtn>
                <VBtn
                  variant="text"
                  size="x-small"
                  color="primary"
                  class="px-1 text-none"
                  @click="showBcc = !showBcc"
                >
                  Cci
                </VBtn>
              </div>
            </div>

            <VSelect
              v-model="selectedRecipientKey"
              :items="recipientsList"
              item-title="label"
              item-value="id"
              density="compact"
              variant="outlined"
              hide-details
            >
              <template #selection="{ item }">
                <div class="d-flex align-center gap-1 text-truncate">
                  <VIcon
                    :icon="item.raw.email_source === 'contact' ? 'tabler-user' : (item.raw.email_source === 'address' ? 'tabler-map-pin' : 'tabler-building')"
                    size="15"
                    color="primary"
                  />
                  <span class="font-weight-medium text-truncate">{{ item.raw.contact_name || item.raw.name }}</span>
                  <span class="text-caption text-medium-emphasis">&lt;{{ item.raw.email }}&gt;</span>
                </div>
              </template>
              <template #item="{ props: itemProps, item }">
                <VListItem
                  v-bind="itemProps"
                  :subtitle="item.raw.email"
                >
                  <template #prepend>
                    <VIcon
                      :icon="item.raw.email_source === 'contact' ? 'tabler-user' : (item.raw.email_source === 'address' ? 'tabler-map-pin' : 'tabler-building')"
                      size="18"
                      class="me-2"
                    />
                  </template>
                </VListItem>
              </template>
            </VSelect>
          </VCol>

          <!-- Optional CC Field -->
          <VCol
            v-if="showCc"
            cols="12"
            class="pt-2"
          >
            <div class="text-caption font-weight-bold text-medium-emphasis mb-1">
              {{ t('communications.email.cc_field', 'Copie conforme (CC)') }}
            </div>
            <div class="d-flex flex-wrap gap-1 mb-1">
              <VChip
                v-for="(email, idx) in ccList"
                :key="`cc-${idx}`"
                size="small"
                closable
                @click:close="removeCcTag(idx)"
              >
                {{ email }}
              </VChip>
            </div>
            <VTextField
              v-model="ccInput"
              density="compact"
              variant="outlined"
              placeholder="Ajouter une adresse email et appuyer sur Entrée..."
              hide-details
              @keydown.enter.prevent="addCcTag"
            />
          </VCol>

          <!-- Optional BCC Field -->
          <VCol
            v-if="showBcc"
            cols="12"
            class="pt-2"
          >
            <div class="text-caption font-weight-bold text-medium-emphasis mb-1">
              {{ t('communications.email.bcc_field', 'Copie cachée (CCI)') }}
            </div>
            <div class="d-flex flex-wrap gap-1 mb-1">
              <VChip
                v-for="(email, idx) in bccList"
                :key="`bcc-${idx}`"
                size="small"
                closable
                @click:close="removeBccTag(idx)"
              >
                {{ email }}
              </VChip>
            </div>
            <VTextField
              v-model="bccInput"
              density="compact"
              variant="outlined"
              placeholder="Ajouter une adresse email et appuyer sur Entrée..."
              hide-details
              @keydown.enter.prevent="addBccTag"
            />
          </VCol>

          <!-- Subject Input -->
          <VCol
            cols="12"
            class="pt-2"
          >
            <div class="text-caption font-weight-bold text-medium-emphasis mb-1">
              {{ t('communications.email.subject_field', 'Objet') }} *
            </div>
            <VTextField
              v-model="subject"
              density="compact"
              variant="outlined"
              hide-details
              placeholder="Objet du message..."
            />
          </VCol>

          <!-- Generated PDF Snapshot Attachment Strip -->
          <VCol
            cols="12"
            class="pt-2"
          >
            <VCard
              variant="tonal"
              color="primary"
              class="pa-2 px-3 d-flex align-center justify-space-between flex-wrap gap-2"
            >
              <div class="d-flex align-center gap-2">
                <VIcon
                  icon="tabler-file-type-pdf"
                  size="24"
                  color="error"
                />
                <div>
                  <div class="text-body-2 font-weight-bold">
                    {{ pdfFilename }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ t('communications.email.pdf_attachment_notice', 'Fiche récapitulative du partenaire générée automatiquement (instantané privé)') }}
                  </div>
                </div>
              </div>
              <VCheckbox
                v-model="includePdf"
                label="Joindre la fiche PDF"
                density="compact"
                hide-details
                color="primary"
              />
            </VCard>
          </VCol>
        </VRow>

        <!-- ── Mode Tabs: Editor vs Live Preview ─────────────────────── -->
        <div class="d-flex align-center justify-space-between mt-3 mb-2 border-b pb-1">
          <VBtnToggle
            v-model="activeTab"
            mandatory
            density="compact"
            color="primary"
            variant="outlined"
          >
            <VBtn
              value="editor"
              size="small"
            >
              <VIcon
                icon="tabler-edit"
                size="15"
                start
              />
              {{ t('communications.email.tab_edit', 'Éditeur de message') }}
            </VBtn>
            <VBtn
              value="preview"
              size="small"
            >
              <VIcon
                icon="tabler-eye"
                size="15"
                start
              />
              {{ t('communications.email.tab_preview', 'Aperçu du rendu') }}
            </VBtn>
          </VBtnToggle>

          <!-- Viewport Toggle for Preview -->
          <VBtnToggle
            v-if="activeTab === 'preview'"
            v-model="previewViewport"
            mandatory
            density="compact"
            variant="tonal"
          >
            <VBtn
              value="desktop"
              size="x-small"
              icon="tabler-device-desktop"
            />
            <VBtn
              value="mobile"
              size="x-small"
              icon="tabler-device-mobile"
            />
          </VBtnToggle>
        </div>

        <!-- Tab 1: Tiptap Rich-Text Editor -->
        <div
          v-if="activeTab === 'editor'"
          class="message-editor-wrapper"
        >
          <TiptapEditor
            v-model="bodyHtml"
            class="border rounded"
            style="min-block-size: 260px;"
          />
        </div>

        <!-- Tab 2: Live Rendered Preview -->
        <div
          v-else
          class="live-preview-wrapper d-flex justify-center bg-grey-50 dark:bg-grey-900 pa-4 rounded border"
        >
          <div
            :style="{
              width: previewViewport === 'mobile' ? '380px' : '100%',
              maxWidth: '680px',
              transition: 'all 0.25s ease',
            }"
            class="bg-surface rounded border shadow-sm pa-4"
          >
            <div class="border-b pb-2 mb-3 text-caption text-medium-emphasis">
              <div><strong>De :</strong> {{ activeSenderProfile?.name }} &lt;{{ activeSenderProfile?.email }}&gt;</div>
              <div><strong>À :</strong> {{ selectedRecipientObject?.email }}</div>
              <div><strong>Objet :</strong> {{ subject }}</div>
            </div>

            <PerfectScrollbar
              :options="{ wheelPropagation: false, suppressScrollX: true }"
              style="max-block-size: 320px; position: relative;"
            >
              <!-- eslint-disable-next-line vue/no-v-html -->
              <div
                class="email-body-rendered text-body-1"
                v-html="bodyHtml"
              />
            </PerfectScrollbar>
          </div>
        </div>
      </VCardText>

      <!-- ── Dialog Footer Actions ─────────────────────────────────────── -->
      <VCardActions class="border-t py-3 px-4 d-flex justify-space-between align-center">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="isSending"
          @click="handleClose"
        >
          {{ t('common.cancel', 'Annuler') }}
        </VBtn>

        <VBtn
          color="primary"
          variant="elevated"
          prepend-icon="tabler-send"
          :loading="isSending"
          :disabled="isLoading || !!loadError"
          @click="handleSend"
        >
          {{ t('communications.email.send_action', 'Envoyer') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.bp-contextual-email-dialog {
  max-block-size: 95vh;
}

:deep(.tiptap) {
  min-block-size: 220px;
  outline: none;
  padding: 12px;
}
</style>
