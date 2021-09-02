<?php

declare(strict_types=1);

namespace ProIPInfo;

class Client
{
    /**
     * 4 bit for start range
     * 4 bit for end range
     * 4 bit for ptr.
     * */
    const V4_HASH_REC_LEN = 12;

    /**
     * 12 bit for start range
     * 12 bit for end range
     * 4 bit for ptr.
     * */
    const V6_HASH_REC_LEN = 36;

    const NOT_FOUND_PTR = -1;

    private DbStream $_file;
    private Meta $_meta;
    private InternalMeta $_internalMeta;

    /**
     * @throws ProIPException
     */
    public function __construct(DbStream $file)
    {
        $this->_file = $file;
        $this->_parseMeta();
    }

    public function getMeta(): Meta
    {
        return $this->_meta;
    }

    /**
     * @throws ProIPException
     */
    public function getRecord(string $ip): ?Record
    {
        $ipV4 = BinaryPacker::toV4($ip);
        if (!empty($ipV4)) {
            return $this->_getRecordV4($ipV4);
        }

        return $this->_getRecordV6($ip);
    }

    private function _getMetaLength(): int
    {
        return 4 + //STRUCT_VERSION_POS
            4 + //BUILD_VERSION_POS
            4 + //COUNT_V4_POS
            4 + //COUNT_V6_POS
            4 + //CONTENT_PTR_POS
            4 + //REGION_PTR_POS
            4 + //CITY_PTR_POS
            4 + //ISP_PTR_POS
            4 + //HASH_V4_POS
            4 + //HASH_V4_MIN
            4 + //HASH_V4_MAX
            4 + //HASH_V4_STEP
            4 + //HASH_V6_POS
            16 + //HASH_V6_MIN
            16 + //HASH_V6_MAX
            16 + //HASH_V6_STEP4
            4 + //HASH_V4_PTR_POS
            4; //HASH_V6_PTR_POS
    }

    /**
     * @throws ProIPException
     */
    private function _readInt(): int
    {
        $buf = $this->_file->read(4);
        if (empty($buf)) {
            return 0;
        }

        return BinaryPacker::unpackInt($buf);
    }

    /**
     * @throws ProIPException
     */
    private function _parseMeta(): void
    {
        $length = $this->_getMetaLength() + 4;
        $this->_file->seek(0, SEEK_SET);
        $buf = $this->_file->read($length);
        if (empty($buf) || substr($buf, 0, 4) != 'GDBC') {
            throw new ProIPException('Invalid meta header');
        }
        $this->_meta = new Meta();
        $this->_meta->structVersion = BinaryPacker::unpackInt(substr($buf, 4, 4));
        $this->_meta->buildVersion = BinaryPacker::unpackInt(substr($buf, 8, 4));
        $this->_meta->countV4 = BinaryPacker::unpackInt(substr($buf, 12, 4));
        $this->_meta->countV6 = BinaryPacker::unpackInt(substr($buf, 16, 4));

        $this->_internalMeta = new InternalMeta();
        $this->_internalMeta->contentPtr = BinaryPacker::unpackInt(substr($buf, 20, 4));
        $this->_internalMeta->regionPtr = BinaryPacker::unpackInt(substr($buf, 24, 4));
        $this->_internalMeta->cityPtr = BinaryPacker::unpackInt(substr($buf, 28, 4));
        $this->_internalMeta->ispPtr = BinaryPacker::unpackInt(substr($buf, 32, 4));
        $this->_internalMeta->hashV4Pos = BinaryPacker::unpackInt(substr($buf, 36, 4));
        $this->_internalMeta->hashV4Min = BinaryPacker::unpackInt(substr($buf, 40, 4));
        $this->_internalMeta->hashV4Max = BinaryPacker::unpackInt(substr($buf, 44, 4));
        $this->_internalMeta->hashV4Step = BinaryPacker::unpackInt(substr($buf, 48, 4));
        $this->_internalMeta->hashV6Pos = BinaryPacker::unpackInt(substr($buf, 52, 4));
        $this->_internalMeta->hashV6Min = BinaryPacker::unpackBigInt(substr($buf, 56, 16));
        $this->_internalMeta->hashV6Max = BinaryPacker::unpackBigInt(substr($buf, 72, 16));
        $this->_internalMeta->hashV6Step = BinaryPacker::unpackBigInt(substr($buf, 88, 16));
        $this->_internalMeta->hashV4PtrPos = BinaryPacker::unpackInt(substr($buf, 104, 4));
        $this->_internalMeta->hashV6PtrPos = BinaryPacker::unpackInt(substr($buf, 108, 4));
    }

