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
    public function index_(){
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

        // $events = Event::get($viewStart, $viewEnd, [], 'akihisa.makimoto@dive.design');

        // foreach ($interviewer_setting as $user => $frequency) {
        //     GetGoogleCalendar::dispatch($viewStart, $viewEnd, $user);
		// 	$calendars = $events->groupBy(function ($v) {
		// 		return $v->googleEvent->start->dateTime;
        //     });
        // }
        $this->getScheduledDateTimes('a', 'b');
        echo "<br>end";
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
        $interview_times = [

        ];

        $viewStart = Carbon::parse('2021-04-19', '+9:00');
        $viewEnd = Carbon::parse('2021-04-25T24:00:00', '+9:00');
        // $viewStart = '2021-04-19T10%3A00%3A00%2B09%3A00';
        // $viewEnd = '2021-04-21T10%3A00%3A00%2B09%3A00';
        $viewStart = '2021-04-26T09:00:00+09:00';
        $viewEnd = '2021-04-26T19:00:00+09:00';
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

        foreach ($interviewer_setting as $user => $frequency) {
            $url = "https://www.googleapis.com/calendar/v3/calendars/{$user}/events";

            // 普通に順次実行した結果
            // $resp = $httpClient->request('GET', $url, [
            //     'query' => ['timeMin' => $viewStart, 'timeMax' => $viewEnd, 'singleEvents' => 'true']
            // ]);
            // echo $url . "<br>";
            // echo $resp->getBody()->getContents() . "<br>";

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
                // $x => function()...とすることで、fulfilledの第2引数に割り当てられる
                yield $user => function() use($httpClient, $url, $viewStart, $viewEnd) {
                    return $httpClient->requestAsync('GET', $url, [
                        'query' => ['timeMin' => $viewStart, 'timeMax' => $viewEnd, 'singleEvents' => 'true', 'orderBy' => 'startTime']
                    ]);
                };
                // yield new Request('GET', $url, [
                //     'query' => ['timeMin' => $viewStart, 'timeMax' => $viewEnd]
                // ]);
            }
        };

        $dateTimes = array();
        $pool = new Pool($httpClient, $requests(), [
            'concurrency' => 5,
            'fulfilled' => function(Response $resp, $user) use(&$dateTimes) {
                $events = collect(json_decode($resp->getBody()->getContents())->items);

                // attendeeについての参考　https://buildersbox.corp-sansan.com/entry/2019/03/07/110000
                $filtered = $events->filter(function ($v) use($user) {
                    // 不要なall-dayイベントを除外する all-day eventだとstartにdateTimeキーがない（代わりにdateキーがある）
                    if (!isset($v->start->dateTime)) {
                        return false;
                    }

                    if (!isset($v->attendees)) { // 複数参加者がいる場合のみ存在するプロパティ
                        return true;
                    }
                    
                    // 辞退している予定は除外する
                    foreach ($v->attendees as $attendee) {
                        if ($attendee->email === $user) {
                            return $attendee->responseStatus !== 'declined';
                        }
                    }
                    
                    return true;
                });

                // foreach ($filtered as $d => $e) {
                //     echo "<br>";
                //     echo json_encode($e, JSON_UNESCAPED_UNICODE);
                // }
                $dateTimesPerInterviewer = array();
                foreach ($filtered as $schedule) {
                    echo "<br>";
                    echo $schedule->start->dateTime . " : " . $schedule->end->dateTime;
                    // echo json_encode($this->getScheduledDateTimes($schedule->start->dateTime, $schedule->end->dateTime));
                    $dateTimesPerInterviewer = array_merge($dateTimesPerInterviewer, $this->getScheduledDateTimes($schedule->start->dateTime, $schedule->end->dateTime));
                }
                $dateTimesPerInterviewer = array_unique($dateTimesPerInterviewer);
                echo "<br>";
                echo $user . " : " . json_encode($dateTimesPerInterviewer);
                $dateTimes = array_merge($dateTimes, $dateTimesPerInterviewer);

                // $calendars = $filtered->groupBy(function ($v) {
                //     return $v->start->dateTime;
                // });

                // $dateTimeListPerInterviewer = array_keys($calendars->toArray());
                // $dateTimeListPerInterviewer = array_unique($dateTimeListPerInterviewer);
                // echo "<br>" . $user . "<br>";
                // print_r($dateTimeListPerInterviewer);

                // $dateTimeList = array_merge($dateTimeList, $dateTimeListPerInterviewer);

                // foreach ($calendars as $dateTime => $schedules) {
                //     array_push($dateTimeListPerInterviewer, $dateTime);
                    
                //     echo "<br>";
                //     echo "time : " . $dateTime . "<br>";
                //     $dateTimeList[$dateTime] = count($schedules);
                // }
            }
        ]);

        $promise = $pool->promise();
        $promise->wait();
        echo "<br>----------------------------<br>";
        print_r($dateTimes);
        echo "<br>----------------------------<br>";

        $schedules = array_count_values($dateTimes);

        print_r($schedules);

        // foreach($dateTimeList as $t => $count) {
        //     echo "<br>last<br>";
        //     echo $t . ":" . $count . "<br>";
        // }

    }

    private function getScheduledDateTimes($start, $end) {
        $dtStart = new Carbon($start);
        $dtStart->minute = 0;

        $dtEnd = new Carbon($end);
        
        $dateTimes = array();
        $dt = $dtStart;

        while ($dt < $dtEnd) {
            if (($dt->hour >= 10) && ($dt->hour <= 19)) {
                array_push($dateTimes, $dt->format('Y-m-d H:i:s'));
            }
            $dt->addHour();
        }

        return $dateTimes;
    }
}
