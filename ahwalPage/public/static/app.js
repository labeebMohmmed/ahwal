/**************************************************
 * Ahwal e-Forms Wizard — matches your current index.html
 * - Panels: #step1, #step2, #step3 (and future #step4..#step7)
 * - Stepper items: .stepper-item[data-step="1..7"]
 * - Requirements list: #reqList  (not "required-docs-list")
 * - No "nextFromStep2" button in HTML → we enable stepper Step 3 and/or auto-advance
 **************************************************/

// ---------- Config ----------
const OFFICE_ID = '080'; // TODO: set from server/user profile if dynamic
let appState = [];
let new_case = false;
let step5Fields = 0;
/* ========= LocalStorage keys ========= */
const LS = {
    saved: 'ahwal.currentStep',   // last viewed step (we won't exceed truth)
    lang: 'docLang',
    main: 'selectedMainGroup',
    altCol: 'selectedAltColName',
    altSub: 'selectedAltSubColName',
    tpl: 'selectedTemplateId',
    caseId: 'caseId',
    extRef: 'externalRef',
    userId: 'userId',
    uploadsDone: 'uploadsDone',
    ar_diplomat: 'ar_diplomat',
    en_diplomat: 'en_diplomat',
    ar_diplomat_job: 'ar_diplomat_job',
    en_diplomat_job: 'en_diplomat_job',
};

// Key map (only define once)
window.LSK ??= {
    main: 'LS.main',
    altCol: 'LS.altCol',
    altSub: 'LS.altSub',
    tpl: 'LS.tpl',
    lang: 'LS.lang',
    caseId: 'caseId',          // you already use this plain key
    uploadsDone: 'LS.uploadsDone'
};

let currentGalleryCaseId = null;
function clearUploadsUI() {
    const host = document.getElementById('uploadsGallery');
    if (host) host.innerHTML = '';
}


// One-time migration from legacy keys -> unified keys
// (function migrateLSKeys() {
//     const get = (k) => (LS?.get ? LS.get(k) : localStorage.getItem(k) || '');
//     const set = (k, v) => (LS?.set ? LS.set(k, v) : localStorage.setItem(k, v ?? ''));

//     const pairs = [
//         ['selectedMainGroup', LSK.main],
//         ['selectedAltColName', LSK.altCol],
//         ['selectedAltSubColName', LSK.altSub],
//         ['selectedTemplateId', LSK.tpl],
//         ['docLang', LSK.lang],
//     ];
//     console.log('mograte');
//     for (const [oldK, newK] of pairs) {
//         const v = localStorage.getItem(oldK);
//         if (v != null && !get(newK)) set(newK, v);
//     }
// })();


// at top-level near other globals
let requiredStatus = {};
let extraCount = 0;

const API_BASE = ''; // set to '' if your server docroot is /public; otherwise 'public/'
const apiUrl = (name) => `${API_BASE}${name}`;

const MAX_FILE_SIZE = 3 * 1024 * 1024; // 3MB
const OK_TYPES = new Set([
    'application/pdf',
    'image/jpeg', 'image/png',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
]);

/* نصوص الشرح لكل مجموعة (يمكن تعديلها لاحقاً) */
const GROUP_HELP_TEXT = {
    'إفادة لمن يهمه الأمر': 'تضم هذه المجموعة عدد من المعاملات التي تصدر عن البعثة لتقديمها إلى من يهمه الأمر لتوضيح أو اثبات  امر بعينه يخص مقدم الطلب',
    'إقرار': 'تضم المجموعة عدد من نماذج المعاملات التي يقر فيها مقدم الطلب بحقائق في امور محددة بغرض اثبات او نفي واقعة بعينها',
    'إقرار مشفوع باليمين': 'تضم المجموعة عدد من نماذج المعاملات التي يقر فيها مقدم الطلب بحقائق في امور محددة بغرض اثبات او نفي واقعة بعينها مشفوعة باليمين',
    'توكيل': 'تضم المعاملات التي تفوض شخص او عدة اشخاص للقيام باجراء معين نيابة عن مقدم الطلب',
    'مخاطبة لتاشيرة دخول': 'تضم مخاطبات للبعثات الدبلوماسية التي تطلب معلومات رسمية من البعثات السودانية بخصوص منح تاشيرة دخول للمواطنيين السودانيين'
};

// ---- Cases list (basic paging/search) ----
const CasesUI = {
    page: 1,
    pageSize: 20,
    total: 0,
    lastQuery: {}
};

// ---- Role toggle + list switching ----
(function initRoleSwitcher() {
    const toggle = document.getElementById('roleToggle');
    const badge = document.getElementById('roleBadge');
    const officeSec = document.getElementById('step10');   // section showing TableAuth+Collection
    const onlineSec = document.getElementById('onlineList');   // section showing online.Cases (customer view)
    const importBox = document.getElementById('importOnlineBox'); // optional employee import UI

    function setRoleUI(role, { persist = true } = {}) {
        localStorage.setItem('role', role);
        if (toggle) toggle.checked = (role === 'employee');

        if (badge) {
            badge.textContent = (role === 'employee') ? 'الدور: موظف' : 'الدور: مراجع';
            // subtle color cue
            badge.style.background = (role === 'employee') ? '#DBEAFE' : '#E5E7EB';
            badge.style.color = (role === 'employee') ? '#1E3A8A' : '#111827';
        }

        // which list to show
        if (officeSec) officeSec.hidden = (role !== 'employee');
        if (onlineSec) onlineSec.hidden = (role === 'employee');
        if (importBox) importBox.hidden = (role !== 'employee');

        // while browsing lists, block auto-routing to hidden step 5
        localStorage.setItem('empAutoRoute', '0');

        // (re)load the proper list
        // if (role === 'employee') {
        //     if (typeof loadOfficeList === 'function') loadOfficeList({});
        // } else {
        //     if (typeof loadCases === 'function') loadCases({});
        // }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const saved = (localStorage.getItem('role') || 'customer').toLowerCase();
        setRoleUI(saved, { persist: false });
        if (toggle) {
            toggle.addEventListener('change', () => {
                const role = toggle.checked ? 'employee' : 'customer';
                setRoleUI(role);
            });
        }

    });
})();


async function loadOfficeList(params = {}) {
    const qs = new URLSearchParams(params).toString();
    // console.log(qs);
    const res = await fetch('api_office_cases_list.php?' + qs);
    if (!res.ok) { alert('فشل تحميل القائمة'); return; }
    const data = await res.json();
    const body = document.getElementById('officeBody');
    const note = document.getElementById('autoTodayNote');
    body.innerHTML = '';
    note.hidden = !data.autoTodayApplied;

    const mobile = document.getElementById('casesOfficeTableMobile');
    const esc = s => String(s ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    if (mobile) mobile.innerHTML = '';
    (data.rows || []).forEach(r => {

        const tr = document.createElement('tr');
        tr.className = `${r.StatusTag} ${r.MethodTag}`; // highlight
        tr.innerHTML = `
            <td>${r.OfficeNumber}</td>
            <td>${r.MainGroup}</td>
            <td>${r.ApplicantName || 'غير مدرج'}</td>
            <td>${r.IdNumber ?? 'غير مدرج'}</td>
            <td>${(r.Date || '').toString().slice(0, 10)}</td>
            <td>${r.ArchStatus ?? 'معاملة جديدة'}</td>
            <td>${r.Method ?? 'حضور مباشرة إلى القنصلية'}</td>
            `;
        tr.addEventListener('click', () => {
            // open detail later via api_office_case_detail.php?table=...&id=...
            console.log('Open', r.OfficeTable, r.OfficeId);
            openOfficeCase(r.OfficeId, r.MainGroup);
        });
        body.appendChild(tr);
        if (mobile) {
            const card = document.createElement('article');
            card.className = 'm-case';
            card.dir = 'rtl';
            card.innerHTML = `
        <div class="m-case-head">
          <strong>قضية #${r.OfficeNumber}</strong>
          <time>${r.Date}</time>
        </div>
        <div class="m-case-meta">
          <div><span class="k">المجموعة:</span> <span class="v">${esc(r.MainGroup) || '-'}</span></div>
          <div><span class="k">الاسم:</span> <span class="v">${r.ApplicantName || '-'}</span></div>
        </div>
        <div class="m-case-actions">
          <button class="btn btn-primary" type="button">فتح</button>
        </div>
      `;
            card.querySelector('.btn')?.addEventListener('click', () => { if (r.OfficeId) openOfficeCase(r.OfficeId, r.MainGroup); });
            mobile.appendChild(card);
        }
    });



    const fromInput = document.getElementById("from");
    const toInput = document.getElementById("to");

    fromInput.addEventListener("change", () => {
        if (!toInput.value) {
            toInput.value = fromInput.value;
        }
    });



}

function getFilterParams() {
  return {
    officeNumber: document.getElementById("docId")?.value.trim() || "",
    applicantName: document.getElementById("applicant")?.value.trim() || "",
    mg: document.getElementById("mg")?.value || "",
    table: document.querySelector("input[name='officeTable']:checked")?.value || "both",
    dateFrom: document.getElementById("from")?.value || "",
    dateTo: document.getElementById("to")?.value || ""
  };
}


["docId", "applicant", "mg", "from", "to"].forEach(id => {
  document.getElementById(id)?.addEventListener("input", () => {
    loadOfficeList(getFilterParams());
  });
});

document.querySelectorAll('input[name="tableFilter"]').forEach(radio => {
  radio.addEventListener("change", () => {
    loadOfficeList(getFilterParams());
  });
});


// Auto-fill 'to' when 'from' changes
const fromInput = document.getElementById("from");
const toInput   = document.getElementById("to");
fromInput.addEventListener("change", () => {
    if (!toInput.value) {
        toInput.value = fromInput.value;
    }
    loadOfficeList(getFilterParams());
});


document.addEventListener("DOMContentLoaded", () => {
    const urlParams = new URLSearchParams(window.location.search);
    const group = urlParams.get("group");
    const id = urlParams.get("id");
    console.log(id, group);
    openOfficeCase(Number(id), group);

});

async function loadCases(opts = {}) {
    document.getElementById('step0').style.display = 'block';
    document.getElementById('step10').style.display = 'none';
    // document.getElementById('roleToggle_row').hidden = false;


    const userId = Number(localStorage.getItem('userId') || 1); // استبدلها بالجلسة لاحقًا
    const q = new URLSearchParams({
        userId,
        page: String(CasesUI.page),
        pageSize: String(CasesUI.pageSize),
    });

    const caseId = document.getElementById('fltCaseId')?.value.trim();
    const name = document.getElementById('fltName')?.value.trim();
    const df = document.getElementById('fltFrom')?.value;
    const dt = document.getElementById('fltTo')?.value;

    if (caseId) q.set('caseId', caseId);
    if (name) q.set('name', name);
    if (df) q.set('dateFrom', df);
    if (dt) q.set('dateTo', dt);

    CasesUI.lastQuery = Object.fromEntries(q.entries());

    const res = await fetch('api_cases_list.php?' + q.toString());
    if (!res.ok) { alert('فشل تحميل القائمة'); return; }
    const data = await res.json();
    CasesUI.total = data.total || 0;
    renderCasesTable(data.items || []);
    renderPager();
}

function personCard(p, idx, section) {
    const div = document.createElement('div');
    div.className = 'p-card';

    const id = p.ids?.[0] || {};
    div.innerHTML = `
    <h4>${p.name || '(بدون اسم)'} <span class="muted">• ${section === 'witnesses' ? 'شاهد' : section === 'authenticated' ? 'موكل' : 'مقدم طلب '}</span></h4>
    <div class="p-meta">
      <div>${p.job ? ('المهنة: ' + p.job) : ''}</div>
      <div>${p.nationality ? ('الجنسية: ' + p.nationality) : ''}</div>
      <div>${id.type ? ('الهوية: ' + id.type) : ''} ${id.number ? ('— ' + id.number) : ''}</div>
      <div>${id.expiry ? ('انتهاء الصلاحية: ' + id.expiry) : ''}</div>
    </div>
    <div class="p-actions">
      <button class="btn btn-sm btn-ghost" data-act="edit">تعديل</button>
      <button class="btn btn-sm btn-danger" data-act="del">حذف</button>
    </div>
  `;
    div.querySelector('[data-act="edit"]').onclick = () => openPartyModal(section, idx, p);
    div.querySelector('[data-act="del"]').onclick = () => deleteParty(section, idx);
    return div;
}

function renderCasesTable(items = []) {
    const body = document.querySelector('#casesTable tbody');
    const empty = document.getElementById('casesEmpty');
    const mobile = document.getElementById('casesTableMobile');

    if (body) body.innerHTML = '';
    if (mobile) mobile.innerHTML = '';

    const hasRows = Array.isArray(items) && items.length > 0;
    if (empty) empty.hidden = hasRows;

    if (!hasRows) return;

    const esc = s => String(s ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    items.forEach(it => {
        // ---- normalize fields ----
        const caseId = it._meta?.caseId ?? it.caseId ?? it.CaseID ?? it.id ?? '';
        const group = it.altColName ?? it.group ?? it.mainGroup ?? '';
        const type = it.altSubColName ?? it.type ?? '';
        const rawDt = it.date ?? it.CreatedAt ?? it.createdAt ?? '';
        const date = (rawDt && String(rawDt).length >= 10) ? String(rawDt).slice(0, 10) : String(rawDt || '');

        // ---- desktop row ----
        if (body) {
            const tr = document.createElement('tr');
            tr.innerHTML = `
        <td>${esc(caseId)}</td>
        <td>${esc(group)}</td>
        <td>${esc(type)}</td>
        <td>${esc(date)}</td>
      `;
            tr.addEventListener('click', () => { if (caseId) openCase(caseId); });
            body.appendChild(tr);
        }

        // ---- mobile card ----
        if (mobile) {
            const card = document.createElement('article');
            card.className = 'm-case';
            card.dir = 'rtl';
            card.innerHTML = `
        <div class="m-case-head">
          <strong>قضية #${esc(caseId)}</strong>
          <time>${esc(date)}</time>
        </div>
        <div class="m-case-meta">
          <div><span class="k">المجموعة:</span> <span class="v">${esc(group) || '-'}</span></div>
          <div><span class="k">النوع:</span> <span class="v">${esc(type) || '-'}</span></div>
        </div>
        <div class="m-case-actions">
          <button class="btn btn-primary" type="button">فتح</button>
        </div>
      `;
            card.querySelector('.btn')?.addEventListener('click', () => { if (caseId) openCase(caseId); });
            mobile.appendChild(card);
        }
    });
}


function renderPager() {
    const pgInfo = document.getElementById('pgInfo');
    const prev = document.getElementById('pgPrev');
    const next = document.getElementById('pgNext');
    const pages = Math.max(1, Math.ceil(CasesUI.total / CasesUI.pageSize));
    pgInfo.textContent = `صفحة ${CasesUI.page} من ${pages} (الإجمالي: ${CasesUI.total})`;
    prev.disabled = (CasesUI.page <= 1);
    next.disabled = (CasesUI.page >= pages);
}

async function openOfficeCase(officeId, MainGroup = 'توكيل', go_to_one = true) {
    console.log('openOfficeCase');
    if (!officeId) return;

    // Build query with proper encoding (Arabic-safe)
    const qs = new URLSearchParams({
        id: String(officeId),
        mainGroup: MainGroup || 'توكيل',
    });
    // console.log(mainGroup);
    const res = await fetch(`api_office_case_detail.php?${qs.toString()}`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
    });
    if (!res.ok) { return; }

    const data = await res.json();
    console.log(data);
    // keep your existing state fill

    appState = {
        doc_id: data.case?.doc_id ?? null,
        caseId: data.case?.caseId,
        userId: data.case?.userId ?? 0,
        lang: (data.details?.model?.langLabel === 'الانجليزية') ? 'en' : 'ar',
        modelId: data.details?.model?.id ?? data.case?.modelId ?? null,
        selected: {
            mainGroup: data.details?.model?.mainGroup,
            altColName: data.details?.model?.altColName,
            altSubColName: data.details?.model?.altSubColName,
        },
        applicants: (data.party?.applicants || []).map((a, i) => ({
            role: i === 0 ? 'primary' : 'co',
            name: a?.name ?? '',
            sex: a?.sex ?? null,
            job: a?.job ?? '',
            nationality: a?.nationality ?? '',
            residenceStatus: a?.residenceStatus ?? '',
            dob: a?.dob ?? '',
            ids: (Array.isArray(a?.ids) && a.ids.length)
                ? [{
                    type: a.ids[0]?.type ?? null,
                    number: a.ids[0]?.number ?? '',
                    issuer: a.ids[0]?.issuer ?? '',
                    expiry: a.ids[0]?.expiry ?? ''
                }]
                : []
        })),
        authenticated: (data.party?.authenticated || []).map(p => ({
            name: p?.name ?? '',
            sex: p?.sex ?? null,
            nationality: p?.nationality ?? '',
            ids: (Array.isArray(p?.ids) && p.ids.length)
                ? [{ type: p.ids[0]?.type ?? 'جواز سفر', number: p.ids[0]?.number ?? '' }]
                : []
        })),
        witnesses: (data.party?.witnesses || []).map(w => ({
            name: w?.name ?? '',
            sex: w?.sex ?? null,
            ids: (Array.isArray(w?.ids) && w.ids.length)
                ? [{ type: w.ids[0]?.type ?? 'جواز سفر', number: w.ids[0]?.number ?? '' }]
                : []
        })),
        answers: data.details?.answers || {},
        flags: {
            needAuthenticated: !!data.details?.requirements?.needAuthenticated,
            needWitnesses: !!data.details?.requirements?.needWitnesses
        }
    };
    localStorage.setItem('appState', JSON.stringify(appState));

    set(LS.caseId, String(data.case?.caseId ?? ''));
    set(LS.extRef, String(data.case?.externalRef ?? ''));
    set(LS.userId, String(data.case?.userId ?? ''));
    set(LS.lang, appState.lang);

    set(LS.tpl, String(appState.modelId ?? ''));
    set(LS.main, MainGroup || '');
    set(LS.altCol, data.details?.model?.altColName ?? '');
    set(LS.altSub, data.details?.model?.altSubColName ?? '');
    set(LS.uploadsDone, '0');

    allowLeaveStep0('open-case');
    if (go_to_one) showStep(1);

    window.universalappState = appState;
    return appState;
}


async function openCase(caseId) {
    if (!caseId) return;

    const res = await fetch('api_case_detail.php?caseId=' + encodeURIComponent(caseId));
    if (!res.ok) { alert('تعذر فتح القضية'); return; }
    const data = await res.json();

    // Build appState (keep if you use it elsewhere)
    const appState = {
        caseId: data.case?.caseId,
        userId: data.case?.userId,
        docLang: (data.details?.model?.langLabel === 'الانجليزية') ? 'en' : 'ar',
        modelId: data.details?.model?.id ?? data.case?.modelId ?? null,
        selected: {
            mainGroup: data.details?.model?.mainGroup,
            altColName: data.details?.model?.altColName,
            altSubColName: data.details?.model?.altSubColName,
        },
        applicants: (data.party?.applicants || []).map(a => ({
            id: 'a_' + (crypto?.randomUUID?.() || Math.random().toString(36).slice(2)),
            fullName: a.name || '',
            sex: a.sex || null,
            job: a.job || '',
            nationality: a.nationality || '',
            dob: a.dob || '',
            idType: (a.ids && a.ids[0]?.type) ? a.ids[0].type : null,
            idNumber: (a.ids && a.ids[0]?.number) ? a.ids[0].number : '',
            idExpiry: (a.ids && a.ids[0]?.expiry) ? a.ids[0].expiry : ''
        })),
        authenticated: data.party?.authenticated || [],
        witnesses: data.party?.witnesses || [],
        answers: data.details?.answers || {},
        flags: {
            needAuthenticated: !!data.details?.requirements?.needAuthenticated,
            needWitnesses: !!data.details?.requirements?.needWitnesses
        }
    };
    localStorage.setItem('appState', JSON.stringify(appState));
    console.log(appState);
    // ---- Save into LS keys (single source for later steps) ----
    set(LS.caseId, String(data.case?.caseId ?? ''));
    set(LS.extRef, String(data.case?.externalRef ?? ''));
    set(LS.userId, String(data.case?.userId ?? ''));
    set(LS.lang, String(data.case?.lang ?? '')) || 'ar'; // store 'ar' | 'en'
    console.log('online cases', appState.modelId)
    set(LS.tpl, String(appState.modelId ?? ''));
    set(LS.main, data.details.model.mainGroup || '');
    set(LS.altCol, data.details.model.altColName ?? '');
    set(LS.altSub, data.details.model.altSubColName ?? '');
    set(LS.uploadsDone, '0'); // default; update later when uploads complete


    // If you added the Step-0 guard, explicitly allow leaving:
    allowLeaveStep0('open-case');

    // ---- Always go to Step 1 (per your instruction) ----
    showStep(1);
}


document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('btnSearch')?.addEventListener('click', () => {
        CasesUI.page = 1;
        let role = localStorage.getItem('role')
        console.log(role);
        if (role === 'employee') {
            if (typeof loadOfficeList === 'function') loadOfficeList({});
        } else {
            if (typeof loadCases === 'function') loadCases({});
        }
    });
    document.getElementById('pgPrev')?.addEventListener('click', () => {
        if (CasesUI.page > 1) { CasesUI.page--; loadCases(); }
    });
    // document.getElementById('pgNext')?.addEventListener('click', () => {
    //     CasesUI.page++;
    //     let role = localStorage.getItem('role')
    //     console.log(role);
    //     if (role === 'employee') {
    //         if (typeof loadOfficeList === 'function') loadOfficeList({});
    //     } else {
    //         if (typeof loadCases === 'function') loadCases({});
    //     }
    // });

    // تحميل أولي

});


