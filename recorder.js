/**
 * Screen Recorder Pro - JavaScript Optimizado
 * Versión: 2.0
 * Formato de salida: MP4 (H.264) con máxima compatibilidad
 */

class ScreenRecorderPro {
    constructor() {
        this.mediaRecorder = null;
        this.recordedChunks = [];
        this.stream = null;
        this.startTime = null;
        this.timer = null;
        this.recordingDuration = 0;
        this.autoStopTimer = null;
    }
    
    /**
     * Iniciar grabación con configuración optimizada para MP4
     */
    async startRecording(options = {}) {
        try {
            // Primero, solicitar captura de pantalla normal
            const displayMediaOptions = {
                video: {
                    width: { ideal: options.width || 1920 },
                    height: { ideal: options.height || 1080 },
                    frameRate: { ideal: options.fps || 30 }
                },
                audio: options.audio || false
            };
            
            // Solicitar captura (el navegador mostrará sus opciones nativas)
            this.stream = await navigator.mediaDevices.getDisplayMedia(displayMediaOptions);
            
            // Si el usuario seleccionó modo "región personalizada", mostrar selector
            if (options.captureMode === 'region') {
                showResult('📐 Ahora selecciona el área específica que deseas grabar...', true);
                
                // Pequeña pausa para que se vea el mensaje
                await new Promise(resolve => setTimeout(resolve, 500));
                
                // Mostrar selector de región
                const region = await this.selectRegion(this.stream);
                
                if (!region) {
                    // Usuario canceló la selección
                    this.stream.getTracks().forEach(track => track.stop());
                    return { success: false, error: 'Selección de región cancelada' };
                }
                
                // Aplicar recorte al stream de video
                this.stream = await this.cropStreamToRegion(this.stream, region, options);
            }
            
            // Agregar audio del micrófono si está habilitado
            if (options.microphoneAudio) {
                try {
                    const micStream = await navigator.mediaDevices.getUserMedia({
                        audio: {
                            echoCancellation: true,
                            noiseSuppression: true,
                            autoGainControl: true,
                            sampleRate: 48000
                        }
                    });
                    
                    // Combinar streams de audio
                    const audioContext = new AudioContext();
                    const destination = audioContext.createMediaStreamDestination();
                    
                    // Audio de la pantalla (si existe)
                    const screenAudioTracks = this.stream.getAudioTracks();
                    if (screenAudioTracks.length > 0) {
                        const screenSource = audioContext.createMediaStreamSource(
                            new MediaStream(screenAudioTracks)
                        );
                        screenSource.connect(destination);
                    }
                    
                    // Audio del micrófono
                    const micSource = audioContext.createMediaStreamSource(micStream);
                    micSource.connect(destination);
                    
                    // Crear stream combinado
                    const videoTracks = this.stream.getVideoTracks();
                    const audioTracks = destination.stream.getAudioTracks();
                    this.stream = new MediaStream([...videoTracks, ...audioTracks]);
                    
                } catch (error) {
                    console.warn('No se pudo acceder al micrófono:', error);
                }
            }
            
            // Configurar MediaRecorder con codec MP4
            const mimeType = this.getBestMimeType();
            console.log('Usando codec:', mimeType);
            
            const recorderOptions = {
                mimeType: mimeType,
                videoBitsPerSecond: options.bitrate || 5000000
            };
            
            this.mediaRecorder = new MediaRecorder(this.stream, recorderOptions);
            this.recordedChunks = [];
            this.startTime = Date.now();
            
            // Event handlers
            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data && event.data.size > 0) {
                    this.recordedChunks.push(event.data);
                }
            };
            
            this.mediaRecorder.onstop = () => {
                this.saveRecording();
                this.stopTimer();
            };
            
            this.mediaRecorder.onerror = (event) => {
                console.error('Error en MediaRecorder:', event);
                showResult('❌ Error durante la grabación', false);
                this.cleanup();
            };
            
            // Detectar cuando el usuario cierra la ventana compartida
            this.stream.getVideoTracks()[0].addEventListener('ended', () => {
                this.stopRecording();
                showResult('ℹ️ Grabación detenida: compartir pantalla finalizado', true);
            });
            
            // Iniciar grabación
            this.mediaRecorder.start(1000);
            this.startTimer();
            
