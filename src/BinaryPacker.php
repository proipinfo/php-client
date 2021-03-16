<?php

namespace ProIPInfo;

class BinaryPacker
{
    public static function packInt($val)
    {
        $buf = pack('V', (int)$val);
        return $buf;
    }

    public static function unpackInt($buf): float
    {
        $unpackedVal = unpack('V', $buf);
        $unpackedVal = $unpackedVal[1];
        if ($unpackedVal < 0) {
            $unpackedVal += 4294967296;
        }
        return (float)$unpackedVal;
    }

    public static function unpackBigInt($ip_n): string
    {
        $bin = '';
        for ($bit = strlen($ip_n) - 1; $bit >= 0; $bit--) {
            $bin = sprintf('%08b', ord($ip_n[$bit])) . $bin;
        }

        $dec = '0';
        for ($i = 0; $i < strlen($bin); $i++) {
            $dec = bcmul($dec, '2', 0);
            $dec = bcadd($dec, $bin[$i], 0);
        }
        return $dec;
    }

    public static function ipV4ToInt($ip): float
    {
        return (float)sprintf("%u", ip2long($ip));
    }

    public static function ipV6ToBigInt($ip): string
    {
        $bin = inet_pton($ip);
        return self::unpackBigInt($bin);
    }

    public static function toV4($ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $ip;
        }
        $bin = inet_pton($ip);
        $i = 0;
        for ($i = 0; $i < strlen($bin) - 4; $i++) {
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

    public static function bcFloor($x): string
    {
        $result = bcmul($x, '1', 0);
        if ((bccomp($result, '0', 0) == -1) && bccomp($x, $result, 1)) {
            $result = bcsub($result, 1, 0);
        }
        return $result;
    }
}