/* جلب نص الشرح أو نص عام افتراضي */
function getGroupHelpText(name) {
    return GROUP_HELP_TEXT[name] || `هذه مجموعة "${name}". اختر النوع المناسب ضمن هذا التصنيف. إن لم تجد النوع المطلوب، تواصل مع المشرف.`;
}

/* إنشاء/عرض نافذة الشرح بجانب الأيقونة */
function openGroupHelpPopover(name, anchor) {
    // إنشاء عنصر واحد يُعاد استخدامه
    let pop = document.getElementById('groupHelpPopover');
    if (!pop) {
        pop = document.createElement('div');
        pop.id = 'groupHelpPopover';
        pop.className = 'group-popover card';
        pop.innerHTML = `
      <div class="group-popover-title">شرح المجموعة</div>
      <div class="group-popover-body" id="groupHelpContent"></div>
    `;
        document.body.appendChild(pop);
    }

    // تعبئة المحتوى
    const bodyEl = document.getElementById('groupHelpContent');
    bodyEl.textContent = getGroupHelpText(name);

    // تموضع قرب الأيقونة مع مراعاة الحواف
    const r = anchor.getBoundingClientRect();
    const gap = 8;
    pop.style.visibility = 'hidden';
    pop.hidden = false;

    // حساب العرض بعد إظهاره مؤقتاً
    const popW = pop.offsetWidth;
    const left = Math.max(12, Math.min(window.innerWidth - popW - 12, r.left));
    const top = Math.max(12, r.bottom + gap);

    pop.style.left = `${left}px`;
    pop.style.top = `${top}px`;
    pop.style.visibility = 'visible';

    // إغلاق بالنقر خارج/زر Esc
    function close() {
        pop.hidden = true;
        document.removeEventListener('click', onDocClick, true);
        document.removeEventListener('keydown', onEsc, true);
    }
    function onDocClick(e) { if (!pop.contains(e.target) && e.target !== anchor) close(); }
    function onEsc(e) { if (e.key === 'Escape') close(); }

    // تفعيل مستمعي الإغلاق بعد الدورة الحالية كي لا يُغلق فوراً
    setTimeout(() => {
        document.addEventListener('click', onDocClick, true);
        document.addEventListener('keydown', onEsc, true);
    }, 0);
}

function fileTypeOK(f) { return OK_TYPES.has(f.type); }

function toastError(msg) {
    // minimal alert; swap with your toast UI if you have one
    alert(msg);
}


/* ========= tiny utils ========= */
const $ = s => document.querySelector(s);
const $$ = s => Array.from(document.querySelectorAll(s));
const get = k => localStorage.getItem(k);
const set = (k, v) => localStorage.setItem(k, v);
const del = k => localStorage.removeItem(k);

/* ========= language normalizer ========= */
function getLangCode() {
    const v = get(LS.lang);
    if (!v) return 'ar';
    const raw = String(v).trim();
    const latin = raw.toLowerCase();
    if (latin === 'en' || latin.includes('english')) return 'en';
    if (latin === 'ar' || latin.includes('arabic')) return 'ar';
    const ar = raw.replace(/[إأآ]/g, 'ا').replace(/\s+/g, '');
    if (ar === 'الانجليزية' || ar === 'انجليزية') return 'en';
    return 'ar';
}

function getLangArabCode() {
    const v = get(LS.lang);
    console.log(v);
    if (!v) return 'العربية';
    const raw = String(v).trim();
    const latin = raw.toLowerCase();
    if (latin === 'en' || latin.includes('english'))
        return 'الانجليزية';
    if (latin === 'ar' || latin.includes('arabic'))
        return 'العربية';

    return 'العربية';
}

/* ========= derive the real step from state ========= */
function truthStep() {
    const main = get(LS.main);
    const altCol = get(LS.altCol);
    const altSub = get(LS.altSub);
    const tpl = get(LS.tpl);
    const caseId = get(LS.caseId);
    const uploadsDone = get(LS.uploadsDone) === '1';

    if (!main) return 1;              // language / intro
    if (!altCol) return 2;            // choose altCol
    if (!altSub) return 2;            // choose altSub
    if (!tpl || !caseId) return 2;    // altSub chosen but draft not created yet
    if (!uploadsDone) return 3;       // draft exists → uploads
    return 4;                         // later: personal data / review / send
}

/* ========= view control ========= */
function panelId(n) { return `#step${n}`; }

function highlightStepper(n) {
    $$('.stepper-item').forEach(btn => {
        const s = Number(btn.dataset.step);
        btn.classList.toggle('is-current', s === n);
        if (n === 0) {
            // Only step 0 enabled while viewing the list
            if (s === 0) btn.removeAttribute('disabled');
            else btn.setAttribute('disabled', '');
        } else {
            if (s <= n) btn.removeAttribute('disabled');
            else btn.setAttribute('disabled', '');
        }
    });
}



function getStepsMeta() {
    const btns = Array.from(document.querySelectorAll('.stepper-item'));
    return btns.map(b => ({
        step: Number(b.dataset.step),
        label: b.querySelector('.lbl')?.textContent?.trim() || '',
        enabled: !b.hasAttribute('disabled'),
        current: b.classList.contains('is-current')
    }));
}

function syncMobileStepper() {
    const mob = document.getElementById('stepperMobile');
    if (!mob) return;

    const steps = getStepsMeta();
    const total = steps.length;
    const cur = steps.find(s => s.current) || steps[0] || { step: 1, label: '', enabled: true };
    const idx = cur.step;

    document.getElementById('smLabel').textContent = cur.label || '—';
    document.getElementById('smCount').textContent = `${idx} / ${total}`;

    const pct = total > 1 ? Math.round(((idx - 1) / (total - 1)) * 100) : 0;
    document.getElementById('smBar').style.width = pct + '%';

    mob.hidden = false; // ensure visible on mobile
}

function openStepPicker() {
    const sheet = document.getElementById('stepPicker');
    const list = document.getElementById('sheetList');
    list.innerHTML = '';
    const steps = getStepsMeta();
    steps.forEach(s => {
        const row = document.createElement('button');
        row.type = 'button';
        row.className = 'sheet-item' + (s.current ? ' current' : '');
        if (!s.enabled) row.setAttribute('aria-disabled', 'true');
        row.innerHTML = `
      <div class="meta">
        <span class="idx">${s.step}</span>
        <span class="lbl">${s.label}</span>
      </div>
      <span class="goto">انتقال</span>
    `;
        if (s.enabled) {
            row.addEventListener('click', () => {
                closeStepPicker();
                showStep(s.step);
            });
        }
        list.appendChild(row);
    });
    sheet.hidden = false;
}
function closeStepPicker() { document.getElementById('stepPicker').hidden = true; }

document.addEventListener('DOMContentLoaded', () => {
    const back = document.getElementById('smBack');
    const menu = document.getElementById('smMenu');
    const close = document.getElementById('sheetClose');
    const sheet = document.getElementById('stepPicker');

    back?.addEventListener('click', () => {
        const steps = getStepsMeta();
        const cur = steps.find(s => s.current);
        const prev = cur ? Math.max(1, cur.step - 1) : 1;
        if (prev !== cur.step) showStep(prev);
    });
    menu?.addEventListener('click', openStepPicker);
    close?.addEventListener('click', closeStepPicker);
    sheet?.addEventListener('click', (e) => { if (e.target === sheet) closeStepPicker(); });

    // initial sync (and resync after first render)
    syncMobileStepper();

});

// IMPORTANT: call this at the end of your goToStep(n) function




let CURRENT_STEP = null;
let STEP_INIT_DONE = new Set();
let step0ExitReason = null;           // only set by user actions
// enable/disable auto-reload after navigation
const AUTO_RELOAD_STEPS = true;              // <- turn off if you change your mind
const RELOAD_KEY = 'ahwal.reloadedForStep';  // session-only guard

function allowLeaveStep0(reason) {      // call this before leaving list
    step0ExitReason = reason || 'user';

}
// ---- drop-in replacement ----
const STEP_RELOAD_GUARD_KEY = 'stepReloadGuard';
const RELOADABLE_STEPS = new Set([2, 3, 4, 5, 6, 7]);
let __showStepBusy = false;

async function showStep(n) {
    n = Number(n);

    if (__showStepBusy) {
        console.warn('[showStep] blocked (busy), requested:', n);

        return;
    }
    __showStepBusy = true;

    // one-reload guard (consume only if it matches this step)
    let justReloaded = false;
    try {
        const raw = sessionStorage.getItem(STEP_RELOAD_GUARD_KEY);
        if (raw) {
            const g = JSON.parse(raw);
            if (g && g.step === n && (Date.now() - g.ts) < 10000) {
                justReloaded = true;
                sessionStorage.removeItem(STEP_RELOAD_GUARD_KEY);
            }
        }
    } catch { }

    // hide all, show target
    document.querySelectorAll('section[id^="step"]').forEach(sec => sec.hidden = true);

    const target = document.getElementById(`step${n}`) || (typeof panelId === 'function' ? document.querySelector(panelId(n)) : null);
    if (target)
        target.hidden = false;
    else console.warn('[showStep] panel not found for step', n);

    // UI state
    if (typeof highlightStepper === 'function') highlightStepper(n);
    if (typeof set === 'function' && typeof LS !== 'undefined') set(LS.saved, String(n));
    const roleRow = document.getElementById('roleToggle_row');
    if (roleRow) roleRow.hidden = n > 0;
    const step0 = document.getElementById('step0');
    const step10 = document.getElementById('step10');
    if (n > 0) {
        if (step0)
            step0.style.display = 'none';
        if (step10)
            step10.style.display = 'none';
    }
    // run init (await if async)
    try {
        const inits = {
            0: (typeof initStep0 === 'function') ? initStep0 : null,
            1: (typeof initStep1 === 'function') ? initStep1 : null,
            2: (typeof initStep2 === 'function') ? initStep2 : null,
            3: (typeof initStep3 === 'function') ? initStep3 : null,
            4: (typeof initStep4 === 'function') ? initStep4 : null,
            5: (typeof initStep5 === 'function') ? initStep5 : null,
            6: (typeof initStep6 === 'function') ? initStep6 : null,
            7: (typeof initStep7 === 'function') ? initStep7 : null,
        };
        const fn = inits[n];
        const ret = fn ? fn() : null;
        if (ret && typeof ret.then === 'function') await ret;
    } catch (e) {
        console.error('[showStep] init error on step', n, e);
    }

    if (typeof syncMobileStepper === 'function') syncMobileStepper();

    // make sure step 1 stays visible (paint next frame)
    requestAnimationFrame(() => {
        if (target) target.hidden = false;
    });

    // reload policy: NEVER on 0–1; at most once on 2–7
    if (RELOADABLE_STEPS.has(n) && !justReloaded) {
        sessionStorage.setItem(STEP_RELOAD_GUARD_KEY, JSON.stringify({ step: n, ts: Date.now() }));
        setTimeout(() => location.reload(), 50);
    }

    // unlock calls after a brief tick (avoid re-entrant flips)
    setTimeout(() => { __showStepBusy = false; }, 150);
}

/* Allow clicking earlier steps to view them */
function wireStepperClicks() {
    $$('.stepper-item').forEach(btn => {
        btn.addEventListener('click', () => {
            const s = Number(btn.dataset.step);
            const saved = Math.max(1, Number(get(LS.saved) || 1));
            const allowed = Math.max(truthStep(), saved);
            if (s <= allowed) showStep(s);
        });
    });
}

/* ========= RESET (عملية جديدة) if you add a button with this id ========= */
$('#btnNewProcess10')?.addEventListener('click', () => {
    const keep = new Set([LS.userId]); // preserve language & user id
    Object.values(LS).forEach(k => { if (!keep.has(k)) del(k); });

    new_case = true;
    showStep(1);
});

$('#btnNewProcess')?.addEventListener('click', () => {
    const keep = new Set([LS.userId]); // preserve language & user id
    Object.values(LS).forEach(k => { if (!keep.has(k)) del(k); });
    new_case = true;
    showStep(1);
});

/* ========= STEP 2 (main/alt selection) ========= */
const MAIN_GROUP_ORDER = [
    'إفادة لمن يهمه الأمر',
    'إقرار',
    'إقرار مشفوع باليمين',
    'توكيل',
    'مخاطبة لتاشيرة دخول'
];
const empText = document.getElementById("empText");

if (empText) {
    empText.addEventListener("input", checkMixedLanguages);
}

function checkMixedLanguages() {
    const text = empText.value || "";

    const inform_user = document.getElementById('inform-user');
    if (!inform_user) return;

    inform_user.style.display = 'none';
    inform_user.innerText = "";

    // --- Find Arabic & English words
    const arabicWords = text.match(/[\u0600-\u06FF]+/g) || [];
    const englishWords = text.match(/[A-Za-z]+/g) || [];

    const significantArabic = arabicWords.filter(w => w.length > 1);
    const significantEnglish = englishWords.filter(w => w.length > 1);

    const arCount = significantArabic.length;
    const enCount = significantEnglish.length;

    if (arCount > 0 && enCount > 0) {
        let minorityWords = [];
        let minorityLang = "";

        if (arCount > enCount) {
            minorityWords = significantEnglish;
            minorityLang = "English";
        } else if (enCount > arCount) {
            minorityWords = significantArabic;
            minorityLang = "Arabic";
        } else {
            // equal case → just pick one (e.g. English)
            minorityWords = significantEnglish;
            minorityLang = "English";
        }

        // pick up to 3 examples
        const examples = minorityWords.slice(0, 3).join(", ");

        inform_user.innerText =
            `⚠️ هذا النص يحتوي على كلمات بالعربية والانجليزية ` +
            `مثل  ${minorityLang}: ${examples}`;

        inform_user.style.display = 'flex';
    }
}


function iconForMain(name) {
    if (name === 'إفادة لمن يهمه الأمر') return 'ℹ️';
    if (name === 'إقرار') return '📝';
    if (name === 'إقرار مشفوع باليمين') return '✋';
    if (name === 'توكيل') return '🤝';
    if (name === 'مخاطبة لتاشيرة دخول') return '✈️';
    return '📌';
}

