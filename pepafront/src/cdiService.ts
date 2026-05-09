import angular from "angular";

/**
 * CDI Widget API Service
 * Handles all API calls to the backend
 * Falls back to mock data if available (for testing)
 */
/**
 * CDI Widget API Service
 * Handles all API calls to the backend
 * Falls back to mock data if available (for testing)
 */
angular.module('cdiService', [])
    .constant('CDI_CONFIG', {
        DEFAULT_LANGUAGE: 'es',
        POLLING_INTERVAL: 2500,
        STATUS_LYE: {
            en: [
                "Normal", "Alarm", "Alarm", "Pre-alarm", "Technical alarm", "Fault", "Line open", "Line in short circuit", "Disabled", "Manual Download Button MDB", "Acknowledge", "Reset", "Abort", "System initialized", "Silence siren", "Low battery", "Full battery", "Power failure", "Power OK", "Robbery"
            ],
            es: [
                "Normal", "Alarma", "Alarma", "Pre alarma", "Alarma Tecnica", "Falla", "Linea abierta", "Linea en corto", "Excluida", "Pulsador de Descarga Manual PDM", "Aceptacion", "Reset", "Aborto", "Sistema inicializado", "Silenciar sirena", "Bateria baja", "Bateria completa", "Falla alimentacion", "Alimentacion OK", "Robo"
            ],
            pt: [
                "Normal", "Alarme", "Alarme", "Pré-alarme", "Alarme Técnico", "Falha", "Linha aberta", "Linha curta", "Excluído", "Botão de download manual do PDM", "Aceitação", "Reiniciar", "Aborto", "Sistema inicializado", "Silêncio sirene", "Bateria Fraca", "Bateria cheia", "Falha de alimentação", "Alimentação OK", "Roubo"
            ],
            it: [
                "Normale", "Allarme", "Allarme", "Pre-allarme", "Allarme tecnico", "Macanza", "Linea aperta", "Linea corta", "Escluso", "Pulsante di download manuale del PDM", "Accettazione", "Ripristina", "Aborto", "Sistema inizializzato", "Sirena del silenzio", "Batteria scarica", "Batteria carica", "Mancanza di alimentazione", "Alimentazione OK", "Rapina"
            ]
        },
        DICTIONARY: {
            common: {
                reset: { es: "Reiniciar", en: "Reset", pt: "Reiniciar", it: "Ripristina" },
                manual: { es: "Manual", en: "Manual", pt: "Manual", it: "Manuale" }
            },
            modals: {
                loader: {
                    header: { es: "Cargando", en: "Loading", pt: "Carregando", it: "Caricamento" }
                },
                alert: {
                    test: {
                        success: {
                            header: { es: "Enviado", en: "Sent", pt: "Enviado", it: "Inviato" },
                            content: { es: "Test enviado correctamente", en: "Test sent correctly", pt: "Teste enviado corretamente", it: "Test inviato correttamente" }
                        },
                        error: {
                            header: { es: "Error", en: "Error", pt: "Erro", it: "Errore" },
                            content: { es: 'Error enviando test', en: "Error sending test", pt: "Erro ao enviar teste", it: "Errore nell'invio del test" }
                        }
                    },
                    acknowledge: {
                        success: {
                            header: { es: "Enviado", en: "Sent", pt: "Enviado", it: "Inviato" },
                            content: { es: "Aceptación enviado correctamente", en: "Acknowledge sent successfully", pt: "Aceitação enviada com sucesso", it: "Accettazione inviata correttamente" }
                        },
                        error: {
                            header: { es: "Error", en: "Error", pt: "Erro", it: "Errore" },
                            content: { es: 'Error enviando aceptación', en: "Error sending acknowledge", pt: "Erro ao enviar aceitação", it: "Errore nell'invio dell'accettazione" }
                        }
                    },
                    reset: {
                        success: {
                            header: { es: "Enviado", en: "Sent", pt: "Enviado", it: "Inviato" },
                            content: { es: "Reset enviado correctamente", en: "Reset sent successfully", pt: "Redefinição enviada com sucesso", it: "Reimpostazione inviata con successo" }
                        },
                        error: {
                            header: { es: "Error", en: "Error", pt: "Erro", it: "Errore" },
                            content: { es: 'Error enviando comando', en: "Error sending command", pt: "Erro ao enviar comando", it: "Errore nell'invio del comando" }
                        }
                    },
                    acknowledgeInput: {
                        header: { es: "Aceptacion", en: "Acknowledge", pt: "Aceitação", it: "Accettazione" },
                        content: { es: "Entrada de aceptacion recibida", en: "Acknowledge input recived", pt: "Entrada de aceitação recebida", it: "Ingresso di accettazione ricevuto" }
                    },
                    resetInput: {
                        header: { es: "Reset", en: "Reset", pt: "Redefinição", it: "Reimpostazione" },
                        content: { es: "Reset enviado correctamente", en: "Reset input recived", pt: "Entrada de redefinição recebida", it: "Ingresso di reimpostazione ricevuto" }
                    }
                }
            },
            statusBar: {
                alarm: { es: "Alarma General", en: "General alarm", pt: "Alarme geral", it: "Allarme generale" },
                fault: { es: "Falla General", en: "General fault", pt: "Falha geral", it: "Macanza Generale" },
                disconnect: { es: "Desconexion", en: "Disconnection", pt: "Desconexão", it: "Exclude" },
                ground: { es: "Derivacion a tierra", en: "Ground connection", pt: "Desconexão", it: "Dispersione a terra" },
                test: { es: "Test", en: "Test", pt: "Teste", it: "Test" },
                extinction: { es: "Extinguiendo", en: "Extinguishing", pt: "Extinção", it: "Spegnimento" },
                battery: {
                    percent100: { es: "Bateria 100%", en: "Battery 100%", pt: "Bateria 100%", it: "Batteria 100%" },
                    percent75: { es: "Bateria 75%", en: "Battery 75%", pt: "Bateria 75%", it: "Batteria 75%" },
                    percent50: { es: "Bateria 50%", en: "Battery 50%", pt: "Bateria 50%", it: "Batteria 50%" },
                    percent25: { es: "Bateria 25%", en: "Battery 25%", pt: "Bateria 25%", it: "Batteria 25%" },
                    fault: { es: "Bateria Falla", en: "Battery fault", pt: "Bateria falha", it: "Batteria macanza" }
                },
                power: {
                    normal: { es: "220 OK", en: "220 OK", pt: "220 OK", it: "220 OK" },
                    fault: { es: "220 Falla", en: "220 fault", pt: "220 falha", it: "220 macanza" }
                },
                network: {
                    normal: { es: "WiFi", en: "WiFi", pt: "WiFi", it: "WiFi" },
                    fault: { es: "No WiFi", en: "Not WiFi", pt: "não WiFi", it: "Non WiFi" }
                }
            },
            main: {
                lineAndInputNormalization: { es: "Lineas y entradas normalizadas", en: "Line and input normalization", pt: "Normalização de linhas e entradas", it: "Normalizzazione di linee e ingressi" },
                buttons: {
                    acknowledge: { es: "Aceptacion", en: "Acknowledge", pt: "Reconhecimento", it: "Aceptazione" },
                    reset: { es: "Reset", en: "Reset", pt: "Reiniciar", it: "Ripristina" },
                    test: { es: "Test", en: "Test", pt: "Teste", it: "Test" },
                },
                bar: {
                    line: {
                        name: { es: "Linea ", en: "Line ", pt: "Linha ", it: "Linea " },
                    },
                    input: {
                        name: { es: "Entrada ", en: "IN", pt: "IN", it: "IN" },
                    },
                    test: { es: "<strong>Test</strong> Activado", en: "<strong>Test</strong> Activated", pt: "<strong>Teste</strong> Ativado", it: "<strong>Test</strong> Attivato" }
                }
            },
        }

    })
    .service('CdiWidgetService', ['$http', '$q', '$timeout', function ($http, $q, $timeout) {

        return {
            /**
             * Authenticate user with API
             */
            authenticateUser: function (apiDomain, userId, userCode) {
                return $http.get(apiDomain + '/api/config/usuarios', {
                    headers: { 'Content-Type': 'application/json' }
                })
                    .then(function (response) {
                        try {
                            const users = JSON.parse(atob(response.data.USR));
                            const user = users.find(u => u.id === userId && u.code === userCode);
                            return user ? { success: true, user: user } : { success: false };
                        } catch (e) {
                            console.error('Authentication parse error:', e);
                            return { success: false };
                        }
                    })
                    .catch(function (error) {
                        console.error('Authentication error:', error);
                        return $q.reject(error);
                    });
            },

            /**
             * Fetch bar status
             */
            getBarStatus: function (apiDomain) {
                return $http.get(apiDomain + '/api/barstatus')
                    .then(function (response) { return response.data; })
                    .catch(function (error) {
                        console.error('Bar status error:', error);
                        return $q.reject(error);
                    });
            },

            /**
             * Fetch lines and inputs
             */
            getLinesStatus: function (apiDomain) {
                return $http.get(apiDomain + '/api/linesstatus')
                    .then(function (response) { return response.data; })
                    .catch(function (error) {
                        console.error('Lines status error:', error);
                        return $q.reject(error);
                    });
            },

            /**
             * Fetch installation name and general config
             */
            getGeneralConfig: function (apiDomain) {
                return $http.get(apiDomain + '/api/config/general')
                    .then(function (response) {

                        return response.data;
                    })
                    .catch(function (error) {
                        console.error('Config error:', error);
                        return $q.reject(error);
                    });
            },

            /**
             * Send acknowledge command
             */
            sendAcknowledge: function (apiDomain, userId) {
                return $http.post(apiDomain + '/api/cmd', {
                    cmdACK: { userId: userId }
                })
                    .then(function (response) { return response.data; })
                    .catch(function (error) {
                        console.error('Acknowledge error:', error);
                        return $q.reject(error);
                    });
            },

            /**
             * Send reset command
             */
            sendReset: function (apiDomain, userId) {
                return $http.post(apiDomain + '/api/cmd', {
                    cmdReset: { userId: userId }
                })
                    .then(function (response) { return response.data; })
                    .catch(function (error) {
                        console.error('Reset error:', error);
                        return $q.reject(error);
                    });
            },

            /**
             * Send test command
             */
            sendTest: function (apiDomain, userId) {
                return $http.post(apiDomain + '/api/cmd', {
                    cmdTest: { userId: userId }
                })
                    .then(function (response) { return response.data; })
                    .catch(function (error) {
                        console.error('Test error:', error);
                        return $q.reject(error);
                    });
            }
        };
    }]);