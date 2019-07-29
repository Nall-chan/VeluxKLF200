<?php

declare(strict_types=1);

namespace Mdanter\Ecc\Serializer\Signature;

use Mdanter\Ecc\Crypto\Signature\SignatureInterface;

interface DerSignatureSerializerInterface
{
    /**
     * @param SignatureInterface $signature
     *
     * @return string
     */
    public function serialize(SignatureInterface $signature): string;

    /**
     * @param string $binary
     *
     * @throws \FG\ASN1\Exception\ParserException
     *
     * @return SignatureInterface
     */
    public function parse(string $binary): SignatureInterface;
}
