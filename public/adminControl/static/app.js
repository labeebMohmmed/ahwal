const state = {
  tab: 'models',
  columns: [],
  rows: [],
  skip: 0,
  take: 20,
  total: 0,
  q: ''
};

const el = sel => document.querySelector(sel);
const gridHead = el('#grid-head');
const gridBody = el('#grid-body');
const pager = el('#pager');
const drawer = el('#drawer');
const drawerBg = el('#drawerBg');
const editForm = el('#editForm');
const reqLinkBar = el('#modelLinkBar');
const reqLinkedModels = el('#reqLinkedModels');

function api(path, opts={}) { return fetch(path, Object.assign({headers:{'Content-Type':'application/json'}}, opts)).then(r=>r.json()); }
function endpoint(path){ return `/admin${path}`; }

async function loadGrid() {
  const base = state.tab === 'models' ? '/models/list' : '/reqs/list';
  const url = endpoint(`${base}?skip=${state.skip}&take=${state.take}&q=${encodeURIComponent(state.q)}`);
  const res = await api(url);
  if (!res.ok) { alert('خطأ في تحميل البيانات'); return; }
  state.columns = res.data.columns || [];
  state.rows = res.data.rows || [];
  state.total = res.data.total || 0;
  renderGrid();
}

function renderGrid(){
  gridHead.innerHTML = '';
  const trh = document.createElement('tr');
  state.columns.forEach(c=>{ const th = document.createElement('th'); th.textContent = c; trh.appendChild(th); });
  gridHead.appendChild(trh);

  gridBody.innerHTML = '';
  state.rows.forEach(row=>{
    const tr = document.createElement('tr');
    tr.style.cursor='pointer';
    tr.addEventListener('click', ()=>openEditor(row));
    state.columns.forEach(c=>{
      const td = document.createElement('td');
      const v = row[c];
      td.textContent = v == null ? '' : String(v);
      tr.appendChild(td);
    });
    gridBody.appendChild(tr);
  });

  // Pager
  pager.innerHTML = '';
  const pages = Math.ceil(state.total / state.take);
  const curr = Math.floor(state.skip / state.take) + 1;
  const mk = (label, cb, disabled=false)=>{ const b=document.createElement('button'); b.className='btn'; b.textContent=label; b.disabled=disabled; b.onclick=cb; return b; };
  pager.appendChild(mk('⟪', ()=>{ state.skip=0; loadGrid(); }, curr<=1));
  pager.appendChild(mk('‹', ()=>{ state.skip=Math.max(0, state.skip-state.take); loadGrid(); }, curr<=1));
  pager.appendChild(document.createTextNode(` صفحة ${curr} من ${Math.max(1,pages)} `));
  pager.appendChild(mk('›', ()=>{ if (curr<pages){ state.skip += state.take; loadGrid(); } }, curr>=pages));
  pager.appendChild(mk('⟫', ()=>{ if (curr<pages){ state.skip = (pages-1)*state.take; loadGrid(); } }, curr>=pages));
}

function openEditor(row){
  editForm.innerHTML = '';
  reqLinkBar.style.display = state.tab === 'models' ? 'block' : 'none';
  reqLinkedModels.style.display = state.tab === 'reqs' ? 'block' : 'none';

  // Build inputs dynamically
  for(const col of state.columns){
    const val = row[col];
    const wrap = document.createElement('div');
    wrap.className = 'col';
    const label = document.createElement('label');
    label.textContent = col;
    const input = buildInput(col, val);
    if (['ID'].includes(col)) input.disabled = true;
    if (/^.+$/i.test(col)) { /* keep all */ }
    wrap.appendChild(label);
    wrap.appendChild(input);
    if (/^.+$/.test(col) && /توضيح|Details|Description/i.test(col)) wrap.classList.add('col-2');
    editForm.appendChild(wrap);
  }

  // Prefill ReqID bar
  const reqIdVal = row['ReqID'] ?? '';
  el('#inpReqID').value = reqIdVal || '';
  el('#btnLinkReq').onclick = async ()=>{
    const reqId = Number(el('#inpReqID').value||0);
    const res = await api(endpoint('/models/link-req'), {method:'POST', body: JSON.stringify({id: row['ID'], reqId})});
    if (res.ok) { alert('تم تحديث الربط'); await loadGrid(); }
    else alert('فشل الربط');
  };

  // Save/Delete
  el('#btnSave').onclick = async ()=>{ await saveRow(row['ID']); };
  el('#btnDelete').onclick = async ()=>{
    if (!confirm('تأكيد حذف السجل؟')) return;
    const base = state.tab==='models'?'/models/delete':'/reqs/delete';
    const res = await api(endpoint(base), {method:'POST', body: JSON.stringify({id: row['ID']})});
    if (res.ok) { closeDrawer(); await loadGrid(); }
    else alert('تعذر الحذف');
  };

  // If ProcReq: show linked models
  if (state.tab === 'reqs') loadLinkedModels(row['ID']);

  openDrawer();
}