            return { 
                success: true, 
                message: 'Grabación iniciada correctamente',
                codec: mimeType
            };
            
        } catch (error) {
            console.error('Error al iniciar grabación:', error);
            
            let errorMsg = 'Error desconocido';
            if (error.name === 'NotAllowedError') {
                errorMsg = 'Permiso denegado para capturar pantalla';
            } else if (error.name === 'NotFoundError') {
                errorMsg = 'No se encontró dispositivo de captura';
            } else if (error.name === 'NotSupportedError') {
                errorMsg = 'Captura de pantalla no soportada en este navegador';
            } else {
                errorMsg = error.message;
            }
            
            return { 
                success: false, 
                error: errorMsg 
            };
        }
    }
    
    /**
     * Selector de región - Mostrar overlay para seleccionar área
     */
    async selectRegion(stream) {
        console.log('>>> selectRegion() llamado');
        
        return new Promise((resolve, reject) => {
            console.log('>>> Creando elementos del selector...');
            
            // Timeout de seguridad (30 segundos)
            const timeout = setTimeout(() => {
                console.error('>>> TIMEOUT: selectRegion tardó más de 30 segundos');
                reject(new Error('Timeout: La selección de región tardó demasiado'));
            }, 30000);
            
            const video = document.createElement('video');
            video.srcObject = stream;
            video.autoplay = true;
            video.muted = true;
            
            console.log('>>> Video element creado, iniciando play...');
            
            const playPromise = video.play();
            if (playPromise !== undefined) {
                playPromise.then(() => {
                    console.log('>>> Video play() exitoso');
                }).catch(err => {
                    console.error('>>> Error al reproducir video:', err);
                });
            }
            
            // Agregar evento de error
            video.onerror = (e) => {
                console.error('>>> Error en video element:', e);
                clearTimeout(timeout);
                reject(new Error('Error al cargar el video'));
            };
            
            video.onloadedmetadata = () => {
                console.log('>>> Video metadata cargada');
                console.log('>>> Dimensiones del video:', video.videoWidth, 'x', video.videoHeight);
                
                if (video.videoWidth === 0 || video.videoHeight === 0) {
                    console.error('>>> Video tiene dimensiones inválidas');
                    clearTimeout(timeout);
                    reject(new Error('El video no tiene dimensiones válidas'));
                    return;
                }
                
                const overlay = document.createElement('div');
                overlay.id = 'region-selector-overlay';
                overlay.innerHTML = `
                    <div class="region-selector-container">
                        <canvas id="region-canvas"></canvas>
                        <div class="region-instructions" id="region-instructions">
                            <div class="drag-handle" id="drag-handle">⋮⋮ Arrastra para mover ⋮⋮</div>
                            <h3>📐 Selecciona el área a grabar</h3>
                            <p>Haz clic y arrastra sobre la imagen para seleccionar la región</p>
                            <div class="region-buttons">
                                <button id="confirm-region" class="btn-success" disabled>✓ Confirmar Selección</button>
                                <button id="cancel-region" class="btn-danger">✗ Cancelar</button>
                            </div>
                        </div>
                        <div id="region-dimensions"></div>
                    </div>
                `;
                
                const style = document.createElement('style');
                style.textContent = `
                    #region-selector-overlay {
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0, 0, 0, 0.9);
                        z-index: 99999;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .region-selector-container {
                        position: relative;
                        max-width: 95%;
                        max-height: 95%;
                    }
                    #region-canvas {
                        display: block;
                        max-width: 100%;
                        max-height: 80vh;
                        cursor: crosshair;
                        border: 3px solid #3b82f6;
                        border-radius: 8px;
                        box-shadow: 0 10px 40px rgba(0,0,0,0.5);
                    }
                    .region-instructions {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        background: white;
                        padding: 20px 30px;
                        border-radius: 12px;
                        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                        text-align: center;
                        z-index: 100000;
                        cursor: move;
                        user-select: none;
                    }
                    .drag-handle {
                        background: linear-gradient(135deg, #3b82f6, #2563eb);
                        color: white;
                        padding: 8px;
                        margin: -20px -30px 15px -30px;
                        border-radius: 12px 12px 0 0;
                        cursor: move;
                        font-weight: 600;
                        font-size: 12px;
                        letter-spacing: 2px;
                    }
                    .region-instructions h3 {
                        margin: 0 0 10px 0;
                        color: #1f2937;
                        font-size: 1.2em;
                    }
                    .region-instructions p {
                        margin: 0 0 15px 0;
                        color: #6b7280;
                        font-size: 0.95em;
                    }
                    .region-buttons {
                        display: flex;
                        gap: 10px;
                        justify-content: center;
                    }
                    .region-buttons button {
                        padding: 10px 20px;
                        border: none;
                        border-radius: 8px;
                        font-weight: 600;
                        cursor: pointer;
                        font-size: 14px;
                    }
                    #region-dimensions {
                        position: fixed;
                        bottom: 30px;
                        left: 50%;
                        transform: translateX(-50%);
                        background: rgba(59, 130, 246, 0.95);
                        color: white;
                        padding: 12px 24px;
                        border-radius: 8px;
                        font-weight: 600;
                        font-size: 16px;
                        display: none;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                    }
                `;
                document.head.appendChild(style);
                document.body.appendChild(overlay);
                
                const canvas = document.getElementById('region-canvas');
                const ctx = canvas.getContext('2d');
                
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                ctx.drawImage(video, 0, 0);
                
                const instructionsPanel = document.getElementById('region-instructions');
                const dragHandle = document.getElementById('drag-handle');
                let isDraggingPanel = false;
                let panelOffsetX = 0;
                let panelOffsetY = 0;
                
                dragHandle.addEventListener('mousedown', (e) => {
                    isDraggingPanel = true;
                    panelOffsetX = e.clientX - instructionsPanel.offsetLeft;
                    panelOffsetY = e.clientY - instructionsPanel.offsetTop;
                    instructionsPanel.style.cursor = 'grabbing';
                });
                
                document.addEventListener('mousemove', (e) => {
                    if (isDraggingPanel) {
                        instructionsPanel.style.left = (e.clientX - panelOffsetX) + 'px';
                        instructionsPanel.style.top = (e.clientY - panelOffsetY) + 'px';
                        instructionsPanel.style.right = 'auto';
                    }
                });
                
                document.addEventListener('mouseup', () => {
                    if (isDraggingPanel) {
                        isDraggingPanel = false;
                        instructionsPanel.style.cursor = 'move';
                    }
                });
                
                let isDrawing = false;
                let startX, startY;
                let selectedRegion = null;
                
                const confirmBtn = document.getElementById('confirm-region');
                const cancelBtn = document.getElementById('cancel-region');
                const dimensionsDiv = document.getElementById('region-dimensions');
                
                function drawSelection(x, y, width, height) {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(video, 0, 0);
                    
                    ctx.fillStyle = 'rgba(0, 0, 0, 0.6)';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    
                    ctx.clearRect(x, y, width, height);
                    ctx.drawImage(video, x, y, width, height, x, y, width, height);
                    
                    ctx.strokeStyle = '#3b82f6';
                    ctx.lineWidth = 4;
                    ctx.setLineDash([10, 5]);
                    ctx.strokeRect(x, y, width, height);
                    ctx.setLineDash([]);
                    
                    const cornerSize = 12;
                    ctx.fillStyle = '#3b82f6';
                    ctx.shadowColor = 'rgba(59, 130, 246, 0.5)';
                    ctx.shadowBlur = 10;
                    
                    ctx.fillRect(x - cornerSize/2, y - cornerSize/2, cornerSize, cornerSize);
                    ctx.fillRect(x + width - cornerSize/2, y - cornerSize/2, cornerSize, cornerSize);
                    ctx.fillRect(x - cornerSize/2, y + height - cornerSize/2, cornerSize, cornerSize);
                    ctx.fillRect(x + width - cornerSize/2, y + height - cornerSize/2, cornerSize, cornerSize);
                    
                    ctx.shadowBlur = 0;
                    
                    dimensionsDiv.textContent = `📏 ${Math.abs(width)}px × ${Math.abs(height)}px`;
                    dimensionsDiv.style.display = 'block';
                }
                
                canvas.addEventListener('mousedown', (e) => {
                    if (isDraggingPanel) return;
                    
                    const rect = canvas.getBoundingClientRect();
                    startX = (e.clientX - rect.left) * (canvas.width / rect.width);
                    startY = (e.clientY - rect.top) * (canvas.height / rect.height);
                    isDrawing = true;
                });
                
                canvas.addEventListener('mousemove', (e) => {
                    if (!isDrawing || isDraggingPanel) return;
                    
                    const rect = canvas.getBoundingClientRect();
                    const currentX = (e.clientX - rect.left) * (canvas.width / rect.width);
                    const currentY = (e.clientY - rect.top) * (canvas.height / rect.height);
                    
                    const width = currentX - startX;
                    const height = currentY - startY;
                    
                    drawSelection(startX, startY, width, height);
                });
                
                canvas.addEventListener('mouseup', (e) => {
                    if (!isDrawing || isDraggingPanel) return;
                    
                    const rect = canvas.getBoundingClientRect();
                    const endX = (e.clientX - rect.left) * (canvas.width / rect.width);
                    const endY = (e.clientY - rect.top) * (canvas.height / rect.height);
                    
                    const width = endX - startX;
                    const height = endY - startY;
                    
                    selectedRegion = {
                        x: Math.min(startX, endX),
                        y: Math.min(startY, endY),
                        width: Math.abs(width),
                        height: Math.abs(height)
                    };
                    
                    if (selectedRegion.width > 50 && selectedRegion.height > 50) {
                        confirmBtn.disabled = false;
                        drawSelection(selectedRegion.x, selectedRegion.y, selectedRegion.width, selectedRegion.height);
                    } else {
                        showResult('⚠️ La región seleccionada es muy pequeña. Mínimo 50x50 píxeles.', false);
                        selectedRegion = null;
                        confirmBtn.disabled = true;
                    }
                    
                    isDrawing = false;
                });
                
                confirmBtn.addEventListener('click', () => {
                    console.log('>>> Confirmar selección clickeado');
                    console.log('>>> Región seleccionada:', selectedRegion);
                    clearTimeout(timeout);
                    video.pause();
                    video.srcObject = null;
                    document.body.removeChild(overlay);
                    document.head.removeChild(style);
                    console.log('>>> Resolviendo promesa con región');
                    resolve(selectedRegion);
                });
                
                cancelBtn.addEventListener('click', () => {
                    console.log('>>> Cancelar clickeado');
                    clearTimeout(timeout);
                    video.pause();
                    video.srcObject = null;
                    document.body.removeChild(overlay);
                    document.head.removeChild(style);
                    console.log('>>> Resolviendo promesa con null');
                    resolve(null);
                });
            };
        });
    }
    
    /**
     * Recortar stream a la región seleccionada
     */
    async cropStreamToRegion(originalStream, region, options) {
        console.log('>>> cropStreamToRegion() llamado');
        console.log('>>> Región:', region);
        console.log('>>> Opciones:', options);
        
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        canvas.width = region.width;
        canvas.height = region.height;
        
        console.log('>>> Canvas creado:', canvas.width, 'x', canvas.height);
        
        const video = document.createElement('video');
        video.srcObject = originalStream;
        video.autoplay = true;
        video.muted = true;
        
        console.log('>>> Iniciando reproducción del video para crop...');
        await video.play();
        
        console.log('>>> Esperando metadata...');
        await new Promise(resolve => {
            if (video.readyState >= 2) {
                console.log('>>> Video ya está listo');
                resolve();
            } else {
                video.onloadedmetadata = () => {
                    console.log('>>> Metadata cargada para crop');
                    video.oncanplay = () => {
                        console.log('>>> Video listo para reproducir');
                        resolve();
                    };
                };
            }
        });
        
        console.log('>>> Configurando loop de dibujado...');
        
        let isRecording = true;
        let frameCount = 0;
        
        const drawFrame = () => {
            if (!isRecording) {
                console.log('>>> Loop de dibujado detenido');
                return;
            }
            
            try {
                ctx.drawImage(
                    video,
                    region.x, region.y, region.width, region.height,
                    0, 0, canvas.width, canvas.height
                );
                frameCount++;
                if (frameCount === 30) {
                    console.log('✓ Grabación de región funcionando correctamente');
                }
                // Ya no logueamos cada 30 frames para no llenar la consola
            } catch (e) {
                console.error('>>> Error dibujando frame:', e);
            }
            
            requestAnimationFrame(drawFrame);
        };
        
        console.log('>>> Iniciando loop de dibujado...');
        drawFrame();
        
        const fps = options.fps || 30;
        console.log('>>> Capturando stream del canvas a', fps, 'FPS');
        const croppedStream = canvas.captureStream(fps);
        
        console.log('>>> Stream capturado');
        console.log('>>> Video tracks en stream recortado:', croppedStream.getVideoTracks().length);
        
        const audioTracks = originalStream.getAudioTracks();
        console.log('>>> Audio tracks disponibles:', audioTracks.length);
        
        if (audioTracks.length > 0) {
            audioTracks.forEach(track => {
                console.log('>>> Agregando audio track:', track.label);
                croppedStream.addTrack(track);
            });
        }
        
        croppedStream._stopCropping = () => {
            console.log('>>> Deteniendo cropping');
            isRecording = false;
            video.pause();
            video.srcObject = null;
        };
        
        console.log('>>> cropStreamToRegion completado');
        return croppedStream;
    }
    
    /**
     * Obtener el mejor codec disponible (priorizando MP4)
     */
    getBestMimeType() {
        const types = [
            'video/mp4;codecs=h264',
            'video/mp4;codecs=avc1',
            'video/mp4',
            'video/webm;codecs=h264',
            'video/webm;codecs=vp9,opus',
            'video/webm;codecs=vp8,opus',
            'video/webm',
            'video/x-matroska;codecs=avc1'
        ];
        
        for (let type of types) {
            if (MediaRecorder.isTypeSupported(type)) {
                return type;
            }
        }
        
        return 'video/webm';
    }
    
    startTimer() {
        this.timer = setInterval(() => {
            this.recordingDuration = Math.floor((Date.now() - this.startTime) / 1000);
            updateRecordingTimer(this.recordingDuration);
        }, 1000);
    }
    
    stopTimer() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    }
    
    pauseRecording() {
        if (this.mediaRecorder && this.mediaRecorder.state === 'recording') {
            this.mediaRecorder.pause();
            this.stopTimer();
            return { success: true, message: 'Grabación pausada' };
        }
        return { success: false, error: 'No se puede pausar' };
    }
    
    resumeRecording() {
        if (this.mediaRecorder && this.mediaRecorder.state === 'paused') {
            this.mediaRecorder.resume();
            this.startTime = Date.now() - (this.recordingDuration * 1000);
            this.startTimer();
            return { success: true, message: 'Grabación reanudada' };
        }
        return { success: false, error: 'No se puede reanudar' };
    }
    
    stopRecording() {
        if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
            this.mediaRecorder.stop();
            return { success: true, message: 'Grabación detenida' };
        }
        return { success: false, error: 'No hay grabación activa' };
    }
    
    async saveRecording() {
        console.log('=== GUARDANDO GRABACIÓN ===');
        console.log('Chunks grabados:', this.recordedChunks.length);
        
        if (this.recordedChunks.length === 0) {
            console.error('❌ No hay datos para guardar');
            showResult('❌ No hay datos para guardar. La grabación puede no haber iniciado correctamente.', false);
            this.cleanup();
            return;
        }
        
        // Calcular tamaño total
        const totalSize = this.recordedChunks.reduce((acc, chunk) => acc + chunk.size, 0);
        console.log('Tamaño total de chunks:', totalSize, 'bytes');
        
        const mimeType = this.mediaRecorder.mimeType;
        console.log('MIME type:', mimeType);
        
        let extension = 'mp4';
        
        if (mimeType.includes('webm')) {
            extension = 'webm';
        } else if (mimeType.includes('matroska') || mimeType.includes('mkv')) {
            extension = 'mkv';
        }
        
        console.log('Extensión seleccionada:', extension);
        
        const blob = new Blob(this.recordedChunks, { type: mimeType });
        console.log('Blob creado, tamaño:', blob.size, 'bytes');
        
        const duration = this.formatDuration(this.recordingDuration);
        const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
        const filename = `screen_${timestamp}_${duration}.${extension}`;
        
        console.log('Nombre del archivo:', filename);
        console.log('Duración:', duration);
        
        // Descargar localmente
        console.log('Descargando archivo localmente...');
        this.downloadBlob(blob, filename);
        
        // Subir al servidor
        console.log('Subiendo al servidor...');
        await this.uploadToServer(blob, filename);
        
        // Limpiar recursos
        this.cleanup();
        console.log('=== GRABACIÓN GUARDADA ===');
    }
    
    downloadBlob(blob, filename) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = filename;
        
        document.body.appendChild(a);
        a.click();
        
        setTimeout(() => {
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }, 100);
    }
    
    async uploadToServer(blob, filename) {
        const formData = new FormData();
        formData.append('video', blob, filename);
        formData.append('action', 'upload');
        formData.append('duration', this.recordingDuration);
        formData.append('csrf_token', document.getElementById('csrfToken').value);
        
        try {
            showResult('⏳ Subiendo archivo al servidor...', true);
            
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                const duration = this.formatDuration(this.recordingDuration);
                showResult(`✅ Grabación guardada: ${result.filename} (${duration})`, true);
                
                setTimeout(() => {
                    document.getElementById('listRecordings').click();
                }, 1000);
            } else {
                showResult(`❌ Error al guardar: ${result.error}`, false);
            }
        } catch (error) {
            console.error('Error al subir:', error);
            showResult('❌ Error de conexión al servidor', false);
        }
    }
    
    formatDuration(seconds) {
        const hrs = Math.floor(seconds / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        
        if (hrs > 0) {
            return `${hrs}h${mins}m${secs}s`;
        }
        return `${mins}m${secs}s`;
    }
    
    cleanup() {
        console.log('>>> cleanup() llamado');
        
        if (this.stream) {
            console.log('>>> Deteniendo todos los tracks del stream...');
            this.stream.getTracks().forEach(track => {
                console.log('>>> Deteniendo track:', track.kind, track.label);
                track.stop();
            });
            
            // Detener el cropping si existe
            if (this.stream._stopCropping) {
                console.log('>>> Deteniendo cropping...');
                this.stream._stopCropping();
            }
            
            this.stream = null;
        }
        
        this.mediaRecorder = null;
        this.recordedChunks = [];
        this.stopTimer();
        
        console.log('>>> cleanup() completado');
    }
}

