<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-10">

            <div>
                <h2 class="font-bold text-2xl text-tinta-950 dark:text-white leading-tight tracking-tight">
                    {{ __('Dashboard') }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-tinta-100/60 mt-1">Hola, {{ $nombre }}.</p>
            </div>

            {{-- ============================= ADMIN ============================= --}}
            @if ($esAdmin)
                <section class="space-y-6 animate-fade-in-up">
                    <h3 class="text-lg font-bold text-tinta-950 dark:text-white">Administración</h3>

                    {{-- Alertas: solo se muestran si hay algo que requiere atención --}}
                    @if ($admin['papeletas_vencidas'] > 0 || $admin['retornos_manuales_periodo'] > 0)
                        <div class="flex flex-col sm:flex-row gap-3">
                            @if ($admin['papeletas_vencidas'] > 0)
                                <div class="flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300 flex-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                                    <span><strong>{{ $admin['papeletas_vencidas'] }}</strong> {{ Str::plural('papeleta', $admin['papeletas_vencidas']) }} vencida{{ $admin['papeletas_vencidas'] > 1 ? 's' : '' }} — el jefe no respondió a tiempo</span>
                                </div>
                            @endif
                            @if ($admin['retornos_manuales_periodo'] > 0)
                                <div class="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300 flex-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                                    <span><strong>{{ $admin['retornos_manuales_periodo'] }}</strong> retorno{{ $admin['retornos_manuales_periodo'] > 1 ? 's' : '' }} manual{{ $admin['retornos_manuales_periodo'] > 1 ? 'es' : '' }} en 30 días — sin foto ni GPS</span>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Flujo activo --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <x-stat-card
                            label="En flujo ahora"
                            :value="$admin['papeletas_activas']"
                            hint="{{ $admin['papeletas_pendiente_jefe'] }} con el jefe · {{ $admin['papeletas_pendiente_rrhh'] }} con RR. HH."
                            tono="tinta"
                        >
                            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" /></svg></x-slot:icon>
                        </x-stat-card>

                        <x-stat-card
                            label="Nuevas (30 días)"
                            :value="$admin['papeletas_periodo']"
                            tono="azul"
                        />

                        <x-stat-card
                            label="Usuarios"
                            :value="$admin['usuarios_total']"
                            hint="{{ $admin['usuarios_por_rol']['trabajador'] }} trabajadores · {{ $admin['usuarios_por_rol']['rrhh'] }} RR. HH. · {{ $admin['usuarios_por_rol']['admin'] }} admin"
                            href="{{ route('usuarios-admin.index') }}"
                        >
                            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg></x-slot:icon>
                        </x-stat-card>
                    </div>

                    {{-- Accesos rápidos --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        @foreach ([
                            ['Organigrama',       route('organigrama.index'),          'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zm0 9.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0115.75 3.75H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zm0 9.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z'],
                            ['Sedes',             route('sedes.index'),                'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21'],
                            ['Usuarios',          route('usuarios-admin.index'),       'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z'],
                            ['Catálogos',         route('catalogos.index'),            'M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75'],
                        ] as [$label, $href, $path])
                            <a href="{{ $href }}" class="glass-card flex flex-col items-center gap-2 p-4 text-center text-sm font-medium text-tinta-800 dark:text-tinta-100 hover:bg-tinta-50 dark:hover:bg-white/5 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-6 text-tinta-400 dark:text-tinta-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
                                </svg>
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>

                    {{-- Papeletas por sede --}}
                    @if ($admin['papeletas_por_sede']->isNotEmpty())
                        <div class="glass-card p-5">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 mb-4">Papeletas por sede — últimos 30 días</h4>
                            <x-bar-list :items="$admin['papeletas_por_sede']" />
                        </div>
                    @endif
                </section>
            @endif

            {{-- ============================= RRHH ============================= --}}
            @if ($esRrhh)
                <section class="space-y-4 animate-fade-in-up">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <h3 class="text-lg font-bold text-tinta-950 dark:text-white">RRHH</h3>
                        <a href="{{ route('rrhh.papeletas.index') }}" class="btn-primary text-xs">
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
                            label="Revisión posterior pendiente"
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
                            label="Tiempo promedio de resolución"
                            :value="$rrhh['promedio_resolucion_minutos'] !== null
                                ? intdiv($rrhh['promedio_resolucion_minutos'], 60).'h '.($rrhh['promedio_resolucion_minutos'] % 60).'m'
                                : null"
                            hint="Últimos 30 días"
                        />
                    </div>

                    <div class="glass-card p-5">
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 mb-4">Papeletas por motivo (últimos 30 días)</h4>
                        <x-bar-list :items="$rrhh['papeletas_por_motivo']" />
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        {{-- Quién está afuera ahora mismo --}}
                        <div class="glass-card p-5">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80">Afuera ahora mismo</h4>
                                <span class="text-xs text-gray-500 dark:text-tinta-100/50">{{ $rrhh['trabajadores_afuera_total'] }} en curso</span>
                            </div>
                            @if ($rrhh['trabajadores_afuera']->isEmpty())
                                <p class="text-sm text-gray-500 dark:text-tinta-100/50">Nadie está afuera en este momento.</p>
                            @else
                                <ul class="divide-y divide-gray-100 dark:divide-white/10">
                                    @foreach ($rrhh['trabajadores_afuera'] as $papeleta)
                                        <li class="py-2 flex items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $papeleta->trabajador->nombre_completo }}</p>
                                                <p class="text-xs text-gray-500 dark:text-tinta-100/50 truncate">{{ $papeleta->motivo->nombre }}</p>
                                            </div>
                                            <span class="text-xs text-gray-500 dark:text-tinta-100/50 shrink-0" title="{{ $papeleta->hora_salida_real?->format('d/m/Y H:i') }}">
                                                {{ $papeleta->hora_salida_real?->diffForHumans(null, true) }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                                @if ($rrhh['trabajadores_afuera_total'] > $rrhh['trabajadores_afuera']->count())
                                    <p class="text-xs text-gray-500 dark:text-tinta-100/50 mt-2">
                                        +{{ $rrhh['trabajadores_afuera_total'] - $rrhh['trabajadores_afuera']->count() }} más
                                    </p>
                                @endif
                            @endif
                        </div>

                        {{-- Top trabajadores por cantidad de papeletas --}}
                        <div class="glass-card p-5">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 mb-4">Top trabajadores por papeletas (últimos 30 días)</h4>
                            <x-bar-list :items="$rrhh['papeletas_por_trabajador']" vacio="Sin papeletas en este periodo." />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        {{-- Horas acumuladas del mes --}}
                        <div class="glass-card p-5">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 capitalize">Horas acumuladas — {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $rrhh['horas_acumuladas_mes'])->translatedFormat('F Y') }}</h4>
                                <a href="{{ route('reportes.horas-acumuladas') }}" class="text-xs text-tinta-600 dark:text-tinta-300 hover:text-tinta-900 dark:hover:text-tinta-100 font-medium">Ver reporte completo →</a>
                            </div>
                            @if ($rrhh['horas_acumuladas_top']->isEmpty())
                                <p class="text-sm text-gray-500 dark:text-tinta-100/50">Sin retornos registrados este mes.</p>
                            @else
                                <ul class="divide-y divide-gray-100 dark:divide-white/10">
                                    @foreach ($rrhh['horas_acumuladas_top'] as $fila)
                                        <li class="py-2 flex items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $fila['trabajador'] }}</p>
                                                <p class="text-xs text-gray-500 dark:text-tinta-100/50">{{ $fila['papeletas'] }} papeleta(s)</p>
                                            </div>
                                            <span class="text-sm font-semibold text-tinta-950 dark:text-white shrink-0">
                                                {{ intdiv($fila['minutos_totales'], 60) }}h {{ $fila['minutos_totales'] % 60 }}m
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        {{-- Seguimiento: rechazadas u observadas por RRHH --}}
                        <div class="glass-card p-5">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-tinta-50/80 mb-4">Seguimiento (rechazadas u observadas, 30 días)</h4>
                            <x-bar-list :items="$rrhh['seguimiento_por_trabajador']" vacio="Sin rechazos ni observaciones en este periodo." />
                        </div>
                    </div>
                </section>
            @endif

            {{-- ============================= JEFE ============================= --}}
            @if ($esJefe)
                <section class="space-y-4 animate-fade-in-up">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <h3 class="text-lg font-bold text-tinta-950 dark:text-white">Tu equipo</h3>
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($esJefeInmediatoPuro)
                                <a href="{{ route('organigrama.index') }}" class="btn-secondary text-xs">
                                    Ver mi organigrama
                                </a>
                            @endif
                            <a href="{{ route('usuarios.index') }}" class="btn-secondary text-xs">
                                Mis trabajadores
                            </a>
                            <a href="{{ route('jefe.papeletas.index') }}" class="btn-primary text-xs">
                                Ir a la bandeja de Jefe
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <x-stat-card label="Trabajadores a tu cargo" :value="$jefe['equipo_total']" href="{{ route('usuarios.index') }}">
                            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg></x-slot:icon>
                        </x-stat-card>

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
                        >
                            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M15.75 3v1.5M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg></x-slot:icon>
                        </x-stat-card>

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
