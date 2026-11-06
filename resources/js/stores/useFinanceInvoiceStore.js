import { $api } from '@/utils/api'
import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useFinanceInvoiceStore = defineStore('financeInvoices', () => {
  // ─── Sales Invoices (AR) ────────────────────────────
  const salesInvoices = ref([])
  const salesInvoicesTotal = ref(0)
  const isSalesInvoicesLoading = ref(false)
  const salesInvoiceAging = ref(null)

  const fetchSalesInvoices = async (params = {}) => {
    isSalesInvoicesLoading.value = true
    try {
      const res = await $api('/finance/sales-invoices', { params })
      salesInvoices.value = res.data?.data || []
      salesInvoicesTotal.value = res.data?.total || 0
      return res.data
    } finally {
      isSalesInvoicesLoading.value = false
    }
  }

  const fetchSalesInvoiceAging = async (clientId = null) => {
    const res = await $api('/finance/sales-invoices/aging', {
      params: clientId ? { client_id: clientId } : {},
    })
    salesInvoiceAging.value = res.data
    return res.data
  }

  const createSalesInvoice = async data => {
    const res = await $api('/finance/sales-invoices', {
      method: 'POST',
      body: data,
    })
    await fetchSalesInvoices()
    return res.data
  }

  const issueSalesInvoice = async invoiceId => {
    const res = await $api(`/finance/sales-invoices/${invoiceId}/issue`, { method: 'POST' })
    await fetchSalesInvoices()
    return res.data
  }

  const voidSalesInvoice = async (invoiceId, reason) => {
    const res = await $api(`/finance/sales-invoices/${invoiceId}/void`, {
      method: 'POST',
      body: { reason },
    })
    await fetchSalesInvoices()
    return res.data
  }

  // ─── Supplier Invoices (AP) ─────────────────────────
  const supplierInvoices = ref([])
  const supplierInvoicesTotal = ref(0)
  const isSupplierInvoicesLoading = ref(false)
  const supplierInvoiceAging = ref(null)

  const fetchSupplierInvoices = async (params = {}) => {
    isSupplierInvoicesLoading.value = true
    try {
      const res = await $api('/finance/supplier-invoices', { params })
      supplierInvoices.value = res.data?.data || []
      supplierInvoicesTotal.value = res.data?.total || 0
      return res.data
    } finally {
      isSupplierInvoicesLoading.value = false
    }
  }

  const fetchSupplierInvoiceAging = async (supplierId = null) => {
    const res = await $api('/finance/supplier-invoices/aging', {
      params: supplierId ? { supplier_id: supplierId } : {},
    })
    supplierInvoiceAging.value = res.data
    return res.data
  }

  const createSupplierInvoice = async data => {
    const res = await $api('/finance/supplier-invoices', {
      method: 'POST',
      body: data,
    })
    await fetchSupplierInvoices()
    return res.data
  }

  const approveSupplierInvoice = async invoiceId => {
    const res = await $api(`/finance/supplier-invoices/${invoiceId}/approve`, { method: 'POST' })
    await fetchSupplierInvoices()
    return res.data
  }

  // ─── Customer Accounts ──────────────────────────────
  const customers = ref([])
  const customersTotal = ref(0)
  const isCustomersLoading = ref(false)
  const customerAccount = ref(null)

  const fetchCustomers = async (params = {}) => {
    isCustomersLoading.value = true
    try {
      const res = await $api('/finance/customers', { params })
      customers.value = res.data?.data || []
      customersTotal.value = res.data?.total || 0
      return res.data
    } finally {
      isCustomersLoading.value = false
    }
  }

  const fetchCustomerAccount = async clientId => {
    isCustomersLoading.value = true
    try {
      const res = await $api(`/finance/customers/${clientId}`)
      customerAccount.value = res.data
      return res.data
    } finally {
      isCustomersLoading.value = false
    }
  }

  // ─── Supplier Accounts ──────────────────────────────
  const suppliers = ref([])
  const suppliersTotal = ref(0)
  const isSuppliersLoading = ref(false)
  const supplierAccount = ref(null)

  const fetchSuppliers = async (params = {}) => {
    isSuppliersLoading.value = true
    try {
      const res = await $api('/finance/suppliers', { params })
      suppliers.value = res.data?.data || []
      suppliersTotal.value = res.data?.total || 0
      return res.data
    } finally {
      isSuppliersLoading.value = false
    }
  }

  const fetchSupplierAccount = async supplierId => {
    isSuppliersLoading.value = true
    try {
      const res = await $api(`/finance/suppliers/${supplierId}`)
      supplierAccount.value = res.data
      return res.data
    } finally {
      isSuppliersLoading.value = false
    }
  }

  return {
    salesInvoices,
    salesInvoicesTotal,
    isSalesInvoicesLoading,
    salesInvoiceAging,
    fetchSalesInvoices,
    fetchSalesInvoiceAging,
    createSalesInvoice,
    issueSalesInvoice,
    voidSalesInvoice,
    supplierInvoices,
    supplierInvoicesTotal,
    isSupplierInvoicesLoading,
    supplierInvoiceAging,
    fetchSupplierInvoices,
    fetchSupplierInvoiceAging,
    createSupplierInvoice,
    approveSupplierInvoice,
    customers,
    customersTotal,
    isCustomersLoading,
    customerAccount,
    fetchCustomers,
    fetchCustomerAccount,
    suppliers,
    suppliersTotal,
    isSuppliersLoading,
    supplierAccount,
    fetchSuppliers,
    fetchSupplierAccount,
  }
})
