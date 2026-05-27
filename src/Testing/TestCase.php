<?php

declare(strict_types=1);

namespace Sofy\Testing;

use Sofy\Core\Application;
use Sofy\Http\Request;
use Sofy\Testing\Fakes\EventFake;
use Sofy\Testing\Fakes\MailFake;
use Sofy\Testing\Fakes\QueueFake;
use Sofy\Testing\Fakes\StorageFake;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case for Sofy applications.
 *
 * Extend this in your tests:
 *
 *   class UserTest extends TestCase
 *   {
 *       public function testRegister(): void
 *       {
 *           $response = $this->post('/register', [
 *               'name'  => 'Alice',
 *               'email' => 'alice@example.com',
 *           ]);
 *           $this->assertStatus(201, $response);
 *       }
 *   }
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = $this->createApplication();
    }

    protected function createApplication(): Application
    {
        $bootstrap = dirname(__DIR__, 2) . '/bootstrap/app.php';
        if (file_exists($bootstrap)) {
            return require $bootstrap;
        }
        return new Application(dirname(__DIR__, 2));
    }

    // ── HTTP helpers ──────────────────────────────────────────────────────────

    public function get(string $uri, array $headers = []): TestResponse
    {
        return $this->request('GET', $uri, [], $headers);
    }

    public function post(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('POST', $uri, $data, $headers);
    }

    public function put(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('PUT', $uri, $data, $headers);
    }

    public function patch(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('PATCH', $uri, $data, $headers);
    }

    public function delete(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('DELETE', $uri, $data, $headers);
    }

    private function request(string $method, string $uri, array $data, array $headers): TestResponse
    {
        $server = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri];
        foreach ($headers as $key => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }

        $request = new Request(
            query:   $method === 'GET' ? $data : [],
            body:    $method !== 'GET' ? $data : [],
            server:  $server,
            files:   [],
            cookies: [],
        );

        $response = $this->app->handle($request);
        return new TestResponse($response);
    }

    // ── Fakes ─────────────────────────────────────────────────────────────────

    public function fakeMail(): MailFake
    {
        return MailFake::swap();
    }

    public function fakeQueue(): QueueFake
    {
        return QueueFake::swap();
    }

    public function fakeEvents(): EventFake
    {
        return EventFake::swap();
    }

    public function fakeStorage(string $disk = ''): StorageFake
    {
        return StorageFake::swap($disk);
    }
}
