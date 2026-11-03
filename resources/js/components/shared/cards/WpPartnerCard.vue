<script setup>
/**
 * WpPartnerCard
 * ─────────────────────────────────────────────────────────────────────────────
 * Business Partner summary for Purchase Orders, Sales Orders, quotations,
 * invoices … It only presents the partner it is given; selection goes through
 * the shared Search & Select dialog (`search-config`) or `request-select`.
 *
 * <WpPartnerCard
 *   :partner="order.client"
 *   partner-label="Client"
 *   :fields="{ name: 'legal_name', code: 'client_id', category: 'client_category', logo: 'logo_url' }"
 *   :details="[{ label: 'VAT', value: 'tax_id', icon: 'tabler-id', copyable: true }]"
 *   :search-config="clientPicker"
 *   status-type="client"
 *   :can-edit="$can('update', 'BusinessPartners')"
 *   :locked="order.isConfirmed" locked-reason="…"
 *   @change="p => order.client = p" @view="open" @edit="edit"
 * />
 *
 * Props   partner · partnerLabel · fields · details · statusType · searchConfig · loading · compact
 *         canChange · canView · canEdit · readonly · locked · lockedReason · clearable
 * Emits   change(partner) · clear · request-select · view(partner) · edit(partner)
 * Slots   badges · details · actions · (empty-action via WpStateBlock)
 *
 * `fields`: { name, tradeName, code, type, category, status, logo, logoSource, logoDomain,
 *             email, phone } – dotted paths or functions; sensible defaults for the
 * WAVE PLUS partner API (legal_name, commercial_name, client_id …).
 *
 * `details` rows: { label, value, icon?, copyable?, href? }
 *   value     dotted path or function (same as `fields`)
 *   copyable  show a copy button on the row (email and phone are copyable by default)
 *   href      string, or (value, partner) => string, turns the value into a link
 *
 * UX notes
 *  - The partner name is a real button when `canView` is true (opens `view`).
 *  - Email and phone are links (mailto: / tel:) with one-click copy.
 *  - Colour carries meaning only: status chip and lock state. Type / category / code stay neutral.
 */
import { resolveAccessor } from '@/composables/shared/useSearchSelect'
import { localizedValue } from '@/composables/useLocaleField'

const props = defineProps({
  partner: { type: Object, default: null },
  partnerLabel: { type: String, default: '' },
  fields: { type: Object, default: () => ({}) },
  details: { type: Array, default: () => [] },
  statusType: { type: String, default: '' },
  searchConfig: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
  canChange: { type: Boolean, default: true },
  canView: { type: Boolean, default: true },
  canEdit: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  /** Selection is frozen by business rules (e.g. confirmed document). */
  locked: { type: Boolean, default: false },
  lockedReason: { type: String, default: '' },
  clearable: { type: Boolean, default: false },
})

const emit = defineEmits(['change', 'clear', 'request-select', 'view', 'edit'])

const { t } = useI18n()

const F = computed(() => ({
  name: 'legal_name',
  tradeName: 'commercial_name',
  code: 'client_id',
  type: 'type',
  category: 'category',
  status: 'status',
  logo: 'logo_url',
  logoSource: 'logo_source',
  logoDomain: 'logo_domain',
  email: 'primary_email',
  phone: 'primary_phone',
  ...props.fields,
}))

const get = key => (props.partner ? resolveAccessor(F.value[key], props.partner) : undefined)

// Translatable fields (`{ en, fr }`) are resolved with the shared locale helper.
const text = key => {
  const v = get(key)

  return v && typeof v === 'object' ? localizedValue(v) : v
}

const isEmpty = v => v === undefined || v === null || v === ''
const dialable = v => String(v).replace(/[^\d+]/g, '')

const name = computed(() => text('name') || '')

// Only show the trade name when it adds something.
const tradeName = computed(() => {
  const v = text('tradeName')

  return v && v !== name.value ? v : ''
})

const detailRows = computed(() => {
  const p = props.partner

  if (!p) return []

  const email = get('email')
  const phone = get('phone')

  const base = [
    {
      key: 'email',
      label: t('wp.cards.partner.email'),
      value: email,
      icon: 'tabler-mail',
      href: isEmpty(email) ? undefined : `mailto:${email}`,
      copyable: true,
    },
    {
      key: 'phone',
      label: t('wp.cards.partner.phone'),
      value: phone,
      icon: 'tabler-phone',
      href: isEmpty(phone) ? undefined : `tel:${dialable(phone)}`,
      copyable: true,
    },
  ]

  const extra = props.details.map((d, i) => {
    const value = resolveAccessor(d.value, p)

    return {
      key: `extra-${i}`,
      copyable: false,
      ...d,
      value,
      href: typeof d.href === 'function' ? d.href(value, p) : d.href,
    }
  })

  return [...base, ...extra].filter(r => !isEmpty(r.value))
})

const hasTags = computed(() => !!(get('status') || get('code') || get('type') || get('category')))

