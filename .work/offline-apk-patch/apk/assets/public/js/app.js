/* Offline attendance APK: record without internet and export the same Excel
 * format the server import accepts. No login, no server sync - the "Live
 * Site" tab is the only place the site's own login is used, in full-window
 * navigation to the real site. */
(function () {
  "use strict";

  var STATUSES = ["present", "absent", "late", "excused"];
  // "Class" is Informational only: the server import reads the first five
  // columns and ignores anything after, so old files stay compatible.
  // Column order is the SERVER import contract (it reads by position):
  // Admission, Name, Date, Status, Remarks, Class(ignored), Time In,
  // Time Out. Keep in sync with AttendanceController::import.
  var EXCEL_HEADER = ["Admission Number", "Student Name", "Date", "Status", "Remarks", "Class", "Time In", "Time Out"];

  var scanner = null, scannerRunning = false;

  function $(id) { return document.getElementById(id); }

  function esc(s) {
    return String(s === null || s === undefined ? "" : s)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;")
      .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }

  /* ---------- Capacitor bridge (no bundler needed) ---------- */
  function capCall(plugin, method, options) {
    if (window.Capacitor && window.Capacitor.nativePromise) {
      return window.Capacitor.nativePromise(plugin, method, options || {});
    }
    return Promise.reject(new Error("no-bridge"));
  }

  /* ---------- screens & tabs ---------- */
  var SCREENS = ["attendance", "live", "settings"];

  function show(name) {
    $("drawer").classList.add("hidden");
    SCREENS.forEach(function (s) { $("screen-" + s).classList.toggle("hidden", s !== name); });
    ["attendance", "live", "settings"].forEach(function (t) {
      $("tab-" + t).classList.toggle("active", t === name);
    });
    if (name !== "attendance") stopScanner();
    if (name === "attendance") renderPending();
  }

  /* ---------- language ---------- */
  function applyLang(l) {
    I18N.lang = l; Store.setLang(l);
    I18N.apply(); renderPending();
  }

  /* ---------- Jalali date dropdowns (year/month/day, numeric) ---------- */
  function pad2(n) { n = String(n); return n.length < 2 ? "0" + n : n; }

// Device clock as "HH:MM" for the scan moment - the check-in time the 
// server import expects in the Time In column.
function nowTime() {
    var d = new Date();
    
    // Convert 24-hour format to 12-hour format
    var hours = d.getHours();
    if (hours >= 12) {
        hours -= 12;
    } else if (hours === 0) {
        hours = 12;
    }
    
    // Determine AM or PM
    var ampm = (d.getHours() < 12) ? "AM" : "PM";
    
    return pad2(hours) + ":" + pad2(d.getMinutes()) + " " + ampm;
}


  function fillRange(sel, from, to, keep, pad) {
    sel.innerHTML = "";
    for (var v = from; v <= to; v++) {
      var o = document.createElement("option");
      o.value = v;
      o.textContent = pad ? pad2(v) : String(v);
      sel.appendChild(o);
    }
    sel.value = keep;
  }

  function syncDateHidden() {
    $("att-date").value = $("att-year").value + "-" +
      pad2($("att-month").value) + "-" + pad2($("att-day").value);
  }

  function rebuildDays() {
    var y = +$("att-year").value, m = +$("att-month").value;
    var len = 31;
    try { len = Jalali.monthLength(y, m); } catch (e) { len = 31; }
    var keep = Math.min(+$("att-day").value || 1, len);
    fillRange($("att-day"), 1, len, keep, true);
    syncDateHidden();
  }

  function populateDateSelects() {
    var parts = Jalali.todayStr().split("-");
    var ty = +parts[0], tm = +parts[1], td = +parts[2];
    var y0 = ty - 5, y1 = ty;
    fillRange($("att-year"), y0, y1, ty, false);
    fillRange($("att-month"), 1, 12, tm, true);
    var len = 31;
    try { len = Jalali.monthLength(ty, tm); } catch (e) {}
    fillRange($("att-day"), 1, len, Math.min(td, len), true);
    syncDateHidden();
    $("att-year").onchange = rebuildDays;
    $("att-month").onchange = rebuildDays;
    $("att-day").onchange = syncDateHidden;
  }

  /* ---------- admission parse (mirrors server extractAdmissionNumber) ---------- */
  function extractAdmission(code) {
    code = String(code || "").trim();
    if (!code) return null;
    var m = code.match(/\bSTU-\d{4}-\d+\b/i);
    if (m) return m[0].toUpperCase();
    if (/^\d{4}-\d{1,4}$/.test(code)) return "STU-" + code;
    return code.toUpperCase();
  }

  /* ---------- local roster lookup (any people export, see loader) ---------- */
  function lookupAdmission(code) {
    var roster = Store.getRoster();
    var key = extractAdmission(code);
    if (!key) return null;
    key = key.toUpperCase();
    return roster[key] || null;
  }

  /* ---------- universal roster loader ----------
   * One file input takes ANY of the site's people exports (students,
   * teachers, employees) or a hand-made file - each ROW is routed by its
   * code prefix, so mixed files work too. Positions follow each export's
   * fixed column order (headers are localized, positions are not):
   *   STU-.. -> name = cols 1+2, class = cols 19+20 (students export)
   *   TCH-.. -> name = cols 1+2, class = col 11     (teachers export)
   *   STF-.. -> name = cols 1+2, class = col 3      (employees export)
   *   other  -> name = cols 1+2, no class (safe fallback)
   * Multi-sheet workbooks offer a sheet picker (sheet names need no
   * translation). Loads MERGE into the stored roster, so students +
   * teachers + employees files accumulate; the message shows the total. */
  function parseRosterSheet(wb, sheetName) {
    var ws = wb.Sheets[sheetName];
    if (!ws) return 0;
    var rows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: "" });
    rows.shift(); // header row
    var roster = Store.getRoster(), n = 0;
    rows.forEach(function (r) {
      var adm = String(r[0] || "").trim().toUpperCase();
      if (!adm) return;
      var nm = (String(r[1] || "").trim() + " " + String(r[2] || "").trim()).trim();
      var cl = "";
      if (/^STU-/i.test(adm)) {
        var sec = String(r[20] || "").trim();
        cl = String(r[19] || "").trim() + (sec ? " - " + sec : "");
      } else if (/^TCH-/i.test(adm)) {
        cl = String(r[11] || "").trim();
      } else if (/^STF-/i.test(adm)) {
        cl = String(r[3] || "").trim();
      }
      roster[adm] = { name: nm, class: cl.trim() };
      n++;
    });
    Store.setRoster(roster);
    return n;
  }

  function showRosterTotal() {
    var box = $("roster-result");
    var msg = document.getElementById("roster-count");
    var text = I18N.t("download") + ": " + Object.keys(Store.getRoster()).length;
    if (msg) { msg.textContent = text; }
    else { box.textContent = text; }
    box.className = "ok";
  }

  function loadRosterFile(file) {
    var box = $("roster-result");
    var reader = new FileReader();
    reader.onload = function () {
      try {
        var wb = XLSX.read(reader.result, { type: "array" });
        if (!wb.SheetNames.length) throw new Error("empty");
        if (wb.SheetNames.length > 1) {
          // Several sheets: offer a picker, parse the first one at once.
          box.className = "";
          box.innerHTML = "";
          var sel = document.createElement("select");
          wb.SheetNames.forEach(function (sn) {
            var o = document.createElement("option");
            o.value = sn; o.textContent = sn;
            sel.appendChild(o);
          });
          sel.onchange = function () { parseRosterSheet(wb, sel.value); showRosterTotal(); };
          box.appendChild(sel);
          var msg = document.createElement("div");
          msg.id = "roster-count";
          msg.className = "hint";
          box.appendChild(msg);
        }
        parseRosterSheet(wb, wb.SheetNames[0]);
        showRosterTotal();
      } catch (e) {
        box.textContent = I18N.t("error_generic");
        box.className = "error";
      }
    };
    reader.onerror = function () {
      box.textContent = I18N.t("error_generic");
      box.className = "error";
    };
    reader.readAsArrayBuffer(file);
  }

  /* ---------- QR payload (mirrors the ID cards) ----------
   * Student cards carry {"a":"STU-...","n":"name","c":"class"} (older ones
   * just the admission number); staff cards add "t":"teacher"|"employee".
   * Resolves to { kind, admission, name, class } with empty strings where
   * unknown. Every card kind saves a row: students as attendance, staff
   * as presence (always present). */
  function parsePayload(text) {
    text = String(text || "").trim();
    var m = text.match(/\{[\s\S]*\}/);
    if (m) {
      try {
        var o = JSON.parse(m[0]);
        if (o && o.a) {
          var kind = (o.t === "teacher" || o.t === "employee") ? o.t : "student";
          return { kind: kind,
                   admission: String(o.a).trim().toUpperCase(),
                   name: String(o.n || "").trim(),
                   class: String(o.c || "").trim() };
        }
      } catch (e) { /* fall through to legacy forms below */ }
    }
    var admission = extractAdmission(text);
    if (!admission) return { kind: "student", admission: null };
    // No JSON type tag (bare type-ins, legacy cards): infer from the
    // system code prefixes - TCH- teachers, STF- employees, else student.
    var kind = /^TCH-/i.test(admission) ? "teacher"
      : (/^STF-/i.test(admission) ? "employee" : "student");
    return { kind: kind, admission: admission, name: "", class: "" };
  }

  /* ---------- save record ---------- */
  // Shared core for BOTH paths: throws on storage failure so callers can
  // show it - a silent drop is the worst possible outcome for attendance.
  // Skips admission+date pairs already in today's list (timeless check on
  // recorded data, not on clocks). Returns true when a row was added.
  function commitRecord(info, date, status, remarks) {
    var dup = Store.pending().some(function (r) {
      return r.admission === info.admission && r.date === date;
    });
    if (dup) {
      renderPending();
      return false;
    }
    Store.addRecord({
      kind: info.kind || "student",
      admission: info.admission,
      name: info.name || "",
      className: info.class || "",
      date: date,
      timeIn: info.timeIn || "",
      timeOut: info.timeOut || "",
      status: status,
      remarks: remarks || ""
    });
    renderPending();
    return true;
  }

  // Scan feedback: writes the preview line directly, never any form box.
  function showScanInfo(info) {
    var box = $("roster-preview");
    if (!box) return;
    box.textContent = (info.name ? info.name + " - " : "") + info.admission +
      (info.class ? " - " + info.class : "");
  }

  function renderPending() {
    var list = Store.pending();
    $("pending-count").textContent = list.length;
    var box = $("pending-list");
    if (!list.length) { box.innerHTML = '<p class="muted">' + esc(I18N.t("no_data")) + "</p>"; return; }
    box.innerHTML = list.map(function (r) {
      return '<div class="rec"><div><b>' + esc(r.admission) + "</b>" +
        (r.name ? " - " + esc(r.name) : "") + "<br>" +
        '<span class="st">' + esc(r.date) +
        (r.timeIn ? " " + esc(r.timeIn) : "") + " - " + esc(I18N.statusLabel(r.status)) +
        (r.className ? " - " + esc(r.className) : "") + "</span></div>" +
        '<button class="del" data-id="' + r.id + '" title="' + esc(I18N.t("delete")) + '">&times;</button></div>';
    }).join("");
    var btns = box.querySelectorAll(".del");
    for (var i = 0; i < btns.length; i++) {
      btns[i].onclick = function () { Store.removeRecord(this.getAttribute("data-id")); renderPending(); };
    }
  }

  /* ---------- QR scanner ---------- */
  function startScanner() {
    var errBox = $("qr-error");
    errBox.classList.add("hidden");
    if (typeof Html5Qrcode === "undefined") {
      errBox.textContent = I18N.t("no_camera"); errBox.classList.remove("hidden");
      return;
    }
    var reader = $("qr-reader");
    reader.classList.remove("hidden");
    $("btn-cam-on").classList.add("hidden");
    $("btn-cam-off").classList.remove("hidden");
    try { scanner = new Html5Qrcode("qr-reader"); }
    catch (e) {
      errBox.textContent = I18N.t("no_camera"); errBox.classList.remove("hidden");
      return;
    }
    scannerRunning = true;
    scanner.start({ facingMode: "environment" },
      { fps: 10,
        qrbox: { width: 250, height: 250 },
        // Ask for a high-resolution stream: wide/default lenses make the
        // code tiny ("zoomed out"); more pixels = bigger, decodable codes.
        // Ideal (not mandatory), so weak cameras still start.
        videoConstraints: { facingMode: "environment",
          width: { ideal: 1920 }, height: { ideal: 1080 } } },
      function (text) {
        // SCAN path, fully separate from the manual boxes: one scan action
        // saves exactly one row straight into Today's list - no box writes,
        // no save button. The camera stops on every decode, so showing two
        // cards in any quick succession can never lose one. Any failure
        // below alerts visibly instead of dying silently.
        try {
          var scanned = parsePayload(text);
          if (!scanned.admission) return;
          var date = $("att-date").value.trim() || Jalali.todayStr();
          if (!Jalali.isValid(date)) { alert(I18N.t("err_date")); return; }
          var found = lookupAdmission(scanned.admission);
          var isStaff = (scanned.kind === "teacher" || scanned.kind === "employee");
          var info = {
            kind: scanned.kind,
            admission: scanned.admission,
            name: scanned.name || (found ? found.name : ""),
            class: scanned.class || (found ? found.class : ""),
            timeIn: nowTime(),
            timeOut: ""
          };
          showScanInfo(info);
          // Scanning a card = present (students and staff alike), stamped
          // with the scan moment as check-in time.
          commitRecord(info, date, "present", "");
          stopScanner();
          // Back to the camera button: Enter/Space starts the next scan.
          $("btn-cam-on").focus();
        } catch (e) {
          alert(I18N.t("error_generic"));
        }
      },
      function () { /* per-frame misses are normal, stay silent */ }
    ).then(function () { setupZoom(); }).catch(function () {
      scannerRunning = false;
      errBox.textContent = I18N.t("camera_error"); errBox.classList.remove("hidden");
      stopScannerUI();
    });
  }

  /* ---------- camera zoom (many phones default to a wide lens) ----------
   * Pure CSS scaling on the preview video: works on EVERY device, unlike
   * hardware track zoom (getCapabilities often reports nothing in WebView,
   * which is why the buttons never appeared). Decoding keeps using the
   * full frames underneath, and the 1080p request above feeds it pixels;
   * this zoom is about framing/aiming. Buttons always show with the camera. */
  // Default zoomed in, and it STAYS that way: starting/stopping the
  // camera never resets it, so every scan opens already zoomed.
  var viewZoom = 2;

  function setupZoom() {
    applyViewZoom();
    $("btn-zoom-in").classList.remove("hidden");
    $("btn-zoom-out").classList.remove("hidden");
  }

  function applyViewZoom() {
    viewZoom = Math.min(4, Math.max(1, viewZoom));
    try {
      var vd = document.querySelector("#qr-reader video");
      if (vd) {
        vd.style.transform = "scale(" + viewZoom + ")";
        vd.style.transformOrigin = "center center";
      }
    } catch (e) {}
  }

  function stopScannerUI() {
    $("qr-reader").classList.add("hidden");
    $("btn-cam-on").classList.remove("hidden");
    $("btn-cam-off").classList.add("hidden");
    $("btn-zoom-in").classList.add("hidden");
    $("btn-zoom-out").classList.add("hidden");
  }

  function stopScanner() {
    if (scanner && scannerRunning) {
      try { scanner.stop().catch(function () {}); } catch (e) {}
    }
    scannerRunning = false; scanner = null;
    stopScannerUI();
  }

  /* ---------- Excel export ----------
   * Two sheets, both server-importable: "Attendance" is the student import
   * format (student rows only); "Staff" is the staff import format -
   * Code, Name, Date, Status, Detail (Detail is reference-only, ignored
   * on import like the student Name column). */
  var STAFF_HEADER = ["Code", "Name", "Date", "Status", "Detail", "Time In", "Time Out"];

  function isStaffRow(r) {
    return r.kind === "teacher" || r.kind === "employee";
  }

  function exportExcel() {
    var pending = Store.pending();
    var box = $("sync-result");
    if (!pending.length) {
      box.textContent = I18N.t("no_data"); box.className = "error";
      return;
    }
    // Everything that can throw before the native chain (bad rows, a
    // broken XLSX lib) lands here visibly - no silent death anymore.
    var b64, name;
    try {
      var rows = [EXCEL_HEADER];
      var staffRows = [STAFF_HEADER];
    pending.forEach(function (r) {
      if (isStaffRow(r)) {
        staffRows.push([r.admission, r.name || "", r.date, r.status, r.className || "", r.timeIn || "", r.timeOut || ""]);
      } else {
        rows.push([r.admission, r.name || "", r.date, r.status, r.remarks || "", r.className || "", r.timeIn || "", r.timeOut || ""]);
      }
    });
      var wb = XLSX.utils.book_new();
      // NOTE: signature is (workbook, worksheet, name) - passing only two
      // args shifts everything and dies inside SheetJS with
      // "Cannot read properties of undefined (reading 'indexOf')".
      XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(rows), "Attendance");
      if (staffRows.length > 1) {
        XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(staffRows), "Staff");
      }
      b64 = XLSX.write(wb, { type: "base64", bookType: "xlsx" });
      name = "attendance-" + Jalali.todayStr() + ".xlsx";
    } catch (e) {
      box.textContent = I18N.t("error_generic"); box.className = "error";
      return;
    }
    // Save-only (no share sheet): the FileSave plugin writes straight to
    // the phone's Downloads via MediaStore, where Files can see it. The
    // confirmation shows the exact file name to look for.
    capCall("FileSave", "saveExcel", { data: b64, name: name })
      .then(function (res) {
        box.textContent = I18N.t("download") + ": " + ((res && res.name) ? res.name : name);
        box.className = "ok";
      })
      .catch(function (err) {
        if (err && err.message === "no-bridge") {
          // Desktop browser testing only: plain download.
          var a = document.createElement("a");
          a.href = "data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64," + b64;
          a.download = name;
          document.body.appendChild(a); a.click();
          setTimeout(function () { document.body.removeChild(a); }, 500);
          box.textContent = I18N.t("download") + ": " + name;
          box.className = "ok";
        } else {
          // Real native failure - say so instead of pretending it saved.
          box.textContent = (err && err.message) ? err.message : I18N.t("error_generic");
          box.className = "error";
        }
      });
  }

  /* ---------- live site tab ----------
   * The site opens in a Custom Tab (full browser context on the device):
   * first-party, so login/cookies/camera just work like Chrome. Crucially
   * the tab carries its own close (X) button, so the user always returns
   * to the offline section with ONE tap. Full-window navigation was tried
   * before and dropped: coming back then depended on walking the whole
   * site history with the phone's Back button, which stranded users. The
   * offline records stay safe in this origin's localStorage while away. */
  function openLiveSite() {
    var off = $("live-offline");
    if (!navigator.onLine) { off.classList.remove("hidden"); return; }
    off.classList.add("hidden");
    capCall("Browser", "open", { url: Store.getServer(), toolbarColor: "#1a73e8" })
      .catch(function () { window.location.href = Store.getServer(); });
  }

  /* ---------- wire up ---------- */
  document.addEventListener("DOMContentLoaded", function () {
    applyLang(Store.getLang());
    $("set-lang").value = I18N.lang;
    $("set-server").value = Store.getServer();
    populateDateSelects();

    $("menu-btn").onclick = function () { $("drawer").classList.toggle("hidden"); };
    $("tab-attendance").onclick = function () { show("attendance"); };
    $("tab-live").onclick = function () { show("live"); openLiveSite(); };
    $("tab-settings").onclick = function () { show("settings"); };



    $("btn-cam-on").onclick = startScanner;
    $("btn-cam-off").onclick = stopScanner;
    $("btn-zoom-in").onclick = function () { viewZoom += 0.25; applyViewZoom(); };
    $("btn-zoom-out").onclick = function () { viewZoom -= 0.25; applyViewZoom(); };

    $("btn-excel").onclick = exportExcel;

    $("btn-settings-save").onclick = function () {
      var s = $("set-server").value.trim();
      if (s) Store.setServer(s);
      applyLang($("set-lang").value);
      show("attendance");
    };

    $("btn-live-attendance").onclick = function () { show("attendance"); };

    // Reset: wipe ALL pending rows (any date) so the app starts fresh
    // tomorrow. Export to Excel FIRST - this cannot be undone. The native
    // confirm() needs no translation: the button label says it all.
    $("btn-reset").onclick = function () {
      if (!Store.pending().length) return;
      show("attendance");
      if (window.confirm(I18N.t("reset_today") + " ?")) {
        try {
          Store.saveRecords([]);
        } catch (e) {
          alert(I18N.t("error_generic"));
          return;
        }
        renderPending();
      }
    };

    show("attendance");
  });
})();
