(function (window) {
  "use strict";

  function parseMDY(value) {
    if (!value || typeof value !== "string") return null;
    var parts = value.trim().split("/");
    if (parts.length !== 3) return null;
    var m = parseInt(parts[0], 10);
    var d = parseInt(parts[1], 10);
    var y = parseInt(parts[2], 10);
    if (!m || !d || !y) return null;
    return new Date(y, m - 1, d);
  }

  function rentalDayCount(pickStr, dropStr) {
    var start = parseMDY(pickStr);
    var end = parseMDY(dropStr);
    if (!start || !end) return 1;
    var t1 = Date.UTC(start.getFullYear(), start.getMonth(), start.getDate());
    var t2 = Date.UTC(end.getFullYear(), end.getMonth(), end.getDate());
    var diff = Math.round((t2 - t1) / 86400000);
    if (diff < 0) return 0;
    return Math.max(1, diff + 1);
  }

  function getPolicyMinDate() {
    var now = new Date();
    var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    var apr2026 = new Date(2026, 3, 1);
    return today.getTime() > apr2026.getTime() ? today : apr2026;
  }

  function addThreeMonths(date) {
    var copy = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    copy.setMonth(copy.getMonth() + 3);
    return copy;
  }

  function validateBookingDatesShortTerm(pickStr, dropStr) {
    var policyMin = getPolicyMinDate();
    var pick = parseMDY(pickStr);
    var drop = parseMDY(dropStr);
    if (!pick || !drop) {
      return {
        ok: false,
        msg: "Vui lòng chọn đủ ngày nhận và ngày trả xe."
      };
    }
    var pickDay = new Date(pick.getFullYear(), pick.getMonth(), pick.getDate());
    var dropDay = new Date(drop.getFullYear(), drop.getMonth(), drop.getDate());
    if (pickDay < policyMin) {
      return {
        ok: false,
        msg: "Ngày nhận xe phải từ 01/04/2026 và không được trước ngày hôm nay."
      };
    }
    if (dropDay < pickDay) {
      return {
        ok: false,
        msg: "Ngày trả xe phải cùng hoặc sau ngày nhận xe."
      };
    }
    if (dropDay > addThreeMonths(pickDay)) {
      return {
        ok: false,
        msg: "Thuê ngắn hạn tối đa 3 tháng kể từ ngày nhận xe."
      };
    }
    return { ok: true };
  }

  function normalizeTrangThaiXe(status) {
    return String(status || "")
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/đ/g, "d")
      .replace(/\s+/g, " ")
      .trim();
  }

  function laXeDangRanh(xe) {
    var trangThai = normalizeTrangThaiXe(xe && xe.trangThai);
    if (!trangThai || trangThai === "null") return false;
    if (trangThai.indexOf("dang thue") !== -1) return false;
    return (
      trangThai === "dang ranh" ||
      trangThai === "san sang" ||
      trangThai.indexOf("dang ranh") !== -1 ||
      trangThai.indexOf("san sang") !== -1 ||
      trangThai.indexOf("available") !== -1 ||
      String((xe && xe.trangThai) || "").indexOf("ráº£nh") !== -1
    );
  }

  window.HKTBookingUtils = {
    parseMDY: parseMDY,
    rentalDayCount: rentalDayCount,
    getPolicyMinDate: getPolicyMinDate,
    addThreeMonths: addThreeMonths,
    validateBookingDatesShortTerm: validateBookingDatesShortTerm,
    normalizeTrangThaiXe: normalizeTrangThaiXe,
    laXeDangRanh: laXeDangRanh
  };
})(window);
