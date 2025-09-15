'use strict';
const personasComponent =
{
    template: require('./Pages/personas.html'),
    bindings: {
    },
    controllerAs: "personas",
    controller: ['localData', 'datosBack', '$state', 'captureMedia','$timeout','$translate',
        function (localData, datosBack, $state, captureMedia,$timeout,$translate) {

            const vm = this;
            vm.persona = {};
            vm.persona_dt = {};
            vm.action_title = '';
            
            
            vm.hide = true;
            vm.copy = false;
            vm.active = 0;
            
            vm.tipoSexo = localData.getTipoSexo();
            vm.tipoDocumento = localData.getTipoDocumento();
            vm.form_title = $translate.instant('Personas');

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
                captureMedia.init();
                if ($state.params.action === 'lista' || $state.params.action === 'agrega') {
                    vm.uiOnParamsChanged($state.params);
                } else {
                    $state.go('.', {
                        action: 'lista',
                    });
                }
                $timeout(function () { vm.grilla.fillGrid() },0);
            };

            const mystate = $state.$current;

            // hack para llamar a $onDestroy
            mystate.onExit = function () {
                vm.$onDestroy();
            };

            this.$onDestroy = function () {
            };

            // Set search items for starting and reset.
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

            // show modal form
            vm.toggle = function (action) {
                vm.persona = {};
                vm.action = action;
                const dtkey = vm.grilla.getdtKey();

                switch (action) {
                    case 'agrega':
                        vm.action_title = $translate.instant('Alta');
                        
                        vm.noteditable = false;
                        vm.hide = false;
                        vm.active = 2;
                        break;
                    case 'copia':
                        if (dtkey && dtkey.length > 0) {
                            vm.action_title = $translate.instant('Alta');
                            vm.copy = true;
                            datosBack.detalle('personas', dtkey, false)
                                .then(function (response) {
                                    vm.persona = response.data.pers;
                                    vm.persona.cod_persona = '';
                                    vm.active = 2;
                                    vm.hide = false;
                                })
                                .catch(function () { });

                            datosBack.detalle('imagenes/persona', dtkey, false, true)
                                .then(function (response) {
                                    vm.persona.img_persona = response.data;
                                })
                                .catch(function () { });

                            datosBack.detalle('imagenes/documento', dtkey, false, true)
                                .then(function (response) {
                                    vm.persona.img_documento = response.data;
                                })
                                .catch(function () { });



                            datosBack.detalle('aptosfisicos', dtkey, false)
                                .then(function (response) {
                                    vm.persona.img_apto_fisico = response.data.img_apto_fisico;
                                    vm.persona.fec_otorgamiento_af = response.data.fec_otorgamiento_af;
                                    vm.persona.fec_vencimiento_af = response.data.fec_vencimiento_af;
                                })
                                .catch(function () { });
                        }
                        break;
                    case 'edita':
                        if (dtkey && dtkey.length > 0) {
                            vm.action_title = $translate.instant('Modificación');
                            datosBack.detalle('personas', dtkey, false)
                                .then(function (response) {
                                    vm.persona = response.data.pers;
                                    vm.active = 2;
                                    vm.hide = false;
                                })
                                .catch(function () { });

                            datosBack.detalle('imagenes/persona', dtkey, false)
                                .then(function (response) {
                                    vm.persona.img_persona = response.data;
                                })
                                .catch(function () { });

                            datosBack.detalle('imagenes/documento', dtkey, false)
                                .then(function (response) {
                                    vm.persona.img_documento = response.data;
                                })
                                .catch(function () { });




                            datosBack.detalle('aptosfisicos', dtkey, false)
                                .then(function (response) {
                                    vm.persona.img_apto_fisico = response.data.img_apto_fisico;
                                    vm.persona.fec_otorgamiento_af = response.data.fec_otorgamiento_af;
                                    vm.persona.fec_vencimiento_af = response.data.fec_vencimiento_af;
                                })
                                .catch(function () { });
                        }
                        break;
                    default:
                        break;
                }
            };

            vm.consulta = function () {
                vm.persona_dt = {};
                const dtkey = vm.grilla.getdtKey();

                if (dtkey !== '') {
                    datosBack.detalle('personas', dtkey, false)
                        .then(function (response) {
                            vm.persona_dt = response.data.pers;
                        })
                        .catch(function () { });

                    datosBack.detalle('imagenes/persona', dtkey, false)
                        .then(function (response) {
                            vm.persona_dt.img_persona = response.data;
                        })
                        .catch(function () { });

                    datosBack.detalle('imagenes/documento', dtkey, false)
                        .then(function (response) {
                            vm.persona_dt.img_documento = response.data;
                        })
                        .catch(function () { });


                    datosBack.detalle('aptosfisicos', dtkey, false)
                        .then(function (response) {
                            vm.persona_dt.img_apto_fisico = response.data.img_apto_fisico;
                            vm.persona_dt.fec_otorgamiento_af = response.data.fec_otorgamiento_af;
                            vm.persona_dt.fec_vencimiento_af = response.data.fec_vencimiento_af;
                        })
                        .catch(function () { });
                }
            };

            vm.ok = function () {
                return datosBack.save(vm.action, 'personas', vm.persona, vm.grilla.getLastSelected()).then(function () {
                    vm.grilla.fillGrid();
                    vm.persona = {};
                    vm.active = 0;
                    vm.hide = true;
                    $state.go('.', { action: 'lista' });
                })
                    .catch(function () { });
            };

            // delete record
            vm.confirmDelete = function () {
                datosBack.delete('personas', vm.grilla.getLastSelected()).then(function (response) {
                    vm.grilla.fillGrid();
                }).catch(function (data) { });
            };
        },
    ]
};

export default personasComponent;
