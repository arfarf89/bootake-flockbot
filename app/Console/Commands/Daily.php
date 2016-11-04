<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class Daily extends Command
{
    protected $signature = 'daily';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Query daily information';

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
        Log::info('schedule:run daily');
        
        $today = Carbon::now();

        $purchaseData = $this->purchase($today);
        $purchaseData = json_decode(json_encode($purchaseData), true);

        $month = $today->format('m');
        $day = $today->format('d');
        $hour = strval($today->format('H'));

        $purchaseMsg = "========================\n" . $month . '월 ' . $day . "일 주문 결산\n" .
                        "========================\n";

        $peperoEventCount = 0;
        $couponCount = 0;
        $cuAppCount = 0;
        $cuCount = 0;
        $signUpCount = 0;
        $generalOrderCount = 0;
        $orderCount = 0;

        foreach ($purchaseData as $event) {

            if ($event["name"] == "Create Order") {
                // TODO Match with storeid
                if ($event["properties"]["Store Name"] == "CU 빼빼로데이 예약배송 이벤트!") {
                    $peperoEventCount += 1;
                } elseif (strpos($event["properties"]["Store Name"], 'CU') !== false) {
                    $cuCount += 1;
                } else {
                    $generalOrderCount += 1;
                }

                if (isset($event["properties"]["Coupon"])) {
                    $couponCount += 1;
                }

                if ($event["properties"]["App"] == "CUMobileApp") {
                    $cuAppCount += 1;
                }

                $orderCount += 1;
            }

            if ($event["name"] == "Sign Up") {
                $signUpCount += 1;
            }
        }

        $purchaseMsg = $purchaseMsg . "회원 가입: $signUpCount 건\n\n";

        $purchaseMsg = $purchaseMsg . "주문 정보:\n";
        $purchaseMsg = $purchaseMsg . "\t빼빼로 예약배송 주문: $peperoEventCount 건\n";
        $purchaseMsg = $purchaseMsg . "\t일반 CU 주문: $cuCount 건 (CU앱: $cuAppCount 건) \n";
        $purchaseMsg = $purchaseMsg . "\t맛집 주문: $generalOrderCount 건\n\n";
        
        $purchaseMsg = $purchaseMsg . "\t총 쿠폰 이용 주문: $couponCount 건\n";
        $purchaseMsg = $purchaseMsg . "\t총 주문: " . $orderCount . " 건\n";

        $purchaseMsg = $purchaseMsg . "---------------------------------------";

        $seriousBotUrl = "https://api.flock.co/hooks/sendMessage/989d90d3-7b19-4ac3-a6c6-0aa1d50e956b";
        $funBotUrl = "https://api.flock.co/hooks/sendMessage/6725b5fa-ea47-4c6a-9b1f-a0c3371c83ff";

//        $this->curlFlock($seriousBotUrl, $purchaseMsg);
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

    private function purchase($today) {
        $api_secret = "4e4ed3a7df70b32e07cf956c2d79dc4f";
        $headers = array("Authorization: Basic " . base64_encode($api_secret));

        $path = "purchase.js";
        $scriptContents = file_get_contents($path);
        $params = json_encode([
            'from_date' => $today->toDateString(),
            'to_date' => $today->toDateString(),
            'events' => [
                [
                    'event' => "Create Order"
                ],
                [
                    'event' => "Sign Up"
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