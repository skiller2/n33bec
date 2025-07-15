'use strict';

const sectoresComponent =
{
    template: require('../Pages/sectores.html'),
    bindings: {
    },
    controllerAs: "sectores",
    controller: ['$timeout', 'datosBack', 'globalData', '$state', '$window','$translate',
        function ($timeout, datosBack, globalData, $state, $window,$translate) {

            const vm = this;
            vm.form_title = $translate.instant("Sectores");
            vm.sector = {};
            vm.sector_dt = {};
            vm.action_title = "";
            vm.hide = true;
            vm.active = 0;
            //            vm.sectoresList = [];
            vm.sector.imagenes = { "img_plano": "" };

            //            localData.getSectoresList(false, false).then(function (resultado) {
            //                vm.sectoresList = resultado;
            //            });

            globalData.getSectoresTree(false, false).then(function (resultado) {
                vm.sectoresList = resultado;
            });



            vm.uiOnParamsChanged = function (newParams) {
                switch (newParams.action) {
                    case "lista":
                        vm.active = 0;
                        break;
                    case "detalle":
                        vm.active = 1;
                        vm.consulta();
                        break;
                    case "videos":
                        vm.active = 3;
                        vm.consulta();
                        break;
                    case "edita":
                        //                        localData.getSectoresList(false, false).then(function (resultado) {
                        //                            vm.sectoresList = resultado;
                        //                        });

                        globalData.getSectoresTree(false, false).then(function (resultado) {
                            vm.sectoresList = resultado;
                        });
            

                        vm.active = 2;
                        vm.toggle(newParams.action);
                        break;
                    case "agrega":
                        //                        localData.getSectoresList(false, false).then(function (resultado) {
                        //                            vm.sectoresList = resultado;
                        //                        });

                        globalData.getSectoresTree(false, false).then(function (resultado) {
                            vm.sectoresList = resultado;
                        });
            

                        vm.active = 2;
                        vm.toggle(newParams.action);
                        break;
                    case "copia":
                        vm.active = 2;
                        vm.toggle(newParams.action);
                        break;
                    default:
                        $state.go('.', {
                            action: 'lista'
                        });
                        break;
                }
            };

            vm.$onInit = function () {
                if ($state.params.action === 'lista' || $state.params.action === 'agrega')
                    vm.uiOnParamsChanged($state.params);
                else
                    $state.go('.', {
                        action: 'lista'
                    });
                $timeout(function () { vm.grilla.fillGrid() },0);

            };

            //Set search items for starting and reset.
            vm.gridOptions = {
                excludeProperties: '__metadata',
                enablePaginationControls: false,
                enableRowSelection: true,
                enableRowHeaderSelection: true,
                multiSelect: false,
                enableFullRowSelection: true,
                useExternalSorting: true,
                data: []
                //multiSelect: false
            };

            //show modal form
            vm.toggle = function (action) {
                vm.sector = {};
                vm.action = action;
                const dtkey = vm.grilla.getdtKey();

                switch (action) {
                    case 'agrega':
                        vm.action_title = $translate.instant("Alta");
                        vm.noteditable = false;
                        vm.hide = false;
                        vm.active = 2;
                        vm.sector.imagenes = { "img_plano": "" };

                        break;
                    case 'copia':
                        if (dtkey && dtkey.length > 0) {
                            vm.action_title = $translate.instant("Alta");
                            datosBack.detalle('sectores', dtkey)
                                .then(function (response) {
                                    vm.sector = response.data;
                                    vm.sector.cod_sector = "";
                                    vm.active = 2;
                                    vm.hide = false;

                                    datosBack.getData('sectoresimgs/' + btoa(JSON.stringify(dtkey)) + "/" + vm.sector.img_hash, true, false, true)
                                        .then(function (response) {
                                            if (response.img_plano) {
                                                vm.sector.imagenes.img_plano = response.img_plano;
                                            }
                                        })
                                        .catch(function () { });
                                })
                                .catch(function () { });


                        }
                        break;
                    case 'edita':
                        vm.sector_dt.imagenes = { "img_plano": "" };

                        if (dtkey && dtkey.length > 0) {
                            vm.action_title = $translate.instant("Modificación");
                            datosBack.detalle('sectores', dtkey)
                                .then(function (response) {
                                    vm.sector = response.data;
                                    vm.sector.imagenes = { "img_plano": "" };
                                    vm.active = 2;
                                    vm.hide = false;

                                    datosBack.getData('sectoresimgs/' + btoa(JSON.stringify(dtkey)) + "/" + vm.sector.img_hash, true, false, true)
                                        .then(function (response) {
                                            if (response.img_plano) {
                                                vm.sector.imagenes.img_plano = response.img_plano;
                                            }
                                        })
                                        .catch(function () { });
                                })
                                .catch(function () { });

                        }
                        break;
                    default:
                        break;
                }
            };

            vm.consulta = function () {
                vm.sector_dt = {};
                vm.sector_dt.imagenes = { "img_plano": "" };
                const dtkey = vm.grilla.getdtKey();

                if (dtkey != "") {
                    datosBack.detalle('sectores', dtkey)
                        .then(function (response) {
                            vm.sector_dt = response.data;
                            if (vm.sector_dt.img_hash != "") {
                                datosBack.getData('sectoresimgs/' + btoa(JSON.stringify(dtkey)) + "/" + vm.sector_dt.img_hash, true, false, true)
                                    .then(function (response) {
                                        if (response.img_plano) {
                                            vm.sector_dt.imagenes = { "img_plano": response.img_plano };
                                        }
                                    })
                                    .catch(function () { });
                            }
                        })
                        .catch(function () { });

                }
            };

            vm.ok = function () {
                console.log("vm.ok",vm.action,vm.grilla.getLastSelected());
                return datosBack.save(vm.action, 'sectores', vm.sector, vm.grilla.getLastSelected())
                    .then(function () {
                        vm.grilla.fillGrid();
                        vm.sector = {};
                        globalData.cleanSectores();
                        vm.active = 0;
                        vm.hide = true;
                        $state.go('.', { action: 'lista' })
                    })
                    .catch(function () { });
            };

            //delete record
            vm.confirmDelete = function () {
                datosBack.delete('sectores', vm.grilla.getLastSelected()).then(function (response) {
                    vm.grilla.fillGrid();
                    globalData.cleanSectores();
                }).catch(function (data) { });
            };

            vm.loaderror = (url) => {
                $window.open(url, "Login", 'toolbar=0,scrollbars=1,location=0,status=0,menubar=0,resizable=0,width=600, height=300,left = 300,top=100');
            };

        }
    ]
};

export default sectoresComponent;