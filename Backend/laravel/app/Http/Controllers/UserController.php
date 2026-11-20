<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function dangKyTaiKhoan(Request $request)
    {
        $rawEmail = trim((string) ($request->input('email') ?? ''));
        $rawPhone = trim((string) ($request->input('sdt') ?? $request->input('soDienThoai') ?? ''));
        $account = trim((string) ($request->input('account') ?? $request->input('taiKhoan') ?? $request->input('username') ?? ''));
        $rawPassword = (string) $request->input('password');
        $name = trim((string) ($request->input('hoTen') ?? $request->input('name') ?? ''));

        // Tương thích ngược nếu client chỉ gửi trường account
        if ($rawEmail === '' && $rawPhone === '' && $account !== '') {
            if (str_contains($account, '@')) {
                $rawEmail = $account;
            } else {
                $rawPhone = $account;
            }
        }

        if ($rawEmail === '' && $rawPhone === '') {
            return response('Lỗi: Vui lòng nhập Email và Số điện thoại!', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        if ($rawPassword === '' || mb_strlen($rawPassword) < 6) {
            return response('Lỗi: Mật khẩu phải có ít nhất 6 ký tự!', 400)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $emailNorm = null;
        if ($rawEmail !== '') {
            if (!filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
                return response('Lỗi: Địa chỉ Email không hợp lệ!', 400)
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            }
            $emailNorm = mb_strtolower($rawEmail);
            if (User::whereRaw('LOWER(email) = ?', [$emailNorm])->exists()) {
                return response('Lỗi: Email đã được đăng ký!', 400)
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            }
        }

        $phoneNorm = null;
        if ($rawPhone !== '') {
            $phoneNorm = AuthController::normalizeVnPhone($rawPhone);
            if (!preg_match('/^0[1-9][0-9]{8}$/', $phoneNorm)) {
                return response('Lỗi: Số điện thoại không hợp lệ (dùng 10 chữ số, ví dụ 0912345678).', 400)
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            }
            if (User::where('sdt', $phoneNorm)->exists()) {
                return response('Lỗi: Số điện thoại đã được đăng ký!', 400)
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            }
        }

        $user = new User();
        $user->name = $name !== '' ? $name : ($emailNorm ?: $phoneNorm);
        $user->email = $emailNorm;
        $user->sdt = $phoneNorm;
        $user->password = Hash::make($rawPassword);
        $user->role = 'USER';
        $user->save();

        return response('Đăng ký tài khoản thành công!', 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function layDanhSachKhachHang()
    {
        $users = User::select('id', 'name', 'email', 'sdt', 'role', 'created_at')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($users);
    }

    public static function getCallerRole(Request $request): string
    {
        $userId = $request->header('X-User-Id') ?? $request->input('callerId') ?? $request->input('adminId');
        $email = $request->header('X-User-Email') ?? $request->input('callerEmail');

        // Chỉ tin cậy vai trò được tra cứu trực tiếp từ bảng users trong CSDL
        if ($userId) {
            $user = User::find($userId);
            if ($user && !empty($user->role)) {
                if ($email && !empty($user->email) && mb_strtolower(trim($email)) !== mb_strtolower(trim($user->email))) {
                    return 'GUEST';
                }
                return strtoupper(trim($user->role));
            }
        }

        if ($email) {
            $emailTrim = mb_strtolower(trim((string) $email));
            $user = User::whereRaw('LOWER(email) = ?', [$emailTrim])
                ->orWhere('sdt', AuthController::normalizeVnPhone($emailTrim))
                ->first();
            if ($user && !empty($user->role)) {
                return strtoupper(trim($user->role));
            }
        }

        return 'GUEST';
    }

    public static function isCallerAdmin(Request $request): bool
    {
        return self::getCallerRole($request) === 'ADMIN';
    }

    public static function isCallerStaffOrAbove(Request $request): bool
    {
        return in_array(self::getCallerRole($request), ['ADMIN', 'MANAGER', 'STAFF'], true);
    }

    public function setRole(Request $request, $id = null)
    {
        if (!self::isCallerAdmin($request)) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => 'Truy cập bị từ chối: Chỉ Quản trị viên (Admin) mới có quyền phân quyền tài khoản!',
            ], 403);
        }

        $targetId = $id ?? $request->input('userId') ?? $request->input('id');
        $targetUser = User::find($targetId);
        if (!$targetUser) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'Không tìm thấy tài khoản người dùng.',
            ], 404);
        }

        $newRole = strtoupper(trim((string)$request->input('role')));
        $validRoles = ['ADMIN', 'MANAGER', 'STAFF', 'USER'];
        if (!in_array($newRole, $validRoles)) {
            return response()->json([
                'code' => 'INVALID_ROLE',
                'message' => 'Vai trò không hợp lệ. Các vai trò được hỗ trợ: ADMIN, MANAGER, STAFF, USER.',
            ], 400);
        }

        // Không cho phép tự tước quyền ADMIN của chính mình nếu chỉ còn 1 ADMIN duy nhất trong hệ thống
        $callerId = $request->header('X-User-Id') ?? $request->input('callerId');
        if ($callerId && (int)$callerId === (int)$targetUser->id && $newRole !== 'ADMIN') {
            $adminCount = User::where('role', 'ADMIN')->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'code' => 'LAST_ADMIN',
                    'message' => 'Không thể hạ quyền tài khoản Admin duy nhất trong hệ thống!',
                ], 400);
            }
        }

        $targetUser->role = $newRole;
        $targetUser->save();

        return response()->json([
            'code' => 'SUCCESS',
            'message' => 'Cập nhật vai trò thành công!',
            'data' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'role' => $targetUser->role,
            ],
        ], 200);
    }
}
