<?php

namespace Tests\Auth;

use Tests\Support\HttpServerTestCase;

/**
 * Section 13.f — one integration test for a single endpoint, the full
 * auth → controller → DB → response shape lifecycle: a real HTTP
 * POST to /api/v1/auth/login, through public/index.php's actual
 * router/middleware pipeline, against a real seeded user row,
 * asserting both the response shape and the session cookie it issues.
 */
class LoginEndpointTest extends HttpServerTestCase
{
    public function testLoginWithCorrectCredentialsSucceedsAndIssuesASessionCookie(): void
    {
        $user = $this->seedUser('advertiser', 'Password123!');

        $response = $this->request('POST', '/api/v1/auth/login', [
            'email' => $user['email'],
            'password' => $user['password'],
        ]);

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['success']);
        $this->assertSame($user['id'], $response['body']['data']['id']);
        $this->assertSame($user['email'], $response['body']['data']['email']);
        $this->assertSame('advertiser', $response['body']['data']['role']);
        $this->assertArrayHasKey('csrf_token', $response['body']['data']);

        $sessionCookie = null;
        foreach ($response['response_headers'] as $header) {
            if (stripos($header, 'Set-Cookie:') === 0 && str_contains($header, 'PHPSESSID')) {
                $sessionCookie = $header;
            }
        }
        $this->assertNotNull($sessionCookie, 'A successful login must set a session cookie.');
        $this->assertStringContainsStringIgnoringCase('HttpOnly', $sessionCookie);
    }

    public function testLoginWithWrongPasswordFailsWithoutRevealingWhichFieldWasWrong(): void
    {
        $user = $this->seedUser('advertiser', 'Password123!');

        $response = $this->request('POST', '/api/v1/auth/login', [
            'email' => $user['email'],
            'password' => 'WrongPassword!',
        ]);

        $this->assertSame(401, $response['status']);
        $this->assertFalse($response['body']['success']);
        $this->assertSame('invalid_credentials', $response['body']['error']['code']);
    }

    public function testLoginWithUnknownEmailFailsWithTheSameErrorAsAWrongPassword(): void
    {
        $response = $this->request('POST', '/api/v1/auth/login', [
            'email' => 'no-such-user@example.test',
            'password' => 'WhateverPassword1',
        ]);

        $this->assertSame(401, $response['status']);
        $this->assertSame('invalid_credentials', $response['body']['error']['code']);
    }
}
