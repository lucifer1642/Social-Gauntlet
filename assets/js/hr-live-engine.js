/**
 * HR Live Engine — Gemini Multimodal Live (WebSocket)
 * Full 2-way voice communication: AI speaks, user speaks via mic.
 * Auto-reconnects on failure. Never gives up.
 */

class HRLiveEngine {
    // Models to try in order (fallback chain)
    static MODELS = [
        'models/gemini-live-2.5-flash-native-audio',
        'models/gemini-2.5-flash-preview-native-audio-dialog',
        'models/gemini-2.0-flash-live-001',
        'models/gemini-2.0-flash-exp'
    ];

    constructor(apiKey, sessionId, candidateName = 'Candidate') {
        this.apiKey = apiKey;
        this.sessionId = sessionId;
        this.candidateName = candidateName;
        this.ws = null;
        this.inputAudioContext = null;
        this.outputAudioContext = null;
        this.stream = null;
        this.processor = null;
        this.setupComplete = false;
        this.micStarted = false;
        this.intentionalClose = false;

        // Reconnection
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 50;
        this.currentModelIndex = 0;

        // Audio Playback Queue
        this.activeSources = [];
        this.nextPlayTime = 0;

        // Behavioral Metrics
        this.metrics = {
            latency: [],
            pauses: 0,
            questionCount: 0,
            startTime: null,
            lastAssistantMessageTime: null
        };

        this.onMessage = null;
        this.onStatus = null;
        this.onTurnComplete = null;

        this._log('Engine created', { candidate: candidateName, sessionId });
    }

    _log(msg, data = null) {
        const ts = new Date().toLocaleTimeString();
        if (data) {
            console.log(`[HREngine ${ts}] ${msg}`, data);
        } else {
            console.log(`[HREngine ${ts}] ${msg}`);
        }
    }

    _buildSystemInstruction() {
        const now = new Date();
        const hour = now.getHours();
        let greeting;
        if (hour < 12) greeting = 'Good Morning';
        else if (hour < 17) greeting = 'Good Afternoon';
        else greeting = 'Good Evening';

        return `You are a highly professional and stern HR Recruiter conducting a formal behavioral interview.

IMPORTANT — YOU MUST START THE CONVERSATION. When the session begins, you do the following IN ORDER:
1. Greet the candidate by name using "${greeting}, ${this.candidateName}."
2. Introduce yourself briefly as the interviewer for today's session.
3. Explain the interview rules clearly:
   - This is a structured behavioral interview with 7 to 8 questions.
   - Answer each question thoroughly and honestly.
   - Responses are evaluated on clarity, depth, professionalism, and logical consistency.
   - Take a moment to think before answering if needed.
   - There are no right or wrong answers, but vague or evasive responses will be challenged.
4. Then ask the FIRST interview question immediately.

INTERVIEW CONDUCT RULES:
- Ask ONE question at a time, then wait for the candidate to answer.
- After they answer, you may ask a brief follow-up, then move to the next question.
- After 7-8 main questions, formally conclude the interview.
- Address the candidate by name (${this.candidateName}) periodically.
- Be professional, composed, and slightly cold. No warm encouragement or filler praise.
- If vague answers are given, challenge them: "Could you be more specific?" or "Give a concrete example."
- When concluding, thank the candidate formally and state that the audit is complete.

YOUR VOICE: Speak clearly and at a measured pace. You are a senior HR professional. Sound authoritative but not hostile.`;
    }

