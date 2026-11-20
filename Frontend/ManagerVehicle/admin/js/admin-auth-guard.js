// Admin Auth Guard: Phân quyền quản trị (Admin, Quản lý, Nhân viên) và chặn khách/user truy cập trái phép
(function () {
  "use strict";

  function getStoredUser() {
    try {
      var raw = localStorage.getItem("thong_tin_user");
      if (!raw) {
        raw = sessionStorage.getItem("thong_tin_user");
      }
      if (!raw) {
        var match = document.cookie.match(new RegExp("(^| )thong_tin_user=([^;]+)"));
        if (match) raw = decodeURIComponent(match[2]);
      }
      if (!raw) {
        var searchParams = new URLSearchParams(window.location.search);
        var u = searchParams.get("u");
        if (u) {
          raw = decodeURIComponent(u);
          try { localStorage.setItem("thong_tin_user", raw); } catch (_) {}
          try { sessionStorage.setItem("thong_tin_user", raw); } catch (_) {}
          try {
            if (window.history && window.history.replaceState && window.location.protocol !== "file:") {
              window.history.replaceState({}, document.title, window.location.pathname);
            }
          } catch (_) {}
        }
      }
      return raw ? JSON.parse(raw) : null;
    } catch (e) {
      return null;
    }
  }

  function clearStoredUser() {
    try { localStorage.removeItem("thong_tin_user"); } catch (_) {}
    try { sessionStorage.removeItem("thong_tin_user"); } catch (_) {}
    try { document.cookie = "thong_tin_user=; Max-Age=-99999999; path=/;"; } catch (_) {}
  }

  function getRole() {
    var user = getStoredUser();
    return String((user && user.role) || "USER").trim().toUpperCase();
  }

  function isAdmin() {
    return getRole() === "ADMIN";
  }

  function isManager() {
    return getRole() === "MANAGER";
  }

  function isStaff() {
    return getRole() === "STAFF";
  }

  function hasAdminWorkspaceAccess() {
    var r = getRole();
    return r === "ADMIN" || r === "MANAGER" || r === "STAFF";
  }

  function getAuthHeaders() {
    var user = getStoredUser();
    if (!user) return {};
    return {
      "X-User-Id": String(user.id || ""),
      "X-User-Role": String(user.role || "USER").toUpperCase(),
      "X-User-Email": String(user.email || "")
    };
  }

  function getRoleBadgeHtml(role) {
    var r = String(role || "USER").toUpperCase();
    if (r === "ADMIN") {
      return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-shield-halved me-1"></i>Quản trị viên (Admin)</span>';
    }
    if (r === "MANAGER") {
      return '<span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="fa-solid fa-user-tie me-1"></i>Quản lý (Manager)</span>';
    }
    if (r === "STAFF") {
      return '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-id-badge me-1"></i>Nhân viên (Staff)</span>';
    }
    return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="fa-solid fa-user me-1"></i>Khách hàng</span>';
  }

  function checkAdminAccess() {
    var user = getStoredUser();
    if (!user) {
      alert("Truy cập bị từ chối!\nVui lòng đăng nhập với tài khoản Quản trị viên, Quản lý hoặc Nhân viên để tiếp tục.");
      window.location.replace("login.html");
      return false;
    }
    if (!hasAdminWorkspaceAccess()) {
      alert("Tài khoản của bạn (" + (user.email || "Khách hàng") + ") không có quyền truy cập khu vực Quản trị!\nHệ thống sẽ chuyển bạn về trang thuê xe.");
      window.location.replace("../car.html");
      return false;
    }
    return true;
  }

  // Chạy kiểm tra ngay lập tức trước khi render trang
  if (!checkAdminAccess()) return;

  // Xuất helper ra window để các trang admin sử dụng
  window.HKTAuth = {
    getUser: getStoredUser,
    getRole: getRole,
    isAdmin: isAdmin,
    isManager: isManager,
    isStaff: isStaff,
    hasAdminWorkspaceAccess: hasAdminWorkspaceAccess,
    getAuthHeaders: getAuthHeaders,
    getRoleBadgeHtml: getRoleBadgeHtml,
    clearUser: clearStoredUser
  };

  // Khi DOM sẵn sàng, gán thông tin admin và nút đăng xuất
  document.addEventListener("DOMContentLoaded", function () {
    try {
      var user = getStoredUser();
      var r = getRole();
      var displayName = (user && (user.name || user.email)) || "Người quản trị";

      // Cập nhật tên hiển thị ở header
      var nameEls = document.querySelectorAll("#adminHeaderName, .admin-user-display, #adminUserEmail, .topbar .text-gray-600");
      nameEls.forEach(function (el) {
        if (el) el.textContent = displayName;
      });

      // Cập nhật email trong dropdown menu
      var emailHeaderEl = document.getElementById("adminHeaderEmail");
      if (emailHeaderEl) {
        emailHeaderEl.textContent = (user && user.email) || "";
      }

      // Thêm huy hiệu vai trò vào cạnh logo hoặc cạnh tên người dùng
      var brandBadge = document.querySelector(".navbar-brand .badge");
      if (brandBadge) {
        if (r === "ADMIN") {
          brandBadge.className = "badge bg-danger-subtle text-danger ms-2 fs-7";
          brandBadge.innerHTML = '<i class="fa-solid fa-shield-halved me-1"></i>Admin';
        } else if (r === "MANAGER") {
          brandBadge.className = "badge bg-primary-subtle text-primary ms-2 fs-7";
          brandBadge.innerHTML = '<i class="fa-solid fa-user-tie me-1"></i>Quản lý';
        } else if (r === "STAFF") {
          brandBadge.className = "badge bg-success-subtle text-success ms-2 fs-7";
          brandBadge.innerHTML = '<i class="fa-solid fa-id-badge me-1"></i>Nhân viên';
        }
      }

      // Bắt sự kiện đăng xuất cho nút đăng xuất tường minh
      var logoutBtns = document.querySelectorAll("#btnAdminDangXuat, .btn-admin-logout, [data-action='logout']");
      logoutBtns.forEach(function (btn) {
        btn.addEventListener("click", function (e) {
          if (confirm("Bạn có chắc chắn muốn đăng xuất tài khoản?")) {
            clearStoredUser();
            window.location.replace("../index.html");
          } else {
            e.preventDefault();
          }
        });
      });
    } catch (_) {}
  });
})();
