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
        'gw-1' => 'gw-1',
        'gw-2' => 'gw-2',
        'gw-3' => 'gw-3',
        'gw-4' => 'gw-4',
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
