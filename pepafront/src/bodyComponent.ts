'use strict';
import angular from 'angular';
declare const VERSION: string;

const bodyDefault = {


    //    selector: 'bodyComponent', //body-component
    template: require('../Pages/framework.html'),
    bindings: {},
    controllerAs: "body",
    controller: ['store', '$q', 'datosBack', '$scope', '$sce', '$filter', '$state',
        'cfg', 'auth', 'ModalService', 'globalData', 'localData', '$transitions', 'sounds', 'realTimeData', 'captureMedia','broadcast','$interval','$translate',
        function (store, $q, datosBack, $scope, $sce, $filter,
            $state, cfg, auth, ModalService, globalData, localData, $transitions, sounds, realTimeData, captureMedia, broadcast, $interval,$translate) {

            const vm = this;

            vm.version = VERSION;
            vm.codOuList = [];
            vm.cod_ou_obj = [];

            localData.getTarjetaFormat();

            localData.getMenuParam(true).then(function (resultado) {
                vm.menu = resultado;

            }).catch(function () {
                vm.menu = [];
            });


            //            vm.menu = localData.getMenu();
            vm.id_disp_origen = '';
            vm.codUsuario = 'Desconocido';
            vm.isLogged = false;
            vm.colorEstadoConsola = 'grey';
            vm.colorEstadoHabiAcceso = 'grey';
            vm.colorEstadoBackend = 'grey';
            vm.colorEstadoTema = 'grey';
            vm.colorEstadoPrueba = 'grey';
            vm.btn_bell_class = 'btn-outline-light';
            vm.icon_bell_class = '';

            $transitions.onSuccess({}, function () {
                vm.state = $state.current.name;
            });

            vm.uiOnParamsChanged = function (params) {
                //                console.log('vm.state', vm.state, $state.current.name);
                switch (params.auth) {
                    case 'logout':
                        auth.logout();
                        break;
                }
            };

            vm.$onDestroy = function () {
            };

            vm.$onInit = function () {
                console.log('body init');
                auth.isLogged();
                vm.state = $state.current.name;
                vm.uiOnParamsChanged($state.params);
                localData.getListaOU(true).then(function (resultado) {
                    vm.codOuList = resultado;
                    globalData.getOU().then(function (response) {
                        vm.cod_ou = response;
                    }).catch(function () {
                        vm.cod_ou = '';
                    });
                }).catch(function (data) {
                    vm.codOuList = {};
                });

                datosBack.getData('parametros/getParametro/TEMA_LOCAL', false, false).then(function (resultado) {
                    vm.id_disp_origen = resultado.val_parametro;
                    globalData.setCodEquipo(vm.id_disp_origen);
                }).catch(function () { });
                //datosBack.getEstadoHabiAcceso().then(function(resultado) { });









        /*         $interval(function () {
                    broadcast.send("pantalla", {
                        message: 'Prueba de servicio de broadcast',
                        level: 'info',
                        level_class: 'info',
                        level_img: 'info',
                        timeStamp: new Date(),
                    });
                }, 5000); */
                

            };

            $scope.$on('abmOU', function (event, args) {
                vm.cod_ou = '';
                localData.getListaOU(true).then(function (resultado) {
                    vm.codOuList = resultado;
                    globalData.getOU().then(function (response) {
                        vm.cod_ou = response;
                    }).catch(function () {
                        vm.cod_ou = '';
                    });
                });
            });

            vm.last_authenticated = false;
            $scope.$on('auth', function (event, args) {
                if (vm.last_authenticated == args.authenticated)
                    return;
                vm.last_authenticated = args.authenticated;

                vm.codUsuario = auth.getCodUsuario();
                if (!vm.isLogged && args.authenticated) {
                    realTimeData.close();
                    realTimeData.connect();
                }


                vm.isLogged = args.authenticated;
                if (args.authenticated) {
                    localData.getListaOU(true).then(function (resultado) {
                        vm.codOuList = resultado;
                        globalData.getOU().then(function (response) {
                            vm.cod_ou = response;
                            globalData.getSectores().then(function () { }).catch(function () { });
                        }).catch(function () {
                            vm.cod_ou = '';
                        });
                    }).catch(function (data) {
                        vm.codOuList = '';
                    });
                } else {
                    store.set('sectoresList', '');
                    store.set('cod_ou', '');
                    $state.go('/about');
                }
            });

            vm.fcambiaSelOU = function () {
                globalData.setOU(vm.cod_ou).then(function () { }).catch(function () {
                    vm.cod_ou = '';
                })
                    .finally(function () {
                        $scope.$broadcast('cambiaSelOU', {});
                    });
            };

            vm.callLogin = auth.callLogin;

            $scope.$on('estados', function (event, args) {
                if (!args.context.EstadoDen)
                    return;

                switch (args.context.EstadoDen) {
                    case "RealTimeData":
                        datosBack.getEstadosLeds();
                        vm.colorEstadoConsola = args.context.EstadoColor;
                        break;
                    case "Backend":
                        vm.colorEstadoBackend = args.context.EstadoColor;
                        break;
                    case "HabiAcceso":
                        vm.colorEstadoHabiAcceso = args.context.EstadoColor;
                        break;
                    case "indModoPrueba":
                        vm.colorEstadoPrueba = args.context.EstadoColor;
                        break;

                    default:
                        break;
                }
            });

            $scope.$on('sucesos', function (event, args) {
                if (args.context.ind_activa_autio && args.context.ind_estado == "1") {
                    vm.btn_bell_class = 'btn-outline-danger';
                    sounds.start();
                    vm.shake_bell_class = 'shaker';
                }
            });

                
            vm.switchAudio = function () {
                sounds.stop();
                vm.btn_bell_class = 'btn-outline-light';
                vm.shake_bell_class = '';
            };

            vm.initCaptureDevices = function () {
                captureMedia.init();
            }

        }]

};

export default bodyDefault;