async function loadMainGroups() {
    const res = await fetch(apiUrl('./api_maingroups.php?lang=' + encodeURIComponent(getLangCode())));
    const grid = $('#groupGrid');
    const empty = $('#groupEmpty');

    if (!res.ok) {
        grid.innerHTML = '';
        empty.hidden = false;
        return;
    }

    const data = await res.json();
    const list = Array.isArray(data.maingroups) ? data.maingroups : [];

    const ordered = MAIN_GROUP_ORDER.map(n => list.find(x => x.name === n) || { name: n, subCount: 0 });

    grid.innerHTML = '';
    empty.hidden = ordered.some(x => x.subCount > 0);

    ordered.forEach(g => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'card-item' + (g.subCount > 0 ? '' : ' is-disabled');
        btn.setAttribute('role', 'listitem');

        btn.innerHTML = `
            <div class="icon-circle">${iconForMain(g.name)}</div>
            <button class="help-ico" type="button" title="شرح" aria-label="شرح المجموعة">
            <!-- أيقونة معلومات بسيطة -->
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor"/>
                <circle cx="12" cy="8" r="1" fill="currentColor"/>
                <rect x="11" y="11" width="2" height="6" fill="currentColor"/>
            </svg>
            </button>
            <div class="card-body">
            <div class="card-title-sm">${g.name}</div>
            <div class="badge">الأنواع: ${g.subCount}</div>
            </div>
        `;

        // زر الشرح: افتح النافذة ولا تُشغّل نقرة البطاقة
        const helpBtn = btn.querySelector('.help-ico');
        helpBtn.addEventListener('click', (ev) => {
            ev.stopPropagation();
            openGroupHelpPopover(g.name, helpBtn);
        });

        // سلوك البطاقة الأساسي
        if (g.subCount > 0) {
            btn.addEventListener('click', () => {
                set(LS.main, g.name);
                openAltcolModal(g.name);
            });
        }

        grid.appendChild(btn);
    });

}

async function initStep0() {
    // keep step 1 disabled while on list

    set(LS.lang, '');
    // const roleToggle_row = document.getElementById('roleToggle_row');
    // if (roleToggle_row)
    //     roleToggle_row.style.display = 'flex';
    // else
    //     return;
    document.querySelector('.stepper-item[data-step="1"]')
        ?.setAttribute('disabled', '');
    let role = localStorage.getItem('role')
    if (role === 'employee') {
        console.log('initStep0');
        document.getElementById('step10').style.display = 'block';
        document.getElementById('step0').style.display = 'none';
        if (typeof loadOfficeList === 'function') await loadOfficeList({});


    } else {
        if (typeof loadCases === 'function') await loadCases({});
        document.getElementById('step10').style.display = 'none';
        document.getElementById('step0').style.display = 'block';
    }


}



// normalize whatever is stored into 'ar' | 'en'
function resolveLangCode() {
    const raw = (get?.(LS.lang) ?? localStorage.getItem(LS.lang) ?? '').trim().toLowerCase();

    if (raw === 'ar' || raw === 'العربية') return 'ar';
    if (raw === 'en' || raw === 'الانجليزية' || raw === 'الإنجليزية') return 'en';
    return ''; // unknown
}

function applyLangRadioFromLS() {
    const langVal = get(LS.lang);                        // ← read the stored value
    if (!langVal) { showStep(0); return; }               // ← go to list if not chosen yet
    const code = resolveLangCode();
    if (!code) return;
    // try radios named docLang or lang
    const radios = document.querySelectorAll(
        'input[type="radio"][name="docLang"], input[type="radio"][name="lang"]'
    );
    let selected = false;
    radios.forEach(r => {
        if ((r.value || '').toLowerCase() === code) {
            if (!r.checked) { r.checked = true; selected = true; }
        }
    });

    // fallback by id or data attr if needed
    if (!selected) {
        const el = document.getElementById(code === 'ar' ? 'lang_ar' : 'lang_en')
            || document.querySelector(`[data-lang="${code}"]`);
        if (el && el.type === 'radio' && !el.checked) { el.checked = true; selected = true; }
    }

    // persist normalized value and notify listeners
    set?.(LS.lang, code);
    const checked =
        document.querySelector('input[type="radio"][name="docLang"]:checked') ||
        document.querySelector('input[type="radio"][name="lang"]:checked');
    if (checked) checked.dispatchEvent(new Event('change', { bubbles: true }));
}

async function initStep1() {
    // Read the stored language value (e.g., 'العربية' | 'الانجليزية' | 'ar' | 'en')
    const lang = get(LS.lang);
    // If no language chosen yet, go to Step 0 (via showStep so UI state stays consistent)
    if (!new_case && (lang == null || lang === '' || lang === 'null')) {
        showStep(0);            // <- don't call initStep0() directly
        return;
    }
    document.getElementById('roleToggle_row').style.display = 'none';
    document.getElementById('step0').style.display = 'none';
    document.getElementById('step10').style.display = 'none';
    console.log('hide list');
    applyLangRadioFromLS();
}




async function initStep2() {
    loadMainGroups();
    renderStep2SelectionSummary();
    console.log(get(LS.tpl));
    const tpl = localStorage.getItem(LS.tpl);
    console.log(tpl);
    if (tpl) {
        console.log('fetchDealExplanationByTemplateId');
        const info = await fetchDealExplanationByTemplateId(tpl);
        showDealExplanation(info || '');
        document.querySelector('.stepper-item[data-step="3"]')?.removeAttribute('disabled');
    }
    console.log(get(LS.tpl));
}

/* ===== altCol modal ===== */
let altcolModalEls;
function getAltcolEls() {
    if (altcolModalEls) return altcolModalEls;
    altcolModalEls = {
        modal: $('#altcolModal'),
        close: $('#altcolClose'),
        title: $('#altcolTitle'),
        hint: $('#altcolHint'),
        grid: $('#altcolGrid'),
        empty: $('#altcolEmpty'),
    };
    return altcolModalEls;
}
function wireAltcolModalListeners() {
    const { modal, close } = getAltcolEls();
    if (!modal) return;
    close?.addEventListener('click', closeAltcolModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeAltcolModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.hidden) closeAltcolModal(); });
}
function openAltcolModal(mainGroupName) {
    showDealExplanation('');
    const { modal, title, hint } = getAltcolEls();
    if (!modal) return;
    title.textContent = `المجموعة: ${mainGroupName}`;
    hint.textContent = 'اختر المجموعة الفرعية للمتابعة:';
    modal.hidden = false;
    loadAltCols(mainGroupName);
}
function closeAltcolModal() {
    const { modal, grid } = getAltcolEls();
    if (!modal) return;
    modal.hidden = true;
    if (grid) grid.innerHTML = '';
}
async function loadAltCols(mainGroupName) {
    const { grid, empty } = getAltcolEls();
    if (!grid || !empty) return;

    grid.innerHTML = '';
    empty.hidden = true;
    console.log(getLangArabCode());
    const url = './api_altcols.php?lang=' + encodeURIComponent(getLangCode()) +
        '&group=' + encodeURIComponent(mainGroupName);
    const res = await fetch(apiUrl(url));

    if (!res.ok) { empty.hidden = false; return; }

    const data = await res.json();
    const items = Array.isArray(data.items) ? data.items : [];
    if (!items.length) { empty.hidden = false; return; }
    let altgroup = '';
    let AltcolCount = 0;
    items.forEach(it => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'card-item';
        btn.setAttribute('role', 'listitem');
        btn.innerHTML = `
            <div class="icon-circle">📁
            </div>
            <div class="card-body">
                <div class="card-title-sm">${it.name}</div>
                <div class="badge">الأنواع: ${it.subCount}</div>
            </div>
        `;
        altgroup = it.name;
        btn.addEventListener('click', () => {
            set(LS.altCol, it.name);
            closeAltcolModal();
            openSubcolModal(mainGroupName, it.name);
        });

        AltcolCount++;
        grid.appendChild(btn);

    });
    if (AltcolCount === 1) {
        set(LS.altCol, altgroup);
        closeAltcolModal();
        openSubcolModal(mainGroupName, altgroup);
    }
}

/* ===== altSub modal ===== */
let subcolModalEls;
function getSubcolEls() {
    if (subcolModalEls) return subcolModalEls;
    subcolModalEls = {
        modal: $('#subcolModal'),
        close: $('#subcolClose'),
        title: $('#subcolTitle'),
        hint: $('#subcolHint'),
        grid: $('#subcolGrid'),
        empty: $('#subcolEmpty'),
    };
    return subcolModalEls;
}
function wireSubcolModalListeners() {
    const { modal, close } = getSubcolEls();
    if (!modal) return;
    close?.addEventListener('click', closeSubcolModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeSubcolModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.hidden) closeSubcolModal(); });
}
function openSubcolModal(mainGroupName, altColName) {
    const { modal, title, hint } = getSubcolEls();
    if (!modal) return;

    if (altColName === mainGroupName || altColName.includes('مذكرة لسفارة')) {
        title.textContent = `المجموعة: ${mainGroupName}`;
    } else {
        title.textContent = `المجموعة: ${mainGroupName} ← ${altColName}`;
    }

    hint.textContent = 'اختر النوع الفرعي المطلوب';
    modal.hidden = false;
    loadAltSubs(mainGroupName, altColName);
}
function closeSubcolModal() {
    const { modal, grid } = getSubcolEls();
    if (!modal) return;
    modal.hidden = true;
    if (grid) grid.innerHTML = '';
}
async function loadAltSubs(mainGroupName, altColName) {
    const { grid, empty } = getSubcolEls();
    if (!grid || !empty) return;

    grid.innerHTML = '';
    empty.hidden = true;

    const url = './api_altsubs.php?lang=' + encodeURIComponent(getLangCode()) +
        '&group=' + encodeURIComponent(mainGroupName) +
        '&altcol=' + encodeURIComponent(altColName);
    const res = await fetch(apiUrl(url));

    if (!res.ok) { empty.hidden = false; return; }

    const data = await res.json();
    const items = Array.isArray(data.items) ? data.items : [];
    if (!items.length) { empty.hidden = false; return; }

    items.forEach(it => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'card-item';
        btn.setAttribute('role', 'listitem');
        btn.innerHTML = `
            <div class="icon-circle">📑</div>
            <div class="card-body">
                <div class="card-title-sm">${it.name}</div>
                
            </div>
        `;
        // <div class="card-title-sm">${it.id}</div>
        btn.addEventListener('click', async () => {
            set(LS.altSub, it.name);
            console.log('api_altsubs', it.id);
            set(LS.tpl, String(it.id));

            closeSubcolModal();

            // توضيح المعاملة
            showDealExplanation('');
            const info = await fetchDealExplanationByTemplateId(it.id);
            showDealExplanation(info);

            // Create draft now (so reload can jump to step 3)
            try { await ensureDraftCase(); } catch { }

            // === NEW: update رقم/نوع المعاملة in DB ===
            try {
                const caseId = localStorage.getItem('caseId') || '';
                const mainGroup = get(LS.main) || '';   // already stored when main group chosen
                const altCol = get(LS.altCol) || '';
                const altSub = get(LS.altSub) || '';

                if (caseId && mainGroup && altCol && altSub) {
                    const payload = { caseId, mainGroup, altCol, altSub };
                    console.log(payload);
                    const res = await fetch(apiUrl('./api_office_case_update_type.php'), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data?.ok) {
                        console.warn('[update_type] failed', res.status, data);
                    } else {
                        console.log('[update_type] success', data);
                    }
                } else {
                    console.warn('[update_type] skipped, missing values', { caseId, mainGroup, altCol, altSub });
                }
            } catch (err) {
                console.error('[update_type] error', err);
            }
            // === END NEW ===

            // after setting LS.altSub & LS.tpl and calling ensureDraftCase()
            const step3Btn = document.querySelector('.stepper-item[data-step="3"]');
            step3Btn?.removeAttribute('disabled');

            const nextBtnStep2 = document.getElementById('nextBtnStep2'); // add in HTML if missing
            if (nextBtnStep2) {
                nextBtnStep2.disabled = false;
                nextBtnStep2.onclick = () => showStep(3);
            }

            // Show توضيح_المعاملة, and wait for user to click stepper or Next
            // (do not auto-showStep(3) here)
        });

        grid.appendChild(btn);
    });
}

/* ===== توضيح المعاملة ===== */
async function fetchDealExplanationByTemplateId(tid) {
    const res = await fetch(apiUrl('./api_proc_req.php?templateId=' + encodeURIComponent(tid)));
    if (!res.ok) return null;
    const { row } = await res.json();
    const raw = row?.['توضيح_المعاملة'];
    if (!raw) return null;
    const parts = String(raw).split(/[-–—]/).map(s => s.trim()).filter(Boolean);
    if (!parts.length) return null;
    return parts.map((s, i) => `${i + 1}. ${s}`).join('\n');
}
function showDealExplanation(text) {
    const box = $('#dealInfo');
    const span = $('#dealInfoText');
    box.hidden = false;
    if (!box || !span) return;
    if (text && String(text).trim() !== '') {
        span.textContent = text;

        box.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        span.textContent = 'لا توجد معلومات متاحة حاليا بشان المعاملة، يرجى التواصل مع صالة خدمات الجمهور للحصول على التفاصيل الكافية';
        // box.hidden = true;
    }
    renderStep2SelectionSummary();
}

/* ===== Draft case (creates online.Cases draft) ===== */
async function ensureDraftCase() {
    const existingCaseId = get(LS.caseId);              // e.g. "123"
    const modelIdRaw = get(LS.tpl);
    const modelId = Number(modelIdRaw || 0);
    if (!modelId) return existingCaseId || null;        // Need a template to proceed

    const userId = Number(get(LS.userId) || 1);

    // Build payload; include caseId ONLY if it exists (server treats it as update)
    const payload = {
        existingCaseId,
        userId,
        modelId,
        lang: getLangCode(),
        mainGroup: get(LS.main) || '',
        altColName: get(LS.altCol) || '',
        altSubColName: get(LS.altSub) || ''
    };
    console.log(payload);
    try {
        let url = apiUrl('./api_case_create.php');
        if (get('role') === 'employee')
            url = apiUrl('./api_office_case_create.php');
        console.log(url);
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if (!res.ok) {
            // Keep current caseId if update failed; otherwise return null
            console.warn('[ensureDraftCase] Non-OK response', res.status);
            return existingCaseId || null;
        }
        const data = await res.json();
        if (!(data?.ok && data.caseId)) {
            return existingCaseId || null;
        }

        const returnedId = String(data.caseId);
        const isInsert = data.mode === 'insert' || !existingCaseId || returnedId !== String(existingCaseId);
        console.log(data);
        // Persist IDs
        localStorage.setItem(LS.caseId, returnedId);
        if (data.externalRef) localStorage.setItem(LS.extRef, data.externalRef);

        // Per-case UI/state reset ONLY on new case
        if (isInsert) {

            currentGalleryCaseId = returnedId;
            clearUploadsUI();
            localStorage.removeItem('apptDate');
            localStorage.removeItem('apptHour');
            localStorage.setItem(LS.uploadsDone, '0');
            refreshUploadsList();
        }

        return returnedId;
    } catch (err) {
        console.error('[ensureDraftCase] Failed', err);
        return existingCaseId || null;
    }
}
// Robust resolver: handles 4xx/5xx, timeouts, soft-fail JSON, and busy UI.
async function resolveModelIdFromAltPair(debug = false) {
    const altCol = get(LS.altCol) || '';
    const altSub = get(LS.altSub) || '';

    if (!altCol || !altSub) {
        // No inputs → go back immediately
        try { showStep(2); } catch { }
        return null;
    }

    const url = apiUrl(`api_case_model.php?${new URLSearchParams({ altCol, altSub })}`);

    let res;
    try {
        res = await fetch(url, { headers: { Accept: 'application/json' } });
    } catch (e) {
        console.error('[resolveModelId] network error', e);
        // Jump back now
        // (defer by a tick so we don't collide with current UI work)
        setTimeout(() => {
            try {
                __showStepBusy = false;
                showStep(2);
            } catch { }
        }, 0);
        return null;
    }

    if (!res.ok) {

        setTimeout(() => { try { showStep(2); } catch { } }, 0);
        return null;
    }

    let j;
    try {
        j = await res.json(); // your API returns manageable { ok, found, id } or { id }
    } catch (e) {
        console.error('[resolveModelId] JSON parse error', e);
        setTimeout(() => { try { showStep(2); } catch { } }, 0);
        return null;
    }

    const id = Number(j?.id ?? 0);
    const found = typeof j?.found === 'boolean' ? j.found : id > 0;

    if (!found || id <= 0) {
        console.warn('[resolveModelId] no id returned', j);
        __showStepBusy = false;
        showStep(2);
        return null;
    }

    set(LS.tpl, String(id));
    return id;
}


/* ===== STEP 3 (requirements list for uploads) ===== */



function updateNextStep3Button(enabled = true) {
    const btn = document.getElementById('nextBtnStep3');
    if (!btn) return;
    btn.classList.toggle('hidden', !enabled);
    btn.disabled = !enabled;
    btn.onclick = enabled ? () => showStep(4) : null;    // navigate
    setUploadsDoneFrom(requiredStatus);
}

// --- Step 3 helper: persist "all required uploaded" for reload logic ---
function setUploadsDoneFrom(requiredStatus) {
    const allOk = Object.values(requiredStatus).every(Boolean);
    localStorage.setItem(LS.uploadsDone, allOk ? '1' : '0'); // FIX
}

