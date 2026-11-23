<?php

declare(strict_types=1);

namespace BibliotecaORM\Infrastructure;

/**
 * Lê a configuração de variáveis de ambiente e, opcionalmente, de um arquivo .env local
 * (fora do Git). Variáveis de ambiente reais têm precedência sobre o .env.
 * Nenhuma credencial é embutida no código: o padrão de DB_PASS é vazio.
 */
final class Config
{
    /** @return array{host:string,port:int,name:string,user:string,pass:string} */
    public static function carregar(string $raizProjeto): array
    {
        $env = self::lerArquivoEnv(rtrim($raizProjeto, '/\\') . DIRECTORY_SEPARATOR . '.env');

        $obter = static function (string $chave, string $padrao) use ($env): string {
            $valor = getenv($chave);
            if ($valor !== false && $valor !== '') {
                return $valor;
            }
            return $env[$chave] ?? $padrao;
        };

        return [
            'host' => $obter('DB_HOST', '127.0.0.1'),
            'port' => (int) $obter('DB_PORT', '3306'),
            'name' => $obter('DB_NAME', 'biblioteca_orm'),
            'user' => $obter('DB_USER', 'biblioteca_app'),
            'pass' => $obter('DB_PASS', ''),
        ];
    }

    /** @return array<string,string> */
    private static function lerArquivoEnv(string $caminho): array
    {
        if (!is_file($caminho)) {
            return [];
        }
        $resultado = [];
        $linhas = file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if ($linha === '' || $linha[0] === '#' || !str_contains($linha, '=')) {
                continue;
            }
            [$chave, $valor] = explode('=', $linha, 2);
            $resultado[trim($chave)] = trim(trim($valor), "\"'");
        }
        return $resultado;
    }
}
