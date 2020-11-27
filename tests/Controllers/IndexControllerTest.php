<?php declare(strict_types=1);
namespace Naisdevice\Jita\Controllers;

use Doctrine\DBAL\Connection;
use Naisdevice\Jita\Session;
use PHPUnit\Framework\{
    MockObject\MockObject,
    TestCase,
};
use RuntimeException;
use Slim\{
    Flash\Messages,
    Psr7\Request,
    Psr7\Response,
    Views\Twig,
};

/**
 * @coversDefaultClass Naisdevice\Jita\Controllers\IndexController
 */
class IndexControllerTest extends TestCase {
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

    private IndexController $controller;
    private string $loginUrl = 'https://loginurl';
    private string $entityId = 'entity-id';

    protected function setUp() : void {
        $this->request       = $this->createMock(Request::class);
        $this->response      = $this->createMock(Response::class);
        $this->view          = $this->createMock(Twig::class);
        $this->session       = $this->createMock(Session::class);
        $this->connection    = $this->createMock(Connection::class);
        $this->flashMessages = $this->createMock(Messages::class);
        $this->controller    = new IndexController(
            $this->view,
            $this->session,
            $this->connection,
            $this->flashMessages,
            $this->loginUrl,
            $this->entityId,
        );
    }

    /**
     * @covers ::index
     * @covers ::__construct
     */
    public function testIndexThrowsExceptionOnMissingGateway() : void {
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
    public function testRedirectsIndexOnMissingUser() : void {
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
}
