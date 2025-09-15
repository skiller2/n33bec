'use strict';

/*
const fs = require('fs');

console.log("fs", fs);

console.log('Electron Process', process);
console.log('Electron Window', window);


window.ipcRenderer = require('electron').ipcRenderer;

*/

//window.electronDefaultApp.initialize();
//import { ipcRenderer } from 'electron';

//console.log("electron", window.ipcRenderer);

import angular from "angular";

import 'angular-ui-grid/ui-grid.css';
import 'angular-dashboard-framework/dist/angular-dashboard-framework.min.css';
import 'ui-select/dist/select.css';

import 'bootstrap/dist/css/bootstrap.min.css';
import './Content/ui-grid-app.css';
import './Content/log-viewer-app.css';
import 'angular-clock/dist/angular-clock.css'
import './Content/estilo.css';

//import '@fortawesome/fontawesome-free/js/brands';
//import '@fortawesome/fontawesome-free/js/fontawesome';
//import '@fortawesome/fontawesome-free/js/regular';
//import '@fortawesome/fontawesome-free/js/solid';

import '@fortawesome/fontawesome-free/css/brands.css';
import '@fortawesome/fontawesome-free/css/fontawesome.css';
import '@fortawesome/fontawesome-free/css/regular.css';
import '@fortawesome/fontawesome-free/css/solid.css';



import 'angular-sanitize';
import 'angular-jwt';
import 'angular-storage';
import 'angular-ui-router';

import 'webcam';

import 'angular-echarts/dist/angular-echarts.js'; // Se define asi porque está mal el package.json

import 'angular-clock';
import 'angular-dashboard-framework';
import 'angular-sanitize'; // Necesario para el ui-select
import 'angular-animate';
import 'angular-spinner';
import 'angular-ui-grid';
import 'ui-bootstrap4';
import 'ui-select';
import 'ng-file-upload';

//import 'angular-translate';
//import 'angular-translate-loader-static-files';


import Sortable from 'sortablejs';
(<any>global).Sortable = Sortable;


import * as moment from 'moment';


import './dataServices';
import './directives';

import appTipoUsoComponent from "./tipoUsoComponent";
import appLectorComponent from "./lectorComponent";
import appComunicComponent from "./comunicComponent";
import appDoutComponent from "./doutComponent";
import appDinExtComponent from "./dinExtComponent";
import appDinComponent from "./dinComponent";
import appAinComponent from "./ainComponent";
import appAoutComponent from "./aoutComponent";
import appSucesoComponent from "./sucesoComponent";

import sectorDetalleComponent from './sectorDetalleComponent';
import controlAccesoComponent from './controlAccesoComponent';
import dashboardComponent from './dashboardComponent';
import bodyDefault from "./bodyComponent";
import display2Component from "./display2Component";
import displayComponent from "./displayComponent";
import displaySectorComponent from "./displaySectorComponent";


import './Widgets/diskspace/src/diskspace.ts';
import './Widgets/io/src/io.ts';
import './Widgets/io_lista/src/io_lista.ts';
import './Widgets/display_area54/src/display_area54.ts';
import './Widgets/ioext/src/ioext.ts';
import './Widgets/movimientos/src/movimientos.ts';
import './Widgets/processor/src/processor.ts';
import './Widgets/weather/src/weather';

