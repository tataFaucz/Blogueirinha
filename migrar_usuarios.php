<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/conexao.php';

$arquivo = __DIR__ . '/autenticacao.txt';
if (!is_file($arquivo) || !is_readable($arquivo)) {
    fwrite(STDERR, "Arquivo autenticacao.txt não encontrado ou sem permissão de leitura.\n");
    exit(1);
}

$linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($linhas === false) {
    fwrite(STDERR, "Não foi possível ler autenticacao.txt.\n");
    exit(1);
}

try {
    $pdo = conectarBanco();
    $inserir = $pdo->prepare(
        'INSERT IGNORE INTO leitores (nome, email, senha)
         VALUES (:nome, :email, :senha)'
    );
    $importados = 0;
    $ignorados = 0;

    foreach ($linhas as $numero => $linha) {
        $usuario = json_decode($linha, true);
        if (
            !is_array($usuario)
            || !is_string($usuario['nome'] ?? null)
            || !is_string($usuario['email'] ?? null)
            || !filter_var($usuario['email'], FILTER_VALIDATE_EMAIL)
            || !is_string($usuario['senha'] ?? null)
            || password_get_info($usuario['senha'])['algo'] === null
        ) {
            $ignorados++;
            fwrite(STDERR, 'Linha ' . ($numero + 1) . " ignorada por estar inválida.\n");
            continue;
        }

        $inserir->execute([
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'senha' => $usuario['senha'],
        ]);
        if ($inserir->rowCount() > 0) {
            $importados++;
        } else {
            $ignorados++;
        }
    }

    echo "Migração concluída: {$importados} usuário(s) importado(s), {$ignorados} ignorado(s).\n";
} catch (PDOException $erro) {
    fwrite(STDERR, "Falha na migração. Confira se database.sql foi importado e se a conexão está configurada.\n");
    exit(1);
}
