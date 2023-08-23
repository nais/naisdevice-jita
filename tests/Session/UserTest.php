<?php declare(strict_types=1);

namespace Naisdevice\Jita\Session;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Naisdevice\Jita\Session\User
 */
class UserTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::getObjectId
     * @covers ::getEmail
     * @covers ::getName
     * @covers ::getGroups
     */
    public function testCanGetValues(): void
    {
        $user = new User('id', 'user@example.com', 'name', ['id1', 'id2']);
        $this->assertSame('id', $user->getObjectId());
        $this->assertSame('user@example.com', $user->getEmail());
        $this->assertSame('name', $user->getName());
        $this->assertSame(['id1', 'id2'], $user->getGroups());
    }
}
