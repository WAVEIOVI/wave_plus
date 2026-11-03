/* eslint-disable camelcase */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * Composable for managing equipment lifecycle dates, expiration calculations,
 * non-blocking date validation warnings, and projected maintenance timeline milestones.
 *
 * @param {Object} options
 * @param {import('vue').Ref<Object>|Function} options.form - Form reactive ref or getter function
 * @param {import('vue').Ref<Object>|Function} [options.blueprintDetails] - Optional blueprint details ref or getter
 * @param {Function} [options.updateField] - Optional callback to set a form field: (key, value) => void
 */
export function useEquipmentTimeline({ form, blueprintDetails = () => null, updateField }) {
  const { t } = useI18n()

  const getForm = () => (typeof form === 'function' ? form() : form.value)
  const getBp = () => (typeof blueprintDetails === 'function' ? blueprintDetails() : blueprintDetails.value)

  const blueprintInterventionCycles = computed(() => {
    const bp = getBp()
    if (!bp) return []

    const list = [...(bp.intervention_cycles || [])]

    return list.sort((a, b) => {
      const aIns = (a.intervention_type || '').toLowerCase() === 'inspection' ? 0 : 1
      const bIns = (b.intervention_type || '').toLowerCase() === 'inspection' ? 0 : 1

      return aIns - bIns
    })
  })

  const calculatedExpiryDate = computed(() => {
    const f = getForm()
    const baseStr = f?.manufacture_date || f?.install_date
    if (!baseStr) return null

    const years = Number(f?.shelf_life_years) || Number(getBp()?.shelf_life_years) || null
    if (!years) return null

    const d = new Date(baseStr)

    d.setFullYear(d.getFullYear() + years)

    return d.toISOString().substring(0, 10)
  })

  const lifeProgressPercentage = computed(() => {
    const f = getForm()
    const baseStr = f?.manufacture_date || f?.install_date
    if (!baseStr || !calculatedExpiryDate.value) return 0

    const start = new Date(baseStr).getTime()
    const end = new Date(calculatedExpiryDate.value).getTime()
    const now = Date.now()

    if (end <= start) return 100
    const pct = Math.round(((now - start) / (end - start)) * 100)

    return Math.min(100, Math.max(0, pct))
  })

  const lifeRemainingLabel = computed(() => {
    if (!calculatedExpiryDate.value) return ''

    const months = Math.round((new Date(calculatedExpiryDate.value).getTime() - Date.now()) / (1000 * 60 * 60 * 24 * 30.44))

    if (months < 0) return t('Expired {count} months ago', { count: Math.abs(months) })
    if (months < 24) return t('{count} months remaining', { count: months })

    return t('{years} years remaining', { years: (months / 12).toFixed(1) })
  })

  const projectedTimelineEvents = computed(() => {
    const events = []
    const todayStr = new Date().toISOString().substring(0, 10)
    const active = getForm()
    if (!active) return []

    if (active.manufacture_date) {
      events.push({
        id: 'mfg',
        title: t('Manufactured'),
        date: active.manufacture_date,
        icon: 'tabler-building-factory-2',
        color: 'secondary',
      })
    }

    if (active.install_date) {
      events.push({
        id: 'install',
        title: t('Installed & Commissioned'),
        date: active.install_date,
        icon: 'tabler-circle-check',
        color: active.install_date <= todayStr ? 'success' : 'info',
      })
    }

    const baseDateStr = active.manufacture_date || active.install_date
    if (baseDateStr && blueprintInterventionCycles.value.length) {
      const maxYears = Number(active.shelf_life_years) || Number(getBp()?.shelf_life_years) || 10

      blueprintInterventionCycles.value.forEach((cycle, cIdx) => {
        const firstMonths = Number(cycle.first_interval_months) || 12
        const recurMonths = Number(cycle.recurring_interval_months) || firstMonths
        let currentMonths = firstMonths
        let iteration = 1

        while (currentMonths <= maxYears * 12) {
          const eventDate = new Date(baseDateStr)

          eventDate.setMonth(eventDate.getMonth() + currentMonths)

          const dateStr = eventDate.toISOString().substring(0, 10)
          const isPast = dateStr < todayStr

          events.push({
            id: `cycle_${cIdx}_${iteration}`,
            title: `${cycle.intervention_type || 'Service'} #${iteration}`,
            date: dateStr,
            icon: 'tabler-clipboard-check',
            color: isPast ? 'secondary' : 'warning',
            isCycle: true,
          })
          currentMonths += recurMonths
          iteration++
        }
      })
    }

    if (active.last_inspection_date) {
      events.push({
        id: 'last_insp',
        title: t('Last Inspection'),
        date: active.last_inspection_date,
        icon: 'tabler-clipboard-list',
        color: 'info',
      })
    }

    if (active.next_inspection_date) {
      events.push({
        id: 'next_insp',
        title: t('Next Inspection Due'),
        date: active.next_inspection_date,
        icon: 'tabler-clock-check',
        color: active.next_inspection_date <= todayStr ? 'error' : 'warning',
      })
    }

    if (calculatedExpiryDate.value) {
      events.push({
        id: 'expiry',
        title: t('End of Useful Life'),
        date: calculatedExpiryDate.value,
        icon: 'tabler-alert-circle',
        color: calculatedExpiryDate.value <= todayStr ? 'error' : 'secondary',
      })
    }

    return events.sort((a, b) => new Date(a.date) - new Date(b.date))
  })

  const timelineGroupedByYear = computed(() => {
    const groups = {}
    const todayStr = new Date().toISOString().substring(0, 10)

    projectedTimelineEvents.value.forEach(evt => {
      const yr = evt.date ? evt.date.substring(0, 4) : t('General')
      if (!groups[yr]) groups[yr] = []
      groups[yr].push({
        ...evt,
        isPast: evt.date < todayStr,
        isToday: evt.date === todayStr,
      })
    })

    return Object.entries(groups)
      .sort(([yA], [yB]) => yA.localeCompare(yB))
      .map(([year, events]) => ({ year, events }))
  })

  const dateWarnings = computed(() => {
    const f = getForm()
    if (!f) return []
    const list = []

    if (f.manufacture_date && f.install_date && f.install_date < f.manufacture_date) {
      list.push({ field: 'install_date', message: t('Installation date is before the manufacture date.') })
    }
    if (f.install_date && f.last_inspection_date && f.last_inspection_date < f.install_date) {
      list.push({ field: 'last_inspection_date', message: t('Last inspection is before the installation date.') })
    }
    if (f.last_inspection_date && f.next_inspection_date && f.next_inspection_date < f.last_inspection_date) {
      list.push({ field: 'next_inspection_date', message: t('Next inspection is before the last inspection.') })
    }
    if (f.install_date && f.decommissioning_date && f.decommissioning_date < f.install_date) {
      list.push({ field: 'decommissioning_date', message: t('Decommissioning is before the installation date.') })
    }

    return list
  })

  const dateWarningFor = field => {
    const w = dateWarnings.value.find(item => item.field === field)
    
    return w ? [w.message] : []
  }

  const setInstallDateToday = () => {
    const today = new Date().toISOString().substring(0, 10)
    if (updateField) updateField('install_date', today)
    else if (form.value) form.value.install_date = today
  }

  const setNextInspectionInOneYear = () => {
    const f = getForm()
    const base = f?.last_inspection_date || f?.install_date || new Date().toISOString().substring(0, 10)
    const d = new Date(base)

    d.setFullYear(d.getFullYear() + 1)

    const nextVal = d.toISOString().substring(0, 10)

    if (updateField) updateField('next_inspection_date', nextVal)
    else if (form.value) form.value.next_inspection_date = nextVal
  }

  return {
    blueprintInterventionCycles,
    calculatedExpiryDate,
    lifeProgressPercentage,
    lifeRemainingLabel,
    projectedTimelineEvents,
    timelineGroupedByYear,
    dateWarnings,
    dateWarningFor,
    setInstallDateToday,
    setNextInspectionInOneYear,
  }
}
