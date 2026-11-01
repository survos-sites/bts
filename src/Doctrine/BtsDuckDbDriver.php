<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ServerVersionProvider;
use DuckDb\DBAL\Driver;
use DuckDb\DBAL\PDO\Connection;

/** Only the demo schema compatibility remains here; profiling is automatic in the bundle. */
final class BtsDuckDbDriver implements DriverInterface
{
    public function connect(array $params): Connection
    {
        unset($params['driverOptions']['s3']);

        return (new Driver())->connect($params);
    }
    public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform { return new BtsDuckDbPlatform(); }
    public function getExceptionConverter(): ExceptionConverter { return (new Driver())->getExceptionConverter(); }
}
