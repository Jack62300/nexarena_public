<?php

namespace App\Service;

class StatusService
{
    public function check(string $ip, int $port, int $timeout = 3): bool
    {
        $connection = @fsockopen($ip, $port, $errno, $errstr, $timeout);

        if ($connection) {
            fclose($connection);
            return true;
        }

        return false;
    }
}
