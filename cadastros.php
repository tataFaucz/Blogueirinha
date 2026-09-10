<?php
declare(strict_types=1);
require_once __DIR__ . '/conexao.php';

try {
    $leitores = conectarBanco()->query(
        'SELECT id, nome, nome_usuario FROM leitores ORDER BY nome, id'
    )->fetchAll();
} catch (PDOException $erro) {
    http_response_code(500);
    $erroBanco = true;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastros - Blogoteca</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-blog">
    <main class="container py-5">
        <section class="card border-0 shadow-lg rounded-4 p-4 p-md-5 bg-white">
            <h1 class="blog-title text-primary mb-3">Pessoas cadastradas</h1>
            <p>Consulte os perfis públicos. Cada pessoa pode alterar ou excluir somente a própria conta.</p>
            <?php if (isset($_GET['excluido'])): ?>
                <div class="alert alert-success" role="status">Cadastro excluído com sucesso.</div>
            <?php endif; ?>
            <?php if (!empty($erroBanco)): ?>
                <div class="alert alert-danger" role="alert">
                    Não foi possível consultar os cadastros. Confira a conexão e importe o arquivo database.sql.
                </div>
            <?php elseif ($leitores === []): ?>
                <div class="alert alert-info" role="status">Ainda não há pessoas cadastradas.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead><tr>
                            <th scope="col">Nome</th><th scope="col">Usuário</th>
                            <th scope="col">Perfil público</th>
                            <th scope="col"><span class="visually-hidden">Ações</span></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($leitores as $leitor): ?>
                            <tr>
                                <td><?= e((string) $leitor['nome']) ?></td>
                                <td><?= e((string) ($leitor['nome_usuario'] ?? '—')) ?></td>
                                <td><a href="perfil.php?id=<?= (int) $leitor['id'] ?>">Ver perfil</a></td>
                                <td class="text-nowrap">
                                    <?php if (isset($_SESSION['leitor_id']) && (int) $_SESSION['leitor_id'] === (int) $leitor['id']): ?>
                                        <a class="btn btn-sm btn-outline-primary mb-1" href="editar_cadastro.php?id=<?= (int) $leitor['id'] ?>">Alterar meus dados</a>
                                        <form action="processar_cadastro.php" method="post" class="d-inline" onsubmit="return confirm('Excluir sua conta e suas publicações? Esta ação não pode ser desfeita.');">
                                            <input type="hidden" name="csrf_token" value="<?= e(tokenCsrf()) ?>">
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="id" value="<?= (int) $leitor['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger mb-1" type="submit">Excluir minha conta</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <div class="d-flex gap-2 flex-wrap mt-3">
                <a class="btn btn-primary" href="cadastro.html">Novo cadastro</a>
                <a class="btn btn-outline-primary" href="index.php">Início</a>
            </div>
        </section>
    </main>
</body>
</html>
