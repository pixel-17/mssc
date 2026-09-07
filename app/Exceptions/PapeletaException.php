<?php

namespace App\Exceptions;

use Exception;

/**
 * Cualquier violación a una regla de negocio del flujo (ventana de
 * turno, exclusividad, transición inválida, etc). Se atrapa en el
 * controller/Livewire component y se muestra como error de validación,
 * nunca como 500.
 */
class PapeletaException extends Exception {}
