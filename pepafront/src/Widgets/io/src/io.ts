'use strict';

function RegisterIO(dashboardProvider) {
    dashboardProvider
        .widget('io', {
            title: 'IO',
            description: 'IO',
            controllerAs: 'widgetio',
            controller: ['$interval', 'config', 'datosBack', 'datosBackIO', '$scope', 'widget', 'globalData',
                function ($interval, config, datosBack, datosBackIO, $scope, widget, globalData) {
                    var vm = this;
                    vm.io = {};
                    widget.config.tiempo_recarga_seg = "10";

                    datosBack.getData('io', false, false).then(function (response) {
                        widget.config.iolist = response;
                    }).catch(function () { });

                    $scope.$on('io', function (event, args) {
                        cargaWidget();
                        /*
                        angular.forEach(args.context,function(val,ind){
                          if(val.io_name == vm.io.io_name && val.cod_equipo==globalData.getCodEquipo())
                              angular.merge(vm.io,val);
                        });*/
                    });

                    function cargaWidget() {
                        //vm.io = datosBackIO.getEstadoIOs()[widget.config.ionro.io_name];
                        datosBack.getData('io/val', false, false).then(function (response) {
                            vm.io = response[widget.config.ionro.io_name];
                        }).catch(function (data) {
                        });
                    }

                    vm.setData = function (io_name, value) {
                        datosBack.save('', 'io', { 'io_name': io_name, 'value': value }, '').then(function (response) {
                            vm.io = response.data;
                        }).catch(function (data) {
                        }).finally(function () {
                            //cargaWidget();
                        });
                    };
                    if (widget.config.ionro) {
                        widget.title = widget.config.ionro.io_label + " (" + widget.config.ionro.io_name + ")";
                        //datosBackIO.setTiempoRecargaSeg(widget.config.tiempo_recarga_seg);
                        cargaWidget();
                    }


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

angular.module('adf.widget.io', ['adf.provider'])
    .config(['dashboardProvider', RegisterIO]);
