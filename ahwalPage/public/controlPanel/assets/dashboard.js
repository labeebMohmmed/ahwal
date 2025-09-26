let currentReportData = {};
let reportType = "";
document.addEventListener("DOMContentLoaded", () => {
    const nav = document.querySelector("nav");
    if (!nav) return;

    nav.querySelectorAll("a[data-page]").forEach(link => {
        link.addEventListener("click", () => {
            nav.querySelectorAll("a").forEach(a => a.classList.remove("active-link"));
            link.classList.add("active-link");
        });
    });
});

// Helper: show styled notification
function showMessage(msg, type = "info") {
    // Create or reuse container
    let box = document.getElementById("notif-box");
    if (box) {
        box = document.createElement("div");
        box.id = "notif-box";
        document.body.appendChild(box);
        Object.assign(box.style, {
            position: "fixed",
            top: "10px",
            right: "10px",
            padding: "10px 15px",
            borderRadius: "8px",
            color: "#fff",
            fontWeight: "bold",
            zIndex: 9999,
            boxShadow: "0 2px 6px rgba(0,0,0,0.2)",
            transition: "opacity 0.3s ease"
        });
    }

    // Apply color based on type
    if (type === "success") {
        box.style.background = "#16a34a"; // green
    } else if (type === "error") {
        box.style.background = "#dc2626"; // red
    } else {
        box.style.background = "#2563eb"; // blue
    }

    // Set message and show
    box.textContent = msg;
    box.style.opacity = "1";

    // Auto-hide after 3s
    setTimeout(() => {
        box.style.opacity = "0";
    }, 3000);
}




// Fetch combo data once and store it globally
fetch('../api_list_combo.php')
    .then(res => res.json())
    .then(j => {
        if (j.ok) {
            window.comboData = j;

            // flatten extra lists
            const mandoubs = [];
            const arabCountries = [];
            const foreignCountries = [];

            (j.comboRows || []).forEach(r => {
                if (r.MandoubNames) mandoubs.push(r.MandoubNames);
                if (r.ArabCountries) arabCountries.push(r.ArabCountries);
                if (r.ForiegnCountries) foreignCountries.push(r.ForiegnCountries);
            });

            window.comboData.mandoubs = mandoubs;
            window.comboData.arabCountries = arabCountries;
            window.comboData.foreignCountries = foreignCountries;

            // diplomats + settings already in payload
            window.comboData.diplomats = j.diplomats || [];
            window.comboData.settings = j.settings || [];

            console.log("✅ Combo data loaded", window.comboData.settings);
        } else {
            console.error("❌ Failed to load combo data", j.error);
        }
    })
    .catch(err => console.error("API error:", err));

function ControlPanel(main) {
    console.log('buildControlPanel');

    if (!main) {
        console.error("❌ main container not provided");
        return;
    }

    main.innerHTML = "";

    const card = document.createElement("div");
    card.className = "card";

    const title = document.createElement("h2");
    title.className = "card-title";
    title.textContent = "اختر الدبلوماسي";
    card.appendChild(title);

    const sel = document.createElement("select");
    sel.id = "diplomat-select";

    const diplomats = window.comboData?.diplomats || [];

    diplomats.forEach((d, i) => {
        const opt = document.createElement("option");
        opt.value = i;
        opt.textContent = d.EmployeeName;
        sel.appendChild(opt);
    });

    const savedIndex = window.comboData.settings[0]?.VCIndesx;
    if (savedIndex != null && sel.options[savedIndex]) {
        sel.value = savedIndex;
    }

    // Save to LS helpers
    set(LS.ar_diplomat, diplomats[savedIndex]?.EmployeeName || "");
    set(LS.en_diplomat, diplomats[savedIndex]?.EngEmployeeName || "");
    set(LS.ar_diplomat_job, diplomats[savedIndex]?.AuthenticType || "");
    set(LS.en_diplomat_job, diplomats[savedIndex]?.AuthenticTypeEng || "");

    sel.addEventListener("change", () => {
        const newIndex = parseInt(sel.value, 10);
        fetch("/api_update_vcindex.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ VCIndesx: newIndex }),
        })
            .then(res => res.json())
            .then(j => {
                if (j.ok) {
                    console.log("✅ Saved diplomat index", newIndex);
                    window.comboData.settings[0].VCIndesx = newIndex;
                    set(LS.ar_diplomat, diplomats[newIndex].EmployeeName);
                    set(LS.en_diplomat, diplomats[newIndex].EngEmployeeName);
                    set(LS.ar_diplomat_job, diplomats[newIndex].AuthenticType);
                    set(LS.en_diplomat_job, diplomats[newIndex].AuthenticTypeEng);
                } else {
                    console.error("❌ Failed to save diplomat index", j.error);
                }
            })
            .catch(err => console.error("❌ Error:", err));
    });

    card.appendChild(sel);
    main.appendChild(card);
}

