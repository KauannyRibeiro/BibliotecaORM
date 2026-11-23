<?php

declare(strict_types=1);

/**
 * Autoloader PSR-4 mínimo (sem Composer): BibliotecaORM\Foo\Bar => src/Foo/Bar.php
 */
spl_autoload_register(static function (string $classe): void {
    $prefixo = 'BibliotecaORM\\';
    if (strncmp($classe, $prefixo, strlen($prefixo)) !== 0) {
        return;
    }
    $relativo = substr($classe, strlen($prefixo));
    $arquivo = __DIR__ . '/' . str_replace('\\', '/', $relativo) . '.php';
    if (is_file($arquivo)) {
        require $arquivo;
    }
});
