@php $depth = $depth ?? 0; @endphp
<tr>
    <td>
        {!! str_repeat('<span class="ml-3"></span>', $depth) !!}
        @if ($depth > 0)<i class="fas fa-level-up-alt fa-rotate-90 text-muted mr-1"></i>@endif
        <i class="{{ $menu->icon ?: 'far fa-circle' }} mr-1 text-muted"></i>
        {{ $menu->name }}
    </td>
    <td><code>{{ $menu->code }}</code></td>
    <td><span class="badge badge-light border">{{ $menu->link_type }}</span></td>
    <td class="small">{{ $menu->target_value ?: '—' }}</td>
    <td class="small">{{ $menu->permission_code ?: '—' }}</td>
    <td class="text-center">{{ $menu->order_no }}</td>
    <td class="text-center">
        <span class="badge badge-{{ $menu->is_active ? 'success' : 'secondary' }}">
            {{ $menu->is_active ? 'Aktif' : 'Nonaktif' }}
        </span>
    </td>
    <td class="text-center">
        @can('system.menu')
            <a href="{{ route('menus.edit', $menu) }}" class="btn btn-xs btn-info"><i class="fas fa-edit"></i></a>
            <form method="POST" action="{{ route('menus.destroy', $menu) }}" class="d-inline"
                  onsubmit="return confirm('Hapus menu ini?')">
                @csrf @method('DELETE')
                <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
            </form>
        @endcan
    </td>
</tr>

@foreach ($menu->children as $child)
    @include('admin.menus.row', ['menu' => $child, 'depth' => $depth + 1])
@endforeach
