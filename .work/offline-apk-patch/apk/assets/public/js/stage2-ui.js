/* Stage 2 offline UI shell. Keeps the legacy attendance controller intact while
 * giving the app a real module navigation layer for the offline rewrite. */
(function () {
  "use strict";

  var screens = ["dashboard", "students", "teachers", "academics", "parents", "timetable", "attendance", "staff-attendance", "announcements", "exams", "fees", "finance", "users", "settings"];

  function $(id) { return document.getElementById(id); }
  function esc(value) {
    return String(value == null ? "" : value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\"/g, "&quot;");
  }

  function hideDrawer() {
    var drawer = $("drawer");
    if (drawer) drawer.classList.add("hidden");
  }

  function navigate(name) {
    if (screens.indexOf(name) < 0) name = "dashboard";
    screens.forEach(function (screen) {
      var el = $("screen-" + screen);
      if (el) el.classList.toggle("hidden", screen !== name);
    });
    document.querySelectorAll("[data-screen]").forEach(function (button) {
      button.classList.toggle("active", button.getAttribute("data-screen") === name);
    });
    hideDrawer();
    if (name === "dashboard") renderDashboard();
    if (name === "students") renderStudents();
    if (name === "fees") renderFees();
  }

  function count(store) {
    return window.OfflineDB ? OfflineDB.all(store).catch(function () { return []; }) : Promise.resolve([]);
  }

  function stat(label, value, tone) {
    return '<div class="stat-card ' + (tone || "") + '"><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong></div>';
  }

  function renderDashboard() {
    var target = $("dashboard-stats");
    if (!target || !window.OfflineDB) return;
    Promise.all([count("students"), count("teachers"), count("attendanceRecords"), count("feeInvoices")]).then(function (values) {
      target.innerHTML = stat("Students", values[0].length, "blue") + stat("Teachers", values[1].length, "green") + stat("Attendance records", values[2].length, "orange") + stat("Fee invoices", values[3].length, "purple");
    });
  }

  function renderStudents() {
    var list = $("student-list");
    if (!list || !window.OfflineDB) return;
    OfflineDB.all("students").then(function (students) {
      var query = ($("student-search") && $("student-search").value || "").toLowerCase().trim();
      var filtered = students.filter(function (student) {
        return !query || [student.name, student.firstName, student.lastName, student.admissionNumber].join(" ").toLowerCase().indexOf(query) >= 0;
      });
      $("student-count").textContent = filtered.length + " local records";
      if (!filtered.length) {
        list.innerHTML = '<div class="empty-state"><strong>No students stored yet</strong><span>Student records will be added here in the next module stage.</span></div>';
        return;
      }
      list.innerHTML = filtered.map(function (student) {
        var name = student.name || [student.firstName, student.lastName].filter(Boolean).join(" ") || "Unnamed student";
        return '<article class="data-row"><div class="avatar">' + esc(name.charAt(0)) + '</div><div class="data-main"><strong>' + esc(name) + '</strong><span>' + esc(student.admissionNumber || "No admission number") + '</span></div><span class="row-tag">Local</span></article>';
      }).join("");
    });
  }

  function renderFees() {
    if (!window.OfflineDB) return;
    Promise.all([count("feeInvoices"), count("feePayments")]).then(function (values) {
      if ($("fee-count")) $("fee-count").textContent = values[0].length;
      if ($("payment-count")) $("payment-count").textContent = values[1].length;
    });
  }

  function emptyAction() {
    var message = document.createElement("div");
    message.className = "toast-message";
    message.textContent = "This offline module is prepared and will be implemented next.";
    document.body.appendChild(message);
    setTimeout(function () { message.classList.add("fade-out"); setTimeout(function () { message.remove(); }, 250); }, 2200);
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-screen]").forEach(function (button) {
      button.addEventListener("click", function () { navigate(button.getAttribute("data-screen")); });
    });
    document.querySelectorAll('[data-action="empty"]').forEach(function (button) { button.addEventListener("click", emptyAction); });
    if ($("student-search")) $("student-search").addEventListener("input", renderStudents);
    if ($("menu-btn")) $("menu-btn").addEventListener("click", function () { $("drawer").classList.toggle("hidden"); });
    if (window.OfflineDB) {
      OfflineDB.ready.then(function () {
        if ($("database-status")) $("database-status").textContent = "Local database ready";
        renderDashboard();
      }).catch(function () {
        if ($("database-status")) $("database-status").textContent = "Local database unavailable";
      });
    }
    navigate("dashboard");
  });

  window.Stage2UI = { navigate: navigate, renderDashboard: renderDashboard, renderStudents: renderStudents };
})();
