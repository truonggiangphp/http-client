<?php

namespace Webikevn\HttpClient;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use LogicException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * A bit of syntax sugar on top of the Guzzle with sensible defaults plus
 * automatic MockHandler to fake requests, eg. in 'testing' environment.
 */
class Factory
{
    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * @var bool
     */
    private $fakeRequests;

    /**
     * @var array
     */
    private $options;

    /**
     * @var HandlerStack
     */
    private $handler;

    /**
     * @var array
     */
    public $history = [];

    /**
     * Factory constructor.
     * @param bool $fakeRequests
     * @param LoggerInterface|null $logger
     */
    public function __construct(bool $fakeRequests, LoggerInterface $logger = null)
    {
        $this->fakeRequests = $fakeRequests;
        $this->logger = $logger;
        $this->reset();
    }

    /**
     * @link http://docs.guzzlephp.org/en/stable/request-options.html
     * @param array $options
     * @return $this
     */
    public function withOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return ClientInterface
     */
    public function make(): ClientInterface
    {
        $client = new Client(['handler' => $this->handler] + $this->options);

        if ($this->fakeRequests) {
            $this->history[$id = spl_object_id($client)] = [];
            $this->withMiddleware(
                Middleware::history($this->history[$id]),
                'fake_history'
            );
        }

        $this->reset();

        return $client;
    }

    /**
     * @param ClientInterface $client
     * @return array
     */
    public function getHistory(ClientInterface $client): array
    {
        return $this->history[spl_object_id($client)] ?? [];
    }

    /**
     * @param string $format
     * @return $this
     */
    public function enableLogging(string $format = Formatter::DEFAULT_FORMAT): self
    {
        if ($this->logger === null) {
            throw new LogicException('In order to use logging a Logger instance must be provided to the Factory');
        }

        return $this->withMiddleware(
            Middleware::log($this->logger, new Formatter($format)),
            'log'
        );
    }

    /**
     * @param int $maxRetries
     * @param int $delayInSec
     * @param int $minErrorCode
     * @return self
     */
    public function enableRetries(int $maxRetries = 3, int $delayInSec = 1, int $minErrorCode = 500): self
    {
        $decider = function ($retries, $_, $response) use ($maxRetries, $minErrorCode) {
            return $retries < $maxRetries
                && $response instanceof ResponseInterface
                && $response->getStatusCode() >= $minErrorCode;
        };

        if ($this->fakeRequests) {
            $delayInSec = 0.0001; // this is so we don't actually wait seconds in tests
        }

        $increasingDelay = function ($attempt) use ($delayInSec) {
            return $attempt * $delayInSec * 1000;
        };

        return $this->withMiddleware(
            Middleware::retry($decider, $increasingDelay),
            'retry'
        );
    }

    /**
     * @param callable $middleware
     * @param string $name
     * @return $this
     */
    public function withMiddleware(callable $middleware, string $name = ''): self
    {
        $this->handler->push($middleware, $name);

        return $this;
    }

    /**
     * @return void
     */
    private function reset(): void
    {
        $this->options = [];

        if ($this->fakeRequests) {
            $mockHandler = new MockHandler;
            $responseCallback = function (RequestInterface $request) use ($mockHandler, &$responseCallback) {
                $mockHandler->append($responseCallback);

                return new Response(200, ['Content-Type' => 'text/plain'], sprintf(
                    'Fake test response for request: %s %s',
                    $request->getMethod(),
                    $request->getUri(),
                ));
            };
            $mockHandler->append($responseCallback);
            $this->handler = HandlerStack::create($mockHandler);
        } else {
            $this->handler = HandlerStack::create();
        }
    }
}
