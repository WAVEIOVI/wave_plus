<script setup>
/* eslint-disable camelcase, lines-around-comment */
/**
 * WpDocumentTraceabilitySection
 * ─────────────────────────────────────────────────────────────────────────────
 * Visual chain of linked upstream/downstream documents (e.g., IPR → RFQ → PO → GRN)
 * with clickable router-link chips/badges and an optional chronological audit timeline.
 *
 *   items / chain: [{ type, id, reference, status, to, route, current }]
 *   activity: [{ id, title, actor, at, icon, color }]
 *   bare: render without the WpSectionCard shell
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import WpSectionCard from '@/components/shared/layout/WpSectionCard.vue'

const props = defineProps({
  items: { type: Array, default: () => [] },
  /** Alias for `items` — accept `chain` from buildTraceability() output directly. */
  chain: { type: Array, default: null },
  activity: { type: Array, default: () => [] },
  title: { type: String, default: '' },
  activityTitle: { type: String, default: '' },
  bare: { type: Boolean, default: false },
  formatTimestamp: { type: Function, default: null },
})

const { t, locale } = useI18n()

const TYPE_META = {
  ipr: { icon: 'tabler-file-import', color: 'info' },
  rfq: { icon: 'tabler-scale', color: 'warning' },
  po: { icon: 'tabler-file-invoice', color: 'primary' },
  grn: { icon: 'tabler-package-import', color: 'success' },
  supplier_invoice: { icon: 'tabler-receipt', color: 'secondary' },
  stock_movement: { icon: 'tabler-arrows-exchange', color: 'secondary' },
}

// Accept either `chain` (alias) or `items`
const sourceItems = computed(() => props.chain ?? props.items ?? [])

const chainNodes = computed(() => (sourceItems.value || []).map((item, idx) => {
  const type = String(item?.type || '').toLowerCase()
  const meta = TYPE_META[type] || { icon: 'tabler-file-text', color: 'primary' }
  const target = item?.to || item?.route || null

  return {
    ...item,
    idx,
    type,
    icon: item?.icon || meta.icon,
    color: item?.color || meta.color,
    target: item?.current ? null : target,
    label: item?.reference || (item?.id ? `#${String(item.id).slice(-6)}` : '—'),
    typeLabel: t(`wp.docs.traceability.types.${type}`, type.toUpperCase()),
  }
}))

const fmt = value => {
  if (!value) return ''
  if (props.formatTimestamp) return props.formatTimestamp(value)

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) return String(value)

  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}
</script>

<template>
  <component
    :is="bare ? 'div' : WpSectionCard"
    v-bind="bare ? {} : { title: title || t('wp.docs.traceability.title'), icon: 'tabler-git-branch' }"
  >
    <div
      v-if="$slots.actions"
      class="d-flex flex-wrap gap-2 mb-4"
    >
      <slot name="actions" />
    </div>

    <div
      v-if="!chainNodes.length && !activity.length"
      class="text-body-2 text-medium-emphasis"
    >
      {{ t('wp.docs.traceability.empty') }}
    </div>

    <!-- Document Chain -->
    <div
      v-if="chainNodes.length"
      class="wp-trace-chain"
      role="list"
      :aria-label="t('wp.docs.traceability.chain_label')"
    >
      <template
        v-for="(node, index) in chainNodes"
        :key="`${node.type}-${node.id ?? index}`"
      >
        <VIcon
          v-if="index > 0"
          icon="tabler-arrow-right"
          size="18"
          class="text-disabled wp-trace-chain__arrow"
        />

        <component
          :is="node.target ? 'RouterLink' : 'div'"
          v-bind="node.target ? { to: node.target } : {}"
          role="listitem"
          class="wp-trace-node"
          :class="{
            'wp-trace-node--current': node.current,
            'wp-trace-node--link': Boolean(node.target),
          }"
        >
          <VAvatar
            :color="node.color"
            variant="tonal"
            size="34"
            rounded
          >
            <VIcon
              :icon="node.icon"
              size="18"
            />
          </VAvatar>

          <div class="wp-trace-node__body">
            <div class="d-flex align-center gap-1 flex-wrap">
              <span class="text-caption text-uppercase font-weight-bold text-medium-emphasis">
                {{ node.typeLabel }}
              </span>
              <VChip
                v-if="node.current"
                size="x-small"
                color="primary"
                variant="tonal"
                label
              >
                {{ t('wp.docs.traceability.current') }}
              </VChip>
            </div>
            <div class="text-body-2 font-weight-bold text-high-emphasis text-truncate">
              {{ node.label }}
            </div>
            <WpStatusChip
              v-if="node.status"
              :status="node.status"
              size="x-small"
              class="mt-1"
            />
          </div>
        </component>
      </template>
    </div>

    <slot />

    <!-- Activity Timeline -->
    <div
      v-if="activity.length"
      :class="{ 'mt-5 pt-4 border-t': chainNodes.length }"
    >
      <div class="text-subtitle-2 font-weight-bold mb-3">
        {{ activityTitle || t('wp.docs.traceability.activity_title') }}
      </div>

      <ol class="wp-trace-timeline">
        <li
          v-for="(ev, idx) in activity"
          :key="ev.id ?? idx"
          class="wp-trace-timeline__item"
        >
          <VAvatar
            :color="ev.color || 'primary'"
            variant="tonal"
            size="30"
          >
            <VIcon
              :icon="ev.icon || 'tabler-history'"
              size="16"
            />
          </VAvatar>
          <div class="wp-trace-timeline__body">
            <div class="text-body-2 font-weight-medium">
              {{ ev.title }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ [ev.actor, fmt(ev.at)].filter(Boolean).join(' · ') }}
            </div>
          </div>
        </li>
      </ol>
    </div>
  </component>
</template>

<style scoped>
.wp-trace-chain {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}

.wp-trace-node {
  display: flex;
  align-items: center;
  gap: 10px;
  min-block-size: 56px;
  padding: 8px 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: rgba(var(--v-theme-surface), 0.7);
  text-decoration: none;
  color: inherit;
  min-inline-size: 0;
}

.wp-trace-node--current {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.08);
}

.wp-trace-node--link:hover {
  border-color: rgb(var(--v-theme-primary));
}

.wp-trace-node__body {
  min-inline-size: 0;
}

.wp-trace-timeline {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.wp-trace-timeline__item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.wp-trace-timeline__body {
  min-inline-size: 0;
  flex: 1 1 auto;
}
</style>

