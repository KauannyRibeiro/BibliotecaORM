-- BibliotecaORM — modelo relacional
-- Execução: mysql -u <usuario> -p < sql/schema.sql

CREATE DATABASE IF NOT EXISTS biblioteca_orm
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE biblioteca_orm;

CREATE TABLE IF NOT EXISTS autores (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome          VARCHAR(150) NOT NULL,
    nacionalidade VARCHAR(60)  NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS livros (
    id             INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    titulo         VARCHAR(200)      NOT NULL,
    isbn           VARCHAR(13)       NOT NULL,
    ano_publicacao SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_livros_isbn (isbn),
    CONSTRAINT ck_livros_ano CHECK (ano_publicacao BETWEEN 1450 AND 2100)
) ENGINE=InnoDB;

-- Relacionamento N:N. A PK composta impede o mesmo vínculo duplicado.
-- RESTRICT nas duas FKs: nada é apagado "em cascata" por acidente.
-- Para excluir um livro, o repositório remove antes os vínculos (em transação).
-- Um autor ainda vinculado a livros NÃO pode ser excluído (erro 1451 / SQLSTATE 23000).
CREATE TABLE IF NOT EXISTS livros_autores (
    livro_id INT UNSIGNED NOT NULL,
    autor_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (livro_id, autor_id),
    KEY idx_livros_autores_autor (autor_id),
    CONSTRAINT fk_la_livro FOREIGN KEY (livro_id) REFERENCES livros (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_la_autor FOREIGN KEY (autor_id) REFERENCES autores (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Ampliação opcional:
-- CREATE TABLE IF NOT EXISTS exemplares (
--     id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
--     livro_id   INT UNSIGNED NOT NULL,
--     codigo     VARCHAR(30)  NOT NULL,
--     disponivel TINYINT(1)   NOT NULL DEFAULT 1,
--     PRIMARY KEY (id),
--     UNIQUE KEY uq_exemplares_codigo (codigo),
--     CONSTRAINT fk_ex_livro FOREIGN KEY (livro_id) REFERENCES livros (id) ON DELETE RESTRICT
-- ) ENGINE=InnoDB;
