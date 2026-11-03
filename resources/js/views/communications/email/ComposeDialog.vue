<script setup>
/* eslint-disable camelcase, regexp/no-super-linear-backtracking, vue/custom-event-name-casing, vue/no-v-html */
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import WpWizardDialog from '@/components/shared/wizard/WpWizardDialog.vue'
import WpAttachmentDialog from '@/components/shared/documents/WpAttachmentDialog.vue'
import WpSearchSelectDialog from '@/components/shared/search/WpSearchSelectDialog.vue'
import WpMediaViewerDialog from '@/components/shared/media/WpMediaViewerDialog.vue'
import SenderIdentityCard from './components/SenderIdentityCard.vue'
import RecipientDirectoryWizardDialog from './components/RecipientDirectoryWizardDialog.vue'
import TemplateCatalogStep from './components/TemplateCatalogStep.vue'
import MessageEditorStep from './components/MessageEditorStep.vue'
import AttachmentStep from './components/AttachmentStep.vue'
import {
  appendSignatureToHtml,
  detectSignatureType,
  htmlToPlainText,
  stripExistingSignature,
} from './utils/signatureUtils'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useAppSnackbar } from '@/composables/useAppSnackbar'
import { $api } from '@/utils/api'
import {
  formatDocumentBytes,
  resolveFileColor,
  resolveFileIcon,
} from '@/composables/documents/documentFiles'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  draftId: { type: [Number, String], default: null },
  initialTo: { type: Array, default: () => [] },
  initialSubject: { type: String, default: '' },
  initialBody: { type: String, default: '' },
  initialRecordType: { type: String, default: null },
  initialRecordId: { type: [Number, String], default: null },
  initialReference: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue', 'close', 'sent', 'draft-saved'])

const { t } = useI18n()
const { smAndDown } = useDisplay()
const { success: notifySuccess, error: notifyError } = useAppSnackbar()

function sanitizeDraftId(id) {
  if (typeof id === 'string') {
    const trimmed = id.trim()
    if (trimmed === '' || trimmed === '[object Object]' || trimmed.includes('PointerEvent') || trimmed.includes('MouseEvent') || trimmed.includes('[object')) {
      return null
    }

    return trimmed
  }
  if (typeof id === 'number' && !Number.isNaN(id)) {
    return id
  }

  return null
}

// ── State ───────────────────────────────────────────────────────────────────
const wizardRef = ref(null)
const isSubmitting = ref(false)
const isSavingDraft = ref(false)
const currentDraftId = ref(sanitizeDraftId(props.draftId))
const activeMessageTab = ref('compose') // 'compose' | 'preview'

// Form state
const form = reactive({
  sender_profile_id: null,
  to: [],
  cc: [],
  bcc: [],
  template_id: null,
  language: 'fr',
  signature_type: 'personal',
  subject: '',
  body_html: '',
  body_text: '',
})

// Attachments state
// Item: { id, source, filename, size_bytes, formatted_size, mime_type, file, document_id, source_type, related_entity, preview_url, is_existing_attachment, backend_attachment_id }
const attachments = ref([])

// Business record associations: array of { type: string, id: string, reference?: string, label?: string }
const associations = ref([])

// Dropdowns / remote data
const senderProfiles = ref([])
const templates = ref([])
const signatures = ref({})
const isProfilesLoading = ref(false)
const isTemplatesLoading = ref(false)
const isSignaturesLoading = ref(false)

// Step 1: Recipients Directory Wizard & Manual input
const isRecipientWizardVisible = ref(false)
const isManualEntryOpen = ref(false)
const showCc = ref(false)
const showBcc = ref(false)

const manualEmail = ref('')
const manualName = ref('')
const manualTarget = ref('to')

// Step 2 & 3: Primary Business Context & Template State
// Context is passed internally from initiating record props without exposing a selector UI.
const primaryContext = reactive({
  type: props.initialRecordType || 'none',
  record_id: props.initialRecordId || null,
  reference: props.initialReference || null,
  label: props.initialReference ? `${props.initialRecordType}: ${props.initialReference}` : null,
})

const hasRecordContext = computed(() => {
  return Boolean(primaryContext.type && primaryContext.record_id && primaryContext.type !== 'none')
})

const recordContextLabel = computed(() => {
  if (!hasRecordContext.value) return null

  return primaryContext.label || primaryContext.reference || `${primaryContext.type} #${primaryContext.record_id}`
})

// Live preview state & resolved variables
const resolvedVariablesMap = ref({})
const isRenderingTemplate = ref(false)
const renderError = ref(null)
const missingVariables = ref([])
const hasMissingRecordContext = ref(false)
const previewDeviceMode = ref('desktop') // 'desktop' | 'mobile'

// Step 4: Acquisition modals & inputs
const isAttachmentDialogOpen = ref(false)
const isDocumentPickerOpen = ref(false)
const isMediaViewerOpen = ref(false)
const mediaViewerItem = ref(null)

// ── Constants & Limits ──────────────────────────────────────────────────────
const MAX_ATTACHMENTS = 10
const MAX_FILE_BYTES = 10 * 1024 * 1024 // 10 MB per file
const MAX_TOTAL_BYTES = 20 * 1024 * 1024 // 20 MB total
const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'png', 'jpg', 'jpeg', 'webp', 'zip', 'txt']
const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

const allCurrentEmails = computed(() => {
  return [...form.to, ...form.cc, ...form.bcc].map(r => r.email?.toLowerCase().trim()).filter(Boolean)
})

function addAssociation(type, id, reference = '', label = null) {
  if (!type || !id || type === 'none') return
  const idStr = String(id)
  if (!associations.value.some(a => a.type === type && String(a.id) === idStr)) {
    associations.value.push({
      type,
      id: idStr,
      reference: reference || `${type} #${idStr}`,
      label: label || `${type}: ${reference || idStr}`,
    })
  }
}

function removeAssociation(index) {
  associations.value.splice(index, 1)
}

// ── Computed Properties ─────────────────────────────────────────────────────
const totalAttachmentBytes = computed(() => {
  return attachments.value.reduce((acc, a) => acc + (a.size_bytes || 0), 0)
})

const totalAttachmentMb = computed(() => {
  return (totalAttachmentBytes.value / (1024 * 1024)).toFixed(2)
})

const isOverCountLimit = computed(() => attachments.value.length > MAX_ATTACHMENTS)
const isOverSizeLimit = computed(() => totalAttachmentBytes.value > MAX_TOTAL_BYTES)

const selectedSender = computed(() => {
  return senderProfiles.value.find(p => p.id === form.sender_profile_id) || null
})

const selectedSignatureHtml = computed(() => {
  if (form.signature_type === 'none') return ''
  const sig = signatures.value?.[form.signature_type]

  return sig?.html || ''
})

const selectedTemplateItem = computed(() => {
  if (!form.template_id) return null

  return templates.value.find(t => t.id === form.template_id) || null
})

// Rendered message preview with clean signature appending (backend resolves template variables)
const renderedPreviewSubject = computed(() => form.subject || '')

const renderedPreviewHtml = computed(() => {
  let content = stripExistingSignature(form.body_html || '')
  if (form.signature_type !== 'none') {
    const sigHtml = signatures.value?.[form.signature_type]?.html || ''
    if (sigHtml) {
      content = appendSignatureToHtml(content, sigHtml)
    }
  }

  return content
})

