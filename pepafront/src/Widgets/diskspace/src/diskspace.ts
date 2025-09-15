'use strict';
function RegisterDiskSpace1(dashboardProvider) {
    dashboardProvider
        .widget('diskspace', {
            title: 'Espacio en disco',
            description: 'Espacio en disco',
            controllerAs: 'widgetdiskspace',
            controller: ['$interval', '$state', 'config', 'datosBack', '$scope', 'widget', 'auth',
            function ($interval, $state, config, datosBack, $scope, widget, auth
            ) {
                const vm = this;
                let Timer;
                vm.labels = [];
                vm.data = [];
                vm.options = config.options;
                vm.last_authenticated = false;
    
                vm.configDisk = {
                    pie: {
                        splitNumber: 5,
                        detail: {
                            /*
                            formatter:function(v){
                                       return v+'%';
                                     },
            */
                            offsetCenter: [0, '70%'],
                            // formatter: null,
                            // 其余属性默认使用全局文本样式，详见TEXTSTYLE
                            textStyle: {
                                color: 'auto',
                                fontSize: 30,
                            },
                        },
                    },
                    tooltip: {
                        trigger: 'item',
                        formatter: '{a} <br/>{b}: {d}%',
                    },
                    legend: {
                        orient: 'horizontal',
                        y: 'top',
                        x: 'center',
                        // data:['Utilizado','Disponible']
                    },
                    debug: false,
                    showXAxis: true,
                    showYAxis: true,
                    showLegend: true,
                    stack: false,
                };
    
                vm.dataDisk = [{
                    name: 'Espacio en disco',
                    datapoints: [/*
                    {y: 70, x:'Utilizado' },
                    {y: 30, x:'Disponible' }*/
                    ],
                }];
    
                $scope.$on('$destroy', function () {
                    vm.stop();
                });
    
                function cargaWidget() {
                    if (!config.selected_disk)
                        config.selected_disk = "/";
                    const sel = JSON.stringify(config.selected_disk);
                    datosBack.getData('diskspace/' + sel, false, false).then(function (response) {
                        // vm.labels = response.labels;
                        // vm.data = response.data;
                        vm.dataDisk[0].datapoints = response.disco;
                        widget.config.showAlert = false;
                    }).catch(function (data) {
                        widget.config.showAlert = true;
                    });
                }
    
                vm.stop = function () {
                    $interval.cancel(Timer);
                };
    
                vm.start = function () {
                    vm.stop();
                    cargaWidget();
                    if (!config.tiempo_recarga_seg || config.tiempo_recarga_seg < 5)
                        config.tiempo_recarga_seg = 10;
                    Timer = $interval(cargaWidget, config.tiempo_recarga_seg * 1000);
                };
    
                $scope.$on('auth', function (event, args) {
                    if (vm.last_authenticated == args.authenticated)
                        return;
                    vm.last_authenticated = args.authenticated;
    
                    if (args.authenticated) 
                        vm.start();
                    else 
                        vm.stop();
                      
                });
    
    //            vm.$onInit = function () {
    //            auth.isLogged();
                if (auth.isLoggedIn()) {
                    vm.last_authenticated = true;
                    vm.start();
                }
                
    //            }
    
            }],
            template: require('./view.html'),
            titleTemplate: require('../../widget-title.html'),
            reload: true,
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

angular.module('adf.widget.diskspace', ['adf.provider'])
    .config(['dashboardProvider', RegisterDiskSpace1]);
