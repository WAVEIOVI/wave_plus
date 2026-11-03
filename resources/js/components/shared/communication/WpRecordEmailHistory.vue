<script setup>
import { $api } from '@/utils/api'
import { computed, onMounted, ref, watch } from 'vue'

const props = defineProps({
  recordType: {
    type: String,
    required: true,
  },
  recordId: {
    type: [String, Number],
    required: true,
  },
  recordReference: {
    type: String,
    default: '',
  },
  recipientEmail: {
    type: String,
    default: '',
  },
  title: {
    type: String,
    default: 'Email Communications',
  },
})

const emit = defineEmits(['compose'])

const conversations = ref([])
const isLoading = ref(false)
const totalConversations = ref(0)
const currentPage = ref(1)

// Link dialog state
const isLinkDialogOpen = ref(false)
const searchQuery = ref('')
const isSearching = ref(false)
const searchResults = ref([])
const selectedConversationId = ref(null)
const isSubmittingLink = ref(false)

// Unlink state
const isUnlinking = ref(false)

const loadConversations = async (page = 1) => {
  if (!props.recordType || !props.recordId) return
  isLoading.value = true
  currentPage.value = page
  try {
    const res = await $api(`/communications/email/records/${props.recordType}/${props.recordId}/conversations`, {
      params: { page },
    })

    conversations.value = res.data || []
    totalConversations.value = res.total || 0
  } catch (err) {
    console.error('Failed to load record email conversations', err)
  } finally {
    isLoading.value = false
  }
}

const searchConversations = async () => {
  if (!searchQuery.value.trim() || searchQuery.value.trim().length < 2) return
  isSearching.value = true
  try {
    const res = await $api('/communications/email/conversations', {
      params: { q: searchQuery.value },
    })

    searchResults.value = res.data || []
  } catch (err) {
    console.error('Failed searching conversations', err)
  } finally {
    isSearching.value = false
  }
}

const linkConversation = async () => {
  if (!selectedConversationId.value || isSubmittingLink.value) return
  isSubmittingLink.value = true
  try {
    await $api(`/communications/email/conversations/${selectedConversationId.value}/links`, {
      method: 'POST',
      body: {
        'record_type': props.recordType,
        'record_id': String(props.recordId),
        reason: props.recordReference ? `Linked to ${props.recordReference}` : undefined,
      },
    })
    isLinkDialogOpen.value = false
    selectedConversationId.value = null
    searchQuery.value = ''
    searchResults.value = []
    await loadConversations(1)
  } catch (err) {
    console.error('Failed linking conversation', err)
  } finally {
    isSubmittingLink.value = false
  }
}

const unlinkConversation = async conversationId => {
  if (isUnlinking.value) return
  isUnlinking.value = true
  try {
    await $api(`/communications/email/conversations/${conversationId}/links`, {
      method: 'DELETE',
      body: {
        'record_type': props.recordType,
        'record_id': String(props.recordId),
      },
    })
    await loadConversations(currentPage.value)
  } catch (err) {
    console.error('Failed unlinking conversation', err)
  } finally {
    isUnlinking.value = false
  }
}

const resolveStatusColor = status => {
  if (status === 'open') return 'success'
  if (status === 'pending') return 'warning'
  if (status === 'resolved') return 'info'
  if (status === 'closed') return 'secondary'

  return 'default'
}

const openComposer = () => {
  emit('compose', {
    recordType: props.recordType,
    recordId: props.recordId,
    recordReference: props.recordReference,
    recipientEmail: props.recipientEmail,
  })
}

watch(
  () => [props.recordType, props.recordId],
  () => {
    loadConversations(1)
  },
  { immediate: true },
)
</script>