import 'angular-bootstrap-grid-tree';
import "angular-bootstrap-nav-tree";
import esquemasComponent from "./esquemasComponent";
import feriadosComponent from "./feriadosComponent";
import ioComponent from "./ioComponent";
import logViewerComponent from "./logComponent";
import movicredsectoresComponent from "./movicredsectoresComponent";
import movisucesosComponent from "./movisucesosComponent";
import parametrosComponent from "./parametrosComponent";
import personasComponent from "./personasComponent";
import sectoresComponent from "./sectoresComponent";
import stockcredencialesComponent from "./stockcredencialesComponent";
import temasComponent from "./temasComponent";
import testComponent from "./testComponent";
import unidadesorganizComponent from "./unidadesorganizComponent";
import usuariosComponent from "./usuariosComponent";
import visitasComponent from "./visitasComponent";
import habilitacionesComponent from "./habilitacionesComponent";
import novedadesComponent from "./Asis/novedadesComponent";
import registrosComponent from "./Asis/registrosComponent";
import tiponovedadComponent from "./Asis/tiponovedadComponent";
import empleadosComponent from "./Asis/empleadosComponent";
import empresasComponent from "./Asis/empresasComponent";
import feriadosasisComponent from "./Asis/feriadosasisComponent";
import aptosfisicosComponent from "./aptosfisicosComponent";
import captureImageComponent from "./captureImageComponent";
import confgrupocredComponent from "./confgrupocredComponent";
import displayGeneralComponent from "./displayGeneralComponent";
import movimientosComponent from "./movimientosComponent";
import movieventosComponent from "./movieventosComponent";
import docsViewerComponent from "./docsViewerComponent";
import panZoomComponent from "./panZoomComponent";
import csIconComponent from "./iconsLibComponent";
import appConsoleComponent from "./consoleComponent";
import adjuntarImgComponent from "./adjuntarImgComponent";
import adjuntarFileComponent from "./adjuntarFileComponent";
import numbersOnlyDirective from "./numbersOnlyDirective";
import rangeSelectComponent from "./rangeSelectComponent";
import diaHoraEmpleadoComponent from "./diaHoraEmpleadoComponent";
import grillaComponent from "./grillaComponent";
import broadcastService from "./broadcast.service";

require("angular-mousewheel/mousewheel.js");


const MODULE_NAME = 'N33BEC';

