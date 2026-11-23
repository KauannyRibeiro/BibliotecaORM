<?php

declare(strict_types=1);

namespace BibliotecaORM\Infrastructure\Repository;

use BibliotecaORM\Contract\RepositoryInterface;
use BibliotecaORM\Domain\Identificavel;
use InvalidArgumentException;
use LogicException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Mapeador objeto-relacional genérico (padrão Template Method).
 *
 * Comportamentos COMPARTILHADOS (implementados aqui, uma única vez):
 *   buscar, listar (com ORDER BY validado por lista branca), salvar (INSERT/UPDATE),
 *   excluir, transacao() e atribuição controlada do id gerado.
 *
 * O que as subclasses ESPECIALIZAM:
 *   tabela(), colunas(), classeEntidade(), hidratar() e extrair()
 *   + ganchos opcionais: hidratarLote(), persistirRelacionamentos(), removerRelacionamentos().
 *
 * Segurança: valores externos SEMPRE via prepared statements; nomes de tabela/coluna
 * nunca vêm do consumidor — vêm das subclasses e ainda são validados (ident()).
 */
abstract class AbstractRepository implements RepositoryInterface
{
    public function __construct(protected PDO $pdo)
    {
    }

    // ---------------------------------------------------------------- pontos de especialização

    /** Nome da tabela (constante da subclasse). */
    abstract protected function tabela(): string;

    /** @return list<string> Colunas gravadas (lista branca; sem a coluna id). */
    abstract protected function colunas(): array;

    /** @return class-string<Identificavel> */
    abstract protected function classeEntidade(): string;

    /** Converte uma linha do banco em objeto de domínio (hidratação). @param array<string,mixed> $linha */
    abstract public function hidratar(array $linha): object;

    /** Converte a entidade em [coluna => valor] para INSERT/UPDATE. @return array<string,mixed> */
    abstract protected function extrair(object $entidade): array;

    // ---------------------------------------------------------------- ganchos (opcionais)

    /**
     * Hidrata várias linhas. Subclasses podem estender (ex.: carregar relacionamentos em lote).
     *
     * @param list<array<string,mixed>> $linhas
     * @return list<object>
     */
    protected function hidratarLote(array $linhas): array
    {
        return array_values(array_map(fn (array $linha): object => $this->hidratar($linha), $linhas));
    }

    /** Chamado dentro da transação de salvar(), depois do INSERT/UPDATE. */
    protected function persistirRelacionamentos(object $entidade): void
    {
    }

    /** Chamado dentro da transação de excluir(), antes do DELETE. */
    protected function removerRelacionamentos(int $id): void
    {
    }

    // ---------------------------------------------------------------- contrato (RepositoryInterface)

    public function buscar(int $id): ?object
    {
        $sql = sprintf('SELECT * FROM %s WHERE %s = :id', $this->ident($this->tabela()), $this->ident('id'));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $linha = $stmt->fetch();

        return $linha === false ? null : $this->hidratarLote([$linha])[0];
    }

    /**
     * Compatível com listar(): array do contrato (parâmetros extras são opcionais).
     * $ordenarPor só aceita 'id' ou uma coluna conhecida; $direcao só ASC/DESC.
     *
     * @return list<object>
     */
    public function listar(?string $ordenarPor = null, string $direcao = 'ASC'): array
    {
        $coluna = $ordenarPor ?? 'id';
        if (!in_array($coluna, array_merge(['id'], $this->colunas()), true)) {
            throw new InvalidArgumentException('Coluna de ordenação não permitida.');
        }
        $direcao = strtoupper($direcao);
        if (!in_array($direcao, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException('Direção de ordenação inválida (use ASC ou DESC).');
        }

        $sql = sprintf('SELECT * FROM %s ORDER BY %s %s', $this->ident($this->tabela()), $this->ident($coluna), $direcao);
        $linhas = $this->pdo->query($sql)->fetchAll();

        return $this->hidratarLote($linhas);
    }

    public function salvar(object $entidade): object
    {
        $this->garantirTipo($entidade);
        /** @var Identificavel $entidade */
        $idAnterior = $entidade->getId();

        try {
            return $this->transacao(function () use ($entidade): object {
                $dados = $this->extrair($entidade);
                if ($entidade->getId() === null) {
                    $this->inserir($entidade, $dados);
                } else {
                    $this->atualizar($entidade, $dados);
                }
                $this->persistirRelacionamentos($entidade);

                return $entidade;
            });
        } catch (Throwable $e) {
            // Rollback desfez o INSERT: o objeto não pode ficar com um id que não existe no banco.
            if ($idAnterior === null) {
                $this->atribuirId($entidade, null);
            }
            throw $e;
        }
    }

    public function excluir(int $id): bool
    {
        return (bool) $this->transacao(function () use ($id): bool {
            $this->removerRelacionamentos($id);
            $stmt = $this->pdo->prepare(
                sprintf('DELETE FROM %s WHERE %s = :id', $this->ident($this->tabela()), $this->ident('id'))
            );
            $stmt->execute([':id' => $id]);

            return $stmt->rowCount() > 0;
        });
    }

    // ---------------------------------------------------------------- infraestrutura compartilhada

    /**
     * Executa $operacao atomicamente. Se já houver transação aberta (ex.: do chamador),
     * participa dela; quem abriu é quem faz commit/rollback.
     */
    protected function transacao(callable $operacao): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $operacao();
        }

