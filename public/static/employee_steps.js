/* static/employee_steps.js — Step 5 (base + employee companion) WITHOUT lexicon
   - Checkbox in #step5 toggles visibility of #step5-emp
   - Builds text from tokens (tN/cN/mN/nN) using values from #step5Form
   - Morphology via Tablechar only (canonicalize → realize)
   - Does NOT call api_lexicon.php
*/
// Map DB party → objects our builders understand
function normalizeCaseParty(party) {
    const asPeople = (arr) => (Array.isArray(arr) ? arr : []).map(p => {
        // Choose first ID doc if available
        const id = (Array.isArray(p.ids) && p.ids[0]) ? p.ids[0] : {};
        return {
            fullName: p.name || p.fullName || '—',
            gender: (p.sex || p.gender || '').toLowerCase(),            // 'm'/'f'/'ذكر'/'أنثى' handled downstream
            nationality: p.nationality || '',
            passportType: id.type || 'جواز سفر',
            documentType: id.type || 'جواز سفر',
            passportNo: id.number || '',
            issuePlace: id.issuer || id.issuePlace || '',
            residesInKSA: (p.residenceStatus || '').includes('مقيم'),
            title: (p.gender === 'f' || p.sex === 'F') ? 'السيدة/' : 'السيد/'
        };
    });

    return {
        applicants: asPeople(party.applicants),
        authenticated: asPeople(party.authenticated),
        witnesses: asPeople(party.witnesses),
        contact: party.contact || { phone: '', email: '' }
    };
}

// Map (count, gender) -> Tablechar slot 0..5
function slotFromCountGender(count, gender) {
    const g = String(gender || 'mixed').toLowerCase();
    if (count <= 1) return g === 'f' ? 1 : 0;   // sg
    if (count === 2) return g === 'f' ? 3 : 2;  // dual
    return g === 'f' ? 4 : 5;                   // plural
}

// Build ctx from parties if needed elsewhere (kept from previous version)
function slotToGender(slot, count) {
    if (count <= 1) return slot === 1 ? 'f' : 'm';
    if (count === 2) return (slot === 3 ? 'f' : (slot === 2 ? 'm' : 'mixed'));
    return slot === 4 ? 'f' : 'mixed';
}
function computeCtxFromParties(applicants, authenticated) {
    const ac = applicants.length, as = deriveMorphSlot(applicants);
    const hc = authenticated.length, hs = deriveMorphSlot(authenticated);
    return {
        app: { count: ac || 1, gender: slotToGender(as, ac || 1) },
        auth: { count: hc || 1, gender: slotToGender(hs, hc || 1) }
    };
}

// Final overall cleanup: canonicalize -> realize(2) -> realize(1) + seam/space fixes
// ---- helpers (add once) ----

// Collapse variants like "أوكلت***" -> "أوكل***" so canonicalization can match
function tightenRootMarkers(text, rows) {
    if (!text || !rows?.length) return text;
    let out = text;
    for (const r of rows) {
        const root = r?.root || '';
        const m = /^([\p{L}]+)([^\p{L}]+)$/u.exec(root);
        if (!m) continue;
        const base = m[1];
        const mark = m[2].replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const re = new RegExp(`(^|[^\\p{L}])(و?)(${base}[\\p{L}]*${mark})(?=$|[^\\p{L}])`, 'gu');
        out = out.replace(re, (full, pre, waw) => `${pre}${waw}${root}`);
    }
    return out;
}

// Remove adjacent duplicate n-grams of up to N words, iteratively until stable
// Remove adjacent duplicate n-grams of up to N words, preserving leading space/boundary
function dedupeNgrams(s, maxN = 6, iterations = 3) {
    for (let pass = 0; pass < iterations; pass++) {
        let changed = false;
        for (let n = maxN; n >= 1; n--) {
            // (^|\s)  -> capture leading boundary so we can keep it
            // (seq)   -> n tokens (no major punctuation)
            // (\s+)   -> the gap between first and second occurrence
            // \2      -> the same seq again
            const re = new RegExp(
                `(^|\\s)` +                                   // 1: leading boundary
                `((?:[^\\s،,.!?؛:]+\\s+){${n - 1}}[^\\s،,.!?؛:]+)` + // 2: the n-gram
                `(\\s+)\\2(?=\\s|[،,.!?؛:]|$)`,               // 3: gap + repeated n-gram
                'gu'
            );
            const before = s;
            s = s.replace(re, (_m, lead, seq/*, gap*/) => `${lead}${seq}`);
            if (s !== before) changed = true;
        }
        if (!changed) break; // stable
    }
    return s;
}


// Normalize punctuation clusters & spacing (Arabic-aware)
function normalizePunctuation(s) {
    if (!s) return s;

    // Unify ASCII comma to Arabic comma
    s = s.replace(/\s*,\s*/g, '، ');

    // Remove spaces before punctuation
    s = s.replace(/\s+([،,:؛.!؟])/gu, '$1');

    // Collapse runs of the same punctuation (،، -> ،)
    s = s.replace(/([،,:؛.!؟])\s*\1+/gu, '$1');

    // For any cluster of mixed punctuation (e.g., "، :"), keep the last char in the cluster
    s = s.replace(/([،,:؛.!؟]+)(?=\s*[^\s،,:؛.!؟])/gu, (m) => m.slice(-1));

    // One space after punctuation if followed by a letter/number
    s = s.replace(/([،,:؛.!؟])(?!\s|$)/gu, '$1 ');

    // Clean slashes spacing
    s = s.replace(/\s*\/\s*/g, '/');

    // Collapse leftover multiple spaces
    s = s.replace(/\s{2,}/g, ' ');

    return s;
}

// Map ctx counts -> slot index (0..5)
function slotFromCountGender(count, gender) {
    const g = String(gender || 'mixed').toLowerCase();
    if (count <= 1) return g === 'f' ? 1 : 0;
    if (count === 2) return g === 'f' ? 3 : 2;
    return g === 'f' ? 4 : 5;
}

function finalCorrections(text, rows, ctxAll) {
    if (!text) return '';

    let s = text;


    // Collapse repeated "في" like "في، في", "في  في", "في،  في، في" -> "في"
    s = s.replace(/(^|[^\p{L}])في(?:\s*[،,]?\s*في)+(?![\p{L}])/gu, '$1في');


    // 1) Normalize root markers, then canonicalize → realize(2) → realize(1)
    s = tightenRootMarkers(s, rows);

    // 2) Generic duplicate sequence removal (handles "في في", "عني ويقوم مقامي عني ويقوم مقامي", etc.)
    s = dedupeNgrams(s, 6, 4);

    // 3) Remove any leftover placeholders (defensive)
    s = s.replace(/\b(?:app|auth)\d\b/gi, '');

    // 4) Normalize punctuation + spacing (Arabic-aware)
    s = normalizePunctuation(s);

    // 5) Collapse spaces again (in case of placeholder removal gaps)
    s = s.replace(/\s{2,}/g, ' ').trim();

    // 6) Ensure exactly one terminal full stop
    s = s.replace(/[.؟!،]+$/u, '');
    s += '.';

    return s;
}



// --- gender/title helpers ---
function normalizeGenderLabel(v) {
    const s = String(v ?? '').trim().toLowerCase();
    if (s === 'm' || s === 'male' || s === 'ذكر') return 'm';
    if (['f', 'female', 'انثى', 'أنثى', 'مؤنث'].includes(s)) return 'f';
    return '';
}
function titleFor(person) {
    // prefer explicit title if provided
    const t = Array.isArray(person) ? null : person?.title;
    if (t) return t;
    const g = normalizeGenderLabel(Array.isArray(person) ? person[3] : (person?.gender ?? person?.sex));
    return g === 'f' ? 'السيدة/' : 'السيد/';
}