const pickerOpen = ref(false)
const state = computed(() => (props.loading ? 'loading' : props.partner ? 'ready' : 'empty'))
const changeable = computed(() => props.canChange && !props.readonly && !props.locked)
const label = computed(() => props.partnerLabel || t('wp.cards.partner.default_label'))

const requestSelect = () => {
  emit('request-select')
  if (props.searchConfig) pickerOpen.value = true
}

const onPicked = partner => {
  if (partner) emit('change', partner)
}

const onNameClick = () => {
  if (props.canView && props.partner) emit('view', props.partner)
}
</script>

<template>
  <VCard
    class="wp-partner-card"
    :class="{ 'wp-partner-card--locked': locked, 'wp-partner-card--compact': compact }"
    variant="flat"
    border
    rounded="lg"
    role="group"
    :aria-label="label"
    :aria-busy="loading"
  >
    <VCardText class="pb-2">
      <WpSectionHeader
        :title="label"
        icon="tabler-building-store"
        :level="3"
      >
        <template #actions>
          <div class="wp-partner-card__actions">
            <VChip
              v-if="locked"
              size="small"
              variant="tonal"
              color="warning"
              prepend-icon="tabler-lock"
            >
              {{ t('wp.cards.locked') }}
            </VChip>

            <slot name="actions" />

            <VBtn
              v-if="partner && canView"
              icon
              size="small"
              variant="text"
              :aria-label="t('wp.cards.view')"
              @click="emit('view', partner)"
            >
              <VIcon icon="tabler-eye" />
              <VTooltip
                activator="parent"
                location="top"
              >
                {{ t('wp.cards.view') }}
              </VTooltip>
            </VBtn>

            <VBtn
              v-if="partner && canEdit && !readonly"
              icon
              size="small"
              variant="text"
              :aria-label="t('wp.cards.edit')"
              @click="emit('edit', partner)"
            >
              <VIcon icon="tabler-edit" />
              <VTooltip
                activator="parent"
                location="top"
              >
                {{ t('wp.cards.edit') }}
              </VTooltip>
            </VBtn>

            <VBtn
              v-if="partner && changeable && clearable"
              icon
              size="small"
              variant="text"
              :aria-label="t('wp.cards.clear')"
              @click="emit('clear')"
            >
              <VIcon icon="tabler-x" />
              <VTooltip
                activator="parent"
                location="top"
              >
                {{ t('wp.cards.clear') }}
              </VTooltip>
            </VBtn>

            <VBtn
              v-if="partner && changeable"
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-replace"
              @click="requestSelect"
            >
              {{ t('wp.cards.change') }}
            </VBtn>
          </div>
        </template>
      </WpSectionHeader>
    </VCardText>

    <VCardText class="pt-0">
      <WpStateBlock
        :state="state"
        skeleton="list-item-avatar-three-line"
        compact
      >
        <template #default>
          <!-- Identity -->
          <div class="wp-partner-card__identity">
            <WpEntityLogo
              :src="get('logo') || ''"
              :name="name"
              :source="get('logoSource') || ''"
              :domain="get('logoDomain') || ''"
              :size="compact ? 40 : 56"
              border-radius="12px"
            />

            <div class="wp-partner-card__who">
              <component
                :is="canView ? 'button' : 'div'"
                v-bind="canView ? { type: 'button', title: t('wp.cards.view') } : {}"
                class="wp-partner-card__name text-subtitle-1 font-weight-bold"
                :class="{ 'wp-partner-card__name--link': canView }"
                @click="onNameClick"
              >
                {{ name }}
              </component>

              <div
                v-if="tradeName"
                class="wp-partner-card__trade text-body-2 text-medium-emphasis"
              >
                {{ tradeName }}
              </div>

              <div
                v-if="hasTags || $slots.badges"
                class="wp-partner-card__tags"
              >
                <WpStatusChip
                  v-if="get('status')"
                  :status="get('status')"
                  :type="statusType"
                />
                <VChip
                  v-if="get('code')"
                  size="x-small"
                  label
                  variant="outlined"
                  prepend-icon="tabler-hash"
                  class="wp-partner-card__code"
                >
                  {{ get('code') }}
                </VChip>
                <VChip
                  v-if="get('type')"
                  size="x-small"
                  label
                  variant="tonal"
                >
                  {{ get('type') }}
                </VChip>
                <VChip
                  v-if="get('category')"
                  size="x-small"
                  label
                  variant="tonal"
                  class="text-capitalize"
                >
                  {{ get('category') }}
                </VChip>
                <slot
                  name="badges"
                  :partner="partner"
                />
              </div>
            </div>
          </div>

          <!-- Contact & details -->
          <template v-if="!compact && (detailRows.length || $slots.details)">
            <slot
              name="details"
              :partner="partner"
              :rows="detailRows"
            >
              <dl class="wp-partner-card__details">
                <div
                  v-for="row in detailRows"
                  :key="row.key || row.label"
                  class="wp-partner-card__row"
                >
                  <span
                    v-if="row.icon"
                    class="wp-partner-card__row-icon"
                    aria-hidden="true"
                  >
                    <VIcon
                      :icon="row.icon"
                      size="16"
                    />
                  </span>

                  <div class="wp-partner-card__row-body">
                    <dt class="text-caption text-medium-emphasis">
                      {{ row.label }}
                    </dt>
                    <dd class="text-body-2 font-weight-medium">
                      <a
                        v-if="row.href"
                        :href="row.href"
                        class="wp-partner-card__link"
                      >{{ row.value }}</a>
                      <span
                        v-else
                        class="wp-partner-card__value"
                      >{{ row.value }}</span>

                      <WpCopyButton
                        v-if="row.copyable"
                        class="wp-partner-card__copy"
                        :value="row.value"
                        :label="t('wp.cards.copy_value', { label: row.label })"
                      />
                    </dd>
                  </div>
                </div>
              </dl>
            </slot>
          </template>

          <!-- Locked explanation -->
          <p
            v-if="locked && lockedReason"
            class="wp-partner-card__note text-body-2"
            role="note"
          >
            <VIcon
              icon="tabler-lock"
              size="16"
            />
            <span>{{ lockedReason }}</span>
          </p>
        </template>

        <template #empty-action>
          <VBtn
            v-if="changeable"
            color="primary"
            variant="tonal"
            prepend-icon="tabler-user-search"
            @click="requestSelect"
          >
            {{ t('wp.cards.partner.select', { label }) }}
          </VBtn>
        </template>
      </WpStateBlock>
    </VCardText>

    <WpSearchSelectDialog
      v-if="searchConfig"
      v-model="pickerOpen"
      :entity-label="label"
      icon="tabler-user-search"
      :selected="partner"
      v-bind="searchConfig"
      @select="onPicked"
    />
  </VCard>
