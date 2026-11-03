<script setup>
/* eslint-disable lines-around-comment */
/**
 * WpWizardReview
 * ─────────────────────────────────────────────────────────────────────────────
 * Final "review & confirm" summary generated from the step definitions: every
 * visible field of every step with its formatted value and an "Edit" shortcut
 * that jumps back to the owning step. No per-wizard template needed.
 *
 * field.format?.(value, form) overrides the default formatting; select-like
 * fields resolve their option title (multi-values render as chips); booleans
 * become Yes/No; password fields are masked; fields inside a step section are
 * grouped under the section title; steps that still have errors are flagged.
 * A summary banner on top says "ready" or "N issues" with a jump to the first one.
 *
 * Slot: `extra` – additional summary content (totals, warnings…).
 */
import { collectFields, isEmptyValue } from '@/composables/shared/useWizard'
import { useDisplay } from 'vuetify'

const props = defineProps({
  steps: { type: Array, required: true },
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },

  /** Server / general error to show above the summary. */
  generalError: { type: String, default: '' },
})

const emit = defineEmits(['edit'])

const { t } = useI18n()
const { xs } = useDisplay()

const PASSWORD_MASK = '••••••••'

const optionsOf = field => (typeof field.options === 'function' ? field.options(props.form) : field.options || [])

const titleOf = (field, options, v) => {
  const hit = options.find(o => (o !== null && typeof o === 'object' ? o.value : o) === v)

  if (hit && typeof hit === 'object') return hit.title
  if (field.remote?.initialItems) {
    const item = field.remote.initialItems.find(i => i[field.remote.itemValue || 'value'] === v)

    if (item) return item[field.remote.itemTitle || 'title']
  }

  return v
}

/** → { text, items } — `items` is set for multi-values so they render as chips. */
const describe = field => {
  const value = props.form[field.key]

  if (typeof field.format === 'function') {
    const out = field.format(value, props.form)

    return { text: out === null || out === undefined ? '' : String(out), items: [] }
  }
  if (isEmptyValue(value)) return { text: '', items: [] }
  if (typeof value === 'boolean') return { text: t(value ? 'wp.wizard.yes' : 'wp.wizard.no'), items: [] }
  if (field.type === 'password') return { text: PASSWORD_MASK, items: [] }

  const options = optionsOf(field)

  if (Array.isArray(value)) return { text: '', items: value.map(v => String(titleOf(field, options, v))) }

  return { text: String(titleOf(field, options, value)), items: [] }
}

const groups = computed(() => {
  const total = props.steps.filter(s => !s.isReview).length

  return props.steps
    .map((step, stepIndex) => {
      if (step.isReview) return null

      // Root fields first, then one bucket per section (collectFields keeps that order).
      const sections = []

      collectFields(step, props.form)
        .filter(({ field }) => field.review !== false && field.type !== 'custom')
        .forEach(({ field, section }) => {
          const id = section ? (section.id ?? section.title) : '__root'
          let bucket = sections.find(s => s.id === id)

          if (!bucket) {
            bucket = { id, title: section?.title || '', rows: [] }
            sections.push(bucket)
          }

          const { text, items } = describe(field)

          bucket.rows.push({
            key: field.key,
            label: field.label || field.key,
            text,
            items,
            isBoolean: typeof props.form[field.key] === 'boolean',
            boolVal: Boolean(props.form[field.key]),
            error: props.errors[field.key] || '',
          })
        })

      const rows = sections.flatMap(s => s.rows)

      if (!rows.length) return null

      return {
        id: step.id,
        title: step.title,
        subtitle: step.subtitle || step.description,
        icon: step.icon,
        stepIndex,
        total,
        sections,
        issues: rows.filter(r => r.error).length,
      }
    })
    .filter(Boolean)
})

const totalIssues = computed(() => groups.value.reduce((sum, g) => sum + g.issues, 0))
const firstIssueIndex = computed(() => groups.value.find(g => g.issues > 0)?.stepIndex ?? -1)
</script>

