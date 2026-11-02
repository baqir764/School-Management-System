/* Offline compatibility store.
 * The attendance screen uses synchronous localStorage APIs. Stage 1 mirrors
 * those records into IndexedDB so the durable database is ready for the next
 * native/offline modules without breaking the existing screen. */
var Store = (function () {
  var K = { lang: "apk_lang", token: "apk_token", user: "apk_user",
            server: "apk_server", records: "apk_records", roster: "apk_roster" };

  function get(k, fallback) {
    try {
      var v = localStorage.getItem(k);
      return v === null ? fallback : JSON.parse(v);
    } catch (e) { return fallback; }
  }
  function set(k, v) { localStorage.setItem(k, JSON.stringify(v)); }
  function mirror(records) {
    if (window.OfflineDB && OfflineDB.mirrorAttendance) OfflineDB.mirrorAttendance(records);
  }

  return {
    getLang: function () { return get(K.lang, "fa"); },
    setLang: function (l) {
      set(K.lang, l);
      if (window.OfflineDB) OfflineDB.put("settings", { key: "language", value: l }).catch(function () {});
    },
    getToken: function () { return get(K.token, null); },
    setToken: function (t) { set(K.token, t); },
    clearToken: function () { localStorage.removeItem(K.token); localStorage.removeItem(K.user); },
    getUser: function () { return get(K.user, null); },
    setUser: function (u) { set(K.user, u); },
    getServer: function () { return ""; },
    setServer: function () {},
    getRecords: function () { return get(K.records, []); },
    saveRecords: function (r) { set(K.records, r); mirror(r); },
    addRecord: function (r) {
      var all = Store.getRecords();
      r.id = "r" + Date.now() + Math.floor(Math.random() * 1000);
      r.synced = false; r.createdAt = Date.now();
      all.push(r); Store.saveRecords(all);
      return r;
    },
    markSynced: function (ids) {
      var left = Store.getRecords().filter(function (r) { return ids.indexOf(r.id) < 0; });
      Store.saveRecords(left);
    },
    removeRecord: function (id) {
      var left = Store.getRecords().filter(function (r) { return r.id !== id; });
      Store.saveRecords(left);
      if (window.OfflineDB) OfflineDB.remove("attendanceRecords", id).catch(function () {});
    },
    pending: function () { return Store.getRecords().filter(function (r) { return !r.synced; }); },
    getRoster: function () { return get(K.roster, {}); },
    setRoster: function (r) {
      set(K.roster, r);
      if (window.OfflineDB) {
        Object.keys(r || {}).forEach(function (admission) {
          var person = r[admission] || {};
          OfflineDB.put("roster", { admission: admission, name: person.name || "", className: person.class || "" }).catch(function () {});
        });
      }
    }
  };
})();