    private function _hashFuncV4(int $val): int
    {
        //Usually this shouldn't be so but just in case add this check
        if ($val > $this->_internalMeta->hashV4Max) {
            $val = $this->_internalMeta->hashV4Max;
        }

        return (int) floor(($val - $this->_internalMeta->hashV4Min) / $this->_internalMeta->hashV4Step);
    }

    /**
     * @param numeric-string $val
     */
    private function _hashFuncV6(string $val): int
    {
        //Usually this shouldn't be so but just in case add this check
        if (bccomp($val, $this->_internalMeta->hashV6Max) > 0) {
            $val = $this->_internalMeta->hashV6Max;
        }
        $tmp = bcsub($val, $this->_internalMeta->hashV6Min);

        return (int) bcdiv($tmp, $this->_internalMeta->hashV6Step);
    }

    private function _getHashStartV4(string $buf, int $pos): int
    {
        $startPos = $pos * self::V4_HASH_REC_LEN;

        return BinaryPacker::unpackInt(substr($buf, $startPos, 4));
    }

    private function _getHashEndV4(string $buf, int $pos): int
    {
        $startPos = $pos * self::V4_HASH_REC_LEN + 4;

        return BinaryPacker::unpackInt(substr($buf, $startPos, 4));
    }

    private function _getHashPtrV4(string $buf, int $pos): int
    {
        $startPos = $pos * self::V4_HASH_REC_LEN + 8;

        return BinaryPacker::unpackInt(substr($buf, $startPos, 4));
    }

    private function _getLeafPtrV4(string $buf, int $searchIPInt): int
    {
        $low = 0;
        $high = (int) (strlen($buf) / self::V4_HASH_REC_LEN - 1);
        $hashLowStart = $this->_getHashStartV4($buf, $low);
        $hashLowEnd = $this->_getHashEndV4($buf, $low);
        $hashLowPtr = $this->_getHashPtrV4($buf, $low);
        if ($hashLowStart <= $searchIPInt && $searchIPInt <= $hashLowEnd) {
            return $hashLowPtr;
        }
        if ($high == $low || $searchIPInt < $hashLowStart) {
            return self::NOT_FOUND_PTR;
        }
        $hashHighStart = $this->_getHashStartV4($buf, $high);
        $hashHighEnd = $this->_getHashEndV4($buf, $high);
        $hashHighPtr = $this->_getHashPtrV4($buf, $high);
        if ($hashHighStart <= $searchIPInt && $searchIPInt <= $hashHighEnd) {
            return $hashHighPtr;
        }
        if ($hashHighEnd < $searchIPInt) {
            return self::NOT_FOUND_PTR;
        }

        while (1) {
            $nextApprox = (int) round($low +
                ($high - $low) *
                ($searchIPInt - $hashLowEnd) /
                ($hashHighStart - $hashLowEnd));
            if ($nextApprox == $low) {
                $nextApprox = $low + 1;
            }
            if ($nextApprox === $high) {
                $nextApprox = $high - 1;
            }
            $hashCurStart = $this->_getHashStartV4($buf, $nextApprox);
            $hashCurEnd = $this->_getHashEndV4($buf, $nextApprox);
            $hashCurPtr = $this->_getHashPtrV4($buf, $nextApprox);

            if ($hashCurStart <= $searchIPInt && $searchIPInt <= $hashCurEnd) {
                return $hashCurPtr;
            }
            if ($searchIPInt > $hashCurEnd) {
                $low = $nextApprox;
            } elseif ($searchIPInt < $hashCurStart) {
                $high = $nextApprox;
            }
            if ($high <= $low + 1) {
                break;
            }
        }

        return self::NOT_FOUND_PTR;
    }

