<script setup>
defineProps({
  entries: { type: Array, default: () => [] },
  kiosk: { type: Boolean, default: false },
})

const initials = name =>
  String(name || '')
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map(part => part[0] || '')
    .join('')
    .toUpperCase() || 'HR'

const shortTime = value => (value ? String(value).substring(0, 5) : '—')
</script>

<template>
  <div class="kiosk-feed">
    <TransitionGroup
      name="kiosk-row"
      tag="div"
      class="kiosk-feed__list"
    >
      <div
        v-for="(entry, index) in entries"
        :key="entry.id"
        class="kiosk-feed__row"
        :class="{ 'kiosk-feed__row--latest': index === 0 }"
      >
        <VAvatar
          :size="kiosk ? 42 : 38"
          :color="entry.type === 'in' ? 'success' : 'warning'"
          variant="tonal"
        >
          <span class="text-caption font-weight-bold">{{ initials(entry.employee?.full_name) }}</span>
        </VAvatar>
        <div class="kiosk-feed__content">
          <div class="kiosk-feed__name">
            {{ entry.employee?.full_name || $t('hr.attendance.employee', 'Employee') }}
          </div>
          <div class="kiosk-feed__meta">
            {{ entry.employee?.employee_number }}
            <span v-if="!kiosk && entry.source"> · {{ entry.source }}</span>
          </div>
        </div>
        <div class="kiosk-feed__action">
          <div class="kiosk-feed__time">
            {{ shortTime(entry.time) }}
          </div>
          <!-- Text + icon so the direction never relies on colour alone -->
          <VChip
            :color="entry.type === 'in' ? 'success' : 'warning'"
            :prepend-icon="entry.type === 'in' ? 'tabler-door-enter' : 'tabler-door-exit'"
            size="x-small"
            variant="tonal"
          >
            {{ entry.type === 'in' ? $t('kiosk.in') : $t('kiosk.out') }}
          </VChip>
        </div>
      </div>
    </TransitionGroup>

    <div
      v-if="!entries.length"
      class="kiosk-feed__empty"
    >
      <VIcon
        icon="tabler-clock-hour-4"
        size="32"
        class="kiosk-feed__empty-icon"
      />
      <div class="kiosk-feed__empty-text">
        {{ $t('hr.attendance.no_punches_yet', 'No punches recorded today. Be the first!') }}
      </div>
    </div>
  </div>
</template>

<style scoped>
.kiosk-feed {
  overflow-y: auto;
  block-size: 100%;
  min-block-size: 0;
  scrollbar-gutter: stable;
}

.kiosk-feed__list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.kiosk-feed__row {
  display: flex;
  align-items: center;
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  gap: 12px;
  min-block-size: 66px;
  transition: border-color 0.2s ease, background-color 0.2s ease;
}

.kiosk-feed__row--latest {
  border-color: rgba(var(--v-theme-primary), 0.55);
  background: rgba(var(--v-theme-primary), 0.05);
}

.kiosk-feed__row:hover {
  border-color: rgba(var(--v-theme-primary), 0.5);
}

.kiosk-feed__content {
  flex: 1;
  min-inline-size: 0;
}

.kiosk-feed__name {
  overflow: hidden;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.92rem;
  font-weight: 600;
  line-height: 1.3;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.kiosk-feed__meta {
  overflow: hidden;
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 0.75rem;
  margin-block-start: 4px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.kiosk-feed__action {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

.kiosk-feed__time {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.9rem;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  line-height: 1.2;
}

.kiosk-feed__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 24px;
  min-block-size: 100%;
  text-align: center;
}

.kiosk-feed__empty-icon {
  margin-block-end: 12px;
  opacity: 0.5;
}

.kiosk-feed__empty-text {
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 0.88rem;
}

.kiosk-row-enter-active {
  transition: opacity 0.35s ease, transform 0.35s ease;
}

.kiosk-row-enter-from {
  opacity: 0;
  transform: translateY(-10px);
}

.kiosk-row-move {
  transition: transform 0.35s ease;
}

@media (max-width: 600px) {
  .kiosk-feed {
    block-size: auto;
    min-block-size: 180px;
  }

  .kiosk-feed__row {
    padding: 10px;
    gap: 10px;
    min-block-size: 62px;
  }

  .kiosk-feed__name {
    font-size: 0.88rem;
  }

  .kiosk-feed__meta {
    font-size: 0.72rem;
  }

  .kiosk-feed__time {
    font-size: 0.85rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .kiosk-row-enter-active,
  .kiosk-row-move {
    transition: none;
  }
}
</style>
