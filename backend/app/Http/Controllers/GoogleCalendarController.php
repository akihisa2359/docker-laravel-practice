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
		// $interviewer_setting = [
		// 	'akihisa.makimoto@dive.design' => 2,
		// 	'yuta.mizusawa@dive.design' => 1,
		// 	'yurika.kobayashi@dive.design' => 1,
		// 	'tatsuya.kato@dive.design' => 1,
		// 	'satoru.ayukawa@dive.design' => 1,
		// 	'masafumi.ishimoto@dive.design' => 1,
        // ];
        $interviewers = array(
            'akihisa.makimoto@dive.design',
            'yuta.mizusawa@dive.design',
            'yurika.kobayashi@dive.design',
            'tatsuya.kato@dive.design',
            'satoru.ayukawa@dive.design',
            'masafumi.ishimoto@dive.design'
        );
        $reservationViewStart = Carbon::parse('2021-04-19', '+9:00');
        $reservationViewEnd = Carbon::parse('2021-04-25T24:00:00', '+9:00');

        $reservationViewStart = '2021-04-26T09:00:00+09:00';
        $reservationViewEnd = '2021-04-26T19:00:00+09:00';

        $schedules = $this->getBusyDateTime($interviewers, $reservationViewStart, $reservationViewEnd);
        echo json_encode($schedules);

        // $client = new Google_Client();
        // $client->setAuthConfig(storage_path('app/credentials.json'));
        // $client->addScope(Google_Service_Calendar::CALENDAR_EVENTS_READONLY);
        // $httpClient = $client->authorize();

        // $requests = function() use($httpClient, $interviewer_setting, $viewStart, $viewEnd) {
        //     foreach ($interviewer_setting as $user => $frequency) {
        //         $url = "https://www.googleapis.com/calendar/v3/calendars/{$user}/events";
        //         // $x => function()...とすることで、fulfilledの第2引数に割り当てられる
        //         yield $user => function() use($httpClient, $url, $viewStart, $viewEnd) {
        //             return $httpClient->requestAsync('GET', $url, [
        //                 'query' => ['timeMin' => $viewStart, 'timeMax' => $viewEnd, 'singleEvents' => 'true', 'orderBy' => 'startTime']
        //             ]);
        //         };
        //     }
        // };

        // $dateTimes = array();
        // $pool = new Pool($httpClient, $requests(), [
        //     'concurrency' => 5,
        //     'fulfilled' => function(Response $resp, $user) use(&$dateTimes) {
        //         $events = collect(json_decode($resp->getBody()->getContents())->items);

        //         $events = $events->filter(function ($v) use($user) {
        //             // all-dayイベントを除外 all-day eventだとstartにdateTimeキーがない（代わりにdateキーがある）
        //             if (!isset($v->start->dateTime)) {
        //                 return false;
        //             }

        //             if (!isset($v->attendees)) { // 他に参加者がいる場合のみ存在するプロパティ
        //                 return true;
        //             }
                    
        //             // 辞退している予定は除外
        //             foreach ($v->attendees as $attendee) {
        //                 if ($attendee->email === $user) {
        //                     return $attendee->responseStatus !== 'declined';
        //                 }
        //             }
                    
        //             return true;
        //         });

        //         $dateTimesPerInterviewer = array();
        //         foreach ($events as $event) {
        //             $dateTimesPerInterviewer = array_merge($dateTimesPerInterviewer, $this->getScheduledDateTimes($event->start->dateTime, $event->end->dateTime));
        //         }
        //         echo "<br>" . $user . "<br>";
        //         echo json_encode($dateTimesPerInterviewer);
        //         $dateTimesPerInterviewer = array_unique($dateTimesPerInterviewer);
        //         $dateTimes = array_merge($dateTimes, $dateTimesPerInterviewer);
        //     }
        // ]);

        // $promise = $pool->promise();
        // $promise->wait();

        // $schedules = array_count_values($dateTimes);

    }

    // public function calendarsWithJobApply(Request $request, Job $job) {
    //     $pref = $job->prefecture_id;
    //     $interviewers = InterviewerPrefectures::where('id', $pref)->get();
    //     $this->getBusyDateTime($interviewers, $request->start_time, $request->end_time);
    // }

    private function getBusyDateTime($interviewers, $reservationViewStart, $reservationViewEnd) {
        $client = new Google_Client();
        $client->setAuthConfig(storage_path('app/credentials.json'));
        $client->addScope(Google_Service_Calendar::CALENDAR_EVENTS_READONLY);
        $httpClient = $client->authorize();

        $requests = function() use($httpClient, $interviewers, $reservationViewStart, $reservationViewEnd) {
            foreach ($interviewers as $user) {
                $url = "https://www.googleapis.com/calendar/v3/calendars/{$user}/events";
                // $x => function()...とすることで、$xの値がfulfilledの第2引数に割り当てられる
                yield $user => function() use($httpClient, $url, $reservationViewStart, $reservationViewEnd) {
                    return $httpClient->requestAsync('GET', $url, [
                        'query' => ['timeMin' => $reservationViewStart, 'timeMax' => $reservationViewEnd, 'singleEvents' => 'true', 'orderBy' => 'startTime']
                    ]);
                };
            }
        };

        $dateTimes = array();
        $pool = new Pool($httpClient, $requests(), [
            'concurrency' => 5,
            'fulfilled' => function(Response $resp, $user) use(&$dateTimes) {
                $events = collect(json_decode($resp->getBody()->getContents())->items);

                $events = $events->filter(function ($v) use($user) {
                    // all-dayイベントを除外 all-day eventだとstartにdateTimeキーがない（代わりにdateキーがある）
                    if (!isset($v->start->dateTime)) {
                        return false;
                    }

                    if (!isset($v->attendees)) { // 他に参加者がいる場合のみ存在するプロパティ
                        return true;
                    }
                    
                    // 辞退している予定は除外
                    foreach ($v->attendees as $attendee) {
                        if ($attendee->email === $user) {
                            return $attendee->responseStatus !== 'declined';
                        }
                    }
                    
                    return true;
                });

                $dateTimesPerInterviewer = array();
                foreach ($events as $event) {
                    $dateTimesPerInterviewer = array_merge($dateTimesPerInterviewer, $this->getScheduledDateTimes($event->start->dateTime, $event->end->dateTime));
                }
                $dateTimesPerInterviewer = array_unique($dateTimesPerInterviewer);
                $dateTimes = array_merge($dateTimes, $dateTimesPerInterviewer);
            }
        ]);

        $promise = $pool->promise();
        $promise->wait();

        $schedules = array_count_values($dateTimes);

        return $schedules;
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
