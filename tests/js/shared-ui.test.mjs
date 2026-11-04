/**
 * Unit tests for the shared UI composables.
 * Run with:  npm run test:shared-ui   (Node's built-in test runner, no extra deps)
 */
/* eslint-disable camelcase */
import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { nextTick, reactive } from 'vue'
import {
  stripExistingSignature,
  detectSignatureType,
  appendSignatureToHtml,
  htmlToPlainText,
} from '../../resources/js/views/communications/email/utils/signatureUtils.js'
import { ability } from '../../resources/js/plugins/casl/ability.js'
import { isDeepEqual, useDirtyState } from '../../resources/js/composables/shared/useDirtyState.js'
import { useAppSnackbar } from '../../resources/js/composables/useAppSnackbar.js'
import { findFieldError, parseServerErrors } from '../../resources/js/composables/shared/useServerErrors.js'
import { useSearchSelect, normalizeSearchResponse } from '../../resources/js/composables/shared/useSearchSelect.js'
import { collectStepErrors, useWizard, WIZARD_REVIEW_ID } from '../../resources/js/composables/shared/useWizard.js'
import { resolveWizardPreset, WIZARD_VARIANTS } from '../../resources/js/composables/shared/wizardPresets.js'
import { useCurrency } from '../../resources/js/composables/useCurrency.js'
import { createLogoRecovery } from '../../resources/js/composables/branding/logoRecovery.js'
import { resolveDocumentPresetFields, DOCUMENT_PRESETS } from '../../resources/js/composables/documents/documentPresets.js'
import {
  areCornersStable,
  detectDocumentCorners,
  isConvexQuad,
  isPlausibleSheet,
  orderCorners,
  rotateCorners,
  smoothCorners,
  suggestOrientation,
  ORIENTATION,
} from '../../resources/js/composables/documents/scanEngine.js'
import {
  ATTACHMENT_ALL_ACCEPT,
  ATTACHMENT_DOCUMENT_ACCEPT,
  ATTACHMENT_MEDIA_ACCEPT,
  addYears,
  formatDocumentBytes as formatBytes,
  getExpiryState,
  isPreviewableFile,
  matchesAccept,
  normalizeMediaUrl,
  resolveFileColor,
  resolveFileIcon,
  resolveFileKind,
  titleFromFileName,
} from '../../resources/js/composables/documents/documentFiles.js'
import {
  applyAspect,
  clampPan,
  coverLayout,
  createCenteredRect,
  fitContain,
  moveRect,
  normalisedToRect,
  rectToNormalised,
  resizeRect,
  rotatedSize,
} from '../../resources/js/composables/media/imageTransforms.js'
import { useCameraCapture } from '../../resources/js/composables/camera/useCameraCapture.js'
import {
  extractEmployeeBadgeCode,
  extractEquipmentQrCode,
  useQrScanner,
} from '../../resources/js/composables/scanner/useQrScanner.js'
import { useOpenCv } from '../../resources/js/composables/documents/useOpenCv.js'
import { useJournalNav } from '../../resources/js/composables/useJournalNav.js'
import { buildBusinessPartnerListQuery } from '../../resources/js/composables/business-partners/businessPartnerListQuery.js'
import {
  SUPPORTED_EXPORT_FORMATS,
  buildBatchExportPayload,
  normalizeExportIds,
  resolveExportFormats,
} from '../../resources/js/composables/shared/exportFormats.js'
import {
  computePlacementGeometry,
  getStudioTemplate,
  getTemplateColorRegions,
  getTemplateZones,
  normalizePlacementForTemplate,
  normalizeSilhouetteTaxonomy,
  normalizeStudioPlacements,
  resolveModelColorRegions,
  setModelRegionColor,
  WORKWEAR_SILHOUETTE_CATALOG,
} from '../../resources/js/composables/client-assets/workwear/useWorkwearStudioConstants.js'

const wait = ms => new Promise(resolve => setTimeout(resolve, ms))

describe('business partner list query', () => {
  it('maps allowed sorting and entity filters to the existing API contract', () => {
    assert.deepEqual(buildBusinessPartnerListQuery({
      page: 3,
      per_page: 25,
      sort_by: 'legal_name',
      sort_order: 'asc',
      search: 'Northwind',
      status: 'active',
      industry: 'manufacturing',
    }, {
      filterKeys: ['status', 'industry'],
      sortFields: ['id', 'legal_name', 'status'],
    }), {
      page: 3,
      itemsPerPage: 25,
      q: 'Northwind',
      sort: 'legal_name',
      'filter[status]': 'active',
      'filter[industry]': 'manufacturing',
    })
  })

  it('falls back to id sorting and leaves empty filters unset', () => {
    assert.deepEqual(buildBusinessPartnerListQuery({
      page: 1,
      per_page: 10,
      sort_by: 'untrusted_field',
      sort_order: 'desc',
      search: undefined,
      status: null,
    }, {
      filterKeys: ['status'],
      sortFields: ['id', 'legal_name'],
    }), {
      page: 1,
      itemsPerPage: 10,
      q: undefined,
      sort: '-id',
      'filter[status]': undefined,
    })
  })
})

// ─────────────────────────────────────────────────────────────────────────────
describe('Workwear silhouette taxonomy', () => {
  it('uses the simplified product-family tree without duplicate item keys', () => {
    const categories = WORKWEAR_SILHOUETTE_CATALOG
    const subcategories = categories.flatMap(category => category.subcategories)
    const items = subcategories.flatMap(subcategory => subcategory.items)

    assert.equal(categories.length, 4)
    assert.equal(subcategories.length, 8)
    assert.equal(items.length, 52)
    assert.equal(new Set(items.map(item => item.value)).size, items.length)
  })

  it('maps saved hi-vis selections to the base garment and preserves class as a standard', () => {
    const model = normalizeSilhouetteTaxonomy({
      silhouette_category: 'high_visibility',
      silhouette_subcategory: 'hivis_class_3',
      silhouette_item: 'hivis_parka_4in1_c3',
      silhouette: 'parka',
      standards: [],
      certifications: [],
    })

    assert.equal(model.silhouette_category, 'clothing')
    assert.equal(model.silhouette_subcategory, 'tops')
    assert.equal(model.silhouette_item, 'parka_3in1_4in1')
    assert.deepEqual(model.standards, ['EN ISO 20471 Class 3'])
  })

  it('maps saved cleanroom items and retains their compliance metadata', () => {
    const model = normalizeSilhouetteTaxonomy({
      silhouette_category: 'cleanroom_medical',
      silhouette_subcategory: 'cleanroom',
      silhouette_item: 'esd_lab_coat',
      silhouette: 'apron',
      standards: [],
      certifications: [],
    })

    assert.equal(model.silhouette_item, 'lab_coat_warehouse_smock')
    assert.deepEqual(model.standards, ['EN 61340-5-1'])
  })

  it('moves legacy records without a specific item into a matching clothing group', () => {
    const model = normalizeSilhouetteTaxonomy({
      silhouette_category: 'high_visibility',
      silhouette_subcategory: 'hivis_class_3',
      silhouette_item: null,
      silhouette: 'trousers',
    })

    assert.equal(model.silhouette_category, 'clothing')
    assert.equal(model.silhouette_subcategory, 'bottoms')
  })
})

// ─────────────────────────────────────────────────────────────────────────────
describe('presets', () => {
  it('exposes the four variants and falls back to standard', () => {
    assert.deepEqual(WIZARD_VARIANTS, ['fast', 'standard', 'developed', 'deeper'])
    assert.equal(resolveWizardPreset('nope').variant, 'standard')
    assert.equal(resolveWizardPreset('deeper').review, true)
    assert.equal(resolveWizardPreset('fast', { width: 400, review: undefined }).width, 400)
    assert.equal(resolveWizardPreset('fast', { review: undefined }).review, false)
  })
})

describe('server errors', () => {
  it('normalises Laravel validation payloads', () => {
    const parsed = parseServerErrors({ statusCode: 422, data: { message: 'Invalid', errors: { email: ['Taken', 'x'], 'lines.0.qty': ['Min 1'] } } })

    assert.equal(parsed.isValidation, true)
    assert.equal(parsed.fieldErrors.email, 'Taken')
    assert.equal(findFieldError(parsed.fieldErrors, 'lines'), 'Min 1')
    assert.equal(parseServerErrors(new Error('boom')).message, 'boom')
  })
})

describe('dirty state', () => {
  it('deep compares independent of key order', () => {
    assert.equal(isDeepEqual({ a: 1, b: [1, { c: 2 }] }, { b: [1, { c: 2 }], a: 1 }), true)
    assert.equal(isDeepEqual({ a: 1 }, { a: 2 }), false)
  })

  it('tracks commit / discard', () => {
    const form = reactive({ name: 'A', tags: ['x'] })
    const { isDirty, commit, discard } = useDirtyState(form)

    assert.equal(isDirty.value, false)
    form.name = 'B'
    assert.equal(isDirty.value, true)
    discard()
    assert.equal(form.name, 'A')
    form.tags.push('y')
    assert.equal(isDirty.value, true)
    commit()
    assert.equal(isDirty.value, false)
  })
})

