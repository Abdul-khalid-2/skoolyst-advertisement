<?php

namespace Tests\Auth;

use Tests\Support\HttpServerTestCase;

/**
 * Integration test for POST /api/v1/auth/logout — the endpoint itself
 * already existed, but nothing in the UI actually called it (the
 * sidebar's logout link just navigated to index.html, leaving the
 * session cookie valid). This confirms the real behavior the frontend
 * fix (dashboard.js's initLogout()) now depends on: a session that's
 * valid before logout must actually be gone after it, not just
 * visually — the whole point of completing "logout" properly.
 */
class LogoutEndpointTest extends HttpServerTestCase
{
    public function testLogoutDestroysTheSessionSoAFollowUpRequestIsLoggedOut(): void
    {
        $user = $this->seedUser();

        $login = $this->request('POST', '/api/v1/auth/login', [
            'email' => $user['email'],
            'password' => $user['password'],
        ]);
        $this->assertSame(200, $login['status']);

        $cookie = $this->extractSessionCookie($login['response_headers']);
        $csrfToken = $login['body']['data']['csrf_token'];

        $before = $this->request('GET', '/api/v1/auth/session', [], ['Cookie' => $cookie]);
        $this->assertTrue($before['body']['data']['loggedIn'], 'Sanity check: session must be valid before logout.');

        $logout = $this->request('POST', '/api/v1/auth/logout', [], [
            'Cookie' => $cookie,
            'X-CSRF-Token' => $csrfToken,
        ]);
        $this->assertSame(200, $logout['status']);
        $this->assertTrue($logout['body']['success']);

        $after = $this->request('GET', '/api/v1/auth/session', [], ['Cookie' => $cookie]);
        $this->assertFalse($after['body']['data']['loggedIn'], 'The same cookie must no longer be a valid session after logout.');
    }

    public function testLogoutWithoutASessionIsUnauthorized(): void
    {
        $response = $this->request('POST', '/api/v1/auth/logout');

        $this->assertSame(401, $response['status']);
    }

    private function extractSessionCookie(array $headers): string
    {
        foreach ($headers as $header) {
            if (stripos($header, 'Set-Cookie:') === 0 && str_contains($header, 'skoolyst_ads_session')) {
                $value = trim(substr($header, strlen('Set-Cookie:')));
                return explode(';', $value)[0];
            }
        }

        throw new \RuntimeException('No session cookie found in the login response headers.');
    }
}
