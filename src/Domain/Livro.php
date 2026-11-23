<?php

declare(strict_types=1);

namespace BibliotecaORM\Domain;

use InvalidArgumentException;
use Stringable;

/**
 * Entidade de domínio Livro (lado N:N com Autor).
 * Sem setId(): a identidade só é atribuída pela infraestrutura após o INSERT.
 */
final class Livro implements Identificavel, Stringable
{
    public const TITULO_MAX = 200;
    public const ANO_MINIMO = 1450;

    private ?int $id;
    private string $titulo;
    private string $isbn;
    private int $anoPublicacao;

    /** @var list<Autor> */
    private array $autores = [];

    public function __construct(string $titulo, string $isbn, int $anoPublicacao, ?int $id = null)
    {
        if ($id !== null && $id <= 0) {
            throw new InvalidArgumentException('O identificador deve ser um inteiro positivo.');
        }
        $this->id = $id;
        $this->setTitulo($titulo);
        $this->setIsbn($isbn);
        $this->setAnoPublicacao($anoPublicacao);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitulo(): string
    {
        return $this->titulo;
    }

    public function setTitulo(string $titulo): void
    {
        $titulo = trim($titulo);
        $tamanho = mb_strlen($titulo);
        if ($tamanho < 1 || $tamanho > self::TITULO_MAX) {
            throw new InvalidArgumentException(
                sprintf('O título deve ter entre 1 e %d caracteres.', self::TITULO_MAX)
            );
        }
        $this->titulo = $titulo;
    }

    public function getIsbn(): string
    {
        return $this->isbn;
    }

    public function setIsbn(string $isbn): void
    {
        $normalizado = self::normalizarIsbn($isbn);
        if (preg_match('/^(?:\d{13}|\d{9}[\dX])$/', $normalizado) !== 1) {
            throw new InvalidArgumentException('ISBN inválido: informe 10 ou 13 dígitos (hífens são aceitos).');
        }
        $this->isbn = $normalizado;
    }

    /** Remove hífens/espaços e coloca em maiúsculas (dígito verificador X). */
    public static function normalizarIsbn(string $isbn): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', $isbn) ?? '');
    }

    public function getAnoPublicacao(): int
    {
        return $this->anoPublicacao;
    }

    public function setAnoPublicacao(int $ano): void
    {
        $maximo = (int) date('Y') + 1;
        if ($ano < self::ANO_MINIMO || $ano > $maximo) {
            throw new InvalidArgumentException(
                sprintf('O ano de publicação deve estar entre %d e %d.', self::ANO_MINIMO, $maximo)
            );
        }
        $this->anoPublicacao = $ano;
    }

    /**
     * Cópia da coleção: o consumidor não consegue alterar a lista interna
     * (só pelos métodos adicionarAutor/removerAutor, que preservam a regra de unicidade).
     *
     * @return list<Autor>
     */
    public function getAutores(): array
    {
        return $this->autores;
    }

    public function possuiAutor(Autor $autor): bool
    {
        foreach ($this->autores as $existente) {
            if ($existente === $autor
                || ($existente->getId() !== null && $existente->getId() === $autor->getId())) {
                return true;
            }
        }
        return false;
    }

    /** Não duplica: a mesma instância ou o mesmo id é ignorado. */
    public function adicionarAutor(Autor $autor): self
    {
        if (!$this->possuiAutor($autor)) {
            $this->autores[] = $autor;
        }
        return $this;
    }

    public function removerAutor(Autor $autor): self
    {
        $this->autores = array_values(array_filter(
            $this->autores,
            static fn (Autor $a): bool => $a !== $autor
                && ($a->getId() === null || $a->getId() !== $autor->getId())
        ));
        return $this;
    }

    public function __toString(): string
    {
        return sprintf(
            'Livro#%s "%s" (ISBN %s, %d)',
            $this->id ?? 'novo',
            $this->titulo,
            $this->isbn,
            $this->anoPublicacao
        );
    }
}
