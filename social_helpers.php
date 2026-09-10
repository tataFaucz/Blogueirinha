<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';

const TIPOS_PUBLICACAO = [
    'artigo' => 'Artigo',
    'opiniao' => 'Opinião',
    'diario' => 'Diário',
    'recomendacao' => 'Recomendação',
    'lista' => 'Lista',
];

function exigirUsuario(): int
{
    $usuarioId = $_SESSION['leitor_id'] ?? null;
    if (!is_int($usuarioId) || $usuarioId < 1) {
        header('Location: login.php');
        exit;
    }

    return $usuarioId;
}

function validarCsrfOuEncerrar(): void
{
    if (!validarTokenCsrf()) {
        http_response_code(403);
        exit('A solicitação expirou. Atualize a página e tente novamente.');
    }
}

function definirFlash(string $tipo, string $mensagem): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensagem' => $mensagem];
}

function consumirFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

function redirecionarSocial(string $destino): void
{
    if (!preg_match('/^(feed\.php|comunidades\.php|perfil\.php\?id=[1-9][0-9]*)$/', $destino)) {
        $destino = 'feed.php';
    }

    header('Location: ' . $destino, true, 303);
    exit;
}

function tamanhoTexto(string $texto): int
{
    $tamanho = preg_match_all('/./us', $texto);
    if ($tamanho === false) {
        throw new InvalidArgumentException('O texto contém uma sequência de caracteres inválida.');
    }

    return $tamanho;
}

function interpretarTags(string $texto): array
{
    $tags = [];
    foreach (explode(',', $texto) as $tag) {
        $tag = trim($tag);
        if ($tag !== '') {
            if (tamanhoTexto($tag) > 40) {
                throw new InvalidArgumentException('Cada tag deve ter até 40 caracteres.');
            }
            $chave = function_exists('mb_strtolower') ? mb_strtolower($tag, 'UTF-8') : strtolower($tag);
            if (!isset($tags[$chave])) {
                $tags[$chave] = $tag;
            }
        }
    }
    if (count($tags) > 8) {
        throw new InvalidArgumentException('Use no máximo oito tags em cada publicação.');
    }

    return array_values($tags);
}

function salvarTags(PDO $pdo, int $publicacaoId, array $tags): void
{
    $pdo->prepare('DELETE FROM publicacao_tag WHERE publicacao_id = :id')
        ->execute(['id' => $publicacaoId]);

    $associadas = [];
    foreach ($tags as $tag) {
        $pdo->prepare('INSERT INTO tags (nome) VALUES (:nome) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)')
            ->execute(['nome' => $tag]);
        $tagId = (int) $pdo->lastInsertId();
        if (isset($associadas[$tagId])) {
            continue;
        }
        $pdo->prepare(
            'INSERT INTO publicacao_tag (publicacao_id, tag_id) VALUES (:publicacao_id, :tag_id)'
        )->execute(['publicacao_id' => $publicacaoId, 'tag_id' => $tagId]);
        $associadas[$tagId] = true;
    }
}

function renderizarCabecalhoSocial(string $titulo, string $pagina): void
{
    $usuarioId = $_SESSION['leitor_id'] ?? null;
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($titulo) ?> - Blogoteca</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="style.css">
    </head>
    <body class="bg-blog">
        <div class="container py-4">
            <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm rounded-4 border border-2 border-warning-subtle">
                <div class="container-fluid px-3 py-2">
                    <a class="navbar-brand blog-title text-primary fw-bold" href="index.php">Blogoteca</a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuSocial" aria-controls="menuSocial" aria-expanded="false" aria-label="Abrir menu">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="menuSocial">
                        <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                            <li class="nav-item"><a class="nav-link <?= $pagina === 'feed' ? 'active' : '' ?>" href="feed.php">Feed</a></li>
                            <li class="nav-item"><a class="nav-link <?= $pagina === 'comunidades' ? 'active' : '' ?>" href="comunidades.php">Comunidades</a></li>
                            <?php if (is_int($usuarioId) && $usuarioId > 0): ?>
                                <li class="nav-item"><a class="nav-link <?= $pagina === 'perfil' ? 'active' : '' ?>" href="perfil.php?id=<?= $usuarioId ?>">Meu perfil</a></li>
                                <li class="nav-item">
                                    <form action="sair.php" method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(tokenCsrf()) ?>">
                                        <button class="btn btn-outline-primary btn-sm" type="submit">Sair</button>
                                    </form>
                                </li>
                            <?php else: ?>
                                <li class="nav-item"><a class="nav-link" href="login.php">Entrar</a></li>
                                <li class="nav-item"><a class="btn btn-primary btn-sm" href="cadastro.html">Criar conta</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </nav>
        </div>
        <main class="container pb-5">
    <?php
}

function renderizarRodapeSocial(): void
{
    ?>
        </main>
        <footer class="container py-4 text-center text-dark">
            <p class="mb-0 fw-semibold">Blogoteca © 2026 — um espaço para compartilhar o que você gosta.</p>
        </footer>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
