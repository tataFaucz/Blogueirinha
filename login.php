<?php
declare(strict_types=1);
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/autenticacao_helpers.php';

$mensagem = '';
$sucesso = false;
$falhaBanco = false;
$falhaArquivo = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailRecebido = $_POST['email'] ?? '';
    $senhaRecebida = $_POST['senha'] ?? '';
    $email = is_string($emailRecebido) ? trim($emailRecebido) : '';
    $senha = is_string($senhaRecebida) ? $senhaRecebida : '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
        $mensagem = 'Informe um e-mail e uma senha válidos.';
    } else {
        $leitor = null;
        try {
            $consulta = conectarBanco()->prepare(
                'SELECT id, nome, email, senha FROM leitores WHERE email = :email LIMIT 1'
            );
            $consulta->execute(['email' => $email]);
            $leitor = $consulta->fetch();
        } catch (PDOException $erro) {
            error_log('Falha ao consultar usuário no banco durante o login: ' . $erro->getMessage());
            $falhaBanco = true;
        }

        if ($leitor && password_verify($senha, (string) $leitor['senha'])) {
            session_regenerate_id(true);
            $_SESSION['leitor_id'] = (int) $leitor['id'];
            $_SESSION['leitor_nome'] = (string) $leitor['nome'];
            $sucesso = true;
            $mensagem = 'Login validado com sucesso. Bem-vindo(a), ' . (string) $leitor['nome'] . '!';
        } else {
            try {
                $nomeArquivo = autenticarUsuarioArquivo($email, $senha);
            } catch (RuntimeException $erro) {
                error_log('Falha ao autenticar usuário pelo arquivo: ' . $erro->getMessage());
                $falhaArquivo = true;
                $nomeArquivo = null;
            }

            if ($nomeArquivo !== null) {
                session_regenerate_id(true);
                if ($leitor) {
                    $_SESSION['leitor_id'] = (int) $leitor['id'];
                } else {
                    unset($_SESSION['leitor_id']);
                }
                $_SESSION['leitor_nome'] = $nomeArquivo;
                $sucesso = true;
                $mensagem = $leitor
                    ? 'Login validado pelo arquivo autenticacao.txt. Bem-vindo(a), ' . $nomeArquivo . '!'
                    : 'A conta foi autenticada, mas precisa ser importada para o banco para publicar e usar as funções sociais.';
            } elseif ($falhaArquivo) {
                http_response_code(500);
                $mensagem = 'Não foi possível validar o arquivo autenticacao.txt. Confira se ele existe, está legível e contém registros válidos.';
            } elseif ($falhaBanco) {
                http_response_code(503);
                $mensagem = 'O banco de dados está indisponível e as credenciais não foram encontradas em autenticacao.txt.';
            } else {
                $mensagem = 'Usuário ou senha incorretos.';
            }
        }
    }
}
if ($sucesso && isset($_SESSION['leitor_id'])) {
    header('Location: feed.php', true, 303);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Blogoteca</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-blog">
    <main class="container py-5">
        <section class="card p-4 p-md-5 mx-auto" style="max-width: 560px;">
            <h1 class="blog-title text-primary mb-3">Login da Blogoteca</h1>
            <?php if ($mensagem !== ''): ?>
                <div class="alert <?= $sucesso ? 'alert-success' : 'alert-danger' ?>" role="alert"><?= e($mensagem) ?></div>
            <?php endif; ?>
            <form method="post" class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-bold" for="email">E-mail</label>
                    <input class="form-control" type="email" id="email" name="email" autocomplete="email" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold" for="senha">Senha</label>
                    <input class="form-control" type="password" id="senha" name="senha" autocomplete="current-password" required>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Entrar</button>
                    <button class="btn btn-outline-primary" type="reset">Limpar</button>
                </div>
            </form>
            <p class="mt-4 mb-0">
                <a href="cadastro.html">Ainda não tenho cadastro</a> |
                <a href="feed.php">Feed</a> |
                <a href="comunidades.php">Comunidades</a> |
                <a href="cadastros.php">Pessoas</a> |
                <a href="index.php">Início</a>
            </p>
        </section>
    </main>
</body>
</html>
