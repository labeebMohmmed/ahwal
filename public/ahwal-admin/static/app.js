/* app.js — Admin control for TableAddModel / TableProcReq (RTL, Arabic UI) */

/* ===================== Constants & API endpoints ===================== */
const API_LIST          = '/ahwal-admin/api/models_list.php';
const API_GET           = '/ahwal-admin/api/models_get.php?id=';
const API_UPDATE        = '/ahwal-admin/api/models_update.php';
const API_DELETE        = '/ahwal-admin/api/models_delete.php';
const API_ADD           = '/ahwal-admin/api/models_addrow.php';

const API_REQ_GET       = '/ahwal-admin/api/reqs_get.php?id=';
const API_REQ_ADDROW    = '/ahwal-admin/api/reqs_addrow.php';
const API_LINK_REQ      = '/ahwal-admin/api/models_link_req.php';
const API_COMPOUND_SAVE = '/ahwal-admin/api/compound_save.php';

const API_ADD_FIELD     = '/ahwal-admin/api/models_addfield.php';        // guarded DDL
const API_COLS          = '/ahwal-admin/api/models_columns.php';
const API_SETORDER      = '/ahwal-admin/api/models_setcolorder.php';
const API_DROP_COL      = '/ahwal-admin/api/models_dropcol.php';
const API_DISTINCT      = '/ahwal-admin/api/models_distinct.php?column=';

/* Hide these columns in the GRID (not in the drawer) */
const HIDE_COLS = new Set([
  'ID', 'ReqID',
  'itext1Length','itext2Length','itext3Length','itext4Length','itext5Length','itext6Length',
  'icombo1Length','icombo2Length','ibtnAdd1Length',
  'dateType1',
  'UpdatedAt','UpdatedBy'
]);

/* Req short fields order (treated as short text, not textarea) */
const REQ_FIELDS_ORDER = [
  'المطلوب_رقم1','المطلوب_رقم2','المطلوب_رقم3',
  'المطلوب_رقم4','المطلوب_رقم5','المطلوب_رقم6',
  'المطلوب_رقم7','المطلوب_رقم8','المطلوب_رقم9'
];

/* Main group options */
const MAINGROUP_OPTIONS = [
  'إفادة لمن يهمه الأمر',
  'إقرار',
  'إقرار مشفوع باليمين',
  'توكيل',
  'مخاطبة لتاشيرة دخول'
];

