<?php declare(strict_types=1);
namespace Naisdevice\Jita;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Naisdevice\Jita\SessionHandler
 */
class SessionHandlerTest extends TestCase
{
    /** @var Connection&MockObject */
    private Connection $connection;
    private SessionHandler $handler;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->handler = new SessionHandler($this->connection);
    }

    /**
     * @covers ::open
     */
    public function testOpenAlwaysReturnsTrue(): void
    {
        $this->assertTrue($this->handler->open('path', 'name'));
    }

    /**
     * @covers ::close
     */
    public function testCloseAlwaysReturnsTrue(): void
    {
        $this->assertTrue($this->handler->close());
    }

    /**
     * @covers ::__construct
     * @covers ::read
     * @covers ::hasExpired
     * @covers ::getSessionLifetime
     */
    public function testCanReadSessionData(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with($this->isType('string'), ['id' => 'session id'])
            ->willReturn(['data' => base64_encode('some data'), 'last_activity' => time()]);

        $this->assertSame('some data', $this->handler->read('session id'));
    }

    /**
     * @covers ::read
     * @covers ::hasExpired
     * @covers ::getSessionLifetime
     */
    public function testReturnsEmptyStringWhenSessionHasExpired(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with($this->isType('string'), ['id' => 'session id'])
            ->willReturn(['data' => base64_encode('some data'), 'last_activity' => time() - 60 * 60 * 24]);

        $this->assertSame('', $this->handler->read('session id'));
    }

    /**
     * @covers ::read
     * @covers ::hasExpired
     * @covers ::getSessionLifetime
     */
    public function testReturnsEmptyStringWhenNoSessionIsFound(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with($this->isType('string'), ['id' => 'session id'])
            ->willReturn(false);

        $this->assertSame('', $this->handler->read('session id'));
    }

    /**
     * @covers ::write
     */
    public function testCanWriteSessionData(): void
    {
        $data = 'some data';
        $encodedData = base64_encode($data);

        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with($this->isType('string'), $this->callback(function (array $params) use ($encodedData): bool {
                $this->assertSame('session id', $params['id']);
                $this->assertSame($encodedData, $params['data']);
                $this->assertEqualsWithDelta(time(), $params['last_activity'], 10);
                return true;
            }));

        $this->assertTrue($this->handler->write('session id', $data));
    }

    /**
     * @covers ::destroy
     */
    public function testCanDestroySession(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with($this->isType('string'), ['id' => 'session id']);

        $this->assertTrue($this->handler->destroy('session id'));
    }

    /**
     * @covers ::gc
     */
    public function testCanRunGc(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with($this->isType('string'), $this->callback(function (array $params): bool {
                $this->assertEqualsWithDelta(time() - 3600, $params['lifetime'], 10);
                return true;
            }))
            ->willReturn(123);

        $this->assertSame(123, $this->handler->gc(3600));
    }
}
