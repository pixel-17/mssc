/**
 * Selector de ubicación en mapa para el formulario de Sede
 * (resources/views/livewire/sedes/sede-form.blade.php).
 *
 * Reemplaza los inputs manuales de latitud/longitud: el admin marca
 * el lugar/zona haciendo clic o arrastrando el marcador, y este
 * componente llena `latitud`/`longitud` directamente en el
 * componente Livewire (App\Livewire\Sedes\SedeForm) vía $wire.set().
 *
 * Leaflet (JS + CSS + iconos, ~150 kB) NO va en el bundle general: solo
 * el formulario de Sede lo usa, así que se descarga con import() dinámico
 * cuando el mapa aparece (ver ./sede-mapa-leaflet.js). Este archivo solo
 * define la fábrica de Alpine y pesa casi nada.
 *
 * El radio (radioMetros) se sigue escribiendo en su propio input
 * numérico normal (wire:model.live); acá solo se observa para que el
 * círculo del mapa se ajuste en vivo.
 */
window.msscMapaSede = function (config) {
    return {
        mapa: null,
        marcador: null,
        circulo: null,

        async init() {
            const { default: L } = await import('./sede-mapa-leaflet');

            // Si el usuario navegó a otra página mientras se descargaba Leaflet,
            // el contenedor ya no existe.
            if (! this.$refs.mapa?.isConnected) {
                return;
            }

            this.mapa = L.map(this.$refs.mapa).setView([config.lat, config.lng], config.zoom);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(this.mapa);

            this.marcador = L.marker([config.lat, config.lng], { draggable: true }).addTo(this.mapa);
            this.circulo = L.circle([config.lat, config.lng], {
                radius: config.radio,
                color: '#0f1c2e',
                fillOpacity: 0.15,
            }).addTo(this.mapa);

            this.marcador.on('dragend', () => this.moverA(this.marcador.getLatLng()));

            this.mapa.on('click', (evento) => {
                this.marcador.setLatLng(evento.latlng);
                this.moverA(evento.latlng);
            });

            this.$wire.$watch('radioMetros', (valor) => {
                if (this.circulo && valor) {
                    this.circulo.setRadius(Number(valor));
                }
            });
        },

        destroy() {
            this.mapa?.remove();
            this.mapa = null;
        },

        moverA(latlng) {
            this.circulo.setLatLng(latlng);
            this.$wire.set('latitud', Number(latlng.lat.toFixed(7)));
            this.$wire.set('longitud', Number(latlng.lng.toFixed(7)));
        },
    };
};
