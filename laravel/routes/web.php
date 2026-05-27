<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// ─── Auth ────────────────────────────────────────────────────────────────────
Volt::route('/registro', 'registro.index')->name('registro')->middleware('guest');

Route::get('/login', function () {
    return view('auth.login');
})->name('login')->middleware('guest');

Route::post('/login', function () {
    $credentials = request()->validate([
        'email'    => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (auth()->attempt($credentials, request()->boolean('remember'))) {
        request()->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }

    return back()->withErrors(['email' => 'Credenciales incorrectas.'])->onlyInput('email');
})->name('login.post')->middleware('guest');

Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
})->name('logout')->middleware('auth');

// ─── App (protected) ─────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::get('/', fn() => redirect()->route('dashboard'));

    Volt::route('/dashboard',         'dashboard')              ->name('dashboard');
    Volt::route('/conversaciones',             'conversaciones.index') ->name('conversaciones');
    Volt::route('/conversaciones/{conversacion}', 'conversaciones.ver')   ->name('conversaciones.ver');
    Volt::route('/zonas',             'zonas.index')            ->name('zonas');
    Volt::route('/estadisticas',       'estadisticas.index')     ->name('estadisticas');
    Volt::route('/productos',          'productos.index')        ->name('productos');
    Volt::route('/ordenes',            'ordenes.index')          ->name('ordenes');
    Volt::route('/usuarios',                    'usuarios.index')            ->name('usuarios');
    Volt::route('/usuarios/crear',            'usuarios.crear')            ->name('usuarios.crear');
    Volt::route('/usuarios/crear/cliente',    'usuarios.crear-cliente')    ->name('usuarios.crear-cliente');
    Volt::route('/usuarios/crear/colaborador','usuarios.crear-colaborador')->name('usuarios.crear-colaborador');
    Volt::route('/usuarios/{user}',           'usuarios.ver')              ->name('usuarios.ver');

    Volt::route('/clientes',                  'clientes.index')            ->name('clientes');
    Volt::route('/clientes/crm',              'clientes.crm')              ->name('clientes.crm');
    Volt::route('/ajustes',                   'ajustes.index')             ->name('ajustes');
    Volt::route('/mis-enlaces',        'mis-enlaces.index')       ->name('mis-enlaces');
    Volt::route('/mi-cuenta',         'mi-cuenta.index')        ->name('mi-cuenta');
    Volt::route('/chat',              'chat.index')             ->name('chat');
    Volt::route('/ai',                'ai.chat')                ->name('ai.chat');
    Volt::route('/sistema',           'sistema.index')           ->name('sistema');
    Volt::route('/cocina',            'cocina.index')            ->name('cocina');
    Volt::route('/campanas',          'campanas.index')          ->name('campanas');
    Volt::route('/entregas',          'entregas.index')          ->name('entregas');
    Volt::route('/ayuda',             'ayuda.index')             ->name('ayuda');
    Volt::route('/portal',            'portal.index')            ->name('portal');

    // ─── Marketing de Redes ───────────────────────────────────────────────────
    Volt::route('/marketing/email',     'marketing.email')     ->name('marketing.email');
    Volt::route('/marketing/whatsapp',  'marketing.whatsapp')  ->name('marketing.whatsapp');
    Volt::route('/marketing/facebook',  'marketing.facebook')  ->name('marketing.facebook');
    Volt::route('/marketing/instagram', 'marketing.instagram') ->name('marketing.instagram');
    Volt::route('/marketing/otros',     'marketing.otros')     ->name('marketing.otros');

});

// ─── Microsite público — transportista (sin login) ───────────────────────────
Volt::route('/r/{token}', 'entregas.microsite')->name('entregas.microsite');

// ─── Portal de clientes (public registration) ────────────────────────────────
Volt::route('/unirme', 'portal.registro')->name('portal.registro')->middleware('guest');

// ─── External tools (links only) ─────────────────────────────────────────────
Route::get('/n8n', fn() => redirect('http://localhost:5678'))->name('n8n')->middleware('auth');
