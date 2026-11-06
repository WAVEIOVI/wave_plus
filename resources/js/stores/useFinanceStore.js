import { $api } from '@/utils/api'
import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useFinanceStore = defineStore('finance', () => {
  // ─── Dashboard ──────────────────────────────────────
  const dashboardKpis = ref(null)
  const isDashboardLoading = ref(false)

  const fetchDashboard = async () => {
    isDashboardLoading.value = true
    try {
      const res = await $api('/finance/dashboard')
      dashboardKpis.value = res.data
      return res.data
    } finally {
      isDashboardLoading.value = false
    }
  }

  // ─── Financial Accounts ─────────────────────────────
  const accounts = ref([])
  const isAccountsLoading = ref(false)

  const fetchAccounts = async (params = {}) => {
    isAccountsLoading.value = true
    try {
      const res = await $api('/finance/financial-accounts', { params })
      accounts.value = res.data
      return res.data
    } finally {
      isAccountsLoading.value = false
    }
  }

  const createAccount = async data => {
    const res = await $api('/finance/financial-accounts', {
      method: 'POST',
      body: data,
    })
    await fetchAccounts()
    return res.data
  }

  const reconcileAccount = async (accountId, transactionIds) => {
    const res = await $api(`/finance/financial-accounts/${accountId}/reconcile`, {
      method: 'POST',
      body: { transaction_ids: transactionIds },
    })
    await fetchAccounts()
    return res.data
  }

  // ─── Financial Periods ──────────────────────────────
  const periods = ref([])
  const currentPeriod = ref(null)
  const isPeriodsLoading = ref(false)

  const fetchPeriods = async (params = {}) => {
    isPeriodsLoading.value = true
    try {
      const res = await $api('/finance/financial-periods', { params })
      periods.value = res.data?.data || res.data || []
      return res.data
    } finally {
      isPeriodsLoading.value = false
    }
  }

  const fetchCurrentPeriod = async () => {
    const res = await $api('/finance/financial-periods/current')
    currentPeriod.value = res.data
    return res.data
  }

  const closePeriod = async periodId => {
    const res = await $api(`/finance/financial-periods/${periodId}/close`, { method: 'POST' })
    await fetchPeriods()
    return res.data
  }

  const reopenPeriod = async periodId => {
    const res = await $api(`/finance/financial-periods/${periodId}/reopen`, { method: 'POST' })
    await fetchPeriods()
    return res.data
  }

  // ─── Cost Centers ───────────────────────────────────
  const costCenters = ref([])
  const isCostCentersLoading = ref(false)

  const fetchCostCenters = async (params = {}) => {
    isCostCentersLoading.value = true
    try {
      const res = await $api('/finance/cost-centers', { params })
      costCenters.value = res.data
      return res.data
    } finally {
      isCostCentersLoading.value = false
    }
  }

  const createCostCenter = async data => {
    const res = await $api('/finance/cost-centers', {
      method: 'POST',
      body: data,
    })
    await fetchCostCenters()
    return res.data
  }

  // ─── Budgets ────────────────────────────────────────
  const budgets = ref([])
  const isBudgetsLoading = ref(false)

  const fetchBudgets = async (params = {}) => {
    isBudgetsLoading.value = true
    try {
      const res = await $api('/finance/budgets', { params })
      budgets.value = res.data?.data || res.data || []
      return res.data
    } finally {
      isBudgetsLoading.value = false
    }
  }

  const createBudget = async data => {
    const res = await $api('/finance/budgets', {
      method: 'POST',
      body: data,
    })
    await fetchBudgets()
    return res.data
  }

  const recalculateBudget = async budgetId => {
    const res = await $api(`/finance/budgets/${budgetId}/recalculate`, { method: 'POST' })
    await fetchBudgets()
    return res.data
  }

  // ─── Profitability ──────────────────────────────────
  const profitabilityOverview = ref(null)
  const isProfitabilityLoading = ref(false)

  const fetchProfitability = async (params = {}) => {
    isProfitabilityLoading.value = true
    try {
      const res = await $api('/finance/profitability/overview', { params })
      profitabilityOverview.value = res.data
      return res.data
    } finally {
      isProfitabilityLoading.value = false
    }
  }

  const fetchSoProfitability = async soId => {
    const res = await $api(`/finance/profitability/sales-order/${soId}`)
    return res.data
  }

  // ─── Accounting Foundation ──────────────────────────
  const chartOfAccounts = ref([])
  const journalEntries = ref([])
  const isAccountingLoading = ref(false)

  const fetchChartOfAccounts = async () => {
    isAccountingLoading.value = true
    try {
      const res = await $api('/finance/accounting/chart-of-accounts')
      chartOfAccounts.value = res.data
      return res.data
    } finally {
      isAccountingLoading.value = false
    }
  }

  const fetchJournalEntries = async (params = {}) => {
    isAccountingLoading.value = true
    try {
      const res = await $api('/finance/accounting/journal-entries', { params })
      journalEntries.value = res.data?.data || res.data || []
      return res.data
    } finally {
      isAccountingLoading.value = false
    }
  }

  const createJournalEntry = async data => {
    const res = await $api('/finance/accounting/journal-entries', {
      method: 'POST',
      body: data,
    })
    await fetchJournalEntries()
    return res.data
  }

  return {
    dashboardKpis,
    isDashboardLoading,
    fetchDashboard,
    accounts,
    isAccountsLoading,
    fetchAccounts,
    createAccount,
    reconcileAccount,
    periods,
    currentPeriod,
    isPeriodsLoading,
    fetchPeriods,
    fetchCurrentPeriod,
    closePeriod,
    reopenPeriod,
    costCenters,
    isCostCentersLoading,
    fetchCostCenters,
    createCostCenter,
    budgets,
    isBudgetsLoading,
    fetchBudgets,
    createBudget,
    recalculateBudget,
    profitabilityOverview,
    isProfitabilityLoading,
    fetchProfitability,
    fetchSoProfitability,
    chartOfAccounts,
    journalEntries,
    isAccountingLoading,
    fetchChartOfAccounts,
    fetchJournalEntries,
    createJournalEntry,
  }
})
