/* eslint-disable camelcase */
import { localizedValue } from '@/composables/useLocaleField'
import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

export const EQUIPMENT_STATUS_LABELS = {
  operational: 'Operational',
  active: 'Active',
  under_construction: 'Under Construction',
  under_maintenance: 'Under Maintenance',
  stored: 'In Storage',
  inactive: 'Inactive',
  draft: 'Draft',
  decommissioned: 'Decommissioned',
  scrapped: 'Scrapped',
  disposed: 'Disposed',
}

export function useSiteEquipment(targetSiteId) {
  const store = useClientAssetStore()
  const { t } = useI18n()

  const loadingEquipment = ref(false)
  const siteEquipment = ref([])
  const equipmentViewMode = ref('cards')

  const fetchEquipment = async () => {
    const siteId = typeof targetSiteId === 'function' ? targetSiteId() : (targetSiteId?.value ?? targetSiteId)
    if (!siteId) return

    loadingEquipment.value = true
    try {
      const res = await store.fetchEquipment({ site_id: siteId, itemsPerPage: -1 })

      siteEquipment.value = res.data || (Array.isArray(res) ? res : [])
    }
    catch (e) {
      console.error('Error fetching site equipment:', e)
    }
    finally {
      loadingEquipment.value = false
    }
  }

  const equipmentKPIs = computed(() => {
    const total = siteEquipment.value.length
    const operational = siteEquipment.value.filter(e => e.lifecycle_status === 'operational' || e.lifecycle_status === 'active').length
    const maintenance = siteEquipment.value.filter(e => e.lifecycle_status === 'under_maintenance').length
    const overdue = siteEquipment.value.filter(e => e.compliance_status === 'overdue' || e.compliance_status === 'non_compliant').length
    const compliant = siteEquipment.value.filter(e => e.compliance_status === 'compliant').length
    const compliancePct = total > 0 ? Math.round((compliant / total) * 100) : 100

    return { total, operational, maintenance, overdue, compliant, compliancePct }
  })

  const equipmentTitle = eq => localizedValue(eq.model_name) || localizedValue(eq.blueprint?.name) || localizedValue(eq.name) || t('Asset')

  const equipmentStatusLabel = stat => {
    const key = (stat || 'active').toLowerCase()

    return EQUIPMENT_STATUS_LABELS[key] ? t(EQUIPMENT_STATUS_LABELS[key]) : key.replaceAll('_', ' ')
  }

  const equipmentTableColumns = [
    { key: 'name', title: t('Asset / Tag'), mobile: 'title' },
    { key: 'type', title: t('Type / Model') },
    { key: 'area', title: t('Location / Zone') },
    { key: 'status', title: t('Status') },
    { key: 'actions', title: t('Actions'), align: 'end', mobile: 'hide' },
  ]

  return {
    loadingEquipment,
    siteEquipment,
    equipmentViewMode,
    equipmentKPIs,
    equipmentTitle,
    equipmentStatusLabel,
    equipmentTableColumns,
    fetchEquipment,
  }
}
