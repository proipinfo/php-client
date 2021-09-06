<?php

declare(strict_types=1);

namespace ProIPInfo;

/**
 * Meta contains meta info about db.
 */
class Meta
{
    /** @var int version of struct */
    public int $structVersion = 0;

    /** @var int version of build */
    public int $buildVersion = 0;

    /** @var int amount of ip v4 */
    public int $countV4 = 0;

    /** @var int amount of ip v6 */
    public int $countV6 = 0;
}