function usersControl(main) {
    console.log("⚡ Users tab clicked");
    fetch("api_users.php")
        .then(res => res.json())
        .then(j => {
            if (!j.ok) {
                main.innerHTML = `<div class="card"><p>❌ ${j.error}</p></div>`;
                return;
            }
            console.log(j);
            const currentUserId = j.currentUserId;
            const accountType = j.accountType;

            if (accountType === "مدير نظام") {
                // === Admin view: manage all users ===
                let html = `
                  <div class="card">
                    <h2 class="card-title">إدارة المستخدمين</h2>
                    <table class="table">
                      <thead>
                        <tr>
                          <th>الاسم</th>
                          <th>معتمد</th>
                          <th>الوظيفة</th>
                          <th>رئيس البعثة</th>
                          <th>كلمة المرور</th>
                        </tr>
                      </thead>
                      <tbody>
                `;


                j.users.forEach(u => {
                    const isApproved = (u["نشاط_الحساب"] === "نشط");
                    html += `
                        <tr data-id="${u.ID}">
                        <td contenteditable="true" data-field="EmployeeName">${u.EmployeeName || ""}</td>
                        <td>
                            <select data-field="نشاط_الحساب">
                                <option value="نشط" ${isApproved ? "selected" : ""}>نشط</option>
                                <option value="غير نشط" ${!isApproved ? "selected" : ""}>غير نشط</option>
                            </select>
                        </td>
                        <td contenteditable="true" data-field="JobPosition">${u.JobPosition || ""}</td>
                        <td>
                            <select data-field="headOfMission">
                                <option value="yes" ${u.headOfMission === "yes" ? "selected" : ""}>نعم</option>
                                <option value="no" ${u.headOfMission === "no" ? "selected" : ""}>لا</option>
                            </select>
                        </td>
                        <td><button class="reset-pass">🔑 Reset</button></td>
                        </tr>`;
                });

                html += `</tbody></table></div>`;
                main.innerHTML = html;

                // === Attach inline update handlers ===
                main.querySelectorAll("td[contenteditable]").forEach(el => {
                    el.addEventListener("blur", () => {
                        const tr = el.closest("tr");
                        const id = tr.dataset.id;
                        const field = el.dataset.field;
                        const value = el.innerText.trim();

                        fetch("api_update_user.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
                            body: JSON.stringify({ id, field, value }),
                        })
                            .then(r => r.json())
                            .then(j => {
                                if (!j.ok) alert("❌ Update failed: " + j.error);
                            });
                    });
                });

                main.querySelectorAll("select[data-field]").forEach(sel => {
                    sel.addEventListener("change", () => {
                        const tr = sel.closest("tr");
                        const id = tr.dataset.id;
                        const field = sel.dataset.field;
                        const value = sel.value;

                        fetch("api_update_user.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
                            body: JSON.stringify({ id, field, value }),
                        })
                            .then(r => r.json())
                            .then(j => {
                                if (!j.ok) alert("❌ Update failed: " + j.error);
                            });
                    });
                });


                // === Attach reset password handlers ===
                main.querySelectorAll(".reset-pass").forEach(btn => {
                    btn.addEventListener("click", () => {
                        const id = btn.closest("tr").dataset.id;
                        fetch("api_reset_password.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
                            body: JSON.stringify({ id }),
                        })
                            .then(r => r.json())
                            .then(j => {
                                if (j.ok) {
                                    alert("🔑 New password: " + j.newPassword);
                                } else {
                                    alert("❌ Failed: " + j.error);
                                }
                            });
                    });
                });

            } else {
                // === Non-admin: personal account view ===
                const me = j.users.find(u => u.ID == currentUserId);
                if (!me) {
                    main.innerHTML = `<div class="card"><p>❌ لم يتم العثور على حسابك.</p></div>`;
                    return;
                }
                const isMale = me["Gender"] === "ذكر";
                const isDiplomat = me["الدبلوماسيون"] === "yes";
                const isAuthorized = me["مأذون"] === "yes";
                const isHeadMission = me["headOfMission"] === "head";
                const isActive = me["نشاط_الحساب"] === "نشط";

                let html = `
                    <div class="card">
                    <h2 class="card-title">إدارة الحساب</h2>
                    <form id="account-form" enctype="multipart/form-data">

                        <label>الاسم بالعربية
                        <input type="text" name="EmployeeName" value="${me.EmployeeName || ""}">
                        </label>

                        <label>الاسم بالانجليزية
                        <input type="text" name="EngEmployeeName" dir="ltr" value="${me.EngEmployeeName || ""}">
                        </label>

                        <fieldset>
                        <legend>الجنس</legend>
                        <label><input type="radio" name="Gender" value="ذكر" ${isMale ? "checked" : ""}> ذكر</label>
                        <label><input type="radio" name="Gender" value="انثى" ${!isMale ? "checked" : ""}> أنثى</label>
                        </fieldset>

                        <fieldset>
                        <legend>الدبلوماسيون</legend>
                        <label><input type="radio" name="الدبلوماسيون" value="yes" ${isDiplomat ? "checked" : ""}> نعم</label>
                        <label><input type="radio" name="الدبلوماسيون" value="no" ${!isDiplomat ? "checked" : ""}> لا</label>
                        </fieldset>

                        <fieldset>
                        <legend>مأذون</legend>
                        <label><input type="radio" name="مأذون" value="yes" ${isAuthorized ? "checked" : ""}> نعم</label>
                        <label><input type="radio" name="مأذون" value="no" ${!isAuthorized ? "checked" : ""}> لا</label>
                        </fieldset>

                        <label>المسمى الوظيفي
                        <select name="JobPosition">
                            <option value="">-- اختر --</option>
                            <option ${me.JobPosition === "القنصل العام" ? "selected" : ""}>القنصل العام</option>
                            <option ${me.JobPosition === "القنصل العام بالإنابة" ? "selected" : ""}>القنصل العام بالإنابة</option>
                            <option ${me.JobPosition === "نائب قنصل" ? "selected" : ""}>نائب قنصل</option>
                            <option ${me.JobPosition === "ملحق إداري" ? "selected" : ""}>ملحق إداري</option>
                            <option ${me.JobPosition === "تعين محلي" ? "selected" : ""}>تعين محلي</option>
                            <option ${me.JobPosition === "مندوب جالية" ? "selected" : ""}>مندوب جالية</option>
                            <option ${me.JobPosition === "محاسب" ? "selected" : ""}>محاسب</option>
                            <option ${me.JobPosition === "مدير مالي" ? "selected" : ""}>مدير مالي</option>
                            <option ${me.JobPosition === "السفير" ? "selected" : ""}>السفير</option>
                            <option ${me.JobPosition === "اخرى" ? "selected" : ""}>اخرى</option>
                        </select>
                        </label>

                        <fieldset>
                            <legend>رئيس البعثة</legend>
                            <label><input type="radio" name="headOfMission" value="head" ${isHeadMission ? "checked" : ""}> نعم</label>
                            <label><input type="radio" name="headOfMission" value="other" ${!isHeadMission ? "checked" : ""}> لا</label>
                        </fieldset>
                            
                        </fieldset>
                        <label>اسم المستخدم (انجليزي فقط)
                            <input type="text" name="UserName" value="${me.UserName || ""}">
                        </label>
                        </fieldset>

                        </fieldset>                        
                        <label>البريد الإلكتروني
                            <input type="email" name="Email" value="${me.Email || ""}">
                        </label>
                        </fieldset>

                        </fieldset>                        
                        <label>رقم الهاتف
                            <input type="text" name="PhoneNo" value="${me.PhoneNo || ""}">
                        </label>
                        </fieldset>

                        </fieldset>                        
                        <label>اللقب بالعربية
                            <input type="text" name="AuthenticType" value="${me.AuthenticType || ""}">
                        </label>
                        </fieldset>
                        
                        </fieldset>                        
                        <label>اللقب بالانجليزية
                            <input type="text" name="AuthenticTypeEng" value="${me.AuthenticTypeEng || ""}">
                        </label>
                        </fieldset>

                        <fieldset>
                            <legend>نشاط الحساب</legend>
                            <label>
                                <input type="radio" name="نشاط_الحساب" value="نشط" ${isActive ? "checked" : ""}> نشط
                            </label>
                            <label>
                                <input type="radio" name="نشاط_الحساب" value="غير نشط" ${!isActive ? "checked" : ""}> غير نشط
                            </label>
                        </fieldset>


                        <fieldset class="file-upload">
                            <legend>التوقيع الرقمي</legend>

                            <input type="file" name="Data1" id="fileInput" accept=".png,.jpg,.jpeg">
                            <label for="fileInput" class="upload-btn">📂 اختر ملف</label>
                            <span id="file-name" class="file-name">لم يتم اختيار ملف</span>

                            ${me.Data1
                        ? `<p class="file-status">✅ توقيع محفوظ بالفعل</p>`
                        : `<p class="file-status muted">لم يتم رفع توقيع بعد</p>`}
                        </fieldset>


                        <fieldset class="actions">
                            <button type="submit" class="primary"> حفظ التعديلات</button>
                        </fieldset>
                        </form>
                    </div>
                    `;

                main.innerHTML = html;

                // attach form submit
                const form = document.getElementById("account-form");
                if (form) {
                    form.addEventListener("submit", e => {
                        e.preventDefault();
                        const formData = new FormData(form);
                        formData.append("id", currentUserId);

                        fetch("api_update_user.php", {
                            method: "POST",
                            body: formData
                        })
                            .then(r => r.json())
                            .then(j => {
                                if (j.requireConfirm) {
                                    if (confirm(j.msg)) {
                                        formData.append("confirmSensitive", "1");
                                        return fetch("api_update_user.php", { method: "POST", body: formData })
                                            .then(r => r.json());
                                    } else {
                                        return { ok: false, error: "تم إلغاء التعديل" };
                                    }
                                }
                                return j;
                            })
                            .then(j => {
                                if (j.ok) {
                                    if (j.logout) {
                                        alert(j.msg);
                                        window.location.href = "../login.php?disabled=1";
                                    } else if (j.disabled) {
                                        alert("✅ تم التحديث. ⚠️ الحساب غير نشط حتى اعتماده.");
                                    } else {
                                        alert("✅ تم تحديث بياناتك");
                                    }
                                } else {
                                    // fallback: show error if exists, else a generic msg
                                    alert("❌ فشل: " + (j.error || j.msg || "خطأ غير معروف"));
                                }
                            })
                            .catch(err => {
                                alert("❌ API Error: " + err);
                            });
                    });
                }
            }

        })
        .catch(err => {
            console.error("❌ API error:", err);
            main.innerHTML = `<div class="card"><p>❌ API Error: ${err}</p></div>`;
        });
    console.log("Rendered HTML:", main.innerHTML);

}

