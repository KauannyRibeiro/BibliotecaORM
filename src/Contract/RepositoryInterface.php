<?php

declare(strict_types=1);

namespace BibliotecaORM\Contract;

/**
 * Contrato único de persistência. O código consumidor depende desta interface,
 * nunca de uma classe concreta (polimorfismo).
 *
 * Os repositórios concretos podem estreitar os tipos de retorno
 * (ex.: ?Livro em vez de ?object) — covariância permitida pelo PHP.
 */
interface RepositoryInterface
{
    /** Retorna a entidade hidratada ou null se o id não existir. */
    public function buscar(int $id): ?object;

    /** @return list<object> */
    public function listar(): array;

    /** INSERT se a entidade ainda não tem id; UPDATE caso contrário. Retorna a própria entidade. */
    public function salvar(object $entidade): object;

    /** Retorna true se uma linha foi removida. */
    public function excluir(int $id): bool;
}
