// ── Translations ─────────────────────────────────────────────────────────────
// All UI labels keyed by language code. Content that comes from JSON (prices,
// announcements, region names, notes) is translated there, not here.
var I18N = {
  'zh-hans': {
    brand:        'Atlantic Chican Seafood',
    pageTitle:    '活龙虾价格表',
    subtitle:     '当前价格参考',
    lastUpdated:  '最后更新',
    announcement: '公告',
    priceTable:   '价格表',
    colSize:      '规格',
    colCad:       'CAD/磅',
    colUsd:       'USD/磅',
    colRmb:       'RMB/千克',
    colStatus:    '状态',
    colNote:      '备注',
    cnfRef:       'CNF 参考附加费',
    colRegion:    '目的地',
    colAddOn:     '预计附加费',
    colCnfNote:   '说明',
    contact:      '联系方式',
    wechat:       '微信',
    email:        '邮箱',
    phone:        '电话',
    copy:         '复制',
    copied:       '已复制',
    disclaimer:   '价格会受供应情况、规格、数量、目的地、物流安排和市场情况影响。下单前需以最终确认为准。',
    loadError:    '价格数据加载失败，请稍后刷新页面，或直接通过以下方式联系我们。',
    status: {
      'Available':           '有货',
      'Limited':             '供应有限',
      'Sold Out':            '售罄',
      'Contact Sales First': '请先联系销售',
      'Pre-order Only':      '仅接受预订',
    },
  },
  'zh-hant': {
    brand:        'Atlantic Chican Seafood',
    pageTitle:    '活龍蝦價格表',
    subtitle:     '當前價格參考',
    lastUpdated:  '最後更新',
    announcement: '公告',
    priceTable:   '價格表',
    colSize:      '規格',
    colCad:       'CAD/磅',
    colUsd:       'USD/磅',
    colRmb:       'RMB/千克',
    colStatus:    '狀態',
    colNote:      '備註',
    cnfRef:       'CNF 參考附加費',
    colRegion:    '目的地',
    colAddOn:     '預計附加費',
    colCnfNote:   '說明',
    contact:      '聯絡方式',
    wechat:       '微信',
    email:        '電郵',
    phone:        '電話',
    copy:         '複製',
    copied:       '已複製',
    disclaimer:   '價格會受供應情況、規格、數量、目的地、物流安排和市場情況影響。下單前需以最終確認為準。',
    loadError:    '價格數據載入失敗，請稍後重新整理頁面，或直接透過以下方式聯絡我們。',
    status: {
      'Available':           '有貨',
      'Limited':             '供應有限',
      'Sold Out':            '售罄',
      'Contact Sales First': '請先聯絡銷售',
      'Pre-order Only':      '僅接受預訂',
    },
  },
  'en': {
    brand:        'Atlantic Chican Seafood',
    pageTitle:    'Live Lobster Price Board',
    subtitle:     'Current price indication',
    lastUpdated:  'Last updated',
    announcement: 'Announcement',
    priceTable:   'Price Table',
    colSize:      'Size',
    colCad:       'CAD/lb',
    colUsd:       'USD/lb',
    colRmb:       'RMB/kg',
    colStatus:    'Status',
    colNote:      'Notes',
    cnfRef:       'CNF Reference Add-on',
    colRegion:    'Destination',
    colAddOn:     'Est. Add-on',
    colCnfNote:   'Notes',
    contact:      'Contact',
    wechat:       'WeChat',
    email:        'Email',
    phone:        'Phone',
    copy:         'Copy',
    copied:       'Copied!',
    disclaimer:   'Prices are subject to availability, size, volume, destination, logistics, and market conditions. Final confirmation is required before order placement.',
    loadError:    'Failed to load price data. Please refresh the page later or contact us directly.',
    status: {
      'Available':           'Available',
      'Limited':             'Limited',
      'Sold Out':            'Sold Out',
      'Contact Sales First': 'Contact Sales First',
      'Pre-order Only':      'Pre-order Only',
    },
  },
};

