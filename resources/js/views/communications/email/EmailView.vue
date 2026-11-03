<script setup>
import { computed, ref, watch } from 'vue'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useEmail } from '@/views/communications/email/useEmail'
import { $api } from '@/utils/api'
import { formatDocumentBytes, resolveFileColor, resolveFileIcon } from '@/composables/documents/documentFiles'

const props = defineProps({
  email: {
    type: null,
    required: true,
  },
  emailMeta: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits([
  'refresh',
  'navigated',
  'close',
  'trash',
  'unread',
  'read',
  'star',
  'unstar',
])

const emailReply = ref('')
const showReplyBox = ref(false)
const showReplyCard = ref(true)
const isSendingReply = ref(false)

const conversationData = ref(null)
const internalNotes = ref([])
const newNoteContent = ref('')
const isAddingNote = ref(false)
const linkedRecords = ref([])
const isLinkingRecord = ref(false)
const showLinkDialog = ref(false)
const newLinkType = ref('quotation')
const newLinkId = ref('')
const newLinkReason = ref('')

const supportedRecordTypes = [
  { title: 'Quotation', value: 'quotation' },
  { title: 'Sales Order', value: 'sales_order' },
  { title: 'Purchase Order', value: 'purchase_order' },
  { title: 'Sales Invoice', value: 'sales_invoice' },
  { title: 'Client', value: 'client' },
  { title: 'Supplier', value: 'supplier' },
  { title: 'Mission', value: 'mission' },
  { title: 'Employee', value: 'employee' },
]

const { updateEmailLabels } = useEmail()
const { labels, resolveLabelColor, emailMoveToFolderActions, shallShowMoveToActionFor, moveSelectedEmailTo } = useEmail()

const handleMoveMailsTo = async action => {
  await moveSelectedEmailTo(action, [props.email.id])
  emit('refresh')
  emit('close')
}

const updateMailLabel = async label => {
  await updateEmailLabels([props.email.id], label)
  emit('refresh')
}

const loadConversation = async convId => {
  if (!convId) {
    conversationData.value = null
    internalNotes.value = []

    return
  }
  try {
    const res = await $api(`/communications/email/conversations/${convId}`)

    conversationData.value = res.data
    internalNotes.value = res.data?.notes || []
  } catch (err) {
    console.error('Failed to load conversation details', err)
  }
}

const loadLinks = async convId => {
  if (!convId) {
    linkedRecords.value = []

    return
  }
  try {
    const res = await $api(`/communications/email/conversations/${convId}/links`)

    linkedRecords.value = res.data || []
  } catch (err) {
    console.error('Failed to load linked records', err)
  }
}

const confirmLink = async linkId => {
  try {
    await $api(`/communications/email/record-links/${linkId}/confirm`, { method: 'POST' })
    if (props.email?.conversationId) {
      await loadLinks(props.email.conversationId)
    }
  } catch (err) {
    console.error('Failed to confirm link', err)
  }
}

const dismissLink = async linkId => {
  try {
    await $api(`/communications/email/record-links/${linkId}/dismiss`, { method: 'POST' })
    if (props.email?.conversationId) {
      await loadLinks(props.email.conversationId)
    }
  } catch (err) {
    console.error('Failed to dismiss link', err)
  }
}

const submitNewLink = async () => {
  if (!newLinkId.value.trim() || !props.email?.conversationId || isLinkingRecord.value) return
  isLinkingRecord.value = true
  try {
    await $api(`/communications/email/conversations/${props.email.conversationId}/links`, {
      method: 'POST',
      body: {
        'record_type': newLinkType.value,
        'record_id': newLinkId.value.trim(),
        reason: newLinkReason.value.trim() || undefined,
      },
    })
    showLinkDialog.value = false
    newLinkId.value = ''
    newLinkReason.value = ''
    await loadLinks(props.email.conversationId)
  } catch (err) {
    console.error('Failed to link record', err)
  } finally {
    isLinkingRecord.value = false
  }
}

watch(() => props.email?.conversationId, id => {
  if (id) {
    loadConversation(id)
    loadLinks(id)
  } else {
    conversationData.value = null
    internalNotes.value = []
    linkedRecords.value = []
  }
}, { immediate: true })

const updateConversationStatus = async newStatus => {
  if (!props.email?.conversationId) return
  try {
    await $api(`/communications/email/conversations/${props.email.conversationId}/status`, {
      method: 'POST',
      body: { status: newStatus },
    })
    await loadConversation(props.email.conversationId)
    emit('refresh')
  } catch (err) {
    console.error('Failed to update status', err)
  }
}

const submitNote = async () => {
  const content = newNoteContent.value.trim()
  if (!content || !props.email?.conversationId || isAddingNote.value) return
  isAddingNote.value = true
  try {
    await $api(`/communications/email/conversations/${props.email.conversationId}/notes`, {
      method: 'POST',
      body: { content },
    })
    newNoteContent.value = ''
    await loadConversation(props.email.conversationId)
  } catch (err) {
    console.error('Failed to add note', err)
  } finally {
    isAddingNote.value = false
  }
}

const sendReply = async () => {
  if (!emailReply.value.trim() || isSendingReply.value) return
  isSendingReply.value = true
  try {
    const subject = props.email.subject.startsWith('Re:') ? props.email.subject : `Re: ${props.email.subject}`
    const parentMsgId = props.email.message_id || (props.email.id ? `<${props.email.id}@waveplus.xyz>` : null)
    const existingRefs = props.email.references || ''
    const updatedRefs = existingRefs ? `${existingRefs} ${parentMsgId}` : parentMsgId

    await $api('/communications/email/messages', {
      method: 'POST',
      body: {
        to: [{ email: props.email.from.email, name: props.email.from.name }],
        subject,
        'body_html': emailReply.value,
        'conversation_id': props.email.conversationId,
        'in_reply_to': parentMsgId,
        'references': updatedRefs,
        'mailbox_id': conversationData.value?.mailbox_id || props.email.mailbox_id || undefined,
        'send_immediately': true,
      },
    })
    emailReply.value = ''
    showReplyBox.value = false
    showReplyCard.value = true
    emit('refresh')
    if (props.email.conversationId) {
      await loadConversation(props.email.conversationId)
    }
  } catch (err) {
    console.error('Failed sending reply', err)
  } finally {
    isSendingReply.value = false
  }
}

const conversationStatusColor = computed(() => {
  const st = conversationData.value?.status
  if (st === 'open') return 'success'
  if (st === 'pending') return 'warning'
  if (st === 'resolved') return 'primary'

  return 'secondary'
})
</script>

<template>
  <VNavigationDrawer
    :model-value="Boolean(props.email)"
    data-allow-mismatch
    absolute
    temporary
    touchless
    location="right"
    :scrim="false"
    floating
    class="email-view"
    width="520"
  >
    <template v-if="props.email">
      <!-- 👉 header -->
      <div class="email-view-header d-flex align-center px-5 py-3">
        <IconBtn
          class="me-2"
          @click="$emit('close'); showReplyBox = false; showReplyCard = true; emailReply = ''"
        >
          <VIcon
            size="22"
            icon="tabler-chevron-left"
            class="flip-in-rtl"
          />
        </IconBtn>

        <div class="d-flex align-center flex-wrap flex-grow-1 overflow-hidden gap-2">
          <div class="text-body-1 text-high-emphasis text-truncate">
            {{ props.email.subject }}
          </div>

          <!-- Conversation Status Dropdown (when threaded) -->
          <VMenu v-if="conversationData">
            <template #activator="{ props: menuProps }">
              <VChip
                v-bind="menuProps"
                size="small"
                :color="conversationStatusColor"
                variant="tonal"
                class="cursor-pointer text-capitalize"
                prepend-icon="tabler-circle-check"
              >
                {{ conversationData.status }}
              </VChip>
            </template>
            <VList density="compact">
              <VListItem @click="updateConversationStatus('open')">
                <VListItemTitle>Open</VListItemTitle>
              </VListItem>
              <VListItem @click="updateConversationStatus('pending')">
                <VListItemTitle>Pending</VListItemTitle>
              </VListItem>
              <VListItem @click="updateConversationStatus('resolved')">
                <VListItemTitle>Resolved</VListItemTitle>
              </VListItem>
              <VListItem @click="updateConversationStatus('closed')">
                <VListItemTitle>Closed</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <!-- Department badge -->
          <VChip
            v-if="conversationData?.department"
            size="x-small"
            color="secondary"
            variant="outlined"
            class="text-uppercase"
          >
            {{ conversationData.department }}
          </VChip>

          <div class="d-flex flex-wrap gap-2">
            <VChip
              v-for="label in props.email.labels"
              :key="label"
              :color="resolveLabelColor(label)"
              class="text-capitalize flex-shrink-0"
              size="small"
              :label="false"
            >
              {{ label }}
            </VChip>
          </div>
        </div>

        <div>
          <div class="d-flex align-center">
            <IconBtn
              :disabled="!props.emailMeta.hasPreviousEmail"
              @click="$emit('navigated', 'previous')"
            >
              <VIcon
                icon="tabler-chevron-left"
                class="flip-in-rtl"
              />
            </IconBtn>

            <IconBtn
              :disabled="!props.emailMeta.hasNextEmail"
              @click="$emit('navigated', 'next')"
            >
              <VIcon
                icon="tabler-chevron-right"
                class="flip-in-rtl"
              />
            </IconBtn>
          </div>
        </div>
      </div>

      <VDivider />

      <!-- 👉 Action bar -->
      <div class="email-view-action-bar d-flex align-center text-medium-emphasis px-6 gap-x-1">
        <!-- Trash -->
        <IconBtn
          v-show="!props.email.isDeleted"
          @click="$emit('trash'); $emit('close')"
        >
          <VIcon
            icon="tabler-trash"
            size="22"
          />
          <VTooltip
            activator="parent"
            location="top"
          >
            Delete Mail
          </VTooltip>
        </IconBtn>

        <!-- Read/Unread -->
        <IconBtn @click.stop="$emit('unread'); $emit('close')">
          <VIcon
            icon="tabler-mail"
            size="22"
          />
          <VTooltip
            activator="parent"
            location="top"
          >
            Mark as Unread
          </VTooltip>
        </IconBtn>

        <!-- Move to folder -->
        <IconBtn>
          <VIcon
            icon="tabler-folder"
            size="22"
          />
          <VTooltip
            activator="parent"
            location="top"
          >
            Move to
          </VTooltip>

          <VMenu activator="parent">
            <VList density="compact">
              <template
                v-for="moveTo in emailMoveToFolderActions"
                :key="moveTo.title"
              >
                <VListItem
                  :class="shallShowMoveToActionFor(moveTo.action) ? 'd-flex' : 'd-none'"
                  class="align-center"
                  href="#"
                  @click="handleMoveMailsTo(moveTo.action)"
                >
                  <template #prepend>
                    <VIcon
                      :icon="moveTo.icon"
                      class="me-2"
                      size="20"
                    />
                  </template>
                  <VListItemTitle class="text-capitalize">
                    {{ moveTo.action }}
                  </VListItemTitle>
                </VListItem>
              </template>
            </VList>
          </VMenu>
        </IconBtn>

        <!-- Update labels -->
        <IconBtn>
          <VIcon
            icon="tabler-tag"
            size="22"
          />
          <VTooltip
            activator="parent"
            location="top"
          >
            Label
          </VTooltip>

          <VMenu activator="parent">
            <VList density="compact">
              <VListItem
                v-for="label in labels"
                :key="label.title"
                href="#"
                @click.stop="updateMailLabel(label.title)"
              >
                <template #prepend>
                  <VBadge
                    inline
                    :color="resolveLabelColor(label.title)"
                    dot
                  />
                </template>
                <VListItemTitle class="ms-2 text-capitalize">
                  {{ label.title }}
                </VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </IconBtn>

        <VSpacer />

        <div class="d-flex align-center gap-x-1">
          <!-- Star/Unstar -->
          <IconBtn
            :color="props.email.isStarred ? 'warning' : 'default'"
            @click="props.email?.isStarred ? $emit('unstar') : $emit('star'); $emit('refresh')"
          >
            <VIcon icon="tabler-star" />
          </IconBtn>
        </div>
      </div>

      <VDivider />

      <!-- 👉 Mail Content -->
      <PerfectScrollbar
        tag="div"
        class="mail-content-container flex-grow-1 pa-sm-6 pa-4"
        :options="{ wheelPropagation: false }"
      >
        <VCard class="mb-4">
          <div class="d-flex align-start align-sm-center pa-4 gap-x-3">
            <VAvatar
              size="38"
              color="primary"
              variant="tonal"
            >
              <VIcon
                icon="tabler-user"
                size="20"
              />
            </VAvatar>

            <div class="d-flex flex-wrap flex-grow-1 overflow-hidden">
              <div class="text-truncate">
                <div class="text-body-1 font-weight-bold text-high-emphasis text-truncate">
                  {{ props.email.from.name }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  &lt;{{ props.email.from.email }}&gt;
                </div>
              </div>

              <VSpacer />

              <div class="d-flex align-center gap-x-2">
                <div class="text-caption text-disabled">
                  {{ new Date(props.email.time).toLocaleDateString() }}
                </div>
              </div>
            </div>
          </div>

          <VDivider />

          <VCardText>
            <!-- eslint-disable vue/no-v-html -->
            <div
              class="text-body-2"
              v-html="props.email.message"
            />
            <!-- eslint-enable vue/no-v-html -->
          </VCardText>

          <!-- Attachments -->
          <template v-if="props.email.attachments?.length">
            <VDivider />

            <VCardText class="d-flex flex-column gap-y-2 pt-3">
              <div class="text-caption font-weight-bold text-medium-emphasis">
                {{ props.email.attachments.length }} Attachment(s)
              </div>
              <div class="d-flex flex-wrap gap-2">
                <VChip
                  v-for="attachment in props.email.attachments"
                  :key="attachment.fileName"
                  size="small"
                  variant="outlined"
                  class="gap-1"
                >
                  <VAvatar
                    size="20"
                    :color="resolveFileColor(attachment.mime_type, attachment.fileName)"
                    variant="tonal"
                  >
                    <VIcon
                      :icon="resolveFileIcon(attachment.mime_type, attachment.fileName)"
                      size="12"
                    />
                  </VAvatar>
                  <span
                    class="text-truncate"
                    style="max-inline-size: 140px;"
                  >{{ attachment.fileName }}</span>
                  <span
                    v-if="attachment.size"
                    class="text-caption text-medium-emphasis"
                  >({{ formatDocumentBytes(attachment.size) }})</span>
                </VChip>
              </div>
            </VCardText>
          </template>
        </VCard>

        <!-- 👉 Associated Business Records -->
        <VCard
          v-if="props.email?.conversationId"
          class="mb-4"
        >
          <VCardItem class="py-2 px-4 bg-var-theme-background">
            <div class="d-flex align-center justify-space-between flex-wrap gap-2">
              <span class="text-caption font-weight-bold text-uppercase">
                <VIcon
                  start
                  icon="tabler-paperclip"
                  size="14"
                />
                Associated Business Records ({{ linkedRecords.filter(l => l.is_confirmed).length }})
              </span>
              <VBtn
                size="x-small"
                variant="text"
                color="primary"
                prepend-icon="tabler-plus"
                @click="showLinkDialog = true"
              >
                Link Record
              </VBtn>
            </div>
          </VCardItem>

          <VCardText class="pa-3">
            <!-- Records List -->
            <div
              v-if="linkedRecords.length > 0"
              class="d-flex flex-wrap gap-2"
            >
              <template
                v-for="link in linkedRecords"
                :key="link.id"
              >
                <VChip
                  v-if="link.is_confirmed"
                  size="small"
                  variant="outlined"
                  color="primary"
                  :to="link.record_url"
                >
                  <VIcon
                    start
                    icon="tabler-link"
                    size="14"
                  />
                  <span class="text-capitalize">{{ link.record_type }}:</span>
                  <span class="font-weight-bold ms-1">{{ link.record_reference }}</span>
                </VChip>

                <!-- Suggested Link Banner -->
                <VChip
                  v-else
                  size="small"
                  variant="tonal"
                  color="warning"
                >
                  <VIcon
                    start
                    icon="tabler-bulb"
                    size="14"
                  />
                  <span>Suggested: {{ link.record_type }} {{ link.record_reference }}</span>
                  <VBtn
                    icon="tabler-check"
                    size="x-small"
                    variant="text"
                    color="success"
                    class="ms-1"
                    title="Confirm Association"
                    @click="confirmLink(link.id)"
                  />
                  <VBtn
                    icon="tabler-x"
                    size="x-small"
                    variant="text"
                    color="error"
                    title="Dismiss Suggestion"
                    @click="dismissLink(link.id)"
                  />
                </VChip>
              </template>
            </div>
            <div
              v-else
              class="text-caption text-medium-emphasis"
            >
              No business records linked to this conversation yet.
            </div>
          </VCardText>
        </VCard>

        <!-- 👉 Internal Team Notes (Shared Inbox Collaboration) -->
        <VCard
          v-if="conversationData"
          class="mb-4"
        >
          <VCardItem class="py-2 px-4 bg-var-theme-background">
            <div class="d-flex align-center justify-space-between">
              <span class="text-caption font-weight-bold text-uppercase">
                <VIcon
                  start
                  icon="tabler-notes"
                  size="14"
                />
                Internal Team Notes ({{ internalNotes.length }})
              </span>
            </div>
          </VCardItem>

          <VCardText class="pa-4">
            <!-- Notes List -->
            <div
              v-if="internalNotes.length > 0"
              class="d-flex flex-column gap-2 mb-3"
            >
              <div
                v-for="note in internalNotes"
                :key="note.id"
                class="pa-2 rounded border bg-surface"
              >
                <div class="d-flex align-center justify-space-between text-caption text-medium-emphasis mb-1">
                  <span class="font-weight-medium">{{ note.author?.name || 'Team Member' }}</span>
                  <span>{{ new Date(note.created_at).toLocaleString() }}</span>
                </div>
                <div class="text-body-2">
                  {{ note.content }}
                </div>
              </div>
            </div>
            <div
              v-else
              class="text-caption text-medium-emphasis mb-3"
            >
              No internal notes yet. Leave a note for your team members below.
            </div>

            <!-- Add Note Input -->
            <div class="d-flex gap-2 align-end">
              <VTextarea
                v-model="newNoteContent"
                placeholder="Write an internal note..."
                rows="2"
                density="compact"
                variant="outlined"
                hide-details
                no-resize
              />
              <VBtn
                color="secondary"
                size="small"
                :loading="isAddingNote"
                :disabled="!newNoteContent.trim()"
                @click="submitNote"
              >
                Post
              </VBtn>
            </div>
          </VCardText>
        </VCard>

        <!-- 👉 Reply or Forward Box -->
        <VCard
          v-show="showReplyCard"
          class="mb-4"
        >
          <VCardText class="font-weight-medium text-high-emphasis">
            <div class="text-base">
              Click here to <span
                class="text-primary cursor-pointer font-weight-bold"
                @click="showReplyBox = true; showReplyCard = false"
              >
                Reply
              </span>
            </div>
          </VCardText>
        </VCard>

        <VCard
          v-if="showReplyBox"
          class="mb-4"
        >
          <VCardText>
            <h6 class="text-subtitle-2 font-weight-bold mb-3">
              Reply to {{ email?.from.name }} &lt;{{ email?.from.email }}&gt;
            </h6>
            <VTextarea
              v-model="emailReply"
              placeholder="Write your reply message..."
              rows="4"
              variant="outlined"
              class="mb-3"
            />
            <div class="d-flex justify-end gap-2 pt-2 flex-wrap">
              <VBtn
                variant="text"
                color="secondary"
                size="small"
                @click="showReplyBox = false; showReplyCard = true; emailReply = ''"
              >
                Cancel
              </VBtn>
              <VBtn
                append-icon="tabler-send"
                color="primary"
                size="small"
                :loading="isSendingReply"
                :disabled="!emailReply.trim()"
                @click="sendReply"
              >
                Send Reply
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </PerfectScrollbar>

      <!-- 👉 Link Business Record Dialog -->
      <VDialog
        v-model="showLinkDialog"
        max-width="500"
      >
        <VCard>
          <VCardItem class="pb-2">
            <div class="d-flex align-center justify-space-between">
              <h6 class="text-h6 font-weight-bold">
                Link Business Record
              </h6>
              <VBtn
                icon="tabler-x"
                variant="text"
                size="small"
                @click="showLinkDialog = false"
              />
            </div>
          </VCardItem>

          <VDivider />

          <VCardText class="pa-4">
            <div class="d-flex flex-column gap-3">
              <VSelect
                v-model="newLinkType"
                :items="supportedRecordTypes"
                label="Record Type"
                density="compact"
                variant="outlined"
              />

              <VTextField
                v-model="newLinkId"
                label="Record ID or Reference"
                placeholder="Enter record ID (e.g. 1, 102, or UUID)"
                density="compact"
                variant="outlined"
              />

              <VTextField
                v-model="newLinkReason"
                label="Link Note (Optional)"
                placeholder="e.g. Discussing project timeline"
                density="compact"
                variant="outlined"
              />
            </div>
          </VCardText>

          <VCardActions class="pa-4 pt-0">
            <VSpacer />
            <VBtn
              variant="text"
              color="secondary"
              @click="showLinkDialog = false"
            >
              Cancel
            </VBtn>
            <VBtn
              color="primary"
              :disabled="!newLinkId.trim()"
              :loading="isLinkingRecord"
              @click="submitNewLink"
            >
              Link
            </VBtn>
          </VCardActions>
        </VCard>
      </VDialog>
    </template>
  </VNavigationDrawer>
</template>

<style lang="scss">
.email-view {
  inline-size: 100% !important;

  @media only screen and (min-width: 1280px) {
    inline-size: calc(100% - 256px) !important;
  }

  .mail-content-container {
    block-size: calc(100% - 100px);
  }
}
</style>
