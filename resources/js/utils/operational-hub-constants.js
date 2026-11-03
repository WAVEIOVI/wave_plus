export const SWO_STATUS_MAP = {
  draft: {
    label: 'Draft',
    color: 'secondary',
    icon: 'tabler-file-pencil',
  },
  accepted: {
    label: 'Accepted',
    color: 'info',
    icon: 'tabler-check',
  },
  ready_for_planning: {
    label: 'Ready for Planning',
    color: 'indigo',
    icon: 'tabler-calendar-time',
  },
  to_plan: {
    label: 'To Plan',
    color: 'info',
    icon: 'tabler-calendar-time',
  },
  planned: {
    label: 'Planned',
    color: 'primary',
    icon: 'tabler-calendar-event',
  },
  scheduled: {
    label: 'Scheduled',
    color: 'info',
    icon: 'tabler-clock-hour-4',
  },
  in_progress: {
    label: 'In Progress',
    color: 'warning',
    icon: 'tabler-progress',
  },
  partially_completed: {
    label: 'Partially Completed',
    color: 'amber',
    icon: 'tabler-progress-alert',
  },
  review: {
    label: 'Review',
    color: 'purple',
    icon: 'tabler-clipboard-check',
  },
  completed: {
    label: 'Completed',
    color: 'success',
    icon: 'tabler-circle-check',
  },
  cancelled: {
    label: 'Cancelled',
    color: 'error',
    icon: 'tabler-circle-x',
  },
  closed: {
    label: 'Closed',
    color: 'secondary',
    icon: 'tabler-lock',
  },
}

export const MISSION_STATUS_MAP = {
  draft: {
    label: 'Draft',
    color: 'secondary',
    icon: 'tabler-file',
  },
  planned: {
    label: 'Planned',
    color: 'primary',
    icon: 'tabler-calendar-event',
  },
  scheduled: {
    label: 'Scheduled',
    color: 'info',
    icon: 'tabler-clock-hour-4',
  },
  ready: {
    label: 'Ready',
    color: 'indigo',
    icon: 'tabler-flag',
  },
  started: {
    label: 'Started',
    color: 'info',
    icon: 'tabler-player-play',
  },
  paused: {
    label: 'Paused',
    color: 'warning',
    icon: 'tabler-player-pause',
  },
  in_progress: {
    label: 'In Progress',
    color: 'warning',
    icon: 'tabler-tools',
  },
  partially_completed: {
    label: 'Partially Completed',
    color: 'amber',
    icon: 'tabler-alert-triangle',
  },
  completed: {
    label: 'Completed',
    color: 'success',
    icon: 'tabler-circle-check',
  },
  cancelled: {
    label: 'Cancelled',
    color: 'error',
    icon: 'tabler-circle-x',
  },
  closed: {
    label: 'Closed',
    color: 'secondary',
    icon: 'tabler-lock',
  },
}

export const WORKSHOP_RECEPTION_STATUS_MAP = {
  pending: {
    label: 'Pending',
    color: 'secondary',
    icon: 'tabler-clock',
  },
  received: {
    label: 'Received',
    color: 'info',
    icon: 'tabler-package-import',
  },
  in_diagnosis: {
    label: 'In Diagnosis',
    color: 'purple',
    icon: 'tabler-stethoscope',
  },
  in_progress: {
    label: 'In Progress',
    color: 'warning',
    icon: 'tabler-tools',
  },
  testing: {
    label: 'Testing',
    color: 'indigo',
    icon: 'tabler-test-pipe',
  },
  completed: {
    label: 'Completed',
    color: 'success',
    icon: 'tabler-circle-check',
  },
  returned: {
    label: 'Returned',
    color: 'primary',
    icon: 'tabler-truck-return',
  },
}

export const FINDING_TYPE_MAP = {
  conformity: {
    label: 'Conformity',
    color: 'success',
    icon: 'tabler-shield-check',
  },
  non_conformity: {
    label: 'Non-Conformity',
    color: 'error',
    icon: 'tabler-shield-x',
  },
  observation: {
    label: 'Observation',
    color: 'info',
    icon: 'tabler-eye',
  },
  defect: {
    label: 'Defect',
    color: 'error',
    icon: 'tabler-alert-triangle',
  },
  recommendation: {
    label: 'Recommendation',
    color: 'warning',
    icon: 'tabler-bulb',
  },
}

