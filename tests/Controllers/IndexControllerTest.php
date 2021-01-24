<?php declare(strict_types=1);
namespace Naisdevice\Jita\Controllers;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Naisdevice\Jita\FlashMessage;
use Naisdevice\Jita\Session;
use Naisdevice\Jita\Session\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use RuntimeException;
use Slim\Flash\Messages;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Views\Twig;

/**
 * @coversDefaultClass Naisdevice\Jita\Controllers\IndexController
 */
class IndexControllerTest extends TestCase
{
    /** @var Request&MockObject */
    private Request $request;

    /** @var Response&MockObject */
    private Response $response;

    /** @var Twig&MockObject */
    private Twig $view;

    /** @var Session&MockObject */
    private Session $session;

    /** @var Connection&MockObject */
    private Connection $connection;

    /** @var Messages&MockObject */
    private Messages $flashMessages;

    /** @var CollectorRegistry&MockObject */
    private CollectorRegistry $collectorRegistry;

    private IndexController $controller;
    private string $loginUrl = 'https://loginurl';
    private string $entityId = 'entity-id';

    protected function setUp(): void
    {
        $this->request           = $this->createMock(Request::class);
        $this->response          = $this->createMock(Response::class);

        $this->view              = $this->createMock(Twig::class);
        $this->session           = $this->createMock(Session::class);
        $this->connection        = $this->createMock(Connection::class);
        $this->flashMessages     = $this->createMock(Messages::class);
        $this->collectorRegistry = $this->createMock(CollectorRegistry::class);

        $this->controller = new IndexController(
            $this->view,
            $this->session,
            $this->connection,
            $this->flashMessages,
            $this->loginUrl,
            $this->entityId,
            $this->collectorRegistry
        );
    }

    /**
     * @covers ::index
     * @covers ::__construct
     */
    public function testIndexThrowsExceptionOnMissingGateway(): void
    {
        $this->request
            ->expects($this->once())
            ->method('getQueryParams')
            ->willReturn([]);
        $this->session
            ->expects($this->once())
            ->method('getGateway')
            ->willReturn(null);

        $this->expectExceptionObject(new RuntimeException('Missing gateway'));
        $this->controller->index($this->request, $this->response);
    }

    /**
     * @covers ::index
     */
    public function testRedirectsIndexOnMissingUser(): void
    {
        $redirectResponse = $this->createMock(Response::class);
        $statusResponse = $this->createMock(Response::class);
        $statusResponse
            ->expects($this->once())
            ->method('withHeader')
            ->with('Location', $this->stringStartsWith(sprintf('%s?SAMLRequest=', $this->loginUrl)))
            ->willReturn($redirectResponse);

        $this->response
            ->expects($this->once())
            ->method('withStatus')
            ->with(302)
            ->willReturn($statusResponse);

        $this->request
            ->expects($this->once())
            ->method('getQueryParams')
            ->willReturn(['gateway' => 'some-gw']);

        $this->session
            ->expects($this->once())
            ->method('setGateway')
            ->with('some-gw');

        $this->assertSame($redirectResponse, $this->controller->index($this->request, $this->response));
    }

