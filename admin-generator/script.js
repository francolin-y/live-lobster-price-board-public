'use strict';

// ── State ──────────────────────────────────────────────────────────────────
let originalData       = {};   // full parsed JSON from the file
let priceRows          = [];   // array of price row objects (mutable)
let cnfRows            = [];   // array of CNF row objects (mutable)
let priceCounter       = 0;    // monotonic id for DOM keys
let cnfCounter         = 0;
let selectedImportFile = null; // file chosen in the import picker

// ── Helpers ────────────────────────────────────────────────────────────────
function el(id) { return document.getElementById(id); }

function val(id)  { const e = el(id); return e ? e.value.trim() : ''; }
function setVal(id, v) { const e = el(id); if (e) e.value = v ?? ''; }

function showError(msg) {
  const b = el('error-banner');
  b.textContent = msg;
  b.classList.add('visible');
}

function renderValidationPanel(errors, warnings) {
  const body = el('validation-panel-body');
  if (body) {
    const hasErrors   = errors.length > 0;
    const hasWarnings = warnings.length > 0;
    let html = '';
    if (!hasErrors) {
      html += '<div class="vp-status passed">&#10003; Validation passed</div>';
    } else {
      html += `<div class="vp-status has-errors">&#10007; ${errors.length} validation error${errors.length !== 1 ? 's' : ''} — export blocked</div>`;
      html += '<ul class="vp-list vp-errors">' + errors.map(m => `<li>${esc(m)}</li>`).join('') + '</ul>';
    }
    if (hasWarnings) {
      html += '<div class="vp-warning-header">Warnings</div>';
      html += '<ul class="vp-list vp-warnings">' + warnings.map(m => `<li>${esc(m)}</li>`).join('') + '</ul>';
    }
    body.innerHTML = html;
  }

  const blocked = el('validation-error');
  if (blocked) {
    if (errors.length > 0) {
      blocked.textContent = 'Export blocked. Please fix validation errors first.';
      blocked.classList.add('visible');
    } else {
      blocked.classList.remove('visible');
    }
  }
}

// ── Import ─────────────────────────────────────────────────────────────────
function setImportMessage(message, type) {
  const msgEl = el('importJsonMessage');
  if (!msgEl) return;
  msgEl.textContent = message;
  msgEl.className = 'status-message ' + (
    type === 'success' ? 'status-success' :
    type === 'error'   ? 'status-error'   :
                         'status-info'
  );
}

function isValidImportedPriceBoardData(data) {
  return (
    data !== null &&
    typeof data === 'object' &&
    'dataStatus'  in data &&
    'lastUpdated' in data &&
    'contacts'    in data &&
    Array.isArray(data.prices) &&
    Array.isArray(data.cnfAddOns)
  );
}

function applyImportedData(importedData) {
  console.log('Imported JSON applied to editor');
  originalData = importedData;
  populateForm(importedData);
}

function loadSelectedImportFile() {
  console.log('Load Selected JSON clicked');
  if (!selectedImportFile) {
    setImportMessage('Please choose a JSON file first.', 'error');
    return;
  }

  const reader = new FileReader();

  reader.onload = function(event) {
    let importedData;
    try {
      importedData = JSON.parse(event.target.result);
      console.log('Imported JSON parsed:', importedData);
    } catch (error) {
      console.error('Import JSON parse error:', error);
      setImportMessage('Import failed. The file is not valid JSON.', 'error');
      return;
    }

    if (!isValidImportedPriceBoardData(importedData)) {
      setImportMessage('Import failed. The selected file is not a valid current-prices.json file.', 'error');
      return;
    }

    applyImportedData(importedData);
    setImportMessage('JSON imported successfully. Please review all fields before export.', 'success');
  };

  reader.onerror = function() {
    setImportMessage('Import failed. The file could not be read.', 'error');
  };

  reader.readAsText(selectedImportFile);
}

// ── Load ───────────────────────────────────────────────────────────────────
async function loadData() {
  try {
    const resp = await fetch('../data/current-prices.json', { cache: 'no-store' });
    if (!resp.ok) throw new Error(`HTTP ${resp.status} ${resp.statusText}`);
    originalData = await resp.json();
    populateForm(originalData);
  } catch (err) {
    showError(
      'Failed to load ../data/current-prices.json. ' +
      'Make sure you are opening this page through a local server (e.g. Live Server in VS Code) ' +
      'and that the file exists. Error: ' + err.message
    );
  }
}