function holderFor(person) {

    const g = normalizeGenderLabel(Array.isArray(person) ? person[3] : (person?.gender ?? person?.sex));
    return (g === 'f') ? 'حاملة' : 'حامل';
}

// Normalize cases like "أوكلت***" -> "أوكل***" so realize pass can match
function tightenRootMarkers(text, rows) {
    if (!text || !rows?.length) return text;
    let out = text;
    for (const r of rows) {
        if (!r?.root) continue;
        const m = /^([\p{L}]+)([^ \p{L}]+)$/u.exec(r.root); // base + marker (***, ###, $$$, …)
        if (!m) continue;
        const base = m[1];
        const mark = m[2].replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        // allow extra letters between base and marker: "base<letters>mark"
        const re = new RegExp(`(^|[^\\p{L}])(${base})[\\p{L}]*(${mark})(?=$|[^\\p{L}])`, 'gu');
        out = out.replace(re, (full, pre, b, mk) => `${pre}${b}${mk}`);
    }
    return out;
}


function normalizeCaseDetails(details) {
    const model = details?.model || {};
    // Map 'ar'|'en' to display label we used downstream
    const langLabel = model.langLabel || ((details?.lang === 'en' || model?.Lang === 'en') ? 'الانجليزية' : 'العربية');
    return {
        mainGroup: model.mainGroup || '',
        altColName: model.altColName || '',
        altSubColName: model.altSubColName || '',
        langLabel
    };
}

