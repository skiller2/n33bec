'use strict';
angular.module('adf.widget.processor', ['adf.provider'])
    .config(['dashboardProvider', RegisterProcessor]);

function RegisterProcessor(dashboardProvider) {
    dashboardProvider
        .widget('processor', {
            title: 'processor',
            description: 'processor',
            controllerAs: 'widgetprocessor',
            controller: ['$interval', '$state', 'config', 'datosBack', '$scope', 'widget', 'auth', function ($interval, $state, config, datosBack, $scope, widget, auth) {
                const vm = this;
                let Timer;
                vm.processor = {};
        
                vm.dataCPULoad = [{
                    name: 'Uso CPU',
                    datapoints: [
                        { y: 100 },
                    ],
                }];
                vm.configCPULoad = {
                    gauge: {
                        splitNumber: 5,
                        detail: {
                            show: true,
                            formatter(v) {
                                return v + '%';
                            },
        
                            offsetCenter: [0, '70%'],
                            // formatter: null,
                            // 其余属性默认使用全局文本样式，详见TEXTSTYLE
                            textStyle: {
                                color: 'auto',
                                fontSize: 15,
                            },
                        },
                    },
        
                    debug: false,
                    showXAxis: true,
                    showYAxis: true,
                    showLegend: true,
                    stack: false,
                };
        
                vm.dataTemp = [{
                    name: 'Temperatura',
                    datapoints: [
                        { y: 150 },
                    ],
                }];
        
                vm.configTemp = {
                    gauge: {
                        splitNumber: 4,
                        // center : ['75%', '50%'],    // 默认全局居中
                        //          radius : '50%',
                        min: 0,
                        max: 140,
                        //          startAngle:315,
                        //          endAngle:225,
                        axisLine: {// 坐标轴线
                            lineStyle: {// 属性lineStyle控制线条样式
                                color: [[0.2, '#228b22'], [0.8, '#48b'], [1, '#ff4500']],
                                width: 8,
                            },
                        },
                        axisTick: {// 坐标轴小标记
                            show: false,
                        },
        
                        axisLabel: {
                            /*
                             formatter:function(v){
                             return v+' C';
                             switch (v + '') {
                             case '0' : return 'H';
                             case '1' : return 'Water';
                             case '2' : return 'C';
                             }
                             }
                             */
                            distance: 0,
                            textStyle: {
                                color: 'auto',
                                fontSize: 3,
                            },
                        },
                        splitLine: {// 分隔线
                            length: 15, // 属性length控制线长
                            lineStyle: {// 属性lineStyle（详见lineStyle）控制线条样式
                                color: 'auto',
                            },
                        },
                        pointer: {
                            width: 2,
                        },
                        title: {
                            show: false,
                        },
                        detail: {
                            formatter(v) {
                                return v + ' °C'; /*
                                         switch (v + '') {
                                         case '0' : return 'H';
                                         case '1' : return 'Water';
                                         case '2' : return 'C';
                                         }*/
                            },
                            show: true,
                            offsetCenter: [0, '70%'],
                            // formatter: null,
                            // 其余属性默认使用全局文本样式，详见TEXTSTYLE
                            textStyle: {
                                color: 'auto',
                                fontSize: 9,
                            },
                        },
                    },
                    debug: false,
                    showXAxis: true,
                    showYAxis: true,
                    showLegend: true,
                    stack: false,
                };
        
                $scope.$on('$destroy', function () {
                    vm.stop();
                });
        
                function cargaWidget() {
                    datosBack.getData('processor', false, false).then(function (response) {
                        vm.processor = response;
                        vm.dataCPULoad[0].datapoints[0].y = response.cpuLoad;
                        vm.dataTemp[0].datapoints[0].y = response.cpuTemp;
                        widget.config.showAlert = false;
                    }).catch(function (data) {
                        widget.config.showAlert = true;
                    });
                }
        
                vm.stop = function () {
                    $interval.cancel(Timer);
                };
        
                vm.start = function () {
                    // stops any running interval to avoid two intervals running at the same time
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
                //        }
                if (auth.isLoggedIn()) {
                    vm.last_authenticated = true;
                    vm.start();
                }
        
            }],
            template: require('./view.html'),
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

