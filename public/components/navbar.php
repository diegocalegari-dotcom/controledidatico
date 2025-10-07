<?php
function render_navbar(string $active_page = '') {
    // Define o nome da página principal para o link "Dashboard"
    $dashboard_page = 'index.php';

    $menu_items = [
        $dashboard_page => 'Dashboard',
        'gerenciar_materias.php' => 'Matérias',
        'gerenciar_cursos.php' => 'Cursos',
        'gerenciar_series.php' => 'Séries',
        'gerenciar_livros.php' => 'Livros',
        'importar.php' => 'Importar Alunos',
        'relatorios.php' => 'Relatórios',
        'gerenciar_anos_letivos.php' => 'Anos Letivos',
        'configuracoes.php' => 'Configurações'
    ];

    $navbar_html = '<nav class="navbar navbar-expand-lg navbar-dark bg-dark">'
                 . '<div class="container-fluid">'
                 . '<a class="navbar-brand" href="' . $dashboard_page . '">Controle Didático</a>'
                 . '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>'
                 . '<div class="collapse navbar-collapse" id="navbarNav">'
                 . '<ul class="navbar-nav">';

    foreach ($menu_items as $url => $title) {
        // Páginas de edição devem ativar o menu principal correspondente
        $is_active_class = '';
        if ($url === $active_page) {
            $is_active_class = ' active';
        } else if (($active_page === 'editar_materia.php' && $url === 'gerenciar_materias.php') ||
                   ($active_page === 'editar_curso.php' && $url === 'gerenciar_cursos.php') ||
                   ($active_page === 'editar_serie.php' && $url === 'gerenciar_series.php') ||
                   ($active_page === 'editar_livro.php' && $url === 'gerenciar_livros.php') ||
                   ($active_page === 'controle_turma.php' && $url === $dashboard_page)) {
            $is_active_class = ' active';
        }

        $navbar_html .= '<li class="nav-item"><a class="nav-link' . $is_active_class . '" href="' . $url . '">' . $title . '</a></li>';
    }

    $navbar_html .= '</ul>'
                 . '</div>'
                 . '</div>'
                 . '</nav>';

    echo $navbar_html;
}
?>