function syncUploadedRequirements(uploadedItems = []) {
    if (!Array.isArray(uploadedItems)) return;

    const rows = document.querySelectorAll('.req-row');
    console.log('syncUploadedRequirements → found rows:', rows.length);

    const norm = s => (s || '').replace(/\s+/g, ' ').trim();
    const uploadedLabels = new Set(uploadedItems.map(it => norm(it.Label || '')));
    console.log('uploadedLabels:', uploadedLabels);

    rows.forEach(row => {
        const labelEl = row.querySelector('.req-label');
        const statusEl = row.querySelector('.req-status');
        if (!labelEl || !statusEl) {
            console.log('row missing label/status', row);
            return;
        }

        const label = norm(labelEl.textContent);
        if (uploadedLabels.has(label)) {
            statusEl.textContent = '✓ تم التحميل';
            statusEl.classList.add('ok');
            requiredStatus[label] = true;
            console.log('✅ matched:', label);
        } else {
            statusEl.textContent = 'غير محمل';
            statusEl.classList.remove('ok');
            requiredStatus[label] = false;
            console.log('❌ not matched:', label);
        }
    });

    if (typeof updateNextStep3Button === 'function') {
        updateNextStep3Button();
    }
}

async function loadRequirements() {
    const step3 = $('#step3');
    if (!step3 || step3.hidden) return [];

    let modelId = get(LS.tpl);
    modelId = await resolveModelIdFromAltPair(false);
    if (!modelId) { renderRequirements([], []); return []; }

    // 1. load requirements
    const res = await fetch(apiUrl('./api_requirements.php?modelId=' + encodeURIComponent(modelId)));
    if (!res.ok) { renderRequirements([], []); return []; }
    const data = await res.json();
    const requiredItems = Array.isArray(data.items) ? data.items : [];

    // 2. load already uploaded files for this case
    const caseId = localStorage.getItem('caseId');
    let uploadedItems = [];
    if (caseId) {
        let url = apiUrl('./api_casefile_list.php?caseId=' + encodeURIComponent(caseId));
        if (get('role') === 'employee') {
            url = apiUrl('./api_office_casefile_list.php?caseId=' + encodeURIComponent(caseId));
        }
        const resUp = await fetch(url);
        if (resUp.ok) {
            const dataUp = await resUp.json();
            if (dataUp?.ok && Array.isArray(dataUp.items)) uploadedItems = dataUp.items;
        }
    }

    // 3. render both together
    renderRequirements(requiredItems, uploadedItems);

    return requiredItems;
}

function renderRequirements(requiredItems, uploadedItems = []) {
    const list = document.getElementById('reqList');
    const empty = document.getElementById('reqEmpty');
    if (!list || !empty) return;

    list.innerHTML = '';
    requiredStatus = {};

    const norm = s => (s || '').replace(/\s+/g, ' ').trim();
    const uploadedSet = new Set(uploadedItems.map(it => norm(it.Label || '')));
    console.log(uploadedSet);
    function normalizeLabel(str) {
        return str.replace(/_\d+$/, ""); // remove underscore + trailing numbers
        }
    requiredItems
        .filter(l => !norm(l).includes('أخرى'))
        .forEach((rawLabel, idx) => {
            const label = norm(rawLabel);
            console.log(label);
            const alreadyUploaded = Array.from(uploadedSet).some(
                item => normalizeLabel(item) === normalizeLabel(label)
                );
            requiredStatus[label] = alreadyUploaded;

            const row = document.createElement('div');
            row.className = 'req-row';
            const inputId = `req-file-${idx}`;

            row.innerHTML = `
                <span class="req-num">${idx + 1}.</span>
                <span class="req-label">${label}</span>
                <label for="${inputId}" class="upload-btn">📂 اختر ملف</label>
                <input id="${inputId}" class="req-file" type="file" accept=".pdf,.jpg,.jpeg,.png" />
                <span class="req-status ${alreadyUploaded ? 'ok' : ''}" id="${inputId}-status">
                  ${alreadyUploaded ? '✓ تم التحميل' : 'غير محمل'}
                </span>
            `;

            row.querySelector('input').addEventListener('change', (e) => {
                const file = e.target.files?.[0];
                if (!file) return;
                handleRequiredUpload(label, file, `${inputId}-status`);
            });

            list.appendChild(row);
        });

    updateNextStep3Button();
}

async function handleRequiredUpload(label, file, statusId) {
    // validations
    if (file.size > MAX_FILE_SIZE) {
        toastError('الحجم يتجاوز 3 ميغابايت.');
        return;
    }
    if (!fileTypeOK(file)) {
        toastError('صيغة الملف غير مدعومة.');
        return;
    }

    const statusEl = document.getElementById(statusId);
    if (statusEl) statusEl.textContent = '... جاري التحميل';

    const form = new FormData();
    form.append('caseId', localStorage.getItem('caseId') || '');
    form.append('modelId', localStorage.getItem('selectedTemplateId') || '');
    form.append('kind', 'required');         // <-- backend hint
    form.append('label', label);             // المطلوب_رقمX label
    form.append('file', file);

    try {
        let url = apiUrl('./api_casefile_upload.php');
        if (get('role') === 'employee')
            url = apiUrl('./api_office_casefile_upload.php');

        const res = await fetch(url, { method: 'POST', body: form });
        const data = await res.json().catch(() => null);

        if (res.ok && data?.success) {
            if (statusEl) {
                console.log('statusEl');
                statusEl.textContent = '✓ تم التحميل';
                statusEl.classList.remove('err');
                statusEl.classList.add('ok');
                refreshUploadsList();
            }
            requiredStatus[label] = true;
        } else {
            if (statusEl) {
                statusEl.textContent = 'فشل التحميل';
                statusEl.classList.remove('ok');
                statusEl.classList.add('err');
            }
            requiredStatus[label] = false;
            toastError(data?.error || 'تعذر التحميل.');
        }
    } catch (e) {
        if (statusEl) {
            statusEl.textContent = 'فشل التحميل';
            statusEl.classList.remove('ok');
            statusEl.classList.add('err');
        }
        requiredStatus[label] = false;
        toastError('تعذر الاتصال بالخادم.');
    } finally {
        updateNextStep3Button();
    }
}

function renderExtraRow(idx) {
    const div = document.createElement('div');
    div.className = 'extra-row';
    const inputId = `extra-file-${idx}`;
    div.innerHTML = `
        <label for="${inputId}">📂 اختر مستند إضافي</label>
        <input id="${inputId}" type="file" accept=".pdf,.jpg,.jpeg,.png,.docx" />
        <button type="button" class="icon-btn" title="إزالة">✖️</button>
        <span class="req-status" id="${inputId}-status">—</span>
    `;
    const input = div.querySelector('input');
    const removeBtn = div.querySelector('button');

    input.addEventListener('change', (e) => {
        const f = e.target.files?.[0];
        if (!f) return;
        handleExtraUpload(idx, f, `${inputId}-status`);
    });
    removeBtn.addEventListener('click', () => {
        div.remove();
        extraCount = Math.max(0, extraCount - 1);
        updateAddExtraButton();
    });

    return div;
}

function updateAddExtraButton() {
    const btn = document.getElementById('btnAddExtra');
    if (!btn) return;
    btn.disabled = extraCount >= 3;
}

document.getElementById('btnAddExtra')?.addEventListener('click', () => {
    if (extraCount >= 3) return;
    const host = document.getElementById('extraList');
    if (!host) return;
    const row = renderExtraRow(extraCount + 1);
    host.appendChild(row);
    extraCount += 1;
    updateAddExtraButton();
});

async function handleExtraUpload(idx, file, statusId) {
    if (file.size > MAX_FILE_SIZE) {
        toastError('الحجم يتجاوز 3 ميغابايت.');
        return;
    }
    if (!fileTypeOK(file)) {
        toastError('صيغة الملف غير مدعومة.');
        return;
    }

    const statusEl = document.getElementById(statusId);
    if (statusEl) statusEl.textContent = '... جاري التحميل';

    const label = `مستند داعم ${idx}`;
    const form = new FormData();
    form.append('caseId', localStorage.getItem('caseId') || '');
    form.append('modelId', localStorage.getItem('selectedTemplateId') || '');
    form.append('kind', 'extra');           // <-- backend hint
    form.append('label', label);
    form.append('file', file);

    try {
        const res = await fetch(apiUrl('./api_casefile_upload.php'), { method: 'POST', body: form });
        const data = await res.json().catch(() => null);
        if (res.ok && data?.success) {
            if (statusEl) {
                statusEl.textContent = '✓ تم التحميل';
                statusEl.classList.remove('err');
                statusEl.classList.add('ok');
                refreshUploadsList();
            }
        } else {
            if (statusEl) {
                statusEl.textContent = 'فشل التحميل';
                statusEl.classList.remove('ok');
                statusEl.classList.add('err');
            }
            toastError(data?.error || 'تعذر التحميل.');
        }
    } catch (e) {
        if (statusEl) {
            statusEl.textContent = 'فشل التحميل';
            statusEl.classList.remove('ok');
            statusEl.classList.add('err');
        }
        toastError('تعذر الاتصال بالخادم.');
    }
}

function renderStep2SelectionSummary() {
    const host = document.getElementById('step2Summary');
    if (!host) return;
    if (!localStorage.getItem(LS.main)) {
        document.getElementById('step2Summary').style.display = 'none';
        return;
    }
    document.getElementById('step2Summary').style.display = 'flex';

    const main = localStorage.getItem(LS.main) || '—';
    const alt = localStorage.getItem(LS.altCol) || '—';
    const sub = localStorage.getItem(LS.altSub) || '—';

    host.innerHTML = `
    <div class="summary-line">
        <span>نوع الإجراء:</span><strong>${main}</strong> - 
        <span>المجموعة:</span><strong>${alt}</strong> - 
        <span>الاجراء الفرعي:</span><strong>${sub}
    </div>`;
    const nextBtnStep2 = document.getElementById('nextBtnStep2'); // add in HTML if missing
    if (nextBtnStep2) {
        nextBtnStep2.disabled = false;
        nextBtnStep2.onclick = () => showStep(3);
    }
}

$('#step0')?.addEventListener('click', () => {
    document.querySelector('.stepper-item[data-step="0"]')?.removeAttribute('disabled');

    // const keep = new Set([LS.userId]); // preserve language & user id
    // Object.values(LS).forEach(k => { if (!keep.has(k)) del(k); });
    // showStep(0);
    let role = localStorage.getItem('role')
    if (role === 'employee') {

        if (typeof loadOfficeList === 'function') loadOfficeList({});
    } else {
        if (typeof loadCases === 'function') loadCases({});
    }
});

function initStep3() {
    // Rebuild required list for current template
    loadRequirements();

    refreshUploadsList('nextBtnStep3');
    setTimeout(() => {
        syncUploadedRequirements(window.uploadedItems);
    }, 50);

    // Reset extra area on entry
    const extraHost = document.getElementById('extraList');
    if (extraHost) extraHost.innerHTML = '';
    extraCount = 0;
    updateAddExtraButton();
    updateNextStep3Button();
}

// Scan ".req-row" list and report missing uploads
function validateRequiredUploads() {
    const rows = document.querySelectorAll('.req-row');
    const missing = [];
    
    rows.forEach(row => {
        const label = (row.querySelector('.req-label')?.textContent || '').trim();
        const stEl = row.querySelector('.req-status');
        const txt = (stEl?.textContent || '').replace(/\s+/g, ' ').trim();

        const isOk =
            (stEl && stEl.classList.contains('ok')) ||
            /^✓/.test(txt) ||
            /تم\s*التحميل/.test(txt);
        console.log(label);
        if (label && !isOk) missing.push(label);
    });

    if (missing.length) {
        alert('الرجاء تحميل المستندات المطلوبة قبل المتابعة:\n- ' + missing.join('\n- '));
        return false;
    }
    return true;
}

document.querySelectorAll('input[name="docLang"]').forEach(radio => {
    radio.addEventListener('change', async (e) => {
        const lang = e.target.value;
        console.log("Language chosen:", lang);

        // 🔹 Update page direction + localStorage
        if (lang === 'ar') {
            document.body.setAttribute('dir', 'rtl');
            localStorage.setItem('docLang', 'ar');
        } else if (lang === 'en') {
            document.body.setAttribute('dir', 'ltr');
            localStorage.setItem('docLang', 'en');
        }

        // 🔹 Send to backend immediately
        const caseId = get(LS.caseId);       // ensure caseId is saved in localStorage earlier
        const mainGroup = get(LS.main) || 'توكيل';
        let lang_tosave = 'العربية'
        if (lang === 'en')
            lang_tosave = 'الانجليزية';
        if (caseId) {
            try {
                const res = await fetch('/api_update_lang.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        caseId: Number(caseId),
                        mainGroup: mainGroup,
                        lang: lang_tosave
                    })
                });
                const j = await res.json();
                if (j.ok) {
                    console.log("✅ Language updated in table:", j);
                } else {
                    console.warn("⚠️ Failed to update language:", j);
                }
            } catch (err) {
                console.error("❌ Network error updating language:", err);
            }
        } else {
            console.warn("⚠️ No caseId found in localStorage; cannot update language.");
        }
    });
});


/* ===== Boot ===== */
document.addEventListener('DOMContentLoaded', () => {
    document.body.dir = 'rtl';

    // Save language radio immediately
    // Restore saved language choice on load
    {
        const savedLang = localStorage.getItem('docLang');
        if (savedLang) {
            const el = document.querySelector(`input[name="docLang"][value="${CSS.escape(savedLang)}"]`);
            if (el) el.checked = true;
        }
    }


    // Lightweight "Next" from Step 1 → Step 2
    $('#nextBtn')?.addEventListener('click', () => {
        // If user picked language, keep it; otherwise default 'ar'
        if (!get(LS.lang)) set(LS.lang, 'ar');
        // Unlock step 2 and move
        document.querySelector('.stepper-item[data-step="2"]')?.removeAttribute('disabled');
        showStep(2);
    });

    // Wire modals
    wireAltcolModalListeners();
    wireSubcolModalListeners();

    // Stepper navigation (allow viewing previous steps)
    wireStepperClicks();

    // Decide panel on reload
    // Decide panel on reload
    const savedRaw = localStorage.getItem(LS.saved);
    const derived = truthStep();                           // fallback if nothing saved
    const saved = (savedRaw == null) ? derived : Number(savedRaw);
    const toShow = Number.isFinite(saved) ? saved : derived;


    for (let s = 2; s <= Math.max(1, toShow); s++) {
        document.querySelector(`.stepper-item[data-step="${s}"]`)?.removeAttribute('disabled');
    }
    showStep(toShow);



    // Next from Step 3 (future: to personal data)
    const nextBtn = document.getElementById('nextBtnStep3') || document.querySelector('#nextBtnStep3');
    if (nextBtn) {
        // If this is a <button type="submit"> inside a form, make it a plain button.
        if (nextBtn.tagName === 'BUTTON' && nextBtn.type === 'submit') {
            nextBtn.type = 'button';
        }

        nextBtn.addEventListener('click', (e) => {
            if (!validateRequiredUploads()) {
                e?.preventDefault?.();
                e?.stopPropagation?.();
                e?.stopImmediatePropagation?.(); // in case other handlers are attached
                return; // <- DO NOT call showStep(4)
            }
            document.querySelector('.stepper-item[data-step="4"]')?.removeAttribute('disabled');
            showStep(4);
        }, true); // capture = true to intercept before bubbling handlers
    }


});

(function () {
    const qs = (s, r = document) => r.querySelector(s);
    const qsa = (s, r = document) => Array.from(r.querySelectorAll(s));

    window.updateAddExtraButton = function updateAddExtraButton(opts = {}) {
        const {
            btnSelector = '#btnAddExtra',
            listSelector = '#extraList',
            maxItems = 3,
            source = 'dom' // 'dom' | 'state'
        } = opts;

        const btn = qs(btnSelector);
        if (!btn) return;

        let count = 0;
        if (source === 'state') {
            const s = window.appState || {};
            const xs = s.step3?.extras ?? s.uploads?.extra ?? s.extras ?? [];
            count = Array.isArray(xs) ? xs.length : 0;
        } else {
            const host = qs(listSelector);
            count = host ? qsa('.extra-row, [data-extra-row], .extra-card', host).length : 0;
        }

        const atLimit = count >= maxItems;
        btn.disabled = atLimit;
        btn.classList.toggle('is-disabled', atLimit);
        btn.setAttribute('aria-disabled', atLimit ? 'true' : 'false');
        btn.title = atLimit ? `تم الوصول للحد الأقصى (${maxItems})` : '';
        return { count, maxItems, atLimit, remaining: Math.max(0, maxItems - count) };
    };
})();

function humanSize(n) {
    if (!Number.isFinite(n)) return '';
    const u = ['B', 'KB', 'MB', 'GB']; let i = 0, v = n;
    while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
    return v.toFixed(v < 10 ? 1 : 0) + ' ' + u[i];
}

async function renderPdfThumbInto(containerEl, fileUrl, boxW = 160, boxH = 180) {
    try {
        const res = await fetch(apiUrl(fileUrl), { cache: 'no-store' });
        const buf = await res.arrayBuffer();

        const pdf = await pdfjsLib.getDocument({ data: buf }).promise;
        const page = await pdf.getPage(1);

        const viewport = page.getViewport({ scale: 1 });
        // scale to fit inside box (contain)
        const scale = Math.min(boxW / viewport.width, boxH / viewport.height);
        const scaledVp = page.getViewport({ scale });

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d', { alpha: false });

        canvas.width = Math.ceil(scaledVp.width);
        canvas.height = Math.ceil(scaledVp.height);

        // center inside thumb box
        canvas.style.maxWidth = '100%';
        canvas.style.maxHeight = '100%';
        canvas.style.objectFit = 'contain';

        await page.render({ canvasContext: ctx, viewport: scaledVp }).promise;

        // clear and mount
        containerEl.innerHTML = '';
        containerEl.appendChild(canvas);
    } catch (e) {
        // fallback icon on failure
        containerEl.innerHTML = '<span style="font-size:48px">📄</span>';
        console.error('PDF thumb render failed:', e);
    }
}