// ─────────────────────────────────────────────────────────────────────────────
describe('wizard engine', () => {
  const steps = [
    { id: 'a', title: 'A', fields: [{ key: 'name', required: true }, { key: 'email', rules: [v => /@/.test(v) || 'bad email'] }] },
    { id: 'b', title: 'B', when: f => f.type === 'company', fields: [{ key: 'vat', required: true }] },
    {
      id: 'c',
      title: 'C',
      sections: [{ id: 's', fields: [{ key: 'country' }, { key: 'city', dependsOn: 'country' }] }],
    },
  ]

  it('validates required + custom rules, skipping rules on empty optional fields', () => {
    const errs = collectStepErrors(steps[0], { name: '', email: '' })

    assert.deepEqual(Object.keys(errs), ['name'])
    assert.equal(collectStepErrors(steps[0], { name: 'x', email: 'nope' }).email, 'bad email')
  })

  it('blocks next() until the step is valid and preserves data when going back', async () => {
    const w = useWizard({ steps, initialValues: { type: 'person' } })

    assert.equal(await w.next(), false)
    assert.ok(w.errors.value.name)
    w.setValue('name', 'Acme')
    assert.equal(w.errors.value.name, undefined)
    assert.equal(await w.next(), true)
    assert.equal(w.current.value.id, 'c') // step b skipped (conditional)
    assert.equal(w.back(), true)
    assert.equal(w.form.name, 'Acme')
  })

  it('adds / removes conditional steps reactively and appends a review step', async () => {
    const w = useWizard({ steps, review: true, initialValues: { type: 'person', name: 'x' } })

    assert.deepEqual(w.activeSteps.value.map(s => s.id), ['a', 'c', WIZARD_REVIEW_ID])
    w.setValue('type', 'company')
    assert.deepEqual(w.activeSteps.value.map(s => s.id), ['a', 'b', 'c', WIZARD_REVIEW_ID])
    assert.equal(w.progress.value, 25)
  })

  it('resets dependent fields when their parent changes', () => {
    const w = useWizard({ steps, initialValues: { country: 'FR', city: 'Paris' } })

    w.setValue('country', 'TN')
    assert.equal(w.form.city, null)
  })

  it('omits values of hidden conditional fields from the payload', () => {
    const w = useWizard({ steps, initialValues: { type: 'person', vat: 'X1', name: 'a' } })

    assert.equal('vat' in w.buildPayload(), false)
  })

  it('prevents duplicate submits and clears dirty state on success', async () => {
    let calls = 0

    const w = useWizard({
      steps: [steps[0]],
      initialValues: { name: 'a' },
      submit: async () => { calls += 1; await wait(30) 

        return { id: 1 } },
    })

    w.setValue('name', 'b')
    assert.equal(w.isDirty.value, true)

    const [r1, r2] = await Promise.all([w.submit(), w.submit()])

    assert.equal(calls, 1)
    assert.equal(r1.ok, true)
    assert.equal(r2.reason, 'busy')
    assert.equal(w.isDirty.value, false)
    assert.equal(w.submitting.value, false)
  })

  it('maps server validation errors back to the right step and allows recovery', async () => {
    let fail = true

    const w = useWizard({
      steps,
      initialValues: { type: 'person', name: 'ok', email: 'a@b.c', country: 'FR' },
      submit: async () => {
        if (fail) throw { statusCode: 422, data: { message: 'Invalid', errors: { email: ['Already used'] } } }
        
        return true
      },
    })

    await w.goTo(1)

    const res = await w.submit()

    assert.equal(res.ok, false)
    assert.equal(w.errors.value.email, 'Already used')
    assert.equal(w.current.value.id, 'a') // jumped back to the failing step
    assert.equal(w.submitting.value, false)

    fail = false
    w.setValue('email', 'new@b.c')
    assert.equal((await w.submit()).ok, true)
  })

  it('supports async step validators', async () => {
    const w = useWizard({
      steps: [{ id: 'x', fields: [], validate: async f => (f.code === 'ok' ? true : { code: 'Unknown code' }) }, { id: 'y' }],
      initialValues: { code: 'nope' },
    })

    assert.equal(await w.next(), false)
    assert.equal(w.errors.value.code, 'Unknown code')
    w.setValue('code', 'ok')
    assert.equal(await w.next(), true)
  })

  it('supports canNavigate and canNavigateTo aliases with visited and completed policies', async () => {
    const w = useWizard({ steps, initialValues: { type: 'person', name: 'Acme' } })

    assert.equal(typeof w.canNavigate, 'function')
    assert.equal(typeof w.canNavigateTo, 'function')
    assert.equal(w.canNavigate(0), true)
    assert.equal(w.canNavigate(1, 'visited'), false)

    await w.next()
    assert.equal(w.canNavigate(0, 'visited'), true)
    assert.equal(w.canNavigateTo(0, 'visited'), true)
  })

  it('falls back to key or auto-index when step id is omitted', () => {
    const w = useWizard({
      steps: [
        { key: 'first', title: 'Step 1' },
        { title: 'Step 2' },
      ],
    })

    assert.equal(w.activeSteps.value[0].id, 'first')
    assert.equal(w.activeSteps.value[1].id, 'step-1')
  })
})

// ─────────────────────────────────────────────────────────────────────────────
describe('currency formatting', () => {
  it('formats values with options or currency string and exposes formatCurrency', () => {
    const { format, formatCurrency, formatPlain } = useCurrency()

    assert.equal(typeof formatCurrency, 'function')
    assert.equal(format(null), '—')
    assert.equal(format(undefined), '—')
    assert.equal(format(''), '—')

    const tnd = formatCurrency(1234.56, 'TND')

    assert.ok(tnd.includes('1,234.56') || tnd.includes('1234.56') || tnd.includes('TND'))

    const plain = formatPlain(1234.56)

    assert.ok(plain.includes('1,234.56') || plain.includes('1234.56'))
  })
})

// ─────────────────────────────────────────────────────────────────────────────
describe('search & select', () => {
  const dataset = Array.from({ length: 45 }, (_, i) => ({ id: i + 1, name: `Item ${i + 1}`, kind: i % 2 ? 'a' : 'b' }))

  const fetcher = async ({ page, perPage, search, filters, sortBy, sortDir }) => {
    let rows = dataset.filter(r => (!search || r.name.includes(search)) && (!filters.kind || r.kind === filters.kind))

    if (sortBy) rows = [...rows].sort((a, b) => (a[sortBy] > b[sortBy] ? 1 : -1) * (sortDir === 'asc' ? 1 : -1))

    return { data: rows.slice((page - 1) * perPage, page * perPage), total: rows.length }
  }

  it('normalises response shapes', () => {
    assert.deepEqual(normalizeSearchResponse([1, 2]), { items: [1, 2], total: 2 })
    assert.equal(normalizeSearchResponse({ data: [1], meta: { total: 9 } }).total, 9)
  })

  it('paginates, filters and sorts server-side', async () => {
    const s = useSearchSelect({ fetcher, perPage: 10, debounce: 5 })

    await s.load()
    assert.equal(s.items.value.length, 10)
    assert.equal(s.total.value, 45)
    assert.equal(s.lastPage.value, 5)
    await s.setPage(5)
    assert.equal(s.items.value.length, 5)
    await s.setFilter('kind', 'a')
    assert.equal(s.page.value, 1)
    assert.equal(s.total.value, 22)
    assert.deepEqual(s.activeFilters.value.map(f => f.key), ['kind'])
    await s.setSort('id', 'desc')
    assert.equal(s.items.value[0].id, 44)
    await s.resetAll()
    assert.equal(s.total.value, 45)
    assert.equal(s.hasCriteria.value, false)
  })

  it('debounces typing into a single request', async () => {
    let calls = 0

    const s = useSearchSelect({ fetcher: async a => { calls += 1 

      return fetcher(a) }, debounce: 20 })

    s.setSearch('I'); s.setSearch('It'); s.setSearch('Item 4')
    await wait(60)
    assert.equal(calls, 1)
    assert.equal(s.total.value, 7) // Item 4, Item 40…45
  })

  it('ignores outdated responses (race condition)', async () => {
    const delays = { slow: 80, fast: 5 }

    const s = useSearchSelect({
      fetcher: async ({ search }) => {
        await wait(delays[search])

        return { data: [{ id: search }], total: 1 }
      },
    })

    s.search.value = 'slow'

    const slow = s.load()

    s.search.value = 'fast'

    const fast = s.load()

    await Promise.all([slow, fast])
    assert.equal(s.items.value[0].id, 'fast')
    assert.equal(s.loading.value, false)
  })

  it('surfaces errors and recovers on retry', async () => {
    let fail = true

    const s = useSearchSelect({ fetcher: async a => { if (fail) throw new Error('down') 

      return fetcher(a) } })

    await s.load()
    assert.equal(s.error.value.message, 'down')
    fail = false
    await s.reload()
    assert.equal(s.error.value, null)
    assert.equal(s.items.value.length > 0, true)
  })

  it('enforces single / multiple selection rules and keeps selection across pages', async () => {
    const single = useSearchSelect({ fetcher })

    single.select(dataset[0]); single.select(dataset[1])
    assert.deepEqual(single.selectedKeys.value, [2])

    const multi = useSearchSelect({ fetcher, multiple: true, maxSelection: 2, perPage: 10 })

    await multi.load()
    multi.toggle(dataset[0]); multi.toggle(dataset[1])
    assert.equal(multi.toggle(dataset[2]), false)
    assert.equal(multi.limitReached.value, true)
    await multi.setPage(2)
    assert.deepEqual(multi.selectedKeys.value, [1, 2])
    multi.toggle(dataset[0])
    assert.deepEqual(multi.selectedKeys.value, [2])
    await nextTick()
  })
})

