'use strict';

import { $q } from "@uirouter/core";
import angular from "angular";

const habilitacionesComponent =
{
    template: require('../Pages/habilitaciones.html'),
    bindings: {
    },
    controllerAs: "habilitaciones",
    controller: ['localData', 'globalData', 'datosBack', '$scope', 'Upload', '$timeout', 'cfg', '$state', 'captureMedia', '$window','$translate',
        function (localData, globalData, datosBack, $scope, Upload, $timeout, cfg, $state, captureMedia, $window,$translate) {
            const vm = this;
            vm.habilitacion = {};
            vm.habilitacion_dt = {};
            vm.action_title = '';
            
            
            vm.hide = true;
            vm.active = 0;
            vm.sectoresList = [];
            vm.esquemasList = [];
            vm.grupoCredList = [];
            
            vm.filtroextra = {};
            
            vm.lastdni = '';
            vm.habilitacion.sectoresSel = [];
            vm.tipoCredencial = localData.getTipoCredencial();
            vm.tipoHabilitacion = localData.getTipoHabilitacion();
            vm.tipoDocumento = localData.getTipoDocumento();
            vm.tipoSexo = localData.getTipoSexo();
            const path = 'habilitaciones';
            vm.ind_muestra_tipocred = false;
            vm.ind_muestra_tipohab = false;
            vm.ind_muestra_grupocred = false;
            vm.last_ou_contacto = '';
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
                        vm.habilita_camara = true;
                        break;
                    case 'copia':
                        vm.active = 2;
                        vm.toggle(newParams.action);
                        break;
                    case 'importa':
                        vm.active = 3;
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
                if ($state.params.action === 'lista' || $state.params.action === 'agrega' || $state.params.action === 'importa') {
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
                ValorCampo: 'P',
                operacion: '=',
            };
            localData.getGrupoCredList(false).then(function (resultado) {
                vm.grupoCredList = resultado;
            }).catch(function () { });

            localData.getListaOU(false).then(function (resultado) {
                vm.ouList = resultado;
            }).catch(function () { });

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
                vm.habilitacion = {};
                vm.action = action;
                const dtkey = vm.grilla.getdtKey();

                vm.lastdni = '';
                angular.forEach(vm.sectoresList, function (value, key) {
                    vm.sectoresList[key].ticked = false;
                });
                switch (action) {
                    case 'agrega':
                        vm.action_title = $translate.instant('Alta');
                        
                        vm.noteditable = false;
                        vm.hide = false;
                        // vm.active=2;
                        vm.lastdni = '';
                        vm.habilitacion.cod_ou_contacto = vm.last_ou_contacto;
                        break;
                    case 'copia':
                        if (dtkey && dtkey.length > 0) {
                            vm.action_title = $translate.instant('Alta');
                            fbuscaDatosCredPer(path, dtkey, 'add');
                        }
                        break;
                    case 'edita':
                        if (dtkey && dtkey.length > 0) {
                            vm.action_title = $translate.instant('Modificación');
                            vm.noteditable = true;
                            fbuscaDatosCredPer(path, dtkey, 'edit');
                        }
                        break;
                    case 'importa':
                        //                        vm.action_title = $translate.instant('Importa datos');
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
                vm.habilitacion.sectoresSel = '';
                vm.habilitacion.cod_esquema_acceso = '';
            });
            $scope.$on('abmOU', function (event, args) {
                localData.getListaOU(false).then(function (resultado) {
                    vm.ouList = resultado;
                }).catch(function () { });
            });

            $scope.$on('input', function (event, args) {

                if (vm.active === 2 && args.context.cod_tema == globalData.getCodTemaLector()) {
                    vm.habilitacion.cod_credencial_nueva = args.context.cod_credencial;
                    //                    var someElement = $window.document.getElementById('cod_ou_contacto');
                    //                    someElement.focus();

                    vm.fbuscaCredencial();
                }
            });

            vm.consulta = function () {
                vm.habilitacion_dt = {};
                const dtkey = vm.grilla.getdtKey();
                if (dtkey !== '') {
                    fbuscaDatosCredPer(path, dtkey, 'dt');
                }
            };

            vm.ok = function () {
                vm.habilitacion.cod_ou = globalData.getOULocal();
                vm.habilitacion.tipo_credencial = 'RFID';
                vm.habilitacion.tipo_habilitacion = 'P';
                vm.last_ou_contacto = vm.habilitacion.cod_ou_contacto;
                return datosBack.save(vm.action, path, vm.habilitacion, vm.grilla.getLastSelected())
                    .then(function () {
                        vm.habilitacion = {};
                        vm.grilla.fillGrid();
                        vm.active = 0;
                        vm.hide = true;
                        $state.go('.', { action: 'lista' });
                    })
                    .catch(function () { });
            };


            vm.importa = async function () {

                vm.habilitacionimp.cod_ou = globalData.getOULocal();
                vm.habilitacionimp.tipo_credencial = 'RFID';
                vm.habilitacionimp.tipo_habilitacion = 'P';
                vm.habilitacionimp.import_hash = "";

                await datosBack.upload(path + '/upload', vm.habilitacionimp.import_file)
                    .then(function (res) {
                        vm.habilitacionimp.import_hash = res.data.import_hash;
                    })
                    .catch(function (error) { 
                        return $q.reject(error);

                    });


                return datosBack.save(vm.action, path + 'importa', vm.habilitacionimp, { selected: "" })
                    .then(function () {
                        vm.habilitacionimp = {};
                    })
                    .catch(function () { });
            };

            // delete record
            vm.confirmDelete = function () {
                datosBack.delete(path, vm.grilla.getLastSelected()).then(function (response) {
                    vm.grilla.fillGrid();
                }).catch(function (data) { });
            };

            vm.typeaheadOnSelect = function ($item, $model, $label) {
                vm.habilitacion.cod_persona = $model.cod_persona || '';
                // vm.habilitacion.des_persona=$model.ape_persona+" "+$model.nom_persona || '';
                if (vm.habilitacion.cod_persona !== '') {
                    vm.habilitacion.busq_persona = '';
                    vm.fbuscaPersona();
                }
            };

            vm.typeaheadOnSelectContacto = function ($item, $model, $label) {
                vm.habilitacion.cod_persona_contacto = $model.cod_persona || '';
                vm.habilitacion.des_persona_contacto = $model.ape_persona + ' ' + $model.nom_persona || '';
                vm.habilitacion.obs_visitas_contacto = $model.obs_visitas || '';
                if ($model.cod_persona)
                    vm.habilitacion.busq_persona_contacto = '';
            };

            vm.getDatosPersona = function (persona) {
                if (persona !== '' && persona.length > 2) {
                    return datosBack.getData('usuarios/getPersona/' + persona, false, false).then(function (resultado) {
                        if (resultado.length < 1) {
                            vm.habilitacion.cod_persona = '';
                            vm.habilitacion.cod_tipo_doc = '';
                            vm.habilitacion.nro_documento = '';
                            vm.habilitacion.ape_persona = '';
                            vm.habilitacion.nom_persona = '';
                            vm.habilitacion.cod_sexo = '';
                            vm.habilitacion.email = '';
                            vm.habilitacion.img_persona = '';
                            vm.habilitacion.img_documento = '';
                            vm.habilitacion.obs_visitas = '';
                            vm.habilitacion.bus_persona_tmp = persona;
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
                            return resultado;
                        }
                    });
                }
            };

            vm.getDatosPersonaxOU = function (persona, cod_ou) {
                if (persona !== '' && cod_ou !== '') {
                    return datosBack.getData('usuarios/getPersonaxOU/' + persona + '/' + cod_ou, false, false).then(function (resultado) {
                        if (resultado.length < 1) {
                            vm.habilitacion.cod_persona_contacto = '';
                            vm.habilitacion.des_persona_contacto = '';
                            vm.habilitacion.obs_visitas_contacto = '';
                        } else {
                            return resultado;
                        }
                    });
                }
            };

            vm.limpiarContacto = function () {
                vm.habilitacion.busq_persona_contacto = '';
                vm.habilitacion.des_persona_contacto = '';
                vm.habilitacion.cod_persona_contacto = '';
            };

            const limpiarForm = function (model) {
                model.obs_tarjeta = '';
                model.cod_esquema_acceso = '';
                // model.cod_ou_contacto="";
                model.cod_persona_contacto = '';
                model.des_persona_contacto = '';
                model.sectoresSel = '';
                model.tipo_credencial = '';
                model.tipo_habilitacion = '';
                model.cod_grupo = '';
                model.cod_persona = '';
                model.ape_persona = '';
                model.cod_sexo = '';
                model.cod_tipo_doc = '';
                model.nro_documento = '';
                model.nom_persona = '';
                model.email = '';
                model.img_persona = '';
                model.img_documento = '';
                model.obs_visitas = '';
                model.vacredenciales = '';
                model.obs_habilitacion = '';
            };

            // Valida Credencial, si existe, devuelve los datos
            vm.fbuscaCredencial = function (action: string = '') {
                if (vm.action !== 'agrega') {
                    return;
                }

                let model = vm.habilitacion;
                if (action === 'dt') {
                    model = vm.habilitacion_dt;
                }
                if (!vm.noteditable && model.cod_credencial && model.cod_credencial !== '') {
                    model.obs_tarjeta = '';
                    limpiarForm(model);
                    const cod_credencial = model.cod_credencial;
                    datosBack.detalle('habilitaciones/valida/cod_credencial', [cod_credencial, 'P'])
                        .then(function (response) {
                            model.cod_credencial = response.data.cod_credencial;
                            model.cod_credencial_nueva = response.data.cod_credencial;
                            if (response.data.cod_esquema_acceso) {
                                model.cod_esquema_acceso = response.data.cod_esquema_acceso;
                            }
                            if (response.data.cod_ou_contacto !== '') {
                                model.cod_ou_contacto = response.data.cod_ou_contacto;
                            }
                            model.cod_persona_contacto = response.data.cod_persona_contacto;
                            model.des_persona_contacto = response.data.des_persona_contacto;
                            if (response.data.sectoresSel && response.data.sectoresSel.length > 0) {
                                model.sectoresSel = response.data.sectoresSel;
                            }
                            model.tipo_credencial = response.data.tipo_credencial;
                            model.tipo_habilitacion = response.data.tipo_habilitacion;
                            model.cod_grupo = response.data.cod_grupo;
                            model.obs_habilitacion = response.data.obs_habilitacion;
                            if (response.data.cod_persona !== '') {
                                model.obs_tarjeta = 'Tarjeta en uso. ';
                            }
                            if (response.data.cod_persona !== '' && model.cod_persona !== response.data.cod_persona) {
                                model.obs_tarjeta = 'Tarjeta ya asignada. ';
                                model.cod_persona = response.data.cod_persona;
                                vm.fbuscaPersona(true, action);
                            }
                            if (response.data.ref_credencial) {
                                model.obs_tarjeta += ' Serie: ' + response.data.ref_credencial;
                            }
                        }).catch(function () {
                            model.cod_credencial = '';
                            model.cod_credencial_nueva = '';
                            model.obs_tarjeta = '';
                        });
                }
            };

            // Valida Persona, si existe, devuelve los datos
            vm.fbuscaPersona = function (ind_credencial = false, action: string = "") {
                let model = vm.habilitacion;
                if (action === 'dt') {
                    model = vm.habilitacion_dt;
                }

                if (model.cod_persona === 'DNI' || model.cod_persona === 'PAS') {
                    model.ape_persona = '';
                    model.cod_sexo = '';
                    model.cod_tipo_doc = model.cod_persona;
                    model.nro_documento = model.bus_persona_tmp;
                    model.nro_documento_ant = model.bus_persona_tmp;
                    model.nom_persona = '';
                    model.email = '';
                    model.img_persona = '';
                    model.img_documento = '';
                    model.cod_persona = '';
                    model.bus_persona_tmp = '';
                    return;
                }

                if (model.cod_persona === '') {
                    return;
                }
                // if (model.nro_documento === vm.lastdni)
                //  return;
                datosBack.detalle('habilitaciones/valida/cod_persona', [model.cod_persona, 'P'])
                    .then(function (response) {
                        model.ape_persona = response.data.datosPersona.ape_persona;
                        model.cod_sexo = response.data.datosPersona.cod_sexo;
                        model.cod_tipo_doc = response.data.datosPersona.cod_tipo_doc;
                        model.nro_documento = response.data.datosPersona.nro_documento;
                        model.nom_persona = response.data.datosPersona.nom_persona;
                        model.email = response.data.datosPersona.email;
                        model.img_persona = response.data.datosPersona.img_persona;
                        model.img_documento = response.data.datosPersona.img_documento;
                        model.obs_visitas = response.data.datosPersona.obs_visitas;
                        model.obs_habilitacion = response.data.datosPersona.obs_habilitacion;
                        model.nro_documento_ant = response.data.datosPersona.nro_documento;
                        if (!ind_credencial) {
                            if (response.data.datosCred.length > 1) {
                                // ModalService.alertMessage('La persona tiene mas de una credencial','Error','danger',response);
                                model.vacredenciales = response.data.datosCred;
                            } else {
                                if (model.cod_credencial === '') {
                                    model.cod_credencial = response.data.datosCred[0].cod_credencial;
                                    model.cod_credencial = response.data.datosCred[0].cod_credencial;
                                    vm.fbuscaCredencial(action);
                                }
                            }
                        }

                        datosBack.detalle('imagenes/persona', [[model.cod_persona]], false, true)
                            .then(function (response) {
                                model.img_persona = response.data;
                            })
                            .catch(function () { });

                        datosBack.detalle('imagenes/documento', [[model.cod_persona]], false, true)
                            .then(function (response) {
                                model.img_documento = response.data;
                            })
                            .catch(function () { });

                        datosBack.detalle('aptosfisicos', [[model.cod_persona]], false)
                            .then(function (response) {
                                model.img_apto_fisico = response.data.img_apto_fisico;
                                model.fec_otorgamiento_af = response.data.fec_otorgamiento_af;
                                model.fec_vencimiento_af = response.data.fec_vencimiento_af;
                            })
                            .catch(function () { });
                    })
                    .catch(function () { });
            };

            const fbuscaDatosCredPer = function (path, clave, action = 'edit') {
                let model = vm.habilitacion;
                if (action === 'dt') {
                    model = vm.habilitacion_dt;
                }
                datosBack.detalle(path, clave)
                    .then(function (response) {
                        model.cod_credencial = response.data.cod_credencial;
                        model.cod_credencial_nueva = response.data.cod_credencial_nueva;
                        model.cod_esquema_acceso = response.data.cod_esquema_acceso;
                        model.cod_grupo = response.data.cod_grupo;
                        model.cod_ou_contacto = response.data.cod_ou_contacto;
                        model.cod_persona_contacto = response.data.cod_persona_contacto;
                        model.des_persona_contacto = response.data.des_persona_contacto;
                        model.sectoresSel = response.data.sectoresSel;
                        model.tipo_credencial = response.data.tipo_credencial;
                        model.tipo_habilitacion = response.data.tipo_habilitacion;
                        model.obs_habilitacion = response.data.obs_habilitacion;
                        if (response.data.cod_persona !== '') {
                            model.cod_persona = response.data.cod_persona;
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
                vm.habilitacion = {};
            };

        },
    ]
};

export default habilitacionesComponent;