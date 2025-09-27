<?php
require __DIR__ . '/auth.php'; // authentication check
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <title>e-Consular Services</title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <!-- Body-only styles (header/footer remain untouched) -->
  <link rel="stylesheet" href="static/styles.css"/>
  <script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js"></script>
    <script>pdfjsLib.GlobalWorkerOptions.workerSrc ="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js";</script>
    <!-- head: add JsBarcode (Code128) -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>

</head>
<body>



<div id="roleToggle_row" class="toolbar-row" hidden>
  <label class="muted" style="display:none;">
    <input id="roleToggle" type="checkbox" checked />
    <span>وضع الموظف (للاختبار)</span>
  </label>
  <span id="roleBadge" class="badge" style="padding:4px 8px;border-radius:8px;background:#e5e7eb;color:#111827;font-size:.85rem;">
    الدور: مراجع
  </span>
</div>


<!-- Mobile stepper (shown only on small screens via CSS) -->
<nav id="stepperMobile" class="stepper-mobile" role="navigation" aria-label="Progress" dir="rtl" hidden>
  <button id="smBack" class="btn btn-ghost" type="button">السابق</button>
  <div class="sm-center">
    <div class="sm-label" id="smLabel">—</div>
    <div class="sm-count" id="smCount">0 / 0</div>
    <div class="sm-progress"><span id="smBar" class="sm-bar"></span></div>
  </div>
  <button id="smMenu" class="btn btn-ghost" type="button" aria-haspopup="dialog" aria-controls="stepPicker">القائمة</button>
</nav>

<!-- Bottom sheet to pick a step -->
<div id="stepPicker" class="sheet-backdrop" hidden>
  <div class="sheet-panel" role="dialog" aria-modal="true" aria-labelledby="sheetTitle" dir="rtl">
    <header class="sheet-head">
      <h3 id="sheetTitle" class="sheet-title">اختر المرحلة</h3>
      <button id="sheetClose" class="modal-close" aria-label="إغلاق">×</button>
    </header>
    <div id="sheetList" class="sheet-list"></div>
  </div>
</div>

<!-- BODY CONTENT (keep header/footer untouched) -->
<nav class="stepper" aria-label="Progress">
  <button class="stepper-item is-big is-current" data-step="0" aria-current="step">
    <span class="idx">0</span><span class="lbl">القائمة</span>
  </button>
  
  <button class="stepper-item is-big " data-step="1" disabled>
    <span class="idx">1</span><span class="lbl">اللغة</span>
  </button>

  <button class="stepper-item is-big" data-step="2" disabled>
    <span class="idx">2</span><span class="lbl">نوع الطلب</span>
  </button>

  <button class="stepper-item is-big" data-step="3" disabled>
    <span class="idx">3</span><span class="lbl">المستندات الداعمة</span>
  </button>

  <button class="stepper-item is-big" data-step="4" disabled>
    <span class="idx">4</span><span class="lbl">البيانات الشخصية</span>
  </button>

  <button class="stepper-item is-big" data-step="5" disabled>
    <span class="idx">5</span><span class="lbl">تفاصيل الطلب</span>
  </button>

  <button class="stepper-item is-big" data-step="6" disabled>
    <span class="idx">6</span><span class="lbl">المراجعة</span>
  </button>

  <button class="stepper-item is-big" data-step="7" disabled>
    <span class="idx">7</span><span class="lbl">الإرسال</span>
  </button>
</nav>

<section id="step10" class="card" style="max-width: 80%" dir="rtl" hidden>
  <h2 class="card-title">قائمة المعاملات</h2>
  <div class="field-block" style="flex-wrap:wrap;">
    <input id="docId" class="input" style="width: 130px; margin: 5px" placeholder="ابحث بالرقم">
    <input id="applicant" class="input" style="width: 180px; margin: 5px" placeholder="ابحث بالاسم">
    <input id="from" style="width: 135px; margin: 5px" type="date">
    <input id="to" style="width: 135px; margin: 5px" type="date">
      <!-- New radio filter -->
    
    
    <button id="btnNewProcess10" class="btn">معاملة جديدة</button>
  </div>
  <div class="field-block" style="margin: 10px 5px;">
    <label><input type="radio" name="tableFilter" value="both" checked> الكل</label>
    <label><input type="radio" name="tableFilter" value="Auth"> توكيل</label>
    <label><input type="radio" name="tableFilter" value="Collection"> تحصيل</label>
  </div>


  <div id="autoTodayNote" class="muted" hidden>تم عرض طلبات اليوم فقط لكثرة النتائج.</div>
  <table class="table">
    <thead>
      <tr>
        <th>رقم المكتب</th>
        <th>المجموعة</th>
        <th>مقدم الطلب</th>
        <th>الهوية</th>
        <th>التاريخ</th>
        <th>الحالة</th>
        <th>الطريقة</th>
      </tr>
    </thead>
    <tbody id="officeBody"></tbody>
  </table>
  <div class="table-mobile" id="casesOfficeTableMobile"></div>

  <div id="pager" style="margin-top:10px; text-align:center;"></div>
