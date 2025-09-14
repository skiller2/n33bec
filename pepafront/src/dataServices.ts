'use strict';

import ReconnectingWebSocket from "reconnecting-websocket";
import angular from 'angular';

import Keyboard from 'simple-keyboard';
import 'simple-keyboard/build/css/index.css';
import ng from "angular";

import "./icon-library/icon-library.css";
import { MediaPlayer } from "dashjs";
import 'angular-translate';
import 'angular-translate-loader-static-files';


angular.module('appServices', [])
    // Autenticar usuario

    .factory('LocaleInterceptor',['LanguageService', function(LanguageService) {
    return {
        request: function(config) {
        // Add the locale header
        config.headers['Locale'] = LanguageService.getLanguage() || 'en-US'; // or use a custom locale value
        return config;
        }
    };
    }])

    .factory('auth', ['$http', 'store', 'cfg', 'jwtHelper', '$q', '$rootScope', '$stateRegistry', '$state', '$uibModal', 'localData', '$timeout', 'sounds', '$location', 'globalData', '$window', 'spinCounter', '$translate','LanguageService',
        function ($http, store, cfg, jwtHelper, $q, $rootScope, $stateRegistry, $state, $uibModal, localData, $timeout, sounds, $location, globalData, $window, spinCounter, $translate,LanguageService) {
            const auth = this;
            let decodedToken = (store.get('decodedToken')) ? store.get('decodedToken') : {};
            let codUsuario = (decodedToken.sub) ? decodedToken.sub : "";
            let token = store.get('token');
            let tokenEXP = store.get('tokenEXP');
            let tokenTimeDiff = store.get('tokenTimeDiff');
            let loginScreen = false;

            auth.callLogin = function (forceRedirect) {
                const loginpath = $state.current.name + '.login';
                if (loginScreen) return;
                loginScreen = true;
                if ($stateRegistry.matcher.find(loginpath))
                    $stateRegistry.deregister(loginpath);
                $stateRegistry.register({
                    name: loginpath,
                    url: '/login',
                    onEnter: function () {
                        var modalInstance = $uibModal.open({
                            windowClass: 'modal-center',
                            template: require('../Pages/signin.html'),
                            controller: ['auth', '$uibModalInstance', function (auth, $uibModalInstance) {
                                const $ctrl = this;
                                let clickRestartCount = 0;
                                $ctrl.auth = auth;
                                $ctrl.usuarios = [];
                                $ctrl.showkeyboard = ($location.host() == "localhost" || $location.host() == "127.0.0.1" || typeof (nw) !== 'undefined') || (navigator.userAgent.indexOf("Electron") >= 0) ? true : false;

                                console.log('agent', navigator.userAgent, window)
                                //                                $ctrl.showkeyboard =  (typeof (window.nw!== 'undefined')) ? true : false;
                                $ctrl.showUserList = $ctrl.showkeyboard;
                                //$ctrl.contrasena = "";
                                $ctrl.cod_usuario = "";
                                $ctrl.frmSignin = {};
                                $ctrl.$onInit = function () {
                                    $timeout(function () {
                                        var someElement = $window.document.getElementById('cod_usuario');
                                        someElement.focus();
                                        //$ctrl.frmSignin.$$controls[0].$setViewValue("u");
                                        //$ctrl.frmSignin.$$controls[0].$render();
                                        //$ctrl.frmSignin.$$controls[0].$setViewValue("");
                                        //$ctrl.frmSignin.$$controls[0].$render();
                                        //                                        console.log("elemento", angular.element("#cod_usuario"));

                                    }, 100);

                                    $timeout(function () {
                                        const lastval = $ctrl.cod_usuario;
                                        $ctrl.frmSignin.$$controls[0].$setViewValue("u");
                                        //$ctrl.frmSignin.$$controls[0].$render();
                                        $ctrl.frmSignin.$$controls[0].$setViewValue(lastval);
                                        $ctrl.frmSignin.$$controls[0].$render();
                                        //                                        console.log("elemento", angular.element("#cod_usuario"));

                                    }, 3000);

                                };


                                $timeout(function () {
                                    $ctrl.keyboard = new Keyboard({

                                        layout: {
                                            default: ["1 2 3", "4 5 6", "7 8 9", "{shift} 0 _", "{bksp}"],
                                            shift: ["! / #", "$ % ^", "& * (", "{shift} ) +", "{bksp}"]
                                        },

                                        onChange: input => onChange(input),
                                        onKeyPress: button => onKeyPress(button),
                                    });

                                    function onChange(input) {
                                        switch ($ctrl.lastFocus) {
                                            case 'contrasena':
                                                $ctrl.frmSignin.$$controls[1].$setViewValue(input);
                                                $ctrl.frmSignin.$$controls[1].$render();

                                                break;
                                            case 'cod_usuario':
                                                $ctrl.frmSignin.$$controls[0].$setViewValue(input);
                                                $ctrl.frmSignin.$$controls[0].$render();

                                                break;
                                            case 'contrasena_nueva':
                                                $ctrl.contrasena_nueva = input;
                                                break;
                                            case 'confirma_contrasena':
                                                $ctrl.confirma_contrasena = input;
                                                break;
                                            default:
                                                break;
                                        }
                                    }

                                    function onKeyPress(button) {
                                        sounds.keypress();
                                    }

                                }, 0);

                                $ctrl.restart = () => {
                                    clickRestartCount++;
                                    if (clickRestartCount < 9)
                                        return;
                                    if (typeof (nw) !== 'undefined') {
                                        nw.App.quit();
                                    }
                                };

                                $ctrl.onChange = function (event) {
                                    switch ($ctrl.lastFocus) {
                                        case 'contrasena':
                                            $ctrl.keyboard.setInput($ctrl.contrasena);
                                            break;
                                        case 'cod_usuario':
                                            $ctrl.keyboard.setInput($ctrl.cod_usuario);
                                            break;
                                        case 'contrasena_nueva':
                                            $ctrl.keyboard.setInput($ctrl.contrasena_nueva);
                                            break;
                                        case 'confirma_contrasena':
                                            $ctrl.keyboard.setInput($ctrl.confirma_contrasena);
                                            break;
                                        default:
                                            $ctrl.keyboard.setInput('');
                                            break;
                                    }
                                }

                                $ctrl.onSelect = function ($item, $model, $label) {
                                    $timeout(function () {
                                        var someElement = $window.document.getElementById('contrasena');
                                        someElement.focus();

                                    }, 100);
                                }

                                $ctrl.onFocus = function (event) {
                                    $ctrl.lastFocus = event;
                                    switch ($ctrl.lastFocus) {
                                        case 'contrasena':
                                            $ctrl.keyboard.setInput($ctrl.contrasena);
                                            break;
                                        case 'cod_usuario':
                                            $ctrl.keyboard.setInput($ctrl.cod_usuario);
                                            break;
                                        case 'contrasena_nueva':
                                            $ctrl.keyboard.setInput($ctrl.contrasena_nueva);
                                            break;
                                        case 'confirma_contrasena':
                                            $ctrl.keyboard.setInput($ctrl.confirma_contrasena);
                                            break;
                                        default:
                                            $ctrl.keyboard.setInput('');
                                            break;
                                    }
                                }

                                if ($ctrl.showUserList)
                                    localData.getListaUsuariosLogin().then(function (lista) { $ctrl.usuarios = lista }).catch(function () { });


                                $ctrl.getUsuariosSelect = function (search) {
                                    var usuarios = $ctrl.usuarios.slice();

                                    if (search && usuarios.indexOf(search) === -1) {
                                        usuarios.unshift(search);
                                    }

                                    if ($ctrl.cod_usuario && usuarios.indexOf($ctrl.cod_usuario) === -1) {
                                        usuarios.unshift($ctrl.cod_usuario);
                                    }

                                    return usuarios;
                                }


                                $ctrl.trySignin = function () {
                                    const formData = {
                                        cod_usuario: $ctrl.cod_usuario,
                                        contrasena: $ctrl.contrasena,
                                        ind_cambio_pass: $ctrl.ind_cambio_pass,
                                        contrasena_nueva: $ctrl.contrasena_nueva,
                                        confirma_contrasena: $ctrl.confirma_contrasena,
                                        lang: LanguageService.getLanguage()
                                    };
                                    $ctrl.contrasena = '';
                                    $ctrl.ind_cambio_pass = '';
                                    $ctrl.contrasena_nueva = '';
                                    $ctrl.confirma_contrasena = '';
                                    $ctrl.error = '';
                                    $ctrl.inputsdisabled = true;

                                    return auth.signin(formData)
                                        .then(function (res) {
                                            $uibModalInstance.close();
                                            globalData.unsetSectores();
                                            if (forceRedirect) {
                                                const ruta_login = (res.data.ruta_login) ? res.data.ruta_login : 'dashboard';
                                                if ($state.href('common.' + ruta_login)) {
                                                    $state.go('common.' + ruta_login);
                                                } else if ($state.href(ruta_login)) {
                                                    $state.go(ruta_login);
                                                } else {
                                                    $state.go('common.dashboard');
                                                }
                                            } else {
                                                if ($state.current.name.indexOf("login"))
                                                    $state.go('^');
                                            }
                                        })
                                        .catch(function (response) {
                                            if (response.status === 429) {
                                                $ctrl.error = $translate.instant('Aguarde 1 minuto y vuelva a enviar las credenciales');
                                            } else {
                                                $ctrl.error = (response.data.error) ? response.data.error : $translate.instant('Reintente operación');
                                            }
                                            //                                            console.log('$ctrl.frmSignin', $ctrl.frmSignin);

                                            $timeout(function () {
                                                var someElement = $window.document.getElementById('contrasena');
                                                someElement.focus();

                                            }, 100);
                                        })
                                        .finally(function () {
                                            $ctrl.inputsdisabled = false;
                                        });
                                }
                            }],
                            controllerAs: '$ctrl',

                        });

                        modalInstance.result.then(function () {
                            // Value sumitted
                        }, function () {
                            // Modal dismissed.
                            if ($state.current.name.indexOf("login"))
                                $state.go('^');
                        })
                    },
                    onExit: function () {
                        if ($stateRegistry.matcher.find(loginpath))
                            $stateRegistry.deregister(loginpath);
                        loginScreen = false;

                    },
                });
                $state.go(loginpath);

            };
            auth.getCodUsuario = () => {
                return codUsuario;
            }

            auth.setToken = function (tokenLocal, refresh) {
                const actualSegs = new Date().valueOf() / 1000;
                const decodedToken = jwtHelper.decodeToken(tokenLocal);

                tokenEXP = decodedToken.exp;
                token = tokenLocal;
                codUsuario = decodedToken.sub;
                store.set('token', token);
                store.set('tokenEXP', tokenEXP);
                store.set('decodedToken', decodedToken);

                if (!refresh) { // La diferencia solo se puede calcular en token nuevo, no despues de refresh
                    tokenTimeDiff = (actualSegs - decodedToken.iat);
                    store.set('tokenTimeDiff', tokenTimeDiff);
                }

                if (tokenTimeDiff > 360) {
                    $rootScope.$broadcast('pantalla', {
                        message: $translate.instant('Diferencia de hora con el servidor mayor a 5 minutos'),
                        level: 'error',
                        level_class: 'danger',
                        level_img: 'warning',
                        timeStamp: new Date(),
                    });
                }
            };

            auth.checkToken = function () {
                if (token === "")
                    return "";

                const actualSegs = new Date().valueOf() / 1000;
                const tokenExpired = ((tokenEXP + tokenTimeDiff) < actualSegs);
                if (tokenExpired && token) {
                    return $http({
                        url: cfg.webApiBaseUrl + 'refreshtoken',
                        skipAuthorization: true,
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + token,
                        },
                    }).then(function (response) {
                        const header = response.headers('Authorization').split(' ');
                        if (header[0].toLowerCase() === 'bearer') {
                            auth.setToken(header[1], true);
                        }
                        return token;
                    }).catch(function (response) {
                        auth.logout();
                        return null;
                    });
                } else {
                    return token;
                }
            };

            auth.signin = function (data) {
                spinCounter.setSpinCounterAdd(1);
                return $http.post(cfg.webApiBaseUrl + 'usuarios/signin', data)
                    .then(function (res) {
                        auth.setToken(res.data.token, false);
                        globalData.setVisitaSimplificada(res.data.ind_visita_simplificada);
                        globalData.setCodTemaLector(res.data.cod_tema_lector);

                        $rootScope.$broadcast('auth', { "authenticated": true, "ruta_login": res.data.ruta_login, "ind_visita_simplificada": res.data.ind_visita_simplificada, "cod_tema_lector": res.data.cod_tema_lector });
                        return res;
                    })
                    .catch(function (res) {
                        auth.logout();
                        $rootScope.$broadcast('auth', { "authenticated": false, "ruta_login": "", "ind_visita_simplificada": "", "cod_tema_lector": "" });
                        throw res;
                    }).finally(function () {
                        spinCounter.setSpinCounterDel(1);
                    });

            };

            auth.isLogged = () => {
                //                console.log('isLogged');
                $rootScope.$broadcast('auth', { "authenticated": (token !== ""), });
                return (token !== "");
            };

            auth.isLoggedIn = () => { return (token !== ""); };

            auth.logout = function () {
                tokenEXP = 0;
                tokenTimeDiff = 0;
                token = '';
                codUsuario = '';
                decodedToken = '';
                store.set('token', token);
                store.set('tokenTimeDiff', tokenTimeDiff);
                store.set('tokenEXP', tokenEXP);
                store.set('decodedToken', decodedToken);
                auth.isLogged();
            };
            return auth;
        }])


    .factory('detalletemas', ['$stateRegistry', '$state', '$uibModal', 'localData', '$timeout', 'sounds', 'datosBack',
        function ($stateRegistry, $state, $uibModal, localData, $timeout, sounds, datosBack) {
            const vm = this;
            vm.modalInstance = null;
            vm.close = function () {
                if (vm.modalInstance)
                    vm.modalInstance.close();
            }

            vm.show = function (cod_tema, selected) {
                const loginpath = $state.current.name + '.detalletemas';
                if ($stateRegistry.matcher.find(loginpath))
                    $stateRegistry.deregister(loginpath);
                $stateRegistry.register({
                    name: loginpath,
                    //                    url: '/detalletemas',
                    onEnter: function () {
                        vm.modalInstance = $uibModal.open({
                            windowClass: 'modal-center',
                            template: require('../Pages/detalletemas.html'),
                            controller: ['detalletemas', '$uibModalInstance', function (detalletemas, $uibModalInstance) {
                                const $ctrl = this;
                                $ctrl.detalletemas = detalletemas;
                                $ctrl.ind_alarma = selected.ind_alarma;
                                $ctrl.ind_alarmatec = selected.ind_alarmatec;
                                $ctrl.ind_falla = selected.ind_falla;
                                $ctrl.ind_prealarma = selected.ind_prealarma;
                                $ctrl.stm_event_alarma = '';
                                $ctrl.stm_event_prealarma = '';
                                $ctrl.stm_event_alarma_tecnica = '';
                                $ctrl.stm_event_falla = '';

                                $ctrl.estados_temas_alarma = [];
                                $ctrl.estados_temas_prealarma = [];
                                $ctrl.estados_temas_alarma_tecnica = [];
                                $ctrl.estados_temas_falla = [];
                                $ctrl.estados_temas_actuales = [];

                                $ctrl.$onInit = function () {
                                    datosBack.getData('displaysucesos/subtemasdetalle/' + cod_tema, true, true).then(function (response) {
                                        $ctrl.stm_event_alarma = response.stm_event_alarma;
                                        $ctrl.stm_event_prealarma = response.stm_event_prealarma;
                                        $ctrl.stm_event_alarma_tecnica = response.stm_event_alarma_tecnica;
                                        $ctrl.stm_event_falla = response.stm_event_falla;
                                        $ctrl.estados_temas_alarma = response.estados_temas_alarma;
                                        $ctrl.estados_temas_prealarma = response.estados_temas_prealarma;
                                        $ctrl.estados_temas_alarma_tecnica = response.estados_temas_alarma_tecnica;
                                        $ctrl.estados_temas_falla = response.estados_temas_falla;
                                        $ctrl.estados_temas_actuales = response.estados_temas_actuales;
                                    }).catch(function (data) {

                                    });;
                                }

                            }],
                            controllerAs: '$ctrl',

                        });

                        vm.modalInstance.result.then(function () {
                            // Value sumitted
                        }, function () {
                            // Modal dismissed.
                            //                            if ($state.current.name.indexOf("detalletemas"))
                            //                                $state.go('^');
                        })
                    },
                    onExit: function () {
                        if ($stateRegistry.matcher.find(loginpath))
                            $stateRegistry.deregister(loginpath);
                    },
                });
                $state.go(loginpath);

            };

            return vm;
        }])










    .factory('broadcastService', ['$rootScope', function ($rootScope) {
        /*
        document.addEventListener('visibilitychange', function () {
            console.log('document.hidden', document.hidden);
            //      document.title = 'vis:' . document.hidden; // change tab text for demo
        });
*/
        return {
            send(msg, data) {
                $rootScope.$broadcast(msg, data);
            },
        };
    }])

    // Organización
    .factory('globalData', ['store', 'localData', '$http', 'cfg', '$q', 'spinCounter', '$translate', function (store, localData, $http, cfg, $q, spinCounter, $translate) {
        return {
            getCodEquipo() {
                let id_disp_origen = false;
                if (store.get('id_disp_origen')) {
                    id_disp_origen = store.get('id_disp_origen');
                }
                return id_disp_origen;
            },
            setCodEquipo(id_disp_origen) {
                store.set('id_disp_origen', id_disp_origen);
            },
            getCodTemaLector() {
                let cod_tema_lector = false;
                if (store.get('cod_tema_lector')) {
                    cod_tema_lector = store.get('cod_tema_lector');
                }
                return cod_tema_lector;
            },
            setCodTemaLector(cod_tema_lector) {
                store.set('cod_tema_lector', cod_tema_lector);
            },
            getVisitaSimplificada() {
                let ind_visita_simplificada = false;
                if (store.get('ind_visita_simplificada')) {
                    ind_visita_simplificada = store.get('ind_visita_simplificada');
                }
                return ind_visita_simplificada;
            },
            setVisitaSimplificada(ind_visita_simplificada) {
                store.set('ind_visita_simplificada', ind_visita_simplificada);
            },

            getTarjetaFormat() {
                let tarjetaFormat = store.get('tarjetaFormat');
                if (!tarjetaFormat) {
                    tarjetaFormat = { 'facility': 3, 'total': 8 };
                }
                return tarjetaFormat;
            },


            unsetSectores() {
                store.set('sectoresList', undefined);
                store.set('sectoresListTree', undefined);
            },

            getClases() {
                const deferred = $q.defer(); `
                `
                let clasesList = [];
                if (store.get('clasesList')) {
                    clasesList = store.get('clasesList');
                    deferred.resolve(clasesList);
                } else {
                    $http.get(cfg.webApiBaseUrl + 'clases/combo/false')
                        .then(function (response) {
                            let clasesList = [];
                            if (response.data) {
                                clasesList = response.data;
                                store.set('clasesList', clasesList);

                            }
                            deferred.resolve(clasesList);
                        }).catch(function (msg, code) {
                            deferred.reject($translate.instant('Error obteniendo Clases'));
                        });
                }
                return deferred.promise;
            },



            getSectores() {
                const deferred = $q.defer();
                let sectoresList = false;
                if (store.get('sectoresList')) {
                    sectoresList = store.get('sectoresList');
                    deferred.resolve(sectoresList);
                } else {
                    $http.get(cfg.webApiBaseUrl + 'sectores/combo/false')
                        .then(function (response) {
                            let sectoresList = false;
                            if (response.data) {
                                sectoresList = response.data;
                                store.set('sectoresList', sectoresList);

                            }
                            deferred.resolve(sectoresList);
                        }).catch(function (msg, code) {
                            deferred.reject($translate.instant('Error obteniendo Sectores'));
                        });
                }
                return deferred.promise;
            },
            getSectoresTree() {
                function getTree(data: string | any[], primaryIdName: string, parentIdName: string) {
                    if (!data || data.length == 0 || !primaryIdName || !parentIdName)
                        return [];

                    var tree = [],
                        rootIds = [],
                        item = data[0],
                        primaryKey = item[primaryIdName],
                        treeObjs = {},
                        tempChildren = {},
                        parentId: string | number,
                        parent: { children: any[]; },
                        len = data.length,
                        i = 0;

                    while (i < len) {
                        item = data[i++];
                        primaryKey = item[primaryIdName];

                        if (tempChildren[primaryKey]) {
                            item.children = tempChildren[primaryKey];
                            delete tempChildren[primaryKey];
                        }

                        treeObjs[primaryKey] = item;
                        parentId = item[parentIdName];

                        if (parentId) {
                            parent = treeObjs[parentId];

                            if (!parent) {
                                var siblings = tempChildren[parentId];
                                if (siblings) {
                                    siblings.push(item);
                                }
                                else {
                                    tempChildren[parentId] = [item];
                                }
                            }
                            else if (parent.children) {
                                parent.children.push(item);
                            }
                            else {
                                parent.children = [item];
                            }
                        }
                        else {
                            rootIds.push(primaryKey);
                        }
                    }

                    for (var i = 0; i < rootIds.length; i++) {
                        tree.push(treeObjs[rootIds[i]]);
                    };

                    return tree;
                }

                function newTreeOptions(rama: any, level: number, options: any): any {
                    angular.forEach(rama, function (sub) {
                        sub.nom_tema = '_'.repeat(level * 2) + sub.nom_tema;
                        options.push(sub);
                        if (sub.children) {
                            options = newTreeOptions(sub.children, level + 1, options);
                        }
                    });
                    return options;
                }


                const deferred = $q.defer();
                let sectoresList = [];
                if (store.get('sectoresListTree')) {
                    sectoresList = store.get('sectoresListTree');
                    deferred.resolve(sectoresList);
                } else {
                    $http.get(cfg.webApiBaseUrl + 'displaysucesos/listasec')
                        .then(function (response) {
                            let sl = [];
                            if (response.data.lista) {
                                let tmp = getTree(response.data.lista, 'id', 'parent_id');
                                sl = newTreeOptions(tmp, 0, []);
                                store.set('sectoresListTree', sl);
                            }
                            deferred.resolve(sl);
                        }).catch(function (msg, code) {
                            //                            console.log('error', msg);
                            deferred.reject($translate.instant('Error obteniendo Arbol de Sectores'), msg);
                        });
                }
                return deferred.promise;
            },
            cleanSectores() {
                store.set('sectoresList', '');
                store.set('sectoresListTree', '');
            },
            getOULocal() {
                let codOu = false;
                if (store.get('cod_ou')) {
                    codOu = store.get('cod_ou');
                }
                return codOu;
            },
            getOU() {
                const deferred = $q.defer();
                $http.get(cfg.webApiBaseUrl + 'preferencias/cod_ou')
                    .then(function (response) {
                        let cod_ou = false;
                        if (response.data) {
                            cod_ou = response.data;
                            store.set('cod_ou', cod_ou);
                        }
                        deferred.resolve(cod_ou);
                    }).catch(function (msg, code) {
                        deferred.reject($translate.instant('Error obteniendo cod_ou'));
                    });
                return deferred.promise;
            },
            setOU(codOu) {
                const deferred = $q.defer();
                $http({
                    method: 'POST',
                    url: cfg.webApiBaseUrl + 'preferencias',
                    data: {
                        preferencias: {
                            cod_ou: codOu,
                        },
                    },
                    headers: {
                        'Content-Type': 'application/json',
                    },
                }).then(function (response) {
                    localData.resetEsquemasList();
                    localData.resetGrupoCredList();
                    store.set('cod_ou', codOu);
                    deferred.resolve(response);
                }).catch(function (response) {
                    deferred.reject(response);
                });
                return deferred.promise;
            },
            getUserDash() {
                const deferred = $q.defer();
                $http.get(cfg.webApiBaseUrl + 'preferencias/dash')
                    .then(function (response) {
                        let cod_ou = false;
                        if (response.data) {
                            cod_ou = response.data;
                            store.set('cod_ou', cod_ou);
                        }
                        deferred.resolve(cod_ou);
                    }).catch(function (msg, code) {
                        deferred.reject($translate.instant('Error obteniendo dash'));
                    });
                return deferred.promise;
            },
            setUserDash(dash) {
                const deferred = $q.defer();
                $http({
                    method: 'POST',
                    url: cfg.webApiBaseUrl + 'preferencias',
                    data: {
                        preferencias: {
                            dash: dash,
                        },
                    },
                    headers: {
                        'Content-Type': 'application/json',
                    },
                }).then(function (response) {
                    deferred.resolve(response);
                }).catch(function (response) {
                    deferred.reject(response);
                });
                return deferred.promise;
            },
        };
    }])

    // LOGVIEWER
    .factory('websocketLogConstants', function () {
        return {
            commands: {
                connect: 'websocket-log-viewer-connect',
                filter: 'websocket-log-viewer-filter',
                highlight: 'websocket-log-viewer-highlight',
                lineCount: 'websocket-log-viewer-line-count',
                pause: 'websocket-log-viewer-pause',
                onlyShow: 'websocket-log-viewer-only-show-sources',
            },
            events: {
                connected: 'websocket-log-viewer-connected',
                disconnected: 'websocket-log-viewer-disconnected',
                highlighted: 'websocket-log-viewer-highlight-match',
            },
        };
    })

    .factory('realTimeData', ['store', '$rootScope', '$location', 'auth', function (store, $rootScope, $location, auth) {
        const wsprotocol = (location.protocol === 'https:') ? 'wss://' : 'ws://';
        const options = {
            connectionTimeout: 20 * 1000,
            maxRetries: Infinity,
            maxReconnectionDelay: 10000,
            minReconnectionDelay: 2000,
        };

        //const wsurl1 = wsprotocol + $location.host() + ':' + $location.port() + $location.path() + '/wssub/pantalla/input/io/estados/display_area54/movcred/common/?token=' + store.get('token') + "&cod_usuario=" + auth.getCodUsuario();

        const fullURL = $location.absUrl().split('#')[0]
        const wsurl = fullURL.replace('http', 'ws').replace(/\/+$/, '') + '/wssub/pantalla/input/io/estados/display_area54/movcred/common/?token=' + store.get('token') + "&cod_usuario=" + auth.getCodUsuario();

        const rws = new ReconnectingWebSocket(wsurl, [], options);
        const messages = {
            io: 'IO: %cod_tema %nom_tema: (%valor) %des_valor, Fecha: %stm_event, Disp: %id_disp_origen, %valor_analogico %des_unidad_medida',
            alertas: 'IO: %io, ALERTA: %des_asunto'
        };

        rws.addEventListener('message', (msg) => {
            let res: any;
            try {
                res = JSON.parse(msg.data);
            } catch (e) {
                res = {
                    username: 'anonymous',
                    message: msg,
                };
            }
            if (messages[res.channel] && !res.message) {
                res.message = messages[res.channel];
            }
            $rootScope.$broadcast(res.channel, res);
        });

        rws.addEventListener('error', (event) => {
            $rootScope.$broadcast('estados', { "context": { "EstadoVal": false, "EstadoDen": "RealTimeData", "EstadoColor": "red" } });
        });

        rws.addEventListener('close', () => {
            $rootScope.$broadcast('estados', { "context": { "EstadoVal": false, "EstadoDen": "RealTimeData", "EstadoColor": "red" } });
        });

        rws.addEventListener('open', () => {
            $rootScope.$broadcast('estados', { "context": { "EstadoVal": true, "EstadoDen": "RealTimeData", "EstadoColor": "green" } });
            /*
            const sendmsg = {
                channel: 'login',
                context: { token: store.get('token'), codUsuario: auth.getCodUsuario() },
            };

            rws.send(JSON.stringify(sendmsg));
            */
        });

        return {
            status() {
                return rws.readyState;
            },

            rws: rws,


            connect() {
                rws.close();
                $rootScope.$broadcast('estados', { "context": { "EstadoVal": false, "EstadoDen": "RealTimeData", "EstadoColor": "yellow" } });
                rws.reconnect();
            },

            close() {
                rws.close();
            },
            send(message) {
                if (angular.isString(message)) {
                    rws.send(message);
                } else if (angular.isObject(message)) {
                    rws.send(JSON.stringify(message));
                }
            },
        };
    }])

    /*
     .factory('logEntryFormatterFactory', ['$sce', 'websocketLogConstants', function ($sce, websocketLogConstants) {
     return function ($scope) {
     var me = {};
     var highlighted = {};
  
     var highlightInLogEntry = function (entry) {
     for (var id in highlighted) {
     var item = highlighted[id];
  
     if (!item.text || !item.class)
     continue;
  
     var isSomethingHighlighted = false;
     while (entry.HtmlLine.indexOf(item.text) != -1) {
     var text = item.text[0];
     text += "<span class='match-breaker'></span>";
     text += item.text.substr(1, item.text.length - 1);
     entry.HtmlLine = entry.HtmlLine.replace(item.text, "<span class='highlight " + item.class + "'>" + text + "</span>");
     isSomethingHighlighted = !item.silent;
     }
  
     if (isSomethingHighlighted)
     $scope.$emit(websocketLogConstants.events.highlighted, { text: item.text, 'class': item.class, highlighIid: item.id, lineId: entry.id, source: entry.source });
     }
     };
  
     me.highlight = function (param) {
     if (param.text && param.text.length >= 2) {
     highlighted[param.id] = param;
     for (var i = 0; i < $scope.loglines.length; i++) {
     me.formatEntry($scope.loglines[i]);
     }
     }
     else if (highlighted[param.id]) {
     delete highlighted[param.id];
     }
     };
  
     me.formatEntry = function (entry) {
     entry.HtmlLine = entry.Line;
     highlightInLogEntry(entry);
     entry.HtmlLine = $sce.trustAsHtml(entry.HtmlLine);
     };
  
     return me;
     };
     }])*/
    .service('localData', ['$http', '$log', '$q', 'cfg', 'spinCounter', 'store', '$translate',
        function ($http, $log, $q, cfg, spinCounter, store, $translate) {
            const self = this;
            const sectoresList = [];
            const esquemasList = [];
            const grupoCredList = [];
            const sectoresListAct = false;
            const esquemasListAct = false;
            const grupoCredListAct = false;
            let cod_tema_lector = "";
            // obtener sectores
            this.getSectoresList = function (cod_ou, xusuario) {
                const deferred = $q.defer();
                let path = 'combo';
                if (cod_ou) {
                    path += 'xou/' + cod_ou;
                }
                if (xusuario) {
                    path += '/true';
                } else {
                    path += '/false';
                }
                // if (!self.sectoresListAct) {
                spinCounter.setSpinCounterAdd(1);
                $http.get(cfg.webApiBaseUrl + 'sectores/' + path)
                    .then(function (response) {
                        self.sectoresList = response.data;
                        self.sectoresListAct = true;
                        deferred.resolve(self.sectoresList);
                    }).catch(function (msg, code) {
                        self.sectoresListAct = false;
                        $log.debug(msg, code);
                        deferred.reject($translate.instant('Error obteniendo lista de sectores'));
                    }).finally(function () {
                        spinCounter.setSpinCounterDel(1);
                    });
                /*} else {
                 deferred.resolve(self.sectoresList);
                 }*/
                return deferred.promise;
            };
            this.resetSectoresList = function () {
                self.sectoresListAct = false;
            };
            // obtener esquemas
            this.getEsquemasList = function (cod_ou, xusuario) {
                const deferred = $q.defer();
                let path = 'combo';
                if (cod_ou) {
                    path += 'xou/' + cod_ou;
                }
                if (xusuario) {
                    path += '/true';
                } else {
                    path += '/false';
                }
                // if (!self.esquemasListAct) {
                spinCounter.setSpinCounterAdd(1);
                $http.get(cfg.webApiBaseUrl + 'esquemas/' + path)
                    .then(function (response) {
                        self.esquemasList = response.data;
                        self.esquemasListAct = true;
                        deferred.resolve(self.esquemasList);
                    }).catch(function (msg, code) {
                        self.esquemasListAct = false;
                        $log.debug(msg, code);
                        deferred.reject($translate.instant('Error obteniendo lista de esquemas'));
                    }).finally(function () {
                        spinCounter.setSpinCounterDel(1);
                    });
                /*} else {
                 deferred.resolve(self.esquemasList);
                 }*/

                return deferred.promise;
            };
            this.resetEsquemasList = function () {
                self.esquemasListAct = false;
            };

            this.getListaUsuariosLogin = function () {
                const deferred = $q.defer();
                // if (!self.grupoCredListAct) {
                spinCounter.setSpinCounterAdd(1);
                $http.get(cfg.webApiBaseUrl + 'parametros/getParametro/SELECT_USUARIOS')
                    .then(function (response) {
                        deferred.resolve(angular.fromJson(response.data.val_parametro));
                    }).catch(function (msg, code) {
                        $log.debug(msg, code);
                        deferred.reject($translate.instant('Error obteniendo lista de usuarios'));
                    }).finally(function () {
                        spinCounter.setSpinCounterDel(1);
                    });
                /*} else {
                 deferred.resolve(self.grupoCredList);
                 }*/

                return deferred.promise;
            };

            // obtener grupo credenciales
            this.getGrupoCredList = function (cod_ou) {
                const deferred = $q.defer();
                const path = 'combo';
                // if (!self.grupoCredListAct) {
                spinCounter.setSpinCounterAdd(1);
                $http.get(cfg.webApiBaseUrl + 'confgrupocred/' + path)
                    .then(function (response) {
                        self.grupoCredList = response.data;
                        self.grupoCredListAct = true;
                        deferred.resolve(self.grupoCredList);
                    }).catch(function (msg, code) {
                        self.grupoCredListAct = false;
                        $log.debug(msg, code);
                        deferred.reject($translate.instant('Error obteniendo lista de grupo de tarjetas'));
                    }).finally(function () {
                        spinCounter.setSpinCounterDel(1);
                    });
                /*} else {
                 deferred.resolve(self.grupoCredList);
                 }*/

                return deferred.promise;
            };


            this.resetGrupoCredList = function () {
                self.grupoCredListAct = false;
            };

            this.getlogList = function () {

                const deferred = $q.defer();

                spinCounter.setSpinCounterAdd(1);

                $http.get(cfg.webApiBaseUrl + 'logs/combo')
                    .then(function (response) {
                        deferred.resolve(response.data);
                    }).catch(function (msg, code) {
                        $log.debug(msg, code);
                        deferred.reject($translate.instant('Error obteniendo lista de logs'));
                    }).finally(function () {
                        spinCounter.setSpinCounterDel(1);
                    });

                return deferred.promise;
            };

            this.getListaEmpresas = function () {
                const deferred = $q.defer();
                // if (!self.listaOUAct) {
                spinCounter.setSpinCounterAdd(1);
                let path = 'empresas/combo';

                $http.get(cfg.webApiBaseUrl + path)
                    .then(function (response) {
                        self.listaEmpresas = response.data;
                        deferred.resolve(self.listaEmpresas);
                    }).catch(function (msg, code) {
                        $log.debug(msg, code);
                        deferred.reject($translate.instant('Error obteniendo lista de Organizaciones'));
                    }).finally(function () {
                        spinCounter.setSpinCounterDel(1);
                    });
                /*} else {
                 deferred.resolve(self.listaOU);
                 }*/
                return deferred.promise;
            };

            this.getListaOU = function (xusuario = false) {
                const deferred = $q.defer();
                // if (!self.listaOUAct) {
                spinCounter.setSpinCounterAdd(1);
                let path = 'unidadesorganiz/combo';
                if (xusuario) {
                    path += 'xusuario';
                }
                $http.get(cfg.webApiBaseUrl + path)
                    .then(function (response) {
                        self.listaOU = response.data;
                        self.listaOUAct = true;
                        deferred.resolve(self.listaOU);
                    }).catch(function (msg, code) {
                        self.listaOUAct = false;
                        $log.debug(msg, code);
                        deferred.reject($translate.instant('Error obteniendo lista de OU'));
                    }).finally(function () {
                        spinCounter.setSpinCounterDel(1);
                    });
                /*} else {
                 deferred.resolve(self.listaOU);
                 }*/
                return deferred.promise;
            };
            this.resetListaOU = function () {
                self.listaOUAct = false;
            };

            // Local data for product search types.
            this.getMenuParam = function () {
                const deferred = $q.defer();
                // if (!self.grupoCredListAct) {
                spinCounter.setSpinCounterAdd(1);
                $http.get(cfg.webApiBaseUrl + 'parametros/getParametro/MENU')
                    .then(function (response) {
                        deferred.resolve(angular.fromJson(response.data.val_parametro));
                    }).catch(function (msg, code) {
                        $log.debug(msg, code);
                        deferred.reject($translate.instant('Error obteniendo menu'));
                    }).finally(function () {
                        spinCounter.setSpinCounterDel(1);
                    });
                /*} else {
                 deferred.resolve(self.grupoCredList);
                 }*/

                return deferred.promise;
            };


            this.getTarjetaFormat = function () {
                const deferred = $q.defer();
                spinCounter.setSpinCounterAdd(1);
                $http.get(cfg.webApiBaseUrl + 'parametros/getParametro/BITS_WIEGAND')
                    .then(function (response) {
                        const bitsWiegand = response.data.val_parametro;
                        let tarjetaFormat = null;
                        switch (bitsWiegand) {
                            case '35':
                                tarjetaFormat = { 'facility': 4, 'total': 11 };
                                break;
                            default:
                                break;
                        }
                        store.set('tarjetaFormat', tarjetaFormat);
                        deferred.resolve(angular.fromJson(response.data.val_parametro));
                    }).catch(function (msg, code) {
                        $log.debug(msg, code);
                        deferred.reject($translate.instant('Error obteniendo parámetros configuración'));
                    }).finally(function () {
                        spinCounter.setSpinCounterDel(1);
                    });

            }

            this.getMenu = function () {
                return [
                ];
            };

            this.getPageSizeList = function () {
                return [{
                    value: 5,
                    text: '5',
                },
                {
                    value: 10,
                    text: '10',
                },
                {
                    value: 25,
                    text: '25',
                },
                {
                    value: 50,
                    text: '50',
                },
                {
                    value: 100,
                    text: '100',
                },
                {
                    value: -1,
                    text: 'ALL',
                },
                ];
            };

            this.getTipoDocumento = function () {
                return [{
                    id: 'DNI',
                    name: 'DNI',
                    selected: true,
                },
                {
                    id: 'PAS',
                    name: $translate.instant('Pasaporte'),
                },
                ];
            };

            this.getTipoNovedad = function () {
                return [{
                    id: 'T',
                    name: $translate.instant('Tipo Trabajo'),
                },
                {
                    id: 'N',
                    name: $translate.instant('Novedad'),
                },
                ];
            };

            this.getTipoCredencial = function () {
                return [{
                    id: 'RFID',
                    name: 'RFID',
                },
                {
                    id: 'OPQR',
                    name: 'OPQR',
                },
                ];
            };

            this.getOperadorLogico = function () {
                return [{
                    id: 'Y',
                    name: 'AND',
                },
                {
                    id: 'O',
                    name: 'OR',
                },
                ];
            };

            this.getIOsList = function () {
                return {
                    IO01: 'IO01',
                    IO02: 'IO02',
                    IO03: 'IO03',
                    IO04: 'IO04',
                    IO05: 'IO05',
                    IO06: 'IO06',
                    IO07: 'IO07',
                    IO08: 'IO08',
                    IO09: 'IO09',
                    IO10: 'IO10',
                    IO11: 'IO11',
                    IO12: 'IO12',
                    IO13: 'IO13',
                    IO14: 'IO14',
                    IO15: 'IO15',
                    IO16: 'IO16',
                    IO17: 'IO17',
                    IO18: 'IO18',
                    IO19: 'IO19',
                    IO20: 'IO20',
                    IO21: 'IO21',
                    IO22: 'IO22',
                    IO23: 'IO23',
                    IO24: 'IO24',
                    IO25: 'IO25',
                    IO26: 'IO26',
                    IO27: 'IO27',
                    IO28: 'IO28',
                    IO29: 'IO29',
                    IO30: 'IO30',
                };
            };

            this.getIOsArrayList = function () {
                return ['IO01', 'IO02', 'IO03', 'IO04', 'IO05', 'IO06', 'IO07', 'IO08', 'IO09', 'IO10',
                    'IO11', 'IO12', 'IO13', 'IO14', 'IO15', 'IO16', 'IO17', 'IO18', 'IO19', 'IO20',
                    'IO21', 'IO22', 'IO23', 'IO24', 'IO25', 'IO26', 'IO27', 'IO28', 'IO29', 'IO30',
                ];
            };

            this.getTipoHabilitacion = function () {
                return [{
                    id: 'P',
                    name: $translate.instant('Permanente'),
                },
                {
                    id: 'T',
                    name: $translate.instant('Temporal/Visita'),
                },
                ];
            };

            this.getIndMovimiento = function () {
                return [{
                    id: 'I',
                    name: $translate.instant('Ingreso'),
                },
                {
                    id: 'E',
                    name: $translate.instant('Egreso'),
                },
                {
                    id: 'L',
                    name: $translate.instant('Lectura'),
                },
                ];
            };

            this.getIndRetencion = function () {
                return [{
                    id: '0',
                    name: $translate.instant('No'),
                },
                {
                    id: '1',
                    name: $translate.instant('Sí'),
                },
                ];
            };

            this.getTipoSexo = function () {
                return [{
                    id: 'M',
                    name: $translate.instant('Masculino'),
                },
                {
                    id: 'F',
                    name: $translate.instant('Femenino'),
                },
                {
                    id: 'NI',
                    name: $translate.instant('No Informado'),
                },
                ];
            };

            this.getSectores = function () {
                return [{
                    id: '1',
                    name: $translate.instant('Ingreso Principal'),
                }];
            };

            this.getColores = function () {
                return [
                    {
                        id: 'warning',
                        name: 'Amarillo',
                    },
                    {
                        id: 'primary',
                        name: 'Azul',
                    },
                    {
                        id: 'info',
                        name: 'Celeste',
                    },
                    {
                        id: 'secondary',
                        name: 'Gris',
                    },
                    {
                        id: 'danger',
                        name: 'Rojo',
                    },
                    {
                        id: 'success',
                        name: 'Verde',
                    },
                ];
            };

            this.getBotones = function () {
                return [
                    {
                        id: 'btn-success',
                        name: 'btn-success',
                    },
                    {
                        id: 'btn-danger',
                        name: 'btn-danger',
                    }
                ];
            };

            this.getTipoUso = function () {
                return [
                    {
                        id: 'LECTOR',
                        name: $translate.instant('Lector'),
                    },
                    {
                        id: 'DIN',
                        name: $translate.instant('Digital IN'),
                    },
                    {
                        id: 'DINEXT',
                        name: $translate.instant('Digital IN Mejorado'),
                    },
                    {
                        id: 'DOUT',
                        name: $translate.instant('Digital OUT'),
                    },
                    {
                        id: 'AIN',
                        name: $translate.instant('Analog IN'),
                    },
                    {
                        id: 'AOUT',
                        name: $translate.instant('Analog OUT'),
                    },
                    {
                        id: 'SUCESO',
                        name: $translate.instant('Suceso'),
                    },
                    {
                        id: 'COMUNIC',
                        name: $translate.instant('Comunicación'),
                    },
                ];
            };

        }])
    .service('ModalService', ['$uibModal', function ($uibModal) {
        const modalOptionsX = {
            backdrop: true,
            modal: true,
            keyboard: true,
            modalFade: true,
            windowTemplate: require('../Pages/window_modal.html'),
            templateUrl: null,
            controller: null,
            resolve: {

            },
        };

        const defaultModalOptions = {
            animation: true,
            ariaLabelledBy: 'modal-title-bottom',
            ariaDescribedBy: 'modal-body-bottom',
            size: 'sm',
            controller: ['$scope', '$uibModalInstance', 'modal_parametros', function ($scope, $uibModalInstance, modal_parametros) {
                $scope.name = 'bottom';

                angular.extend(this, modal_parametros);

                this.ok = function (result) {
                    $uibModalInstance.close(result);
                };
                this.close = function (result) {
                    $uibModalInstance.dismiss('cancel');
                    // $uibModalInstance.close(result);
                };

            }],
            controllerAs: 'modalAlert',
        }

        this.show = function (customModalDefaults) {
            const tempModalOptions = defaultModalOptions;
            angular.extend(tempModalOptions, customModalDefaults);
            return $uibModal.open(tempModalOptions).result;
        };

        this.alertMessage = function (message, title, icon, response = null) {
            let msg_debug = {};
            if (response && response.status === '500' && response.data.debug) {
                message = response.data.error;
                msg_debug = response.data.debug;
            }
            if (response && response.status === '413') {
                message = response.data.error;
            }
            if (response && response.status === '403') {
                message = response.data.error;
            }

            if (response && (response.status === '401' || response.status === '400')) {
                return;
            }

            const modalOptions = {
                template: require('../Pages/Templates/alert_modal_template.html'),
                resolve: {
                    modal_parametros() {
                        return {
                            msg_title: title,
                            msg_message: message,
                            msg_debug,
                            msg_icon: icon, // info, warning, danger, question
                        };
                    },
                },

            };
            //return this.show(modalOptions);
            const tempModalOptions = defaultModalOptions;
            angular.extend(tempModalOptions, modalOptions);
            return $uibModal.open(tempModalOptions).result;


        };

        this.confirmaCancela = function (message, title) {

            const modalOptions = {
                template: require('../Pages/Templates/confirma_cancela_template.html'),
                resolve: {
                    modal_parametros() {
                        return {
                            msg_title: title,
                            msg_message: message,
                            //              msg_debug,
                            //              msg_icon: 'dialog-icon-' + icon, // info, warning, error, question
                        };
                    },
                },

            };

            return this.show(modalOptions);
        };
    }])

    .service('spinCounter', ['usSpinnerService', function (usSpinnerService) {
        let spin_counter = 0;
        return {
            setSpinCounterDel(value) {
                spin_counter = spin_counter - value;
                if (spin_counter <= 0) {
                    usSpinnerService.stop('spinner-1');
                    spin_counter = 0;
                }
            },
            setSpinCounterAdd(value) {
                usSpinnerService.spin('spinner-1');
                spin_counter = spin_counter + value;
            },
            getSpinCounter() {
                return spin_counter;
            },
            isSpinning() {
                return (spin_counter > 0);
            }

        };
    }])

    .service('datosBack', ['$http', '$log', '$q', 'ModalService', 'cfg', 'spinCounter', 'globalData', 'store', '$rootScope', '$translate',
        function ($http, $log, $q, ModalService, cfg, spinCounter, globalData, store, $rootScope, $translate) {
            const self = this;
            self.request_load = null;
            self.previousBackendStatus = 0;

            self.upload = function (path, data) {
                var fd = new FormData();
                //Take the first selected file
                if (!data) return $q.reject($translate.instant("Seleccione Archivo"));
                fd.append("file", data);

                return $http.post(cfg.webApiBaseUrl + path, fd, {
                    withCredentials: true,
                    headers: { 'Content-Type': undefined },
                    transformRequest: angular.identity
                }).then().catch();


            }


            self.save = function (action, path, data, selected) {
                let clave = [];
                let method = 'POST';
                if (angular.isDefined(selected.selected)) {
                    clave = selected.selected;
                }
                const saveprom = $q.defer();
                let msg_error = 'Error grabando datos';
                if (clave.length > 1) {
                    ModalService.alertMessage($translate.instant('Debe seleccionar un solo registro'), $translate.instant('Alerta'), 'warning');
                    saveprom.reject($translate.instant('Debe seleccionar un solo registro'));
                } else {
                    const url = cfg.webApiBaseUrl + path;

                    if (action === 'edita') {
                        method = 'PUT';
                    }
                    spinCounter.setSpinCounterAdd(1);
                    //                    console.log("a grabar", data);
                    //                    console.log("plano", data.imagenes);


                    $http({
                        method,
                        url,
                        data,
                        headers: {
                            'Content-Type': 'application/json',
                        },
                    }).then(function (response) {
                        saveprom.resolve(response);
                    }).catch(function (response) {
                        if (response.data.error) {
                            msg_error = response.data.error;
                        }
                        ModalService.alertMessage(msg_error, $translate.instant('Error'), 'danger', response)
                            .then(function () { })
                            .catch(function () { })
                            .finally(function () {
                                $log.debug(response);
                                if (response.data.campos) {
                                    saveprom.reject(response.data);
                                } else {
                                    saveprom.reject(msg_error);
                                }
                            });
                    }).finally(function () {
                        spinCounter.setSpinCounterDel(1);
                    });
                }
                return saveprom.promise;
            };

            self.save_ext = function (action, url, data) {
                let method = 'POST';
                const deferred = $q.defer();
                let msg_error = $translate.instant('Error grabando datos');
                if (action === 'edita') {
                    method = 'PUT';
                }
                spinCounter.setSpinCounterAdd(1);
                $http({
                    method,
                    url,
                    data,
                    headers: {
                        'Content-Type': 'application/json',
                    },
                }).then(function (response) {
                    deferred.resolve();
                }).catch(function (response) {
                    if (response.data.error) {
                        msg_error = response.data.error;
                    }
                    ModalService.alertMessage(msg_error, $translate.instant('Error'), 'danger', response);
                    $log.debug(response);
                    deferred.reject(msg_error);
                }).finally(function () {
                    spinCounter.setSpinCounterDel(1);
                });
                return deferred.promise;
            };

            self.load = function (loadOptions) {
                const deferred = $q.defer();
                const filterJson = loadOptions.filtro;
                const msg_error = $translate.instant('Error obteniendo lista');
                // var mybody = angular.element(document).find('body');
                if (filterJson.error !== undefined && filterJson.error !== '') {
                    ModalService.alertMessage(filterJson.error, $translate.instant('Error'), 'danger');
                } else {

                    spinCounter.setSpinCounterAdd(1);

                    if (self.request_load) {
                        self.request_load.resolve();
                    }

                    self.request_load = $q.defer();

                    $http.get(cfg.webApiBaseUrl + loadOptions.path + '/false', {
                        timeout: self.request_load.promise,
                        params: {
                            pageSize: loadOptions.pageSize,
                            page: loadOptions.pageNumber,
                            filtro: filterJson,
                            cod_ou: globalData.getOULocal(),
                            sort: loadOptions.sort,
                        },
                    })
                        .then(function (response) {
                            deferred.resolve(response);
                            if (self.previousBackendStatus != response.status) {
                                self.previousBackendStatus = response.status;
                                $rootScope.$broadcast('estados', { "context": { "EstadoVal": true, "EstadoDen": "Backend", "EstadoColor": "green" } });
                            }
                        }).catch(function (msg, code) {
                            if (msg.status !== -1) {
                                if (msg.status != 401)
                                    ModalService.alertMessage(msg_error, $translate.instant('Error'), 'danger', msg);
                                deferred.reject(msg_error);
                            }

                            if (msg.status > 499 || msg.status < 0) {
                                self.previousBackendStatus = msg.status;
                                $rootScope.$broadcast('estados', { "context": { "EstadoVal": false, "EstadoDen": "Backend", "EstadoColor": "red" } });
                            }
                        }).finally(function () {
                            spinCounter.setSpinCounterDel(1);
                        });

                }
                return deferred.promise;
            };

            self.gridOptions = function (loadOptions) {
                spinCounter.setSpinCounterAdd(1);
                const deferred = $q.defer();
                const msg_error = $translate.instant('Error obteniendo gridOptions');

                $http.get(cfg.webApiBaseUrl + loadOptions.path, {})
                    .then(function (response) {
                        deferred.resolve(response);
                    }).catch(function (response, code) {
                        if (response.status != 401)
                            ModalService.alertMessage(msg_error, $translate.instant('Error'), 'danger', response);
                        deferred.reject(msg_error);
                    }).finally(function () {
                        spinCounter.setSpinCounterDel(1);
                    });
                return deferred.promise;
            };

            self.delete = function (path, selected) {

                let clave = [];
                if (angular.isDefined(selected.selected)) {
                    clave = selected.selected;
                }
                const deferred = $q.defer();
                let msg_error = $translate.instant('Error eliminando registro');
                if (clave.length > 1) {
                    ModalService.alertMessage($translate.instant('Debe seleccionar un solo registro'), $translate.instant('Alerta'), 'warning');
                    deferred.reject($translate.instant('Debe seleccionar un solo registro'));
                } else {
                    if (clave.length > 0) {
                        ModalService.confirmaCancela($translate.instant('Está seguro que desea eliminar registro?'), $translate.instant('Eliminar registro'))
                            .then(function () {
                                spinCounter.setSpinCounterAdd(1);
                                return $http({
                                    method: 'DELETE',
                                    url: cfg.webApiBaseUrl + path + '/' + btoa(JSON.stringify(clave)),
                                });
                            }).then(function (response) {
                                deferred.resolve(response);
                            }).catch(function (error) {
                                if (error !== 'cancel') {
                                    if (error.data.error) {
                                        msg_error = error.data.error;
                                    }
                                    ModalService.alertMessage(msg_error, $translate.instant('Error'), 'danger', error);
                                    deferred.reject(msg_error);
                                }
                            }).finally(function () {
                                spinCounter.setSpinCounterDel(1);
                            });
                    } else {
                        ModalService.alertMessage($translate.instant('Debe seleccionar un registro'), $translate.instant('Alerta'), 'warning');
                        deferred.reject($translate.instant('Debe seleccionar un registro'));
                    }
                }
                return deferred.promise;
            };

            self.getFullUrl = function (path) {
                return cfg.webApiBaseUrl + path;
            }

            self.detalle = function (path, clave, include_ou = true, ignore404 = false) {
                const deferred = $q.defer();
                const cod_ou = globalData.getOULocal();
                let msg_error = $translate.instant('Error obteniendo detalle');

                if (angular.isDefined(clave) && clave.length > 0) {
                    spinCounter.setSpinCounterAdd(1);

                    let routepath = cfg.webApiBaseUrl + path + '/' + btoa(JSON.stringify(clave));
                    if (include_ou) {
                        routepath += '/' + cod_ou;
                    }
                    $http.get(routepath)
                        .then(function (response) {
                            deferred.resolve(response);
                        }).catch(function (response) {
                            if (response.data.error) {
                                msg_error = response.data.error;
                            }
                            if (!ignore404 && response.status != "404")
                                ModalService.alertMessage(msg_error, $translate.instant('Error'), 'danger', response);
                            deferred.reject(msg_error);
                        }).finally(function () {
                            spinCounter.setSpinCounterDel(1);
                        });
                } else {
                    deferred.reject($translate.instant('Debe seleccionar un registro'));
                    ModalService.alertMessage($translate.instant('Debe seleccionar un registro'), $translate.instant('Alerta'), 'warning');
                }

                return deferred.promise;
            };

            self.screen = function (path) {
                const deferred = $q.defer();
                let msg_error = 'Error';
                $http.get(cfg.webApiBaseUrl + path)
                    .then(function (response) {
                        deferred.resolve(response);
                    }).catch(function (response) {
                        if (response.data.error) {
                            msg_error = response.data.error;
                        }
                        // showMessage(msg_error, parameters.color);
                        deferred.reject(msg_error);
                    }).finally(function () { });

                return deferred.promise;
            };

            self.logs = function (log_id, posicion) {
                const deferred = $q.defer();
                let msg_error = 'Error';
                $http.get(cfg.webApiBaseUrl + 'logs/lista/' + log_id + '/' + posicion)
                    .then(function (response) {
                        deferred.resolve(response);
                    }).catch(function (response) {
                        if (response.data.error) {
                            msg_error = response.data.error;
                        }
                        // showMessage(msg_error, parameters.color);
                        deferred.reject(msg_error);
                    }).finally(function () { });

                return deferred.promise;
            };

            self.getEstadosLeds = function () {
                let msg_error = 'Error';
                return $http.get(cfg.webApiBaseUrl + 'temas/getEstadosLeds')
                    .then(function (response) {
                        $rootScope.$broadcast('estados', { "context": { "EstadoVal": true, "EstadoDen": "Backend", "EstadoColor": "green" } });

                    }).catch(function (response) {
                        if (response.data && response.data.error) {
                            msg_error = response.data.error;
                        }
                        // showMessage(msg_error, parameters.color);
                    }).finally(function () { });
            };

            self.export = function (loadOptions, tipo) {
                const deferred = $q.defer();
                const filterJson = loadOptions.filtro;
                if (filterJson.error !== undefined && filterJson.error !== '') {
                    ModalService.alertMessage(filterJson.error, $translate.instant('Error'), 'danger');
                } else {
                    const cod_ou = globalData.getOULocal();
                    window.location.href = cfg.webApiBaseUrl + loadOptions.path + '/' + tipo + '?filtro=' + JSON.stringify(filterJson) + '&sort=' + JSON.stringify(loadOptions.sort) + '&token=' + store.get('token') + '&cod_ou=' + cod_ou;
                    deferred.resolve();
                }
                return deferred.promise;
            };

            self.getData = function (path: string, showSpin: boolean, showErrorDialog: boolean = true, cache: boolean = false) {
                const deferred = $q.defer();
                let msg_error = $translate.instant('Error obteniendo datos');
                if (showSpin) {
                    spinCounter.setSpinCounterAdd(1);
                }
                $http.get(cfg.webApiBaseUrl + path, { cache: cache })
                    .then(function (response) {
                        deferred.resolve(response.data);
                        if (self.previousBackendStatus != response.status) {
                            self.previousBackendStatus = response.status;
                            $rootScope.$broadcast('estados', { "context": { "EstadoVal": true, "EstadoDen": "Backend", "EstadoColor": "green" } });
                        }
                    }).catch(function (response) {
                        if (response.data && response.data.error) {
                            msg_error = response.data.error;
                        }

                        if (showErrorDialog && response.status != 401) {
                            ModalService.alertMessage(msg_error, $translate.instant('Error'), 'danger', response);
                        }
                        $log.debug(response);
                        deferred.reject(msg_error);

                        if (response.status > 499 || response.status < 0) {

                            self.previousBackendStatus = response.status;
                            $rootScope.$broadcast('estados', { "context": { "EstadoVal": false, "EstadoDen": "Backend", "EstadoColor": "red" } });
                        }

                    }).finally(function () {
                        if (showSpin) {
                            spinCounter.setSpinCounterDel(1);
                        }
                    });

                return deferred.promise;
            };

            self.postData = function (path, data) {
                let method = 'POST';
                const saveprom = $q.defer();
                let msg_error = $translate.instant('Error grabando datos');
                const url = cfg.webApiBaseUrl + path;

                spinCounter.setSpinCounterAdd(1);
                $http({
                    method,
                    url,
                    data,
                    headers: {
                        'Content-Type': 'application/json',
                    },
                }).then(function (response) {
                    saveprom.resolve(response);
                }).catch(function (response) {
                    if (response.data.error) {
                        msg_error = response.data.error;
                    }
                    if (response.status != 401)
                        ModalService.alertMessage(msg_error, $translate.instant('Error'), 'danger', response)
                            .then(function () { })
                            .catch(function () { })
                            .finally(function () {
                                $log.debug(response);
                                if (response.data.campos) {
                                    saveprom.reject(response.data);
                                } else {
                                    saveprom.reject(msg_error);
                                }
                            });
                    else
                        saveprom.reject(msg_error);
                }).finally(function () {
                    spinCounter.setSpinCounterDel(1);
                });
                return saveprom.promise;
            }
        },
    ])

    .service('sounds', ['datosBack', function (datosBack) {
        const self = this;
        self.alarmSnd = new Audio();
        self.alarmSnd.src = require("../Content/alarma.ogg");
        self.alarmSnd.loop = true;


        self.keypresssnd = new Audio();
        self.keypresssnd.src = require("../Content/keypress.wav");
        self.keypresssnd.loop = false;

        self.stop = function () {
            self.alarmSnd.pause();
            self.alarmSnd.currentTime = 0;
        };

        self.start = function () {
            self.alarmSnd.play();
        };

        self.keypress = function () {
            self.keypresssnd.currentTime = 0;
            self.keypresssnd.play();
        }
    }])


    .service('LanguageService',['$translate','store', function ($translate,store) {
        let currentLanguage = 'es'; // idioma por defecto
        this.setLanguage = function (lang) {
            currentLanguage = lang;
            $translate.use(lang);
            store.set('idioma',lang)
        };

        this.getLanguage = function () {
            return currentLanguage;
        };
    }])



    .service('videoSvc', ['datosBack', function (datosBack) {
        const self = this;
        self.player = null

        self.start = function (id: string, video_url: string) {
            const video = document.getElementById(id) as HTMLMediaElement

            try { self.player.destroy() } catch (e) { }
            self.player = MediaPlayer().create();
            self.player.initialize(video, video_url, true);
        };

        self.stop = function () {
            try { self.player.destroy() } catch (e) { }
        }

    }])


    .service('captureMedia', ['datosBack', '$rootScope', '$q', '$translate', function (datosBack, $rootScope, $q, $translate) {
        const self = this;
        let inicializando = false;
        let mediastream = [];
        let videoDevices = [];

        const getCameraSelection = async () => {
            mediastream = [];
            let num = 0;

            //            var defRes = $q.defer();


            $rootScope.$broadcast('pantalla', {
                message: $translate.instant("Buscando dispositivos de captura de imagen"),
                level: 'info',
                level_class: 'info',
                level_img: 'info',
                timeStamp: new Date(),
            });

            await navigator.mediaDevices.getUserMedia({ audio: false, video: true }).then(function (stream) {
                stream.getTracks().forEach(function (track) {
                    track.stop();
                });
            });

            let devnum = 0;
            return navigator.mediaDevices.enumerateDevices().then(function (devices) {
                return Promise.all(
                    devices.filter(device => device.kind === 'videoinput')
                        .map(videoDevice => {
                            videoDevices[devnum] = videoDevice;
                            devnum++;
                            //                console.log("videoDevice",videoDevice);
                            //                            return navigator.mediaDevices.getUserMedia({ video: { deviceId: { exact: videoDevice.deviceId }, width: { exact: 640 }, height: { exact: 480 } }, audio: false })

                            /*
                                                        return navigator.mediaDevices.getUserMedia({ video: { deviceId: { exact: videoDevice.deviceId }, }, audio: false })
                              
                            .then(function (stream) {
                                                                mediastream[num] = stream;
                                                                num++;
                            
                                                                $rootScope.$broadcast('pantalla', {
                                                                    message: videoDevice.label + ' habilitado',
                                                                    level: 'info',
                                                                    level_class: 'info',
                                                                    level_img: 'info',
                                                                    timeStamp: new Date(),
                                                                });
                                                                console.log('listo', videoDevice.label);
                            
                            
                                                            })
                                                            .catch(function (error) {
                                                                //                        if (error instanceof OverconstrainedError)
                                                                //                        else
                                                                console.log('Error', videoDevice.label, error);
                            
                                                                $rootScope.$broadcast('pantalla', {
                                                                    message: 'Error en dispositivo ' + videoDevice.label + ' ' + error,
                                                                    level: 'error',
                                                                    level_class: 'danger',
                                                                    level_img: 'warning',
                                                                    timeStamp: new Date(),
                                                                });
                                                            })
                            */

                        })).then(function () {
                        }).catch(function (error) {
                        }).finally(function () {
                            if (videoDevices.length == 0) {
                                $rootScope.$broadcast('pantalla', {
                                    message: $translate.instant('No se encontró dispositivo de captura'),
                                    level: 'info',
                                    level_class: 'info',
                                    level_img: 'info',
                                    timeStamp: new Date(),
                                });
                            } else {
                                $rootScope.$broadcast('pantalla', {
                                    message: $translate.instant('Se encontraron {{COUNT}} dispositivo/s de captura', { COUNT: videoDevices.length }),
                                    level: 'info',
                                    level_class: 'info',
                                    level_img: 'info',
                                    timeStamp: new Date(),
                                });

                            }


                        });

            }).catch(function (error) {

            });

        };

        self.getStream = (num: number) => {
            const cant = mediastream.length - 1;
            if (cant == -1) return false;

            if (num > cant)
                num = cant;
            //            console.log('stream', cant);

            return mediastream[num];
        }

        self.stopStream = async (deviceNum: number) => {
            mediastream.forEach(stream => {
                if (stream) {
                    stream.getTracks().forEach(function (track) {
                        track.stop();
                    });
                    stream = false;
                }
            });
        }

        self.getNewStream = async (deviceNum: number) => {

            mediastream.forEach(stream => {
                if (stream)

                    stream.getTracks().forEach(function (track) {
                        track.stop();

                    });

                //                console.log("stream", stream);
            });

            /*            
                        if (mediastream[deviceNum]) {
                            mediastream[deviceNum].stream.getTracks().forEach(function (track) {
                                track.stop();
                            
                            });
                            mediastream[deviceNum] = false;
                        }
            */

            if (videoDevices.length == 0 || deviceNum > videoDevices.length)
                return false;
            if (deviceNum >= videoDevices.length - 1)
                deviceNum = videoDevices.length - 1;


            const deviceId = videoDevices[deviceNum].deviceId;

            return navigator.mediaDevices.getUserMedia({ video: { deviceId: { exact: deviceId }, /*width: { exact: 640 }, height: { exact: 480 }*/ }, audio: false }).
                then(function (stream) {
                    mediastream[deviceNum] = stream;
                    return stream;
                    //                    Promise.resolve(stream);
                });
        }


        self.init = () => {
            if (!inicializando) {
                self.close();
                inicializando = true;
                getCameraSelection()
                    .then(function () {
                        inicializando = false; console.log('ok'); self.close();
                    })

                    .catch(function (error) {
                        inicializando = false;
                        $rootScope.$broadcast('pantalla', {
                            message: $translate.instant('Error listando dispositivos de captura') + ' ' + error,
                            level: 'error',
                            level_class: 'danger',
                            level_img: 'warning',
                            timeStamp: new Date(),
                        });
                    });
            }
        }

        self.close = () => {
            if (mediastream.length == 0)
                return;
            //            console.log("captureMedia close ");
            mediastream.forEach(stream => {

                stream.getTracks().forEach(function (track) {
                    track.stop();

                });

                //                console.log("stream", stream);
            });
            mediastream = [];
        }

    }])


    .service('IdleTimeout', ['$timeout', '$document', function ($timeout, $document) {
        const self = this;
        var timeout = null;
        var delay_ms = 10000;
        var cb = null;

        const _start = () => {
            timeout = $timeout(function () {
                _goneIdle();
            }, delay_ms);
        }

        const _goneIdle = () => {
            $timeout.cancel(timeout);
            if (cb) cb();
        }

        const _cancel = () => {
            $timeout.cancel(timeout);
        }

        var events = ['keydown', 'keyup', 'click', 'mousemove', 'DOMMouseScroll', 'mousewheel', 'mousedown', 'touchstart', 'touchmove', 'scroll', 'focus'];
        var $body = angular.element($document);
        var reset = function () {
            _cancel();
            _start();
        };

        self.start = (_delay_ms: number, _cb: Function) => {
            delay_ms = _delay_ms;
            cb = _cb;
            _start();
            ng.forEach(events, function (event) {
                $body.on(event, reset);
            });
        }

        self.stop = () => {
            _cancel();
            ng.forEach(events, function (event) {
                $body.off(event, reset);
            });
        }
    }])


    .service('iconsLibSvc', ['$interval', 'datosBack', '$translate', function ($interval, datosBack, $translate) {

        import(  /* webpackPrefetch: -100 */ '!!raw-loader!./icon-library/icon-library.svg')
            .then(module => {
                angular.element(document.head).append(module.default);
            })
            .catch(err => {
            });


        const vm = this;
        vm.getIconList = () => {
            return [{ nom_icono: "Cartel", class: "cs-icon-cartel" },
            { nom_icono: $translate.instant("Detector Humo"), class: "cs-icon-detector-humo" },
            { nom_icono: $translate.instant("Pulsador"), class: "fab fa-uber" },
            { nom_icono: $translate.instant("Espejo"), class: "cs-icon-espejo" },
            { nom_icono: $translate.instant("Flash"), class: "cs-icon-flash" },
            { nom_icono: $translate.instant("Lineal"), class: "cs-icon-lineal" },
            { nom_icono: $translate.instant("Sirena"), class: "fas fa-volume-up" },
            { nom_icono: $translate.instant("Central"), class: "cs-icon-central" },
            { nom_icono: $translate.instant("Campana"), class: "cs-icon-campana" },
            { nom_icono: $translate.instant("Bomba"), class: "cs-icon-bomba" },
            { nom_icono: $translate.instant("Alarma Gral"), class: "cs-icon-alarma-gral" },
            { nom_icono: $translate.instant("Moto Bomba"), class: "cs-icon-motobomba" },
            { nom_icono: $translate.instant("Electro Bomba"), class: "cs-icon-electrobomba" },
            { nom_icono: $translate.instant("Tanque Agua"), class: "cs-icon-tanqueagua" },
            { nom_icono: $translate.instant("Control Acceso"), class: "cs-icon-controlacceso" },
            { nom_icono: $translate.instant("Amplicación Extinción"), class: "cs-icon-ampliacionextincion" },

            { nom_icono: $translate.instant("Detector"), class: "fab fa-ubuntu" },
            { nom_icono: $translate.instant("Puerta"), class: "fas fa-door-open" },
            { nom_icono: $translate.instant("Lector huella"), class: "fas fa-fingerprint" },
            { nom_icono: $translate.instant("Extinguidor"), class: "fas fa-fire-extinguisher" },
            { nom_icono: $translate.instant("Cámara"), class: "fas fa-video" },
            { nom_icono: $translate.instant("Extinguidor"), class: "fas fa-fire-extinguisher" },
            ];
        };
    }])



    .service('datosBackIO', ['$interval', 'datosBack', function ($interval, datosBack) {
        const self = this;
        let Timer;
        let tiempo_recarga_seg = 0;
        let estado_ios = { 'showAlert': false };

        self.stop = function () {
            $interval.cancel(Timer);
            Timer = null;
        };

        self.start = function () {
            self.stop();
            Timer = $interval(function () {
                datosBack.getData('io/val', false, false).then(function (response) {
                    estado_ios = response;
                    estado_ios.showAlert = false;
                }).catch(function (data) {
                    estado_ios.showAlert = true;
                });
            }, tiempo_recarga_seg * 1000);
        };

        self.setTiempoRecargaSeg = function (tiempo_recarga) {
            tiempo_recarga_seg = tiempo_recarga;
            if (Timer == null) {
                self.start();
            }
        };

        self.getEstadoIOs = function () {
            return estado_ios;
        };

    }])
    ;
