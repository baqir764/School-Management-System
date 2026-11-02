/* Stage 1 offline foundation.
 * IndexedDB is the durable local database for the native/offline app.
 * The current attendance UI remains synchronous for compatibility; Store
 * mirrors its records into this database while later screens use the async API.
 */
var OfflineDB = (function () {
  "use strict";

  var NAME = "school_management_offline";
  var VERSION = 1;
  var db = null;
  var readyResolve;
  var readyReject;
  var ready = new Promise(function (resolve, reject) {
    readyResolve = resolve;
    readyReject = reject;
  });

  var STORES = {
    appMeta: { key: "key", indexes: [] },
    settings: { key: "key", indexes: [] },
    sessions: { key: "id", indexes: [{ name: "userId", keyPath: "userId" }] },
    users: { key: "id", indexes: [{ name: "email", keyPath: "email", unique: true }, { name: "active", keyPath: "active" }] },
    roles: { key: "id", indexes: [{ name: "name", keyPath: "name", unique: true }] },
    permissions: { key: "id", indexes: [{ name: "name", keyPath: "name", unique: true }] },
    userRoles: { key: "id", indexes: [{ name: "userId", keyPath: "userId" }, { name: "roleId", keyPath: "roleId" }] },
    rolePermissions: { key: "id", indexes: [{ name: "roleId", keyPath: "roleId" }, { name: "permissionId", keyPath: "permissionId" }] },
    academicYears: { key: "id", indexes: [{ name: "active", keyPath: "active" }] },
    classes: { key: "id", indexes: [{ name: "academicYearId", keyPath: "academicYearId" }] },
    sections: { key: "id", indexes: [{ name: "classId", keyPath: "classId" }] },
    shifts: { key: "id", indexes: [] },
    subjects: { key: "id", indexes: [] },
    teachers: { key: "id", indexes: [{ name: "code", keyPath: "code", unique: true }] },
    students: { key: "id", indexes: [{ name: "admissionNumber", keyPath: "admissionNumber", unique: true }] },
    parents: { key: "id", indexes: [] },
    enrollments: { key: "id", indexes: [{ name: "studentId", keyPath: "studentId" }, { name: "academicYearId", keyPath: "academicYearId" }] },
    studentDocuments: { key: "id", indexes: [{ name: "studentId", keyPath: "studentId" }] },
    periods: { key: "id", indexes: [] },
    timetableEntries: { key: "id", indexes: [{ name: "sectionId", keyPath: "sectionId" }] },
    attendanceRecords: { key: "id", indexes: [{ name: "date", keyPath: "date" }, { name: "studentId", keyPath: "studentId" }, { name: "admission", keyPath: "admission" }] },
    staffAttendanceRecords: { key: "id", indexes: [{ name: "date", keyPath: "date" }, { name: "personId", keyPath: "personId" }] },
    exams: { key: "id", indexes: [{ name: "academicYearId", keyPath: "academicYearId" }] },
    examSubjects: { key: "id", indexes: [{ name: "examId", keyPath: "examId" }] },
    gradeScales: { key: "id", indexes: [] },
    marks: { key: "id", indexes: [{ name: "examSubjectId", keyPath: "examSubjectId" }, { name: "studentId", keyPath: "studentId" }] },
    feeStructures: { key: "id", indexes: [{ name: "academicYearId", keyPath: "academicYearId" }] },
    feeInvoices: { key: "id", indexes: [{ name: "studentId", keyPath: "studentId" }, { name: "status", keyPath: "status" }] },
    feePayments: { key: "id", indexes: [{ name: "invoiceId", keyPath: "invoiceId" }, { name: "paymentDate", keyPath: "paymentDate" }] },
    employees: { key: "id", indexes: [{ name: "code", keyPath: "code", unique: true }] },
    salaryPayments: { key: "id", indexes: [{ name: "payeeId", keyPath: "payeeId" }, { name: "salaryMonth", keyPath: "salaryMonth" }] },
    expenseCategories: { key: "id", indexes: [] },
    expenses: { key: "id", indexes: [{ name: "expenseDate", keyPath: "expenseDate" }] },
    incomeCategories: { key: "id", indexes: [] },
    miscIncome: { key: "id", indexes: [{ name: "incomeDate", keyPath: "incomeDate" }] },
    announcements: { key: "id", indexes: [{ name: "publishedAt", keyPath: "publishedAt" }] },
    notifications: { key: "id", indexes: [{ name: "readAt", keyPath: "readAt" }] },
    auditLogs: { key: "id", indexes: [{ name: "createdAt", keyPath: "createdAt" }, { name: "userId", keyPath: "userId" }] },
    roster: { key: "admission", indexes: [] },
    files: { key: "id", indexes: [{ name: "entity", keyPath: "entity" }] }
  };

  function request(req) {
    return new Promise(function (resolve, reject) {
      req.onsuccess = function () { resolve(req.result); };
      req.onerror = function () { reject(req.error || new Error("IndexedDB request failed")); };
    });
  }

  function upgrade(event) {
    var database = event.target.result;
    Object.keys(STORES).forEach(function (name) {
      var spec = STORES[name];
      var store = database.objectStoreNames.contains(name)
        ? event.target.transaction.objectStore(name)
        : database.createObjectStore(name, { keyPath: spec.key });
      spec.indexes.forEach(function (idx) {
        if (!store.indexNames.contains(idx.name)) {
          store.createIndex(idx.name, idx.keyPath, { unique: !!idx.unique });
        }
      });
    });
  }

  function init() {
    if (!window.indexedDB) {
      var error = new Error("IndexedDB is not available on this device");
      readyReject(error);
      return Promise.reject(error);
    }
    if (db) return Promise.resolve(db);
    return new Promise(function (resolve, reject) {
      var req;
      try { req = indexedDB.open(NAME, VERSION); } catch (e) { readyReject(e); reject(e); return; }
      req.onupgradeneeded = upgrade;
      req.onsuccess = function () {
        db = req.result;
        db.onversionchange = function () { db.close(); db = null; };
        // Resolve readiness before migration because migration uses the public
        // transaction helpers, which intentionally wait for this promise.
        readyResolve(db);
        migrateLegacy().then(function () {
          readyResolve(db); resolve(db);
        }).catch(function (e) { readyReject(e); reject(e); });
      };
      req.onerror = function () { readyReject(req.error); reject(req.error); };
    });
  }

  function tx(storeNames, mode, work) {
    return ready.then(function () {
      return new Promise(function (resolve, reject) {
        var transaction = db.transaction(storeNames, mode);
        var result;
        transaction.oncomplete = function () { resolve(result); };
        transaction.onerror = function () { reject(transaction.error || new Error("IndexedDB transaction failed")); };
        transaction.onabort = function () { reject(transaction.error || new Error("IndexedDB transaction aborted")); };
        try { result = work(transaction); } catch (e) { reject(e); }
      });
    });
  }

  function put(storeName, value) {
    return tx([storeName], "readwrite", function (t) {
      return request(t.objectStore(storeName).put(value));
    });
  }

  function bulkPut(storeName, values) {
    return tx([storeName], "readwrite", function (t) {
      var store = t.objectStore(storeName);
      values.forEach(function (value) { store.put(value); });
      return values.length;
    });
  }

  function get(storeName, key) {
    return tx([storeName], "readonly", function (t) {
      return request(t.objectStore(storeName).get(key));
    });
  }

  function all(storeName) {
    return tx([storeName], "readonly", function (t) {
      return request(t.objectStore(storeName).getAll());
    });
  }

  function remove(storeName, key) {
    return tx([storeName], "readwrite", function (t) {
      return request(t.objectStore(storeName).delete(key));
    });
  }

  function clear(storeName) {
    return tx([storeName], "readwrite", function (t) {
      return request(t.objectStore(storeName).clear());
    });
  }

  function migrateLegacy() {
    return get("appMeta", "legacy-v1-migrated").then(function (marker) {
      if (marker) return;
      var records = [];
      var roster = {};
      try { records = JSON.parse(localStorage.getItem("apk_records") || "[]") || []; } catch (e) {}
      try { roster = JSON.parse(localStorage.getItem("apk_roster") || "{}") || {}; } catch (e) {}
      var jobs = [];
      records.forEach(function (row, index) {
        jobs.push(put("attendanceRecords", {
          id: row.id || ("legacy-" + index + "-" + Date.now()),
          admission: row.admission || "",
          studentName: row.name || "",
          date: row.date || "",
          status: row.status || "present",
          remarks: row.remarks || "",
          timeIn: row.timeIn || "",
          timeOut: row.timeOut || "",
          synced: !!row.synced,
          createdAt: row.createdAt || Date.now(),
          source: "legacy-localStorage"
        }));
      });
      Object.keys(roster).forEach(function (admission) {
        var person = roster[admission] || {};
        jobs.push(put("roster", { admission: admission, name: person.name || "", className: person.class || "" }));
      });
      jobs.push(put("settings", { key: "language", value: localStorage.getItem("apk_lang") ? JSON.parse(localStorage.getItem("apk_lang")) : "fa" }));
      jobs.push(put("appMeta", { key: "legacy-v1-migrated", value: true, migratedAt: Date.now(), recordCount: records.length }));
      return Promise.all(jobs);
    });
  }

  function mirrorAttendance(rows) {
    return bulkPut("attendanceRecords", (rows || []).map(function (row, index) {
      return {
        id: row.id || ("mirror-" + index + "-" + Date.now()),
        admission: row.admission || "",
        studentName: row.name || "",
        date: row.date || "",
        status: row.status || "present",
        remarks: row.remarks || "",
        timeIn: row.timeIn || "",
        timeOut: row.timeOut || "",
        synced: !!row.synced,
        createdAt: row.createdAt || Date.now(),
        source: "attendance-ui"
      };
    })).catch(function () { return 0; });
  }

  function exportAll() {
    return ready.then(function () {
      var names = Object.keys(STORES);
      return Promise.all(names.map(all)).then(function (values) {
        var result = { database: NAME, version: VERSION, exportedAt: Date.now(), stores: {} };
        names.forEach(function (name, index) { result.stores[name] = values[index]; });
        return result;
      });
    });
  }

  init().catch(function () { /* UI remains usable on browsers without IndexedDB. */ });

  return {
    name: NAME,
    version: VERSION,
    stores: Object.keys(STORES),
    ready: ready,
    init: init,
    put: put,
    bulkPut: bulkPut,
    get: get,
    all: all,
    remove: remove,
    clear: clear,
    mirrorAttendance: mirrorAttendance,
    exportAll: exportAll
  };
})();
