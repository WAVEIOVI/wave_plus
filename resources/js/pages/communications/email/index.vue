<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { refDebounced } from '@vueuse/core'
import { useDisplay } from 'vuetify'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useResponsiveLeftSidebar } from '@core/composable/useResponsiveSidebar'
import { avatarText, formatDateToMonthShort } from '@core/utils/formatters'
import { $api } from '@/utils/api'
import { WpEmptyState } from '@/components/shared'
import ComposeDialog from '@/views/communications/email/ComposeDialog.vue'
import EmailLeftSidebarContent from '@/views/communications/email/EmailLeftSidebarContent.vue'
import EmailView from '@/views/communications/email/EmailView.vue'
import { useEmail } from '@/views/communications/email/useEmail'

definePage({
  path: '/communications/email/:filter?',
  name: 'communications-email',
  meta: {
    layout: 'default',
    module: 'communications',
    action: 'read',
    subject: 'Communications',
    layoutWrapperClasses: 'layout-content-height-fixed',
  },
})

const route = useRoute()
const { mdAndDown } = useDisplay()
const { isLeftSidebarOpen } = useResponsiveLeftSidebar()

// Composables
const {
  labels,
  resolveLabelColor,
  emailMoveToFolderActions,
  shallShowMoveToActionFor,
  moveSelectedEmailTo,
  updateEmails,
  updateEmailLabels,
} = useEmail()

// Compose dialog
const isComposeDialogVisible = ref(false)
const activeDraftId = ref(null)

const openComposer = (draftId = null) => {
  const isId = (typeof draftId === 'string' || typeof draftId === 'number') &&
    String(draftId).trim() !== '' &&
    !String(draftId).includes('PointerEvent') &&
    !String(draftId).includes('MouseEvent') &&
    !String(draftId).includes('[object')

  activeDraftId.value = isId ? String(draftId).trim() : null
  isComposeDialogVisible.value = true
}

// Search & Selection
const q = ref('')
const debouncedQ = refDebounced(q, 300)
const selectedEmails = ref([])

// Email Data State
const emails = ref([])

const emailsMeta = ref({
  inbox: 0,
  assigned: 0,
  sent: 0,
  draft: 0,
  scheduled: 0,
  star: 0,
  spam: 0,
  trash: 0,
  archived: 0,
  unanswered: 0,
  pending_replies: 0,
  unassigned: 0,
  recent_activity: 0,
})

const isLoading = ref(false)
const error = ref(null)

// Current folder & configuration for empty state & header
const currentFolder = computed(() => {
  return route.params?.filter || route.query?.filter || 'inbox'
})

