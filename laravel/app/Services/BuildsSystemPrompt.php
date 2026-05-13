<?php

namespace App\Services;

use App\Models\Producto;

trait BuildsSystemPrompt
{
    public function buildSystemPrompt(): string
    {
        $path     = resource_path('prompts/systemPrompt.md');
        $template = file_exists($path) ? file_get_contents($path) : '';

        $menu = Producto::where('activo', true)->orderBy('orden')->get()
            ->map(function (Producto $p): string {
                $p250 = $p->precio_250g ? '$' . number_format((float) $p->precio_250g, 0, ',', '.') : 'a confirmar';
                $p400 = $p->precio_400g ? '$' . number_format((float) $p->precio_400g, 0, ',', '.') : 'a confirmar';
                return "- **{$p->tipoLabel()}**: 250 kcal {$p250} · 400 kcal {$p400}";
            })
            ->join("\n");

        return str_replace('{{MENU_SEMANAL}}', $menu ?: '_(sin menú cargado esta semana)_', $template);
    }
}