</section>


<section id="step0" class="card" dir="rtl" hidden>
  <h2 class="card-title">قائمة الطلبات</h2>

  <div class="field-block" style="flex-wrap:wrap;">
    <input id="fltCaseId" class="input" style="width: 120px; margin: 5px" type="text" placeholder="رقم الملف"/>    
    <input id="fltFrom"   class="input" style="width: 135px; margin: 5px" type="date" />
    <input id="fltTo"     class="input" style="width: 135px; margin: 5px" type="date" />
    <button id="btnSearch" class="btn">بحث</button>
    <button id="btnNewProcess" class="btn">معاملة جديدة</button>
  </div>

  <div class="table-wrap">
    <table class="table" id="casesTable" aria-label="قائمة الطلبات">
      <thead>
        <tr>
          <th>رقم</th>          
          <th>المجموعة</th>
          <th>النوع</th>
          <th>التاريخ</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <div class="table-mobile" id="casesTableMobile">
      
     
    </div>    
    <div id="casesEmpty" class="empty" hidden>لا توجد نتائج.</div>
  </div>

  <div class="pager">
    <button id="pgPrev" class="btn" disabled>السابق</button>
    <span id="pgInfo" class="muted"></span>
    <button id="pgNext" class="btn" disabled>التالي</button>
  </div>
</section>


<section id="step1" class="card" dir="rtl">
  <h2 class="card-title">اختر لغة المستند</h2>
  <p class="muted">
    هذا الاختيار يحدد اللغة التي سيتم طباعة المستند بها، يجب اختيار اللغة الانجليزية. فقط في حالة ان المستند سيتم تقديمه لجهة غير ناطقة بالعربية
  </p>

  <fieldset class="field-block" role="radiogroup" aria-labelledby="langLegend">
    <legend id="langLegend" class="legend">اللغة</legend>

    <label class="choice">
      <input type="radio" name="docLang" value="ar">
      <span class="choice-label">العربية</span>
    </label>

    <label class="choice">
      <input type="radio" name="docLang" value="en">
      <span class="choice-label">وثيقة باللغة الإنجليزية</span>
    </label>
  </fieldset>

  <!-- <div class="policy" id="policyText" aria-live="polite"></div> -->

  <div class="footer-actions">
    <button id="nextBtn" class="btn btn-primary" >التالي</button>
  </div>
</section>

<!-- Optional placeholder for Step 2; we’ll build it next -->
<section id="step2" class="card" dir="rtl" hidden>
  <h2 class="card-title">اختر نوع الطلب</h2>

  <!-- سطر تمهيدي قبل عرض المجموعات -->
  <p class="muted-intro">
    * هذه القائمة تضم المعاملات الأكثر انتشارًا ويطلبها عدد من المواطنيين بمنطقة التمثيل.
  </p>
  <p class="muted-intro">
    * في حالة أن المعاملة المطلوبة غير موجودة بالقوائم يُرجى مراجعة صالة خدمات الجمهور مباشرة.        
  </p>
  <p class="muted-intro">
    * جميع المعاملات تتطلب مستندات رسمية معينة تختلف باختلاف المعاملة
  </p>
  

  <span class="muted" id="groupCountBadge"></span>
  <div id="groupGrid" class="grid-cards" role="list" aria-label="المجموعات الرئيسية"></div>

  <div id="groupEmpty" class="empty" hidden>لا توجد مجموعات متاحة لهذه اللغة.</div>
<p></p>
  <div id="step2Summary" class="selection-summary"></div>
  <p></p>
  <div id="dealInfo" class="deal-info" hidden>
    <div class="deal-info-title">تفاصيل المعاملة:</div>
    <span id="dealInfoText"></span>
  </div>
  

  <div class="footer-actions">
    <button id="nextBtnStep2" class="btn btn-primary" disabled>التالي</button>
  </div>