export const SWO_PRIORITIES = [
  { value: 'low', label: 'Low', color: 'secondary' },
  { value: 'normal', label: 'Normal', color: 'info' },
  { value: 'high', label: 'High', color: 'warning' },
  { value: 'urgent', label: 'Urgent', color: 'error' },
]

export const SWO_FULFILLMENT_MODES = [
  { value: 'field_service', label: 'Field Service', color: 'info' },
  { value: 'workshop', label: 'Workshop', color: 'primary' },
]

export const SWO_STATUSES = [
  { value: 'draft', label: 'Draft', color: 'secondary' },
  { value: 'accepted', label: 'Accepted', color: 'info' },
  { value: 'ready_for_planning', label: 'Ready for Planning', color: 'indigo' },
  { value: 'planned', label: 'Planned', color: 'primary' },
  { value: 'scheduled', label: 'Scheduled', color: 'info' },
  { value: 'in_progress', label: 'In Progress', color: 'warning' },
  { value: 'partially_completed', label: 'Partially Completed', color: 'amber' },
  { value: 'completed', label: 'Completed', color: 'success' },
  { value: 'cancelled', label: 'Cancelled', color: 'error' },
  { value: 'closed', label: 'Closed', color: 'secondary' },
]

export const MISSION_STATUSES = [
  { value: 'planned', label: 'Planned', color: 'primary' },
  { value: 'ready', label: 'Ready', color: 'indigo' },
  { value: 'started', label: 'Started', color: 'info' },
  { value: 'in_progress', label: 'In Progress', color: 'warning' },
  { value: 'paused', label: 'Paused', color: 'amber' },
  { value: 'partially_completed', label: 'Partially Completed', color: 'amber-darken-2' },
  { value: 'completed', label: 'Completed', color: 'success' },
  { value: 'cancelled', label: 'Cancelled', color: 'error' },
  { value: 'closed', label: 'Closed', color: 'secondary' },
]

export const MISSION_MODES = [
  { value: 'field_service', label: 'Field Service', color: 'info' },
  { value: 'workshop', label: 'Workshop', color: 'primary' },
]

// Real database concepts only: fixed | mobile (no fake bay/bench/clean_room).
export const WORKSHOP_UNIT_TYPES = [
  { value: 'fixed', label: 'Fixed Workshop' },
  { value: 'mobile', label: 'Mobile Unit' },
]

export const WORKSHOP_UNIT_STATUSES = [
  { value: 'active', label: 'Active', color: 'success' },
  { value: 'inactive', label: 'Inactive', color: 'secondary' },
  { value: 'maintenance', label: 'Maintenance', color: 'warning' },
]

export const WORKSHOP_RECEPTION_STATUSES = [
  { value: 'pending', label: 'Pending', color: 'secondary' },
  { value: 'received', label: 'Received', color: 'info' },
  { value: 'in_diagnosis', label: 'In Diagnosis', color: 'purple' },
  { value: 'in_progress', label: 'In Progress', color: 'warning' },
  { value: 'testing', label: 'Testing', color: 'indigo' },
  { value: 'completed', label: 'Completed', color: 'success' },
  { value: 'returned', label: 'Returned', color: 'primary' },
]

// Derived workshop work-queue stages (from real reception + mission states).
export const WORKSHOP_QUEUE_STAGES = [
  { key: 'incoming', label: 'Incoming', icon: 'tabler-package-import', color: 'secondary', statuses: ['pending', 'received'] },
  { key: 'diagnosis', label: 'Diagnosis', icon: 'tabler-stethoscope', color: 'info', statuses: ['in_diagnosis'] },
  { key: 'repair', label: 'Repair', icon: 'tabler-tool', color: 'warning', statuses: ['in_progress'] },
  { key: 'testing', label: 'Testing', icon: 'tabler-test-pipe', color: 'purple', statuses: ['testing'] },
  { key: 'ready_to_return', label: 'Ready to Return', icon: 'tabler-circle-check', color: 'success', statuses: ['completed', 'returned'] },
]