// ─────────────────────────────────────────────────────────────────────────────
describe('entity logo recovery & fallback', () => {
  it('recovers when initial provider URL fails using cache bypass', async () => {
    let resolvedCalls = 0

    const resolveByDomain = async (domain, force) => {
      resolvedCalls++
      assert.equal(domain, 'acme.com')
      assert.equal(force, true)

      return { url: 'https://cdn.example.com/logo-v2.png' }
    }

    const recovery = createLogoRecovery({ maxRetries: 2, resolveByDomain })

    assert.equal(recovery.retryCount.value, 0)
    assert.equal(recovery.imageFailed.value, false)

    // First failure on provider-resolved URL
    const nextUrl = await recovery.handleError({
      currentUrl: 'https://cdn.example.com/logo-v1-broken.png',
      domain: 'acme.com',
    })

    assert.equal(resolvedCalls, 1)
    assert.equal(nextUrl, 'https://cdn.example.com/logo-v2.png')
    assert.equal(recovery.resolvedUrl.value, 'https://cdn.example.com/logo-v2.png')
    assert.equal(recovery.retryCount.value, 1)
    assert.equal(recovery.imageFailed.value, false)
    assert.ok(recovery.failedUrls.value.has('https://cdn.example.com/logo-v1-broken.png'))
  })

  it('falls back to secondary CDN provider when cache-bypass fails or returns same URL', async () => {
    const resolveByDomain = async () => ({ url: 'https://cdn.example.com/still-broken.png' })
    // eslint-disable-next-line camelcase
    const fetchConfig = async () => ({ logo_dev_public_key: 'pk_live_123' })

    const recovery = createLogoRecovery({ maxRetries: 2, resolveByDomain, fetchConfig })

    // Simulate currentUrl already tried and failing
    const nextUrl = await recovery.handleError({
      currentUrl: 'https://cdn.example.com/still-broken.png',
      domain: 'acme.com',
    })

    assert.equal(
      nextUrl,
      'https://img.logo.dev/acme.com?size=512&format=png&token=pk_live_123',
    )
    assert.equal(recovery.resolvedUrl.value, nextUrl)
    assert.equal(recovery.imageFailed.value, false)
  })

  it('stops retrying after bounded maxRetries and falls back to initials', async () => {
    let attempts = 0

    const resolveByDomain = async () => {
      attempts++

      return { url: `https://cdn.example.com/broken-${attempts}.png` }
    }

    const recovery = createLogoRecovery({ maxRetries: 2, resolveByDomain })

    // Error 1: initial provider URL fails -> retry 1
    const res1 = await recovery.handleError({ currentUrl: 'https://cdn.example.com/init.png', domain: 'acme.com' })

    assert.equal(res1, 'https://cdn.example.com/broken-1.png')
    assert.equal(recovery.retryCount.value, 1)

    // Error 2: retry 1 fails -> retry 2
    const res2 = await recovery.handleError({ currentUrl: 'https://cdn.example.com/broken-1.png', domain: 'acme.com' })

    assert.equal(res2, 'https://cdn.example.com/broken-2.png')
    assert.equal(recovery.retryCount.value, 2)

    // Error 3: retry 2 fails -> maxRetries reached, must NOT retry again
    const res3 = await recovery.handleError({ currentUrl: 'https://cdn.example.com/broken-2.png', domain: 'acme.com' })

    assert.equal(res3, null)
    assert.equal(recovery.retryCount.value, 2)
    assert.equal(recovery.imageFailed.value, true)
    assert.equal(attempts, 2) // Never invoked a 3rd time
  })

  it('resets cleanly when props change', async () => {
    const recovery = createLogoRecovery({ maxRetries: 2 })

    await recovery.handleError({ currentUrl: 'https://cdn.example.com/test.png', domain: 'test.com' })

    assert.equal(recovery.retryCount.value, 1)
    assert.equal(recovery.failedUrls.value.size, 1)

    recovery.reset()

    assert.equal(recovery.retryCount.value, 0)
    assert.equal(recovery.failedUrls.value.size, 0)
    assert.equal(recovery.resolvedUrl.value, '')
    assert.equal(recovery.imageFailed.value, false)
    assert.equal(recovery.resolving.value, false)
  })
})

describe('document preset resolution', () => {
  it('maps standard presets to their expected fields', () => {
    const bpFields = resolveDocumentPresetFields('bp')

    assert.ok(bpFields.includes('title'))
    assert.ok(bpFields.includes('type'))
    assert.ok(bpFields.includes('reference'))
    assert.ok(bpFields.includes('status'))
    assert.ok(bpFields.includes('effective_date'))
    assert.ok(bpFields.includes('auto_renewable'))

    const fleetFields = resolveDocumentPresetFields(DOCUMENT_PRESETS.FLEET)

    assert.ok(fleetFields.includes('title'))
    assert.ok(fleetFields.includes('type'))
    assert.ok(fleetFields.includes('reference'))
    assert.ok(fleetFields.includes('expiration_date'))
    assert.equal(fleetFields.includes('auto_renewable'), false)

    const employeeFields = resolveDocumentPresetFields(DOCUMENT_PRESETS.EMPLOYEE)

    assert.deepEqual(employeeFields, ['type', 'title', 'notes'])

    const siteFields = resolveDocumentPresetFields(DOCUMENT_PRESETS.SITE)

    assert.deepEqual(siteFields, ['type', 'title', 'notes'])

    const compactFields = resolveDocumentPresetFields(DOCUMENT_PRESETS.COMPACT)

    assert.deepEqual(compactFields, ['type', 'title', 'notes'])
  })

  it('respects custom field overrides when provided', () => {
    const custom = ['title', 'custom_attr_1', 'custom_attr_2']
    const resolved = resolveDocumentPresetFields('bp', custom)

    assert.deepEqual(resolved, custom)
  })

  it('falls back to default full preset for unknown preset names', () => {
    const fallback = resolveDocumentPresetFields('nonexistent_preset')

    assert.ok(fallback.includes('title'))
    assert.ok(fallback.includes('auto_renewable'))
  })
})

describe('document scanner math & geometry', () => {
  it('correctly orders 4 disordered corner points into TL, TR, BR, BL', () => {
    const scrambled = [
      { x: 0.9, y: 0.9 }, // BR
      { x: 0.1, y: 0.1 }, // TL
      { x: 0.9, y: 0.1 }, // TR
      { x: 0.1, y: 0.9 }, // BL
    ]

    const ordered = orderCorners(scrambled)

    assert.equal(ordered.length, 4)
    assert.deepEqual(ordered[0], { x: 0.1, y: 0.1 }) // TL
    assert.deepEqual(ordered[1], { x: 0.9, y: 0.1 }) // TR
    assert.deepEqual(ordered[2], { x: 0.9, y: 0.9 }) // BR
    assert.deepEqual(ordered[3], { x: 0.1, y: 0.9 }) // BL
  })

  it('returns null for degenerate or non-4-corner input', () => {
    assert.equal(orderCorners(null), null)
    assert.equal(orderCorners([{ x: 1, y: 1 }]), null)
  })

  it('rotates corners in 90 degree increments', () => {
    const corners = [
      { x: 0, y: 0 },
      { x: 1, y: 0 },
      { x: 1, y: 1 },
      { x: 0, y: 1 },
    ]

    const fullTurn = rotateCorners(corners, 4)

    assert.deepEqual(fullTurn, corners)

    const oneTurn = rotateCorners(corners, 1)

    assert.deepEqual(oneTurn[0], corners[3])
    assert.deepEqual(oneTurn[1], corners[0])
    assert.deepEqual(oneTurn[2], corners[1])
    assert.deepEqual(oneTurn[3], corners[2])
  })

  it('suggests landscape vs portrait orientation based on aspect ratio', () => {
    const landscapeCorners = [
      { x: 0, y: 0 },
      { x: 1, y: 0 },
      { x: 1, y: 0.4 },
      { x: 0, y: 0.4 },
    ]

    assert.equal(suggestOrientation(landscapeCorners, 1000, 1000), ORIENTATION.LANDSCAPE)

    const portraitCorners = [
      { x: 0, y: 0 },
      { x: 0.4, y: 0 },
      { x: 0.4, y: 1 },
      { x: 0, y: 1 },
    ]

    assert.equal(suggestOrientation(portraitCorners, 1000, 1000), ORIENTATION.PORTRAIT)
  })

  it('validates convex quads and plausible document sheets', () => {
    const validSheet = [
      { x: 0.1, y: 0.1 },
      { x: 0.9, y: 0.12 },
      { x: 0.88, y: 0.92 },
      { x: 0.12, y: 0.9 },
    ]

    assert.equal(isConvexQuad(validSheet, 1000, 1000), true)
    assert.equal(isPlausibleSheet(validSheet, 1000, 1000), true)

    // Bow-tie (self-intersecting) quad must be rejected
    const bowTie = [
      { x: 0.1, y: 0.1 },
      { x: 0.9, y: 0.9 },
      { x: 0.9, y: 0.1 },
      { x: 0.1, y: 0.9 },
    ]

    assert.equal(isConvexQuad(bowTie, 1000, 1000), false)
    assert.equal(isPlausibleSheet(bowTie, 1000, 1000), false)
  })

  it('smooths corner jitter and detects corner stability across frames', () => {
    const prev = [
      { x: 0.1, y: 0.1 },
      { x: 0.9, y: 0.1 },
      { x: 0.9, y: 0.9 },
      { x: 0.1, y: 0.9 },
    ]

    const slightShift = [
      { x: 0.112, y: 0.095 },
      { x: 0.89, y: 0.108 },
      { x: 0.908, y: 0.892 },
      { x: 0.092, y: 0.908 },
    ]

    const largeJump = [
      { x: 0.25, y: 0.25 },
      { x: 0.75, y: 0.25 },
      { x: 0.75, y: 0.75 },
      { x: 0.25, y: 0.75 },
    ]

    assert.equal(areCornersStable(prev, slightShift, 1000, 1000), true)
    assert.equal(areCornersStable(prev, largeJump, 1000, 1000), false)

    const smoothed = smoothCorners(prev, slightShift, 1000, 1000)

    assert.equal(smoothed.length, 4)
    assert.ok(smoothed[0].x > 0.1 && smoothed[0].x < 0.112)
  })

  it('ranks contours by area so noise contours before index 12 never hide the real document sheet', () => {
    // 18 small noise contours followed by 1 large valid document contour at index 18 (320x240 detection canvas)
    const smallNoisePts = [
      { x: 5, y: 5 },
      { x: 12, y: 5 },
      { x: 12, y: 12 },
      { x: 5, y: 12 },
    ]

    const sheetPts = [
      { x: 32, y: 24 },
      { x: 288, y: 24 },
      { x: 288, y: 216 },
      { x: 32, y: 216 },
    ]

    const makeContourMat = (pts, area) => ({
      _pts: pts,
      _area: area,
      rows: pts.length,
      data32S: Int32Array.from(pts.flatMap(p => [p.x, p.y])),
      delete() {},
    })

    const contourList = [
      ...Array.from({ length: 18 }, () => makeContourMat(smallNoisePts, 49)),
      makeContourMat(sheetPts, 256 * 192),
    ]

    class FakeMat {
      delete() {}
    }

    class FakeMatVector {
      size() { return contourList.length }
      get(i) { return contourList[i] }
      delete() {}
    }

    const fakeCv = {
      Mat: FakeMat,
      MatVector: FakeMatVector,
      Size: function Size(w, h) { this.width = w; this.height = h },
      COLOR_RGBA2GRAY: 1,
      BORDER_DEFAULT: 4,
      RETR_EXTERNAL: 0,
      RETR_LIST: 1,
      CHAIN_APPROX_SIMPLE: 2,
      MORPH_RECT: 0,
      MORPH_CLOSE: 3,
      MORPH_OPEN: 2,
      THRESH_BINARY: 0,
      THRESH_OTSU: 8,
      matFromImageData: () => new FakeMat(),
      cvtColor: () => {},
      GaussianBlur: () => {},
      minMaxLoc: () => ({ minVal: 15, maxVal: 235 }),
      Canny: () => {},
      getStructuringElement: () => new FakeMat(),
      morphologyEx: () => {},
      threshold: () => {},
      bitwise_not: () => {},
      findContours: () => {},
      contourArea: c => c._area,
      convexHull: (contour, hull) => {
        hull.rows = contour.rows
        hull.data32S = contour.data32S
      },
      arcLength: () => 896,
      approxPolyDP: (hull, approx) => {
        approx.rows = hull.rows
        approx.data32S = hull.data32S
      },
    }

    const fakeSource = {
      width: 320,
      height: 240,
    }

    // Stub document.createElement('canvas') for headless Node test
    const prevDoc = globalThis.document

    globalThis.document = {
      createElement: () => ({
        width: 320,
        height: 240,
        getContext: () => ({
          drawImage: () => {},
          getImageData: () => ({ data: new Uint8ClampedArray(320 * 240 * 4), width: 320, height: 240 }),
        }),
      }),
    }

    try {
      const detected = detectDocumentCorners(fakeCv, fakeSource)

      assert.ok(detected, 'Expected document quad at contour index 18 to be detected')
      assert.equal(detected.length, 4)
      assert.equal(Math.round(detected[0].x * 100), 10)
      assert.equal(Math.round(detected[0].y * 100), 10)
      assert.equal(Math.round(detected[2].x * 100), 90)
      assert.equal(Math.round(detected[2].y * 100), 90)
    } finally {
      globalThis.document = prevDoc
    }
  })
})