// Automatically computed contact associations from selected recipients
const linkedContacts = computed(() => {
  const result = []
  const seenKeys = new Set()
  const allRecipients = [...form.to, ...form.cc, ...form.bcc]

  for (const rec of allRecipients) {
    const rType = rec.recipient_type ? String(rec.recipient_type).toLowerCase() : null
    if (!rType || rType === 'other' || rType === 'manual' || rType === 'custom') {
      continue
    }

    // 1. Employee
    if (rType === 'employee' && rec.recipient_id) {
      const key = `employee-${rec.recipient_id}`
      if (!seenKeys.has(key)) {
        seenKeys.add(key)
        result.push({
          key,
          type: 'employee',
          id: String(rec.recipient_id),
          title: rec.name || rec.email,
          subtitle: t('communications.email.source_employee', 'Collaborateur'),
          icon: 'tabler-user-check',
          color: 'success',
        })
      }
      continue
    }

    // 2. Business Partner Contact
    if (rec.recipient_id && rec.contact_id && rec.email_source === 'contact') {
      const contactKey = `bp-contact-${rec.contact_id}`
      if (!seenKeys.has(contactKey)) {
        seenKeys.add(contactKey)

        const bpName = rec.entity_name || (rec.name?.includes('(') ? rec.name.split('(')[1].replace(')', '').trim() : '')
        const contactName = rec.contact_name || rec.name?.split('(')[0].trim() || `Contact #${rec.contact_id}`
        const title = bpName && bpName !== contactName ? `${bpName} — ${contactName}` : contactName

        result.push({
          key: contactKey,
          type: 'bp_contact',
          id: String(rec.contact_id),
          bp_type: rType,
          bp_id: String(rec.recipient_id),
          title,
          subtitle: t('communications.email.source_contact', 'Contact Partenaire'),
          icon: 'tabler-user',
          color: 'primary',
        })
      }

      // Also ensure owning BP is associated
      const bpKey = `bp-${rType}-${rec.recipient_id}`
      if (!seenKeys.has(bpKey)) {
        seenKeys.add(bpKey)
        result.push({
          key: bpKey,
          type: rType,
          id: String(rec.recipient_id),
          title: rec.entity_name || `BP #${rec.recipient_id}`,
          subtitle: t('communications.email.source_general', 'Partenaire'),
          icon: 'tabler-building',
          color: 'info',
        })
      }
      continue
    }

    // 3. Business Partner General or Address Email
    if (rec.recipient_id) {
      const bpKey = `bp-${rType}-${rec.recipient_id}`
      if (!seenKeys.has(bpKey)) {
        seenKeys.add(bpKey)

        const bpTitle = rec.entity_name || rec.name || `BP #${rec.recipient_id}`

        result.push({
          key: bpKey,
          type: rType,
          id: String(rec.recipient_id),
          title: bpTitle,
          subtitle: rec.email_source === 'address'
            ? t('communications.email.source_address', 'Adresse / Filiale')
            : t('communications.email.source_general', 'Partenaire'),
          icon: 'tabler-building',
          color: 'info',
        })
      }
    }
  }

  return result
})

// Clean deduplicated summary for UI display (matching: ABC Company — Mohamed Ben Ali / XYZ Supplies / Ahmed Employee)
const displayLinkedContacts = computed(() => {
  const list = []
  const seenBpIds = new Set()
  const allRecipients = [...form.to, ...form.cc, ...form.bcc]

  for (const rec of allRecipients) {
    const rType = rec.recipient_type ? String(rec.recipient_type).toLowerCase() : null
    if (!rType || rType === 'other' || rType === 'manual' || rType === 'custom') {
      continue
    }

    if (rType === 'employee' && rec.recipient_id) {
      const key = `emp-${rec.recipient_id}`
      if (!list.some(item => item.key === key)) {
        list.push({
          key,
          title: rec.name || rec.email,
          badge: t('communications.email.source_employee', 'Collaborateur'),
          icon: 'tabler-user-check',
          color: 'success',
        })
      }
      continue
    }

    if (rec.recipient_id) {
      if (rec.contact_id && rec.email_source === 'contact') {
        const contactKey = `contact-${rec.contact_id}`
        if (!list.some(item => item.key === contactKey)) {
          const bpName = rec.entity_name || (rec.name?.includes('(') ? rec.name.split('(')[1].replace(')', '').trim() : '')
          const contactName = rec.contact_name || rec.name?.split('(')[0].trim() || rec.name
          const title = bpName && bpName !== contactName ? `${bpName} — ${contactName}` : contactName

          list.push({
            key: contactKey,
            title,
            badge: t('communications.email.source_contact', 'Contact Partenaire'),
            icon: 'tabler-user',
            color: 'primary',
          })
          seenBpIds.add(String(rec.recipient_id))
        }
      } else {
        const bpKey = `bp-${rec.recipient_id}`
        if (!seenBpIds.has(String(rec.recipient_id)) && !list.some(item => item.key === bpKey)) {
          const bpName = rec.entity_name || (rec.name?.includes('(') ? rec.name.split('(')[0].trim() : rec.name)

          list.push({
            key: bpKey,
            title: bpName,
            badge: rec.email_source === 'address'
              ? t('communications.email.source_address', 'Adresse / Filiale')
              : t('communications.email.source_general', 'Partenaire'),
            icon: 'tabler-building',
            color: 'info',
          })
          seenBpIds.add(String(rec.recipient_id))
        }
      }
    }
  }

  return list
})

// Wizard Steps Definition
const wizardSteps = computed(() => [
  {
    id: 'sender_recipients',
    title: t('communications.email.step_sender_recipients', 'Sender & Recipients'),
    subtitle: t('communications.email.step_sender_recipients_sub', 'Choose From identity and select recipients'),
    icon: 'tabler-user-check',
    validate: () => {
      if (!form.sender_profile_id) {
        return { sender_profile_id: t('communications.email.validation_sender_required', 'Please select a sender profile.') }
      }
      if (!form.to || form.to.length === 0) {
        return { to: t('communications.email.validation_to_required', 'At least one recipient is required in To.') }
      }

      return true
    },
  },
  {
    id: 'template_selection',
    title: t('communications.email.step_template_selection', 'Template Selection'),
    subtitle: t('communications.email.step_template_selection_sub', 'Choose a predefined template or write blank'),
    icon: 'tabler-template',
    validate: () => true,
  },
  {
    id: 'message_content',
    title: t('communications.email.step_message_content', 'Message & Content'),
    subtitle: t('communications.email.step_message_content_sub', 'Compose subject, message and signature'),
    icon: 'tabler-edit',
    validate: () => {
      if (!form.subject?.trim()) {
        return { subject: t('communications.email.validation_subject_required', 'Subject cannot be empty.') }
      }
      if (!form.body_html?.trim() || form.body_html === '<p></p>') {
        return { body_html: t('communications.email.validation_body_required', 'Message body cannot be empty.') }
      }

      return true
    },
  },
  {
    id: 'attachments',
    title: t('communications.email.step_attachments', 'Attachments'),
    subtitle: t('communications.email.step_attachments_sub', 'Upload, scan or attach documents'),
    icon: 'tabler-paperclip',
    optional: true,
    validate: () => {
      if (isOverCountLimit.value) {
        return t('communications.email.validation_max_attachments', 'Maximum 10 attachments allowed.')
      }
      if (isOverSizeLimit.value) {
        return t('communications.email.validation_max_size', 'Total attachments size exceeds 20 MB.')
      }

      return true
    },
  },
])