function mandoubControl(main) {

    function showMandoubInsertPanel(main) {
        // Remove if already exists
        const old = document.getElementById("mandoub-insert-panel");
        if (old) old.remove();

        const panel = document.createElement("div");
        panel.id = "mandoub-insert-panel";
        panel.innerHTML = `
            <div class="panel-overlay"></div>
            <div class="panel-content">
                <h2 class="card-title">➕ إضافة مندوب جديد</h2>
                <form id="mandoub-insert-form" enctype="multipart/form-data">
                <!-- fields same as before -->
                <fieldset>
                    <legend>البيانات الأساسية</legend>
                    <label>اسم المندوب
                    <input type="text" name="MandoubNames" required>
                    </label>
                    <label>الهاتف
                    <input type="text" name="MandoubPhones">
                    </label>
                    <label>المنطقة
                    <input type="text" name="MandoubAreas">
                    </label>
                </fieldset>

                <fieldset>
                    <legend>مواعيد الحضور</legend>
                    <label>
                    <select name="مواعيد_الحضور">
                        <option value="">اختر</option>
                        <option value="السبت">السبت</option>
                        <option value="الأحد">الأحد</option>
                        <option value="الاثنين">الاثنين</option>
                        <option value="الثلاثاء">الثلاثاء</option>
                        <option value="الأربعاء">الأربعاء</option>
                        <option value="الخميس">الخميس</option>
                        <option value="الجمعة">الجمعة</option>
                    </select>
                    </label>
                </fieldset>

                <fieldset>
                    <legend>الصفة</legend>
                    <label>
                    <input type="radio" name="الصفة" value="رئيس"> رئيس
                    </label>
                    <label>
                    <input type="radio" name="الصفة" value="عضو"> عضو
                    </label>
                </fieldset>

                <fieldset>
                    <legend>وضع المندوب</legend>
                    <label>
                    <input type="radio" name="وضع_المندوب" value="مفعل"> مفعل
                    </label>
                    <label>
                    <input type="radio" name="وضع_المندوب" value="معطل"> معطل
                    </label>
                </fieldset>

                <fieldset>
                    <legend>بيانات إضافية</legend>
                    <label>رقم الجواز
                    <input type="text" name="رقم_الجواز">
                    </label>
                    <label>ملاحظات
                    <textarea name="comment"></textarea>
                    </label>
                </fieldset>

                <fieldset class="file-upload">
                    <legend>المرفقات</legend>
                    <input type="file" name="Data1" id="fileInput" accept=".png,.jpg,.jpeg">
                    <label for="fileInput" class="upload-btn">📂 اختر ملف</label>
                    <span id="file-name" class="file-name">لم يتم اختيار ملف</span>
                    <p class="file-status muted">يمكن رفع خطاب تكليف المندوب هنا</p>
                </fieldset>

                <fieldset class="panel-actions">
                <button type="submit" class="primary"> حفظ</button>
                
            </fieldset>
            
            </form>
        </div>
            `;
        main.appendChild(panel);

        // Close panel

        panel.querySelector(".panel-overlay").onclick = () => panel.remove();

        // File input update
        const fileInput = panel.querySelector("#fileInput");
        const fileNameSpan = panel.querySelector("#file-name");
        if (fileInput && fileNameSpan) {
            fileInput.addEventListener("change", () => {
                fileNameSpan.textContent = fileInput.files.length > 0
                    ? fileInput.files[0].name
                    : "لم يتم اختيار ملف";
            });
        }

        // Handle form submit
        const form = panel.querySelector("#mandoub-insert-form");
        form.addEventListener("submit", e => {
            e.preventDefault();
            const formData = new FormData(form);

            showMessage("⏳ جاري الحفظ...", "info"); // temporary status

            fetch("api_insert_mandoub.php", {
                method: "POST",
                body: formData
            })
                .then(r => r.json())
                .then(j => {
                    if (j.ok) {
                        showMessage("✅ تمت إضافة المندوب الجديد", "success");
                        panel.remove();
                        mandoubControl(main); // reload table inside main
                    } else {
                        showMessage("❌ فشل الإضافة: " + j.error, "error");
                    }
                })
                .catch(err => {
                    showMessage("⚠️ خطأ في الاتصال: " + err, "error");
                });
        });

    }

    fetch("api_mandoub.php")
        .then(res => res.json())
        .then(j => {
            if (!j.ok) {
                main.innerHTML = `<div class="card"><p>❌ ${j.error}</p></div>`;
                return;
            }

            let html = `
              <div class="card">
                <h2 class="card-title">إدارة المندوبين</h2>
                <button id="add-mandoub" class="primary">➕ إضافة مندوب جديد</button>
                <table class="table">
                  <thead>
                    <tr>
                      <th>الاسم</th>
                      <th>الهاتف</th>
                      <th>المنطقة</th>
                      <th>مواعيد الحضور</th>
                      <th>الصفة</th>
                      <th>الوضع</th>
                      <th>رقم الجواز</th>
                      <th>ملاحظات</th>
                    </tr>
                  </thead>
                  <tbody>
            `;

            j.mandoub.forEach(m => {
                html += `
                    <tr data-id="${m.ID}">
                        <td contenteditable="true" data-field="MandoubNames">${m.MandoubNames || ""}</td>
                        <td contenteditable="true" data-field="MandoubPhones">${m.MandoubPhones || ""}</td>
                        <td contenteditable="true" data-field="MandoubAreas">${m.MandoubAreas || ""}</td>
                        
                        <td>
                        <select data-field="مواعيد_الحضور">
                            <option value="" ${!m["مواعيد_الحضور"] ? "selected" : ""}>اختر</option>
                            <option value="السبت"   ${m["مواعيد_الحضور"] === "السبت" ? "selected" : ""}>السبت</option>
                            <option value="الأحد"   ${m["مواعيد_الحضور"] === "الأحد" ? "selected" : ""}>الأحد</option>
                            <option value="الاثنين" ${m["مواعيد_الحضور"] === "الاثنين" ? "selected" : ""}>الاثنين</option>
                            <option value="الثلاثاء" ${m["مواعيد_الحضور"] === "الثلاثاء" ? "selected" : ""}>الثلاثاء</option>
                            <option value="الأربعاء" ${m["مواعيد_الحضور"] === "الأربعاء" ? "selected" : ""}>الأربعاء</option>
                            <option value="الخميس"  ${m["مواعيد_الحضور"] === "الخميس" ? "selected" : ""}>الخميس</option>
                            <option value="الجمعة"  ${m["مواعيد_الحضور"] === "الجمعة" ? "selected" : ""}>الجمعة</option>
                        </select>
                        </td>
                        
                        <td>
                        <select data-field="الصفة">
                            <option value="" ${!m["الصفة"] ? "selected" : ""}>اختر</option>
                            <option value="رئيس" ${m["الصفة"] === "رئيس" ? "selected" : ""}>رئيس</option>
                            <option value="عضو" ${m["الصفة"] === "عضو" ? "selected" : ""}>عضو</option>
                        </select>
                        </td>
                        
                        <td>
                        <select data-field="وضع_المندوب">
                            <option value="" ${!m["وضع_المندوب"] ? "selected" : ""}>اختر</option>
                            <option value="الحساب مفعل" ${m["وضع_المندوب"] === "الحساب مفعل" ? "selected" : ""}>الحساب مفعل</option>
                            <option value="معطل" ${m["وضع_المندوب"] === "معطل" ? "selected" : ""}>معطل</option>
                        </select>
                        </td>
                        
                        <td contenteditable="true" data-field="رقم_الجواز">${m["رقم_الجواز"] || ""}</td>
                        <td contenteditable="true" data-field="comment">${m.comment || ""}</td>
                    </tr>
                    `;

            });

            html += `</tbody></table></div>`;
            main.innerHTML = html;

            // === Inline update handlers ===
            main.querySelectorAll("td[contenteditable]").forEach(el => {
                el.addEventListener("blur", () => {
                    const tr = el.closest("tr");
                    const id = tr.dataset.id;
                    const field = el.dataset.field;
                    const value = el.innerText.trim();

                    fetch("api_update_mandoub.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ id, field, value }),
                    })
                        .then(r => r.json())
                        .then(j => {
                            if (!j.ok) alert("❌ Update failed: " + j.error);
                        });
                });
            });

            // === Inline update for editable cells ===
            main.querySelectorAll("td[contenteditable]").forEach(el => {
                el.addEventListener("blur", () => {
                    const tr = el.closest("tr");
                    const id = tr.dataset.id;
                    const field = el.dataset.field;
                    const value = el.innerText.trim();

                    fetch("api_update_mandoub.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ id, field, value }),
                    })
                        .then(r => r.json())
                        .then(j => {
                            if (j.ok) {
                                showMessage("✅ تم الحفظ بنجاح", "success");
                            } else {
                                showMessage("❌ فشل التحديث: " + j.error, "error");
                            }
                        })
                        .catch(err => {
                            showMessage("⚠️ خطأ في الاتصال: " + err, "error");
                        });
                });
            });

            // === Insert new mandoub ===
            document.getElementById("add-mandoub").addEventListener("click", () => {
                showMandoubInsertPanel(main);
            });
        });
}