<template>
  <VCard class="wp-record-email-history">
    <VCardItem class="pb-2">
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <div class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-mail"
            size="22"
            color="primary"
          />
          <h6 class="text-h6 font-weight-bold">
            {{ title }}
            <span
              v-if="totalConversations > 0"
              class="text-caption text-medium-emphasis ms-1"
            >({{ totalConversations }})</span>
          </h6>
        </div>

        <div class="d-flex align-center gap-2">
          <VBtn
            variant="outlined"
            size="small"
            color="primary"
            prepend-icon="tabler-link"
            @click="isLinkDialogOpen = true"
          >
            Link Email
          </VBtn>
          <VBtn
            size="small"
            color="primary"
            prepend-icon="tabler-pencil"
            @click="openComposer"
          >
            Compose
          </VBtn>
        </div>
      </div>
    </VCardItem>

    <VDivider />

    <VCardText class="pa-4">
      <!-- Loading State -->
      <div
        v-if="isLoading"
        class="d-flex justify-center align-center py-8"
      >
        <VProgressCircular
          indeterminate
          color="primary"
        />
      </div>

      <!-- Empty State -->
      <div
        v-else-if="conversations.length === 0"
        class="text-center py-6"
      >
        <VAvatar
          size="48"
          color="primary"
          variant="tonal"
          class="mb-3"
        >
          <VIcon
            icon="tabler-mail-off"
            size="24"
          />
        </VAvatar>
        <div class="text-body-1 font-weight-medium">
          No email conversations linked yet
        </div>
        <div class="text-caption text-medium-emphasis mb-3">
          Link an existing company email or compose a new message for this record.
        </div>
      </div>

      <!-- Conversations List -->
      <div
        v-else
        class="d-flex flex-column gap-3"
      >
        <VCard
          v-for="conv in conversations"
          :key="conv.id"
          variant="outlined"
          class="pa-3"
        >
          <div class="d-flex align-start justify-space-between flex-wrap gap-2 mb-2">
            <div>
              <div class="d-flex align-center gap-2 mb-1">
                <span class="text-body-1 font-weight-bold">{{ conv.subject }}</span>
                <VChip
                  :color="resolveStatusColor(conv.status)"
                  size="x-small"
                  variant="tonal"
                  class="text-capitalize"
                >
                  {{ conv.status }}
                </VChip>
                <VChip
                  v-if="conv.department"
                  size="x-small"
                  variant="outlined"
                  color="secondary"
                  class="text-capitalize"
                >
                  {{ conv.department }}
                </VChip>
              </div>

              <div class="text-caption text-medium-emphasis">
                <span v-if="conv.participant_names">With: {{ conv.participant_names }}</span>
                <span
                  v-if="conv.last_message_at"
                  class="ms-2"
                >
                  • Last activity: {{ new Date(conv.last_message_at).toLocaleString() }}
                </span>
              </div>
            </div>

            <div class="d-flex align-center gap-1">
              <VBtn
                icon="tabler-external-link"
                variant="text"
                size="x-small"
                color="primary"
                :to="{
                  name: 'communications-email',
                  query: { q: conv.subject },
                }"
                title="View in Email Center"
              />
              <VBtn
                icon="tabler-unlink"
                variant="text"
                size="x-small"
                color="error"
                title="Unlink Conversation"
                :loading="isUnlinking"
                @click="unlinkConversation(conv.id)"
              />
            </div>
          </div>

          <!-- Snippet of last message if present -->
          <div
            v-if="conv.messages && conv.messages.length > 0"
            class="text-caption text-truncate text-medium-emphasis bg-var-theme-background pa-2 rounded"
          >
            {{ (conv.messages[0].body_text || conv.messages[0].body_html || '').replace(/<[^>]*>?/gm, '').substring(0, 140) }}...
          </div>
        </VCard>
      </div>
    </VCardText>

    <!-- Link Dialog -->
    <VDialog
      v-model="isLinkDialogOpen"
      max-width="560"
    >
      <VCard>
        <VCardItem class="pb-2">
          <div class="d-flex align-center justify-space-between">
            <h6 class="text-h6 font-weight-bold">
              Link Existing Email Conversation
            </h6>
            <VBtn
              icon="tabler-x"
              variant="text"
              size="small"
              @click="isLinkDialogOpen = false"
            />
          </div>
        </VCardItem>

        <VDivider />

        <VCardText class="pa-4">
          <div class="mb-4">
            <VTextField
              v-model="searchQuery"
              placeholder="Search conversations by subject or participant..."
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-search"
              clearable
              @keydown.enter="searchConversations"
            >
              <template #append-inner>
                <VBtn
                  size="x-small"
                  variant="text"
                  color="primary"
                  :loading="isSearching"
                  @click="searchConversations"
                >
                  Search
                </VBtn>
              </template>
            </VTextField>
          </div>

          <!-- Search Results -->
          <div
            v-if="searchResults.length > 0"
            class="d-flex flex-column gap-2 mb-4"
            style="max-block-size: 260px; overflow-y: auto;"
          >
            <VCard
              v-for="res in searchResults"
              :key="res.id"
              variant="outlined"
              :class="selectedConversationId === res.id ? 'border-primary bg-primary-subtle' : ''"
              class="pa-3 cursor-pointer"
              @click="selectedConversationId = res.id"
            >
              <div class="d-flex align-center justify-space-between">
                <span class="text-body-2 font-weight-bold">{{ res.subject }}</span>
                <VRadio
                  :model-value="selectedConversationId === res.id"
                  density="compact"
                  hide-details
                />
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ res.department ? `${res.department.toUpperCase()} • ` : '' }}
                {{ res.participant_names || 'Participants' }}
              </div>
            </VCard>
          </div>
          <div
            v-else-if="searchQuery.trim().length >= 2 && !isSearching"
            class="text-center py-4 text-caption text-medium-emphasis"
          >
            No conversations matching your query.
          </div>
        </VCardText>

        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn
            variant="text"
            color="secondary"
            @click="isLinkDialogOpen = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            :disabled="!selectedConversationId"
            :loading="isSubmittingLink"
            @click="linkConversation"
          >
            Link Selected
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VCard>
</template>