function renderUploadsGallery(items) {
    // Ensure gallery host
    let host = document.getElementById('uploadsGallery');
    if (!host) {
        host = document.createElement('div');
        host.id = 'uploadsGallery';
        host.className = 'uploads-gallery';
        document.getElementById('step3')?.appendChild(host);
    }
    host.innerHTML = '';

    // Empty/placeholder element (by id or class)
    const emptyEl =
        document.getElementById('uploadsEmpty') ||
        document.querySelector('.uploads-empty') ||
        document.getElementById('empty');

    // Build map: label -> status span from the required rows
    const norm = s => (s || '').replace(/\s+/g, ' ').trim();
    const statusMap = new Map();
    document.querySelectorAll('.req-row').forEach(row => {
        const lbl = row.querySelector('.req-label')?.textContent || '';
        const st = row.querySelector('.req-status');
        if (lbl && st) statusMap.set(norm(lbl), st);
    });

    // Helper: infer kind if MimeType is missing
    const inferKind = it => {
        let m = (it.MimeType || it.mimeType || it.MIME || '').toLowerCase();
        let ext = (it.Extension1 || it.extension || '').toLowerCase();
        if (!m && ext) {
            ext = ext.startsWith('.') ? ext.slice(1) : ext;
            if (ext === 'pdf') m = 'application/pdf';
            else if (ext === 'png') m = 'image/png';
            else if (ext === 'jpg' || ext === 'jpeg') m = 'image/jpeg';
        }
        return m;
    };

    // Mark uploaded statuses by label and render cards
    (items || []).forEach(it => {
        const key = norm(it.Label || '');
        const stEl = statusMap.get(key);
        if (stEl) {
            stEl.textContent = '✓ تم التحميل';
            stEl.classList.add('ok'); // optional CSS hook
            if (typeof requiredStatus === 'object') {
                requiredStatus[key] = true;
            }
        }

        if (typeof requiredStatus === 'object' && key) {
            requiredStatus[key] = true;
        }

        // --- Card UI ---
        const card = document.createElement('div');
        card.className = 'u-card';

        const thumb = document.createElement('div');
        thumb.className = 'u-thumb';

        const kind = inferKind(it);
        let fileUrl = `./api_casefile_get.php?fileId=${encodeURIComponent(it.FileID)}`;
        if (get('role') === 'employee') {
            fileUrl = `./api_office_casefile_get.php?fileId=${encodeURIComponent(it.FileID)}`;
        }
        console.log(kind);
        if (kind.startsWith('image/') || kind.includes('jpeg') || kind.endsWith('jpg') || kind.startsWith('.jpg') || kind.endsWith('png')) {
            const img = document.createElement('img');
            img.loading = 'eager';
            img.decoding = 'async';
            img.alt = it.Label || '';
            img.src = fileUrl;
            thumb.appendChild(img);
        } else if (kind === 'application/pdf' || kind.endsWith('pdf')) {
            renderPdfThumbInto(thumb, fileUrl, 160, 180);
        } else {
            const span = document.createElement('span');
            span.textContent = '📄';
            span.style.fontSize = '48px';
            thumb.appendChild(span);
        }

        const meta = document.createElement('div');
        meta.className = 'u-meta';
        const sizeTxt = (typeof it.SizeBytes === 'number' && it.SizeBytes > 0) ? humanSize(it.SizeBytes) : '';
        meta.innerHTML = `
      <div title="${it.Label || ''}">${it.Label || ''}</div>
      <div class="muted">${sizeTxt}</div>
    `;

        const actions = document.createElement('div');
        actions.className = 'u-actions';

        // View (عرض) — inline open
        const view = document.createElement('a');
        view.href = fileUrl;
        view.target = '_blank';
        view.rel = 'noopener';
        view.className = 'btn-xs btn-ghost';
        view.title = 'عرض';
        view.innerHTML = `
      <span class="ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"></path>
          <circle cx="12" cy="12" r="3"></circle>
        </svg>
      </span>
      <span>عرض</span>
    `;
        actions.appendChild(view);

        // Delete (حذف)
        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'btn-xs btn-danger';
        del.title = 'حذف';
        del.innerHTML = `
      <span class="ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="3 6 5 6 21 6"></polyline>
          <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
          <line x1="10" y1="11" x2="10" y2="17"></line>
          <line x1="14" y1="11" x2="14" y2="17"></line>
        </svg>
      </span>
      <span>حذف</span>
    `;

        let del_url = apiUrl('./api_casefile_delete.php');
        if (get('role') === 'employee') del_url = apiUrl('./api_office_casefile_delete.php');

        del.onclick = async () => {
            if (!confirm('حذف هذا المستند؟')) return;
            const form = new FormData();
            form.append('fileId', it.FileID);
            form.append('caseId', localStorage.getItem('caseId') || '');
            const res = await fetch(del_url, { method: 'POST', body: form });
            const data = await res.json().catch(() => ({}));
            if (data?.ok) {
                // revert required status for this label
                const lblEl = statusMap.get(key);
                if (lblEl) {
                    lblEl.textContent = 'غير محمل';
                    lblEl.classList.remove('ok');
                }
                if (typeof requiredStatus === 'object' && key) {
                    requiredStatus[key] = false;
                }
                updateNextStep3Button?.();
                refreshUploadsList();
            } else {
                alert(data?.error || 'تعذر الحذف.');
            }
        };
        actions.appendChild(del);

        // Assemble card
        card.appendChild(thumb);
        card.appendChild(meta);
        card.appendChild(actions);
        host.appendChild(card);
    });

    // --- Control the empty/placeholder visibility here ---
    let showEmpty = false;
    if (!items || items.length === 0) {
        showEmpty = true; // no uploads at all
    } else if (typeof requiredStatus === 'object' && Object.keys(requiredStatus).length > 0) {
        // show as long as ANY required label is still missing
        showEmpty = Object.values(requiredStatus).some(v => !v);
    }
    if (emptyEl) emptyEl.hidden = !showEmpty;

    // Re-evaluate “Next” button state
    if (typeof updateNextStep3Button === 'function') updateNextStep3Button();
    return items;
}

async function refreshUploadsList() {
    const caseId = localStorage.getItem('caseId');
    if (!caseId) { clearUploadsUI(); currentGalleryCaseId = null; return; }

    if (currentGalleryCaseId !== caseId) { // case switched → wipe UI first
        clearUploadsUI();
        currentGalleryCaseId = caseId;
    }
    let url = apiUrl('./api_casefile_list.php?caseId=' + encodeURIComponent(caseId))
    if (get('role') === 'employee') {
        url = apiUrl('./api_office_casefile_list.php?caseId=' + encodeURIComponent(caseId))
    }

    const res = await fetch(url);
    if (!res.ok) return;
    const data = await res.json();
    if (data?.ok && Array.isArray(data.items)) {
        renderUploadsGallery(data.items);
        window.uploadedItems = data.items;

    }
}


// step 4
function val(id) { return (document.getElementById(id)?.value || '').trim(); }
function setVal(id, v) { const el = document.getElementById(id); if (el) el.value = v ?? ''; }
function isEmailOk(s) { return !s || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s); }

// function validateStep4() {
//     const ok = !!val('fullName') && !!val('idNumber') && !!val('phone') && isEmailOk(val('email'));
//     const btn = document.getElementById('nextBtnStep4');
//     if (btn) btn.disabled = !ok;
//     return ok;
// }

async function fetchCaseDetailsJson() {
    const caseId = localStorage.getItem('caseId');
    if (!caseId) return {};
    const res = await fetch(apiUrl('./api_case_get.php?caseId=' + encodeURIComponent(caseId)));
    if (!res.ok) return {};
    const data = await res.json().catch(() => ({}));
    return data?.row?.DetailsJson ? JSON.parse(data.row.DetailsJson) : {};
}

// Step 4 boot
// ===== Step 4 state =====
let partyState = {
    applicants: [],
    authenticated: [],
    witnesses: [],
    contact: { phone: '', email: '' },
    requirements: { needAuthenticated: false, needWitnesses: false } // fallback; we’ll load real values if present
};

function setFieldError(inputEl, msg) {
    // remove old error
    inputEl.classList.remove('input-error');
    inputEl.parentElement?.querySelector('.error-text')?.remove();

    if (!msg) return;

    inputEl.classList.add('input-error');
    const span = document.createElement('span');
    span.className = 'error-text';
    span.textContent = msg;
    inputEl.parentElement?.appendChild(span);
}

function clearFormErrors() {
    document.querySelectorAll('#partyForm .input-error').forEach(el => el.classList.remove('input-error'));
    document.querySelectorAll('#partyForm .error-text').forEach(el => el.remove());
}

function arabicDocLang() { return (localStorage.getItem('docLang') || 'ar').toLowerCase().startsWith('ar'); }

// Utilities
const SCHEMA_SECTIONS = ['applicants', 'authenticated', 'witnesses'];
const $g = (id) => document.getElementById(id);

function safeSetValue(id, v) {
    const el = $g(id);
    if (el) el.value = v ?? '';
}

// النوع (sex) radios
function getSexValue() {
    return document.querySelector('input[name="pf_sex"]:checked')?.value || '';
}
function setSexValue(v) {
    const el = document.querySelector(`input[name="pf_sex"][value="${CSS.escape(v)}"]`);
    if (el) el.checked = true;
}

// حالة الإقامة (residence) radios
function getResidenceValue() {
    return document.querySelector('input[name="pf_res"]:checked')?.value || '';
}
function setResidenceValue(v) {
    const el = document.querySelector(`input[name="pf_res"][value="${CSS.escape(v)}"]`);
    if (el) el.checked = true;
}

const caseIdLS = () => localStorage.getItem('caseId') || '';
const langIsArabic = () => (localStorage.getItem('docLang') || 'ar').toLowerCase().startsWith('ar');

// Input constraints per language
function sanitizeName(s) {
    console.log(langIsArabic());
    const v = String(s || '').trim();
    if (langIsArabic()) return v.replace(/[A-Za-z]/g, '');
    return v.replace(/[\u0600-\u06FF]/g, ''); // strip Arabic for English docs
}

// Validations
function isSudanPassport(num) { return /^[PB]\d{8}$/.test(num || ''); }
function isSudanNID(num) { return /^\d{11}$/.test(num || ''); }
function isFuture(dateStr) {
    if (!dateStr) return true;
    const d = new Date(dateStr), today = new Date();
    if (Number.isNaN(d.getTime())) return false;
    d.setHours(0, 0, 0, 0); today.setHours(0, 0, 0, 0);
    return d >= today;
}

/**
 * Returns { ok:boolean, errors: {fieldId: 'msg', ...}, list: ['msg1','msg2',...] }
 * fieldId values map to your modal inputs: pf_name, pf_sex, pf_id_type, pf_id_number, pf_id_expiry, pf_id_issuer
 */
function validatePersonDetailed(section, p, requirements) {
    const errors = {};
    const list = [];

    // ---------- Normalize ID type (Arabic/English) ----------
    const id = (p.ids && p.ids[0]) ? p.ids[0] : {};
    const rawType = String(id.type || '').trim().toLowerCase();
    // map to canonical Arabic labels we use everywhere
    const typeNorm =
        rawType === 'passport' || rawType === 'جواز سفر' ? 'جواز سفر' :
            rawType === 'national_id' || rawType === 'رقم وطني' ? 'رقم وطني' :
                rawType === 'iqama' || rawType === 'إقامة' || rawType === 'اقامة' ? 'إقامة' :
                    rawType === 'other' || rawType === 'أخرى' ? 'أخرى' :
                        (id.type || ''); // leave as-is if unknown

    // reflect normalization back (so caller/state gets the normalized value)
    if (id.type !== typeNorm) id.type = typeNorm;

    const isPassport = typeNorm === 'جواز سفر';
    const isNationalId = typeNorm === 'رقم وطني';

    // ---------- Name (all sections) ----------
    console.log(p);
    if (!p.name?.trim()) {
        errors.pf_name = 'الاسم مطلوب.';
        list.push('الاسم مطلوب.');
    } else {
        const parts = p.name.trim().split(/\s+/);
        if (parts.length < 4) {
            errors.pf_name = 'الاسم يجب أن يتكوّن من أربعة أسماء على الأقل.';
            list.push('الاسم يجب أن يتكوّن من أربعة أسماء على الأقل.');
        }
        // language constraint
        if (arabicDocLang() && /[A-Za-z]/.test(p.name)) {
            errors.pf_name = 'اسم المستفيد يجب أن يكون بالحروف العربية.';
            list.push('اسم المستفيد يجب أن يكون بالحروف العربية.');
        }
        if (!arabicDocLang() && /[\u0600-\u06FF]/.test(p.name)) {
            errors.pf_name = 'اسم المستفيد يجب أن يكون بالحروف الانجليزية.';
            list.push('اسم المستفيد يجب أن يكون بالحروف الانجليزية.');
        }
    }

    // ---------- Section flags ----------
    const needAuth = !!(requirements?.needAuthenticated);
    const needWit = !!(requirements?.needWitnesses);

    // ---------- Sex ----------
    // Required for applicants & authenticated; not required for witnesses
    // if (!getRadioVal('pf_sex')) {
    //     errors.pf_sex_group = 'النوع مطلوب.';
    //     list.push('النوع مطلوب.');
    // }

    if (!p.sex?.trim() && (get('role') !== 'employee' && section === 'witnesses')) {
        errors.pf_sex_group = 'النوع مطلوب.';
        list.push('النوع مطلوب.');
    }


    // ---------- Nationality handling ----------
    // Applicants: UI locks to "سوداني" (no check needed here)
    // Witnesses: no nationality required
    // Authenticated: REMOVE read-only in UI; if empty default to "سوداني" (no error)
    if (section === 'authenticated') {
        if (!p.nationality || !p.nationality.trim()) {
            p.nationality = 'سوداني'; // default silently
        }
    }

    // ---------- ID presence ----------
    if (section === 'witnesses') {

        if (!id.number?.trim()) {
            errors.pf_id_number = 'رقم جواز الشاهد مطلوب.';
            list.push('رقم جواز الشاهد مطلوب.');
        }
        if (getRadioVal('pf_sex') === 'F') {
            errors.pf_sex_group = 'الشاهد يجب ان يكون ذكر فقط';
            list.push('الشاهد يجب ان يكون ذكر فقط');
        }

    } else {
        // applicants & authenticated
        if (!id.type) {
            errors.pf_id_type = 'نوع الهوية مطلوب.';
            list.push('نوع الهوية مطلوب.');
        }
        if (!id.number?.trim()) {
            errors.pf_id_number = 'رقم الهوية مطلوب.';
            list.push('رقم الهوية مطلوب.');
        }
        if (section !== 'authenticated') {
            if (!id.issuer) {
                errors.pf_id_type = 'مكان الإصدار مطلوب.';
                list.push('مكان الإصدار مطلوب.');
            } else {
                // language constraint
                if (arabicDocLang() && /[A-Za-z]/.test(id.issuer)) {
                    errors.pf_id_issuer = 'مكان الإصدار يجب أن يكون بالحروف العربية.';
                    list.push('مكان الإصدار يجب أن يكون بالحروف العربية.');
                }
                if (!arabicDocLang() && /[\u0600-\u06FF]/.test(id.issuer)) {
                    errors.pf_id_issuer = 'مكان الإصدار يجب أن يكون بالحروف الانجليزية.';
                    list.push('مكان الإصدار يجب أن يكون بالحروف الانجليزية.');
                }
            }
        }

    }

    // ---------- ID format ----------
    if (isPassport && id.number) {
        if (!isSudanPassport(id.number)) {
            errors.pf_id_number = 'صيغة جواز السفر غير صحيحة (P/B متبوعة بـ 8 أرقام).';
            list.push('صيغة جواز السفر غير صحيحة (P/B متبوعة بـ 8 أرقام).');
        }
    }
    if (isNationalId && id.number) {
        if (!isSudanNID(id.number)) {
            errors.pf_id_number = 'الرقم الوطني يجب أن يتكون من 11 رقمًا.';
            list.push('الرقم الوطني يجب أن يتكون من 11 رقمًا.');
        }
    }

    // ---------- Issuer / Expiry ----------
    // Applicants: issuer required unless national_id; expiry optional but if present must be future
    if (section === 'applicants') {
        if (!isNationalId && !id.issuer) {
            errors.pf_id_issuer = 'جهة/مكان الإصدار مطلوب.';
            list.push('جهة/مكان الإصدار مطلوب.');
        }
        if (id.expiry && !isFuture(id.expiry)) {
            errors.pf_id_expiry = 'تاريخ الانتهاء لا يمكن أن يكون في الماضي.';
            list.push('تاريخ الانتهاء لا يمكن أن يكون في الماضي.');
        }
    }
    // Authenticated & Witnesses: issuer/expiry not required (no checks)

    // ---------- Extra constraints ----------
    // If the case requires authenticated or witnesses, applicants must use Sudanese ID (passport or national_id)
    if ((needAuth || needWit) && section === 'applicants') {
        if (!(isPassport || isNationalId)) {
            errors.pf_id_type = 'يجب أن تكون هوية المتقدّم سودانية (جواز سوداني أو رقم وطني).';
            list.push('يجب أن تكون هوية المتقدّم سودانية (جواز سوداني أو رقم وطني).');
        }
    }

    // Witnesses rule is already enforced above (passport + pattern)

    return { ok: list.length === 0, errors, list };
}


// Back-compat shim: old code still calls validatePerson(section, person)
function validatePerson(section, p) {
    return validatePersonDetailed(section, p, partyState?.requirements || {}).ok;
}

// Getter
function getResidenceValue() {
    return document.querySelector('input[name="pf_res"]:checked')?.value || '';
}

// Setter (when opening modal)
function setResidenceValue(v) {
    const el = document.querySelector(`input[name="pf_res"][value="${v}"]`);
    if (el) el.checked = true;
}