// ── Data Fetching ───────────────────────────────────────────────────────────
async function loadSenderProfiles() {
  isProfilesLoading.value = true
  try {
    const res = await $api('/communications/email/sender-profiles')

    senderProfiles.value = res.data || []
    if (!form.sender_profile_id && senderProfiles.value.length > 0) {
      const defaultProf = senderProfiles.value.find(p => p.is_default && !p.is_system) || senderProfiles.value.find(p => !p.is_system) || senderProfiles.value[0]

      form.sender_profile_id = defaultProf?.id || null
    }
  } catch (err) {
    console.error('Failed to load sender profiles', err)
  } finally {
    isProfilesLoading.value = false
  }
}

async function loadTemplates() {
  isTemplatesLoading.value = true
  try {
    const res = await $api('/communications/email/templates')

    templates.value = res.data || []
  } catch (err) {
    console.error('Failed to load templates', err)
  } finally {
    isTemplatesLoading.value = false
  }
}

async function loadSignatures() {
  isSignaturesLoading.value = true
  try {
    const res = await $api('/communications/email/signatures')

    signatures.value = res.data || {}
  } catch (err) {
    console.error('Failed to load signatures', err)
  } finally {
    isSignaturesLoading.value = false
  }
}

async function loadDraft(id) {
  const validId = sanitizeDraftId(id)
  if (!validId) return
  try {
    const res = await $api(`/communications/email/messages/${validId}`)
    const msg = res.data?.email || res.data || res.email
    if (!msg) return

    form.sender_profile_id = msg.sender_profile_id || null
    form.subject = msg.subject || ''

    const rawHtml = msg.body_html || msg.message || ''
    const detectedSig = detectSignatureType(rawHtml)

    if (detectedSig !== 'none') {
      form.signature_type = detectedSig
    }
    form.body_html = stripExistingSignature(rawHtml)
    form.body_text = msg.body_text || htmlToPlainText(form.body_html)
    form.template_id = msg.template_id || null

    // Restore primary business context
    if (msg.primary_context && msg.primary_context.type && msg.primary_context.type !== 'none') {
      primaryContext.type = msg.primary_context.type
      primaryContext.record_id = msg.primary_context.record_id
      primaryContext.reference = msg.primary_context.reference
      primaryContext.label = `${msg.primary_context.type}: ${msg.primary_context.reference || msg.primary_context.record_id}`
    }

    // Restore associations
    if (Array.isArray(msg.associations)) {
      associations.value = msg.associations.map(a => ({
        type: a.record_type || a.type,
        id: String(a.record_id || a.id),
        reference: a.record_reference || a.reference || '',
        label: a.record_label || a.label || `${a.record_type || a.type}: ${a.record_reference || a.reference || a.id}`,
      }))
    }

    // Restore recipients with metadata
    const recs = msg.recipients || []
    const toRecs = recs.filter(r => r.type === 'to').length > 0 ? recs.filter(r => r.type === 'to') : (msg.to || [])
    const ccRecs = recs.filter(r => r.type === 'cc').length > 0 ? recs.filter(r => r.type === 'cc') : (msg.cc || [])
    const bccRecs = recs.filter(r => r.type === 'bcc').length > 0 ? recs.filter(r => r.type === 'bcc') : (msg.bcc || [])

    form.to = toRecs.map(r => ({
      email: r.email,
      name: r.name,
      recipient_type: r.recipient_type || r.entity_type || null,
      recipient_id: r.recipient_id || r.entity_id || null,
      contact_id: r.contact_id || null,
      contact_name: r.contact_name || null,
      entity_name: r.entity_name || null,
      address_label: r.address_label || null,
      email_source: r.email_source || 'general',
    }))

    form.cc = ccRecs.map(r => ({
      email: r.email,
      name: r.name,
      recipient_type: r.recipient_type || r.entity_type || null,
      recipient_id: r.recipient_id || r.entity_id || null,
      contact_id: r.contact_id || null,
      contact_name: r.contact_name || null,
      entity_name: r.entity_name || null,
      address_label: r.address_label || null,
      email_source: r.email_source || 'general',
    }))

    form.bcc = bccRecs.map(r => ({
      email: r.email,
      name: r.name,
      recipient_type: r.recipient_type || r.entity_type || null,
      recipient_id: r.recipient_id || r.entity_id || null,
      contact_id: r.contact_id || null,
      contact_name: r.contact_name || null,
      entity_name: r.entity_name || null,
      address_label: r.address_label || null,
      email_source: r.email_source || 'general',
    }))

    if (form.cc.length > 0) showCc.value = true
    if (form.bcc.length > 0) showBcc.value = true

    // Restore attachments
    if (msg.attachments && msg.attachments.length > 0) {
      attachments.value = msg.attachments.map(att => ({
        id: `draft-att-${att.id}`,
        backend_attachment_id: att.id,
        source: att.source || 'upload',
        source_type: att.source_type || 'bp_document',
        document_id: att.source_id || att.document_id,
        filename: att.filename || att.fileName,
        size_bytes: att.size_bytes || att.size || 0,
        formatted_size: att.formatted_size || formatDocumentBytes(att.size_bytes || att.size || 0),
        mime_type: att.mime_type,
        is_existing_attachment: true,
        file: null,
      }))
    }
  } catch (err) {
    notifyError(t('communications.email.load_draft_error', 'Failed to load draft details.'))
  }
}

// ── Recipients Management ──────────────────────────────────────────────────
function onDirectoryRecipientsConfirmed(selectedRecipients) {
  if (!Array.isArray(selectedRecipients)) return

  selectedRecipients.forEach(rec => {
    addRecipientToComposer(rec, rec.target || 'to')
  })
}

function addRecipientToComposer(item, target = 'to') {
  if (!item || !item.email) return

  const emailLower = item.email.toLowerCase().trim()

  // Prevent adding sender profile address
  if (selectedSender.value?.email && selectedSender.value.email.toLowerCase().trim() === emailLower) {
    notifyError(t('communications.email.sender_cannot_be_recipient', 'The sender profile address cannot be added as a recipient.'))

    return
  }

  // Prevent duplicate recipients across To, CC, and BCC
  const allCurrent = [...form.to, ...form.cc, ...form.bcc].map(r => r.email.toLowerCase().trim())
  if (allCurrent.includes(emailLower)) {
    return
  }

  const list = form[target] || form.to

  list.push({
    email: item.email.trim(),
    name: item.name || item.display_name || item.contact_name || '',
    recipient_type: item.recipient_type || item.entity_type || null,
    recipient_id: item.recipient_id || item.entity_id || null,
    contact_id: item.contact_id || null,
    contact_name: item.contact_name || null,
    entity_name: item.entity_name || null,
    address_label: item.address_label || null,
    email_source: item.email_source || item.source || 'general',
  })

  if (target === 'cc') showCc.value = true
  if (target === 'bcc') showBcc.value = true
}

function removeRecipient(target, index) {
  form[target].splice(index, 1)
}

function addManualRecipient() {
  const email = manualEmail.value.trim()
  if (!email || !EMAIL_REGEX.test(email)) {
    notifyError(t('communications.email.validation_invalid_email', 'Please enter a valid email address.'))

    return
  }

  const emailLower = email.toLowerCase()

  if (selectedSender.value?.email && selectedSender.value.email.toLowerCase().trim() === emailLower) {
    notifyError(t('communications.email.sender_cannot_be_recipient', 'The sender profile address cannot be added as a recipient.'))

    return
  }

  const allCurrent = [...form.to, ...form.cc, ...form.bcc].map(r => r.email.toLowerCase().trim())
  if (allCurrent.includes(emailLower)) {
    notifyError(t('communications.email.recipient_already_added', 'This email address is already added.'))

    return
  }

  const target = manualTarget.value || 'to'

  form[target].push({
    email,
    name: manualName.value.trim() || '',
    recipient_type: 'custom',
    recipient_id: null,
    contact_id: null,
    email_source: 'manual',
  })

  manualEmail.value = ''
  manualName.value = ''
  if (target === 'cc') showCc.value = true
  if (target === 'bcc') showBcc.value = true
}

