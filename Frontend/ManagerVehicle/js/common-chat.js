(function (window) {
  "use strict";

  function getApiBase() {
    var host = window.location.hostname;
    if (!host || host === "localhost") return "http://127.0.0.1:8081";
    return "http://" + host + ":8081";
  }

  function escapeHtml(str) {
    return String(str || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function formatMessageText(str) {
    return escapeHtml(str).replace(/\n/g, "<br>");
  }

  function resolveCurrentUser() {
    var fallback = "Khách hàng";
    try {
      var raw = localStorage.getItem("thong_tin_user");
      var user = raw ? JSON.parse(raw) : null;
      if (user && (user.email || user.hoTen)) {
        return String(user.email || user.hoTen).trim();
      }
    } catch (err) {}
    return fallback;
  }

  function initDirectChat() {
    var popup = document.getElementById("sellerDirectChatPopup");
    var btnOpen = document.getElementById("sellerChatOpenDirect");
    var btnClose = document.getElementById("sellerChatClose");
    var btnSend = document.getElementById("sellerChatSend");
    var input = document.getElementById("sellerChatInput");
    var box = document.getElementById("sellerChatMessages");
    var toggleModeBtn = document.getElementById("btnToggleChatMode");
    var headElem = document.querySelector(".seller-chat-popup-head");
    var modeIcon = document.getElementById("sellerChatModeIcon");
    var titleElem = document.getElementById("sellerChatTitle");
    var subElem = document.getElementById("sellerChatSub");
    var chipsBar = document.getElementById("sellerChatChips");

    if (!popup || !btnOpen || !btnClose || !btnSend || !input || !box) return;
    if (popup.dataset.chatInited) return;
    popup.dataset.chatInited = "1";

    // Ẩn các thành phần không cần thiết theo yêu cầu người dùng
    if (toggleModeBtn) toggleModeBtn.style.display = "none";
    if (subElem) subElem.style.display = "none";
    if (chipsBar) chipsBar.style.display = "none";

    var apiBase = getApiBase();
    var currentUser = resolveCurrentUser();
    var currentMode = "AI"; // "AI" (mặc định) hoặc "HUMAN"
    var isSubmitting = false;

    function applyModeUI(mode) {
      currentMode = mode;
      if (mode === "HUMAN") {
        if (headElem) headElem.classList.add("mode-human");
        if (modeIcon) modeIcon.textContent = "👤";
        if (titleElem) titleElem.textContent = "Nhân viên HKTGO";
      } else {
        if (headElem) headElem.classList.remove("mode-human");
        if (modeIcon) modeIcon.textContent = "🤖";
        if (titleElem) titleElem.textContent = "Trợ lí HKTGO";
      }
      if (subElem) subElem.style.display = "none";
      if (toggleModeBtn) toggleModeBtn.style.display = "none";
      if (chipsBar) chipsBar.style.display = "none";
    }

    function renderLogs(logs) {
      if (!logs || !logs.length) {
        box.innerHTML =
          '<div class="seller-chat-msg ai">' +
          '<div class="seller-chat-msg-author">🤖 Trợ lí HKTGO</div>' +
          'Dạ em chào anh/chị! 🛵 Em là Trợ lí HKTGO. Anh/chị đang quan tâm đến dòng xe nào hay cần hỗ trợ thông tin gì ạ?' +
          '<div class="chat-meet-staff-wrapper mt-2 pt-1 border-top" style="border-color: rgba(0,0,0,0.08) !important;">' +
          '<button type="button" class="btn btn-sm btn-link p-0 text-primary font-weight-bold btn-meet-staff" style="text-decoration: underline; font-size: 13px; cursor: pointer;">' +
          '💬 Gặp nhân viên tư vấn' +
          '</button>' +
          '</div>' +
          '</div>';
      } else {
        var html = "";
        logs.forEach(function (m) {
          var role = String(m.fromRole || m.from_role || "USER").toUpperCase();
          if (role === "SYSTEM") {
            html += '<div class="seller-chat-msg system">' +
              formatMessageText(m.noiDung || "") +
              (currentMode === "HUMAN" ? '<div class="mt-1 text-center"><button type="button" class="btn btn-xs btn-link p-0 text-muted btn-back-ai" style="font-size: 11px; text-decoration: underline;">🤖 Quay lại Trợ lí HKTGO</button></div>' : '') +
              '</div>';
          } else if (role === "AI") {
            html +=
              '<div class="seller-chat-msg ai">' +
              '<div class="seller-chat-msg-author">🤖 Trợ lí HKTGO</div>' +
              formatMessageText(m.noiDung || "") +
              '<div class="chat-meet-staff-wrapper mt-2 pt-1 border-top" style="border-color: rgba(0,0,0,0.08) !important;">' +
              '<button type="button" class="btn btn-sm btn-link p-0 text-primary font-weight-bold btn-meet-staff" style="text-decoration: underline; font-size: 13px; cursor: pointer;">' +
              '💬 Gặp nhân viên tư vấn' +
              '</button>' +
              '</div>' +
              '</div>';
          } else if (role === "ADMIN") {
            html +=
              '<div class="seller-chat-msg seller">' +
              '<div class="seller-chat-msg-author text-success">👨‍💼 Nhân viên tư vấn</div>' +
              formatMessageText(m.noiDung || "") +
              '<div class="mt-1"><button type="button" class="btn btn-xs btn-link p-0 text-muted btn-back-ai" style="font-size: 11px;">🤖 Quay lại Trợ lí HKTGO</button></div>' +
              '</div>';
          } else {
            // USER (Khách)
            html +=
              '<div class="seller-chat-msg me">' +
              formatMessageText(m.noiDung || "") +
              '</div>';
          }
        });
        box.innerHTML = html;
      }
      box.scrollTop = box.scrollHeight;
    }

    function showTypingIndicator() {
      var existing = document.getElementById("chatTypingIndicator");
      if (existing) return;
      var div = document.createElement("div");
      div.id = "chatTypingIndicator";
      div.className = "seller-chat-msg ai";
      div.innerHTML = '<div class="seller-chat-msg-author">🤖 Trợ lí HKTGO</div><em><i class="fa-solid fa-spinner fa-spin mr-1"></i>Đang soạn câu trả lời...</em>';
      box.appendChild(div);
      box.scrollTop = box.scrollHeight;
    }

    function hideTypingIndicator() {
      var existing = document.getElementById("chatTypingIndicator");
      if (existing) existing.remove();
    }

    function loadMessages() {
      fetch(apiBase + "/api/chat/danh-sach?limit=80&user=" + encodeURIComponent(currentUser))
        .then(function (r) {
          return r.ok ? r.json() : [];
        })
        .then(function (list) {
          renderLogs(list || []);
        })
        .catch(function () {});

      // Kiểm tra chế độ hiện tại
      fetch(apiBase + "/api/chat/che-do?user=" + encodeURIComponent(currentUser))
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (res) {
          if (res && res.cheDo && res.cheDo !== currentMode) {
            applyModeUI(res.cheDo);
          }
        })
        .catch(function () {});
    }

    function chuyenCheDo(mode) {
      applyModeUI(mode);
      fetch(apiBase + "/api/chat/doi-che-do", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          user: currentUser,
          cheDo: mode
        })
      })
        .then(function (r) { return r.json(); })
        .then(function () {
          loadMessages();
        })
        .catch(function () {});
    }

    // Bắt sự kiện click vào nút "Gặp nhân viên tư vấn" hoặc "Quay lại AI" bên dưới câu trả lời
    box.addEventListener("click", function (e) {
      var btnMeet = e.target.closest(".btn-meet-staff");
      if (btnMeet) {
        e.preventDefault();
        chuyenCheDo("HUMAN");
        return;
      }
      var btnBack = e.target.closest(".btn-back-ai");
      if (btnBack) {
        e.preventDefault();
        chuyenCheDo("AI");
        return;
      }
    });

    function guiTinNhan(text) {
      var value = (text || input.value).trim();
      if (!value || isSubmitting) return;

      isSubmitting = true;
      btnSend.disabled = true;

      // Hiển thị ngay tin nhắn của người dùng trong box
      var tempMsg = document.createElement("div");
      tempMsg.className = "seller-chat-msg me";
      tempMsg.innerHTML = formatMessageText(value);
      box.appendChild(tempMsg);
      box.scrollTop = box.scrollHeight;

      if (currentMode === "AI") {
        showTypingIndicator();
      }

      input.value = "";

      fetch(apiBase + "/api/chat/gui", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          noiDung: value,
          nguoiGui: currentUser,
          fromRole: "USER",
          cheDo: currentMode
        })
      })
        .then(function (res) {
          if (!res.ok) throw new Error("HTTP " + res.status);
          return res.json();
        })
        .then(function (data) {
          hideTypingIndicator();
          if (data && data.cheDo) {
            applyModeUI(data.cheDo);
          }
          loadMessages();
        })
        .catch(function () {
          hideTypingIndicator();
          alert("Không gửi được tin nhắn. Vui lòng kiểm tra backend cổng 8081.");
        })
        .finally(function () {
          isSubmitting = false;
          btnSend.disabled = false;
        });
    }

    btnOpen.addEventListener("click", function () {
      popup.classList.add("open");
      loadMessages();
      input.focus();
    });

    btnClose.addEventListener("click", function () {
      popup.classList.remove("open");
    });

    btnSend.addEventListener("click", function () {
      guiTinNhan();
    });

    input.addEventListener("keydown", function (e) {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        guiTinNhan();
      }
    });

    // Mặc định áp dụng giao diện AI
    applyModeUI("AI");
    loadMessages();

    // Tự động kiểm tra tin nhắn mới mỗi 4 giây
    setInterval(function () {
      if (popup.classList.contains("open")) {
        loadMessages();
      }
    }, 4000);
  }

  window.HKTChat = {
    initDirectChat: initDirectChat
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initDirectChat);
  } else {
    initDirectChat();
  }
})(window);
