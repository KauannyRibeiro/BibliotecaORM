<?php

declare(strict_types=1);

/**
 * Testes sem dependências externas.  Uso: php tests/run_tests.php
 * - Testes de domínio: não precisam de banco.
 * - Testes de integração: precisam do MySQL configurado; são PULADOS se não conectar.
 * Usam ISBNs 97811111111xx e limpam o que criam.
 */

require __DIR__ . '/../src/autoload.php';

use BibliotecaORM\Contract\RepositoryInterface;
use BibliotecaORM\Domain\Autor;
use BibliotecaORM\Domain\Livro;
use BibliotecaORM\Infrastructure\Config;
use BibliotecaORM\Infrastructure\Connection;
use BibliotecaORM\Infrastructure\Repository\AbstractRepository;
use BibliotecaORM\Infrastructure\Repository\AutorRepository;
use BibliotecaORM\Infrastructure\Repository\LivroRepository;

$placar = ['ok' => 0, 'falha' => 0];

function teste(string $nome, callable $corpo): void
{
    global $placar;
    try {
        $corpo();
        echo "[OK]     $nome" . PHP_EOL;
        $placar['ok']++;
    } catch (Throwable $e) {
        echo "[FALHA]  $nome -> " . $e::class . ': ' . $e->getMessage() . PHP_EOL;
        $placar['falha']++;
    }
}

function afirmar(bool $condicao, string $mensagem = 'Afirmação falhou'): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

function lanca(string $classe, callable $corpo): void
{
    try {
        $corpo();
    } catch (Throwable $e) {
        if ($e instanceof $classe) {
            return;
        }
        throw new RuntimeException("Esperava $classe, mas veio " . $e::class . ': ' . $e->getMessage());
    }
    throw new RuntimeException("Esperava $classe, mas nada foi lançado");
}

// ======================================================================= DOMÍNIO (sem banco)

teste('Autor rejeita nome vazio e nome acima de 150 caracteres', function () {
    lanca(InvalidArgumentException::class, fn () => new Autor('   '));
    lanca(InvalidArgumentException::class, fn () => new Autor(str_repeat('a', 151)));
});

teste('Livro valida ISBN e normaliza hífens', function () {
    lanca(InvalidArgumentException::class, fn () => new Livro('X', '123', 2000));
    $livro = new Livro('X', '978-0-00-000000-1', 2000);
    afirmar($livro->getIsbn() === '9780000000001', 'ISBN deveria ser normalizado');
});

teste('Livro valida ano de publicação', function () {
    lanca(InvalidArgumentException::class, fn () => new Livro('X', '9780000000001', 1200));
    lanca(InvalidArgumentException::class, fn () => new Livro('X', '9780000000001', 3000));
});

teste('Identificador é imutável para o consumidor (sem setId, propriedade privada)', function () {
    foreach ([Livro::class, Autor::class] as $classe) {
        afirmar(!method_exists($classe, 'setId'), "$classe não pode ter setId()");
        afirmar((new ReflectionProperty($classe, 'id'))->isPrivate(), "$classe::\$id deve ser privada");
    }
    lanca(Error::class, function () {
        $a = new Autor('Fulano');
        $a->id = 10; // acesso a propriedade privada
    });
});

teste('Livro não duplica autor (mesma instância ou mesmo id)', function () {
    $livro = new Livro('X', '9780000000001', 2000);
    $a = new Autor('Fulano', null, 5);
    $livro->adicionarAutor($a)->adicionarAutor($a)->adicionarAutor(new Autor('Fulano', null, 5));
    afirmar(count($livro->getAutores()) === 1, 'Esperava 1 autor');
});

teste('Coleção devolvida por getAutores() é cópia (não altera o estado interno)', function () {
    $livro = new Livro('X', '9780000000001', 2000);
    $copia = $livro->getAutores();
    $copia[] = new Autor('Intruso');
    afirmar(count($livro->getAutores()) === 0, 'Estado interno foi alterado');
});

teste('Repositórios concretos estendem AbstractRepository e implementam o contrato', function () {
    foreach ([LivroRepository::class, AutorRepository::class] as $classe) {
        afirmar(is_subclass_of($classe, AbstractRepository::class), "$classe deve estender AbstractRepository");
        afirmar(is_subclass_of($classe, RepositoryInterface::class), "$classe deve implementar RepositoryInterface");
    }
});

// ======================================================================= INTEGRAÇÃO (MySQL)

try {
    $pdo = Connection::criar(Config::carregar(dirname(__DIR__)));
} catch (Throwable $e) {
    $pdo = null;
    echo '[PULADO] Testes de integração: sem conexão com o MySQL (' . trim(strtok($e->getMessage(), "\n")) . ')' . PHP_EOL;
}

