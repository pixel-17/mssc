<?php

declare(strict_types=1);

namespace App\Livewire\Organigrama;

use App\Livewire\Organigrama\Concerns\MuestraFicha;
use App\Livewire\Organigrama\Concerns\MueveJefe;
use App\Livewire\Organigrama\Concerns\MueveTrabajador;
use App\Models\Sede;
use App\Models\User;
use App\Services\Organigrama\ArmadorOrganigrama;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Organigrama como árbol: unidades → jefe inmediato → trabajadores, con la
 * sede de cada persona, para ver de un vistazo quién está ligado a quién.
 *
 * - Filtro por sede (`$sede`): '' = todas, 'sin' = sin sede, o el id de una sede.
 * - Panel lateral (`$personaId`): ficha de la persona. Solo se abre para
 *   gente que cae dentro del alcance de quien mira (se revalida en cada render).
 * - Modo edición (`$modoEdicion`): interruptor que habilita arrastrar un
 *   trabajador a otra unidad. Soltarlo NO mueve nada: abre una
 *   confirmación con lo que cambia (`$propuesta`). Todas las reglas las
 *   valida MoverTrabajadorAction en el servidor; aquí solo se orquesta.
 * - Un jefe inmediato (admin) se arrastra sobre su nueva área: se mueve su
 *   unidad con su gente (MoverJefeInmediatoAction), también con confirmación.
 * - Historial: los últimos movimientos (alcance de quien mira), con
 *   opción de deshacer.
 *
 * - Admin: todo el organigrama (desde las unidades raíz).
 * - Jefe de Área (encabeza una unidad que tiene sub-unidades): su unidad y todo lo que
 *   cuelga de ella. Si encabeza varias, solo se pintan las de más
 *   arriba (las demás ya salen dentro).
 * - Cualquier otro (incluido el Jefe Inmediato de una unidad hoja): 403. La autorización se repite en render() porque
 *   las llamadas de Livewire no re-evalúan el middleware de la ruta.
 *
 * Capa fina: el armado del árbol (consultas, alcance, filtros, ficha e
 * historial) vive en ArmadorOrganigrama; las reglas y ediciones, en
 * App\Actions\Organigrama (reglas compartidas: ReglasOrganigrama).
 *
 * Esta clase quedó grande (mover un trabajador, mover un jefe y la ficha
 * lateral son tres flujos bien distintos, cada uno con su propia
 * confirmación), así que cada flujo vive en su propio trait dentro de
 * Concerns/ — un trait es la misma clase en tiempo de ejecución, solo
 * separa el archivo físico: no cambia nada de cómo Livewire la ve ni de
 * cómo se comporta.
 *
 * - MueveTrabajador: arrastrar/confirmar el movimiento de un trabajador
 *   raso, y el historial (deshacer incluido).
 * - MueveJefe: arrastrar/confirmar el movimiento de un jefe (inmediato o
 *   de área), con sus tres formas posibles.
 * - MuestraFicha: panel lateral de una persona y su atajo de mover sin
 *   arrastrar.
 */
#[Layout('layouts.app')]
#[Title('Organigrama del área')]
class OrganigramaArbol extends Component
{
    use MuestraFicha;
    use MueveJefe;
    use MueveTrabajador;

    public string $buscar = '';

    public bool $verInactivos = false;

    /** '' = todas las sedes, 'sin' = personas sin sede, o el id de una sede. */
    public string $sede = '';

    public bool $modoEdicion = false;

    public ?string $mensajeOk = null;

    public ?string $mensajeError = null;

    public function mount(): void
    {
        $this->autorizarLectura();

        // Vuelta desde «Edición completa» (UsuarioForm, ?volver=organigrama):
        // reabre la ficha de la misma persona en vez de dejar el panel cerrado.
        $personaId = request()->integer('persona') ?: null;

        if ($personaId) {
            $this->personaId = $personaId;
        }
    }