// ── Step 2 & 3: Templates Handlers ──────────────────────────────────────────
watch(() => [props.initialRecordType, props.initialRecordId, props.initialReference], ([newType, newId, newRef]) => {
  if (newType && newId && newType !== 'none') {
    primaryContext.type = newType
    primaryContext.record_id = newId
    primaryContext.reference = newRef || null
    primaryContext.label = newRef ? `${newType}: ${newRef}` : `${newType} #${newId}`
  }
})

async function applyTemplateWithContext(tmpl) {
  if (!tmpl) {
    form.template_id = null
    renderError.value = null
    missingVariables.value = []
    hasMissingRecordContext.value = false

    return
  }
  isRenderingTemplate.value = true
  renderError.value = null
  missingVariables.value = []
  hasMissingRecordContext.value = false

  try {
    const payload = {
      record_type: hasRecordContext.value ? primaryContext.type : null,
      record_id: hasRecordContext.value ? String(primaryContext.record_id) : null,
      recipient: form.to[0] ? {
        name: form.to[0].name || form.to[0].email,
        email: form.to[0].email,
        phone: form.to[0].phone || '',
        company: form.to[0].company || '',
      } : {},
      sender_profile_id: form.sender_profile_id,
      signature_type: 'none', // Render template body purely so user edits message body only
    }

    const res = await $api(`/communications/email/templates/${tmpl.id}/render`, {
      method: 'POST',
      body: payload,
    })

    const rendered = res.data

    form.template_id = tmpl.id
    form.subject = rendered.subject || tmpl.subject || ''
    form.body_html = stripExistingSignature(rendered.body_html || tmpl.body_html || '')
    form.body_text = rendered.body_text || htmlToPlainText(form.body_html)
    form.language = tmpl.language || 'fr'

    missingVariables.value = res.missing_variables || res.data?.variables?.missing || []
    hasMissingRecordContext.value = Boolean(res.has_missing_record_context)

    if (hasMissingRecordContext.value || missingVariables.value.length > 0) {
      notifyError(t('communications.email.missing_context_alert', 'Ce modèle nécessite un enregistrement source pour certaines variables.'))
    } else {
      notifySuccess(t('communications.email.template_applied', `Modèle "${tmpl.name}" appliqué`))
    }
  } catch (err) {
    const errorMsg = err.data?.message || err.message || t('communications.email.render_error', 'Échec du rendu du modèle prédéfini.')

    renderError.value = errorMsg
    form.template_id = tmpl.id
    form.subject = tmpl.subject || ''
    form.body_html = stripExistingSignature(tmpl.body_html || tmpl.html_body || '')
    form.body_text = tmpl.body_text || htmlToPlainText(form.body_html)
    form.language = tmpl.language || 'fr'
    notifyError(errorMsg)
    console.error('Failed to render template on server:', err)
  } finally {
    isRenderingTemplate.value = false
  }
}

async function onTemplateSelected(tmpl) {
  if (!tmpl) {
    form.template_id = null
  } else {
    await applyTemplateWithContext(tmpl)
  }
  nextTick(() => {
    wizardRef.value?.wizard?.next()
  })
}