const folderConfig = computed(() => {
  const f = currentFolder.value
  switch (f) {
  case 'sent':
    return {
      title: 'Sent',
      emptyTitle: 'No sent messages yet',
      emptySubtitle: 'Outgoing emails and replies sent from company mailboxes will appear here.',
      icon: 'tabler-send',
      actionLabel: 'Compose Email',
      showCompose: true,
    }
  case 'draft':
    return {
      title: 'Drafts',
      emptyTitle: 'No draft messages',
      emptySubtitle: 'Drafts you save will be kept here until you are ready to send them.',
      icon: 'tabler-edit',
      actionLabel: 'New Draft',
      showCompose: true,
    }
  case 'scheduled':
    return {
      title: 'Scheduled',
      emptyTitle: 'No scheduled emails',
      emptySubtitle: 'Messages queued for future dispatch will wait here.',
      icon: 'tabler-clock',
      actionLabel: 'Schedule Email',
      showCompose: true,
    }
  case 'starred':
    return {
      title: 'Starred',
      emptyTitle: 'No starred messages',
      emptySubtitle: 'Star critical conversations to keep them within easy reach.',
      icon: 'tabler-star',
      actionLabel: null,
      showCompose: false,
    }
  case 'spam':
    return {
      title: 'Spam',
      emptyTitle: 'Spam is clean',
      emptySubtitle: 'No flagged or suspicious messages have been found.',
      icon: 'tabler-alert-circle',
      actionLabel: null,
      showCompose: false,
    }
  case 'trashed':
  case 'trash':
    return {
      title: 'Trash',
      emptyTitle: 'Trash is empty',
      emptySubtitle: 'Deleted emails will be held here before permanent removal.',
      icon: 'tabler-trash',
      actionLabel: null,
      showCompose: false,
    }
  case 'archived':
    return {
      title: 'Archived',
      emptyTitle: 'No archived emails',
      emptySubtitle: 'Completed conversations archived for safekeeping will show up here.',
      icon: 'tabler-archive',
      actionLabel: null,
      showCompose: false,
    }
  case 'assigned':
    return {
      title: 'Assigned to Me',
      emptyTitle: 'No conversations assigned',
      emptySubtitle: 'Messages assigned to you by teammates or automation will appear here.',
      icon: 'tabler-user-check',
      actionLabel: null,
      showCompose: false,
    }
  case 'unanswered':
    return {
      title: 'Unanswered',
      emptyTitle: 'All caught up!',
      emptySubtitle: 'No customer messages are currently waiting for a first reply.',
      icon: 'tabler-message-question',
      actionLabel: null,
      showCompose: false,
    }
  case 'pending_replies':
    return {
      title: 'Pending Replies',
      emptyTitle: 'No pending replies',
      emptySubtitle: 'All follow-up threads have been answered.',
      icon: 'tabler-clock-hour-4',
      actionLabel: null,
      showCompose: false,
    }
  case 'unassigned':
    return {
      title: 'Unassigned',
      emptyTitle: 'No unassigned conversations',
      emptySubtitle: 'Every active conversation is assigned to an employee or department.',
      icon: 'tabler-user-off',
      actionLabel: null,
      showCompose: false,
    }
  case 'recent_activity':
    return {
      title: 'Recent Activity',
      emptyTitle: 'No recent activity',
      emptySubtitle: 'Live communication updates will appear here in chronological order.',
      icon: 'tabler-history',
      actionLabel: null,
      showCompose: false,
    }
  case 'inbox':
  default:
    return {
      title: 'Inbox',
      emptyTitle: 'Your inbox is clear',
      emptySubtitle: 'There are no unread or pending emails in this mailbox right now.',
      icon: 'tabler-inbox',
      actionLabel: 'Compose Email',
      showCompose: true,
    }
  }
})

// Email View (detail overlay)
const openedEmail = ref(null)

const fetchEmails = async () => {
  isLoading.value = true
  error.value = null
  try {
    const filterParam = route.params?.filter || route.query?.filter || 'inbox'
    const labelParam = route.params?.label || route.query?.label || undefined
    const mailboxId = route.query?.mailbox_id

    const res = await $api('/communications/email/messages', {
      method: 'GET',
      params: {
        q: q.value?.trim() || undefined,
        filter: filterParam,
        label: labelParam,
        mailbox_id: mailboxId,
      },
    })

    emails.value = Array.isArray(res?.emails) ? res.emails : []
    emailsMeta.value = res?.emailsMeta || {}
  } catch (err) {
    console.error('Failed to fetch emails', err)
    error.value = err?.message || 'Failed to load emails'
    emails.value = []
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchEmails()
})

watch(debouncedQ, () => {
  fetchEmails()
})

watch(
  [() => route.params.filter, () => route.query.filter, () => route.query.label, () => route.query.mailbox_id],
  () => {
    selectedEmails.value = []
    openedEmail.value = null
    if (mdAndDown.value) {
      isLeftSidebarOpen.value = false
    }
    fetchEmails()
  },
)

const toggleSelectedEmail = emailId => {
  const emailIndex = selectedEmails.value.indexOf(emailId)
  if (emailIndex === -1)
    selectedEmails.value.push(emailId)
  else
    selectedEmails.value.splice(emailIndex, 1)
}

const selectAllEmailCheckbox = computed(() => emails.value.length > 0 && emails.value.length === selectedEmails.value.length)
const isSelectAllEmailCheckboxIndeterminate = computed(() => Boolean(selectedEmails.value.length) && emails.value.length !== selectedEmails.value.length)

const isAllMarkRead = computed(() => {
  if (!selectedEmails.value.length)
    return false

  return selectedEmails.value.every(emailId => emails.value.find(email => email.id === emailId)?.isRead)
})

const selectAllCheckboxUpdate = () => {
  selectedEmails.value = !selectAllEmailCheckbox.value ? emails.value.map(email => email.id) : []
}