const screenRecorder = new ScreenRecorderPro();

let isRecording = false;
let isPaused = false;
let autoStopTimer = null;
let currentVideo = null;
let playbackSpeeds = [0.25, 0.5, 0.75, 1, 1.25, 1.5, 2];
let currentSpeedIndex = 3;

const startBtn = document.getElementById('startRecording');
const pauseBtn = document.getElementById('pauseRecording');
const resumeBtn = document.getElementById('resumeRecording');
const stopBtn = document.getElementById('stopRecording');
const statusDiv = document.getElementById('recordingStatus');
const resultDiv = document.getElementById('result');
const autoStopCheckbox = document.getElementById('autoStop');
const autoStopSelect = document.getElementById('autoStopTime');
const videoModal = document.getElementById('videoModal');
const videoPlayer = document.getElementById('videoPlayer');
const videoTitle = document.getElementById('videoTitle');
const videoInfo = document.getElementById('videoInfo');

function showResult(message, success = true) {
    resultDiv.innerHTML = message;
    resultDiv.className = 'result ' + (success ? 'success' : 'error');
    resultDiv.style.display = 'block';
    
    setTimeout(() => {
        resultDiv.style.display = 'none';
    }, 5000);
}

function updateRecordingTimer(seconds) {
    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    let timeStr = '';
    if (hrs > 0) {
        timeStr = `${hrs.toString().padStart(2, '0')}:`;
    }
    timeStr += `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    
    if (isRecording && !isPaused) {
        statusDiv.innerHTML = `🔴 Grabando... <span class="timer">${timeStr}</span>`;
    } else if (isPaused) {
        statusDiv.innerHTML = `⏸️ Pausado en <span class="timer">${timeStr}</span>`;
    }
}

function updateRecordingState(recording, paused = false) {
    isRecording = recording;
    isPaused = paused;
    
    startBtn.disabled = recording;
    pauseBtn.disabled = !recording || paused;
    resumeBtn.disabled = !paused;
    stopBtn.disabled = !recording;
    
    if (paused) {
        pauseBtn.style.display = 'none';
        resumeBtn.style.display = 'inline-block';
        statusDiv.className = 'status paused';
    } else {
        pauseBtn.style.display = 'inline-block';
        resumeBtn.style.display = 'none';
        
        if (recording) {
            statusDiv.className = 'status recording';
        } else {
            statusDiv.textContent = 'Estado: Listo para grabar';
            statusDiv.className = 'status stopped';
        }
    }
}

async function loadSystemStats() {
    try {
        const formData = new FormData();
        formData.append('action', 'list');
        formData.append('csrf_token', document.getElementById('csrfToken').value);
        
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        const statsSection = document.getElementById('statsSection');
        statsSection.innerHTML = `
            <div class="stat-card">
                <h4>🎬 Total Grabaciones</h4>
                <div class="value">${data.total_files}</div>
                <div class="subtext">${data.total_size_formatted}</div>
            </div>
            <div class="stat-card">
                <h4>💾 Espacio Disponible</h4>
                <div class="value">${data.space_available_formatted}</div>
                <div class="subtext">de ${data.space_limit_formatted}</div>
            </div>
            <div class="stat-card">
                <h4>📊 Uso de Almacenamiento</h4>
                <div class="value">${data.percentage_used}%</div>
                <div class="subtext">
                    <div class="progress-bar" style="margin-top: 10px;">
                        <div class="progress-fill" style="width: ${data.percentage_used}%"></div>
                    </div>
                </div>
            </div>
        `;
    } catch (error) {
        console.error('Error cargando estadísticas:', error);
    }
}

function openVideoPlayer(recording) {
    currentVideo = recording;
    videoPlayer.src = recording.url;
    videoTitle.textContent = `🔹 ${recording.filename}`;
    
    const iconMap = {
        'mp4': '🎥',
        'webm': '🎬',
        'mkv': '📹'
    };
    const icon = iconMap[recording.extension] || '🎬';
    
    videoInfo.innerHTML = `
        <div class="video-info-item">
            <strong>📁 Archivo</strong>
            <span>${icon} ${recording.filename}</span>
        </div>
        <div class="video-info-item">
            <strong>📦 Tamaño</strong>
            <span>${recording.size_formatted}</span>
        </div>
        <div class="video-info-item">
            <strong>📅 Fecha</strong>
            <span>${recording.created_formatted}</span>
        </div>
        <div class="video-info-item">
            <strong>🎞️ Formato</strong>
            <span>${recording.extension.toUpperCase()}</span>
        </div>
    `;
    
    videoModal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    videoPlayer.play().catch(e => {
        console.log('Autoplay prevented:', e);
    });
}

function closeVideoPlayer() {
    videoModal.style.display = 'none';
    document.body.style.overflow = 'auto';
    videoPlayer.pause();
    videoPlayer.src = '';
    currentVideo = null;
    currentSpeedIndex = 3;
    document.getElementById('speedBtn').textContent = '🏃 Velocidad: 1x';
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        videoPlayer.requestFullscreen().catch(err => {
            console.log('Error fullscreen:', err);
            showResult('❌ No se pudo activar pantalla completa', false);
        });
    } else {
        document.exitFullscreen();
    }
}

function changePlaybackSpeed() {
    currentSpeedIndex = (currentSpeedIndex + 1) % playbackSpeeds.length;
    const speed = playbackSpeeds[currentSpeedIndex];
    videoPlayer.playbackRate = speed;
    document.getElementById('speedBtn').textContent = `🏃 Velocidad: ${speed}x`;
}

function downloadCurrentVideo() {
    if (currentVideo) {
        const a = document.createElement('a');
        a.href = currentVideo.url;
        a.download = currentVideo.filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        showResult('📥 Descarga iniciada: ' + currentVideo.filename, true);
    }
}

async function renameRecording(oldName) {
    const newName = prompt('Nuevo nombre para el archivo:', oldName);
    if (!newName || newName === oldName) return;
    
    const formData = new FormData();
    formData.append('action', 'rename');
    formData.append('old_name', oldName);
    formData.append('new_name', newName);
    formData.append('csrf_token', document.getElementById('csrfToken').value);
    
    try {
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showResult('✅ Archivo renombrado correctamente', true);
            document.getElementById('listRecordings').click();
        } else {
            showResult('❌ Error al renombrar: ' + data.error, false);
        }
    } catch (error) {
        showResult('❌ Error: ' + error.message, false);
    }
}

async function deleteRecording(filename) {
    if (!confirm(`¿Estás seguro de eliminar "${filename}"?\n\nEsta acción no se puede deshacer.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('filename', filename);
    formData.append('csrf_token', document.getElementById('csrfToken').value);
    
    try {
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showResult('✅ Archivo eliminado: ' + filename, true);
            document.getElementById('listRecordings').click();
            loadSystemStats();
        } else {
            showResult('❌ Error al eliminar: ' + data.error, false);
        }
    } catch (error) {
        showResult('❌ Error: ' + error.message, false);
    }
}

