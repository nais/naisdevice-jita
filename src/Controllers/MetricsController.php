<?php declare(strict_types=1);

namespace Naisdevice\Jita\Controllers;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MetricsController
{
    public const NS = 'jita';

    private CollectorRegistry $collectorRegistry;

    public function __construct(CollectorRegistry $registry)
    {
        $this->collectorRegistry = $registry;
    }

    public function metrics(Request $request, Response $response): Response
    {
        $responseBody = $response->getBody();
        $responseBody->write((new RenderTextFormat())->render($this->collectorRegistry->getMetricFamilySamples()));

        return $response->withHeader('Content-Type', 'text/plain');
    }
}