// ── Step 4: Attachments Handlers ────────────────────────────────────────────
function validateAndAddFile(file, source = 'upload') {
  if (!file) return false

  if (attachments.value.length >= MAX_ATTACHMENTS) {
    notifyError(t('communications.email.validation_max_attachments', 'Maximum 10 attachments allowed.'))

    return false
  }

  if (file.size > MAX_FILE_BYTES) {
    notifyError(`${file.name}: ${t('communications.email.validation_file_too_large', 'File exceeds 10 MB limit.')}`)

    return false
  }

  const ext = file.name.split('.').pop()?.toLowerCase() || ''
  if (!ALLOWED_EXTENSIONS.includes(ext)) {
    notifyError(`${file.name}: ${t('communications.email.validation_extension_not_allowed', 'Extension not allowed.')}`)

    return false
  }

  if (totalAttachmentBytes.value + file.size > MAX_TOTAL_BYTES) {
    notifyError(t('communications.email.validation_max_size', 'Total attachments size exceeds 20 MB.'))

    return false
  }

  const preview_url = file.type.startsWith('image/') || file.type === 'application/pdf'
    ? URL.createObjectURL(file)
    : null

  attachments.value.push({
    id: `local-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    source,
    filename: file.name,
    size_bytes: file.size,
    formatted_size: formatDocumentBytes(file.size),
    mime_type: file.type || 'application/octet-stream',
    file,
    preview_url,
    is_existing_attachment: false,
  })

  return true
}

function onAttachmentDialogSaved(payload) {
  if (!payload) return
  const filesToAdd = payload.files?.length ? payload.files : (payload.file ? [payload.file] : [])

  filesToAdd.forEach(file => {
    const isScan = file.name?.toLowerCase().startsWith('scan_') || payload.source === 'scan'
    const isPhoto = payload.source === 'photo'
    const resolvedSource = isScan ? 'scan' : (isPhoto ? 'photo' : (payload.source || 'upload'))

    validateAndAddFile(file, resolvedSource)
  })
  isAttachmentDialogOpen.value = false
}

// Search & select existing WAVE PLUS documents
const docColumns = [
  { key: 'name', title: t('Name', 'Name') },
  { key: 'related_entity', title: t('Related Entity', 'Related Entity') },
  { key: 'module', title: t('Module', 'Module') },
  { key: 'category', title: t('Category', 'Category') },
  { key: 'formatted_size', title: t('Size', 'Size') },
]

const docDisplay = {
  title: 'name',
  subtitle: item => item.related_entity ? `${item.related_entity.type}: ${item.related_entity.name}` : (item.module || ''),
  chips: [
    item => ({ label: item.category || item.module, color: 'primary' }),
    item => ({ label: item.formatted_size, color: 'secondary' }),
  ],
}

async function fetchWaveDocuments({ page, perPage, search }) {
  const res = await $api('/communications/email/documents/search', {
    params: {
      query: search,
      page,
      per_page: perPage,
    },
  })

  return {
    items: res.data || [],
    total: res.meta?.total || 0,
  }
}

function onDocumentsSelected(selectedDocs) {
  const items = Array.isArray(selectedDocs) ? selectedDocs : [selectedDocs]

  items.forEach(doc => {
    if (!doc || !doc.id) return

    // Avoid duplicates
    if (attachments.value.some(a => a.document_id === doc.id)) return

    if (attachments.value.length >= MAX_ATTACHMENTS) {
      notifyError(t('communications.email.validation_max_attachments', 'Maximum 10 attachments allowed.'))

      return
    }

    const docSize = doc.file_size || 0
    if (totalAttachmentBytes.value + docSize > MAX_TOTAL_BYTES) {
      notifyError(t('communications.email.validation_max_size', 'Total attachments size exceeds 20 MB.'))

      return
    }

    attachments.value.push({
      id: `bp-doc-${doc.id}`,
      source: 'document',
      filename: doc.name || doc.file_name,
      size_bytes: docSize,
      formatted_size: doc.formatted_size || formatDocumentBytes(docSize),
      mime_type: doc.mime_type || 'application/pdf',
      document_id: doc.id,
      source_type: 'bp_document',
      related_entity: doc.related_entity,
      file: null,
      preview_url: null,
      is_existing_attachment: false,
    })
  })
}

function removeAttachment(index) {
  const att = attachments.value[index]
  if (att && att.preview_url?.startsWith('blob:')) {
    URL.revokeObjectURL(att.preview_url)
  }
  attachments.value.splice(index, 1)
}

function openAttachmentPreview(att) {
  if (att.file || att.preview_url) {
    mediaViewerItem.value = {
      url: att.preview_url || (att.file ? URL.createObjectURL(att.file) : null),
      name: att.filename,
      mime_type: att.mime_type,
    }
    isMediaViewerOpen.value = true
  }
}

// ── Step 4: Submission & Draft Persistence ──────────────────────────────────
async function submitEmail({ sendImmediately = false }) {
  if (isSubmitting.value) return
  isSubmitting.value = true

  try {
    const formData = new FormData()

    if (form.sender_profile_id) {
      formData.append('sender_profile_id', String(form.sender_profile_id))
    }

    // Primary business context
    if (primaryContext.type && primaryContext.type !== 'none' && primaryContext.record_id) {
      formData.append('primary_context_type', primaryContext.type)
      formData.append('primary_context_id', String(primaryContext.record_id))
    }

    // Contact associations derived automatically from recipients
    let assocIdx = 0
    linkedContacts.value.forEach(lc => {
      if (lc.type === 'bp_contact') {
        formData.append(`associations[${assocIdx}][type]`, lc.bp_type)
        formData.append(`associations[${assocIdx}][id]`, String(lc.bp_id))
        assocIdx++
        formData.append(`associations[${assocIdx}][type]`, 'bp_contact')
        formData.append(`associations[${assocIdx}][id]`, String(lc.id))
        assocIdx++
      } else {
        formData.append(`associations[${assocIdx}][type]`, lc.type)
        formData.append(`associations[${assocIdx}][id]`, String(lc.id))
        assocIdx++
      }
    })

    // Recipients
    form.to.forEach((rec, idx) => {
      formData.append(`to[${idx}][email]`, rec.email)
      if (rec.name) formData.append(`to[${idx}][name]`, rec.name)
      if (rec.recipient_type) formData.append(`to[${idx}][recipient_type]`, rec.recipient_type)
      if (rec.recipient_id) formData.append(`to[${idx}][recipient_id]`, String(rec.recipient_id))
      if (rec.contact_id) formData.append(`to[${idx}][contact_id]`, String(rec.contact_id))
      if (rec.email_source) formData.append(`to[${idx}][email_source]`, rec.email_source)
    })

    form.cc.forEach((rec, idx) => {
      formData.append(`cc[${idx}][email]`, rec.email)
      if (rec.name) formData.append(`cc[${idx}][name]`, rec.name)
      if (rec.recipient_type) formData.append(`cc[${idx}][recipient_type]`, rec.recipient_type)
      if (rec.recipient_id) formData.append(`cc[${idx}][recipient_id]`, String(rec.recipient_id))
      if (rec.contact_id) formData.append(`cc[${idx}][contact_id]`, String(rec.contact_id))
      if (rec.email_source) formData.append(`cc[${idx}][email_source]`, rec.email_source)
    })

    form.bcc.forEach((rec, idx) => {
      formData.append(`bcc[${idx}][email]`, rec.email)
      if (rec.name) formData.append(`bcc[${idx}][name]`, rec.name)
      if (rec.recipient_type) formData.append(`bcc[${idx}][recipient_type]`, rec.recipient_type)
      if (rec.recipient_id) formData.append(`bcc[${idx}][recipient_id]`, String(rec.recipient_id))
      if (rec.contact_id) formData.append(`bcc[${idx}][contact_id]`, String(rec.contact_id))
      if (rec.email_source) formData.append(`bcc[${idx}][email_source]`, rec.email_source)
    })

    formData.append('subject', form.subject)

    let finalHtml = stripExistingSignature(form.body_html || '')

    // Prevent duplicate signature insertion: only append chosen signature once
    if (form.signature_type !== 'none') {
      const sigHtml = signatures.value?.[form.signature_type]?.html || ''
      if (sigHtml) {
        finalHtml = appendSignatureToHtml(finalHtml, sigHtml)
      }
    }
    formData.append('body_html', finalHtml)

    // Generate synchronized plain-text body
    const plainText = (form.body_text && form.body_text.trim())
      ? form.body_text
      : htmlToPlainText(finalHtml)

    formData.append('body_text', plainText)

    // Pre-flight check: validate unresolved template placeholders
    if (sendImmediately) {
      const unresolvedInSubject = (form.subject || '').match(/\{\{\s*[\w.\-]+\s*\}\}/g)
      const unresolvedInBody = (finalHtml || '').match(/\{\{\s*[\w.\-]+\s*\}\}/g)
      if (unresolvedInSubject || unresolvedInBody) {
        const tokens = [...(unresolvedInSubject || []), ...(unresolvedInBody || [])].slice(0, 3).join(', ')

        notifyError(t('communications.email.unresolved_variables', `Please fill or remove template variables before sending: ${tokens}`))
        isSubmitting.value = false
        isSavingDraft.value = false
        
        return
      }
    }

    if (form.template_id) {
      formData.append('template_id', String(form.template_id))
    }

    formData.append('send_immediately', sendImmediately ? '1' : '0')

    // Uploaded / photo / scanner files
    attachments.value.forEach(att => {
      if (att.file instanceof File || att.file instanceof Blob) {
        formData.append('attachments[]', att.file, att.filename)
      }
    })

    // Existing WAVE PLUS documents
    const docAttachments = attachments.value.filter(att => att.source === 'document' && att.document_id)

    docAttachments.forEach((att, idx) => {
      formData.append(`document_attachments[${idx}][source]`, att.source_type || 'bp_document')
      formData.append(`document_attachments[${idx}][id]`, String(att.document_id))
    })

    const validDraftId = sanitizeDraftId(currentDraftId.value)

    // Keep already-persisted attachments if updating draft
    if (validDraftId) {
      formData.append('_method', 'PUT')
      formData.append('sync_attachments', '1')

      const keepIds = attachments.value
        .filter(att => att.is_existing_attachment && att.backend_attachment_id)
        .map(att => att.backend_attachment_id)

      keepIds.forEach((id, idx) => {
        formData.append(`keep_attachment_ids[${idx}]`, String(id))
      })
    }

    const endpoint = validDraftId
      ? `/communications/email/messages/${validDraftId}`
      : '/communications/email/messages'

    const res = await $api(endpoint, {
      method: 'POST',
      body: formData,
    })

    if (!validDraftId && res.data?.id) {
      currentDraftId.value = res.data.id
    }

    if (sendImmediately) {
      notifySuccess(t('communications.email.email_queued_success', 'Email queued for sending.'))
      emit('sent', res.data)
      emit('update:modelValue', false)
      emit('close')
      resetComposer()
    } else {
      notifySuccess(t('communications.email.draft_saved_success', 'Email draft saved successfully.'))
      emit('draft-saved', res.data)
    }

    return res.data
  } catch (err) {
    const msg = err.data?.message || err.message || t('wp.wizard.error', 'An error occurred.')

    notifyError(msg)
    throw err
  } finally {
    isSubmitting.value = false
    isSavingDraft.value = false
  }
}

async function handleSaveDraftAction() {
  isSavingDraft.value = true
  try {
    await submitEmail({ sendImmediately: false })
  } catch (err) {
    // handled in submitEmail
  }
}

function resetComposer() {
  attachments.value.forEach(att => {
    if (att.preview_url?.startsWith('blob:')) {
      URL.revokeObjectURL(att.preview_url)
    }
  })
  attachments.value = []
  associations.value = []
  primaryContext.type = 'none'
  primaryContext.record_id = null
  primaryContext.reference = null
  primaryContext.label = null
  currentDraftId.value = null
  form.to = []
  form.cc = []
  form.bcc = []
  form.subject = ''
  form.body_html = ''
  form.body_text = ''
  form.template_id = null
  form.signature_type = 'sender_default'
  showCc.value = false
  showBcc.value = false
  activeMessageTab.value = 'compose'
}

// ── Lifecycle ───────────────────────────────────────────────────────────────
onMounted(async () => {
  await Promise.all([
    loadSenderProfiles(),
    loadTemplates(),
    loadSignatures(),
  ])

  if (props.initialRecordType && props.initialRecordId) {
    primaryContext.type = props.initialRecordType
    primaryContext.record_id = props.initialRecordId
    primaryContext.reference = props.initialReference
    primaryContext.label = `${props.initialRecordType}: ${props.initialReference || props.initialRecordId}`
  }

  const validInitialDraftId = sanitizeDraftId(props.draftId)
  if (validInitialDraftId) {
    await loadDraft(validInitialDraftId)
  }
  if (props.initialTo && props.initialTo.length > 0) {
    form.to = [...props.initialTo]
  }
  if (props.initialSubject) {
    form.subject = props.initialSubject
  }
  if (props.initialBody) {
    form.body_html = props.initialBody
  }
})

watch(() => props.draftId, newId => {
  const validId = sanitizeDraftId(newId)

  currentDraftId.value = validId
  if (validId) {
    loadDraft(validId)
  }
})

onBeforeUnmount(() => {
  attachments.value.forEach(att => {
    if (att.preview_url?.startsWith('blob:')) {
      URL.revokeObjectURL(att.preview_url)
    }
  })
})
</script>

<template>
  <WpWizardDialog
    ref="wizardRef"
    :model-value="modelValue"
    variant="standard"
    :compact-stepper="false"
    :title="t('communications.email.composer_title', 'Smart Email Composer')"
    :description="t('communications.email.composer_desc', 'Compose, customize, attach documents, and send emails securely.')"
    icon="tabler-mail-fast"
    :steps="wizardSteps"
    :initial-values="form"
    review
    :review-title="t('communications.email.step_review', 'Review & Send')"
    :submit-label="t('communications.email.send_now', 'Send Now')"
    :submit="() => submitEmail({ sendImmediately: true })"
    :loading="isSubmitting"
    :max-width="960"
    @update:model-value="$emit('update:modelValue', $event)"
    @cancel="resetComposer(); $emit('close')"
  >
    <!-- ────────────────────────────────────────────────────────────────── -->
    <!-- STEP 1: SENDER AND RECIPIENTS                                      -->
    <!-- ────────────────────────────────────────────────────────────────── -->
    <template #step-sender_recipients="{ errors }">
      <div class="py-2">
        <!-- Sender Profile Selection -->
        <SenderIdentityCard
          v-model="form.sender_profile_id"
          :profiles="senderProfiles"
          :loading="isProfilesLoading"
          :error-message="errors.sender_profile_id"
        />

        <VDivider class="my-4" />

        <!-- Recipient Action Bar: Dedicated Recipient Directory Wizard & Manual Entry -->
        <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
          <div>
            <div class="text-subtitle-1 font-weight-bold">
              {{ t('communications.email.recipients_directory_title', 'Recipients') }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ t('communications.email.recipients_directory_desc', 'Open the multi-step directory to search and assign Business Partners and Employees, or enter manual email addresses.') }}
            </div>
          </div>

          <div class="d-flex align-center gap-2">
            <VBtn
              variant="elevated"
              color="primary"
              prepend-icon="tabler-address-book"
              @click="isRecipientWizardVisible = true"
            >
              {{ t('communications.email.browse_directory', 'Browse Directory') }}
            </VBtn>

            <VBtn
              variant="tonal"
              color="secondary"
              :prepend-icon="isManualEntryOpen ? 'tabler-chevron-up' : 'tabler-pencil-plus'"
              @click="isManualEntryOpen = !isManualEntryOpen"
            >
              {{ t('communications.email.others_manual', 'Others / Manual') }}
            </VBtn>
          </div>
        </div>

        <!-- Manual Recipient Entry (Under Others / Manual) -->
        <VExpandTransition>
          <div
            v-if="isManualEntryOpen"
            class="pa-4 rounded-lg border mb-4 bg-var-theme-background"
          >
            <div class="text-caption font-weight-bold mb-2 text-uppercase text-medium-emphasis d-flex align-center gap-1">
              <VIcon
                icon="tabler-pencil"
                size="14"
              />
              {{ t('communications.email.manual_add_title', 'Add Recipient Manually (Others)') }}
            </div>
            <VRow
              dense
              align="center"
            >
              <VCol
                cols="12"
                sm="5"
              >
                <VTextField
                  v-model="manualEmail"
                  :placeholder="t('communications.email.manual_email', 'Email address')"
                  density="compact"
                  variant="outlined"
                  prepend-inner-icon="tabler-at"
                  hide-details
                  @keyup.enter="addManualRecipient"
                />
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <VTextField
                  v-model="manualName"
                  :placeholder="t('communications.email.manual_name', 'Contact name (optional)')"
                  density="compact"
                  variant="outlined"
                  prepend-inner-icon="tabler-user"
                  hide-details
                  @keyup.enter="addManualRecipient"
                />
              </VCol>
              <VCol
                cols="8"
                sm="2"
              >
                <VSelect
                  v-model="manualTarget"
                  :items="[
                    { title: t('communications.email.to', 'To'), value: 'to' },
                    { title: t('communications.email.cc', 'Cc'), value: 'cc' },
                    { title: t('communications.email.bcc', 'Bcc'), value: 'bcc' },
                  ]"
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </VCol>
              <VCol
                cols="4"
                sm="1"
                class="d-flex justify-end"
              >
                <VBtn
                  color="primary"
                  size="small"
                  @click="addManualRecipient"
                >
                  {{ t('communications.email.add_recipient', 'Add') }}
                </VBtn>
              </VCol>
            </VRow>
          </div>
        </VExpandTransition>

        <!-- Selected Recipients Lists (To, Cc, Bcc) -->
        <div class="rounded border pa-3 bg-surface">
          <!-- TO Field -->
          <div class="d-flex align-start flex-wrap gap-2 mb-2">
            <span
              class="text-subtitle-2 font-weight-bold text-high-emphasis pt-1"
              style="min-inline-size: 40px;"
            >
              {{ t('communications.email.to', 'To') }}:
            </span>
            <div class="d-flex flex-wrap gap-2 flex-grow-1">
              <VChip
                v-for="(rec, idx) in form.to"
                :key="`to-${rec.email}-${idx}`"
                closable
                size="small"
                color="primary"
                variant="tonal"
                class="font-weight-medium"
                @click:close="removeRecipient('to', idx)"
              >
                <VIcon
                  start
                  icon="tabler-mail"
                  size="14"
                />
                <span>{{ rec.name ? `${rec.name} <${rec.email}>` : rec.email }}</span>
                <span
                  v-if="rec.email_source"
                  class="ms-1 text-caption opacity-75"
                >
                  ({{ rec.email_source }})
                </span>
              </VChip>
              <span
                v-if="form.to.length === 0"
                class="text-caption text-disabled pt-1"
              >
                {{ t('communications.email.no_to_selected', 'Search or enter recipients above...') }}
              </span>
            </div>
            <div class="d-flex align-center gap-1">
              <VBtn
                variant="text"
                size="x-small"
                color="secondary"
                @click="showCc = !showCc"
              >
                {{ showCc ? '- Cc' : '+ Cc' }}
              </VBtn>
              <VBtn
                variant="text"
                size="x-small"
                color="secondary"
                @click="showBcc = !showBcc"
              >
                {{ showBcc ? '- Bcc' : '+ Bcc' }}
              </VBtn>
            </div>
          </div>
          <div
            v-if="errors.to"
            class="text-caption text-error mb-2"
          >
            {{ errors.to }}
          </div>

          <!-- CC Field -->
          <VExpandTransition>
            <div
              v-show="showCc"
              class="d-flex align-start flex-wrap gap-2 pt-2 border-t"
            >
              <span
                class="text-subtitle-2 font-weight-medium text-medium-emphasis pt-1"
                style="min-inline-size: 40px;"
              >
                {{ t('communications.email.cc', 'Cc') }}:
              </span>
              <div class="d-flex flex-wrap gap-2 flex-grow-1">
                <VChip
                  v-for="(rec, idx) in form.cc"
                  :key="`cc-${rec.email}-${idx}`"
                  closable
                  size="small"
                  color="secondary"
                  variant="tonal"
                  @click:close="removeRecipient('cc', idx)"
                >
                  <span>{{ rec.name ? `${rec.name} <${rec.email}>` : rec.email }}</span>
                </VChip>
                <span
                  v-if="form.cc.length === 0"
                  class="text-caption text-disabled pt-1"
                >
                  {{ t('communications.email.no_cc', 'No CC recipients.') }}
                </span>
              </div>
            </div>
          </VExpandTransition>

          <!-- BCC Field -->
          <VExpandTransition>
            <div
              v-show="showBcc"
              class="d-flex align-start flex-wrap gap-2 pt-2 border-t mt-2"
            >
              <span
                class="text-subtitle-2 font-weight-medium text-medium-emphasis pt-1"
                style="min-inline-size: 40px;"
              >
                {{ t('communications.email.bcc', 'Bcc') }}:
              </span>
              <div class="d-flex flex-wrap gap-2 flex-grow-1">
                <VChip
                  v-for="(rec, idx) in form.bcc"
                  :key="`bcc-${rec.email}-${idx}`"
                  closable
                  size="small"
                  color="warning"
                  variant="tonal"
                  @click:close="removeRecipient('bcc', idx)"
                >
                  <span>{{ rec.name ? `${rec.name} <${rec.email}>` : rec.email }}</span>
                </VChip>
                <span
                  v-if="form.bcc.length === 0"
                  class="text-caption text-disabled pt-1"
                >
                  {{ t('communications.email.no_bcc', 'No BCC recipients.') }}
                </span>
              </div>
            </div>
          </VExpandTransition>

          <!-- Linked Contacts Summary -->
          <div
            v-if="displayLinkedContacts.length > 0"
            class="pt-3 border-t mt-3"
          >
            <div class="d-flex align-center gap-1 text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2">
              <VIcon
                icon="tabler-address-book"
                size="15"
                color="primary"
              />
              {{ t('communications.email.linked_contacts_title', 'Linked contacts') }} ({{ displayLinkedContacts.length }}):
            </div>
            <div class="d-flex flex-wrap gap-2">
              <VChip
                v-for="lc in displayLinkedContacts"
                :key="`step1-lc-${lc.key}`"
                size="small"
                :color="lc.color"
                variant="tonal"
                class="font-weight-medium"
              >
                <VIcon
                  start
                  :icon="lc.icon"
                  size="14"
                />
                {{ lc.title }}
              </VChip>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- ────────────────────────────────────────────────────────────────── -->
    <!-- STEP 2: TEMPLATE SELECTION                                         -->
    <!-- ────────────────────────────────────────────────────────────────── -->
    <template #step-template_selection>
      <TemplateCatalogStep
        v-model="form.template_id"
        :templates="templates"
        :loading="isTemplatesLoading"
        :has-record-context="hasRecordContext"
        :record-context-label="recordContextLabel"
        :initial-language="form.language || 'fr'"
        @select="onTemplateSelected"
      />
    </template>

    <!-- ────────────────────────────────────────────────────────────────── -->
    <!-- STEP 3: MESSAGE CONTENT & SUBJECT                                  -->
    <!-- ────────────────────────────────────────────────────────────────── -->
    <template #step-message_content="{ errors }">
      <MessageEditorStep
        v-model:subject="form.subject"
        v-model:body-html="form.body_html"
        v-model:signature-type="form.signature_type"
        :signatures="signatures"
        :selected-template="selectedTemplateItem"
        :has-record-context="hasRecordContext"
        :record-context-label="recordContextLabel"
        :missing-variables="missingVariables"
        :render-error="renderError"
        :is-rendering="isRenderingTemplate"
        :language="form.language || 'fr'"
        :sender="selectedSender"
        :to-recipients="form.to"
        :cc-recipients="form.cc"
        :errors="errors"
        @change-template="wizardRef?.wizard?.goTo('template_selection')"
      />
    </template>

    <!-- ────────────────────────────────────────────────────────────────── -->
    <!-- STEP 3: ATTACHMENTS                                                -->
    <!-- ────────────────────────────────────────────────────────────────── -->
    <template #step-attachments>
      <AttachmentStep
        :attachments="attachments"
        :max-attachments="MAX_ATTACHMENTS"
        :max-total-bytes="MAX_TOTAL_BYTES"
        :max-file-bytes="MAX_FILE_BYTES"
        @open-attachment-dialog="isAttachmentDialogOpen = true"
        @open-document-picker="isDocumentPickerOpen = true"
        @remove-attachment="removeAttachment"
        @preview-attachment="openAttachmentPreview"
      />
    </template>

    <!-- ────────────────────────────────────────────────────────────────── -->
    <!-- STEP 4: REVIEW AND SEND                                            -->
    <!-- ────────────────────────────────────────────────────────────────── -->
    <template #review>
      <div class="py-2">
        <VAlert
          type="info"
          variant="tonal"
          density="comfortable"
          icon="tabler-info-circle"
          class="mb-4"
        >
          <div class="font-weight-medium">
            {{ t('communications.email.review_title', 'Review & Confirm') }}
          </div>
          <div class="text-caption">
            {{ t('communications.email.review_desc', 'Carefully review all email details before sending.') }}
          </div>
        </VAlert>

        <!-- Summary Card -->
        <VCard
          variant="outlined"
          class="pa-4 mb-4"
        >
          <VRow dense>
            <!-- From -->
            <VCol
              cols="12"
              sm="6"
              class="border-b pb-2"
            >
              <div class="text-caption text-medium-emphasis">
                {{ t('communications.email.from_identity', 'From Identity') }}:
              </div>
              <div class="font-weight-bold d-flex align-center gap-2">
                <span>{{ selectedSender?.display_name }}</span>
                <span class="text-caption text-medium-emphasis">&lt;{{ selectedSender?.email }}&gt;</span>
                <VChip
                  v-if="selectedSender?.department"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                >
                  {{ selectedSender?.department }}
                </VChip>
              </div>
            </VCol>

            <!-- Signature -->
            <VCol
              cols="12"
              sm="6"
              class="border-b pb-2"
            >
              <div class="text-caption text-medium-emphasis">
                {{ t('communications.email.signature', 'Signature') }}:
              </div>
              <div class="font-weight-medium text-capitalize">
                {{ form.signature_type }}
              </div>
            </VCol>

            <!-- To Recipients -->
            <VCol
              cols="12"
              class="border-b py-2"
            >
              <div class="text-caption text-medium-emphasis mb-1">
                {{ t('communications.email.to', 'To') }}:
              </div>
              <div class="d-flex flex-wrap gap-1">
                <VChip
                  v-for="rec in form.to"
                  :key="`rev-to-${rec.email}`"
                  size="small"
                  color="primary"
                  variant="tonal"
                >
                  {{ rec.name ? `${rec.name} <${rec.email}>` : rec.email }}
                  <span
                    v-if="rec.email_source"
                    class="ms-1 opacity-75"
                  >({{ rec.email_source }})</span>
                </VChip>
              </div>
            </VCol>

            <!-- Cc Recipients -->
            <VCol
              v-if="form.cc.length > 0"
              cols="12"
              class="border-b py-2"
            >
              <div class="text-caption text-medium-emphasis mb-1">
                {{ t('communications.email.cc', 'Cc') }}:
              </div>
              <div class="d-flex flex-wrap gap-1">
                <VChip
                  v-for="rec in form.cc"
                  :key="`rev-cc-${rec.email}`"
                  size="small"
                  color="secondary"
                  variant="tonal"
                >
                  {{ rec.name ? `${rec.name} <${rec.email}>` : rec.email }}
                </VChip>
              </div>
            </VCol>

            <!-- Bcc Recipients -->
            <VCol
              v-if="form.bcc.length > 0"
              cols="12"
              class="border-b py-2"
            >
              <div class="text-caption text-medium-emphasis mb-1">
                {{ t('communications.email.bcc', 'Bcc') }}:
              </div>
              <div class="d-flex flex-wrap gap-1">
                <VChip
                  v-for="rec in form.bcc"
                  :key="`rev-bcc-${rec.email}`"
                  size="small"
                  color="warning"
                  variant="tonal"
                >
                  {{ rec.name ? `${rec.name} <${rec.email}>` : rec.email }}
                </VChip>
              </div>
            </VCol>

            <!-- Subject -->
            <VCol
              cols="12"
              class="border-b py-2"
            >
              <div class="text-caption text-medium-emphasis">
                {{ t('communications.email.subject', 'Subject') }}:
              </div>
              <div class="text-subtitle-1 font-weight-bold">
                {{ form.subject }}
              </div>
            </VCol>

            <!-- Attachments -->
            <VCol
              cols="12"
              class="pt-2"
            >
              <div class="text-caption text-medium-emphasis mb-1">
                {{ t('communications.email.step_attachments', 'Attachments') }} ({{ attachments.length }} - {{ totalAttachmentMb }} MB):
              </div>
              <div
                v-if="attachments.length > 0"
                class="d-flex flex-wrap gap-2"
              >
                <VChip
                  v-for="att in attachments"
                  :key="`rev-att-${att.id}`"
                  size="small"
                  variant="outlined"
                  class="gap-1"
                >
                  <VIcon
                    :icon="resolveFileIcon(att.mime_type, att.filename)"
                    size="14"
                  />
                  <span>{{ att.filename }}</span>
                  <span class="text-caption text-medium-emphasis">({{ att.formatted_size }})</span>
                </VChip>
              </div>
              <div
                v-else
                class="text-caption text-medium-emphasis"
              >
                {{ t('communications.email.no_attachments', 'No attachments.') }}
              </div>
            </VCol>

            <!-- Linked Contacts -->
            <VCol
              cols="12"
              class="pt-2 border-t mt-2"
            >
              <div class="text-caption text-medium-emphasis mb-1 font-weight-medium d-flex align-center gap-1">
                <VIcon
                  icon="tabler-address-book"
                  size="14"
                  color="primary"
                />
                {{ t('communications.email.linked_contacts_title', 'Linked contacts') }} ({{ displayLinkedContacts.length }}):
              </div>
              <div
                v-if="displayLinkedContacts.length > 0"
                class="d-flex flex-wrap gap-2"
              >
                <VChip
                  v-for="lc in displayLinkedContacts"
                  :key="`rev-lc-${lc.key}`"
                  size="small"
                  :color="lc.color"
                  variant="tonal"
                  class="font-weight-medium"
                >
                  <VIcon
                    start
                    :icon="lc.icon"
                    size="14"
                  />
                  {{ lc.title }}
                </VChip>
              </div>
              <div
                v-else
                class="text-caption text-medium-emphasis"
              >
                {{ t('communications.email.no_linked_contacts', 'No automatic contact associations.') }}
              </div>
            </VCol>
          </VRow>
        </VCard>

        <!-- Rendered Message Box -->
        <div class="rounded border pa-4 bg-surface">
          <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2">
            {{ t('communications.email.rendered_preview', 'Rendered Email Content') }}
          </div>
          <PerfectScrollbar
            :options="{ wheelPropagation: false, suppressScrollX: true }"
            style="max-block-size: 380px; position: relative; overflow: hidden !important;"
          >
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div
              class="email-preview-content pe-2"
              v-html="renderedPreviewHtml"
            />
          </PerfectScrollbar>
        </div>
      </div>
    </template>

    <!-- ────────────────────────────────────────────────────────────────── -->
    <!-- FOOTER ACTIONS                                                     -->
    <!-- ────────────────────────────────────────────────────────────────── -->
    <template #footer-start>
      <div class="d-flex align-center gap-2">
        <VBtn
          variant="tonal"
          color="warning"
          prepend-icon="tabler-device-floppy"
          size="default"
          :loading="isSavingDraft"
          :disabled="isSubmitting"
          @click="handleSaveDraftAction"
        >
          {{ t('communications.email.save_draft', 'Save Draft') }}
        </VBtn>
        <span
          v-if="currentDraftId"
          class="text-caption text-medium-emphasis d-none d-sm-inline"
        >
          (Draft #{{ currentDraftId }})
        </span>
      </div>
    </template>
  </WpWizardDialog>

  <!-- External Acquisition Sub-Dialogs -->
  <!-- 1. Standard WAVE PLUS Attachment Dialog (upload, photo, scan) -->
  <WpAttachmentDialog
    v-model="isAttachmentDialogOpen"
    :title="t('communications.email.attach_files', 'Attach Files')"
    preset="compact"
    multiple
    :allowed-sources="['file', 'photo', 'scan', 'media']"
    :max-size-mb="10"
    @save="onAttachmentDialogSaved"
  />

  <!-- 2. Search Existing WAVE PLUS Documents Dialog -->
  <WpSearchSelectDialog
    v-model="isDocumentPickerOpen"
    :title="t('communications.email.search_documents', 'Search Documents')"
    entity-label="document"
    :fetcher="fetchWaveDocuments"
    :columns="docColumns"
    :display="docDisplay"
    multiple
    @select="onDocumentsSelected"
  />

  <!-- 2b. Browse Recipient Directory Wizard Dialog -->
  <RecipientDirectoryWizardDialog
    v-model="isRecipientWizardVisible"
    :existing-emails="allCurrentEmails"
    :sender-email="selectedSender?.email || ''"
    @confirm="onDirectoryRecipientsConfirmed"
  />

  <!-- 3. Attachment Media Preview Dialog -->
  <WpMediaViewerDialog
    v-model="isMediaViewerOpen"
    :item="mediaViewerItem"
    :title="mediaViewerItem?.name"
  />
</template>

<style lang="scss" scoped>
.email-preview-content {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  font-size: 0.9375rem;
  line-height: 1.6;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));

  :deep(p) {
    margin-block-end: 0.75rem;
  }

  :deep(ul),
  :deep(ol) {
    padding-inline-start: 1.5rem;
    margin-block-end: 0.75rem;
  }
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
