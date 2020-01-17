<?php

/**
 * @license  : MIT
 *
 * @copyright: 2019 Matt Kynaston <matt@kynx.org>
 */

declare(strict_types=1);

namespace KynxTest\Guzzle\Mezzio;

use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Psr7\Uri;
use Iterator;
use Kynx\Guzzle\Mezzio\Exception\InvalidCookieException;
use Kynx\Guzzle\Mezzio\Exception\NoSessionException;
use Kynx\Guzzle\Mezzio\MezzioCookieJar;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_intersect_key;
use function json_decode;
use function json_encode;

/**
 * @coversDefaultClass \Kynx\Guzzle\Mezzio\MezzioCookieJar
 */
class MezzioCookieJarTest extends TestCase
{
    private $sessionKey    = 'test';
    private $sessionCookie = [
        'Name'   => 'JSESSiONID',
        'Value'  => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'Domain' => 'localhost',
        'Path'   => '/',
    ];
    private $cookie        = [
        'Name'    => 'foo',
        'Value'   => 'bar',
        'Domain'  => 'localhost',
        'Path'    => '/',
        'Expires' => 2177409599, // '2038-12-31T11:59:59'
    ];

    /**
     * @covers ::__construct
     */
    public function testConstructor()
    {
        $session = $this->getSession([$this->cookie]);
        new MezzioCookieJar($session, $this->sessionKey);
        $actual = json_decode($session->get($this->sessionKey), true);
        $this->assertCount(1, $actual);
        $actual = array_intersect_key($actual[0], $this->cookie);
        $this->assertEquals($this->cookie, $actual);
    }

    /**
     * @covers ::load
     */
    public function testConstructorLoads()
    {
        $session = $this->getSession([$this->cookie]);
        new MezzioCookieJar($session, $this->sessionKey);
        $actual = json_decode($session->get($this->sessionKey), true);
        $this->assertCount(1, $actual);
        $actual = array_intersect_key($actual[0], $this->cookie);
        $this->assertEquals($this->cookie, $actual);
    }

    /**
     * @covers ::persist
     */
    public function testConstructorPersists()
    {
        $session = $this->getSession([$this->cookie]);
        new MezzioCookieJar($session, $this->sessionKey);
        $actual = json_decode($session->get($this->sessionKey), true);
        $this->assertCount(1, $actual);
        $actual = array_intersect_key($actual[0], $this->cookie);
        $this->assertEquals($this->cookie, $actual);
    }

    /**
     * @covers ::load
     */
    public function testConstructorInvalidCookieThrowsException()
    {
        $this->expectException(InvalidCookieException::class);
        $session = $this->getSession(['foo']);
        new MezzioCookieJar($session, $this->sessionKey);
    }

    /**
     * @covers ::persist
     */
    public function testConstructorDoesPersistSessionCookies()
    {
        $session = $this->getSession([$this->sessionCookie]);
        new MezzioCookieJar($session, $this->sessionKey, true);
        $actual = json_decode($session->get($this->sessionKey), true);
        $this->assertCount(1, $actual);
        $actual = array_intersect_key($actual[0], $this->sessionCookie);
        $this->assertEquals($this->sessionCookie, $actual);
    }