document.addEventListener("DOMContentLoaded", () => {
    const fileInput = document.getElementById("fileInput");
    const fileName = document.getElementById("file-name");
    if (fileInput && fileName) {
        fileInput.addEventListener("change", () => {
            fileName.textContent = fileInput.files.length > 0
                ? fileInput.files[0].name
                : "لم يتم اختيار ملف";
        });
    }
});


// === Example: default table renderer ===
function showDefaultTable(main) {
    // replace with your actual fetch + render
    main.innerHTML =
        `<div class="card"> 
        <h2 class="card-title">قائمة التطبيقات</h2> 
        <p class="muted">سيتم عرض قائمة التطبيقات هنا.</p> 
    </div>;`
}
// === Example: default table renderer ===
function officeCasesControl(main) {
    fetch("api_office_not_complete_cases_list.php")
        .then(res => res.json())
        .then(j => {
            if (!j.ok) {
                main.innerHTML = `<div class="card"><p>❌ ${j.error}</p></div>`;
                return;
            }

            let html = `
              <div class="card">
                <h2 class="card-title">قائمة المعاملات غير المكتملة</h2>
                <table class="table">
                  <thead>
                    <tr>
                      <th>الرقم</th>
                      <th>اسم مقدم الطلب</th>                      
                      <th>المجموعة</th>
                      <th>التاريخ</th>
                      <th>الحالة</th>
                      <th>الطريقة</th>
                    </tr>
                  </thead>
                  <tbody>
            `;

            j.cases.forEach(c => {
                html += `
                  <tr>
                    <td>${c.OfficeNumber || ""}</td>
                    <td>${c.ApplicantName || ""}</td>                    
                    <td>${c.MainGroup || ""}</td>
                    <td>${c.Date || ""}</td>
                    <td>${c.StatusTag || c.ArchStatus || ""}</td>
                    <td>${c.MethodTag || c.Method || ""}</td>
                  </tr>
                `;
            });

            html += `</tbody></table></div>`;
            main.innerHTML = html;
        })
        .catch(err => {
            main.innerHTML = `<div class="card"><p>❌ API Error: ${err}</p></div>`;
        });
}

