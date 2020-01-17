# mezzio-guzzle-cookiejar

[![Build Status](https://travis-ci.org/kynx/expressive-guzzle-cookiejar.svg?branch=master)](https://secure.travis-ci.org/kynx/expressive-guzzle-cookiejar)
[![Coverage Status](https://coveralls.io/repos/github/kynx/expressive-guzzle-cookiejar/badge.svg?branch=master)](https://coveralls.io/github/kynx/expressive-guzzle-cookiejar?branch=master)

A [Guzzle cookiejar] implementation with [mezzio-session] persistence.

If your Mezzio application uses Guzzle and you need to persist the cookies Guzzle receives between requests to 
your application, this package is for you. It's particularly useful if you are accessing an API endpoint that uses
sessions to log you in.

## Installation

```
composer install kynx/mezzio-guzzle-cookiejar
```

## Usage

You will need `Mezzio\Session\SessionMiddleware` [piped into your application] _before_ the handler that is
using Guzzle so that the session is available in the request.

The following illustrates a simple proxy for an imaginary REST API:

```php
<?php

namespace My\Handler;

use GuzzleHttp\ClientInterface;
use Kynx\Guzzle\Mezzio\ExpressiveCookieJar;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MyProxy implements RequestHandlerInterface
{
    private $client;
    private $username;
    private $password;
    
    public function __construct(ClientInterface $client, string $username, string $password) 
    {
        $this->client = $client;
        $this->username = $username;
        $this->password = $password;
    }
    
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        // pass `true` as the third parameter so session cookies are stored
        $cookieJar = MezzioCookieJar::fromRequest($request, 'my-proxy', true);
        
        // do we have any cookies?
        if (! count($cookieJar)) {
            $this->login($cookieJar);
        }
        
        // proxy the request
        return $this->client->request('GET', $request->getUri()->getPath(), ['cookies' => $cookieJar]);
    }
    
    private function login(MezzioCookieJar $cookieJar)
    {
        $this->client->request('GET', '/some/rest/auth', [
            'auth' => [$this->username, $this->password],
            'cookies' => $cookieJar
        ]);
    }
}
```

OK, there will be a bit more to it than that, like handling authentication failures and session timeouts on the remote
system. But not too much more :)




[Guzzle cookiejar]: http://docs.guzzlephp.org/en/stable/request-options.html#cookies
[mezzio-session]: https://github.com/mezzio/mezzio-session
[piped into your application]: https://docs.mezzio.dev/mezzio-session/middleware/#adding-the-middleware-to-your-application
