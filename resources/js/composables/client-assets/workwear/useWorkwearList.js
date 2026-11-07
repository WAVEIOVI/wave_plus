import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

export function useWorkwearList() {
  const store = useClientAssetStore()
  const { t } = useI18n()
  const route = useRoute()
  const router = useRouter()

  const validTabs = ['models', 'items', 'employees', 'assignments']
  const initialTab = validTabs.includes(route.query?.tab) ? route.query.tab : 'models'
  const activeTab = ref(initialTab)

  watch(activeTab, newTab => {
    if (route.query?.tab !== newTab) {
      router.replace({ query: { ...route.query, tab: newTab } })
    }
  })

  watch(() => route.query?.tab, newQueryTab => {
    if (newQueryTab && validTabs.includes(newQueryTab) && activeTab.value !== newQueryTab) {
      activeTab.value = newQueryTab
    }
  })

  const tabs = computed(() => [
    {
      id: 'models',
      title: t('Workwear Models'),
      icon: 'tabler-shirt',
      count: store.totalWorkwearModels || 0,
      description: t('Catalog specifications, PPE garments, and studio blueprints'),
    },
    {
      id: 'items',
      title: t('Workwear Items'),
      icon: 'tabler-hanger',
      count: store.totalWorkwearItems || 0,
      description: t('Tracked individual garments, QR codes, and stock availability'),
    },
    {
      id: 'employees',
      title: t('Client Employees'),
      icon: 'tabler-users',
      count: store.totalWorkwearEmployees || 0,
      description: t('Client employees, garment sizes, and body measurements'),
    },
    {
      id: 'assignments',
      title: t('Assignments & Tracking'),
      icon: 'tabler-arrows-TransferUpDown',
      count: store.totalWorkwearItems || 0,
      description: t('Assign workwear items to client employees and track handovers'),
    },
  ])

  return {
    activeTab,
    tabs,
  }
}
