<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="{{ route('dashboard') }}" class="brand-link">
        <i class="fas fa-cubes brand-image ml-3 mr-2"></i>
        <span class="brand-text font-weight-light">{{ setting('app_name', config('app.name')) }}</span>
    </a>

    <div class="sidebar">
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                @each('layouts.adminlte.partials.menu-item', $menuTree, 'menu')
            </ul>
        </nav>
    </div>
</aside>
