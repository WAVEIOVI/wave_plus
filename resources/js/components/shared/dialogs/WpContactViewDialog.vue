<script setup>
/* eslint-disable lines-around-comment, camelcase, vue/custom-event-name-casing */
/**
 * WpContactViewDialog
 * ─────────────────────────────────────────────────────────────────────────────
 * Modern, mobile-first responsive shared view dialog for displaying detailed contact
 * information across WAVE PLUS modules.
 *
 * Clones the exact header structure, scroll container, responsive sizing, and
 * action footer from WpWizardDialog with full 320px+ mobile optimization.
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'
import WpActionFooter from '@/components/shared/layout/WpActionFooter.vue'
import { localizedValue } from '@/composables/useLocaleField'
import { getDepartments } from '@/utils/bp-constants'
import { avatarText } from '@core/utils/formatters'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  contact: { type: Object, default: null },
  title: { type: String, default: '' },
  canEdit: { type: Boolean, default: false },
  canSetPrimary: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'edit', 'set-primary', 'close'])

const { t } = useI18n()
const { smAndDown } = useDisplay()

const isOpen = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const c = computed(() => props.contact || {})

const firstName = computed(() => localizedValue(c.value.first_name || c.value.firstName))
const lastName = computed(() => localizedValue(c.value.last_name || c.value.lastName))

const fullName = computed(() => {
  const parts = [firstName.value, lastName.value].filter(Boolean)
  if (parts.length > 0) return parts.join(' ')
  if (c.value.name) return localizedValue(c.value.name)
  if (c.value.full_name) return localizedValue(c.value.full_name)
  
  return t('Contact Details')
})

const jobTitle = computed(() => c.value.job_title || c.value.jobTitle || '')
const department = computed(() => c.value.department || '')
const departmentOptions = computed(() => getDepartments())
const departmentTitle = computed(() => departmentOptions.value.find(d => d.value.toLowerCase() === (department.value || '').toLowerCase() || d.title.toLowerCase() === (department.value || '').toLowerCase())?.title || department.value)
const notes = computed(() => localizedValue(c.value.notes))

const genderVariant = computed(() => {
  const g = (c.value.gender || '').toLowerCase()
  if (g === 'female') return { icon: 'tabler-gender-female', color: 'pink', label: t('Female') }
  if (g === 'male') return { icon: 'tabler-gender-male', color: 'info', label: t('Male') }
  
  return { icon: 'tabler-user', color: 'secondary', label: t('Not Specified') }
})

const statusVariant = computed(() => {
  const s = (c.value.status || 'active').toLowerCase()
  if (s === 'active') return { color: 'success', label: t('Active'), icon: 'tabler-check' }
  
  return { color: 'warning', label: t('Inactive'), icon: 'tabler-alert-circle' }
})

const isPrimary = computed(() => Boolean(c.value.is_primary || c.value.isPrimary))
const isDecisionMaker = computed(() => Boolean(c.value.is_decision_maker || c.value.isDecisionMaker))

const departmentMeta = {
  executive: { icon: 'tabler-crown', color: 'primary' },
  operations: { icon: 'tabler-settings', color: 'info' },
  sales: { icon: 'tabler-chart-line', color: 'primary' },
  marketing: { icon: 'tabler-speakerphone', color: 'warning' },
  finance: { icon: 'tabler-currency-dollar', color: 'secondary' },
  hr: { icon: 'tabler-users', color: 'purple' },
  it: { icon: 'tabler-device-laptop', color: 'info' },
  legal: { icon: 'tabler-scale', color: 'error' },
  engineering: { icon: 'tabler-tools', color: 'warning' },
  sustainability: { icon: 'tabler-leaf', color: 'success' },
  international: { icon: 'tabler-world', color: 'info' },
  'project management': { icon: 'tabler-checklist', color: 'primary' },
  'customer service': { icon: 'tabler-headset', color: 'success' },
  healthcare: { icon: 'tabler-pill', color: 'error' },
}

const getDeptMeta = deptVal => {
  const key = (deptVal || '').toLowerCase()

  return departmentMeta[key] || { icon: 'tabler-briefcase', color: 'secondary' }
}

const whatsappCleanNumber = computed(() => {
  const raw = c.value.whatsapp_number || c.value.whatsappNumber || c.value.mobile || c.value.phone || ''
  
  return String(raw).replace(/[^0-9+]/g, '')
})

const linkedAddresses = computed(() => {
  const addrs = c.value.addresses || c.value.bp_addresses || []
  
  return Array.isArray(addrs) ? addrs : []
})

const handleEdit = () => {
  emit('edit', c.value)
  isOpen.value = false
}

const handleSetPrimary = () => {
  emit('set-primary', c.value)
}

const handleClose = () => {
  isOpen.value = false
  emit('close')
}
</script>

<template>
  <VDialog
    v-model="isOpen"
    :max-width="720"
    :fullscreen="smAndDown"
    scrollable
    class="wp-view-dialog"
  >
    <VCard class="wp-view-dialog__card d-flex flex-column h-100">
      <!-- ── Fixed Header (Cloned from WpWizardDialog) ────────────────── -->
      <header class="wp-view-dialog__header">
        <div class="d-flex align-center gap-2 gap-sm-3 min-w-0 w-100">
          <VAvatar
            :color="genderVariant.color || 'primary'"
            variant="elevated"
            rounded="lg"
            :size="smAndDown ? 36 : 48"
            class="wp-view-dialog__avatar flex-shrink-0"
          >
            <VImg
              v-if="c.avatar_url || c.avatar"
              :src="c.avatar_url || c.avatar"
              :alt="fullName"
            />
            <span
              v-else-if="fullName"
              class="font-weight-bold text-white"
              :class="smAndDown ? 'text-caption' : 'text-subtitle-1'"
            >
              {{ avatarText(fullName) }}
            </span>
            <VIcon
              v-else
              :icon="genderVariant.icon || 'tabler-user'"
              :size="smAndDown ? 20 : 26"
            />
          </VAvatar>

          <div class="flex-grow-1 min-w-0">
            <div class="d-flex align-center gap-1.5 flex-wrap min-w-0">
              <h2 class="text-subtitle-1 text-sm-h6 font-weight-bold text-break mb-0">
                {{ fullName }}
              </h2>
              <VChip
                v-if="isPrimary"
                size="x-small"
                color="success"
                variant="tonal"
                label
                class="font-weight-semibold flex-shrink-0"
              >
                <VIcon
                  icon="tabler-star-filled"
                  size="10"
                  start
                />
                {{ t('wp.cards.contacts.primary') }}
              </VChip>
            </div>
          </div>

          <IconBtn
            variant="tonal"
            size="small"
            color="secondary"
            class="wp-view-dialog__close-btn rounded-lg flex-shrink-0 ms-auto"
            :aria-label="t('Close')"
            @click="handleClose"
          >
            <VIcon
              icon="tabler-x"
              size="20"
            />
          </IconBtn>
        </div>
      </header>

      <VDivider />

      <!-- ── Scrollable Body (Cloned from WpWizardDialog) ───────────────── -->
      <PerfectScrollbar
        class="wp-view-dialog__body"
        :options="{ wheelPropagation: false, suppressScrollX: true }"
      >
        <div
          v-if="contact"
          class="d-flex flex-column gap-4"
        >
          <!-- Section Card 1: Professional Profile & Authority -->
          <VCard
            variant="flat"
            border
            rounded="lg"
            class="wp-view-dialog__section overflow-hidden"
          >
            <div class="wp-view-dialog__section-header d-flex align-center gap-3 px-4 px-sm-5 py-3">
              <VAvatar
                color="primary"
                variant="tonal"
                rounded="lg"
                :size="smAndDown ? 34 : 38"
                class="flex-shrink-0"
              >
                <VIcon
                  icon="tabler-briefcase"
                  :size="smAndDown ? 18 : 20"
                />
              </VAvatar>
              <div class="flex-grow-1 min-w-0">
                <h3 class="text-subtitle-1 font-weight-bold mb-0 text-break">
                  {{ t('Professional Profile & Authority') }}
                </h3>
                <div class="text-caption text-medium-emphasis text-break">
                  {{ t('Organizational role, business domain and decision authority') }}
                </div>
              </div>
            </div>
            <VDivider />
            <div class="px-4 px-sm-5 py-3">
              <dl class="wp-view-dialog__list">
                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('Department') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0">
                    <VChip
                      v-if="department"
                      :color="getDeptMeta(department).color"
                      size="small"
                      label
                      class="font-weight-medium"
                    >
                      <VIcon
                        :icon="getDeptMeta(department).icon"
                        size="14"
                        start
                      />
                      {{ departmentTitle }}
                    </VChip>
                    <span
                      v-else
                      class="text-disabled font-italic"
                    >—</span>
                  </dd>
                </div>

                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('Job Title / Position') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0 text-break">
                    {{ jobTitle || '—' }}
                  </dd>
                </div>

                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('Roles & Authority') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0">
                    <div class="d-flex flex-wrap gap-1">
                      <VChip
                        v-if="isPrimary"
                        color="primary"
                        size="small"
                        label
                      >
                        <VIcon
                          icon="tabler-star-filled"
                          size="12"
                          start
                        />
                        {{ t('Primary Contact') }}
                      </VChip>
                      <VChip
                        v-if="isDecisionMaker"
                        color="warning"
                        size="small"
                        label
                      >
                        <VIcon
                          icon="tabler-crown"
                          size="12"
                          start
                        />
                        {{ t('Decision Maker') }}
                      </VChip>
                      <span
                        v-if="!isPrimary && !isDecisionMaker"
                        class="text-body-2 text-medium-emphasis"
                      >
                        {{ t('Standard Contact') }}
                      </span>
                    </div>
                  </dd>
                </div>
              </dl>
            </div>
          </VCard>

          <!-- Section Card 2: Direct Communication Channels -->
          <VCard
            variant="flat"
            border
            rounded="lg"
            class="wp-view-dialog__section overflow-hidden"
          >
            <div class="wp-view-dialog__section-header d-flex align-center gap-3 px-4 px-sm-5 py-3">
              <VAvatar
                color="primary"
                variant="tonal"
                rounded="lg"
                :size="smAndDown ? 34 : 38"
                class="flex-shrink-0"
              >
                <VIcon
                  icon="tabler-phone-call"
                  :size="smAndDown ? 18 : 20"
                />
              </VAvatar>
              <div class="flex-grow-1 min-w-0">
                <h3 class="text-subtitle-1 font-weight-bold mb-0 text-break">
                  {{ t('Direct Communication Channels') }}
                </h3>
                <div class="text-caption text-medium-emphasis text-break">
                  {{ t('Verified emails, direct telephone lines and messaging accounts') }}
                </div>
              </div>
            </div>
            <VDivider />
            <div class="px-4 px-sm-5 py-3">
              <dl class="wp-view-dialog__list">
                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('Email Address') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0">
                    <a
                      v-if="c.email"
                      :href="`mailto:${c.email}`"
                      class="text-decoration-none text-primary d-inline-flex align-center gap-1 text-break"
                    >
                      <VIcon
                        icon="tabler-mail"
                        size="14"
                        class="flex-shrink-0"
                      />
                      <span>{{ c.email }}</span>
                    </a>
                    <span
                      v-else
                      class="text-disabled font-italic"
                    >—</span>
                  </dd>
                </div>

                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('Direct Office Phone') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0">
                    <a
                      v-if="c.phone"
                      :href="`tel:${c.phone}`"
                      class="text-decoration-none text-primary d-inline-flex align-center gap-1 text-break"
                    >
                      <VIcon
                        icon="tabler-phone"
                        size="14"
                        class="flex-shrink-0"
                      />
                      <span>{{ c.phone }}</span>
                    </a>
                    <span
                      v-else
                      class="text-disabled font-italic"
                    >—</span>
                  </dd>
                </div>

                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('Mobile Phone') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0">
                    <a
                      v-if="c.mobile"
                      :href="`tel:${c.mobile}`"
                      class="text-decoration-none text-primary d-inline-flex align-center gap-1 text-break"
                    >
                      <VIcon
                        icon="tabler-device-mobile"
                        size="14"
                        class="flex-shrink-0"
                      />
                      <span>{{ c.mobile }}</span>
                    </a>
                    <span
                      v-else
                      class="text-disabled font-italic"
                    >—</span>
                  </dd>
                </div>

                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('WhatsApp') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0">
                    <VBtn
                      v-if="whatsappCleanNumber"
                      :href="`https://wa.me/${whatsappCleanNumber}`"
                      target="_blank"
                      rel="noopener noreferrer"
                      size="small"
                      variant="tonal"
                      color="success"
                      prepend-icon="tabler-brand-whatsapp"
                    >
                      {{ c.whatsapp_number || c.whatsappNumber || c.mobile || c.phone }}
                    </VBtn>
                    <span
                      v-else
                      class="text-disabled font-italic"
                    >—</span>
                  </dd>
                </div>

                <div class="wp-view-dialog__row">
                  <dt class="wp-view-dialog__dt text-caption text-medium-emphasis">
                    {{ t('LinkedIn Profile') }}
                  </dt>
                  <dd class="wp-view-dialog__dd text-body-2 font-weight-medium mb-0">
                    <VBtn
                      v-if="c.linkedin_url || c.linkedinUrl"
                      :href="c.linkedin_url || c.linkedinUrl"
                      target="_blank"
                      rel="noopener noreferrer"
                      size="small"
                      variant="tonal"
                      color="info"
                      prepend-icon="tabler-brand-linkedin"
                    >
                      {{ t('Open LinkedIn') }}
                    </VBtn>
                    <span
                      v-else
                      class="text-disabled font-italic"
                    >—</span>
                  </dd>
                </div>
              </dl>
            </div>
          </VCard>

          <!-- Section Card 3: Associated Locations & Addresses -->
          <VCard
            variant="flat"
            border
            rounded="lg"
            class="wp-view-dialog__section overflow-hidden"
          >
            <div class="wp-view-dialog__section-header d-flex align-center gap-3 px-4 px-sm-5 py-3">
              <VAvatar
                color="primary"
                variant="tonal"
                rounded="lg"
                :size="smAndDown ? 34 : 38"
                class="flex-shrink-0"
              >
                <VIcon
                  icon="tabler-map-pin"
                  :size="smAndDown ? 18 : 20"
                />
              </VAvatar>
              <div class="flex-grow-1 min-w-0">
                <h3 class="text-subtitle-1 font-weight-bold mb-0 text-break">
                  {{ t('Associated Addresses & Locations') }}
                </h3>
                <div class="text-caption text-medium-emphasis text-break">
                  {{ t('Business partner offices, branches and delivery sites') }}
                </div>
              </div>
            </div>
            <VDivider />
            <div class="px-4 px-sm-5 py-3">
              <div
                v-if="linkedAddresses.length > 0"
                class="d-flex flex-wrap gap-2"
              >
                <VChip
                  v-for="(addr, idx) in linkedAddresses"
                  :key="addr.id || idx"
                  size="small"
                  variant="tonal"
                  color="primary"
                  label
                  class="font-weight-medium"
                >
                  <VIcon
                    icon="tabler-map-pin"
                    size="14"
                    start
                  />
                  <span>{{ localizedValue(addr.label) || localizedValue(addr.address_street) || t('Address') }}</span>
                  <span
                    v-if="addr.city"
                    class="text-medium-emphasis ms-1"
                  >({{ localizedValue(addr.city) }})</span>
                </VChip>
              </div>
              <div
                v-else
                class="text-body-2 text-medium-emphasis font-italic"
              >
                {{ t('No specific address attached to this contact yet.') }}
              </div>
            </div>
          </VCard>

          <!-- Section Card 4: Notes & Remarks -->
          <VCard
            v-if="notes"
            variant="flat"
            border
            rounded="lg"
            class="wp-view-dialog__section overflow-hidden"
          >
            <div class="wp-view-dialog__section-header d-flex align-center gap-3 px-4 px-sm-5 py-3">
              <VAvatar
                color="primary"
                variant="tonal"
                rounded="lg"
                :size="smAndDown ? 34 : 38"
                class="flex-shrink-0"
              >
                <VIcon
                  icon="tabler-notes"
                  :size="smAndDown ? 18 : 20"
                />
              </VAvatar>
              <div class="flex-grow-1 min-w-0">
                <h3 class="text-subtitle-1 font-weight-bold mb-0 text-break">
                  {{ t('Notes & Remarks') }}
                </h3>
                <div class="text-caption text-medium-emphasis text-break">
                  {{ t('Additional remarks, languages spoken and preferences') }}
                </div>
              </div>
            </div>
            <VDivider />
            <div class="px-4 px-sm-5 py-3 text-body-2 text-break">
              {{ notes }}
            </div>
          </VCard>
        </div>
      </PerfectScrollbar>

      <!-- ── Fixed Footer matching WpWizardDialog (WpActionFooter) ────── -->
      <WpActionFooter
        bordered
        class="wp-view-dialog__footer"
      >
        <template #start>
          <div
            v-if="!smAndDown && c.id"
            class="d-flex align-center gap-2 text-caption text-medium-emphasis"
          >
            <VIcon
              icon="tabler-id"
              size="16"
            />
            <span>{{ t('Contact ID') }}: #{{ c.id }}</span>
          </div>
        </template>

        <VBtn
          :icon="smAndDown"
          variant="tonal"
          color="secondary"
          :aria-label="t('Close')"
          @click="handleClose"
        >
          <VIcon icon="tabler-x" />
          <span
            v-if="!smAndDown"
            class="ms-1"
          >{{ t('Close') }}</span>
          <VTooltip
            activator="parent"
            location="top"
          >
            {{ t('Close') }}
          </VTooltip>
        </VBtn>

        <VBtn
          v-if="canSetPrimary && !isPrimary && !readonly"
          :icon="smAndDown"
          variant="tonal"
          color="warning"
          :aria-label="t('Set as Main')"
          @click="handleSetPrimary"
        >
          <VIcon icon="tabler-star" />
          <span
            v-if="!smAndDown"
            class="ms-1"
          >{{ t('Set as Main') }}</span>
          <VTooltip
            activator="parent"
            location="top"
          >
            {{ t('Set as Main') }}
          </VTooltip>
        </VBtn>

        <VBtn
          v-if="canEdit && !readonly"
          :icon="smAndDown"
          color="primary"
          :aria-label="t('Edit Contact')"
          @click="handleEdit"
        >
          <VIcon icon="tabler-edit" />
          <span
            v-if="!smAndDown"
            class="ms-1"
          >{{ t('Edit Contact') }}</span>
          <VTooltip
            activator="parent"
            location="top"
          >
            {{ t('Edit Contact') }}
          </VTooltip>
        </VBtn>
      </WpActionFooter>
    </VCard>
  </VDialog>
</template>

<style scoped>
.wp-view-dialog__card {
  position: relative;
  overflow: hidden;
  max-block-size: min(92dvh, 840px);
}

.wp-view-dialog__header {
  flex: 0 0 auto;
  background:
    radial-gradient(ellipse at 100% 0%, rgba(var(--v-theme-primary), 0.12), transparent 60%),
    rgba(var(--v-theme-primary), 0.02);
  padding-block: 16px 14px;
  padding-inline: 20px;
}

.wp-view-dialog__avatar {
  box-shadow: 0 6px 18px rgba(var(--v-theme-primary), 0.25);
}

.wp-view-dialog__close-btn {
  transition: transform 0.2s ease, background-color 0.2s ease;
}

.wp-view-dialog__close-btn:hover {
  transform: scale(1.05);
}

.wp-view-dialog__body {
  --wp-edge: rgb(var(--v-theme-surface));
  --wp-edge-shade: rgba(var(--v-theme-on-surface), 0.14);

  overflow: hidden auto;
  flex: 1 1 auto;
  padding: 20px;
  background-attachment: local, local, scroll, scroll;
  background-image:
    linear-gradient(var(--wp-edge) 30%, transparent),
    linear-gradient(transparent, var(--wp-edge) 70%),
    radial-gradient(farthest-side at 50% 0, var(--wp-edge-shade), transparent),
    radial-gradient(farthest-side at 50% 100%, var(--wp-edge-shade), transparent);
  background-position: center top, center bottom, center top, center bottom;
  background-repeat: no-repeat;
  background-size: 100% 24px, 100% 24px, 100% 10px, 100% 10px;
  min-inline-size: 0;
  -webkit-overflow-scrolling: touch;
}

.wp-view-dialog__section {
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.wp-view-dialog__section-header {
  background:
    radial-gradient(ellipse at 100% 0%, rgba(var(--v-theme-primary), 0.08), transparent 60%),
    rgba(var(--v-theme-primary), 0.02);
}

/* Grid layout matching WpWizardReview: zero negative margin, responsive wrap */
.wp-view-dialog__list {
  display: grid;
  margin: 0;
  gap: 14px 20px;
  grid-template-columns: repeat(auto-fill, minmax(min(200px, 100%), 1fr));
}