</section>



<!-- Centered modal for altColName -->
<div id="altcolModal" class="modal-backdrop" hidden>
  <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="altcolTitle" dir="rtl">
    <header class="modal-header">
      <h3 id="altcolTitle" class="modal-title">اختر المجموعة الفرعية</h3>
      <button id="altcolClose" class="modal-close" aria-label="إغلاق">×</button>
    </header>
    <div class="modal-body">
      <p class="muted" id="altcolHint"></p>
      <div id="altcolGrid" class="grid-cards" role="list" aria-label="المجموعات الفرعية"></div>
      <div id="altcolEmpty" class="empty" hidden>لا توجد مجموعات فرعية متاحة.</div>
    </div>
  </div>
</div>

<!-- Centered modal for altSubColName -->
<div id="subcolModal" class="modal-backdrop" hidden>
  <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="subcolTitle" dir="rtl">
    <header class="modal-header">
      <h3 id="subcolTitle" class="modal-title">اختر النوع (altSubColName)</h3>
      <button id="subcolClose" class="modal-close" aria-label="إغلاق">×</button>
    </header>
    <div class="modal-body">
      <p class="muted" id="subcolHint"></p>
      <div id="subcolGrid" class="grid-cards" role="list" aria-label="الأنواع المتاحة"></div>
      <div id="subcolEmpty" class="empty" hidden>لا توجد أنواع متاحة.</div>
    </div>
  </div>
</div>

<section id="step3" class="card" dir="rtl" hidden>
  <h2 class="card-title">المستندات الداعمة</h2>
  <p class="muted">الرجاء تحميل المستندات المطلوبة قبل المتابعة.</p>

  <div id="reqList" class="req-list"></div>
  <div id="reqEmpty" class="empty" hidden>لا توجد مستندات مطلوبة لهذا النوع.</div>

  <!-- Extra supporting docs (up to 3) -->
  <div class="extra-block">
    <div class="extra-head">
      <span class="card-title-sm">مستندات داعمة إضافية (اختياري)</span>
      <button id="btnAddExtra" type="button" class="icon-btn" title="إضافة مستند">
        ➕
      </button>
    </div>
    <div id="extraList" class="extra-list"></div>
    <div id="extraNote" class="muted">يمكن إضافة حتى 3 مستندات داعمة إضافية (PDF/JPG/PNG/DOCX، بحد أقصى 3MB لكل ملف).</div>
  </div>

  <div class="footer-actions">
    <button id="nextBtnStep3" class="btn btn-primary" disabled>التالي</button>
  </div>
</section>

<section id="step4" class="card" dir="rtl" hidden>
  <h2 class="card-title">البيانات الشخصية</h2>
  

  <!-- Applicants -->
  <div class="party-section">
    <div class="party-head" >
      <div class="party-title">مقدم الطلب</div>
      <button id="btnAddApplicant" type="button" class="btn btn-primary btn-sm">+ إضافة متقدم</button>
    </div>
    <div id="listApplicants" class="party-list"></div>
  </div>

  <!-- Authenticated (موكّل/مصادِق): shown only when needed -->
  <div class="party-section" id="sectionAuth" hidden>
    <div class="party-head">
      <div class="party-title">الموكل</div>
      <button id="btnAddAuth" type="button" class="btn btn-primary btn-sm">+ إضافة موكل</button>
    </div>
    <div id="listAuth" class="party-list"></div>
  </div>

  <!-- Witnesses: shown only when needed -->
  <div class="party-section" id="sectionWitness" hidden>
    <div class="party-head">
      <div class="party-title">الشاهدان</div>
      <button id="btnAddWitness" type="button" class="btn btn-primary btn-sm">+ إضافة شاهد</button>
    </div>
    <div id="listWitnesses" class="party-list"></div>
    <div class="muted">مطلوب شاهدان بالضبط إذا كانت المعاملة تتطلب ذلك.</div>
  </div>

  <div class="footer-actions">
    <button id="saveStep4" class="btn btn-ghost">حفظ</button>
    <button id="nextBtnStep4" class="btn btn-primary" disabled>التالي</button>
  </div>
</section>

