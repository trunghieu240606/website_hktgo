<?php

namespace App\Http\Controllers;

use App\Models\DonThue;
use App\Models\Motorbike;
use App\Models\User;
use App\Services\DonThueStatusService;
use Illuminate\Http\Request;

class DonThueController extends Controller
{
    private static function chiLaySo(?string $s): string
    {
        if ($s === null) return '';
        return preg_replace('/\D/', '', $s);
    }

    private function khachHopLe(DonThue $don, ?string $email, ?string $soDienThoai): bool
    {
        $nSdt = self::chiLaySo($soDienThoai);
        $donSdt = self::chiLaySo($don->so_dien_thoai);
        if (empty($nSdt) || $nSdt !== $donSdt) {
            return false;
        }
        if (!empty($don->email_khach)) {
            return !empty($email) && mb_strtolower(trim($don->email_khach)) === mb_strtolower(trim($email));
        }
        return true;
    }

    private function timUserIdTheoTaiKhoan(?string $emailKhach): ?int
    {
        if (empty($emailKhach)) {
            return null;
        }
        $s = trim($emailKhach);
        $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower($s)])->first();
        if ($user) {
            return $user->id;
        }
        $phone = AuthController::normalizeVnPhone($s);
        if (!empty($phone)) {
            $userByPhone = User::where('sdt', $phone)->first();
            if ($userByPhone) {
                return $userByPhone->id;
            }
        }
        return null;
    }

    private function layMaNhom(DonThue $don): int
    {
        return !empty($don->ma_nhom_don) ? (int) $don->ma_nhom_don : (int) $don->id;
    }

    private function layCacDongTrongNhom(int $donId)
    {
        $don = DonThue::find($donId);
        if (!$don) {
            return collect();
        }
        $maNhom = $this->layMaNhom($don);
        return DonThue::where('ma_nhom_don', $maNhom)
            ->orWhere(function ($q) use ($maNhom) {
                $q->whereNull('ma_nhom_don')->where('id', $maNhom);
            })
            ->orderBy('id', 'asc')
            ->get();
    }

    private function donChoPhepThemHoacSuaXe(DonThue $don): bool
    {
        $tt = trim($don->trang_thai ?? '');
        return $tt === DonThueStatusService::TT_CHO_THANH_TOAN
            || $tt === DonThueStatusService::TT_CHO_DUYET;
    }

    // 1. Danh sách toàn bộ đơn
    public function layDanhSachDon()
    {
        DonThueStatusService::huyDonQuaHanThanhToan();
        $list = DonThue::orderBy('id', 'desc')->get();
        return response()->json($list);
    }

    // 2. Nhận đơn đặt xe mới
    public function datXeMoi(Request $request)
    {
        $emailKhach = trim((string) ($request->input('emailKhach') ?? $request->input('email_khach') ?? ''));
        if (empty($emailKhach)) {
            return response('Vui lòng đăng nhập tài khoản khách trước khi đặt xe.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $bienSo = trim((string) ($request->input('bienSo') ?? $request->input('bien_so') ?? ''));
        $xeId = $request->input('xeId') ?? $request->input('xe_id');
        $tenXe = trim((string) ($request->input('tenXe') ?? $request->input('ten_xe') ?? ''));

        if (!empty($bienSo)) {
            DonThueStatusService::giaiPhongGiuChoQuaHanTheoBienSo($bienSo);
            $xe = Motorbike::where('bien_so', $bienSo)->first();
        } else {
            $xe = null;
        }

        if (!$xe && !empty($xeId)) {
            $xe = Motorbike::find($xeId);
        }
        if (!$xe && !empty($tenXe)) {
            $xe = Motorbike::where('ten_xe', $tenXe)->first();
        }

        if (!$xe) {
            return response('Không tìm thấy xe trong kho.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $conLai = DonThueStatusService::soLuongConLai($xe);
        if ($conLai <= 0) {
            return response('Mẫu xe [' . ($xe->ten_xe ?: $tenXe) . '] hiện đã hết số lượng cho thuê. Vui lòng chọn xe khác.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $ngayNhan = $request->input('ngayNhan') ?? $request->input('ngay_nhan');
        $ngayTra = $request->input('ngayTra') ?? $request->input('ngay_tra');
        if (!empty($ngayNhan) && !empty($ngayTra)) {
            $tsNhan = strtotime((string) $ngayNhan);
            $tsTra = strtotime((string) $ngayTra);
            if ($tsNhan !== false && $tsTra !== false && $tsTra < $tsNhan) {
                return response('Ngày trả xe không hợp lệ (phải lớn hơn hoặc bằng ngày nhận xe).', 400)
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            }
        }

        $userId = $this->timUserIdTheoTaiKhoan($emailKhach);

        $don = new DonThue();
        $don->user_id = $userId;
        $don->xe_id = $xe->id;
        $don->xe_may_id = $xe->id;
        $don->ten_khach_hang = $request->input('tenKhachHang') ?? $request->input('ten_khach_hang');
        $don->so_dien_thoai = $request->input('soDienThoai') ?? $request->input('so_dien_thoai');
        $don->ten_xe = $xe->ten_xe ?? $tenXe;
        $don->bien_so = $xe->bien_so ?? $bienSo;
        $don->tong_tien = (float) ($request->input('tongTien') ?? $request->input('tong_tien') ?? 0);
        $don->ngay_nhan = $request->input('ngayNhan') ?? $request->input('ngay_nhan');
        $don->ngay_tra = $request->input('ngayTra') ?? $request->input('ngay_tra');
        $don->gio_nhan = $request->input('gioNhan') ?? $request->input('gio_nhan');
        $don->gio_tra = $request->input('gioTra') ?? $request->input('gio_tra');
        $don->diem_nhan = $request->input('diemNhan') ?? $request->input('diem_nhan');
        $don->diem_tra = $request->input('diemTra') ?? $request->input('diem_tra');
        $don->trang_thai = DonThueStatusService::TT_CHO_THANH_TOAN;
        $don->email_khach = $emailKhach;
        $don->han_thanh_toan = now()->addMinutes(15);
        $don->thoi_diem_thanh_toan = null;
        $don->kenh_thanh_toan = null;
        $don->save();

        $don->ma_nhom_don = $don->id;
        $don->save();

        DonThueStatusService::apDungTrangThaiChoDonVaXe($don, DonThueStatusService::TT_CHO_THANH_TOAN);

        return response()->json($don, 200);
    }

    // 3. Khách xác nhận đã thanh toán QR
    public function xacNhanThanhToan(Request $request, $id)
    {
        $don = DonThue::find($id);
        if (!$don) {
            return response('Không tìm thấy đơn.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $email = $request->input('email');
        $soDienThoai = $request->input('soDienThoai');
        $kenhThanhToan = $request->input('kenhThanhToan') ?? 'MoMo';

        if (!$this->khachHopLe($don, $email, $soDienThoai)) {
            return response('Không khớp email/SĐT với đơn này.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        if (trim($don->trang_thai) !== DonThueStatusService::TT_CHO_THANH_TOAN) {
            return response('Đơn không ở trạng thái chờ thanh toán.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        if (!empty($don->han_thanh_toan) && now()->gt($don->han_thanh_toan)) {
            DonThueStatusService::apDungTrangThaiChoDonVaXe($don, DonThueStatusService::TT_DA_HUY);
            return response('Đơn đã quá hạn 15 phút và bị hủy.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $dongNhom = $this->layCacDongTrongNhom($id);
        $daXacNhan = 0;
        $nowPay = now();

        foreach ($dongNhom as $d) {
            if (trim($d->trang_thai) !== DonThueStatusService::TT_CHO_THANH_TOAN) {
                continue;
            }
            if (!empty($d->han_thanh_toan) && now()->gt($d->han_thanh_toan)) {
                DonThueStatusService::apDungTrangThaiChoDonVaXe($d, DonThueStatusService::TT_DA_HUY);
                continue;
            }
            $d->kenh_thanh_toan = $kenhThanhToan;
            $d->thoi_diem_thanh_toan = $nowPay;
            $d->save();
            DonThueStatusService::apDungTrangThaiChoDonVaXe($d, DonThueStatusService::TT_CHO_DUYET);
            $daXacNhan++;
        }

        if ($daXacNhan === 0) {
            return response('Không còn dòng đơn chờ thanh toán hợp lệ.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response('Đã ghi nhận thanh toán. Đơn chuyển sang Chờ duyệt.', 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    // 4. Admin / Quản lý / Nhân viên chuyển trạng thái đơn VÀ ĐỒNG BỘ sang kho xe
    public function capNhatTrangThai(Request $request, $id)
    {
        if (!UserController::isCallerStaffOrAbove($request)) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => 'Truy cập bị từ chối: Chỉ Quản trị viên, Quản lý hoặc Nhân viên mới được phép cập nhật trạng thái đơn thuê!',
            ], 403);
        }

        $don = DonThue::find($id);
        if (!$don) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng!'], 404);
        }

        $trangThaiMoi = $request->input('trangThaiMoi') 
            ?? $request->input('trang_thai_moi') 
            ?? $request->query('trangThaiMoi') 
            ?? $request->query('trang_thai_moi');

        if (empty($trangThaiMoi)) {
            $data = $request->json()->all();
            if (is_array($data)) {
                $trangThaiMoi = $data['trangThaiMoi'] ?? $data['trang_thai_moi'] ?? null;
            }
        }

        if (empty($trangThaiMoi)) {
            $raw = json_decode($request->getContent(), true);
            if (is_array($raw)) {
                $trangThaiMoi = $raw['trangThaiMoi'] ?? $raw['trang_thai_moi'] ?? null;
            }
        }

        if (empty($trangThaiMoi)) {
            return response()->json([
                'message' => 'Trạng thái mới không được để trống.',
                'received' => $request->all()
            ], 400);
        }

        // Chuẩn hóa tên trạng thái
        $norm = DonThueStatusService::normalize($trangThaiMoi);
        if (str_contains($norm, 'cho duyet') || str_contains($norm, 'cho thanh toan')) {
            $trangThaiMoi = 'Chờ duyệt';
        } elseif (str_contains($norm, 'dang thue') || str_contains($norm, 'da duyet') || str_contains($norm, 'duyet') || str_contains($norm, 'thue')) {
            $trangThaiMoi = 'Đang thuê';
        } elseif (str_contains($norm, 'hoan thanh') || str_contains($norm, 'tra xe') || str_contains($norm, 'da xong')) {
            $trangThaiMoi = 'Hoàn thành';
        } elseif (str_contains($norm, 'huy')) {
            $trangThaiMoi = 'Đã hủy';
        }

        DonThueStatusService::apDungTrangThaiChoDonVaXe($don, $trangThaiMoi);

        return response()->json([
            'code' => 'SUCCESS',
            'message' => 'Đã cập nhật trạng thái đơn thành: ' . $trangThaiMoi,
            'trangThai' => $trangThaiMoi,
            'don' => $don
        ], 200);
    }

    // 5. Đơn của khách: theo email hoặc tra cứu theo SĐT
    public function donCuaToi(Request $request)
    {
        $email = $request->input('email');
        $soDienThoai = $request->input('soDienThoai');

        if (!empty($email)) {
            $list = DonThue::whereRaw('LOWER(email_khach) = ?', [mb_strtolower(trim($email))])
                ->orderBy('id', 'desc')
                ->get();
            return response()->json($list);
        }

        if (!empty($soDienThoai)) {
            $n = self::chiLaySo($soDienThoai);
            $list = DonThue::where('so_dien_thoai', $n)
                ->orderBy('id', 'desc')
                ->get();
            if ($list->isNotEmpty()) {
                return response()->json($list);
            }
            $all = DonThue::all()->filter(function ($d) use ($n) {
                return self::chiLaySo($d->so_dien_thoai) === $n;
            })->sortByDesc('id')->values();
            return response()->json($all);
        }

        return response()->json([]);
    }

    // 6. Khách thao tác: hủy đơn hoặc xin trả xe sớm
    public function khachThaoTac(Request $request, $id)
    {
        $don = DonThue::find($id);
        if (!$don) {
            return response('Không tìm thấy đơn.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $email = $request->input('email');
        $soDienThoai = $request->input('soDienThoai');
        $hanhDong = mb_strtolower(trim((string) $request->input('hanhDong')));

        if (!$this->khachHopLe($don, $email, $soDienThoai)) {
            return response('Không khớp email/SĐT với đơn này.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $tt = trim($don->trang_thai ?? '');

        if ($hanhDong === 'huy') {
            if ($tt !== DonThueStatusService::TT_CHO_DUYET && $tt !== DonThueStatusService::TT_CHO_THANH_TOAN) {
                return response('Chỉ hủy được đơn đang chờ thanh toán/chờ duyệt.', 400)
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            }
            $dongNhom = $this->layCacDongTrongNhom($id);
            $daHuy = 0;
            foreach ($dongNhom as $d) {
                $ttd = trim($d->trang_thai ?? '');
                if ($ttd === DonThueStatusService::TT_CHO_DUYET || $ttd === DonThueStatusService::TT_CHO_THANH_TOAN) {
                    DonThueStatusService::apDungTrangThaiChoDonVaXe($d, DonThueStatusService::TT_DA_HUY);
                    $daHuy++;
                }
            }
            if ($daHuy === 0) {
                return response('Không còn dòng đơn nào để hủy.', 400)
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            }
            return response($daHuy > 1 ? 'Đã hủy toàn bộ xe trong đơn.' : 'Đã hủy đơn.', 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        if ($hanhDong === 'tra_som') {
            if ($tt !== DonThueStatusService::TT_DANG_THUE) {
                return response('Chỉ áp dụng khi đơn đang ở trạng thái Đang thuê.', 400)
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            }
            $dongNhom = $this->layCacDongTrongNhom($id);
            $daTra = 0;
            foreach ($dongNhom as $d) {
                $ttd = trim($d->trang_thai ?? '');
                if ($ttd === DonThueStatusService::TT_DANG_THUE) {
                    DonThueStatusService::apDungTrangThaiChoDonVaXe($d, DonThueStatusService::TT_HOAN_THANH);
                    $daTra++;
                }
            }
            if ($daTra === 0) {
                return response('Không còn xe đang thuê trong đơn.', 400)
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            }
            return response('Đã ghi nhận trả xe. Cảm ơn bạn!', 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response('Hành động không hợp lệ.', 400)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    // 7. Xem chi tiết đơn nhóm
    public function xemDonNhom(Request $request, $id)
    {
        $don = DonThue::find($id);
        if (!$don) {
            return response('Không tìm thấy đơn.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $email = $request->input('email');
        $soDienThoai = $request->input('soDienThoai');

        if (!$this->khachHopLe($don, $email, $soDienThoai)) {
            return response('Không khớp email/SĐT với đơn này.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $dongList = $this->layCacDongTrongNhom($id);
        $dau = $dongList->first() ?? $don;

        $tong = 0.0;
        foreach ($dongList as $d) {
            if (trim($d->trang_thai ?? '') !== DonThueStatusService::TT_DA_HUY) {
                $tong += (float) ($d->tong_tien ?? 0);
            }
        }

        $view = [
            'maNhom' => $this->layMaNhom($dau),
            'tenKhachHang' => $dau->ten_khach_hang,
            'soDienThoai' => $dau->so_dien_thoai,
            'emailKhach' => $dau->email_khach,
            'trangThai' => $dau->trang_thai,
            'hanThanhToan' => $dau->han_thanh_toan,
            'tongTienNhom' => $tong,
            'choPhepSua' => $this->donChoPhepThemHoacSuaXe($dau),
            'cacDong' => $dongList,
        ];

        return response()->json($view);
    }

    // 8. Thêm xe vào đơn
    public function themXeVaoDon(Request $request, $id)
    {
        $mau = DonThue::find($id);
        if (!$mau) {
            return response('Không tìm thấy đơn.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $email = $request->input('email');
        $soDienThoai = $request->input('soDienThoai');

        if (!$this->khachHopLe($mau, $email, $soDienThoai)) {
            return response('Không khớp email/SĐT với đơn này.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        if (!$this->donChoPhepThemHoacSuaXe($mau)) {
            return response('Chỉ thêm xe khi đơn đang chờ thanh toán hoặc chờ duyệt.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $bienSo = trim((string) ($request->input('bienSo') ?? $request->input('bien_so') ?? ''));
        if (empty($bienSo)) {
            return response('Biển số không được để trống.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        DonThueStatusService::giaiPhongGiuChoQuaHanTheoBienSo($bienSo);

        $xe = Motorbike::where('bien_so', $bienSo)->first();
        if (!$xe) {
            return response('Không tìm thấy xe trong kho.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        if (!DonThueStatusService::xeDangRanh($xe)) {
            return response('Xe đã có người giữ chỗ/đang thuê. Vui lòng chọn xe khác.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $dongHienTai = $this->layCacDongTrongNhom($id);
        $trungXe = $dongHienTai->contains(function ($d) use ($bienSo) {
            return mb_strtolower(trim($d->bien_so ?? '')) === mb_strtolower($bienSo);
        });

        if ($trungXe) {
            return response('Xe này đã có trong đơn.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $dongMoi = new DonThue();
        $dongMoi->ma_nhom_don = $this->layMaNhom($mau);
        $dongMoi->ten_khach_hang = $mau->ten_khach_hang;
        $dongMoi->so_dien_thoai = $mau->so_dien_thoai;
        $dongMoi->email_khach = $mau->email_khach;
        $dongMoi->user_id = $mau->user_id;
        $dongMoi->ten_xe = trim((string) ($request->input('tenXe') ?? $request->input('ten_xe') ?: $xe->ten_xe));
        $dongMoi->bien_so = $bienSo;
        $dongMoi->tong_tien = (float) ($request->input('tongTien') ?? $request->input('tong_tien') ?? 0);
        $dongMoi->ngay_nhan = $mau->ngay_nhan;
        $dongMoi->ngay_tra = $mau->ngay_tra;
        $dongMoi->gio_nhan = $mau->gio_nhan;
        $dongMoi->gio_tra = $mau->gio_tra;
        $dongMoi->diem_nhan = $mau->diem_nhan;
        $dongMoi->diem_tra = $mau->diem_tra;
        $dongMoi->trang_thai = $mau->trang_thai;
        $dongMoi->han_thanh_toan = $mau->han_thanh_toan;
        $dongMoi->xe_id = $xe->id;
        $dongMoi->save();

        DonThueStatusService::apDungTrangThaiChoDonVaXe($dongMoi, $dongMoi->trang_thai);

        return response()->json($dongMoi, 200);
    }

    // 9. Cập nhật dòng đơn
    public function capNhatDongDon(Request $request, $id)
    {
        $don = DonThue::find($id);
        if (!$don) {
            return response('Không tìm thấy dòng đơn.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $email = $request->input('email');
        $soDienThoai = $request->input('soDienThoai');

        if (!$this->khachHopLe($don, $email, $soDienThoai)) {
            return response('Không khớp email/SĐT với đơn này.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        if (!$this->donChoPhepThemHoacSuaXe($don)) {
            return response('Chỉ cập nhật được đơn đang chờ thanh toán hoặc chờ duyệt.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $bienSoCu = trim((string) ($don->bien_so ?? ''));
        $bienSoMoi = trim((string) ($request->input('bienSo') ?? $request->input('bien_so') ?? ''));

        if (empty($bienSoMoi)) {
            return response('Biển số xe không hợp lệ.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        if (mb_strtolower($bienSoMoi) !== mb_strtolower($bienSoCu)) {
            $dongHienTai = $this->layCacDongTrongNhom($id);
            $trungXe = $dongHienTai->where('id', '!=', $don->id)->contains(function ($d) use ($bienSoMoi) {
                return mb_strtolower(trim($d->bien_so ?? '')) === mb_strtolower($bienSoMoi);
            });
            if ($trungXe) {
                return response('Xe này đã có trong đơn.', 400)
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            }

            $xeMoi = Motorbike::where('bien_so', $bienSoMoi)->first();
            if (!$xeMoi) {
                return response('Không tìm thấy xe trong kho.', 400)
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            }

            DonThueStatusService::giaiPhongGiuChoQuaHanTheoBienSo($bienSoMoi);
            $xeMoi = Motorbike::where('bien_so', $bienSoMoi)->first();
            if (!DonThueStatusService::xeDangRanh($xeMoi)) {
                return response('Xe mới đã có người giữ chỗ/đang thuê.', 400)
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            }

            $xeCu = Motorbike::where('bien_so', $bienSoCu)->first();

            $don->bien_so = $bienSoMoi;
            $don->ten_xe = trim((string) ($request->input('tenXe') ?? $request->input('ten_xe') ?: $xeMoi->ten_xe));
            $don->xe_id = $xeMoi->id;
            $don->tong_tien = (float) ($request->input('tongTien') ?? $request->input('tong_tien') ?? 0);
            $don->save();

            DonThueStatusService::dongBoTrangThaiXe($xeCu);
            DonThueStatusService::apDungTrangThaiChoDonVaXe($don, $don->trang_thai);

            return response()->json($don, 200);
        } else {
            $don->ten_xe = trim((string) ($request->input('tenXe') ?? $request->input('ten_xe') ?: $don->ten_xe));
        }

        $don->tong_tien = (float) ($request->input('tongTien') ?? $request->input('tong_tien') ?? 0);
        $don->save();

        return response()->json($don, 200);
    }

    // 10. Xóa dòng đơn
    public function xoaDongDon(Request $request, $id)
    {
        $don = DonThue::find($id);
        if (!$don) {
            return response('Không tìm thấy dòng đơn.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $email = $request->input('email');
        $soDienThoai = $request->input('soDienThoai');

        if (!$this->khachHopLe($don, $email, $soDienThoai)) {
            return response('Không khớp email/SĐT với đơn này.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        if (!$this->donChoPhepThemHoacSuaXe($don)) {
            return response('Chỉ xóa được xe khi đơn đang chờ thanh toán hoặc chờ duyệt.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $dongNhom = $this->layCacDongTrongNhom($id);
        if ($dongNhom->count() <= 1) {
            DonThueStatusService::apDungTrangThaiChoDonVaXe($don, DonThueStatusService::TT_DA_HUY);
            return response('Đã hủy đơn (đơn chỉ còn một xe).', 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        DonThueStatusService::apDungTrangThaiChoDonVaXe($don, DonThueStatusService::TT_DA_HUY);
        return response('Đã xóa xe khỏi đơn.', 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
