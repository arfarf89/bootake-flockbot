<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class QueryHourly extends Command
{
    protected $signature = 'hourly';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Query hourly information';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     * @return mixed
     */
    public function handle()
    {
        Log::info('schedule:run hourly');
        $purchaseData = $this->purchase();
        $purchaseData = json_decode(json_encode($purchaseData), true);

        $today = Carbon::now();
        $month = $today->format('m');
        $day = $today->format('d');
        $hour = strval($today->format('H'));

        $purchaseMsg = "========================\n" . $month . '월 ' . $day . "일 " . ($hour - 1) . "시 - " .
                        $hour . "시 주문 현황\n" . "========================\n";

        $peperoEventCount = 0;
        $couponCount = 0;
        $cuAppCount = 0;
        $platformCount = [
            'ANDROID' => 0,
            'IOS' => 0,
            'WEB' => 0,
            'ANDROID_WEB' => 0,
            'IOS_WEB' => 0
        ];
        $cuCount = 0;
        $generalOrderCount = 0;

        foreach ($purchaseData as $event) {
            if (strval($event["properties"]["Store ID"]) == 28777) {
                $peperoEventCount += 1;
            } elseif (strpos($event["properties"]["Store Name"], 'CU') !== false) {
                $cuCount += 1;
            } else {
                $generalOrderCount += 1;
            }

            if (isset($event['properties']['Coupon'])) {
                $couponCount += 1;
            }

            if (isset($event["properties"]["Platform"])) {
               $platform = $event["properties"]["Platform"];
               $platformCount[$platform] += 1;                
            }

            if ($event["properties"]["App"] == "CUMobileApp") {
                $cuAppCount += 1;
            }
        }

        $purchaseMsg = $purchaseMsg . "빼빼로 예약배송 주문: $peperoEventCount 건\n";
        
        $purchaseMsg = $purchaseMsg . "일반 CU 주문: $cuCount 건 (CU앱: $cuAppCount 건) \n";
        $purchaseMsg = $purchaseMsg . "맛집 주문: $generalOrderCount 건 \n\n";

        $purchaseMsg = $purchaseMsg . "총 쿠폰 이용 주문: $couponCount 건\n";
        $purchaseMsg = $purchaseMsg . "총 주문: " . count($purchaseData) . " 건\n";
   
        foreach ($platformCount as $key => $value) {
            $purchaseMsg = $purchaseMsg . "\t" . $key . ": " . $value . "건\n";
        }
        
        $purchaseMsg = $purchaseMsg . "---------------------------------------\n\n\n";

        // $seriousBotUrl = "https://api.flock.co/hooks/sendMessage/989d90d3-7b19-4ac3-a6c6-0aa1d50e956b";
        $funBotUrl = "https://api.flock.co/hooks/sendMessage/6725b5fa-ea47-4c6a-9b1f-a0c3371c83ff";

        // $this->curlFlock($seriousBotUrl, $purchaseMsg);
        $this->curlFlock($funBotUrl, $purchaseMsg);

    }

    private function curlFlock($url, $msg)
    {
        $newCurl = curl_init($url);
        curl_setopt($newCurl, CURLOPT_CUSTOMREQUEST, 'POST'); // -X
        curl_setopt($newCurl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']); // -H
        curl_setopt($newCurl, CURLOPT_POSTFIELDS, "{ 'text': '$msg' }"); // -d
        curl_setopt($newCurl, CURLOPT_POST, 1);

        $newData = curl_exec($newCurl);
        curl_close($newCurl);
    }

    private function purchase()
    {
        $api_secret = "4e4ed3a7df70b32e07cf956c2d79dc4f";
        $headers = array("Authorization: Basic " . base64_encode($api_secret));

        $path = "purchase.js";
        $scriptContents = file_get_contents($path);
        $params = json_encode([
            'from_date' => Carbon::now()->toDateString(),
            'to_date' => Carbon::now()->toDateString(),
            'hour' => Carbon::now()->subHour(1)->format('H'),
            'events' => [
                [
                    'event' => "Create Order"
                ]
            ]
        ]);

        $request_url = 'https://mixpanel.com/api/2.0/jql?';
        $request_url = $request_url . 'params=' . urlencode($params);
        $request_url = $request_url . '&script=' . urlencode($scriptContents);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $request_url,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER => $headers,
        ));

        $data = curl_exec($curl);
        curl_close($curl);

        return json_decode($data);
    }
}