<!-- Reusable modal for person -->
<!-- Party Modal -->
<div id="partyModal" class="modal-backdrop" hidden>
  <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="partyModalTitle" dir="rtl">
    <header class="modal-header">
      <h3 id="partyModalTitle" class="modal-title">بيانات الشخص</h3>
      <button id="partyModalClose" class="modal-close" aria-label="إغلاق">×</button>
    </header>

    <div class="modal-body">
      <form id="partyForm" class="form-grid">
        <input type="hidden" id="pf_section"/>
        <input type="hidden" id="pf_index"/>

        <div class="field" id="fld_name">
            <label for="pf_name">الاسم رباعيا</label>
            <input id="pf_name" type="text" required />
            </div>

            <div class="field" id="fld_sex">
            <label class="legend" id="pf_sex_label">النوع</label>
            <div role="radiogroup" aria-labelledby="pf_sex_label">
                <label class="choice"><input type="radio" name="pf_sex" value="M"><span class="choice-label">ذكر</span></label>
                <label class="choice"><input type="radio" name="pf_sex" value="F"><span class="choice-label">أنثى</span></label>
            </div>
            </div>

            <div class="field" id="fld_job">
            <label for="pf_job">المهنة</label>
            <input id="pf_job" type="text"/>
            </div>

            <div class="field" id="fld_nat">
            <label for="pf_nationality">الجنسية</label>
            <input id="pf_nationality" type="text" value="سوداني" />
            </div>

            <div class="field" id="fld_res">
            <label class="legend" id="pf_res_label">حالة الإقامة</label>
            <div role="radiogroup" aria-labelledby="pf_res_label">
                <label class="choice"><input type="radio" name="pf_res" value="مقيم"><span class="choice-label">مقيم</span></label>
                <label class="choice"><input type="radio" name="pf_res" value="زائر"><span class="choice-label">زائر</span></label>
                <label class="choice"><input type="radio" name="pf_res" value="مواطن"><span class="choice-label">مواطن</span></label>
            </div>
            </div>

            <div class="field" id="fld_dob">
            <label for="pf_dob">تاريخ الميلاد</label>
            <input id="pf_dob" type="date"/>
            </div>

            <div class="field" id="fld_id_type">
            <label for="pf_id_type">نوع الوثيقة</label>
            <select id="pf_id_type" required>
                <option value="">نوع الهوية</option>
                <option value="جواز سفر">جواز سفر</option>
                <option value="رقم وطني">رقم وطني</option>
                <option value="إقامة">إقامة</option>
                <option value="أخرى">أخرى</option>
            </select>
            </div>

            <div class="field" id="fld_id_num">
            <label for="pf_id_number">رقم الهوية</label>
            <input id="pf_id_number" type="text" placeholder="رقم الهوية" required/>
            </div>

            <div class="field" id="fld_id_issuer">
            <label for="pf_id_issuer">مكان الإصدار</label>
            <input id="pf_id_issuer" type="text" placeholder="جهة/مكان الإصدار"/>
            </div>

            <div class="field" id="fld_id_exp">
            <label for="pf_id_expiry">تاريخ الانتهاء</label>
            <input id="pf_id_expiry" type="date" placeholder="تاريخ الانتهاء"/>
            </div>

      </form>
    </div>

    <footer class="modal-footer">
      <button id="partyModalSave" class="btn btn-primary">حفظ</button>
    </footer>
  </div>
</div>

<section id="step5" name="step5Feilds" class="card" dir="rtl" hidden>
  <h2 class="card-title">تفاصيل الطلب</h2>

  <!-- Role toggle (testing only) -->


  <p class="muted" id="fieldsNames" style="display: none">أكمل الحقول التالية حسب نوع المعاملة.</p>

  <!-- ✅ The form is always present for both roles -->
  <form id="step5Form" class="form-grid">
    <div id="step5Fields" class="fields-stack"></div>
  </form>

  <div class="footer-actions">
    <button id="nextBtnStep5" class="btn btn-primary">التالي</button>
  </div>
</section>

