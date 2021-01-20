<?php declare(strict_types=1);
namespace Naisdevice\Jita;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Naisdevice\Jita\FlashMessage
 */
class FlashMessageTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::isError
     * @covers ::getMessage
     */
    public function testCanGetValues(): void
    {
        $message = new FlashMessage('some message');
        $this->assertSame('some message', $message->getMessage());
        $this->assertFalse($message->isError());

        $message = new FlashMessage('some message', true);
        $this->assertSame('some message', $message->getMessage());
        $this->assertTrue($message->isError());
    }
}
