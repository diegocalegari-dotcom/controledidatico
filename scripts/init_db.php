<?php
// Inclui o arquivo de configuração do banco de dados
require_once __DIR__ . '/../config/database.php';

// Obtém a conexão com o banco de dados
$conn = connect_db();

echo "Iniciando a criação das tabelas...\n";

// SQL para deletar tabelas existentes (para permitir reexecutar o script)
$sql_drop = "
DROP TABLE IF EXISTS emprestimos;
DROP TABLE IF EXISTS estudantes;
DROP TABLE IF EXISTS livros;
DROP TABLE IF EXISTS materias;
DROP TABLE IF EXISTS turmas;
DROP TABLE IF EXISTS series;
DROP TABLE IF EXISTS cursos;
";

echo "Removendo tabelas antigas (se existirem)...\n";
if ($conn->multi_query($sql_drop)) {
    while ($conn->next_result()) {;}
    echo "Tabelas antigas removidas com sucesso.\n";
} else {
    die("Erro ao remover tabelas antigas: " . $conn->error . "\n");
}

// SQL para criar as tabelas
$sql_create = "
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

echo "Criando novas tabelas...\n";
if ($conn->multi_query($sql_create)) {
    echo "Tabelas criadas com sucesso!\n";
} else {
    die("Erro ao criar as tabelas: " . $conn->error . "\n");
}

$conn->close();
?>