function buildInput(col, val){
  let input;
  if (/^itxtDate\d+$/.test(col)) {
    input = document.createElement('input'); input.type='date';
    if (val) input.value = String(val).substring(0,10);
  } else if (/^icheck\d+$/.test(col)) {
    input = document.createElement('select');
    input.innerHTML = '<option value="">غير محدد</option><option value="1">نعم</option><option value="0">لا</option>';
    if (val === true || val === 1 || val === '1') input.value='1';
    else if (val === false || val === 0 || val === '0') input.value='0';
    else input.value='';
  } else if (/^Lang$/.test(col)) {
    input = document.createElement('select');
    input.innerHTML = '<option value="">—</option><option value="AR">AR</option><option value="EN">EN</option>';
    input.value = val ?? '';
  } else if (/ReqID/i.test(col)) {
    input = document.createElement('input'); input.type='number'; input.value = val ?? '';
  } else if (/^(itext\d+|icombo\d+|icombo\d+Option|altColName|altSubColName|ibtnAdd1)$/i.test(col)) {
    input = document.createElement(/Option|Name|ibtnAdd1|icombo\d+/.test(col)?'textarea':'input');
    if (input.tagName==='INPUT') input.type='text';
    input.value = val ?? '';
  } else if (/Length$/i.test(col)) {
    input = document.createElement('input'); input.type='number'; input.value = val ?? '';
  } else {
    input = document.createElement('textarea'); input.value = val ?? '';
  }
  input.name = col;
  return input;
}

async function saveRow(id){
  const patch = {};
  for (const el of editForm.querySelectorAll('[name]')) {
    if (el.disabled) continue;
    let v = el.value;
    if (/^icheck\d+$/.test(el.name)) {
      v = (v === '' ? null : Number(v));
    } else if (/Length$|^ReqID$/.test(el.name)) {
      v = (v === '' ? null : Number(v));
    }
    patch[el.name] = v;
  }
  const base = state.tab==='models'?'/models/update':'/reqs/update';
  const res = await api(endpoint(base), {method:'POST', body: JSON.stringify({id, patch})});
  if (res.ok) { alert('تم الحفظ'); await loadGrid(); }
  else alert('تعذر الحفظ');
}

function openDrawer(){ drawer.classList.add('open'); drawerBg.classList.add('show'); }
function closeDrawer(){ drawer.classList.remove('open'); drawerBg.classList.remove('show'); }

el('#btnClose').onclick = closeDrawer; drawerBg.onclick = closeDrawer;

// Tabs
for (const t of document.querySelectorAll('.tab')) {
  t.onclick = ()=>{
    document.querySelector('.tab.active')?.classList.remove('active');
    t.classList.add('active');
    state.tab = t.dataset.tab;
    state.skip = 0; state.q = '';
    el('#search').value = '';
    loadGrid();
  };
}

// Search debounce
let tSearch;
el('#search').addEventListener('input', e=>{
  clearTimeout(tSearch);
  tSearch = setTimeout(()=>{ state.q = e.target.value.trim(); state.skip = 0; loadGrid(); }, 300);
});

