(function (window) {
  "use strict";

  function getValidCustomerAccount() {
    try {
      var raw = localStorage.getItem("thong_tin_user");
      if (!raw) return null;
      var user = JSON.parse(raw);
      if (!user || !String(user.email || "").trim()) return null;
      var role = String(user.role || "").trim().toUpperCase();
      if (role === "ADMIN") return null;
      return user;
    } catch (err) {
      return null;
    }
  }

  function setButtonLabel(button, label) {
    if (!button) return;
    if (String(button.tagName || "").toUpperCase() === "INPUT") {
      button.value = label;
      return;
    }
    button.textContent = label;
  }

  function submit(config) {
    var customer = (config.getCustomer || getValidCustomerAccount)();
    if (!customer) {
      alert(config.requireLoginMessage || "Vui lòng đăng nhập để đặt xe.");
      if (config.loginRedirectUrl) {
        window.location.href = config.loginRedirectUrl;
      }
      return;
    }

    var button = document.getElementById(config.submitButtonId);
    if (button) {
      button.disabled = true;
      setButtonLabel(button, config.submittingText || "Đang gửi...");
    }

    var payload = Object.assign({}, config.payload || {});
    if (!String(payload.emailKhach || "").trim()) {
      payload.emailKhach = customer.email || "";
    }

    fetch(config.apiBase + "/api/don-thue/dat-xe", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    })
      .then(function (res) {
        if (!res.ok) {
          return res.text().then(function (txt) {
            throw { http: res.status, msg: txt };
          });
        }
        var ct = res.headers.get("content-type") || "";
        if (ct.indexOf("application/json") !== -1) return res.json();
        return res.text();
      })
      .then(function (responseData) {
        if (typeof config.onSuccess === "function") {
          config.onSuccess(responseData, customer);
        }
      })
      .catch(function (err) {
        if (err && err.http === 400) {
          alert(
            (err.msg && String(err.msg).trim()) ||
              config.server400Fallback ||
              "Dữ liệu gửi lên không hợp lệ."
          );
          return;
        }
        alert(config.genericErrorMessage || "Không gửi được đơn. Vui lòng thử lại.");
      })
      .finally(function () {
        if (button) {
          button.disabled = false;
          setButtonLabel(
            button,
            config.defaultButtonText || "Gửi đặt xe"
          );
        }
      });
  }

  window.HKTBookingSubmit = {
    getValidCustomerAccount: getValidCustomerAccount,
    submit: submit
  };
})(window);
