<?php

namespace Webikevn\HttpClient;

use Exception;
use GuzzleHttp\MessageFormatter;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Extension of the Guzzle formatter providing additional substitution:
 *
 * - {\req_body}: Request body if it's a string (eg. skips binary)
 * - {\res_body}: Response body if it's a string (eg. skips binary)
 */
class Formatter extends MessageFormatter
{
    const DEFAULT_FORMAT = '{method} {uri} HTTP/{version} {code} ({res_header_Content-Length} {res_header_Content-Type}) {"request": {\req_body}, "response": {\res_body}}';
    const ALLOWED_CONTENT_TYPES = [
        'application/json',
        'application/ld+json',
        'application/xml',
        'multipart/form-data',
        'text/plain',
        'text/xml',
        'text/html',
    ];

    public function format(RequestInterface $request, ResponseInterface $response = null, Exception $error = null)
    {
        return preg_replace_callback_array([
            preg_quote('/{\req_body}/') => $this->formatter($request),
            preg_quote('/{\res_body}/') => $this->formatter($response),
        ], parent::format($request, $response, $error));
    }

    private function formatter(?MessageInterface $message): callable
    {
        if ($message === null || (string)$message->getBody() === '') {
            return fn() => '';
        }

        $contentType = $message->getHeader('Content-Type')[0] ?? '';

        foreach (self::ALLOWED_CONTENT_TYPES as $allowed) {
            if (strpos($contentType, $allowed) !== false) {
                return function () use ($message) {
                    $body = (string)$message->getBody();
                    $message->getBody()->rewind();

                    return $body;
                };
            }
        }

        return fn() => '[stripped body: ' . $contentType . ']';
    }
}
