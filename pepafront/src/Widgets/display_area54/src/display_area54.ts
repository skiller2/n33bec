'use strict';

function RegisterDiplayArea54(dashboardProvider) {
    dashboardProvider
        .widget('display_area54', {
            title: 'Display Central Incendio',
            description: 'Display Central Incendio',
            controllerAs: 'widgetdisplayArea54',
            controller: ['datosBack', '$scope', 'widget', 'globalData','auth','config', function (datosBack, $scope, widget, globalData,auth,config) {
                const vm = this;
                vm.linea1 = "".padEnd(40,' ');
                vm.linea2 = "".padEnd(40,' ');
                vm.linea3 = "".padEnd(40,' ');
                vm.linea4 = "".padEnd(40,' ');
                vm.status = 0;
                vm.display_buzzer=0
                vm.display_falla=0
        
                const defDisplay = () => { 
                    vm.linea1 = "                                        ";
                    vm.linea2 = "                SIN                     ";
                    vm.linea3 = "             CONEXIÓN                   ";
                    vm.linea4 = "                                        ";
                }
        
        
                function cargaWidget() {
                    datosBack.getData('display_area54/'+btoa(config.cod_tema), false, false).then(function (response) {
                    }).catch(function (data) {
                    });
                }
        
                vm.sendCMD = (cmd: string) => {
                    console.log('recibido', cmd,config.cod_tema)
                    const cod_tema = config.cod_tema+"/00/000"; 
                    //reset //up //down //left //right //ack
                    return datosBack.save('proceso', 'displaysucesos/cmdcentral', { cod_tema: cod_tema,cmd:cmd }, '')
                        .then(function () {
                        })
                        .catch(function () { });
                }
        

                $scope.$on('auth', function (event, args) {
                    if (args.authenticated) 
                        cargaWidget();
                });
        
                $scope.$on('display_area54', function (event, args) {
                    if (args.context.display && config.cod_tema == args.context.cod_tema) {
                        vm.status = (args.context.display_status) ? args.context.display_status:0;
                        vm.command_enabled = (args.context.command_enabled) ? args.context.command_enabled:0;
                        if (!vm.linea1 && !vm.linea2 && !vm.linea3 && !vm.linea4) {
                            defDisplay();
                        } else {
                            vm.linea1 = args.context.display[1].padEnd(40,' ');
                            vm.linea2 = args.context.display[2].padEnd(40,' ');
                            vm.linea3 = args.context.display[3].padEnd(40,' ');
                            vm.linea4 = args.context.display[4].padEnd(40,' ');
                        }
                    }

                    vm.display_alarma = vm.status >> 1 & 1;
                    vm.display_falla = vm.status >> 2 & 1;
                    vm.display_buzzer = vm.status >> 6 & 1;

//                    console.log('display_alarma ', vm.display_alarma)
//                    console.log('display_falla ',vm.display_falla)
//                    console.log('display_buzzer ',vm.display_buzzer)

                });
        
        //        vm.$onInit = function () {
        //            auth.isLogged();
        //       }
                if (auth.isLoggedIn())
                    cargaWidget();
        
            }],
            reload: true,
            template: require('./view.html'),
            titleTemplate: require('../../widget-title.html'),
            edit: {
                template: require('./edit.html')
            },
            resolve: {
                config: ['config', function (config) {
                    return config;
                }]
            }
        });
}

angular.module('adf.widget.display_area54', ['adf.provider'])
    .config(['dashboardProvider', RegisterDiplayArea54]);