// Add row
el('#btnAddRow').onclick = async ()=>{
  const base = state.tab==='models'?'/models/addrow':'/reqs/addrow';
  const res = await api(endpoint(base), {method:'POST', body: JSON.stringify({values:{}})});
  if (res.ok) { await loadGrid(); alert('تمت إضافة صف جديد (ID='+res.data.id+')'); }
  else alert('تعذر إضافة صف');
};

// Schema panel toggle (admin only — guard on server too)
const schemaPanel = el('#schemaPanel');
let schemaOpen = false;

el('#btnSchema').onclick = ()=>{ schemaOpen = !schemaOpen; schemaPanel.classList.toggle('show', schemaOpen); };

el('#btnAddCol').onclick = async ()=>{
  const name = el('#ddlColName').value; if (!name) return alert('اختر اسم الحقل');
  const len = Number(el('#colLen').value||200);
  const base = state.tab==='models'?'/models/addcol':'/reqs/addcol';
  if (!confirm('تأكيد إضافة الحقل: '+name+' ؟')) return;
  const res = await api(endpoint(base), {method:'POST', body: JSON.stringify({name, typeSpec:{len}})});
  if (res.ok) { alert('تمت إضافة الحقل'); await loadGrid(); }
  else alert('تعذر إضافة الحقل: '+(res.data||''));
};

el('#btnDropCol').onclick = async ()=>{
  const name = el('#ddlColName').value; if (!name) return alert('اختر اسم الحقل');
  const base = state.tab==='models'?'/models/dropcol':'/reqs/dropcol';
  if (!confirm('سيتم حذف الحقل نهائياً: '+name+' — هل أنت متأكد؟')) return;
  const res = await api(endpoint(base), {method:'POST', body: JSON.stringify({name})});
  if (res.ok) { alert('تم حذف الحقل'); await loadGrid(); }
  else alert('تعذر حذف الحقل: '+(res.data||''));
};

// Req picker
const picker = el('#picker'); const pickerBg = el('#pickerBg');
el('#btnPickReq').onclick = ()=>{ openPicker(); };
el('#btnClosePicker').onclick = closePicker; pickerBg.onclick = closePicker;
el('#pickerSearch').addEventListener('input', debounce(async (e)=>{
  await renderPicker(e.target.value.trim());
}, 250));

function openPicker(){ picker.classList.add('open'); pickerBg.classList.add('show'); renderPicker(''); }
function closePicker(){ picker.classList.remove('open'); pickerBg.classList.remove('show'); }

async function renderPicker(q){
  const res = await api(endpoint(`/reqs/list?skip=0&take=50&q=${encodeURIComponent(q||'')}`));
  const box = el('#pickerList'); box.innerHTML = '';
  if (!res.ok) return;
  const rows = res.data.rows;
  rows.forEach(r=>{
    const item = document.createElement('div');
    item.style.padding = '10px'; item.style.borderBottom='1px solid var(--border)'; item.style.cursor='pointer';
    const head = document.createElement('div'); head.style.display='flex'; head.style.justifyContent='space-between';
    head.innerHTML = `<strong>ID ${r.ID ?? ''}</strong>`;
    const sub = document.createElement('div'); sub.style.color='var(--muted)';
    const desc = r['توضيح_المعاملة'] || r['Description'] || r['details'] || '';
    sub.textContent = String(desc).slice(0,160);
    item.appendChild(head); item.appendChild(sub);
    item.onclick = ()=>{ el('#inpReqID').value = r.ID; closePicker(); };
    box.appendChild(item);
  });
}

async function loadLinkedModels(reqId){
  const res = await api(endpoint(`/reqs/linked-models?id=${reqId}`));
  if (!res.ok) { reqLinkedModels.style.display='none'; return; }
  reqLinkedModels.style.display='block';
  const list = res.data;
  reqLinkedModels.innerHTML = `<h3>النماذج المرتبطة بهذا المتطلب (${list.length})</h3>` +
    list.map(r=>`<div style="padding:8px;border-bottom:1px solid var(--border)">ID ${r.ID} — ${r.altColName ?? ''} / ${r.altSubColName ?? ''}</div>`).join('');
}

function debounce(fn, ms){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }

// Boot
loadGrid();
