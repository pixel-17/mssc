/**
 * Marcar retorno (resources/views/trabajador/papeletas/show.blade.php).
 *
 * Antes había dos botones separados: "Usar mi ubicación actual" (GPS)
 * y "Tomar / adjuntar foto" (input[type=file], que también permitía
 * elegir una imagen ya guardada en la galería). Se unifican en un
 * solo flujo "Tomar foto de retorno" al estilo Timestamp Camera:
 *
 *   1) Se abre la cámara en vivo (getUserMedia) y en paralelo se pide
 *      la ubicación + se resuelve la dirección contra Nominatim.
 *   2) Al capturar, se "sella" la foto con la dirección, la fecha y
 *      la hora directamente sobre el canvas, en el cliente — el
 *      servidor nunca recibe una foto sin el sello.
 *   3) La foto sellada (Blob) se inyecta al <input type="file"> oculto
 *      vía DataTransfer justo antes del submit nativo del formulario,
 *      así el backend (RetornoController) no cambia: sigue
 *      recibiendo foto + latitud + longitud por POST normal.
 *
 * No hay <input type=file> clickeable en ningún momento: la única
 * forma de llenar ese campo es capturando con la cámara en vivo, por
 * lo que no se puede adjuntar una foto de la galería.
 *
 * latitud/longitud se siguen enviando igual que antes (el backend los
 * usa para `dentro_de_radio`, ver MarcarRetornoAction) — la dirección
 * en texto es solo una lectura para el sello, no se guarda aparte.
 */

