export default [
  { heading: 'financial_center.title' },

  {
    title: 'financial_center.dashboard',
    to: 'financial-center',
    icon: { icon: 'tabler-layout-dashboard' },
    module: 'financial-center',
    action: 'read',
    subject: 'FinancialCenter',
  },

  {
    title: 'financial_center.customers_receivables',
    to: 'financial-center-customers',
    icon: { icon: 'tabler-users' },
    module: 'financial-center',
    action: 'read',
    subject: 'FinancialCenter',
  },

  {
    title: 'financial_center.suppliers_payables',
    to: 'financial-center-suppliers',
    icon: { icon: 'tabler-building-warehouse' },
    module: 'financial-center',
    action: 'read',
    subject: 'FinancialCenter',
  },

  {
    title: 'financial_center.invoices',
    icon: { icon: 'tabler-file-invoice' },
    module: 'financial-center',
    action: 'read',
    subject: 'FinancialCenter',
    children: [
      {
        title: 'financial_center.sales_invoices',
        to: 'financial-center-invoices-sales',
      },
      {
        title: 'financial_center.supplier_invoices',
        to: 'financial-center-invoices-supplier',
      },
    ],
  },

  {
    title: 'financial_center.payments',
    to: 'financial-center-payments',
    icon: { icon: 'tabler-receipt' },
    module: 'financial-center',
    action: 'read',
    subject: 'FinancialCenter',
  },

  {
    title: 'financial_center.cash_banks',
    to: 'financial-center-accounts',
    icon: { icon: 'tabler-building-bank' },
    module: 'financial-center',
    action: 'read',
    subject: 'FinancialCenter',
  },

  {
    title: 'financial_center.expenses',
    to: 'financial-center-expenses',
    icon: { icon: 'tabler-receipt-refund' },
    module: 'financial-center',
    action: 'read',
    subject: 'FinancialCenter',
  },

  {
    title: 'financial_center.profitability',
    to: 'financial-center-profitability',
    icon: { icon: 'tabler-chart-arrows-vertical' },
    module: 'financial-center',
    action: 'read',
    subject: 'FinancialCenter',
  },

  {
    title: 'financial_center.cost_centers',
    to: 'financial-center-cost-centers',
    icon: { icon: 'tabler-hierarchy-2' },
    module: 'financial-center',
    action: 'read',
    subject: 'FinancialCenter',
  },

  {
    title: 'financial_center.budgets',
    to: 'financial-center-budgets',
    icon: { icon: 'tabler-target' },
    module: 'financial-center',
    action: 'read',
    subject: 'FinancialCenter',
  },

  {
    title: 'financial_center.accounting',
    to: 'financial-center-accounting',
    icon: { icon: 'tabler-book' },
    module: 'financial-center',
    action: 'read',
    subject: 'FinancialCenter',
  },

  {
    title: 'financial_center.financial_periods',
    to: 'financial-center-periods',
    icon: { icon: 'tabler-calendar-time' },
    module: 'financial-center',
    action: 'read',
    subject: 'FinancialCenter',
  },
]
