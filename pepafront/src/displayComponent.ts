'use strict';

import moment from "moment";


const displayComponent = {
    template: require('./Pages/display.html'),
    bindings: {},
    controllerAs: "display",
    controller:
        ['$scope', 'datosBack', 'sounds', '$timeout','$translate',
            function ($scope, datosBack, sounds, $timeout,$translate) {
                const vm = this;
                vm.linea1 = '1111111111111111111111111111111111111111'; // "PRUEBA                             11111";
                vm.linea2 = '2222222222222222222222222222222222222222'; // "PRUEBA                             22222";
                vm.linea3 = '3333333333333333333333333333333333333333'; // "PRUEBA                             33333";
                vm.linea4 = '4444444444444444444444444444444444444444'; // "PRUEBA                             44444";
                vm.cod_tema = 'fourseassons/elkron';
                vm.des_valor = $translate.instant('Normal');
                vm.button = 'btn-success';

                $scope.$on('display_area54', function (event, args) {
                    if (args.context.display && vm.cod_tema==args.context.cod_tema) { 
                        vm.linea1 = args.context.display[1];
                        vm.linea2 = args.context.display[2];
                        vm.linea3 = args.context.display[3];
                        vm.linea4 = args.context.display[4];
        //                console.log('display', config.cod_tema,vm);
                        if (!vm.linea1 && !vm.linea2 && !vm.linea3 && !vm.linea4) { 
                            defDisplay();
                        }

                        if (vm.linea1.indexOf('ALLARME') >= 0 || vm.linea2.indexOf('ALLARME') >= 0 || vm.linea3.indexOf('ALLARME') >= 0 || vm.linea4.indexOf('ALLARME') >= 0) {
                            vm.fireAlarm();
                        }
                        if (vm.linea1.indexOf('ALARM') >= 0 || vm.linea2.indexOf('ALARM') >= 0 || vm.linea3.indexOf('ALARM') >= 0 || vm.linea4.indexOf('ALARM') >= 0) {
                            vm.fireAlarm();
                        }

                    }
                });

                /*

                $scope.$on('display_area54', function (event, args) {
                    if (args.context.display) {
  
                        if (args.context.display[1]) vm.message1 = args.context.display[1];
                        if (args.context.display[2]) vm.message2 = args.context.display[2];
                        if (args.context.display[3]) vm.message3 = args.context.display[3];
                        if (args.context.display[4]) vm.message4 = args.context.display[4];

                        if (vm.message1.indexOf('ALLARME') >= 0 || vm.message2.indexOf('ALLARME') >= 0 || vm.message3.indexOf('ALLARME') >= 0 || vm.message4.indexOf('ALLARME') >= 0) {
                            vm.fireAlarm();
                        }

                        $scope.$applyAsync();
                    }
                });
                $scope.$on('sucesos', function (event, args) {
                    if (args.context.ind_activa_autio && args.context.ind_estado == "1") {
                        sounds.start();
                    }
                });
                */

                /*
                            $scope.$on('io', function(event, args) {
                                // if (args.context.id_disp_origen == globalData.getCodEquipo())
                                //    angular.merge(vm.io[args.context.io_name], args.context);
                
                                if (args.context.io_name === vm.io_name) {
                                    // vm.io_name = args.context.io_name;
                                    vm.value = args.context.value;
                                    vm.des_valor = args.context.des_valor;
                                    if (vm.value === 1) { vm.button = 'btn-danger'; } else { vm.button = 'btn-success'; }
                                    // vm.button = args.context.button;
                
                                    if (vm.suceso === 1) {
                                        sounds.start();
                                    } else {
                                        sounds.stop();
                                    }
                                }
                            });
                */
            
                const defDisplay = () => { 
                    vm.linea1 = "                                        ";
                    vm.linea2 = "                SIN                     ";
                    vm.linea3 = "             CONEXIÓN                   ";
                    vm.linea4 = "                                        ";
                }
                            
                
                vm.fireAlarm = function () {
                    vm.button = 'btn-danger';
                    vm.des_valor = $translate.instant("Alarma").toUpperCase();
                    //22-6
                    const hora = parseInt(moment(Date.now()).format("hh"));
                    //if (hora > 22 && hora < 6) {
                        sounds.start();
                    //}
                }

                vm.stopAlarm = function () {
                    vm.button = 'btn-success';
                    vm.des_valor = $translate.instant("Normal").toUpperCase();
                    sounds.stop();
                }
                /*
                            vm.setData = function (io_name, value) {
                                datosBack.save('', 'directio', { io_name, value }, '').then(function (response) {
                
                                }).catch(function (data) {
                                }).finally(function () {
                                    // cargaWidget();
                                });
                            };
                */
                vm.$onDestroy = function () {
                };

                vm.$onInit = function () {
                    $timeout(function () {
                            datosBack.getData('display_area54/'+btoa(vm.cod_tema), false, false).then(function (response) {
                            }).catch(function (data) {
                            });

                    }, 2000);

                    /*
                    datosBack.getData('io/1234/IO25', false, false).then(function (response) {
    
                        if (response.io_name === vm.io_name) {
                            // vm.io_name = args.context.io_name;
                            vm.value = response.value;
                            vm.suceso = response.value;
                            vm.des_valor = response.des_valor;
                            if (vm.value === 1) { vm.button = 'btn-danger'; } else { vm.button = 'btn-success'; }
                            // vm.button = args.context.button;
    
                            if (vm.suceso === 1) {
                                sounds.start();
                            } else {
                                sounds.stop();
                            }
                        }
                    }).catch(function (data) {
                    });
                    */
                };
            }]
};

export default displayComponent;