const emailViewMeta = computed(() => {
  const returnValue = {
    hasNextEmail: false,
    hasPreviousEmail: false,
  }

  if (openedEmail.value && emails.value.length) {
    const openedEmailIndex = emails.value.findIndex(e => e.id === openedEmail.value?.id)
    if (openedEmailIndex !== -1) {
      returnValue.hasNextEmail = Boolean(emails.value[openedEmailIndex + 1])
      returnValue.hasPreviousEmail = Boolean(emails.value[openedEmailIndex - 1])
    }
  }

  return returnValue
})

const refreshOpenedEmail = async () => {
  if (!openedEmail.value)
    return
  const currentId = openedEmail.value.id

  await fetchEmails()
  openedEmail.value = emails.value.find(e => e.id === currentId) || openedEmail.value
}

const handleActionClick = async (action, emailIds = selectedEmails.value) => {
  if (!emailIds.length)
    return

  // Optimistic UI updates
  if (action === 'trash') {
    emails.value = emails.value.filter(e => !emailIds.includes(e.id))
    await updateEmails(emailIds, { isDeleted: true, folder: 'trash' })
  } else if (action === 'spam') {
    emails.value = emails.value.filter(e => !emailIds.includes(e.id))
    await updateEmails(emailIds, { folder: 'spam' })
  } else if (action === 'unread') {
    emails.value.forEach(e => {
      if (emailIds.includes(e.id)) e.isRead = false
    })
    await updateEmails(emailIds, { isRead: false })
  } else if (action === 'read') {
    emails.value.forEach(e => {
      if (emailIds.includes(e.id)) e.isRead = true
    })
    await updateEmails(emailIds, { isRead: true })
  } else if (action === 'star') {
    emails.value.forEach(e => {
      if (emailIds.includes(e.id)) e.isStarred = true
    })
    await updateEmails(emailIds, { isStarred: true })
  } else if (action === 'unstar') {
    emails.value.forEach(e => {
      if (emailIds.includes(e.id)) e.isStarred = false
    })
    await updateEmails(emailIds, { isStarred: false })
  }

  selectedEmails.value = selectedEmails.value.filter(id => !emailIds.includes(id))

  if (openedEmail.value) {
    if (action === 'trash' && emailIds.includes(openedEmail.value.id)) {
      openedEmail.value = null
    } else {
      await refreshOpenedEmail()
    }
  } else {
    await fetchEmails()
  }
}

const handleMoveMailsTo = async action => {
  if (!selectedEmails.value.length)
    return
  const ids = [...selectedEmails.value]

  selectedEmails.value = []
  await moveSelectedEmailTo(action, ids)
  await fetchEmails()
}

const handleEmailLabels = async labelTitle => {
  if (!selectedEmails.value.length)
    return
  const ids = [...selectedEmails.value]

  selectedEmails.value = []
  await updateEmailLabels(ids, labelTitle)
  await fetchEmails()
}

const changeOpenedEmail = dir => {
  if (!openedEmail.value || !emails.value.length)
    return
  const openedEmailIndex = emails.value.findIndex(e => e.id === openedEmail.value?.id)
  if (openedEmailIndex === -1)
    return
  const newEmailIndex = dir === 'previous' ? openedEmailIndex - 1 : openedEmailIndex + 1
  if (emails.value[newEmailIndex]) {
    openedEmail.value = emails.value[newEmailIndex]
    if (!openedEmail.value.isRead) {
      handleActionClick('read', [openedEmail.value.id])
    }
  }
}

const openEmail = async email => {
  if (!email)
    return
  if (email.status === 'draft' || email.folder === 'draft' || route.params.filter === 'draft' || route.query.filter === 'draft') {
    openComposer(email.id)

    return
  }
  openedEmail.value = email
  if (!email.isRead) {
    await handleActionClick('read', [email.id])
  }
}
</script>

