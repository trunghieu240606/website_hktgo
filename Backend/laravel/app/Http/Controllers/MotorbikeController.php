<?php

namespace App\Http\Controllers;

use App\Models\DanhMucXe;
use App\Models\Motorbike;
use App\Services\DonThueStatusService;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;

class MotorbikeController extends Controller
{
    private function resolveDanhMucXeId(?string $loaiXe, $explicitId = null): ?int
    {
        if ($explicitId !== null && $explicitId !== '' && (int) $explicitId > 0) {
            return (int) $explicitId;
        }
        $ten = trim((string) $loaiXe);
        if ($ten === '') {
            return null;
        }
        $dm = DanhMucXe::whereRaw('LOWER(ten) = ?', [mb_strtolower($ten)])->first();
        return $dm ? (int) $dm->id : null;
    }

    public function layDanhSachXe()
    {
        $danhSach = Motorbike::orderBy('id', 'asc')->get();
        
        $result = $danhSach->map(function ($xe) {
            $tongSoLuong = max(1, (int)($xe->so_luong ?? 5));
            $conLai = DonThueStatusService::soLuongConLai($xe);
            
            $data = $xe->toArray();
            $data['soLuong'] = $tongSoLuong;
            $data['so_luong'] = $tongSoLuong;
            $data['soLuongConLai'] = $conLai;
            $data['so_luong_con_lai'] = $conLai;
            $data['trangThai'] = ($conLai > 0) ? 'Đang rảnh' : 'Hết xe';
            $data['trang_thai'] = ($conLai > 0) ? 'Đang rảnh' : 'Hết xe';
            return $data;
        });

        return response()->json($result);
    }

