import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Los iconos por defecto de Leaflet se rompen con el bundling de Vite
// (referencian rutas relativas que no existen tras el build); se
// reemplazan por los mismos assets pero resueltos por Vite.
import iconRetina from 'leaflet/dist/images/marker-icon-2x.png';
import icon from 'leaflet/dist/images/marker-icon.png';
import iconSombra from 'leaflet/dist/images/marker-shadow.png';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: iconRetina,
    iconUrl: icon,
    shadowUrl: iconSombra,
});

/**
 * Selector de ubicación en mapa para el formulario de Sede
 * (resources/views/livewire/sedes/sede-form.blade.php).
 *
 * Reemplaza los inputs manuales de latitud/longitud: el admin marca
 * el lugar/zona haciendo clic o arrastrando el marcador, y este
 * componente llena `latitud`/`longitud` directamente en el
 * componente Livewire (App\Livewire\Sedes\SedeForm) vía $wire.set().
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

        init() {
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

        moverA(latlng) {
            this.circulo.setLatLng(latlng);
            this.$wire.set('latitud', Number(latlng.lat.toFixed(7)));
            this.$wire.set('longitud', Number(latlng.lng.toFixed(7)));
        },
    };
};