/* ===================== DOM refs ===================== */
(() => {
  const grid         = document.getElementById('grid');
  const thead        = grid.querySelector('thead');
  const tbody        = grid.querySelector('tbody');

  const qEl          = document.getElementById('q');
  const btnSearch    = document.getElementById('btnSearch');
  const status       = document.getElementById('status');
  const prev         = document.getElementById('prev');
  const next         = document.getElementById('next');
  const pageInfo     = document.getElementById('pageInfo');
  const btnNew       = document.getElementById('btnNew');

  const drawer           = document.getElementById('drawer');
  const drawerBackdrop   = document.getElementById('drawerBackdrop');
  const drawerTitle      = document.getElementById('drawerTitle');
  const drawerBody       = document.getElementById('drawerBody');
  const drawerClose      = document.getElementById('drawerClose');
  const btnClose2        = document.getElementById('btnClose2');
  const btnCopyJson      = document.getElementById('btnCopyJson');
  const btnEdit          = document.getElementById('btnEdit');
  const btnSave          = document.getElementById('btnSave');
  const btnCancel        = document.getElementById('btnCancel');
  const btnDelete        = document.getElementById('btnDelete');

  /* paging state */
  let skip = 0, take = 10, total = 0, columns = [];
  let rowsCache = [];
  let currentRow = null;
  let currentMode = 'view';

  /* ===================== helpers (UI & data) ===================== */

  function toBool(v){
    const s = String(v ?? '').trim().toLowerCase();
    return s==='1'||s==='true'||s==='on'||s==='yes'||s==='y'||s==='نعم';
  }

  function normalizeStr(s){ return String(s ?? '').replace(/\s+/g,' ').trim(); }

  // Treat empty or 'غير مدرج' as empty for Req fields
  function reqIsEmptyVal(v){
    const s = normalizeStr(v);
    return s === '' || s === 'غير مدرج';
  }

  function createInput(value = '', editable = false){
    const el = document.createElement('input');
    el.type = 'text'; el.value = value ?? '';
    el.readOnly = !editable; el.disabled = !editable;
    el.style.background = editable ? '#fff' : '#fafafa';
    return el;
  }
  function createTextarea(value = '', editable = false){
    const ta = document.createElement('textarea');
    ta.value = value ?? '';
    ta.readOnly = !editable; ta.disabled = !editable;
    ta.style.background = editable ? '#fff' : '#f9fafb';
    ta.rows = 3;
    return ta;
  }
  function mk(labelText, node){
    const lbl = document.createElement('label'); lbl.textContent = labelText;
    return [lbl, node];
  }

  function createRadioGroup(name, currentValue, options, editable){
    const wrap = document.createElement('div');
    wrap.className = 'radio-group';
    options.forEach(({value, label})=>{
      const id = `fld_${name}_${value}`;
      const inp = document.createElement('input');
      inp.type = 'radio';
      inp.name = name;
      inp.id = id;
      inp.value = value;
      inp.checked = String(currentValue ?? '').toLowerCase() === String(value).toLowerCase();
      inp.disabled = !editable;

      const lab = document.createElement('label');
      lab.setAttribute('for', id);
      lab.textContent = label;

      wrap.appendChild(inp); wrap.appendChild(lab);
    });
    return wrap;
  }

  function attachDatalist(inputEl, column){
    const listId = `dl_${column}`;
    let dl = document.getElementById(listId);
    if (!dl){ dl = document.createElement('datalist'); dl.id = listId; document.body.appendChild(dl); }
    inputEl.setAttribute('list', listId);

    async function loadOnce(){
      if (dl.dataset.loaded === '1') return;
      try{
        const res = await fetch(API_DISTINCT + encodeURIComponent(column) + '&limit=20');
        const j = await res.json();
        if (!res.ok || j.error) return;
        dl.innerHTML = '';
        (j.values||[]).forEach(v=>{
          const opt = document.createElement('option'); opt.value = v; dl.appendChild(opt);
        });
        dl.dataset.loaded = '1';
      }catch{}
    }
    inputEl.addEventListener('focus', loadOnce, {once:true});
  }

  function parseIndex(name, base){
    const m = new RegExp('^' + base + '(\\d+)$','i').exec(name);
    return m ? parseInt(m[1], 10) : null;
  }
  function collectFamilyIndices(cols, base){
    const set = new Set();
    (cols||[]).forEach(c=>{ const n = parseIndex(c, base); if (n) set.add(n); });
    return Array.from(set).sort((a,b)=>a-b);
  }
  // show only instances with main value present
  function firstNonEmptyIndices(row, family, indices){
    const get = {
      itext:   n => row['itext'+n],
      icombo:  n => row['icombo'+n],
      icheck:  n => row['icheck'+n],
      itxtDate:n => row['itxtDate'+n],
      ibtnAdd: n => row['ibtnAdd'+n],
    }[family];
    return (indices||[]).filter(n => !reqIsEmptyVal(get(n)));
  }
  // n = (max itext index) + 1   (per your rule)
  function nextItextIndex(cols){
    let maxI = 0;
    (cols||[]).forEach(c => { const n = parseIndex(c,'itext'); if (n && n > maxI) maxI = n; });
    return maxI + 1;
  }

  /* grid truncation */
  const WORD_LIMIT = 5;
  function firstNWords(input, n=WORD_LIMIT){
    const s = String(input ?? '').replace(/\s+/g,' ').trim();
    if (!s) return '';
    const words = s.split(/\s+/);
    const short = words.slice(0, n).join(' ');
    return words.length > n ? short + ' …' : short;
  }

  /* ===================== Data grid ===================== */
  async function fetchPage(){
    status.textContent = 'جارِ التحميل…';
    const params = new URLSearchParams();
    params.set('skip', String(skip));
    params.set('take', String(take));
    if (qEl.value.trim()) params.set('q', qEl.value.trim());

    const res = await fetch(API_LIST + '?' + params.toString(), { headers: {'Accept':'application/json'} });
    if (!res.ok){ status.textContent = 'خطأ في التحميل'; return; }
    const data = await res.json();

    columns   = data.columns || [];
    total     = data.total || 0;
    rowsCache = data.rows   || [];

    const displayColumns = columns.filter(c => !HIDE_COLS.has(c));
    renderHeader(displayColumns);
    renderRows(displayColumns, rowsCache);
    renderPager();
    status.textContent = `إجمالي السجلات: ${total.toLocaleString('ar-EG')}`;
  }

  function renderHeader(cols){
    thead.innerHTML = '';
    const tr = document.createElement('tr');
    cols.forEach(cn => {
      const th = document.createElement('th');
      th.textContent = cn;
      tr.appendChild(th);
    });
    thead.appendChild(tr);
  }

  function renderRows(cols, rows){
    tbody.innerHTML = '';
    rows.forEach(row => {
      const tr = document.createElement('tr');
      tr.style.cursor = 'pointer';
      tr.addEventListener('click', () => openDrawer(row, columns, 'view'));

      cols.forEach(cn => {
        const td = document.createElement('td');
        let raw = row[cn]; if (raw == null) raw = '';
        const cell = document.createElement('div');
        cell.className = 'table-cell';
        const display = firstNWords(raw, WORD_LIMIT);
        cell.textContent = display;
        cell.title = String(raw);
        cell.dataset.full  = String(raw);
        cell.dataset.trunc = display;
        cell.addEventListener('dblclick', ()=>{
          const showingFull = cell.textContent === cell.dataset.full;
          cell.textContent = showingFull ? cell.dataset.trunc : cell.dataset.full;
        });
        td.appendChild(cell); tr.appendChild(td);
      });

      tbody.appendChild(tr);
    });
  }

  function renderPager(){
    const page  = Math.floor(skip / take) + 1;
    const pages = Math.max(1, Math.ceil(total / take));
    pageInfo.textContent = `صفحة ${page} من ${pages}`;
    prev.disabled = (skip <= 0);
    next.disabled = (skip + take >= total);
  }

  /* ===================== Drawer ===================== */

  function setMode(mode){
    currentMode = mode; // 'view' | 'edit' | 'create'
    const editable = (mode !== 'view');

    drawerBody.querySelectorAll('input,textarea,select').forEach(el => {
      if (el.name === 'ID' || el.name === 'ReqID'){
        el.readOnly = true; el.disabled = true; el.style.background = '#f3f4f6';
      } else {
        if (el.type === 'radio' || el.tagName === 'SELECT'){
          el.disabled = !editable;
        } else {
          el.readOnly = !editable; el.disabled = !editable;
        }
        el.style.background = editable ? '#fff' : '#fafafa';
      }
    });

    btnEdit.hidden   = (mode !== 'view');
    btnDelete.hidden = (mode === 'create');
    btnSave.hidden   = !editable;
    btnCancel.hidden = !editable;
  }

  function openDrawer(row, cols, mode='view'){
    currentRow = JSON.parse(JSON.stringify(row || {}));
    drawerBody.dataset.modelId = row?.ID ? String(row.ID) : ''; // stash ID
    const idText = (row?.ID != null) ? `#${row.ID}` : '';
    drawerTitle.textContent = (mode === 'create') ? 'نموذج جديد' : `تفاصيل النموذج ${idText}`;

    buildFormFromRow(row || Object.fromEntries(cols.map(c=>[c,''])), cols, (mode!=='view'));
    drawer.classList.add('open'); drawer.removeAttribute('aria-hidden');
    drawerBackdrop.hidden = false;
    setMode(mode);
    drawer.focus();
  }

  function closeDrawer(){
    currentRow = null;
    drawer.classList.remove('open');
    drawer.setAttribute('aria-hidden','true');
    drawerBackdrop.hidden = true;
  }

  /* ===================== Form builder ===================== */

  function buildFormFromRow(row, cols, editable=false){
    drawerBody.innerHTML = '';
    drawerBody.dataset.modelId = row?.ID ? String(row.ID) : '';

    // ===== Fixed MAIN section (Arabic labels) =====
    const main = document.createElement('div');
    main.className = 'form-grid';

    // keep refs to hide/show rows
    const nodeRefs = {};
    function addRow(labelText, fieldEl, fieldName){
      const lbl = document.createElement('label'); lbl.textContent = labelText;
      main.appendChild(lbl); main.appendChild(fieldEl);
      if (fieldName) nodeRefs[fieldName] = {label: lbl, field: fieldEl};
    }
    const v = (k)=> (row?.[k]==null ? '' : String(row[k]));

    // Hidden ID (for saving)
    const hid = document.createElement('input');
    hid.type='hidden'; hid.name='ID'; hid.id='fld_ID'; hid.value = row?.ID ?? '';
    main.appendChild(hid);

    // (1) اسم النموذج → ColName
    { const inp = createInput(v('ColName'), editable);
      inp.id='fld_ColName'; inp.name='ColName';
      addRow('اسم النموذج', inp, 'ColName'); }

    // (2) المجموعة الرئيسية → maingroups (select)
    let applyRightsVisibility = null;
    { const sel = document.createElement('select');
      sel.id='fld_maingroups'; sel.name='maingroups';
      sel.className = 'pretty-select'; sel.disabled = !editable;
      const cur = v('maingroups');
      if (cur && !MAINGROUP_OPTIONS.includes(cur)){
        const opt = document.createElement('option'); opt.value=cur; opt.textContent=cur; sel.appendChild(opt);
      }
      MAINGROUP_OPTIONS.forEach(txt=>{
        const opt = document.createElement('option'); opt.value=txt; opt.textContent=txt; sel.appendChild(opt);
      });
      if (cur) sel.value = cur;
      addRow('المجموعة الرئيسية', sel, 'maingroups');

      // toggle قائمة_الحقوق visibility
      applyRightsVisibility = ()=>{
        const show = (sel.value === 'توكيل');
        const n = nodeRefs['قائمة_الحقوق'];
        if (n){
          n.label.style.display = show?'':'none';
          n.field.style.display = show?'':'none';
          if (!show) n.field.dataset.hidden = '1'; else delete n.field.dataset.hidden;
        }
      };
      sel.addEventListener('change', applyRightsVisibility);
    }

    // (3) اسم العمود (القائمة) → altColName + history
    { const inp = createInput(v('altColName'), editable);
      inp.id='fld_altColName'; inp.name='altColName';
      attachDatalist(inp, 'altColName');
      addRow('القائمة', inp, 'altColName'); }

    // (4) اسم العمود الفرعي → altSubColName + history
    { const inp = createInput(v('altSubColName'), editable);
      inp.id='fld_altSubColName'; inp.name='altSubColName';
      attachDatalist(inp, 'altSubColName');
      addRow('القائمة الفرعية', inp, 'altSubColName'); }

    // (5) العنوان الافتراضي → titleDefault
    { const inp = createInput(v('titleDefault'), editable);
      inp.id='fld_titleDefault'; inp.name='titleDefault';
      addRow('عنوان المكاتبة', inp, 'titleDefault'); }

    // (6) وصف النموذج → TextModel (textarea)
    { const ta = createTextarea(v('TextModel'), editable);
      ta.id='fld_TextModel'; ta.name='TextModel'; ta.rows=3;
      addRow('صياغة النموذج', ta, 'TextModel'); }

    // (7) الأهلية → الأهلية (textarea)
    { const ta = createTextarea(v('الأهلية'), editable);
      ta.id='fld_الأهلية'; ta.name='الأهلية'; ta.rows=3;
      addRow('أهلية مقدم الطلب', ta, 'الأهلية'); }

    // (8) قائمة الحقوق → قائمة_الحقوق (textarea) — hidden unless maingroups === "توكيل"
    { const ta = createTextarea(v('قائمة_الحقوق'), editable);
      ta.id='fld_قائمة_الحقوق'; ta.name='قائمة_الحقوق'; ta.rows=3;
      addRow('قائمة الحقوق الممنوحة لإكمال الطلب', ta, 'قائمة_الحقوق'); }

    // (9) اللغة → Lang (radio ar/en)
    { // accept 'ar'|'en' or Arabic words in db, normalize to ar|en
      const curRaw = v('Lang');
      const norm = (curRaw === 'العربية') ? 'العربية' : (curRaw === 'الإنجليزية' ? 'الإنجليزية' : (curRaw || 'ar'));
      const g = createRadioGroup('Lang', norm, [
        {value:'العربية', label:'العربية'},
        {value:'الإنجليزية', label:'الإنجليزية'}
      ], editable);
      addRow('اللغة', g, 'Lang'); }

    // (10) الحالة → is_active (radio 1/0)
    { const cur = toBool(v('is_active')) ? '1' : '0';
      const g = createRadioGroup('is_active', cur, [
        {value:'1', label:'نشط'},
        {value:'0', label:'غير نشط'}
      ], editable);
      addRow('الحالة', g, 'is_active'); }

    // (11) ReqID (hidden read-only, we still stash it)
    { const hidReq = document.createElement('input');
      hidReq.type='hidden'; hidReq.name='ReqID'; hidReq.id='fld_ReqID';
      hidReq.value = v('ReqID'); main.appendChild(hidReq); }

    // (12) UpdatedAt/By (ignored in save; not shown)
    // —

    drawerBody.appendChild(main);

    // Apply initial visibility for قائمة_الحقوق
    if (applyRightsVisibility) applyRightsVisibility();

    /* ===== Families: only show instances that have main value; +Add creates columns on server ===== */
    const familyIndices = {
      itext:   collectFamilyIndices(cols, 'itext'),
      icombo:  collectFamilyIndices(cols, 'icombo'),
      icheck:  collectFamilyIndices(cols, 'icheck'),
      itxtDate:collectFamilyIndices(cols, 'itxtDate'),
      ibtnAdd: collectFamilyIndices(cols, 'ibtnAdd'),
    };

    function addSection(title){
      const wrap = document.createElement('div');
      wrap.style.marginTop='12px'; wrap.style.borderTop='1px solid var(--border,#e5e7eb)';
      wrap.style.paddingTop='10px';
      const head = document.createElement('div');
      head.style.display='flex'; head.style.justifyContent='space-between'; head.style.alignItems='center'; head.style.marginBottom='8px';
      const h = document.createElement('strong'); h.textContent = title;
      const actions = document.createElement('div'); actions.style.display='flex'; actions.style.gap='8px';
      head.appendChild(h); head.appendChild(actions);
      const grid = document.createElement('div'); grid.className='form-grid';
      wrap.appendChild(head); wrap.appendChild(grid);
      return {wrap, head, actions, grid};
    }

    function renderItext(){
      const sec = addSection('نصوص إدخال البيانات');
      const all = familyIndices.itext;
      const withData = firstNonEmptyIndices(row, 'itext', all);
      (withData.length ? withData : []).forEach(n=>{
        const vv  = row['itext'+n] ?? '';
        const len = row['itext'+n+'Length'] ?? '';
        const isTA = Number(len) >= 700;
        const f  = isTA ? createTextarea(String(vv), editable) : createInput(String(vv), editable);
        f.name   = 'itext'+n;
        const l  = createInput(String(len), editable); l.name = 'itext'+n+'Length';
        sec.grid.append(...mk('itext'+n, f), ...mk('itext'+n+'Length', l));
      });
      const btn = document.createElement('button'); btn.className='btn'; btn.textContent='➕ إضافة نص';
      btn.addEventListener('click', async ()=>{
        const n = nextItextIndex(cols);
        const r = await fetch(API_ADD_FIELD, { method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({type:'text', n}) });
        const j = await r.json(); if (!r.ok || j.error){ alert('خطأ إضافة حقل: ' + (j.error||r.status)); return; }
        if (typeof fetchPage === 'function') await fetchPage();
        openDrawer(rowsCache.find(rw=>rw.ID===row.ID)||row, columns, 'edit');
      });
      sec.actions.appendChild(btn);
      drawerBody.appendChild(sec.wrap);
    }

    function renderCombo(){
      const sec = addSection('قائمة خيارات متعددة');
      const all = familyIndices.icombo;
      const withData = firstNonEmptyIndices(row, 'icombo', all);
      withData.forEach(n=>{
        const v1 = row['icombo'+n] ?? '';
        const v2 = row['icombo'+n+'Option'] ?? '';
        const v3 = row['icombo'+n+'Length'] ?? '';
        const f1 = createInput(String(v1), editable); f1.name='icombo'+n;
        const f2 = createTextarea(String(v2), editable); f2.name='icombo'+n+'Option';
        const f3 = createInput(String(v3), editable); f3.name='icombo'+n+'Length';
        sec.grid.append(...mk('icombo'+n, f1), ...mk('icombo'+n+'Option', f2), ...mk('icombo'+n+'Length', f3));
      });
      const btn = document.createElement('button'); btn.className='btn'; btn.textContent='➕ إضافة قائمة';
      btn.addEventListener('click', async ()=>{
        const n = nextItextIndex(cols);
        const r = await fetch(API_ADD_FIELD, { method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({type:'select', n}) });
        const j = await r.json(); if (!r.ok || j.error){ alert('خطأ إضافة قائمة: ' + (j.error||r.status)); return; }
        if (typeof fetchPage === 'function') await fetchPage();
        openDrawer(rowsCache.find(rw=>rw.ID===row.ID)||row, columns, 'edit');
      });
      sec.actions.appendChild(btn);
      drawerBody.appendChild(sec.wrap);
    }

    function renderCheck(){
      const sec = addSection('خيارات ثنائية');
      const all = familyIndices.icheck;
      const withData = firstNonEmptyIndices(row, 'icheck', all);
      withData.forEach(n=>{
        const v1 = row['icheck'+n] ?? '';
        const v2 = row['icheck'+n+'Option'] ?? '';
        const f1 = createInput(String(v1), editable); f1.name='icheck'+n;
        const f2 = createInput(String(v2), editable); f2.name='icheck'+n+'Option';
        sec.grid.append(...mk('icheck'+n, f1), ...mk('icheck'+n+'Option', f2));
      });
      const btn = document.createElement('button'); btn.className='btn'; btn.textContent='➕ إضافة خانة اختيار';
      btn.addEventListener('click', async ()=>{
        const n = nextItextIndex(cols);
        const r = await fetch(API_ADD_FIELD, { method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({type:'checkbox', n}) });
        const j = await r.json(); if (!r.ok || j.error){ alert('خطأ إضافة خانة: ' + (j.error||r.status)); return; }
        if (typeof fetchPage === 'function') await fetchPage();
        openDrawer(rowsCache.find(rw=>rw.ID===row.ID)||row, columns, 'edit');
      });
      sec.actions.appendChild(btn);
      drawerBody.appendChild(sec.wrap);
    }

    function renderDate(){
      const sec = addSection('أحداث زمنية');
      const all = familyIndices.itxtDate;
      const withData = firstNonEmptyIndices(row, 'itxtDate', all);
      withData.forEach(n=>{
        const v1 = row['itxtDate'+n] ?? '';
        const f1 = createInput(String(v1), editable); f1.name='itxtDate'+n;
        sec.grid.append(...mk('itxtDate'+n, f1));
      });
      const btn = document.createElement('button'); btn.className='btn'; btn.textContent='➕ إضافة تاريخ';
      btn.addEventListener('click', async ()=>{
        const n = nextItextIndex(cols);
        const r = await fetch(API_ADD_FIELD, { method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({type:'date', n}) });
        const j = await r.json(); if (!r.ok || j.error){ alert('خطأ إضافة تاريخ: ' + (j.error||r.status)); return; }
        if (typeof fetchPage === 'function') await fetchPage();
        openDrawer(rowsCache.find(rw=>rw.ID===row.ID)||row, columns, 'edit');
      });
      sec.actions.appendChild(btn);
      drawerBody.appendChild(sec.wrap);
    }

    function renderButton(){
      const sec = addSection('أزرار الإضافة');
      const all = familyIndices.ibtnAdd;
      const withData = firstNonEmptyIndices(row, 'ibtnAdd', all);
      withData.forEach(n=>{
        const v1 = row['ibtnAdd'+n] ?? '';
        const v2 = row['ibtnAdd'+n+'Length'] ?? '';
        const f1 = createInput(String(v1), editable); f1.name='ibtnAdd'+n;
        const f2 = createInput(String(v2), editable); f2.name='ibtnAdd'+n+'Length';
        sec.grid.append(...mk('ibtnAdd'+n, f1), ...mk('ibtnAdd'+n+'Length', f2));
      });
      const btn = document.createElement('button'); btn.className='btn'; btn.textContent='➕ إضافة زر';
      btn.addEventListener('click', async ()=>{
        const n = nextItextIndex(cols);
        const r = await fetch(API_ADD_FIELD, { method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({type:'button', n}) });
        const j = await r.json(); if (!r.ok || j.error){ alert('خطأ إضافة زر: ' + (j.error||r.status)); return; }
        if (typeof fetchPage === 'function') await fetchPage();
        openDrawer(rowsCache.find(rw=>rw.ID===row.ID)||row, columns, 'edit');
      });
      sec.actions.appendChild(btn);
      drawerBody.appendChild(sec.wrap);
    }

    renderItext(); renderCombo(); renderCheck(); renderDate(); renderButton();

    /* ===== Linked requirements (TableProcReq) ===== */
    const reqBlock = document.createElement('div');
    reqBlock.style.marginTop='12px';
    reqBlock.style.borderTop='1px solid var(--border,#e5e7eb)';
    reqBlock.style.paddingTop='10px';

    const head = document.createElement('div');
    head.style.display='flex'; head.style.justifyContent='space-between'; head.style.alignItems='center';
    const title = document.createElement('strong'); title.textContent = 'المتطلبات المرتبطة';
    const reqStatus = document.createElement('span'); reqStatus.className='muted';
    head.appendChild(title); head.appendChild(reqStatus);

    const reqGrid = document.createElement('div'); reqGrid.className='form-grid';
    reqBlock.appendChild(head); reqBlock.appendChild(reqGrid);
    drawerBody.appendChild(reqBlock);

    async function ensureReqRowAndLink(){
      const r1 = await fetch(API_REQ_ADDROW, {method:'POST'});
      const j1 = await r1.json();
      if (!r1.ok || j1.error) throw new Error(j1.error || r1.status);
      const newId = j1.id;

      const r2 = await fetch(API_LINK_REQ, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ id: row.ID, reqId: newId })
      });
      const j2 = await r2.json();
      if (!r2.ok || j2.error) throw new Error(j2.error || r2.status);

      row.ReqID = newId;
      drawerBody.dataset.reqId = String(newId);
      return newId;
    }

    function renderReqInputs(rec){
      reqGrid.innerHTML = '';

      const filled = REQ_FIELDS_ORDER.filter(c => !reqIsEmptyVal(rec[c]));
      filled.forEach(cn=>{
        const lbl = document.createElement('label'); lbl.textContent = cn;
        const inp = document.createElement('input');
        inp.type='text'; inp.value = String(rec[cn] ?? '');
        inp.readOnly = !editable; inp.disabled = !editable;
        inp.style.background = editable ? '#fff' : '#fafafa';
        inp.name = 'REQ__' + cn;
        reqGrid.appendChild(lbl); reqGrid.appendChild(inp);
      });

      // Add-more button
      const btnRow = document.createElement('div'); btnRow.style.gridColumn = '1 / -1';
      const btnNext = document.createElement('button'); btnNext.className='btn'; btnNext.textContent='➕ إضافة المطلوب التالي';
      btnNext.addEventListener('click', async ()=>{
        try{
          if (!editable){ btnEdit.click(); return; }

          let rid = Number(row?.ReqID || 0);
          if (!rid){
            reqStatus.textContent = 'إنشاء وربط سجل المتطلبات…';
            rid = await ensureReqRowAndLink();
          }
          const rr = await fetch(API_REQ_GET + rid, { headers:{'Accept':'application/json'}, cache:'no-store' });
          const jj = await rr.json(); if (!rr.ok || jj.error) throw new Error(jj.error || rr.status);
          const rec2 = jj.row || jj;

          const next = REQ_FIELDS_ORDER.find(c => reqIsEmptyVal(rec2[c]));
          if (!next){ alert('لا مزيد من الحقول المتاحة (1..9).'); return; }

          const lbl = document.createElement('label'); lbl.textContent = next;
          const inp = document.createElement('input'); inp.type='text'; inp.value='';
          inp.readOnly=false; inp.disabled=false; inp.style.background='#fff';
          inp.name = 'REQ__' + next;
          reqGrid.appendChild(lbl); reqGrid.appendChild(inp);

          const init = JSON.parse(drawerBody.dataset.reqInitial || '{}');
          init[next] = '';
          drawerBody.dataset.reqInitial = JSON.stringify(init);
        }catch(e){ alert('خطأ: ' + e.message); }
      });
      btnRow.appendChild(btnNext); reqGrid.appendChild(btnRow);

      // توضيح_المعاملة (textarea)
      const L = document.createElement('label'); L.textContent = 'توضيح_المعاملة';
      const TA = document.createElement('textarea');
      TA.name='REQ__توضيح_المعاملة';
      TA.value = String(rec['توضيح_المعاملة'] ?? '');
      TA.readOnly = !editable; TA.disabled = !editable;
      TA.style.background = editable ? '#fff' : '#f9fafb';
      TA.rows = 3;
      reqGrid.appendChild(L); reqGrid.appendChild(TA);
    }

    (async ()=>{
      let rid = Number(row?.ReqID || 0);
      if (!rid){
        reqStatus.textContent = 'لا يوجد ReqID مرتبط.';
        if (editable){
          const btnCreate = document.createElement('button'); btnCreate.className='btn';
          btnCreate.textContent='➕ إنشاء متطلبات وربطها';
          btnCreate.addEventListener('click', async ()=>{
            try{
              reqStatus.textContent = 'إنشاء وربط…';
              const newId = await ensureReqRowAndLink();
              const r = await fetch(API_REQ_GET + newId, { headers:{'Accept':'application/json'} });
              const j = await r.json();
              const rec = (r.ok && !j.error) ? (j.row || j) : {};
              renderReqInputs(rec);
              const emptyReq = REQ_FIELDS_ORDER.reduce((o,k)=>(o[k]='',o),{});
              emptyReq['توضيح_المعاملة'] = '';
              drawerBody.dataset.reqInitial = JSON.stringify(emptyReq);
            }catch(e){ reqStatus.textContent='تعذر الإنشاء'; alert('خطأ: ' + e.message); }
          });
          head.appendChild(btnCreate);
        }
        drawerBody.dataset.reqId = '';
        const emptyReq = REQ_FIELDS_ORDER.reduce((o,k)=>(o[k]='',o),{});
        emptyReq['توضيح_المعاملة']='';
        drawerBody.dataset.reqInitial = JSON.stringify(emptyReq);
        renderReqInputs({});
      } else {
        const r = await fetch(API_REQ_GET + rid, { headers:{'Accept':'application/json'}, cache:'no-store' });
        const j = await r.json();
        if (!r.ok || j.error){ reqStatus.textContent='تعذر تحميل المتطلبات'; return; }
        const rec = j.row || j;
        renderReqInputs(rec);
        drawerBody.dataset.reqId = String(rid);
        const initReq = REQ_FIELDS_ORDER.reduce((o,k)=>(o[k]=rec[k]??'',o),{});
        initReq['توضيح_المعاملة'] = rec['توضيح_المعاملة'] ?? '';
        drawerBody.dataset.reqInitial = JSON.stringify(initReq);
      }
    })();

    // ===== Snapshot of model inputs (ignore hidden) =====
    const modelSnapshot = {};
    drawerBody.querySelectorAll('.form-grid input, .form-grid textarea, .form-grid select').forEach(el=>{
      if (el.name?.startsWith('REQ__')) return;
      if (el.dataset.hidden === '1') return;
      const cs = getComputedStyle(el); if (cs.display === 'none') return;

      let v;
      if (el.type === 'checkbox') v = el.checked ? '1' : '0';
      else if (el.type === 'radio'){ if (!el.checked) return; v = el.value; }
      else v = el.value;

      modelSnapshot[el.name] = v;
    });
    drawerBody.dataset.modelInitial = JSON.stringify(modelSnapshot);
  }

  /* ===================== Events ===================== */

  btnSearch.addEventListener('click', ()=>{ skip=0; fetchPage(); });
  qEl.addEventListener('keydown', (e)=>{ if (e.key==='Enter'){ skip=0; fetchPage(); }});
  prev.addEventListener('click', ()=>{ skip=Math.max(0, skip - take); fetchPage(); });
  next.addEventListener('click', ()=>{ skip=skip + take; fetchPage(); });

  drawerClose.addEventListener('click', closeDrawer);
  btnClose2.addEventListener('click', closeDrawer);
  drawerBackdrop.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', (e)=>{ if (e.key==='Escape' && drawer.classList.contains('open')) closeDrawer(); });

  btnCopyJson.addEventListener('click', async ()=>{
    const text = JSON.stringify(currentRow || {}, null, 2);
    try{ await navigator.clipboard.writeText(text); alert('تم نسخ JSON إلى الحافظة'); }
    catch{ alert('نُسخ'); }
  });

  btnEdit.addEventListener('click', ()=>{
    // rebuild in edit mode using current values in the drawer
    const obj = {};
    drawerBody.querySelectorAll('input,textarea,select').forEach(el=>{
      if (el.type === 'radio'){ if (el.checked) obj[el.name] = el.value; }
      else if (el.type === 'checkbox') obj[el.name] = el.checked ? '1' : '0';
      else obj[el.name] = el.value;
    });
    obj.ID = obj.ID || drawerBody.dataset.modelId || currentRow?.ID || '';
    buildFormFromRow(obj, columns, true);
    setMode('edit');
  });

  btnSave.addEventListener('click', async ()=>{
    if (currentMode !== 'edit' && currentMode !== 'create') return;

    const modelId = Number(drawerBody.dataset.modelId || document.getElementById('fld_ID')?.value || 0);
    if (!modelId){ alert('لا يوجد رقم ID'); return; }

    const nowModel = {};
    const nowReq   = {};
    drawerBody.querySelectorAll('.form-grid input, .form-grid textarea, .form-grid select').forEach(el=>{
      // ignore hidden
      if (el.dataset.hidden === '1') return;
      const cs = getComputedStyle(el); if (cs.display === 'none') return;

      if (el.name?.startsWith('REQ__')){
        if (el.type === 'radio' && !el.checked) return;
        nowReq[el.name.replace(/^REQ__/, '')] = (el.type==='checkbox') ? (el.checked?'1':'0') : el.value;
      } else if (el.name){
        if (el.type === 'radio' && !el.checked) return;
        nowModel[el.name] = (el.type==='checkbox') ? (el.checked?'1':'0') : el.value;
      }
    });

    const initModel = JSON.parse(drawerBody.dataset.modelInitial || '{}');
    const modelPatch = {};
    Object.keys(nowModel).forEach(k=>{
      if (k==='ID') return;
      if (String(nowModel[k] ?? '') !== String(initModel[k] ?? '')) modelPatch[k] = nowModel[k];
    });

    const initReq = JSON.parse(drawerBody.dataset.reqInitial || '{}');
    const reqPatch = {};
    Object.keys(nowReq).forEach(k=>{
      if (String(nowReq[k] ?? '') !== String(initReq[k] ?? '')) reqPatch[k] = nowReq[k];
    });

    if (!Object.keys(modelPatch).length && !Object.keys(reqPatch).length){
      alert('لا تغييرات'); setMode('view'); return;
    }

    let reqId = Number(drawerBody.dataset.reqId || 0);
    const payload = {
      modelId,
      modelPatch,
      reqId: reqId || null,
      reqPatch,
      createReqIfMissing: true
    };

    const res = await fetch(API_COMPOUND_SAVE, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (!res.ok || data.error){ alert('خطأ: ' + (data.error || res.status)); return; }

    currentRow = data.model || currentRow;
    if (typeof fetchPage === 'function') await fetchPage();
    openDrawer(currentRow, columns, 'view');
  });

  btnCancel.addEventListener('click', ()=>{
    if (!currentRow) { closeDrawer(); return; }
    openDrawer(currentRow, columns, 'view');
  });

  btnDelete.addEventListener('click', async ()=>{
    const id = Number(drawerBody.dataset.modelId || document.getElementById('fld_ID')?.value || 0);
    if (!id){ alert('لا يوجد رقم ID'); return; }
    if (!confirm('تأكيد حذف السجل #' + id + ' ؟')) return;

    const res = await fetch(API_DELETE, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id}) });
    const data = await res.json();
    if (!res.ok || data.error){ alert('خطأ: ' + (data.error || res.status)); return; }

    closeDrawer(); fetchPage();
  });

  btnNew.addEventListener('click', async ()=>{
    // NOTE: Save for new record isn’t wired to create via compound_save here.
    // If you need full create, call API_ADD then reopen with the new ID.
    if (!columns.length) await fetchPage();
    const empty = Object.fromEntries(columns.map(c=>[c,'']));
    empty.ID = '';
    openDrawer(empty, columns, 'create');
  });

  /* ===================== Schema modal (columns) ===================== */
  (function schemaTools(){
    const btnSchema      = document.getElementById('btnSchema');
    const schemaModal    = document.getElementById('schemaModal');
    const schemaBackdrop = document.getElementById('schemaBackdrop');
    const schemaClose    = document.getElementById('schemaClose');
    const schemaClose2   = document.getElementById('schemaClose2');

    const ddlAddName     = document.getElementById('ddlAddName');
    const inLength       = document.getElementById('inLength');
    const inTypeSpec     = document.getElementById('inTypeSpec');
    const btnDoAdd       = document.getElementById('btnDoAdd');
    const dropList       = document.getElementById('dropList');

    function buildWhitelist(){
      const names = [];
      for (let i=1;i<=10;i++){ names.push(`itext${i}`, `itext${i}Length`); }
      for (let i=1;i<=5;i++){
        names.push(`icombo${i}`, `icombo${i}Option`, `icombo${i}Length`);
        names.push(`icheck${i}`, `icheck${i}Option`);
        names.push(`itxtDate${i}`);
      }
      names.push('ibtnAdd1','ibtnAdd1Length','Lang','altColName','altSubColName','ReqID','is_active');
      return names;
    }

    async function fetchColStats(name){
      const r = await fetch(`/ahwal-admin/api/models_colstats.php?name=`+encodeURIComponent(name));
      return await r.json(); // {hasData:boolean, top:[{value,count}]}
    }

    function openSchema(){ schemaModal.classList.add('open'); schemaBackdrop.hidden=false; refreshSchema(); }
    function closeSchema(){ schemaModal.classList.remove('open'); schemaBackdrop.hidden=true; }

    async function refreshSchema(){
      const res = await fetch(API_COLS, { headers:{'Accept':'application/json'} });
      const data = await res.json();
      const existing = (data.columns||[]).map(c=>c.column_name);

      // fill dropdown with allowed-but-missing
      const allow = buildWhitelist().filter(n => !existing.includes(n));
      ddlAddName.innerHTML = '';
      allow.forEach(n=>{
        const opt = document.createElement('option'); opt.value=n; opt.textContent=n; ddlAddName.appendChild(opt);
      });

      // render all existing (server order)
      dropList.innerHTML = '';
      const list = existing.slice();
      let dragEl = null;

      for (const n of list){
        const row = document.createElement('div');
        row.className = 'col-chip'; row.setAttribute('draggable','true'); row.dataset.col = n;
        if (!buildWhitelist().includes(n)) row.classList.add('unlisted');

        const left = document.createElement('div'); left.style.display='flex'; left.style.alignItems='center'; left.style.gap='8px';
        const handle = document.createElement('span'); handle.className='handle'; handle.innerHTML='⠿';
        const nameSpan = document.createElement('span'); nameSpan.textContent=n;
        left.appendChild(handle); left.appendChild(nameSpan);

        const actions = document.createElement('div');
        const btn = document.createElement('button'); btn.className='btn'; btn.textContent='🗑️ حذف';
        btn.addEventListener('click', async ()=>{
          if (!confirm(`حذف العمود "${n}" نهائيًا؟`)) return;
          const r = await fetch(API_DROP_COL, { method:'POST', headers:{'Content-Type':'application/json'},
            cache:'no-store', body: JSON.stringify({ name:n }) });
          const j = await r.json();
          if (!r.ok || j.error){ alert('خطأ: ' + (j.error || r.status)); return; }
          await refreshSchema(); if (typeof fetchPage === 'function') fetchPage();
        });
        actions.appendChild(btn);

        row.appendChild(left); row.appendChild(actions); dropList.appendChild(row);

        fetchColStats(n).then(s=>{
          if (s && s.hasData === false) row.classList.add('empty');
          const tip = (s?.top || []).map(x => `${x.value ?? '(NULL)'} × ${x.count}`).join('\n') || 'لا توجد قيم';
          row.title = tip;
        }).catch(()=>{});
      }

      // drag & drop persist
      dropList.querySelectorAll('.col-chip').forEach(el=>{
        el.addEventListener('dragstart', e=>{ dragEl=el; e.dataTransfer.effectAllowed='move'; });
        el.addEventListener('dragover',  e=>{ e.preventDefault(); e.dataTransfer.dropEffect='move'; });
        el.addEventListener('drop', async e=>{
          e.preventDefault(); if (!dragEl || dragEl===el) return;
          const rect = el.getBoundingClientRect();
          const before = (e.clientY - rect.top) < rect.height/2;
          dropList.insertBefore(dragEl, before ? el : el.nextSibling);
          const newOrder = Array.from(dropList.querySelectorAll('.col-chip')).map(x=>x.dataset.col);
          const r = await fetch(API_SETORDER, { method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ order:newOrder }) });
          const j = await r.json(); if (!r.ok || j.error){ alert('خطأ في حفظ الترتيب: ' + (j.error||r.status)); return; }
          if (typeof fetchPage === 'function') fetchPage();
        });
      });
    }

    btnSchema?.addEventListener('click', openSchema);
    schemaClose?.addEventListener('click', closeSchema);
    schemaClose2?.addEventListener('click', closeSchema);
    schemaBackdrop?.addEventListener('click', closeSchema);

    btnDoAdd?.addEventListener('click', async ()=>{
      const name    = ddlAddName.value;
      const length  = parseInt((inLength.value||'0'),10) || undefined;
      const typeSpec= inTypeSpec.value.trim() || undefined;

      const payload = { name };
      if (length)  payload.length  = length;
      if (typeSpec)payload.typeSpec= typeSpec;

      const r = await fetch(API_ADD_FIELD.replace('models_addfield','models_addcol'), {
        method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)
      });
      const j = await r.json();
      if (!r.ok || j.error){ alert('خطأ: ' + (j.error||r.status)); return; }

      inLength.value=''; inTypeSpec.value='';
      await refreshSchema(); if (typeof fetchPage==='function') fetchPage();
    });
  })();

  /* ===================== Init ===================== */
  fetchPage();
})();
