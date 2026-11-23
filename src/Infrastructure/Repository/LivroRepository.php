<?php

declare(strict_types=1);

namespace BibliotecaORM\Infrastructure\Repository;

use BibliotecaORM\Domain\Autor;
use BibliotecaORM\Domain\Livro;
use DomainException;
use PDO;

/**
 * Repositório concreto de Livro. Além de tabela/mapeamento/hidratação, gerencia o
 * relacionamento N:N via livros_autores (dentro da mesma transação do livro).
 */
final class LivroRepository extends AbstractRepository
{
    public const TABELA = 'livros';
    private const TABELA_VINCULO = 'livros_autores';

    private AutorRepository $autores;

    public function __construct(PDO $pdo, ?AutorRepository $autores = null)
    {
        parent::__construct($pdo);
        $this->autores = $autores ?? new AutorRepository($pdo);
    }

    protected function tabela(): string
    {
        return self::TABELA;
    }

    protected function colunas(): array
    {
        return ['titulo', 'isbn', 'ano_publicacao'];
    }

    protected function classeEntidade(): string
    {
        return Livro::class;
    }

    /** Hidrata o livro SEM autores; a coleção é montada em hidratarLote(). */
    public function hidratar(array $linha): Livro
    {
        return new Livro(
            (string) $linha['titulo'],
            (string) $linha['isbn'],
            (int) $linha['ano_publicacao'],
            (int) $linha['id']
        );
    }

    protected function extrair(object $entidade): array
    {
        /** @var Livro $entidade */
        return [
            'titulo'         => $entidade->getTitulo(),
            'isbn'           => $entidade->getIsbn(),
            'ano_publicacao' => $entidade->getAnoPublicacao(),
        ];
    }

    public function buscar(int $id): ?Livro
    {
        return parent::buscar($id);
    }

    public function buscarPorIsbn(string $isbn): ?Livro
    {
        $stmt = $this->pdo->prepare(
            sprintf('SELECT * FROM %s WHERE %s = :isbn', $this->ident(self::TABELA), $this->ident('isbn'))
        );
        $stmt->execute([':isbn' => Livro::normalizarIsbn($isbn)]);
        $linha = $stmt->fetch();

        return $linha === false ? null : $this->hidratarLote([$linha])[0];
    }

    // ---------------------------------------------------------------- N:N

    /**
     * Hidrata os livros e carrega TODOS os autores deles com UMA consulta (evita N+1).
     * Um mapa de identidade local garante que o mesmo autor, em vários livros do mesmo
     * carregamento, seja UMA única instância (sem objetos duplicados).
     */
    protected function hidratarLote(array $linhas): array
    {
        $livros = parent::hidratarLote($linhas);
        $this->carregarAutores($livros);

        return $livros;
    }

    /** @param list<Livro> $livros */
    private function carregarAutores(array $livros): void
    {
        if ($livros === []) {
            return;
        }

        $porId = [];
        foreach ($livros as $livro) {
            $porId[$livro->getId()] = $livro;
        }
        $ids = array_keys($porId);

        // Só marcadores "?" são gerados dinamicamente; os valores vão por bind.
        $marcadores = implode(', ', array_fill(0, count($ids), '?'));
        $sql = sprintf(
            'SELECT la.livro_id, a.* FROM %s la INNER JOIN %s a ON a.id = la.autor_id '
            . 'WHERE la.livro_id IN (%s) ORDER BY la.livro_id, a.id',
            $this->ident(self::TABELA_VINCULO),
            $this->ident(AutorRepository::TABELA),
            $marcadores
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($ids);

        /** @var array<int,Autor> $mapaIdentidade */
        $mapaIdentidade = [];
        foreach ($stmt->fetchAll() as $linha) {
            $autorId = (int) $linha['id'];
            $mapaIdentidade[$autorId] ??= $this->autores->hidratar($linha);
            $porId[(int) $linha['livro_id']]->adicionarAutor($mapaIdentidade[$autorId]);
        }
    }

    /** Sincroniza os vínculos com a coleção atual do livro (mesma transação do livro). */
    protected function persistirRelacionamentos(object $entidade): void
    {
        /** @var Livro $entidade */
        $this->removerRelacionamentos((int) $entidade->getId());

        $insere = $this->pdo->prepare(sprintf(
            'INSERT INTO %s (%s, %s) VALUES (:livro, :autor)',
            $this->ident(self::TABELA_VINCULO),
            $this->ident('livro_id'),
            $this->ident('autor_id')
        ));

        foreach ($entidade->getAutores() as $autor) {
            if ($autor->getId() === null) {
                throw new DomainException('Salve o autor antes de vinculá-lo a um livro.');
            }
            // Se o autor não existir, a FK falha aqui => a transação inteira sofre rollback.
            $insere->execute([':livro' => $entidade->getId(), ':autor' => $autor->getId()]);
        }
    }

    protected function removerRelacionamentos(int $id): void
    {
        $stmt = $this->pdo->prepare(sprintf(
            'DELETE FROM %s WHERE %s = :livro',
            $this->ident(self::TABELA_VINCULO),
            $this->ident('livro_id')
        ));
        $stmt->execute([':livro' => $id]);
    }
}