    async init() {
        this.updateStatus('Requesting Microphone Access...');
        this._log('Initializing...');
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this._log('Microphone access granted');

            this.inputAudioContext = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 16000 });
            this.outputAudioContext = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 24000 });

            // Resume audio contexts (required by browsers after user gesture)
            if (this.inputAudioContext.state === 'suspended') await this.inputAudioContext.resume();
            if (this.outputAudioContext.state === 'suspended') await this.outputAudioContext.resume();

            this._log('Audio contexts ready', {
                input: this.inputAudioContext.state,
                output: this.outputAudioContext.state
            });

            this.connect();
        } catch (err) {
            this._log('Mic access denied', err);
            this.updateStatus('ERROR: Microphone Denied — Please allow mic access and refresh');
        }
    }

    connect() {
        if (this.intentionalClose) return;

        const model = HRLiveEngine.MODELS[this.currentModelIndex];
        this._log(`Connecting with model: ${model} (attempt ${this.reconnectAttempts + 1})`);
        this.updateStatus('Connecting to Neural Link...');

        this.setupComplete = false;

        const url = `wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContent?key=${this.apiKey}`;
        
        try {
            this.ws = new WebSocket(url);
        } catch (e) {
            this._log('WebSocket constructor error', e);
            this.scheduleReconnect();
            return;
        }

        // Connection timeout — if no setupComplete within 8 seconds, retry
        this._connectTimeout = setTimeout(() => {
            if (!this.setupComplete) {
                this._log('Connection timeout — no setupComplete received');
                try { this.ws.close(); } catch(e) {}
                this.scheduleReconnect();
            }
        }, 8000);

        this.ws.onopen = () => {
            this._log('WebSocket opened, sending setup...');
            this.updateStatus('Establishing Secure Channel...');
            this.sendSetup(model);
        };

        this.ws.onmessage = async (event) => {
            try {
                let dataStr;
                if (event.data instanceof Blob) {
                    dataStr = await event.data.text();
                } else {
                    dataStr = event.data;
                }
                const response = JSON.parse(dataStr);
                this.handleResponse(response);
            } catch (e) {
                this._log('Message parse error', e);
            }
        };

        this.ws.onerror = (err) => {
            this._log('WebSocket error', err);
        };

        this.ws.onclose = (event) => {
            clearTimeout(this._connectTimeout);
            this._log(`WebSocket closed: code=${event.code}, reason=${event.reason}, clean=${event.wasClean}`);

            if (this.intentionalClose) {
                this.updateStatus('Session Ended');
                return;
            }

            // If setup never completed, the model might not be available — try next model
            if (!this.setupComplete) {
                this._log('Setup never completed — trying next model');
                this.currentModelIndex = (this.currentModelIndex + 1) % HRLiveEngine.MODELS.length;
            }

            this.updateStatus('Reconnecting...');
            this.scheduleReconnect();
        };
    }

    scheduleReconnect() {
        if (this.intentionalClose) return;
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            this._log('Max reconnection attempts reached');
            this.updateStatus('Connection failed after multiple attempts. Please refresh the page.');
            return;
        }

        this.reconnectAttempts++;
        // Exponential backoff: 1s, 2s, 4s, 8s, max 15s
        const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts - 1), 15000);
        this._log(`Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts})`);
        this.updateStatus(`Reconnecting in ${Math.round(delay/1000)}s... (attempt ${this.reconnectAttempts})`);

        setTimeout(() => this.connect(), delay);
    }

    sendSetup(model) {
        const setupMsg = {
            setup: {
                model: model,
                generationConfig: {
                    responseModalities: ["AUDIO"],
                    speechConfig: {
                        voiceConfig: {
                            prebuiltVoiceConfig: {
                                voiceName: "Kore"
                            }
                        }
                    }
                },
                systemInstruction: {
                    parts: [{ text: this._buildSystemInstruction() }]
                }
            }
        };
        this._log('Sending setup', { model });
        this.ws.send(JSON.stringify(setupMsg));
    }

    handleResponse(response) {
        // 1. Setup complete
        if (response.setupComplete) {
            clearTimeout(this._connectTimeout);
            this._log('Setup complete!');
            this.setupComplete = true;
            this.reconnectAttempts = 0; // Reset on successful connection
            this.metrics.startTime = Date.now();
            this.updateStatus('Neural Link Established');

            // Start mic streaming
            this.startMicStreaming();

            // Trigger AI to speak first
            setTimeout(() => {
                if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                    this._log('Sending initial prompt to trigger AI greeting');
                    this.ws.send(JSON.stringify({
                        clientContent: {
                            turns: [{
                                role: "user",
                                parts: [{ text: `[Session Start] My name is ${this.candidateName}. Please begin the interview now with the greeting and rules, then ask the first question.` }]
                            }],
                            turnComplete: true
                        }
                    }));
                    this.updateStatus('AI Interviewer Preparing...');
                }
            }, 500);
            return;
        }

        // 2. Server interruption (user barged in)
        if (response.serverContent && response.serverContent.interrupted) {
            this._log('User interruption detected');
            this.stopAllPlayback();
            this.updateStatus('Listening...');
            return;
        }

        // 3. Model audio/text turn
        if (response.serverContent && response.serverContent.modelTurn) {
            this.metrics.lastAssistantMessageTime = Date.now();
            const parts = response.serverContent.modelTurn.parts;
            if (parts) {
                parts.forEach(part => {
                    if (part.inlineData && part.inlineData.data) {
                        this.queueAudioChunk(part.inlineData.data);
                    }
                    if (part.text && this.onMessage) {
                        this.onMessage('assistant', part.text);
                    }
                });
            }
            this.updateStatus('AI Interviewer Speaking...');
        }

        // 4. Turn complete
        if (response.serverContent && response.serverContent.turnComplete) {
            this._log('Model turn complete');
            this.metrics.questionCount++;
            this.updateStatus('Listening — Your turn to speak...');
            if (this.onTurnComplete) this.onTurnComplete();
        }
    }

    startMicStreaming() {
        if (this.micStarted) return;
        this.micStarted = true;
        this._log('Starting mic streaming');

        const source = this.inputAudioContext.createMediaStreamSource(this.stream);
        this.processor = this.inputAudioContext.createScriptProcessor(4096, 1, 1);

        this.analyser = this.inputAudioContext.createAnalyser();
        this.analyser.fftSize = 256;
        const bufferLength = this.analyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);

        source.connect(this.analyser);
        this.analyser.connect(this.processor);
        this.processor.connect(this.inputAudioContext.destination);

        const rings = document.querySelectorAll('.visualizer-ring');
        const core = document.querySelector('.visualizer-core');

        this.processor.onaudioprocess = (e) => {
            if (!this.ws || this.ws.readyState !== WebSocket.OPEN || !this.setupComplete) return;

            // Visualization
            this.analyser.getByteFrequencyData(dataArray);
            let sum = 0;
            for (let i = 0; i < bufferLength; i++) sum += dataArray[i];
            const avg = sum / bufferLength;

            if (rings.length > 0) {
                rings[0].style.transform = `scale(${1 + (avg / 128)})`;
                if (rings[1]) rings[1].style.transform = `scale(${1 + (avg / 96)})`;
                if (rings[2]) rings[2].style.transform = `scale(${1 + (avg / 64)})`;
            }
            if (core) core.style.transform = `scale(${1 + (avg / 200)})`;

            // Stream mic audio to Gemini
            const inputData = e.inputBuffer.getChannelData(0);
            const pcmData = this.floatTo16BitPCM(inputData);
            const base64Data = this.arrayBufferToBase64(pcmData.buffer);

            try {
                this.ws.send(JSON.stringify({
                    realtimeInput: {
                        mediaChunks: [{
                            mimeType: "audio/pcm;rate=16000",
                            data: base64Data
                        }]
                    }
                }));
            } catch (e) {
                // WebSocket might have closed between check and send
                this._log('Failed to send audio chunk', e);
            }
        };
    }

    queueAudioChunk(base64Data) {
        try {
            const binaryStr = atob(base64Data);
            const bytes = new Uint8Array(binaryStr.length);
            for (let i = 0; i < binaryStr.length; i++) {
                bytes[i] = binaryStr.charCodeAt(i);
            }

            // Gemini outputs 16-bit PCM at 24kHz — convert to Float32 for Web Audio
            const int16Array = new Int16Array(bytes.buffer);
            const float32Array = new Float32Array(int16Array.length);
            for (let i = 0; i < int16Array.length; i++) {
                float32Array[i] = int16Array[i] / 32768.0;
            }

            const audioBuffer = this.outputAudioContext.createBuffer(1, float32Array.length, 24000);
            audioBuffer.getChannelData(0).set(float32Array);

            const source = this.outputAudioContext.createBufferSource();
            source.buffer = audioBuffer;
            source.connect(this.outputAudioContext.destination);

            // Schedule sequentially
            const currentTime = this.outputAudioContext.currentTime;
            const startTime = Math.max(currentTime + 0.01, this.nextPlayTime);
            this.nextPlayTime = startTime + audioBuffer.duration;

            this.activeSources.push(source);
            source.onended = () => {
                this.activeSources = this.activeSources.filter(s => s !== source);
            };

            source.start(startTime);
        } catch (e) {
            this._log('Audio playback error', e);
        }
    }

    stopAllPlayback() {
        this.activeSources.forEach(source => {
            try { source.stop(); } catch (e) {}
        });
        this.activeSources = [];
        this.nextPlayTime = 0;
    }

    stopMic() {
        if (this.processor) {
            try { this.processor.disconnect(); } catch(e) {}
            this.processor = null;
        }
        this.micStarted = false;
    }

    floatTo16BitPCM(input) {
        const output = new Int16Array(input.length);
        for (let i = 0; i < input.length; i++) {
            const s = Math.max(-1, Math.min(1, input[i]));
            output[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
        }
        return output;
    }

    arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        const chunkSize = 8192;
        for (let i = 0; i < bytes.length; i += chunkSize) {
            const chunk = bytes.subarray(i, i + chunkSize);
            binary += String.fromCharCode.apply(null, chunk);
        }
        return btoa(binary);
    }

    disconnect() {
        this._log('Intentional disconnect');
        this.intentionalClose = true;
        this.stopMic();
        this.stopAllPlayback();
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
        }
        if (this.ws) {
            try { this.ws.close(); } catch(e) {}
            this.ws = null;
        }
    }

    updateStatus(msg) {
        if (this.onStatus) this.onStatus(msg);
    }
}
