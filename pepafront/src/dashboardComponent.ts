'use strict';

import { defaultCipherList } from "constants";

/*
    Se agrego
    window.addEventListener('resize', function() {chart.resize({width: element.parent()[0].clientWidth});});
    en angular-echars.js  getLinkFunction line 190
*/
const dashboardComponent = {
    //    selector: 'bodyComponent', //body-component
    template: require('./Pages/dashboard.html'),
    bindings: {},
    controllerAs: "dashboard",
    controller: ['$scope', 'store', '$timeout', '$window', 'globalData', 'auth', function ($scope, store, $timeout, $window, globalData, auth) {
        const vm = this;
        const name = 'sample-04.1';

        vm.tabData = [
            {
                heading: 'Settings',
                route: 'common.dashboard.test',
            },
            {
                heading: 'Accounts',
                route: 'common.dashboard.test2',
                // disable: true
            },
        ];



        // set default model for demo purposes
        const modeldefault = {
            title: 'Panel de control',
            //        titleTemplateUrl:"",
            structure: '6/3',
            rows: [{
                columns: [
                    {
                        styleClass: 'col-md-8',
                        widgets: [
                            {
                                type: 'movimientos',
                                config: {
                                    cant_dias: '5',
                                    tiempo_recarga_seg: '10',
                                },
                                title: 'Movimientos',
                            },
                            {
                                type: 'diskspace',
                                config: {
                                    selected_disk: '/',
                                    tiempo_recarga_seg: '10',
                                },
                                title: 'Espacio en disco',
                            },
                            {
                                type: 'display_area54',
                                config: {
                                    tiempo_recarga_seg: '50',
                                },
                                title: 'Display Central Incendio',
                            },

                        ],
                    },
                    {
                        styleClass: 'col-md-4',
                        widgets: [
                            {
                                type: 'io_lista',
                                config: {
                                    tiempo_recarga_seg: '50',
                                },
                                title: 'I/O',
                            },
                            {
                                type: 'processor',
                                config: {
                                    tiempo_recarga_seg: '10',
                                },
                                title: 'Processor',
                            },
                            {
                                type: 'weather',
                                config: {
                                    location: 'Buenos Aires',
                                },
                                title: 'Weather',
                            },
                        ],
                    },
                ],
            },
            ],
        };


        vm.name = name;
        vm.collapsible = false;
        vm.maximizable = false;
        vm.categories = false;

        vm.$onInit = function () {
            auth.isLogged();
            $timeout(function () {
                $window.dispatchEvent(new Event("resize"));
            }, 500);

        }

        $scope.$on('adfDashboardChanged', function (_event: any, _name: any, model: any) {
            globalData.setUserDash(model);
        });

        $scope.$on('auth', function (event, args) {
            if (vm.model)
                return;
            //                console.log('auth', args);

            if (args.authenticated) {
                globalData.getUserDash().then(function (modelo) {

                    if (modelo)
                        vm.model = modelo;
                    else
                        vm.model = modeldefault;
                });
            }
        });
    }]
};

export default dashboardComponent;
 