// ── Populate form ──────────────────────────────────────────────────────────
function populateForm(d) {
  // Status
  setVal('dataStatus', d.dataStatus || 'sample');
  setVal('lastUpdated', d.lastUpdated || '');

  // Announcement
  setVal('ann-zhHans', d.announcement?.zhHans || '');
  setVal('ann-zhHant', d.announcement?.zhHant || '');
  setVal('ann-en',     d.announcement?.en     || '');

  // Contacts
  setVal('wechat', d.contacts?.wechat || '');
  setVal('email',  d.contacts?.email  || '');
  setVal('phone',  d.contacts?.phone  || '');

  // Prices
  priceRows = (d.prices || []).map((p, i) => ({ ...p, _key: ++priceCounter }));
  renderPriceRows();

  // CNF
  cnfRows = (d.cnfAddOns || []).map((c, i) => ({ ...c, _key: ++cnfCounter }));
  renderCnfRows();

  // Disclaimer
  const discSection = el('disclaimer-section');
  if (d.disclaimer) {
    discSection.style.display = '';
    setVal('disc-zhHans', d.disclaimer.zhHans || '');
    setVal('disc-zhHant', d.disclaimer.zhHant || '');
    setVal('disc-en',     d.disclaimer.en     || '');
  } else {
    discSection.style.display = 'none';
  }

  updatePreview();
}

// ── Price rows ─────────────────────────────────────────────────────────────
const STATUS_OPTIONS = ['Available', 'Limited', 'Sold Out', 'Contact Sales First', 'Pre-order Only'];

function buildStatusOptions(selected) {
  return STATUS_OPTIONS.map(s =>
    `<option value="${s}"${s === selected ? ' selected' : ''}>${s}</option>`
  ).join('');
}

function renderPriceRows() {
  const badge = el('price-count-badge');
  if (badge) badge.textContent = priceRows.length;
  const container = el('price-rows-container');
  container.innerHTML = '';
  priceRows.forEach((row, idx) => {
    const div = document.createElement('div');
    div.className = 'item-card';
    div.dataset.key = row._key;
    div.innerHTML = `
      <div class="item-card-header">
        <span>Row ${idx + 1} — ${row.size || '(new)'}</span>
        <div class="item-card-actions">
          <button class="btn btn-secondary btn-sm" onclick="movePriceRow(${row._key}, -1)" title="Move up">↑</button>
          <button class="btn btn-secondary btn-sm" onclick="movePriceRow(${row._key}, 1)"  title="Move down">↓</button>
          <button class="btn btn-danger btn-sm"    onclick="deletePriceRow(${row._key})">Delete</button>
        </div>
      </div>
      <div class="item-card-body">
        <div class="field-row">
          <div class="field-group">
            <label>ID</label>
            <input type="text" data-field="id" value="${esc(row.id || '')}" placeholder="e.g. s100-115">
          </div>
          <div class="field-group">
            <label>Size</label>
            <input type="text" data-field="size" value="${esc(row.size || '')}" placeholder="e.g. 1.00–1.15 lb">
          </div>
        </div>
        <div class="field-row triple">
          <div class="field-group">
            <label>CAD / lb</label>
            <input type="text" data-field="cadPerLb" value="${esc(row.cadPerLb || '')}" placeholder="CAD $10.50/lb">
          </div>
          <div class="field-group">
            <label>USD / lb</label>
            <input type="text" data-field="usdPerLb" value="${esc(row.usdPerLb || '')}" placeholder="USD $7.65/lb">
          </div>
          <div class="field-group">
            <label>RMB / kg</label>
            <input type="text" data-field="rmbPerKg" value="${esc(row.rmbPerKg || '')}" placeholder="RMB ¥118/kg">
          </div>
        </div>
        <div class="field-row single">
          <div class="field-group">
            <label>Status</label>
            <select data-field="status">${buildStatusOptions(row.status)}</select>
          </div>
        </div>
        <div class="section-label">Note</div>
        <div class="field-row">
          <div class="field-group">
            <label>简体中文 (zhHans)</label>
            <textarea data-field="note.zhHans">${esc(row.note?.zhHans || '')}</textarea>
          </div>
          <div class="field-group">
            <label>繁體中文 (zhHant)</label>
            <textarea data-field="note.zhHant">${esc(row.note?.zhHant || '')}</textarea>
          </div>
        </div>
        <div class="field-row single">
          <div class="field-group">
            <label>English (en)</label>
            <textarea data-field="note.en">${esc(row.note?.en || '')}</textarea>
          </div>
        </div>
      </div>
    `;
    // Live-update state on change
    div.querySelectorAll('[data-field]').forEach(input => {
      input.addEventListener('input', () => {
        syncPriceRow(row._key, div);
        updatePreview();
      });
    });
    container.appendChild(div);
  });
  updatePreview();
}

