<?php

namespace App\Exceptions;

use Exception;

/**
 * Cualquier violación a una regla de negocio al crear o vincular
 * usuarios (DNI duplicado, unidad fuera de área, unidad ya con jefe,
 * etc). Se atrapa en el controller y se muestra como error de
 * validación, nunca como 500. Mismo patrón que PapeletaException.
 */
class UsuarioException extends Exception {}
