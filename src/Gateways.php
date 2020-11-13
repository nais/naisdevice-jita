<?php declare(strict_types=1);
namespace Naisdevice\Jita;

class Gateways {
    /**
     * All available gateways
     *
     * @todo Replace with actual gateways
     * @var array<string,string>
     */
    private array $gateways = [
        'nais-device-gw-k8s-labs'  => 'nais-device-gw-k8s-labs',
        'nais-device-gw-k8s-dev'   => 'nais-device-gw-k8s-dev',
        'nais-device-gw-k8s-prod'  => 'nais-device-gw-k8s-prod',
        'nais-device-gw-k8s-ci'    => 'nais-device-gw-k8s-ci',
        'nav-utvikler'             => 'nav-utvikler',
        'naisdevice-vdi'           => 'naisdevice-vdi',
        'nais-device-k8s-onprem'   => 'nais-device-k8s-onprem',
        'nais-device-k8s-kubeflow' => 'nais-device-k8s-kubeflow',
        'naisdevice-postgres-dev'  => 'naisdevice-postgres-dev',
        'naisdevice-gosys'         => 'naisdevice-gosys',
        'oracle-dev'               => 'oracle-dev',
        'kafka-prod'               => 'kafka-prod',
        'kafka-dev'                => 'kafka-dev',
    ];

    /**
     * Get gateways available for a specific user
     *
     * @todo Use $userId to filter the gateways
     * @param string $userId
     * @return array<string,string>
     */
    public function getUserGateways(string $userId) : array {
        return $this->gateways;
    }
}
