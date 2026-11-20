<?php

namespace App\Services;

use App\Models\DonThue;
use App\Models\Motorbike;

class DonThueStatusService
{
    public const TT_CHO_THANH_TOAN = 'Chờ thanh toán';
    public const TT_CHO_DUYET = 'Chờ duyệt';
    public const TT_DANG_THUE = 'Đang thuê';
    public const TT_HOAN_THANH = 'Hoàn thành';
    public const TT_DA_HUY = 'Đã hủy';

    public const XE_DANG_RANH = 'Đang rảnh';
    public const XE_HET_XE = 'Hết xe';
    public const XE_DANG_GIU_CHO = 'Đang giữ chỗ';

    public static function normalize(string $text = null): string
    {
        if ($text === null) {
            return '';
        }
        $str = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        // Replace accented Vietnamese chars
        $str = preg_replace('/[àáạảãâầấậẩẫăằắặẳẵ]/u', 'a', $str);
        $str = preg_replace('/[èéẹẻẽêềếệểễ]/u', 'e', $str);
        $str = preg_replace('/[ìíịỉĩ]/u', 'i', $str);
        $str = preg_replace('/[òóọỏõôồốộổỗơờớợởỡ]/u', 'o', $str);
        $str = preg_replace('/[ùúụủũưừứựửữ]/u', 'u', $str);
        $str = preg_replace('/[ỳýỵỷỹ]/u', 'y', $str);
        $str = preg_replace('/[đ]/u', 'd', $str);
        $str = preg_replace('/[ÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴ]/u', 'a', $str);
        $str = preg_replace('/[ÈÉẸẺẼÊỀẾỆỂỄ]/u', 'e', $str);
        $str = preg_replace('/[ÌÍỊỈĨ]/u', 'i', $str);
        $str = preg_replace('/[ÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠ]/u', 'o', $str);
        $str = preg_replace('/[ÙÚỤỦŨƯỪỨỰỬỮ]/u', 'u', $str);
        $str = preg_replace('/[ỲÝỴỶỸ]/u', 'y', $str);
        $str = preg_replace('/[Đ]/u', 'd', $str);
        $str = mb_strtolower(trim($str));
        return preg_replace('/\s+/', ' ', $str);
    }

    public static function demDonDangChiemsDung(?Motorbike $xe): int
    {
        if (!$xe) return 0;
        return DonThue::where(function ($q) use ($xe) {
            $hasBienSo = !empty(trim((string)$xe->bien_so));
            if ($hasBienSo) {
                $q->where('bien_so', trim($xe->bien_so));
            }
            if (!empty($xe->id)) {
                if ($hasBienSo) {
                    $q->orWhere('xe_id', $xe->id);
                } else {
                    $q->where('xe_id', $xe->id);
                }
            }
            if (!empty($xe->ten_xe)) {
                $q->orWhere('ten_xe', trim($xe->ten_xe));
            }
        })
        ->whereIn('trang_thai', [
            self::TT_CHO_THANH_TOAN,
            self::TT_CHO_DUYET,
            self::TT_DANG_THUE,
            'Đã duyệt',
            'Đang thuê',
            'Chờ duyệt',
            'Chờ thanh toán'
        ])
        ->count();
    }

    public static function soLuongConLai(?Motorbike $xe): int
    {
        if (!$xe) return 0;
        $tong = max(1, (int)($xe->so_luong ?? 5));
        $daThue = self::demDonDangChiemsDung($xe);
        return max(0, $tong - $daThue);
    }

    public static function xeDangRanh(?Motorbike $xe): bool
    {
        if (!$xe) {
            return false;
        }
        return self::soLuongConLai($xe) > 0;
    }

    public static function dongBoTrangThaiXe(?Motorbike $xe): void
    {
        if (!$xe) {
            return;
        }
        $conLai = self::soLuongConLai($xe);
        $xe->trang_thai = ($conLai > 0) ? self::XE_DANG_RANH : self::XE_HET_XE;
        $xe->save();
    }

    public static function apDungTrangThaiChoDonVaXe(DonThue $don, string $trangThaiMoi): void
    {
        $don->trang_thai = $trangThaiMoi;
        $don->save();

        $xe = null;
        if (!empty($don->bien_so)) {
            $xe = Motorbike::where('bien_so', trim($don->bien_so))->first();
        }
        if (!$xe && !empty($don->xe_id)) {
            $xe = Motorbike::find($don->xe_id);
        }
        if (!$xe && !empty($don->ten_xe)) {
            $xe = Motorbike::where('ten_xe', trim($don->ten_xe))->first();
        }
        if (!$xe) {
            return;
        }

        self::dongBoTrangThaiXe($xe);
    }

    public static function giaiPhongGiuChoQuaHanTheoBienSo(string $bienSo): void
    {
        if (empty($bienSo)) {
            return;
        }
        $quaHan = DonThue::where('bien_so', trim($bienSo))
            ->where('trang_thai', self::TT_CHO_THANH_TOAN)
            ->where('han_thanh_toan', '<', now())
            ->get();

        foreach ($quaHan as $don) {
            self::apDungTrangThaiChoDonVaXe($don, self::TT_DA_HUY);
        }
    }

    public static function huyDonQuaHanThanhToan(): int
    {
        $quaHan = DonThue::where('trang_thai', self::TT_CHO_THANH_TOAN)
            ->where('han_thanh_toan', '<', now())
            ->get();

        $count = 0;
        foreach ($quaHan as $don) {
            self::apDungTrangThaiChoDonVaXe($don, self::TT_DA_HUY);
            $count++;
        }
        return $count;
    }
}
