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

---

## 6. Estado Atual do Projeto (03/10/2025)

O sistema possui uma base sólida e funcional. As seguintes funcionalidades foram implementadas:

- **Gerenciamento de Dados Base:**
  - Interfaces completas para Adicionar, Editar, Arquivar e Excluir Matérias, Cursos e Séries.
  - Interface completa para Adicionar, Editar, Arquivar e Excluir Livros, com busca de dados por ISBN nas APIs do Google Books e OpenLibrary.

- **Automação e Ferramentas:**
  - Script de importação inteligente que processa arquivos CSV (`manha.csv`, `tarde.csv`) e popula automaticamente o banco de dados com Cursos, Séries, Turmas e Alunos.
  - Ferramenta de configuração para Resetar o banco de dados de forma segura, exigindo uma frase de confirmação.

- **Tela de Controle Principal (`controle_turma.php`):
  - Dashboard na página inicial que lista todas as turmas.
  - Grade de controle visual que cruza Alunos vs. Livros da turma.
  - **Funcionalidades Ativas:**
    - Botão "Entregar" para registrar um novo empréstimo (abre um modal para selecionar o estado de conservação).
    - Botão "Entregar Todos" para registrar todos os livros pendentes de um aluno de uma só vez.
    - Botão "Cancelar Entrega" (dentro do modal de devolução) para apagar um lançamento feito por engano.

## 7. Próximos Passos (A Implementar)

### 7.1. Finalizar Controle de Devolução/Perda

**Objetivo:** Ativar os botões "Confirmar Devolução" e "Marcar como Perdido" na janela modal que abre ao clicar em um livro já entregue.

**Plano de Ação:**
1.  **Criar API para Devolução:** Criar o arquivo `public/api/registrar_devolucao.php`. Ele receberá o `emprestimo_id` e o `estado de conservacao` da devolução, e fará o `UPDATE` na tabela `emprestimos`, alterando o `status` para 'Devolvido' e preenchendo `data_devolucao` e `conservacao_devolucao`.
2.  **Criar API para Perda:** Criar o arquivo `public/api/marcar_como_perdido.php`. Ele receberá o `emprestimo_id` e fará o `UPDATE` na tabela `emprestimos`, alterando o `status` para 'Perdido'.
3.  **Implementar JavaScript:** No arquivo `public/controle_turma.php`, adicionar os `event listeners` para os botões `#btn-confirmar-devolucao` e `#btn-marcar-perdido`. Cada um fará uma chamada `fetch` para seu respectivo script de API e, em caso de sucesso, recarregará a página para exibir o novo status.

### 7.2. Implementar Gerenciamento de Alunos na Turma

**Objetivo:** Permitir que o usuário adicione, remova ou remaneje alunos diretamente da tela de controle da turma.

**Plano de Ação:**
1.  **Adicionar Botão "Gerenciar Aluno":** Na página `controle_turma.php`, adicionar um botão (ex: ícone de engrenagem ⚙️) ao lado do nome de cada aluno.
2.  **Criar Modal de Gerenciamento:** Clicar no botão abrirá uma nova janela (`#gerenciarAlunoModal`) com os dados do aluno.
3.  **Implementar Ação "Remanejar":**
    - No modal, adicionar um menu dropdown com a lista de todas as outras turmas do sistema.
    - Adicionar um botão "Salvar" que enviará o `aluno_id` e o `nova_turma_id` para um novo script `public/api/remanejar_aluno.php`.
    - O script de API fará o `UPDATE` na tabela `estudantes`, alterando o `turma_id` do aluno.
4.  **Implementar Ação "Remover da Turma":**
    - No modal, adicionar um botão vermelho "Remover Aluno da Turma".
    - O botão enviará o `aluno_id` para um novo script `public/api/remover_aluno_turma.php`.
    - O script de API fará o `UPDATE` na tabela `estudantes`, definindo o `turma_id` como `NULL`.
5.  **Implementar Ação "Adicionar Aluno":**
    - Adicionar um botão "Adicionar Novo Aluno" no topo da página da turma.
    - O botão abrirá um modal com um formulário para "Nome" e "CGM".
    - Ao salvar, os dados serão enviados para `public/api/adicionar_aluno.php`, que fará o `INSERT` do novo estudante já associado à turma atual.
