<?php

namespace ProIPInfo;

class Client
{
    /**
     * PRIVATE SECTION
     */

    const START_POS = 0;
    const END_POS = 1;
    const PTR_POS = 2;
    const NOT_FOUND_PTR = -1;

    /**
     * @var DbStream
     */
    private $_file;

    /**
     * @var Meta
     */
    private $_meta;

    /**
     * @var InternalMeta
     */
    private $_internalMeta;

    /**
     * PUBLIC SECTION
     */

    /**
     * Client constructor.
     * @param string $filename
     * @throws \Exception
     */
    public function __construct(string $filename)
    {
        $this->_file = new DbStream($filename, true);
        $this->_parseMeta();
    }

    public function getMeta(): Meta
    {
        return $this->_meta;
    }

    public function getRecord($ip): Record
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

    private function _readInt(): float
    {
        $buf = $this->_file->read(4);
        return BinaryPacker::unpackInt($buf);
    }

    /**
     * @throws \Exception
     */
    private function _parseMeta()
    {
        $length = $this->_getMetaLength() + 4;
        $this->_file->seek(0, SEEK_SET);
        $buf = $this->_file->read($length);
        if (substr($buf, 0, 4) != "GDBC") {
            throw new \Exception("Invalid meta header");
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

    /**
     * @param $val
     * @return false|float
     */
    private function _hashFuncV4($val)
    {
        //Usually this shouldn't be so but just in case add this check
        if ($val > $this->_internalMeta->hashV4Max) {
            $val = $this->_internalMeta->hashV4Max;
        }
        return floor(($val - $this->_internalMeta->hashV4Min) / $this->_internalMeta->hashV4Step);
    }

    /**
     * @param $val
     * @return string|null
     */
    private function _hashFuncV6($val): ?string
    {
        //Usually this shouldn't be so but just in case add this check
        if (bccomp($val, $this->_internalMeta->hashV6Max) > 0) {
            $val = $this->_internalMeta->hashV6Max;
        }
        $tmp = bcsub($val, $this->_internalMeta->hashV6Min);
        return bcdiv($tmp, $this->_internalMeta->hashV6Step);
    }

    private function _getHashValsV4($buf, $pos): array
    {
        $startPos = $pos * 12;
        return [
            self::START_POS => BinaryPacker::unpackInt(substr($buf, $startPos, 4)),
            self::END_POS => BinaryPacker::unpackInt(substr($buf, $startPos + 4, 4)),
            self::PTR_POS => BinaryPacker::unpackInt(substr($buf, $startPos + 8, 4)),
        ];
    }

    private function _getLeafPtrV4($buf, $searchIPInt): int
    {
        $low = 0;
        $high = strlen($buf) / 12 - 1;
        $hashLow = $this->_getHashValsV4($buf, $low);
        if ($hashLow[self::START_POS] <= $searchIPInt && $searchIPInt <= $hashLow[self::END_POS]) {
            return $hashLow[self::PTR_POS];
        }
        if ($high == $low || $searchIPInt < $hashLow[self::START_POS]) {
            return self::NOT_FOUND_PTR;
        }
        $hashHigh = $this->_getHashValsV4($buf, $high);
        if ($hashHigh[self::START_POS] <= $searchIPInt && $searchIPInt <= $hashHigh[self::END_POS]) {
            return $hashHigh[self::PTR_POS];
        }
        if ($hashHigh[self::END_POS] < $searchIPInt) {
            return self::NOT_FOUND_PTR;
        }

        while (1) {
            $nextApprox = round($low +
                ($high - $low) *
                ($searchIPInt - $hashLow[self::END_POS]) /
                ($hashHigh[self::START_POS] - $hashLow[self::END_POS]));
            if ($nextApprox == $low) {
                $nextApprox = $low + 1;
            }
            if ($nextApprox == $high) {
                $nextApprox = $high - 1;
            }
            $hashCur = $this->_getHashValsV4($buf, $nextApprox);
            if ($hashCur[self::START_POS] <= $searchIPInt && $searchIPInt <= $hashCur[self::END_POS]) {
                return $hashCur[self::PTR_POS];
            } elseif ($searchIPInt > $hashCur[self::END_POS]) {
                $low = $nextApprox;
            } elseif ($searchIPInt < $hashCur[self::START_POS]) {
                $high = $nextApprox;
            }
            if ($high <= $low + 1) {
                break;
            }
        }
        return self::NOT_FOUND_PTR;
    }

    private function _getHashListV4($searchIPInt)
    {
        $block = $this->_hashFuncV4($searchIPInt);
        $hashAddrPos = $this->_internalMeta->hashV4PtrPos + $block * 4;
        $this->_file->seek($hashAddrPos, SEEK_SET);
        $hashListPtr = $this->_readInt();
        $this->_file->seek($this->_internalMeta->hashV4Pos + $hashListPtr, SEEK_SET);
        $hashLen = $this->_readInt();
        if ($hashLen == 0) {
            return "";
        }
        $buf = $this->_file->read($hashLen);
        return $buf;
    }

    private function _readDic($ptr)
    {
        $this->_file->seek($ptr, SEEK_SET);
        $buf = $this->_file->read(256);
        return substr($buf, 1, ord($buf[0]));
    }

    private function _getLeaf($ptr): Record
    {
        $this->_file->seek($this->_internalMeta->contentPtr + $ptr, SEEK_SET);
        $leaf = new Record();
        $leaf->countryCode = $this->_file->read(2);
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

    private function _getRecordV4($ip): ?Record
    {
        $searchIPInt = BinaryPacker::ipV4ToInt($ip);
        if ($searchIPInt < $this->_internalMeta->hashV4Min ||
            $this->_internalMeta->hashV4Max < $searchIPInt
        ) {
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

    private function _getRecordV6($ip): ?Record
    {
        $searchIPInt = BinaryPacker::ipV6ToBigInt($ip);
        if (bccomp($searchIPInt, $this->_internalMeta->hashV6Min) < 0 ||
            bccomp($this->_internalMeta->hashV6Max, $searchIPInt) < 0
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

    private function _getHashValsV6($buf, $pos): array
    {
        $startPos = $pos * 36;
        return [
            self::START_POS => BinaryPacker::unpackBigInt(substr($buf, $startPos, 16)),
            self::END_POS => BinaryPacker::unpackBigInt(substr($buf, $startPos + 16, 16)),
            self::PTR_POS => BinaryPacker::unpackInt(substr($buf, $startPos + 32, 4)),
        ];
    }

    private function _getLeafPtrV6($buf, $searchIPInt): int
    {
        $low = 0;
        $high = strlen($buf) / 36 - 1;
        $hashLow = $this->_getHashValsV6($buf, $low);
        if (bccomp($hashLow[self::START_POS], $searchIPInt) <= 0 &&
            bccomp($searchIPInt, $hashLow[self::END_POS]) <= 0
        ) {
            return $hashLow[self::PTR_POS];
        }
        if ($high == $low || bccomp($searchIPInt, $hashLow[self::START_POS]) < 0) {
            return self::NOT_FOUND_PTR;
        }
        $hashHigh = $this->_getHashValsV6($buf, $high);
        if (bccomp($hashHigh[self::START_POS], $searchIPInt) <= 0 &&
            bccomp($searchIPInt, $hashHigh[self::END_POS]) <= 0
        ) {
            return $hashHigh[self::PTR_POS];
        }
        if (bccomp($hashHigh[self::END_POS], $searchIPInt) < 0) {
            return self::NOT_FOUND_PTR;
        }
        while (1) {
            $fullInterval = bcsub($hashHigh[self::START_POS], $hashLow[self::END_POS]);
            $nextApprox = bcsub($searchIPInt, $hashLow[self::END_POS]);
            $nextApprox = bcmul($nextApprox, $high - $low);
            $nextApprox = bcdiv($nextApprox, $fullInterval);
            $nextApprox = bcadd($nextApprox, $low);
            $nextApprox = BinaryPacker::bcFloor($nextApprox);
            if ($nextApprox == $low) {
                $nextApprox = $low + 1;
            }
            if ($nextApprox == $high) {
                $nextApprox = $high - 1;
            }
            $hashCur = $this->_getHashValsV4($buf, $nextApprox);
            if (bccomp($hashCur[self::START_POS], $searchIPInt) <= 0 &&
                bccomp($searchIPInt, $hashCur[self::END_POS]) <= 0
            ) {
                return $hashCur[self::PTR_POS];
            } elseif (bccomp($searchIPInt, $hashCur[self::END_POS]) > 0) {
                $low = $nextApprox;
            } elseif (bccomp($searchIPInt, $hashCur[self::START_POS]) < 0) {
                $high = $nextApprox;
            }
            if ($high <= $low + 1) {
                break;
            }
        }
        return self::NOT_FOUND_PTR;
    }

    private function _getHashListV6($searchIPInt)
    {
        $block = $this->_hashFuncV6($searchIPInt);
        $hashAddrPos = $this->_internalMeta->hashV6PtrPos + $block * 4;
        $this->_file->seek($hashAddrPos, SEEK_SET);
        $hashListPtr = $this->_readInt();
        $this->_file->seek($this->_internalMeta->hashV6Pos + $hashListPtr, SEEK_SET);
        $hashLen = $this->_readInt();
        if (empty($hashLen)) {
            return "";
        }
        $buf = $this->_file->read($hashLen);
        return $buf;
    }
}
