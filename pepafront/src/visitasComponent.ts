'use strict';

import angular from "angular";

const visitasComponent =
{
    template: require('../Pages/visitas.html'),
    bindings: {
    },
    controllerAs: "visitas",
    controller: ['localData', 'globalData', 'datosBack', '$scope', 'Upload', '$timeout', 'cfg','$translate',
        '$state', 'ModalService', 'captureMedia', '$window',
        function (localData, globalData, datosBack, $scope, Upload, $timeout, cfg,$translate,
            $state, ModalService, captureMedia, $window) {

            const vm = this;
            vm.visita = {};
            vm.visita_dt = {};
            vm.action_title = '';
            
            vm.hide = true;
            vm.active = 0;
            vm.sectoresList = [];
            vm.esquemasList = [];
            vm.grupoCredList = [];
            
            
            vm.filtroextra = {};
            
            vm.visita.sectoresSel = [];
            vm.tipoCredencial = localData.getTipoCredencial();
            vm.tipoHabilitacion = localData.getTipoHabilitacion();
            vm.tipoDocumento = localData.getTipoDocumento();
            vm.tipoSexo = localData.getTipoSexo();
            vm.path = 'visitas';
            let sector_default = '';
            let esquema_default = '';
            vm.ind_muestra_tipocred = false;
            vm.ind_muestra_tipohab = false;
            vm.ind_muestra_grupocred = false;
            vm.last_ou_contacto = '';
            vm.ind_visita_simplificada = globalData.getVisitaSimplificada();
            vm.showbusqueda = true;
            vm.showcontacto = false;
            vm.ind_nueva_persona = false;
            vm.habilita_camara = false;

            vm.uiOnParamsChanged = function (newParams) {
                vm.habilita_camara = false;
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

            vm.filtroextra = {
                NombreCampo: 'tipo_habilitacion',
                operacion: '=',
                ValorCampo: 'T',
            };

            localData.getGrupoCredList(false).then(function (resultado) {
                vm.grupoCredList = resultado;
            }).catch(function () { });

            localData.getListaOU(false).then(function (resultado) {
                vm.ouList = resultado;
            }).catch(function () { });

            datosBack.getData('habilitaciones/defaults', false, false).then(function (resultado) {
                sector_default = resultado.sector_default;
                esquema_default = resultado.esquema_default;
            });

            vm.refreshLists = function () {
                const cod_ou = globalData.getOULocal();
                const xusuario = true;
                localData.getEsquemasList(cod_ou, xusuario).then(function (resultado) {
                    vm.esquemasList = resultado;
                }).catch(function () { });

                // SECTORES
                globalData.getSectores().then(function (response) {
                    const sectoresList = response;
                    localData.getSectoresList(cod_ou, xusuario).then(function (resultado) {
                        const sectoresxOu = resultado;
                        angular.forEach(sectoresList, function (valueList, indexList) {
                            let existe = false;
                            angular.forEach(sectoresxOu, function (valxOu, ind) {
                                if (valueList.cod_sector === valxOu.cod_sector) {
                                    existe = true;
                                }
                            });
                            if (!existe) {
                                sectoresList[indexList].estado = 'inactivo';
                            } else {
                                sectoresList[indexList].estado = 'activo';
                            }
                        });
                        vm.sectoresList = sectoresList;
                    }).catch(function () { });
                }).catch(function () { });
            };

            vm.refreshLists();

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
                vm.visita = {};
                vm.action = action;
                const dtkey = vm.grilla.getdtKey();

                angular.forEach(vm.sectoresList, function (value, key) {
                    vm.sectoresList[key].ticked = false;
                });
                switch (action) {
                    case 'agrega':
                        vm.action_title = $translate.instant('Alta');
                        vm.noteditable = false;
                        vm.hide = false;
                        vm.visita.cod_ou_contacto = vm.last_ou_contacto;
                        if (sector_default !== '') {
                            angular.forEach(vm.sectoresList, function (value, key) {
                                if (value.cod_sector === sector_default) {
                                    vm.visita.sectoresSel = [sector_default];
                                }
                            });
                        }
                        if (esquema_default !== '') {
                            angular.forEach(vm.esquemasList, function (value, key) {
                                if (value.cod_esquema_acceso === esquema_default) {
                                    vm.visita.cod_esquema_acceso = value.cod_esquema_acceso;
                                }
                            });
                        }
                        break;
                    case 'copia':
                        if (dtkey && dtkey.length > 0) {
                            vm.action_title = $translate.instant('Alta');
                            fbuscaDatosCredPer(vm.path, dtkey, 'add');
                        }
                        break;
                    case 'edita':
                        if (dtkey && dtkey.length > 0) {
                            vm.action_title = $translate.instant('Modificación');
                            vm.noteditable = true;
                            fbuscaDatosCredPer(vm.path, dtkey, 'edit');
                        }
                        break;
                    default:
                        break;
                }
            };

            vm.exportar = function (tipo) {
                datosBack.export(vm.grilla.getLoadOptions(), tipo).then(function (response) { }).catch(function (data) { });
            };

            $scope.$on('cambiaSelOU', function (event, args) {
                vm.refreshLists();
                vm.visita.sectoresSel = '';
                vm.visita.cod_esquema_acceso = '';
            });
            $scope.$on('abmOU', function (event, args) {
                localData.getListaOU(false).then(function (resultado) {
                    vm.ouList = resultado;
                }).catch(function () { });
            });

            $scope.$on('input', function (event, args) {
                if (vm.active === 2 && args.context.cod_tema == globalData.getCodTemaLector()) {
                    vm.visita.cod_credencial = args.context.cod_credencial;
                    var someElement = $window.document.getElementById('cod_ou_contacto');
                    someElement.focus();
                    vm.fbuscaCredencial();
                }
            });

            vm.consulta = function () {
                const dtkey = vm.grilla.getdtKey();
                vm.visita_dt = {};
                if (dtkey !== '') {
                    fbuscaDatosCredPer(vm.path, dtkey, 'dt');
                }
            };

            vm.ok = function () {
                vm.visita.cod_ou = globalData.getOULocal();
                vm.visita.tipo_credencial = 'RFID';
                vm.visita.tipo_habilitacion = 'T';
                vm.last_ou_contacto = '';
                return datosBack.save(vm.action, vm.path, vm.visita, vm.grilla.getLastSelected())
                    .then(function () {
                        vm.visita = {};
                        vm.grilla.fillGrid();
                        vm.active = 0;
                        vm.hide = true;
                        $state.go('.', {
                            action: 'lista',
                        });
                    })
                    .catch(function () { });
            };

            // delete record
            vm.confirmDelete = function () {
                datosBack.delete(vm.path, vm.grilla.getLastSelected()).then(function (response) {
                    vm.grilla.fillGrid();
                }).catch(function (data) { });
            };

            vm.typeaheadOnSelect = function ($item, $model, $label) {
                vm.visita.cod_persona = $model.cod_persona || '';
                if (vm.visita.cod_persona !== '') {
                    vm.fbuscaPersona();
                    vm.visita.busq_persona = '';
                }
            };

            vm.typeaheadOnSelectContacto = function ($item, $model, $label) {
                vm.visita.cod_persona_contacto = $model.cod_persona || '';
                vm.visita.des_persona_contacto = $model.ape_persona + ' ' + $model.nom_persona || '';
                vm.visita.obs_visitas_contacto = $model.obs_visitas || '';
                if ($model.cod_persona) {
                    vm.visita.busq_persona_contacto = '';
                    vm.showbusqueda = false;
                    vm.showcontacto = true;
                }
            };

            vm.getDatosPersona = function (persona) {
                if (persona !== '' && persona.length > 2) {
                    return datosBack.getData('usuarios/getPersona/' + persona, false, false).then(function (resultado) {
                        if (resultado.length < 1) {
                            vm.ind_nueva_persona = true;
                            vm.visita.cod_persona = '';
                            // vm.visita.cod_tipo_doc = "";
                            vm.visita.nro_documento = '';
                            vm.visita.ape_persona = '';
                            vm.visita.nom_persona = '';
                            vm.visita.cod_sexo = '';
                            vm.visita.email = '';
                            vm.visita.img_persona = '';
                            vm.visita.img_documento = '';

                            vm.visita.bus_persona_tmp = persona;
                            return [{
                                des_persona: 'Nuevo DNI ' + persona,
                                cod_persona: 'DNI',
                                nro_documento: persona,
                                ape_persona: '',
                                nom_persona: '',
                                cod_tipo_documento: 'DNI',
                            }, {
                                des_persona: 'Nuevo PAS ' + persona,
                                cod_persona: 'PAS',
                                nro_documento: persona,
                                ape_persona: '',
                                nom_persona: '',
                                cod_tipo_documento: 'PAS',
                            }];

                        } else {
                            vm.ind_nueva_persona = false;
                            //                return [{ des_persona: "Nueva visita", cod_persona: "", nro_documento: persona, ape_persona: "", nom_persona: "" }];

                            return resultado;
                        }
                    });
                }
            };

            vm.getDatosPersonaxOU = function (persona, cod_ou) {
                if (persona !== '' && cod_ou !== '') {
                    return datosBack.getData('usuarios/getPersonaxOU/' + persona + '/' + cod_ou, false, false).then(function (resultado) {
                        if (resultado.length < 1) {
                            //vm.visita.busq_persona_contacto = '';
                            vm.visita.cod_persona_contacto = '';
                            vm.visita.des_persona_contacto = '';
                            vm.visita.obs_visitas_contacto = '';
                        } else {
                            return resultado;
                        }
                    });
                }
            };

            vm.limpiarContacto = function () {
                vm.visita.busq_persona_contacto = '';
                vm.visita.des_persona_contacto = '';
                vm.visita.cod_persona_contacto = '';
                vm.visita.obs_visitas_contacto = '';
            };

            // Valida Credencial, si existe, devuelve los datos
            vm.fbuscaCredencial = function (action) {
                let ctrl = vm.visita;
                if (action === 'dt') {
                    ctrl = vm.visita_dt;
                }
                if (!vm.noteditable && ctrl.cod_credencial && ctrl.cod_credencial !== '') {
                    const cod_credencial = ctrl.cod_credencial;
                    datosBack.detalle('habilitaciones/valida/cod_credencial', [cod_credencial, 'T'])
                        .then(function (response) {
                            ctrl.obs_tarjeta = '';
                            // ctrl.busq_persona_contacto = "";
                            // ctrl.obs_visitas_contacto = "";
                            ctrl.cod_credencial = response.data.cod_credencial;
                            if (response.data.cod_esquema_acceso) {
                                ctrl.cod_esquema_acceso = response.data.cod_esquema_acceso;
                            }
                            if (response.data.cod_ou_contacto !== '') {
                                ctrl.cod_ou_contacto = response.data.cod_ou_contacto;
                            }
                            ctrl.cod_persona_contacto = response.data.cod_persona_contacto;
                            ctrl.des_persona_contacto = response.data.des_persona_contacto;
                            if (response.data.sectoresSel && response.data.sectoresSel.length > 0) {
                                ctrl.sectoresSel = response.data.sectoresSel;
                            }
                            ctrl.tipo_credencial = response.data.tipo_credencial;
                            ctrl.tipo_habilitacion = response.data.tipo_habilitacion;
                            ctrl.obs_habilitacion = response.data.obs_habilitacion;
                            ctrl.cod_grupo = response.data.cod_grupo;
                            if (!response.data.stockcred) {
                                ctrl.obs_tarjeta = $translate.instant('Tarjeta inexistente en Stock.');
                            }
                            if (response.data.cod_persona !== '' && ctrl.cod_persona !== response.data.cod_persona) {
                                ctrl.cod_persona = response.data.cod_persona;
                                ctrl.obs_tarjeta = $translate.instant('Tarjeta ya asignada.');
                                // readonly datos persona
                                // vm.noteditable = true;
                                vm.fbuscaPersona(true);
                            }
                            if (response.data.ref_credencial) {
                                ctrl.obs_tarjeta += ' '+$translate.instant('Serie:')+' ' + response.data.ref_credencial;
                            }
                        }).catch(function () {
                            ctrl.cod_credencial = '';
                            ctrl.obs_tarjeta = '';
                        });
                }
            };

            // Valida Persona, si existe, devuelve los datos
            vm.fbuscaPersona = function (ind_credencial = false, action: string = '') {
                let ctrl = vm.visita;
                if (action === 'dt') {
                    ctrl = vm.visita_dt;
                }
                if (ctrl.cod_persona === 'DNI' || ctrl.cod_persona === 'PAS') {
                    ctrl.ape_persona = '';
                    ctrl.cod_sexo = '';
                    ctrl.cod_tipo_doc = ctrl.cod_persona;
                    ctrl.nro_documento = ctrl.bus_persona_tmp;
                    ctrl.nro_documento_ant = ctrl.bus_persona_tmp;
                    ctrl.nom_persona = '';
                    ctrl.email = '';
                    ctrl.img_persona = '';
                    ctrl.img_documento = '';
                    ctrl.cod_persona = '';
                    ctrl.bus_persona_tmp = '';
                    return;
                }

                if (ctrl.cod_persona === '') {
                    return;
                }
                datosBack.detalle('habilitaciones/valida/cod_persona', [ctrl.cod_persona, 'T'])
                    .then(function (response) {
                        // vm.visita.des_persona = response.data.datosPersona.des_persona;
                        // ctrl.busq_persona_contacto = "";
                        // ctrl.busq_persona = "";
                        ctrl.ape_persona = response.data.datosPersona.ape_persona;
                        ctrl.cod_sexo = response.data.datosPersona.cod_sexo;
                        ctrl.cod_tipo_doc = response.data.datosPersona.cod_tipo_doc;
                        ctrl.nro_documento = response.data.datosPersona.nro_documento;
                        ctrl.nom_persona = response.data.datosPersona.nom_persona;
                        ctrl.email = response.data.datosPersona.email;
                        ctrl.img_persona = response.data.datosPersona.img_persona;

                           
                        ctrl.img_documento = response.data.datosPersona.img_documento;
                        ctrl.nro_documento_ant = response.data.datosPersona.nro_documento;
                        ctrl.obs_habilitacion = response.data.datosPersona.obs_habilitacion;

                        if (response.data.vccredenciales !== '') {
                            ModalService.alertMessage(response.data.vccredenciales, 'Alerta', 'warning');
                        }

                        if (!ind_credencial) {
                            if (response.data.datosCred.length > 1) {
                                ModalService.alertMessage($translate.instant('La persona tiene mas de una credencial'),'Error','danger',response);
                                ctrl.vacredenciales = response.data.datosCred;
                            } else {
                                if (ctrl.cod_credencial === '') {
                                    ctrl.cod_credencial = response.data.datosCred[0].cod_credencial;
                                    vm.fbuscaCredencial(action);
                                }
                            }
                        }
                        console.log('busca imgs');
                        datosBack.detalle('imagenes/persona', [[ctrl.cod_persona]], false, true)
                            .then(function (response) {
                                ctrl.img_persona = response.data;
                                if (response.data.length < 200 && vm.active == 2)
                                    vm.habilita_camara = true;

                            })
                            .catch(function () { });
                        datosBack.detalle('imagenes/documento', [[ctrl.cod_persona]], false, true)
                            .then(function (response) {
                                ctrl.img_documento = response.data;
                            })
                            .catch(function () { });



                    })
                    .catch(function () { });
            };

            const fbuscaDatosCredPer = function (path, clave, action = 'edit') {
                let ctrl = vm.visita;
                if (action === 'dt') {
                    ctrl = vm.visita_dt;
                }
                datosBack.detalle(path, clave)
                    .then(function (response) {
                        ctrl.cod_credencial = response.data.cod_credencial;
                        ctrl.cod_esquema_acceso = response.data.cod_esquema_acceso;
                        ctrl.cod_grupo = response.data.cod_grupo;
                        ctrl.cod_ou_contacto = response.data.cod_ou_contacto;
                        ctrl.cod_persona_contacto = response.data.cod_persona_contacto;
                        ctrl.des_persona_contacto = response.data.des_persona_contacto;
                        // ctrl.sectoresSel = filtraSectores(response.data.sectoresSel,vm.sectoresList);
                        ctrl.sectoresSel = response.data.sectoresSel;
                        ctrl.tipo_credencial = response.data.tipo_credencial;
                        ctrl.tipo_habilitacion = response.data.tipo_habilitacion;
                        ctrl.obs_habilitacion = response.data.obs_habilitacion;
                        if (response.data.cod_persona !== '') {
                            ctrl.cod_persona = response.data.cod_persona;
                            vm.fbuscaPersona(false, action);
                            if (action !== 'dt') {
                                vm.active = 2;
                                vm.hide = false;
                            }
                        }
                    })
                    .catch(function () { });
            };

            vm.uploadFiles = function (file, errFiles) {
                vm.f = file;
                vm.errFile = errFiles && errFiles[0];
                if (file) {
                    // file.type.substring(0,file.type.lastIndexOf("/"))=="image"
                    file.upload = Upload.upload({
                        url: cfg.webApiBaseUrl + 'habilitaciones/upload',
                        data: {
                            file,
                        },
                    });

                    file.upload.then(function (response) {
                        $timeout(function () {
                            file.result = response.data;
                        });
                    }, function (response) {
                        if (response.status > 0) {
                            vm.errorMsg = response.status + ': ' + response.data.error;
                        }
                    }, function (evt) {
                        file.progress = Math.min(100, Math.round(100.0 *
                            evt.loaded / evt.total));
                        vm.errorMsg = '';
                    }).catch(function (response) {
                        if (response.status > 0) {
                            vm.errorMsg = response.status + ': ' + response.data.error;
                        }
                    });
                }
            };

            vm.cancela = function () {
                vm.visita = {};
            };

        },
    ]
};

export default visitasComponent;