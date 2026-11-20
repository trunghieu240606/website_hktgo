(function (window) {
  "use strict";

  function parseCurrentUser() {
    try {
      var searchParams = new URLSearchParams(window.location.search);
      // Nếu có cờ logout, xóa sạch session và xóa cờ khỏi URL
      if (searchParams.get("logout") === "1") {
        clearStoredUser();
        if (window.history && window.history.replaceState) {
          window.history.replaceState({}, document.title, window.location.pathname);
        }
        return null;
      }

      var raw = localStorage.getItem("thong_tin_user");
      if (!raw) {
        raw = sessionStorage.getItem("thong_tin_user");
      }
      if (!raw) {
        var match = document.cookie.match(new RegExp("(^| )thong_tin_user=([^;]+)"));
        if (match) raw = decodeURIComponent(match[2]);
      }
      if (!raw) {
        var u = searchParams.get("u");
        if (u) {
          raw = decodeURIComponent(u);
          try { localStorage.setItem("thong_tin_user", raw); } catch (_) {}
          try { sessionStorage.setItem("thong_tin_user", raw); } catch (_) {}
          // Xóa ngay ?u= khỏi thanh địa chỉ để tránh tự động login lại khi reload/logout
          try {
            if (window.history && window.history.replaceState && window.location.protocol !== "file:") {
              window.history.replaceState({}, document.title, window.location.pathname);
            }
          } catch (_) {}
        }
      }
      if (!raw) return null;
      return JSON.parse(raw);
    } catch (err) {
      return null;
    }
  }

  function clearStoredUser() {
    try { localStorage.removeItem("thong_tin_user"); } catch (_) {}
    try { sessionStorage.removeItem("thong_tin_user"); } catch (_) {}
    try { localStorage.removeItem("currentUser"); } catch (_) {}
    try { sessionStorage.removeItem("currentUser"); } catch (_) {}
    try {
      document.cookie = "thong_tin_user=; Max-Age=-99999999; path=/;";
      document.cookie = "thong_tin_user=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
      document.cookie = "thong_tin_user=; expires=Thu, 01 Jan 1970 00:00:00 UTC;";
    } catch (_) {}
  }

  function executeLogout() {
    clearStoredUser();
    // Chuyển hướng mọi tài khoản sau khi đăng xuất đều trở về trang index
    var path = window.location.pathname || "";
    if (path.indexOf("/admin/") !== -1) {
      window.location.href = "../index.html";
    } else {
      window.location.href = "index.html";
    }
  }

  function renderAuthMenu(config) {
    var navAuthArea = document.getElementById(config.navAuthAreaId || "navAuthArea");
    if (!navAuthArea) return;

    var user = parseCurrentUser();
    if (!user) return;

    var role = String(user.role || "").trim().toUpperCase();
    var displayName = user.name || user.hoTen || user.email || "Tài khoản";
    var isStaffOrAdmin = role === "ADMIN" || role === "MANAGER" || role === "STAFF";

    navAuthArea.innerHTML =
      '<div class="user-avatar-menu">' +
      '<button type="button" class="user-avatar-btn" aria-label="Tài khoản"><i class="ion-ios-person"></i></button>' +
      '<div class="user-avatar-dropdown">' +
      '<div class="user-avatar-email">' +
      displayName +
      "</div>" +
      (isStaffOrAdmin && config.adminLink
        ? '<a href="' + config.adminLink + '" class="user-avatar-link">Vào quản trị</a>'
        : "") +
      '<a href="javascript:void(0)" id="' +
      (config.logoutId || "btnDangXuat") +
      '" class="user-avatar-link logout btn-logout-action">Đăng xuất</a>' +
      "</div></div>";

    var navDon = document.getElementById(config.navOrdersId || "navDonCuaToi");
    if (navDon && role !== "ADMIN") {
      navDon.classList.remove("d-none");
    }

    var logoutBtn = document.getElementById(config.logoutId || "btnDangXuat");
    if (logoutBtn) {
      logoutBtn.onclick = function (e) {
        if (e) e.preventDefault();
        executeLogout();
      };
    }
  }

  // Lắng nghe sự kiện click trên toàn trang để đảm bảo nút logout và chặn truy cập đơn khi chưa đăng nhập
  document.addEventListener("click", function (e) {
    var target = e.target;
    var logoutEl = target.closest(".btn-logout-action, .user-avatar-link.logout, [data-action='logout-guest']");
    if (logoutEl) {
      e.preventDefault();
      executeLogout();
      return;
    }

    // Chặn người dùng chưa đăng nhập bấm vào liên kết Đơn của tôi
    var donLink = target.closest('a[href*="don-cua-toi.html"]');
    if (donLink) {
      var currentUser = parseCurrentUser();
      if (!currentUser || (!currentUser.email && !currentUser.id)) {
        e.preventDefault();
        alert("Vui lòng đăng nhập để xem đơn của bạn!");
        var loginUrl = (window.location.pathname.indexOf("/admin/") !== -1) ? "login.html" : "admin/login.html";
        window.location.href = loginUrl;
      }
    }
  });

  window.HKTAuth = {
    parseCurrentUser: parseCurrentUser,
    clearStoredUser: clearStoredUser,
    executeLogout: executeLogout,
    renderAuthMenu: renderAuthMenu
  };
})(window);
