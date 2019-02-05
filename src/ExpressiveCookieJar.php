<?php
/**
 * @copyright: 2019 Matt Kynaston <matt@kynx.org>
 * @license  : MIT
 */
declare(strict_types=1);

namespace Kynx\Guzzle\Expressive;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\CookieJarInterface;
use GuzzleHttp\Cookie\SetCookie;
use Kynx\Guzzle\Expressive\Exception\InvalidCookieException;
use Kynx\Guzzle\Expressive\Exception\NoSessionException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Session\SessionInterface;
use Zend\Expressive\Session\SessionMiddleware;

/**
 * Guzzle cookie jar implementation using zend-expressive-session for persistence
 */
final class ExpressiveCookieJar implements CookieJarInterface
{
    /**
     * @var SessionInterface
     */
    private $session;
    private $sessionKey;
    private $storeSessionCookies;
    private $cookieJar;

    /**
     * ExpressiveCookieJar constructor.
     *
     * @param SessionInterface $session              Session to persist cookies in
     * @param string           $sessionKey           Key to store session cookies in
     * @param bool             $storeSessionCookies  If true, session cookies will be stored
     */
    public function __construct(SessionInterface $session, string $sessionKey, bool $storeSessionCookies = false)
    {
        $this->session = $session;
        $this->sessionKey = $sessionKey;
        $this->storeSessionCookies = $storeSessionCookies;
        $this->cookieJar = new CookieJar();
        $this->load();
    }

    /**
     * Returns instance loaded from request
     *
     * If the expressive session has not been set in `SessionMiddleware::SESSION_ATTRIBUTE` request attribute prior to
     * calling this a `NoSessionException` exception will be thrown.
     *
     * @param ServerRequestInterface $request
     * @param string                 $sessionKey
     * @param bool                   $storeSessionCookies
     *
     * @return ExpressiveCookieJar
     * @throws NoSessionException
     */
    public static function fromRequest(
        ServerRequestInterface $request,
        string $sessionKey,
        bool $storeSessionCookies = false
    ): ExpressiveCookieJar {
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
    public function withCookieHeader(RequestInterface $request)
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
    public function toArray()
    {
        return $this->cookieJar->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return $this->cookieJar->getIterator();
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return $this->cookieJar->count();
    }
}
