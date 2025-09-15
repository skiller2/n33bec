'use strict';
const stockcredencialesComponent =
{
    template: require('./Pages/stockcredenciales.html'),
    bindings: {
    },
    controllerAs: "stockcredenciales",
    controller: ['localData', 'datosBack', '$state', 'globalData', '$scope', '$window', '$timeout','$translate',
        function (localData, datosBack, $state, globalData, $scope, $window,$timeout,$translate) {

            var vm = this;
            vm.form_title = $translate.instant("Stock Tarjetas");
            vm.credencial = {};
            vm.credencial_dt = {};
            vm.action_title = "";
            vm.hide = true;
            vm.active = 0;
            vm.tipoCredencial = localData.getTipoCredencial();
            vm.tipoHabilitacion = localData.getTipoHabilitacion();
            vm.ref_credencial_last = "";

            vm.uiOnParamsChanged = function (newParams) {
                switch (newParams.action) {
                    case "lista":
                        vm.active = 0;
                        break;
                    case "detalle":
                        vm.active = 1;
                        vm.consulta();
                        break;
                    case "edita":
                        vm.active = 2;
                        vm.toggle(newParams.action);
                        break;
                    case "agrega":
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
            vm.toggle = function (action, selected) {
                //vm.credencial = {};
                vm.action = action;
                const dtkey = vm.grilla.getdtKey();


                switch (action) {
                    case 'agrega':
                        vm.action_title = $translate.instant("Alta");
                        vm.noteditable = false;
                        vm.credencial.cod_credencial = "";
                        vm.hide = false;
                        vm.active = 2;
                        break;
                    case 'copia':
                        vm.credencial = {};
                        if (dtkey && dtkey.length > 0) {
                            vm.action_title = $translate.instant("Alta");
                            datosBack.detalle('stockcredenciales', dtkey)
                                .then(function (response) {
                                    vm.credencial = response.data;
                                    vm.credencial.cod_credencial = "";
                                    vm.active = 2;
                                    vm.hide = false;
                                })
                                .catch(function () { });
                        }
                        break;
                    case 'edita':
                        vm.credencial = {};
                        if (dtkey && dtkey.length > 0) {
                            vm.action_title = $translate.instant("Modificación");
                            vm.noteditable = true;
                            datosBack.detalle('stockcredenciales', dtkey)
                                .then(function (response) {
                                    vm.credencial = response.data;
                                    vm.active = 2;
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
                const dtkey = vm.grilla.getdtKey();
                vm.credencial_dt = {};
                if (dtkey != "") {
                    datosBack.detalle('stockcredenciales', dtkey)
                        .then(function (response) {
                            vm.credencial_dt = response.data;
                        })
                        .catch(function () { });
                }
            };

            vm.ok = function () {
                return datosBack.save(vm.action, 'stockcredenciales', vm.credencial, vm.grilla.getLastSelected())
                    .then(function () {
                        vm.grilla.fillGrid();
                        //                        console.log('valida', isFinite(vm.credencial.ref_credencial), vm.credencial.ref_credencial);
                        if (isFinite(vm.credencial.ref_credencial)) {
                            vm.credencial.ref_credencial = parseInt(vm.credencial.ref_credencial) + 1;

                            //                            console.log('incremneto', vm.credencial.ref_credencial);

                        }
                        //Incremento ref_credencial +1 si es numérica.
                        vm.active = 0;
                        vm.hide = true;
                        $state.go('.', { action: 'lista' });
                    })
                    .catch(function () { });
            };

            //delete record
            vm.confirmDelete = function () {
                datosBack.delete('stockcredenciales', vm.grilla.getLastSelected()).then(function (response) {
                    vm.grilla.fillGrid();
                }).catch(function (data) { });
            };

            $scope.$on('input', function (event, args) {
                if (args.context.cod_tema == globalData.getCodTemaLector()) {
                    vm.credencial.cod_credencial = args.context.cod_credencial;
                    var someElement = $window.document.getElementById('ref_credencial');
                    someElement.focus();
                }
            });

        }
    ]
};

export default stockcredencialesComponent;