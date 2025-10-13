<?php
function render_navbar(string $active_page = '') {
    $dashboard_page = 'index.php';

    // --- Estrutura do Menu ---
    $config_items = [
        'gerenciar_cursos.php' => 'Cursos',
        'gerenciar_series.php' => 'Séries',
        'gerenciar_materias.php' => 'Matérias',
        'gerenciar_livros.php' => 'Livros',
        'reserva_tecnica.php' => 'Reserva Técnica',
        'gerenciar_anos_letivos.php' => 'Anos Letivos',
        'importar.php' => 'Importar Alunos',
        'configuracoes.php' => 'Configurações Gerais',
    ];
    
    // Páginas de edição que ativam um item do menu de configuração
    $edit_page_map = [
        'editar_curso.php' => 'gerenciar_cursos.php',
        'editar_serie.php' => 'gerenciar_series.php',
        'editar_materia.php' => 'gerenciar_materias.php',
        'editar_livro.php' => 'gerenciar_livros.php',
    ];

    // --- Lógica de Ativação ---
    $is_dashboard_active = ($active_page === $dashboard_page || $active_page === 'controle_turma.php') ? 'active' : '';
    $is_relatorios_active = ($active_page === 'relatorios.php') ? 'active' : '';
    
    $is_config_active = (in_array($active_page, array_keys($config_items)) || in_array($active_page, array_keys($edit_page_map))) ? 'active' : '';

    // --- Renderização do HTML ---
    $navbar_html = '<nav class="navbar navbar-expand-lg navbar-dark bg-dark">'
                 . '<div class="container-fluid">'
                 . '<a class="navbar-brand" href="' . $dashboard_page . '">Controle Didático</a>'
                 . '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>'
                 . '<div class="collapse navbar-collapse" id="navbarNav">'
                 . '<ul class="navbar-nav">';

    // Item: Painel de Controle
    $navbar_html .= '<li class="nav-item"><a class="nav-link ' . $is_dashboard_active . '" href="' . $dashboard_page . '">Painel de Controle</a></li>';

    // Item: Relatórios
    $navbar_html .= '<li class="nav-item"><a class="nav-link ' . $is_relatorios_active . '" href="relatorios.php">Relatórios</a></li>';

    // Dropdown: Configurações
    $navbar_html .= '<li class="nav-item dropdown">'
                  . '<a class="nav-link dropdown-toggle ' . $is_config_active . '" href="#" id="navbarDropdownConfig" role="button" data-bs-toggle="dropdown" aria-expanded="false">Configurações</a>'
                  . '<ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="navbarDropdownConfig">';

    foreach ($config_items as $url => $title) {
        $is_item_active = '';
        // Ativa o item se for a página ativa ou se a página de edição correspondente estiver ativa
        if ($active_page === $url || (isset($edit_page_map[$active_page]) && $edit_page_map[$active_page] === $url)) {
            $is_item_active = 'active';
        }
        $navbar_html .= '<li><a class="dropdown-item ' . $is_item_active . '" href="' . $url . '">' . $title . '</a></li>';
    }

    $navbar_html .= '</ul></li>'; // Fecha dropdown

    $navbar_html .= '</ul></div></div></nav>'; // Fecha navbar

    echo $navbar_html;
}
?>
