<?php
namespace ProIPInfo;

/**
 * Meta contains meta info about db
 */
class Meta
{
    /**
     * @var int version of struct
     */
    public $structVersion;

    /**
     * @var int version of build
     */
    public $buildVersion;

    /**
     * @var int amount of ip v4
     */
    public $countV4;

    /**
     * @var int amount of ip v6
     */
    public $countV6;
}
