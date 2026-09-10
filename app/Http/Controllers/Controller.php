<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * `AuthorizesRequests` habilita $this->authorize(...) — lo usan
 * PapeletaController y SustentoController contra PapeletaPolicy. Los
 * controladores Admin\* no lo necesitan (usan el middleware
 * 'role:admin'), pero mantenerlo aquí es lo estándar en Laravel.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
