'use strict';
import angular from "angular";

const sectorDetalleComponent = {
    template: require('../Pages/Templates/sector_detalle.html'),
    bindings: {
        cod_tema: '<codTema',
        cod_sector: '<codSector',
        onClose: '&'
    },
    controllerAs: "ctrl",
    controller: ['$element', '$scope', '$window', '$timeout', 'datosBack', '$attrs', '$sce', '$uibModal', 'iconsLibSvc','$filter','videoSvc', function ($element, $scope, $window, $timeout, datosBack, $attrs, $sce, $uibModal, iconsLibSvc, $filter,videoSvc) {
        const vm = this;

        vm.ind_video = false;
        vm.video_url = "";
        vm.ind_detalle_temas = false;
        vm.ind_documentos = false;
        vm.imgdata = "";
        vm.sectorTree = [];
        vm.selected = { cod_sector: vm.cod_sector, ind_alarma: false, ind_alarmatec: false, ind_prealarma: false, ind_falla: false };
        vm.icons_imagen = [];
        vm.documentos_url = "";
        vm.nom_sector = "";
        vm.btn_reset = false;

        vm.verDocs = () => {
            //                console.log("documentos " + vm.documentos_url);
            vm.ind_detalle_temas = false;
            vm.ind_video = false;
            videoSvc.stop()
            vm.video_url = '';
            vm.ind_documentos = true;
            $scope.$applyAsync();
        };

        vm.showVideo = (url) => {
            vm.ind_detalle_temas = false;
            vm.ind_documentos = false;
            vm.video_url = url;
            vm.ind_video = true;
            $scope.$applyAsync();

            setTimeout(() => {
                videoSvc.start("dashVideo",url)
                
            }, 250);

        };

        vm.showImagenSector = () => {
            vm.ind_detalle_temas = false;
            vm.ind_video = false;
            vm.video_url = '';
            vm.ind_documentos = false;
            vm.imgdata = vm.imgdata_sector;
            vm.showSectorIcons();
            vm.video_url = "";
            videoSvc.stop()
            //            $scope.$applyAsync();
        }

        vm.sendCMD = (cod_tema:string,cmd:string) => {
            return datosBack.save('proceso', 'displaysucesos/cmdcentral', { cod_tema: cod_tema,cmd:cmd }, '')
                .then(function () {
                    vm.btn_reset = false;
                
                })
                .catch(function () { });
        }


        vm.showImagenTema = (cod_tema) => {
            vm.video_url = "";
            vm.selected.img_hash_tema = btoa(cod_tema);
            datosBack.getData('displaysucesos/temaimgdata/' + btoa(cod_tema) + "/" + vm.selected.img_hash_tema, true, false, false).then(function (response: any) {
                vm.ind_detalle_temas = false;
                vm.ind_video = false;
                videoSvc.stop()
                vm.video_url = '';
                vm.ind_documentos = false;

                vm.icons_imagen = [];
                vm.imgdata_tema = response;
                vm.imgdata = vm.imgdata_tema;
            }).catch(function (data: any) { });
        }

        vm.showSectorIcons = () => {

            vm.video_url = "";
            datosBack.getData('displaysucesos/sectortemasdetalle/' + btoa(vm.cod_sector), true, true).then(function (response) {
                vm.estados_temas_actuales = response.estados_temas_actuales;
                vm.icons_imagen = [];
                angular.forEach(vm.estados_temas_actuales, function (tema_detalle: any, index: any) {

                    if (tema_detalle.tipo_evento == "")
                        tema_detalle.tipo_evento = "NO";

                    if (tema_detalle.json_posicion_img) {
                        tema_detalle.json_posicion_img.cod_tema = tema_detalle.cod_tema;
                        tema_detalle.json_posicion_img.nom_tema = tema_detalle.nom_tema;

                        if (tema_detalle.cod_tema == vm.cod_tema)
                            tema_detalle.json_posicion_img.event_class = "onoff-" + tema_detalle.tipo_evento;
                        else
                            tema_detalle.json_posicion_img.event_class = "csicon-" + tema_detalle.tipo_evento;

                        vm.icons_imagen.push(angular.merge({ "left": 0, "top": 0, "rotacion_grados": 0, "rotaciony_grados": 0, "width": 100 }, tema_detalle.json_posicion_img));
                    }

                    if (tema_detalle.cod_tema == vm.cod_tema) {
                        vm.estados_temas_actuales.unshift(vm.estados_temas_actuales[index]);
                        vm.estados_temas_actuales.splice(index + 1, 1);

                    }


                    return true;
                });

                //                    console.log('vm.estados_temas_actuales', vm.estados_temas_actuales);
                //                    console.log('vm.icons_imagen', vm.icons_imagen);

            }).catch(function (data) {

            });;
        }

        vm.verEstadosIOs = (cod_sector: string) => {
            vm.ind_video = false;
            videoSvc.stop()
            vm.video_url = '';
            vm.ind_documentos = false;
            vm.showSectorIcons();
            vm.ind_detalle_temas = true;
            $scope.$applyAsync();

        }
        vm.temaPopUp = (cod_tema: string) => {
            //                var parentElem = parentSelector ? 
            //                angular.element($document[0].querySelector('.modal-demo ' + parentSelector)) : undefined;

            datosBack.getData('displaysucesos/temadetalle/' + btoa(cod_tema), true, false).then(function (response: any) {

                var modalInstance = $uibModal.open({
                    animation: true,
                    ariaLabelledBy: 'modal-title',
                    ariaDescribedBy: 'modal-body',
                    templateUrl: 'detalleModalContent.html',
                    controller: function () {
                        this.cod_tema = response.cod_tema;
                        this.des_ubicacion = response.des_ubicacion;
                        this.des_observaciones = response.des_observaciones;
                        this.nom_tema = response.nom_tema;
                        this.stm_evento = response.stm_evento;
                        this.stm_evento_ultimo = response.stm_evento_ultimo;
                        this.stm_evento_prueba = response.stm_evento_prueba;
                        this.json_posicion_img = response.json_posicion_img;
                        this.bus_id = response.bus_id;
                        this.btn_reset_disabled = false;
                        this.ok = () => {
                            modalInstance.close();
                        };
                        this.showImagenTema = () => {
                            vm.showImagenTema(this.cod_tema);
                            this.ok();
                        }

                        this.sendReset = () => { 
                            vm.sendCMD(this.cod_tema,"reset");
                            this.btn_reset_disabled = true;
                        }
                    },
                    controllerAs: 'ctrl',
                    //                size: size,
                    //                appendTo: $element,
                    resolve: {
                        //                items: function () {
                        //                  return $ctrl.items;
                        //                }
                    }
                });







            }).catch();





        }

        vm.updateSelectedTema = (cod_tema: string) => {
            if (!cod_tema)
                return;
            datosBack.getData('displaysucesos/temadetalle/' + btoa(cod_tema), true, false).then(function (response: any) {
                vm.tema = response;
                vm.tema.json_posicion_img.event_class = " onoff-" + vm.tema.tipo_evento_prioridad;
                vm.tema.json_posicion_img.cod_tema = vm.tema.cod_tema;
                vm.tema.json_posicion_img.nom_tema = vm.tema.nom_tema;

                angular.forEach(vm.icons_imagen, function (tema_detalle: any, index: any) {
                    if (tema_detalle.cod_tema == cod_tema)
                        vm.icons_imagen.splice(index, 1);
                });

                vm.icons_imagen.push(vm.tema.json_posicion_img);

                //                    console.log('vm.icons_imagen', vm.icons_imagen);
            }).catch(function (data: any) {
            });
        }

        vm.showSector = (cod_sector: string, first: boolean) => {
            vm.imgdata = "";
            vm.icons_imagen = [];
            if (vm.cod_tema)
                vm.updateSelectedTema(vm.cod_tema);
            vm.selected.cod_sector = "";
            datosBack.getData('displaysucesos/sectordetalle/' + btoa(cod_sector), true, true).then(function (response: any) {
                if (response.sectores) {
                    vm.selected = response;

                    if (first)
                        vm.sectorTree = response.sectores;
                    vm.nom_sector = $filter('filter')(vm.sectorTree, { 'cod_tema_sector': cod_sector });
                    if (vm.nom_sector[0])
                        vm.nom_sector = vm.nom_sector[0].nom_sector;
                    
                    if (vm.selected.img_hash != "") {

                        datosBack.getData('displaysucesos/sectorimgdata/' + btoa(cod_sector) + "/" + vm.selected.img_hash, true, false, true).then(function (response: any) {
                            vm.imgdata_sector = response;
                            vm.showImagenSector();
                        }).catch(function (data: any) { });

                    }


                    if (vm.selected.obj_urls_videos && vm.selected.obj_urls_videos.length > 0) {
                        angular.element('.btnvideo').removeAttr("disabled");
                        angular.element('.btnvideo').on('click', function () {
                            vm.showVideo(vm.selected.obj_urls_videos[0]);
                        });
                    }
                }
            }).catch(function (data: any) { });
        }

        vm.$onInit = function () {

            //angular.element($window).on('resize', vm.resizeWindow);

            $scope.$watch(function () { return $element.is(':visible') }, function (newValue, oldValue) {
                vm.video_url = "#";
                if (newValue)
                    vm.onCodSectorChange(vm.cod_sector);

            });
/*
            $scope.$watch(function () { return vm.cod_sector }, function (newValue, oldValue) {
                vm.onCodSectorChange(newValue);
            });
*/

//            vm.onCodSectorChange(vm.cod_sector);

        };


        vm.onCodSectorChange = (cod_sector) => {
            vm.ind_video = false;
            videoSvc.stop()
            vm.video_url = "";
            vm.ind_detalle_temas = false;
            vm.ind_documentos = false;

            vm.selected.cod_sector = cod_sector;
            if (cod_sector) {
                vm.showSector(vm.selected.cod_sector, true);

                datosBack.getData('parametros/getParametro/URL_DOCUMENTOS', false, false).then(function (resultado) {
                    vm.documentos_url = resultado.val_parametro;
                }).catch(function () { });
            }
        }

        vm.$onDestroy = function () {
            angular.element('.btnvideo').off('click');
            angular.element('.btnvideo').attr("disabled", "disabled");
            vm.ind_video = false;
            videoSvc.stop()
            vm.video_url = "";
            vm.ind_detalle_temas = false;
            vm.ind_documentos = false;

        }

        vm.fresetear = (ind) => {
            return datosBack.postData('displaysucesos/resetear', {
                cod_tema: vm.selected.cod_tema,
                ind: ind
            })
                .then(function (res: any) {
                }).catch(function () { });

        };

        vm.restaurarTema = () => {
            return datosBack.postData('displaysucesos/restaurarTema', {
                cod_tema: vm.cod_tema,
            }).then(function (res: any) {
                vm.updateSelectedTema(vm.cod_tema);
            }).catch(function () { });
        };

        vm.close = () => {
            vm.video_url = "";
            vm.icons_imagen = [];
            console.log('cierre');
            vm.$onDestroy();
            $scope.$applyAsync();

            if ($attrs.onClose) {
                vm.onClose();
                //                    vm.$onDestroy();
            }
        };
    }],
};

export default sectorDetalleComponent;