    public function updatedSede(): void
    {
        // El valor viene del navegador: solo se aceptan los que el selector ofrece.
        if ($this->sede !== '' && $this->sede !== 'sin' && ! ctype_digit($this->sede)) {
            $this->sede = '';
        }
    }

    public function limpiarSede(): void
    {
        $this->sede = '';
    }

    public function alternarEdicion(): void
    {
        $this->autorizar();

        $this->modoEdicion = ! $this->modoEdicion;
        $this->propuesta = null;
        $this->propuestaJefe = null;
        $this->mensajeOk = $this->mensajeError = null;
    }

    public function cancelarMovimiento(): void
    {
        $this->propuesta = null;
        $this->propuestaJefe = null;
    }

    /** Un modal de edición (unidad/trabajador) guardó algo: se repinta el árbol y se muestra el aviso. */
    #[On('org-actualizado')]
    public function alActualizar(?string $mensaje = null): void
    {
        $this->autorizar();
        $this->propuesta = null;
        $this->propuestaJefe = null;
        $this->mensajeError = null;

        if ($mensaje !== null) {
            $this->mensajeOk = $mensaje;
        }
    }

    /** Puede EDITAR (arrastrar, mover, activar modo edición): admin o Jefe de Área. */
    private function autorizar(): User
    {
        /** @var User|null $usuario */
        $usuario = auth()->user();

        abort_unless(
            $usuario && ($usuario->hasRole('admin') || $usuario->esJefeDeArea()),
            403,
        );

        return $usuario;
    }

    /**
     * Puede VER el organigrama (de solo lectura): admin, Jefe de Área o
     * Jefe Inmediato "puro" consultando la estructura de su propia unidad
     * (antes solo entraba el Jefe de Área; el Jefe Inmediato de una unidad
     * hoja recibía 403). esJefeDeEquipo() cubre ambos casos.
     */
    private function autorizarLectura(): User
    {
        /** @var User|null $usuario */
        $usuario = auth()->user();

        abort_unless(
            $usuario && ($usuario->hasRole('admin') || $usuario->esJefeDeEquipo()),
            403,
        );

        return $usuario;
    }

    public function render(): View
    {
        $usuario = $this->autorizarLectura();
        $puedeEditar = $usuario->hasRole('admin') || $usuario->esJefeDeArea();

        // El Jefe Inmediato "puro" solo consulta: si por alguna razón quedó
        // con modoEdicion activo (no debería, el botón ni se le muestra),
        // se fuerza a false para no exponer arrastre/ediciones sin permiso.
        if (! $puedeEditar) {
            $this->modoEdicion = false;
        }

        $armador = new ArmadorOrganigrama($this->buscar, $this->sede, $this->verInactivos);
        ['raices' => $raices, 'stats' => $stats, 'conteoSedes' => $conteoSedes, 'unidades' => $unidades, 'enAlcance' => $enAlcance] = $armador->armar($usuario);

        $ficha = $armador->fichaDe($this->personaId, $unidades, $enAlcance);

        return view('livewire.organigrama.organigrama-arbol', [
            'raices' => $raices,
            'stats' => $stats,
            'esAdmin' => $usuario->hasRole('admin'),
            // Para mostrar «Agregar sub-unidad» por unidad: el Jefe de Área puede crear
            // ramas nuevas dentro de su propia área (ver UnidadModal/GuardarUnidadOrganicaAction).
            'esJefeDeArea' => $usuario->esJefeDeArea(),
            'puedeEditar' => $puedeEditar,
            'sedes' => Sede::orderBy('nombre')->get(['id', 'nombre']),
            'conteoSedes' => $conteoSedes,
            'ficha' => $ficha,
            'destinosMover' => $this->destinosParaMover($ficha, $unidades, $enAlcance),
            'movimiento' => $this->vistaPreviaMovimiento($usuario),
            'movimientoJefe' => $this->vistaPreviaMovimientoJefe($usuario),
            'historial' => $armador->historialDe($usuario, $enAlcance, $this->modoEdicion),
        ]);
    }
}