// Maps each status string to its CSS class suffix (defined in styles.css)
var STATUS_CLASS = {
  'Available':           'available',
  'Limited':             'limited',
  'Sold Out':            'sold-out',
  'Contact Sales First': 'contact',
  'Pre-order Only':      'preorder',
};

// Maps our language codes to the JSON field names for translated content
var LANG_KEY = {
  'zh-hans': 'zhHans',
  'zh-hant': 'zhHant',
  'en':       'en',
};

// ── State ─────────────────────────────────────────────────────────────────────
var currentLang = 'zh-hans';
var priceData   = null;

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  currentLang = localStorage.getItem('lang') || 'zh-hans';
  applyLang(currentLang);
  updateStaticLabels();

  document.querySelectorAll('.lang-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      switchLanguage(btn.dataset.lang);
    });
  });

  loadData();
});

// ── Language ──────────────────────────────────────────────────────────────────
function switchLanguage(lang) {
  currentLang = lang;
  localStorage.setItem('lang', lang);
  applyLang(lang);
  if (priceData) {
    renderPage(priceData);
  } else {
    updateStaticLabels();
  }
}

// Update active button highlight and the html[lang] attribute for accessibility
function applyLang(lang) {
  document.querySelectorAll('.lang-btn').forEach(function (btn) {
    btn.classList.toggle('active', btn.dataset.lang === lang);
  });
  var htmlLang = lang === 'zh-hans' ? 'zh-Hans'
               : lang === 'zh-hant' ? 'zh-Hant'
               : 'en';
  document.documentElement.setAttribute('lang', htmlLang);
}

// Update text that does not depend on the JSON payload
function updateStaticLabels() {
  var t = I18N[currentLang];
  setText('brand-name',    t.brand);
  setText('page-title',    t.pageTitle);
  setText('page-subtitle', t.subtitle);
  document.title = t.pageTitle + ' — ' + t.brand;
}

// ── Data loading ──────────────────────────────────────────────────────────────
function loadData() {
  fetch('data/current-prices.json')
    .then(function (resp) {
      if (!resp.ok) throw new Error('HTTP ' + resp.status);
      return resp.json();
    })
    .then(function (data) {
      priceData = data;
      renderPage(data);
    })
    .catch(function () {
      showError();
    });
}

// ── Full page render ──────────────────────────────────────────────────────────
// Called on initial load and on every language switch.
function renderPage(data) {
  var t  = I18N[currentLang];
  var lk = LANG_KEY[currentLang];

  updateStaticLabels();
  renderLastUpdated(data, t);
  renderAnnouncement(data, t, lk);
  renderPriceTable(data, t, lk);
  renderCnfTable(data, t, lk);
  renderContacts(data, t);
  renderDisclaimer(t);

  // Reveal all content sections and clear the loading / error messages
  document.getElementById('sections-wrapper').removeAttribute('hidden');
  document.getElementById('loading-msg').style.display = 'none';
  document.getElementById('error-msg').classList.remove('visible');
}

// ── Section renderers ─────────────────────────────────────────────────────────
function renderLastUpdated(data, t) {
  setText('last-updated', t.lastUpdated + ': ' + (data.lastUpdated || '—'));
}

function renderAnnouncement(data, t, lk) {
  setText('announcement-title', t.announcement);
  var text = (data.announcement && data.announcement[lk]) ? data.announcement[lk] : '';
  setText('announcement-text', text);
}