// Normalize Arabic for comparisons (remove diacritics, unify forms)
function normalizeArabic(s = '') {
    return String(s)
        .trim()
        .replace(/[\u064B-\u0652]/g, '')  // tashkeel
        .replace(/[إأآ]/g, 'ا')
        .replace(/ة/g, 'ه')
        .replace(/\s+/g, ' ');
}

function nameKey(s) {
    // compact key for detecting duplicates (ignore extra spaces/case)
    return normalizeArabic(s).toLowerCase();
}

function idKey(s) {
    // normalize ID number: strip non-alphanumerics, uppercase
    return String(s || '').replace(/[^0-9A-Za-z]/g, '').toUpperCase();
}

// Build a simulated party state that includes the pending edit
function withProposedChange(baseParty, section, index, person) {
    const clone = {
        applicants: Array.isArray(baseParty.applicants) ? baseParty.applicants.slice() : [],
        authenticated: Array.isArray(baseParty.authenticated) ? baseParty.authenticated.slice() : [],
        witnesses: Array.isArray(baseParty.witnesses) ? baseParty.witnesses.slice() : [],
    };
    const arr = clone[section] || [];
    if (index >= 0 && index < arr.length) arr[index] = person;
    else arr.push(person);
    clone[section] = arr;
    return clone;
}



/**
 * Check duplicates across all sections.
 * Returns { ok: boolean, names: string[], ids: string[], messages: string[] }
 */
function checkPartyDuplicates(partyAll) {
    const namesSeen = new Map();   // key -> [displayNames...]
    const idsSeen = new Map();   // key -> [display (name#last4)...]

    function addPerson(p) {
        if (!p) return;
        if (p.name) {
            const k = nameKey(p.name);
            if (k) namesSeen.set(k, (namesSeen.get(k) || []).concat(p.name));
        }
        const id = (p.ids && p.ids[0]) || {};
        if (id.number) {
            const k = idKey(id.number);
            if (k) {
                const tag = p.name ? `${p.name}#${id.number.slice(-4)}` : id.number;
                idsSeen.set(k, (idsSeen.get(k) || []).concat(tag));
            }
        }
    }

    (partyAll.applicants || []).forEach(addPerson);
    (partyAll.authenticated || []).forEach(addPerson);
    (partyAll.witnesses || []).forEach(addPerson);

    const dupNames = [];
    namesSeen.forEach((arr) => { if (arr.length > 1) dupNames.push([...new Set(arr)].join('، ')); });

    const dupIds = [];
    idsSeen.forEach((arr) => { if (arr.length > 1) dupIds.push([...new Set(arr)].join('، ')); });

    const messages = [];
    if (dupNames.length) messages.push('توجد أسماء مكررة: ' + dupNames.join(' | '));
    if (dupIds.length) messages.push('توجد أرقام هويات مكررة: ' + dupIds.join(' | '));

    return { ok: dupNames.length === 0 && dupIds.length === 0, names: dupNames, ids: dupIds, messages };
}


function canProceedStep4() {
    const req = partyState?.requirements || { needAuthenticated: false, needWitnesses: false, needWitnessesOptional: false };
    const A = partyState?.applicants ?? [];
    const AU = partyState?.authenticated ?? [];
    const W = partyState?.witnesses ?? [];
    document.getElementById('saveStep4').style.display = "none";
    document.getElementById('nextBtnStep4').style.display = "none";
    if (A.length < 1) return { ok: false, reason: 'يجب إضافة متقدّم واحد على الأقل.' };
    if (req.needAuthenticated && AU.length < 1) return { ok: false, reason: 'هذه المعاملة تتطلب مصادِق.' };
    if (req.needWitnesses && W.length !== 2) return { ok: false, reason: 'هذه المعاملة تتطلب شاهدين بالضبط.' };

    // Field-level validation
    for (const [section, list] of [['applicants', A], ['authenticated', AU], ['witnesses', W]]) {
        for (let i = 0; i < list.length; i++) {
            const r = validatePersonDetailed(section, list[i], req);
            if (!r.ok) return { ok: false, reason: `خطأ في ${section === 'applicants' ? 'المتقدّم' : section === 'authenticated' ? 'الموكل' : 'الشاهد'} رقم ${i + 1}: ${r.list[0]}` };
        }
    }

    // Global duplicate check
    const dup = checkPartyDuplicates({ applicants: A, authenticated: AU, witnesses: W });
    if (!dup.ok) {
        return { ok: false, reason: dup.messages.join(' — ') || 'توجد بيانات مكررة.' };
    }
    document.getElementById('saveStep4').style.display = "flex";
    document.getElementById('nextBtnStep4').style.display = "flex";
    return { ok: true };
}





function updateNextBtnStep4() {
    const btn = $g('nextBtnStep4');
    if (!btn) return;

    const res = canProceedStep4();

    // Enable/disable
    btn.disabled = !res.ok;
    btn.classList.toggle('is-disabled', !res.ok);

    // Optional inline hint under the button
    const hintId = 'nextStep4Hint';
    let hint = $g(hintId);
    if (!hint) {
        hint = document.createElement('div');
        hint.id = hintId;
        hint.className = 'muted';
        btn.parentElement?.appendChild(hint);
    }
    hint.textContent = res.ok ? '' : res.reason;

    // For debugging if needed:
    // console.debug('[Step4] gate:', res);
}

// Render to BOTH views: table (desktop) and cards (mobile).
function renderCases(rows = []) {
    // Desktop
    const tb = document.querySelector('#casesTable tbody');
    const emptyDesktop = document.getElementById('casesEmpty');
    if (tb) tb.innerHTML = rows.map(r => `
    <tr data-id="${r.id}">
      <td>${r.id ?? ''}</td>
      <td>${r.group ?? ''}</td>
      <td>${r.type ?? ''}</td>
      <td>${r.date ?? ''}</td>
    </tr>
  `).join('');

    const hasRows = rows.length > 0;
    if (emptyDesktop) emptyDesktop.hidden = hasRows;

    // Mobile cards
    const cardsHost = document.getElementById('casesCards');
    const emptyMobile = document.getElementById('casesEmptyMobile');
    if (cardsHost) {
        cardsHost.hidden = !hasRows;
        cardsHost.innerHTML = rows.map(r => `
      <article class="case-card" data-id="${r.id}" dir="rtl">
        <header>
          <div>قضية #${r.id ?? ''}</div>
          <time datetime="${r.date ?? ''}">${r.date ?? ''}</time>
        </header>
        <div class="case-meta">
          <div><strong>المجموعة:</strong> ${r.group ?? '-'}</div>
          <div><strong>النوع:</strong> ${r.type ?? '-'}</div>
        </div>
        <div class="case-actions">
          <button class="btn btn-ghost" data-action="open">فتح</button>
          <button class="btn" data-action="continue">متابعة</button>
        </div>
      </article>
    `).join('');
    }
    if (emptyMobile) emptyMobile.hidden = hasRows;

    // Click handlers for mobile cards (open/continue)
    cardsHost?.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-action]');
        if (!btn) return;
        const card = btn.closest('.case-card');
        const id = card?.getAttribute('data-id');
        if (!id) return;
        if (btn.dataset.action === 'open') {
            // your open handler:
            openCase?.(id);
        } else if (btn.dataset.action === 'continue') {
            // your continue flow:
            openCase?.(id);
            showStep?.(1);
        }
    }, { once: true }); // remove if you re-render frequently
}

// Example: mapping your existing row shape to {id, group, type, date}
function mapRow(r) {
    return {
        id: r.CaseID ?? r.id,
        group: r.mainGroup ?? r.group ?? r['المجموعة'],
        type: r.altSubColName ?? r.type ?? r['النوع'],
        date: r.CreatedAt?.slice(0, 10) ?? r.date
    };
}

// After fetching:
/// const rows = data.rows.map(mapRow); renderCases(rows);


// ===== Render cards =====
function personCard(p, idx, section) {
    const div = document.createElement('div');
    div.className = 'p-card';

    const id = p.ids?.[0] || {};
    div.innerHTML = `
    <h4>${p.name || '(بدون اسم)'} <span class="muted">• ${section === 'witnesses' ? 'شاهد' : section === 'authenticated' ? 'موكل' : 'مقدم طلب '}</span></h4>
    <div class="p-meta">
      <div>${p.job ? ('المهنة: ' + p.job) : ''}</div>
      <div>${p.nationality ? ('الجنسية: ' + p.nationality) : ''}</div>
      <div>${id.type ? ('الهوية: ' + id.type) : ''} ${id.number ? ('— ' + id.number) : ''}</div>
      <div>${id.expiry ? ('انتهاء الصلاحية: ' + id.expiry) : ''}</div>
    </div>
    <div class="p-actions">
      <button class="btn btn-sm btn-ghost" data-act="edit">تعديل</button>
      <button class="btn btn-sm btn-danger" data-act="del">حذف</button>
    </div>
  `;
    div.querySelector('[data-act="edit"]').onclick = () => openPartyModal(section, idx, p);
    div.querySelector('[data-act="del"]').onclick = () => deleteParty(section, idx);
    return div;
}

function toggle(el, show) {
    if (!el) return;
    el.classList.toggle('hidden', !show);
}

function req(input, on) {
    if (!input) return;
    if (on) input.setAttribute('required', 'required');
    else input.removeAttribute('required');
}

function clearValue(id) {
    const el = $g(id);
    if (!el) return;
    if (el.tagName === 'INPUT' || el.tagName === 'SELECT') el.value = '';
    if (el.type === 'radio') {
        document.querySelectorAll(`input[name="${el.name}"]`).forEach(r => r.checked = false);
    }
}

function configurePartyModalFor(section) {
    // default (applicants-like)
    let show = {
        name: true, sex: true, job: true, nat: true, res: true, dob: true,
        idType: true, idNum: true, idIssuer: true, idExp: true
    };
    let required = {
        name: true, sex: true, nat: false, res: false, dob: false,
        idType: true, idNum: true
    };

    const nat = $g('pf_nationality');
    const idTypeSel = $g('pf_id_type');

    // ---- reset per-field locks when switching sections ----
    if (idTypeSel) idTypeSel.disabled = false; // will be re-disabled for witnesses
    if (nat) nat.removeAttribute('readonly');

    if (section === 'authenticated') {
        // Only: name, sex, nationality, id type, id number
        show = { name: true, sex: true, job: false, nat: true, res: false, dob: false, idType: true, idNum: true, idIssuer: false, idExp: false };
        required = { name: true, sex: true, nat: true, res: false, dob: false, idType: true, idNum: true };

        // Nationality: editable, default to "سوداني" if empty
        if (nat && (!nat.value || !nat.value.trim())) nat.value = 'سوداني';

    } else if (section === 'witnesses') {
        // Only: name + sex + id number; id type fixed to passport (hidden)
        show = { name: true, sex: true, job: false, nat: true, res: false, dob: false, idType: true, idNum: true, idIssuer: false, idExp: false };
        required = { name: true, sex: true, nat: true, res: false, dob: false, idType: false, idNum: true };

        // Lock ID type to "جواز سفر" and disable (even though hidden, keep state consistent)
        if (idTypeSel) {
            // Select the option robustly
            let matched = false;
            for (const opt of idTypeSel.options) {
                if (opt.value && opt.value.trim() === 'جواز سفر') {
                    opt.selected = true;
                    matched = true;
                    break;
                }
            }
            if (!matched) idTypeSel.value = 'جواز سفر'; // fallback
            idTypeSel.disabled = true;
        }

        // Nationality: force and lock to "سوداني"
        if (nat) {
            nat.value = 'سوداني';
            nat.setAttribute('readonly', 'readonly');
        }

    } else {
        // Applicants: nationality locked to "سوداني"
        if (nat) {
            nat.value = 'سوداني';
            nat.setAttribute('readonly', 'readonly');
        }
    }

    // ---- apply visibility ----
    toggle($g('fld_name'), show.name);
    toggle($g('fld_sex'), show.sex);
    toggle($g('fld_job'), show.job);
    toggle($g('fld_nat'), show.nat);
    toggle($g('fld_res'), show.res);
    toggle($g('fld_dob'), show.dob);
    toggle($g('fld_id_type'), show.idType);
    toggle($g('fld_id_num'), show.idNum);
    toggle($g('fld_id_issuer'), show.idIssuer);
    toggle($g('fld_id_exp'), show.idExp);

    // ---- apply required (only for visible direct inputs) ----
    req($g('pf_name'), required.name);
    // sex radios required handled by validator
    req($g('pf_dob'), required.dob);
    req($g('pf_id_type'), required.idType);
    req($g('pf_id_number'), required.idNum);

    // ---- clear hidden fields to avoid stale values ----
    if (!show.job) clearValue('pf_job');
    if (!show.res) document.querySelectorAll('input[name="pf_res"]').forEach(r => r.checked = false);
    if (!show.dob) clearValue('pf_dob');
    if (!show.idIssuer) clearValue('pf_id_issuer');
    if (!show.idExp) clearValue('pf_id_expiry');
    if (!show.idType) clearValue('pf_id_type'); // NEW: clear if hidden
}



function renderPartyLists() {

    const req = partyState?.requirements || {};

    // Show/Hide sections
    $g('sectionAuth') && ($g('sectionAuth').hidden = !req.needAuthenticated);

    const showWitness = !!(req.needWitnesses || req.needWitnessesOptional);
    $g('sectionWitness') && ($g('sectionWitness').hidden = !showWitness);

    // Update the witnesses note (required vs optional)
    const witNote = document.querySelector('#sectionWitness .muted');
    if (witNote) {
        witNote.textContent = req.needWitnesses
            ? 'مطلوب شاهدان بالضبط.'
            : 'الشاهدات اختياريان (يمكنك إضافة شاهدين إن رغبت).';
    }

    // Render lists
    const hostA = $g('listApplicants'); if (hostA) {
        hostA.innerHTML = '';
        (partyState.applicants || []).forEach((p, i) =>
            hostA.appendChild(personCard(p, i, 'applicants'))
        );
    }

    const hostAuth = $g('listAuth'); if (hostAuth) {
        hostAuth.innerHTML = '';
        (partyState.authenticated || []).forEach((p, i) =>
            hostAuth.appendChild(personCard(p, i, 'authenticated'))
        );
    }

    const hostW = $g('listWitnesses'); if (hostW) {
        hostW.innerHTML = '';
        (partyState.witnesses || []).forEach((p, i) =>
            hostW.appendChild(personCard(p, i, 'witnesses'))
        );
    }

    // Gate the Next button (optional witnesses must NOT block)
    updateNextBtnStep4?.();
}

// ===== Modal open/save =====
function openPartyModal(section, index = -1, existing = null) {
    $g('pf_section').value = section;
    $g('pf_index').value = String(index);
    configurePartyModalFor(section);
    const title = section === 'witnesses' ? 'بيانات الشاهد'
        : section === 'authenticated' ? 'بيانات الموكل'
            : 'بيانات المتقدم';
    $g('partyModalTitle').textContent = title + (index >= 0 ? ' (تعديل)' : ' (إضافة)');

    const p = existing || {};

    safeSetValue('pf_name', p.name || '');

    if (document.querySelector('input[name="pf_sex"]')) {
        setSexValue(p.sex || '');
    } else {
        safeSetValue('pf_sex', p.sex || '');
    }

    safeSetValue('pf_job', p.job || '');
    safeSetValue('pf_dob', p.dob || '');

    // Residence radios/select
    if (document.querySelector('input[name="pf_res"]')) {
        setResidenceValue(p.residenceStatus || '');
    } else {
        safeSetValue('pf_res', p.residenceStatus || '');
    }

    // IDs
    const id0 = (p.ids && p.ids[0]) || {};
    safeSetValue('pf_id_type', id0.type || 'جواز سفر');
    safeSetValue('pf_id_number', id0.number || '');
    safeSetValue('pf_id_issuer', id0.issuer || '');
    safeSetValue('pf_id_expiry', id0.expiry || '');

    // ---- Nationality behavior ----
    const nat = $g('pf_nationality');
    if (nat) {
        if (section === 'applicants') {
            nat.value = 'سوداني';
            nat.setAttribute('readonly', 'readonly');   // lock it
        } else if (section === 'authenticated') {
            nat.removeAttribute('readonly');            // editable
            nat.value = (p.nationality && p.nationality.trim())
                ? p.nationality.trim()
                : 'سوداني';                               // default if empty
        } else {
            // witnesses: not needed; make it readonly blank (or hide via CSS)
            nat.value = 'سوداني';
            nat.setAttribute('readonly', 'readonly');
        }
    }

    // show modal
    $g('partyModal').hidden = false;
}

function getRadioVal(name) {
    const el = document.querySelector(`input[name="${name}"]:checked`);
    return el ? el.value : "";
}
function setRadioVal(name, value) {
    const el = document.querySelector(`input[name="${name}"][value="${value}"]`);
    if (el) el.checked = true;
}

function closePartyModal() { $g('partyModal').hidden = true; }

$g('partyModalClose')?.addEventListener('click', closePartyModal);
$g('partyModal')?.addEventListener('click', (e) => { if (e.target === $g('partyModal')) closePartyModal(); });

