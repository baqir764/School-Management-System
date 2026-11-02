/* Thin client for the server token API (routes/api.php):
 * POST {server}/api/login  -> { token, name, email, role }
 * POST {server}/api/attendance/sync (Bearer token) -> { imported, failed, errors[] } */
var Api = (function () {
  function base() { return Store.getServer() + "api/"; }

  function post(path, body, token) {
    var headers = { "Content-Type": "application/json", "Accept": "application/json" };
    if (token) headers.Authorization = "Bearer " + token;
    return fetch(base() + path, { method: "POST", headers: headers, body: JSON.stringify(body) })
      .then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
          return { status: res.status, ok: res.ok, data: data };
        });
      });
  }

  return {
    login: function (email, password) {
      return post("login", { email: email, password: password, device_name: "attendance-apk" });
    },
    sync: function (token, records) {
      return post("attendance/sync", { records: records }, token);
    }
  };
})();
