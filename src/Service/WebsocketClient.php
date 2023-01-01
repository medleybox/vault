<?php

declare(strict_types=1);

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

    public function completeRefreshSource($uuid): void
    {
        $this->sendData('completeRefreshSource', ['uuid' => $uuid]);
    }

    public function refreshMediaList(): void
    {
        $this->send('refreshMediaList');
    }

    public function refreshLatestList(): void
    {
        $this->send('refreshLatestList');
    }

    public function refreshUserList(): void
    {
        $this->send('refreshUserList');
    }

    public function importOutput($data): void
    {
        $lines = [];
        foreach (explode('\n', $data) as $line) {
            if ('' === $line) {
                continue;
            }

            $lines[] = str_replace(["\n"], "", $line);
        }

        $this->sendData('importOutput', ['data' => $data, 'lines' => $lines]);
    }

    public function importLogOutput($data, $stage = 'no-set'): void
    {
        $wsData = [
            'type' => 'importLogOutput',
            'data' => $data,
            'stage' => $stage
        ];

        $this->sendData('importLogOutput', $wsData);
    }

    public function ping(): void
    {
        $this->send('ping');
    }

    private function sendData(string $name, array $data): void
    {
        $wsData = [
            'type' => $name,
            'data' => $data,
        ];

        $this->send(json_encode($wsData));
    }

    private function send(string $string): bool
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
