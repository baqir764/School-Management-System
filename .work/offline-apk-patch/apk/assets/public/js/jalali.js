/* Minimal Jalali (Shamsi) helpers for offline use: Gregorian->Jalali for
 * defaulting "today", plus month-length/leap validation. Core algorithm is
 * the public-domain jalaali-js calendar math. */
var Jalali = (function () {
  function div(a, b) { return ~~(a / b); }
  function mod(a, b) { return a - ~~(a / b) * b; }

  var BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210,
                1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

  function jalCalLeap(jy) {
    var bl = BREAKS.length, gy = jy + 621, leapJ = -14,
        jp = BREAKS[0], jm, jump, leap, leapG, march, n, i;
    if (jy < jp || jy >= BREAKS[bl - 1]) throw new Error("Invalid Jalaali year " + jy);
    for (i = 1; i < bl; i += 1) {
      jm = BREAKS[i]; jump = jm - jp;
      if (jy < jm) break;
      leapJ = leapJ + div(jump, 33) * 8 + div(mod(jump, 33), 4);
      jp = jm;
    }
    n = jy - jp;
    leapJ = leapJ + div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
    if (mod(jump, 33) === 4 && jump - n === 4) leapJ += 1;
    leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
    march = 20 + leapJ - leapG;
    if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33;
    leap = mod(mod(n + 1, 33) - 1, 4);
    if (leap === -1) leap = 4;
    return { leap: leap, gy: gy, march: march };
  }

  function isLeapYear(jy) { return jalCalLeap(jy).leap === 0; }

  function monthLength(jy, jm) {
    if (jm <= 6) return 31;
    if (jm <= 11) return 30;
    return isLeapYear(jy) ? 30 : 29;
  }

  var G_DAYS = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

  function toJalaali(gy, gm, gd) {
    var gy2 = (gm > 2) ? (gy + 1) : gy;
    var days = 355666 + (365 * gy) + div(gy2 + 3, 4) - div(gy2 + 99, 100) +
               div(gy2 + 399, 400) + gd + G_DAYS[gm - 1];
    var jy = -1595 + (33 * div(days, 12053));
    days %= 12053;
    jy += 4 * div(days, 1461);
    days %= 1461;
    if (days > 365) { jy += div(days - 1, 365); days = (days - 1) % 365; }
    var jm, jd;
    if (days < 186) { jm = 1 + div(days, 31); jd = 1 + mod(days, 31); }
    else { jm = 7 + div(days - 186, 30); jd = 1 + mod(days - 186, 30); }
    return { jy: jy, jm: jm, jd: jd };
  }

  function pad(n, len) { n = String(n); while (n.length < (len || 2)) n = "0" + n; return n; }

  function todayStr() {
    var d = new Date(), j = toJalaali(d.getFullYear(), d.getMonth() + 1, d.getDate());
    return j.jy + "-" + pad(j.jm) + "-" + pad(j.jd);
  }

  function isValid(str) {
    var m = /^(\d{3,4})-(\d{1,2})-(\d{1,2})$/.exec(String(str || "").replace(/\//g, "-"));
    if (!m) return false;
    var jy = +m[1], jm = +m[2], jd = +m[3];
    if (jm < 1 || jm > 12 || jd < 1) return false;
    try { return jd <= monthLength(jy, jm); } catch (e) { return false; }
  }

  return { todayStr: todayStr, isValid: isValid, monthLength: monthLength, isLeapYear: isLeapYear, toJalaali: toJalaali };
})();
