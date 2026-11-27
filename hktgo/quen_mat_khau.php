<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

function taoMatKhauManhNgauNhien(int $length = 10): string
{
    $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower   = 'abcdefghijkmnopqrstuvwxyz';
    $digits  = '23456789';
    $special = '@#$%&*!';

    $chars = [
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
        $special[random_int(0, strlen($special) - 1)],
    ];

    $all = $upper . $lower . $digits . $special;
    for ($i = 4; $i < $length; $i++) {
        $chars[] = $all[random_int(0, strlen($all) - 1)];
    }

    shuffle($chars);
    return implode('', $chars);
}

function capMatKhauTamChoUser(PDO $pdo, array $userRow): string
{
    $matKhauTam = taoMatKhauManhNgauNhien(10);
    $hashMoi    = password_hash($matKhauTam, PASSWORD_DEFAULT);

    $upd = $pdo->prepare("UPDATE users SET mat_khau = ? WHERE id = ?");
    $upd->execute([$hashMoi, (int) $userRow['id']]);

    if (!isset($_SESSION['temp_pwd_user_ids']) || !is_array($_SESSION['temp_pwd_user_ids'])) {
        $_SESSION['temp_pwd_user_ids'] = [];
    }
    $_SESSION['temp_pwd_user_ids'][(int) $userRow['id']] = true;

    return $matKhauTam;
}

$step         = 1;
$emailInput   = '';
$sdtInput     = '';
$cccdInput    = '';
$errors       = [];
$matKhauMoi   = null;
$tenKhachCap  = '';
$lyDoCapNhanh = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors[] = 'Phiên biểu mẫu không hợp lệ hoặc tệp ảnh quá lớn, vui lòng thử lại.';
    }

    $postStep   = (int) ($_POST['step'] ?? 1);
    $emailInput = trim((string) ($_POST['email'] ?? ''));
    $sdtInput   = normalizePhone((string) ($_POST['sdt'] ?? ''));
    $cccdInput  = preg_replace('/\s+/', '', (string) ($_POST['so_cccd'] ?? ''));

    if (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Vui lòng nhập đúng định dạng Email đã đăng ký.';
    }
    if (!preg_match('/^0\d{9}$/', $sdtInput)) {
        $errors[] = 'Số điện thoại phải gồm đúng 10 chữ số và bắt đầu bằng số 0.';
    }

    $userRow = null;
    $oldCccd = '';

    if (empty($errors)) {
        $stmtUser = $pdo->prepare("SELECT id, ho_ten, email, sdt, trang_thai FROM users WHERE LOWER(email) = LOWER(?) AND sdt = ? LIMIT 1");
        $stmtUser->execute([$emailInput, $sdtInput]);
        $userRow = $stmtUser->fetch();

        if (!$userRow) {
            $errors[] = 'Thông tin Email và Số điện thoại không trùng khớp với tài khoản nào trên hệ thống.';
        } elseif (($userRow['trang_thai'] ?? 'Hoạt động') === 'Bị khóa') {
            $errors[] = 'Tài khoản này hiện đang bị khóa do vi phạm/nợ phạt nguội. Vui lòng liên hệ trực tiếp cửa hàng HKTGO!';
        } else {
            $stmtCccd = $pdo->prepare("
                SELECT so_cccd
                FROM don_thue
                WHERE user_id = ? AND so_cccd IS NOT NULL AND so_cccd <> ''
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmtCccd->execute([(int) $userRow['id']]);
            $oldCccd = (string) ($stmtCccd->fetchColumn() ?: '');
        }
    }

    if (empty($errors) && $userRow) {
        if ($oldCccd === '') {
            $matKhauMoi   = capMatKhauTamChoUser($pdo, $userRow);
            $tenKhachCap  = $userRow['ho_ten'];
            $lyDoCapNhanh = true;
        } else {
            if ($postStep === 1) {
                $step = 2;
            } else {
                $step = 2;
                if (!preg_match('/^\d{12}$/', $cccdInput)) {
                    $errors[] = 'Vui lòng nhập đủ 12 chữ số định danh cá nhân / CCCD (VNeID).';
                } elseif ($cccdInput !== $oldCccd) {
                    $errors[] = 'Số định danh VNeID không trùng khớp với hồ sơ định danh đã lưu trên đơn thuê trước của tài khoản này.';
                }

                $hasFile = (isset($_FILES['anh_vneid']) && $_FILES['anh_vneid']['error'] !== UPLOAD_ERR_NO_FILE);
                if (!$hasFile) {
                    $errors[] = 'Vui lòng tải lên ảnh chụp màn hình VNeID (hoặc mặt trước CCCD) để đối chiếu chính chủ.';
                } elseif ($_FILES['anh_vneid']['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = 'Tải ảnh VNeID thất bại, vui lòng chọn lại ảnh.';
                } elseif ($_FILES['anh_vneid']['size'] > 8 * 1024 * 1024) {
                    $errors[] = 'Dung lượng ảnh VNeID không được vượt quá 8MB.';
                } else {
                    $ext = strtolower(pathinfo($_FILES['anh_vneid']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'jfif'], true)) {
                        $errors[] = 'Ảnh VNeID chỉ chấp nhận định dạng JPG, PNG hoặc WEBP.';
                    }
                }

                if (empty($errors)) {
                    $uploadDir = __DIR__ . '/uploads/giay_to';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0777, true);
                    }
                    $ext = strtolower(pathinfo($_FILES['anh_vneid']['name'], PATHINFO_EXTENSION));
                    $fileName = 'khoiphuc_vneid_' . (int) $userRow['id'] . '_' . time() . '.' . $ext;
                    @move_uploaded_file($_FILES['anh_vneid']['tmp_name'], $uploadDir . '/' . $fileName);

                    $matKhauMoi  = capMatKhauTamChoUser($pdo, $userRow);
                    $tenKhachCap = $userRow['ho_ten'];
                }
            }
        }
    }
}

