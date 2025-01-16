<?php declare(strict_types=1);

namespace Naisdevice\Jita;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlashMessage::class)]
class FlashMessageTest extends TestCase
{
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
