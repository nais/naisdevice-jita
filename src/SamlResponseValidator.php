<?php declare(strict_types=1);

namespace Naisdevice\Jita;

use OneLogin\Saml2\Utils;

/**
 * @codeCoverageIgnore
 */
class SamlResponseValidator
{
    private string $certificate;

    /**
     * Class constructor
     *
     * @param string $certificate
     */
    public function __construct(string $certificate)
    {
        $this->certificate = $certificate;
    }

    /**
     * Validate the response
     *
     * @param string $responseXml The complete SAML XML response
     * @return bool
     */
    public function validate(string $responseXml): bool
    {
        return Utils::validateSign($responseXml, $this->certificate);
    }
}