function createVideoThumbnail(videoUrl, callback) {
    const video = document.createElement('video');
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    
    video.addEventListener('loadedmetadata', function() {
        canvas.width = 120;
        canvas.height = 68;
        video.currentTime = Math.min(2, video.duration / 2);
    });
    
    video.addEventListener('seeked', function() {
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        callback(canvas);
    });
    
    video.addEventListener('error', function() {
        ctx.fillStyle = '#667eea';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = 'white';
        ctx.font = '30px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('🎬', canvas.width/2, canvas.height/2 + 10);
        callback(canvas);
    });
    
    video.src = videoUrl;
    video.load();
}

// Event Listeners

startBtn.addEventListener('click', async function() {
    console.log('=== INICIANDO GRABACIÓN ===');
    
    const options = {
        captureMode: document.getElementById('captureMode').value,
        audio: document.getElementById('includeAudio').checked,
        microphoneAudio: document.getElementById('includeMicrophone').checked,
        width: parseInt(document.getElementById('videoQuality').value) * 16/9,
        height: parseInt(document.getElementById('videoQuality').value),
        fps: parseInt(document.getElementById('frameRate').value),
        bitrate: parseInt(document.getElementById('bitrate').value)
    };
    
    console.log('Opciones:', options);
    
    if (options.captureMode === 'region') {
        showResult('📐 Paso 1: Selecciona qué compartir. Paso 2: Recorta el área deseada', true);
    }
    
    try {
        console.log('Llamando a screenRecorder.startRecording()...');
        const result = await screenRecorder.startRecording(options);
        
        console.log('Resultado de startRecording:', result);
        
        if (result && result.success) {
            console.log('✅ Grabación iniciada con éxito');
            updateRecordingState(true);
            
            let audioMsg = '';
            if (options.audio && options.microphoneAudio) {
                audioMsg = ' con audio del sistema y micrófono';
            } else if (options.audio) {
                audioMsg = ' con audio del sistema';
            } else if (options.microphoneAudio) {
                audioMsg = ' con audio del micrófono';
            } else {
                audioMsg = ' sin audio';
            }
            
            const regionMsg = options.captureMode === 'region' ? ' (región personalizada)' : '';
            showResult(`✅ Grabación iniciada${audioMsg}${regionMsg}`, true);
            
            if (autoStopCheckbox.checked) {
                const stopTime = parseInt(autoStopSelect.value) * 1000;
                autoStopTimer = setTimeout(() => {
                    stopBtn.click();
                    showResult('⏰ Grabación detenida automáticamente', true);
                }, stopTime);
            }
        } else {
            console.error('❌ Error en grabación:', result ? result.error : 'resultado null o undefined');
            showResult('❌ Error: ' + (result ? result.error : 'No se pudo iniciar la grabación'), false);
            updateRecordingState(false);
        }
    } catch (error) {
        console.error('❌ Excepción capturada en event listener:', error);
        console.error('Stack:', error.stack);
        showResult('❌ Error inesperado: ' + error.message, false);
        updateRecordingState(false);
    }
});

