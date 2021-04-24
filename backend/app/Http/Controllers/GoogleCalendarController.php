<?php

namespace App\Http\Controllers;

// use Illuminate\Http\Request;
use Spatie\GoogleCalendar\Event;
use App\Jobs\GetGoogleCalendar;
use Carbon\Carbon;
use Google_Client;
use Google_Service;
use Google_Service_Calendar;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Promise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Pool;
use phpDocumentor\Reflection\PseudoTypes\True_;

class GoogleCalendarController extends Controller
{
    public function index_trial(){
        // phpinfo();
		$interviewer_setting = [
			'akihisa.makimoto@dive.design' => 2,
			'yuta.mizusawa@dive.design' => 1,
			'yurika.kobayashi@dive.design' => 1,
			'tatsuya.kato@dive.design' => 1,
			'satoru.ayukawa@dive.design' => 1,
			'masafumi.ishimoto@dive.design' => 1,
		];
		$viewStart = Carbon::parse('2021-04-19', '+9:00');
        $viewEnd = Carbon::parse('2021-04-25T24:00:00', '+9:00');
        
        $event = new Event;
  
        $events = Event::get($viewStart, $viewEnd, [], 'akihisa.makimoto@dive.design');

        foreach ($interviewer_setting as $user => $frequency) {
            GetGoogleCalendar::dispatch($viewStart, $viewEnd, $user);
			$calendars = $events->groupBy(function ($v) {
				return $v->googleEvent->start->dateTime;
            });
        }

        echo "end";
    }

    public function index() {
		$interviewer_setting = [
			'akihisa.makimoto@dive.design' => 2,
			'yuta.mizusawa@dive.design' => 1,
			'yurika.kobayashi@dive.design' => 1,
			'tatsuya.kato@dive.design' => 1,
			'satoru.ayukawa@dive.design' => 1,
			'masafumi.ishimoto@dive.design' => 1,
		];

        $viewStart = Carbon::parse('2021-04-19', '+9:00');
        $viewEnd = Carbon::parse('2021-04-25T24:00:00', '+9:00');
        // $viewStart = '2021-04-19T10%3A00%3A00%2B09%3A00';
        // $viewEnd = '2021-04-21T10%3A00%3A00%2B09%3A00';
        $viewStart = '2021-04-20T10:00:00+09:00';
        $viewEnd = '2021-04-21T23:00:00+09:00';
        echo $viewStart;
        echo $viewEnd;
        $client = new Google_Client();
        $client->setAuthConfig(storage_path('app/credentials.json'));
        $client->addScope(Google_Service_Calendar::CALENDAR_EVENTS_READONLY);
        $httpClient = $client->authorize();
        // ↓ノーマルの成功ケース
        // $resp = $httpClient->get('https://www.googleapis.com/calendar/v3/calendars/akihisa.makimoto@dive.design/events');
        // echo $resp->getBody()->getContents();

        // $url = 'https://www.googleapis.com/calendar/v3/calendars/akihisa.makimoto@dive.design/events?timeMin=' . $viewStart . '&timeMax=' . $viewEnd;
        $url = 'https://www.googleapis.com/calendar/v3/calendars/akihisa.makimoto@dive.design/events';

        foreach ($interviewer_setting as $user => $frequency) {
            $url = "https://www.googleapis.com/calendar/v3/calendars/{$user}/events";
            echo $url;

            // 普通に順次実行した結果
            // $resp = $httpClient->request('GET', $url, [
            //     'query' => ['timeMin' => $viewStart, 'timeMax' => $viewEnd]
            // ]);
            // echo $resp->getBody()->getContents();

            // 並列処理
            // $promise = $httpClient->requestAsync('GET', $url, [
            //     'query' => ['timeMin' => $viewStart, 'timeMax' => $viewEnd]
            // ]);
            // $promises[] = $promise;
        }

        // 並列処理ができるが、メモリを食う
        // $results = \GuzzleHttp\Promise\all($promises)->wait();
        // foreach ($results as $key => $resp) {
        //     $contents = $resp->getBody()->getContents();
        //     echo $contents . "<br><br>";
        // }

        // メモリを節約できる方法------------------------------------
        $requests = function() use($httpClient, $interviewer_setting, $viewStart, $viewEnd) {
            foreach ($interviewer_setting as $user => $frequency) {
                $url = "https://www.googleapis.com/calendar/v3/calendars/{$user}/events";
                yield function() use($httpClient, $url, $viewStart, $viewEnd) {
                    return $httpClient->requestAsync('GET', $url, [
                        'query' => ['timeMin' => $viewStart, 'timeMax' => $viewEnd, 'singleEvents' => 'true']
                    ]);
                };
                // yield new Request('GET', $url, [
                //     'query' => ['timeMin' => $viewStart, 'timeMax' => $viewEnd]
                // ]);
            }
        };

        $dateTimeList = array();
        $pool = new Pool($httpClient, $requests(), [
            'concurrency' => 5,
            'fulfilled' => function(Response $resp, $index) use(&$dateTimeList) {
                echo "----------<br>";
                // echo $resp->getBody()->getContents();
                echo "----------<br>";

                $events = collect(json_decode($resp->getBody()->getContents())->items);
                
                foreach ($events as $k => $v) {
                    // echo $k . " : " . json_encode($v, JSON_UNESCAPED_UNICODE) . "<br>";
                }

                // 不要なall-dayイベントを除外する all-day eventだとstartにdateTimeキーがない（代わりにdateキーがある）
                $filtered = $events->filter(function ($v) {
                    return isset($v->start->dateTime);
                });

                $calendars = $filtered->groupBy(function ($v) {
                    // echo json_encode($v);
                    return $v->start->dateTime;
                });

                // echo "<br>";
                // echo json_encode($calendars);

                $dateTimeListPerInterviewer = array_keys($calendars->toArray());
                $dateTimeListPerInterviewer = array_unique($dateTimeListPerInterviewer);
                print_r($dateTimeListPerInterviewer);

                foreach ($calendars as $dateTime => $schedules) {
                    array_push($dateTimeListPerInterviewer, $dateTime);
                    
                    // echo "<br>";
                    // echo "time : " . $dateTime . "<br>";
                    // $dateTimeList[$dateTime] = count($schedules);
                }
            }
        ]);

        $promise = $pool->promise();
        $promise->wait();

        foreach($dateTimeList as $t => $count) {
            echo "<br>last<br>";
            echo $t . ":" . $count . "<br>";
        }

        $url = "https://www.googleapis.com/calendar/v3/calendars/akihisa.makimoto@dive.design/events/dd99p88lc84fkqcc03cl4s0grn_R20210415T103000";
        $res = $httpClient->request('GET', $url);
        echo "<br><br>";
        echo get_class($res->getBody());
        echo $res->getBody()->getContents();
        //------------------------------------------------------
        
        // $promise->then(
        //     function($response) {
        //         return $response;
        //     }
        // );
        // $resp = $promise->wait();
        // echo $resp->getBody()->getContents();

    }
}
