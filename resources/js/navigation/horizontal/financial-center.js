export default [
  {
    title: 'financial_center.title',
    icon: { icon: 'tabler-cash-banknote' },
    children: [
      {
        title: 'financial_center.dashboard',
        to: 'financial-center',
        icon: { icon: 'tabler-layout-dashboard' },
      },
      {
        title: 'financial_center.customers_receivables',
        to: 'financial-center-customers',
        icon: { icon: 'tabler-users' },
      },
      {
        title: 'financial_center.suppliers_payables',
        to: 'financial-center-suppliers',
        icon: { icon: 'tabler-building-warehouse' },
      },
      {
        title: 'financial_center.sales_invoices',
        to: 'financial-center-invoices-sales',
        icon: { icon: 'tabler-file-invoice' },
      },
      {
        title: 'financial_center.supplier_invoices',
        to: 'financial-center-invoices-supplier',
        icon: { icon: 'tabler-file-text' },
      },
      {
        title: 'financial_center.payments',
        to: 'financial-center-payments',
        icon: { icon: 'tabler-receipt' },
      },
      {
        title: 'financial_center.cash_banks',
        to: 'financial-center-accounts',
        icon: { icon: 'tabler-building-bank' },
      },
      {
        title: 'financial_center.expenses',
        to: 'financial-center-expenses',
        icon: { icon: 'tabler-receipt-refund' },
      },
      {
        title: 'financial_center.profitability',
        to: 'financial-center-profitability',
        icon: { icon: 'tabler-chart-arrows-vertical' },
      },
      {
        title: 'financial_center.cost_centers',
        to: 'financial-center-cost-centers',
        icon: { icon: 'tabler-hierarchy-2' },
      },
      {
        title: 'financial_center.budgets',
        to: 'financial-center-budgets',
        icon: { icon: 'tabler-target' },
      },
      {
        title: 'financial_center.accounting',
        to: 'financial-center-accounting',
        icon: { icon: 'tabler-book' },
      },
      {
        title: 'financial_center.financial_periods',
        to: 'financial-center-periods',
        icon: { icon: 'tabler-calendar-time' },
      },
    ],
  },
]
