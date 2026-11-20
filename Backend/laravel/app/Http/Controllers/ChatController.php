<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Motorbike;
use App\Services\DonThueStatusService;
use App\Services\GeminiChatService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    private function layCheDoHienTai(string $user): string
    {
        if (empty($user)) {
            return 'AI';
        }

        $lastSpecial = ChatMessage::where(function ($q) use ($user) {
            $q->whereRaw('LOWER(nguoi_gui) = ?', [mb_strtolower($user)])
              ->orWhereRaw('LOWER(to_user) = ?', [mb_strtolower($user)]);
        })
        ->whereIn('from_role', ['SYSTEM', 'ADMIN', 'AI'])
        ->orderBy('id', 'desc')
        ->first();

        if ($lastSpecial) {
            if ($lastSpecial->from_role === 'ADMIN' || $lastSpecial->kenh === 'DIRECT') {
                return 'HUMAN';
            }
            if ($lastSpecial->from_role === 'AI' || $lastSpecial->kenh === 'AI') {
                return 'AI';
            }
        }

        return 'AI';
    }

    public function layCheDo(Request $request)
    {
        $user = trim((string) $request->input('user', ''));
        $cheDo = $this->layCheDoHienTai($user);
        return response()->json(['user' => $user, 'cheDo' => $cheDo]);
    }

    public function doiCheDo(Request $request)
    {
        $user = trim((string) ($request->input('user') ?? $request->input('nguoiGui') ?? 'Khách hàng'));
        $cheDo = strtoupper(trim((string) $request->input('cheDo', 'AI')));

        if ($cheDo === 'HUMAN' || $cheDo === 'DIRECT') {
            $sysMsg = ChatMessage::create([
                'noi_dung' => '🔔 [CHUYỂN KẾT NỐI] Em đã chuyển phiên chat sang Nhân viên tư vấn trực tiếp của HKTGO. Chuyên viên sẽ hỗ trợ bạn ngay trong ít phút! (Bạn có thể bấm "Quay lại AI" bất kỳ lúc nào).',
                'nguoi_gui' => 'Hệ thống HKTGO',
                'from_role' => 'SYSTEM',
                'kenh' => 'DIRECT',
                'to_user' => $user,
                'read_by_admin' => false,
            ]);

            ChatMessage::create([
                'noi_dung' => "Khách hàng [{$user}] đã yêu cầu chuyển sang hỗ trợ trực tiếp từ nhân viên!",
                'nguoi_gui' => 'Hệ thống HKTGO',
                'from_role' => 'SYSTEM',
                'kenh' => 'DIRECT',
                'to_user' => 'ADMIN',
                'read_by_admin' => false,
            ]);

            return response()->json([
                'code' => 'SUCCESS',
                'cheDo' => 'HUMAN',
                'message' => 'Đã chuyển sang chế độ Chat trực tiếp với nhân viên.',
                'systemMessage' => $sysMsg,
            ]);
        }

        // Chuyển lại AI
        $aiMsg = ChatMessage::create([
            'noi_dung' => 'Dạ em là Trợ lí HKTGO đã quay trở lại hỗ trợ anh/chị đây ạ! Anh/chị đang cần tìm hiểu mẫu xe nào hay cần hỗ trợ thông tin gì ạ? 🛵',
            'nguoi_gui' => 'Trợ lí HKTGO',
            'from_role' => 'AI',
            'kenh' => 'AI',
            'to_user' => $user,
            'read_by_admin' => true,
        ]);

        return response()->json([
            'code' => 'SUCCESS',
            'cheDo' => 'AI',
            'message' => 'Đã bật lại Trợ lí HKTGO.',
            'aiMessage' => $aiMsg,
        ]);
    }

    public function guiTinNhan(Request $request)
    {
        $noiDung = trim((string) ($request->input('noiDung') ?? $request->input('noi_dung') ?? ''));
        if (empty($noiDung)) {
            return response()->json(['message' => 'Nội dung tin nhắn không được để trống.'], 400);
        }

        $nguoiGui = trim((string) ($request->input('nguoiGui') ?? $request->input('nguoi_gui') ?? 'Khách hàng'));
        if (empty($nguoiGui)) $nguoiGui = 'Khách hàng';

        $fromRole = strtoupper(trim((string) ($request->input('fromRole') ?? $request->input('from_role') ?? 'USER')));
        if (empty($fromRole)) $fromRole = 'USER';

        $toUser = trim((string) ($request->input('toUser') ?? $request->input('to_user') ?? ''));
        $cheDoYeuCau = strtoupper(trim((string) $request->input('cheDo', '')));
        $yeuCauTrucTiep = filter_var($request->input('yeuCauTrucTiep', false), FILTER_VALIDATE_BOOLEAN);
        $yeuCauQuayLaiAI = filter_var($request->input('yeuCauQuayLaiAI', false), FILTER_VALIDATE_BOOLEAN);

        // 1. Nếu tin nhắn từ ADMIN
        if ($fromRole === 'ADMIN') {
            $message = ChatMessage::create([
                'noi_dung' => $noiDung,
                'nguoi_gui' => 'Quản trị viên',
                'from_role' => 'ADMIN',
                'kenh' => 'DIRECT',
                'to_user' => $toUser,
                'read_by_admin' => true,
            ]);

            return response()->json([
                'code' => 'SUCCESS',
                'cheDo' => 'HUMAN',
                'message' => $message,
            ], 200);
        }

        // 2. Nếu tin nhắn từ USER
        // Kiểm tra xem khách có yêu cầu chat trực tiếp không
        $isDirectReq = $yeuCauTrucTiep || $cheDoYeuCau === 'HUMAN' || GeminiChatService::laYeuCauChatTrucTiep($noiDung);
        if ($isDirectReq) {
            $userMsg = ChatMessage::create([
                'noi_dung' => $noiDung,
                'nguoi_gui' => $nguoiGui,
                'from_role' => 'USER',
                'kenh' => 'DIRECT',
                'to_user' => $toUser,
                'read_by_admin' => false,
            ]);

            $sysMsg = ChatMessage::create([
                'noi_dung' => '🔔 [CHUYỂN KẾT NỐI] Em đã chuyển phiên chat sang Nhân viên tư vấn trực tiếp của HKTGO. Chuyên viên sẽ hỗ trợ bạn ngay trong ít phút! (Bạn có thể bấm "Quay lại AI" bất kỳ lúc nào).',
                'nguoi_gui' => 'Hệ thống HKTGO',
                'from_role' => 'SYSTEM',
                'kenh' => 'DIRECT',
                'to_user' => $nguoiGui,
                'read_by_admin' => false,
            ]);

            ChatMessage::create([
                'noi_dung' => "Khách hàng [{$nguoiGui}] yêu cầu chat trực tiếp với nhân viên tư vấn: \"{$noiDung}\"",
                'nguoi_gui' => 'Hệ thống HKTGO',
                'from_role' => 'SYSTEM',
                'kenh' => 'DIRECT',
                'to_user' => 'ADMIN',
                'read_by_admin' => false,
            ]);

            return response()->json([
                'code' => 'SUCCESS',
                'cheDo' => 'HUMAN',
                'message' => $userMsg,
                'botMessage' => $sysMsg,
            ], 200);
        }

        // Kiểm tra xem khách có muốn bật lại AI không (chỉ khi có yêu cầu chuyển đổi cụ thể)
        $isBackToAI = $yeuCauQuayLaiAI || GeminiChatService::laYeuCauQuayLaiAI($noiDung);
        if ($isBackToAI) {
            $userMsg = ChatMessage::create([
                'noi_dung' => $noiDung,
                'nguoi_gui' => $nguoiGui,
                'from_role' => 'USER',
                'kenh' => 'AI',
                'to_user' => $toUser,
                'read_by_admin' => true,
            ]);

            $aiMsg = ChatMessage::create([
                'noi_dung' => 'Dạ em là Trợ lý AI HKTGO đã quay trở lại hỗ trợ anh/chị đây ạ! Anh/chị đang cần tìm hiểu mẫu xe nào hay cần hướng dẫn thủ tục thuê xe ạ? 🛵',
                'nguoi_gui' => 'Trợ lý AI HKTGO',
                'from_role' => 'AI',
                'kenh' => 'AI',
                'to_user' => $nguoiGui,
                'read_by_admin' => true,
            ]);

            return response()->json([
                'code' => 'SUCCESS',
                'cheDo' => 'AI',
                'message' => $userMsg,
                'botMessage' => $aiMsg,
            ], 200);
        }

        // Kiểm tra chế độ hiện tại
        $cheDoHienTai = ($cheDoYeuCau === 'AI') ? 'AI' : $this->layCheDoHienTai($nguoiGui);

        // NẾU ĐANG Ở CHẾ ĐỘ HUMAN: Không gọi AI, chờ Admin trả lời
        if ($cheDoHienTai === 'HUMAN') {
            $userMsg = ChatMessage::create([
                'noi_dung' => $noiDung,
                'nguoi_gui' => $nguoiGui,
                'from_role' => 'USER',
                'kenh' => 'DIRECT',
                'to_user' => $toUser,
                'read_by_admin' => false,
            ]);

            return response()->json([
                'code' => 'SUCCESS',
                'cheDo' => 'HUMAN',
                'message' => $userMsg,
            ], 200);
        }

        // NẾU ĐANG Ở CHẾ ĐỘ AI (Mặc định): Gọi Gemini AI tự động trả lời
        $userMsg = ChatMessage::create([
            'noi_dung' => $noiDung,
            'nguoi_gui' => $nguoiGui,
            'from_role' => 'USER',
            'kenh' => 'AI',
            'to_user' => $toUser,
            'read_by_admin' => true,
        ]);

        // Lấy lịch sử hội thoại gần nhất
        $lichSu = ChatMessage::where(function ($q) use ($nguoiGui) {
            $q->whereRaw('LOWER(nguoi_gui) = ?', [mb_strtolower($nguoiGui)])
              ->orWhereRaw('LOWER(to_user) = ?', [mb_strtolower($nguoiGui)]);
        })
        ->orderBy('id', 'desc')
        ->limit(6)
        ->get()
        ->reverse()
        ->toArray();

        // Gọi Gemini AI
        $aiReply = GeminiChatService::traLoi($noiDung, $lichSu);

        $aiMsg = ChatMessage::create([
            'noi_dung' => $aiReply,
            'nguoi_gui' => 'Trợ lý AI HKTGO',
            'from_role' => 'AI',
            'kenh' => 'AI',
            'to_user' => $nguoiGui,
            'read_by_admin' => true,
        ]);

        return response()->json([
            'code' => 'SUCCESS',
            'cheDo' => 'AI',
            'message' => $userMsg,
            'botMessage' => $aiMsg,
        ], 200);
    }

    public function layDanhSach(Request $request)
    {
        $sinceId = (int) ($request->input('sinceId', 0));
        $limit = (int) ($request->input('limit', 50));
        if ($limit < 1) $limit = 50;
        if ($limit > 200) $limit = 200;

        $markRead = filter_var($request->input('markRead', false), FILTER_VALIDATE_BOOLEAN);
        $user = trim((string) $request->input('user', ''));

        $query = ChatMessage::where('id', '>', $sinceId);

        if (!empty($user)) {
            $query->where(function ($q) use ($user) {
                $q->where(function ($sub) use ($user) {
                    $sub->whereIn('from_role', ['ADMIN', 'AI', 'SYSTEM'])
                        ->where(function ($s2) use ($user) {
                            $s2->whereRaw('LOWER(to_user) = ?', [mb_strtolower($user)])
                               ->orWhere('to_user', '');
                        });
                })->orWhere(function ($sub) use ($user) {
                    $sub->where('from_role', 'USER')
                        ->whereRaw('LOWER(nguoi_gui) = ?', [mb_strtolower($user)]);
                });
            });
        }

        $messages = $query->orderBy('id', 'asc')->limit($limit)->get();

        if ($markRead) {
            $idsToMark = $messages->where('from_role', 'USER')->pluck('id');
            if ($idsToMark->isNotEmpty()) {
                ChatMessage::whereIn('id', $idsToMark)->update(['read_by_admin' => true]);
                foreach ($messages as $m) {
                    if ($m->from_role === 'USER') {
                        $m->read_by_admin = true;
                    }
                }
            }
        }

        return response()->json($messages);
    }

    public function demChuaDoc()
    {
        $count = ChatMessage::where('from_role', 'USER')
            ->where('kenh', 'DIRECT')
            ->where('read_by_admin', false)
            ->count();

        return response((string) $count, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function danhDauDaDoc()
    {
        ChatMessage::where('from_role', 'USER')
            ->where('read_by_admin', false)
            ->update(['read_by_admin' => true]);

        return response()->noContent();
    }
}
