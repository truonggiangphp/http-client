# http-client
Wrapper around Guzzle HTTP Client to simplify bootstrapping


Simplest example in a Laravel app - ready to copy-paste and run
```php
use Webikevn\HttpClient\Factory;

// register factory in IoC with default app logger
app()->bind(Factory::class, fn () => new Factory(app()->environment('testing'), app('log')->driver()));

// then inject or resolve wherever you need
$httpClient = app(Factory::class)
  ->withOptions(['base_uri' => 'https://api.github.com'])
  ->enableRetries()
  ->enableLogging()
  ->make();
  
$httpClient->get('users/GiangBeo');

// et voila! Automatic retries in case of server errors and logging out of the box:
[2020-05-22 12:38:32] local.INFO: GET https://api.github.com/users/GiangBeo HTTP/1.1 200 (1351 application/json; charset=utf-8) {"request": , "response": {
  "login": "GiangBeo",
  "blog": "https://rivercrane.vn",
  "location": "Vietnam",
  ...
  }
}
```

Raw example with more customization:

```php
// Raw example
$logger = new Logger('HttpLogger');
$logger->pushHandler(
  new StreamHandler('/path/to/the/log/' . date('Y-m-d') . '.log'))
);

$clientFactory = new Factory($fakeRequests, $logger);
$httpClient = $clientFactory
  ->withOptions([
    'base_uri' => 'https://api.github.com',
    'headers' => [
      'User-Agent' => 'My Awesome Client',
    ],
  ])
  ->enableRetries($retries = 3, $delayInSec = 1, $minStatus = 500)
  ->enableLogging($customLogFormat)
  ->withMiddleware($customGuzzleMiddleware)
  ->make();
```


## Testing

Guzzle on its own offers [testing facilities](http://docs.guzzlephp.org/en/stable/testing.html) but here we made it even easier.
Just so you don't have to worry about setting up custom testing clients or dropping `if`s here and there:

```php
$factory = new Factory(true, $logger);
$httpClient = $factory->enableLogging()->make();
$httpClient->get('https://api.github.com/users/GiangBeo');

$factory->getHistory($httpClient);
=>
[
 [
   "request" => GuzzleHttp\Psr7\Request {#7218},
   "response" => GuzzleHttp\Psr7\Response {#7225},
   "error" => null,
   "options" => [
     "synchronous" => true,
     "handler" => GuzzleHttp\HandlerStack {#7205},
     "allow_redirects" => [
       "max" => 5,
       "protocols" => [
         "http",
         "https",
       ],
       "strict" => false,
       "referer" => false,
       "track_redirects" => false,
     ],
     "http_errors" => true,
     "decode_content" => true,
     "verify" => true,
     "cookies" => false,
     "idn_conversion" => true,
   ],
 ],
 ...
 ```



