-- Dados fictícios de exemplo (sem dados pessoais). ISBNs fictícios.
-- IDs explícitos + INSERT IGNORE: pode ser executado mais de uma vez.
USE biblioteca_orm;

INSERT IGNORE INTO autores (id, nome, nacionalidade) VALUES
    (1, 'Lygia Fagundes Telles', 'Brasileira'),
    (2, 'Graciliano Ramos',      'Brasileira'),
    (3, 'Jorge Amado',           'Brasileira');

INSERT IGNORE INTO livros (id, titulo, isbn, ano_publicacao) VALUES
    (1, 'Vidas Secas',       '9780000100001', 1938),
    (2, 'Capitães da Areia', '9780000100002', 1937);

INSERT IGNORE INTO livros_autores (livro_id, autor_id) VALUES
    (1, 2),
    (2, 3);
