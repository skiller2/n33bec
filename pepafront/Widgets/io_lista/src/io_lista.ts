'use strict';

function RegisterIOLista(dashboardProvider) {
    dashboardProvider
        .widget('io_lista', {
            title: 'IO Lista',
            description: 'IO',
            controllerAs: 'widgetiolista',
            controller: ['datosBack', '$scope', 'widget', 'globalData', 'auth', function (datosBack, $scope, widget, globalData, auth) {
                const vm = this;
                vm.io = {};

                function cargaWidget() {
                    datosBack.getData('ios', false, false).then(function (response) {
                        vm.io = response;
                        widget.config.showAlert = false;
                    }).catch(function (data) {
                        widget.config.showAlert = true;
                    });
                }

                $scope.$on('io', function (event, args) {
                    if (vm.io[args.context.cod_tema])
                        angular.merge(vm.io[args.context.cod_tema], args.context);
                    /*angular.forEach(args.context,function(val,ind){
                        if(val.id_disp_origen==globalData.getCodEquipo())
                            angular.merge(vm.io,args.context);
                    });*/
                });

                $scope.$on('auth', function (event, args) {
                    if (args.authenticated)
                        cargaWidget();
                });



                vm.setData = function (cod_tema: string, value: string) {
                    let valueout: string;
                    switch (value) {
                        case '1':
                            valueout = '0';
                            break;
                        case '0':
                            valueout = '1';
                            break;
                        case 'NORM':
                            valueout = 'ALARM';
                            break;
                        case 'ALARM':
                            valueout = 'NORM';
                            break;
                        case '':
                            valueout = 'ALARM';
                            break;
                        case 'NONE':
                            valueout = '0';
                            break;

                        default:
                            break;
                    }
                    /*
                    datosBack.save('', 'io', { 'cod_tema': cod_tema, 'value': valueout }, '').then(function (response) {
                    }).catch(function (data) {
                    }).finally(function () {
                        cargaWidget();
                    });
                    */
                };

                //        vm.$onInit = function () {
                //            auth.isLogged();
                //        }
                if (auth.isLoggedIn()) {
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

angular.module('adf.widget.io_lista', ['adf.provider'])
    .config(['dashboardProvider', RegisterIOLista]);