$g('partyModalSave')?.addEventListener('click', async (e) => {
    e.preventDefault();
    clearFormErrors();

    const section = $g('pf_section').value;
    const index = parseInt($g('pf_index').value || '-1', 10);

    let nationalityVal = ($g('pf_nationality')?.value || '').trim();

    // enforce per section
    if (section === 'applicants') {
        nationalityVal = 'سوداني';                         // always
    } else if (section === 'authenticated') {
        if (!nationalityVal) nationalityVal = 'سوداني';    // default if empty
    } else {
        nationalityVal = '';                                // witnesses
    }

    const person = {
        role: section === 'applicants' ? 'primary' : undefined,
        name: $g('pf_name').value,
        sex: getRadioVal('pf_sex'),
        job: $g('pf_job').value.trim() || undefined,
        nationality: nationalityVal || undefined,
        residenceStatus: (document.querySelector('input[name="pf_res"]')
            ? getResidenceValue()
            : ($g('pf_res')?.value || '')) || undefined,
        dob: $g('pf_dob').value || undefined,
        ids: [{
            type: $g('pf_id_type').value,
            number: ($g('pf_id_number').value || '').trim(),
            issuer: $g('pf_id_issuer').value.trim() || undefined,
            expiry: $g('pf_id_expiry').value || undefined
        }]
    };

    if (person.ids[0].type === 'national_id' || person.ids[0].type === 'رقم وطني') {
        person.ids[0].issuer = undefined; // not needed
    }
    console.log(person.name);
    // validate (this version already defaults auth nationality when empty)
    const v = validatePersonDetailed(section, person, partyState.requirements);
    if (!v.ok) {
        Object.entries(v.errors).forEach(([fid, msg]) => {
            const input = document.getElementById(fid);
            if (input) setFieldError(input, msg);
        });
        alert('تحقق من البيانات:\n- ' + v.list.join('\n- '));
        return;
    }
    // BEFORE calling upsertParty(...)
    const futureParty = withProposedChange(partyState, section, index, person);
    const dup = checkPartyDuplicates(futureParty);
    if (!dup.ok) {
        // Show a concise alert; you can also paint specific fields if you want
        alert('تحقق من البيانات:\n- ' + dup.messages.join('\n- '));
        return;
    }
    let main = get(LS.main);
    let ok = await upsertParty(main, section, index, person);
    if (!ok) {
        alert('تعذر الحفظ'); return;
    }

    closePartyModal();
    initStep4();


});


// ===== Buttons to add new person =====
$g('btnAddApplicant')?.addEventListener('click', () => openPartyModal('applicants'));
$g('btnAddAuth')?.addEventListener('click', () => openPartyModal('authenticated'));
$g('btnAddWitness')?.addEventListener('click', () => {
    // if witnesses required and already 2, block

    if (partyState.requirements.needWitnesses && (partyState.witnesses?.length || 0) >= 2) {
        alert('مطلوب شاهدان بالضبط.'); return;
    }
    openPartyModal('witnesses', index = partyState.witnesses.length);
});

// ===== API calls =====
async function loadPartyState() {
    if (get('role') === 'employee') return;
    const caseId = caseIdLS();
    if (!caseId) return;
    const res = await fetch(apiUrl('./api_case_party_get.php?caseId=' + encodeURIComponent(caseId)));
    if (!res.ok) return;
    const data = await res.json().catch(() => ({}));
    // Merge defaults
    const pj = data?.party || {};
    const req = data?.requirements || {};
    partyState = {
        applicants: pj.applicants || [],
        authenticated: pj.authenticated || [],
        witnesses: pj.witnesses || [],
        contact: pj.contact || { phone: '', email: '' },
        requirements: { needAuthenticated: !!req.needAuthenticated, needWitnesses: !!req.needWitnesses }
    };
}

async function upsertParty(mainGroup, section, index, person) {
    const caseId = caseIdLS();
    console.log('[upsertParty] request', { mainGroup, section, index, person, caseId });
    if (!caseId) return false;

    // choose endpoint
    let url = 'api_case_party_upsert.php';
    if (get('role') === 'employee') url = 'api_office_party_upsert.php';

    const payload = { caseId, section, index, person, mainGroup };

    try {
        const res = await fetch(apiUrl('./' + url), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });

        let data = null;
        try {
            data = await res.json();
        } catch (e) {
            const raw = await res.text().catch(() => '');
            console.warn('[upsertParty] non-JSON response', res.status, raw);
            return false;
        }

        if (!res.ok || !data?.ok) {
            console.warn('[upsertParty] server returned error', {
                status: res.status,
                data,
                url,
                payload,
            });
            return false;
        }

        console.log('[upsertParty] success', data);
        return true;
    } catch (e) {
        console.error('[upsertParty] fetch error:', e, { url, payload });
        return false;
    }
}

async function deleteParty(section, index) {
    if (!confirm('حذف هذا السجل؟')) return;
    let url = 'api_case_party_delete.php';
    if (get('role') === 'employee')
        url = 'api_office_party_delete.php';
    const caseId = caseIdLS(); if (!caseId) return;
    const res = await fetch(apiUrl('./' + url), {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ caseId, section, index })
    });
    const data = await res.json().catch(() => ({}));
    if (data?.ok) {
        await loadPartyState();
        renderPartyLists();
    } else {
        alert(data?.error || 'تعذر الحذف');
    }
}

// ===== Step 4 init & next =====
async function initStep4() {
    // unlock stepper visually
    document.querySelector('.stepper-item[data-step="4"]')?.removeAttribute('disabled');
    if (get('role') === 'employee') {
        const officeId = get(LS.caseId);
        const main = get(LS.main);
        const state = await openOfficeCase(officeId, main, false);  // ← await!




        const flags =
            main === 'توكيل'
                ? { needAuthenticated: true, needWitnesses: true, needWitnessesOptional: false }
                : main === 'إقرار مشفوع باليمين'
                    ? { needAuthenticated: false, needWitnesses: true, needWitnessesOptional: false }
                    : main === 'إقرار'
                        ? { needAuthenticated: false, needWitnesses: false, needWitnessesOptional: true }
                        : { needAuthenticated: false, needWitnesses: false, needWitnessesOptional: false };

        partyState = {
            applicants: state?.applicants ?? [],
            authenticated: state?.authenticated ?? [],
            witnesses: state?.witnesses ?? [],
            contact: state?.contact ?? { phone: '', email: '' },
            requirements: flags
        };


        // return;
    } else {
        await loadPartyState();
    }

    renderPartyLists();

    // wire actions
    $g('saveStep4')?.addEventListener('click', async (e) => { e.preventDefault(); updateNextBtnStep4(); });
    $g('nextBtnStep4')?.addEventListener('click', (e) => {
        e.preventDefault();
        const res = canProceedStep4();
        if (!res.ok) {
            alert(res.reason);
            return;
        }
        // move on
        showStep(5)
    });
}

// ---------- small utilities ----------
const debounce = (fn, ms = 400) => {
    let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
};
function arLang() { return (localStorage.getItem('docLang') || 'ar').toLowerCase().startsWith('ar'); }
function onlyArabicInput(el) {
    el.addEventListener('input', () => {
        // keep Arabic letters, digits, spaces, common punctuation
        el.value = el.value.replace(/[A-Za-z]/g, '');
    });
}
function onlyLatinInput(el) {
    el.addEventListener('input', () => {
        el.value = el.value.replace(/[\u0600-\u06FF]/g, '');
    });
}
function setFieldErrorNode(node, msg) {
    node.classList.add('input-error');
    const old = node.parentElement?.querySelector('.error-text');
    if (old) old.remove();
    const s = document.createElement('div');
    s.className = 'error-text';
    s.textContent = msg;
    node.parentElement?.appendChild(s);
}
function clearFieldErrorNode(node) {
    node.classList.remove('input-error');
    node.parentElement?.querySelector('.error-text')?.remove();
}

// ---------- API calls ----------
async function fetchCaseDetailsMeta(caseId) {
    const res = await fetch(apiUrl(`api_case_details_meta.php?caseId=${encodeURIComponent(caseId)}`));
    if (!res.ok) return { ok: false, model: null, answers: null };
    const j = await res.json();
    return { ok: true, model: j.model || { fields: [] }, answers: j.answers || {} };
}



const saveAnswersDebounced = debounce(saveCaseDetailsPatch, 1000);

async function saveCaseDetailsPatch(patch) {
    const caseId = Number(localStorage.getItem('caseId') || 0);

    if (!caseId) return;
    if (get('role') === 'employee') {
        const payload = {
            caseId,
            fields: patch,
            mainGroup: get(LS.main) || 'توكيل'  // send mainGroup explicitly
        }
        console.log(payload);
        await fetch(apiUrl('api_office_details_upsert.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

    } else {
        await fetch(apiUrl('api_case_details_upsert.php'), {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ caseId, fields: patch })
        });
    }
}

function renderStep5Form(model, answers) {

    const host = document.getElementById('step5Fields');
    if (!host) return;
    host.innerHTML = '';

    const fields = Array.isArray(model.fields) ? model.fields : [];


    fields.forEach(raw => {
        const key = String(raw.key || '').trim();

        // Force-map by key pattern first (highest priority)
        let t = null;
        if (/^itxtdate\d+$/i.test(key)) t = 'date';
        else if (/^itext\d+$/i.test(key)) t = 'text';
        else if (/^icheck\d+$/i.test(key)) t = 'radio';
        else if (/^icombo\d+$/i.test(key)) t = 'select';

        // If still not decided, fall back to API-provided type (case-insensitive)
        if (!t && raw.type) t = String(raw.type).toLowerCase();

        // Final fallback
        if (!t) t = 'text';

        // If this is a combo/check with ≤3 options, coerce to radio per rule
        let options = Array.isArray(raw.options) ? raw.options : [];
        if ((t === 'select' || t === 'radio') && options.length > 0 && options.length <= 3) {
            t = 'radio';
        }

        const desc = { ...raw, key, type: t, options };
        const node = renderStep5Field(desc, answers?.[key]);
        host.appendChild(node);
    });

    updateNextBtnStep5(true);
    step5Fields = fields.length;
    if (step5Fields === 0)
        document.getElementById('fieldsNames').style.display = 'none';
    else
        document.getElementById('fieldsNames').style.display = 'flex';
}

function renderStep5Field(desc, value) {
    // console.log('renderStep5Field', desc, value);
    const wrap = document.createElement('div');
    wrap.className = 'field';
    const id = `fld_${desc.key}`;

    const label = document.createElement('label');
    label.htmlFor = id;
    label.textContent = desc.label || desc.key;

    let control = null;

    if (desc.type === 'date') {
        control = document.createElement('input');
        control.type = 'date';
        control.id = id;
        if (value) control.value = value;   // expect YYYY-MM-DD
        control.addEventListener('change', () => {
            const v = control.value || null;
            const patch = {}; patch[desc.key] = v;
            saveAnswersDebounced(patch);
        });
    }
    else if (desc.type === 'text') {
        control = document.createElement('input');
        control.type = 'text';
        control.id = id;
        control.value = value ?? '';
        if (desc.maxLen) control.maxLength = Number(desc.maxLen);
        if (arLang()) onlyArabicInput(control); else onlyLatinInput(control);
        control.addEventListener('input', () => {
            const v = control.value.trim();
            const patch = {}; patch[desc.key] = v;
            saveAnswersDebounced(patch);
        });
    }
    else if (desc.type === 'radio') {
        const box = document.createElement('div');
        box.id = id;
        (desc.options || []).forEach(opt => {
            const lab = document.createElement('label');
            lab.className = 'choice';
            const inp = document.createElement('input');
            inp.type = 'radio';
            inp.name = id;
            inp.value = opt;
            if (value === opt) inp.checked = true;
            inp.addEventListener('change', () => {
                const patch = {}; patch[desc.key] = opt;
                saveAnswersDebounced(patch);
            });
            const span = document.createElement('span');
            span.className = 'choice-label';
            span.textContent = opt;
            lab.appendChild(inp); lab.appendChild(span);
            box.appendChild(lab);
        });
        control = box;
    }
    else if (desc.type === 'select') {
        const sel = document.createElement('select');
        sel.id = id;

        const blank = document.createElement('option');
        blank.value = '';
        blank.textContent = '—';
        sel.appendChild(blank);

        // 🔹 Special handling for "الدولة"
        let options = desc.options || [];
        if ((desc.label && desc.label.includes('الدولة')) || (desc.key && desc.key.includes('الدولة'))) {
            const lang = localStorage.getItem('docLang') || 'ar';
            let options = [];

            if (lang === 'ar' || lang === 'العربية') {
                options = window.comboData?.arabCountries || [];
            } else {
                options = window.comboData?.foreignCountries || [];
            }

            options.forEach(label => {
                if (!label) return; // skip null/empty

                const o = document.createElement('option');
                o.value = label;
                o.textContent = label;
                if (value === label) o.selected = true;

                sel.appendChild(o);
            });

        }


        options.forEach(opt => {
            const o = document.createElement('option');
            o.value = opt;
            o.textContent = opt;
            if (value === opt) o.selected = true;
            sel.appendChild(o);
        });

        sel.addEventListener('change', () => {
            const patch = {};
            patch[desc.key] = sel.value || null;
            saveAnswersDebounced(patch);
        });

        control = sel;
    }

    else {
        // fallback
        control = document.createElement('input');
        control.type = 'text';
        control.id = id;
        control.value = value ?? '';
        control.addEventListener('input', () => {
            const patch = {}; patch[desc.key] = control.value.trim();
            saveAnswersDebounced(patch);
        });
    }

    wrap.appendChild(label);
    wrap.appendChild(control);

    if (desc.maxLen && desc.type === 'text') {
        const small = document.createElement('div');
        small.className = 'hint';
        wrap.appendChild(small);
    }

    return wrap;
}


function updateNextBtnStep5(can = true, reason = '') {
    const btn = document.getElementById('nextBtnStep5');
    if (!btn) return;
    btn.disabled = !can;
    btn.classList.toggle('is-disabled', !can);

    let hint = document.getElementById('nextStep5Hint');
    if (!hint) {
        hint = document.createElement('div');
        hint.id = 'nextStep5Hint';
        hint.className = 'muted';
        btn.parentElement?.appendChild(hint);
    }
    hint.textContent = can ? '' : reason;
}

function pickDynamicAnswers(row) {
    // source can be row.answers.fields (preferred) or any plain object
    const src = (row && row.answers && row.answers.fields) ? row.answers.fields
        : (row && row.answers) ? row.answers
            : (row || {});

    // collect matching keys (case-insensitive)
    const dyn = {};
    for (const [k, v] of Object.entries(src)) {
        if (/^(itext\d+|itxtDate\d+|icombo\d+|icheck\d+)$/i.test(k)) {
            dyn[k] = (v === undefined) ? null : v;
        }
    }

    // stable ordering (by family, then numeric suffix)
    const order = { itext: 0, itxtDate: 1, icombo: 2, icheck: 3 };
    const sorted = Object.keys(dyn).sort((a, b) => {
        const ma = a.match(/^(itext|itxtDate|icombo|icheck)(\d+)$/i);
        const mb = b.match(/^(itext|itxtDate|icombo|icheck)(\d+)$/i);
        const fa = ma ? order[ma[1]] : 99, fb = mb ? order[mb[1]] : 99;
        if (fa !== fb) return fa - fb;
        const na = ma ? parseInt(ma[2], 10) : 0;
        const nb = mb ? parseInt(mb[2], 10) : 0;
        return na - nb;
    });

    const fields = {};
    for (const k of sorted) fields[k] = dyn[k];

    return fields;
}


async function initStep5() {
    const caseId = Number(localStorage.getItem('caseId') || 0);

    if (!caseId) {
        alert('لا يوجد رقم معاملة. الرجاء اختيار النوع أولاً.');
        // showStep(2);
        return;
    }
    let meta = [];
    document.querySelector('.stepper-item[data-step="4"]')?.removeAttribute('disabled');
    if (get('role') === 'employee') {
        const officeId = get(LS.caseId);
        const mainGroup = get(LS.main);

        const state = await openOfficeCase(officeId, mainGroup, false);  // ← await!


        // ✅ correct usage
        const modelId = await resolveModelIdFromAltPair(true);

        if (!modelId) { toast('لم يتم العثور على النموذج'); return; }


        const res = await fetch(apiUrl(`api_case_model_fields.php?modelId=${encodeURIComponent(modelId)}`));
        const { ok, model } = await res.json();
        meta = {
            model: model,
            answers: pickDynamicAnswers(state)
        }
        document.getElementById('nextBtnStep5').style.display = 'none';
        // return;
    } else {
        meta = await fetchCaseDetailsMeta(caseId);
    }


    if (!meta || !meta.model) {
        document.getElementById('step5Fields').innerHTML = '<div class="empty">تعذر تحميل نموذج التفاصيل.</div>';
        updateNextBtnStep5(false, 'تعذر تحميل نموذج التفاصيل.');
        return;
    }


    renderStep5Form(meta.model, meta.answers || {});
    updateNextBtnStep5(true, '');

    // 👉 attach employee companion (does NOT override your initStep5)
    if (window.attachEmployeeStep5) window.attachEmployeeStep5();

    const next = document.getElementById('nextBtnStep5');
    if (next) {
        next.type = 'button';
        next.disabled = false;
        next.classList.remove('is-disabled');
        next.replaceWith(next.cloneNode(true));
        const next2 = document.getElementById('nextBtnStep5');
        next2.addEventListener('click', (e) => { e.preventDefault(); showStep(6); });
    }
    document.querySelector('.stepper-item[data-step="6"]')?.removeAttribute('disabled');

}



function canProceedStep5() { return { ok: true, reason: '' }; }


function buildCaseUID(officeId, caseId, createdAtIso = null) {
    const oid = String(officeId || '').padStart(3, '0');
    const y = (createdAtIso ? new Date(createdAtIso) : new Date()).getFullYear() % 100;
    const cid = String(caseId || 0).padStart(7, '0');
    return `${oid}-${String(y).padStart(2, '0')}-${cid}`;
}

function renderBarcode(svgEl, value) {
    try {
        if (window.JsBarcode && svgEl) {
            JsBarcode(svgEl, value, { format: 'code128', displayValue: false, height: 50, margin: 0 });
        }
    } catch (e) { /* ignore */ }
}

async function fetchParty(caseId) {
    const r = await fetch(apiUrl(`api_case_party_get.php?caseId=${encodeURIComponent(caseId)}`));
    if (!r.ok) return { party: {}, requirements: {} };
    const j = await r.json(); return { party: j.party || {}, requirements: j.requirements || {} };
}