    public function themXeMoi(Request $request)
    {
        if (!UserController::isCallerAdmin($request)) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => 'Truy cập bị từ chối: Chỉ Quản trị viên (Admin) mới có quyền thêm xe vào kho!',
            ], 403);
        }

        $tenXe = trim((string) ($request->input('tenXe') ?? $request->input('ten_xe') ?? ''));
        $bienSo = trim((string) ($request->input('bienSo') ?? $request->input('bien_so') ?? ''));
        $loaiXe = trim((string) ($request->input('loaiXe') ?? $request->input('loai_xe') ?? ''));
        $danhMucXeId = $request->input('danhMucXeId') ?? $request->input('danh_muc_xe_id');
        $giaThue = $request->input('giaThue') ?? $request->input('gia_thue');
        $soLuong = $request->input('soLuong') ?? $request->input('so_luong') ?? 5;
        $trangThai = $request->input('trangThai') ?? $request->input('trang_thai') ?? 'Đang rảnh';
        $hangXe = trim((string) ($request->input('hangXe') ?? $request->input('hang_xe') ?? 'Honda'));
        $hinhAnh = trim((string) ($request->input('hinhAnh') ?? $request->input('hinh_anh') ?? ''));

        if ($tenXe === '' || $bienSo === '') {
            return response()->json([
                'code' => 'INVALID_INPUT',
                'message' => 'Tên xe và biển số xe không được để trống.',
            ], 400);
        }

        if (Motorbike::whereRaw('LOWER(bien_so) = ?', [mb_strtolower($bienSo)])->exists()) {
            return response()->json([
                'code' => 'DUPLICATE_PLATE',
                'message' => 'Biển số xe "' . $bienSo . '" đã tồn tại trong kho!',
            ], 409);
        }

        if (empty($trangThai)) {
            $trangThai = 'Đang rảnh';
        }
        if ($hangXe === '') {
            $hangXe = 'Honda';
        }

        $xe = Motorbike::create([
            'danh_muc_xe_id' => $this->resolveDanhMucXeId($loaiXe, $danhMucXeId),
            'ten_xe' => $tenXe,
            'bien_so' => $bienSo,
            'loai_xe' => $loaiXe,
            'gia_thue' => (float) $giaThue,
            'so_luong' => max(1, (int) $soLuong),
            'trang_thai' => $trangThai,
            'hang_xe' => $hangXe,
            'hinh_anh' => $hinhAnh,
        ]);

        return response()->json($xe, 200);
    }

    public function capNhatXe(Request $request, $id = null)
    {
        if (!UserController::isCallerAdmin($request)) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => 'Truy cập bị từ chối: Chỉ Quản trị viên (Admin) mới có quyền cập nhật thông tin xe!',
            ], 403);
        }

        $xeId = $id ?? $request->input('id') ?? $request->input('xeId');
        $xe = Motorbike::find($xeId);

        if (!$xe) {
            return response()->json(['message' => 'Không tìm thấy xe.'], 404);
        }

        $tenXe = $request->input('tenXe') ?? $request->input('ten_xe');
        $bienSo = $request->input('bienSo') ?? $request->input('bien_so');
        $loaiXe = $request->input('loaiXe') ?? $request->input('loai_xe');
        $danhMucXeId = $request->input('danhMucXeId') ?? $request->input('danh_muc_xe_id');
        $giaThue = $request->input('giaThue') ?? $request->input('gia_thue');
        $soLuong = $request->input('soLuong') ?? $request->input('so_luong');
        $trangThai = $request->input('trangThai') ?? $request->input('trang_thai');
        $hangXe = $request->input('hangXe') ?? $request->input('hang_xe');
        $hinhAnh = $request->input('hinhAnh') ?? $request->input('hinh_anh');

        if ($bienSo !== null) {
            $bienSoTrim = trim((string) $bienSo);
            $trungBien = Motorbike::whereRaw('LOWER(bien_so) = ?', [mb_strtolower($bienSoTrim)])
                ->where('id', '!=', $xe->id)
                ->exists();
            if ($trungBien) {
                return response()->json([
                    'code' => 'DUPLICATE_PLATE',
                    'message' => 'Biển số xe "' . $bienSoTrim . '" đã được sử dụng cho xe khác!',
                ], 409);
            }
            $xe->bien_so = $bienSoTrim;
        }

        if ($tenXe !== null) $xe->ten_xe = trim((string) $tenXe);
        if ($loaiXe !== null) {
            $xe->loai_xe = trim((string) $loaiXe);
            $xe->danh_muc_xe_id = $this->resolveDanhMucXeId($xe->loai_xe, $danhMucXeId);
        } elseif ($danhMucXeId !== null) {
            $xe->danh_muc_xe_id = (int) $danhMucXeId ?: null;
        }
        if ($giaThue !== null) $xe->gia_thue = (float) $giaThue;
        if ($soLuong !== null) $xe->so_luong = max(1, (int) $soLuong);
        if ($trangThai !== null) $xe->trang_thai = $trangThai;
        if ($hangXe !== null && trim((string) $hangXe) !== '') $xe->hang_xe = trim((string) $hangXe);
        if ($hinhAnh !== null) $xe->hinh_anh = trim((string) $hinhAnh);

        $xe->save();

        return response()->json($xe, 200);
    }

    public function xoaXe(Request $request, $id = null)
    {
        if (!UserController::isCallerAdmin($request)) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => 'Truy cập bị từ chối: Chỉ Quản trị viên (Admin) mới có quyền xóa xe khỏi kho!',
            ], 403);
        }

        $xeId = $id ?? $request->input('id') ?? $request->input('xeId');
        $xe = Motorbike::find($xeId);

        if (!$xe) {
            return response()->json(['message' => 'Không tìm thấy xe.'], 404);
        }

        $xe->delete();

        return response()->noContent();
    }

    public function xoaTatCa(Request $request)
    {
        if (!UserController::isCallerAdmin($request)) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => 'Truy cập bị từ chối: Chỉ Quản trị viên (Admin) mới có quyền xóa toàn bộ kho xe!',
            ], 403);
        }

        Motorbike::query()->delete();
        return response()->json(['message' => 'Đã xóa toàn bộ xe trong kho.'], 200);
    }

    public function uploadAnh(Request $request)
    {
        if (!UserController::isCallerAdmin($request)) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => 'Truy cập bị từ chối: Chỉ Quản trị viên (Admin) mới có quyền tải ảnh xe!',
            ], 403);
        }
        $file = $request->file('anhXe')
            ?? $request->file('file')
            ?? $request->file('image')
            ?? $request->file('hinhAnh');
        if (!$file) {
            return response()->json(['code' => 'BAD_REQUEST', 'message' => 'Vui lòng chọn file ảnh để tải lên.'], 400);
        }

        if (!$file->isValid()) {
            return response()->json(['code' => 'BAD_REQUEST', 'message' => 'File ảnh không hợp lệ.'], 400);
        }

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, $allowed)) {
            return response()->json(['code' => 'BAD_REQUEST', 'message' => 'Chỉ chấp nhận file ảnh định dạng: jpg, jpeg, png, gif, webp.'], 400);
        }

        $filename = 'car_' . time() . '_' . uniqid() . '.' . $ext;

        $destLaravel = public_path('uploads/vehicles');
        if (!file_exists($destLaravel)) {
            @mkdir($destLaravel, 0777, true);
        }
        $file->move($destLaravel, $filename);

        $destFrontend = base_path('../../Frontend/ManagerVehicle/images');
        if (is_dir($destFrontend)) {
            @copy($destLaravel . DIRECTORY_SEPARATOR . $filename, $destFrontend . DIRECTORY_SEPARATOR . $filename);
        }

        $relativePath = 'images/' . $filename;

        return response()->json([
            'code' => 'SUCCESS',
            'message' => 'Tải ảnh lên thành công.',
            'url' => $relativePath,
            'filename' => $filename,
            'data' => [
                'filePath' => $relativePath,
                'fileName' => $filename,
            ],
        ], 200);
    }
}