    /**
     * @covers ::index
     * @covers ::userHasAccessToGateway
     */
    public function testCanRenderIndexPage(): void
    {
        $response = $this->createMock(Response::class);
        $request = $this->createConfiguredMock(Request::class, [
            'getQueryParams' => [
                'gateway' => 'some-gw',
            ],
        ]);

        $user = $this->createConfiguredMock(User::class, [
            'getObjectId' => 'user-object-id',
        ]);

        $this->session
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->session
            ->expects($this->once())
            ->method('setGateway')
            ->with('some-gw');

        $this->session
            ->expects($this->once())
            ->method('setPostToken')
            ->with($this->isType('string'));

        $result = $this->createMock(Response::class);

        $this->view
            ->expects($this->once())
            ->method('render')
            ->with($response, 'index.html', $this->callback(function (array $data) use ($user): bool {
                return
                    false === $data['hasActiveAccessRequest'] &&
                    is_string($data['postToken']) &&
                    $user === $data['user'] &&
                    ['some message'] === $data['flashMessages'] &&
                    'some-gw' === $data['gateway'] &&
                    [
                        [
                            'id' => 3,
                            'created' => '2221-01-23 10:34:03+00',
                            'gateway' => 'some-third-gw',
                            'reason' => 'some reason',
                            'expires' => '2221-01-23 11:34:03+00',
                            'revoked' => null,
                            'hasExpired' => false,
                            'isRevoked' => false,
                        ],
                        [
                            'id' => 2,
                            'created' => '2021-01-22 10:34:03+00',
                            'gateway' => 'some-second-gw',
                            'reason' => 'some reason',
                            'expires' => '2021-01-22 11:34:03+00',
                            'revoked' => null,
                            'hasExpired' => true,
                            'isRevoked' => false,
                        ],
                        [
                            'id' => 1,
                            'created' => '2021-01-23 10:34:03+00',
                            'gateway' => 'some-gw',
                            'reason' => 'some reason',
                            'expires' => '2021-01-23 16:34:03+00',
                            'revoked' => '2021-01-23 14:34:03+00',
                            'hasExpired' => true,
                            'isRevoked' => true,
                        ],
                    ] === $data['requests'];
            }))
            ->willReturn($result);

        $this->flashMessages
            ->expects($this->once())
            ->method('getMessage')
            ->with(FlashMessage::class)
            ->willReturn(['some message']);

        $this->connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->isType('string'), ['user_id' => 'user-object-id'])
            ->willReturn([
                [
                    'id' => 3,
                    'created' => '2221-01-23 10:34:03+00',
                    'gateway' => 'some-third-gw',
                    'reason' => 'some reason',
                    'expires' => '2221-01-23 11:34:03+00',
                    'revoked' => null,
                ],
                [
                    'id' => 2,
                    'created' => '2021-01-22 10:34:03+00',
                    'gateway' => 'some-second-gw',
                    'reason' => 'some reason',
                    'expires' => '2021-01-22 11:34:03+00',
                    'revoked' => null,
                ],
                [
                    'id' => 1,
                    'created' => '2021-01-23 10:34:03+00',
                    'gateway' => 'some-gw',
                    'reason' => 'some reason',
                    'expires' => '2021-01-23 16:34:03+00',
                    'revoked' => '2021-01-23 14:34:03+00',
                ],
            ]);

        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with($this->isType('string'), ['user_id' => 'user-object-id', 'gateway' => 'some-gw'])
            ->willReturn(false);

