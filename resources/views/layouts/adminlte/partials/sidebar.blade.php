<aside class="main-sidebar {{ setting('sidebar_skin', 'sidebar-dark-primary') }} elevation-4">
    <a href="{{ route('dashboard') }}" class="brand-link">
        @if ($logo = setting_file('app_logo'))
            <img src="{{ $logo }}" alt="Logo" class="brand-image img-circle elevation-3" style="opacity: .9">
        @else
            <i class="fas fa-cubes brand-image ml-3 mr-2"></i>
        @endif
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
