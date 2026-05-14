<?php

namespace App\Traits;

trait LogsActivity
{
    protected static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            \App\Models\ActividadLog::registrar(
                'crear', class_basename($model), $model->getKey(),
                $model->logDescripcion('crear')
            );
        });

        static::updated(function ($model) {
            $tracked = property_exists($model, 'logCampos') ? $model->logCampos : $model->getFillable();
            $cambios = [];
            foreach ($tracked as $campo) {
                if ($model->wasChanged($campo)) {
                    $de = $model->getOriginal($campo);
                    $a  = $model->getAttribute($campo);
                    if ($de !== $a) {
                        $cambios[] = ['campo' => $campo, 'de' => $de, 'a' => $a];
                    }
                }
            }
            if (! empty($cambios)) {
                \App\Models\ActividadLog::registrar(
                    'actualizar', class_basename($model), $model->getKey(),
                    $model->logDescripcion('actualizar'), $cambios
                );
            }
        });

        static::deleted(function ($model) {
            \App\Models\ActividadLog::registrar(
                'eliminar', class_basename($model), $model->getKey(),
                $model->logDescripcion('eliminar')
            );
        });
    }

    public function logDescripcion(string $accion): string
    {
        $labels = ['crear' => 'Creó', 'actualizar' => 'Actualizó', 'eliminar' => 'Eliminó'];
        return ($labels[$accion] ?? $accion) . ' ' . strtolower(class_basename($this)) . ' #' . $this->getKey();
    }
}