        $this->assertSame($result, $this->controller->index($request, $response));
    }

    /**
     * @covers ::createRequest
     */
    public function testCreateRequestRedirectsWhenThereIsNoUser(): void
    {
        $this->session
            ->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->session
            ->expects($this->once())
            ->method('destroy');

        $redirectResponse = $this->createMock(Response::class);

        $statusResponse = $this->createMock(Response::class);
        $statusResponse
            ->expects($this->once())
            ->method('withHeader')
            ->with('Location', '/')
            ->willReturn($redirectResponse);

        $this->response
            ->expects($this->once())
            ->method('withStatus')
            ->with(302)
            ->willReturn($statusResponse);

        $this->connection
            ->expects($this->never())
            ->method('insert');

        $this->assertSame($redirectResponse, $this->controller->createRequest($this->request, $this->response));
    }

    /**
     * @covers ::createRequest
     */
    public function testCreateRequestWillRedirectOnError(): void
    {
        $this->session
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($this->createMock(User::class));

        $this->session
            ->expects($this->never())
            ->method('destroy');

        $redirectResponse = $this->createMock(Response::class);

        $statusResponse = $this->createMock(Response::class);
        $statusResponse
            ->expects($this->once())
            ->method('withHeader')
            ->with('Location', '/')
            ->willReturn($redirectResponse);

        $this->response
            ->expects($this->once())
            ->method('withStatus')
            ->with(302)
            ->willReturn($statusResponse);

        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn([]);

        $this->flashMessages
            ->expects($this->exactly(4))
            ->method('addMessage');

        $this->connection
            ->expects($this->never())
            ->method('insert');

        $this->assertSame($redirectResponse, $this->controller->createRequest($this->request, $this->response));
    }

    /**
     * @covers ::createRequest
     */
    public function testFailsToCreateRequestWhenUserAlreadyHasAccessToGateway(): void
    {
        $this->session
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($this->createConfiguredMock(User::class, [
                'getObjectId' => 'user-object-id',
            ]));

        $this->session
            ->expects($this->once())
            ->method('getPostToken')
            ->willReturn('token');

        $redirectResponse = $this->createMock(Response::class);

        $statusResponse = $this->createMock(Response::class);
        $statusResponse
            ->expects($this->once())
            ->method('withHeader')
            ->with('Location', '/')
            ->willReturn($redirectResponse);

        $this->response
            ->expects($this->once())
            ->method('withStatus')
            ->with(302)
            ->willReturn($statusResponse);

        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn([
                'postToken' => 'token',
                'reason'    => 'some reason',
                'gateway'   => 'some-gw',
                'duration'  => '1',
            ]);

        $this->flashMessages
            ->expects($this->once())
            ->method('addMessage');

        $this->connection
            ->expects($this->never())
            ->method('insert');

        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with($this->isType('string'), ['user_id' => 'user-object-id', 'gateway' => 'some-gw'])
            ->willReturn(['id' => '1']);

        $this->assertSame($redirectResponse, $this->controller->createRequest($this->request, $this->response));
    }

    /**
     * @covers ::createRequest
     */
    public function testCreateRequestCanAddDatabaseFailureMessage(): void
    {
        $this->session
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($this->createConfiguredMock(User::class, [
                'getObjectId' => 'user-object-id',
            ]));

        $this->session
            ->expects($this->once())
            ->method('getPostToken')
            ->willReturn('token');

        $redirectResponse = $this->createMock(Response::class);

        $statusResponse = $this->createMock(Response::class);
        $statusResponse
            ->expects($this->once())
            ->method('withHeader')
            ->with('Location', '/')
            ->willReturn($redirectResponse);

        $this->response
            ->expects($this->once())
            ->method('withStatus')
            ->with(302)
            ->willReturn($statusResponse);

        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn([
                'postToken' => 'token',
                'reason'    => 'some reason',
                'gateway'   => 'some-gw',
                'duration'  => '3', // 3 hours
            ]);

        $this->flashMessages
            ->expects($this->once())
            ->method('addMessage')
            ->with(FlashMessage::class, $this->callback(function (FlashMessage $message) {
                return false === $message->isError();
            }));

        $this->connection
            ->expects($this->once())
            ->method('insert')
            ->with('requests', $this->callback(
                /**
                 * @param array{user_id:string,gateway:string,expires:DateTimeImmutable,created:DateTimeImmutable} $data
                 */
                fn (array $data): bool =>
                    'user-object-id' === $data['user_id'] &&
                    'some-gw' === $data['gateway'] &&
                    $data['expires']->getTimestamp() === ($data['created']->getTimestamp() + 3600 * 3)
            ));

        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with($this->isType('string'), ['user_id' => 'user-object-id', 'gateway' => 'some-gw'])
            ->willReturn(false);

        $this->assertSame($redirectResponse, $this->controller->createRequest($this->request, $this->response));
    }

    /**
     * @covers ::createRequest
     */
    public function testCreateRequest(): void
    {
        $this->session
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($this->createConfiguredMock(User::class, [
                'getObjectId' => 'user-object-id',
            ]));

        $this->session
            ->expects($this->once())
            ->method('getPostToken')
            ->willReturn('token');

        $redirectResponse = $this->createMock(Response::class);

        $statusResponse = $this->createMock(Response::class);
        $statusResponse
            ->expects($this->once())
            ->method('withHeader')
            ->with('Location', '/')
            ->willReturn($redirectResponse);

        $this->response
            ->expects($this->once())
            ->method('withStatus')
            ->with(302)
            ->willReturn($statusResponse);

        $this->request
            ->expects($this->once())
            ->method('getParsedBody')
            ->willReturn([
                'postToken' => 'token',
                'reason'    => 'some reason',
                'gateway'   => 'some-gw',
                'duration'  => '3', // 3 hours
            ]);

        $this->flashMessages
            ->expects($this->once())
            ->method('addMessage')
            ->with(FlashMessage::class, $this->callback(function (FlashMessage $message) {
                return true === $message->isError();
            }));

        $this->connection
            ->expects($this->once())
            ->method('insert')
            ->willThrowException($this->createMock(DriverException::class));

        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with($this->isType('string'), ['user_id' => 'user-object-id', 'gateway' => 'some-gw'])
            ->willReturn(false);

        $this->assertSame($redirectResponse, $this->controller->createRequest($this->request, $this->response));
    }
}
