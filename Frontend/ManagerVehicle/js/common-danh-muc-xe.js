(function (window) {
  "use strict";

  function apiHost() {
    return (window.location.hostname && window.location.hostname !== "localhost") ? window.location.hostname : "127.0.0.1";
  }

  function apiBase() {
    return "http://" + apiHost() + ":8081";
  }

  function escAttr(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/</g, "&lt;");
  }

  function normalizeText(value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .trim();
  }

  function fetchDanhMuc() {
    return fetch(apiBase() + "/api/danh-muc-xe/danh-sach")
      .then(function (res) {
        if (!res.ok) throw new Error("HTTP " + res.status);
        return res.json();
      });
  }

  function fillSelect(selectEl, list, emptyOption) {
    if (!selectEl) return;
    var html = "";
    if (emptyOption !== false) {
      var label = emptyOption && emptyOption.label ? emptyOption.label : "— Chọn danh mục —";
      var value = emptyOption && emptyOption.value !== undefined ? emptyOption.value : "";
      html += '<option value="' + escAttr(value) + '">' + escAttr(label) + "</option>";
    }
    (list || []).forEach(function (dm) {
      var ten = String((dm && dm.ten) || "").trim();
      if (!ten) return;
      html += '<option value="' + escAttr(ten) + '">' + escAttr(ten) + "</option>";
    });
    selectEl.innerHTML = html;
  }

  function fillSelectFilter(selectEl, list) {
    fillSelect(selectEl, list, { label: "Tất cả danh mục", value: "" });
  }

  window.HKTDanhMucXe = {
    apiBase: apiBase,
    escAttr: escAttr,
    normalizeText: normalizeText,
    fetchDanhMuc: fetchDanhMuc,
    fillSelect: fillSelect,
    fillSelectFilter: fillSelectFilter
  };
})(window);
