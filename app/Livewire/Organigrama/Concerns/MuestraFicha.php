<?php

declare(strict_types=1);

namespace App\Livewire\Organigrama\Concerns;

/**
 * Parte de OrganigramaArbol: panel lateral con la ficha de una persona, y
 * el atajo «Mover a otra unidad» desde ahí (misma confirmación que
 * arrastrar, sin arrastrar).
 *
 * Extraído de OrganigramaArbol únicamente para que el archivo principal no
 * crezca sin límite: sigue siendo la misma clase en tiempo de ejecución
 * (un trait se "aplana" dentro de la clase que lo usa), así que accede
 * sin problema a autorizar(), proponerMovimiento() (del trait
 * MueveTrabajador) y al resto de OrganigramaArbol.
 */
trait MuestraFicha
{
    public ?int $personaId = null;

    /**
     * Unidad elegida en «Mover a otra unidad» de la ficha (alternativa al
     * arrastre, que no funciona en táctil ni con teclado). Viene del
     * navegador: solo se usa como id para proponerMovimiento(), que lo
     * revalida entero.
     */
    public ?int $destinoMover = null;

    public function verPersona(int $id): void
    {
        $this->personaId = $id;
        $this->destinoMover = null;
        $this->resetErrorBag('destinoMover');
    }

    public function cerrarPersona(): void
    {
        $this->personaId = null;
        $this->destinoMover = null;
        $this->resetErrorBag('destinoMover');
    }

    /**
     * «Mover a otra unidad» desde la ficha: misma confirmación que al soltar
     * una persona sobre una unidad (proponerMovimiento), pero sin arrastrar.
     * Se cierra la ficha para que la ventana de confirmación no quede
     * apilada encima de ella.
     */
    public function moverDesdeFicha(): void
    {
        $this->autorizar();

        $personaId = $this->personaId;
        $destinoId = $this->destinoMover;

        if (! $this->modoEdicion || $personaId === null) {
            return;
        }

        if ($destinoId === null) {
            $this->addError('destinoMover', 'Elige la unidad de destino.');

            return;
        }

        $this->cerrarPersona();
        $this->proponerMovimiento($personaId, $destinoId);
    }
}
