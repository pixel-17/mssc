<?php

declare(strict_types=1);

namespace App\Livewire\Usuarios;

use App\Actions\Usuario\CambiarEstadoUsuarioAction;
use App\Livewire\Concerns\ManejaExcepcionesDeUsuario;
use App\Livewire\Concerns\RequiereAdmin;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Panel para dar de baja a un jefe (inmediato o de área) que todavía tiene
 * gente a cargo (solo admin). Por cada unidad que encabeza se elige qué pasa
 * con la jefatura: pasársela a otra persona ya registrada, crear un jefe nuevo
 * o dejarla sin jefe. Se abre con el evento `usuario-baja-abrir` ({id}).
 *
 * La lógica vive en CambiarEstadoUsuarioAction::desactivar(); aquí solo se
 * recogen las decisiones. Avisa con `org-actualizado` (organigrama) y
 * `usuarios-actualizado` (lista de usuarios).
 */
class BajaConReemplazo extends Component
{
    use ManejaExcepcionesDeUsuario;
    use RequiereAdmin;

    public bool $abierto = false;

    #[Locked]
    public ?int $usuarioId = null;

    /**
     * unidad_id => ['modo' => ''|'existente'|'nuevo'|'vacante', 'usuario_id' => ?int, 'nuevo' => [...]].
     *
     * @var array<int|string, array<string, mixed>>
     */
    public array $decisiones = [];

    public ?string $error = null;

    #[On('usuario-baja-abrir')]
    public function abrir(int $id, CambiarEstadoUsuarioAction $estado): void
    {
        $this->autorizarAdmin();

        $usuario = User::findOrFail($id);

        $this->usuarioId = $usuario->id;
        $this->error = null;
        $this->decisiones = $estado->jefaturasPorResolver($usuario)
            ->mapWithKeys(fn (array $fila) => [$fila['unidad']->id => [
                'modo' => '',
                'usuario_id' => null,
                'nuevo' => ['name' => '', 'apellido' => '', 'dni' => '', 'email' => '', 'regimen' => $usuario->regimen ?? '276'],
            ]])
            ->all();
        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
    }

    public function confirmar(CambiarEstadoUsuarioAction $estado): void
    {
        $this->autorizarAdmin();
        $this->error = null;

        $usuario = $this->usuarioId ? User::find($this->usuarioId) : null;

        if (! $usuario) {
            $this->abierto = false;

            return;
        }

        $reemplazos = [];

        foreach ($this->decisiones as $unidadId => $decision) {
            $modo = $decision['modo'] ?? '';
            $reemplazos[$unidadId] = [
                'modo' => $modo,
                'usuario_id' => $decision['usuario_id'] ?? null,
                'nuevo' => $decision['nuevo'] ?? [],
            ];

            if ($modo === CambiarEstadoUsuarioAction::REEMPLAZO_NUEVO) {
                $correo = (string) ($decision['nuevo']['email'] ?? '');
                if ($correo !== '' && User::where('email', $correo)->exists()) {
                    $this->error = 'El correo '.$correo.' ya está registrado: usa otro o elige a esa persona como reemplazo.';

                    return;
                }
            }
        }

        if (! $this->intentar(
            fn () => $estado->desactivar(auth()->user(), $usuario, $reemplazos),
            fn ($e) => $this->error = $e->getMessage(),
        )) {
            return;
        }

        $this->abierto = false;
        $this->dispatch('usuarios-actualizado');
        $this->dispatch('org-actualizado', mensaje: 'Usuario desactivado: ya no podrá iniciar sesión.');
    }

    public function render(CambiarEstadoUsuarioAction $estado): View
    {
        if (! $this->abierto) {
            return view('livewire.usuarios.baja-con-reemplazo', ['usuario' => null, 'filas' => collect(), 'candidatos' => [], 'sedes' => []]);
        }

        $usuario = User::find($this->usuarioId);

        return view('livewire.usuarios.baja-con-reemplazo', [
            'usuario' => $usuario,
            'filas' => $usuario ? $estado->jefaturasPorResolver($usuario) : collect(),
            'candidatos' => User::with('unidadOrganica:id,nombre')
                ->where('activo', true)->whereKeyNot($this->usuarioId)
                ->whereDoesntHave('roles', fn ($r) => $r->where('name', 'admin'))
                ->orderBy('name')->orderBy('apellido')->get()
                ->map(fn (User $c) => ['id' => $c->id, 'label' => $c->nombre_completo, 'hint' => $c->unidadOrganica?->nombre])
                ->all(),
            'sedes' => Sede::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }
}
