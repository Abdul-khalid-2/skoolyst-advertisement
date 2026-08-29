<?php

namespace Tests\Support;

/**
 * HttpServerTestCase
 *
 * Base class for the integration test (13.f) and API contract test
 * (13.h) — both need a real HTTP request through public/index.php's
 * actual router/middleware pipeline, not a direct method call, since
 * that's the whole point of an integration test (per the roadmap
 * item's own wording: "auth → controller → DB → response shape").
 *
 * Spins up PHP's built-in server (`php -S`, the same command the
 * Quick Start section documents for API-only local testing) once per
 * test class, pointed at the test database via explicit process
 * environment variables — never .env — so it's wired to the exact
 * same disposable database DatabaseTestCase truncates/seeds.
 */
abstract class HttpServerTestCase extends DatabaseTestCase
{
    /** @var resource|null */
    private static $process = null;
    private static array $pipes = [];
    protected static string $baseUrl;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 2);
        $port = getenv('TEST_HTTP_PORT') ?: '8098';
        self::$baseUrl = "http://127.0.0.1:{$port}";

        // Pass the already-loaded .env.testing values through as real
        // process environment variables for the server subprocess.
        // core/Env.php never overrides a variable that's already set
        // via getenv(), so this guarantees the spun-up server reads
        // the same test database as this PHPUnit process, with no
        // dependency on .env.testing being re-parsed by it.
        $env = [];
        foreach (['DB_DRIVER', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET', 'APP_TIMEZONE', 'PATH'] as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $env[$key] = $value;
            }
        }
        $env['APP_ENV'] = 'testing';
        $env['APP_DEBUG'] = 'true';

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        self::$process = proc_open(
            ['php', '-S', "127.0.0.1:{$port}", '-t', 'public', 'public/index.php'],
            $descriptors,
            self::$pipes,
            $root,
            $env
        );

        if (self::$process === false) {
            throw new \RuntimeException('Failed to start the test HTTP server (php -S).');
        }

        $deadline = microtime(true) + 5;
        $up = false;
        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', (int) $port, $errno, $errstr, 0.2);
            if ($socket !== false) {
                fclose($socket);
                $up = true;
                break;
            }
            usleep(100000);
        }

        if (!$up) {
            self::stopServer();
            throw new \RuntimeException("Test HTTP server on port {$port} did not come up in time.");
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer();
    }

    private static function stopServer(): void
    {
        if (self::$process !== null && is_resource(self::$process)) {
            proc_terminate(self::$process);
            proc_close(self::$process);
            self::$process = null;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @return array{status: int, body: array<string, mixed>|null, raw: string, response_headers: string[]}
     */
    protected function request(string $method, string $path, array $data = [], array $headers = []): array
    {
        $url = self::$baseUrl . $path;
        $ch = curl_init();

        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = "{$name}: {$value}";
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_TIMEOUT => 5,
        ];

        if (in_array($method, ['POST', 'PATCH', 'PUT'], true)) {
            $options[CURLOPT_POSTFIELDS] = http_build_query($data);
            $curlHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
            $options[CURLOPT_HTTPHEADER] = $curlHeaders;
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);

        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("HTTP request to {$url} failed: {$error}");
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr($raw, 0, $headerSize);
        $rawBody = substr($raw, $headerSize);
        $responseHeaders = array_filter(array_map('trim', explode("\r\n", $rawHeaders)));

        return [
            'status' => $status,
            'body' => json_decode($rawBody, true),
            'raw' => $rawBody,
            'response_headers' => $responseHeaders,
        ];
    }
}
