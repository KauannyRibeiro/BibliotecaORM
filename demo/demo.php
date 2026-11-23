<?php

declare(strict_types=1);

/**
 * Demonstração reproduzível das 7 evidências exigidas.
 * Uso:  php demo/demo.php            (ou:  php demo/demo.php | tee docs/evidencias.txt)
 * Pré-requisito: schema.sql aplicado e .env (ou variáveis de ambiente) configurado.
 */

require __DIR__ . '/../src/autoload.php';

use BibliotecaORM\Contract\RepositoryInterface;
use BibliotecaORM\Domain\Autor;
use BibliotecaORM\Domain\Livro;
use BibliotecaORM\Infrastructure\Config;
use BibliotecaORM\Infrastructure\Connection;
use BibliotecaORM\Infrastructure\Repository\AutorRepository;
use BibliotecaORM\Infrastructure\Repository\LivroRepository;

const ISBN_1 = '9780000000001';
const ISBN_2 = '9780000000002';
const ISBN_POLI = '9780000000003';
const ISBN_FALHA = '9780000000004';

function secao(string $titulo): void
{
    echo PHP_EOL . str_repeat('=', 74) . PHP_EOL . $titulo . PHP_EOL . str_repeat('=', 74) . PHP_EOL;
}

function linha(string $texto): void
{
    echo '  ' . $texto . PHP_EOL;
}

function primeiraLinha(string $texto): string
{
    return trim(strtok($texto, "\n") ?: $texto);
}

/**
 * EVIDÊNCIA 5 — polimorfismo: recebe o CONTRATO, não uma classe concreta.
 * Nenhum if/instanceof: o mesmo código opera LivroRepository e AutorRepository.
 */
function demonstrarContrato(RepositoryInterface $repo, object $novaEntidade): void
{
    linha('Repositório recebido: ' . (new ReflectionClass($repo))->getShortName());

    $salvo = $repo->salvar($novaEntidade);                 // INSERT
    linha('salvar()  => ' . $salvo);

    $lido = $repo->buscar($salvo->getId());                // SELECT por id
    linha('buscar()  => ' . $lido);

    linha('listar()  => ' . count($repo->listar()) . ' registro(s) na tabela');

    linha('excluir() => ' . var_export($repo->excluir($salvo->getId()), true));  // DELETE
    linha('buscar() após excluir => ' . var_export($repo->buscar($salvo->getId()), true));
}

// ------------------------------------------------------------------ conexão
try {
    $pdo = Connection::criar(Config::carregar(dirname(__DIR__)));
} catch (PDOException $e) {
    fwrite(STDERR, 'Falha ao conectar: ' . primeiraLinha($e->getMessage()) . PHP_EOL);
    fwrite(STDERR, 'Verifique o .env (veja .env.example) e se o schema.sql foi aplicado.' . PHP_EOL);
    exit(1);
}

$autores = new AutorRepository($pdo);
$livros = new LivroRepository($pdo, $autores);

/** Remove resíduos de execuções anteriores (demo reexecutável). */
function limparResiduos(LivroRepository $livros): void
{
    foreach ([ISBN_1, ISBN_2, ISBN_POLI, ISBN_FALHA] as $isbn) {
        $existente = $livros->buscarPorIsbn($isbn);
        if ($existente !== null) {
            $livros->excluir($existente->getId());
        }
    }
}

limparResiduos($livros);

