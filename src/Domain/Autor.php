<?php

declare(strict_types=1);

namespace BibliotecaORM\Domain;

use InvalidArgumentException;
use Stringable;

/**
 * Entidade de domínio. Não conhece PDO, SQL nem nomes de tabela.
 *
 * Encapsulamento: todas as propriedades são privadas e o identificador NÃO possui
 * setter. O id gerado pelo MySQL é atribuído somente pela infraestrutura
 * (AbstractRepository::atribuirId), e o construtor valida todos os dados.
 */
final class Autor implements Identificavel, Stringable
{
    public const NOME_MAX = 150;
    public const NACIONALIDADE_MAX = 60;

    private ?int $id;
    private string $nome;
    private ?string $nacionalidade = null;

    public function __construct(string $nome, ?string $nacionalidade = null, ?int $id = null)
    {
        if ($id !== null && $id <= 0) {
            throw new InvalidArgumentException('O identificador deve ser um inteiro positivo.');
        }
        $this->id = $id;
        $this->setNome($nome);
        $this->setNacionalidade($nacionalidade);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function setNome(string $nome): void
    {
        $nome = trim($nome);
        $tamanho = mb_strlen($nome);
        if ($tamanho < 1 || $tamanho > self::NOME_MAX) {
            throw new InvalidArgumentException(
                sprintf('O nome do autor deve ter entre 1 e %d caracteres.', self::NOME_MAX)
            );
        }
        $this->nome = $nome;
    }

    public function getNacionalidade(): ?string
    {
        return $this->nacionalidade;
    }

    public function setNacionalidade(?string $nacionalidade): void
    {
        if ($nacionalidade !== null) {
            $nacionalidade = trim($nacionalidade);
            if ($nacionalidade === '') {
                $nacionalidade = null;
            }
        }
        if ($nacionalidade !== null && mb_strlen($nacionalidade) > self::NACIONALIDADE_MAX) {
            throw new InvalidArgumentException(
                sprintf('A nacionalidade deve ter no máximo %d caracteres.', self::NACIONALIDADE_MAX)
            );
        }
        $this->nacionalidade = $nacionalidade;
    }

    public function __toString(): string
    {
        return sprintf(
            'Autor#%s "%s"%s',
            $this->id ?? 'novo',
            $this->nome,
            $this->nacionalidade !== null ? " ({$this->nacionalidade})" : ''
        );
    }
}
