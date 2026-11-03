/* eslint-disable camelcase */
import { useHrStore } from '@/stores/hrStore'
import { ensureTranslatable, localizedValue } from '@/composables/useLocaleField'
import { computed, reactive, ref } from 'vue'

/**
 * useEmployeeForm
 * ─────────────────────────────────────────────────────────────────────────────
 * Employee form state and field configuration — shared between the
 * AddNewEmployeeDialog and the employee edit page.
 *
 * Owns:
 *   - Form reactive data object
 *   - Form sections / information architecture
 *   - Department / position / team option loading (from hrStore meta)
 *   - Field validation helpers
 *   - Form reset / hydrate (from existing employee data)
 *
 * Does NOT own:
 *   - Submission/save (that's useEmployeeActions)
 *   - Dialog open/close (that's useEmployeeDialogs)
 */
export function useEmployeeForm() {
  const hrStore = useHrStore()

  // ── Form data ────────────────────────────────────────────────────────────
  const form = reactive({
    // Identity
    first_name: ensureTranslatable(),
    middle_name: ensureTranslatable(),
    last_name: ensureTranslatable(),
    preferred_name: ensureTranslatable(),
    gender: null,
    date_of_birth: null,
    nationality: '',

    // Contact
    work_email: '',
    personal_email: '',
    work_phone: '',
    personal_phone: '',
    address: '',

    // Employment
    employee_number: '',
    hire_date: null,
    termination_date: null,
    status: 'active',
    notes: '',

    // Organization
    department_id: null,
    position_id: null,
    current_team_id: null,
    manager_id: null,

    // System
    user_id: null,
  })

  const formErrors = ref({})
  const formLoading = ref(false)

  // ── Meta / options ────────────────────────────────────────────────────────

  /**
   * Load HR meta (departments, positions, teams, managers).
   * No-op if already loaded.
   */
  const loadMeta = (force = false) => hrStore.fetchEmployeesMeta(force)

  const departmentOptions = computed(() =>
    hrStore.departments.map(d => ({ title: localizedValue(d.name), value: d.id })),
  )

  const positionOptions = computed(() => {
    const deptId = form.department_id
    const all    = hrStore.positions.map(p => ({ title: localizedValue(p.title), value: p.id, departmentId: p.department_id }))

    return deptId ? all.filter(p => p.departmentId === deptId || p.departmentId == null) : all
  })

  const teamOptions = computed(() =>
    hrStore.teams.map(t => ({ title: localizedValue(t.name), value: t.id })),
  )

  const managerOptions = computed(() =>
    hrStore.managers.map(m => ({ title: m.name, value: m.id })),
  )

  const availableUserOptions = computed(() =>
    hrStore.availableUsers.map(u => ({ title: `${u.name} (${u.email})`, value: u.id })),
  )

  const initialValues = ref(null)

  // Explicitly defined nullable fields that can be cleared by sending null to backend
  // NOTE: user_id is intentionally NOT here — unlinking an employee's user
  // account is a destructive operation reserved for explicit workflows.
  const NULLABLE_FIELDS = new Set([
    'middle_name',
    'preferred_name',
    'gender',
    'date_of_birth',
    'nationality',
    'work_email',
    'personal_email',
    'work_phone',
    'personal_phone',
    'address',
    'termination_date',
    'notes',
    'department_id',
    'position_id',
    'current_team_id',
    'manager_id',
  ])

  // System-managed or read-only fields that must never be sent in update payloads
  const SYSTEM_READONLY_FIELDS = new Set([
    'id',
    'employee_number',
    'created_at',
    'updated_at',
    'avatar_url',
  ])

  // ── Reset & Hydrate ───────────────────────────────────────────────────────

  const resetForm = () => {
    Object.assign(form, {
      first_name: ensureTranslatable(), middle_name: ensureTranslatable(),
      last_name: ensureTranslatable(), preferred_name: ensureTranslatable(),
      gender: null, date_of_birth: null, nationality: '',
      work_email: '', personal_email: '', work_phone: '', personal_phone: '', address: '',
      employee_number: '', hire_date: null, termination_date: null, status: 'active', notes: '',
      department_id: null, position_id: null, current_team_id: null, manager_id: null,
      user_id: null,
    })
    initialValues.value = null
    formErrors.value = {}
  }

  /**
   * Hydrate the form from an existing employee record.
   * @param {object} employee
   */
  const hydrateForm = employee => {
    if (!employee) return

    const values = {
      first_name: ensureTranslatable(employee.first_name),
      middle_name: ensureTranslatable(employee.middle_name),
      last_name: ensureTranslatable(employee.last_name),
      preferred_name: ensureTranslatable(employee.preferred_name),
      gender: employee.gender         || null,
      date_of_birth: employee.date_of_birth  || null,
      nationality: employee.nationality    || '',
      work_email: employee.work_email     || '',
      personal_email: employee.personal_email || '',
      work_phone: employee.work_phone     || '',
      personal_phone: employee.personal_phone || '',
      address: employee.address        || '',
      employee_number: employee.employee_number || '',
      hire_date: employee.hire_date      || null,
      termination_date: employee.termination_date || null,
      status: employee.status         || 'active',
      notes: employee.notes          || '',
      department_id: employee.department_id   || null,
      position_id: employee.position_id     || null,
      current_team_id: employee.current_team_id || null,
      manager_id: employee.manager_id      || null,
      user_id: employee.user_id         || null,
    }

    Object.assign(form, values)
    initialValues.value = { ...values }
    formErrors.value = {}
  }

  /**
   * Build the API payload from current form state.
   *
   * Functional Rules:
   * 1. employee_number is system-managed / read-only -> never sent in update payload.
   * 2. In update mode:
   *    - field omitted -> don't update (unchanged)
   *    - field: null   -> intentionally clear nullable value
   * 3. In create mode:
   *    - send all defined non-empty values
   *
   * @param {object} options { mode: 'update' | 'create' }
   */
  const buildPayload = (options = {}) => {
    const isUpdate = options.mode ? options.mode === 'update' : initialValues.value !== null
    const payload = {}

    if (isUpdate && initialValues.value) {
      for (const [key, value] of Object.entries(form)) {
        if (SYSTEM_READONLY_FIELDS.has(key)) continue

        const initialVal = initialValues.value[key]
        const normalizedCurrent = (value === '' || value === undefined) ? null : value
        const normalizedInitial = (initialVal === '' || initialVal === undefined) ? null : initialVal

        // If field has changed from its initial hydrated state
        if (normalizedCurrent !== normalizedInitial) {
          if (normalizedCurrent === null) {
            if (NULLABLE_FIELDS.has(key)) {
              payload[key] = null
            }
          } else {
            payload[key] = normalizedCurrent
          }
        }
      }
    } else {
      // Create mode
      for (const [key, value] of Object.entries(form)) {
        if (SYSTEM_READONLY_FIELDS.has(key)) continue
        if (value !== '' && value !== null && value !== undefined) {
          payload[key] = value
        }
      }
    }

    return payload
  }

  return {
    form,
    formErrors,
    formLoading,
    initialValues,

    // Meta loading
    loadMeta,
    departmentOptions,
    positionOptions,
    teamOptions,
    managerOptions,
    availableUserOptions,

    // Form helpers
    resetForm,
    hydrateForm,
    buildPayload,
  }
}
