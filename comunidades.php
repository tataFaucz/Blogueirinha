<?php
declare(strict_types=1);

require_once __DIR__ . '/social_helpers.php';

$usuarioId = isset($_SESSION['leitor_id']) && is_int($_SESSION['leitor_id'])
    ? $_SESSION['leitor_id']
    : null;
$flash = consumirFlash();
$buscaRecebida = $_GET['busca'] ?? '';
$busca = is_string($buscaRecebida) ? trim($buscaRecebida) : '';
$comunidades = [];
$comunidadeSelecionada = null;
$publicacoesDaComunidade = [];
$erroBanco = false;
$comunidadeId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (tamanhoTexto($busca) > 100) {
    http_response_code(422);
    $busca = '';
    $flash = ['tipo' => 'warning', 'mensagem' => 'A busca deve ter até 100 caracteres.'];
}

try {
    $pdo = conectarBanco();
    $parametros = [];
    $where = '';
    if ($busca !== '') {
        $where = 'WHERE c.nome LIKE :nome ESCAPE \'!\' OR c.descricao LIKE :descricao ESCAPE \'!\'';
        $termo = '%' . strtr($busca, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $parametros = ['nome' => $termo, 'descricao' => $termo];
    }
    $consulta = $pdo->prepare(
        'SELECT c.id, c.nome, c.descricao, c.imagem_url, c.criada_em, c.criador_id,
                l.nome AS nome_criador, l.nome_usuario,
                COUNT(DISTINCT m.usuario_id) AS total_membros,
                COUNT(DISTINCT pc.publicacao_id) AS total_publicacoes,
                MAX(CASE WHEN m2.usuario_id = :usuario_id THEN 1 ELSE 0 END) AS participa
         FROM comunidades c
         INNER JOIN leitores l ON l.id = c.criador_id
         LEFT JOIN membros_comunidade m ON m.comunidade_id = c.id
         LEFT JOIN publicacao_comunidade pc ON pc.comunidade_id = c.id
         LEFT JOIN membros_comunidade m2 ON m2.comunidade_id = c.id
         ' . $where . '
         GROUP BY c.id, c.nome, c.descricao, c.imagem_url, c.criada_em, c.criador_id, l.nome, l.nome_usuario
         ORDER BY c.nome
         LIMIT 100'
    );
    $consulta->execute(['usuario_id' => $usuarioId ?? 0] + $parametros);
    $comunidades = $consulta->fetchAll();

    if ($comunidadeId) {
        $consultaComunidade = $pdo->prepare(
            'SELECT c.id, c.nome, c.descricao, c.imagem_url, c.criador_id, l.nome AS nome_criador, l.nome_usuario
             FROM comunidades c
             INNER JOIN leitores l ON l.id = c.criador_id
             WHERE c.id = :id'
        );
        $consultaComunidade->execute(['id' => $comunidadeId]);
        $comunidadeSelecionada = $consultaComunidade->fetch() ?: null;
        if ($comunidadeSelecionada) {
            $consultaPublicacoes = $pdo->prepare(
                'SELECT p.id, p.titulo, p.conteudo, p.tipo, p.criada_em, l.id AS autor_id,
                        l.nome, l.nome_usuario
                 FROM publicacao_comunidade pc
                 INNER JOIN publicacoes p ON p.id = pc.publicacao_id
                 INNER JOIN leitores l ON l.id = p.autor_id
                 WHERE pc.comunidade_id = :comunidade_id
                 ORDER BY p.criada_em DESC, p.id DESC
                 LIMIT 50'
            );
            $consultaPublicacoes->execute(['comunidade_id' => $comunidadeId]);
            $publicacoesDaComunidade = $consultaPublicacoes->fetchAll();
        }
    }
} catch (PDOException $erro) {
    error_log('Falha ao carregar comunidades da Blogoteca: ' . $erro->getMessage());
    http_response_code(503);
    $erroBanco = true;
}

renderizarCabecalhoSocial('Comunidades', 'comunidades');
?>
<section class="mb-4">
    <h1 class="blog-title text-primary display-5">Comunidades</h1>
    <p class="lead mb-0">Encontre pessoas reunidas por gêneros, personagens e temas dos conteúdo.</p>
</section>

<?php if ($flash): ?>
    <div class="alert alert-<?= e((string) $flash['tipo']) ?>" role="status"><?= e((string) $flash['mensagem']) ?></div>
<?php endif; ?>
<?php if ($erroBanco): ?>
    <div class="alert alert-danger" role="alert">Não foi possível carregar as comunidades. Verifique a conexão e o esquema do banco de dados.</div>
<?php else: ?>
    <?php if ($usuarioId !== null): ?>
        <section class="card border-0 shadow-sm rounded-4 mb-4" aria-labelledby="tituloCriarComunidade">
            <div class="card-body p-4">
                <h2 id="tituloCriarComunidade" class="h4 text-primary">Criar comunidade</h2>
                <form action="processar_social.php" method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= e(tokenCsrf()) ?>">
                    <input type="hidden" name="acao" value="criar_comunidade">
                    <input type="hidden" name="retorno" value="comunidades.php">
                    <div class="col-md-4">
                        <label class="form-label fw-bold" for="nome-comunidade">Nome</label>
                        <input class="form-control" id="nome-comunidade" name="nome" maxlength="100" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-bold" for="descricao-comunidade">Descrição</label>
                        <input class="form-control" id="descricao-comunidade" name="descricao" maxlength="1000" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold" for="imagem-comunidade">Imagem (URL opcional)</label>
                        <input class="form-control" type="url" id="imagem-comunidade" name="imagem_url" maxlength="2048" placeholder="https://exemplo.com/comunidade.jpg">
                    </div>
                    <div class="col-12"><button class="btn btn-primary" type="submit">Criar comunidade</button></div>
                </form>
            </div>
        </section>
    <?php else: ?>
        <div class="alert alert-info" role="status"><a href="login.php">Entre</a> para criar ou participar de uma comunidade.</div>
    <?php endif; ?>

    <section class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form action="comunidades.php" method="get" role="search">
                <label class="form-label fw-bold text-primary" for="busca-comunidades">Buscar comunidades</label>
                <div class="input-group">
                    <input class="form-control" id="busca-comunidades" name="busca" maxlength="100" value="<?= e($busca) ?>" placeholder="Nome ou descrição">
                    <button class="btn btn-primary" type="submit">Buscar</button>
                </div>
            </form>
        </div>
    </section>

    <?php if ($comunidadeId): ?>
        <?php if (!$comunidadeSelecionada): ?>
            <div class="alert alert-warning" role="alert">Comunidade não encontrada.</div>
        <?php else: ?>
            <section class="card border-0 shadow-sm rounded-4 mb-4" aria-labelledby="tituloDetalheComunidade">
                <div class="card-body p-4">
                    <a class="btn btn-sm btn-outline-secondary mb-3" href="comunidades.php">Voltar às comunidades</a>
                    <h2 id="tituloDetalheComunidade" class="h3 text-primary"><?= e((string) $comunidadeSelecionada['nome']) ?></h2>
                    <?php if (!empty($comunidadeSelecionada['imagem_url'])): ?><img class="img-fluid rounded-3 mb-3" src="<?= e((string) $comunidadeSelecionada['imagem_url']) ?>" alt="Imagem da comunidade <?= e((string) $comunidadeSelecionada['nome']) ?>" style="max-height: 240px;"><?php endif; ?>
                    <p><?= e((string) $comunidadeSelecionada['descricao']) ?></p>
                    <p class="small text-muted mb-0">Criada por <a href="perfil.php?id=<?= (int) $comunidadeSelecionada['criador_id'] ?>"><?= e((string) ($comunidadeSelecionada['nome_usuario'] ?: $comunidadeSelecionada['nome_criador'])) ?></a></p>
                </div>
            </section>
            <section class="mb-4" aria-labelledby="tituloPublicacoesComunidade">
                <h2 id="tituloPublicacoesComunidade" class="h4 text-primary">Publicações da comunidade</h2>
                <?php if ($publicacoesDaComunidade === []): ?>
                    <div class="alert alert-info" role="status">Ainda não há publicações nesta comunidade.</div>
                <?php else: ?>
                    <div class="d-grid gap-3">
                        <?php foreach ($publicacoesDaComunidade as $publicacao): ?>
                            <article class="card border-0 shadow-sm rounded-4">
                                <div class="card-body">
                                    <span class="badge text-bg-primary"><?= e(TIPOS_PUBLICACAO[(string) $publicacao['tipo']] ?? 'Publicação') ?></span>
                                    <h3 class="h5 text-primary mt-2"><?= e((string) $publicacao['titulo']) ?></h3>
                                    <p class="small text-muted">Por <a href="perfil.php?id=<?= (int) $publicacao['autor_id'] ?>"><?= e((string) ($publicacao['nome_usuario'] ?: $publicacao['nome'])) ?></a> · <?= e(date('d/m/Y H:i', strtotime((string) $publicacao['criada_em']))) ?></p>
                                    <p><?= nl2br(e((string) $publicacao['conteudo'])) ?></p>
                                    <a class="btn btn-sm btn-outline-primary" href="feed.php">Ver interações no feed</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($comunidades === []): ?>
        <div class="alert alert-info" role="status"><?= $busca === '' ? 'Ainda não há comunidades. Crie a primeira!' : 'Nenhuma comunidade encontrada.' ?></div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($comunidades as $comunidade): ?>
                <div class="col-md-6">
                    <article class="card h-100 border-0 shadow-sm rounded-4">
                        <div class="card-body d-flex flex-column">
                            <h2 class="h4 text-primary"><?= e((string) $comunidade['nome']) ?></h2>
                            <p><?= e((string) $comunidade['descricao']) ?></p>
                            <p class="small text-muted mb-3">
                                Criada por <a href="perfil.php?id=<?= (int) $comunidade['criador_id'] ?>"><?= e((string) ($comunidade['nome_usuario'] ?: $comunidade['nome_criador'])) ?></a>
                                · <?= (int) $comunidade['total_membros'] ?> membro(s)
                                · <?= (int) $comunidade['total_publicacoes'] ?> publicação(ões)
                            </p>
                            <a class="btn btn-sm btn-outline-primary mb-3" href="comunidades.php?id=<?= (int) $comunidade['id'] ?>">Ver publicações da comunidade</a>
                            <?php if ($usuarioId !== null): ?>
                                <form action="processar_social.php" method="post" class="mt-auto">
                                    <input type="hidden" name="csrf_token" value="<?= e(tokenCsrf()) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $comunidade['id'] ?>">
                                    <input type="hidden" name="retorno" value="comunidades.php">
                                    <?php if ((int) $comunidade['participa'] === 1): ?>
                                        <input type="hidden" name="acao" value="sair_comunidade">
                                        <button class="btn btn-outline-primary" type="submit" <?= (int) $comunidade['criador_id'] === $usuarioId ? 'disabled aria-describedby="aviso-criador-' . (int) $comunidade['id'] . '"' : '' ?>><?= (int) $comunidade['criador_id'] === $usuarioId ? 'Você é o criador' : 'Sair da comunidade' ?></button>
                                        <?php if ((int) $comunidade['criador_id'] === $usuarioId): ?><span class="visually-hidden" id="aviso-criador-<?= (int) $comunidade['id'] ?>">O criador permanece como membro.</span><?php endif; ?>
                                    <?php else: ?>
                                        <input type="hidden" name="acao" value="participar">
                                        <button class="btn btn-primary" type="submit">Participar</button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php renderizarRodapeSocial(); ?>
