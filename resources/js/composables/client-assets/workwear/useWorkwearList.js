import { useClientAssetStore } from '@/stores/useClientAssetStore'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

export function useWorkwearList() {
  const store = useClientAssetStore()
  const { t } = useI18n()
  const route = useRoute()
  const router = useRouter()

  const validTabs = ['items', 'models', 'employees']
  const initialTab = validTabs.includes(route.query?.tab) ? route.query.tab : 'items'
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
      id: 'items',
      title: t('Workwear Items'),
      icon: 'tabler-hanger',
      count: store.totalWorkwearItems || 0,
      description: t('Tracked individual garments, serials, and assignment states'),
    },
    {
      id: 'models',
      title: t('Garment Models'),
      icon: 'tabler-shirt',
      count: store.totalWorkwearModels || 0,
      description: t('Catalog specifications, PPE garments, and versions'),
    },
    {
      id: 'employees',
      title: t('Staff Assignments'),
      icon: 'tabler-users',
      count: store.totalWorkwearEmployees || 0,
      description: t('Client employees, active allocations, and return history'),
    },
  ])

  return {
    activeTab,
    tabs,
  }
}
