<?php

declare(strict_types=1);

namespace ProIPInfo;

/**
 * Record class contains info about ip address.
 */
class Record
{
    public string $countryCode = '';

    public string $region = '';

    public string $city = '';

    public string $ISP = '';
}
