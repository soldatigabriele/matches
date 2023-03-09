<?php

namespace App\Console\Commands;

use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use GuzzleHttp\Exception\ClientException;

class Matches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:matches {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $date = $this->options()['date'] ?? today()->format('Y-m-d');
        app('log')->info('Fetching matches for day: ' . $date);

        try {
            $client = new \GuzzleHttp\Client;
            $endpoint = 'https://api.football-data.org/v2/teams/66/matches?dateFrom=' . $date . '&dateTo=' . $date;
            $response = $client->get($endpoint, [
                'headers' => [
                    'X-Auth-Token' => env('FD_API_KEY'),
                ]]);
        } catch (ClientException $e) {
            app('log')->error('could not retrieve matches');
            $telegram = new \Telegram\Bot\Api(env('TELEGRAM_BOT_TOKEN'));
            $telegram->sendMessage(['chat_id' => env('TELEGRAM_CHAT_ID'),
                'text' => 'Problem retrieving match information for date: ' . $date . ' code: ' . $e->getResponse()->getStatusCode()]);
            return 1;
        }

        $content = json_decode($response->getBody()->getContents(), true);

        if ($content['count'] == 0) {
            info('no matches found'); 
            return 0;
        }
        foreach ($content['matches'] as $match) {
            info('found matches'); 
            // 66 = Manchester United FC
            if ($match['homeTeam']['id'] == env('TEAM_ID', 66)) {
                $time = Carbon::parse($match['utcDate']);
                $message = 'Manchester United Match vs ' . ($match['awayTeam']['name'] ?? '') . ' on the ' . $time->format('d/m/Y') . ' at ' . $time->format('H:i');
                $telegram = new \Telegram\Bot\Api(env('TELEGRAM_BOT_TOKEN'));
                $telegram->sendMessage(['chat_id' => env('TELEGRAM_CHAT_ID'), 'text' => $message]);
            }
        }
        return 0;
    }
}