try {
    // ------------------------------------------------------------------ 1
    secao('1. Cadastro de Autor e Livro (ids gerados pelo MySQL)');
    $a1 = new Autor('Machado de Assis', 'Brasileira');
    $a2 = new Autor('Clarice Lispector', 'Brasileira');
    linha('Antes do INSERT: id do autor 1 = ' . var_export($a1->getId(), true));
    $autores->salvar($a1);
    $autores->salvar($a2);
    linha('Depois: ' . $a1);
    linha('Depois: ' . $a2);

    $livro = new Livro('Dom Casmurro', ISBN_1, 1899);
    linha('Antes do INSERT: id do livro = ' . var_export($livro->getId(), true));
    $livros->salvar($livro);
    linha('Depois: ' . $livro);

    // ------------------------------------------------------------------ 2
    secao('2. Busca por ID e reconstrução das propriedades (hidratação)');
    $carregado = $livros->buscar($livro->getId());
    linha('Classe ........: ' . $carregado::class);
    linha('id ............: ' . var_export($carregado->getId(), true) . ' [' . get_debug_type($carregado->getId()) . ']');
    linha('titulo ........: ' . var_export($carregado->getTitulo(), true) . ' [' . get_debug_type($carregado->getTitulo()) . ']');
    linha('isbn ..........: ' . var_export($carregado->getIsbn(), true) . ' [' . get_debug_type($carregado->getIsbn()) . ']');
    linha('anoPublicacao .: ' . var_export($carregado->getAnoPublicacao(), true) . ' [' . get_debug_type($carregado->getAnoPublicacao()) . ']');
    linha('Mesmo id, objeto novo (nova instância): ' . var_export($carregado !== $livro && $carregado->getId() === $livro->getId(), true));
    linha('buscar(id inexistente) => ' . var_export($livros->buscar(999999999), true));

    // ------------------------------------------------------------------ 3
    secao('3. Atualização e nova consulta');
    $livro->setTitulo('Dom Casmurro (edição revista)');
    $livro->setAnoPublicacao(1900);
    $livros->salvar($livro);                                // UPDATE
    $relido = $livros->buscar($livro->getId());
    linha('Relido do banco: ' . $relido);

    // ------------------------------------------------------------------ 4
    secao('4. Relacionamento N:N — dois autores em um livro + coleção recuperada');
    $livro->adicionarAutor($a1)->adicionarAutor($a2)->adicionarAutor($a1); // duplicata ignorada
    linha('Autores na memória (após adicionar $a1 duas vezes): ' . count($livro->getAutores()));
    $livros->salvar($livro);                                // UPDATE + vínculos, em transação

    $recuperado = $livros->buscar($livro->getId());
    linha('Autores recuperados de "' . $recuperado->getTitulo() . '":');
    foreach ($recuperado->getAutores() as $autor) {
        linha('  - ' . $autor);
    }

    $livro2 = new Livro('Memórias Póstumas de Brás Cubas', ISBN_2, 1881);
    $livro2->adicionarAutor($a1);
    $livros->salvar($livro2);

    $lista = array_filter(
        $livros->listar(),
        static fn (Livro $l): bool => in_array($l->getIsbn(), [ISBN_1, ISBN_2], true)
    );
    $instancias = [];
    foreach ($lista as $l) {
        foreach ($l->getAutores() as $autor) {
            if ($autor->getId() === $a1->getId()) {
                $instancias[spl_object_id($autor)] = true;
            }
        }
    }
    linha('Autor #' . $a1->getId() . ' aparece em 2 livros no mesmo listar(); instâncias distintas: '
        . count($instancias) . ' (esperado: 1 — sem duplicação)');

    // ------------------------------------------------------------------ 5
    secao('5. Polimorfismo — o MESMO método com os dois repositórios');
    demonstrarContrato($livros, new Livro('Livro Polimórfico', ISBN_POLI, 2020));
    echo PHP_EOL;
    demonstrarContrato($autores, new Autor('Autor Polimórfico', 'Qualquer'));

    // ------------------------------------------------------------------ 6
    secao('6. Erro intencional dentro da transação => ROLLBACK');
    $antes = count($livros->listar());
    $falho = new Livro('Livro que deve falhar', ISBN_FALHA, 2024);
    $falho->adicionarAutor(new Autor('Autor Inexistente', null, 999999999)); // id que não existe em autores
    linha('Livros antes: ' . $antes);
    linha('Salvando livro + vínculo com autor inexistente (INSERT do livro ocorre ANTES do vínculo)...');
    try {
        $livros->salvar($falho);
        linha('ERRO: deveria ter falhado!');
    } catch (Throwable $e) {
        $sqlstate = $e instanceof PDOException ? ' SQLSTATE ' . $e->getCode() : '';
        linha('Exceção capturada: ' . $e::class . $sqlstate);
        linha('Mensagem: ' . primeiraLinha($e->getMessage()));
    }
    linha('Livros depois: ' . count($livros->listar()) . ' (esperado: ' . $antes . ')');
    linha('Livro existe no banco? ' . var_export($livros->buscarPorIsbn(ISBN_FALHA) !== null, true) . ' (esperado: false)');
    linha('id do objeto após rollback: ' . var_export($falho->getId(), true) . ' (revertido para null)');
    linha('Há transação aberta? ' . var_export($pdo->inTransaction(), true));

    // ------------------------------------------------------------------ 7
    secao('7. Exclusão controlada e restrições de chave estrangeira');
    linha('Tentando excluir o Autor #' . $a1->getId() . ' enquanto vinculado a livros...');
    try {
        $autores->excluir($a1->getId());
        linha('ERRO: deveria ter sido bloqueado!');
    } catch (PDOException $e) {
        linha('Bloqueado pela FK (ON DELETE RESTRICT) — SQLSTATE ' . $e->getCode());
        linha('Mensagem: ' . primeiraLinha($e->getMessage()));
    }
    linha('Autor continua existindo? ' . var_export($autores->buscar($a1->getId()) !== null, true));

    linha('Excluindo os livros (o repositório remove os vínculos e depois o livro, em transação):');
    linha('excluir(livro 1) => ' . var_export($livros->excluir($livro->getId()), true));
    linha('excluir(livro 2) => ' . var_export($livros->excluir($livro2->getId()), true));
    linha('Agora sem vínculos, os autores podem ser excluídos:');
    linha('excluir(autor 1) => ' . var_export($autores->excluir($a1->getId()), true));
    linha('excluir(autor 2) => ' . var_export($autores->excluir($a2->getId()), true));

    secao('Demonstração concluída.');
} finally {
    limparResiduos($livros);
}