function consularDocsControl(main) {
    console.log("⚡ Consular Docs tab opened");

    main.innerHTML = `
    <div class="card">
      <h2 class="card-title">📑 المعاملات القنصلية</h2>
      <div class="filter-bar">
        <input type="text" id="docNumber" placeholder="🔍 رقم التوكيل">
        <input type="text" id="applicantName" placeholder="👤 اسم مقدم الطلب">
        <input type="date" id="dateFrom">
        <input type="date" id="dateTo">
      </div>
      <div id="consularDocsResult">
        <p class="muted">⏳ يتم تحميل البيانات...</p>
      </div>
      <div id="caseFilesContainer"></div>
    </div>
  `;

    const searchInputNum = main.querySelector("#docNumber");
    const searchInputName = main.querySelector("#applicantName");
    const dateFrom = main.querySelector("#dateFrom");
    const dateTo = main.querySelector("#dateTo");
    const resultDiv = main.querySelector("#consularDocsResult");
    const filesDiv = main.querySelector("#caseFilesContainer");

    async function fetchDocs() {
        const num = encodeURIComponent(searchInputNum.value.trim());
        const name = encodeURIComponent(searchInputName.value.trim());
        const from = encodeURIComponent(dateFrom.value);
        const to = encodeURIComponent(dateTo.value);

        resultDiv.innerHTML = `<p class="muted">⏳ جاري التحميل...</p>`;
        filesDiv.innerHTML = "";

        const res = await fetch(
            `api_office_concular_docs.php?officeNumber=${num}&applicantName=${name}&dateFrom=${from}&dateTo=${to}`
        );
        const j = await res.json();

        if (!j.ok) {
            resultDiv.innerHTML = `<p class="error">❌ ${j.error}</p>`;
            return;
        }

        if (!j.cases || j.cases.length === 0) {
            resultDiv.innerHTML = `<p class="muted">⚠️ لا توجد نتائج</p>`;
            return;
        }

        let html = `
        <table class="table">
            <thead>
            <tr>
                <th>الرقم</th>
                <th>اسم مقدم الطلب</th>
                <th>التاريخ</th>
                <th>المجموعة</th>
                <th>الحالة</th>
                <th>الطريقة</th>
                <th>الإجراءات</th>
            </tr>
            </thead>
            <tbody>
        `;

        j.cases.forEach(c => {
            const table = c.OfficeTable === "Auth" ? "TableAuth" : "TableCollection";

            html += `
            <tr>
            <td>${c.OfficeNumber || ""}</td>
            <td>${c.ApplicantName || ""}</td>
            <td>${c.Date || ""}</td>
            <td>${c.MainGroup || ""}</td>
            <td>${c.StatusTag || c.ArchStatus || ""}</td>
            <td>${c.MethodTag || c.Method || ""}</td>
            <td>
                <button class="btn-xs btn-ghost show-files" data-id="${c.OfficeId}" data-table="${table}">مستندات</button>
                <button class="btn-xs btn-edit edit-app" data-group="${c.MainGroup}" data-id="${c.OfficeId}" data-table="${table}">تعديل</button>
                ${c.PayloadJson
                    ? `<button class="btn-xs btn-primary print-doc" data-name="${c.ApplicantName}" data-id="${c.OfficeId}" data-table="${table}">طباعة</button>`
                    : ""
                }
            </td>
            </tr>
        `;
        });



        html += "</tbody></table>";
        resultDiv.innerHTML = html;

        // === Attach actions ===
        resultDiv.querySelectorAll(".show-files").forEach(btn => {
            btn.addEventListener("click", async () => {
                const table = btn.dataset.table;
                const id = btn.dataset.id;

                console.log("show-files clicked:", table, id);

                filesDiv.innerHTML = `<p class="muted">⏳ تحميل المستندات...</p>`;
                const resF = await fetch(`../api_office_casefiles.php?table=${table}&id=${id}`);
                const jf = await resF.json();

                if (!jf.ok) {
                    filesDiv.innerHTML = `<p class="error">❌ ${jf.error}</p>`;
                    return;
                }

                if (!jf.items || jf.items.length === 0) {
                    filesDiv.innerHTML = `<p class="muted">⚠️ لا توجد مستندات مرفقة</p>`;
                    return;
                }

                let fhtml = `
          <h2 class="doc-section-title"> الملفات الملحقة بالمعاملة</h2>
          <div class="doc-cards">
        `;

                jf.items.forEach(f => {
                    const ext = (f.filename || "").split(".").pop().toLowerCase();
                    fhtml += `
            <div class="doc-card">
              ${["jpg", "jpeg", "png", "gif", "bmp", "webp"].includes(ext)
                            ? `<img class="doc-preview" src="../download.php?fileId=${f.FileID}&preview=1" alt="Preview">`
                            : ext === "pdf"
                                ? `<iframe class="doc-preview" src="../download.php?fileId=${f.FileID}&preview=1"></iframe>`
                                : `<div class="doc-preview"> ${ext}</div>`
                        }
              <div class="doc-title">${f.Label || ""}</div>
              <div class="doc-actions">
                <a class="btn btn-view" target="_blank" href="../download.php?fileId=${f.FileID}&preview=1">عرض / تحميل</a>
              </div>
            </div>
          `;
                });

                fhtml += `</div>`;
                filesDiv.innerHTML = fhtml;
            });
        });

        resultDiv.querySelectorAll(".edit-app").forEach(btn => {
            btn.addEventListener("click", () => {

                const id = btn.dataset.id;
                const group = btn.dataset.group;
                console.log("edit-app clicked:", group, id); // ✅ will log values now
                alert("❌ API Error: " + group + id);
                window.location.href = `http://192.168.0.68:8000/index.php?group=${encodeURIComponent(group)}&id=${encodeURIComponent(id)}`;
            });
        });



        resultDiv.querySelectorAll(".print-doc").forEach(btn => {
            btn.addEventListener("click", async () => {
                const id = btn.dataset.id;
                const table = btn.dataset.table;
                const name = btn.dataset.name;

                // 1. Fetch saved payload
                const res = await fetch(`get_payload.php?id=${id}&table=${table}`);
                if (!res.ok) {
                    alert("تعذر تحميل البيانات");
                    return;
                }
                const payload = await res.json();

                // 2. Send payload to generator
                const gen = await fetch("../docGenerator/fill_docx.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json; charset=UTF-8" },
                    body: JSON.stringify(payload)
                });
                if (!gen.ok) {
                    alert("فشل إنشاء الملف");
                    return;
                }

                // 3. Download file
                const blob = await gen.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement("a");
                a.href = url;
                a.download = name + ' ' + (payload["نوع_المكاتبة"] || "document") + "." + (payload["output_format"] || "docx");
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            });
        });

    }
    searchInputNum.addEventListener("input", fetchDocs);
    searchInputName.addEventListener("input", fetchDocs);
    dateFrom.addEventListener("change", fetchDocs);
    dateTo.addEventListener("change", fetchDocs);

    // Load immediately
    fetchDocs();
}

function renderDailyReports(container, data) {
    let html = "";

    if (data.collection && data.collection.length > 0) {
        html += `<h3>📑 TableCollection (المعاملات)</h3>`;
        html += `<table class="table"><thead><tr>
                    <th>الرقم المرجعي للمعاملة</th>
                    <th>نوع المعاملة</th>
                    <th>اسم مقدم الطلب</th>
                    <th>الإجراء</th>
                 </tr></thead><tbody>`;
        data.collection.forEach(r => {
            html += `<tr>
                        <td>${r.رقم_المعاملة || ""}</td>
                        <td>${r.نوع_المعاملة || ""}</td>
                        <td>${r.مقدم_الطلب || ""}</td>
                        <td>${r.نوع_الإجراء || ""}</td>
                     </tr>`;
        });
        html += `</tbody></table>`;
    } else {
        html += `<p>⚠️ لا توجد معاملات في TableCollection لليوم المحدد</p>`;
    }

    if (data.auth && data.auth.length > 0) {
        html += `<h3>📝 TableAuth (التوكيلات)</h3>`;
        html += `<table class="table"><thead><tr>
                    <th>الرقم المرجعي للمعاملة</th>
                    <th>الموكل بالإجراء</th>
                    <th>اسم مقدم الطلب</th>
                    <th>الإجراء</th>
                 </tr></thead><tbody>`;
        data.auth.forEach(r => {
            html += `<tr>
                        <td>${r.رقم_التوكيل || ""}</td>
                        <td>${r.الموكَّل || ""}</td>
                        <td>${r.مقدم_الطلب || ""}</td>
                        <td>${r.إجراء_التوكيل || ""}</td>
                     </tr>`;
        });
        html += `</tbody></table>`;
    } else {
        html += `<p>⚠️ لا توجد معاملات في TableAuth لليوم المحدد</p>`;
    }

    container.innerHTML = html;
}

async function loadAvailableFilters(type) {
    const res = await fetch(`report_options.php?type=${type}`);
    const data = await res.json();
    console.log("Filter data for", type, data);  // 👀 debug
    renderFilters(type, data);
}

