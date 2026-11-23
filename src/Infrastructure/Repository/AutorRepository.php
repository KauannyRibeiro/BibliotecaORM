<?php

declare(strict_types=1);

namespace BibliotecaORM\Infrastructure\Repository;

use BibliotecaORM\Domain\Autor;

/** Repositório concreto de Autor: só especializa tabela, mapeamento e hidratação. */
final class AutorRepository extends AbstractRepository
{
    public const TABELA = 'autores';

    protected function tabela(): string
    {
        return self::TABELA;
    }

    protected function colunas(): array
    {
        return ['nome', 'nacionalidade'];
    }

    protected function classeEntidade(): string
    {
        return Autor::class;
    }

    /** Linha do MySQL => objeto Autor, com tipos corretos (int/string/null). */
    public function hidratar(array $linha): Autor
    {
        return new Autor(
            (string) $linha['nome'],
            $linha['nacionalidade'] !== null ? (string) $linha['nacionalidade'] : null,
            (int) $linha['id']
        );
    }

    protected function extrair(object $entidade): array
    {
        /** @var Autor $entidade */
        return [
            'nome'          => $entidade->getNome(),
            'nacionalidade' => $entidade->getNacionalidade(),
        ];
    }

    public function buscar(int $id): ?Autor
    {
        return parent::buscar($id);
    }
}