    /**
     * @throws ProIPException
     */
    private function _getHashListV4(int $searchIPInt): string
    {
        $block = $this->_hashFuncV4($searchIPInt);
        $hashAddrPos = $this->_internalMeta->hashV4PtrPos + $block * 4;
        $this->_file->seek($hashAddrPos, SEEK_SET);
        $hashListPtr = $this->_readInt();
        $this->_file->seek($this->_internalMeta->hashV4Pos + $hashListPtr, SEEK_SET);
        $hashLen = $this->_readInt();
        if ($hashLen == 0) {
            return '';
        }

        return $this->_file->read($hashLen) ?: '';
    }

    /**
     * @throws ProIPException
     */
    private function _readDic(int $ptr): string
    {
        $this->_file->seek($ptr, SEEK_SET);
        $buf = $this->_file->read(256) ?: '';

        return substr($buf, 1, ord($buf[0]));
    }

    /**
     * @throws ProIPException
     */
    private function _getLeaf(int $ptr): Record
    {
        $this->_file->seek($this->_internalMeta->contentPtr + $ptr, SEEK_SET);
        $leaf = new Record();
        $countryCode = $this->_file->read(2) ?: '';
        $leaf->countryCode = $countryCode;
        $regionPtr = $this->_readInt();
        $pos = $this->_file->tell();
        $leaf->region = $this->_readDic($this->_internalMeta->regionPtr + $regionPtr);
        $this->_file->seek($pos, SEEK_SET);
        $cityPtr = $this->_readInt();
        $pos = $this->_file->tell();
        $leaf->city = $this->_readDic($this->_internalMeta->cityPtr + $cityPtr);
        $this->_file->seek($pos, SEEK_SET);
        $ispPtr = $this->_readInt();
        $pos = $this->_file->tell();
        $leaf->ISP = $this->_readDic($this->_internalMeta->ispPtr + $ispPtr);
        $this->_file->seek($pos, SEEK_SET);

        return $leaf;
    }

    /**
     * @throws ProIPException
     */
    private function _getRecordV4(string $ip): ?Record
    {
        $searchIPInt = BinaryPacker::ipV4ToInt($ip);
        if ($searchIPInt < $this->_internalMeta->hashV4Min || $this->_internalMeta->hashV4Max < $searchIPInt) {
            return null;
        }
        $buf = $this->_getHashListV4($searchIPInt);
        if (empty($buf)) {
            return null;
        }
        $leafPtr = $this->_getLeafPtrV4($buf, $searchIPInt);
        if ($leafPtr == self::NOT_FOUND_PTR) {
            return null;
        }

        return $this->_getLeaf($leafPtr);
    }

    /**
     * @throws ProIPException
     */
    private function _getRecordV6(string $ip): ?Record
    {
        $searchIPInt = BinaryPacker::ipV6ToBigInt($ip);
        if (
            bccomp($searchIPInt, $this->_internalMeta->hashV6Min) < 0
            || bccomp($this->_internalMeta->hashV6Max, $searchIPInt) < 0
        ) {
            return null;
        }
        $buf = $this->_getHashListV6($searchIPInt);
        if (empty($buf)) {
            return null;
        }
        $leafPtr = $this->_getLeafPtrV6($buf, $searchIPInt);
        if ($leafPtr == self::NOT_FOUND_PTR) {
            return null;
        }

        return $this->_getLeaf($leafPtr);
    }

    /**
     * @return numeric-string
     */
    private function _getHashStartV6(string $buf, int $pos): string
    {
        $startPos = $pos * self::V6_HASH_REC_LEN;

        return BinaryPacker::unpackBigInt(substr($buf, $startPos, 16));
    }

