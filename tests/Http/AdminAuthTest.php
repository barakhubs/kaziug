<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Console\Commands\AdminCreateCommand;
use App\Console\Io;
use App\Core\Config;
use App\Core\Session\Session;
use App\Services\AdminGuard;
use Tests\Support\HttpTestCase;

final class AdminAuthTest extends HttpTestCase
{
    private const EMAIL = 'admin@kazi.ug';
    private const PASSWORD = 'a long test passphrase';

    protected function setUp(): void
    {
        parent::setUp();
        $io = new Io([self::EMAIL, self::PASSWORD, self::PASSWORD], echo: false);
        $this->assertSame(0, $this->container->get(AdminCreateCommand::class)->run([], $io));
    }

    public function testAdminCreateRefusesASecondAdmin(): void
    {
        $io = new Io(['second@kazi.ug', self::PASSWORD, self::PASSWORD], echo: false);

        $this->assertSame(1, $this->container->get(AdminCreateCommand::class)->run([], $io));
        $this->assertStringContainsString('already exists', implode("\n", $io->output));
        $this->assertSame(1, (int) $this->db->fetchValue('SELECT count(*) FROM admin_users'));
    }

    public function testThereIsNoPublicRegistrationRoute(): void
    {
        foreach (['/register', '/signup', '/admin/register', '/admin/users', '/admin/setup'] as $path) {
            $this->assertStatus(404, $this->get($path));
        }
    }

    public function testGuestsAreRedirectedToLogin(): void
    {
        $this->assertRedirect('/admin/login', $this->get('/admin'));
        $this->assertRedirect('/admin/login', $this->get('/admin/account'));
        $this->assertStatus(200, $this->get('/admin/login'));
    }

    public function testLoginRequiresCsrfToken(): void
    {
        $this->assertStatus(419, $this->post('/admin/login', ['email' => self::EMAIL, 'password' => self::PASSWORD], withToken: false));
    }

    public function testFailedLoginShowsGenericError(): void
    {
        $response = $this->post('/admin/login', ['email' => self::EMAIL, 'password' => 'wrong']);

        $this->assertStatus(422, $response);
        $this->assertStringContainsString('Incorrect email or password.', $response->body());
    }

    public function testSuccessfulLoginRegeneratesSessionAndRotatesCsrf(): void
    {
        $this->get('/admin/account'); // creates a pre-login session holding the intended URL
        $sessionBefore = $this->cookies['kaziug_session'] ?? null;
        $csrfBefore = $this->cookies['kaziug_csrf'];
        $this->assertNotNull($sessionBefore);

        $response = $this->post('/admin/login', ['email' => self::EMAIL, 'password' => self::PASSWORD]);

        $this->assertRedirect('/admin/account', $response, );
        $this->assertNotSame($sessionBefore, $this->cookies['kaziug_session']);
        $this->assertNotSame($csrfBefore, $this->cookies['kaziug_csrf']);
        $this->assertSame(0, (int) $this->db->fetchValue('SELECT count(*) FROM sessions WHERE id = ?', [Session::hashId($sessionBefore)]));
        $this->assertStatus(200, $this->get('/admin'));
        $this->assertSame(1, (int) $this->db->fetchValue("SELECT count(*) FROM audit_logs WHERE action = 'auth.login'"));
    }

    public function testSessionCookieFlags(): void
    {
        $this->login();
        $response = $this->get('/admin');
        $this->assertStatus(200, $response);

        $loginResponse = $this->post('/admin/logout');
        $this->assertRedirect('/admin/login', $loginResponse);
        $options = null;
        foreach ($loginResponse->cookies() as $cookie) {
            if ($cookie['name'] === 'kaziug_csrf') {
                $options = $cookie['options'];
            }
        }
        $this->assertNotNull($options);
        $this->assertTrue($options['httponly']);
        $this->assertSame('Lax', $options['samesite']);
    }

    public function testLockoutAfterRepeatedFailures(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertStatus(422, $this->post('/admin/login', ['email' => self::EMAIL, 'password' => "wrong{$i}"]));
        }

        $locked = $this->post('/admin/login', ['email' => self::EMAIL, 'password' => 'wrong again']);
        $this->assertStatus(429, $locked);
        $this->assertStringContainsString('Too many failed attempts', $locked->body());
        $this->assertStatus(429, $this->post('/admin/login', ['email' => self::EMAIL, 'password' => self::PASSWORD]));
    }

    public function testLogoutEndsSession(): void
    {
        $this->login();
        $this->assertRedirect('/admin/login', $this->post('/admin/logout'));
        $this->assertRedirect('/admin/login', $this->get('/admin'));
    }

    public function testSignOutEverywhereRevokesOtherBrowsers(): void
    {
        $this->login();
        $firstBrowser = $this->cookies;

        $this->cookies = [];
        $this->login();
        $this->assertRedirect('/admin/login', $this->post('/admin/account/logout-all'));

        $this->cookies = $firstBrowser;
        $this->assertRedirect('/admin/login', $this->get('/admin'));
    }

    public function testChangePasswordKeepsCurrentSessionAndRevokesOthers(): void
    {
        $this->login();
        $otherBrowser = $this->cookies;
        $this->cookies = [];
        $this->login();

        $bad = $this->post('/admin/account/password', [
            'current_password' => 'nope', 'password' => 'brand new passphrase', 'password_confirmation' => 'brand new passphrase',
        ]);
        $this->assertStatus(422, $bad);
        $this->assertStringContainsString('current password is incorrect', $bad->body());

        $ok = $this->post('/admin/account/password', [
            'current_password' => self::PASSWORD, 'password' => 'brand new passphrase', 'password_confirmation' => 'brand new passphrase',
        ]);
        $this->assertRedirect('/admin/account', $ok);
        $this->assertStatus(200, $this->get('/admin/account'));

        $this->cookies = $otherBrowser;
        $this->assertRedirect('/admin/login', $this->get('/admin'));
    }

    public function testIdleSessionIsSignedOut(): void
    {
        $this->login();
        $idle = $this->container->get(Config::class)->int('session.idle_minutes') * 60;

        // Age the stored session's last-seen time past the idle limit.
        $hash = Session::hashId($this->cookies['kaziug_session']);
        $payload = json_decode((string) $this->db->fetchValue('SELECT payload FROM sessions WHERE id = ?', [$hash]), true);
        $payload[AdminGuard::KEY_LAST_SEEN] = time() - $idle - 5;
        $this->db->execute('UPDATE sessions SET payload = ? WHERE id = ?', [json_encode($payload), $hash]);

        $this->assertRedirect('/admin/login', $this->get('/admin'));
        $this->assertStringContainsString('inactivity', $this->get('/admin/login')->body());
    }

    public function testLoginDoesNotRedirectOffSite(): void
    {
        $this->get('/admin');
        $hash = Session::hashId($this->cookies['kaziug_session']);
        $payload = json_decode((string) $this->db->fetchValue('SELECT payload FROM sessions WHERE id = ?', [$hash]), true);
        $payload[AdminGuard::KEY_INTENDED] = '//evil.example/admin';
        $this->db->execute('UPDATE sessions SET payload = ? WHERE id = ?', [json_encode($payload), $hash]);

        $this->assertRedirect('/admin', $this->post('/admin/login', ['email' => self::EMAIL, 'password' => self::PASSWORD]));
    }

    private function login(): void
    {
        $this->assertContains(
            $this->post('/admin/login', ['email' => self::EMAIL, 'password' => self::PASSWORD])->status(),
            [303],
        );
    }
}