function syncPriceRow(key, div) {
  const row = priceRows.find(r => r._key === key);
  if (!row) return;
  div.querySelectorAll('[data-field]').forEach(input => {
    const field = input.dataset.field;
    if (field.startsWith('note.')) {
      const lang = field.split('.')[1];
      if (!row.note) row.note = {};
      row.note[lang] = input.value;
    } else {
      row[field] = input.value;
    }
  });
  // Update header label
  const header = div.querySelector('.item-card-header span');
  const idx = priceRows.findIndex(r => r._key === key);
  if (header) header.textContent = `Row ${idx + 1} — ${row.size || '(new)'}`;
}

function addPriceRow() {
  priceRows.push({
    _key: ++priceCounter,
    id: '', size: '', cadPerLb: '', usdPerLb: '', rmbPerKg: '',
    status: 'Available',
    note: { zhHans: '', zhHant: '', en: '' }
  });
  renderPriceRows();
  // Scroll to last row
  const container = el('price-rows-container');
  container.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function deletePriceRow(key) {
  priceRows = priceRows.filter(r => r._key !== key);
  renderPriceRows();
}

function movePriceRow(key, dir) {
  const idx = priceRows.findIndex(r => r._key === key);
  const target = idx + dir;
  if (target < 0 || target >= priceRows.length) return;
  [priceRows[idx], priceRows[target]] = [priceRows[target], priceRows[idx]];
  renderPriceRows();
}

// ── CNF rows ───────────────────────────────────────────────────────────────
function renderCnfRows() {
  const badge = el('cnf-count-badge');
  if (badge) badge.textContent = cnfRows.length;
  const container = el('cnf-rows-container');
  container.innerHTML = '';
  cnfRows.forEach((row, idx) => {
    const div = document.createElement('div');
    div.className = 'item-card';
    div.dataset.key = row._key;
    div.innerHTML = `
      <div class="item-card-header">
        <span>CNF ${idx + 1} — ${row.region?.en || '(new)'}</span>
        <div class="item-card-actions">
          <button class="btn btn-danger btn-sm" onclick="deleteCnfRow(${row._key})">Delete</button>
        </div>
      </div>
      <div class="item-card-body">
        <div class="field-row">
          <div class="field-group">
            <label>ID</label>
            <input type="text" data-field="id" value="${esc(row.id || '')}" placeholder="e.g. cnf-mainland-china">
          </div>
          <div class="field-group">
            <label>Add-On (display string)</label>
            <input type="text" data-field="addOn" value="${esc(row.addOn || '')}" placeholder="e.g. +CAD $3.00/lb">
          </div>
        </div>
        <div class="section-label">Region</div>
        <div class="field-row triple">
          <div class="field-group">
            <label>简体中文 (zhHans)</label>
            <input type="text" data-field="region.zhHans" value="${esc(row.region?.zhHans || '')}">
          </div>
          <div class="field-group">
            <label>繁體中文 (zhHant)</label>
            <input type="text" data-field="region.zhHant" value="${esc(row.region?.zhHant || '')}">
          </div>
          <div class="field-group">
            <label>English (en)</label>
            <input type="text" data-field="region.en" value="${esc(row.region?.en || '')}">
          </div>
        </div>
        <div class="section-label">Note</div>
        <div class="field-row">
          <div class="field-group">
            <label>简体中文 (zhHans)</label>
            <textarea data-field="note.zhHans">${esc(row.note?.zhHans || '')}</textarea>
          </div>
          <div class="field-group">
            <label>繁體中文 (zhHant)</label>
            <textarea data-field="note.zhHant">${esc(row.note?.zhHant || '')}</textarea>
          </div>
        </div>
        <div class="field-row single">
          <div class="field-group">
            <label>English (en)</label>
            <textarea data-field="note.en">${esc(row.note?.en || '')}</textarea>
          </div>
        </div>
      </div>
    `;
    div.querySelectorAll('[data-field]').forEach(input => {
      input.addEventListener('input', () => {
        syncCnfRow(row._key, div);
        updatePreview();
      });
    });
    container.appendChild(div);
  });
  updatePreview();
}

function syncCnfRow(key, div) {
  const row = cnfRows.find(r => r._key === key);
  if (!row) return;
  div.querySelectorAll('[data-field]').forEach(input => {
    const field = input.dataset.field;
    if (field.startsWith('region.')) {
      const lang = field.split('.')[1];
      if (!row.region) row.region = {};
      row.region[lang] = input.value;
    } else if (field.startsWith('note.')) {
      const lang = field.split('.')[1];
      if (!row.note) row.note = {};
      row.note[lang] = input.value;
    } else {
      row[field] = input.value;
    }
  });
  const header = div.querySelector('.item-card-header span');
  const idx = cnfRows.findIndex(r => r._key === key);
  if (header) header.textContent = `CNF ${idx + 1} — ${row.region?.en || '(new)'}`;
}

function addCnfRow() {
  cnfRows.push({
    _key: ++cnfCounter,
    id: '', addOn: '',
    region: { zhHans: '', zhHant: '', en: '' },
    note:   { zhHans: '', zhHant: '', en: '' }
  });
  renderCnfRows();
  el('cnf-rows-container').lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function deleteCnfRow(key) {
  cnfRows = cnfRows.filter(r => r._key !== key);
  renderCnfRows();
}

// ── Build JSON object ──────────────────────────────────────────────────────
function buildJSON() {
  // Collect form values
  const dataStatus  = val('dataStatus');
  const lastUpdated = val('lastUpdated');

  const announcement = {
    zhHans: val('ann-zhHans'),
    zhHant: val('ann-zhHant'),
    en:     val('ann-en')
  };

  const contacts = {
    wechat: val('wechat'),
    email:  val('email'),
    phone:  val('phone')
  };

  const prices = priceRows.map(r => {
    const out = {
      id:       r.id,
      size:     r.size,
      cadPerLb: r.cadPerLb,
      usdPerLb: r.usdPerLb,
      rmbPerKg: r.rmbPerKg,
      status:   r.status,
      note: {
        zhHans: r.note?.zhHans || '',
        zhHant: r.note?.zhHant || '',
        en:     r.note?.en     || ''
      }
    };
    return out;
  });

  const cnfAddOns = cnfRows.map(r => ({
    id:    r.id,
    region: {
      zhHans: r.region?.zhHans || '',
      zhHant: r.region?.zhHant || '',
      en:     r.region?.en     || ''
    },
    addOn: r.addOn,
    note: {
      zhHans: r.note?.zhHans || '',
      zhHant: r.note?.zhHant || '',
      en:     r.note?.en     || ''
    }
  }));

  const obj = {
    dataStatus,
    lastUpdated,
    // Preserve fields the public page may not render
    ...(originalData.dataNotes          && { dataNotes:          originalData.dataNotes          }),
    ...(originalData.priceDisplayRules  && { priceDisplayRules:  originalData.priceDisplayRules  }),
    ...(originalData.liveSwitchChecklist && { liveSwitchChecklist: originalData.liveSwitchChecklist }),
    announcement,
    contacts,
    prices,
    cnfAddOns
  };

  // Include disclaimer only if section is visible
  const discSection = el('disclaimer-section');
  if (discSection && discSection.style.display !== 'none') {
    obj.disclaimer = {
      zhHans: val('disc-zhHans'),
      zhHant: val('disc-zhHant'),
      en:     val('disc-en')
    };
  } else if (originalData.disclaimer) {
    obj.disclaimer = originalData.disclaimer;
  }

  return obj;
}

// ── Validate ───────────────────────────────────────────────────────────────
function validate(obj) {
  const errors   = [];
  const warnings = [];

  // Top-level
  if (!obj.dataStatus || !['sample', 'live'].includes(obj.dataStatus)) {
    errors.push('dataStatus must be "sample" or "live".');
  }
  if (!obj.lastUpdated) {
    errors.push('lastUpdated must not be empty.');
  }
  if (!obj.contacts) {
    errors.push('contacts block is missing.');
  }
  if (!Array.isArray(obj.prices) || obj.prices.length === 0) {
    errors.push('prices must be a non-empty array.');
  }
  if (!Array.isArray(obj.cnfAddOns)) {
    errors.push('cnfAddOns must be an array.');
  }

  // Contacts
  if (obj.contacts) {
    if (!obj.contacts.wechat) errors.push('contacts.wechat must not be empty (use TBD if unknown).');
    if (!obj.contacts.email)  errors.push('contacts.email must not be empty (use TBD if unknown).');
    if (!obj.contacts.phone)  errors.push('contacts.phone must not be empty (use TBD if unknown).');
  }

  // Price rows
  if (Array.isArray(obj.prices) && obj.prices.length > 0) {
    const seenIds = new Set();
    obj.prices.forEach((row, idx) => {
      const n   = idx + 1;
      const ref = row.id ? `"${row.id}"` : `row ${n}`;
      if (!row.id) {
        errors.push(`Price row ${n}: id must not be empty.`);
      } else if (seenIds.has(row.id)) {
        errors.push(`Price row ${n}: id "${row.id}" is duplicated.`);
      } else {
        seenIds.add(row.id);
      }
      if (!row.size)     errors.push(`Price ${ref}: size must not be empty.`);
      if (!row.cadPerLb) errors.push(`Price ${ref}: cadPerLb must not be empty.`);
      if (!row.usdPerLb) errors.push(`Price ${ref}: usdPerLb must not be empty.`);
      if (!row.rmbPerKg) errors.push(`Price ${ref}: rmbPerKg must not be empty.`);
      if (!STATUS_OPTIONS.includes(row.status)) {
        errors.push(`Price ${ref}: status "${row.status}" is not a valid value.`);
      }
      if (!row.note) {
        errors.push(`Price ${ref}: note block is missing.`);
      } else {
        if (!row.note.zhHans) errors.push(`Price ${ref}: note.zhHans must not be empty.`);
        if (!row.note.zhHant) errors.push(`Price ${ref}: note.zhHant must not be empty.`);
        if (!row.note.en)     errors.push(`Price ${ref}: note.en must not be empty.`);
      }
    });
  }

  // CNF rows
  if (Array.isArray(obj.cnfAddOns) && obj.cnfAddOns.length > 0) {
    const seenIds = new Set();
    obj.cnfAddOns.forEach((row, idx) => {
      const n   = idx + 1;
      const ref = row.id ? `"${row.id}"` : `row ${n}`;
      if (!row.id) {
        errors.push(`CNF row ${n}: id must not be empty.`);
      } else if (seenIds.has(row.id)) {
        errors.push(`CNF row ${n}: id "${row.id}" is duplicated.`);
      } else {
        seenIds.add(row.id);
      }
      if (!row.addOn) errors.push(`CNF ${ref}: addOn must not be empty.`);
      if (!row.region) {
        errors.push(`CNF ${ref}: region block is missing.`);
      } else {
        if (!row.region.zhHans) errors.push(`CNF ${ref}: region.zhHans must not be empty.`);
        if (!row.region.zhHant) errors.push(`CNF ${ref}: region.zhHant must not be empty.`);
        if (!row.region.en)     errors.push(`CNF ${ref}: region.en must not be empty.`);
      }
      if (!row.note) {
        errors.push(`CNF ${ref}: note block is missing.`);
      } else {
        if (!row.note.zhHans) errors.push(`CNF ${ref}: note.zhHans must not be empty.`);
        if (!row.note.zhHant) errors.push(`CNF ${ref}: note.zhHant must not be empty.`);
        if (!row.note.en)     errors.push(`CNF ${ref}: note.en must not be empty.`);
      }
    });
  }

  // Warnings
  const isLive = obj.dataStatus === 'live';
  if (obj.dataStatus === 'sample') {
    warnings.push('dataStatus is sample. The public page will show the sample data banner.');
  }

  const hasTBDPrice = Array.isArray(obj.prices) && obj.prices.some(r =>
    r.cadPerLb === 'TBD' || r.usdPerLb === 'TBD' || r.rmbPerKg === 'TBD'
  );
  if (hasTBDPrice) {
    warnings.push(isLive
      ? 'Live data contains TBD price fields. Confirm before publishing.'
      : 'Some price fields are still TBD.'
    );
  }

  const hasTBDContact = obj.contacts && (
    obj.contacts.wechat === 'TBD' ||
    obj.contacts.email  === 'TBD' ||
    obj.contacts.phone  === 'TBD'
  );
  if (hasTBDContact) {
    warnings.push('Some contact fields are still TBD.');
  }

  return { errors, warnings };
}

// ── Preview + live validation ──────────────────────────────────────────────
function updatePreview() {
  const preview = el('json-preview');
  try {
    const obj = buildJSON();
    if (preview) preview.textContent = JSON.stringify(obj, null, 2);
    const { errors, warnings } = validate(obj);
    renderValidationPanel(errors, warnings);
  } catch (e) {
    if (preview) preview.textContent = '(error building preview)';
  }
}

function togglePreview() {
  const container = el('preview-container');
  const btn = el('preview-toggle-btn');
  const open = container.classList.toggle('open');
  btn.textContent = open ? 'Hide JSON preview ▲' : 'Show JSON preview ▼';
}

// ── Export ─────────────────────────────────────────────────────────────────
function exportJSON() {
  const obj = buildJSON();
  const { errors, warnings } = validate(obj);
  renderValidationPanel(errors, warnings);

  if (errors.length > 0) {
    el('validation-panel-card')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    return;
  }

  const json  = JSON.stringify(obj, null, 2);
  const blob  = new Blob([json], { type: 'application/json' });
  const url   = URL.createObjectURL(blob);
  const a     = document.createElement('a');
  a.href      = url;
  a.download  = 'current-prices.json';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);

  const successMsg = el('export-success');
  if (successMsg) {
    successMsg.classList.add('visible');
    successMsg.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
}

// ── Escape HTML for attribute injection ───────────────────────────────────
function esc(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

// ── Wire up static form change listeners ──────────────────────────────────
function wireStaticListeners() {
  const ids = [
    'dataStatus', 'lastUpdated',
    'ann-zhHans', 'ann-zhHant', 'ann-en',
    'wechat', 'email', 'phone',
    'disc-zhHans', 'disc-zhHant', 'disc-en'
  ];
  ids.forEach(id => {
    const e = el(id);
    if (e) e.addEventListener('input', updatePreview);
  });
}

// ── Expose globals for inline onclick handlers ────────────────────────────
window.addPriceRow    = addPriceRow;
window.deletePriceRow = deletePriceRow;
window.movePriceRow   = movePriceRow;
window.addCnfRow      = addCnfRow;
window.deleteCnfRow   = deleteCnfRow;
window.exportJSON     = exportJSON;
window.togglePreview  = togglePreview;

// ── Init ───────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  wireStaticListeners();
  loadData();

  // Import JSON — explicit event binding (no inline handlers)
  const importInput    = el('importJsonFile');
  const loadButton     = el('loadImportedJsonButton');
  const selectedNameEl = el('selectedImportFileName');

  if (importInput) {
    importInput.addEventListener('change', function(event) {
      console.log('Import input changed');
      selectedImportFile = (event.target.files && event.target.files[0])
        ? event.target.files[0]
        : null;
      console.log('Selected import file:', selectedImportFile ? selectedImportFile.name : 'none');
      if (selectedNameEl) {
        selectedNameEl.textContent = selectedImportFile
          ? selectedImportFile.name
          : 'No file selected';
      }
      if (selectedImportFile) {
        setImportMessage('File selected. Click Load Selected JSON to import.', 'info');
      }
    });
  }

  if (loadButton) {
    loadButton.addEventListener('click', function(event) {
      event.preventDefault();
      loadSelectedImportFile();
    });
  }
});