<template>
  <VLayout
    style="z-index: 0; min-block-size: 100%;"
    class="email-app-layout"
  >
    <VNavigationDrawer
      v-model="isLeftSidebarOpen"
      data-allow-mismatch
      absolute
      touchless
      location="start"
      :temporary="$vuetify.display.mdAndDown"
    >
      <EmailLeftSidebarContent
        :emails-meta="emailsMeta"
        @toggle-compose-dialog-visibility="() => openComposer()"
      />
    </VNavigationDrawer>
    <EmailView
      v-if="openedEmail"
      :email="openedEmail"
      :email-meta="emailViewMeta"
      @refresh="refreshOpenedEmail"
      @navigated="changeOpenedEmail"
      @close="openedEmail = null"
      @trash="handleActionClick('trash', openedEmail ? [openedEmail.id] : [])"
      @unread="handleActionClick('unread', openedEmail ? [openedEmail.id] : [])"
      @star="handleActionClick('star', openedEmail ? [openedEmail.id] : [])"
      @unstar="handleActionClick('unstar', openedEmail ? [openedEmail.id] : [])"
    />
    <VMain>
      <VCard
        flat
        class="email-content-list h-100 d-flex flex-column"
      >
        <!-- 👉 Search & Folder Header -->
        <div class="d-flex align-center px-4 py-2 border-b">
          <IconBtn
            class="d-lg-none me-2"
            @click="isLeftSidebarOpen = true"
          >
            <VIcon icon="tabler-menu-2" />
          </IconBtn>

          <!-- Current Folder Title & Badge -->
          <div class="d-flex align-center gap-2 me-4 flex-shrink-0">
            <VIcon
              :icon="folderConfig.icon"
              size="20"
              color="primary"
            />
            <span class="text-subtitle-1 font-weight-semibold text-high-emphasis">
              {{ folderConfig.title }}
            </span>
            <VChip
              v-if="emails.length"
              size="x-small"
              color="primary"
              variant="tonal"
            >
              {{ emails.length }}
            </VChip>
          </div>

          <!-- 👉 Search -->
          <VTextField
            v-model="q"
            density="compact"
            class="email-search flex-grow-1"
            placeholder="Search mail by sender, recipient, subject, or content..."
            clearable
            hide-details
          >
            <template #prepend-inner>
              <VIcon
                icon="tabler-search"
                size="20"
                class="me-1 text-medium-emphasis"
              />
            </template>
          </VTextField>
        </div>

        <!-- 👉 Action bar -->
        <div class="py-2 px-4 d-flex align-center border-b gap-x-2">
          <VCheckbox
            :model-value="selectAllEmailCheckbox"
            :indeterminate="isSelectAllEmailCheckboxIndeterminate"
            density="compact"
            hide-details
            @update:model-value="selectAllCheckboxUpdate"
          />

          <!-- Staged / Bulk selection actions -->
          <template v-if="selectedEmails.length > 0">
            <VChip
              size="small"
              color="primary"
              variant="tonal"
              class="font-weight-medium"
            >
              {{ selectedEmails.length }} selected
            </VChip>

            <!-- Trash -->
            <IconBtn
              v-show="currentFolder !== 'trashed'"
              @click="handleActionClick('trash')"
            >
              <VIcon
                icon="tabler-trash"
                size="20"
              />
              <VTooltip
                activator="parent"
                location="top"
              >
                Delete Mail
              </VTooltip>
            </IconBtn>

            <!-- Mark unread/read -->
            <IconBtn @click="isAllMarkRead ? handleActionClick('unread') : handleActionClick('read')">
              <VIcon
                :icon="isAllMarkRead ? 'tabler-mail' : 'tabler-mail-opened'"
                size="20"
              />
              <VTooltip
                activator="parent"
                location="top"
              >
                {{ isAllMarkRead ? 'Mark as Unread' : 'Mark as Read' }}
              </VTooltip>
            </IconBtn>

            <!-- Move to folder -->
            <IconBtn>
              <VIcon
                icon="tabler-folder"
                size="20"
              />
              <VTooltip
                activator="parent"
                location="top"
              >
                Move to Folder
              </VTooltip>
              <VMenu activator="parent">
                <VList density="compact">
                  <template
                    v-for="moveTo in emailMoveToFolderActions"
                    :key="moveTo.action"
                  >
                    <VListItem
                      v-if="shallShowMoveToActionFor(moveTo.action)"
                      @click="handleMoveMailsTo(moveTo.action)"
                    >
                      <template #prepend>
                        <VIcon
                          :icon="moveTo.icon"
                          class="me-2"
                          size="18"
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
                size="20"
              />
              <VTooltip
                activator="parent"
                location="top"
              >
                Apply Label
              </VTooltip>
              <VMenu activator="parent">
                <VList density="compact">
                  <VListItem
                    v-for="label in labels"
                    :key="label.title"
                    @click="handleEmailLabels(label.title)"
                  >
                    <template #prepend>
                      <VBadge
                        inline
                        :color="resolveLabelColor(label.title)"
                        dot
                        class="me-2"
                      />
                    </template>
                    <VListItemTitle class="text-capitalize">
                      {{ label.title }}
                    </VListItemTitle>
                  </VListItem>
                </VList>
              </VMenu>
            </IconBtn>
          </template>

          <VSpacer />

          <!-- Right side shortcuts -->
          <div class="d-flex align-center gap-1">
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-edit"
              class="d-none d-sm-inline-flex"
              @click="() => openComposer()"
            >
              Compose
            </VBtn>

            <IconBtn
              :loading="isLoading"
              @click="fetchEmails"
            >
              <VIcon
                icon="tabler-refresh"
                size="20"
              />
              <VTooltip
                activator="parent"
                location="top"
              >
                Refresh
              </VTooltip>
            </IconBtn>
          </div>
        </div>

        <!-- 👉 Body content states -->
        <!-- Loading State -->
        <div
          v-if="isLoading"
          class="d-flex flex-column align-center justify-center py-16 flex-grow-1"
        >
          <VProgressCircular
            indeterminate
            color="primary"
            size="44"
            width="3"
            class="mb-3"
          />
          <div class="text-caption text-medium-emphasis font-weight-medium">
            Loading emails...
          </div>
        </div>

        <!-- Error State -->
        <div
          v-else-if="error"
          class="d-flex align-center justify-center flex-grow-1 pa-6"
        >
          <WpEmptyState
            icon="tabler-alert-triangle"
            title="Failed to load emails"
            :subtitle="error"
          >
            <template #action>
              <VBtn
                variant="tonal"
                color="primary"
                prepend-icon="tabler-refresh"
                @click="fetchEmails"
              >
                Retry
              </VBtn>
            </template>
          </WpEmptyState>
        </div>

        <!-- Empty State (WAVE PLUS Concept using WpEmptyState) -->
        <div
          v-else-if="!emails.length"
          class="d-flex align-center justify-center flex-grow-1 pa-6"
        >
          <WpEmptyState
            :icon="q ? 'tabler-search-off' : folderConfig.icon"
            :title="q ? 'No matching emails found' : folderConfig.emptyTitle"
            :subtitle="q ? `No emails match &quot;${q}&quot;. Try different keywords.` : folderConfig.emptySubtitle"
          >
            <template #action>
              <div class="d-flex align-center flex-wrap justify-center gap-2">
                <VBtn
                  v-if="q"
                  variant="tonal"
                  color="primary"
                  prepend-icon="tabler-x"
                  @click="q = ''"
                >
                  Clear search
                </VBtn>
                <VBtn
                  v-else-if="folderConfig.showCompose"
                  variant="elevated"
                  color="primary"
                  prepend-icon="tabler-edit"
                  @click="() => openComposer()"
                >
                  {{ folderConfig.actionLabel }}
                </VBtn>
                <VBtn
                  variant="outlined"
                  color="secondary"
                  prepend-icon="tabler-refresh"
                  @click="fetchEmails"
                >
                  Refresh
                </VBtn>
              </div>
            </template>
          </WpEmptyState>
        </div>

        <!-- 👉 Emails list -->
        <PerfectScrollbar
          v-else
          tag="ul"
          :options="{ wheelPropagation: false }"
          class="email-list flex-grow-1"
        >
          <li
            v-for="email in emails"
            :key="email.id"
            class="email-item d-flex align-center pa-4 gap-2 cursor-pointer"
            :class="[{ 'email-read': email.isRead }]"
            @click="openEmail(email)"
          >
            <VCheckbox
              :model-value="selectedEmails.includes(email.id)"
              class="flex-shrink-0"
              @update:model-value="toggleSelectedEmail(email.id)"
              @click.stop
            />
            <IconBtn
              :color="email.isStarred ? 'warning' : 'default'"
              @click.stop="handleActionClick(email.isStarred ? 'unstar' : 'star', [email.id])"
            >
              <VIcon
                :icon="email.isStarred ? 'tabler-star-filled' : 'tabler-star'"
                size="22"
              />
            </IconBtn>
            <VAvatar
              size="32"
              color="primary"
              variant="tonal"
              class="flex-shrink-0"
            >
              <VImg
                v-if="email.from && email.from.avatar"
                :src="email.from.avatar"
                :alt="email.from.name"
              />
              <span
                v-else
                class="text-xs font-weight-medium"
              >
                {{ avatarText(email.from?.name || email.from?.email || '?') }}
              </span>
            </VAvatar>
            <h6
              class="text-h6 text-truncate font-weight-medium"
              style="max-inline-size: 180px;"
            >
              {{ email.from?.name || email.from?.email || 'Unknown' }}
            </h6>
            <span class="text-body-2 truncate flex-grow-1">{{ email.subject || '(No Subject)' }}</span>

            <VSpacer />

            <!-- 👉 Email meta -->
            <div
              class="email-meta align-center gap-2"
              :class="$vuetify.display.xs ? 'd-none' : ''"
            >
              <VIcon
                v-if="email.hasAttachments || (email.attachments && email.attachments.length)"
                icon="tabler-paperclip"
                size="16"
                class="text-medium-emphasis"
              />

              <VIcon
                v-for="label in email.labels"
                :key="label"
                icon="tabler-circle-filled"
                size="10"
                :color="resolveLabelColor(label)"
              />

              <span class="text-sm text-disabled">
                {{ formatDateToMonthShort(email.time) }}
              </span>
            </div>
            <!-- 👉 Email actions -->
            <div class="email-actions d-none">
              <IconBtn @click.stop="handleActionClick('trash', [email.id])">
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
              <IconBtn
                class="mx-2"
                @click.stop="handleActionClick(email.isRead ? 'unread' : 'read', [email.id])"
              >
                <VIcon
                  :icon="email.isRead ? 'tabler-mail' : 'tabler-mail-opened'"
                  size="22"
                />
                <VTooltip
                  activator="parent"
                  location="top"
                >
                  {{ email.isRead ? 'Mark as Unread' : 'Mark as Read' }}
                </VTooltip>
              </IconBtn>
              <IconBtn @click.stop="handleActionClick('spam', [email.id])">
                <VIcon
                  icon="tabler-info-circle"
                  size="22"
                />
                <VTooltip
                  activator="parent"
                  location="top"
                >
                  Move to Spam
                </VTooltip>
              </IconBtn>
            </div>
          </li>
        </PerfectScrollbar>
      </VCard>
      <ComposeDialog
        v-model="isComposeDialogVisible"
        :draft-id="activeDraftId"
        @close="isComposeDialogVisible = false; activeDraftId = null"
        @sent="fetchEmails"
        @draft-saved="fetchEmails"
      />
    </VMain>
  </VLayout>