async function openOfficeCase1(officeId, go_to_one = true) {
    if (!officeId) return;
    const res = await fetch('api_office_case_detail.php?id=' + encodeURIComponent(officeId));
    if (!res.ok) { alert('تعذر فتح بيانات المكتب'); return; }
    const data = await res.json();

    // You can reuse the exact same body from openCase(data.case.caseId) after the fetch:
    appState = {
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
            sex: a?.sex ?? null,                 // 'M' | 'F' | null
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
            sex: p?.sex ?? null,                 // 'M' | 'F'
            nationality: p?.nationality ?? '',
            ids: (Array.isArray(p?.ids) && p.ids.length)
                ? [{ type: p.ids[0]?.type ?? 'جواز سفر', number: p.ids[0]?.number ?? '' }]
                : []
        })),

        witnesses: (data.party?.witnesses || []).map(w => ({
            name: w?.name ?? '',
            sex: w?.sex ?? null, // 'M' | 'F' | null
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
    set(LS.main, data.details.model.mainGroup || '');
    set(LS.altCol, data.details.model.altColName ?? '');
    set(LS.altSub, data.details.model.altSubColName ?? '');
    set(LS.uploadsDone, '0');
    allowLeaveStep0('open-case');
    if (go_to_one)
        showStep(1);
    return appState;
}

// Fetch from backend and cache on window for Step 5
async function loadCaseContextFromServer(caseId) {
    let data = [];
    let party = [];
    let meta = [];
    let docLang = 'ar';
    if (localStorage.getItem('role') === 'employee') {
        
        openOfficeCase1(caseId, false);
        data.party = {
            applicants: window.universalappState.applicants,
            authenticated: window.universalappState.authenticated,
            witnesses: window.universalappState.witnesses,
            contact: { phone: '', email: '' }
        }
        

        data.details = {
            mainGroup: window.universalappState.selected.mainGroup || '',
            altColName: window.universalappState.selected.altColName || '',
            altSubColName: window.universalappState.selected.altSubColName || '',
            langLabel: window.universalappState.answers.legalStatusText
        }
        docLang = window.universalappState.lang;
        data.ok = true;
        
    } else {
        const res = await fetch(apiResolve(`api_case_get.php?caseId=${encodeURIComponent(caseId)}`), { credentials: 'same-origin' });
        if (!res.ok) return null;
        data = await res.json().catch(() => null);
        docLang = (data.lang || '').toLowerCase() === 'en' ? 'en' : 'ar';
    }
    
    if (!data?.ok) return null;

    party = normalizeCaseParty(data.party || {});
    meta = normalizeCaseDetails(data.details || {});
    // console.log(party,data.details);
    window.currentCaseParty = party;        
    window.currentCaseMeta = data.details;
    // Make available to the builder    

    // Also refresh LS so later calls see proper context
    localStorage.setItem('docLang', docLang);
    localStorage.setItem('mainGroup', meta.mainGroup || '');
    localStorage.setItem('altCol', meta.altColName || '');
    localStorage.setItem('altSub', meta.altSubColName || '');

    return { party, meta, lang: docLang };
}

// ---- API URL resolver (works whether you have api() or apiUrl() or neither) ----
function apiResolve(path) {
    if (typeof api === 'function') { try { return api(path); } catch (e) { } }
    if (typeof apiUrl === 'function') { try { return apiUrl(path); } catch (e) { } }
    // fallback: relative
    if (/^https?:\/\//.test(path) || path.startsWith('/')) return path;
    return './' + String(path).replace(/^\.?\/*/, '');
}

(() => {
    'use strict';


    // ---------- Helpers ----------
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const getLS = (k, d = null) => { try { const v = localStorage.getItem(k); return v === null ? d : v; } catch { return d; } };
    const setLS = (k, v) => { try { localStorage.setItem(k, String(v)); } catch { } };
    const api = (p) => (typeof window.apiUrl === 'function' ? window.apiUrl(p) : p);

    // ---------- DOM refs ----------
    const secBase = $('#step5');
    const secEmp = $('#step5-emp');
    if (!secBase) return;
    window.DEBUG_STEP5 = true;

    const roleTgl = $('#roleToggle', secBase);
    const roleBadge = $('#roleBadge', secBase);
    const formEl = $('#step5Form', secBase);

    const empTextPanel = $('#employeeTextPanel', secEmp || document);
    const empText = $('#empText', secEmp || document);
    const autoSync = $('#autoSyncEmpText', secEmp || document);
    const btnBuild = $('#btnRebuildEmpText', secEmp || document);
    const btnLoadTM = $('#btnLoadTextModel', secEmp || document);

    const nextBase = $('#nextBtnStep5', secBase);
    const nextEmp = secEmp ? $('#nextBtnStep5', secEmp) || $('#nextBtnStep5Emp', secEmp) : null;

    // ---------- State ----------
    let textModel = '';
    let rightsText = '';
    let legalStatusText = '';
    let tablecharRows = null;  // prepped rows
    let wired = false;
    let currentRole = (() => {
        const r = (getLS('role') || '').toLowerCase();
        return (r === 'employee' || r === 'customer') ? r : 'customer';
    })();

    // ---------- Public hook (call this from YOUR initStep5()) ----------
    window.attachEmployeeStep5 = function attachEmployeeStep5() {

        syncRoleUI();
        applyRoleToSections();
        wireOnce();
        if (localStorage.getItem('role') === 'employee' && autoSync?.checked)
            safeRebuildEmployeeText();
    };

    // ---------- Role UI + companion toggle ----------
    function syncRoleUI() {
        if (roleBadge) {
            roleBadge.textContent = `الدور: ${currentRole === 'employee' ? 'موظف' : 'زبون'}`;
            roleBadge.style.background = currentRole === 'employee' ? '#d1fae5' : '#e5e7eb';
        }
        if (roleTgl) roleTgl.checked = (currentRole === 'employee');
    }
    function applyRoleToSections() {
        if (!secEmp) return;
        if (currentRole === 'employee') {
            secEmp.hidden = false;
            secEmp.style.removeProperty('display');
            secEmp.classList.remove('hidden', 'is-hidden');
            if (empTextPanel) empTextPanel.style.display = '';
        } else {
            secEmp.hidden = true;
        }
    }

    // ---------- Wiring ----------
    function wireOnce() {
        if (wired) return;
        wired = true;

        roleTgl?.addEventListener('change', () => {
            currentRole = roleTgl.checked ? 'employee' : 'customer';
            setLS('role', currentRole);
            syncRoleUI();
            applyRoleToSections();
            if (currentRole === 'employee' && autoSync?.checked) safeRebuildEmployeeText();
        });

        btnBuild?.addEventListener('click', safeRebuildEmployeeText);
        // Replace these two listeners with the block below:

        btnBuild?.addEventListener('click', () => {
            // Always rebuild on button press (ignores autoSync)
            safeRebuildEmployeeText();
        });

        btnLoadTM?.addEventListener('click', async () => {
            // Force-refresh the template from the server
            const { textModel: tm, rights } = await loadTextModel(true);

            if (!tm) {
                console.warn('[Step5] No TextModel received from API.');
                return;
            }

            // OPTIONAL: if you have a place to show rights, update it here
            // const rightsBox = document.getElementById('empRights');
            // if (rightsBox) rightsBox.textContent = rights || '';

            // Rebuild the text preview regardless of autoSync toggle
            await safeRebuildEmployeeText();
        });


        // Auto-sync only from base form inputs
        formEl?.addEventListener('input', () => {
            if (currentRole !== 'employee' || !autoSync?.checked) return;
            clearTimeout(wireOnce._deb);
            wireOnce._deb = setTimeout(safeRebuildEmployeeText, 120);
        });

        const onNext = () => {
            if (currentRole === 'employee' && empText) {
                sessionStorage.setItem('emp_final_text', empText.value);
            }
        };
        nextBase?.addEventListener('click', onNext);
        nextEmp?.addEventListener('click', onNext);
    }

    // ---------- Build pipeline ----------
    // Add this helper
    function stampDataTokens(scope) {
        if (!scope) return;
        const all = scope.querySelectorAll('input, select, textarea');

        const mapKeyToToken = (s) => {
            if (!s) return null;
            const str = String(s);
            // Accept exact or embedded: itext1 | itext-1 | answers[itext1]
            const tryOne = (base, letter) => {
                const re = new RegExp(`${base}\\s*(?:[-_]?|\\[)\\s*(\\d+)`, 'i');
                const m = re.exec(str);
                return m ? `${letter}${Number(m[1])}` : null;
            };
            return (
                tryOne('itxtdate', 'n') || // dates first (to avoid matching "itext")
                tryOne('icheck', 'c') ||
                tryOne('icombo', 'm') ||
                tryOne('itext', 't')
            );
        };

        all.forEach(el => {
            if (el.dataset && el.dataset.token) return; // already stamped
            const fromName = mapKeyToToken(el.name);
            const fromId = mapKeyToToken(el.id);
            const tok = fromName || fromId;
            if (tok) el.setAttribute('data-token', tok);
        });
    }

    // Reads "<prefix>Count" and "<prefix>Gender" from localStorage.
    // prefix: "applicants" | "authenticated"
    function getGroupContext(prefix) {
        let count = Number(localStorage.getItem(prefix + 'Count') || '1');
        if (!Number.isFinite(count) || count <= 0) count = 1;

        let gender = (localStorage.getItem(prefix + 'Gender') || '').toLowerCase();
        // normalize / fallback
        if (!['m', 'f', 'mixed'].includes(gender)) {
            gender = count > 1 ? 'mixed' : 'm'; // default singular → m, plural → mixed
        }
        return { count, gender };
    }

    // Returns both contexts
    function computeAllContexts() {
        return {
            app: getGroupContext('applicants'),    // الضمير = 1
            auth: getGroupContext('authenticated'), // الضمير = 2
        };
    }

    // Add this once (utility)
    function normalizeArabicText(str) {
        if (!str) return '';
        // Unify line breaks
        let s = String(str).replace(/\r\n?/g, '\n');

        // Remove kashida (tatweel)
        s = s.replace(/\u0640+/g, '');

        // Trim each line + collapse inner spaces
        s = s.split('\n').map(line =>
            line
                .replace(/[ \t\u00A0]{2,}/g, ' ')   // collapse spaces
                .trim()
        ).join('\n');

        // Prefer Arabic punctuation when surrounded by Arabic letters/digits
        // ASCII , ; ? -> Arabic ، ؛ ؟
        s = s.replace(/(?<=\p{Script=Arabic}|\d)\s*,\s*(?=\p{Script=Arabic}|\d)/gu, '،');
        s = s.replace(/(?<=\p{Script=Arabic}|\d)\s*;\s*(?=\p{Script=Arabic}|\d)/gu, '؛');
        s = s.replace(/(?<=\p{Script=Arabic}|\d)\s*\?\s*(?=\p{Script=Arabic}|\d)/gu, '؟');

        // Spacing around punctuation: no space before, one space after
        s = s
            .replace(/\s+([،؛:؟.])/g, '$1')
            .replace(/([،؛:؟.])(?!\s|\n)/g, '$1 ');

        // Collapse multiple blanks lines
        s = s.replace(/\n{3,}/g, '\n\n');

        // Final trim
        return s.trim();
    }

    // Replace your safeRebuildEmployeeText() with this version
    // --- helper: try to read applicants/authenticated lists for testing ---
    function getPartyLists() {
        // Preferred: a preloaded object on window (e.g., from your case API)
        if (window.currentCaseParty && (Array.isArray(window.currentCaseParty.applicants) || Array.isArray(window.currentCaseParty.authenticated))) {
            return {
                applicants: window.currentCaseParty.applicants || [],
                authenticated: window.currentCaseParty.authenticated || []
            };
        }

        // Fallback: localStorage JSON (let you seed it during tests)
        const keys = ['partyJson', 'PartyJson', 'online.case.party', 'caseParty'];
        for (const k of keys) {
            try {
                const raw = localStorage.getItem(k);
                if (!raw) continue;
                const obj = JSON.parse(raw);
                if (obj && (Array.isArray(obj.applicants) || Array.isArray(obj.authenticated))) {
                    return { applicants: obj.applicants || [], authenticated: obj.authenticated || [] };
                }
            } catch { }
        }

        // Nothing found — return empty arrays (intro builder will be minimal)
        return { applicants: [], authenticated: [] };
    }

    // --- helper: compose the 3-line Wakala intro, neutral line-3 gets morphed alone ---
    function buildWakalaIntroBlock({ applicants, authenticated, chars, altColName, altSubColName, mainGroup, lang, legalStatus }) {
        const rows = normalizeTablecharRows(chars);

        // 1) Applicants line (uses legalStatus)
        const line1 = buildApplicantsIntro(altSubColName, applicants, rows, '', mainGroup, lang, legalStatus);
        let line2 = '';
        let line3 = '';
        if (!isWakala(mainGroup)) {
            console.log('line1', line1);
            return line1;
        }

        line2 = authNamePart1(authenticated, applicants, rows, altColName, mainGroup, lang);

        const appSlot = deriveMorphSlot(applicants);
        const authSlot = deriveMorphSlot(authenticated);
        const p1 = realizeTextForPerson(rows, 'عني', appSlot, '1', { debug: false });
        const p2 = realizeTextForPerson(rows, 'ويقوم', appSlot, '2', { debug: false });
        const p3 = realizeTextForPerson(rows, 'مقامي', appSlot, '1', { debug: false });
        let theWay = '';
        if (authSlot >= 2)
            theWay = 'سواء مجتمعين أو مفترقين'
        line3 = `${p1} ${p2} ${p3} ${theWay} في`;

        line3 = realizeTextForPerson(rows, line3, appSlot, '1', { debug: false });
        const out = `${line1} ${line2} ${line3}`;
        return out;
    }


    // --- drop-in replacement ---
    async function safeRebuildEmployeeText() {
        try {
            const { textModel: tm, rights, legalStatus } = await loadTextModel();
            if (!tm) {
                if (empText && !empText.value) empText.value = '— لا يوجد قالب نص لهذا النموذج —';
                return;
            }

            // Ensure case context ready
            const caseId = Number(localStorage.getItem('caseId') || 0);

            if (caseId > 0 && (!window.currentCaseParty || !window.currentCaseMeta)) {

                await loadCaseContextFromServer(caseId);
            }

            // Parties + meta
            const party = window.currentCaseParty || { applicants: [], authenticated: [] };
            const meta = window.currentCaseMeta || {
                mainGroup: localStorage.getItem('mainGroup') || '',
                altColName: localStorage.getItem('altCol') || '',
                altSubColName: localStorage.getItem('altSub') || '',
                langLabel: (localStorage.getItem('docLang') === 'en' ? 'الانجليزية' : 'العربية')
            };
            const applicants = party.applicants || [];
            const authenticated = party.authenticated || [];
            const mainGroup = meta.mainGroup || '';
            const altColName = meta.altColName || '';
            const altSubColName = meta.altSubColName || '';
            const lang = (localStorage.getItem('docLang') === 'en') ? 'الانجليزية' : 'العربية';
           
            // Tablechar rows
            const rows = await ensureTablechar({ force: false });

            // Collect current field values for token replacement
            stampDataTokens(formEl);
            const values = collectValuesSmart(formEl);

            // Inject legalStatus token so it can be used inside TextModel if present
            if (legalStatus && !values.legalStatus) values.legalStatus = legalStatus;

            // ==== 1) Build the full TextModel block (token replacement) ====
            let modelBlockRaw = replaceTokens(tm, values) || '';
            // Morphology for the model block
            const appSlot = deriveMorphSlot(applicants);
            const authSlot = deriveMorphSlot(authenticated);
            let modelBlock = morphTextWithTablechar(rows, modelBlockRaw, 0, '*');               // canonicalize
            modelBlock = realizeTextForPerson(rows, modelBlock, authSlot, '2', { debug: false });
            modelBlock = realizeTextForPerson(rows, modelBlock, appSlot, '1', { debug: false });
            modelBlock = modelBlock.trim();

            // ==== 2) Build the intro block (Wakala intro) ====
            const introBlock = buildWakalaIntroBlock({
                applicants,
                authenticated,

                chars: rows,
                altColName,
                altSubColName,
                mainGroup,
                lang,
                legalStatus: legalStatus || ''
            }).trim();
            
            // ==== 3) Decide if we need to append the model block (avoid duplicates) ====
            const normalizeLite = (s) =>
                String(s || '')
                    .replace(/\s+/g, ' ')     // collapse whitespace
                    .replace(/[،,]+/g, '،')   // unify comma
                    .trim();

            let combined = introBlock;
            const introNorm = normalizeLite(introBlock);
            const modelNorm = normalizeLite(modelBlock);

            if (modelNorm && !introNorm.includes(modelNorm)) {
                // Only append if the intro doesn't already contain the model content
                combined = `${combined}،\n\n${modelBlock}`;
            }

            // ==== 4) Build rights block (token replacement + morph) ====
            let rightsBlock = '';
            if (typeof rights === 'string' && rights.trim() && isWakala(mainGroup)) {
                let r = replaceTokens(rights, values);
                r = realizeTextForPerson(rows, r, appSlot, '1', { debug: false });
                r = realizeTextForPerson(rows, r, authSlot, '2', { debug: false });
                rightsBlock = r.trim();
            }

            if (rightsBlock) combined = `${combined}،\n\n${rightsBlock}`;

            // ==== 5) Final pass: overall corrections ====
            const ctxAll = computeCtxFromParties(applicants, authenticated);
            const finalText = finalCorrections(combined, rows, ctxAll);

            if (empText) empText.value = finalText;

            // Debug summary
            console.debug('[Step5] Built:', {
                hasIntro: !!introBlock,
                hasModelBlock: !!modelBlock,
                appendedModel: !introNorm.includes(modelNorm),
                hasRights: !!rightsBlock
            });

        } catch (err) {
            console.error('[Step5] rebuild failed:', err);
        }
    }

    // ---------- Value collection ----------
    // Replace your collector call with this version
    function collectValuesSmart(scope) {
        const out = { t: {}, c: {}, m: {}, n: {} };
        if (!scope) return out;

        // Ensure tokens are stamped first
        stampDataTokens(scope);

        // 1) Text + Date + Selects directly
        scope.querySelectorAll('input[data-token], textarea[data-token], select[data-token]').forEach(el => {
            const tok = el.dataset.token || '';
            const typ = tok.charAt(0);
            const idx = Number(tok.slice(1));
            if (!['t', 'c', 'm', 'n'].includes(typ) || !Number.isFinite(idx)) return;

            if (typ === 't') {
                out.t[idx] = (el.value ?? '').trim();
            } else if (typ === 'n') {
                out.n[idx] = normalizeDate(el.value);
            } else if (typ === 'm' && el.tagName === 'SELECT') {
                out.m[idx] = el.selectedOptions?.[0]?.text?.trim() || el.value || '';
            }
        });

        // 2) Radio/checkbox groups (cN / mN rendered as radios)
        // Group by data-token value on inputs
        const groupMap = {};
        scope.querySelectorAll('input[type="radio"][data-token], input[type="checkbox"][data-token]').forEach(el => {
            const tok = el.dataset.token || '';
            if (!tok) return;
            (groupMap[tok] = groupMap[tok] || []).push(el);
        });

        const getLabel = (el) => {
            if (el.id) {
                const lab = scope.querySelector(`label[for="${el.id}"]`);
                if (lab) return (lab.textContent || '').trim();
            }
            return (el.value ?? '').trim();
        };

        for (const [tok, inputs] of Object.entries(groupMap)) {
            const typ = tok.charAt(0);
            const idx = Number(tok.slice(1));
            if (!Number.isFinite(idx)) continue;

            const checked = inputs.find(i => i.checked);
            const val = checked ? getLabel(checked) : '';

            if (typ === 'c') out.c[idx] = val;
            if (typ === 'm') out.m[idx] = val || out.m[idx] || ''; // radios used as combo
        }

        // 3) Fallback: try flexible name/id patterns if any indices are still missing
        const ensure = (obj, letter, base) => {
            const need = new Set();
            // scan template? optional. Instead, infer from DOM again:
            scope.querySelectorAll(`[name*="${base}"], [id*="${base}"]`).forEach(el => {
                const re = new RegExp(`${base}\\s*(?:[-_]?|\\[)\\s*(\\d+)`, 'i');
                const m = re.exec(el.name || '') || re.exec(el.id || '');
                if (m) need.add(Number(m[1]));
            });
            need.forEach(n => { if (!(n in obj)) obj[n] = obj[n] ?? ''; });
        };
        ensure(out.t, 't', 'itext');
        ensure(out.n, 'n', 'itxtdate');
        ensure(out.c, 'c', 'icheck');
        ensure(out.m, 'm', 'icombo');

        return out;
    }


    function extractIndex(name, prefix) {
        const m = new RegExp(`^${prefix}(\\d+)$`, 'i').exec(name || '');
        return m ? Number(m[1]) : null;
    }
    function labelOrValue(inputEl, scope) {
        const id = inputEl.id;
        if (id) {
            const lab = scope?.querySelector(`label[for="${id}"]`);
            if (lab) return (lab.textContent || '').trim();
        }
        return (inputEl.value ?? '').trim();
    }
    function normalizeDate(v) {
        if (!v) return '';
        try {
            const d = new Date(v);
            if (Number.isNaN(d.getTime())) return v;
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${dd}`;
        } catch { return v; }
    }

    // ---------- Token replacement ----------
    function replaceTokens(template, values) {
        if (!template) return '';
        return template.replace(/\b([tcmn])(\d+)\b/g, (full, typ, numStr) => {
            const n = Number(numStr);
            if (typ === 't') return values.t[n] ?? '';
            if (typ === 'c') return values.c[n] ?? '';
            if (typ === 'm') return values.m[n] ?? '';
            if (typ === 'n') return values.n[n] ?? '';
            return full;
        });
    }

    // ---------- TextModel (robust) ----------
    // Globals (optional; keep near your other state)



    async function loadTextModel(force = false) {
        if (!force && (textModel || rightsText || legalStatusText)) {
            return { textModel, rights: rightsText, legalStatus: legalStatusText };
        }

        const tplId =
            getLS('selectedTemplateId') || getLS('tpl') ||
            sessionStorage.getItem('selectedTemplateId') || sessionStorage.getItem('tpl') || '';
        
        const lang = getLS('docLang') || 'ar';
        if (!tplId) {
            textModel = ''; rightsText = ''; legalStatusText = '';
            return { textModel, rights: rightsText, legalStatus: legalStatusText };
        }

        let res, bodyText;
        try {
            res = await fetch(apiResolve(`api_get_textmodel.php?template_id=${encodeURIComponent(tplId)}&lang=${encodeURIComponent(lang)}`), { credentials: 'same-origin' });
            bodyText = await res.text();
        } catch (e) {
            console.warn('[TextModel] fetch failed', e);
            textModel = ''; rightsText = ''; legalStatusText = '';
            return { textModel, rights: rightsText, legalStatus: legalStatusText };
        }

        if (!res.ok) {
            console.warn('[TextModel] HTTP', res.status, '→ empty. sample:', (bodyText || '').slice(0, 200));
            textModel = ''; rightsText = ''; legalStatusText = '';
            return { textModel, rights: rightsText, legalStatus: legalStatusText };
        }

        let data = null; try { data = bodyText ? JSON.parse(bodyText) : null; } catch { }

        const tm = data
            ? (typeof data === 'string' ? data : (data.textModel ?? data.template ?? ''))
            : '';
        const rt = data && typeof data !== 'string' ? (data.rights ?? '') : '';
        const ls = data && typeof data !== 'string' ? (data.legalStatus ?? data['الأهلية'] ?? '') : '';

        textModel = String(tm || '');
        rightsText = String(rt || '');
        legalStatusText = String(ls || '');

        return { textModel, rights: rightsText, legalStatus: legalStatusText };
    }


    // ---------- Tablechar (load once + cache) ----------
    // Global cache
    // let tablecharRows = Array.isArray(window.tablecharRows) ? window.tablecharRows : null;

    async function ensureTablechar(force = false) {
        // Allow object form too: ensureTablechar({ force:true })
        const opts = (typeof force === 'object' && force) ? force : { force };
        const hardForce = !!opts.force;

        // New keys to force a refresh the first time after schema change
        const LS_KEY = 'tablechar_cache_rows_v2';
        const META_KEY = 'tablechar_cache_meta_v2';
        const VER_KEY = 'tablechar_cache_ver';
        const CODE_SCHEMA_VERSION = 2;           // bump when schema/shape changes
        const ttlMs = 24 * 60 * 60 * 1000;    // 24h

        // Use live cache if present and not forcing
        if (!hardForce && Array.isArray(tablecharRows)) return tablecharRows;

        // Check stored version to decide whether to bust cache
        let storedVer = 0;
        try { storedVer = Number(localStorage.getItem(VER_KEY) || '0'); } catch { }

        let cached = null, meta = null;
        try { cached = JSON.parse(localStorage.getItem(LS_KEY) || 'null'); } catch { }
        try { meta = JSON.parse(localStorage.getItem(META_KEY) || 'null'); } catch { }

        const fresh = !!cached && !!meta && (Date.now() - (meta.savedAt || 0) < ttlMs);
        const needSchemaBust = (storedVer < CODE_SCHEMA_VERSION);

        const headers = {};
        if (!hardForce && !needSchemaBust) {
            if (meta?.etag) headers['If-None-Match'] = meta.etag;
            if (meta?.lastModified) headers['If-Modified-Since'] = meta.lastModified;
        }

        let res, text;
        try {
            // Ask server for the extended schema explicitly
            let url = apiResolve('api_tablechar.php?schema=2');
            // If we need a hard refresh (force or schema bump), use a cache-buster and skip conditionals
            if (hardForce || needSchemaBust) {
                url += `&t=${Date.now()}`;
                res = await fetch(url, { credentials: 'same-origin' });
            } else {
                res = await fetch(url, { headers, credentials: 'same-origin' });
            }

            if (res.status === 304 && fresh && cached && !needSchemaBust && !hardForce) {
                tablecharRows = cached;
                return tablecharRows;
            }

            text = await res.text();
        } catch (e) {
            console.warn('[Tablechar] fetch failed → using cache if any', e);
            tablecharRows = cached || [];
            return tablecharRows;
        }

        let data = null;
        try { data = text ? JSON.parse(text) : null; } catch { }

        const rows = Array.isArray(data?.rows) ? data.rows : [];

        // Helper: normalize pronoun fields (الضمير / الضمير2), accept arrays/strings/csv
        const toSet = (val) => {
            const set = new Set();
            const push = (x) => {
                const s = String(x ?? '').trim();
                if (s) set.add(s);
            };
            if (Array.isArray(val)) {
                val.forEach(v => push(v));
            } else if (val != null) {
                String(val).split(/[,\s|/]+/).forEach(v => push(v));
            }
            return set;
        };

        // Map to runtime shape
        tablecharRows = rows.map(r => {
            const forms = Array.isArray(r.forms) ? r.forms : [r.f1, r.f2, r.f3, r.f4, r.f5, r.f6];
            const cleanForms = (forms || []).map(v => v || '').filter(Boolean);

            // Accept multiple possible keys for the two pronoun columns
            const p1 = r.pronounType ?? r['pronounType'] ?? r['الضمير'] ?? r['ضمير'] ?? '';

            const p2 = r.pronounTypeExt ?? r['pronounTypeExt'] ?? r['الضمير2'] ?? r['ضمير2'] ?? r['pronoun2'] ?? '';

            const pronounType = String(p1 ?? '').trim();  // keep the primary as-is
            const pronounTypeExt = String(p2 ?? '').trim();
            const pronounTypeSet = toSet([pronounType]);     // set for quick match
            const pronounTypeExtSet = toSet(pronounTypeExt);    // ext types

            return {
                id: r.id ?? r.ID ?? r.Id,
                root: r.root ?? r['root'] ?? r['الرموز'] ?? r['رمز'] ?? '',
                forms: Array.isArray(forms) ? forms : cleanForms,
                pronounType,
                pronounTypeExt,
                pronounTypeSet,
                pronounTypeExtSet,
                detectSet: Array.from(new Set(cleanForms))
            };
        });

        // Save cache & metadata
        const etag = res.headers.get('ETag');
        const lastMod = res.headers.get('Last-Modified');
        const newMeta = { savedAt: Date.now(), etag: etag || null, lastModified: lastMod || null };
        try { localStorage.setItem(LS_KEY, JSON.stringify(tablecharRows)); } catch { }
        try { localStorage.setItem(META_KEY, JSON.stringify(newMeta)); } catch { }
        try { localStorage.setItem(VER_KEY, String(CODE_SCHEMA_VERSION)); } catch { }

        return tablecharRows;
    }

    // ---------- Canonicalize → Realize ----------
    function buildWordishRegex(phrases) {
        const esc = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const body = phrases.map(esc).join('|');
        return new RegExp(`(^|[^\\p{L}])(${body})(?=$|[^\\p{L}])`, 'gu');
    }

    function canonicalizeToRoots(text, rows) {
        if (!text || !rows?.length) return text;
        let out = text;
        for (const r of rows) {
            if (!r.detectSet?.length) continue;
            const re = buildWordishRegex(r.detectSet);
            out = out.replace(re, (m, pre, word) => `${pre}${r.root}`);
        }
        return out;
    }

    function chooseIndex(pronounType, groupCount, groupGender) {
        // 0: masc sg | 1: fem sg | 2: dual masc | 3: dual fem | 4: plural fem | 5: plural masc
        if (pronounType === 0) return 0;
        const g = (groupGender || '').toLowerCase();
        const isF = g === 'f';
        const isM = g === 'm';
        const isMixed = !isF && !isM;
        if (groupCount <= 1) return isF ? 1 : 0;
        if (groupCount === 2) return isF ? 3 : 2;
        // 3+
        if (isF) return 4;
        if (isM || isMixed) return 5;
        return 0;
    }

    function realizeFromRoots(text, rows, ctxAll) {
        if (!text || !rows?.length) return text;
        const app = ctxAll?.app || { count: 1, gender: 'm' }; // للضمير = 1
        const auth = ctxAll?.auth || { count: 1, gender: 'mixed' }; // للضمير = 2

        let out = text;
        for (const r of rows) {
            let groupCtx;

            if (r.pronounType === 1) {
                // متكلم/المتقدمين
                groupCtx = app;
            } else if (r.pronounType === 2) {
                // غائب/الموثق لهم (authenticated)
                groupCtx = auth;
            } else {
                // ثابت
                groupCtx = { count: 1, gender: 'm' };
            }

            const idx = chooseIndex(r.pronounType, groupCtx.count, groupCtx.gender);
            const replacement = r.forms[idx] || r.forms[0] || '';
            if (!replacement) continue;

            const rootEsc = r.root.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            out = out.replace(new RegExp(rootEsc, 'g'), replacement);
        }
        return out;
    }


    // ---------- Context ----------
    function computeGroupContext() {
        const count = Number(localStorage.getItem('applicantsCount') || '1');
        const gender = (localStorage.getItem('applicantsGender') || 'mixed').toLowerCase();
        return { count: isFinite(count) && count > 0 ? count : 1, gender };
    }

})();


// ###### text processing 
// ---------------- Core utilities ----------------

function isWakala(mainGroup) {
    return String(mainGroup || '').trim() === 'توكيل';
}


function isIqrar(mainGroup) {
    return String(mainGroup || '').trim() === 'إقرار';
}


function isSowrnIqrar(mainGroup) {
    return String(mainGroup || '').trim() === 'إقرار مشفوع باليمين';
}


// 0..5 slot from people list (arrays or objects)
function deriveMorphSlot(people) {
    const list = Array.isArray(people) ? people : [];
    const count = list.length;

    const norm = (g) => {
        const s = String(g ?? '').trim().toLowerCase();
        if (s === 'm' || s === 'male' || s === 'ذكر') return 'm';
        if (s === 'f' || s === 'female' || s === 'انثى' || s === 'أنثى' || s === 'مؤنث') return 'f';
        return ''; // unknown
    };
    const gAt = (i) => norm(Array.isArray(list[i]) ? list[i][3] : (list[i]?.gender ?? list[i]?.sex));

    // 0) empty → default like Python’s fallback (masc. singular)
    if (count === 0) return 0;

    // 1) single
    if (count === 1) {
        return gAt(0) === 'f' ? 1 : 0; // 0: sg-m, 1: sg-f
    }

    // 2) dual
    if (count === 2) {
        const g1 = gAt(0), g2 = gAt(1);
        if ((g1 === 'm' && g2 === 'm') || g1 === 'f' && g2 === 'm' || g1 === 'm' && g2 === 'f') return 2; // dual-m
        if (g1 === 'f' && g2 === 'f') return 3; // dual-f

        return 5; // mixed dual → fall back to plural-m/mixed (matches Python’s final return)
    }

    // 3) exactly three
    if (count === 3) {
        const allF = (gAt(0) === 'f' && gAt(1) === 'f' && gAt(2) === 'f');
        if (allF) return 4; // plural-f (exactly as in Python)
        return 5;          // otherwise plural-m/mixed
    }

    // 4) > 3 — Python didn’t special-case; it always falls through to 5
    return 5; // plural-m/mixed
}


// Accept raw DB rows or normalized objects for Tablechar rows
function normalizeTablecharRows(chars) {
    if (!Array.isArray(chars) || !chars.length) return [];
    const r0 = chars[0];
    if (r0 && typeof r0 === 'object' && 'forms' in r0 && 'root' in r0) return chars; // already normalized
    // raw arrays: [ID, root, f1..f6, pronoun]
    return chars.map(r => ({
        root: r[1],
        forms: [r[2], r[3], r[4], r[5], r[6], r[7]].map(v => v || ''),
        pronounType: r[8],
        pronounTypeExt: r[9]
    }));
}

/**
 * Realize text for a specific person by replacing any matched root OR form
 * with forms[targetIndex]. Handles tokens with leading 'و' correctly.
 *
 * @param {Array} rows  Tablechar rows (root, forms[0..5], pronounType[/Ext])
 * @param {string} text
 * @param {number} targetIndex  0..5 (المقابل1..6)
 * @param {string|number} person '1' | '2' | '0'
 * @param {{debug?: boolean, trace?: string[]}} opts
 */
function realizeTextForPerson(rows, text, targetIndex, person, opts = {}) {
    const debug = !!opts.debug;
    const traces = Array.isArray(opts.trace) ? new Set(opts.trace) : null;

    if (!text || !Array.isArray(rows) || rows.length === 0) {
        if (debug) console.debug('[Realize] skip: empty text/rows');
        return text || '';
    }

    const personStr = String(person);
    const idx = Number.isFinite(targetIndex) ? targetIndex : 0;

    const splitSet = (val) => new Set(
        String(val ?? '')
            .split(/[,\s|/]+/)
            .map(x => x.trim())
            .filter(Boolean)
    );
    const asForms = (r) =>
        Array.isArray(r.forms) ? r.forms.map(v => v || '')
            : [r.F1, r.F2, r.F3, r.F4, r.F5, r.F6].map(v => v || '');

    const personMatches = (r) => {
        if (String(r.pronounType ?? '') === personStr) return true;
        const ext = r.pronounTypeExtSet || splitSet(r.pronounTypeExt);
        return ext.has(personStr);
    };

    // Prepare candidate rows
    const preppedAll = rows.map(r => {
        const root = r.root || r.RootToken || '';
        const mv = /^([\p{L}]+)([^\p{L}]+)$/u.exec(root);
        return {
            id: r.id ?? r.ID ?? null,
            root,
            base: mv ? mv[1] : root,
            marker: mv ? mv[2] : '',
            forms: asForms(r),
            pronounType: r.pronounType,
            pronounTypeExt: r.pronounTypeExt
        };
    }).filter(x => x.root && x.forms.length);

    const candidates = preppedAll.filter(personMatches)
        .sort((a, b) => b.root.length - a.root.length);
    const others = preppedAll.filter(r => !personMatches(r));

    if (debug) {
        console.debug(`[Realize] start: person=${personStr}, slot=${idx}, candidates=${candidates.length}, textLen=${text.length}`);
        // Quick presence check for your problem rows:
        const row143 = candidates.find(r => r.id === 143 || r.root.includes('وبطوعي'));
        if (!row143) console.debug('[Realize] NOTE: row 143 (وبطوعي$$$) not in candidates — check pronounType/Ext for "1".');
    }

    const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    // Arabic-aware tokenizer: (^|non-letter)(optional 'و')(letters [+ optional marker])(optional punct)
    const tokenRE = /(^|[^\p{L}])(و?)([\p{L}]+(?:[^\p{L}]+)?)([،,/]?)(?=$|[^\p{L}])/gu;

    let tokenNo = 0, hits = 0;

    const out = text.replace(tokenRE, (m, pre, waw, token, punct) => {
        tokenNo++;

        // Build the two match candidates
        const full = (waw ? 'و' : '') + token; // includes conjunction if present
        const bare = token;                    // excluding the conjunction

        const wantLog = !traces || traces.has(full) || traces.has(bare) || traces.has(token);

        // Try each candidate row
        for (const r of candidates) {
            const target = r.forms[idx] || r.forms[0] || '';
            if (!target) continue;

            // --- Try FULL first (so rows that store 'و...' match) ---
            // 1) exact root
            if (full === r.root) {
                hits++;
                if (debug && wantLog) console.debug(`[Realize#${tokenNo}] ROOT@FULL "${full}" -> row#${r.id} -> "${target}"`);
                // Matched with FULL -> don't re-add captured 'waw', target already has whatever it needs
                return `${pre}${target}${punct}`;
            }
            // 2) root-variant (base + letters + marker)
            if (r.marker) {
                const reVarFull = new RegExp(`^${esc(r.base)}[\\p{L}]*${esc(r.marker)}$`, 'u');
                if (reVarFull.test(full)) {
                    hits++;
                    if (debug && wantLog) console.debug(`[Realize#${tokenNo}] RVAR@FULL "${full}" -> row#${r.id} -> "${target}"`);
                    return `${pre}${target}${punct}`;
                }
            }
            // 3) any form
            for (let i = 0; i < r.forms.length; i++) {
                const f = r.forms[i];
                if (f && full === f) {
                    hits++;
                    if (debug && wantLog) console.debug(`[Realize#${tokenNo}] FORM${i}@FULL "${full}" -> row#${r.id} -> "${target}"`);
                    return `${pre}${target}${punct}`;
                }
            }

            // --- Then try BARE (so lexemes without 'و' still match) ---
            if (bare === r.root) {
                hits++;
                if (debug && wantLog) console.debug(`[Realize#${tokenNo}] ROOT@BARE "${bare}" -> row#${r.id} -> "${target}"`);
                // Matched with BARE -> keep the captured waw if it existed
                return `${pre}${waw}${target}${punct}`;
            }
            if (r.marker) {
                const reVarBare = new RegExp(`^${esc(r.base)}[\\p{L}]*${esc(r.marker)}$`, 'u');
                if (reVarBare.test(bare)) {
                    hits++;
                    if (debug && wantLog) console.debug(`[Realize#${tokenNo}] RVAR@BARE "${bare}" -> row#${r.id} -> "${target}"`);
                    return `${pre}${waw}${target}${punct}`;
                }
            }
            for (let i = 0; i < r.forms.length; i++) {
                const f = r.forms[i];
                if (f && bare === f) {
                    hits++;
                    if (debug && wantLog) console.debug(`[Realize#${tokenNo}] FORM${i}@BARE "${bare}" -> row#${r.id} -> "${target}"`);
                    return `${pre}${waw}${target}${punct}`;
                }
            }
        }

        // No match — optionally log a hint (limit noise)
        if (debug && wantLog) {
            console.debug(`[Realize#${tokenNo}] no match: full="${full}", bare="${bare}"`);
        }
        return m;
    });

    if (debug) {
        console.debug(`[Realize] done: replacements=${hits}`);
    }
    return out;
}


function morphTextWithTablechar(rows, text, targetIndex, person) {
    if (!text || !Array.isArray(rows) || rows.length === 0) return text;

    const personStr = String(person);

    // accept match via pronounType OR pronounTypeExt (CSV / space / pipe)
    const splitSet = (val) => {
        const s = String(val ?? '');
        if (!s) return new Set();
        return new Set(s.split(/[,\s|/]+/).map(x => x.trim()).filter(Boolean));
    };
    const matchPerson = (r) => {
        if (personStr === '*') return true;
        if (String(r.pronounType || '') === personStr) return true;
        if (r.pronounTypeExtSet && r.pronounTypeExtSet.has(personStr)) return true;
        const extSet = r.pronounTypeExtSet || splitSet(r.pronounTypeExt);
        return extSet.has(personStr);
    };

    const filtered = (personStr === '*') ? rows : rows.filter(matchPerson);

    const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    // prefer longer roots first to avoid partial hits

    // console.log('prepped: ', prepped);
    // Arabic-aware token scanner:
    // (^|non-letter) (optional 'و') (token=[letters][optional non-letter marker]) (optional punct) (…)
    const tokenRE = /(^|[^\p{L}])(و?)([\p{L}]+(?:[^\p{L}]+)?)([،,/]?)(?=$|[^\p{L}])/gu;

    let out = text;

    if (personStr === '*') {
        // ---------- Canonicalize: any form -> root ----------
        // (unchanged from your current version)
        for (const r of rows) {
            const forms = (r.forms || []).filter(Boolean);
            const root = r.root || '';
            if (!root) continue;

            // forms -> root
            if (forms.length) {
                const reForms = new RegExp(`(^|[^\\p{L}])(و?)(${forms.map(esc).sort((a, b) => b.length - a.length).join('|')})([،,/]?)(?=$|[^\\p{L}])`, 'gu');
                out = out.replace(reForms, (m, pre, waw, _word, punct) => `${pre}${waw}${root}${punct}`);
            }

            // base+letters+marker -> root
            const mv = /^([\p{L}]+)([^\p{L}]+)$/u.exec(root);
            if (mv) {
                const base = mv[1], mark = esc(mv[2]);
                const reVar = new RegExp(`(^|[^\\p{L}])(و?)(${esc(base)}[\\p{L}]*${mark})([،,/]?)(?=$|[^\\p{L}])`, 'gu');
                out = out.replace(reVar, (full, pre, waw, _var, punct) => `${pre}${waw}${root}${punct}`);
            }
        }
        return out;
    }

    // ---------- Realize: loop over TEXT TOKENS and replace roots -> target form ----------
    const prepped = filtered
        .map(r => {
            const root = r.root || '';
            const mv = /^([\p{L}]+)([^\p{L}]+)$/u.exec(root);
            const base = mv ? mv[1] : root;
            const marker = mv ? mv[2] : '';
            return {
                root,
                base,
                marker,
                forms: (r.forms || []).map(v => v || ''),
            };
        })
        .filter(x => x.root && x.forms.length)
        .sort((a, b) => b.root.length - a.root.length);
    const idx = Number.isFinite(targetIndex) ? targetIndex : 0;
    console.log('text', out);
    out = out.replace(tokenRE, (m, pre, waw, token, punct) => {
        // Try to match this token against any root (exact or base+letters+marker)
        console.log(pre);
        for (const r of prepped) {
            // console.log(token);
            const targetForm = r.forms[idx] || r.forms[0] || '';
            if (!targetForm) continue;

            // Exact root?
            if (token === r.root) {

                console.log('targetForm: ', targetForm);
                return `${pre}${waw}${targetForm}${punct}`;
            }

            // Variant: starts with base and ends with the same marker, allowing extra letters in between
            if (r.marker) {
                const reVar = new RegExp(`^${esc(r.base)}[\\p{L}]*${esc(r.marker)}$`, 'u');
                if (reVar.test(token)) {
                    return `${pre}${waw}${targetForm}${punct}`;
                }
            }
        }
        // no match → keep original token
        return m;
    });

    return out;
}



// ---------------- Small formatting helpers ----------------

function gx(obj, idx, prop) { return Array.isArray(obj) ? obj[idx] : obj?.[prop]; }
function isSudanese(n) { return String(n || '').trim() === 'سوداني'; }
function nationalityParen(n) { const s = String(n || '').trim(); return s && !isSudanese(s) ? ` (${s} الجنسية،) ` : ' '; }
function normalizePassportNo(no) { return String(no ?? '').replace(/p/g, 'P'); }

function fmtAuthSingleLine(person) {
    const title = titleFor(person); // السيد/السيدة
    const name = gx(person, 2, 'fullName') || '—';
    const nat = nationalityParen(gx(person, 4, 'nationality'));
    const doc = gx(person, 5, 'documentType') || gx(person, 5, 'passportType') || 'جواز سفر';
    const pass = normalizePassportNo(gx(person, 6, 'passportNo'));
    // End at "لينوب ..." — DO NOT include "عني ويقوم مقامي" to avoid duplication with the purpose line
    return `${title} ${name}${nat}حامل ${doc} بالرقم ${pass}، وذلك لينوب auth1`;
}

function fmtAuthList(auths) {
    let out = '';
    for (let i = 0; i < auths.length; i++) {
        const a = auths[i];
        const holder = holderFor(a);
        const title = titleFor(a);
        const name = gx(a, 2, 'fullName') || '—';

        const nat = String(gx(a, 4, 'nationality') || '').trim();
        const natSeg = (nat && nat !== 'سوداني') ? `(${nat})الجنسية،` : '';
        const doc = gx(a, 5, 'documentType') || gx(a, 5, 'passportType') || 'جواز سفر';
        const pass = normalizePassportNo(gx(a, 6, 'passportNo'));
        out += `${i + 1}- ${title} ${name}${natSeg} ${holder} ${doc} بالرقم ${pass}.\n`;
    }
    // Keep it neutral; Tablechar will realize (dual/plural)

    return `المذكورين أدناه:\n` + out.trimEnd();
}

// ---------------- Applicants intro (uses altSubColName) ----------------

/**
 * buildApplicantsIntro(altSubColName, appName, chars, authPart, mainGroup, lang?)
 * - Keeps static phrasing; Tablechar handles morphology (ضمير=1).
*/

// buildApplicantsIntro(altSubColName, appName, chars, authPart, mainGroup, lang, legalStatusText?)
function buildApplicantsIntro(altSubColName, appName, chars, authPart, mainGroup, lang = 'العربية', legalStatus = '') {
    const count = Array.isArray(appName) ? appName.length : 0;
    if (count === 0) return '';

    let textRequest = 'بهذا ';
    if (isWakala(mainGroup)) {
        textRequest = (altSubColName === 'إقرار بالتنازل') ? 'بهذا فقد تنازلت تنازلا نهائيا' : 'بهذا فقد أوكلت';
    } else if (isIqrar(mainGroup)) {
        textRequest = 'بهذا أقر ';
    }
    else if (isSowrnIqrar(mainGroup)) {
        textRequest = 'بهذا اقسم بالله العظيم وأقر ';
    }

    let text = '';
    let residState = '، ';
    const rows = normalizeTablecharRows(chars);
    const slot = deriveMorphSlot(appName);
    if (count === 1) {
        const app = appName[0];
        const sex = gx(app, 3, 'gender')
        const name = gx(app, 2, 'fullName') || '—';
        const doc = gx(app, 5, 'documentType') || gx(app, 5, 'passportType') || 'جواز سفر';
        const pass = normalizePassportNo(gx(app, 6, 'passportNo'));
        const issue = gx(app, 7, 'issuePlace') || '';
        const resides = Boolean(gx(app, 9, 'residesInKSA'));
        if (resides) residState = '، المقيم بالمملكة العربية السعودية ';

        if (lang === 'الانجليزية') {
            text = `I, ${name} holder of Sudanese ${doc} no. ${pass} issued in ${issue}, ${legalStatus || 'legal status'}, `;
        } else {
            const ls = legalStatus ? `${legalStatus}، ` : '';
            if (sex === 'm')
                text = `أنا المواطن / ${name}${residState}حامل ${doc} بالرقم ${pass} إصدار ${issue}، ${ls}${textRequest}`;
            else
                text = `أنا المواطنة / ${name}${residState}حاملة ${doc} بالرقم ${pass} إصدار ${issue}، ${ls}${textRequest}`;
        }
    } else {
        const ls = legalStatus ? `${legalStatus}، ` : '';
        text = `نحن المواطنون الموقعون أعلاه، ${ls}${textRequest}`;
    }


    let result = realizeTextForPerson(rows, text, slot, '1', { debug: true })
    

    if (authPart && typeof authPart === 'string' && authPart.trim()) {
        result = `${result} ${authPart}`.trim();
    }
    return result;
}


// ---------------- Authorized intro (uses altColName & mainGroup) ----------------

/**
 * authNamePart1(authName, appName, chars, altColName, mainGroup, lang?)
 * - For mainGroup === 'توكيل': builds single/list form, then Tablechar morph (2 then 1).
 * - For other groups (the old "10"): mirrors your إقرار branches using altColName.
 */
function authNamePart1(authName, appName, chars, altColName, mainGroup, lang = 'العربية') {
    const rows = normalizeTablecharRows(chars);

    if (isWakala(mainGroup)) {
        const n = Array.isArray(authName) ? authName.length : 0;
        if (n <= 0) return '';

        // 1) Build neutral text
        let text = (n === 1) ? fmtAuthSingleLine(authName[0]) : fmtAuthList(authName);

        // 2) Canonicalize once so tokens match roots even if the neutral text uses variants
        const authSlot = deriveMorphSlot(authName); // 0..5
        const appSlot = deriveMorphSlot(appName);  // 0..5

        console.debug('[wakala] before realize:', { n, authSlot, appSlot, text });

        // 4) Always realize for both persons (no gating by specific slots)


        // 5) If you want an extra “مجتمعين أو مفترقين …” tail for multi-auth
        if (n > 1) {
            // Build small clause with proper morphology; canonicalize tiny bits first
            let tail = 'وذلك ';
            console.log(authSlot);
            let v1 = realizeTextForPerson(rows, 'لينوب', authSlot, '2', { debug: false });

            tail += `${v1}`;

            text = `${text}\n${tail}`;
        } else { text = realizeTextForPerson(rows, text, authSlot, '2', { debug: false }); }

        console.debug('[wakala] after realize:', { text });
        return text;
    }


    // Other main groups (previously refNum '10')
    if (lang === 'الانجليزية') return '';
    const slotFromAuth = deriveMorphSlot(authName);

    if (altColName === 'إقرار') {
        return morphTextWithTablechar(rows, 'أقر', slotFromAuth, '1');
    }
    if (altColName === 'إقرار مشفوع باليمين') {
        return morphTextWithTablechar(rows, 'أقسم بالله العظيم وأقر', slotFromAuth, '1');
    }
    return '';
}
