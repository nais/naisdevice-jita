<?php declare(strict_types=1);

namespace Naisdevice\Jita;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

#[CoversClass(SamlRequest::class)]
class SamlRequestTest extends TestCase
{
    /**
     * @return array<int,array{issuer:string}>
     */
    public static function getSamlRequestParams(): array
    {
        return [
            [
                'issuer' => 'some-issuer',
            ],
        ];
    }

    #[DataProvider('getSamlRequestParams')]
    public function testCanPresentAsString(string $issuer): void
    {
        $samlRequest = new SamlRequest($issuer);

        /** @var SimpleXMLElement */
        $request = simplexml_load_string((string) gzinflate((string) base64_decode((string) $samlRequest, true)), 'SimpleXMLElement', 0, 'samlp');
        $request->registerXPathNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');

        /** @var array<SimpleXMLElement> */
        $elems = $request->xpath('/samlp:AuthnRequest/saml:Issuer');
        $this->assertSame($issuer, (string) $elems[0]);

        /** @var array<SimpleXMLElement> */
        $elems = $request->xpath(sprintf('/samlp:AuthnRequest[@ID="%s"]', $samlRequest->getId()));
        $this->assertCount(1, $elems);
    }
}
