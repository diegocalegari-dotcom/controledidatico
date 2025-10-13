<?php
// Inclui o arquivo de configuração do banco de dados
require_once __DIR__ . '/../config/database.php';

// Função para executar uma query e verificar o resultado
function execute_query($conn, $sql, $message) {
    echo "Executando: $message... ";
    if ($conn->query($sql)) {
        echo "OK\n";
        return true;
    } else {
        // Ignora erro de 'coluna duplicada' ou 'tabela já existe' para tornar o script re-executável
        if ($conn->errno == 1060 || $conn->errno == 1050) {
            echo "Já aplicado.\n";
            return true;
        }
        echo "ERRO: " . $conn->error . "\n";
        // Em um script de instalação completo, talvez seja melhor parar aqui.
        // die("Execução interrompida."); 
        return false;
    }
}

// Obtém a conexão com o banco de dados
$conn = connect_db();

echo "Iniciando a criação e atualização completa do banco de dados...\n\n";

// 1. Deletar todas as tabelas para um início limpo
$drop_sql = "
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS emprestimos;
DROP TABLE IF EXISTS estudantes;
DROP TABLE IF EXISTS livros;
DROP TABLE IF EXISTS materias;
DROP TABLE IF EXISTS turmas;
DROP TABLE IF EXISTS series;
DROP TABLE IF EXISTS cursos;
DROP TABLE IF EXISTS anos_letivos;
DROP TABLE IF EXISTS reserva_tecnica;

DROP TABLE IF EXISTS configuracoes;
SET FOREIGN_KEY_CHECKS = 1;
";
echo "Removendo todas as tabelas antigas (se existirem)...\n";
if ($conn->multi_query($drop_sql)) {
    // Esvazia os resultados de multi_query
    while ($conn->next_result()) {;}
    echo "Tabelas removidas com sucesso.\n\n";
} else {
    die("Erro fatal ao remover tabelas antigas: " . $conn->error . "\n");
}


// 2. Criar tabelas da base (init_db.php com ISBN opcional)
$create_base_sql = "
CREATE TABLE cursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('ATIVO', 'ARQUIVADO') NOT NULL DEFAULT 'ATIVO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE series (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    curso_id INT NOT NULL,
    status ENUM('ATIVO', 'ARQUIVADO') NOT NULL DEFAULT 'ATIVO',
    FOREIGN KEY (curso_id) REFERENCES cursos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE turmas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    serie_id INT NOT NULL,
    turno ENUM('Manhã', 'Tarde', 'Noite') NOT NULL,
    ano_letivo YEAR NOT NULL,
    status ENUM('ATIVO', 'ARQUIVADO') NOT NULL DEFAULT 'ATIVO',
    FOREIGN KEY (serie_id) REFERENCES series(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE estudantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cgm VARCHAR(20) NOT NULL UNIQUE,
    nome VARCHAR(255) NOT NULL,
    situacao VARCHAR(50) DEFAULT 'Matriculado',
    turma_id INT,
    FOREIGN KEY (turma_id) REFERENCES turmas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE materias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('ATIVO', 'ARQUIVADO') NOT NULL DEFAULT 'ATIVO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE livros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(20) DEFAULT NULL UNIQUE,
    titulo VARCHAR(255) NOT NULL,
    autor VARCHAR(255),
    materia_id INT NOT NULL,
    serie_id INT NOT NULL,
    status ENUM('ATIVO', 'ARQUIVADO') NOT NULL DEFAULT 'ATIVO',
    FOREIGN KEY (materia_id) REFERENCES materias(id),
    FOREIGN KEY (serie_id) REFERENCES series(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE emprestimos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    livro_id INT NOT NULL,
    estudante_id INT NOT NULL,
    data_entrega DATE NOT NULL,
    data_devolucao DATE,
    conservacao_entrega ENUM('ÓTIMO', 'BOM', 'REGULAR', 'RUIM', 'PÉSSIMO') NOT NULL,
    conservacao_devolucao ENUM('ÓTIMO', 'BOM', 'REGULAR', 'RUIM', 'PÉSSIMO'),
    status ENUM('Emprestado', 'Devolvido', 'Perdido') NOT NULL,
    FOREIGN KEY (livro_id) REFERENCES livros(id),
    FOREIGN KEY (estudante_id) REFERENCES estudantes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
echo "Criando esquema de tabelas base...\n";
if ($conn->multi_query($create_base_sql)) {
    // Esvazia os resultados de multi_query
    while ($conn->next_result()) {;}
    echo "Tabelas base criadas com sucesso.\n\n";
} else {
    die("Erro fatal ao criar tabelas base: " . $conn->error . "\n");
}

// 3. Aplicar atualizações de esquema
echo "Aplicando atualizações de esquema...\n";

// v3
execute_query($conn, "ALTER TABLE emprestimos ADD COLUMN ano_letivo INT(4) AFTER data_entrega", "v3: Adicionando coluna 'ano_letivo' em 'emprestimos'");
execute_query($conn, "UPDATE emprestimos SET ano_letivo = YEAR(data_entrega) WHERE ano_letivo IS NULL", "v3: Preenchendo 'ano_letivo' com dados existentes");

// v4
execute_query($conn, "CREATE TABLE anos_letivos (id INT AUTO_INCREMENT PRIMARY KEY, ano INT(4) NOT NULL UNIQUE)", "v4: Criando tabela 'anos_letivos'");
$current_year = date('Y');
$stmt_v4 = $conn->prepare("INSERT IGNORE INTO anos_letivos (ano) VALUES (?)");
$stmt_v4->bind_param("i", $current_year);
$stmt_v4->execute();
$stmt_v4->close();
echo "Executando: v4: Inserindo ano letivo atual... OK\n";

// v5
execute_query($conn, "ALTER TABLE anos_letivos ADD COLUMN sessao_ativa ENUM('ENTREGA', 'DEVOLUCAO') NOT NULL DEFAULT 'ENTREGA'", "v5: Adicionando 'sessao_ativa' em 'anos_letivos'");

// v6
execute_query($conn, "CREATE TABLE reserva_tecnica (id INT AUTO_INCREMENT PRIMARY KEY, livro_id INT NOT NULL, conservacao ENUM('ÓTIMO', 'BOM', 'REGULAR', 'RUIM', 'PÉSSIMO') NOT NULL, quantidade INT NOT NULL DEFAULT 0, ano_letivo YEAR NOT NULL, UNIQUE KEY (livro_id, conservacao, ano_letivo), FOREIGN KEY (livro_id) REFERENCES livros(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "v6: Criando tabela 'reserva_tecnica'");

// Correção 'configuracoes'
execute_query($conn, "CREATE TABLE configuracoes ( chave VARCHAR(100) NOT NULL PRIMARY KEY, valor VARCHAR(255) NOT NULL ) ENGINE=MyISAM", "Correção: Criando tabela 'configuracoes'");
execute_query($conn, "INSERT INTO configuracoes (chave, valor) VALUES ('ano_letivo_ativo', '2025') ON DUPLICATE KEY UPDATE valor = '2025'", "Correção: Inserindo 'ano_letivo_ativo' em 'configuracoes'");


echo "\n\nBanco de dados criado e atualizado com sucesso!\n";

$conn->close();
?>