/** Nominatim pide no abusar del servicio: un intento, con timeout corto. */
async function resolverDireccion(lat, lng) {
    const controlador = new AbortController();
    const expira = setTimeout(() => controlador.abort(), 8000);

    try {
        const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&zoom=18&addressdetails=0&accept-language=es`;
        const respuesta = await fetch(url, {
            signal: controlador.signal,
            headers: { Accept: 'application/json' },
        });

        if (!respuesta.ok) {
            return null;
        }

        const datos = await respuesta.json();

        return datos.display_name ?? null;
    } catch (error) {
        // Sin internet, timeout, o Nominatim caído: se sigue con las
        // coordenadas como texto, nunca se bloquea el retorno por esto.
        return null;
    } finally {
        clearTimeout(expira);
    }
}

function formatearFechaHora(fecha) {
    const dos = (n) => String(n).padStart(2, '0');

    return {
        fecha: `${dos(fecha.getDate())}/${dos(fecha.getMonth() + 1)}/${fecha.getFullYear()}`,
        hora: `${dos(fecha.getHours())}:${dos(fecha.getMinutes())}:${dos(fecha.getSeconds())}`,
    };
}

function partirTexto(ctx, texto, anchoMaximo) {
    const palabras = texto.split(' ');
    const lineas = [];
    let lineaActual = '';

    palabras.forEach((palabra) => {
        const intento = lineaActual ? `${lineaActual} ${palabra}` : palabra;

        if (ctx.measureText(intento).width > anchoMaximo && lineaActual) {
            lineas.push(lineaActual);
            lineaActual = palabra;
        } else {
            lineaActual = intento;
        }
    });

    if (lineaActual) {
        lineas.push(lineaActual);
    }

    return lineas;
}

/** Sella fecha, hora y dirección sobre el frame capturado, estilo Timestamp Camera. */
function sellarFoto(canvas, { direccion, lat, lng }) {
    const ctx = canvas.getContext('2d');
    const ancho = canvas.width;
    const alto = canvas.height;

    const { fecha, hora } = formatearFechaHora(new Date());
    const textoUbicacion = direccion ?? `${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`;

    const paddingX = Math.round(ancho * 0.04);
    const tamanoGrande = Math.max(18, Math.round(ancho * 0.05));
    const tamanoPequeno = Math.max(13, Math.round(ancho * 0.032));
    const interlineado = Math.round(tamanoPequeno * 1.3);

    ctx.font = `${tamanoPequeno}px sans-serif`;
    const lineasDireccion = partirTexto(ctx, `📍 ${textoUbicacion}`, ancho - paddingX * 2).slice(0, 2);

    const lineas = [`🕒 ${fecha} ${hora}`, ...lineasDireccion];
    const altoBanda = Math.round(tamanoGrande * 1.6) + lineasDireccion.length * interlineado + 18;

    const degradado = ctx.createLinearGradient(0, alto - altoBanda, 0, alto);
    degradado.addColorStop(0, 'rgba(0, 0, 0, 0)');
    degradado.addColorStop(0.35, 'rgba(0, 0, 0, 0.55)');
    degradado.addColorStop(1, 'rgba(0, 0, 0, 0.75)');
    ctx.fillStyle = degradado;
    ctx.fillRect(0, alto - altoBanda, ancho, altoBanda);

    ctx.textBaseline = 'bottom';
    ctx.fillStyle = '#ffffff';
    ctx.shadowColor = 'rgba(0, 0, 0, 0.6)';
    ctx.shadowBlur = 4;

    let y = alto - 14;

    for (let i = lineas.length - 1; i >= 0; i -= 1) {
        const esFecha = i === 0;
        ctx.font = `${esFecha ? 'bold ' : ''}${esFecha ? tamanoGrande : tamanoPequeno}px sans-serif`;
        ctx.fillText(lineas[i], paddingX, y);
        y -= esFecha ? Math.round(tamanoGrande * 1.5) : interlineado;
    }

    ctx.shadowBlur = 0;
}

window.msscRetornoCamara = function () {
    return {
        // 'inicial' -> 'abriendo' -> 'en-vivo' -> 'capturada'
        estado: 'inicial',
        enviando: false,

        stream: null,
        facingMode: 'environment',

        lat: '',
        lng: '',
        direccion: null,
        ubicacionLista: false,
        ubicacionError: '',
        camaraError: '',

        fotoBlob: null,
        fotoPreviewUrl: null,

        async iniciar() {
            this.estado = 'abriendo';
            this.camaraError = '';
            this.ubicacionError = '';

            // Espera a que Alpine termine de insertar el <video x-ref="video">
            // (el x-if de arriba) antes de tocar $refs.video: si getUserMedia
            // resuelve muy rápido (permiso ya concedido antes), se adelanta
            // al render de Alpine y $refs.video todavía es undefined.
            await this.$nextTick();

            // En paralelo: la cámara no debe esperar a que resuelva el GPS.
            this.iniciarUbicacion();
            await this.abrirCamara();
        },

        async abrirCamara() {
            if (!('mediaDevices' in navigator) || !navigator.mediaDevices.getUserMedia) {
                this.camaraError = 'Este dispositivo no soporta cámara en vivo.';
                this.estado = 'inicial';
                return;
            }

            // Espera activa hasta que Alpine inserte el <video x-ref="video">
            // en el DOM. $nextTick a veces no alcanza cuando getUserMedia ya
            // tiene permiso concedido y resuelve antes de que x-if termine.
            const videoListo = await new Promise((resolve) => {
                const intentos = 20; // máx ~200 ms
                let n = 0;
                const check = () => {
                    if (this.$refs.video) return resolve(true);
                    if (++n >= intentos) return resolve(false);
                    requestAnimationFrame(check);
                };
                check();
            });

            if (!videoListo) {
                this.camaraError = 'No se pudo iniciar la cámara (elemento de video no disponible).';
                this.estado = 'inicial';
                return;
            }

            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: this.facingMode } },
                    audio: false,
                });

                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                this.estado = 'en-vivo';
            } catch (error) {
                this.camaraError = 'No se pudo abrir la cámara: ' + (error.message || 'permiso denegado.');
                this.estado = 'inicial';
            }
        },

        async cambiarCamara() {
            this.facingMode = this.facingMode === 'environment' ? 'user' : 'environment';
            this.detenerStream();
            await this.abrirCamara();
        },

        iniciarUbicacion() {
            if (!('geolocation' in navigator)) {
                this.ubicacionError = 'Este dispositivo no soporta geolocalización.';
                return;
            }

            navigator.geolocation.getCurrentPosition(
                async (posicion) => {
                    this.lat = posicion.coords.latitude;
                    this.lng = posicion.coords.longitude;
                    this.ubicacionLista = true;
                    this.direccion = await resolverDireccion(this.lat, this.lng);
                },
                (error) => {
                    this.ubicacionError = 'No se pudo obtener tu ubicación: ' + error.message;
                },
                { enableHighAccuracy: true, timeout: 15000 }
            );
        },

        capturar() {
            if (this.estado !== 'en-vivo' || !this.ubicacionLista) {
                return;
            }

            const video = this.$refs.video;
            const canvas = this.$refs.canvas;
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            sellarFoto(canvas, { direccion: this.direccion, lat: this.lat, lng: this.lng });

            canvas.toBlob(
                (blob) => {
                    this.fotoBlob = blob;
                    this.fotoPreviewUrl = URL.createObjectURL(blob);
                    this.estado = 'capturada';
                    // Ya no hace falta mantener la cámara prendida mientras se revisa/envía.
                    this.detenerStream();
                },
                'image/jpeg',
                0.85
            );
        },

        reintentar() {
            if (this.fotoPreviewUrl) {
                URL.revokeObjectURL(this.fotoPreviewUrl);
            }
            this.fotoBlob = null;
            this.fotoPreviewUrl = null;
            this.iniciar();
        },

        detenerStream() {
            this.stream?.getTracks().forEach((pista) => pista.stop());
            this.stream = null;
        },

        /** Inyecta la foto sellada al input real justo antes del submit nativo del form. */
        prepararEnvio(evento) {
            if (this.enviando) {
                evento.preventDefault();
                return;
            }

            if (!this.fotoBlob || !this.lat || !this.lng) {
                evento.preventDefault();
                this.camaraError = 'Primero captura la foto de retorno.';
                return;
            }

            const archivo = new File([this.fotoBlob], 'retorno.jpg', { type: 'image/jpeg' });
            const transferencia = new DataTransfer();
            transferencia.items.add(archivo);
            this.$refs.inputFoto.files = transferencia.files;

            this.enviando = true;
        },
    };
};