.wp-view-dialog__row {
  min-inline-size: 0;
  overflow-wrap: anywhere;
}

.wp-view-dialog__dt {
  font-size: 0.75rem;
  letter-spacing: 0.2px;
  line-height: 1.3;
  margin-block-end: 4px;
}

.wp-view-dialog__dd {
  color: rgb(var(--v-theme-on-surface));
  line-height: 1.45;
}

@media (max-width: 599.98px) {
  .wp-view-dialog__card {
    border-radius: 0 !important;
    block-size: 100dvh !important;
    max-block-size: 100dvh !important;
    padding-block-end: env(safe-area-inset-bottom);
  }

  .wp-view-dialog__header {
    padding-block: calc(12px + env(safe-area-inset-top)) 10px !important;
    padding-inline: 14px !important;
  }

  .wp-view-dialog__body {
    padding: 14px !important;
  }

  .wp-view-dialog__list {
    gap: 12px 14px;
  }

  .wp-view-dialog__footer :deep(.wp-action-footer__actions) {
    justify-content: flex-end !important;
    gap: 8px !important;
    flex-wrap: nowrap !important;
  }

  .wp-view-dialog__footer :deep(.v-btn--icon) {
    flex: 0 0 auto !important;
    inline-size: 38px !important;
    block-size: 38px !important;
    min-inline-size: 38px !important;
  }
}
</style>
