# Projeto Controle Didático - Plano de Desenvolvimento para o Gemini

## 1. Objetivo do Projeto
Desenvolver uma aplicação WEB para gerenciar a entrega, devolução e o inventário de livros didáticos para estudantes de uma instituição de ensino. A aplicação visa substituir o controle manual (atualmente feito em planilhas) por um sistema automatizado, ágil e confiável.

## 2. Requisitos Principais (Análise do `prompt.txt`)

### Entidades Principais
- **Livros:**
  - Atributos: Título, Autor(es), Matéria, ISBN (código único).
  - Relacionado a uma Matéria e a uma Seriação/Curso.
  - Estado de Conservação: ÓTIMO, BOM, REGULAR, RUIM, PÉSSIMO (padrão: BOM).
- **Estudantes:**
  - Atributos: Nome, CGM (Código de Matrícula - identificador único).
  - Estrutura: Organizados por Curso, Série e Turma.
  - Situação: Matriculado, Transferido, Remanejado, Desistente, etc.
- **Inventário:**
  - Controle quantitativo de livros em estoque (reserva) e livros emprestados.
  - Deve ser possível registrar a entrada de novos livros e o remanejamento para outras escolas.

### Funcionalidades Essenciais
- **Cadastro de Livros:** Interface para gerenciar (criar, ler, atualizar, deletar) os livros e suas informações.
- **Importação de Alunos:** Script para importar/sincronizar os dados de alunos, cursos, séries e turmas a partir de arquivos `.csv` ou `.xls`.
- **Entrega e Devolução:**
  - Interface principal (estilo planilha) para visualizar as turmas.
  - Registrar a entrega e devolução de livros para cada estudante.
  - Suporte a leitor de código de barras (ISBN) para identificar livros rapidamente.
  - Registro do estado de conservação do livro na entrega e na devolução.
- **Gestão de Perdas:** Registrar quando um livro é perdido e, se necessário, dar baixa no inventário ao entregar um novo exemplar.
- **Relatórios:** Visualização do inventário geral, quantidade de livros por estado de conservação e listas de alunos com pendências.

## 3. Análise dos Arquivos Fornecidos

- **`CONF. ENTR. LIVRO DIDÁTICO FUND. - 6A.csv`:**
  - É o modelo do controle manual existente.
  - Valida a necessidade de rastrear o status dos livros por aluno e por matéria, além de gerar uma lista de "devedores".

- **`manha.csv` e `tarde.csv`:**
  - São relatórios de matrículas exportados do sistema da escola.
  - **Estrutura:** O arquivo é segmentado por turmas. Cada turma possui um cabeçalho identificando `Curso`, `Seriação`, `Turno` e `Turma`. Abaixo do cabeçalho, vem a lista de alunos com colunas como `CGM`, `Nome do Estudante` e `Situação`.
  - **Plano de Integração:** Criar um script que leia estes arquivos, identifique os blocos de cada turma pelos cabeçalhos e extraia os dados dos alunos. O `CGM` será a chave primária para identificar unicamente cada estudante, permitindo criar novos registros ou atualizar os existentes (importante para lidar com transferências, remanejamentos, etc.).

## 4. Tecnologia Proposta
- **Backend:** PHP (aproveitando o ambiente XAMPP do usuário).
- **Banco de Dados:** MySQL (padrão no XAMPP).
- **Frontend:** HTML, CSS, e JavaScript.
- **Framework/Bibliotecas:** Bootstrap para criar uma interface de usuário limpa, responsiva e com componentes modernos (como a visualização em planilha).

## 5. Plano de Implementação Proposto
1.  **Estrutura Inicial:** Criar a estrutura de pastas do projeto PHP, o banco de dados MySQL e as tabelas (`cursos`, `series`, `turmas`, `materias`, `livros`, `estudantes`, `emprestimos`).
2.  **Cadastro de Livros (CRUD):** Implementar a funcionalidade inicial para gerenciar os livros no sistema.
3.  **Importação de Alunos:** Desenvolver o script de importação para processar os arquivos `manha.csv` e `tarde.csv` e popular o banco de dados.
4.  **Controle de Entrega e Devolução:** Criar a interface principal para gerenciar os empréstimos de livros aos alunos, com suporte ao leitor de código de barras.
5.  **Relatórios:** Desenvolver as telas para visualização do inventário e outras informações gerenciais.
