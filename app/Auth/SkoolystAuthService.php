<?php

namespace App\Auth;

/**
 * SkoolystAuthService
 *
 * "Login with Skoolyst" (10.t) — this app as an OAuth2 client of
 * skoolyst.com's identity provider. Kept thin like every other
 * controller/service in this codebase: public/auth/skoolyst.php and
 * public/auth/skoolyst-callback.php are the actual HTTP entry points
 * (same convention as login.php/signup.php — direct-access pages, not
 * routed through routes/api.php, since they issue redirects and manage
 * the session cookie directly); this class holds the actual logic so
 * those two files stay wiring-only.
 */
class SkoolystAuthService
{
    private array $config;
    private UserRepository $users;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/skoolyst_auth.php';
        $this->users = new UserRepository();
    }

    public function isConfigured(): bool
    {
        return $this->config['client_id'] !== '' && $this->config['client_secret'] !== '' && $this->config['redirect_uri'] !== '';
    }

    /**
     * Step 2 of the guide — the URL to send the browser to. $state must
     * already be generated and stored (session) by the caller; this
     * only builds the URL, it doesn't touch the session itself.
     */
    public function buildAuthorizeUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect_uri'],
            'state' => $state,
        ]);

        return $this->config['base_url'] . '/oauth/authorize?' . $query;
    }

    /**
     * Step 4 of the guide — server-to-server code exchange. Never
     * called with anything from the browser except the `code` itself;
     * client_secret comes only from this server's own config.
     *
     * @return array{ok: true, user: array{id: int, name: string, email: string}}|array{ok: false, message: string}
     */
    public function exchangeCodeForUser(string $code): array
    {
        $ch = curl_init($this->config['base_url'] . '/api/oauth/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'code' => $code,
                'redirect_uri' => $this->config['redirect_uri'],
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            error_log("[skoolyst-auth] token exchange request failed: {$curlError}");

            return ['ok' => false, 'message' => 'Could not reach the Skoolyst login service.'];
        }

        $response = json_decode((string) $raw, true);

        if ($status !== 201 || empty($response['success']) || empty($response['data']['user']['id'])) {
            $errorMessage = $response['error']['message'] ?? "HTTP {$status}";
            error_log("[skoolyst-auth] token exchange rejected: {$errorMessage}");

            return ['ok' => false, 'message' => 'Skoolyst login could not be verified. Please try again.'];
        }

        $user = $response['data']['user'];

        return ['ok' => true, 'user' => [
            'id' => (int) $user['id'],
            'name' => (string) ($user['name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
        ]];
    }

    /**
     * Find-or-create/link (10.t) — keyed by skoolyst_id, per the guide's
     * "key local users by user.id, not by email" rule. Three cases:
     *   1. Already linked (repeat login)     -> sync name/email, return it.
     *   2. A local account with this email exists but isn't linked yet
     *      (registered locally before ever using SSO) -> link it.
     *   3. Neither -> create a brand-new local account.
     *
     * @param array{id: int, name: string, email: string} $skoolystUser
     */
    public function findOrCreateLocalUser(array $skoolystUser): UserModel
    {
        $existingByLink = $this->users->findBySkoolystId($skoolystUser['id']);
        if ($existingByLink !== null) {
            $this->users->syncSsoProfile($existingByLink->id, $skoolystUser['name'], $skoolystUser['email']);

            return $this->users->findById($existingByLink->id);
        }

        $existingByEmail = $skoolystUser['email'] !== '' ? $this->users->findByEmail($skoolystUser['email']) : null;
        if ($existingByEmail !== null) {
            $this->users->linkSkoolystId($existingByEmail->id, $skoolystUser['id']);

            return $this->users->findById($existingByEmail->id);
        }

        return $this->users->createSsoUser($skoolystUser['name'], $skoolystUser['email'], $skoolystUser['id']);
    }
}
