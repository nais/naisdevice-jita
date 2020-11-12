<?php declare(strict_types=1);
namespace Naisdevice\Jita\Controllers;

use Doctrine\DBAL\{
    Connection,
    Exception\DriverException,
};
use Naisdevice\Jita\{
    FlashMessage,
    Gateways,
    SamlRequest,
    Session,
};
use Psr\Http\Message\{
    ResponseInterface as Response,
    ServerRequestInterface as Request,
};
use Slim\{
    Flash\Messages,
    Views\Twig,
};

class IndexController {
    private Twig $view;
    private Session $session;
    private Connection $connection;
    private Messages $flashMessages;
    private string $loginUrl;
    private string $entityId;
    private Gateways $gateways;

    public function __construct(Twig $view, Session $session, Connection $connection, Messages $flashMessages, string $loginUrl, string $entityId, Gateways $gateways) {
        $this->view          = $view;
        $this->session       = $session;
        $this->connection    = $connection;
        $this->flashMessages = $flashMessages;
        $this->loginUrl      = $loginUrl;
        $this->entityId      = $entityId;
        $this->gateways      = $gateways;
    }

    public function index(Request $request, Response $response) : Response {
        /** @var array<string,mixed> */
        $query   = $request->getQueryParams();
        $gateway = array_key_exists('gateway', $query) ? (string) $query['gateway'] : $this->session->getGateway();
        $user    = $this->session->getUser();

        $this->session->setGateway($gateway);

        if (null === $user) {
            $samlRequest = new SamlRequest($this->entityId);

            $this->session->setSamlRequestId($samlRequest->getId());

            return $response
                ->withStatus(302)
                ->withHeader('Location', sprintf(
                    '%s?SAMLRequest=%s',
                    $this->loginUrl,
                    urlencode((string) $samlRequest),
                ));
        }

        $postToken = uniqid('', true);
        $this->session->setPostToken($postToken);

        return $this->view->render($response, 'index.html', [
            'postToken'       => $postToken,
            'user'            => $user,
            'flashMessages'   => $this->flashMessages->getMessage(FlashMessage::class),
            'selectedGateway' => $gateway,
            'gateways'        => $this->gateways->getUserGateways($user->getObjectId()),
            'requests' => array_map(fn(array $r) : array => [
                'gateway'       => $r['gateway'],
                'reason'        => $r['reason'],
                'expires'       => $r['expires'],
                'hasExpired'    => $r['expires'] < time(),
            ], $this->connection->fetchAllAssociative(
                'SELECT gateway, reason, expires FROM requests WHERE user_id = :user_id ORDER BY id DESC LIMIT 10',
                ['user_id' => $user->getObjectId()],
            )),
        ]);
    }

    public function createRequest(Request $request, Response $response) : Response {
        $user      = $this->session->getUser();
        /** @var array{postToken?:string,gateway?:string,reason?:string,duration?:int} */
        $params    = $request->getParsedBody() ?: [];
        $postToken = array_key_exists('postToken', $params) ? trim((string) $params['postToken']) : '';
        $gateway   = array_key_exists('gateway', $params) ? trim((string) $params['gateway']) : '';
        $reason    = array_key_exists('reason', $params) ? trim((string) $params['reason']) : '';
        $duration  = array_key_exists('duration', $params) ? (int) $params['duration'] : 0;

        if (null === $user) {
            $this->session->destroy();
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/');
        }

        $error = false;

        if ($postToken !== $this->session->getPostToken()) {
            $this->flashMessages->addMessage(
                FlashMessage::class,
                new FlashMessage('Incorrect POST token.', true),
            );
            $error = true;
        }

        if ('' === $reason) {
            $this->flashMessages->addMessage(
                FlashMessage::class,
                new FlashMessage('Specify a reason for the access request.', true),
            );
            $error = true;
        }

        if ('' === $gateway) {
            $this->flashMessages->addMessage(
                FlashMessage::class,
                new FlashMessage('Specify a gateway to request access to.', true),
            );
            $error = true;
        }

        if (1 > $duration || 8 < $duration) {
            $this->flashMessages->addMessage(
                FlashMessage::class,
                new FlashMessage('Invalid duration value. Must be between 1 and 8, inclusive.', true),
            );
            $error = true;
        }

        if ($error) {
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/');
        }

        $now = time();

        try {
            $this->connection->insert('requests', [
                'created'  => $now,
                'user_id'  => $user->getObjectId(),
                'gateway'  => $gateway,
                'reason'   => $reason,
                'expires'  => $now + ($duration * 3600),
            ]);
        } catch (DriverException $e){
            $this->flashMessages->addMessage(
                FlashMessage::class,
                new FlashMessage('Database error.', true),
            );

            return $response
                ->withStatus(302)
                ->withHeader('Location', '/');
        }

        $this->flashMessages->addMessage(
            FlashMessage::class,
            new FlashMessage(sprintf('The request has been registered. Re-connect naisdevice to allow connection to the %s gateway.', $gateway)),
        );

        $this->session->setGateway(null);

        return $response
            ->withStatus(302)
            ->withHeader('Location', '/');
    }
}
