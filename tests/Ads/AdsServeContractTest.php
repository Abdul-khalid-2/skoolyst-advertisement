<?php

namespace Tests\Ads;

use Tests\Support\HttpServerTestCase;

/**
 * Section 13.h — API contract test matching an api-docs.php example.
 * Asserts the real response from GET /api/v1/ads/serve matches the
 * exact shape documented in public/api-docs.php's "Serve an Ad"
 * section: `{success, data: {ad: {id, title, description, image_path,
 * cta_text, click_url}}}` — same fields, same envelope, not just "some
 * JSON came back". If a future change to AdController::serve() or
 * AdRepository::findServableForPlacement() drops or renames a field,
 * this is the test that should catch the docs going stale.
 */
class AdsServeContractTest extends HttpServerTestCase
{
    public function testServeResponseMatchesTheDocumentedShape(): void
    {
        $user = $this->seedUser();
        $app = $this->seedApp();
        $placement = $this->seedPlacement($app['app']['id'], 'home_top');

        $ad = $this->seedAd($user['id'], $app['app']['id'], $placement['id'], [
            'title' => 'Speak Confidently in 8 Weeks',
            'description' => 'Small-group spoken English classes with weekend batches.',
            'image_path' => '/images/ads/3f1c9e2a-test.jpg',
            'cta_text' => 'Book a Seat',
            'click_url' => 'https://fluentenglish.example.test/enroll',
            'status' => 'active',
        ]);

        $response = $this->request('GET', "/api/v1/ads/serve?placement={$placement['code']}", [], [
            'Authorization' => 'Bearer ' . $app['api_key'],
        ]);

        $this->assertSame(200, $response['status']);
        $this->assertTrue($response['body']['success']);
        $this->assertArrayHasKey('data', $response['body']);
        $this->assertArrayHasKey('ad', $response['body']['data']);

        $returnedAd = $response['body']['data']['ad'];

        // Exactly the documented field set — the contract, not just a
        // superset check, so a renamed/dropped/added field fails loudly.
        $this->assertSame(
            ['id', 'title', 'description', 'image_path', 'cta_text', 'click_url'],
            array_keys($returnedAd)
        );

        $this->assertSame((int) $ad['id'], $returnedAd['id']);
        $this->assertSame('Speak Confidently in 8 Weeks', $returnedAd['title']);
        $this->assertSame('Book a Seat', $returnedAd['cta_text']);
        $this->assertSame('https://fluentenglish.example.test/enroll', $returnedAd['click_url']);
    }

    public function testServeWithoutAnApiKeyIsUnauthorized(): void
    {
        $response = $this->request('GET', '/api/v1/ads/serve?placement=home_top');

        $this->assertSame(401, $response['status']);
        $this->assertSame('unauthorized', $response['body']['error']['code']);
    }

    public function testServeReturnsNullAdWhenNothingIsEligibleForThePlacement(): void
    {
        $app = $this->seedApp();
        $placement = $this->seedPlacement($app['app']['id'], 'empty_slot');

        $response = $this->request('GET', "/api/v1/ads/serve?placement={$placement['code']}", [], [
            'Authorization' => 'Bearer ' . $app['api_key'],
        ]);

        $this->assertSame(200, $response['status']);
        $this->assertNull($response['body']['data']['ad']);
    }
}
