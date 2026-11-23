<?php
/**
 * dieu_khoan.php
 * Văn bản Điều khoản & Chính sách cho thuê xe máy HKTGO (Dạng bản giấy hoàn chỉnh)
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Điều khoản & Chính sách thuê xe - HKTGO';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    .paper-doc {
        background: #ffffff;
        max-width: 860px;
        margin: 0 auto;
        padding: 48px 56px;
        border: 1px solid #cbd5e1;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        color: #1e293b;
        line-height: 1.75;
        font-size: 15px;
    }
    .paper-doc h5 {
        font-weight: 700;
        color: #0f172a;
        margin-top: 22px;
        margin-bottom: 8px;
        font-size: 16px;
        text-transform: uppercase;
    }
    .paper-doc ul {
        padding-left: 20px;
        margin-bottom: 12px;
    }
    .paper-doc li {
        margin-bottom: 6px;
    }
    @media (max-width: 767.98px) {
        .paper-doc {
            padding: 24px 18px;
            font-size: 14px;
        }
    }
    @media print {
        body * { visibility: hidden; }
        .paper-doc, .paper-doc * { visibility: visible; }
        .paper-doc {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            max-width: 100%;
            box-shadow: none;
            border: none;
            padding: 15px 25px;
        }
        .no-print { display: none !important; }
    }
</style>

<section class="py-5" style="background: #f1f5f9;">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center mx-auto mb-3 no-print" style="max-width: 860px; gap: 8px;">
            <button type="button" onclick="if (window.opener) { window.close(); } else if (window.history.length > 1) { window.history.back(); } else { window.location.href = 'index.php'; }" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left mr-1"></i>Quay lại / Đóng trang
            </button>
            <button type="button" onclick="window.print();" class="btn btn-primary btn-sm font-weight-bold">
                <i class="fa-solid fa-print mr-1"></i>In bản điều khoản / Lưu PDF
            </button>
        </div>

        <div class="paper-doc rounded">
            <!-- Quốc hiệu & Tiêu ngữ -->
            <div class="text-center mb-4">
                <div class="font-weight-bold" style="font-size: 15px; letter-spacing: 0.4px;">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</div>
                <div class="font-weight-bold" style="font-size: 15px;">Độc lập - Tự do - Hạnh phúc</div>
                <div style="width: 180px; height: 1px; background: #0f172a; margin: 6px auto 20px;"></div>

                <h3 class="font-weight-bold text-uppercase mb-1" style="color: #0f172a; font-size: 21px;">
                    QUY ĐỊNH &amp; ĐIỀU KHOẢN CHO THUÊ XE MÁY
                </h3>
                <p class="text-muted small mb-0">Ban hành kèm theo Hệ thống đặt xe trực tuyến HKTGO (Áp dụng từ năm 2026)</p>
            </div>

            <hr>

            <h5>Điều 1. Điều kiện về giấy tờ và định danh người thuê</h5>
            <ul>
                <li><strong>Xác thực định danh VNeID:</strong> Khách hàng đặt xe bắt buộc cung cấp chính xác số định danh cá nhân/CCCD (12 chữ số) và tải lên ảnh chụp màn hình định danh điện tử mức độ 2 trên ứng dụng VNeID (hoặc ảnh CCCD gắn chip còn hiệu lực).</li>
                <li><strong>Giấy phép lái xe (BLX):</strong> Đối với các dòng xe có dung tích xi-lanh trên 50cm³ (xe số, xe tay ga, xe côn tay), người điều khiển bắt buộc có Giấy phép lái xe hạng A1 hoặc A hợp lệ và tải ảnh đối chiếu lên hệ thống. Đối với xe máy điện hoặc xe dưới 50cm³ được miễn yêu cầu BLX.</li>
                <li><strong>Tính chính chủ:</strong> Họ tên người nhận xe tại điểm giao nhận phải trùng khớp 100% với thông tin VNeID đã khai báo trên đơn đặt xe. HKTGO có quyền từ chối giao xe nếu phát hiện giấy tờ giả mạo hoặc không chính chủ.</li>
            </ul>

            <h5>Điều 2. Quy định về giữ chỗ, thanh toán và tiền đặt cọc</h5>
            <ul>
                <li><strong>Thời hạn giữ chỗ trực tuyến:</strong> Sau khi khởi tạo đơn thuê, hệ thống giữ suất xe trong vòng <strong>15 phút</strong>. Quá thời hạn này nếu chưa hoàn tất chuyển khoản VietQR, đơn thuê tự động hủy để nhường xe cho khách hàng khác.</li>
                <li><strong>Tiền đặt cọc tài sản:</strong> Ngoài tiền thuê xe tính theo số ngày thực tế, khách hàng thanh toán kèm khoản tiền đặt cọc theo từng dòng xe (từ <strong>1.000.000 ₫</strong> đến <strong>3.000.000 ₫</strong>) làm tài sản bảo đảm.</li>
                <li><strong>Cơ chế hoàn cọc và giữ cọc phạt nguội 15 ngày:</strong>
                    <ul>
                        <li>Ngay tại thời điểm khách hàng trả xe nguyên vẹn, đúng hạn và không phát sinh hư hỏng, HKTGO hoàn trả ngay toàn bộ tiền cọc tài sản, chỉ <strong>giữ lại 500.000 ₫ trong thời hạn 15 ngày</strong> để tra cứu dữ liệu vi phạm giao thông (phạt nguội) trên hệ thống của Cục CSGT.</li>
                        <li>Sau 15 ngày kể từ thời điểm trả xe, nếu không ghi nhận biên bản phạt nguội phát sinh trong thời gian khách thuê, HKTGO hoàn trả đủ <strong>500.000 ₫</strong> còn lại cho khách hàng.</li>
                    </ul>
                </li>
            </ul>

            <h5>Điều 3. Quy định về thời gian trả xe và phí quá hạn</h5>
            <ul>
                <li>Thời gian thuê được tính tròn theo ngày dương lịch đăng ký trên Hợp đồng thuê xe số.</li>
                <li>Trường hợp khách hàng có nhu cầu gia hạn thêm ngày thuê, phải thông báo cho HKTGO trước tối thiểu <strong>04 giờ</strong> so với thời điểm trả xe dự kiến để kiểm tra lịch trống.</li>
                <li>Trường hợp tự ý trả xe quá hạn mà không được sự đồng ý của HKTGO, khách hàng chịu phí phạt quá hạn bằng <strong>150% đơn giá thuê ngày</strong> nhân với số ngày trễ thực tế. Khoản phí này được khấu trừ trực tiếp vào tiền đặt cọc.</li>
            </ul>

            <h5>Điều 4. Trách nhiệm khi xảy ra hư hỏng, mất mát hoặc vi phạm phạt nguội</h5>
            <ul>
                <li><strong>Hư hỏng hoặc mất phụ kiện:</strong> Khi nhận trả xe, nhân viên HKTGO tiến hành nghiệm thu thực tế. Nếu xe bị trầy xước nặng, vỡ dàn áo, hỏng hóc động cơ do lỗi sử dụng hoặc mất mũ bảo hiểm, gương, giấy tờ xe, khách hàng có trách nhiệm bồi thường chi phí sửa chữa/thay thế theo bảng giá phụ tùng chính hãng (khấu trừ trực tiếp vào tiền cọc).</li>
                <li><strong>Mất xe hoặc tai nạn nghiêm trọng:</strong> Khách hàng phải giữ nguyên hiện trường, báo ngay cho cơ quan Công an gần nhất và thông báo cho HKTGO để phối hợp giải quyết; chịu trách nhiệm bồi thường theo giá trị thực tế của phương tiện tại thời điểm thuê.</li>
                <li><strong>Vi phạm giao thông (Phạt nguội):</strong> Khách hàng chịu hoàn toàn trách nhiệm pháp lý và tài chính đối với mọi hành vi vi phạm Luật Giao thông đường bộ xảy ra trong khung thời gian nhận xe đến khi trả xe. Nếu mức phạt vượt quá khoản cọc 500.000 ₫ đang tạm giữ, HKTGO căn cứ vào <strong>Hợp đồng thuê xe số</strong> và hồ sơ <strong>VNeID/BLX</strong> để yêu cầu khách hàng thanh toán phần chênh lệch; trường hợp cố tình trốn tránh sẽ bị khóa tài khoản vĩnh viễn trên hệ thống và chuyển hồ sơ cho cơ quan chức năng xử lý.</li>
            </ul>

            <h5>Điều 5. Hiệu lực xác nhận điện tử</h5>
            <p class="mb-4">
                Việc khách hàng tích chọn ô <em>"Tôi đã đọc kỹ và hoàn toàn đồng ý với các điều khoản cho thuê xe của HKTGO"</em> trên biểu mẫu đặt xe trực tuyến được ghi nhận là sự chấp thuận đầy đủ về mặt pháp lý và là căn cứ cấu thành Hợp đồng thuê xe số giữa hai bên.
            </p>

            <div class="row mt-5 pt-2 text-center">
                <div class="col-6">
                    <div class="font-weight-bold text-uppercase">ĐẠI DIỆN BÊN CHO THUÊ</div>
                    <div class="small text-muted font-italic">(Đã ban hành điện tử)</div>
                    <div class="font-weight-bold text-primary mt-3">HỆ THỐNG CHO THUÊ XE HKTGO</div>
                </div>
                <div class="col-6">
                    <div class="font-weight-bold text-uppercase">BÊN THUÊ XE</div>
                    <div class="small text-muted font-italic">(Xác nhận qua biểu mẫu đặt xe trực tuyến)</div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>