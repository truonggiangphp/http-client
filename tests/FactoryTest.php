<?php


namespace Webikevn\HttpClient\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Webikevn\HttpClient\Factory;

class FactoryTest extends TestCase
{
    public function testClientForFakeRequests()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $factory = new Factory(true, $logger);
        $client = $factory->enableLogging('log request: {method} {uri}')->make();

        $logger->expects($this->once())
            ->method('log')
            ->with(LogLevel::INFO, 'log request: GET https://some.url');

        $client->request('get', 'https://some.url');
    }

    public function testFakeClientWithHistoryForTestingPurposes()
    {
        $factory = new Factory(true);
        $client = $factory->withOptions([
            'base_uri' => 'https://some.url',
        ])->make();

        $client->request('get', 'path');

        $history = $factory->getHistory($client);
        $this->assertNotEmpty($history[0]);
        /** @var RequestInterface $request */
        $request = $history[0]['request'];
        /** @var ResponseInterface $response */
        $response = $history[0]['response'];

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('https://some.url/path', $request->getUri());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Fake test response for request: GET https://some.url/path', $response->getBody());
    }

    public function testRetryingClient()
    {
        $factory = new Factory(true);
        $client = $factory->enableRetries(2, 0.001, 200)->make();

        $client->request('get', 'https://some.url');

        $history = $factory->getHistory($client);
        $this->assertEquals(3, count($history));
    }

    public function testMakeStandardClient()
    {
        $factory = new Factory(true);
        $client = $factory->withOptions([
            'base_uri' => 'https://some.url',
            'auth' => ['user', 'secret'],
        ])->make();

        $this->assertEquals('https://some.url', $client->getConfig('base_uri'));
        $this->assertEquals(['user', 'secret'], $client->getConfig('auth'));
    }
}
