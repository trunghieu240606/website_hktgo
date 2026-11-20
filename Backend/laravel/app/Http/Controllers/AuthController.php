<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public static function normalizeVnPhone(?string $raw): string
    {
        if (empty($raw)) {
            return '';
        }
        $d = preg_replace('/[\s.-]/', '', $raw);
        if (str_starts_with($d, '+84')) {
            return '0' . substr($d, 3);
        }
        if (str_starts_with($d, '84') && strlen($d) >= 10) {
            return '0' . substr($d, 2);
        }
        return $d;
    }

    public function login(Request $request)
    {
        $login = $request->input('email')
            ?? $request->input('taiKhoan')
            ?? $request->input('username')
            ?? $request->input('account');
        $rawPassword = (string) $request->input('password');

        if (empty($login) || $rawPassword === '') {
            return response()->json([
                'code' => 'UNAUTHORIZED',
                'message' => 'Email hoặc mật khẩu không đúng.',
                'data' => null,
            ], 401);
        }

        $login = trim($login);
        $loginLower = mb_strtolower($login);
        $user = null;

        // 1. Tìm chính xác theo email
        $user = User::whereRaw('LOWER(email) = ?', [$loginLower])->first();

        // 2. Tìm chính xác theo số điện thoại đã chuẩn hóa
        if (!$user) {
            $phone = self::normalizeVnPhone($login);
            if (!empty($phone)) {
                $user = User::where('sdt', $phone)->first();
            }
        }

        if (!$user) {
            return response()->json([
                'code' => 'UNAUTHORIZED',
                'message' => 'Email/SĐT hoặc mật khẩu không đúng.',
                'data' => null,
            ], 401);
        }

        $storedPassword = (string) $user->password;

        $isBcrypt = str_starts_with($storedPassword, '$2a$')
            || str_starts_with($storedPassword, '$2b$')
            || str_starts_with($storedPassword, '$2y$');

        $passwordValid = false;
        if ($isBcrypt) {
            $passwordValid = Hash::check($rawPassword, $storedPassword);
        } else {
            $passwordValid = hash_equals($storedPassword, $rawPassword);
            if ($passwordValid) {
                $user->password = Hash::make($rawPassword);
                $user->save();
            }
        }

        if (!$passwordValid) {
            return response()->json([
                'code' => 'UNAUTHORIZED',
                'message' => 'Email/SĐT hoặc mật khẩu không đúng.',
                'data' => null,
            ], 401);
        }

        if (empty($user->role)) {
            $user->role = 'USER';
            $user->save();
        }

        $display = $user->email ?: ($user->primary_login_display ?? $user->sdt);

        return response()->json([
            'code' => 'SUCCESS',
            'message' => 'Đăng nhập thành công.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'hoTen' => $user->name,
                'email' => $display,
                'sdt' => $user->sdt,
                'role' => $user->role,
                'message' => 'Đăng nhập thành công.',
            ],
        ]);
    }
}