</template>

<style scoped>
/* Header actions: one tight cluster that wraps instead of overflowing */
.wp-partner-card__actions {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 4px;
}

/* Identity */
.wp-partner-card__identity {
  display: flex;
  align-items: flex-start;
  gap: 14px;
}

.wp-partner-card__who {
  display: grid;
  flex: 1 1 auto;
  justify-items: start;
  min-inline-size: 0;
  row-gap: 2px;
}

.wp-partner-card__name,
.wp-partner-card__trade {
  overflow-wrap: anywhere;
}

.wp-partner-card__name {
  padding: 0;
  margin: 0;
  border: 0;
  background: none;
  color: inherit;
  text-align: start;
}

.wp-partner-card__name--link {
  border-radius: 4px;
  cursor: pointer;
  transition: color 0.15s ease;
}

.wp-partner-card__name--link:hover {
  color: rgb(var(--v-theme-primary));
  text-decoration: underline;
  text-underline-offset: 3px;
}

.wp-partner-card__name--link:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.wp-partner-card__tags {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin-block-start: 8px;
}

.wp-partner-card__code {
  font-variant-numeric: tabular-nums;
}

/* Contact panel: every row is an action */
.wp-partner-card__details {
  display: grid;
  padding: 12px 14px;
  margin: 16px 0 0;
  background: rgba(var(--v-theme-on-surface), 0.035);
  border-radius: 12px;
  gap: 14px 20px;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 210px), 1fr));
}

.wp-partner-card--compact .wp-partner-card__details {
  display: none;
}

.wp-partner-card__row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  min-inline-size: 0;
}

.wp-partner-card__row-icon {
  display: grid;
  flex: none;
  background: rgba(var(--v-theme-primary), 0.1);
  block-size: 28px;
  border-radius: 8px;
  color: rgb(var(--v-theme-primary));
  inline-size: 28px;
  place-items: center;
}

.wp-partner-card__row-body {
  min-inline-size: 0;
}

.wp-partner-card__row-body dd {
  display: flex;
  align-items: center;
  margin: 0;
  gap: 2px;
}

.wp-partner-card__link,
.wp-partner-card__value {
  overflow-wrap: anywhere;
}

.wp-partner-card__link {
  border-radius: 3px;
  color: inherit;
  text-decoration: none;
}

.wp-partner-card__link:hover {
  color: rgb(var(--v-theme-primary));
  text-decoration: underline;
  text-underline-offset: 3px;
}

.wp-partner-card__link:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

/* Copy buttons appear on hover / keyboard focus, always visible on touch */
.wp-partner-card__copy {
  opacity: 0;
  transition: opacity 0.15s ease;
}

.wp-partner-card__row:hover .wp-partner-card__copy,
.wp-partner-card__row:focus-within .wp-partner-card__copy {
  opacity: 1;
}

@media (hover: none) {
  .wp-partner-card__copy {
    opacity: 1;
  }
}

/* Locked */
.wp-partner-card__note {
  display: flex;
  align-items: flex-start;
  padding: 10px 12px;
  margin: 12px 0 0;
  background: rgba(var(--v-theme-warning), 0.1);
  border-radius: 10px;
  color: rgb(var(--v-theme-warning));
  gap: 8px;
}

.wp-partner-card__note > span {
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
}

@media (prefers-reduced-motion: reduce) {
  .wp-partner-card__name--link,
  .wp-partner-card__copy {
    transition: none;
  }
}
</style>