    /**
     * @covers ::fromRequest
     */
    public function testFromRequestNoSessionThrowsNoSessionException()
    {
        $this->expectException(NoSessionException::class);
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE)
            ->willReturn(null)
            ->shouldBeCalled();
        MezzioCookieJar::fromRequest($request->reveal(), $this->sessionKey);
    }

    /**
     * @covers ::fromRequest
     */
    public function testFromRequestReturnsCookieJar()
    {
        $session = $this->getSession();
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE)
            ->willReturn($session)
            ->shouldBeCalled();

        $actual = MezzioCookieJar::fromRequest($request->reveal(), $this->sessionKey);
        $this->assertInstanceOf(MezzioCookieJar::class, $actual);
    }

    /**
     * @covers ::withCookieHeader
     */
    public function testWithCookieHeader()
    {
        $uri     = new Uri('http://localhost');
        $request = $this->prophesize(RequestInterface::class);
        $request->getUri()
            ->willReturn($uri);
        $request->withHeader('Cookie', 'foo=bar')
            ->willReturn($request->reveal())
            ->shouldBeCalled();

        $session   = $this->getSession([$this->cookie]);
        $cookieJar = new MezzioCookieJar($session, $this->sessionKey);
        $actual    = $cookieJar->withCookieHeader($request->reveal());
        $this->assertInstanceOf(RequestInterface::class, $actual);
    }

    /**
     * @covers ::extractCookies
     */
    public function testExtractCookiesPersists()
    {
        $uri     = new Uri('http://localhost');
        $request = $this->prophesize(RequestInterface::class);
        $request->getUri()
            ->willReturn($uri);
        $response = $this->prophesize(ResponseInterface::class);
        $response->getHeader('Set-Cookie')
            ->willReturn(['another=cookie; Path=/; Max-Age=' . 60 * 60 * 24]);

        $session   = $this->getSession();
        $cookieJar = new MezzioCookieJar($session, $this->sessionKey);
        $cookieJar->extractCookies($request->reveal(), $response->reveal());

        $expected = [
            'Name'   => 'another',
            'Value'  => 'cookie',
            'Domain' => 'localhost',
            'Path'   => '/',
        ];

        $actual = json_decode($session->get($this->sessionKey), true);
        $this->assertCount(1, $actual);

        $actual = array_intersect_key($actual[0], $expected);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers ::setCookie
     * @covers ::persist
     */
    public function testSetCookiePersists()
    {
        $session   = $this->getSession();
        $cookieJar = new MezzioCookieJar($session, $this->sessionKey);
        $cookieJar->setCookie(new SetCookie($this->cookie));

        $actual = json_decode($session->get($this->sessionKey), true);
        $this->assertCount(1, $actual);

        $actual = array_intersect_key($actual[0], $this->cookie);
        $this->assertEquals($this->cookie, $actual);
    }

    /**
     * @covers ::persist
     */
    public function testSetSessionCookieDoesNotPersist()
    {
        $session   = $this->getSession();
        $cookieJar = new MezzioCookieJar($session, $this->sessionKey);
        $cookieJar->setCookie(new SetCookie($this->sessionCookie));

        $this->assertFalse($session->has($this->sessionKey));
    }

    /**
     * @covers ::toArray
     */
    public function testToArrayReturnsCookies()
    {
        $cookieJar = new MezzioCookieJar($this->getSession([$this->cookie]), $this->sessionKey);
        $actual    = $cookieJar->toArray();
        $this->assertCount(1, $actual);
        $actual = array_intersect_key($actual[0], $this->cookie);
        $this->assertEquals($this->cookie, $actual);
    }

    /**
     * @covers ::count
     */
    public function testCountReturnsCount()
    {
        $cookieJar = new MezzioCookieJar($this->getSession([$this->cookie]), $this->sessionKey);
        $actual    = $cookieJar->count();
        $this->assertEquals(1, $actual);
    }

    /**
     * @covers ::getIterator
     */
    public function testGetIteratorReturnIterator()
    {
        $cookieJar = new MezzioCookieJar($this->getSession([$this->cookie]), $this->sessionKey);
        $actual    = $cookieJar->getIterator();
        $this->assertInstanceOf(Iterator::class, $actual);
    }

    /**
     * @covers ::clearSessionCookies
     */
    public function testClearSessionCookies()
    {
        $session   = $this->getSession([$this->cookie, $this->sessionCookie]);
        $cookieJar = new MezzioCookieJar($session, $this->sessionKey);
        $cookieJar->clearSessionCookies();

        $actual = json_decode($session->get($this->sessionKey), true);
        $this->assertCount(1, $actual);

        $actual = array_intersect_key($actual[0], $this->cookie);
        $this->assertEquals($this->cookie, $actual);

        $this->assertCount(1, $cookieJar->toArray());
    }

    /**
     * @covers ::clear
     */
    public function testClear()
    {
        $session   = $this->getSession([$this->cookie, $this->sessionCookie]);
        $cookieJar = new MezzioCookieJar($session, $this->sessionKey);
        $cookieJar->clear();
        $this->assertFalse($session->has($this->sessionKey));
        $this->assertCount(0, $cookieJar->toArray());
    }

    private function getSession(?array $cookies = null): SessionInterface
    {
        $items = $cookies ? [$this->sessionKey => json_encode($cookies)] : [];
        return new class ($this, $items) implements SessionInterface {
            private $test;
            private $items = [];

            public function __construct(TestCase $test, array $items)
            {
                $this->test  = $test;
                $this->items = $items;
            }

            public function toArray(): array
            {
                return $this->items;
            }

            /**
             * @param mixed|null $default
             * @return mixed|null
             */
            public function get(string $name, $default = null)
            {
                return $this->items[$name] ?? $default;
            }

            public function has(string $name): bool
            {
                return isset($this->items[$name]);
            }

            /**
             * @param mixed $value
             */
            public function set(string $name, $value): void
            {
                $this->items[$name] = $value;
            }

            public function unset(string $name): void
            {
                unset($this->items[$name]);
            }

            public function clear(): void
            {
                $this->items = [];
            }

            public function hasChanged(): bool
            {
                $this->test::fail("hasChanged called");
                return false;
            }

            public function regenerate(): SessionInterface
            {
                $this->test::fail("regenerate called");
                return clone $this;
            }

            public function isRegenerated(): bool
            {
                $this->test::fail("isRegenerated called");
                return false;
            }
        };
    }
}
