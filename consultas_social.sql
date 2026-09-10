USE blogoteca;

SET @usuario_id = 1;
SET @comunidade_id = 1;

-- Feed cronológico de quem o usuário segue.
SELECT p.id, p.titulo, p.tipo, p.criada_em, l.nome_usuario, l.nome
FROM publicacoes p
INNER JOIN seguidores s ON s.seguido_id = p.autor_id
INNER JOIN leitores l ON l.id = p.autor_id
WHERE s.seguidor_id = @usuario_id
ORDER BY p.criada_em DESC, p.id DESC;

-- Publicações de um autor.
SELECT id, titulo, tipo, criada_em, atualizada_em
FROM publicacoes
WHERE autor_id = @usuario_id
ORDER BY criada_em DESC, id DESC;

-- Publicações com mais curtidas.
SELECT p.id, p.titulo, COUNT(c.usuario_id) AS total_curtidas
FROM publicacoes p
LEFT JOIN curtidas c ON c.publicacao_id = p.id
GROUP BY p.id, p.titulo
ORDER BY total_curtidas DESC, p.id DESC;

-- Quantidade de comentários por publicação.
SELECT p.id, p.titulo, COUNT(c.id) AS total_comentarios
FROM publicacoes p
LEFT JOIN comentarios c ON c.publicacao_id = p.id
GROUP BY p.id, p.titulo
ORDER BY total_comentarios DESC, p.id DESC;

-- Comunidades com mais participantes.
SELECT c.id, c.nome, COUNT(m.usuario_id) AS total_membros
FROM comunidades c
LEFT JOIN membros_comunidade m ON m.comunidade_id = c.id
GROUP BY c.id, c.nome
ORDER BY total_membros DESC, c.nome;

-- Tags mais utilizadas.
SELECT t.id, t.nome, COUNT(pt.publicacao_id) AS total_publicacoes
FROM tags t
LEFT JOIN publicacao_tag pt ON pt.tag_id = t.id
GROUP BY t.id, t.nome
ORDER BY total_publicacoes DESC, t.nome;

-- Seguidores de um usuário.
SELECT l.id, l.nome, l.nome_usuario
FROM seguidores s
INNER JOIN leitores l ON l.id = s.seguidor_id
WHERE s.seguido_id = @usuario_id
ORDER BY l.nome_usuario, l.nome;

-- Perfis seguidos por um usuário.
SELECT l.id, l.nome, l.nome_usuario
FROM seguidores s
INNER JOIN leitores l ON l.id = s.seguido_id
WHERE s.seguidor_id = @usuario_id
ORDER BY l.nome_usuario, l.nome;

-- Solicitações e amizades aceitas de um usuário.
SELECT a.id, a.status, l.nome, l.nome_usuario, a.criada_em
FROM amizades a
INNER JOIN leitores l
    ON l.id = CASE
        WHEN a.solicitante_id = @usuario_id THEN a.destinatario_id
        ELSE a.solicitante_id
    END
WHERE (a.solicitante_id = @usuario_id OR a.destinatario_id = @usuario_id)
ORDER BY a.status, a.criada_em DESC;

-- Mensagens públicas recebidas no perfil de um usuário.
SELECT m.id, m.conteudo, m.criada_em, l.nome, l.nome_usuario
FROM mensagens_perfil m
INNER JOIN leitores l ON l.id = m.autor_id
WHERE m.perfil_id = @usuario_id
ORDER BY m.criada_em DESC, m.id DESC;

-- Publicações associadas a uma comunidade.
SELECT p.id, p.titulo, p.tipo, p.criada_em, l.nome, l.nome_usuario
FROM publicacao_comunidade pc
INNER JOIN publicacoes p ON p.id = pc.publicacao_id
INNER JOIN leitores l ON l.id = p.autor_id
WHERE pc.comunidade_id = @comunidade_id
ORDER BY p.criada_em DESC, p.id DESC;
