<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: {
    type: [Number, String],
    default: null,
  },
  profiles: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  errorMessage: {
    type: String,
    default: '',
  },
  readonly: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'change'])

const { t } = useI18n()

const isDialogOpen = ref(false)
const stagedProfileId = ref(props.modelValue)

watch(() => props.modelValue, val => {
  stagedProfileId.value = val
})

const selectedProfile = computed(() => {
  if (!props.modelValue || !props.profiles.length) return null
  
  return props.profiles.find(p => String(p.id) === String(props.modelValue)) || null
})

function openSelectionDialog() {
  if (props.readonly || props.loading) return
  stagedProfileId.value = props.modelValue
  isDialogOpen.value = true
}

function selectStaged(id) {
  stagedProfileId.value = id
}

function applySelection() {
  if (stagedProfileId.value !== null) {
    emit('update:modelValue', stagedProfileId.value)

    const profile = props.profiles.find(p => String(p.id) === String(stagedProfileId.value))

    emit('change', profile)
  }

  isDialogOpen.value = false
}
</script>

<template>
  <div class="sender-identity-card-wrapper mb-4">
    <!-- Active Sender Identity Card -->
    <VCard
      variant="outlined"
      class="sender-identity-card pa-4 rounded-lg"
      :class="{ 'border-error': !!errorMessage }"
    >
      <div class="d-flex flex-wrap align-center justify-space-between gap-3">
        <!-- Identity Summary -->
        <div class="d-flex align-center gap-3 overflow-hidden flex-grow-1">
          <VAvatar
            size="48"
            rounded
            variant="tonal"
            color="primary"
            class="flex-shrink-0"
          >
            <VIcon
              icon="tabler-mail-forward"
              size="24"
            />
          </VAvatar>

          <div class="overflow-hidden">
            <div class="d-flex flex-wrap align-center gap-2 mb-1">
              <span class="text-caption text-uppercase font-weight-bold text-medium-emphasis">
                {{ t('communications.email.from_identity', 'From Identity') }}
              </span>

              <VChip
                v-if="selectedProfile?.department"
                size="x-small"
                color="primary"
                variant="tonal"
                class="font-weight-medium"
              >
                <VIcon
                  start
                  icon="tabler-building"
                  size="12"
                />
                {{ selectedProfile.department }}
              </VChip>

              <VChip
                v-if="selectedProfile?.is_default"
                size="x-small"
                color="info"
                variant="tonal"
              >
                <VIcon
                  start
                  icon="tabler-star"
                  size="12"
                />
                {{ t('common.default', 'Default') }}
              </VChip>

              <VChip
                v-if="selectedProfile?.is_active"
                size="x-small"
                color="success"
                variant="tonal"
              >
                {{ t('common.active', 'Active') }}
              </VChip>
            </div>

            <div
              v-if="selectedProfile"
              class="d-flex flex-wrap align-baseline gap-2"
            >
              <span class="text-h6 font-weight-bold text-high-emphasis">
                {{ selectedProfile.display_name || selectedProfile.name }}
              </span>
              <span class="text-body-2 text-medium-emphasis">
                &lt;{{ selectedProfile.email }}&gt;
              </span>
            </div>

            <div
              v-else
              class="text-body-2 text-disabled"
            >
              {{ t('communications.email.no_sender_selected', 'No sender identity selected') }}
            </div>

            <!-- Optional Reply-To Information -->
            <div
              v-if="selectedProfile?.reply_to && selectedProfile.reply_to !== selectedProfile.email"
              class="text-caption text-disabled mt-1"
            >
              {{ t('communications.email.reply_to', 'Reply-To') }}: {{ selectedProfile.reply_to }}
            </div>
          </div>
        </div>

        <!-- Change Action Button -->
        <div class="flex-shrink-0">
          <VBtn
            v-if="!readonly"
            variant="tonal"
            color="primary"
            prepend-icon="tabler-arrows-exchange"
            :loading="loading"
            :disabled="loading || profiles.length <= 1"
            @click="openSelectionDialog"
          >
            {{ t('common.change', 'Change') }}
          </VBtn>
        </div>
      </div>

      <div
        v-if="errorMessage"
        class="text-caption text-error mt-2 font-weight-medium"
      >
        {{ errorMessage }}
      </div>
    </VCard>

    <!-- Dedicated Status-Change Styled Identity Selection Dialog -->
    <VDialog
      v-model="isDialogOpen"
      max-width="560"
      persistent
    >
      <VCard class="sender-identity-dialog">
        <!-- Dialog Header -->
        <VCardTitle class="d-flex align-center justify-space-between pa-4 border-b">
          <div class="d-flex align-center gap-2">
            <VIcon
              icon="tabler-user-check"
              color="primary"
              size="22"
            />
            <span class="text-h6 font-weight-bold">
              {{ t('communications.email.select_sender_title', 'Select Sender Identity') }}
            </span>
          </div>

          <IconBtn
            size="small"
            @click="isDialogOpen = false"
          >
            <VIcon icon="tabler-x" />
          </IconBtn>
        </VCardTitle>

        <!-- Dialog Body -->
        <VCardText class="pa-4">
          <p class="text-caption text-medium-emphasis mb-4">
            {{ t('communications.email.select_sender_desc', 'Choose an authorized sender profile for your outgoing messages. Note: System profiles are restricted to automated workflows.') }}
          </p>

          <div class="d-flex flex-column gap-2 mb-2">
            <div
              v-for="profile in profiles"
              :key="profile.id"
              class="sender-tile pa-3 rounded-lg border cursor-pointer d-flex align-center justify-space-between transition-all"
              :class="{
                'sender-tile--active border-primary bg-var-theme-surface': String(stagedProfileId) === String(profile.id),
              }"
              @click="selectStaged(profile.id)"
            >
              <div class="d-flex align-center gap-3 overflow-hidden">
                <VAvatar
                  size="40"
                  rounded
                  variant="tonal"
                  :color="String(stagedProfileId) === String(profile.id) ? 'primary' : 'secondary'"
                  class="flex-shrink-0"
                >
                  <VIcon
                    icon="tabler-user"
                    size="20"
                  />
                </VAvatar>

                <div class="overflow-hidden">
                  <div class="d-flex align-center gap-2 mb-1">
                    <span class="text-subtitle-2 font-weight-bold text-high-emphasis">
                      {{ profile.display_name || profile.name }}
                    </span>

                    <VChip
                      v-if="profile.department"
                      size="x-small"
                      color="primary"
                      variant="tonal"
                    >
                      {{ profile.department }}
                    </VChip>

                    <VChip
                      v-if="profile.is_default"
                      size="x-small"
                      color="info"
                      variant="tonal"
                    >
                      {{ t('common.default', 'Default') }}
                    </VChip>
                  </div>

                  <span class="text-caption text-medium-emphasis text-truncate d-block">
                    {{ profile.email }}
                    <span
                      v-if="profile.reply_to && profile.reply_to !== profile.email"
                      class="opacity-75"
                    >
                      (Reply-To: {{ profile.reply_to }})
                    </span>
                  </span>
                </div>
              </div>

              <VIcon
                :icon="String(stagedProfileId) === String(profile.id) ? 'tabler-circle-check-filled' : 'tabler-circle'"
                :color="String(stagedProfileId) === String(profile.id) ? 'primary' : 'disabled'"
                size="22"
                class="ms-2 flex-shrink-0"
              />
            </div>
          </div>
        </VCardText>

        <!-- Dialog Actions -->
        <VDivider />
        <VCardActions class="pa-4 justify-end gap-2">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isDialogOpen = false"
          >
            {{ t('common.cancel', 'Cancel') }}
          </VBtn>

          <VBtn
            color="primary"
            :disabled="stagedProfileId === null"
            @click="applySelection"
          >
            {{ t('communications.email.apply_sender', 'Apply Identity') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.sender-tile {
  transition: all 0.15s ease-in-out;
}

.sender-tile:hover {
  background-color: rgba(var(--v-theme-primary), 0.04);
}

.sender-tile--active {
  border-width: 2px !important;
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
}
</style>
