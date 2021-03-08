<?php

namespace Package\Schedule;

use Illuminate\Support\Facades\Log;
use Package\Theme\BlockchainWeb\Util\Task;
use Telegram\Bot\Api;

class RegisterSchedule
{
    public static function call() {
        try {
            $result_binance = Task::syncBalance();
            $result_vps = Task::syncBalanceVPS();
            $result_money = Task::syncBalanceMoney();
            $result_value_percap = Task::syncPricePerCap();

            $message_bianance = isset($result_binance->msg) ? $result_binance->msg : "Đồng bộ dữ liệu tiền điện tử lúc: " . now();
            $message_vps = isset($result_vps->msg) ? $result_vps->msg : "Đồng bộ dữ liệu chứng khoán lúc: " . now();
            $message_money = isset($result_money->msg) ? $result_money->msg : "Đồng bộ tài sản tương đương tiền lúc: " . now();
            $message_price = isset($result_value_percap->msg) ? $result_value_percap->msg : "Đồng bộ giá trị cổ phần lúc: " . now();

            $message = "System::" . now()->format("d/m/Y H:i:m")
                . "\n:" . $message_bianance
                . "\n:" . $message_vps
                . "\n:" . $message_money
                . "\n:" . $message_price;

            Log::channel('package')->info($message);

            return true;
        } catch (\Exception $exception) {
            $message = "System::" . now()->format("d/m/Y H:i:m") . ": lịch trình cập nhật thất bại";
            Log::channel('package')->info($message);
        }
    }

    public static function bot() {
        try {
            $telegram = new Api();

            $chat_id = "@icarebotapi";
            $mode = "HTML";


            $result = Task::listInfoInvestWeb();

            $totalCal = isset($result->total_cap) ? number_format($result->total_cap) : 0;
            $totalSupply = number_format(10000000);
            $tokenUnlock = isset($result->tokenListed) ? number_format((float)$result->tokenListed->value) : 0;
            $tokenPrice = isset($result->totalValueCap) ? number_format($result->totalValueCap->value) : 0;
            $eps = isset($result->eps_90_day) ? number_format($result->eps_90_day->value) : 0;
            $roe = isset($result->roe) ? number_format($result->roe->value, 2) : 0;
            $vps = isset($result->total_balance_vps) ? number_format($result->total_balance_vps) : 0;
            $persentVps = isset($result->percent_vps) ? number_format($result->percent_vps, 2) : 0;
            $binance = isset($result->total_balance_binance) ? number_format($result->total_balance_binance) : 0;
            $persentBinance = isset($result->percent_binance) ? number_format($result->percent_binance, 2) : 0;
            $money = isset($result->eps_90_day) ? number_format($result->total_balance_money) : 0;
            $persentMoney = isset($result->eps_90_day) ? number_format($result->percent_money, 2) : 0;

            $formatMessage = "<pre>Vốn sở hữu: $totalCal VND</pre>";
            $formatMessage .= "<pre>Tổng nguồn cung: $totalSupply IIS</pre>";
            $formatMessage .= "<pre>Cổ phẩn mở khóa: $tokenUnlock IIS</pre>";
            $formatMessage .= "<pre>Giá trị cố phần: $tokenPrice VND</pre>";
            $formatMessage .= "<pre>EPS tháng gần nhất: $eps VND</pre>";
            $formatMessage .= "<pre>ROE: $roe %</pre>";
            $formatMessage .= "<pre>Tài khoản chứng khóa: $vps VND ($persentVps %)</pre>";
            $formatMessage .= "<pre>Tiền điện tử: $binance VND ($persentBinance %)</pre>";
            $formatMessage .= "<pre>Các khoản tương đương tiền: $money VND ($persentMoney %)</pre>";


            $params = [
                'chat_id' => $chat_id,
                'text' => $formatMessage,
                'parse_mode' => $mode
            ];

            $telegram->sendMessage($params);

            $message = "send message from bot success at " . now();

            Log::channel('package')->info($message);

            return true;
        } catch (\Exception $exception) {
            $message = "System::" . now()->format("d/m/Y H:i:m") . ": lịch trình cập nhật thất bại";
            Log::channel('package')->error($message);
        }
    }
}
