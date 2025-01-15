<?php declare(strict_types=1);

namespace Naisdevice\Jita;

use Naisdevice\Jita\Session\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Session::class)]
class SessionTest extends TestCase
{
    private Session $session;

    public function setUp(): void
    {
        $_SESSION['user'] = null;
        $this->session = new Session();
    }

    public function testCanSetAndGetUser(): void
    {
        $user = new User('id', 'mail@example.com', 'name', ['id1', 'id2']);
        $this->assertNull($this->session->getUser());
        $this->session->setUser($user);
        $this->assertSame($user, $this->session->getUser());
    }

    public function testCanSetAndGetPostToken(): void
    {
        $this->assertNull($this->session->getPostToken());
        $this->session->setPostToken('token');
        $this->assertSame('token', $this->session->getPostToken());
    }

    public function testCanSetAndGetGateway(): void
    {
        $this->assertNull($this->session->getGateway());
        $this->session->setGateway('gw');
        $this->assertSame('gw', $this->session->getGateway());
    }

    public function testCanCheckIfTheSessionHasAUser(): void
    {
        $this->assertFalse($this->session->hasUser());
        $_SESSION['user'] = 'some value';
        $this->assertFalse($this->session->hasUser());
        $_SESSION['user'] = new User('id', 'mail@example.com', 'name', []);
        $this->assertTrue($this->session->hasUser());
    }
}
