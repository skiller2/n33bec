'use strict';

function RegisterMovimientos(dashboardProvider) {
    dashboardProvider
        .widget('movimientos', {
            title: 'Movimientos',
            description: 'Movimientos',
            controllerAs: 'widgetmovimientos',
            controller: ['$interval', 'datosBack', 'config', '$scope', 'widget', 'auth',
            function ($interval, datosBack, config, $scope, widget, auth) {
                const vm = this;
    
                let Timer;
                // vm.series = config.series;
                vm.series = ['Permanentes', 'Temporales', 'Rechazados'];
                vm.labels = [];
                vm.data = [];
    
                vm.configMov = {
                    tooltip: {
                        trigger: 'axis',
                    },
                    debug: false,
                    showXAxis: true,
                    showYAxis: true,
                    showLegend: true,
                    stack: false,
                    theme: 'shine',
                };
    
                vm.dataMov = [
                    {
                        name: 'Permanentes',
                        datapoints: [],
                    },
                    {
                        name: 'Temporales',
                        datapoints: [],
                    },
                    {
                        name: 'Rechazados',
                        datapoints: [],
                    },
                ];
    
                $scope.$on('$destroy', function () {
                    vm.stop();
                });
    
                function cargaWidget() {
                    const cant_dias = (config.cant_dias!=undefined)?config.cant_dias:'7'
                    datosBack.getData('movimientos/dashboard/' +cant_dias , false, false).then(function (response) {
                        vm.dataMov[0].datapoints = response.dashboard_data[0];
                        vm.dataMov[1].datapoints = response.dashboard_data[1];
                        vm.dataMov[2].datapoints = response.dashboard_data[2];
                        widget.config.showAlert = false;
                    }).catch(function (data) {
                        widget.config.showAlert = true;
                    });
                }
    
                vm.stop = function () {
                    $interval.cancel(Timer);
                };
    
                vm.start = function () {
                    if (!config.tiempo_recarga_seg || config.tiempo_recarga_seg < 5)
                    config.tiempo_recarga_seg = 10;
                    vm.stop();
                    cargaWidget();
                    Timer = $interval(cargaWidget, config.tiempo_recarga_seg * 1000);
                };
    
                vm.last_authenticated = false;
                $scope.$on('auth', function (event, args) {
                    if (vm.last_authenticated == args.authenticated)
                        return;
                    vm.last_authenticated = args.authenticated;
    
                    if (args.authenticated)
                        vm.start();
                    else
                        vm.stop();
                });
    
                //        vm.$onInit = function () {
                //auth.isLogged();
                if (auth.isLoggedIn()) {
                    vm.last_authenticated = true;
                    vm.start();
                }
    
                //        }
    
            }],
            reload: true,
            template: require('./view.html'),
            titleTemplate: require('../../widget-title.html'),
            edit: {
                template: require('./edit.html'),
            },
            resolve: {
                config: ['config', function (config) {
                    return config;
                }],
            },
        });
}

angular.module('adf.widget.movimientos', ['adf.provider'])
    .config(['dashboardProvider', RegisterMovimientos])
