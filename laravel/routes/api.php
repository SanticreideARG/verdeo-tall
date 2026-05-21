<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// ── Meta Webhook (Messenger + Instagram) ──────────────────────────────────────
// GET: Meta verifica el endpoint al configurar el webhook en Developer Console
Route::get('/webhook/meta', function (Request $request) {
    $verifyToken = \App\Models\Setting::get('meta_verify_token', '');
    if (
        $request->get('hub_mode') === 'subscribe' &&
        $request->get('hub_verify_token') === $verifyToken &&
        $verifyToken !== ''
    ) {
        return response($request->get('hub_challenge'), 200)
            ->header('Content-Type', 'text/plain');
    }
    return response('Verification failed', 403);
});

// POST: Meta envía eventos de mensaje (o n8n los relaya normalizados)
Route::post('/webhook/meta', function (Request $request) {
    // Verificar firma HMAC-SHA256 si hay App Secret configurado
    $secret    = \App\Models\Setting::get('meta_app_secret', '');
    $signature = $request->header('X-Hub-Signature-256', '');
    if ($secret && $signature) {
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
        if (!hash_equals($expected, $signature)) {
            return response('Forbidden', 403);
        }
    }

    // TODO: despachar a job para procesar el evento
    // ProcessMetaWebhookEvent::dispatch($request->all());

    return response('EVENT_RECEIVED', 200);
})->withoutMiddleware(['auth:sanctum']);