function renderReportTable(container, reportType, data) {
    if (!data.ok) {
        container.innerHTML = `<p>⚠️ خطأ في تحميل البيانات: ${data.error || "غير معروف"}</p>`;
        return;
    }

    let html = "";

    // === DAILY ===
    if (reportType === "daily") {
        html += `<h3>📋 تقرير يومي</h3>`;
        ["collection", "auth"].forEach(src => {
            const rows = data[src] || [];
            if (!rows.length) return;

            html += `<h4>${src === "collection" ? "TableCollection" : "TableAuth"}</h4>`;
            html += `<table class="table"><thead><tr>`;

            if (src === "collection") {
                html += `
                    <th>الرقم المرجعي</th>
                    <th>نوع المعاملة</th>
                    <th>اسم مقدم الطلب</th>`;
            } else {
                html += `
                    <th>الرقم المرجعي</th>
                    <th>نوع التوكيل</th>
                    <th>اسم مقدم الطلب</th>
                    <th>الموكَّل</th>`;
            }

            html += `</tr></thead><tbody>`;

            rows.forEach(r => {
                html += `<tr>`;
                if (src === "collection") {
                    html += `<td>${r["رقم_المعاملة"] || ""}</td>
                             <td>${r["نوع_المعاملة"] || ""}</td>
                             <td>${r["مقدم_الطلب"] || ""}</td>`;
                } else {
                    html += `<td>${r["رقم_التوكيل"] || ""}</td>
                             <td>${r["نوع_التوكيل"] || ""}</td>
                             <td>${r["مقدم_الطلب"] || ""}</td>
                             <td>${r["الموكَّل"] || ""}</td>`;
                }
                html += `</tr>`;
            });

            html += `</tbody></table>`;
        });
    }

    // === PERIODIC (monthly / quarterly / biannually / yearly) ===
    else {
        const caseTypes = data.caseTypes || [];
        const rows = data.rows || [];

        // --- Build readable heading safely ---
        let heading = "تقرير ";
        const year = document.querySelector("input[name=filterYear]:checked")?.value
            || document.getElementById("filterYear")?.value
            || "";

        const months = {
            "1": "يناير", "2": "فبراير", "3": "مارس", "4": "أبريل",
            "5": "مايو", "6": "يونيو", "7": "يوليو", "8": "أغسطس",
            "9": "سبتمبر", "10": "أكتوبر", "11": "نوفمبر", "12": "ديسمبر"
        };
        const quarters = {
            "1": "الربع الأول", "2": "الربع الثاني",
            "3": "الربع الثالث", "4": "الربع الرابع"
        };
        const halves = { "1": "النصف الأول", "2": "النصف الثاني" };

        if (reportType === "monthly") {
            const monthVal = document.querySelector("input[name=filterMonth]:checked")?.value
                || document.getElementById("filterMonth")?.value
                || "";
            const monthLabel = monthVal ? (months[monthVal] || monthVal) : "";
            heading += (monthLabel ? `شهر ${monthLabel}` : "") + (year ? ` ${year}` : "");
        } else if (reportType === "quarterly") {
            const qVal = document.querySelector("input[name=filterQuarter]:checked")?.value || "";
            const qLabel = qVal ? (quarters[qVal] || qVal) : "";
            heading += (qLabel ? `${qLabel}` : "") + (year ? ` ${year}` : "");
        } else if (reportType === "biannually") {
            const hVal = document.querySelector("input[name=filterHalf]:checked")?.value || "";
            const hLabel = hVal ? (halves[hVal] || hVal) : "";
            heading += (hLabel ? `${hLabel}` : "") + (year ? ` ${year}` : "");
        } else if (reportType === "yearly") {
            heading += (year ? `سنة ${year}` : "");
        }

        html += `<h3>${heading.trim()}</h3>`;
        html += `<table class="table"><thead><tr>
                <th>${reportType === "monthly" ? "التاريخ" : "الشهر"}</th>`;
        caseTypes.forEach(ct => html += `<th>${ct}</th>`);
        html += `<th>مجموع المعاملات</th></tr></thead><tbody>`;

        rows.forEach(r => {
            html += `<tr><td>${r.Period || ""}</td>`;
            caseTypes.forEach(ct => {
                html += `<td>${r[ct] || 0}</td>`;
            });
            html += `<td>${r.Total || 0}</td></tr>`;
        });

        html += `</tbody></table>`;
    }

    container.innerHTML = html;
}

