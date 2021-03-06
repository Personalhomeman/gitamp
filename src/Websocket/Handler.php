<?php declare(strict_types = 1);

namespace ekinhbayar\GitAmp\Websocket;

use function Amp\asyncCall;
use Amp\Delayed;
use Aerys\Request;
use Aerys\Response;
use Aerys\Websocket;
use Aerys\Websocket\Endpoint;
use ekinhbayar\GitAmp\Provider\Listener;
use ekinhbayar\GitAmp\Response\Results;
use ekinhbayar\GitAmp\Storage\Counter;

class Handler implements Websocket
{
    /** @var Endpoint */
    private $endpoint;

    private $counter;

    private $origin;

    private $provider;

    /** @var Results */
    private $lastEvents;

    public function __construct(Counter $counter, string $origin, Listener $provider)
    {
        $this->origin   = $origin;
        $this->counter  = $counter;
        $this->provider = $provider;
    }

    public function onStart(Endpoint $endpoint)
    {
        $this->endpoint = $endpoint;

        $this->counter->set(0);

        asyncCall(function () {
            while (true) {
                $this->emit(yield $this->provider->listen());

                yield new Delayed(25000);
            }
        });
    }

    public function onHandshake(Request $request, Response $response)
    {
        if ($request->getHeader('origin') !== $this->origin) {
            $response->setStatus(403);
            $response->end('<h1>origin not allowed</h1>');

            return null;
        }

        return $request->getConnectionInfo()['client_addr'];
    }

    public function onOpen(int $clientId, $handshakeData)
    {
        $this->counter->increment();

        $this->sendConnectedUsersCount($this->counter->get());

        if ($this->lastEvents) {
            $this->endpoint->send($this->lastEvents->jsonEncode(), $clientId);
        }
    }

    private function emit(Results $events)
    {
        if (!$events->hasEvents()) {
            return;
        }

        $this->lastEvents = $events;

        $this->endpoint->broadcast($events->jsonEncode());
    }

    private function sendConnectedUsersCount(int $count)
    {
        $this->endpoint->broadcast(\json_encode(['connectedUsers' => $count]));
    }

    public function onData(int $clientId, Websocket\Message $msg)
    {
        // yielding $msg buffers the complete payload into a single string.
    }

    public function onClose(int $clientId, int $code, string $reason)
    {
        $this->counter->decrement();

        $this->sendConnectedUsersCount($this->counter->get());
    }

    public function onStop()
    {
        // intentionally left blank
    }
}
