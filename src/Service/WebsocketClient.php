<?php

namespace App\Service;

use Amp\Websocket\Client\Connection;
use Amp\Websocket\Message;

use function Amp\delay;
use function Amp\Websocket\Client\connect;

class WebsocketClient
{
    /**
     * @var string
     */
    private string $string;

    public function refreshMediaList()
    {
        $this->send('refreshMediaList');
    }

    public function importOutput($data)
    {
        $lines = [];
        foreach (explode('\n', $data) as $line) {
            if ('' === $line) {
                continue;
            }

            $lines[] = str_replace(["\n"], "", $line);
        }

        $wsData = [
            'type' => 'importOutput',
            'data' => $data,
        ];

        $this->send(json_encode($wsData));
    }

    public function importLogOutput($data, $stage = 'no-set')
    {
        $wsData = [
            'type' => 'importLogOutput',
            'data' => $data,
            'stage' => $stage
        ];

        $this->send(json_encode($wsData));
    }

    public function ping()
    {
        $this->send('ping');
    }

    private function send(string $string)
    {
        $this->string = $string;
        \Amp\Loop::run(function () {
            $connection = yield connect('ws://websocket:8089/socketserver');
            yield $connection->send($this->string);

            $connection->close();
            $connection = null;

            \Amp\Loop::stop();
        });

        return true;
    }
}