function reportsControl(container) {
    container.innerHTML = `
    <h2>التقارير الدورية</h2>
    <div class="filters">
        <fieldset class="inline-radios">
          <legend>نوع التقرير</legend>
          <label><input type="radio" name="reportType" value="daily" checked> يومي</label>
          <label><input type="radio" name="reportType" value="monthly"> شهري</label>
          <label><input type="radio" name="reportType" value="quarterly"> ربع سنوي</label>
          <label><input type="radio" name="reportType" value="biannually"> نصف سنوي</label>
          <label><input type="radio" name="reportType" value="yearly"> سنوي</label>
        </fieldset>
        <div id="reportFilters"></div>
    </div>
    
    <div id="reportTableWrapper"></div>
    
    <fieldset class="actions">
        <legend>طباعة التقارير</legend>
        <button type="button" class="primary" id="btnDailyAuth">طباعة تقرير التوكيلات </button>
        <button type="button" class="primary" id="btnDailyCollection">طباعة تقرير المعاملات </button>
        <button type="button" class="primary" id="btnPeriodic">طباعة تقرير دوري</button>
    </fieldset>
`;

    // ===== Control button visibility =====
    function updateButtonsVisibility() {
        const type = document.querySelector("input[name=reportType]:checked").value;
        document.getElementById("btnDailyAuth").style.display =
            document.getElementById("btnDailyCollection").style.display =
            (type === "daily") ? "inline-block" : "none";
        document.getElementById("btnPeriodic").style.display =
            (type !== "daily") ? "inline-block" : "none";
    }
    document.querySelectorAll("input[name=reportType]").forEach(r => {
        r.addEventListener("change", updateButtonsVisibility);
    });
    updateButtonsVisibility();

    const filtersDiv = document.getElementById("reportFilters");

    function renderFilters(type, data) {
        let html = "";
        if (!data.ok) {
            filtersDiv.innerHTML = `<p>⚠️ لم يتم العثور على خيارات</p>`;
            return;
        }

        if (type === "daily") {
            html = `
                <label>📅 اختر التاريخ</label>
                <input type="date" id="filterDate" value="${data.default || new Date().toISOString().split('T')[0]}">
            `;
            filtersDiv.innerHTML = html;
            return;
        }

        if (type === "monthly") {
            const monthNames = {
                1: "يناير", 2: "فبراير", 3: "مارس", 4: "أبريل",
                5: "مايو", 6: "يونيو", 7: "يوليو", 8: "أغسطس",
                9: "سبتمبر", 10: "أكتوبر", 11: "نوفمبر", 12: "ديسمبر"
            };

            html = `
                <fieldset class="inline-radios-scroll" id="yearRadios">
                  <legend>📅 السنة</legend>
                  ${(data.years || []).map(y => `
                    <label>
                      <input type="radio" name="filterYear" value="${y}" ${y == data.defaultYear ? "checked" : ""}>
                      ${y}
                    </label>
                  `).join("")}
                </fieldset>
                <div id="monthWrapper">⏳ تحميل الأشهر...</div>
            `;
            filtersDiv.innerHTML = html;

            const defaultYear = document.querySelector("input[name=filterYear]:checked")?.value;
            if (defaultYear) {
                loadMonthsForYear(defaultYear);
            }

            document.querySelectorAll("input[name=filterYear]").forEach(radio => {
                radio.addEventListener("change", e => {
                    loadMonthsForYear(e.target.value);
                });
            });

            async function loadMonthsForYear(year) {
                const res = await fetch(`report_options.php?type=monthly&year=${year}`);
                const mdata = await res.json();
                if (!mdata.ok) {
                    document.getElementById("monthWrapper").innerHTML = "<p>⚠️ لا توجد أشهر متاحة</p>";
                    return;
                }
                const monthsHtml = `
                    <fieldset class="inline-radios-scroll" id="monthRadios">
                      <legend>📅 الشهر</legend>
                      ${(mdata.months || []).map((m, idx) => {
                    const val = parseInt(m.val, 10);
                    return `
                            <label>
                              <input type="radio" name="filterMonth" value="${val}" ${idx === 0 ? "checked" : ""}>
                              ${monthNames[val] || val}
                            </label>
                          `;
                }).join("")}
                    </fieldset>
                `;
                document.getElementById("monthWrapper").innerHTML = monthsHtml;

                // 👇 auto-load if only one month option
                if ((mdata.months || []).length === 1) {
                    loadReport("monthly");
                }
            }
            return;
        }

        if (type === "quarterly") {
            html = `
                <fieldset class="inline-radios-scroll">
                  <legend>📅 السنة</legend>
                  ${(data.years || []).map(y => `
                    <label><input type="radio" name="filterYear" value="${y}" ${y == data.defaultYear ? "checked" : ""}>${y}</label>
                  `).join("")}
                </fieldset>
                <fieldset class="inline-radios">
                  <legend>📅 الربع</legend>
                  ${(data.quarters || []).map(q => `
                    <label><input type="radio" name="filterQuarter" value="${q.val}">${q.label}</label>
                  `).join("")}
                </fieldset>
            `;
        } else if (type === "biannually") {
            html = `
                <fieldset class="inline-radios-scroll">
                  <legend>📅 السنة</legend>
                  ${(data.years || []).map(y => `
                    <label><input type="radio" name="filterYear" value="${y}" ${y == data.defaultYear ? "checked" : ""}>${y}</label>
                  `).join("")}
                </fieldset>
                <fieldset class="inline-radios">
                  <legend>📅 النصف</legend>
                  ${(data.halves || []).map(h => `
                    <label><input type="radio" name="filterHalf" value="${h.val}">${h.label}</label>
                  `).join("")}
                </fieldset>
            `;
        } else if (type === "yearly") {
            html = `
                <fieldset class="inline-radios-scroll">
                  <legend>📅 السنة</legend>
                  ${(data.years || []).map(y => `
                    <label><input type="radio" name="filterYear" value="${y}" ${y == data.defaultYear ? "checked" : ""}>${y}</label>
                  `).join("")}
                </fieldset>
            `;
        }

        filtersDiv.innerHTML = html;
    }

    async function loadAvailableFilters(type) {
        const res = await fetch(`report_options.php?type=${type}`);
        const data = await res.json();
        renderFilters(type, data);
    }

    async function loadReport(type) {
        let query = `reports.php?type=${type}`;
        if (type === "daily") {
            const date = document.getElementById("filterDate").value;
            query += `&date=${date}`;
        } else if (type === "monthly") {
            const year = document.querySelector("input[name=filterYear]:checked")?.value;
            const month = document.querySelector("input[name=filterMonth]:checked")?.value;
            if (!year || !month) return;
            query += `&year=${year}&month=${month}`;
        } else if (type === "quarterly") {
            query += `&year=${document.querySelector("input[name=filterYear]:checked")?.value}&quarter=${document.querySelector("input[name=filterQuarter]:checked")?.value}`;
        } else if (type === "biannually") {
            query += `&year=${document.querySelector("input[name=filterYear]:checked")?.value}&half=${document.querySelector("input[name=filterHalf]:checked")?.value}`;
        } else if (type === "yearly") {
            query += `&year=${document.querySelector("input[name=filterYear]:checked")?.value}`;
        }

        const res = await fetch(query);
        const data = await res.json();
        currentReportData = data;
        renderReportTable(document.getElementById("reportTableWrapper"), type, data);
    }

    document.querySelectorAll("input[name=reportType]").forEach(radio => {
        radio.addEventListener("change", e => {
            loadAvailableFilters(e.target.value).then(() => {
                loadReport(e.target.value);
            });
        });
    });

    document.getElementById("reportFilters").addEventListener("change", () => {
        const type = document.querySelector("input[name=reportType]:checked").value;
        loadReport(type);
    });

    (async () => {
        await loadAvailableFilters("daily");
        loadReport("daily");
    })();



    // --- downloadReport helper
    async function downloadReport(docxfile, tablename, extraPayload = {}, filename = "report.docx") {
        const type = document.querySelector("input[name=reportType]:checked")?.value || "";
        const payload = { type, docxfile, tablename, ...extraPayload };
        payload['refNum'] = 'ق س ج/80/1/';
        const now = new Date();
        const todayFormatted = now.toLocaleDateString("en-GB").replace(/\//g, "-"); // dd-mm-yyyy
        payload["تاريخ_الطباعة"] = todayFormatted;

        // --- Selected date for proDate (daily only) ---
        if (type === "daily") {
            const selected = document.getElementById("filterDate")?.value || "";
            if (selected) {
                const d = new Date(selected);
                payload["proDate"] = d.toLocaleDateString("en-GB").replace(/\//g, "-"); // dd-mm-yyyy
            } else {
                payload["proDate"] = "";
            }
        } else {
            payload["proDate"] = "";
        }
        const index = window.comboData.settings[0].VCIndesx;
        payload['signer'] = window.comboData.diplomats[index].EmployeeName;
        payload['title'] = window.comboData.diplomats[index].AuthenticType;
        payload['reportType'] = type;
        console.log(window.comboData.diplomats);
        console.log(payload);
        try {
            const res = await fetch("fill_reports_docx.php", {
                method: "POST",
                headers: { "Content-Type": "application/json; charset=UTF-8" },
                body: JSON.stringify(payload)
            });
            if (!res.ok) {
                const msg = await res.text();
                alert("فشل إنشاء الملف: " + msg);
                return;
            }
            const blob = await res.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        } catch (err) {
            console.error(err);
            alert("⚠️ خطأ أثناء إنشاء الملف");
        }
    }

    // --- Button bindings
    document.getElementById("btnDailyAuth").addEventListener("click", () => {
        const date = document.getElementById("filterDate").value;
        downloadReport(
            "TableAuth Daily Reports.docx",
            "TableAuth",
            { rows: currentReportData.auth },
            "DailyAuth_report.docx"
        );
    });

    document.getElementById("btnDailyCollection").addEventListener("click", () => {
        const date = document.getElementById("filterDate").value;
        downloadReport(
            "TableCollection Daily Reports.docx",
            "TableCollection",
            { rows: currentReportData.collection },
            "DailyCollection_report.docx"
        );
    });

    // Helper: Arabic period label
    function getPeriodLabel(type, extra) {
        const months = {
            "1": "يناير", "2": "فبراير", "3": "مارس", "4": "أبريل",
            "5": "مايو", "6": "يونيو", "7": "يوليو", "8": "أغسطس",
            "9": "سبتمبر", "10": "أكتوبر", "11": "نوفمبر", "12": "ديسمبر"
        };
        const quarters = { "1": "الربع الأول", "2": "الربع الثاني", "3": "الربع الثالث", "4": "الربع الرابع" };
        const halves = { "1": "النصف الأول", "2": "النصف الثاني" };

        if (type === "monthly") {
            return "تقرير شهر " + (months[extra.month] || extra.month) + " ";
        } else if (type === "quarterly") {
            return "تقرير " + (quarters[extra.quarter] || extra.quarter) + " ";
        } else if (type === "biannually") {
            return "تقرير " + (halves[extra.half] || extra.half) + " ";
        } else if (type === "yearly") {
            return "تقرير المعاملات القنصلية " + (extra.year || "");
        }
        return "";
    }

    document.getElementById("btnPeriodic").addEventListener("click", () => {
        const type = document.querySelector("input[name=reportType]:checked").value;
        const extra = { type };
        let docxfile = "Queraterly Annually Reports.docx";
        if (type === "monthly") {
            extra.year = document.querySelector("input[name=filterYear]:checked")?.value;
            extra.month = document.querySelector("input[name=filterMonth]:checked")?.value;
            reportType = getPeriodLabel(type, extra);
            docxfile = "Monthly.docx";
        } else if (type === "quarterly") {
            extra.year = document.querySelector("input[name=filterYear]:checked")?.value;
            extra.quarter = document.querySelector("input[name=filterQuarter]:checked")?.value;
            reportType = getPeriodLabel(type, extra);
        } else if (type === "biannually") {
            extra.year = document.querySelector("input[name=filterYear]:checked")?.value;
            extra.half = document.querySelector("input[name=filterHalf]:checked")?.value;
            reportType = getPeriodLabel(type, extra);
        } else if (type === "yearly") {
            extra.year = document.querySelector("input[name=filterYear]:checked")?.value;
            reportType = getPeriodLabel(type, extra);
        }

        extra.caseTypes = currentReportData.caseTypes;
        extra.rows = currentReportData.rows;

        downloadReport(
            docxfile,
            "TableSummary",
            extra,
            `${type}_Summary_report.docx`
        );
    });
}

async function showSettingsPage(container) {
    let settingsRow = (window.comboData && window.comboData.settings && window.comboData.settings[0]) || {};

    // Parse mission_Details JSON if available
    let settings = {};
    try {
        settings = JSON.parse(settingsRow["mission_Details"] || "{}");
    } catch {
        settings = {};
    }

    container.innerHTML = `
        <h2>⚙️ الإعدادات</h2>

        <form id="settingsForm" class="settings-form">
            <label>اسم البعثة باللغة العربية
                <input type="text" name="missionNameAr" value="${settings.missionNameAr || ""}">
            </label>

            <label>اسم البعثة باللغة الاجنبية
                <input type="text" dir="ltr" name="missionNameEn" value="${settings.missionNameEn || ""}">
            </label>

            <fieldset>
                <legend>📍 عنوان وأرقام تواصل البعثة</legend>

                <label>العنوان
                    <input type="text" name="missionAddr" value="${settings.missionAddr || ""}" 
                        placeholder="مثال: حي السفارات – جدة">
                </label>

                <label>الهاتف
                    <input type="text" name="missionPhone" value="${settings.missionPhone || ""}" 
                        placeholder="مثال: 6055888">
                </label>

                <label>الفاكس
                    <input type="text" name="missionFax" value="${settings.missionFax || ""}" 
                        placeholder="مثال: 6548826">
                </label>

                <label>صندوق البريد
                    <input type="text" name="missionPO" value="${settings.missionPO || ""}" 
                        placeholder="مثال: 480">
                </label>

                <label>الرمز البريدي
                    <input type="text" name="missionPostal" value="${settings.missionPostal || ""}" 
                        placeholder="مثال: 21411">
                </label>
            </fieldset>

            <label>الرقم المرجعي
                <input type="text" name="refNum" value="${settings.refNum || ""}">
            </label>

            <fieldset>
                <legend>تفعيل الباركود</legend>
                <label><input type="radio" name="barcodeEnabled" value="true" ${settings.barcodeEnabled ? "checked" : ""}> نعم</label>
                <label><input type="radio" name="barcodeEnabled" value="false" ${!settings.barcodeEnabled ? "checked" : ""}> لا</label>
            </fieldset>
            
            <fieldset>
                <button type="submit" class="primary">حفظ</button>
            </fieldset>
        </form>
    `;

    document.getElementById("settingsForm").addEventListener("submit", async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const cMissionArName = formData.get("missionNameAr");
        const cMissionEnName = formData.get("missionNameEn");
        const payload = {
            missionNameAr: cMissionArName,
            missionNameEn: cMissionEnName,
            missionAddr: formData.get("missionAddr"),
            missionPhone: formData.get("missionPhone"),
            missionFax: formData.get("missionFax"),
            missionPO: formData.get("missionPO"),
            missionPostal: formData.get("missionPostal"),
            refNum: formData.get("refNum"),
            barcodeEnabled: formData.get("barcodeEnabled") === "true"
        };

        try {
            const res = await fetch("save_settings.php", {
                method: "POST",
                headers: { "Content-Type": "application/json; charset=UTF-8" },
                body: JSON.stringify(payload)
            });
            const result = await res.json();

            if (result.ok) {
                alert("✅ تم حفظ الإعدادات بنجاح");

                // compare with old settings
                if (
                    cMissionArName !== settings.missionNameAr ||
                    cMissionEnName !== settings.missionNameEn
                ) {
                    // call header handler if mission name changed
                    await fetch("header_handler.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json; charset=UTF-8" },
                        body: JSON.stringify({
                            missionNameAr: cMissionArName,
                            missionNameEn: cMissionEnName
                        })

                    })
                        .then(r => r.json())
                        .then(h => {
                            if (h.ok) {
                                console.log("📄 Headers updated for DOCX templates:", h.updated);
                            } else {
                                console.error("⚠️ Header update failed:", h.error);
                            }
                        })
                        .catch(err => console.error("⚠️ Network error header_handler:", err));
                }

                // update local cached settings
                // Parse once
                let details = {};
                try {
                    details = JSON.parse(window.comboData.settings[0].mission_Details || "{}");
                } catch {
                    details = {};
                }

                // Update fields
                details.missionNameAr = cMissionArName;
                details.missionNameEn = cMissionEnName;

                // Save back as string
                window.comboData.settings[0].mission_Details = JSON.stringify(details);


            } else {
                alert("⚠️ خطأ أثناء الحفظ: " + (result.error || "غير معروف"));
            }
        } catch (err) {
            console.error(err);
            alert("⚠️ خطأ في الاتصال بالخادم");
        }
    });

}

document.addEventListener("DOMContentLoaded", () => {
    const navLinks = document.querySelectorAll("nav a[data-page]");
    const main = document.getElementById("dashboard-main");

    navLinks.forEach(link => {
        link.addEventListener("click", e => {
            e.preventDefault();
            const page = link.getAttribute("data-page");

            switch (page) {
                case "headDepartment":
                    ControlPanel(main);
                    break;

                case "users":
                    usersControl(main);
                    break;

                case "mandoubs":
                    mandoubControl(main);
                    break;

                case "officeCasesControl":
                    officeCasesControl(main);
                    break;

                case "concularDoc":
                    consularDocsControl(main);
                    break;

                case "reports":
                    reportsControl(main);
                    break;

                case "settings":
                    showSettingsPage(main);
                    break;

                default:
                    showDefaultTable(main);
            }
        });
    });

    // ✅ Load office cases list immediately
    // showSettingsPage(main);
});