<template>
  <div class="wp-wizard-review d-flex flex-column gap-4">
    <VAlert
      v-if="generalError"
      type="error"
      variant="tonal"
      density="comfortable"
      role="alert"
    >
      {{ generalError }}
    </VAlert>

    <!-- Summary banner -->
    <div
      v-if="groups.length"
      class="wp-wizard-review__summary"
      :class="totalIssues ? 'is-error' : 'is-ready'"
      role="status"
    >
      <VAvatar
        :color="totalIssues ? 'error' : 'success'"
        variant="tonal"
        rounded="lg"
        size="42"
        class="flex-shrink-0"
      >
        <VIcon
          :icon="totalIssues ? 'tabler-alert-octagon' : 'tabler-circle-check'"
          size="24"
        />
      </VAvatar>
      <div class="flex-grow-1 min-w-0">
        <div class="text-subtitle-1 font-weight-bold">
          {{ totalIssues ? t('wp.wizard.review_issues', { count: totalIssues }) : t('wp.wizard.review_ready') }}
        </div>
        <div class="text-caption text-medium-emphasis">
          {{ t('wp.wizard.review_hint') }}
        </div>
      </div>
      <VBtn
        v-if="totalIssues && firstIssueIndex >= 0"
        size="small"
        color="error"
        variant="flat"
        class="flex-shrink-0"
        append-icon="tabler-arrow-right"
        @click="emit('edit', firstIssueIndex)"
      >
        {{ t('wp.wizard.fix_now') }}
      </VBtn>
    </div>

    <VCard
      v-for="(group, n) in groups"
      :key="group.id"
      variant="flat"
      border
      rounded="lg"
      class="wp-wizard-review__card overflow-hidden"
      :class="{ 'wp-wizard-review__card--error': group.issues > 0 }"
      :style="{ '--wp-stagger': n }"
    >
      <div class="wp-wizard-review__header d-flex align-center gap-3 px-4 px-sm-5 py-3">
        <VAvatar
          :color="group.issues > 0 ? 'error' : 'primary'"
          variant="tonal"
          rounded="lg"
          size="38"
          class="flex-shrink-0"
        >
          <VIcon
            :icon="group.issues > 0 ? 'tabler-alert-triangle' : (group.icon || 'tabler-file-check')"
            size="20"
          />
        </VAvatar>

        <div class="flex-grow-1 min-w-0">
          <div class="d-flex align-center gap-2">
            <h3 class="text-subtitle-1 font-weight-bold mb-0 text-truncate">
              {{ group.title }}
            </h3>
            <VChip
              v-if="group.issues > 0"
              size="x-small"
              variant="tonal"
              color="error"
              class="font-weight-semibold flex-shrink-0"
            >
              {{ t('wp.wizard.to_fix', { count: group.issues }) }}
            </VChip>
            <VChip
              v-else
              size="x-small"
              variant="tonal"
              color="primary"
              class="font-weight-semibold flex-shrink-0 d-none d-sm-inline-flex"
            >
              {{ t('wp.wizard.step_of', { current: group.stepIndex + 1, total: group.total }) }}
            </VChip>
          </div>
          <div
            v-if="group.subtitle"
            class="text-caption text-medium-emphasis text-truncate"
          >
            {{ group.subtitle }}
          </div>
        </div>

        <VBtn
          size="small"
          :variant="group.issues > 0 ? 'flat' : 'tonal'"
          :color="group.issues > 0 ? 'error' : 'primary'"
          class="flex-shrink-0"
          :icon="xs ? 'tabler-pencil' : undefined"
          :prepend-icon="xs ? undefined : 'tabler-pencil'"
          :aria-label="`${t('wp.wizard.edit_step')}: ${group.title}`"
          @click="emit('edit', group.stepIndex)"
        >
          <template v-if="!xs">
            {{ t('wp.wizard.edit_step') }}
          </template>
        </VBtn>
      </div>

      <VDivider />

      <div class="wp-wizard-review__sections">
        <section
          v-for="section in group.sections"
          :key="section.id"
          class="wp-wizard-review__section"
        >
          <h4
            v-if="section.title"
            class="wp-wizard-review__section-title"
          >
            {{ section.title }}
          </h4>

          <dl class="wp-wizard-review__list">
            <div
              v-for="row in section.rows"
              :key="row.key"
              class="wp-wizard-review__row"
              :class="{ 'is-error': row.error }"
            >
              <dt class="wp-wizard-review__dt text-caption text-medium-emphasis mb-1">
                {{ row.label }}
              </dt>
              <dd class="wp-wizard-review__dd text-body-2 font-weight-medium mb-0">
                <VChip
                  v-if="row.isBoolean"
                  size="x-small"
                  :color="row.boolVal ? 'success' : 'secondary'"
                  :prepend-icon="row.boolVal ? 'tabler-check' : 'tabler-x'"
                  variant="tonal"
                  class="font-weight-medium"
                >
                  {{ row.text }}
                </VChip>
                <div
                  v-else-if="row.items.length"
                  class="d-flex flex-wrap gap-1"
                >
                  <VChip
                    v-for="(item, k) in row.items"
                    :key="k"
                    size="x-small"
                    variant="tonal"
                    class="font-weight-medium"
                  >
                    {{ item }}
                  </VChip>
                </div>
                <span
                  v-else-if="row.text !== ''"
                  class="wp-wizard-review__text"
                >{{ row.text }}</span>
                <span
                  v-else
                  class="text-disabled font-italic"
                >{{ t('wp.wizard.empty_value') }}</span>

                <div
                  v-if="row.error"
                  class="d-flex align-center gap-1 text-caption text-error mt-1"
                  role="alert"
                >
                  <VIcon
                    icon="tabler-alert-circle"
                    size="14"
                  />
                  <span>{{ row.error }}</span>
                </div>
              </dd>
            </div>
          </dl>
        </section>
      </div>
    </VCard>

    <slot name="extra" />
  </div>