<section id="step5-emp" class="card" dir="rtl" hidden>
  
  <!-- 🧩 Employee-only extension (shown/hidden by JS; form stays visible) -->
  <div id="employeeTextPanel" class="card muted" style="display:none;margin-top:16px;padding:12px;border:1px solid var(--border,#e5e7eb);border-radius:10px;">
    <div style="display:flex;align-items:center;gap:12px;justify-content:space-between;margin-bottom:8px;">
      <h3 style="margin:0;font-size:1.05rem;">مراجعة النص النهائي:</h3>
      <label style="display:flex;align-items:center;gap:6px;">
        <input id="autoSyncEmpText" type="checkbox" checked />
        <span>تحديث تلقائي</span>
      </label>
    </div>

    
    
    <textarea id="empText" dir="rtl" style="width:100%;min-height:220px;padding:10px;border:1px solid var(--border,#e5e7eb);border-radius:8px;"></textarea>
    <span id="inform-user" style="disp[lay:none"></span>
    <h3 style="margin-top: 20px;font-size:1.05rem;">الموثق:</h3>
    <textarea id="authenticater_text" dir="rtl" style=" width:100%;min-height:70px;padding:10px;border:1px solid var(--border,#e5e7eb);border-radius:8px;"></textarea>

    <p class="muted" style="margin-top:8px;">ملاحظات:</p>
      <p class="muted" style="margin-top:8px;">
      يمكن تعديل النص يدوياً. عند تفعيل “تحديث تلقائي” سيتم إعادة البناء عند تغيير الحقول.
    </p>
    <p class="muted" style="margin-top:8px;">
      التعديل في اسماء جميع الاطراف المتعلقة بالمكاتبة لا يعتد بها وسيتم استخدام الاسماء التي تم ادخالها في نافذة البيانات الشخصية
    </p>
  </div>
  <h3 style="margin-top: 20px;font-size:1.05rem;">اضافة تعليق:</h3>
    <textarea id="comment_text" dir="rtl" style=" width:100%;min-height:70px;padding:10px;border:1px solid var(--border,#e5e7eb);border-radius:8px;"></textarea>


  <div class="footer-actions">
    <button id="nextBtnStep5Emp" class="btn btn-primary">طباعة المستند</button>
  </div>
</section>



<section id="step6" class="card" dir="rtl" hidden>
  <h2 class="card-title">الإرسال</h2>
  <p class="muted">اختر تاريخ الموعد والساعة، ثم الموافقة على الشروط لإرسال الطلب.</p>

  <form id="step6Form" class="form-grid" novalidate>
    <!-- Date -->
    <div class="field">
      <label for="apptDate">تاريخ الموعد *</label>
      <input id="apptDate" type="date" required />
      <div class="field-error" id="dtErr" hidden>رجاءً اختر التاريخ.</div>
    </div>

    <!-- Hours grid -->
    <div class="field" style="grid-column:1/-1">
      <label>الساعة المتاحة *</label>
      <div id="hourGrid" class="hour-grid"></div>
      <div class="field-error" id="hrErr" hidden>رجاءً اختر الساعة.</div>
    </div>

    <!-- Terms -->
    <div class="field" style="grid-column:1/-1">
      <div class="terms-box">
        <ul>
          <li>أقرّ بصحة البيانات المدخلة وأنها تخصني أو لدي تفويض قانوني بذلك.</li>
          <li>أوافق على استخدام مستنداتي للأغراض الرسمية الخاصة بالمعاملة.</li>
          <li>أتحمّل المسؤولية القانونية عند تقديم أي معلومات غير صحيحة.</li>
          <li>اتعهد بالحضور الى القنصلية لاكمال الاجراءات الخاصة بالمعاملة خلال الموعد الذي تم اختياره، علما بانه سيتم حذف جميع البيانات الخاصة بالمعاملة تلقائيا بعد مرور </li>
        </ul>
      </div>
      <div class="ack">
        <input type="checkbox" id="ackTerms" checked />
        <label for="ackTerms">أوافق على الشروط والأحكام المذكورة أعلاه.</label>
      </div>
      <div class="field-error" id="ackErr" hidden>يجب الموافقة على الشروط.</div>
    </div>
  </form>

  <div class="footer-actions">
    <button id="submitBtn" class="btn btn-primary" disabled>المتابعة</button>
  </div>
</section>

