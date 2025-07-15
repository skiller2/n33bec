'use strict';

import createPanZoom from "panzoom";
import angular from "angular";

const testComponent =
{
    template: require('../Pages/test.html'),
    bindings: {
    },
    controllerAs: "test",
    controller: ['$scope', 'datosBack', 'treegridTemplate', '$timeout', 'spinCounter', 'captureMedia', 'globalData',
        function ($scope, datosBack, treegridTemplate, $timeout, spinCounter, captureMedia, globalData) {

            //            var gui = require('nw.gui'); //or global.window.nwDispatcher.requireNwGui() (see https://github.com/rogerwang/node-webkit/issues/707)

            // Get the current window

            const vm = this;
            vm.imagen = "holahola";

            vm.limpia = () => {
                //                console.log('vm.limpia');
                vm.imagen = "";
                //                spinCounter.setSpinCounterAdd(1);

            }

            vm.butclose = () => {
                //                console.log("Se apretó botón");
            }

            vm.treedata_avm = [
                {
                    label: 'Animal',
                    children: [
                        {
                            label: 'Dog',
                            data: {
                                description: "man's best friend"
                            }
                        }, {
                            label: 'Cat',
                            data: {
                                description: "Felis catus"
                            }
                        }, {
                            label: 'Hippopotamus',
                            data: {
                                description: "hungry, hungry"
                            }
                        }, {
                            label: 'Chicken',
                            children: ['White Leghorn', 'Rhode Island Red', 'Jersey Giant']
                        }
                    ]
                }, {
                    label: 'Vegetable',
                    data: {
                        definition: "A plant or part of a plant used as food, typically as accompaniment to meat or fish, such as a cabbage, potato, carrot, or bean.",
                        data_can_contain_anything: true
                    },
                    onSelect: function (branch) {
                        return $scope.output = "Vegetable: " + branch.data.definition;
                    },
                    children: [
                        {
                            label: 'Oranges'
                        }, {
                            label: 'Apples',
                            children: [
                                {
                                    label: 'Granny Smith',
                                    //                          onSelect: apple_selected
                                }, {
                                    label: 'Red Delicous',
                                    //                          onSelect: apple_selected
                                }, {
                                    label: 'Fuji',
                                    //                          onSelect: apple_selected
                                }
                            ]
                        }
                    ]
                }, {
                    label: 'Mineral',
                    children: [
                        {
                            label: 'Rock',
                            children: ['Igneous', 'Sedimentary', 'Metamorphic']
                        }, {
                            label: 'Metal',
                            children: ['Aluminum', 'Steel', 'Copper']
                        }, {
                            label: 'Plastic',
                            children: [
                                {
                                    label: 'Thermoplastic',
                                    children: ['polyethylene', 'polypropylene', 'polystyrene', ' polyvinyl chloride']
                                }, {
                                    label: 'Thermosetting Polymer',
                                    children: ['polyester', 'polyurethane', 'vulcanized rubber', 'bakelite', 'urea-formaldehyde']
                                }
                            ]
                        }
                    ]
                }
            ];




            vm.$onInit = function () {
                vm.limpia();
                captureMedia.init();
                vm.cod_sector = "737334034759";

                globalData.getSectoresTree(false, false).then(function (resultado) {
                    vm.sectoresList = resultado;
                    console.log('getSectoresTree vm.sectoresList', vm.sectoresList);

                });
    
            };

            vm.stopcam = () => {
                captureMedia.close();
            }
            vm.startcam = () => {
                captureMedia.init();
            }
        }]
};

export default testComponent;