    /**
     * @return numeric-string
     */
    private function _getHashEndV6(string $buf, int $pos): string
    {
        $startPos = $pos * self::V6_HASH_REC_LEN + 16;

        return BinaryPacker::unpackBigInt(substr($buf, $startPos, 16));
    }

    private function _getHashPtrV6(string $buf, int $pos): int
    {
        $startPos = $pos * self::V6_HASH_REC_LEN + 32;

        return BinaryPacker::unpackInt(substr($buf, $startPos, 4));
    }

    /**
     * @psalm-param numeric-string $searchIPInt
     */
    private function _getLeafPtrV6(string $buf, string $searchIPInt): int
    {
        $low = 0;
        $high = (int) (strlen($buf) / self::V6_HASH_REC_LEN - 1);
        $hashLowStart = $this->_getHashStartV6($buf, $low);
        $hashLowEnd = $this->_getHashEndV6($buf, $low);
        $hashLowPtr = $this->_getHashPtrV6($buf, $low);
        if (bccomp($hashLowStart, $searchIPInt) <= 0 && bccomp($searchIPInt, $hashLowEnd) <= 0) {
            return $hashLowPtr;
        }
        if ($high == $low || bccomp($searchIPInt, $hashLowStart) < 0) {
            return self::NOT_FOUND_PTR;
        }
        $hashHighStart = $this->_getHashStartV6($buf, $high);
        $hashHighEnd = $this->_getHashEndV6($buf, $high);
        $hashHighPtr = $this->_getHashPtrV6($buf, $high);
        if (bccomp($hashHighStart, $searchIPInt) <= 0 && bccomp($searchIPInt, $hashHighEnd) <= 0) {
            return $hashHighPtr;
        }
        if (bccomp($hashHighEnd, $searchIPInt) < 0) {
            return self::NOT_FOUND_PTR;
        }
        while (1) {
            $fullInterval = bcsub($hashHighStart, $hashLowEnd);
            $nextApprox = bcsub($searchIPInt, $hashLowEnd);
            $nextApprox = bcmul($nextApprox, (string) ($high - $low));
            $nextApprox = bcdiv($nextApprox, $fullInterval);
            if ($nextApprox === null) {
                throw new ProIPException('Zero length iterval found');
            }
            $nextApprox = bcadd($nextApprox, (string) $low);
            $nextApprox = (int) BinaryPacker::bcFloor($nextApprox);
            if ($nextApprox == $low) {
                $nextApprox = $low + 1;
            }
            if ($nextApprox == $high) {
                $nextApprox = $high - 1;
            }
            $hashCurStart = $this->_getHashStartV6($buf, $nextApprox);
            $hashCurEnd = $this->_getHashEndV6($buf, $nextApprox);
            $hashCurPtr = $this->_getHashPtrV6($buf, $nextApprox);
            if (bccomp($hashCurStart, $searchIPInt) <= 0 && bccomp($searchIPInt, $hashCurEnd) <= 0) {
                return $hashCurPtr;
            }
            if (bccomp($searchIPInt, $hashCurEnd) > 0) {
                $low = $nextApprox;
            } elseif (bccomp($searchIPInt, $hashCurStart) < 0) {
                $high = $nextApprox;
            }
            if ($high <= $low + 1) {
                break;
            }
        }

        return self::NOT_FOUND_PTR;
    }

    /**
     * @psalm-param numeric-string $searchIPInt
     *
     * @throws ProIPException
     */
    private function _getHashListV6(string $searchIPInt): string
    {
        $block = $this->_hashFuncV6($searchIPInt);
        $hashAddrPos = $this->_internalMeta->hashV6PtrPos + $block * 4;
        $this->_file->seek($hashAddrPos, SEEK_SET);
        $hashListPtr = $this->_readInt();
        $this->_file->seek($this->_internalMeta->hashV6Pos + $hashListPtr, SEEK_SET);
        $hashLen = $this->_readInt();
        if (empty($hashLen)) {
            return '';
        }

        return $this->_file->read($hashLen) ?: '';
    }
}