<!-- Step 6: Review + Print -->
<section id="step7" class="card" dir="rtl" hidden>
  <h2 class="card-title">المراجعة والطباعة</h2>

  <div id="caseHeader" class="case-header">
    
    <div class="meta">
      <div><strong>التاريخ:</strong> <span id="caseDate">—</span></div>
      <div><strong>اللغة:</strong> <span id="caseLang">—</span></div>
      <div><strong>المجموعة:</strong> <span id="caseMainGroup">—</span></div>
      <div><strong>النوع:</strong> <span id="caseAltSub">—</span></div>
    </div>
    <div class="case-id-block">
      <div class="uid-label">رقم المعاملة</div>
      <div id="caseUid" class="uid-value">—</div>
      <svg id="caseBarcode" class="barcode"></svg>
    </div>
  </div>

  <hr/>
    <div style="text-align: center;"><strong>يجب مراجعة صالة خدمات الجمهور خلال فترة لا تتجاوز (7) ايام من التاريخ الموضح أعلاه وخلال الفترة الزمنية التي تم اختيارها </strong></div>
    <div class="review-section">
    <h3>مقدم الطلب:</h3>
    <div id="reviewApplicants" class="party-review"></div>
    </div>

    <div class="review-section" id="secAuth" hidden>
    <h3>الموكَّل:</h3>
    <div id="reviewAuth" class="party-review"></div>
    </div>

    <div class="review-section" id="secWitness" hidden>
    <h3>الشاهدان:</h3>
    <div id="reviewWitnesses" class="party-review"></div>
    </div>


  <div class="review-section">
    <h3>البيانات المطلوبة:</h3>
    <div id="reviewAnswers" class="answers-grid"></div>
  </div>

  <div class="review-section">
    <h3>المستندات المرفوعة (يجب احضارها عند المراجعة):</h3>
    <div id="reviewFiles" class="files-grid"></div>
  </div>

  <div class="footer-actions">
    <button id="btnPrint" class="btn">طباعة / حفظ PDF</button>
    <button id="nextBtnStep7" class="btn btn-primary">تسليم الطلب</button>
  </div>
  <div class="review-section">
    <h3>العنوان</h3>
    <div id="office-Addres" class="files-grid">القنصلية العامة لجمهورية السودان بجدة، حي النهضة</div>
  </div>
</section>

<section id="step7" class="card" dir="rtl" hidden>
  <h2 class="card-title">المراجعة والطباعة</h2>

  <div id="caseHeader" class="case-header">
    
    <div class="meta">
      <div><strong>التاريخ:</strong> <span id="caseDate">—</span></div>
      <div><strong>اللغة:</strong> <span id="caseLang">—</span></div>
      <div><strong>المجموعة:</strong> <span id="caseMainGroup">—</span></div>
      <div><strong>النوع:</strong> <span id="caseAltSub">—</span></div>
    </div>
    <div class="case-id-block">
      <div class="uid-label">رقم المعاملة</div>
      <div id="caseUid" class="uid-value">—</div>
      <svg id="caseBarcode" class="barcode"></svg>
    </div>
  </div>

  <hr/>
    <div style="text-align: center;"><strong>يجب مراجعة صالة خدمات الجمهور خلال فترة لا تتجاوز (7) ايام من التاريخ الموضح أعلاه وخلال الفترة الزمنية التي تم اختيارها </strong></div>
    <div class="review-section">
    <h3>مقدم الطلب:</h3>
    <div id="reviewApplicants" class="party-review"></div>
    </div>

    <div class="review-section" id="secAuth" hidden>
    <h3>الموكَّل:</h3>
    <div id="reviewAuth" class="party-review"></div>
    </div>

    <div class="review-section" id="secWitness" hidden>
    <h3>الشاهدان:</h3>
    <div id="reviewWitnesses" class="party-review"></div>
    </div>


  <div class="review-section">
    <h3>البيانات المطلوبة:</h3>
    <div id="reviewAnswers" class="answers-grid"></div>
  </div>

  <div class="review-section">
    <h3>المستندات المرفوعة (يجب احضارها عند المراجعة):</h3>
    <div id="reviewFiles" class="files-grid"></div>
  </div>

  <div class="footer-actions">
    <button id="btnPrint" class="btn">طباعة / حفظ PDF</button>
    <button id="nextBtnStep7" class="btn btn-primary">تسليم الطلب</button>
  </div>
  <div class="review-section">
    <h3>العنوان</h3>
    <div id="office-Addres" class="files-grid">القنصلية العامة لجمهورية السودان بجدة، حي النهضة</div>
  </div>
</section>
<div id="qrPreview" style="direction:ltr; text-align:right; margin-top:12px; display: flex;"></div>




<script src="static/app.js"></script>
<script src="static/employee_steps.js"></script>

</body>
</html>