$pageTitle = 'Quên mật khẩu tài khoản - HKTGO';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hkt-sub-banner">
    <div class="container">
        <h2 class="font-weight-bold text-white mb-1">
            <i class="fa-solid fa-key mr-2" style="color:#01d28e;"></i>Khôi Phục Mật Khẩu Tài Khoản
        </h2>
        <p class="text-white-50 mb-0">Xác minh thông tin chính chủ để nhận mật khẩu tạm thời đa ký tự</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="content-card">
                    <?php if ($matKhauMoi !== null): ?>
                        <div class="text-center py-3">
                            <div class="mb-3">
                                <span class="d-inline-flex align-items-center justify-content-center rounded-circle"
                                      style="width:68px;height:68px;background:#dcfce7;color:#16a34a;font-size:30px;">
                                    <i class="fa-solid fa-shield-check"></i>
                                </span>
                            </div>
                            <h4 class="font-weight-bold text-dark">Xác Thực Thành Công!</h4>
                            <p class="text-muted mb-2">
                                Xin chào <strong><?= e($tenKhachCap) ?></strong>, hệ thống đã cấp lại mật khẩu tạm thời (gồm chữ hoa, chữ thường, số và ký tự đặc biệt) cho tài khoản của bạn:
                            </p>

                            <?php if ($lyDoCapNhanh): ?>
                                <p class="small text-info mb-3">
                                    <i class="fa-solid fa-circle-check mr-1"></i>Tài khoản chưa phát sinh giao dịch thuê xe nên hệ thống xác thực trực tiếp qua Email &amp; Số điện thoại đăng ký.
                                </p>
                            <?php endif; ?>

                            <div class="p-3 rounded mx-auto mb-3" style="max-width:420px;background:#f8fafc;border:2px dashed #01d28e;">
                                <div class="small text-muted mb-1">Mật khẩu tạm thời của bạn:</div>
                                <div class="d-flex justify-content-center align-items-center">
                                    <code id="tempPasswordText" class="font-weight-bold text-danger" style="font-size:24px;letter-spacing:1.5px;"><?= e($matKhauMoi) ?></code>
                                    <button type="button" onclick="copyTempPassword()" class="btn btn-sm btn-outline-dark ml-3" title="Sao chép mật khẩu">
                                        <i class="fa-regular fa-copy mr-1"></i>Sao chép
                                    </button>
                                </div>
                                <div id="copyNotice" class="small text-success mt-1 d-none">Đã sao chép mật khẩu vào bộ nhớ tạm!</div>
                            </div>

                            <div class="alert alert-info text-left small mx-auto" style="max-width:520px;">
                                <i class="fa-solid fa-circle-info mr-1"></i>
                                <strong>Lưu ý bảo mật:</strong> Sau khi dùng mật khẩu tạm thời này để đăng nhập, hệ thống sẽ hiển thị bảng gợi ý để bạn tự đặt lại mật khẩu cá nhân mới (hoặc đổi sau trong mục <em>Tài khoản cá nhân</em>).
                            </div>

                            <a href="dang_nhap.php" class="btn btn-primary btn-lg font-weight-bold px-5 mt-2">
                                <i class="fa-solid fa-right-to-bracket mr-2"></i>Đi tới trang Đăng nhập
                            </a>
                        </div>
                    <?php else: ?>
                        <h4 class="font-weight-bold text-dark mb-2">
                            <?= $step === 1 ? 'Bước 1: Nhập Thông Tin Tài Khoản Đăng Ký' : 'Bước 2: Xác Thực Định Danh VNeID' ?>
                        </h4>
                        <p class="text-muted small mb-4">
                            <?php if ($step === 1): ?>
                                Nhập <strong>Email</strong> và <strong>Số điện thoại</strong> bạn đã dùng để đăng ký tài khoản. Nếu tài khoản đã từng thuê xe, hệ thống sẽ yêu cầu đối chiếu thêm VNeID ở bước tiếp theo.
                            <?php else: ?>
                                Tài khoản của bạn đã có hồ sơ giao dịch thuê xe trên hệ thống. Vui lòng nhập đúng <strong>Số VNeID (12 số)</strong> đã lưu và tải <strong>Ảnh chụp VNeID</strong> để hoàn tất khôi phục.
                            <?php endif; ?>
                        </p>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0 pl-3">
                                    <?php foreach ($errors as $err): ?>
                                        <li><?= e($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="quen_mat_khau.php" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="step" value="<?= (int) $step ?>">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-semibold">Email đăng ký tài khoản <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control <?= $step === 2 ? 'bg-light' : '' ?>" required
                                           <?= $step === 2 ? 'readonly' : '' ?>
                                           value="<?= e($emailInput) ?>" placeholder="example@gmail.com">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="font-weight-semibold">Số điện thoại đăng ký <span class="text-danger">*</span></label>
                                    <input type="text" name="sdt" class="form-control <?= $step === 2 ? 'bg-light' : '' ?>" required maxlength="10"
                                           <?= $step === 2 ? 'readonly' : '' ?>
                                           value="<?= e($sdtInput) ?>" placeholder="0912345678">
                                </div>
                            </div>

                            <?php if ($step === 2): ?>
                                <div class="p-3 rounded mb-4" style="background:#f8fafc;border:1px solid #cbd5e1;">
                                    <div class="row">
                                        <div class="col-md-6 mb-3 mb-md-0">
                                            <label class="font-weight-semibold">Số định danh / CCCD (VNeID) <span class="text-danger">*</span></label>
                                            <input type="text" name="so_cccd" class="form-control" required maxlength="12" autofocus
                                                   value="<?= e($cccdInput) ?>" placeholder="Nhập đủ 12 chữ số CCCD">
                                            <small class="text-muted">Phải khớp với số VNeID đã lưu khi đặt xe.</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="font-weight-semibold">Ảnh chụp màn hình VNeID / CCCD <span class="text-danger">*</span></label>
                                            <input type="file" name="anh_vneid" id="inputRecoverVneid" class="form-control" style="height:auto;padding:6px;" required accept="image/*">
                                            <div id="previewRecoverBox" class="mt-2 d-none">
                                                <img id="imgRecoverPreview" src="" alt="Xem trước VNeID" class="img-thumbnail" style="max-height:100px;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <?php if ($step === 2): ?>
                                    <a href="quen_mat_khau.php" class="btn btn-outline-secondary">
                                        <i class="fa-solid fa-rotate-left mr-1"></i>Nhập lại Email/SĐT
                                    </a>
                                    <button type="submit" class="btn btn-success font-weight-bold px-4" style="background:#01d28e;border-color:#01d28e;">
                                        <i class="fa-solid fa-shield-check mr-2"></i>Xác thực VNeID &amp; Nhận mật khẩu
                                    </button>
                                <?php else: ?>
                                    <a href="dang_nhap.php" class="btn btn-outline-secondary">
                                        <i class="fa-solid fa-arrow-left mr-1"></i>Quay lại Đăng nhập
                                    </a>
                                    <button type="submit" class="btn btn-primary font-weight-bold px-4">
                                        Tiếp tục xác minh <i class="fa-solid fa-arrow-right ml-1"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function copyTempPassword() {
    var el = document.getElementById('tempPasswordText');
    var notice = document.getElementById('copyNotice');
    if (!el) return;
    navigator.clipboard.writeText(el.textContent.trim()).then(function () {
        if (notice) notice.classList.remove('d-none');
    });
}

(function () {
    var inp = document.getElementById('inputRecoverVneid');
    var box = document.getElementById('previewRecoverBox');
    var img = document.getElementById('imgRecoverPreview');
    if (inp && box && img) {
        inp.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    img.src = e.target.result;
                    box.classList.remove('d-none');
                };
                reader.readAsDataURL(this.files[0]);
            } else {
                box.classList.add('d-none');
            }
        });
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