        $this->pdo->beginTransaction();
        try {
            $resultado = $operacao();
            $this->pdo->commit();

            return $resultado;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Atribui o id gerado pelo banco. A entidade não tem setId(); aqui o closure é executado
     * no escopo da própria classe da entidade, de modo que SOMENTE a infraestrutura
     * consegue gravar o identificador.
     */
    protected function atribuirId(object $entidade, ?int $id): void
    {
        (function (?int $novoId): void {
            $this->id = $novoId;
        })->call($entidade, $id);
    }

    /** @param array<string,mixed> $dados */
    private function inserir(object $entidade, array $dados): void
    {
        $colunas = $this->validarColunas(array_keys($dados));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->ident($this->tabela()),
            implode(', ', array_map(fn (string $c): string => $this->ident($c), $colunas)),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $colunas))
        );
        $this->pdo->prepare($sql)->execute($this->parametros($dados));
        $this->atribuirId($entidade, (int) $this->pdo->lastInsertId());
    }

    /** @param array<string,mixed> $dados */
    private function atualizar(object $entidade, array $dados): void
    {
        /** @var Identificavel $entidade */
        $colunas = $this->validarColunas(array_keys($dados));
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :id',
            $this->ident($this->tabela()),
            implode(', ', array_map(fn (string $c): string => $this->ident($c) . ' = :' . $c, $colunas)),
            $this->ident('id')
        );
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->parametros($dados) + [':id' => $entidade->getId()]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException(
                sprintf('Registro %d não encontrado em "%s"; nada foi atualizado.', $entidade->getId(), $this->tabela())
            );
        }
    }

    /**
     * @param array<string,mixed> $dados
     * @return array<string,mixed>
     */
    private function parametros(array $dados): array
    {
        $parametros = [];
        foreach ($dados as $coluna => $valor) {
            $parametros[':' . $coluna] = $valor;
        }

        return $parametros;
    }

    /**
     * @param list<string> $colunas
     * @return list<string>
     */
    private function validarColunas(array $colunas): array
    {
        foreach ($colunas as $coluna) {
            if (!in_array($coluna, $this->colunas(), true)) {
                throw new LogicException(sprintf('Coluna "%s" não está na lista branca do repositório.', $coluna));
            }
        }

        return $colunas;
    }

    private function garantirTipo(object $entidade): void
    {
        $classe = $this->classeEntidade();
        if (!$entidade instanceof $classe) {
            throw new InvalidArgumentException(
                sprintf('%s só persiste instâncias de %s (recebido: %s).', static::class, $classe, $entidade::class)
            );
        }
    }

    /**
     * Valida e protege um identificador SQL (tabela/coluna). Aceita apenas [A-Za-z_][A-Za-z0-9_]*,
     * então nada com espaço, ponto, aspas ou ponto e vírgula chega ao SQL.
     */
    protected function ident(string $nome): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $nome) !== 1) {
            throw new InvalidArgumentException('Identificador SQL inválido.');
        }

        return '`' . $nome . '`';
    }
}
