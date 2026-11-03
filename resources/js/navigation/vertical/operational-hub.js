export default [
  { heading: 'Operational Hub' },

  {
    title: 'Dashboard',
    to: 'operational-hub',
    icon: { icon: 'tabler-chart-radar' },
    module: 'operational-hub',
    action: 'read',
    subject: 'OperationalHub',
  },

  {
    title: 'Planning',
    to: 'operational-hub-planning',
    icon: { icon: 'tabler-calendar' },
    module: 'operational-hub',
    action: 'read',
    subject: 'OperationalHub',
  },

  {
    title: 'Service Work Orders',
    to: 'operational-hub-swos-list',
    icon: { icon: 'tabler-clipboard-text' },
    module: 'operational-hub',
    action: 'read',
    subject: 'OperationalHub',
  },

  {
    title: 'Missions',
    to: 'operational-hub-missions-list',
    icon: { icon: 'tabler-map-route' },
    module: 'operational-hub',
    action: 'read',
    subject: 'OperationalHub',
  },

  {
    title: 'Delivery Notes',
    to: 'operational-hub-service-delivery-notes-list',
    icon: { icon: 'tabler-file-certificate' },
    module: 'operational-hub',
    action: 'read',
    subject: 'OperationalHub',
  },

  {
    title: 'Forecast',
    to: 'operational-hub-forecast-list',
    icon: { icon: 'tabler-telescope' },
    module: 'operational-hub',
    action: 'read',
    subject: 'OperationalHub',
  },

  {
    title: 'Checklists',
    to: 'operational-hub-checklists-list',
    icon: { icon: 'tabler-checkbox' },
    module: 'operational-hub',
    action: 'read',
    subject: 'OperationalHub',
  },
]