describe('image crop & transform geometry', () => {
  it('fits an image inside bounds while preserving aspect ratio', () => {
    const fitted = fitContain(800, 400, 16 / 9)

    assert.equal(fitted.height, 400)
    assert.equal(fitted.width, Math.floor(400 * (16 / 9)))
  })

  it('creates centered crop rectangles and enforces aspect ratios and bounds on move/resize', () => {
    const bounds = { width: 400, height: 300 }
    const rect = createCenteredRect(bounds, { aspect: 1, fill: 0.8 })

    assert.equal(Math.round(rect.width), Math.round(rect.height))
    assert.equal(Math.round(rect.x), Math.round((400 - rect.width) / 2))

    // Move clamped to bounds
    const moved = moveRect(rect, 500, -500, bounds)

    assert.equal(moved.x + moved.width, bounds.width)
    assert.equal(moved.y, 0)

    // Resize with minSize and aspect lock
    const resized = resizeRect(rect, 'se', -300, -300, bounds, { aspect: 1, minSize: 44 })

    assert.ok(resized.width >= 44)
    assert.equal(Math.round(resized.width), Math.round(resized.height))

    // Normalised round-trip
    const norm = rectToNormalised(rect, bounds)
    const restored = normalisedToRect(norm, bounds)

    assert.equal(Math.round(restored.width), Math.round(rect.width))
    assert.equal(Math.round(restored.height), Math.round(rect.height))
  })

  it('computes rotated dimensions and square avatar cover layout', () => {
    assert.deepEqual(rotatedSize(1920, 1080, 90), { width: 1080, height: 1920 })
    assert.deepEqual(rotatedSize(1920, 1080, 180), { width: 1920, height: 1080 })

    const layout = coverLayout({ imageWidth: 800, imageHeight: 400, rotation: 0, zoom: 1, size: 200 })

    assert.equal(layout.drawHeight, 200)
    assert.equal(layout.drawWidth, 400)
    assert.equal(layout.maxPanX, 100)
    assert.equal(layout.maxPanY, 0)
    assert.deepEqual(clampPan({ x: 250, y: -50 }, layout), { x: 100, y: 0 })

    const aspectApplied = applyAspect({ x: 20, y: 20, width: 200, height: 160 }, 16 / 9, { width: 400, height: 300 })

    assert.equal(Math.round((aspectApplied.width / aspectApplied.height) * 100), Math.round((16 / 9) * 100))
  })
})

describe('document file & expiry helpers', () => {
  it('resolves file kinds, human-readable titles, and accept filters', () => {
    assert.equal(resolveFileKind('application/pdf', 'plan.pdf'), 'pdf')
    assert.equal(resolveFileKind('', 'contract_2026-v2.docx'), 'word')
    assert.equal(resolveFileKind('image/webp', 'photo.webp'), 'image')
    assert.equal(titleFromFileName('fire_evacuation-plan_2026.pdf'), 'fire evacuation plan 2026')
    assert.equal(formatBytes(1048576), '1 MB')

    assert.equal(matchesAccept({ name: 'spec.pdf', type: 'application/pdf' }, '.pdf,image/*'), true)
    assert.equal(matchesAccept({ name: 'photo.png', type: 'image/png' }, '.pdf,image/*'), true)
    assert.equal(matchesAccept({ name: 'script.exe', type: 'application/x-msdownload' }, '.pdf,image/*'), false)
  })

  it('computes leap-safe expiry dates and expiry status states', () => {
    assert.equal(addYears('2024-02-29', 1), '2025-02-28')
    assert.equal(addYears('2026-05-10', 2), '2028-05-10')

    const refNow = new Date(2026, 9, 20) // 2026-10-20 local

    assert.equal(getExpiryState('2026-10-10', 30, refNow).state, 'expired')
    assert.equal(getExpiryState('2026-10-30', 30, refNow).state, 'soon')
    assert.equal(getExpiryState('2027-06-01', 30, refNow).state, 'valid')
  })
})

describe('opencv runtime safety', () => {
  it('strips Emscripten self-resolving .then from window.cv so async/await never freezes the browser', async () => {
    const prevWindow = globalThis.window

    const emscriptenCv = {
      Mat: function Mat() {},
      then(func) {
        func(emscriptenCv)

        return emscriptenCv
      },
    }

    globalThis.window = { cv: emscriptenCv }

    try {
      const { loadOpenCv, getCv, readyRef } = useOpenCv()

      await loadOpenCv()
      assert.equal(readyRef.value, true)

      const cv = getCv()

      assert.ok(cv)
      assert.equal(typeof cv.then, 'undefined')

      // Returning cv from an async function must resolve immediately without infinite microtask recursion
      const asyncReturnCv = async () => getCv()
      const resolved = await asyncReturnCv()

      assert.equal(resolved, emscriptenCv)
    } finally {
      if (prevWindow !== undefined) {
        globalThis.window = prevWindow
      } else {
        delete globalThis.window
      }
    }
  })
})

