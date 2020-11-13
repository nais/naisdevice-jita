<?php declare(strict_types=1);
namespace Naisdevice\Jita;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Naisdevice\Jita\Gateways
 */
class GatewaysTest extends TestCase {
    /**
     * @covers ::getUserGateways
     */
    public function testCanGetGateways() : void {
        $this->assertSame(
            (new Gateways())->getUserGateways('user1'),
            (new Gateways())->getUserGateways('user2'),
        );
    }
}
