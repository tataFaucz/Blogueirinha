USE blogoteca;

ALTER TABLE publicacoes
    MODIFY COLUMN tipo ENUM('artigo', 'opiniao', 'diario', 'recomendacao', 'lista') NOT NULL;

ALTER TABLE leitores
    ADD COLUMN nome_usuario VARCHAR(40) NULL AFTER senha,
    ADD COLUMN biografia TEXT NULL AFTER nome_usuario,
    ADD COLUMN imagem_perfil_url VARCHAR(2048) NULL AFTER biografia,
    ADD UNIQUE KEY uq_leitores_nome_usuario (nome_usuario),
    ADD CONSTRAINT chk_leitores_termos CHECK (termos_aceitos IN (0, 1));

ALTER TABLE leitores
    DROP COLUMN IF EXISTS publicações_mes,
    DROP COLUMN IF EXISTS gibis_mes,
    DROP COLUMN IF EXISTS cor_favorita,
    DROP COLUMN IF EXISTS genero,
    DROP COLUMN IF EXISTS frequencia,
    DROP COLUMN IF EXISTS tipos,
    DROP COLUMN IF EXISTS nivel_leitura,
    DROP COLUMN IF EXISTS publicação_favorito,
    DROP COLUMN IF EXISTS gibi_favorito,
    DROP COLUMN IF EXISTS personagem_favorito;

CREATE TABLE IF NOT EXISTS publicacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    autor_id BIGINT UNSIGNED NOT NULL,
    titulo VARCHAR(160) NOT NULL,
    conteudo TEXT NOT NULL,
    tipo ENUM('artigo', 'opiniao', 'diario', 'recomendacao', 'lista') NOT NULL,
    imagem_url VARCHAR(2048) NULL,
    criada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_publicacoes_autor_data (autor_id, criada_em),
    CONSTRAINT fk_publicacoes_autor FOREIGN KEY (autor_id) REFERENCES leitores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comentarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    publicacao_id BIGINT UNSIGNED NOT NULL,
    autor_id BIGINT UNSIGNED NOT NULL,
    conteudo VARCHAR(1000) NOT NULL,
    criada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_comentarios_publicacao_data (publicacao_id, criada_em),
    CONSTRAINT fk_comentarios_publicacao FOREIGN KEY (publicacao_id) REFERENCES publicacoes (id) ON DELETE CASCADE,
    CONSTRAINT fk_comentarios_autor FOREIGN KEY (autor_id) REFERENCES leitores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS curtidas (
    usuario_id BIGINT UNSIGNED NOT NULL,
    publicacao_id BIGINT UNSIGNED NOT NULL,
    criada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, publicacao_id),
    KEY idx_curtidas_publicacao (publicacao_id),
    CONSTRAINT fk_curtidas_usuario FOREIGN KEY (usuario_id) REFERENCES leitores (id) ON DELETE CASCADE,
    CONSTRAINT fk_curtidas_publicacao FOREIGN KEY (publicacao_id) REFERENCES publicacoes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seguidores (
    seguidor_id BIGINT UNSIGNED NOT NULL,
    seguido_id BIGINT UNSIGNED NOT NULL,
    criada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (seguidor_id, seguido_id),
    KEY idx_seguidores_seguido (seguido_id),
    CONSTRAINT chk_seguidores_nao_proprio CHECK (seguidor_id <> seguido_id),
    CONSTRAINT fk_seguidores_seguidor FOREIGN KEY (seguidor_id) REFERENCES leitores (id) ON DELETE CASCADE,
    CONSTRAINT fk_seguidores_seguido FOREIGN KEY (seguido_id) REFERENCES leitores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comunidades (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    criador_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao VARCHAR(1000) NOT NULL,
    criada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_comunidades_nome (nome),
    CONSTRAINT fk_comunidades_criador FOREIGN KEY (criador_id) REFERENCES leitores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS membros_comunidade (
    comunidade_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    papel ENUM('criador', 'participante') NOT NULL DEFAULT 'participante',
    entrou_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (comunidade_id, usuario_id),
    KEY idx_membros_usuario (usuario_id),
    CONSTRAINT fk_membros_comunidade FOREIGN KEY (comunidade_id) REFERENCES comunidades (id) ON DELETE CASCADE,
    CONSTRAINT fk_membros_usuario FOREIGN KEY (usuario_id) REFERENCES leitores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS publicacao_comunidade (
    publicacao_id BIGINT UNSIGNED NOT NULL,
    comunidade_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (publicacao_id, comunidade_id),
    KEY idx_publicacao_comunidade_comunidade (comunidade_id),
    CONSTRAINT fk_publicacao_comunidade_publicacao FOREIGN KEY (publicacao_id) REFERENCES publicacoes (id) ON DELETE CASCADE,
    CONSTRAINT fk_publicacao_comunidade_comunidade FOREIGN KEY (comunidade_id) REFERENCES comunidades (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(40) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tags_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS publicacao_tag (
    publicacao_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (publicacao_id, tag_id),
    KEY idx_publicacao_tag_tag (tag_id),
    CONSTRAINT fk_publicacao_tag_publicacao FOREIGN KEY (publicacao_id) REFERENCES publicacoes (id) ON DELETE CASCADE,
    CONSTRAINT fk_publicacao_tag_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE comunidades
    ADD COLUMN imagem_url VARCHAR(2048) NULL AFTER descricao;

CREATE TABLE IF NOT EXISTS amizades (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    solicitante_id BIGINT UNSIGNED NOT NULL,
    destinatario_id BIGINT UNSIGNED NOT NULL,
    status ENUM('pendente', 'aceita', 'recusada') NOT NULL DEFAULT 'pendente',
    criada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    respondida_em TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_amizades_par (solicitante_id, destinatario_id),
    KEY idx_amizades_destinatario_status (destinatario_id, status),
    KEY idx_amizades_solicitante_status (solicitante_id, status),
    CONSTRAINT chk_amizades_nao_proprio CHECK (solicitante_id <> destinatario_id),
    CONSTRAINT fk_amizades_solicitante FOREIGN KEY (solicitante_id) REFERENCES leitores (id) ON DELETE CASCADE,
    CONSTRAINT fk_amizades_destinatario FOREIGN KEY (destinatario_id) REFERENCES leitores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensagens_perfil (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    autor_id BIGINT UNSIGNED NOT NULL,
    perfil_id BIGINT UNSIGNED NOT NULL,
    conteudo VARCHAR(1000) NOT NULL,
    criada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mensagens_perfil_data (perfil_id, criada_em),
    CONSTRAINT chk_mensagens_perfil_nao_proprio CHECK (autor_id <> perfil_id),
    CONSTRAINT fk_mensagens_perfil_autor FOREIGN KEY (autor_id) REFERENCES leitores (id) ON DELETE CASCADE,
    CONSTRAINT fk_mensagens_perfil_destino FOREIGN KEY (perfil_id) REFERENCES leitores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
