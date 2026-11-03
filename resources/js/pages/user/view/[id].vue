<script setup>
import { useUserStore } from '@/stores/useUserStore'
import UserBioPanel from '@/views/user/view/UserBioPanel.vue'
import UserTabAccount from '@/views/user/view/UserTabAccount.vue'
import UserTabNotifications from '@/views/user/view/UserTabNotifications.vue'
import UserTabSecurity from '@/views/user/view/UserTabSecurity.vue'

definePage({
  path: '/user/view/:id',
  name: 'user-view-id',
  meta: {
    layout: 'default',
    module: 'user',
    action: 'read',
    subject: 'users',
  },
})

const route = useRoute('user-view-id')
const userTab = ref(null)

const tabs = [
  {
    icon: 'tabler-users',
    title: 'Account',
  },
  {
    icon: 'tabler-lock',
    title: 'Security',
  },
  {
    icon: 'tabler-bell',
    title: 'Notifications',
  },
]

const userStore = useUserStore()
const userData = ref(null)

const fetchUser = async () => {
  userData.value = await userStore.fetchUser(route.params.id)
}

await fetchUser()
</script>

<template>
  <VRow v-if="userData">
    <VCol
      cols="12"
      md="5"
      lg="4"
    >
      <UserBioPanel
        :user-data="userData"
        @refresh="fetchUser"
      />
    </VCol>

    <VCol
      cols="12"
      md="7"
      lg="8"
    >
      <VTabs
        v-model="userTab"
        class="v-tabs-pill"
      >
        <VTab
          v-for="tab in tabs"
          :key="tab.icon"
        >
          <VIcon
            :size="18"
            :icon="tab.icon"
            class="me-1"
          />
          <span>{{ tab.title }}</span>
        </VTab>
      </VTabs>

      <VWindow
        v-model="userTab"
        class="mt-6 disable-tab-transition"
        :touch="false"
      >
        <VWindowItem>
          <UserTabAccount />
        </VWindowItem>

        <VWindowItem>
          <UserTabSecurity />
        </VWindowItem>

        <VWindowItem>
          <UserTabNotifications />
        </VWindowItem>
      </VWindow>
    </VCol>
  </VRow>
  <div v-else>
    <VAlert
      type="error"
      variant="tonal"
    >
      Invoice with ID  {{ route.params.id }} not found!
    </VAlert>
  </div>
</template>