</template>

<style scoped>
/* Summary banner */
.wp-wizard-review__summary {
  display: flex;
  align-items: center;
  border: 1px solid transparent;
  border-radius: 10px;
  gap: 14px;
  padding-block: 14px;
  padding-inline: 16px;
}

.wp-wizard-review__summary.is-ready {
  border-color: rgba(var(--v-theme-success), 0.25);
  background: rgba(var(--v-theme-success), 0.06);
}

.wp-wizard-review__summary.is-error {
  border-color: rgba(var(--v-theme-error), 0.3);
  background: rgba(var(--v-theme-error), 0.06);
}

/* Cards enter one after another */
.wp-wizard-review__card {
  animation: wp-review-in 0.32s cubic-bezier(0.2, 0.8, 0.2, 1) both;
  animation-delay: calc(min(var(--wp-stagger, 0), 6) * 45ms);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.wp-wizard-review__card:hover {
  box-shadow: 0 4px 18px rgba(var(--v-theme-on-surface), 0.06);
}

@keyframes wp-review-in {
  from {
    opacity: 0;
    transform: translateY(8px);
  }

  to {
    opacity: 1;
    transform: none;
  }
}

.wp-wizard-review__card--error {
  border-color: rgba(var(--v-theme-error), 0.45);
}

.wp-wizard-review__header {
  background:
    radial-gradient(ellipse at 100% 0%, rgba(var(--v-theme-primary), 0.08), transparent 60%),
    rgba(var(--v-theme-primary), 0.02);
}

.wp-wizard-review__card--error .wp-wizard-review__header {
  background:
    radial-gradient(ellipse at 100% 0%, rgba(var(--v-theme-error), 0.1), transparent 60%),
    rgba(var(--v-theme-error), 0.02);
}

.wp-wizard-review__sections {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding-block: 16px;
  padding-inline: 16px;
}

@media (min-width: 600px) {
  .wp-wizard-review__sections {
    padding-inline: 20px;
  }
}

/* Section heading: a quiet label with a hairline, so grouped fields read as one unit */
.wp-wizard-review__section-title {
  display: flex;
  align-items: center;
  color: rgb(var(--v-theme-primary));
  font-size: 0.8125rem;
  font-weight: 600;
  gap: 10px;
  line-height: 1.2;
  margin-block-end: 12px;
}

.wp-wizard-review__section-title::after {
  flex: 1 1 auto;
  background: rgba(var(--v-border-color), var(--v-border-opacity));
  block-size: 1px;
  content: "";
}

.wp-wizard-review__list {
  display: grid;
  margin: 0;
  gap: 16px 24px;
  grid-template-columns: repeat(auto-fill, minmax(min(220px, 100%), 1fr));
}

.wp-wizard-review__row {
  min-inline-size: 0;
  overflow-wrap: anywhere;
}

/* Rows with a problem get a soft highlight so they're found at a glance */
.wp-wizard-review__row.is-error {
  border-radius: 8px;
  background: rgba(var(--v-theme-error), 0.05);
  box-shadow: inset 3px 0 0 rgb(var(--v-theme-error));
  margin-block: -6px;
  margin-inline: -8px;
  padding-block: 6px;
  padding-inline: 8px;
}

[dir="rtl"] .wp-wizard-review__row.is-error {
  box-shadow: inset -3px 0 0 rgb(var(--v-theme-error));
}

.wp-wizard-review__dt {
  font-size: 0.75rem;
  letter-spacing: 0.2px;
}

.wp-wizard-review__dd {
  color: rgb(var(--v-theme-on-surface));
  line-height: 1.4;
}

/* Keep the line breaks of long text answers */
.wp-wizard-review__text {
  white-space: pre-line;
}

@media (prefers-reduced-motion: reduce) {
  .wp-wizard-review__card {
    animation: none;
    transition: none;
  }
}
</style>