describe('camera capture & device switching', () => {
  it('enumerates cameras and cycles through multiple devices with torch support', async () => {
    const prevMediaDevices = Object.getOwnPropertyDescriptor(globalThis.navigator, 'mediaDevices')

    const dummyCameras = [
      { deviceId: 'cam-laptop', kind: 'videoinput', label: 'Integrated Webcam' },
      { deviceId: 'cam-phone-back', kind: 'videoinput', label: 'Phone Back Camera' },
      { deviceId: 'cam-phone-front', kind: 'videoinput', label: 'Phone Front Camera' },
      { deviceId: 'mic-1', kind: 'audioinput', label: 'Microphone' },
    ]

    let currentConstraints = null

    const dummyTrack = {
      stop: () => {},
      getSettings: () => ({ deviceId: currentConstraints?.video?.deviceId?.exact || 'cam-laptop' }),
      getCapabilities: () => ({ torch: true }),
      applyConstraints: async () => {},
    }

    const dummyStream = {
      getVideoTracks: () => [dummyTrack],
      getTracks: () => [dummyTrack],
    }

    const mockMediaDevices = {
      enumerateDevices: async () => dummyCameras,
      getUserMedia: async constraints => {
        currentConstraints = constraints

        return dummyStream
      },
      addEventListener: () => {},
      removeEventListener: () => {},
    }

    Object.defineProperty(globalThis.navigator, 'mediaDevices', {
      value: mockMediaDevices,
      configurable: true,
      writable: true,
    })

    try {
      const {
        startCamera,
        switchCamera,
        selectCamera,
        toggleTorch,
        availableCamerasRef,
        currentCameraIdRef,
        torchSupportedRef,
        torchEnabledRef,
        hasMultipleCameras,
      } = useCameraCapture()

      await startCamera()
      assert.equal(availableCamerasRef.value.length, 3)
      assert.equal(hasMultipleCameras.value, true)
      assert.equal(currentCameraIdRef.value, 'cam-laptop')
      assert.equal(torchSupportedRef.value, true)

      // Test torch toggle
      assert.equal(torchEnabledRef.value, false)
      await toggleTorch()
      assert.equal(torchEnabledRef.value, true)
      await toggleTorch()
      assert.equal(torchEnabledRef.value, false)

      // Cycle camera 1 -> 2 (phone back)
      await switchCamera()
      assert.equal(currentCameraIdRef.value, 'cam-phone-back')

      // Cycle camera 2 -> 3 (phone front)
      await switchCamera()
      assert.equal(currentCameraIdRef.value, 'cam-phone-front')

      // Cycle camera 3 -> 1 (laptop)
      await switchCamera()
      assert.equal(currentCameraIdRef.value, 'cam-laptop')

      // Direct selection
      await selectCamera('cam-phone-back')
      assert.equal(currentCameraIdRef.value, 'cam-phone-back')

      // Passing a DOM click Event object to switchCamera(event) must NOT treat the Event as a deviceId
      await switchCamera({ type: 'click', target: {} })
      assert.equal(currentCameraIdRef.value, 'cam-phone-front')

      // Calling startCamera('user') without an explicit deviceId resets currentCameraIdRef and uses facingMode
      await startCamera('user')
      assert.equal(currentConstraints.video.facingMode.ideal, 'user')
      assert.equal(currentConstraints.video.deviceId, undefined)
    } finally {
      if (prevMediaDevices) {
        Object.defineProperty(globalThis.navigator, 'mediaDevices', prevMediaDevices)
      } else {
        delete globalThis.navigator.mediaDevices
      }
    }
  })

  it('deduplicates virtual default camera aliases in selectableCamerasRef', async () => {
    const prevMediaDevices = Object.getOwnPropertyDescriptor(globalThis.navigator, 'mediaDevices')

    const devicesWithDefaultAlias = [
      { deviceId: 'default', groupId: 'grp-1', kind: 'videoinput', label: 'Default - USB Webcam' },
      { deviceId: 'real-cam-1', groupId: 'grp-1', kind: 'videoinput', label: 'USB Webcam' },
      { deviceId: '', groupId: 'grp-2', kind: 'videoinput', label: '' },
    ]

    Object.defineProperty(globalThis.navigator, 'mediaDevices', {
      value: {
        enumerateDevices: async () => devicesWithDefaultAlias,
        addEventListener: () => {},
        removeEventListener: () => {},
      },
      configurable: true,
      writable: true,
    })

    try {
      const { enumerateCameras, selectableCamerasRef, hasRealMultipleCameras } = useCameraCapture()

      await enumerateCameras()
      assert.equal(selectableCamerasRef.value.length, 1)
      assert.equal(selectableCamerasRef.value[0].deviceId, 'real-cam-1')
      assert.equal(hasRealMultipleCameras.value, false)
    } finally {
      if (prevMediaDevices) {
        Object.defineProperty(globalThis.navigator, 'mediaDevices', prevMediaDevices)
      } else {
        delete globalThis.navigator.mediaDevices
      }
    }
  })
})

describe('journal navigation composable (useJournalNav)', () => {
  it('normalizes flat sections and formats mobileNavItems for AppBottomNav', () => {
    const sections = [
      { id: 'bio', label: 'Identity', icon: 'tabler-user' },
      { id: 'addresses', label: 'Addresses', icon: 'tabler-map-pin', badge: 3 },
      { id: 'financial', label: 'Financial', icon: 'tabler-coin', disabled: true },
    ]

    const nav = useJournalNav(sections, { autoSetupObserver: false })

    assert.equal(nav.isGrouped.value, false)
    assert.equal(nav.flatSections.value.length, 3)
    assert.equal(nav.activeSection.value, 'bio')

    const mobile = nav.mobileNavItems.value

    assert.equal(mobile.length, 3)
    assert.deepEqual(mobile[0], {
      key: 'bio',
      value: 'bio',
      title: 'Identity',
      icon: 'tabler-user',
      badge: null,
      disabled: false,
    })
    assert.equal(mobile[1].badge, 3)
    assert.equal(mobile[2].disabled, true)
  })

  it('normalizes grouped sections like HR employees and computes both groups and flatSections', () => {
    const groups = [
      {
        id: 'profile',
        label: 'Profile Group',
        sections: [
          { id: 'identity', label: 'Identity Card', icon: 'tabler-user' },
          { id: 'contact', label: 'Contact Card', icon: 'tabler-phone' },
        ],
      },
      {
        id: 'records',
        label: 'Records Group',
        sections: [
          { id: 'documents', label: 'Documents Tab', icon: 'tabler-file', badge: 5 },
        ],
      },
    ]

    const nav = useJournalNav(groups, { autoSetupObserver: false })

    assert.equal(nav.isGrouped.value, true)
    assert.equal(nav.groups.value.length, 2)
    assert.equal(nav.groups.value[0].sections.length, 2)
    assert.equal(nav.groups.value[1].sections.length, 1)

    assert.equal(nav.flatSections.value.length, 3)
    assert.equal(nav.mobileNavItems.value.length, 3)
    assert.equal(nav.mobileNavItems.value[2].key, 'documents')
    assert.equal(nav.mobileNavItems.value[2].badge, 5)
  })

  it('handles navigation state change and manual scroll lock', () => {
    const sections = [
      { id: 'sec1', label: 'Section 1' },
      { id: 'sec2', label: 'Section 2' },
    ]

    const nav = useJournalNav(sections, { autoSetupObserver: false, defaultSection: 'sec1' })

    assert.equal(nav.activeSection.value, 'sec1')

    nav.scrollToSection('sec2')
    assert.equal(nav.activeSection.value, 'sec2')
    assert.equal(nav.isManualScrolling.value, true)
  })
})

describe('Workwear 2D Studio coordinate, scale, zone & colour-region contract', () => {
  it('uses a uniform unitsPerMm factor for both offsets and dimensions and marks scale as unverified', () => {
    for (const sil of ['coverall', 'jacket', 'parka', 'vest', 'trousers', 'polo']) {
      const tpl = getStudioTemplate(sil)
      const firstZone = tpl.zones[0].value

      assert.equal(tpl.scale.scaleVerified, false)
      assert.ok(tpl.scale.unitsPerMm > 0)

      const base = computePlacementGeometry({
        zone: firstZone,
        view: 'front',
        width_mm: 80,
        height_mm: 40,
        offset_x_mm: 0,
        offset_y_mm: 0,
      }, sil)

      const shiftedAndGrown = computePlacementGeometry({
        zone: firstZone,
        view: 'front',
        width_mm: 90,
        height_mm: 50,
        offset_x_mm: 10,
        offset_y_mm: 10,
      }, sil)

      // 10mm offset shift in SVG units must equal 10mm dimension growth in SVG units
      assert.equal(
        Math.round((shiftedAndGrown.cx - base.cx) * 100),
        Math.round((shiftedAndGrown.w - base.w) * 100),
      )
      assert.equal(
        Math.round((shiftedAndGrown.cy - base.cy) * 100),
        Math.round((shiftedAndGrown.h - base.h) * 100),
      )
      assert.equal(base.scaleVerified, false)
    }
  })

  it('provides template-specific placement zones and safe boundaries for Coverall, Jacket/Parka, Vest, Trousers and Polo', () => {
    const coverallZones = getTemplateZones('coverall').map(z => z.value)
    const jacketZones = getTemplateZones('jacket').map(z => z.value)
    const vestZones = getTemplateZones('vest').map(z => z.value)
    const trousersZones = getTemplateZones('trousers').map(z => z.value)
    const poloZones = getTemplateZones('polo').map(z => z.value)

    // Coverall spans upper and lower body
    assert.ok(coverallZones.includes('chest_left'))
    assert.ok(coverallZones.includes('knee_left'))

    // Jacket & Polo have upper body and sleeves, no thighs or knees
    assert.ok(jacketZones.includes('chest_left'))
    assert.ok(jacketZones.includes('sleeve_left'))
    assert.ok(!jacketZones.includes('knee_left'))
    assert.ok(!poloZones.includes('thigh_left'))

    // Sleeveless Hi-Vis Vest has chest/shoulders/back, no sleeves or legs
    assert.ok(vestZones.includes('chest_left'))
    assert.ok(!vestZones.includes('sleeve_left'))
    assert.ok(!vestZones.includes('thigh_left'))

    // Trousers have waist, pockets, thighs, knees, hem, back_lower — no chest or sleeves
    assert.ok(trousersZones.includes('waist'))
    assert.ok(trousersZones.includes('thigh_left'))
    assert.ok(trousersZones.includes('knee_left'))
    assert.ok(!trousersZones.includes('chest_left'))
    assert.ok(!trousersZones.includes('sleeve_left'))

    // Trousers waist anchor is at the top waistband (y=90), not mid-canvas (y=205)
    const trousersWaist = getTemplateZones('trousers').find(z => z.value === 'waist')

    assert.equal(trousersWaist.y, 90)
  })

  it('resolves and updates named colour regions while preserving backward-compatible [primary, contrast] array', () => {
    const coverallRegions = getTemplateColorRegions('coverall').map(r => r.key)
    const vestRegions = getTemplateColorRegions('vest').map(r => r.key)
    const trousersRegions = getTemplateColorRegions('trousers').map(r => r.key)
    const poloRegions = getTemplateColorRegions('polo').map(r => r.key)

    assert.ok(coverallRegions.includes('body') && coverallRegions.includes('yoke'))
    assert.ok(vestRegions.includes('hi_vis_body') && vestRegions.includes('contrast_panel'))
    assert.ok(trousersRegions.includes('main_fabric') && trousersRegions.includes('cargo_accents'))
    assert.ok(poloRegions.includes('body') && poloRegions.includes('shoulder_panels'))

    // Legacy 2-element colors array maps cleanly to named regions
    const resolved = resolveModelColorRegions(['navy', 'hi-vis-yellow'], 'coverall')

    assert.equal(resolved.body, 'navy')
    assert.equal(resolved.yoke, 'hi-vis-yellow')
    assert.equal(resolved.collar, 'navy')

    // Setting a specific secondary named region preserves slots 0 and 1 and appends region override
    const updatedColors = setModelRegionColor(['navy', 'hi-vis-yellow'], 'coverall', 'collar', 'royal-blue')

    assert.equal(updatedColors[0], 'navy')
    assert.equal(updatedColors[1], 'hi-vis-yellow')
    assert.ok(updatedColors.includes('collar:royal-blue'))

    const reResolved = resolveModelColorRegions(updatedColors, 'coverall')

    assert.equal(reResolved.collar, 'royal-blue')
  })

  it('normalizes legacy or cross-silhouette placements into valid zones and safe boundaries', () => {
    const legacy = [
      {
        view: 'back',
        type: 'logo',
        label: 'Old Chest Logo on Back',
        zone: 'chest_left',
        width_mm: 450,
        height_mm: 300,
        offset_x_mm: 250,
        offset_y_mm: -250,
      },
    ]

    const normalizedForTrousers = normalizeStudioPlacements(legacy, 'trousers')

    assert.equal(normalizedForTrousers.length, 1)
    assert.equal(normalizedForTrousers[0].zone, 'pocket_left')
    assert.equal(normalizedForTrousers[0].view, 'back')

    const geom = computePlacementGeometry(normalizedForTrousers[0], 'trousers')

    assert.equal(geom.withinSafeBounds, true)

    const singleClamped = normalizePlacementForTemplate({
      zone: 'chest_left',
      view: 'front',
      type: 'logo',
      width_mm: 80,
      height_mm: 35,
      offset_x_mm: 500,
      offset_y_mm: 500,
    }, 'polo')

    const poloGeom = computePlacementGeometry(singleClamped, 'polo')

    assert.equal(poloGeom.withinSafeBounds, true)
  })
})

