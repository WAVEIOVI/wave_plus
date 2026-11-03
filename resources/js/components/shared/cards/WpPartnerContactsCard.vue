<script setup>
/* eslint-disable lines-around-comment, camelcase, vue/custom-event-name-casing */
/**
 * WpPartnerContactsCard
 * ─────────────────────────────────────────────────────────────────────────────
 * Displays contacts belonging to a Business Partner with contact selection,
 * primary contact indication, and detailed contact info.
 *
 * Requirements:
 * - Single or multiple contact selection mode
 * - Display name, job title, email, phone, avatar, and primary badge
 * - Invalidation/refresh when partner changes
 * - Actions: select/change, add contact (if authorized), view details, set primary
 * - Responsive layout: clean cards or list with mobile optimization
 */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { resolveAccessor } from '@/composables/shared/useSearchSelect'
import { localizedValue } from '@/composables/useLocaleField'
import { avatarText } from '@core/utils/formatters'

const props = defineProps({
  contacts: { type: Array, default: () => [] },
  selectedContactId: { type: [Number, String, Array], default: null },
  partnerId: { type: [Number, String], default: null },
  title: { type: String, default: '' },
  multiple: { type: Boolean, default: false },
  canSelect: { type: Boolean, default: true },
  canAdd: { type: Boolean, default: false },
  canEdit: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
  canSetPrimary: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  searchConfig: { type: Object, default: null },
  fields: { type: Object, default: () => ({}) },
})

const emit = defineEmits([
  'update:selectedContactId',
  'select',
  'add',
  'edit',
  'delete',
  'view',
  'set-primary',
  'partner-changed',
])

const { t } = useI18n()
const { smAndDown } = useDisplay()

const F = computed(() => ({
  id: 'id',
  name: 'name',
  fullName: 'full_name',
  firstName: 'first_name',
  lastName: 'last_name',
  jobTitle: 'job_title',
  email: 'email',
  phone: 'phone',
  isPrimary: 'is_primary',
  avatar: 'avatar_url',
  department: 'department',
  ...props.fields,
}))

const get = (key, item) => resolveAccessor(F.value[key] || key, item)

const getName = contact => {
  const full = get('fullName', contact)

  if (full) {
    const val = localizedValue(full)
    if (val && typeof val === 'string') return val
  }
  const fn = get('firstName', contact) || contact?.first_name
  const ln = get('lastName', contact) || contact?.last_name
  const locFn = localizedValue(fn)
  const locLn = localizedValue(ln)
  const combined = [locFn, locLn].filter(Boolean).join(' ').trim()

  if (combined) return combined
  const name = get('name', contact)

  if (name) {
    const val = localizedValue(name)
    if (val && typeof val === 'string') return val
  }

  return t('wp.cards.contact.unnamed') || 'Unnamed Contact'
}

// Invalidate or notify when partnerId changes
watch(() => props.partnerId, (newVal, oldVal) => {
  if (oldVal !== undefined && newVal !== oldVal) {
    emit('partner-changed', newVal)
    if (props.canSelect && !props.readonly) {
      emit('update:selectedContactId', props.multiple ? [] : null)
      emit('select', props.multiple ? [] : null)
    }
  }
})

const isSelected = contact => {
  const id = get('id', contact)

  if (props.multiple) {
    return Array.isArray(props.selectedContactId) && props.selectedContactId.includes(id)
  }

  return props.selectedContactId === id
}

const toggleSelect = contact => {
  if (!props.canSelect || props.readonly) return
  const id = get('id', contact)

  if (props.multiple) {
    const list = Array.isArray(props.selectedContactId) ? [...props.selectedContactId] : []
    const idx = list.indexOf(id)

    if (idx > -1) {
      list.splice(idx, 1)
    } else {
      list.push(id)
    }
    emit('update:selectedContactId', list)
    emit('select', props.contacts.filter(c => list.includes(get('id', c))))
  } else {
    const next = props.selectedContactId === id ? null : id

    emit('update:selectedContactId', next)
    emit('select', next ? contact : null)
  }
}

const searchDialogOpen = ref(false)

