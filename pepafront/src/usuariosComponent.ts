'use strict';
const usuariosComponent =
{
    template: require('./Pages/usuarios.html'),
    bindings: {
    },
    controllerAs: "usuarios",
    controller: ['localData', 'datosBack', '$scope', '$state','$timeout','$translate',
        function (localData, datosBack, $scope, $state, $timeout,$translate) {
            const vm = this;
            vm.form_title = $translate.instant('Usuarios');
            vm.usuario = {};
            vm.usuario_dt = {};
            vm.action_title = '';
            
            vm.hide = true;
            vm.active = 0;
            
            vm.action = '';

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

            datosBack.getData('usuarios/getAbilities', false, false).then(function (resultado) {
                vm.obj_permisos = resultado;
            });
            localData.getListaOU(false).then(function (resultado) {
                vm.obj_ou = resultado;
            });
            localData.getSectoresList(false, false).then(function (resultado) {
                vm.obj_sectores = resultado;
            });
            localData.getEsquemasList(false, false).then(function (resultado) {
                vm.obj_esquemas = resultado;
            });
            datosBack.getData('temas/getLectores/L', false, false).then(function (resultado) {
                vm.lectores = resultado;
            });

            vm.gridOptions = {
                excludeProperties: '__metadata',
                enablePaginationControls: false,
                enableRowSelection: true,
                enableRowHeaderSelection: true,
                multiSelect: false,
                enableFullRowSelection: true,
                useExternalSorting: true,
                data: [],
            };

            vm.typeaheadOnSelect = function ($item, $model, $label) {
                vm.usuario.cod_persona = $model.cod_persona || '';
            };

            vm.toggle = function (action) {
                vm.usuario = {};
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
                            vm.noteditable = false;
                            datosBack.detalle('usuarios', dtkey)
                                .then(function (response) {
                                    vm.usuario = response.data;
                                    vm.usuario.cod_usuario = '';
                                    vm.active = 2;
                                    vm.hide = false;
                                })
                                .catch(function () { });
                        }
                        break;
                    case 'edita':
                        if (dtkey && dtkey.length > 0) {
                            vm.action_title = $translate.instant('Modificación');
                            vm.noteditable = true;
                            datosBack.detalle('usuarios', dtkey)
                                .then(function (response) {
                                    vm.usuario = response.data;
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
                vm.usuario_dt = {};
                if (dtkey !== '') {
                    datosBack.detalle('usuarios', dtkey)
                        .then(function (response) {
                            vm.usuario_dt = response.data;
                        })
                        .catch(function () { });
                }
            };

            vm.ok = function () {
                return datosBack.save(vm.action, 'usuarios', vm.usuario, vm.grilla.getLastSelected())
                    .then(function () {
                        vm.grilla.fillGrid();
                        vm.usuario = {};
                        localData.resetListaOU();
                        localData.resetEsquemasList();
                        localData.resetSectoresList();
                        $scope.$emit('abmOU', {});
                        vm.active = 0;
                        vm.hide = true;
                        $state.go('.', { action: 'lista' });
                    })
                    .catch(function () { });
            };

            vm.getDatosPersona = function (persona) {
                if (persona !== '' && persona.length > 2) {
                    return datosBack.getData('usuarios/getPersona/' + persona, false, false).then(function (resultado) {
                        return resultado;
                    });
                }
            };

            // delete record
            vm.confirmDelete = function () {
                datosBack.delete('usuarios', vm.grilla.getLastSelected()).then(function (response) {
                    vm.grilla.fillGrid();
                    localData.resetListaOU();
                    localData.resetEsquemasList();
                    localData.resetSectoresList();
                }).catch(function (data) { });
            };

        },
    ]
};

export default usuariosComponent;