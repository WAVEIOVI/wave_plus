export default [
  { heading: 'Workshop' },

  {
    title: 'Dashboard',
    to: 'workshop',
    icon: { icon: 'tabler-tools' },
    module: 'workshop',
    action: 'read',
    subject: 'Workshop',
  },

  {
    title: 'Workshop Units',
    to: 'workshop-units-list',
    icon: { icon: 'tabler-building-factory-2' },
    module: 'workshop',
    action: 'read',
    subject: 'Workshop',
  },

  {
    title: 'Work Board',
    to: 'workshop-work-list',
    icon: { icon: 'tabler-list-check' },
    module: 'workshop',
    action: 'read',
    subject: 'Workshop',
  },

  {
    title: 'Equipment Reception',
    to: 'workshop-receptions-list',
    icon: { icon: 'tabler-package-import' },
    module: 'workshop',
    action: 'read',
    subject: 'Workshop',
  },

  {
    title: 'Workshop Missions',
    to: 'workshop-missions-list',
    icon: { icon: 'tabler-map-route' },
    module: 'workshop',
    action: 'read',
    subject: 'Workshop',
  },

  {
    title: 'History',
    to: 'workshop-history',
    icon: { icon: 'tabler-history' },
    module: 'workshop',
    action: 'read',
    subject: 'Workshop',
  },
]