describe('unified attachment workflow filters and helpers', () => {
  it('validates document, media, and video accept filters accurately', () => {
    // Document filter
    assert.equal(matchesAccept({ name: 'specs.pdf', type: 'application/pdf' }, ATTACHMENT_DOCUMENT_ACCEPT), true)
    assert.equal(matchesAccept({ name: 'data.xlsx', type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }, ATTACHMENT_DOCUMENT_ACCEPT), true)
    assert.equal(matchesAccept({ name: 'drawing.svg', type: 'image/svg+xml' }, ATTACHMENT_DOCUMENT_ACCEPT), true)
    assert.equal(matchesAccept({ name: 'clip.mp4', type: 'video/mp4' }, ATTACHMENT_DOCUMENT_ACCEPT), false)

    // Media filter (images and videos)
    assert.equal(matchesAccept({ name: 'clip.mp4', type: 'video/mp4' }, ATTACHMENT_MEDIA_ACCEPT), true)
    assert.equal(matchesAccept({ name: 'recording.webm', type: 'video/webm' }, ATTACHMENT_MEDIA_ACCEPT), true)
    assert.equal(matchesAccept({ name: 'photo.jpg', type: 'image/jpeg' }, ATTACHMENT_MEDIA_ACCEPT), true)
    assert.equal(matchesAccept({ name: 'graphic.svg', type: 'image/svg+xml' }, ATTACHMENT_MEDIA_ACCEPT), true)
    assert.equal(matchesAccept({ name: 'contract.pdf', type: 'application/pdf' }, ATTACHMENT_MEDIA_ACCEPT), false)

    // All accept filter
    assert.equal(matchesAccept({ name: 'contract.pdf', type: 'application/pdf' }, ATTACHMENT_ALL_ACCEPT), true)
    assert.equal(matchesAccept({ name: 'clip.mp4', type: 'video/mp4' }, ATTACHMENT_ALL_ACCEPT), true)
    assert.equal(matchesAccept({ name: 'blueprint.ai', type: 'application/postscript' }, ATTACHMENT_ALL_ACCEPT), true)
    assert.equal(matchesAccept({ name: 'virus.exe', type: 'application/x-msdownload' }, ATTACHMENT_ALL_ACCEPT), false)
  })

  it('correctly classifies media kinds including video and text', () => {
    assert.equal(resolveFileKind('video/mp4', 'clip.mp4'), 'video')
    assert.equal(resolveFileKind('video/webm', 'screen.webm'), 'video')
    assert.equal(resolveFileKind('video/quicktime', 'movie.mov'), 'video')
    assert.equal(resolveFileKind('image/jpeg', 'photo.jpg'), 'image')
    assert.equal(resolveFileKind('application/pdf', 'file.pdf'), 'pdf')
    assert.equal(resolveFileKind('text/csv', 'data.csv'), 'sheet')
  })
})

describe('shared QR scanner and module payload normalization', () => {
  it('extracts clean equipment codes from raw codes, /ca/scan/eq/ URLs, WP:EQ prefixes, and scanner objects', () => {
    assert.equal(extractEquipmentQrCode('EQ-2026-4821'), 'EQ-2026-4821')
    assert.equal(
      extractEquipmentQrCode('https://waveplus.example.com/ca/scan/eq/EQ-2026-4821'),
      'EQ-2026-4821',
    )
    assert.equal(
      extractEquipmentQrCode('/ca/scan/eq/EQ-2026-4821-QR-8F3A?source=sticker#main'),
      'EQ-2026-4821-QR-8F3A',
    )
    assert.equal(
      extractEquipmentQrCode('WP:EQ:EQ-2026-9910'),
      'EQ-2026-9910',
    )
    assert.equal(
      extractEquipmentQrCode({
        value: 'https://waveplus.example.com/ca/scan/eq/EQ-2026-7744',
        format: 'qr',
      }),
      'EQ-2026-7744',
    )
    assert.equal(extractEquipmentQrCode(''), '')
    assert.equal(extractEquipmentQrCode('   '), '')
    assert.equal(extractEquipmentQrCode(null), '')
  })

  it('extracts employee badge codes from strings, scanner result objects, and nested kiosk payloads', () => {
    assert.equal(extractEmployeeBadgeCode('WP:EMP:EMP-00001'), 'WP:EMP:EMP-00001')
    assert.equal(extractEmployeeBadgeCode('  EMP-00042  '), 'EMP-00042')
    assert.equal(
      extractEmployeeBadgeCode({ value: 'WP:EMP:EMP-00019', format: 'qr' }),
      'WP:EMP:EMP-00019',
    )
    assert.equal(
      extractEmployeeBadgeCode({ badge: { value: 'WP:EMP:EMP-00088', format: 'qr' } }),
      'WP:EMP:EMP-00088',
    )
    assert.equal(extractEmployeeBadgeCode(''), '')
    assert.equal(extractEmployeeBadgeCode(null), '')
  })

  it('processes manual QR input, suppresses duplicate consecutive scans until reset, and cleans up on stop', async () => {
    const scanner = useQrScanner()

    assert.equal(scanner.hasScannedRef.value, false)

    // Blank input is rejected
    assert.equal(scanner.useManualInput('   '), null)
    assert.equal(scanner.hasScannedRef.value, false)

    // Valid manual input sets scanResultRef and stops scanning
    const first = scanner.useManualInput('  WP:EMP:EMP-00105  ')

    assert.ok(first)
    assert.equal(first.value, 'WP:EMP:EMP-00105')
    assert.equal(scanner.hasScannedRef.value, true)
    assert.equal(scanner.scanningRef.value, false)

    // After resetScanState / retry, the same code can be scanned again
    scanner.retry()
    assert.equal(scanner.hasScannedRef.value, false)

    const repeated = scanner.useManualInput('WP:EMP:EMP-00105')

    assert.ok(repeated)
    assert.equal(repeated.value, 'WP:EMP:EMP-00105')

    await scanner.stopScanning()
  })
})