const openSearchDialog = () => {
  if (props.searchConfig) {
    searchDialogOpen.value = true
  }
}

const handleDialogSelected = itemOrItems => {
  if (!itemOrItems) return

  if (props.multiple) {
    const items = Array.isArray(itemOrItems) ? itemOrItems : [itemOrItems]
    const ids = items.map(c => get('id', c))

    emit('update:selectedContactId', ids)
    emit('select', items)
  } else {
    const id = get('id', itemOrItems)

    emit('update:selectedContactId', id)
    emit('select', itemOrItems)
  }
}
</script>

<template>
  <VCard
    class="wp-partner-contacts-card"
    variant="flat"
    border
    rounded="lg"
  >
    <VCardText class="pb-2">
      <WpSectionHeader
        :title="title || t('wp.cards.contacts.title')"
        icon="tabler-users"
        :count="contacts.length"
        :level="3"
      >
        <template #actions>
          <slot name="actions" />
          <VBtn
            v-if="searchConfig && canSelect && !readonly"
            size="small"
            variant="tonal"
            prepend-icon="tabler-user-search"
            @click="openSearchDialog"
          >
            {{ t('wp.cards.contacts.select_dialog') }}
          </VBtn>
          <VBtn
            v-if="canAdd && !readonly"
            size="small"
            color="primary"
            variant="tonal"
            prepend-icon="tabler-user-plus"
            class="d-none d-md-inline-flex"
            @click="emit('add')"
          >
            {{ t('wp.cards.contacts.add') }}
          </VBtn>
        </template>
      </WpSectionHeader>
    </VCardText>

    <VCardText class="pt-0">
      <WpStateBlock
        :state="loading ? 'loading' : contacts.length === 0 ? 'empty' : 'ready'"
        skeleton="list-item-avatar-two-line, list-item-avatar-two-line"
        :title="t('wp.cards.contacts.empty_title')"
        :text="t('wp.cards.contacts.empty_text')"
        compact
      >
        <div class="wp-partner-contacts-card__list">
          <div
            v-for="contact in contacts"
            :key="get('id', contact)"
            class="wp-partner-contacts-card__item"
            :class="{
              'is-selected': isSelected(contact),
              'is-selectable': (canSelect && !readonly) || !canSelect,
            }"
            @click="canSelect && !readonly ? toggleSelect(contact) : emit('view', contact)"
          >
            <div
              v-if="canSelect && !readonly"
              class="wp-partner-contacts-card__select me-2"
            >
              <VIcon
                :icon="isSelected(contact) ? (multiple ? 'tabler-square-check-filled' : 'tabler-circle-check-filled') : (multiple ? 'tabler-square' : 'tabler-circle')"
                :color="isSelected(contact) ? 'primary' : 'secondary'"
                size="20"
              />
            </div>

            <!-- Avatar: Hidden on mobile screens to grant full width to contact name and details -->
            <VAvatar
              v-if="!smAndDown"
              size="40"
              rounded="lg"
              color="primary"
              variant="tonal"
              class="wp-partner-contacts-card__avatar flex-shrink-0 align-self-start mt-0.5"
            >
              <VImg
                v-if="get('avatar', contact)"
                :src="get('avatar', contact)"
                :alt="getName(contact)"
              />
              <span
                v-else
                class="text-caption font-weight-bold"
              >
                {{ avatarText(getName(contact)) }}
              </span>
            </VAvatar>

            <div class="wp-partner-contacts-card__content">
              <!-- Mobile Layout: Top action bar (Primary chip left, Actions right), followed by full-width Name underneath -->
              <template v-if="smAndDown">
                <div class="wp-partner-contacts-card__mobile-actions-bar d-flex align-center justify-space-between mb-1.5">
                  <VChip
                    v-if="get('isPrimary', contact)"
                    size="x-small"
                    color="success"
                    variant="tonal"
                    label
                    class="font-weight-medium flex-shrink-0"
                  >
                    <VIcon
                      icon="tabler-star-filled"
                      size="12"
                      start
                    />
                    {{ t('wp.cards.contacts.primary') }}
                  </VChip>
                  <div v-else />

                  <div
                    class="wp-partner-contacts-card__item-actions"
                    @click.stop
                  >
                    <VBtn
                      v-if="canSetPrimary && !get('isPrimary', contact) && !readonly"
                      icon
                      size="small"
                      variant="text"
                      color="warning"
                      :aria-label="t('wp.cards.contacts.make_primary')"
                      @click="emit('set-primary', contact)"
                    >
                      <VIcon
                        icon="tabler-star"
                        size="18"
                      />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        {{ t('wp.cards.contacts.make_primary') }}
                      </VTooltip>
                    </VBtn>

                    <VBtn
                      v-if="canEdit && !readonly"
                      icon
                      size="small"
                      variant="text"
                      :aria-label="t('wp.cards.edit')"
                      @click="emit('edit', contact)"
                    >
                      <VIcon
                        icon="tabler-edit"
                        size="18"
                      />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        {{ t('wp.cards.edit') }}
                      </VTooltip>
                    </VBtn>

                    <VBtn
                      v-if="canDelete && !readonly"
                      icon
                      size="small"
                      variant="text"
                      color="error"
                      :aria-label="t('wp.cards.delete') || 'Delete'"
                      @click="emit('delete', contact)"
                    >
                      <VIcon
                        icon="tabler-trash"
                        size="18"
                      />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        {{ t('wp.cards.delete') || 'Delete' }}
                      </VTooltip>
                    </VBtn>
                  </div>
                </div>

                <!-- Full-width Name on Mobile (placed underneath action icons as requested) -->
                <div class="mb-1">
                  <span class="text-body-2 font-weight-bold wp-partner-contacts-card__name text-break">
                    {{ getName(contact) }}
                  </span>
                </div>
              </template>

              <!-- Desktop Layout: Single top row (Name + Primary chip left, Actions right) -->
              <template v-else>
                <div class="wp-partner-contacts-card__desktop-top-row d-flex align-center justify-space-between gap-2 mb-0.5">
                  <div class="wp-partner-contacts-card__name-box">
                    <span class="text-body-2 font-weight-bold wp-partner-contacts-card__name text-break">
                      {{ getName(contact) }}
                    </span>
                    <VChip
                      v-if="get('isPrimary', contact)"
                      size="x-small"
                      color="success"
                      variant="tonal"
                      label
                      class="font-weight-medium flex-shrink-0"
                    >
                      <VIcon
                        icon="tabler-star-filled"
                        size="12"
                        start
                      />
                      {{ t('wp.cards.contacts.primary') }}
                    </VChip>
                  </div>

                  <div
                    class="wp-partner-contacts-card__item-actions"
                    @click.stop
                  >
                    <VBtn
                      v-if="canSetPrimary && !get('isPrimary', contact) && !readonly"
                      icon
                      size="small"
                      variant="text"
                      color="warning"
                      :aria-label="t('wp.cards.contacts.make_primary')"
                      @click="emit('set-primary', contact)"
                    >
                      <VIcon
                        icon="tabler-star"
                        size="18"
                      />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        {{ t('wp.cards.contacts.make_primary') }}
                      </VTooltip>
                    </VBtn>

                    <VBtn
                      v-if="canEdit && !readonly"
                      icon
                      size="small"
                      variant="text"
                      :aria-label="t('wp.cards.edit')"
                      @click="emit('edit', contact)"
                    >
                      <VIcon
                        icon="tabler-edit"
                        size="18"
                      />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        {{ t('wp.cards.edit') }}
                      </VTooltip>
                    </VBtn>

                    <VBtn
                      v-if="canDelete && !readonly"
                      icon
                      size="small"
                      variant="text"
                      color="error"
                      :aria-label="t('wp.cards.delete') || 'Delete'"
                      @click="emit('delete', contact)"
                    >
                      <VIcon
                        icon="tabler-trash"
                        size="18"
                      />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        {{ t('wp.cards.delete') || 'Delete' }}
                      </VTooltip>
                    </VBtn>
                  </div>
                </div>
              </template>

              <!-- Subtitle: Job title & Department (complete content without ellipsis) -->
              <div
                v-if="get('jobTitle', contact)"
                class="text-caption text-medium-emphasis wp-partner-contacts-card__role mt-0.5"
              >
                {{ get('jobTitle', contact) }}
                <span v-if="get('department', contact)"> • {{ get('department', contact) }}</span>
              </div>

              <!-- Channels: Email & Phone -->
              <div
                v-if="get('email', contact) || get('phone', contact)"
                class="d-flex flex-wrap gap-x-3 gap-y-1 mt-1 text-caption min-w-0"
              >
                <span
                  v-if="get('email', contact)"
                  class="d-inline-flex align-center gap-1 min-w-0 mw-100 text-truncate"
                >
                  <VIcon
                    icon="tabler-mail"
                    size="13"
                    class="text-medium-emphasis flex-shrink-0"
                  />
                  <a
                    :href="`mailto:${get('email', contact)}`"
                    class="text-decoration-none text-high-emphasis text-truncate"
                    @click.stop
                  >{{ get('email', contact) }}</a>
                </span>
                <span
                  v-if="get('phone', contact)"
                  class="d-inline-flex align-center gap-1 min-w-0 mw-100 text-truncate"
                >
                  <VIcon
                    icon="tabler-phone"
                    size="13"
                    class="text-medium-emphasis flex-shrink-0"
                  />
                  <a
                    :href="`tel:${get('phone', contact)}`"
                    class="text-decoration-none text-high-emphasis text-truncate"
                    @click.stop
                  >{{ get('phone', contact) }}</a>
                </span>
              </div>
            </div>
          </div>
        </div>

        <template #empty-action>
          <VBtn
            v-if="canAdd && !readonly"
            size="small"
            color="primary"
            variant="tonal"
            prepend-icon="tabler-user-plus"
            class="d-none d-md-inline-flex"
            @click="emit('add')"
          >
            {{ t('wp.cards.contacts.add') }}
          </VBtn>
        </template>
      </WpStateBlock>
    </VCardText>

    <WpSearchSelectDialog
      v-if="searchConfig"
      v-model="searchDialogOpen"
      :multiple="multiple"
      :entity-label="t('wp.cards.contacts.entity')"
      icon="tabler-users"
      v-bind="searchConfig"
      @select="handleDialogSelected"
    />
  </VCard>
