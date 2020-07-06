<?php

namespace Webikevn\HttpClient\Tests;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Webikevn\HttpClient\Formatter;

class FormatterTest extends TestCase
{
    public function testFormatDoesntChangeStateOfTheStream()
    {
        $request = new Request('get', 'uri');
        $response = new Response(200, ['Content-Type' => 'application/json'], json_encode(['one' => 'two']));

        $formatter = new Formatter('{\res_body}');
        $formatter->format($request, $response);

        $this->assertSame(0, $response->getBody()->tell());
        $this->assertSame('{"one":"two"}', $response->getBody()->getContents());
    }

    /**
     * @dataProvider requests
     */
    public function testFormat($request, $response, $log)
    {
        $formatter = new Formatter('{method} HTTP/{version} {"request": {\req_body}, "response": {\res_body}}');
        $this->assertSame($log, $formatter->format($request, $response));
    }

    public function requests()
    {
        return [
            'json request' => [
                new Request('get', 'uri', ['Content-Type' => 'application/json'], json_encode(['key' => 'value'])),
                null,
                'GET HTTP/1.1 {"request": {"key":"value"}, "response": }',
            ],
            'json response' => [
                new Request('get', 'uri'),
                new Response(200, ['Content-Type' => 'application/json; charset=utf-8'], json_encode(['one' => 'two'])),
                'GET HTTP/1.1 {"request": , "response": {"one":"two"}}',
            ],
            'image' => [
                new Request('get', 'uri', ['Content-Type' => 'application/json'], json_encode(['key' => 'value'])),
                new Response(200, ['Content-Type' => 'image/png'], 'binary data here'),
                'GET HTTP/1.1 {"request": {"key":"value"}, "response": [stripped body: image/png]}',
            ],
            'pdf' => [
                new Request('get', 'uri'),
                new Response(200, ['Content-Type' => 'application/pdf'], 'binary data here'),
                'GET HTTP/1.1 {"request": , "response": [stripped body: application/pdf]}',
            ],
        ];
    }
}
