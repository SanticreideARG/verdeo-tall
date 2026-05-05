<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// ─── Auth ────────────────────────────────────────────────────────────────────
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
    Volt::route('/conversaciones',    'conversaciones.index')   ->name('conversaciones');
    Volt::route('/zonas',             'zonas.index')            ->name('zonas');
    Volt::route('/estadisticas',       'estadisticas.index')     ->name('estadisticas');
    Volt::route('/productos',          'productos.index')        ->name('productos');
    Volt::route('/ordenes',            'ordenes.index')          ->name('ordenes');
    Volt::route('/usuarios',          'usuarios.index')         ->name('usuarios');
    Volt::route('/usuarios/crear',    'usuarios.crear')         ->name('usuarios.crear');
    Volt::route('/ajustes',           'ajustes.index')          ->name('ajustes');

});

// ─── External tools (links only) ─────────────────────────────────────────────
Route::get('/n8n', fn() => redirect('http://localhost:5678'))->name('n8n')->middleware('auth');
