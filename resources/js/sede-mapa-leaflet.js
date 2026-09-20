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

export default L;