pauseBtn.addEventListener('click', function() {
    const result = screenRecorder.pauseRecording();
    if (result.success) {
        updateRecordingState(true, true);
        showResult('⏸️ Grabación pausada', true);
    }
});

resumeBtn.addEventListener('click', function() {
    const result = screenRecorder.resumeRecording();
    if (result.success) {
        updateRecordingState(true, false);
        showResult('▶️ Grabación reanudada', true);
    }
});

stopBtn.addEventListener('click', function() {
    console.log('=== BOTÓN DETENER PRESIONADO ===');
    console.log('Estado actual del MediaRecorder:', screenRecorder.mediaRecorder ? screenRecorder.mediaRecorder.state : 'null');
    
    const result = screenRecorder.stopRecording();
    
    console.log('Resultado de stopRecording:', result);
    
    if (result.success) {
        updateRecordingState(false);
        showResult('⏹️ Grabación detenida. Guardando archivo...', true);
        
        if (autoStopTimer) {
            clearTimeout(autoStopTimer);
            autoStopTimer = null;
        }
    } else {
        showResult('❌ Error: ' + result.error, false);
    }
});

autoStopCheckbox.addEventListener('change', function() {
    autoStopSelect.disabled = !this.checked;
});

document.getElementById('listRecordings').addEventListener('click', async function() {
    const formData = new FormData();
    formData.append('action', 'list');
    formData.append('csrf_token', document.getElementById('csrfToken').value);
    
    try {
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        const recordingsList = document.getElementById('recordingsList');
        
        if (data.recordings && data.recordings.length > 0) {
            let html = `
                <div style="margin: 20px 0; padding: 15px; background: #e3f2fd; border-radius: 8px;">
                    <strong>📊 Resumen:</strong> ${data.total_files} archivos | 
                    ${data.total_size_formatted} de ${data.space_limit_formatted} usados 
                    (${data.percentage_used}% del límite)
                </div>
                <h4>🎬 Grabaciones Disponibles:</h4>
            `;
            
            data.recordings.forEach(function(recording, index) {
                const iconMap = {
                    'mp4': '🎥',
                    'webm': '🎬',
                    'mkv': '📹'
                };
                const icon = iconMap[recording.extension] || '🎬';
                
                html += `
                    <div class="recording-item slide-up">
                        <div class="recording-meta">
                            <canvas class="recording-thumbnail" 
                                    id="thumb_${index}"
                                    onclick='openVideoPlayer(${JSON.stringify(recording).replace(/'/g, "&#39;")})'
                                    title="Clic para reproducir"
                                    width="120" height="68"></canvas>
                            <div class="recording-details">
                                <strong>${icon} ${recording.filename}</strong>
                                <div>
                                    📦 ${recording.size_formatted} | 
                                    📅 ${recording.created_formatted}
                                </div>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <button onclick='openVideoPlayer(${JSON.stringify(recording).replace(/'/g, "&#39;")})' 
                                    class="btn-info">
                                ▶️ Reproducir
                            </button>
                            <a href="${recording.url}" download="${recording.filename}" style="text-decoration: none;">
                                <button class="btn-success">💾 Descargar</button>
                            </a>
                            <button onclick="renameRecording('${recording.filename}')" 
                                    class="btn-warning">
                                ✏️ Renombrar
                            </button>
                            <button onclick="deleteRecording('${recording.filename}')" 
                                    class="btn-danger">
                                🗑️ Eliminar
                            </button>
                        </div>
                    </div>
                `;
            });
            
            recordingsList.innerHTML = html;
            
            data.recordings.forEach(function(recording, index) {
                const canvas = document.getElementById(`thumb_${index}`);
                if (canvas) {
                    createVideoThumbnail(recording.url, function(thumbnailCanvas) {
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(thumbnailCanvas, 0, 0);
                    });
                }
            });
            
            loadSystemStats();
            
        } else {
            recordingsList.innerHTML = `
                <p style="text-align: center; color: #666; padding: 40px;">
                    🔭 No hay grabaciones disponibles.<br>
                    <small>Inicia una grabación para comenzar.</small>
                </p>
            `;
        }
    } catch (error) {
        showResult('❌ Error al cargar grabaciones: ' + error.message, false);
    }
});

