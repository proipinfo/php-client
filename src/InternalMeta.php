<?php

declare(strict_types=1);

namespace ProIPInfo;

/**
 * Meta contains meta info about db.
 */
class InternalMeta
{
    public int $contentPtr = 0;
    public int $regionPtr = 0;
    public int $cityPtr = 0;
    public int $ispPtr = 0;
    public int $hashV4Pos = 0;
    public int $hashV4Min = 0;
    public int $hashV4Max = 0;
    public int $hashV4Step = 0;
    public int $hashV6Pos = 0;
    /** @var numeric-string */
    public string $hashV6Min = '0';
    /** @var numeric-string */
    public string $hashV6Max = '0';
    /** @var numeric-string */
    public string $hashV6Step = '0';
    public int $hashV4PtrPos = 0;
    public int $hashV6PtrPos = 0;
}
