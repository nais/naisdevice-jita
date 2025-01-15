<?php declare(strict_types=1);

namespace Naisdevice\Jita\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\MetricFamilySamples;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

#[CoversClass(MetricsController::class)]
class MetricsControllerTest extends TestCase
{
    public function testMetrics(): void
    {
        $registry = $this->createMock(CollectorRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getMetricFamilySamples')
            ->willReturn([$this->createMock(MetricFamilySamples::class)]);

        $body = $this->createMock(StreamInterface::class);
        $body
            ->expects($this->once())
            ->method('write');

        $expectedResponse = $this->createMock(ResponseInterface::class);

        $response = $this->createConfiguredMock(ResponseInterface::class, [
            'getBody' => $body,
        ]);
        $response
            ->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'text/plain')
            ->willReturn($expectedResponse);

        $this->assertSame($expectedResponse, (new MetricsController($registry))->metrics(
            $this->createMock(ServerRequestInterface::class),
            $response,
        ));
    }
}
