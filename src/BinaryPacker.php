<?php

declare(strict_types=1);

namespace ProIPInfo;

class BinaryPacker
{
    public static function packInt(int $val): ?string
    {
        return pack('V', $val);
    }

    public static function unpackInt(string $buf): int
    {
        /** @var int[] $unpackedVal */
        $unpackedVal = unpack('V', $buf);
        $unpackedVal = $unpackedVal[1];
        if ($unpackedVal < 0) {
            $unpackedVal += 4294967296;
        }

        return $unpackedVal;
    }

    /**
     * @psalm-return numeric-string
     */
    public static function unpackBigInt(string $ip_n): string
    {
        $bin = '';
        for ($bit = strlen($ip_n) - 1; $bit >= 0; --$bit) {
            $bin = sprintf('%08b', ord($ip_n[$bit])) . $bin;
        }

        $dec = '0';
        for ($i = 0; $i < strlen($bin); ++$i) {
            $dec = bcmul($dec, '2', 0);
            /** @psalm-var numeric-string $digit */
            $digit = $bin[$i];
            $dec = bcadd($dec, $digit, 0);
        }

        return $dec;
    }

    public static function ipV4ToInt(string $ip): int
    {
        return (int) sprintf('%u', ip2long($ip));
    }

    /**
     * @psalm-return numeric-string
     */
    public static function ipV6ToBigInt(string $ip): string
    {
        $bin = inet_pton($ip);

        return self::unpackBigInt($bin);
    }

    public static function toV4(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $ip;
        }
        $bin = inet_pton($ip);
        for ($i = 0; $i < strlen($bin) - 4; ++$i) {
            $symbol = $bin[$i];
            if ($i < 10 && bin2hex($symbol) != '00') {
                return null;
            }
            if ($i >= 10 && $i < 12 && bin2hex($symbol) != 'ff') {
                return null;
            }
        }
        $result = unpack('C*', substr($bin, 12, 4));

        return implode('.', $result);
    }

    /**
     * @psalm-param numeric-string $x
     *
     * @psalm-return numeric-string
     */
    public static function bcFloor(string $x): string
    {
        $result = bcmul($x, '1', 0);
        if ((bccomp($result, '0', 0) == -1) && bccomp($x, $result, 1)) {
            $result = bcsub($result, '1', 0);
        }

        return $result;
    }
}
