<?php
declare(strict_types=1);

function autenticarUsuarioArquivo(string $email, string $senha, ?string $caminho = null): ?string
{
    $email = trim($email);
    $caminho ??= __DIR__ . '/autenticacao.txt';
    if (!is_file($caminho) || !is_readable($caminho)) {
        throw new RuntimeException('O arquivo autenticacao.txt não existe ou não pode ser lido.');
    }

    $linhas = file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($linhas === false) {
        throw new RuntimeException('Não foi possível ler o arquivo autenticacao.txt.');
    }

    foreach ($linhas as $indice => $linha) {
        $usuario = json_decode($linha, true);
        if (
            !is_array($usuario)
            || !is_string($usuario['nome'] ?? null)
            || trim($usuario['nome']) === ''
            || !is_string($usuario['email'] ?? null)
            || !filter_var($usuario['email'], FILTER_VALIDATE_EMAIL)
            || !is_string($usuario['senha'] ?? null)
            || password_get_info($usuario['senha'])['algo'] === null
        ) {
            throw new RuntimeException('Registro inválido na linha ' . ($indice + 1) . ' do arquivo autenticacao.txt.');
        }

        if (strcasecmp($usuario['email'], $email) === 0 && password_verify($senha, $usuario['senha'])) {
            return $usuario['nome'];
        }
    }

    return null;
}
