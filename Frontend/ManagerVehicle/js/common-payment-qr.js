(function (window) {
  "use strict";

  function createQrPaymentFlow(config) {
    var ctx = {
      orderId: null,
      amount: 0,
      channel: "MoMo",
      email: "",
      soDienThoai: "",
      expireAtMs: 0,
      timerId: null
    };

    function stopCountdown() {
      if (ctx.timerId) {
        clearInterval(ctx.timerId);
        ctx.timerId = null;
      }
    }

    function copyToClipboard(text, successMsg) {
      if (!text) return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
          alert(successMsg || ("Đã sao chép: " + text));
        }).catch(function() {
          prompt("Sao chép thủ công:", text);
        });
      } else {
        prompt("Sao chép thủ công:", text);
      }
    }

    function updateQr(amount, orderId) {
      var img = document.getElementById(config.qrImageId);
      var hint = document.getElementById(config.qrHintId);
      var amountEl = document.getElementById(config.amountId);
      var ndEl = document.getElementById("valNoiDungChuyenKhoan");
      var stkEl = document.getElementById("valSoTaiKhoan");

      var noiDung = "HKTGO DH" + (orderId || "1") + (ctx.soDienThoai ? " " + ctx.soDienThoai : "");

      if (amountEl) {
        amountEl.textContent = Number(amount || 0).toLocaleString("vi-VN") + " ₫";
      }

      if (ndEl) {
        ndEl.textContent = noiDung;
      }

      if (hint) {
        hint.textContent = "Thanh toán quét mã MoMo / VietQR cho đơn #" + (orderId || "PENDING");
      }

      if (img) {
        // Tự động gắn số tiền và nội dung chuyển khoản cố định vào mã VietQR
        var vietQrUrl = "https://img.vietqr.io/image/MB-0365145525-compact2.png?amount=" + 
          encodeURIComponent(amount || 0) + 
          "&addInfo=" + encodeURIComponent(noiDung) + 
          "&accountName=TRAN%20TRUNG%20HIEU";
        
        img.onerror = function() {
          this.onerror = null;
          this.src = config.staticQrImage || "images/momo-qr-fixed.png";
        };
        img.src = vietQrUrl;
      }

      // Gắn sự kiện copy nút sao chép
      var btnCopyNd = document.querySelector(".btn-copy-nd");
      if (btnCopyNd) {
        btnCopyNd.onclick = function() {
          copyToClipboard(noiDung, "Đã sao chép nội dung chuyển khoản: " + noiDung);
        };
      }
      var btnCopyStk = document.querySelector(".btn-copy-stk");
      if (btnCopyStk) {
        btnCopyStk.onclick = function() {
          copyToClipboard((stkEl && stkEl.textContent) || "0365145525", "Đã sao chép số tài khoản: 0365145525");
        };
      }
    }

    function startCountdown() {
      var countdownEl = document.getElementById(config.countdownId);
      if (!countdownEl) return;
      stopCountdown();
      ctx.timerId = setInterval(function () {
        var ms = ctx.expireAtMs - Date.now();
        if (ms <= 0) {
          countdownEl.textContent = "Đơn đã quá hạn thanh toán 15 phút.";
          stopCountdown();
          return;
        }
        var totalSeconds = Math.floor(ms / 1000);
        var mm = String(Math.floor(totalSeconds / 60)).padStart(2, "0");
        var ss = String(totalSeconds % 60).padStart(2, "0");
        countdownEl.textContent = "Hạn thanh toán còn: " + mm + ":" + ss;
      }, 1000);
    }

    function showModal() {
      if (!window.jQuery) return;
      window.jQuery("#" + config.modalId).modal("show");
    }

    function hideModal() {
      if (!window.jQuery) return;
      window.jQuery("#" + config.modalId).modal("hide");
    }

    function initModalCleanup() {
      if (!window.jQuery) return;
      window.jQuery("#" + config.modalId).on("hidden.bs.modal", function () {
        stopCountdown();
      });
    }

    function open(options) {
      var btnConfirm = document.getElementById(config.btnConfirmId);
      if (!btnConfirm || !window.jQuery) return;

      ctx.orderId = options.orderId;
      ctx.amount = Number(options.amount || 0);
      ctx.channel = "MoMo";
      ctx.email = options.email || "";
      ctx.soDienThoai = options.soDienThoai || "";
      ctx.expireAtMs =
        Number(options.expireAtMs || 0) > 0
          ? Number(options.expireAtMs)
          : Date.now() + 15 * 60 * 1000;

      updateQr(ctx.amount, ctx.orderId);

      btnConfirm.onclick = function () {
        if (!ctx.orderId) return;
        btnConfirm.disabled = true;
        btnConfirm.textContent = "Đang xác nhận...";

        var params = new URLSearchParams({
          email: ctx.email,
          soDienThoai: ctx.soDienThoai,
          kenhThanhToan: ctx.channel
        });

        fetch(
          config.apiBase +
            "/api/don-thue/xac-nhan-thanh-toan/" +
            ctx.orderId +
            "?" +
            params.toString(),
          { method: "PUT" }
        )
          .then(function (res) {
            return res.text().then(function (txt) {
              if (!res.ok) throw new Error(txt || "Xác nhận thất bại");
              return txt;
            });
          })
          .then(function (msg) {
            alert(msg || "Đã ghi nhận thanh toán thành công!");
            hideModal();
            if (typeof config.onConfirmed === "function") {
              config.onConfirmed();
            }
          })
          .catch(function (err) {
            alert(err.message || "Không xác nhận được thanh toán.");
          })
          .finally(function () {
            btnConfirm.disabled = false;
            btnConfirm.textContent = "Tôi Đã Hoàn Tất Thanh Toán";
          });
      };

      startCountdown();
      showModal();
    }

    initModalCleanup();

    return {
      open: open,
      stopCountdown: stopCountdown
    };
  }

  window.HKTPaymentQr = {
    createQrPaymentFlow: createQrPaymentFlow
  };
})(window);
