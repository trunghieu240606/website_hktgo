(function (window) {
  "use strict";

  function escAttr(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/</g, "&lt;");
  }

  function normalizeVehicleName(value) {
    return String(value || "")
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/\s+/g, " ")
      .trim();
  }

  function buildVehicleOptions(list) {
    var html = '<option value="">— Chọn xe —</option>';
    (list || []).forEach(function (xe) {
      var meta = {
        tenXe: xe.tenXe,
        bienSo: xe.bienSo,
        giaThue: Number(xe.giaThue) || 0
      };
      var label =
        meta.tenXe +
        " (" +
        meta.bienSo +
        ") — " +
        meta.giaThue.toLocaleString("vi-VN") +
        "₫/ngày";
      html +=
        '<option value="' +
        escAttr(JSON.stringify(meta)) +
        '">' +
        label +
        "</option>";
    });
    return html;
  }

  function selectVehicleByName(selectEl, targetName) {
    if (!selectEl || !selectEl.options || selectEl.options.length < 2) return false;
    var target = normalizeVehicleName(targetName);
    if (!target) return false;

    for (var i = 1; i < selectEl.options.length; i++) {
      var value = selectEl.options[i].value;
      if (!value) continue;
      try {
        var meta = JSON.parse(value);
        if (normalizeVehicleName(meta.tenXe) === target) {
          selectEl.selectedIndex = i;
          return true;
        }
      } catch (err) {}
    }

    for (var j = 1; j < selectEl.options.length; j++) {
      var text = normalizeVehicleName(selectEl.options[j].text || "");
      if (text.indexOf(target) !== -1) {
        selectEl.selectedIndex = j;
        return true;
      }
    }

    return false;
  }

  window.HKTVehicleOptions = {
    escAttr: escAttr,
    normalizeVehicleName: normalizeVehicleName,
    buildVehicleOptions: buildVehicleOptions,
    selectVehicleByName: selectVehicleByName
  };
})(window);
