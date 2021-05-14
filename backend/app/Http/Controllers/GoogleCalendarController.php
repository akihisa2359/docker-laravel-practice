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
use DateTime;
use DateTimeZone;

class GoogleCalendarController extends Controller
{
    public function index_(){
        // phpinfo();
		$interviewer_setting = [

		];
		$viewStart = Carbon::parse('2021-04-19', '+9:00');
        $viewEnd = Carbon::parse('2021-04-25T24:00:00', '+9:00');
        
        $event = new Event;

        // $events = Event::get($viewStart, $viewEnd, [], '');

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
        $timeZone = '+9:00';
        $dateTimeZone = new DateTimeZone($timeZone);
		$end = new DateTime();
		// $end->setTimezone($dateTimeZone);
		$end = Carbon::parse($end->format('Y-m-d'), $timeZone);
        $end = $end->toRfc3339String(); // 2021-04-28 00:00:00
        // echo $end; // 2021-04-28 00:00:00

        $interviewers = array(

        );
        $reservationViewStart = Carbon::parse('2021-04-19', '+9:00');
        $reservationViewEnd = Carbon::parse('2021-04-25T24:00:00', '+9:00');

        $reservationViewStart = '2021-05-10T19:00:00+09:00';
        $reservationViewEnd = '2021-05-10T20:00:00+09:00';

        // $schedules = $this->getBusyDateTime($interviewers, $reservationViewStart, $end);
        // echo json_encode($schedules);
        // echo $reservationViewStart->setTime(10, 0, 0)->toRfc3339String();
        // return $schedules;

        $schedules = $this->getFreeBusy($interviewers, $reservationViewStart, $reservationViewEnd);

        $body = [
            'start' => ['dateTime' => $reservationViewStart],
            'end' => ['dateTime' => $reservationViewEnd],
        ];
        if (empty($schedules[$job->user]->busy)) {
            $interviewer = InterviewerWeightings::where('contact_employee_id', $job->client_dive_contact_employee_id)->first('email');
            $body['attendees'] = $interviewer;
            $url = "https://www.googleapis.com/calendar/v3/calendars/{$id}/events";
            $resp = $httpClient->request('POST', $url, ['body' => json_encode($body)]);
        } else {
            
        }
    }

    // public function calendarsWithJobApply(Request $request, Job $job) {
    //     $pref = $job->prefecture_id;
    //     $interviewers = InterviewerPrefectures::where('id', $pref)->first();
    //     $this->getBusyDateTime($interviewers, $request->start_time, $request->end_time);
    // }

    private function getFreeBusy($interviewers, $reservationViewStart, $reservationViewEnd)
    {
        $client = new Google_Client();
        $client->setAuthConfig(storage_path('app/credentials.json'));
        $client->addScope('https://www.googleapis.com/auth/calendar.readonly');
        $httpClient = $client->authorize();

        $ids = [];
        foreach ($interviewers as $interviewer) {
            $ids[] = ['id' => $interviewer];
        }
        // echo json_encode($ids);
        $body = [
            'timeMin' => $reservationViewStart,
            'timeMax' => $reservationViewEnd,
            'timeZone' => 'JST',
            // 'items' => [['id' => ''], ['id' => ''], ['id' => '']]
            'items' => $ids
        ];

        $url = "https://www.googleapis.com/calendar/v3/freeBusy";
        $resp = $httpClient->request('POST', $url, ['body' => json_encode($body)]);
        $calendars = collect(json_decode($resp->getBody()->getContents())->calendars);
        echo "<br>";
        // echo json_encode($calendars) . "<br>";

        $me = '';
        echo json_encode($calendars[$me]->busy) . "<br>";

        

        $calendars = $calendars->filter(function ($calendar) {
            if (empty($calendar->busy)) {
                return false;
            }
            return true;
        });

        echo json_encode($calendars);

        // foreach ($calendars as $user => $calendar) {
        //     echo $user . "<br>";
        //     echo json_encode($calendar) . "<br>";
        // }

        // echo json_encode($calendars) . "<br>";
        // var_dump(array_keys(array($calendars)));


        // $requests = function() use($httpClient, $interviewers, $reservationViewStart, $reservationViewEnd) {
        //     foreach ($interviewers as $user) {
        //         $url = "https://www.googleapis.com/calendar/v3/freeBusy";
        //         // $x => function()...とすることで、$xの値がfulfilledの第2引数に割り当てられる
        //         yield $user => function() use($httpClient, $url, $reservationViewStart, $reservationViewEnd) {
        //             return $httpClient->requestAsync('GET', $url, [
        //                 'query' => ['timeMin' => $reservationViewStart, 'timeMax' => $reservationViewEnd, 'singleEvents' => 'true', 'orderBy' => 'startTime']
        //             ]);
        //         };
        //     }
        // };
    }

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
            'fulfilled' => function($resp, $user) use(&$dateTimes) {
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