export const getWorkshopUnitStatusChip = status => {
  const norm = status?.value || status || 'active'
  return WORKSHOP_UNIT_STATUSES.find(s => s.value === norm) || { label: norm, color: 'secondary' }
}

export const CHECKLIST_ITEM_STATUSES = [
  { value: 'pending', label: 'Pending' },
  { value: 'passed', label: 'Passed' },
  { value: 'failed', label: 'Failed' },
  { value: 'na', label: 'N/A' },
]

export const FINDING_TYPES = [
  { value: 'observation', label: 'Observation' },
  { value: 'conformity', label: 'Conformity' },
  { value: 'non_conformity', label: 'Non-Conformity' },
  { value: 'recommendation', label: 'Recommendation' },
]

// Mirrors SwoStatus::allowedTransitions() from the backend enum exactly.
export const SWO_TRANSITIONS = {
  draft:               ['accepted', 'ready_for_planning', 'planned', 'scheduled', 'cancelled'],
  accepted:            ['ready_for_planning', 'planned', 'cancelled'],
  ready_for_planning:  ['planned', 'scheduled', 'cancelled'],
  planned:             ['scheduled', 'in_progress', 'ready_for_planning', 'cancelled'],
  scheduled:           ['in_progress', 'planned', 'cancelled'],
  in_progress:         ['partially_completed', 'completed', 'scheduled', 'cancelled'],
  partially_completed: ['in_progress', 'completed', 'cancelled'],
  completed:           ['closed'],
  closed:              [],
  cancelled:           [],
}

export const getSwoStatusChip = status => {
  const norm = status?.value || status || 'draft'
  const def = SWO_STATUS_MAP[norm]
  return def || { label: norm, color: 'secondary' }
}

export const getMissionStatusChip = status => {
  const norm = status?.value || status || 'planned'
  const def = MISSION_STATUS_MAP[norm]
  return def || { label: norm, color: 'secondary' }
}

export const getSwoPriorityChip = priority => {
  const norm = priority?.value || priority || 'normal'
  switch (norm) {
    case 'urgent': return { label: 'Urgent', color: 'error' }
    case 'high': return { label: 'High', color: 'warning' }
    case 'low': return { label: 'Low', color: 'secondary' }
    default: return { label: 'Normal', color: 'info' }
  }
}

export const getSwoFulfillmentModeChip = mode => {
  const norm = mode?.value || mode || 'field_service'
  if (norm === 'workshop') {
    return { label: 'Workshop', color: 'primary' }
  }
  return { label: 'Field Service', color: 'info' }
}

export const getMissionModeChip = mode => {
  const norm = mode?.value || mode || 'field_service'
  if (norm === 'workshop') {
    return { label: 'Workshop', color: 'primary' }
  }
  return { label: 'Field Service', color: 'info' }
}

export const getWorkshopReceptionStatusChip = status => {
  const norm = status?.value || status || 'pending'
  const def = WORKSHOP_RECEPTION_STATUS_MAP[norm]
  return def || { label: norm, color: 'secondary' }
}

// Workflow grouping buckets — statusFilter must only contain real SwoStatus enum values.
export const SWO_WORKFLOW_BUCKETS = [
  { value: 'all', label: 'All Orders', statusFilter: null },
  { value: 'to_plan', label: 'To Plan', statusFilter: 'draft,accepted,ready_for_planning' },
  { value: 'planned', label: 'Planned & Scheduled', statusFilter: 'planned,scheduled' },
  { value: 'execution', label: 'In Execution', statusFilter: 'in_progress,partially_completed' },
  { value: 'completed', label: 'Completed', statusFilter: 'completed,closed' },
]

export const getSwoStatusColor = status => SWO_STATUS_MAP[status]?.color || 'secondary'
export const getMissionStatusColor = status => MISSION_STATUS_MAP[status]?.color || 'secondary'
export const getReceptionStatusColor = status => WORKSHOP_RECEPTION_STATUS_MAP[status]?.color || 'secondary'
export const getFindingColor = type => FINDING_TYPE_MAP[type]?.color || 'info'
export const getPriorityColor = priority => getSwoPriorityChip(priority).color
