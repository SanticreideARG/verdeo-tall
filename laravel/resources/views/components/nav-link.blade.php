@props(['active' => false])

<a {{ $attributes->merge([
    'class' => 'flex items-center px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 ' .
               ($active ? 'nav-link-active' : 'nav-link-inactive')
]) }}>
    {{ $slot }}
</a>
