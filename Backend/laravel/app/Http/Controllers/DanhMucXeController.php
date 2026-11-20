<?php

namespace App\Http\Controllers;

use App\Models\DanhMucXe;
use App\Models\Motorbike;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;

class DanhMucXeController extends Controller
{
    private function chuanHoaTen(?string $raw): string
    {
        if ($raw === null) return '';
        return trim(preg_replace('/\s+/', ' ', $raw));
    }

    private function chuanHoaMoTa(?string $raw): string
    {
        if ($raw === null) return '';
        $m = trim($raw);
        return mb_substr($m, 0, 255);
    }

    public function layDanhSach()
    {
        $list = DanhMucXe::orderBy('thu_tu', 'asc')
            ->orderBy('ten', 'asc')
            ->get();
        return response()->json($list);
    }

    public function them(Request $request)
    {
        if (!UserController::isCallerAdmin($request)) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => 'Truy cập bị từ chối: Chỉ Quản trị viên (Admin) mới có quyền thêm danh mục xe!',
            ], 403);
        }

        $ten = $this->chuanHoaTen($request->input('ten'));
        $moTa = $this->chuanHoaMoTa($request->input('moTa') ?? $request->input('mo_ta'));
        $thuTu = (int) ($request->input('thuTu') ?? $request->input('thu_tu') ?? 0);

        if (empty($ten)) {
            return response('Tên danh mục không được để trống.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        if (DanhMucXe::whereRaw('LOWER(ten) = ?', [mb_strtolower($ten)])->exists()) {
            return response('Danh mục "' . $ten . '" đã tồn tại.', 409)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $dm = DanhMucXe::create([
            'ten' => $ten,
            'mo_ta' => $moTa,
            'thu_tu' => $thuTu,
        ]);

        return response()->json($dm, 200);
    }

    public function sua(Request $request, $id)
    {
        if (!UserController::isCallerAdmin($request)) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => 'Truy cập bị từ chối: Chỉ Quản trị viên (Admin) mới có quyền sửa danh mục xe!',
            ], 403);
        }

        $dm = DanhMucXe::find($id);
        if (!$dm) {
            return response()->json(['message' => 'Không tìm thấy danh mục.'], 404);
        }

        $tenMoi = $this->chuanHoaTen($request->input('ten'));
        $moTa = $this->chuanHoaMoTa($request->input('moTa') ?? $request->input('mo_ta'));
        $thuTu = (int) ($request->input('thuTu') ?? $request->input('thu_tu') ?? 0);

        if (empty($tenMoi)) {
            return response('Tên danh mục không được để trống.', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $exists = DanhMucXe::whereRaw('LOWER(ten) = ?', [mb_strtolower($tenMoi)])
            ->where('id', '!=', $id)
            ->exists();
        if ($exists) {
            return response('Danh mục "' . $tenMoi . '" đã tồn tại.', 409)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $tenCu = $dm->ten;
        $dm->ten = $tenMoi;
        $dm->mo_ta = $moTa;
        $dm->thu_tu = $thuTu;
        $dm->save();

        if (mb_strtolower($tenCu) !== mb_strtolower($tenMoi)) {
            Motorbike::whereRaw('LOWER(loai_xe) = ?', [mb_strtolower($tenCu)])
                ->orWhere('danh_muc_xe_id', $dm->id)
                ->update([
                    'loai_xe' => $tenMoi,
                    'danh_muc_xe_id' => $dm->id,
                ]);
        } else {
            Motorbike::whereRaw('LOWER(loai_xe) = ?', [mb_strtolower($tenMoi)])
                ->update(['danh_muc_xe_id' => $dm->id]);
        }

        return response()->json($dm, 200);
    }

    public function xoa(Request $request, $id)
    {
        if (!UserController::isCallerAdmin($request)) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => 'Truy cập bị từ chối: Chỉ Quản trị viên (Admin) mới có quyền xóa danh mục xe!',
            ], 403);
        }

        $dm = DanhMucXe::find($id);
        if (!$dm) {
            return response()->json(['message' => 'Không tìm thấy danh mục.'], 404);
        }

        $count = Motorbike::where('danh_muc_xe_id', $dm->id)
            ->orWhereRaw('LOWER(loai_xe) = ?', [mb_strtolower($dm->ten)])
            ->count();
        if ($count > 0) {
            return response('Không thể xóa danh mục đang được gán cho xe trong kho.', 409)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $dm->delete();
        return response()->noContent();
    }
}
