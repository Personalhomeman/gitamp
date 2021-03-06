<?php declare(strict_types = 1);

namespace ekinhbayar\GitAmpTests\Provider;

use Amp\Artax\Client;
use Amp\Artax\HttpException;
use Amp\Artax\Response;
use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\Message;
use Amp\Promise;
use Amp\Success;
use ekinhbayar\GitAmp\Provider\RequestFailedException;
use ekinhbayar\GitAmp\Github\Token;
use ekinhbayar\GitAmp\Provider\GitHub;
use ekinhbayar\GitAmp\Response\Factory;
use ekinhbayar\GitAmp\Response\Results;
use PHPUnit\Framework\TestCase;
use function Amp\Promise\wait;
use Psr\Log\LoggerInterface;

class GitHubTest extends TestCase
{
    private $credentials;

    private $factory;

    private $logger;

    public function setUp()
    {
        $this->credentials = new Token('token');
        $this->factory     = $this->createMock(Factory::class);
        $this->logger      = $this->createMock(LoggerInterface::class);
    }

    public function testListenThrowsOnFailedRequest()
    {
        $httpClient = $this->createMock(Client::class);

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->will($this->throwException(new HttpException()))
        ;

        $gitamp = new GitHub($httpClient, $this->credentials, $this->factory, $this->logger);

        $this->expectException(RequestFailedException::class);
        $this->expectExceptionMessage('Failed to send GET request to API endpoint');

        wait($gitamp->listen());
    }

    public function testListenThrowsOnNonOkResponse()
    {
        $response = $this->createMock(Response::class);

        $response
            ->expects($this->exactly(2))
            ->method('getStatus')
            ->will($this->returnValue(403))
        ;

        $response
            ->expects($this->once())
            ->method('getReason')
            ->will($this->returnValue('invalid'))
        ;

        $httpClient = $this->createMock(Client::class);

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->will($this->returnValue(new Success($response)))
        ;

        $gitamp = new GitHub($httpClient, $this->credentials, $this->factory, $this->logger);

        $this->expectException(RequestFailedException::class);
        $this->expectExceptionMessage('A non-200 response status (403 - invalid) was encountered');

        wait($gitamp->listen());
    }

    public function testListenReturnsPromise()
    {
        $response = $this->createMock(Response::class);

        $response
            ->expects($this->once())
            ->method('getStatus')
            ->will($this->returnValue(200))
        ;

        $httpClient = $this->createMock(Client::class);

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->will($this->returnValue(new Success($response)))
        ;

        $this->assertInstanceOf(
            Promise::class,
            (new GitHub($httpClient, $this->credentials, $this->factory, $this->logger))->listen()
        );
    }

    public function testListenReturnsResults()
    {
        $response = $this->createMock(Response::class);

        $response
            ->expects($this->once())
            ->method('getStatus')
            ->will($this->returnValue(200))
        ;

        $response
            ->method('getBody')
            ->willReturn(new Message(new InMemoryStream("mock data")));

        $httpClient = $this->createMock(Client::class);

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->will($this->returnValue(new Success($response)))
        ;

        $this->factory
            ->expects($this->once())
            ->method('build')
            ->will($this->returnValue(new Success($this->createMock(Results::class))))
        ;

        $this->assertInstanceOf(
            Results::class,
            wait((new GitHub($httpClient, $this->credentials, $this->factory, $this->logger))->listen())
        );
    }
}

