'use strict';

const movimientosComponent = {
    template: require('../Pages/movimientos.html'),
    bindings: {},
    controllerAs: "movimientos",
    controller: ['localData', 'datosBack', 'ModalService', '$state', '$scope', '$timeout','$translate',
        function (localData, datosBack, ModalService, $state, $scope, $timeout,$translate) {
            const vm = this;
            vm.persona = {};
            vm.test = {};
            datosBack.getData('temas/getLectores', false, false).then(function (resultado) {
                vm.lectores = resultado;
            });


            vm.tipoSexo = localData.getTipoSexo();
            vm.tipoDocumento = localData.getTipoDocumento();
            vm.form_title = $translate.instant('Movimientos');
            vm.fistreloadP = true;
            vm.fistreloadT = true;
            vm.fistreloadR = true;
            vm.$onDestroy = function () {
            };

            vm.uiOnParamsChanged = function (newParams) {
                switch (newParams.action) {
                    case 'permanentes':
                        vm.active = 0;
                        if (vm.fistreloadP) {
                            vm.fistreloadP = false;
                            vm.grillaPermanentes.fillGrid();
                        }
                        break;
                    case 'temporales':
                        vm.active = 1;
                        if (vm.fistreloadT) {
                            vm.fistreloadT = false;
                            vm.grillaTemporales.fillGrid();
                        }
                        break;
                    case 'rechazados':
                        vm.active = 2;
                        if (vm.fistreloadR) {
                            vm.fistreloadR = false;
                            vm.grillaRechazados.fillGrid();

                        }
                        break;
                    case 'detalle':
                        vm.active = 3;
                        vm.consulta();
                        break;
                    case 'leetarjeta':
                        vm.active = 4;
                        break;
                    default:
                        $state.go('.', {
                            action: 'permanentes',
                        });
                        break;
                }
            };

            vm.$onInit = function () {

                $timeout(function () {
                    if ($state.params.action === '') {
                        $state.go('.', {
                            action: 'permanentes',
                        });
                    } else
                        vm.uiOnParamsChanged($state.params);
                }, 0);

                //          vm.autoRefreshRechazados = true;
                //          vm.autoRefreshBtnRechazados = true;

            };



            const mystate = $state.$current;

            mystate.onExit = function () {
                vm.$onDestroy();
            };

            vm.gridOptionsPermanentes = {
                excludeProperties: '__metadata',
                enablePaginationControls: false,
                enableRowSelection: true,
                enableRowHeaderSelection: true,
                multiSelect: false,
                enableFullRowSelection: true,
                useExternalSorting: true,
                data: [],
            };

            vm.gridOptionsTemporales = {
                excludeProperties: '__metadata',
                enablePaginationControls: false,
                enableRowSelection: true,
                enableRowHeaderSelection: true,
                multiSelect: false,
                enableFullRowSelection: true,
                useExternalSorting: true,
                data: [],
            };

            vm.gridOptionsRechazados = {
                excludeProperties: '__metadata',
                enablePaginationControls: false,
                enableRowSelection: true,
                enableRowHeaderSelection: true,
                multiSelect: false,
                enableFullRowSelection: true,
                useExternalSorting: true,
                data: [],
            };

            vm.onSelection = (sel) => {
                vm.dtkey = "";
                vm.urlbacksel = "";
                //          console.log("vm.dtkey", vm.dtkey);
                if (sel) {
                    vm.dtkey = sel.selected;
                    vm.urlbacksel = sel.urlback;
                }
            }

            vm.consulta = function () {
                vm.persona = {};

                vm.form_title = $translate.instant('Detalle Persona');
                if (vm.dtkey) {
                    datosBack.detalle(vm.urlbacksel, vm.dtkey)
                        .then(function (response) {
                            if (response.data.cod_res === 0) {
                                if ($state.current.url === '/dt') {
                                    $state.go('^');
                                }
                                ModalService.alertMessage('No se encontraron datos de la persona', 'Alerta', 'warning');
                            } else {
                                vm.persona = response.data.datosPersona;
                                vm.persona.stm_movimiento = response.data.clave;
                                if (vm.persona.cod_persona) {
                                    datosBack.detalle('imagenes', [[vm.persona.cod_persona]], false)
                                        .then(function (response) {
                                            vm.persona.img_persona = response.data.img_persona;
                                        })
                                        .catch(function () { });
                                }
                            }
                        })
                        .catch(function () { });

                }
            };

            vm.exportarPermanentes = function (tipo) {
                datosBack.export(vm.grillaPermanentes.getLoadOptions(), tipo).then(function (response) { }).catch(function (data) { });
            };

            vm.exportarTemporales = function (tipo) {
                datosBack.export(vm.grillaTemporales.getLoadOptions(), tipo).then(function (response) { }).catch(function (data) { });
            };
            vm.exportarRechazados = function (tipo) {
                datosBack.export(vm.grillaRechazados.getLoadOptions(), tipo).then(function (response) { }).catch(function (data) { });
            };

            $scope.$watch('movimientos.lastselected', function (newValue, oldValue) {
                if ($state.current.url === '/dt') {
                    vm.dt();
                }
            });

            vm.leerCredencial = function () {
                const cod_tema_origen = vm.test.id_lector;
                const ind_separa_facility_code = vm.lectores[cod_tema_origen].ind_separa_facility_code;
                const datos = {
                    cod_credencial: vm.test.cod_credencial,
                    cod_tema_origen: cod_tema_origen,
                    ind_separa_facility_code: ind_separa_facility_code,
                };
                return datosBack.save('', 'movimientos/test', datos, '')
                    .then(function () { })
                    .catch(function () { });
            };

        },
    ]
};
export default movimientosComponent;