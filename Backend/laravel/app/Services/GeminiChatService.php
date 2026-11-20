<?php

namespace App\Services;

use App\Models\Motorbike;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiChatService
{
    public static function laYeuCauChatTrucTiep(string $noiDung): bool
    {
        $norm = DonThueStatusService::normalize($noiDung);
        $keywords = [
            'chat truc tiep',
            'gap nhan vien',
            'gap tu van vien',
            'noi chuyen voi nguoi',
            'gap nguoi',
            'gap nguoi that',
            'gap admin',
            'ho tro truc tiep',
            'chuyen nhan vien',
            'nhan vien tu van',
            'nhan vien ho tro',
            'tu van truc tiep',
            'chuyen nguoi',
            'gap tu van',
            'goi nguoi',
            'gap truc tiep'
        ];

        foreach ($keywords as $kw) {
            if (str_contains($norm, $kw)) {
                return true;
            }
        }
        return false;
    }

    public static function laYeuCauQuayLaiAI(string $noiDung): bool
    {
        $norm = DonThueStatusService::normalize($noiDung);
        $keywords = [
            'quay lai ai',
            'bat lai ai',
            'tro ly ai',
            'chuyen ve ai',
            'tro lai ai',
            'bat bot',
            'chat voi ai',
            'hoi ai',
            'quay lai bot'
        ];

        foreach ($keywords as $kw) {
            if (str_contains($norm, $kw)) {
                return true;
            }
        }
        return false;
    }

    public static function buildSystemPrompt(array $danhSachXe): string
    {
        $xeStr = "";
        foreach ($danhSachXe as $x) {
            $ten = $x['tenXe'] ?? $x['ten_xe'] ?? '';
            $loai = $x['loaiXe'] ?? $x['loai_xe'] ?? '';
            $gia = number_format($x['giaThue'] ?? $x['gia_thue'] ?? 0, 0, ',', '.') . ' ₫/ngày';
            $con = $x['soLuongConLai'] ?? $x['so_luong_con_lai'] ?? 5;
            $xeStr .= "- {$ten} ({$loai}) - Giá thuê: {$gia} - Còn lại: {$con} xe\n";
        }

        return "Bạn là 'Trợ lí HKTGO', tư vấn viên chuyên nghiệp của nền tảng dịch vụ cho thuê xe máy trực tuyến HKTGO tại Hà Nội.\n\n" .
            "Thông tin dịch vụ HKTGO:\n" .
            "- Địa chỉ nhận/trả xe: Văn phòng HKTGO - 123 Cầu Giấy, Hà Nội (có hỗ trợ giao xe tận nơi theo yêu cầu).\n" .
            "- Hotline / Zalo: 0365145525 (Chủ tài khoản: TRẦN TRUNG HIẾU - MB Bank / MoMo).\n" .
            "- Giờ hoạt động: 07:00 - 22:00 tất cả các ngày trong tuần.\n" .
            "- Thủ tục thuê xe: Cần CCCD/Hộ chiếu gốc + Giấy phép lái xe (A1/A2). Tiền cọc minh bạch hoặc thanh toán trước qua QR.\n" .
            "- Quy trình đặt xe: Khách chọn xe trong bảng 'Danh sách xe trong kho', bấm 'Đặt ngay', điền thông tin và quét mã VietQR tự động khóa số tiền và nội dung để được duyệt nhanh nhất.\n\n" .
            "Danh sách xe hiện có trong kho HKTGO:\n" .
            $xeStr . "\n" .
            "Nguyên tắc tư vấn:\n" .
            "1. Luôn xưng 'em' và gọi khách là 'anh/chị' hoặc 'bạn' với thái độ niềm nở, lịch sự, nhiệt tình.\n" .
            "2. Trả lời ngắn gọn, súc tích, đi thẳng vào câu hỏi của khách hàng, dùng icon nhẹ nhàng (🛵, 📍, 💰, 📞).\n" .
            "3. Khi khách hỏi giá hoặc mẫu xe, tư vấn cụ thể theo danh sách xe ở trên.\n" .
            "4. Nếu khách cần thương lượng riêng, khiếu nại hoặc cần người xử lý trực tiếp, hãy hướng dẫn khách nhắn 'chat trực tiếp' hoặc bấm nút 'Gặp nhân viên tư vấn'.";
    }

    public static function traLoi(string $tinNhan, array $lichSu = [], array $danhSachXe = []): string
    {
        $apiKey = env('GEMINI_API_KEY');
        $model = env('GEMINI_MODEL', 'gemini-1.5-flash');

        if (empty($danhSachXe)) {
            $danhSachXe = Motorbike::all()->map(function ($x) {
                return [
                    'tenXe' => $x->ten_xe,
                    'loaiXe' => $x->loai_xe,
                    'giaThue' => $x->gia_thue,
                    'soLuongConLai' => DonThueStatusService::soLuongConLai($x),
                ];
            })->toArray();
        }

        $systemPrompt = self::buildSystemPrompt($danhSachXe);

        // Nếu có API key hợp lệ, gọi Google Gemini REST API
        if (!empty($apiKey) && strlen(trim($apiKey)) > 10 && !str_contains($apiKey, 'YOUR_GEMINI_API_KEY')) {
            $modelsToTry = array_unique([$model, 'gemini-flash-lite-latest', 'gemini-3.1-flash-lite', 'gemini-3.8-flash']);

            foreach ($modelsToTry as $currentModel) {
                try {
                    $contents = [];
                    // Thêm lịch sử ngắn
                    $recent = array_slice($lichSu, -6);
                    foreach ($recent as $msg) {
                        $role = (strtoupper($msg['fromRole'] ?? $msg['from_role'] ?? 'USER') === 'AI') ? 'model' : 'user';
                        $txt = trim($msg['noiDung'] ?? $msg['noi_dung'] ?? '');
                        if (!empty($txt)) {
                            $contents[] = [
                                'role' => $role,
                                'parts' => [['text' => $txt]]
                            ];
                        }
                    }

                    // Tin nhắn hiện tại
                    $contents[] = [
                        'role' => 'user',
                        'parts' => [['text' => $tinNhan]]
                    ];

                    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$currentModel}:generateContent?key=" . trim($apiKey);

                    $response = Http::timeout(10)->post($url, [
                        'system_instruction' => [
                            'parts' => [
                                ['text' => $systemPrompt]
                            ]
                        ],
                        'contents' => $contents,
                        'generationConfig' => [
                            'temperature' => 0.7,
                            'maxOutputTokens' => 800,
                        ]
                    ]);

                    if ($response->successful()) {
                        $json = $response->json();
                        $parts = $json['candidates'][0]['content']['parts'] ?? [];
                        $reply = '';
                        foreach ($parts as $p) {
                            if (isset($p['text'])) {
                                $reply .= $p['text'];
                            }
                        }
                        if (!empty(trim((string)$reply))) {
                            return trim($reply);
                        }
                    } else {
                        Log::warning("Gemini model {$currentModel} returned {$response->status()}: " . $response->body());
                    }
                } catch (\Throwable $e) {
                    Log::error("Gemini model {$currentModel} exception: " . $e->getMessage());
                }
            }
        }

        // Fallback AI thông minh chuyên nghiệp khi chưa cấu hình Key hoặc timeout
        return self::fallbackAIResponse($tinNhan, $danhSachXe);
    }

    private static function fallbackAIResponse(string $tinNhan, array $danhSachXe): string
    {
        $norm = DonThueStatusService::normalize($tinNhan);

        if (str_contains($norm, 'chao') || str_contains($norm, 'hello') || str_contains($norm, 'hi') || str_contains($norm, 'alo')) {
            return "Dạ em chào anh/chị! 🛵 Em là Trợ lí HKTGO. Anh/chị đang quan tâm đến mẫu xe nào hay cần em hỗ trợ thủ tục, bảng giá thuê xe ạ?";
        }

        if (str_contains($norm, 'gia') || str_contains($norm, 'bao nhieu') || str_contains($norm, 'bang gia') || str_contains($norm, 'chi phi')) {
            $res = "Dạ bảng giá thuê xe hiện tại của HKTGO như sau ạ:\n";
            foreach ($danhSachXe as $x) {
                $ten = $x['tenXe'] ?? $x['ten_xe'] ?? '';
                $gia = number_format($x['giaThue'] ?? $x['gia_thue'] ?? 0, 0, ',', '.') . ' ₫/ngày';
                $con = $x['soLuongConLai'] ?? $x['so_luong_con_lai'] ?? 5;
                $res .= "• {$ten}: {$gia} (còn {$con} xe)\n";
            }
            $res .= "\nAnh/chị có thể bấm 'Đặt ngay' trên xe mình thích để khóa xe nhanh chóng ạ!";
            return $res;
        }

        if (str_contains($norm, 'thu tuc') || str_contains($norm, 'giay to') || str_contains($norm, 'can gi') || str_contains($norm, 'coc')) {
            return "Dạ thủ tục thuê xe tại HKTGO rất đơn giản và nhanh gọn ạ:\n" .
                "1. CCCD / Hộ chiếu gốc.\n" .
                "2. Giấy phép lái xe máy (bản gốc hoặc VNeID).\n" .
                "3. Tiền cọc minh bạch hoặc thanh toán trước qua chuyển khoản VietQR.\n" .
                "Sau khi gửi đơn, hệ thống sẽ giữ xe ngay cho anh/chị!";
        }

        if (str_contains($norm, 'dia chi') || str_contains($norm, 'o dau') || str_contains($norm, 'van phong') || str_contains($norm, 'lay xe')) {
            return "📍 Văn phòng HKTGO tọa lạc tại: 123 Cầu Giấy, Hà Nội.\n" .
                "Bên em có hỗ trợ giao nhận xe tận nơi theo địa chỉ anh/chị yêu cầu trong khu vực Hà Nội ạ. Hotline/Zalo hỗ trợ: 0365145525.";
        }

        if (str_contains($norm, 'xe ga') || str_contains($norm, 'tay ga') || str_contains($norm, 'vision') || str_contains($norm, 'vario') || str_contains($norm, 'sh')) {
            return "🛵 Về dòng xe tay ga, HKTGO hiện có:\n" .
                "• Honda Vario 160: 100.000 ₫/ngày\n" .
                "• Honda Vision 2024: 150.000 ₫/ngày\n" .
                "• Honda SH 150i: 350.000 ₫/ngày\n" .
                "Tất cả đều được bảo dưỡng định kỳ và vận hành êm ái, anh/chị có thể chọn đặt ngay trên web ạ!";
        }

        if (str_contains($norm, 'xe so') || str_contains($norm, 'wave') || str_contains($norm, 'future')) {
            return "🛵 Về dòng xe số tiết kiệm xăng, bên em có:\n" .
                "• Honda Wave: 200.000 ₫/ngày\n" .
                "• Honda Future 125 FI: 150.000 ₫/ngày\n" .
                "Xe rất khỏe và phù hợp đi lại linh hoạt trong thành phố cũng như đi xa ạ!";
        }

        if (str_contains($norm, 'thanh toan') || str_contains($norm, 'stk') || str_contains($norm, 'ngan hang') || str_contains($norm, 'momo') || str_contains($norm, 'vietqr')) {
            return "💳 HKTGO hỗ trợ thanh toán tiện lợi qua chuyển khoản VietQR & MoMo:\n" .
                "• Chủ tài khoản: TRẦN TRUNG HIẾU\n" .
                "• Ngân hàng: MB Bank / Ví MoMo (0365145525)\n" .
                "Khi anh/chị đặt xe trên web, hệ thống sẽ tự động tạo mã QR chứa đúng số tiền và nội dung đơn để quét thanh toán 1 chạm ạ!";
        }

        // Mặc định
        return "Dạ em đã ghi nhận thông tin của anh/chị ạ! HKTGO hỗ trợ thuê xe máy theo ngày với nhiều dòng xe phong phú. Nếu cần hỗ trợ đặc biệt hoặc gặp trực tiếp chuyên viên tư vấn, anh/chị vui lòng nhấn nút '💬 Gặp nhân viên tư vấn' hoặc nhắn 'chat trực tiếp' để bên em hỗ trợ ngay nhé!";
    }
}
