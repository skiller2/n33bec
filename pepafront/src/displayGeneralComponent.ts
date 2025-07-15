"use strict";
//window.require = require;

import angular from "angular";
const displayGeneralComponent = {
    template: require("../Pages/display_general.html"),
    bindings: {
        //      cod_tema: '<codTema',
        //      cod_sector: '<codSector',
        //      onClose: '&'
    },
    controllerAs: "ctrl",
    controller: [
        "$scope",
        "datosBack",
        "$timeout",
        "sounds",
        "auth",
        "$window",
        "$templateCache",
        "$filter",
        "IdleTimeout",
        "$state",
        function (
            $scope,
            datosBack,
            $timeout,
            sounds,
            auth,
            $window,
            $templateCache,
            $filter,
            IdleTimeout,
            $state
        ) {
            const vm = this;
            let timer: any;
            let prevType = "";

            $templateCache.put(
                "tree_grid.html",
                require("../Pages/Templates/tree_grid_template.html")
            );
            vm.sectorestree = [];
            vm.ind_listado = false;
            vm.btn_bell_class = "btn-dark";
            vm.showListTimer = false;
            vm.button_video = "btn-info";
            vm.cod_tema = "";
            vm.cod_sector = "";
            vm.silenced = false;
            vm.expanding_property = {
                field: "nom_tema",
                displayName: "Nombre",
                sortable: true,
                sortDirection: "asc",
                sortingType: "string",
                filterable: true,
                //                cellTemplate: "<i>{{row.branch[expandingProperty.field]}}</i>"
            };
            vm.alertas = {};
            vm.col_defs = [];
            vm.showBtnPantallaPrincipal = false;
            vm.ind_modo_prueba = false;
            
            vm.cont_falla = 0;
            vm.cont_alarma = 0;
            vm.cont_prealarma = 0;
            vm.cont_alarmatec = 0;
            vm.cont_desconexion = 0;
            vm.cont_exclusion = 0;
            vm.cont_btn_alarma = 0;
            vm.cont_btn_falla = 0;

            function getNodeById(fndid: string, node: any) {
                var reduce = [].reduce;
                function runner(result, node) {
                    if (result || !node) return result;
                    return (
                        (node.id === fndid && node) || //is this the proper node?
                        runner(null, node.children) || //process this nodes children
                        reduce.call(Object(node), runner, result)
                    ); //maybe this is some ArrayLike Structure
                }
                return runner(null, node);
            }

            function getTree(
                data: string | any[],
                primaryIdName: string,
                parentIdName: string
            ) {
                if (
                    !data ||
                    data.length == 0 ||
                    !primaryIdName ||
                    !parentIdName
                )
                    return [];

                var tree = [],
                    rootIds = [],
                    item = data[0],
                    primaryKey = item[primaryIdName],
                    treeObjs = {},
                    tempChildren = {},
                    parentId: string | number,
                    parent: { children: any[] },
                    len = data.length,
                    i = 0;

                while (i < len) {
                    item = data[i++];
                    primaryKey = item[primaryIdName];

                    if (tempChildren[primaryKey]) {
                        item.children = tempChildren[primaryKey];
                        delete tempChildren[primaryKey];
                    }

                    treeObjs[primaryKey] = item;
                    parentId = item[parentIdName];

                    if (parentId) {
                        parent = treeObjs[parentId];

                        if (!parent) {
                            var siblings = tempChildren[parentId];
                            if (siblings) {
                                siblings.push(item);
                            } else {
                                tempChildren[parentId] = [item];
                            }
                        } else if (parent.children) {
                            parent.children.push(item);
                        } else {
                            parent.children = [item];
                        }
                    } else {
                        rootIds.push(primaryKey);
                    }
                }

                for (var i = 0; i < rootIds.length; i++) {
                    tree.push(treeObjs[rootIds[i]]);
                }

                return tree;
            }

            vm.restart = () => {};

            vm.flexFont = function () {
                var divs = document.getElementsByClassName(
                    "flexFont"
                ) as HTMLCollectionOf<HTMLElement>;
                for (var i = 0; i < divs.length; i++) {
                    var relFontsize = divs[i].offsetWidth * 0.05;
                    divs[i].style.fontSize = relFontsize + "px";
                }
            };
            /*            
            window.onload = function(event) {
                flexFont();
            };
            window.onresize = function(event) {
                flexFont();
            };
*/
            vm.wcsuc = {
                // the fields below are all optional
                videoHeight: 800,
                videoWidth: 600,
                video: null, // Will reference the video element on success
            };
            const resetClases = () => {
                vm.cont_btn_alarma = 0;
                vm.cont_btn_falla = 0;
                vm.alertas_old = null;
                vm.des_valor_alarma = "SIN CONEXIÓN";
                vm.des_valor_falla = "SIN CONEXIÓN";
                vm.button_alarma = "btn-dark";
                vm.button_falla = "btn-dark";
                vm.cs_shaker_alarpre = "";
                vm.cs_shaker_falla = "";
            };

            const updateLeftIcons = (
                ind_falla_gral,
                ind_alarma_gral,
                ind_prealarma_gral,
                ind_alarmatec_gral,
                ind_desconexion,
                ind_exclusion
            ) => {
                vm.des_valor_alarma = "NORMAL";
                vm.des_valor_falla = "NORMAL";
                vm.cs_shaker_alarpre = "";
                vm.cs_shaker_falla = "";
                vm.button_alarma = "cs-btn-normal";
                vm.button_falla = "cs-btn-normal";

                if (ind_alarma_gral) {
                    vm.button_alarma = "cs-btn-alarma";
                    vm.des_valor_alarma = "ALARMA";
                    vm.cs_shaker_alarpre = "shaker";
                } else if (ind_alarmatec_gral) {
                    vm.button_alarma = "cs-btn-alarma";
                    vm.des_valor_alarma = "ALARMA TÉCNICA";
                    vm.cs_shaker_alarpre = "shaker";
                } else if (ind_prealarma_gral) {
                    vm.button_alarma = "cs-btn-prealarma";
                    vm.des_valor_alarma = "PREALARMA";
                    vm.cs_shaker_alarpre = "shaker";
                }

                if (ind_falla_gral) {
                    vm.button_falla = "cs-btn-falla";
                    vm.des_valor_falla = "FALLA";
                    vm.cs_shaker_falla = "shaker";
                } else if (ind_desconexion) {
                    vm.button_falla = "cs-btn-desconexion";
                    vm.des_valor_falla = "DESCONEXIÓN";
                    vm.cs_shaker_falla = "shaker";
                }

                if (
                    !ind_alarma_gral &&
                    !ind_alarmatec_gral &&
                    !ind_prealarma_gral &&
                    !ind_falla_gral &&
                    !ind_desconexion
                ) {
                    //Sin alarmas, ni prealarmas,
                    sounds.stop();
                    vm.silenced = false;
                    vm.btn_bell_class = "btn-dark";
                    vm.shake_bell_class = "";
                } else {
                    if (!vm.silenced) {
                        sounds.start();
                        vm.btn_bell_class = "btn-warning"; //'btn-danger';
                        vm.shake_bell_class = "shaker";
                    }
                }
            };

            const fillSectoresTree = () => {
                datosBack
                    .getData("displaysucesos/listasec", false, false)
                    .then(function (response: {
                        lista: any;
                    }) {
                        const itemsSorted = $filter("orderBy")(response.lista, [
                            "parent_id",
                            "nom_tema",
                        ]);
                        vm.sectorestree = getTree(
                            itemsSorted,
                            "id",
                            "parent_id"
                        );
                    })
                    .catch(function (data: any) {
                        vm.sectorestree = [];
                        vm.fillSectoresTreeTimer = $timeout(
                            fillSectoresTree,
                            2000
                        );
                    });
            };

            const showList = () => {
                if (vm.showListTimer) $timeout.cancel(vm.showListTimer);
                datosBack
                    .getData("displaysucesos/lista/false", false, false)
                    .then(function (response: any) {
                        if (!angular.equals(vm.alertas_old, response)) {
                            vm.ind_listado = true;
                            vm.ind_detalle = false;
                            vm.alertas_old = response;
                            vm.alertas = $filter("orderBy")(response, [
                                "tipo_evento",
                                "stm_evento",
                            ]);

                            let ind_alarma_gral = false;
                            let ind_alarmatec_gral = false;
                            let ind_prealarma_gral = false;
                            let ind_falla_gral = false;
                            let ind_desconexion = false;
                            let ind_exclusion = false;
                            
                            vm.cont_alarma = 0;
                            vm.cont_prealarma = 0;
                            vm.cont_alarmatec = 0;
                            vm.cont_desconexion = 0;
                            vm.cont_exclusion = 0;
                            vm.cont_falla = 0;

                            vm.alertas.forEach((alerta) => {
                                switch (alerta.tipo_evento) {
                                    case "FA":
                                        ind_falla_gral = true;
                                        vm.cont_falla++;
                                        break;
                                    case "AL":
                                        ind_alarma_gral = true;
                                        vm.cont_alarma++;
                                        break;
                                    case "PA":
                                        ind_prealarma_gral = true;
                                        vm.cont_prealarma++;
                                        break;
                                    case "AT":
                                        ind_alarmatec_gral = true;
                                        vm.cont_alarmatec++;
                                        break;
                                    case "DE":
                                        ind_desconexion = true;
                                        vm.cont_desconexion++;
                                        break;
                                    case "EX":
                                        ind_exclusion = true;
                                        vm.cont_exclusion++;
                                        break;

                                    default:
                                        break;
                                }
                            });
                            vm.cont_btn_alarma = vm.cont_alarma + vm.cont_prealarma + vm.cont_alarmatec;
                            vm.cont_btn_falla = vm.cont_desconexion + vm.cont_exclusion + vm.cont_falla;
                

                            updateLeftIcons(
                                ind_falla_gral,
                                ind_alarma_gral,
                                ind_prealarma_gral,
                                ind_alarmatec_gral,
                                ind_desconexion,
                                ind_exclusion
                            );
                        }
                        vm.showListTimer = $timeout(showList, 7000);
                    })
                    .catch(function (data: any) {
                        vm.alertas = {};
                        resetClases();
                        vm.showListTimer = $timeout(showList, 2000);
                    });
            };

            vm.$onDestroy = function () {
                if (vm.showListTimer) $timeout.cancel(vm.showListTimer);
                if (vm.fillSectoresTreeTimer)
                    $timeout.cancel(vm.fillSectoresTreeTimer);
            };

            vm.$onInit = function () {
                resetClases();
                //                document.getElementById("viewport").setAttribute("content", "width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1");

                /*
                console.log('zoom', document.body.style.zoom);
                console.log('devicePixelRatio', window.devicePixelRatio);
                const scale = 'scale(1)';
//                document.body.style.webkitTransform =  scale;    // Chrome, Opera, Safari
//                document.body.style.msTransform =   scale;       // IE 9
                document.body.style.transform = scale;     // General
                document.body.style.zoom = '1.0';
*/
                auth.isLogged();
                vm.sectores = vm.treedata_avm;
                vm.showDisplay("listado");

                fillSectoresTree();

                $window.onfocus = function () {
                    //                    vm.showDisplay("listado");
                };

                datosBack.getEstadosLeds();
            };

            $scope.$on("estados", function (event, args) {
                if (args.context.EstadoDen == "indModoPrueba") {
                    vm.ind_modo_prueba = args.context.EstadoVal;
                    vm.testModeLabel =
                        vm.ind_modo_prueba == 0
                            ? "Activar Prueba"
                            : "Cancelar Prueba";
                    $scope.$applyAsync();
                }

                if (args.context.EstadoDen == "Sectores") {
                    fillSectoresTree();
                }

            });

            $scope.$on(
                "auth",
                function (event: any, args: { authenticated: any }) {
                    vm.isLogged = args.authenticated;
                    vm.btnauthtext = args.authenticated ? "Salir" : "Ingresar";
                    vm.codUsuario = auth.getCodUsuario();
                    if (vm.codUsuario == "admin" || vm.codUsuario == "efaisa")
                        vm.showBtnPantallaPrincipal = true;
                    else vm.showBtnPantallaPrincipal = false;
                }


                
            );

            vm.authaction = () => {
                if (vm.isLogged) auth.logout();
                else auth.callLogin(false);
                $scope.$applyAsync();
            };

            vm.showDisplay = (den_display: string) => {
                $timeout.cancel(timer);
                vm.ind_listado = false;
                switch (den_display) {
                    case "listado":
                        vm.ind_listado = true;
                        vm.ind_detalle = false;
                        vm.ind_controlacceso = false;
                        showList();
                        break;
                    case "detalle":
                        vm.ind_listado = false;
                        vm.ind_detalle = true;
                        vm.ind_controlacceso = false;
                        break;
                    case "controlacceso":
                        vm.ind_controlacceso = true;
                        vm.ind_listado = false;
                        vm.ind_detalle = false;
                        break;
                }
                $timeout(function () {
                    $window.dispatchEvent(new Event("resize"));
                }, 1);
            };

            vm.showSectorDetalle = (row: any) => {
                vm.cod_tema = row.cod_tema;
                vm.cod_sector = row.cod_sector;
                vm.cod_tema_sector = row.cod_tema_sector;
                vm.showDisplay("detalle");
            };

            $scope.$on(
                "io",
                function (
                    event: any,
                    args: { context: { ind_activa_audio: number } }
                ) {
                    showList();
                }
            );

            vm.switchAudio = function () {
                vm.silenced = true;
                sounds.stop();
                vm.btn_bell_class = "btn-dark";
                vm.shake_bell_class = "";
                $scope.$applyAsync();
            };

            vm.toogleTestMode = function () {
                return datosBack
                    .postData("temas/setOperationMode", {
                        ind_modo_prueba: !vm.ind_modo_prueba,
                    })
                    .then(function (res: any) {})
                    .catch(function () {});
            };
        },
    ],
};

export default displayGeneralComponent;
