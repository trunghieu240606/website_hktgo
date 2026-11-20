<?php

namespace App\Console\Commands;

use App\Services\DonThueStatusService;
use Illuminate\Console\Command;

class CancelExpiredOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:cancel-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tự động hủy các đơn thuê xe quá hạn 15 phút chưa thanh toán và giải phóng xe';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = DonThueStatusService::huyDonQuaHanThanhToan();
        $this->info("Đã quét và hủy {$count} đơn quá hạn thanh toán.");
        return Command::SUCCESS;
    }
}
