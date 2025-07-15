'use strict';

import angular from "angular";
//import { isAbsolute } from "path";

const temasComponent =
{
    template: require('../Pages/temas.html'),
    bindings: {
    },
    controllerAs: "temas",
    controller: ['localData', 'datosBack', '$state', '$timeout', 'globalData', 'iconsLibSvc','$translate',
        function (localData, datosBack, $state, $timeout, globalData, iconsLibSvc,$translate) {

            const vm = this;
            vm.form_title = $translate.instant('Componentes');
            vm.tema = {};
            vm.tema_dt = {};
            vm.send = {};
            vm.sector = {};
            vm.tema.json_parametros = {};
            vm.action_title = '';

            vm.json_posicion_img_style = { 'position': 'absolute', 'font-size': '100px', 'color': 'black', 'left': '0px', 'top': '0px' };
            vm.active = 0;
            vm.tipoUsoList = localData.getTipoUso();
            vm.noteditable = false;
            vm.listaTemas = [];
            vm.fistreloadNR = true;


            vm.libreriaIconos = iconsLibSvc.getIconList();
            /*
            localData.getSectoresList(false, false).then(function (resultado) {
                vm.sectoresList = resultado;

            });
*/
            globalData.getSectoresTree(false, false).then(function (resultado) {
                vm.sectoresList = resultado;
            });

            globalData.getClases(false, false).then(function (resultado) {
                vm.clasesList = resultado;
            });



            vm.uiOnParamsChanged = function (newParams) {
                switch (newParams.action) {
                    case 'lista':
                        vm.active = 0;
                        break;
                    case 'temas_noreg':
                        vm.active = 6;
                        if (vm.fistreloadNR) {
                            vm.fistreloadNR = false;
                            $timeout(function () { vm.grillaNR.fillGrid() }, 0);
                        }
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
                    case 'copianr':
                        vm.active = 2;
                        vm.toggle(newParams.action);
                        break;
                    case 'sector':
                        vm.active = 3;
                        vm.consultaSector();
                        break;
                    case 'comunicacion':
                        vm.active = 4;
                        datosBack.getData('temas/getTemas/', false, false).then(function (resultado) {
                            vm.listaTemas = resultado;
                        });

                        break;
                    case 'temas_eventos':
                        vm.active = 5;
                        datosBack.getData('temas/getTemas/', false, false).then(function (resultado) {
                            vm.listaTemas = resultado;
                        });

                        if (!angular.isDefined(vm.send.valor))
                            vm.send = { "valor": '' };
                        break;

                    default:
                        $state.go('.', {
                            action: 'lista',
                        });
                        break;
                }
            };

            vm.$onInit = function () {
                if ($state.params.action === 'lista' || $state.params.action === 'agrega' || $state.params.action === 'sector' || $state.params.action === 'temas_noreg') {
                    vm.uiOnParamsChanged($state.params);
                    if ($state.params.action === 'lista')
                        $timeout(function () { vm.grilla.fillGrid() }, 0);
                    if ($state.params.action === 'temas_noreg') {
                        $timeout(function () { vm.grillaNR.fillGrid() }, 0);
                    }

                } else {
                    $state.go('.', {
                        action: 'lista',
                    });
                    $timeout(function () { vm.grilla.fillGrid() }, 0);
                }

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
            vm.gridOptionsNoRegistrados = {
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
                vm.tema = {};
                vm.action = action;
                const dtkey = vm.grilla.getdtKey();
                console.log("action", action);
                switch (action) {
                    case 'agrega':
                        vm.action_title = $translate.instant('Alta');

                        vm.noteditable = false;
                        vm.hide = false;
                        vm.tema.json_parametros = {};
                        vm.tema.json_posicion_img = {};
                        vm.tema.json_posicion_img.left = 0;
                        vm.tema.json_posicion_img.top = 0;
                        //            vm.tema.imagenes = { "img_plano": "" };
                        vm.noteditable = false;
                        break;
                    case 'copianr':
                        const dtkeynr = vm.grillaNR.getdtKey();
                        if (dtkeynr && dtkeynr.length > 0) {
                            vm.action_title = $translate.instant('Alta');
                            vm.noteditable = false;
                            vm.sector_imgdata = '';
                            vm.tema.cod_tema = dtkeynr[0][0];
                            vm.tema.json_parametros = {};
                            vm.tema.json_posicion_img = {};
                            vm.tema.json_posicion_img.left = 0;
                            vm.tema.json_posicion_img.top = 0;
                        }
                        break;
                    case 'copia':
                        if (dtkey && dtkey.length > 0) {
                            vm.action_title = $translate.instant('Alta');
                            vm.noteditable = false;
                            vm.sector_imgdata = '';
                            datosBack.detalle('temas', dtkey, false)
                                .then(function (response) {
                                    vm.tema = response.data;
                                    vm.tema.cod_tema = '';
                                    vm.tema.tipo_habilitaciones = response.data.tipo_habilitaciones;
                                    vm.hide = false;
                                    vm.tema.img_tema = "";

                                    vm.loadSectorImg(vm.tema.cod_sector);

                                })
                                .catch(function () { });


                        }
                        break;
                    case 'edita':
                        if (dtkey && dtkey.length > 0) {
                            vm.action_title = $translate.instant('Modificación');
                            vm.noteditable = true;
                            vm.sel_io = '';
                            vm.sector_imgdata = '';

                            datosBack.detalle('temas', dtkey, false)
                                .then(function (response) {
                                    vm.tema = response.data;
                                    vm.tema.tipo_habilitaciones = response.data.tipo_habilitaciones;
                                    vm.hide = false;
                                    vm.tema.img_tema = "";
                                    vm.tema.img_hash = btoa(JSON.stringify(dtkey));
                                    datosBack.getData('temasimgs/' + btoa(JSON.stringify(dtkey)) + "/" + vm.tema.img_hash, true, false, false)
                                        .then(function (response) {
                                            if (response.img_tema) {
                                                vm.tema.img_tema = response.img_tema;
                                            }
                                        })
                                        .catch(function () { });


                                    vm.loadSectorImg(vm.tema.cod_sector);
                                    vm.onChangeIconData(vm.tema);
                                })
                                .catch(function () { });

                        }
                        break;

                    default:
                        break;
                }
            };

            vm.loadSectorImg = (cod_sector: string) => {

                vm.sector_imgdata = "";
                if (!cod_sector) return;
                
                datosBack.getData('displaysucesos/sectordetalle/' + btoa(cod_sector), true, true).then(function (response: any) {
                    if (response.sectores) {
                        vm.sector = response;
                        if (vm.sector.img_hash != "") {
                            datosBack.getData('displaysucesos/sectorimgdata/' + btoa(cod_sector) + "/" + vm.sector.img_hash, true, false, true).then(function (response: any) {
                                vm.sector_imgdata = response;
                            }).catch(function (data: any) { });
                        }
                    }
                }).catch(function (data: any) { });
            }

            vm.consulta = function () {
                const dtkey = vm.grilla.getdtKey();
                vm.tema_dt = {};
                vm.sector_imgdata = '';
                //                vm.sector_imgiconos = [];
                if (dtkey !== '') {
                    datosBack.detalle('temas', dtkey, false)
                        .then(function (response) {
                            vm.tema_dt = response.data;
                            vm.tema_dt.tipo_habilitaciones = response.data.tipo_habilitaciones;
                            //                            vm.sector_imgiconos = vm.tema_dt.json_posicion_img;
                            vm.tema_dt.img_hash = btoa(JSON.stringify(dtkey));
                            vm.tema_dt.img_tema = "";
                            datosBack.getData('temasimgs/' + btoa(JSON.stringify(dtkey)) + "/" + vm.tema_dt.img_hash, true, false, false)
                                .then(function (response) {
                                    if (response.img_tema) {
                                        vm.tema_dt.img_tema = response.img_tema;
                                    }
                                })
                                .catch(function () { });

                            vm.loadSectorImg(vm.tema_dt.cod_sector);
                            vm.onChangeIconData(vm.tema_dt);
                        })
                        .catch(function () { });


                }
            };
            vm.consultaSector = function () {
                const dtkey = vm.grilla.getdtKey();
                vm.sector_imgdata = '';
                vm.sector_imgiconos = [];

                const cod_tema = dtkey[0];

                if (dtkey !== '') {
                    datosBack.detalle('temas', dtkey, false)
                        .then(function (response) {

                            datosBack.getData('displaysucesos/sectortemasdetalle/' + btoa(response.data.cod_sector), true, true)
                                .then(function (response) {
                                    vm.estados_temas_actuales = response.estados_temas_actuales;
                                    vm.sector_imgiconos = [];
                                    angular.forEach(response.estados_temas_actuales, function (tema_detalle: any, index: any) {

                                        if (tema_detalle.json_posicion_img) {
                                            tema_detalle.json_posicion_img.cod_tema = tema_detalle.cod_tema;
                                            tema_detalle.json_posicion_img.nom_tema = tema_detalle.nom_tema;
                                            /*
                                                                                        if (tema_detalle.cod_tema == cod_tema)
                                                                                            tema_detalle.json_posicion_img.event_class = "cs-icon-animate  csicon-" + tema_detalle.tipo_evento;
                                                                                        else
                                                                                            tema_detalle.json_posicion_img.event_class = "csicon-" + tema_detalle.tipo_evento;
                                            */
                                            tema_detalle.json_posicion_img.event_class = "csicon-" + tema_detalle.tipo_evento;
                                            tema_detalle.json_posicion_img.animate = (tema_detalle.cod_tema == cod_tema) ? "cs-icon-animate" : "";

                                            //                                            vm.sector_imgiconos.push(tema_detalle.json_posicion_img);
                                            vm.sector_imgiconos.push(angular.merge({ "left": 0, "top": 0, "rotacion_grados": 0, "rotaciony_grados": 0, "width": 100 }, tema_detalle.json_posicion_img));

                                        }

                                        return true;
                                    });
                                })
                                .catch(function () { });

                            vm.loadSectorImg(response.data.cod_sector);







                        })
                        .catch(function () { });
                }
            };

            vm.temaPopUp = (cod_tema) => {
                console.log('cod_tema', cod_tema);
            }

            vm.ok = function () {

                return datosBack.save(vm.action, 'temas', vm.tema, vm.grilla.getLastSelected())
                    .then(function () {
                        vm.grilla.fillGrid();
                        vm.tema = {};
                        vm.active = 0;
                        vm.hide = true;
                        $state.go('.', { action: 'lista' });
                    })
                    .catch(function () { });
            };


            // delete record
            vm.confirmDelete = function () {
                datosBack.delete('temas', vm.grilla.getLastSelected()).then(function (response) {
                    vm.grilla.fillGrid();
                }).catch(function (data) { });
            };
            vm.confirmDeleteNoReg = function () {
                datosBack.delete('temasnr', vm.grillaNR.getLastSelected()).then(function (response) {
                    vm.grillaNR.fillGrid();
                }).catch(function (data) { });
            };

            vm.sendCommand = function (msg: any) {
                return datosBack.save('', 'temas/sendCommand', msg, '')
                    .then(function () { })
                    .catch(function () { });
            };

            vm.runEvent = function (msg: any, valor: any) {
                if (valor != "") {
                    vm.send.valor = valor;
                }
                return datosBack.save('', 'temas/runEvent', msg, '')
                    .then(function () { })
                    .catch(function () { });
            };

            vm.onChangeTema = (tema) => {
                let dtkey = [[tema]];
                datosBack.detalle('temas', dtkey, false)
                    .then(function (response) {
                        const temafull = response.data;
                        vm.valor_omision = (temafull.json_parametros.valor_omision) ? temafull.json_parametros.valor_omision : "";
                        vm.val_NO = (temafull.json_parametros.val_NO) ? temafull.json_parametros.val_NO : "";
                        vm.val_FA = (temafull.json_parametros.val_FA) ? temafull.json_parametros.val_FA : "";
                        vm.val_AL = (temafull.json_parametros.val_AL) ? temafull.json_parametros.val_AL : "";
                    });
            }

            vm.onChangeIconData = (data) => {
                var tmpData: any = {};
                angular.copy(data, tmpData);

                if (tmpData.json_posicion_img.width == undefined)
                    tmpData.json_posicion_img.width = 100;
                if (tmpData.json_posicion_img.left == undefined)
                    tmpData.json_posicion_img.left = 0;
                if (tmpData.json_posicion_img.top == undefined)
                    tmpData.json_posicion_img.top = 0;
                if (tmpData.json_posicion_img.rotacion_grados == undefined)
                    tmpData.json_posicion_img.rotacion_grados = 0;
                if (tmpData.json_posicion_img.rotaciony_grados == undefined)
                    tmpData.json_posicion_img.rotaciony_grados = 0;

                vm.json_posicion_img_style = {
                    'position': 'absolute',
                    'font-size': tmpData.json_posicion_img.width + 'px',
                    'color': 'black',
                    'left': tmpData.json_posicion_img.left + 'px',
                    'top': tmpData.json_posicion_img.top + 'px',
                    'line-height': '0',
                    'transform': 'rotateZ(' + tmpData.json_posicion_img.rotacion_grados + 'deg) rotateY(' + tmpData.json_posicion_img.rotaciony_grados + 'deg)',
                    'touch-action': 'auto'
                };

            }
            vm.onMoveIcon = (event) => {
                vm.tema.json_posicion_img.left = event.left;
                vm.tema.json_posicion_img.top = event.top;
                vm.frmTemas.$setDirty();
            }
        },
    ]
};


export default temasComponent;