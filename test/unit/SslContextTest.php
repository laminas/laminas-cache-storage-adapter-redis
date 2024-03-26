<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter;

use InvalidArgumentException;
use Laminas\Cache\Storage\Adapter\SslContext;
use PHPUnit\Framework\TestCase;
use TypeError;

use function boolval;

use const OPENSSL_DEFAULT_STREAM_CIPHERS;
use const OPENSSL_TLSEXT_SERVER_NAME;

final class SslContextTest extends TestCase
{
    private SslContext $correspondingSslContextObject;
    private array $correspondingSslContextArray;

    protected function setUp(): void
    {
        $this->correspondingSslContextObject = new SslContext(
            peerName: 'some peer name',
            allowSelfSigned: true,
            verifyDepth: 10,
            peerFingerprint: ['md5' => 'some fingerprint']
        );

        $this->correspondingSslContextArray = [
            'peer_name'           => 'some peer name',
            'verify_peer'         => true,
            'verify_peer_name'    => true,
            'allow_self_signed'   => true,
            'verify_depth'        => 10,
            'ciphers'             => OPENSSL_DEFAULT_STREAM_CIPHERS,
            'SNI_enabled'         => boolval(OPENSSL_TLSEXT_SERVER_NAME),
            'disable_compression' => true,
            'peer_fingerprint'    => ['md5' => 'some fingerprint'],
        ];
    }

    public function testExchangeArraySetsPropertiesCorrectly(): void
    {
        $sslContextObject = new SslContext();
        $sslContextObject->exchangeArray($this->correspondingSslContextArray);

        $this->assertEquals(
            $this->correspondingSslContextObject,
            $sslContextObject
        );
    }

    public function testGetArrayCopyReturnsAnArrayWithPropertyValues()
    {
        $sslContextArray = $this->correspondingSslContextObject->getArrayCopy();

        $this->assertEquals($this->correspondingSslContextArray, $sslContextArray);
    }

    public function testExchangeArrayThrowsExceptionWhenProvidingInvalidKeyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            '/does not contain the property "someInvalidKey" corresponding to the array key "some_invalid_key"/'
        );

        $sslContextObject = new SslContext();
        $sslContextObject->exchangeArray(['some_invalid_key' => true]);
    }

    public function testExchangeArrayThrowsExceptionWhenProvidingInvalidValueType(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessageMatches(
            '/\$verifyPeer of type bool/'
        );

        $sslContextObject = new SslContext();
        $sslContextObject->exchangeArray(['verify_peer' => 'invalid type']);
    }
}
