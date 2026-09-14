<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-800 tracking-tight">Panel de Administración</h2>
    </x-slot>

    {{-- ---------- Contadores generales ---------- --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4 stagger">
        <div class="glass-card p-4 flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-brand-400 to-brand-600 flex items-center justify-center text-white shrink-0 shadow-glass">
                <x-icon name="users" class="w-5 h-5" />
            </div>
            <div>
                <p class="text-2xl font-extrabold text-gray-800">{{ $totalUsuarios }}</p>
                <p class="text-xs text-gray-500">Usuarios</p>
            </div>
        </div>
        <div class="glass-card p-4 flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-brand-400 to-brand-600 flex items-center justify-center text-white shrink-0 shadow-glass">
                <x-icon name="building" class="w-5 h-5" />
            </div>
            <div>
                <p class="text-2xl font-extrabold text-gray-800">{{ $totalAreas }}</p>
                <p class="text-xs text-gray-500">Áreas</p>
            </div>
        </div>
        <div class="glass-card p-4 flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center text-white shrink-0 shadow-glass">
                <x-icon name="map-pin" class="w-5 h-5" />
            </div>
            <div>
                <p class="text-2xl font-extrabold text-gray-800">{{ $totalSedes }}</p>
                <p class="text-xs text-gray-500">Sedes</p>
            </div>
        </div>
    </div>

    {{-- ---------- KPIs del mes ---------- --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4 stagger">
        <div class="glass-card p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-brand-50 flex items-center justify-center text-brand-600 shrink-0">
                <x-icon name="document" class="w-4 h-4" />
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-0.5">Papeletas este mes</p>
                <p class="text-2xl font-extrabold text-gray-800">{{ $kpis['total_mes'] }}</p>
            </div>
        </div>
        <div class="glass-card p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg {{ $kpis['tasa_rechazo_mes'] > 15 ? 'bg-accent-50 text-accent-600' : 'bg-brand-50 text-brand-600' }} flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-8.25 3.75h.008v.008h-.008v-.008z" /></svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-0.5">Tasa de rechazo/vencidas (mes)</p>
                <p class="text-2xl font-extrabold {{ $kpis['tasa_rechazo_mes'] > 15 ? 'text-accent-600' : 'text-gray-800' }}">
                    {{ $kpis['tasa_rechazo_mes'] }}%
                </p>
            </div>
        </div>
        <div class="glass-card p-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-brand-50 flex items-center justify-center text-brand-600 shrink-0">
                <x-icon name="clock" class="w-4 h-4" />
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-0.5">Tiempo prom. de aprobación (mes)</p>
                <p class="text-2xl font-extrabold text-gray-800">{{ $kpis['tiempo_promedio_aprobacion'] }}</p>
            </div>
        </div>
    </div>

    {{-- ---------- Tendencia diaria ---------- --}}
    <div class="glass-panel p-5 mb-4 animate-fade-in-up">
        <h3 class="font-semibold text-sm text-gray-700 mb-4">Papeletas solicitadas — últimos 14 días</h3>
        <canvas id="chartTendencia" height="90"></canvas>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
        {{-- ---------- Por estado ---------- --}}
        <div class="glass-panel p-5 animate-fade-in-up">
            <h3 class="font-semibold text-sm text-gray-700 mb-4">Papeletas por estado</h3>
            @forelse($papeletasPorEstado as $fila)
                <div class="flex items-center gap-3 py-2 border-b border-white/50 last:border-0">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $fila->color }}"></span>
                    <span class="text-sm text-gray-700 flex-1">{{ $fila->nombre }}</span>
                    <span class="text-sm font-bold text-gray-800">{{ $fila->total }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-400">Aún no hay papeletas registradas.</p>
            @endforelse
        </div>

        {{-- ---------- Top áreas ---------- --}}
        <div class="glass-panel p-5 animate-fade-in-up">
            <h3 class="font-semibold text-sm text-gray-700 mb-4">Áreas con más salidas</h3>
            @forelse($topAreas as $fila)
                <div class="flex items-center gap-3 py-2 border-b border-white/50 last:border-0">
                    <span class="text-sm text-gray-700 flex-1">{{ $fila->nombre }}</span>
                    <span class="text-sm font-bold text-gray-800">{{ $fila->total }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-400">Aún no hay papeletas registradas.</p>
            @endforelse
        </div>
    </div>

    {{-- ---------- Top motivos ---------- --}}
    <div class="glass-panel p-5 mb-4 animate-fade-in-up">
        <h3 class="font-semibold text-sm text-gray-700 mb-4">Motivos más frecuentes</h3>
        <canvas id="chartMotivos" height="80"></canvas>
    </div>

    {{-- ---------- Accesos rápidos ---------- --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 stagger">
        <a href="{{ route('users.index') }}" class="glass-card p-4 text-center">
            <x-icon name="users" class="w-6 h-6 mx-auto mb-1 text-brand-600" />
            <span class="text-sm text-gray-700 font-semibold">Usuarios</span>
        </a>
        <a href="{{ route('areas.index') }}" class="glass-card p-4 text-center">
            <x-icon name="building" class="w-6 h-6 mx-auto mb-1 text-brand-600" />
            <span class="text-sm text-gray-700 font-semibold">Áreas</span>
        </a>
        <a href="{{ route('cargos.index') }}" class="glass-card p-4 text-center">
            <x-icon name="briefcase" class="w-6 h-6 mx-auto mb-1 text-brand-600" />
            <span class="text-sm text-gray-700 font-semibold">Cargos</span>
        </a>
        <a href="{{ route('sedes.index') }}" class="glass-card p-4 text-center">
            <x-icon name="map-pin" class="w-6 h-6 mx-auto mb-1 text-brand-600" />
            <span class="text-sm text-gray-700 font-semibold">Sedes</span>
        </a>
        <a href="{{ route('motivos.index') }}" class="glass-card p-4 text-center">
            <x-icon name="clipboard" class="w-6 h-6 mx-auto mb-1 text-brand-600" />
            <span class="text-sm text-gray-700 font-semibold">Motivos</span>
        </a>
        <a href="{{ route('papeletas.index') }}" class="glass-card p-4 text-center">
            <x-icon name="document" class="w-6 h-6 mx-auto mb-1 text-brand-600" />
            <span class="text-sm text-gray-700 font-semibold">Papeletas</span>
        </a>
        <a href="{{ route('admin.auditoria') }}" class="glass-card p-4 text-center">
            <x-icon name="shield" class="w-6 h-6 mx-auto mb-1 text-brand-600" />
            <span class="text-sm text-gray-700 font-semibold">Auditoría</span>
        </a>
    </div>

    @push('scripts')
        {{-- Cargado solo en esta página: no vale la pena meter Chart.js al
        bundle de Vite para dos gráficos que solo ve el Administrador. --}}
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js" crossorigin="anonymous"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const tendencia = @json($tendenciaDiaria);
                const motivos = @json($topMotivos);

                new Chart(document.getElementById('chartTendencia'), {
                    type: 'line',
                    data: {
                        labels: tendencia.map(d => d.etiqueta),
                        datasets: [{
                            label: 'Papeletas',
                            data: tendencia.map(d => d.total),
                            borderColor: '#2c5480',
                            backgroundColor: 'rgba(44, 84, 128, 0.12)',
                            tension: 0.35,
                            fill: true,
                            pointRadius: 3,
                        }],
                    },
                    options: {
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                    },
                });

                new Chart(document.getElementById('chartMotivos'), {
                    type: 'bar',
                    data: {
                        labels: motivos.map(m => m.nombre),
                        datasets: [{
                            label: 'Papeletas',
                            data: motivos.map(m => m.total),
                            backgroundColor: '#d4652f',
                            borderRadius: 6,
                        }],
                    },
                    options: {
                        indexAxis: 'y',
                        plugins: { legend: { display: false } },
                        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
                    },
                });
            });
        </script>
    @endpush
</x-app-layout>
