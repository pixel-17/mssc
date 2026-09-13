<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-10">

            <h2 class="font-bold text-2xl text-ocean-950 dark:text-white leading-tight tracking-tight">
                {{ __('Dashboard') }}
            </h2>

            {{-- ============================= ADMIN ============================= --}}
            @if ($esAdmin)
                <section class="space-y-4 animate-fade-in-up">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <h3 class="text-lg font-bold text-ocean-950 dark:text-white">Administración</h3>
                        <a href="{{ route('catalogos.index') }}" class="btn-ocean-outline text-xs">
                            Ir al panel de catálogos
                        </a>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <x-stat-card
                            label="Usuarios totales"
                            :value="$admin['usuarios_total']"
                            hint="{{ $admin['usuarios_por_rol']['admin'] }} admin · {{ $admin['usuarios_por_rol']['rrhh'] }} RRHH · {{ $admin['usuarios_por_rol']['trabajador'] }} trabajador"
                            href="/admin/users"
                        >
                            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg></x-slot:icon>
                        </x-stat-card>

                        <x-stat-card
                            label="Papeletas activas ahora"
                            :value="$admin['papeletas_activas']"
                            hint="En algún paso del flujo, sin cerrar"
                            tono="ocean"
                        >
                            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" /></svg></x-slot:icon>
                        </x-stat-card>

                        <x-stat-card
                            label="Emergencias activas"
                            :value="$admin['emergencias_activas']"
                            hint="Bypass de aprobación en curso"
                            tono="red"
                        >
                            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg></x-slot:icon>
                        </x-stat-card>

                        <x-stat-card
                            label="Retornos manuales (30 días)"
                            :value="$admin['retornos_manuales_periodo']"
                            hint="Marcados sin foto/GPS por falla de conectividad"
                            tono="amber"
                        >
                            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg></x-slot:icon>
                        </x-stat-card>

                        <x-stat-card label="Papeletas nuevas (30 días)" :value="$admin['papeletas_periodo']" />
                        <x-stat-card label="Sedes activas" :value="$admin['sedes_activas']" href="{{ route('sedes.index') }}" />
                        <x-stat-card label="Unidades orgánicas activas" :value="$admin['unidades_organicas']" href="/admin/unidad-organicas" />
                    </div>

                    <div class="glass-card p-5">
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-ocean-50/80 mb-4">Papeletas por sede (últimos 30 días)</h4>
                        <x-bar-list :items="$admin['papeletas_por_sede']" />
                    </div>
                </section>
            @endif

            {{-- ============================= RRHH ============================= --}}
            @if ($esRrhh)
                <section class="space-y-4 animate-fade-in-up">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <h3 class="text-lg font-bold text-ocean-950 dark:text-white">RRHH</h3>
                        <a href="{{ route('rrhh.papeletas.index') }}" class="btn-ocean text-xs">
                            Ir a la bandeja de RRHH
                        </a>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <x-stat-card
                            label="Pendientes de decisión"
                            :value="$rrhh['pendientes_decision']"
                            href="{{ route('rrhh.papeletas.index') }}"
                            tono="amber"
                        >
                            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></x-slot:icon>
                        </x-stat-card>

                        <x-stat-card
                            label="Revisión post-hoc pendiente"
                            :value="$rrhh['posthoc_pendientes']"
                            hint="Autorizadas fuera de horario de RRHH"
                            href="{{ route('rrhh.papeletas.index') }}"
                        />

                        <x-stat-card
                            label="Sustentos por revisar"
                            :value="$rrhh['sustentos_por_revisar']"
                            href="{{ route('rrhh.papeletas.index') }}"
                            tono="purple"
                        />

                        <x-stat-card
                            label="Emergencias activas"
                            :value="$rrhh['emergencias_activas']"
                            tono="red"
                        />

                        <x-stat-card
                            label="Tiempo promedio de resolución"
                            :value="$rrhh['promedio_resolucion_minutos'] !== null
                                ? intdiv($rrhh['promedio_resolucion_minutos'], 60).'h '.($rrhh['promedio_resolucion_minutos'] % 60).'m'
                                : null"
                            hint="Últimos 30 días"
                        />
                    </div>

                    <div class="glass-card p-5">
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-ocean-50/80 mb-4">Papeletas por motivo (últimos 30 días)</h4>
                        <x-bar-list :items="$rrhh['papeletas_por_motivo']" />
                    </div>
                </section>
            @endif

            {{-- ============================= JEFE ============================= --}}
            @if ($esJefe)
                <section class="space-y-4 animate-fade-in-up">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <h3 class="text-lg font-bold text-ocean-950 dark:text-white">Tu equipo</h3>
                        <a href="{{ route('jefe.papeletas.index') }}" class="btn-ocean text-xs">
                            Ir a la bandeja de Jefe
                        </a>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <x-stat-card label="Trabajadores a tu cargo" :value="$jefe['equipo_total']" />

                        <x-stat-card
                            label="Por decidir"
                            :value="$jefe['por_decidir']"
                            href="{{ route('jefe.papeletas.index') }}"
                            tono="amber"
                        >
                            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></x-slot:icon>
                        </x-stat-card>

                        <x-stat-card
                            label="Observaciones RRHH por reconocer"
                            :value="$jefe['observaciones_rrhh']"
                            href="{{ route('jefe.papeletas.index') }}"
                            tono="red"
                        />

                        <x-stat-card
                            label="En curso"
                            :value="$jefe['en_curso']"
                            href="{{ route('jefe.papeletas.index') }}"
                        />

                        <x-stat-card
                            label="Sustentos por revisar"
                            :value="$jefe['sustentos_por_revisar']"
                            href="{{ route('jefe.papeletas.index') }}"
                            tono="purple"
                        />

                        <x-stat-card
                            label="Tasa de aprobación"
                            :value="$jefe['tasa_aprobacion_periodo'] !== null ? $jefe['tasa_aprobacion_periodo'].'%' : null"
                            hint="Últimos 30 días"
                            tono="emerald"
                        />
                    </div>
                </section>
            @endif

        </div>
    </div>
</div>
