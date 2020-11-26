<?php declare(strict_types=1);
namespace Naisdevice\Jita;

use Naisdevice\Jita\Session\User;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Naisdevice\Jita\Session
 */
class SessionTest extends TestCase {
    private Session $session;

    public function setUp() : void {
        $_SESSION['user'] = null;
        $this->session = new Session();
    }

    /**
     * @covers ::setUser
     * @covers ::getUser
     */
    public function testCanSetAndGetUser() : void {
        $user = new User('id', 'mail@example.com', 'name', ['id1', 'id2']);
        $this->assertNull($this->session->getUser());
        $this->session->setUser($user);
        $this->assertSame($user, $this->session->getUser());
    }

    /**
     * @covers ::setPostToken
     * @covers ::getPostToken
     */
    public function testCanSetAndGetPostToken() : void {
        $this->assertNull($this->session->getPostToken());
        $this->session->setPostToken('token');
        $this->assertSame('token', $this->session->getPostToken());
    }

    /**
     * @covers ::setGateway
     * @covers ::getGateway
     */
    public function testCanSetAndGetGateway() : void {
        $this->assertNull($this->session->getGateway());
        $this->session->setGateway('gw');
        $this->assertSame('gw', $this->session->getGateway());
    }

    /**
     * @covers ::hasUser
     */
    public function testCanCheckIfTheSessionHasAUser() : void {
        $this->assertFalse($this->session->hasUser());
        $_SESSION['user'] = 'some value';
        $this->assertFalse($this->session->hasUser());
        $_SESSION['user'] = new User('id', 'mail@example.com', 'name', []);
        $this->assertTrue($this->session->hasUser());
    }
}