</template>

<style scoped>
.wp-partner-contacts-card__list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.wp-partner-contacts-card__item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  background: rgba(var(--v-theme-surface), 0.6);
  transition: all 0.15s ease;
  min-width: 0;
  width: 100%;
}

.wp-partner-contacts-card__item.is-selectable {
  cursor: pointer;
}

.wp-partner-contacts-card__item.is-selectable:hover {
  background: rgba(var(--v-theme-primary), 0.04);
}

.wp-partner-contacts-card__item.is-selected {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.08);
}

.wp-partner-contacts-card__content {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
}

.wp-partner-contacts-card__top-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  min-width: 0;
}

.wp-partner-contacts-card__name-box {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
  flex: 1 1 auto;
}

.wp-partner-contacts-card__name {
  word-break: break-word;
  line-height: 1.35;
}

.wp-partner-contacts-card__role {
  word-break: break-word;
  line-height: 1.35;
}

.wp-partner-contacts-card__item-actions {
  display: inline-flex;
  flex-wrap: nowrap !important;
  align-items: center;
  justify-content: flex-end;
  gap: 2px;
  flex-shrink: 0;
  margin-inline-start: auto;
}

.wp-partner-contacts-card__item-actions :deep(.v-btn--icon) {
  inline-size: 28px;
  block-size: 28px;
  min-inline-size: 28px;
}

@media (max-width: 599.98px) {
  .wp-partner-contacts-card__avatar {
    display: none !important;
  }

  .wp-partner-contacts-card__item {
    padding: 8px 10px;
    gap: 8px;
  }

  .wp-partner-contacts-card__item-actions :deep(.v-btn--icon) {
    inline-size: 24px;
    block-size: 24px;
    min-inline-size: 24px;
  }
}
</style>
