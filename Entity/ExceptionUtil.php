<?php

namespace Kimerikal\UtilBundle\Entity;

use Doctrine\ORM\EntityManager;
use Exception;

class ExceptionUtil
{
    /**
     * Used to regenerate a closed EntityManager. 
     * 
     * @param EntityManager $em - Pass by reference.
     * @return void
     */
    public static function reopenEntityManager(EntityManager &$em): void
    {
        if (!$em->isOpen()) {
            $em = $em->create($em->getConnection(), $em->getConfiguration());
        }
    }

    /**
     * Used to catch a doctrine DbalException. 
     * The method logs the exception and checks if the entity manager is opened, if not, it resets the connection and reopen the entity manager.
     * 
     * @param EntityManager $em - Pass by reference.
     * @param Exception $exception
     * @param string $method - Method name where the try catch block is.
     * @return void
     */
    public static function logAndReOpenEM(EntityManager &$em, Exception $exception = null, string $method = null): void
    {
        self::logException($exception, $method);
        self::reopenEntityManager($em);
    }

    /**
     * Used to log a caught Exception. 
     * 
     * @param Exception $exception
     * @param string $method - Method name where the try catch block is.
     * @return void
     */
    public static function logException(Exception $exception = null, string $method = null): void
    {
        if ($exception != NULL)
            \error_log('FATAL EXCEPTION || ' . \get_class($exception) . ' ' . ($exception->getFile()) . (!empty($method) ? ' || Method: ' . $method : '') . ' || Line: ' . $exception->getLine()  . ' || CODE: ' . $exception->getCode() . ' || MESSAGE: ' . $exception->getMessage());
        else \error_log('FATAL EXCEPTION');
    }
}
