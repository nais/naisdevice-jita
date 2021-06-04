<?php declare(strict_types=1);
namespace Naisdevice\Jita\Controllers;

use Naisdevice\Jita\SamlResponseValidator;
use Naisdevice\Jita\Session;
use Naisdevice\Jita\Session\User;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\StreamInterface;

/**
 * @coversDefaultClass Naisdevice\Jita\Controllers\SamlController
 */
class SamlControllerTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::logout
     */
    public function testCanLogOut(): void
    {
        $session = $this->createMock(Session::class);
        $session
            ->expects($this->once())
            ->method('destroy');

        $response2 = $this->createMock(Response::class);
        $response2
            ->expects($this->once())
            ->method('withStatus')
            ->with(302)
            ->willReturn($this->createMock(Response::class));

        $response1 = $this->createMock(Response::class);
        $response1
            ->expects($this->once())
            ->method('withHeader')
            ->with('Location', 'logout-url')
            ->willReturn($response2);

        (new SamlController($session, $this->createMock(SamlResponseValidator::class), 'logout-url'))->logout(
            $this->createMock(Request::class),
            $response1,
        );
    }

    /**
     * @covers ::acs
     */
    public function testFailsWhenSessionAlreadyExists(): void
    {
        $body = $this->createMock(StreamInterface::class);
        $body
            ->expects($this->once())
            ->method('write')
            ->with('User has already been authenticated');

        $response1 = $this->createConfiguredMock(Response::class, ['getBody' => $body]);
        $response1
            ->expects($this->once())
            ->method('withStatus')
            ->with(400)
            ->willReturn($this->createMock(Response::class));

        $controller = new SamlController(
            $this->createConfiguredMock(Session::class, ['hasUser' => true]),
            $this->createMock(SamlResponseValidator::class),
            'logout-url',
        );
        $controller->acs(
            $this->createMock(Request::class),
            $response1,
        );
    }

    /**
     * @covers ::acs
     */
    public function testFailsWhenRequestIsMissingSamlResponse(): void
    {
        $body = $this->createMock(StreamInterface::class);
        $body
            ->expects($this->once())
            ->method('write')
            ->with('Missing SAML response');

        $response1 = $this->createConfiguredMock(Response::class, ['getBody' => $body]);
        $response1
            ->expects($this->once())
            ->method('withStatus')
            ->with(400)
            ->willReturn($this->createMock(Response::class));

        $controller = new SamlController(
            $this->createConfiguredMock(Session::class, ['hasUser' => false]),
            $this->createMock(SamlResponseValidator::class),
            'logout-url',
        );
        $controller->acs(
            $this->createConfiguredMock(Request::class, [
                'getParsedBody' => [],
            ]),
            $response1,
        );
    }

    /**
     * @covers ::acs
     */
    public function testFailsWhenSamlResponseIsNotCorrectlyEncoded(): void
    {
        $body = $this->createMock(StreamInterface::class);
        $body
            ->expects($this->once())
            ->method('write')
            ->with('Value is not properly base64 encoded');

        $response1 = $this->createConfiguredMock(Response::class, ['getBody' => $body]);
        $response1
            ->expects($this->once())
            ->method('withStatus')
            ->with(400)
            ->willReturn($this->createMock(Response::class));

        $controller = new SamlController(
            $this->createConfiguredMock(Session::class, ['hasUser' => false]),
            $this->createMock(SamlResponseValidator::class),
            'logout-url',
        );
        $controller->acs(
            $this->createConfiguredMock(Request::class, [
                'getParsedBody' => [
                    'SAMLResponse' => '<foobar>',
                ],
            ]),
            $response1,
        );
    }

    /**
     * @covers ::acs
     */
    public function testFailsWhenSamlResponseIsInvalid(): void
    {
        $body = $this->createMock(StreamInterface::class);
        $body
            ->expects($this->once())
            ->method('write')
            ->with('Invalid SAML response');

        $response1 = $this->createConfiguredMock(Response::class, ['getBody' => $body]);
        $response1
            ->expects($this->once())
            ->method('withStatus')
            ->with(400)
            ->willReturn($this->createMock(Response::class));

        $validator = $this->createMock(SamlResponseValidator::class);
        $validator
            ->expects($this->once())
            ->method('validate')
            ->with('some xml')
            ->willReturn(false);

        $controller = new SamlController(
            $this->createConfiguredMock(Session::class, ['hasUser' => false]),
            $validator,
            'logout-url',
        );
        $controller->acs(
            $this->createConfiguredMock(Request::class, [
                'getParsedBody' => [
                    'SAMLResponse' => base64_encode('some xml'),
                ],
            ]),
            $response1,
        );
    }

    /**
     * @covers ::acs
     * @covers ::getUserFromSamlResponse
     */
    public function testFailsWhenSamlResponseIsMissingObjectId(): void
    {
        $body = $this->createMock(StreamInterface::class);
        $body
            ->expects($this->once())
            ->method('write')
            ->with('Missing objectidentifier claim.');

        $response1 = $this->createConfiguredMock(Response::class, ['getBody' => $body]);
        $response1
            ->expects($this->once())
            ->method('withStatus')
            ->with(400)
            ->willReturn($this->createMock(Response::class));

        $controller = new SamlController(
            $this->createConfiguredMock(Session::class, ['hasUser' => false]),
            $this->createConfiguredMock(SamlResponseValidator::class, ['validate' => true]),
            'logout-url',
        );
        $controller->acs(
            $this->createConfiguredMock(Request::class, [
                'getParsedBody' => [
                    'SAMLResponse' => base64_encode((string) file_get_contents(__DIR__ . '/../fixtures/response-with-missing-object-id.xml')),
                ],
            ]),
            $response1,
        );
    }

    /**
     * @covers ::acs
     * @covers ::getUserFromSamlResponse
     */
    public function testFailsWhenSamlResponseIsMissingGivenNameObjectId(): void
    {
        $body = $this->createMock(StreamInterface::class);
        $body
            ->expects($this->once())
            ->method('write')
            ->with('Missing givenname claim.');

        $response1 = $this->createConfiguredMock(Response::class, ['getBody' => $body]);
        $response1
            ->expects($this->once())
            ->method('withStatus')
            ->with(400)
            ->willReturn($this->createMock(Response::class));

        $controller = new SamlController(
            $this->createConfiguredMock(Session::class, ['hasUser' => false]),
            $this->createConfiguredMock(SamlResponseValidator::class, ['validate' => true]),
            'logout-url',
        );
        $controller->acs(
            $this->createConfiguredMock(Request::class, [
                'getParsedBody' => [
                    'SAMLResponse' => base64_encode((string) file_get_contents(__DIR__ . '/../fixtures/response-with-missing-given-name.xml')),
                ],
            ]),
            $response1,
        );
    }

    /**
     * @covers ::acs
     * @covers ::getUserFromSamlResponse
     */
    public function testFailsWhenSamlResponseIsMissingEmailAddress(): void
    {
        $body = $this->createMock(StreamInterface::class);
        $body
            ->expects($this->once())
            ->method('write')
            ->with('Missing emailaddress claim.');

        $response1 = $this->createConfiguredMock(Response::class, ['getBody' => $body]);
        $response1
            ->expects($this->once())
            ->method('withStatus')
            ->with(400)
            ->willReturn($this->createMock(Response::class));

        $controller = new SamlController(
            $this->createConfiguredMock(Session::class, ['hasUser' => false]),
            $this->createConfiguredMock(SamlResponseValidator::class, ['validate' => true]),
            'logout-url',
        );
        $controller->acs(
            $this->createConfiguredMock(Request::class, [
                'getParsedBody' => [
                    'SAMLResponse' => base64_encode((string) file_get_contents(__DIR__ . '/../fixtures/response-with-missing-email-address.xml')),
                ],
            ]),
            $response1,
        );
    }

    /**
     * @covers ::acs
     * @covers ::getUserFromSamlResponse
     */
    public function testCanSuccessfullySetUserInSession(): void
    {
        $response2 = $this->createMock(Response::class);
        $response2
            ->expects($this->once())
            ->method('withStatus')
            ->with(302)
            ->willReturn($this->createMock(Response::class));

        $response1 = $this->createMock(Response::class);
        $response1
            ->expects($this->once())
            ->method('withHeader')
            ->with('Location', '/')
            ->willReturn($response2);

        $session = $this->createConfiguredMock(Session::class, ['hasUser' => false]);
        $session
            ->expects($this->once())
            ->method('setUser')
            ->with($this->callback(function (User $user): bool {
                return
                    'user-id' === $user->getObjectId() &&
                    'user@nav.no' === $user->getEmail() &&
                    'Givenname' === $user->getName() &&
                    ['id1', 'id2'] === $user->getGroups();
            }));

        $controller = new SamlController(
            $session,
            $this->createConfiguredMock(SamlResponseValidator::class, ['validate' => true]),
            'logout-url',
        );
        $controller->acs(
            $this->createConfiguredMock(Request::class, [
                'getParsedBody' => [
                    'SAMLResponse' => base64_encode((string) file_get_contents(__DIR__ . '/../fixtures/response.xml')),
                ],
            ]),
            $response1,
        );
    }

    /**
     * @covers ::acs
     * @covers ::getUserFromSamlResponse
     */
    public function testFailsWhenSamlResponseIsNotValid(): void
    {
        $body = $this->createMock(StreamInterface::class);
        $body
            ->expects($this->once())
            ->method('write')
            ->with('Unable to load XML.');

        $response1 = $this->createConfiguredMock(Response::class, ['getBody' => $body]);
        $response1
            ->expects($this->once())
            ->method('withStatus')
            ->with(400)
            ->willReturn($this->createMock(Response::class));

        $controller = new SamlController(
            $this->createConfiguredMock(Session::class, ['hasUser' => false]),
            $this->createConfiguredMock(SamlResponseValidator::class, ['validate' => true]),
            'logout-url',
        );
        $controller->acs(
            $this->createConfiguredMock(Request::class, [
                'getParsedBody' => [
                    'SAMLResponse' => base64_encode('some string'),
                ],
            ]),
            $response1,
        );
    }
}
