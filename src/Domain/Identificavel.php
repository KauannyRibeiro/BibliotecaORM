<?php

declare(strict_types=1);

namespace BibliotecaORM\Domain;

/** Toda entidade persistível expõe seu identificador (somente leitura). */
interface Identificavel
{
    public function getId(): ?int;
}
