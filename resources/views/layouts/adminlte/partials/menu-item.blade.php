@php
    $hasChildren = $menu->children->isNotEmpty();
    $url = $menu->url();
    $isActive = $url !== '#' && request()->url() === $url;
    $hasActiveChild = $menu->children->contains(fn ($c) => request()->url() === $c->url());
@endphp

@if ($menu->link_type === 'header')
    <li class="nav-header">{{ strtoupper($menu->name) }}</li>
    @each('layouts.adminlte.partials.menu-item', $menu->children, 'menu')
@else
    <li class="nav-item {{ $hasChildren && $hasActiveChild ? 'menu-open' : '' }}">
        <a href="{{ $hasChildren ? '#' : $url }}"
           class="nav-link {{ $isActive ? 'active' : '' }}"
           @if (! $hasChildren && $menu->open_new_tab) target="_blank" rel="noopener" @endif>
            <i class="nav-icon {{ $menu->icon ?: 'far fa-circle' }}"></i>
            <p>
                {{ $menu->name }}
                @if ($hasChildren)<i class="right fas fa-angle-left"></i>@endif
            </p>
        </a>

        @if ($hasChildren)
            <ul class="nav nav-treeview">
                @each('layouts.adminlte.partials.menu-item', $menu->children, 'menu')
            </ul>
        @endif
    </li>
@endif
