import { $api } from '@/utils/api'
import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useFinancePaymentStore = defineStore('financePayments', () => {
  // ─── Payments ───────────────────────────────────────
  const payments = ref([])
  const paymentsTotal = ref(0)
  const isPaymentsLoading = ref(false)

  const fetchPayments = async (params = {}) => {
    isPaymentsLoading.value = true
    try {
      const res = await $api('/api/finance/payments', { params })
      payments.value = res.data?.data || []
      paymentsTotal.value = res.data?.total || 0
      return res.data
    } finally {
      isPaymentsLoading.value = false
    }
  }

  const createPayment = async data => {
    const res = await $api('/api/finance/payments', {
      method: 'POST',
      body: data,
    })
    await fetchPayments()
    return res.data
  }

  const confirmPayment = async paymentId => {
    const res = await $api(`/api/finance/payments/${paymentId}/confirm`, { method: 'POST' })
    await fetchPayments()
    return res.data
  }

  const allocatePayment = async (paymentId, allocations) => {
    const res = await $api(`/api/finance/payments/${paymentId}/allocate`, {
      method: 'POST',
      body: { allocations },
    })
    await fetchPayments()
    return res.data
  }

  // ─── Expenses ───────────────────────────────────────
  const expenses = ref([])
  const expensesTotal = ref(0)
  const isExpensesLoading = ref(false)

  const fetchExpenses = async (params = {}) => {
    isExpensesLoading.value = true
    try {
      const res = await $api('/api/finance/expenses', { params })
      expenses.value = res.data?.data || []
      expensesTotal.value = res.data?.total || 0
      return res.data
    } finally {
      isExpensesLoading.value = false
    }
  }

  const createExpense = async data => {
    const res = await $api('/api/finance/expenses', {
      method: 'POST',
      body: data,
    })
    await fetchExpenses()
    return res.data
  }

  const postExpense = async expenseId => {
    const res = await $api(`/api/finance/expenses/${expenseId}/post`, { method: 'POST' })
    await fetchExpenses()
    return res.data
  }

  const cancelExpense = async expenseId => {
    const res = await $api(`/api/finance/expenses/${expenseId}/cancel`, { method: 'POST' })
    await fetchExpenses()
    return res.data
  }

  return {
    payments,
    paymentsTotal,
    isPaymentsLoading,
    fetchPayments,
    createPayment,
    confirmPayment,
    allocatePayment,
    expenses,
    expensesTotal,
    isExpensesLoading,
    fetchExpenses,
    createExpense,
    postExpense,
    cancelExpense,
  }
})
