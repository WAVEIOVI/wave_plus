/* eslint-disable camelcase */
import { localizedValue } from '@/composables/useLocaleField'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTheme } from 'vuetify'

export const LIFECYCLE_META = {
  operational: ['Operational', 'success'],
  active: ['Active', 'success'],
  in_service: ['Operational', 'success'],
  under_construction: ['Under Construction', 'warning'],
  under_maintenance: ['Under Maintenance', 'warning'],
  in_maintenance: ['Under Maintenance', 'warning'],
  stored: ['In Storage', 'secondary'],
  draft: ['Draft', 'secondary'],
  inactive: ['Inactive', 'secondary'],
  decommissioned: ['Decommissioned', 'error'],
  scrapped: ['Scrapped', 'error'],
  disposed: ['Disposed', 'error'],
}

export const COMPLIANCE_META = {
  compliant: ['Compliant', 'success'],
  due_soon: ['Due Soon', 'warning'],
  overdue: ['Overdue', 'error'],
  non_compliant: ['Non-Compliant', 'error'],
  not_applicable: ['Not Applicable', 'secondary'],
}

export const CRITICALITY_META = {
  low: ['Low', 'secondary'],
  medium: ['Medium', 'info'],
  high: ['High', 'warning'],
  critical: ['Critical', 'error'],
}

