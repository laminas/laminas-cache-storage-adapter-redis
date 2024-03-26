<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use InvalidArgumentException;
use Laminas\Stdlib\ArraySerializableInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

use function boolval;
use function get_object_vars;
use function property_exists;
use function sprintf;

use const OPENSSL_DEFAULT_STREAM_CIPHERS;
use const OPENSSL_TLSEXT_SERVER_NAME;

/**
 * Class containing the SSL context options in its fields.
 *
 * @link https://www.php.net/manual/en/context.ssl.php
 */
final class SslContext implements ArraySerializableInterface
{
    /**
     * Peer name to be used.
     * If this value is not set, then the name is guessed based on the hostname used when opening the stream.
     */
    private ?string $peerName;

    /**
     * Require verification of SSL certificate used.
     */
    private bool $verifyPeer;

    /**
     * Require verification of peer name.
     */
    private bool $verifyPeerName;

    /**
     * Allow self-signed certificates. Requires verifyPeer.
     */
    private bool $allowSelfSigned;

    /**
     * Location of Certificate Authority file on local filesystem which should be used with the verifyPeer
     * context option to authenticate the identity of the remote peer.
     */
    private ?string $cafile;

    /**
     * If cafile is not specified or if the certificate is not found there, the directory pointed to by capath is
     * searched for a suitable certificate. capath must be a correctly hashed certificate directory.
     */
    private ?string $capath;

    /**
     * Path to local certificate file on filesystem. It must be a PEM encoded file which contains your certificate and
     * private key. It can optionally contain the certificate chain of issuers.
     * The private key also may be contained in a separate file specified by localPk.
     */
    private ?string $localCert;

    /**
     * Path to local private key file on filesystem in case of separate files for certificate (localCert)
     * and private key.
     */
    private ?string $localPk;

    /**
     * Passphrase with which your localCert file was encoded.
     */
    private ?string $passphrase;

    /**
     * Abort if the certificate chain is too deep.
     * If not set, defaults to no verification.
     */
    private ?int $verifyDepth;

    /**
     * Sets the list of available ciphers. The format of the string is described in
     * https://www.openssl.org/docs/manmaster/man1/ciphers.html#CIPHER-LIST-FORMAT
     */
    private string $ciphers;

    /**
     * If set to true server name indication will be enabled. Enabling SNI allows multiple certificates on the same
     * IP address.
     * If not set, will automatically be enabled if SNI support is available.
     */
    private ?bool $sniEnabled;

    /**
     * If set, disable TLS compression. This can help mitigate the CRIME attack vector.
     */
    private bool $disableCompression;

    /**
     * Aborts when the remote certificate digest doesn't match the specified hash.
     *
     * When a string is used, the length will determine which hashing algorithm is applied,
     * either "md5" (32) or "sha1" (40).
     *
     * When an array is used, the keys indicate the hashing algorithm name and each corresponding
     * value is the expected digest.
     */
    private string|array|null $peerFingerprint;

    /**
     * Sets the security level. If not specified the library default security level is used. The security levels are
     * described in https://www.openssl.org/docs/man1.1.1/man3/SSL_CTX_get_security_level.html.
     */
    private ?int $securityLevel;

    public function __construct(
        ?string $peerName = null,
        bool $verifyPeer = true,
        bool $verifyPeerName = true,
        bool $allowSelfSigned = false,
        ?string $cafile = null,
        ?string $capath = null,
        ?string $localCert = null,
        ?string $localPk = null,
        ?string $passphrase = null,
        ?int $verifyDepth = null,
        string $ciphers = OPENSSL_DEFAULT_STREAM_CIPHERS,
        ?bool $sniEnabled = null,
        bool $disableCompression = true,
        array|string|null $peerFingerprint = null,
        ?int $securityLevel = null
    ) {
        $this->peerName           = $peerName;
        $this->verifyPeer         = $verifyPeer;
        $this->verifyPeerName     = $verifyPeerName;
        $this->allowSelfSigned    = $allowSelfSigned;
        $this->cafile             = $cafile;
        $this->capath             = $capath;
        $this->localCert          = $localCert;
        $this->localPk            = $localPk;
        $this->passphrase         = $passphrase;
        $this->verifyDepth        = $verifyDepth;
        $this->ciphers            = $ciphers;
        $this->sniEnabled         = $sniEnabled;
        $this->disableCompression = $disableCompression;
        $this->peerFingerprint    = $peerFingerprint;
        $this->securityLevel      = $securityLevel;
        if ($sniEnabled === null) {
            $this->sniEnabled = boolval(OPENSSL_TLSEXT_SERVER_NAME);
        }
    }

    public function exchangeArray(array $array): void
    {
        foreach ($array as $key => $value) {
            $property = $this->mapArrayKeyToPropertyName($key);
            if (! property_exists($this, $property)) {
                throw new InvalidArgumentException(
                    sprintf(
                        '%s does not contain the property "%s" corresponding to the array key "%s"',
                        self::class,
                        $property,
                        $key
                    )
                );
            }
            $this->$property = $value;
        }
    }

    public function getArrayCopy(): array
    {
        $array = [];
        foreach (get_object_vars($this) as $property => $value) {
            if ($value !== null) {
                $key         = $this->mapPropertyNameToArrayKey($property);
                $array[$key] = $value;
            }
        }
        return $array;
    }

    private function mapArrayKeyToPropertyName(string $key): string
    {
        if ($key === 'SNI_enabled') {
            return 'sniEnabled';
        }
        return (new CamelCaseToSnakeCaseNameConverter())->denormalize($key);
    }

    private function mapPropertyNameToArrayKey(string $property): string
    {
        if ($property === 'sniEnabled') {
            return 'SNI_enabled';
        }
        return (new CamelCaseToSnakeCaseNameConverter())->normalize($property);
    }
}