</template>

<style lang="scss">
@use "@styles/variables/vuetify";
@use "@core-scss/base/mixins";

// ℹ️ Remove border. Using variant plain cause UI issue, caret isn't align in center
.email-search {
  .v-field__outline {
    display: none;
  }

  .v-field__field {
    .v-field__input {
      font-size: 0.9375rem !important;
      line-height: 1.375rem !important;
    }
  }
}

.email-app-layout {
  position: relative;
  overflow: hidden;
  border-radius: vuetify.$card-border-radius;

  @include mixins.elevation(vuetify.$card-elevation);

  $sel-email-app-layout: &;

  @at-root {
    .skin--bordered {
      @include mixins.bordered-skin($sel-email-app-layout);
    }
  }
}

.email-empty-state {
  white-space: normal !important;
  list-style: none;
  min-block-size: 320px;
}

.email-content-list {
  border-end-start-radius: 0;
  border-start-start-radius: 0;
}

.email-list {
  white-space: nowrap;

  .email-item {
    block-size: 4.375rem;
    transition: all 0.2s ease-in-out;
    will-change: transform, box-shadow;

    &.email-read {
      background-color: rgba(var(--v-theme-on-surface), var(--v-hover-opacity));
    }

    & + .email-item {
      border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    }
  }

  .email-item .email-meta {
    display: flex;
  }

  .email-item:hover {
    transform: translateY(-2px);

    @include mixins.elevation(4);

    // ℹ️ Don't show actions on hover on mobile & tablet devices
    @media screen and (min-width: 1280px) {
      .email-actions {
        display: block !important;
      }

      .email-meta {
        display: none;
      }
    }

    + .email-item {
      border-color: transparent;
    }

    @media screen and (max-width: 600px) {
      .email-actions {
        display: none !important;
      }
    }
  }
}
</style>
