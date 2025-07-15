'use strict';
       
const displaySectorComponent = {
    template: require('../Pages/display_sector.html'),
    bindings: {},
    controllerAs: "display",
    controller:
        ['$scope', 'realTimeData', 'datosBack', '$state', '$timeout',
            function ($scope, realTimeData, datosBack, $state, $timeout) {

                const vm = this;
                const fotouser = require('../Content/Images/user.svg');

                let timer;
                let cod_sector = '';

                vm.nom_sector = '';
                vm.cant_personas = '';
                vm.max_cant_personas = '';

                vm.ind_lectura = false;
                vm.ind_reloj = false;
                vm.ind_listado = false;
                vm.ind_rechazo = '';
                vm.color = 'dark';
                vm.nom_persona = '';
                vm.ape_persona = '';
                vm.nom_ou = '';
                vm.vencimiento = '';
                vm.tipo_habilitacion = '';
                vm.lista = [];

                vm.showList = () => {
                    if (!vm.ind_listado) {
                        datosBack.getData('displaysector/lista/' + cod_sector, false, false).then(function (response) {
                            vm.lista = response;
                            showDisplay('listado', '');
                        }).catch(function (data) {
                            vm.lista = [];

                            showDisplay('listado', '');
                        });
                    } else
                        showDisplay('reloj', '');
                }

                vm.$onDestroy = function () { };

                vm.$onInit = function () {
                    if ($state.params.cod_sector) {
                        cod_sector = $state.params.cod_sector;
                        vm.refreshCounter();
                    } else {
                        $state.go('common.dashboard');
                    }
                    showDisplay('reloj', '');
                    realTimeData.connect();
                };

                vm.refreshCounter = () => {
                    datosBack.getData('displaysector/sector/' + cod_sector, false, false).then(function (response) {
                        vm.nom_sector = response.nom_sector;
                        vm.cant_personas = response.cant_personas;
                        vm.max_cant_personas = response.max_cant_personas;
                        vm.progresspercen = response.cant_personas * 100 / response.max_cant_personas;
                    }).catch(function (data) {
                    });
                }

                const showDisplay = (den_display, cod_credencial) => {
                    $timeout.cancel(timer);
                    vm.ind_lectura = false;
                    vm.ind_reloj = false;
                    vm.ind_listado = false;
                    vm.foto = fotouser;
                    vm.nom_persona = '';
                    vm.ape_persona = '';
                    vm.nom_ou = '';
                    vm.vencimiento = '';
                    vm.tipo_habilitacion = '';
                    vm.color = 'dark';

                    switch (den_display) {
                        case "reloj": //
                            vm.ind_reloj = true;
                            break;
                        case "persona": //
                            switch (vm.ind_rechazo) {
                                case 'R':
                                    vm.color = 'danger';
                                    break;
                                case 'F':
                                    vm.color = 'danger';
                                    break;
                                case 'G':
                                    vm.color = 'danger';
                                    break;
                                case 'H':
                                    vm.color = 'danger';
                                    break;
                                case '':
                                    vm.color = 'success';
                                    break;
                                default:
                                    vm.color = 'dark';
                                    break;
                            }

                            if (cod_credencial) {
                                datosBack.getData('displaysector/persona/' + cod_credencial, false, false).then(function (response) {
                                    vm.ape_persona = response.ape_persona;
                                    vm.nom_persona = response.nom_persona;
                                    vm.nom_ou = response.nom_ou;
                                    vm.vencimiento = response.vencimiento;
                                    vm.tipo_habilitacion = response.tipo_habilitacion;
                                    vm.ind_lectura = true;
                                    vm.refreshCounter();

                                }).catch(function (data) {
                                    showDisplay('reloj', '');
                                });
                                getFoto(cod_credencial);
                            } else
                                vm.ind_lectura = true;

                            timer = $timeout(function () {
                                showDisplay('reloj', '');
                            }, 5000);

                            break;
                        case "listado": //
                            vm.ind_listado = true;
                            timer = $timeout(function () {
                                showDisplay('reloj', '');
                            }, 5000);

                            break;
                    }
                }

                const getFoto = (cod_credencial) => {
                    datosBack.getData('displaysector/foto/' + cod_credencial, false, false).then(function (response) {
                        if (response)
                            vm.foto = response;
                    }).catch(function (data) {
                    });
                }

                $scope.$on('movcred', function (event, args) {
                    if ((args.context.cod_tema && args.context.cod_tema.indexOf("1000/bus3/9"))) {
                        let cod_credencial = args.context.cod_credencial;
                        vm.ind_rechazo = args.context.ind_rechazo;
                        showDisplay('persona', cod_credencial);
                    }
                });

            }]
};

export default displaySectorComponent;