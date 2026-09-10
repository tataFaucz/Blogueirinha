<?php
declare(strict_types=1);

require_once __DIR__ . '/social_helpers.php';

$recentes = [];
$erroBanco = false;
try {
    $recentes = conectarBanco()->query(
        'SELECT p.id, p.titulo, p.tipo, p.conteudo, p.criada_em, l.id AS autor_id,
                l.nome, l.nome_usuario
         FROM publicacoes p
         INNER JOIN leitores l ON l.id = p.autor_id
         ORDER BY p.criada_em DESC, p.id DESC
         LIMIT 3'
    )->fetchAll();
} catch (PDOException $erro) {
    error_log('Falha ao carregar a página inicial da Blogoteca: ' . $erro->getMessage());
    http_response_code(503);
    $erroBanco = true;
}

renderizarCabecalhoSocial('Início', 'inicio');
?>
<section class="hero card border-0 shadow-lg rounded-4 overflow-hidden mb-5">
    <div class="row g-0 align-items-center">
        <div class="col-12 p-4 p-lg-5">
            <span class="badge rounded-pill text-bg-light border border-2 border-warning text-uppercase mb-3">Rede social de blogs</span>
            <h1 class="blog-title display-4 text-primary mb-3">★ BLOGOTECA ★</h1>
            <p class="lead text-dark mb-4">Um espaço para jovens adultos publicarem sobre o que gostam, descobrirem pessoas legais e conversarem sobre seus interesses.</p>
            <div class="d-flex flex-wrap gap-3">
                <a class="btn btn-primary btn-lg px-4 rounded-pill" href="feed.php">Explorar publicações</a>
                <a class="btn btn-outline-primary btn-lg px-4 rounded-pill" href="comunidades.php">Ver comunidades</a>
            </div>
        </div>
    </div>
</section>

<section class="row g-4 mb-5 text-center" aria-label="Recursos da Blogoteca">
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm rounded-4 p-4">
            <h2 class="h4 text-primary">Publique</h2>
            <p class="mb-0 text-muted">Compartilhe textos, ideias, opiniões, recomendações e listas sobre o que faz parte da sua vida.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm rounded-4 p-4">
            <h2 class="h4 text-primary">Descubra</h2>
            <p class="mb-0 text-muted">Siga pessoas, encontre publicações por texto e tags e acompanhe seu feed.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm rounded-4 p-4">
            <h2 class="h4 text-primary">Participe</h2>
            <p class="mb-0 text-muted">Curta, comente e reúna pessoas em comunidades sobre assuntos que vocês gostam.</p>
        </div>
    </div>
</section>

<section aria-labelledby="tituloRecentes">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 id="tituloRecentes" class="blog-title text-primary h3 mb-0">Publicações recentes</h2>
        <a class="btn btn-outline-primary" href="feed.php">Ver todas</a>
    </div>
    <?php if ($erroBanco): ?>
        <div class="alert alert-warning" role="status">O feed estará disponível após a configuração do banco de dados.</div>
    <?php elseif ($recentes === []): ?>
        <div class="alert alert-info" role="status">Ainda não há publicações. <a href="cadastro.html">Crie sua conta</a> e seja a primeira pessoa a publicar.</div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($recentes as $publicacao): ?>
                <div class="col-md-4">
                    <article class="card h-100 border-0 shadow-sm rounded-4">
                        <div class="card-body">
                            <span class="badge text-bg-primary"><?= e(TIPOS_PUBLICACAO[(string) $publicacao['tipo']] ?? 'Publicação') ?></span>
                            <h3 class="h5 text-primary mt-2"><?= e((string) $publicacao['titulo']) ?></h3>
                            <p class="small text-muted">Por <a href="perfil.php?id=<?= (int) $publicacao['autor_id'] ?>"><?= e((string) ($publicacao['nome_usuario'] ?: $publicacao['nome'])) ?></a></p>
                            <?php
                            $resumo = (string) $publicacao['conteudo'];
                            if (tamanhoTexto($resumo) > 180) {
                                $partes = preg_split('//u', $resumo, -1, PREG_SPLIT_NO_EMPTY);
                                $resumo = implode('', array_slice($partes ?: [], 0, 177)) . '...';
                            }
                            ?>
                            <p><?= e($resumo) ?></p>
                            <a href="feed.php" class="btn btn-sm btn-outline-primary">Participar da conversa</a>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php renderizarRodapeSocial(); ?>
