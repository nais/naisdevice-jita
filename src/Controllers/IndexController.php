<?php declare(strict_types=1);

namespace Naisdevice\Jita\Controllers;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Types\Types;
use Naisdevice\Jita\FlashMessage;
use Naisdevice\Jita\SamlRequest;
use Naisdevice\Jita\Session;
use Prometheus\CollectorRegistry;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Flash\Messages;
use Slim\Views\Twig;

class IndexController
{
    private Twig $view;
    private Session $session;
    private Connection $connection;
    private Messages $flashMessages;
    private string $loginUrl;
    private string $entityId;
    private CollectorRegistry $collectorRegistry;

    public function __construct(Twig $view, Session $session, Connection $connection, Messages $flashMessages, string $loginUrl, string $entityId, CollectorRegistry $registry)
    {
        $this->view              = $view;
        $this->session           = $session;
        $this->connection        = $connection;
        $this->flashMessages     = $flashMessages;
        $this->loginUrl          = $loginUrl;
        $this->entityId          = $entityId;
        $this->collectorRegistry = $registry;
    }

    /**
     * Main page
     *
     * @param Request $request
     * @param Response $response
     * @throws RuntimeException
     * @return Response
     */
    public function index(Request $request, Response $response): Response
    {
        /** @var array<string,mixed> */
        $query   = $request->getQueryParams();
        $gateway = array_key_exists('gateway', $query) ? (string) $query['gateway'] : $this->session->getGateway();
        $user    = $this->session->getUser();

        if (null === $gateway || '' === $gateway) {
            throw new RuntimeException('Missing gateway, please click on the gateway you want access to in the naisdevice application.', 400);
        }

        $this->session->setGateway($gateway);

        if (null === $user) {
            $samlRequest = new SamlRequest($this->entityId);

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

        $now = new DateTime('now', new DateTimeZone('UTC'));

        try {
            $requests = $this->connection->fetchAllAssociative(<<<SQL
                SELECT id, created, gateway, reason, expires, revoked
                FROM requests
                WHERE user_id = :user_id
                ORDER BY id DESC
                LIMIT 10
            SQL, [
                'user_id' => $user->getObjectId(),
            ]);
        } catch (DriverException $e) {
            throw new RuntimeException('Unable to fetch previous access requests.', 500, $e);
        }

        return $this->view->render($response, 'index.html', [
            'hasActiveAccessRequest' => $this->userHasAccessToGateway($user->getObjectId(), $gateway),
            'postToken'              => $postToken,
            'user'                   => $user,
            'flashMessages'          => $this->flashMessages->getMessage(FlashMessage::class),
            'gateway'                => $gateway,
            'requests'               => array_map(fn (array $r): array => [
                'id'         => $r['id'],
                'created'    => $r['created'],
                'gateway'    => $r['gateway'],
                'reason'     => $r['reason'],
                'expires'    => $r['expires'],
                'revoked'    => $r['revoked'],
                'hasExpired' => new DateTime((string) $r['expires'], new DateTimeZone('UTC')) < $now,
                'isRevoked'  => null !== $r['revoked'],
            ], $requests),
        ]);
    }

    /**
     * Create an access request
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function createRequest(Request $request, Response $response): Response
    {
        $user      = $this->session->getUser();
        /** @var array{postToken?:string,gateway?:string,reason?:string,duration?:int} */
        $params    = $request->getParsedBody() ?: [];
        $postToken = array_key_exists('postToken', $params) ? trim((string) $params['postToken']) : '';
        $gateway   = array_key_exists('gateway', $params) ? trim((string) $params['gateway']) : '';
        $reason    = array_key_exists('reason', $params) ? trim((string) $params['reason']) : '';
        $duration  = array_key_exists('duration', $params) ? (int) $params['duration'] : 0;

        if (null === $user) {
            $this->session->end();
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
        } elseif ($this->userHasAccessToGateway($user->getObjectId(), $gateway)) {
            $this->flashMessages->addMessage(
                FlashMessage::class,
                new FlashMessage('You already have a valid access request for this gateway.', true),
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

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        try {
            $this->collectorRegistry
                ->getOrRegisterCounter(MetricsController::NS, 'request_counter', 'number of jita requests', ['duration', 'gateway'])
                ->incBy(1, [(string) $duration, $gateway]);

            $this->connection->insert('requests', [
                'created' => $now,
                'user_id' => $user->getObjectId(),
                'gateway' => $gateway,
                'reason'  => $reason,
                'expires' => $now->add(new DateInterval(sprintf('PT%dH', $duration))),
            ], [
                Types::DATETIMETZ_IMMUTABLE,
                Types::STRING,
                Types::STRING,
                Types::STRING,
                Types::DATETIMETZ_IMMUTABLE,
            ]);
        } catch (DriverException $e) {
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
            new FlashMessage('The request has been registered. The gateway will connect shortly.'),
        );

        return $response
            ->withStatus(302)
            ->withHeader('Location', '/');
    }

    /**
     * Create an access request
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function revokeAccess(Request $request, Response $response): Response
    {
        $user      = $this->session->getUser();
        /** @var array{postToken?:string,requestId?:string} */
        $params    = $request->getParsedBody() ?: [];
        $postToken = array_key_exists('postToken', $params) ? trim((string) $params['postToken']) : '';
        $requestId = array_key_exists('requestId', $params) ? trim((string) $params['requestId']) : '';
        $error     = false;

        if (null === $user) {
            $this->session->end();
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/');
        }

        if ($postToken !== $this->session->getPostToken()) {
            $this->flashMessages->addMessage(
                FlashMessage::class,
                new FlashMessage('Incorrect POST token.', true),
            );
            $error = true;
        }

        if ('' === $requestId) {
            $this->flashMessages->addMessage(
                FlashMessage::class,
                new FlashMessage('Missing request ID.', true),
            );
            $error = true;
        }

        if ($error) {
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/');
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        try {
            $affectedRows = $this->connection->update('requests', [
                'revoked' => $now,
            ], [
                'id'      => (int) $requestId,
                'user_id' => $user->getObjectId(),
                'revoked' => null,
            ], [
                'id'      => Types::INTEGER,
                'user_id' => Types::STRING,
                'revoked' => Types::DATETIMETZ_IMMUTABLE,
            ]);

            if (1 === $affectedRows) {
                $this->flashMessages->addMessage(
                    FlashMessage::class,
                    new FlashMessage('Access request has been revoked.'),
                );
            } else {
                $this->flashMessages->addMessage(
                    FlashMessage::class,
                    new FlashMessage('Unable to revoke access request.', true),
                );
            }
        } catch (DriverException $e) {
            $this->flashMessages->addMessage(
                FlashMessage::class,
                new FlashMessage('Database error.', true),
            );
        }

        return $response
            ->withStatus(302)
            ->withHeader('Location', '/');
    }

    /**
     * Check if the current user has a valid access request to a specific gateway
     *
     * @param string $userId
     * @param string $gateway
     * @return bool
     */
    private function userHasAccessToGateway(string $userId, string $gateway): bool
    {
        return false !== $this->connection->fetchOne(<<<SQL
            SELECT id
            FROM requests
            WHERE user_id = :user_id
            AND gateway = :gateway
            AND expires > NOW()
            AND revoked IS NULL
        SQL, [
            'user_id' => $userId,
            'gateway' => $gateway,
        ]);
    }
}