// Declare app level module which depends on filters, and services
angular.module(MODULE_NAME,
    [
        'ui.router',
        'angular-storage',
        //    'Sortable', //lo usa adf
        //    uiBootstrap,
        //    uiGrid,
        'ngFileUpload',
        'ui.select',
        'ngSanitize',
        'ngAnimate',
        'adf',
        'ds.clock',
        'angular-echarts',
        'ui.grid.pagination', // data grid Pagination
        'ui.grid.resizeColumns', // data grid Resize column
        'ui.grid.moveColumns', // data grid Move column
        'ui.grid.pinning', // data grid Pin column Left/Right
        'ui.grid.selection', // data grid Select Rows
        //        'ui.grid.autoResize', // data grid Enabled auto column Size
        'ui.grid.exporter',
        //        'ui.grid.infiniteScroll',
        //        'hmTouchEvents',
        'angularSpinner',
        'appDirectives',
        //        'appComponents',
        'appServices',
        'ui.bootstrap.modal',
        'ui.bootstrap.typeahead',
        'adf.widget.weather',
        'adf.widget.movimientos',
        'adf.widget.diskspace',
        'adf.widget.processor',
        'adf.widget.io_lista',
        'adf.widget.display_area54',
        'adf.widget.io',
        'adf.widget.ioext',
        'angular-jwt',
        'webcam',
        //        'panzoom',
        //        'panzoomwidget',
        'treeGrid',
        'angularBootstrapNavTree',
        //        'createPanZoom',
        'pascalprecht.translate',
    ])

    // Configure the routes
    .constant('cfg', {
        dateformat: 'dd/MM/yyyy',
        dateformatMoment: 'DD/MM/YYYY',
        datetimeHours: 'HH:mm',
        datetimeformat: 'dd/MM/yyyy HH:mm:ss',
        datetimeformatMoment: 'DD/MM/YYYY HH:mm:ss',
        datetimeformatclock: 'dd/MM/yyyy HH:mm:ss',
        dateformatmodel: 'yyyy-MM-dd',
        datetimeformatmodel: 'yyyy-MM-dd HH:mm:ss',
        datetimeformatmodelMoment: 'YYYY-MM-DD HH:mm:ss',
        dateformatmodelMoment: 'YYYY-MM-DD',
        dpdateoptions: {
            formatYear: 'yyyy',
            showWeeks: 'false',
            startingDay: 1,
        },
        dummy: 'any',
        webApiBaseUrl: 'api/v1/',
    })
    .config(['$httpProvider', '$stateProvider', '$urlRouterProvider', 'jwtInterceptorProvider', 'jwtOptionsProvider', '$animateProvider', '$locationProvider', '$translateProvider',
        ($httpProvider, $stateProvider, $urlRouterProvider, jwtInterceptorProvider, jwtOptionsProvider, $animateProvider, $locationProvider, $translateProvider) => {
            $animateProvider.classNameFilter(/^(?:(?!ng-animate-disabled).)*$/);

            $urlRouterProvider.otherwise('/dashboard');
            //            $locationProvider.html5Mode(true);

            $translateProvider.useStaticFilesLoader({
                
                prefix: './langs/locale-',
                suffix: '.json'
            });

//            $translateProvider.preferredLanguage('es');
// /            $translateProvider.useLocalStorage()

            $stateProvider
                .state('common', {
                    abstract: true,
                    component: 'bodyDefault'
                })
                .state('common.about', {
                    params: {
                        auth: {
                            dynamic: true,
                            value: '',
                        },

                    },
                    url: '/about',
                    views: {
                        about: {
                            template: require('./Pages/about.html'),
                        },
                    },
                })
                .state('common.logout', {
                    params: {
                        auth: {
                            dynamic: true,
                            value: 'logout',
                        },
                    },
                    url: '/about',
                    views: {
                        "logout": {
                            template: require('./Pages/about.html'),
                        },
                    },
                })
                .state('common.dashboard', {
                    url: '/dashboard',
                    views: {
                        'dashboard': {
                            component: 'dashboard',
                        },
                    },

                })
                .state('common.io', {
                    url: '/io',
                    views: {
                        io: {
                            component: 'io',
                        },
                    },
                })
                .state('common.movimientos', {
                    params: {
                        action: {
                            dynamic: true,
                        },
                    },
                    url: '/movimientos/:action',
                    views: {
                        movimientos: {
                            scope: false,
                            component: "movimientos"
                        },
                    },
                })
                .state('common.feriados', {
                    params: {
                        action: {
                            dynamic: true,
                        },
                    },
                    url: '/feriados/:action',
                    views: {
                        feriados: {
                            component: 'feriados',
                            scope: false,
                        },
                    },
                })
                .state('common.movisucesos', {
                    url: '/movisucesos',
                    views: {
                        movisucesos: {
                            component: 'movisucesos',
                            scope: false,
                        },
                    },
                })
                .state('common.movieventos', {
                    url: '/movieventos',
                    views: {
                        movieventos: {
                            scope: false,
                            component: "movieventos"
                        },
                    },
                })
                .state('common.movicredsectores', {
                    params: {
                        action: {
                            dynamic: true,
                        },
                    },
                    url: '/movicredsectores/:action',
                    views: {
                        movicredsectores: {
                            component: 'movicredsectores',
                            scope: false,
                        },
                    },
                })
                .state('common.personas', {
                    params: {
                        action: {
                            dynamic: true,
                        },
                    },
                    url: '/personas/:action',
                    views: {
                        personas: {
                            component: 'personas',
                            scope: false,
                        },
                    },

                })
                .state('common.aptosfisicos', {
                    params: {
                        action: {
                            dynamic: true,
                        },
                    },
                    url: '/aptosfisicos/:action',
                    views: {
                        aptosfisicos: {
                            scope: false,
                            component: "aptosfisicos"
                        },
                    },
                })
                .state('common.usuarios', {
                    params: {
                        action: {
                            dynamic: true,
                            value: 'lista',
                        },
                    },
                    url: '/usuarios/:action',
                    views: {
                        usuarios: {
                            component: 'usuarios',
                            scope: false,
                        },
                    },
                })
                .state('common.temas', {
                    params: {
                        action: {
                            dynamic: true,
                            value: 'lista',
                        },
                    },
                    url: '/temas/:action',
                    views: {
                        temas: {
                            component: 'temas',
                            scope: false,
                        },
                    },
                })
                .state('common.visitas', {
                    params: {
                        action: {
                            dynamic: true,
                            value: 'lista',
                        },
                        ind_visita: true,
                    },
                    url: '/visitas/:action',
                    views: {
                        visitas: {
                            component: 'visitas',
                            scope: false,
                        },
                    },
                })
                .state('common.parametros', {
                    params: {
                        action: {
                            dynamic: true,
                            value: 'lista',
                        },
                    },
                    url: '/parametros/:action',
                    views: {
                        parametros: {
                            component: 'parametros',
                            scope: false,
                        },
                    },
                })
                .state('common.habilitaciones', {
                    params: {
                        action: {
                            dynamic: true,
                            value: 'lista',
                        },
                    },
                    url: '/habilitaciones/:action',
                    views: {
                        habilitaciones: {
                            component: 'habilitaciones',
                            scope: false,
                        },
                    },
                })
                .state('common.stockcredenciales', {
                    params: {
                        action: {
                            dynamic: true,
                            value: 'lista',
                        },
                    },
                    url: '/stockcredenciales/:action',
                    views: {
                        stockcredenciales: {
                            component: 'stockcredenciales',
                            scope: false,
                        },
                    },
                })
                .state('common.sectores', {
                    params: {
                        action: {
                            dynamic: true,
                            value: 'lista',
                        },
                    },
                    url: '/sectores/:action',
                    views: {
                        sectores: {
                            component: 'sectores',
                            scope: false,
                        },
                    },
                })
                .state('common.unidadesorganiz', {
                    params: {
                        action: {
                            dynamic: true,
                            value: 'lista',
                        },
                    },
                    url: '/unidadesorganiz/:action',
                    views: {
                        unidadesorganiz: {
                            component: 'unidadesorganiz',
                            scope: false,
                        },
                    },
                })
                .state('common.esquemas', {
                    params: {
                        action: {
                            dynamic: true,
                            value: 'lista',
                        },
                    },
                    url: '/esquemas/:action',
                    views: {
                        esquemas: {
                            component: 'esquemas',
                            scope: false,
                        },
                    },
                })
                .state('common.confgrupocred', {
                    params: {
                        action: {
                            dynamic: true,
                            value: 'lista',
                        },
                    },
                    url: '/confgrupocred/:action',
                    views: {
                        confgrupocred: {
                            scope: false,
                            component: "confgrupocred"
                        },
                    },
                })
                .state('common.logviewer', {
                    params: {
                        action: {
                            dynamic: true,
                            value: 'lista',
                        },
                    },
                    url: '/logviewer',
                    views: {
                        logviewer: {
                            component: 'logviewer',
                            scope: false,
                        },
                    },
                })
                .state('display', {

                    component: "display",
                    url: '/display',
                })
                .state('display2', {
                    component: "display2",
                    url: '/display2',
                })
                .state('displaysec', {
                    url: '/displaysec/:cod_sector',
                    component: "displaySector"
                })
                .state('displaysucesos', {
                    component: 'displayGeneral',
                    url: '/displaygeneral',

                })
                .state('displaygeneral', {
                    component: 'displayGeneral',
                    url: '/displaygeneral',
                })
                .state('test', {
                    component: 'test',
                    url: '/test',
                })

                // ASIS
                .state('common.feriadosasis', {
                    params: {
                        action: {
                            dynamic: true,
                            value: 'lista',
                        },
                    },
                    url: '/feriadosasis/:action',
                    views: {
                        feriadosasis: {
                            component: 'feriadosasis',
                            scope: false,
                        },
                    },
                })
                .state('common.tiponovedad', {
                    params: {
                        action: {
                            dynamic: true,
                            value: 'lista',
                        },
                    },
                    url: '/tiponovedad/:action',
                    views: {
                        tiponovedad: {
                            component: 'tiponovedad',
                            scope: false,
                        },
                    },
                })
                .state('common.empleados', {
                    params: {
                        action: {
                            dynamic: true,
                            value: 'lista',
                        },
                    },
                    url: '/empleados/:action',
                    views: {
                        empleados: {
                            component: 'empleados',
                            scope: false,
                        },
                    },
                })
                .state('common.empresas', {
                    params: {
                        action: {
                            dynamic: true,
                            value: 'lista',
                        },
                    },
                    url: '/empresas/:action',
                    views: {
                        empleados: {
                            component: 'empresas',
                            scope: false,
                        },
                    },
                })
                .state('common.novedades', {
                    params: {
                        action: {
                            dynamic: true,
                            value: 'lista',
                        },
                    },
                    url: '/novedades/:action',
                    views: {
                        novedades: {
                            component: 'novedades',
                            scope: false,
                        },
                    },
                })
                .state('common.registros', {
                    params: {
                        action: {
                            dynamic: true,
                            value: 'lista',
                        },
                    },
                    url: '/registros/:action',
                    views: {
                        registros: {
                            component: 'registros',
                            scope: false,
                        },
                    },
                })
                ;

            jwtOptionsProvider.config({
                unauthenticatedRedirector: ['$state', 'auth', function ($state, auth) {
                    //                    console.log('unauthenticatedRedirector callLogin');
                    auth.callLogin(false);
                }],
                whiteListedDomains: ['*'],
            });

            jwtInterceptorProvider.tokenGetter = ['cfg', '$state', 'auth', function (cfg, $state, auth) {
                //                const token = auth.checkToken();
                //                if (!token) {
                //                    return null;
                //                }
                return auth.checkToken();
            }];

            $httpProvider.interceptors.push('jwtInterceptor');

            $httpProvider.interceptors.push('LocaleInterceptor');

            const interceptor = ['$state', '$q', 'broadcastService', function ($state, $q, broadcastService) {
                return {
                    responseError(response) {
                        if (response.status === 400 && response.data.error === 'token_not_provided') {
                            //#2
                            //auth.callLogin(false);
                            //                            if ($state.current.name!='common.signin')
                            //                                $state.go('common.signin',{referer: $state.current.name});

                        }
                        if (response.status === 401) {
                            broadcastService.send('auth', { "authenticated": false, "ruta_login": "", "ind_visita_simplificada": "" });
                        }
                        return $q.reject(response);
                    },
                };
            }];

            $httpProvider.interceptors.push(interceptor);
            $httpProvider.defaults.headers.common['Access-Control-Allow-Origin'] = '*';

        }])
    .component("bodyDefault", bodyDefault)
    .component("controlAcceso", controlAccesoComponent)
    .component("dashboard", dashboardComponent)
    .component("sectorDetalle", sectorDetalleComponent)
    .component("display2", display2Component)
    .component("display", displayComponent)
    .component("displaySector", displaySectorComponent)
    .component("esquemas", esquemasComponent)
    .component("feriados", feriadosComponent)
    .component("io", ioComponent)
    .component("logviewer", logViewerComponent)
    .component("movicredsectores", movicredsectoresComponent)
    .component("movisucesos", movisucesosComponent)
    .component("parametros", parametrosComponent)
    .component("personas", personasComponent)
    .component("sectores", sectoresComponent)
    .component("stockcredenciales", stockcredencialesComponent)
    .component("temas", temasComponent)
    .component("test", testComponent)
    .component("unidadesorganiz", unidadesorganizComponent)
    .component("usuarios", usuariosComponent)
    .component("visitas", visitasComponent)
    .component("habilitaciones", habilitacionesComponent)
    .component("aptosfisicos", aptosfisicosComponent)
    .component("confgrupocred", confgrupocredComponent)
    .component("displayGeneral", displayGeneralComponent)
    .component("movimientos", movimientosComponent)
    .component("movieventos", movieventosComponent)


    .component("novedades", novedadesComponent)
    .component("registros", registrosComponent)
    .component("tiponovedad", tiponovedadComponent)
    .component("empleados", empleadosComponent)
    .component("empresas", empresasComponent)
    .component("feriadosasis", feriadosasisComponent)


    .component("captureImage", captureImageComponent)
    .component("docsViewer", docsViewerComponent)
    .component("panZoom", panZoomComponent)
    .component("csIcon", csIconComponent)
    .component("appConsole", appConsoleComponent)
    .component("adjuntarImg", adjuntarImgComponent)
    .component("adjuntarFile", adjuntarFileComponent)
    .component("rangeSelect", rangeSelectComponent)
    .component("diaHoraEmpleado", diaHoraEmpleadoComponent)
    .component("grilla", grillaComponent)


    //    .component("numbersOnly", numbersOnlyDirective)


    //Temas tipo de uso
    .component("appTipoUso", appTipoUsoComponent)
    .component("appLector", appLectorComponent)
    .component("appComunic", appComunicComponent)
    .component("appDout", appDoutComponent)
    .component("appDinExt", appDinExtComponent)
    .component("appDin", appDinComponent)
    .component("appAin", appAinComponent)
    .component("appAout", appAoutComponent)
    .component("appSuceso", appSucesoComponent)


    .value('adfTemplatePath', './Templates/')
    .config(['dashboardProvider', function (dashboardProvider) {
        dashboardProvider
            .widgetsPath('Widgets')
            .structure('6-6', {
                rows: [{
                    columns: [{
                        styleClass: 'col-md-6',
                    }, {
                        styleClass: 'col-md-6',
                    }],
                }],
            })
            .structure('4-8', {
                rows: [{
                    columns: [{
                        styleClass: 'col-md-4',
                        widgets: [],
                    }, {
                        styleClass: 'col-md-8',
                        widgets: [],
                    }],
                }],
            })
            .structure('12/4-4-4', {
                rows: [{
                    columns: [{
                        styleClass: 'col-md-12',
                    }],
                }, {
                    columns: [{
                        styleClass: 'col-md-4',
                    }, {
                        styleClass: 'col-md-4',
                    }, {
                        styleClass: 'col-md-4',
                    }],
                }],
            })
            .structure('12/6-6', {
                rows: [{
                    columns: [{
                        styleClass: 'col-md-12',
                    }],
                }, {
                    columns: [{
                        styleClass: 'col-md-6',
                    }, {
                        styleClass: 'col-md-6',
                    }],
                }],
            })
            .structure('12/6-6/12', {
                rows: [{
                    columns: [{
                        styleClass: 'col-md-12',
                    }],
                }, {
                    columns: [{
                        styleClass: 'col-md-6',
                    }, {
                        styleClass: 'col-md-6',
                    }],
                }, {
                    columns: [{
                        styleClass: 'col-md-12',
                    }],
                }],
            })
            .structure('3-9 (12/6-6)', {
                rows: [{
                    columns: [{
                        styleClass: 'col-md-3',
                    }, {
                        styleClass: 'col-md-9',
                        rows: [{
                            columns: [{
                                styleClass: 'col-md-12',
                            }],
                        }, {
                            columns: [{
                                styleClass: 'col-md-6',
                            }, {
                                styleClass: 'col-md-6',
                            }],
                        }],
                    }],
                }],
            });
    }])

    .run(['cfg', 'authManager', '$state', '$location', 'IdleTimeout', 'LanguageService','store', function (cfg, authManager, $state, $location, IdleTimeout,LanguageService,store) {
        const lang = store.get('idioma')
        if (lang)
            LanguageService.setLanguage(lang)
        else 
            LanguageService.setLanguage('es')

        console.log('seteo lang')
        authManager.redirectWhenUnauthenticated();
        authManager.checkAuthOnRefresh();
        //        console.log('initial run', $location.path());
        if ($location.path() == "/displaygeneral") {
            IdleTimeout.start(5 * 60 * 1000, function () {
                const ruta = "displaygeneral";
                if ($state.href(ruta) && $state.current.name != "displaygeneral")
                    $state.go(ruta);
            });
        }

    }])

    .filter('ftDateTime', ['cfg', function (cfg) {
        return function (value) {
            if (value && value !== '0000-00-00 00:00:00') {
                return moment.utc(value, 'YYYY-MM-DD HH:mm:ss.SSS').local().format(cfg.datetimeformatMoment);
            } else {
                return '';
            }
        };
    }])

    .filter('ftHours', ['cfg', function (cfg) {
        return function (value) {
            if (value) {
                //return moment.utc(value, 'HH:mm').local().format(cfg.datetimeHours);
                return moment.utc(value, 'YYYY-MM-DD HH:mm:ss.SSS').format(cfg.dateformatMoment);
            } else {
                return '';
            }
        };
    }])

    .filter('ftDate', ['cfg', function (cfg) {
        return function (value) {
            if (value && value !== '0000-00-00 00:00:00') {
                return moment.utc(value, 'YYYY-MM-DD HH:mm:ss.SSS').format(cfg.dateformatMoment);
            } else {
                return '';
            }
        };
    }])
    .filter('ftArray', function () {
        return function (value) {
            return value.toString();
        };
    })
    .filter('ftDesdeHasta', function () {
        return function (value) {
            let resultado = '';
            angular.forEach(value, function (val, ind) {
                resultado += val.d + '-' + val.h + '; ';
            });
            return resultado;
        };
    })
    .filter('ftCondiciones', function () {
        return function (value) {
            let resultado = '';
            angular.forEach(value, function (val, ind) {
                resultado += val.io + '=' + val.val + '; ';
            });
            return resultado;
        };
    })
    .filter('ftPermisos', ['datosBack', function (datosBack) {
        let obj_permisos = [];
        datosBack.getData('usuarios/getAbilities', false, false).then(function (resultado) {
            obj_permisos = resultado;
        });

        return function (value) {
            let resultado = '';
            angular.forEach(value, function (val, ind) {
                angular.forEach(obj_permisos, function (permiso, ind) {
                    if (permiso.den === val) {
                        resultado += permiso.nom + '; ';
                    }
                });
            });
            return resultado;
        };
    }])
    .filter('ftOU', ['datosBack', function (datosBack) {
        let obj_ou = [];
        datosBack.getData('unidadesorganiz/combo', false, false).then(function (resultado) {
            obj_ou = resultado;
        });

        return function (value) {
            let resultado = '';
            angular.forEach(value, function (val, ind) {
                angular.forEach(obj_ou, function (ous, ind) {
                    if (ous.cod_ou === val) {
                        resultado += ous.nom_ou + '; ';
                    }
                });
            });
            return resultado;
        };
    }])
    .filter('ftAcciones', function () {
        return function (value) {
            let resultado = '';
            angular.forEach(value, function (val, ind) {
                resultado += val.io + ' (segs=' + val.segs + ', start=' + val.start + '); ';
            });
            return resultado;
        };
    })
    .filter('ftEstados', function () {
        return function (value) {
            return value.cod_tema + ' (' + value.nom_tema + '): ' + value.des_valor + ' (' + value.valor + '); ';
        };
    })
    .filter('ftBoolean', function () {
        return function (value) {
            return (value === true) ? 'Sí' : 'No';
        };
    })
    .filter('ftMovimiento', function () {
        return function (value) {
            return (value === 'I') ? 'Ingreso' : 'Egreso';
        };
    })
    .filter('ftTipoRechazo', function () {
        return function (value, tipo_habilitacion) {
            if (angular.isArray(value)) {
                value = value.toString();
            }
            if (tipo_habilitacion) {
                tipo_habilitacion = (tipo_habilitacion === 'T') ? 'Temporal' : 'Permanente';
            }
            switch (value) {
                case 'C':
                    return 'Cantidad excedida';
                case 'R':
                    return 'No habilitado';
                case 'T':
                    return 'Tiempo expirado';
                case 'V':
                    return 'No vigente';
                case 'H':
                    return 'Tipo habilitación ' + tipo_habilitacion;
                case 'S':
                    return 'Sector incorrecto';
                case 'E':
                    return 'Fuera horario';
                case 'F':
                    return 'Apto físico vencido';
                case 'G':
                    return 'No posee apto físico';
                case '':
                    return 'Habilitado';
                default:
                    return 'Desconocido';
            }
        };
    })

    .filter('ftTipoHab', function () {
        return function (value) {
            if (angular.isArray(value)) {
                value = value.toString();
            }
            switch (value) {
                case 'P':
                    return 'Permanente';
                case 'T':
                    return 'Temporal/Visita';
                case 'P,T':
                    return 'Permanente, Temporal/Visita';
                default:
                    return '';
            }
        };
    })
    .filter('ftOpLogico', function () {
        return function (value) {
            let descripcion = '';
            switch (value) {
                case 'Y':
                    descripcion = 'AND';
                    break;
                case 'O':
                    descripcion = 'OR';
                    break;
            }
            return descripcion;
        };
    })
    .filter('ftTarjeta', ["globalData", function (globalData) {
        return function (value) {
            const format = globalData.getTarjetaFormat();
            function pad(str, max) {
                str = str.toString();
                return str.length < max ? pad("0" + str, max) : str;
            }
            if (value) {
                value = value.toString();
                value = value.replace('-', '');
                value = value.replace(' ', '');
                value = pad(value, format.total);
                value = value.substr(0, format.facility) + '-' + value.substr(format.facility);
            }
            return value;
        };
    }])
    .filter('ftTarjetaInversa', ["globalData", function (globalData) {
        return function (value) {
            //            const format = globalData.getTarjetaFormat();
            if (value) {
                value = value.toString();
                value = value.replace('-', '');
            }
            return value;
        };
    }])
    .filter('sprintf', function () {

        function parse(str, args) {
            let i = 0;
            return str.replace('/%s/g', function () {
                return args[i++] || '';
            });
        }

        return function () {
            return parse(Array.prototype.slice.call(arguments, 0, 1)[0], Array.prototype.slice.call(arguments, 1));
        };
    })
    .filter('ftHora', function () {
        return function (value) {
            if (value) {
                if (value.length === 8) {
                    value = value.slice(0, 5);
                }
                value = value.replace(':', '');
                value = ('0000' + value).slice(-4);
                value = value.substr(0, 2) + ':' + value.substr(2);
            }
            return value;
        };
    })
    .filter('ftHorarios', function () {
        return function (value) {
            if (value === '00:00:00') {
                value = '';
            }
            return value;
        };
    })
    .filter('ftPad', function () {
        return function (value, len) {
            return '&nbsp;'.repeat(len) + value;
        };
    })
    .service("broadcast",broadcastService)
    
    ;
// Global function.
function isNumeric(value) {
    return !isNaN(parseFloat(value)) && isFinite(value);
}
/*
 var filtraSectores = function(sectoresSel,sectoresList){
 //vm.sectoresList
 var sectores = [];
 angular.forEach(sectoresSel,function(cod_sector,index){
 var existe=false;
 angular.forEach(sectoresList,function(val,ind){
 if(cod_sector==val['cod_sector'])
 existe=true;
 });
 if(existe){
 sectores.push(cod_sector);
 }
 });
 return sectores;
 };*/
export default MODULE_NAME;