async function fetchDetails(caseId) {
    const r = await fetch(apiUrl(`api_case_details_meta.php?caseId=${encodeURIComponent(caseId)}`));
    if (!r.ok) return { model: { fields: [] }, answers: {} };
    const j = await r.json(); return { model: j.model || { fields: [] }, answers: j.answers || {} };
}

async function fetchCaseFiles(caseId) {
    // If you already have api_casefile_list.php, use it:
    console.log('fetchCaseFiles');
    let url = apiUrl(`api_casefile_list.php?caseId=${encodeURIComponent(caseId)}`);
    if (get('role') === 'employee') {
        url = apiUrl(`api_office_casefile_list.php?caseId=${encodeURIComponent(caseId)}`);
    }
    console.log(url);
    const r = await fetch(url);
    if (!r.ok) return [];
    const j = await r.json();
    return (j?.ok && Array.isArray(j.items)) ? j.items : [];
}

// ---------- Renderers ----------
function renderPartyReview(hostId, title, people) {
    const host = document.getElementById(hostId);
    if (!host) return;
    host.innerHTML = '';
    if (!people || !people.length) { host.parentElement.hidden = true; return; }
    host.parentElement.hidden = false;

    people.forEach((p, idx) => {
        const id = (p.ids && p.ids[0]) || {};
        const div = document.createElement('div');
        div.className = 'item';
        const line = [
            p.name ? `الاسم: ${p.name}` : '',
            p.sex ? `النوع: ${p.sex === 'M' ? 'ذكر' : 'أنثى'}` : '',
            p.nationality ? `الجنسية: ${p.nationality}` : '',
            id.type ? `الهوية: ${id.type}` : '',
            id.number ? `(${id.number})` : ''
        ].filter(Boolean).join(' — ');
        div.textContent = line || `—`;
        host.appendChild(div);
    });
}

function renderAnswersReview(hostId, model, answers) {
    const host = document.getElementById(hostId);
    if (!host) return;
    host.innerHTML = '';

    const map = Array.isArray(model.fields) ? model.fields : [];
    map.forEach(f => {
        const k = f.key, lbl = f.label || k;
        let v = answers?.[k];
        if (v == null || v === '') v = '—';
        const box = document.createElement('div');
        box.className = 'ans';
        box.innerHTML = `<div class="k">${lbl}</div><div class="v">${v}</div>`;
        host.appendChild(box);
    });
}

function renderFilesReview(hostId, files) {
    const host = document.getElementById(hostId);
    if (!host) return;
    host.innerHTML = '';
    if (!files || !files.length) {
        host.innerHTML = '<div class="muted">لا توجد مرفقات.</div>';
        return;
    }
    files.forEach(f => {
        const box = document.createElement('div');
        box.className = 'ans';
        // show human label if you keep it; else fallback to OriginalName
        const label = f.Label || f.label || f.OriginalName || 'ملف';
        const sizeKB = f.SizeBytes ? ` — ${(f.SizeBytes / 1024).toFixed(1)}KB` : '';
        box.innerHTML = `<div class="k">${label}</div><div class="v">${sizeKB}</div>`;
        host.appendChild(box);
    });
}

function fmtSex(s) { return s === 'M' ? 'ذكر' : s === 'F' ? 'أنثى' : ''; }

function renderPartyGroup(hostId, people, formatRow, sectionWrapperId = null) {
    const host = document.getElementById(hostId);
    if (!host) return;
    host.innerHTML = '';

    const wrapper = sectionWrapperId ? document.getElementById(sectionWrapperId) : host.closest('.review-section');
    const list = Array.isArray(people) ? people : [];

    if (!list.length) {
        if (wrapper) wrapper.hidden = true;
        return;
    }
    if (wrapper) wrapper.hidden = false;

    list.forEach((p, i) => {
        const div = document.createElement('div');
        div.className = 'item';
        div.textContent = formatRow(p, i) || '—';
        host.appendChild(div);
    });
}

// Row formatters per section
function formatApplicantRow(p) {
    const id = (p.ids && p.ids[0]) || {};
    return [
        p.name ? `الاسم: ${p.name}` : '',
        p.sex ? `النوع: ${fmtSex(p.sex)}` : '',
        p.nationality ? `الجنسية: ${p.nationality}` : '',
        id.type ? `الهوية: ${id.type}` : '',
        id.number ? `(${id.number})` : '',
        id.expiry ? `انتهاء: ${id.expiry}` : ''
    ].filter(Boolean).join(' — ');
}

function formatAuthRow(p) {
    const id = (p.ids && p.ids[0]) || {};
    return [
        p.name ? `الاسم: ${p.name}` : '',
        p.sex ? `النوع: ${fmtSex(p.sex)}` : '',
        p.nationality ? `الجنسية: ${p.nationality}` : '',
        id.type ? `الهوية: ${id.type}` : '',
        id.number ? `(${id.number})` : ''
    ].filter(Boolean).join(' — ');
}

function formatWitnessRow(p) {
    const id = (p.ids && p.ids[0]) || {};
    return [
        p.name ? `الاسم: ${p.name}` : '',
        id.number ? `(${id.number})` : ''
    ].filter(Boolean).join(' — ');
}

// ---------- Step 6 initializer ----------
async function initStep7() {
    console.log('→ initStep6');

    document.querySelector('.stepper-item[data-step="6"]')?.removeAttribute('disabled');

    const caseId = Number(localStorage.getItem('caseId') || 0);
    if (!caseId) {
        alert('لا يوجد رقم معاملة');
        showStep(2);
        return;
    }

    const docLang = localStorage.getItem('docLang') || 'ar';
    const main = localStorage.getItem('selectedMainGroup') || '';
    const altSub = localStorage.getItem('selectedAltSubColName') || '';

    // fetch all required info
    const [{ party }, { model, answers }, files] = await Promise.all([
        fetchParty(caseId),
        fetchDetails(caseId),
        fetchCaseFiles(caseId)
    ]);

    // --- Header info ---
    const uid = buildCaseUID(OFFICE_ID, caseId, null);
    document.getElementById('caseUid')?.replaceChildren(document.createTextNode(uid));
    renderBarcode(document.getElementById('caseBarcode'), uid);

    document.getElementById('caseDate')?.replaceChildren(document.createTextNode(new Date().toLocaleDateString('ar-EG')));
    document.getElementById('caseLang')?.replaceChildren(document.createTextNode(docLang.startsWith('ar') ? 'العربية' : 'الإنجليزية'));
    document.getElementById('caseMainGroup')?.replaceChildren(document.createTextNode(main || '—'));
    document.getElementById('caseAltSub')?.replaceChildren(document.createTextNode(altSub || '—'));

    // --- Party renderers ---
    function renderPartySection(sectionId, list, lineFn) {
        const wrap = document.getElementById(sectionId);
        if (!wrap) return;
        wrap.innerHTML = '';
        const people = Array.isArray(list) ? list : [];
        wrap.parentElement.hidden = people.length === 0;
        people.forEach(p => {
            const d = document.createElement('div');
            d.className = 'item';
            d.textContent = lineFn(p);
            wrap.appendChild(d);
        });
    }

    const firstId = p => (p.ids && p.ids[0]) || {};

    renderPartySection('reviewApplicants', party.applicants, (p) => {
        const id = firstId(p);
        return [
            p.name && `الاسم: ${p.name}`,
            p.sex && `النوع: ${p.sex === 'M' ? 'ذكر' : 'أنثى'}`,
            p.nationality && `الجنسية: ${p.nationality}`,
            id.type && `الهوية: ${id.type}`,
            id.number && `(${id.number})`
        ].filter(Boolean).join(' — ') || '—';
    });

    renderPartySection('reviewAuth', party.authenticated, (p) => {
        const id = firstId(p);
        return [
            p.name && `الاسم: ${p.name}`,
            id.type && `الهوية: ${id.type}`,
            id.number && `(${id.number})`
        ].filter(Boolean).join(' — ') || '—';
    });

    renderPartySection('reviewWitnesses', party.witnesses, (p) => {
        const id = firstId(p);
        return [
            p.name && `الاسم: ${p.name}`,
            p.sex && `النوع: ${p.sex === 'M' ? 'ذكر' : 'أنثى'}`,
            id.number && `(${id.number})`
        ].filter(Boolean).join(' — ') || '—';
    });


    // --- Answers ---
    const revAns = document.getElementById('reviewAnswers');
    if (revAns) {
        revAns.innerHTML = '';
        (model.fields || []).forEach(f => {
            const box = document.createElement('div');
            box.className = 'ans';
            const v = (answers || {})[f.key];
            box.innerHTML = `
        <div class="k">${f.label || f.key}</div>
        <div class="v">${(v == null || v === '') ? '—' : v}</div>
      `;
            revAns.appendChild(box);
        });
    }

    // --- Files ---
    const revFiles = document.getElementById('reviewFiles');
    if (revFiles) {
        revFiles.innerHTML = '';
        if (!files.length) {
            revFiles.innerHTML = '<div class="muted">لا توجد مرفقات.</div>';
        } else {
            files.forEach(f => {
                const box = document.createElement('div');
                box.className = 'ans';
                const label = f.Label || f.OriginalName || 'ملف';
                const sizeKB = f.SizeBytes ? ` — ${(f.SizeBytes / 1024).toFixed(1)}KB` : '';
                box.innerHTML = `<div class="k">${label}</div><div class="v">${sizeKB}</div>`;
                revFiles.appendChild(box);
            });
        }
    }

    // --- Buttons ---
    document.getElementById('btnPrint')?.addEventListener('click', () => window.print());
    document.getElementById('nextBtnStep7')?.addEventListener('click', (e) => {
        e.preventDefault();
        submitCase();
    });
}


// ---- LSK additions (safe) ----
window.LSK ??= {};
LSK.apptDate ??= 'LS.apptDate';
LSK.apptTime ??= 'LS.apptTime';

// ---- Step 7 helpers ----
function todayISO() {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    return d.toISOString().slice(0, 10);
}
function addDaysISO(days) {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}
function validDateWithin6(dateStr) {
    if (!dateStr) return false;
    const d = new Date(dateStr + 'T00:00:00');
    const min = new Date(todayISO() + 'T00:00:00');
    const max = new Date(addDaysISO(7) + 'T23:59:59');
    return d >= min && d <= max;
}
function validTimeStr(t) { return /^\d{2}:\d{2}$/.test(t || ''); }

function validateStep6() {
    const ack = document.getElementById('ackTerms').checked;
    const dateStr = document.getElementById('apptDate').value;
    const timeStr = document.getElementById('apptTime').value;

    const dateOk = validDateWithin6(dateStr);
    const timeOk = validTimeStr(timeStr);

    document.getElementById('ackError').hidden = ack;
    document.getElementById('apptError').hidden = (dateOk && timeOk);

    const ok = ack && dateOk && timeOk;
    document.getElementById('submitBtnStep6').disabled = !ok;
    return ok;
}

async function fetchHourAvailability(dateStr) {
    const res = await fetch('./api_apt_hours.php?date=' + encodeURIComponent(dateStr));
    if (!res.ok) return [];
    const data = await res.json();
    return Array.isArray(data.hours) ? data.hours : [];
}

function renderHours(hours, selectedHour) {
    const grid = document.getElementById('hourGrid');
    grid.innerHTML = '';
    hours.forEach(h => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'hour-btn' + (h.disabled ? ' is-disabled' : '') + (selectedHour === h.time ? ' is-selected' : '');
        btn.textContent = h.time;
        const cap = document.createElement('span');
        cap.className = 'hour-cap';
        // cap.textContent = h.disabled ? 'ممتلئ' : `متاح: ${h.remaining}`;
        cap.textContent = h.disabled ? 'ممتلئ' : `متاح`;
        btn.appendChild(cap);

        if (!h.disabled) {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.hour-btn.is-selected').forEach(el => el.classList.remove('is-selected'));
                btn.classList.add('is-selected');
                localStorage.setItem('apptHour', h.time);
                validateStep6();
            });
        }
        grid.appendChild(btn);
    });
}

function validateStep6() {
    const dateEl = document.getElementById('apptDate');
    const ack = document.getElementById('ackTerms');
    const submit = document.getElementById('submitBtn');
    const dtErr = document.getElementById('dtErr');
    const hrErr = document.getElementById('hrErr');
    const ackErr = document.getElementById('ackErr');

    const dateOk = !!dateEl.value;
    const hourSel = localStorage.getItem('apptHour') || '';
    const hourOk = !!hourSel;
    const ackOk = !!ack.checked;

    submit.disabled = !(dateOk && hourOk && ackOk);

    dtErr.hidden = dateOk;
    hrErr.hidden = hourOk;
    ackErr.hidden = ackOk;
}

async function onDateChange() {
    const dateEl = document.getElementById('apptDate');
    const date = dateEl.value;
    localStorage.setItem('apptDate', date);
    localStorage.removeItem('apptHour'); // reset hour selection on date change
    if (!date) { renderHours([], null); validateStep7(); return; }
    const hours = await fetchHourAvailability(date);
    renderHours(hours, null);
    validateStep6();
}

function initStep6() {
    const dateEl = document.getElementById('apptDate');
    const submit = document.getElementById('submitBtn');
    const ack = document.getElementById('ackTerms');

    // min date = today
    const now = new Date();
    const pad = n => String(n).padStart(2, '0');
    const minDate = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
    dateEl.min = minDate;

    // restore previous selection if any
    const savedDate = localStorage.getItem('apptDate') || '';
    const savedHour = localStorage.getItem('apptHour') || '';
    if (savedDate) {
        dateEl.value = savedDate;
        fetchHourAvailability(savedDate).then(hours => {
            renderHours(hours, savedHour);
            validateStep6();
        });
    } else {
        renderHours([], null);
    }

    dateEl.addEventListener('change', onDateChange);
    ack.addEventListener('change', validateStep6);

    submit.addEventListener('click', (e) => {
        validateStep6();
        if (submit.disabled) {
            e.preventDefault();
            return;
        }

    });

    validateStep6();
    submit.addEventListener('click', async (e) => {
        e.preventDefault();
        // ensure still valid (date, hour, terms)
        const ack = document.getElementById('ackTerms');
        const dateOk = !!document.getElementById('apptDate').value;
        const hourOk = !!localStorage.getItem('apptHour');
        if (!dateOk || !hourOk || !ack.checked) return;
        showStep(7);
    });
}

async function submitCase() {
    const caseId = Number(localStorage.getItem('caseId') || 0);
    const date = localStorage.getItem('apptDate') || '';
    const hour = localStorage.getItem('apptHour') || '';
    if (!caseId || !date || !hour) return;

    const res = await fetch('./api_case_submit.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ caseId, date, hour })
    });
    const data = await res.json();
    if (data.ok) {
        localStorage.setItem('caseStatus', 'submitted');
        alert('تم حجز الموعد وإرسال الطلب.');
        resetAfterSubmit();
        showStep(0);
    } else {
        alert(data.error || 'تعذر الإرسال');
    }
}




// ---- Reset after submit ----

// All localStorage keys tied to the current case (keep userId; optionally keep docLang)
const CASE_KEYS = [
    'caseId', 'externalRef', 'caseStatus',
    'selectedMainGroup', 'selectedAltColName', 'selectedAltSubColName', 'selectedTemplateId',
    'apptDate', 'apptHour'
    // If you also want to reset language, uncomment:
    // ,'docLang'
];

function clearCaseLocalState() {
    CASE_KEYS.forEach(k => localStorage.removeItem(k));
}

function resetFormsAndViews() {
    // Close any open modals
    ['altcolModal', 'subcolModal', 'partyModal'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.hidden = true;
    });

    // Step 2: groups
    const groupGrid = document.getElementById('groupGrid');
    if (groupGrid) groupGrid.innerHTML = '';
    const groupBadge = document.getElementById('groupCountBadge');
    if (groupBadge) groupBadge.textContent = '';
    const groupEmpty = document.getElementById('groupEmpty');
    if (groupEmpty) groupEmpty.hidden = true;

    // Deal explanation
    const dealInfo = document.getElementById('dealInfo');
    if (dealInfo) dealInfo.hidden = true;
    const dealInfoText = document.getElementById('dealInfoText');
    if (dealInfoText) dealInfoText.textContent = '';

    // Step 3: requirements
    const reqList = document.getElementById('reqList');
    if (reqList) reqList.innerHTML = '';
    const reqEmpty = document.getElementById('reqEmpty');
    if (reqEmpty) reqEmpty.hidden = true;

    // Step 5: dynamic fields
    document.getElementById('step5Form')?.reset();
    const s5 = document.getElementById('step5Fields');
    if (s5) s5.innerHTML = '';

    // Step 7: appointment + terms
    document.getElementById('step7Form')?.reset();
    const hourGrid = document.getElementById('hourGrid');
    if (hourGrid) hourGrid.innerHTML = '';
    const dtErr = document.getElementById('dtErr'); if (dtErr) dtErr.hidden = true;
    const hrErr = document.getElementById('hrErr'); if (hrErr) hrErr.hidden = true;
    const ackErr = document.getElementById('ackErr'); if (ackErr) ackErr.hidden = true;
}

function resetStepperToStep1() {
    // Hide all step sections
    document.querySelectorAll('section[id^="step"]').forEach(sec => sec.hidden = true);
    // Show Step 1
    const s1 = document.getElementById('step1');
    if (s1) s1.hidden = false;

    // Reset stepper buttons
    document.querySelectorAll('.stepper-item').forEach(btn => {
        const step = Number(btn.dataset.step);
        const isFirst = step === 1;
        btn.classList.toggle('is-current', isFirst);
        if (isFirst) btn.removeAttribute('disabled');
        else btn.setAttribute('disabled', '');
    });

    // Scroll to top of the page
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetAfterSubmit() {
    clearCaseLocalState();
    resetFormsAndViews();
    resetStepperToStep1();
    // If your Step 1 needs initialization code, call it here:
    // if (typeof initStep1 === 'function') initStep1();
}
