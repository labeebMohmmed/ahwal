<?php
// If you already have a header include / auth check, require it here.
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="x-ua-compatible" content="ie=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="static/style.css"/>
  <title>لوحة التحكم — النماذج</title>
  
</head>
<!-- Drawer (right side) -->
<div id="drawerBackdrop" class="drawer-backdrop" hidden></div>
<aside id="drawer" class="drawer" aria-hidden="true" aria-labelledby="drawerTitle" tabindex="-1">
  <header class="drawer-header">
    <h2 id="drawerTitle">تفاصيل النموذج رقم: </h2>
    <button id="drawerClose" class="btn" type="button" aria-label="إغلاق">✖</button>
  </header>
  <div id="drawerBody" class="drawer-body">
    <!-- form grid injected here -->
  </div>
  <footer class="drawer-footer">
    <button id="btnEdit" class="btn" type="button">تعديل</button>
    <button id="btnSave" class="btn" type="button" hidden>حفظ</button>
    <button id="btnCancel" class="btn" type="button" hidden>إلغاء</button>
    <button id="btnDelete" class="btn" type="button">حذف</button>
    <button id="btnCopyJson" class="btn" type="button">نسخ JSON</button>
    <button id="btnClose2" class="btn" type="button">إغلاق</button>
  </footer>

</aside>

<body>
  <div class="container">
    

    <div class="toolbar">
      <button id="btnNew" class="btn">➕ سجل جديد</button>
      <button id="btnSchema" class="btn">🛠️ إدارة الأعمدة</button>

      <input id="q" type="search" placeholder="بحث في النماذج…" aria-label="بحث" />
      <button id="btnSearch" class="btn">بحث</button>
      <span id="status" class="muted"></span>
    </div>

    <div class="card">
      <div class="card-body">
        <div style="min-height: 320px; overflow:auto;">
          <table id="grid">
            <thead></thead>
            <tbody></tbody>
          </table>
        </div>

        <div class="pager">
          <button id="prev" class="btn">السابق</button>
          <button id="next" class="btn">التالي</button>
          <span id="pageInfo" class="muted"></span>
        </div>
      </div>
    </div>
  </div>
  <!-- Schema Modal -->
<div id="schemaBackdrop" class="drawer-backdrop" hidden></div>
<aside id="schemaModal" class="drawer" aria-hidden="true" style="width:min(560px,100%); left:auto; right:0;">
  <header class="drawer-header">
    <h2>إدارة الأعمدة (TableAddModel)</h2>
    <button id="schemaClose" class="btn" type="button">✖</button>
  </header>
  <div class="drawer-body">
    <section style="margin-bottom:16px;">
      <h3 style="margin:6px 0;">إضافة عمود</h3>
      <div class="form-grid">
        <label for="ddlAddName">الاسم (قائمة مسموحة)</label>
        <select id="ddlAddName"></select>

        <label for="inLength">الطول (لـ NVARCHAR)</label>
        <input id="inLength" type="number" min="1" placeholder="مثال: 255" />

        <label for="inTypeSpec">نوع مخصص (اختياري)</label>
        <input id="inTypeSpec" type="text" placeholder="NVARCHAR(255) / INT / DATE ..." />
      </div>
      <div style="margin-top:8px;">
        <button id="btnDoAdd" class="btn">➕ إضافة</button>
      </div>
    </section>

    <section>
      <h3 style="margin:6px 0;">الأعمدة الحالية (قابلة للحذف)</h3>
      <div id="dropList"></div>
    </section>
  </div>
  <footer class="drawer-footer">
    <button id="schemaClose2" class="btn" type="button">إغلاق</button>
  </footer>
</aside>

 <script src="static/app.js"></script>
</body>
</html>
