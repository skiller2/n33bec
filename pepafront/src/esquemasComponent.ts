'use strict';


const esquemasComponent =
{
    template: require('./Pages/esquemas.html'),
    bindings: {
    },
    controllerAs: "esquemas",
    controller:
        ['localData', 'datosBack', 'globalData', '$state','$timeout','$translate',
            function (localData, datosBack, globalData, $state,$timeout,$translate) {

                const vm = this;
                vm.form_title = $translate.instant('Esquemas de Acceso');
                vm.esquema = {};
                vm.esquema_dt = {};
                vm.action_title = '';
                vm.hide = true;
                vm.active = 0;
                localData.getListaOU(true).then(function (resultado) {
                    vm.cod_ou = resultado;
                });
                vm.range = {};

                vm.uiOnParamsChanged = function (newParams) {
                    switch (newParams.action) {
                        case 'lista':
                            vm.active = 0;
                            break;
                        case 'detalle':
                            vm.active = 1;
                            vm.consulta();
                            break;
                        case 'edita':
                            vm.active = 2;
                            vm.toggle(newParams.action);
                            break;
                        case 'agrega':
                            vm.active = 2;
                            vm.toggle(newParams.action);
                            break;
                        case 'copia':
                            vm.active = 2;
                            vm.toggle(newParams.action);
                            break;
                        default:
                            $state.go('.', {
                                action: 'lista',
                            });
                            break;
                    }
                };

                vm.$onInit = function () {
                    if ($state.params.action === 'lista' || $state.params.action === 'agrega') {
                        vm.uiOnParamsChanged($state.params);
                    } else {
                        $state.go('.', {
                            action: 'lista',
                        });
                    }
                    $timeout(function () { vm.grilla.fillGrid() },0);
                };

                // Set search items for starting and reset.
 
/*
                vm.gridOptions = {
                    excludeProperties: '__metadata',
                    enablePaginationControls: false,
                    enableRowSelection: true,
                    enableRowHeaderSelection: true,
                    multiSelect: false,
                    enableFullRowSelection: true,
                    useExternalSorting: true,
                    data: [],
                    // multiSelect: false
                };
*/
                // show modal form
                vm.toggle = function (action) {
                    vm.esquema = {};
                    vm.action = action;
                    const dtkey = vm.grilla.getdtKey();
                    console.log("dtkey", dtkey);
                    switch (action) {
                        case 'agrega':
                            vm.action_title = $translate.instant('Alta');
                            vm.noteditable = false;
                            vm.hide = false;
                            // vm.active=2;
                            break;
                        case 'copia':
                            if (dtkey && dtkey.length > 0) {
                                vm.action_title = $translate.instant('Alta');
                                vm.noteditable = false;
                                vm.disabled = '';
                                datosBack.detalle('esquemas', dtkey)
                                    .then(function (response) {
                                        vm.esquema = response.data;
                                        vm.esquema.cod_esquema_acceso = '';
                                        // vm.active=2;
                                        vm.hide = false;
                                    })
                                    .catch(function () { });
                            }
                            break;
                        case 'edita':
                            if (dtkey && dtkey.length > 0) {
                                vm.action_title = $translate.instant('Modificación');
                                vm.noteditable = true;
                                vm.disabled = 'disabled';
                                datosBack.detalle('esquemas', dtkey)
                                    .then(function (response) {

                                        vm.esquema = response.data;
                                        // vm.active=2;
                                        vm.hide = false;
                                    })
                                    .catch(function () { });
                            }
                            break;
                        default:
                            break;
                    }
                };

                vm.consulta = function () {
                    vm.esquema_dt = {};
                    if (vm.grilla.getdtKey() !== '') {
                        datosBack.detalle('esquemas', vm.grilla.getdtKey())
                            .then(function (response) {
                                vm.esquema_dt = response.data;
                            })
                            .catch(function () { });
                    }
                };

                vm.ok = function () {
                    vm.esquema.cod_ou_sel = globalData.getOU();

                    return datosBack.save(vm.action, 'esquemas', vm.esquema, vm.grilla.getLastSelected())
                        .then(function () {
                            vm.grilla.fillGrid();
                            vm.esquema = {};
                            localData.resetEsquemasList();
                            vm.active = 0;
                            vm.hide = true;
                            $state.go('.', { action: 'lista' });
                        })
                        .catch(function () { });
                };

                // delete record
                vm.confirmDelete = function () {
                    datosBack.delete('esquemas', vm.grilla.getLastSelected()).then(function (response) {
                        vm.grilla.fillGrid();
                        localData.resetEsquemasList();
                    }).catch(function (data) { });
                };
            },
        ]
};

export default esquemasComponent;