describe('standardized media and attachment helpers (documentFiles)', () => {
  it('resolves file kind, icon, and color from both (mime, name) strings and media/document item objects, including vector design files', () => {
    const aiItem = {
      file_name: 'chest-embroidery-logo.ai',
      mime_type: 'application/postscript',
    }

    assert.equal(resolveFileKind(aiItem), 'design')
    assert.equal(resolveFileIcon(aiItem), 'tabler-vector-bezier-2')
    assert.equal(resolveFileColor(aiItem), 'info')

    const pdfItem = {
      file_name: 'en-iso-20471-certificate.pdf',
      mime_type: 'application/pdf',
    }

    assert.equal(resolveFileKind(pdfItem), 'pdf')
    assert.equal(resolveFileIcon(pdfItem), 'tabler-file-type-pdf')
    assert.equal(resolveFileColor(pdfItem), 'error')

    const mp4Item = {
      file_name: 'inspection-walkthrough.mp4',
      mime_type: 'video/mp4',
    }

    assert.equal(resolveFileKind(mp4Item), 'video')
    assert.equal(resolveFileIcon(mp4Item), 'tabler-video')
  })

  it('determines whether an attachment is previewable in WpMediaViewerDialog', () => {
    assert.equal(isPreviewableFile({ mime_type: 'application/pdf', file_name: 'cert.pdf' }), true)
    assert.equal(isPreviewableFile({ mime_type: 'image/png', file_name: 'logo.png' }), true)
    assert.equal(isPreviewableFile({ mime_type: 'video/mp4', file_name: 'clip.mp4' }), true)
    assert.equal(isPreviewableFile({ mime_type: 'application/postscript', file_name: 'artwork.ai' }), false)
    assert.equal(isPreviewableFile('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'sheet.xlsx'), false)
  })

  it('normalizes localhost storage URLs and preserves blob/data/external URLs', () => {
    const prevWindow = globalThis.window

    globalThis.window = { location: { origin: 'http://localhost:8000' } }
    try {
      assert.equal(normalizeMediaUrl('blob:https://example.com/123'), 'blob:https://example.com/123')
      assert.equal(normalizeMediaUrl('data:image/png;base64,iVBOR'), 'data:image/png;base64,iVBOR')
      assert.equal(
        normalizeMediaUrl('http://localhost/storage/15/site-photo.jpg'),
        'http://localhost:8000/storage/15/site-photo.jpg',
      )
      assert.equal(
        normalizeMediaUrl('http://127.0.0.1/storage/22/logo.png'),
        'http://localhost:8000/storage/22/logo.png',
      )
    }
    finally {
      globalThis.window = prevWindow
    }
  })
})

describe('batch export helpers (exportFormats)', () => {
  it('resolves genuinely supported export formats (PDF and CSV) and filters out unsupported formats like XLSX', () => {
    const defaults = resolveExportFormats()

    assert.deepEqual(defaults.map(f => f.value), ['pdf', 'csv'])
    assert.equal(SUPPORTED_EXPORT_FORMATS.pdf.extension, 'pdf')
    assert.equal(SUPPORTED_EXPORT_FORMATS.csv.extension, 'csv')

    const withUnsupported = resolveExportFormats(['pdf', 'xlsx', 'CSV', 'pdf', 'xml'])

    assert.deepEqual(withUnsupported.map(f => f.value), ['pdf', 'csv'])
  })

  it('normalizes and deduplicates selected IDs from ULID strings, primitive integers, row objects, and Sets while rejecting invalid entries', () => {
    const ulidA = '01j8z9k2m3n4p5q6r7s8t9v0wx'
    const ulidB = '01j8z9k2m3n4p5q6r7s8t9v0wy'

    assert.deepEqual(
      normalizeExportIds([ulidA, { id: ulidB }, { id: ulidA }, 10, { id: 42 }, null, undefined, '', '   ', -5, 0, '0', true]),
      [ulidA, ulidB, 10, 42],
    )
    assert.deepEqual(
      normalizeExportIds(new Set([ulidA, ulidB, ulidA])),
      [ulidA, ulidB],
    )
    assert.deepEqual(normalizeExportIds([]), [])
  })

  it('validates batch export payloads for single and multiple selections and rejects empty or unsupported format requests', () => {
    const singlePdf = buildBatchExportPayload([15], 'pdf')

    assert.equal(singlePdf.valid, true)
    assert.deepEqual(singlePdf.ids, [15])
    assert.equal(singlePdf.format, 'pdf')
    assert.equal(singlePdf.count, 1)
    assert.equal(singlePdf.error, null)

    const multiCsv = buildBatchExportPayload([{ id: 4 }, { id: 9 }, 4], 'CSV')

    assert.equal(multiCsv.valid, true)
    assert.deepEqual(multiCsv.ids, [4, 9])
    assert.equal(multiCsv.format, 'csv')
    assert.equal(multiCsv.count, 2)

    const emptySelection = buildBatchExportPayload([], 'pdf')

    assert.equal(emptySelection.valid, false)
    assert.deepEqual(emptySelection.ids, [])
    assert.equal(emptySelection.count, 0)
    assert.ok(emptySelection.error)

    const unsupportedFormat = buildBatchExportPayload([1, 2], 'xlsx')

    assert.equal(unsupportedFormat.valid, false)
    assert.equal(unsupportedFormat.format, 'xlsx')
    assert.ok(unsupportedFormat.error)
  })
})

describe('unified snackbar composable (useAppSnackbar)', () => {
  it('displays notifications with standard colors, icons and custom timeouts', () => {
    const { snackbar, success, error, warning, info, clearAll } = useAppSnackbar()

    clearAll()

    success('Client created successfully', { timeout: 2500 })
    assert.equal(snackbar.value.show, true)
    assert.equal(snackbar.value.text, 'Client created successfully')
    assert.equal(snackbar.value.color, 'success')
    assert.equal(snackbar.value.icon, 'tabler-circle-check')
    assert.equal(snackbar.value.timeout, 2500)

    clearAll()
    error('Failed to create partner')
    assert.equal(snackbar.value.show, true)
    assert.equal(snackbar.value.text, 'Failed to create partner')
    assert.equal(snackbar.value.color, 'error')
    assert.equal(snackbar.value.icon, 'tabler-alert-circle')
    assert.equal(snackbar.value.timeout, 6000)

    clearAll()
    warning('Attention required')
    assert.equal(snackbar.value.color, 'warning')
    assert.equal(snackbar.value.icon, 'tabler-alert-triangle')

    clearAll()
    info('Background export in progress')
    assert.equal(snackbar.value.color, 'info')
    assert.equal(snackbar.value.icon, 'tabler-info-circle')
    clearAll()
  })

  it('queues multiple notifications and advances sequentially on dismiss', async () => {
    const { snackbar, queue, showMessage, dismiss, clearAll } = useAppSnackbar()

    clearAll()

    showMessage('First Message', 'info')
    assert.equal(snackbar.value.text, 'First Message')
    assert.equal(queue.length, 0)

    showMessage('Second Message', 'success')
    assert.equal(snackbar.value.text, 'First Message')
    assert.equal(queue.length, 1)
    assert.equal(queue[0].text, 'Second Message')

    dismiss()

    // Wait for transition delay (220ms + margin)
    await new Promise(resolve => setTimeout(resolve, 260))

    assert.equal(snackbar.value.show, true)
    assert.equal(snackbar.value.text, 'Second Message')
    assert.equal(queue.length, 0)
    clearAll()
  })

  it('preempts non-error message when an urgent error arrives', () => {
    const { snackbar, queue, info, error, clearAll } = useAppSnackbar()

    clearAll()

    info('Doing something slow')
    assert.equal(snackbar.value.text, 'Doing something slow')
    assert.equal(snackbar.value.color, 'info')

    error('Critical error occurred!')
    assert.equal(snackbar.value.text, 'Critical error occurred!')
    assert.equal(snackbar.value.color, 'error')
    assert.equal(queue.length, 1)
    assert.equal(queue[0].text, 'Doing something slow')
    clearAll()
  })

  it('suppresses duplicate identical notifications within the debounce window', () => {
    const { snackbar, queue, success, clearAll } = useAppSnackbar()

    clearAll()

    success('Saved successfully')
    assert.equal(queue.length, 0)

    // Immediate duplicate
    success('Saved successfully')
    assert.equal(queue.length, 0)
    clearAll()
  })

  it('resolves API error messages from various Laravel response shapes', () => {
    const { snackbar, showApiError, clearAll } = useAppSnackbar()

    clearAll()

    // 1. Direct message in data
    showApiError({ data: { message: 'Entity is locked' } }, 'Fallback error')
    assert.equal(snackbar.value.text, 'Entity is locked')

    clearAll()

    // 2. Validation errors dictionary
    showApiError({
      data: {
        message: 'The given data was invalid.',
        errors: { legal_name: ['The legal name is required.', 'Too short'] },
      },
    }, 'Fallback error')
    assert.equal(snackbar.value.text, 'The given data was invalid.')

    clearAll()

    // 3. Validation errors without main message
    showApiError({
      data: {
        errors: { tax_id: ['Tax ID is invalid'] },
      },
    }, 'Fallback error')
    assert.equal(snackbar.value.text, 'Tax ID is invalid')

    clearAll()

    // 4. Fallback when empty error object
    showApiError({}, 'Custom fallback error message')
    assert.equal(snackbar.value.text, 'Custom fallback error message')
    clearAll()
  })

  it('supports action button execution and auto-dismiss', () => {
    const { snackbar, showSnackbar, handleAction, clearAll } = useAppSnackbar()

    clearAll()

    let clicked = false

    showSnackbar({
      text: 'Deleted contact',
      color: 'warning',
      action: {
        label: 'Undo',
        onClick: () => { clicked = true },
      },
    })

    assert.equal(snackbar.value.show, true)
    assert.ok(snackbar.value.action)
    assert.equal(snackbar.value.action.label, 'Undo')

    handleAction()
    assert.equal(clicked, true)
    assert.equal(snackbar.value.show, false)
    clearAll()
  })
})

describe('smart composer signature & message utils', () => {
  const sampleBody = '<p>Bonjour,</p><p>Veuillez trouver ci-joint votre document.</p>'
  const personalSig = '<div class="wp-signature wp-signature-personal"><p>John Doe</p><p>Sales</p></div>'
  const companySig = '<div class="wp-signature wp-signature-company"><p>WAVE PLUS</p><p>Enterprise</p></div>'

  it('stripExistingSignature removes signature blocks without modifying body text', () => {
    const withPersonal = `${sampleBody}\n${personalSig}`
    const stripped = stripExistingSignature(withPersonal)

    assert.equal(stripped, sampleBody)
    assert.equal(stripExistingSignature(sampleBody), sampleBody)
    assert.equal(stripExistingSignature(''), '')
  })

  it('detectSignatureType accurately detects personal, company and none', () => {
    assert.equal(detectSignatureType(`${sampleBody}\n${personalSig}`), 'personal')
    assert.equal(detectSignatureType(`${sampleBody}\n${companySig}`), 'company')
    assert.equal(detectSignatureType(sampleBody), 'none')
    assert.equal(detectSignatureType(''), 'none')
  })

  it('appendSignatureToHtml prevents duplicate signature insertion', () => {
    // 1st append

    const step1 = appendSignatureToHtml(sampleBody, personalSig)

    assert.ok(step1.includes('wp-signature-personal'))
    assert.equal(step1.split('wp-signature-personal').length - 1, 1)

    // 2nd append with company signature replaces personal signature cleanly

    const step2 = appendSignatureToHtml(step1, companySig)

    assert.ok(!step2.includes('wp-signature-personal'))
    assert.ok(step2.includes('wp-signature-company'))
    assert.equal(step2.split('wp-signature-company').length - 1, 1)

    // Append empty or none strips signature

    const step3 = appendSignatureToHtml(step2, '')

    assert.ok(!step3.includes('wp-signature'))
    assert.equal(step3, sampleBody)
  })

  it('htmlToPlainText converts HTML and synchronizes plain text with entities decoded', () => {
    const html = '<p>Bonjour <strong>Client &amp; Partenaire</strong>,</p><p>Total: 100 &euro;<br>Réf: DEV-01</p>'
    const text = htmlToPlainText(html)

    assert.ok(text.includes('Bonjour Client & Partenaire,'))
    assert.ok(text.includes('Total: 100'))
    assert.ok(text.includes('Réf: DEV-01'))
    assert.ok(!text.includes('<p>'))
    assert.ok(!text.includes('&amp;'))
  })
})

describe('Vuexy CASL Business Partners ability & permissions', () => {
  it('normalizes entity CRUD actions from backend Spatie permissions', () => {
    ability.setTestPermissions(['read-clients', 'create-clients', 'edit-clients', 'delete-clients', 'export-clients'])

    assert.equal(ability.can('read', 'clients'), true)
    assert.equal(ability.can('create', 'clients'), true)
    assert.equal(ability.can('update', 'clients'), true)
    assert.equal(ability.can('edit', 'clients'), true)
    assert.equal(ability.can('delete', 'clients'), true)
    assert.equal(ability.can('export', 'clients'), true)

    // Other partner types remain unauthorized
    assert.equal(ability.can('read', 'suppliers'), false)
    assert.equal(ability.can('create', 'suppliers'), false)
    assert.equal(ability.can('update', 'suppliers'), false)
    assert.equal(ability.can('read', 'service-providers'), false)
    assert.equal(ability.can('read', 'organisations'), false)
  })

  it('handles canonical entity-specific subjects for all partner types', () => {
    ability.setTestPermissions([
      'read-suppliers',
      'edit-suppliers',
      'export-suppliers',
      'create-service-providers',
      'read-organisations',
      'delete-organisations',
    ])

    assert.equal(ability.can('read', 'suppliers'), true)
    assert.equal(ability.can('update', 'suppliers'), true)
    assert.equal(ability.can('export', 'suppliers'), true)
    assert.equal(ability.can('create', 'suppliers'), false)

    assert.equal(ability.can('create', 'service-providers'), true)
    assert.equal(ability.can('read', 'service-providers'), false)

    assert.equal(ability.can('read', 'organisations'), true)
    assert.equal(ability.can('delete', 'organisations'), true)
    assert.equal(ability.can('update', 'organisations'), false)
  })

  it('authorizes dashboard access only with read-bp-dashboard', () => {
    ability.setTestPermissions(['read-clients'])
    assert.equal(ability.can('read', 'bp-dashboard'), false)

    ability.setTestPermissions(['read-bp-dashboard'])
    assert.equal(ability.can('read', 'bp-dashboard'), true)
  })

  it('authorizes bank accounts with read-bp-bank-accounts and manage-bp-bank-accounts', () => {
    ability.setTestPermissions(['read-bp-bank-accounts'])
    assert.equal(ability.can('read', 'bp-bank-accounts'), true)
    assert.equal(ability.can('update', 'bp-bank-accounts'), false)
    assert.equal(ability.can('manage', 'bp-bank-accounts'), false)
    assert.equal(ability.can('create', 'bp-bank-accounts'), false)

    ability.setTestPermissions(['manage-bp-bank-accounts'])
    assert.equal(ability.can('read', 'bp-bank-accounts'), true)
    assert.equal(ability.can('update', 'bp-bank-accounts'), true)
    assert.equal(ability.can('manage', 'bp-bank-accounts'), true)
    assert.equal(ability.can('create', 'bp-bank-accounts'), true)
  })

  it('authorizes contextual email messages and batch history', () => {
    ability.setTestPermissions(['create-email-messages'])
    assert.equal(ability.can('create', 'email-messages'), true)
    assert.equal(ability.can('send', 'email-messages'), true)
    assert.equal(ability.can('read', 'bp-email-batches'), false)

    ability.setTestPermissions(['view-all-bp-email-batches'])
    assert.equal(ability.can('read', 'bp-email-batches'), true)
  })

  it('removes inconsistent generic BusinessPartners ACL checks and isolates module visibility', () => {
    ability.setTestPermissions(['read-BusinessPartners'])

    // Only allows top-level module visibility in app switcher
    assert.equal(ability.can('read', 'BusinessPartners'), true)

    // NEVER grants entity access
    assert.equal(ability.can('read', 'clients'), false)
    assert.equal(ability.can('read', 'suppliers'), false)
    assert.equal(ability.can('read', 'service-providers'), false)
    assert.equal(ability.can('read', 'organisations'), false)

    // NEVER allows generic create / update / delete on BusinessPartners
    assert.equal(ability.can('update', 'BusinessPartners'), false)
    assert.equal(ability.can('edit', 'BusinessPartners'), false)
    assert.equal(ability.can('create', 'BusinessPartners'), false)
    assert.equal(ability.can('delete', 'BusinessPartners'), false)
  })

  it('allows Super Admin full access across all actions and subjects', () => {
    ability.setTestPermissions([], ['Super Admin'])

    assert.equal(ability.can('read', 'clients'), true)
    assert.equal(ability.can('update', 'clients'), true)
    assert.equal(ability.can('delete', 'clients'), true)
    assert.equal(ability.can('export', 'clients'), true)
    assert.equal(ability.can('read', 'bp-dashboard'), true)
    assert.equal(ability.can('manage', 'bp-bank-accounts'), true)
    assert.equal(ability.can('create', 'email-messages'), true)
  })
})

describe('Vuexy CASL HR (People & Skills) ability & permissions', () => {
  it('authorizes employee operations from granular Spatie permissions', () => {
    ability.setTestPermissions(['hr.employees.view', 'hr.employees.create', 'hr.employees.edit', 'hr.employees.archive'])

    assert.equal(ability.can('read', 'employees'), true)
    assert.equal(ability.can('create', 'employees'), true)
    assert.equal(ability.can('update', 'employees'), true)
    assert.equal(ability.can('edit', 'employees'), true)
    assert.equal(ability.can('delete', 'employees'), true)
    assert.equal(ability.can('manage', 'employees'), true)
  })

  it('restricts employee actions when only view permission is granted', () => {
    ability.setTestPermissions(['hr.employees.view'])

    assert.equal(ability.can('read', 'employees'), true)
    assert.equal(ability.can('create', 'employees'), false)
    assert.equal(ability.can('update', 'employees'), false)
    assert.equal(ability.can('delete', 'employees'), false)
  })

  it('authorizes attendance and corrections correctly', () => {
    ability.setTestPermissions(['hr.attendance.view', 'hr.attendance.correct'])

    assert.equal(ability.can('read', 'attendance'), true)
    assert.equal(ability.can('correct', 'attendance'), true)
    assert.equal(ability.can('create', 'attendance'), false)

    ability.setTestPermissions(['hr.attendance.manage'])
    assert.equal(ability.can('create', 'attendance'), true)
    assert.equal(ability.can('update', 'attendance'), true)
    assert.equal(ability.can('correct', 'attendance'), true)
  })

  it('authorizes leave requests and approvals', () => {
    ability.setTestPermissions(['hr.leave.view', 'hr.leave.request'])

    assert.equal(ability.can('read', 'leave'), true)
    assert.equal(ability.can('create', 'leave'), true)
    assert.equal(ability.can('approve', 'leave'), false)

    ability.setTestPermissions(['hr.leave.approve'])
    assert.equal(ability.can('approve', 'leave'), true)
  })

  it('authorizes recruitment and hiring', () => {
    ability.setTestPermissions(['hr.recruitment.view', 'hr.recruitment.hire'])

    assert.equal(ability.can('read', 'recruitment'), true)
    assert.equal(ability.can('hire', 'recruitment'), true)
    assert.equal(ability.can('create', 'recruitment'), false)
  })

  it('authorizes fleet vehicles and drivers', () => {
    ability.setTestPermissions(['hr.fleet.view', 'hr.fleet.manage'])

    assert.equal(ability.can('read', 'fleet'), true)
    assert.equal(ability.can('create', 'fleet'), true)
    assert.equal(ability.can('update', 'fleet'), true)
    assert.equal(ability.can('delete', 'fleet'), true)
  })

  it('authorizes departments, positions, and teams with granular permissions', () => {
    ability.setTestPermissions(['hr.departments.view', 'hr.positions.manage', 'hr.teams.view'])

    assert.equal(ability.can('read', 'departments'), true)
    assert.equal(ability.can('create', 'departments'), false)
    assert.equal(ability.can('read', 'positions'), true)
    assert.equal(ability.can('create', 'positions'), true)
    assert.equal(ability.can('read', 'teams'), true)
    assert.equal(ability.can('create', 'teams'), false)
  })

  it('authorizes confidential employee data and appraisals strictly', () => {
    ability.setTestPermissions(['hr.employees.view'])

    assert.equal(ability.can('read', 'employees'), true)
    assert.equal(ability.can('view-sensitive', 'employees'), false)
    assert.equal(ability.can('read', 'appraisals'), false)

    ability.setTestPermissions(['hr.employees.view-sensitive', 'hr.appraisals.view'])
    assert.equal(ability.can('view-sensitive', 'employees'), true)
    assert.equal(ability.can('read', 'appraisals'), true)
    assert.equal(ability.can('create', 'appraisals'), false)
  })

  it('ensures general read-PeopleSkills does not grant mutation or approval privileges', () => {
    ability.setTestPermissions(['read-PeopleSkills'])

    assert.equal(ability.can('read', 'employees'), true)
    assert.equal(ability.can('read', 'departments'), true)
    assert.equal(ability.can('read', 'attendance'), true)
    assert.equal(ability.can('read', 'leave'), true)

    assert.equal(ability.can('create', 'employees'), false)
    assert.equal(ability.can('delete', 'employees'), false)
    assert.equal(ability.can('correct', 'attendance'), false)
    assert.equal(ability.can('approve', 'leave'), false)
    assert.equal(ability.can('hire', 'recruitment'), false)
    assert.equal(ability.can('view-sensitive', 'employees'), false)
  })
})