if ($pdo !== null) {
    $autores = new AutorRepository($pdo);
    $livros = new LivroRepository($pdo, $autores);
    $isbns = ['9781111111101', '9781111111102', '9781111111103', '9781111111104'];

    $limpar = function () use ($livros, $isbns): void {
        foreach ($isbns as $isbn) {
            $l = $livros->buscarPorIsbn($isbn);
            if ($l !== null) {
                $livros->excluir($l->getId());
            }
        }
    };
    $limpar();

    teste('CRUD de Livro: INSERT, SELECT, UPDATE, listagem e DELETE', function () use ($livros, $isbns) {
        $livro = new Livro('Teste CRUD', $isbns[0], 2001);
        $livros->salvar($livro);
        afirmar(is_int($livro->getId()) && $livro->getId() > 0, 'id deveria ser gerado');

        $lido = $livros->buscar($livro->getId());
        afirmar($lido instanceof Livro && $lido !== $livro, 'Deveria hidratar nova instância');
        afirmar($lido->getTitulo() === 'Teste CRUD' && $lido->getAnoPublicacao() === 2001, 'Propriedades divergem');
        afirmar(is_int($lido->getAnoPublicacao()), 'ano deveria ser int');

        $livro->setTitulo('Teste CRUD 2');
        $livros->salvar($livro);
        afirmar($livros->buscar($livro->getId())->getTitulo() === 'Teste CRUD 2', 'UPDATE não refletiu');

        $ids = array_map(fn (Livro $l) => $l->getId(), $livros->listar());
        afirmar(in_array($livro->getId(), $ids, true), 'listar() não retornou o livro');

        afirmar($livros->excluir($livro->getId()) === true, 'excluir deveria retornar true');
        afirmar($livros->buscar($livro->getId()) === null, 'livro ainda existe');
    });

    teste('Autor: INSERT e SELECT com tipos preservados', function () use ($autores) {
        $a = new Autor('Autor Teste', null);
        $autores->salvar($a);
        $lido = $autores->buscar($a->getId());
        afirmar($lido !== null && $lido->getNome() === 'Autor Teste', 'nome divergente');
        afirmar($lido->getNacionalidade() === null, 'nacionalidade deveria ser null');
        $autores->excluir($a->getId());
    });

    teste('N:N: persiste 2 autores, reconstrói coleção e reutiliza a mesma instância', function () use ($livros, $autores, $isbns) {
        $a1 = $autores->salvar(new Autor('Coautor A'));
        $a2 = $autores->salvar(new Autor('Coautor B'));
        $l1 = (new Livro('NN 1', $isbns[0], 2002))->adicionarAutor($a1)->adicionarAutor($a2);
        $l2 = (new Livro('NN 2', $isbns[1], 2003))->adicionarAutor($a1);
        $livros->salvar($l1);
        $livros->salvar($l2);

        $rec = $livros->buscar($l1->getId());
        afirmar(count($rec->getAutores()) === 2, 'Esperava 2 autores');

        $instancias = [];
        foreach ($livros->listar() as $l) {
            foreach ($l->getAutores() as $autor) {
                if ($autor->getId() === $a1->getId()) {
                    $instancias[spl_object_id($autor)] = true;
                }
            }
        }
        afirmar(count($instancias) === 1, 'Autor duplicado em memória: ' . count($instancias) . ' instâncias');

        $livros->excluir($l1->getId());
        $livros->excluir($l2->getId());
        $autores->excluir($a1->getId());
        $autores->excluir($a2->getId());
    });

    teste('Transação: falha no vínculo desfaz o INSERT do livro (rollback)', function () use ($livros, $pdo, $isbns) {
        $livro = new Livro('Deve falhar', $isbns[2], 2004);
        $livro->adicionarAutor(new Autor('Fantasma', null, 999999999));
        lanca(PDOException::class, fn () => $livros->salvar($livro));
        afirmar($livros->buscarPorIsbn($isbns[2]) === null, 'Livro sobreviveu ao rollback');
        afirmar($livro->getId() === null, 'id deveria ter sido revertido para null');
        afirmar(!$pdo->inTransaction(), 'Não deveria haver transação aberta');
    });

    teste('Integridade referencial: autor vinculado não pode ser excluído', function () use ($livros, $autores, $isbns) {
        $a = $autores->salvar(new Autor('Autor Vinculado'));
        $l = $livros->salvar((new Livro('Com vínculo', $isbns[3], 2005))->adicionarAutor($a));
        lanca(PDOException::class, fn () => $autores->excluir($a->getId()));
        $livros->excluir($l->getId());
        afirmar($autores->excluir($a->getId()) === true, 'Após remover vínculos deveria excluir');
    });

    teste('Segurança: ORDER BY fora da lista branca / injeção é rejeitado', function () use ($livros) {
        lanca(InvalidArgumentException::class, fn () => $livros->listar('id; DROP TABLE livros'));
        lanca(InvalidArgumentException::class, fn () => $livros->listar('titulo', 'ASC; --'));
        afirmar(is_array($livros->listar('titulo', 'DESC')), 'ordenação válida deveria funcionar');
    });

    teste('Segurança: valor malicioso vira dado, não SQL (prepared statement)', function () use ($livros, $isbns) {
        $l = $livros->salvar(new Livro("Robert'); DROP TABLE livros;--", $isbns[0], 2006));
        $lido = $livros->buscar($l->getId());
        afirmar($lido->getTitulo() === "Robert'); DROP TABLE livros;--", 'Título deveria ser gravado literalmente');
        $livros->excluir($l->getId());
    });

    teste('Polimorfismo: uma função aceita RepositoryInterface para ambos os repositórios', function () use ($livros, $autores, $isbns) {
        $ciclo = function (RepositoryInterface $repo, object $e): bool {
            $salvo = $repo->salvar($e);
            $achou = $repo->buscar($salvo->getId()) !== null;
            $removeu = $repo->excluir($salvo->getId());
            return $achou && $removeu && $repo->buscar($salvo->getId()) === null;
        };
        afirmar($ciclo($livros, new Livro('Poli', $isbns[0], 2007)), 'ciclo falhou para LivroRepository');
        afirmar($ciclo($autores, new Autor('Poli')), 'ciclo falhou para AutorRepository');
    });

    $limpar();
}

// ======================================================================= resumo
echo PHP_EOL . sprintf('Resultado: %d ok, %d falha(s)', $placar['ok'], $placar['falha']) . PHP_EOL;
exit($placar['falha'] > 0 ? 1 : 0);