document.getElementById('cleanOld').addEventListener('click', async function() {
    const days = prompt('¿Eliminar archivos anteriores a cuántos días?', '30');
    if (!days || isNaN(days)) return;
    
    if (!confirm(`¿Eliminar todas las grabaciones de hace más de ${days} días?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'clean_old');
    formData.append('days', days);
    formData.append('csrf_token', document.getElementById('csrfToken').value);
    
    try {
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showResult('🧹 ' + data.message, true);
            document.getElementById('listRecordings').click();
        } else {
            showResult('❌ Error: ' + data.error, false);
        }
    } catch (error) {
        showResult('❌ Error: ' + error.message, false);
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && videoModal.style.display === 'block') {
        closeVideoPlayer();
    }
});

videoModal.addEventListener('click', function(e) {
    if (e.target === videoModal) {
        closeVideoPlayer();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
        showResult('❌ Tu navegador no soporta captura de pantalla. Usa Chrome, Firefox, Edge o Safari moderno.', false);
        startBtn.disabled = true;
    } else {
        showResult('🎉 Sistema cargado correctamente. Formato de grabación: MP4 (H.264)', true);
    }
    
    loadSystemStats();
    document.getElementById('listRecordings').click();
    
    setInterval(loadSystemStats, 30000);
});

videoPlayer.addEventListener('error', function(e) {
    console.error('Error en reproductor:', e);
    showResult('❌ Error al cargar el video', false);
});

videoPlayer.addEventListener('ended', function() {
    showResult('✅ Reproducción completada', true);
});