export function useSiteEquipmentAnalytics(equipmentRef, areasFlatRef) {
  const { t } = useI18n()
  const vuetifyTheme = useTheme()
  const isDark = computed(() => vuetifyTheme.current.value.dark)

  const groupMode = ref('general')
  const selectedBlueprintId = ref(null)
  const selectedOptionKey = ref(null)

  const humanize = value => (value || '').toString().replaceAll('_', ' ')

  const labelOf = (val, fallback = t('Unclassified')) => {
    if (val == null || val === '')
      return fallback
    if (typeof val === 'object')
      return localizedValue(val) || val.name_text || fallback

    return String(val)
  }

  const optionDisplay = ov => {
    if (!ov) return t('Unspecified')
    if (ov.display_value) return ov.display_value
    if (ov.value_text) return ov.value_text
    if (ov.value_number != null && ov.value_number !== '') return String(ov.value_number)
    if (ov.value_boolean != null) return ov.value_boolean ? t('Yes') : t('No')
    if (ov.value_date) return ov.value_date

    return t('Unspecified')
  }

  const optionName = ov => labelOf(ov?.option_name || ov?.blueprint_option?.name, t('Option'))

  const describeFrom = meta => key => {
    const entry = meta[key]
    if (entry) return { label: t(entry[0]), color: entry[1] }
    const unset = key === 'not_set' || key === 'unset'

    return { label: unset ? t('Unspecified') : humanize(key), color: 'secondary' }
  }

  const isOperational = e => {
    const s = (e.lifecycle_status || e.status || '').toLowerCase()

    return s === 'operational' || s === 'active' || s === 'in_service'
  }

  const isMaintenance = e => {
    const s = (e.lifecycle_status || '').toLowerCase()

    return s === 'under_maintenance' || s === 'in_maintenance' || s === 'maintenance_due'
  }

  const isOverdue = e => {
    const s = (e.compliance_status || '').toLowerCase()

    return s === 'overdue' || s === 'non_compliant'
  }

  const kpis = computed(() => {
    const list = equipmentRef.value || []
    const total = list.length
    const operational = list.filter(isOperational).length
    const maintenance = list.filter(isMaintenance).length
    const overdue = list.filter(isOverdue).length
    const stored = list.filter(e => e.lifecycle_status === 'stored' || e.lifecycle_status === 'draft').length
    const compliant = list.filter(e => e.compliance_status === 'compliant').length
    const unassigned = list.filter(e => !e.area_id && !e.area?.id).length
    const classes = new Set(list.map(e => e.equipment_class_id).filter(Boolean))
    const blueprints = new Set(list.map(e => e.blueprint_id).filter(Boolean))
    const compliancePct = total > 0 ? Math.round((compliant / total) * 100) : 100

    return {
      total,
      operational,
      maintenance,
      overdue,
      stored,
      compliant,
      unassigned,
      classCount: classes.size,
      blueprintCount: blueprints.size,
      compliancePct,
    }
  })

  const groupModeItems = computed(() => [
    { title: t('General'), short: t('General'), value: 'general', icon: 'tabler-chart-pie' },
    { title: t('Equipment Class'), short: t('Class'), value: 'class', icon: 'tabler-category' },
    { title: t('Blueprint'), short: t('Blueprint'), value: 'blueprint', icon: 'tabler-schema' },
    { title: t('Blueprint Options'), short: t('Options'), value: 'options', icon: 'tabler-adjustments' },
  ])

  const kpiTiles = computed(() => [
    {
      key: 'total',
      label: t('Total Assets'),
      value: kpis.value.total,
      hint: `${kpis.value.unassigned} ${t('unassigned')}`,
      icon: 'tabler-fire-extinguisher',
      color: 'primary',
      valueClass: '',
    },
    {
      key: 'operational',
      label: t('Operational'),
      value: kpis.value.operational,
      hint: t('In regular operation'),
      icon: 'tabler-circle-check',
      color: 'success',
      valueClass: 'text-success',
    },
    {
      key: 'maintenance',
      label: t('Under Maintenance'),
      value: kpis.value.maintenance,
      hint: t('Temporarily out of service'),
      icon: 'tabler-tool',
      color: 'warning',
      valueClass: 'text-warning',
    },
    {
      key: 'compliance',
      label: t('Compliance Rate'),
      value: `${kpis.value.compliancePct}%`,
      hint: `${kpis.value.compliant} ${t('compliant')}`,
      icon: 'tabler-shield-check',
      color: kpis.value.compliancePct >= 90 ? 'success' : (kpis.value.compliancePct >= 70 ? 'warning' : 'error'),
      valueClass: kpis.value.compliancePct >= 90 ? 'text-success' : (kpis.value.compliancePct >= 70 ? 'text-warning' : 'text-error'),
    },
    {
      key: 'overdue',
      label: t('Overdue'),
      value: kpis.value.overdue,
      hint: t('Needs attention'),
      icon: 'tabler-alert-triangle',
      color: 'error',
      valueClass: 'text-error',
    },
    {
      key: 'blueprints',
      label: t('Blueprints'),
      value: kpis.value.blueprintCount,
      hint: `${kpis.value.classCount} ${t('classes')}`,
      icon: 'tabler-schema',
      color: 'info',
      valueClass: '',
    },
  ])

  const siteBlueprints = computed(() => {
    const map = new Map()

    for (const eq of equipmentRef.value || []) {
      const id = eq.blueprint_id || eq.blueprint?.id
      if (!id) continue
      if (!map.has(id)) {
        map.set(id, {
          id,
          title: labelOf(eq.blueprint?.name || eq.model_name, `${t('Blueprint')} #${id}`),
          value: id,
          count: 0,
        })
      }
      map.get(id).count += 1
    }

    return [...map.values()].sort((a, b) => b.count - a.count)
  })

  const optionKeysForBlueprint = computed(() => {
    if (!selectedBlueprintId.value) return []
    const keys = new Map()

    for (const eq of equipmentRef.value || []) {
      const bpId = eq.blueprint_id || eq.blueprint?.id
      if (bpId !== selectedBlueprintId.value) continue
      for (const ov of eq.option_values || []) {
        const key = ov.blueprint_option_id || optionName(ov)
        if (!keys.has(key)) {
          keys.set(key, {
            value: key,
            title: optionName(ov),
          })
        }
      }
    }

    return [...keys.values()]
  })

  watch(groupMode, mode => {
    if (mode === 'options' && !selectedBlueprintId.value && siteBlueprints.value.length)
      selectedBlueprintId.value = siteBlueprints.value[0].value
  })

  watch([selectedBlueprintId, optionKeysForBlueprint], () => {
    const keys = optionKeysForBlueprint.value
    if (!keys.find(k => k.value === selectedOptionKey.value))
      selectedOptionKey.value = keys[0]?.value || null
  })

  const countBy = (list, getKey, describe = key => ({ label: key })) => {
    const map = new Map()

    for (const eq of list) {
      const key = getKey(eq)
      if (!map.has(key)) {
        const { label, color } = describe(key)

        map.set(key, { key, label, color, count: 0, operational: 0, overdue: 0, maintenance: 0 })
      }

      const row = map.get(key)

      row.count += 1
      if (isOperational(eq)) row.operational += 1
      if (isOverdue(eq)) row.overdue += 1
      if (isMaintenance(eq)) row.maintenance += 1
    }

    return [...map.values()].sort((a, b) => b.count - a.count)
  }

  const groupedStats = computed(() => {
    const list = equipmentRef.value || []

    if (groupMode.value === 'class') {
      return countBy(list, eq => labelOf(eq.equipment_class?.name || eq.equipment_class?.name_text, t('Unclassified')))
    }

    if (groupMode.value === 'blueprint') {
      return countBy(list, eq => labelOf(eq.blueprint?.name || eq.model_name, t('No blueprint')))
    }

    if (groupMode.value === 'options') {
      const filtered = list.filter(eq => (eq.blueprint_id || eq.blueprint?.id) === selectedBlueprintId.value)
      if (!selectedOptionKey.value) {
        return countBy(filtered, () => t('No option values'))
      }

      return countBy(filtered, eq => {
        const match = (eq.option_values || []).find(ov =>
          (ov.blueprint_option_id || optionName(ov)) === selectedOptionKey.value,
        )

        return match ? optionDisplay(match) : t('Unspecified')
      })
    }

    return countBy(list, eq => (eq.lifecycle_status || 'operational').toLowerCase(), describeFrom(LIFECYCLE_META))
  })

  const complianceStats = computed(() => countBy(
    equipmentRef.value || [],
    eq => (eq.compliance_status || 'not_set').toLowerCase(),
    describeFrom(COMPLIANCE_META),
  ))

  const criticalityStats = computed(() => countBy(
    equipmentRef.value || [],
    eq => (eq.criticality || 'unset').toLowerCase(),
    describeFrom(CRITICALITY_META),
  ))

  const zoneStats = computed(() => {
    const map = {}

    for (const a of areasFlatRef.value || [])
      map[a.id] = { key: a.id, label: localizedValue(a.name) || a.name, count: 0, operational: 0, overdue: 0, maintenance: 0 }

    map.unassigned = { key: 'unassigned', label: t('Unassigned'), count: 0, operational: 0, overdue: 0, maintenance: 0 }

    for (const eq of equipmentRef.value || []) {
      const id = eq.area_id || eq.area?.id
      const row = (id && map[id]) ? map[id] : map.unassigned

      row.count += 1
      if (isOperational(eq)) row.operational += 1
      if (isOverdue(eq)) row.overdue += 1
      if (isMaintenance(eq)) row.maintenance += 1
    }

    return Object.values(map).filter(z => z.count > 0).sort((a, b) => b.count - a.count).slice(0, 10)
  })

  const themeColor = (name, fallback) => vuetifyTheme.current.value.colors[name] || fallback

  const withAlpha = (hex, alpha) =>
    (/^#[0-9a-f]{6}$/i.test(hex) ? `${hex}${Math.round(alpha * 255).toString(16).padStart(2, '0')}` : hex)

  const chartText = computed(() => withAlpha(themeColor('on-surface', isDark.value ? '#cfd3ec' : '#5d596c'), 0.68))
  const chartTitle = computed(() => withAlpha(themeColor('on-surface', isDark.value ? '#ffffff' : '#4b465c'), 0.9))
  const gridColor = computed(() => withAlpha(themeColor('on-surface', isDark.value ? '#ffffff' : '#000000'), 0.08))

  const palette = computed(() => [
    themeColor('primary', '#7367f0'),
    themeColor('success', '#28c76f'),
    themeColor('warning', '#ff9f43'),
    themeColor('error', '#ea5455'),
    themeColor('info', '#00cfe8'),
    themeColor('secondary', '#a8aaae'),
    '#826bce',
    '#ff6f61',
  ])

  const donutColors = computed(() => groupedStats.value.map((row, i) =>
    (groupMode.value === 'general' && row.color
      ? themeColor(row.color, palette.value[i % palette.value.length])
      : palette.value[i % palette.value.length]),
  ))

  const pct = n => (kpis.value.total ? Math.round((n / kpis.value.total) * 100) : 0)

  const donutSeries = computed(() => groupedStats.value.map(r => r.count))

  const donutOptions = computed(() => ({
    chart: { type: 'donut', parentHeightOffset: 0, toolbar: { show: false } },
    labels: groupedStats.value.map(r => r.label),
    colors: donutColors.value,
    stroke: { width: 0 },
    dataLabels: { enabled: false },
    legend: {
      position: 'bottom',
      markers: { offsetX: -3 },
      labels: { colors: chartText.value },
    },
    plotOptions: {
      pie: {
        donut: {
          size: '70%',
          labels: {
            show: true,
            value: {
              fontSize: '1.15rem',
              fontWeight: '600',
              color: chartTitle.value,
              formatter: val => `${val}`,
            },
            total: {
              show: true,
              fontSize: '0.8rem',
              label: t('Assets'),
              color: chartText.value,
              formatter: () => `${kpis.value.total}`,
            },
          },
        },
      },
    },
  }))

  const barCategories = computed(() => {
    if (groupMode.value === 'general')
      return zoneStats.value.map(z => z.label)

    return groupedStats.value.slice(0, 12).map(r => r.label)
  })

  const barSeries = computed(() => [{
    name: t('Assets'),
    data: groupMode.value === 'general'
      ? zoneStats.value.map(z => z.count)
      : groupedStats.value.slice(0, 12).map(r => r.count),
  }])

  const donutSummary = computed(() => groupedStats.value.map(r => `${r.label}: ${r.count}`).join(', '))

  const barSummary = computed(() => barCategories.value
    .map((label, i) => `${label}: ${barSeries.value[0].data[i]}`)
    .join(', '))

  const barChartHeight = computed(() => Math.min(360, Math.max(240, barCategories.value.length * 28)))

  const barOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false } },
    colors: [themeColor('primary', '#7367f0')],
    plotOptions: {
      bar: { horizontal: true, borderRadius: 4, barHeight: '62%' },
    },
    dataLabels: { enabled: true, style: { fontSize: '11px', colors: ['#fff'] } },
    xaxis: {
      categories: barCategories.value,
      labels: { style: { colors: chartText.value } },
    },
    yaxis: {
      labels: {
        style: { colors: chartText.value },
        maxWidth: 160,
        formatter: value => (value && value.length > 22 ? `${value.slice(0, 21)}…` : value),
      },
    },
    grid: { borderColor: gridColor.value },
  }))

  return {
    groupMode,
    selectedBlueprintId,
    selectedOptionKey,
    kpis,
    kpiTiles,
    groupModeItems,
    siteBlueprints,
    optionKeysForBlueprint,
    groupedStats,
    complianceStats,
    criticalityStats,
    zoneStats,
    donutOptions,
    donutSeries,
    donutSummary,
    barCategories,
    barOptions,
    barSeries,
    barSummary,
    barChartHeight,
    pct,
  }
}
