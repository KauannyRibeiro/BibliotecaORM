<?php

declare(strict_types=1);

namespace BibliotecaORM\Infrastructure;

use PDO;

/** Fábrica de conexões PDO com opções seguras e consistentes. */
final class Connection
{
    /** @param array{host:string,port:int,name:string,user:string,pass:string} $cfg */
    public static function criar(array $cfg): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['host'],
            $cfg['port'],
            $cfg['name']
        );

        return new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // erros viram exceções (necessário p/ rollback)
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,                   // prepared statements reais no servidor
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,                    // rowCount() = linhas casadas (UPDATE sem mudança != "não achou")
        ]);
    }
}