function renderPriceTable(data, t, lk) {
  setText('price-title', t.priceTable);
  setText('col-size',    t.colSize);
  setText('col-cad',     t.colCad);
  setText('col-usd',     t.colUsd);
  setText('col-rmb',     t.colRmb);
  setText('col-status',  t.colStatus);
  setText('col-note',    t.colNote);

  var tbody = document.getElementById('price-tbody');
  tbody.innerHTML = '';

  (data.prices || []).forEach(function (row) {
    var statusLabel = t.status[row.status] || row.status;
    var statusCls   = 'status-badge status-' + (STATUS_CLASS[row.status] || 'available');
    var note        = (row.note && row.note[lk]) ? row.note[lk] : '';

    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td>' + esc(row.size)     + '</td>' +
      '<td>' + esc(row.cadPerLb) + '</td>' +
      '<td>' + esc(row.usdPerLb) + '</td>' +
      '<td>' + esc(row.rmbPerKg) + '</td>' +
      '<td><span class="' + statusCls + '">' + esc(statusLabel) + '</span></td>' +
      '<td>' + esc(note)         + '</td>';
    tbody.appendChild(tr);
  });
}

function renderCnfTable(data, t, lk) {
  setText('cnf-title',    t.cnfRef);
  setText('col-region',   t.colRegion);
  setText('col-addon',    t.colAddOn);
  setText('col-cnf-note', t.colCnfNote);

  var tbody = document.getElementById('cnf-tbody');
  tbody.innerHTML = '';

  (data.cnfAddOns || []).forEach(function (row) {
    var region = (row.region && row.region[lk]) ? row.region[lk] : '';
    var note   = (row.note   && row.note[lk])   ? row.note[lk]   : '';

    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td>' + esc(region)    + '</td>' +
      '<td>' + esc(row.addOn) + '</td>' +
      '<td>' + esc(note)      + '</td>';
    tbody.appendChild(tr);
  });
}

function renderContacts(data, t) {
  setText('contact-title', t.contact);

  var contacts = data.contacts || {};
  var list     = document.getElementById('contact-list');
  list.innerHTML = '';

  var items = [
    { label: t.wechat, value: contacts.wechat },
    { label: t.email,  value: contacts.email  },
    { label: t.phone,  value: contacts.phone  },
  ];

  items.forEach(function (item) {
    if (!item.value) return;

    var row = document.createElement('div');
    row.className = 'contact-row';

    var label = document.createElement('span');
    label.className   = 'contact-label';
    label.textContent = item.label;

    var value = document.createElement('span');
    value.className   = 'contact-value';
    value.textContent = item.value;

    var btn = document.createElement('button');
    btn.className  = 'copy-btn';
    btn.textContent = t.copy;
    btn.setAttribute('aria-label', t.copy + ' ' + item.label);
    btn.addEventListener('click', function () {
      copyToClipboard(item.value, btn, t.copied, t.copy);
    });

    row.appendChild(label);
    row.appendChild(value);
    row.appendChild(btn);
    list.appendChild(row);
  });
}

function renderDisclaimer(t) {
  setText('disclaimer-text', t.disclaimer);
}

// ── Error state ───────────────────────────────────────────────────────────────
function showError() {
  var t = I18N[currentLang];
  updateStaticLabels();
  var el = document.getElementById('error-msg');
  el.textContent = t.loadError;
  el.classList.add('visible');
  document.getElementById('loading-msg').style.display = 'none';
}

// ── Clipboard ─────────────────────────────────────────────────────────────────
function copyToClipboard(text, btn, copiedLabel, copyLabel) {
  function onSuccess() {
    btn.textContent = copiedLabel;
    btn.classList.add('copied');
    setTimeout(function () {
      btn.textContent = copyLabel;
      btn.classList.remove('copied');
    }, 2000);
  }

  // Modern Clipboard API — works in all current browsers over HTTPS or localhost
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(onSuccess).catch(legacyCopy);
  } else {
    legacyCopy();
  }

  // Fallback for older browsers or non-secure contexts
  function legacyCopy() {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none;';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    try {
      document.execCommand('copy');
      onSuccess();
    } catch (e) {
      // Silent fail — clipboard unavailable
    }
    document.body.removeChild(ta);
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function setText(id, text) {
  var el = document.getElementById(id);
  if (el) el.textContent = text;
}

// Escape user-facing strings before inserting into innerHTML
function esc(str) {
  if (str == null) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
