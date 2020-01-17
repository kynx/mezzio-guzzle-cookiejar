<?php

/**
 * @license  : MIT
 *
 * @copyright: 2019 Matt Kynaston <matt@kynx.org>
 */

declare(strict_types=1);

namespace Kynx\Guzzle\Mezzio;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\CookieJarInterface;
use GuzzleHttp\Cookie\SetCookie;
use Kynx\Guzzle\Mezzio\Exception\InvalidCookieException;
use Kynx\Guzzle\Mezzio\Exception\NoSessionException;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Traversable;

use function count;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function sprintf;
use function strlen;

/**
 * Guzzle cookie jar implementation using mezzio-session for persistence
 */
final class MezzioCookieJar implements CookieJarInterface
{
    /** @var SessionInterface */
    private $session;
    private $sessionKey;
    private $storeSessionCookies;
    private $cookieJar;

    /**
     * @param SessionInterface $session             Session to persist cookies in
     * @param string           $sessionKey          Key to store session cookies in
     * @param bool             $storeSessionCookies If true, session cookies will be stored
     */
    public function __construct(SessionInterface $session, string $sessionKey, bool $storeSessionCookies = false)
    {
        $this->session             = $session;
        $this->sessionKey          = $sessionKey;
        $this->storeSessionCookies = $storeSessionCookies;
        $this->cookieJar           = new CookieJar();
        $this->load();
    }

    /**
     * Returns instance loaded from request
     *
     * If the expressive session has not been set in `SessionMiddleware::SESSION_ATTRIBUTE` request attribute prior to
     * calling this a `NoSessionException` exception will be thrown.
     *
     * @throws NoSessionException
     */
    public static function fromRequest(
        ServerRequestInterface $request,
        string $sessionKey,
        bool $storeSessionCookies = false
    ): self {
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        if (! $session instanceof SessionInterface) {
            throw new NoSessionException(sprintf(
                "Request does not have an %s attribute",
                SessionMiddleware::SESSION_ATTRIBUTE
            ));
        }

        return new self($session, $sessionKey, $storeSessionCookies);
    }

    /**
     * Loads cookies from session
     */
    private function load(): void
    {
        $persisted = $this->session->get($this->sessionKey, '');

        $data = json_decode($persisted, true);
        if (is_array($data)) {
            foreach ($data as $cookie) {
                $this->cookieJar->setCookie(new SetCookie($cookie));
            }
        } elseif (is_string($data) && strlen($data)) {
            throw new InvalidCookieException("Invalid cookie data");
        }
    }

    /**
     * Persists cookies in session
     */
    private function persist(): void
    {
        $json = [];
        /** @var SetCookie $cookie */
        foreach ($this->cookieJar as $cookie) {
            if (CookieJar::shouldPersist($cookie, $this->storeSessionCookies)) {
                $json[] = $cookie->toArray();
            }
        }
        if (count($json)) {
            $this->session->set($this->sessionKey, json_encode($json));
        } else {
            $this->session->unset($this->sessionKey);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function withCookieHeader(RequestInterface $request): RequestInterface
    {
        return $this->cookieJar->withCookieHeader($request);
    }

    /**
     * {@inheritdoc}
     */
    public function extractCookies(RequestInterface $request, ResponseInterface $response)
    {
        $this->cookieJar->extractCookies($request, $response);
        $this->persist();
    }

    /**
     * {@inheritdoc}
     */
    public function setCookie(SetCookie $cookie)
    {
        $this->cookieJar->setCookie($cookie);
        $this->persist();
    }

    /**
     * {@inheritdoc}
     *
     * @param string|null $domain Clears cookies matching a domain
     * @param string|null $path   Clears cookies matching a domain and path
     * @param string|null $name   Clears cookies matching a domain, path, and name
     */
    public function clear($domain = null, $path = null, $name = null)
    {
        $this->cookieJar->clear($domain, $path, $name);
        $this->persist();
    }

    /**
     * {@inheritdoc}
     */
    public function clearSessionCookies()
    {
        $this->cookieJar->clearSessionCookies();
        $this->persist();
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->cookieJar->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Traversable
    {
        return $this->cookieJar->getIterator();
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return $this->cookieJar->count();
    }
}
