<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { $api } from '@/utils/api'

const props = defineProps({
  emailsMeta: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['toggleComposeDialogVisibility'])

defineOptions({
  inheritAttrs: false,
})

const inboxEmails = ref(0)
const assignedEmails = ref(0)
const sentEmails = ref(0)
const draftEmails = ref(0)
const scheduledEmails = ref(0)
const spamEmails = ref(0)
const starredEmails = ref(0)
const trashEmails = ref(0)
const archivedEmails = ref(0)
const unansweredEmails = ref(0)
const pendingRepliesEmails = ref(0)
const unassignedEmails = ref(0)
const recentActivityEmails = ref(0)

const sharedMailboxes = ref([])
const isMailboxesLoading = ref(false)

watch(() => props.emailsMeta, emailsMeta => {
  if (!emailsMeta)
    return
  inboxEmails.value = emailsMeta.inbox || 0
  assignedEmails.value = emailsMeta.assigned || 0
  sentEmails.value = emailsMeta.sent || 0
  draftEmails.value = emailsMeta.draft || 0
  scheduledEmails.value = emailsMeta.scheduled || 0
  spamEmails.value = emailsMeta.spam || 0
  starredEmails.value = emailsMeta.star || 0
  trashEmails.value = emailsMeta.trash || 0
  archivedEmails.value = emailsMeta.archived || 0
  unansweredEmails.value = emailsMeta.unanswered || 0
  pendingRepliesEmails.value = emailsMeta.pending_replies || 0
  unassignedEmails.value = emailsMeta.unassigned || 0
  recentActivityEmails.value = emailsMeta.recent_activity || 0
}, {
  immediate: true,
  deep: true,
})

onMounted(async () => {
  isMailboxesLoading.value = true
  try {
    const res = await $api('/communications/email/mailboxes')

    sharedMailboxes.value = res.data || []
  } catch (err) {
    console.error('Failed to load shared mailboxes', err)
  } finally {
    isMailboxesLoading.value = false
  }
})

const folders = computed(() => [
  {
    title: 'Inbox',
    prependIcon: 'tabler-mail',
    to: { name: 'communications-email' },
    badge: {
      content: inboxEmails.value,
      color: 'primary',
    },
  },
  {
    title: 'Assigned to Me',
    prependIcon: 'tabler-user-check',
    to: {
      name: 'communications-email',
      params: { filter: 'assigned' },
    },
    badge: {
      content: assignedEmails.value,
      color: 'info',
    },
  },
  {
    title: 'Sent',
    prependIcon: 'tabler-send',
    to: {
      name: 'communications-email',
      params: { filter: 'sent' },
    },
  },
  {
    title: 'Draft',
    prependIcon: 'tabler-edit',
    to: {
      name: 'communications-email',
      params: { filter: 'draft' },
    },
    badge: {
      content: draftEmails.value,
      color: 'warning',
    },
  },
  {
    title: 'Scheduled',
    prependIcon: 'tabler-clock',
    to: {
      name: 'communications-email',
      params: { filter: 'scheduled' },
    },
    badge: {
      content: scheduledEmails.value,
      color: 'secondary',
    },
  },
  {
    title: 'Starred',
    prependIcon: 'tabler-star',
    to: {
      name: 'communications-email',
      params: { filter: 'starred' },
    },
    badge: {
      content: starredEmails.value,
      color: 'success',
    },
  },
  {
    title: 'Spam',
    prependIcon: 'tabler-alert-circle',
    to: {
      name: 'communications-email',
      params: { filter: 'spam' },
    },
    badge: {
      content: spamEmails.value,
      color: 'error',
    },
  },
  {
    title: 'Archived',
    prependIcon: 'tabler-archive',
    to: {
      name: 'communications-email',
      params: { filter: 'archived' },
    },
  },
  {
    title: 'Trash',
    prependIcon: 'tabler-trash',
    to: {
      name: 'communications-email',
      params: { filter: 'trashed' },
    },
  },
])

const quickViews = computed(() => [
  {
    title: 'Unanswered',
    prependIcon: 'tabler-message-question',
    to: {
      name: 'communications-email',
      query: { filter: 'unanswered' },
    },
    badge: {
      content: unansweredEmails.value,
      color: 'error',
    },
  },
  {
    title: 'Pending Replies',
    prependIcon: 'tabler-clock-hour-4',
    to: {
      name: 'communications-email',
      query: { filter: 'pending_replies' },
    },
    badge: {
      content: pendingRepliesEmails.value,
      color: 'warning',
    },
  },
  {
    title: 'Unassigned',
    prependIcon: 'tabler-user-off',
    to: {
      name: 'communications-email',
      query: { filter: 'unassigned' },
    },
    badge: {
      content: unassignedEmails.value,
      color: 'info',
    },
  },
  {
    title: 'Recent Activity',
    prependIcon: 'tabler-history',
    to: {
      name: 'communications-email',
      query: { filter: 'recent_activity' },
    },
    badge: {
      content: recentActivityEmails.value,
      color: 'primary',
    },
  },
])

const labels = [
  {
    title: 'Personal',
    color: 'success',
    to: {
      name: 'communications-email',
      params: { label: 'personal' },
    },
  },
  {
    title: 'Company',
    color: 'primary',
    to: {
      name: 'communications-email',
      params: { label: 'company' },
    },
  },
  {
    title: 'Important',
    color: 'warning',
    to: {
      name: 'communications-email',
      params: { label: 'important' },
    },
  },
  {
    title: 'Private',
    color: 'error',
    to: {
      name: 'communications-email',
      params: { label: 'private' },
    },
  },
]
</script>

<template>
  <div class="d-flex flex-column h-100">
    <!-- 👉 Compose -->
    <div class="px-6 pb-5 pt-6">
      <VBtn
        block
        @click="$emit('toggleComposeDialogVisibility')"
      >
        Compose
      </VBtn>
    </div>

    <!-- 👉 Folders -->
    <PerfectScrollbar
      :options="{ wheelPropagation: false }"
      class="h-100"
    >
      <!-- Standard Folders -->
      <ul class="email-filters py-2">
        <RouterLink
          v-for="folder in folders"
          :key="folder.title"
          v-slot="{ isActive, href, navigate }"
          class="d-flex align-center cursor-pointer align-center"
          :to="folder.to"
          custom
        >
          <li
            v-bind="$attrs"
            :href="href"
            :class="isActive && 'email-filter-active text-primary'"
            class="d-flex align-center cursor-pointer"
            @click="navigate"
          >
            <VIcon
              :icon="folder.prependIcon"
              class="me-2"
              size="20"
            />
            <div class="text-base">
              {{ folder.title }}
            </div>

            <VSpacer />

            <VChip
              v-if="folder.badge?.content"
              :color="folder.badge.color"
              label
              size="small"
              class="rounded-xl px-2"
            >
              {{ folder.badge?.content }}
            </VChip>
          </li>
        </RouterLink>
      </ul>

      <!-- 👉 Workflow Quick Views -->
      <div class="text-caption text-disabled mb-2 mt-4 px-6 font-weight-bold">
        WORKFLOW VIEWS
      </div>
      <ul class="email-filters py-1">
        <RouterLink
          v-for="view in quickViews"
          :key="view.title"
          v-slot="{ isActive, href, navigate }"
          class="d-flex align-center cursor-pointer align-center"
          :to="view.to"
          custom
        >
          <li
            v-bind="$attrs"
            :href="href"
            :class="isActive && 'email-filter-active text-primary'"
            class="d-flex align-center cursor-pointer"
            @click="navigate"
          >
            <VIcon
              :icon="view.prependIcon"
              class="me-2"
              size="20"
            />
            <div class="text-base">
              {{ view.title }}
            </div>

            <VSpacer />

            <VChip
              v-if="view.badge?.content"
              :color="view.badge.color"
              label
              size="small"
              class="rounded-xl px-2"
            >
              {{ view.badge?.content }}
            </VChip>
          </li>
        </RouterLink>
      </ul>

      <!-- 👉 Shared Company Inboxes -->
      <template v-if="sharedMailboxes.length > 0">
        <div class="text-caption text-disabled mb-2 mt-4 px-6 font-weight-bold">
          SHARED INBOXES
        </div>
        <ul class="email-filters py-1">
          <RouterLink
            v-for="mb in sharedMailboxes"
            :key="mb.id"
            v-slot="{ href, navigate }"
            class="d-flex align-center cursor-pointer align-center"
            :to="{ name: 'communications-email', query: { mailbox_id: mb.id } }"
            custom
          >
            <li
              v-bind="$attrs"
              :href="href"
              class="d-flex align-center cursor-pointer"
              @click="navigate"
            >
              <VIcon
                icon="tabler-building"
                class="me-2"
                size="18"
              />
              <div
                class="text-body-2 text-truncate"
                :title="mb.name"
              >
                {{ mb.name }}
              </div>

              <VSpacer />

              <VChip
                v-if="mb.open_conversations_count"
                color="primary"
                variant="tonal"
                size="x-small"
                class="rounded-xl"
              >
                {{ mb.open_conversations_count }}
              </VChip>
            </li>
          </RouterLink>
        </ul>
      </template>

      <!-- 👉 Labels -->
      <div class="text-caption text-disabled mb-2 mt-4 px-6 font-weight-bold">
        LABELS
      </div>
      <ul class="email-labels py-1">
        <RouterLink
          v-for="label in labels"
          :key="label.title"
          v-slot="{ isActive, href, navigate }"
          class="d-flex align-center"
          :to="label.to"
          custom
        >
          <li
            v-bind="$attrs"
            :href="href"
            :class="isActive && 'email-label-active text-primary'"
            class="cursor-pointer d-flex align-center"
            @click="navigate"
          >
            <VIcon
              icon="tabler-circle-filled"
              :color="label.color"
              class="me-2"
              size="12"
            />
            <div class="text-body-1 text-high-emphasis">
              {{ label.title }}
            </div>
          </li>
        </RouterLink>
      </ul>
    </PerfectScrollbar>
  </div>
</template>

<style lang="scss">
.email-filters,
.email-labels {
  .email-filter-active,
  .email-label-active {
    &::after {
      position: absolute;
      background: currentcolor;
      block-size: 100%;
      content: "";
      inline-size: 3px;
      inset-block-start: 0;
      inset-inline-start: 0;
    }
  }
}

.email-filters {
  > li {
    position: relative;
    margin-block-end: 4px;
    padding-block: 4px;
    padding-inline: 24px;
  }
}

.email-labels {
  > li {
    position: relative;
    margin-block-end: 0.75rem;
    padding-inline: 24px;
  }
}
</style>
