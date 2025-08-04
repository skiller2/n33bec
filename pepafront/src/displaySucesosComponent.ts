'use strict';
const displaySucesosComponent = {
    template: require('../Pages/display_sucesos.html'),
    bindings: {},
    controllerAs: "displaySucesos",
    controller:
            
        ['$scope', 'datosBack', '$timeout', 'sounds', 'auth', '$window', '$templateCache','$translate',
            function ($scope, datosBack, $timeout, sounds, auth, $window, $templateCache,$translate) {
                const vm = this;
                let timer: any;

                $templateCache.put('tree_grid.html', require("../Pages/Templates/tree_grid_template.html"));

                vm.sucesotree = [];
                vm.ind_listado = false;
                vm.btn_bell_class = 'btn-dark';
                vm.showListTimer = false;
                vm.button_video = 'btn-info';
                vm.cod_tema = '';
                vm.cod_sector = '';


                vm.expanding_property = {
                    field: "nom_tema",
                    displayName: "Nombre",
                    sortable: true,
                    filterable: true,
                    //                cellTemplate: "<i>{{row.branch[expandingProperty.field]}}</i>"
                };

                vm.col_defs = [
                    {
                        field: "stm_ult_suceso",
                        displayName: "Último Suceso",
                        sortable: true,
                        class: "cs-w-20 text-center",
                        bodyclass: "text-left",
                        sortingType: "string",
                        cellTemplate: '<span>{{ row.branch.stm_ult_suceso | ftDateTime }}</span>'
                    },
                    {
                        field: "ind_alarmatec",
                        displayName: "Alarma Técnica",
                        class: "cs-w-15 text-center",
                        bodyclass: "text-center",
                        sortable: true,
                        sortingType: "text",
                        filterable: true,
                        cellTemplate: '<i ng-if="row.branch.ind_alarmatec == 1" class="fa fa-wrench cs-fa-15x cs-text-alarmatec"></i>'

                    },
                    {
                        field: "ind_alarma text-center",
                        displayName: "Alarma",
                        class: "cs-w-15 ",
                        bodyclass: "text-center",
                        sortable: true,
                        sortingType: "text",
                        filterable: true,
                        cellTemplate: '<i ng-if="row.branch.ind_prealarma == 1" class="fa fa-bell cs-fa-15x cs-text-alarma"></i>'

                    },
                    {
                        field: "ind_prealarma",
                        displayName: "Pre Alarma",
                        class: "cs-w-15 text-center",
                        bodyclass: "text-center",
                        sortable: true,
                        sortingType: "text",
                        filterable: true,
                        cellTemplate: '<i ng-if="row.branch.ind_prealarma == 1" class="fa fa-bell cs-fa-15x cs-text-prealarma"></i>'

                    },
                    {
                        field: "ind_falla",
                        displayName: "Falla",
                        class: "cs-w-15 text-center",
                        bodyclass: "text-center",
                        sortable: true,
                        sortingType: "text",
                        filterable: true,
                        cellTemplate: '<i ng-if="row.branch.ind_falla == 1" class="fa fa-exclamation-triangle cs-fa-15x cs-text-falla"></i>'
                    },
                    {
                        field: "cant_activacion",
                        class: "cs-w-15 text-right",
                        displayName: "Cantidad",
                        bodyclass: "text-right",
                        sortable: true,
                        sortingType: "number"
                    },
                ];

                function getNodeById(fndid: string, node: any) {
                    var reduce = [].reduce;
                    function runner(result, node) {
                        if (result || !node) return result;
                        return node.id === fndid && node || //is this the proper node?
                            runner(null, node.children) || //process this nodes children
                            reduce.call(Object(node), runner, result);  //maybe this is some ArrayLike Structure
                    }
                    return runner(null, node);
                }

                function getTree(data: string | any[], primaryIdName: string, parentIdName: string) {
                    if (!data || data.length == 0 || !primaryIdName || !parentIdName)
                        return [];

                    var tree = [],
                        rootIds = [],
                        item = data[0],
                        primaryKey = item[primaryIdName],
                        treeObjs = {},
                        tempChildren = {},
                        parentId: string | number,
                        parent: { children: any[]; },
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
                                }
                                else {
                                    tempChildren[parentId] = [item];
                                }
                            }
                            else if (parent.children) {
                                parent.children.push(item);
                            }
                            else {
                                parent.children = [item];
                            }
                        }
                        else {
                            rootIds.push(primaryKey);
                        }
                    }

                    for (var i = 0; i < rootIds.length; i++) {
                        tree.push(treeObjs[rootIds[i]]);
                    };

                    return tree;
                }

                vm.wcsuc = {
                    // the fields below are all optional
                    videoHeight: 800,
                    videoWidth: 600,
                    video: null // Will reference the video element on success
                };
                const resetClases = () => {
                    vm.des_valor_alarma = 'SIN CONEXIÓN';
                    vm.des_valor_falla = 'SIN CONEXIÓN';
                    vm.button_alarma = 'btn-dark';
                    vm.button_falla = 'btn-dark';
                    vm.cs_shaker_alarpre = "";
                    vm.cs_shaker_falla = "";
                }

                resetClases();

                const showList = () => {
                    if (vm.showListTimer)
                        $timeout.cancel(vm.showListTimer);

                    datosBack.getData('displaysucesos/lista/false', true, false).then(function (response: any) {
                        //                    console.log('alertas', response);
                    }).catch(function (data: any) {
                    });
  
                    datosBack.getData('displaysucesos/listasec', true, false).then(function (response: { lista: any; ind_alarma_gral: any; ind_alarmatec_gral: any; ind_prealarma_gral: any; ind_falla_gral: any; }) {
                        vm.lista = response.lista;
                        vm.des_valor_alarma = "NORMAL";
                        vm.des_valor_falla = "NORMAL";
                        vm.cs_shaker_alarpre = "";
                        vm.cs_shaker_falla = "";
                        vm.button_alarma = "cs-btn-normal";
                        vm.button_falla = "cs-btn-normal";

                        if (response.ind_alarma_gral) {
                            vm.button_alarma = "cs-btn-alarma";
                            vm.des_valor_alarma = $translate.instant("ALARMA");
                            vm.cs_shaker_alarpre = "shaker";
                        } else if (response.ind_alarmatec_gral) {
                            vm.button_alarma = "cs-btn-alarma";
                            vm.des_valor_alarma = $translate.instant("ALARMA TÉCNICA");
                            vm.cs_shaker_alarpre = "shaker";
                        } else if (response.ind_prealarma_gral) {
                            vm.button_alarma = "cs-btn-prealarma";
                            vm.des_valor_alarma = $translate.instant("PREALARMA");
                            vm.cs_shaker_alarpre = "shaker";
                        }

                        if (response.ind_falla_gral) {
                            vm.button_falla = "cs-btn-falla";
                            vm.des_valor_falla = $translate.instant("FALLA");
                            vm.cs_shaker_falla = "shaker";
                        }

                        if (!response.ind_alarma_gral && !response.ind_alarmatec_gral && !response.ind_prealarma_gral && !response.ind_falla_gral) { //Sin alarmas, ni prealarmas,  
                            sounds.stop();
                            vm.btn_bell_class = 'btn-dark';
                            vm.shake_bell_class = '';
                        }

                        //                    console.log('antes', vm.sucesotree);
                        vm.sucesotree = getTree(vm.lista, 'id', 'parent_id');
                        //                    console.log('despues', vm.sucesotree);

                        //                    console.log('find', getNodeById("387dae622b482a92fe59", vm.sucesotree));
                        //67538503267
                        var node = getNodeById("770398973651", vm.sucesotree);
                        //                    node.expanded = true;
                        //console.log('node', node);
                        //                    vm.sucesotree[0].expanded = true;


                    }).catch(function (data: any) {
                        vm.lista = [];
                        resetClases();
                        $timeout(showList, 2000);
                    });
                }




                vm.$onDestroy = function () {
                    if (vm.showListTimer)
                        $timeout.cancel(vm.showListTimer);
                };

                vm.$onInit = function () {
                    auth.isLogged();
                    vm.showDisplay('listado');

                };

                $scope.$on('auth', function (event: any, args: { authenticated: any; }) {
                    vm.isLogged = args.authenticated;
                    vm.btnauthtext = (args.authenticated) ? $translate.instant("Salir") : $translate.instant("Ingresar");
                    vm.codUsuario = auth.getCodUsuario();
                });

                vm.authaction = () => {
                    if (vm.isLogged)
                        auth.logout();
                    else
                        auth.callLogin(false);
                    $scope.$applyAsync();
                };

                vm.showDisplay = (den_display: string) => {
                    $timeout.cancel(timer);
                    vm.ind_listado = false;
                    switch (den_display) {
                        case "listado":
                            vm.ind_listado = true;
                            vm.ind_detalle = false;
                            showList();
                            break;
                        case "detalle":
                            vm.ind_listado = false;
                            vm.ind_detalle = true;
                            break;
                    }
                    $timeout(function () {
                        $window.dispatchEvent(new Event("resize"));
                    }, 1);


                }

                vm.showDetalleSuceso = (row: any) => {
                    //console.log('click row', row);
                    vm.cod_tema = row.cod_tema;
                    vm.cod_sector = row.cod_sector;
                    vm.showDisplay('detalle');
                }

                $scope.$on('sucesos', function (event: any, args: { context: { ind_activa_audio: number; }; }) {

                    if ((args.context.ind_activa_audio == 1)) {
                        vm.btn_bell_class = 'btn-warning';  //'btn-danger';
                        sounds.start();
                        vm.shake_bell_class = 'shaker';
                    }
                    vm.showDisplay('listado');
                });


                vm.switchAudio = function () {
                    sounds.stop();
                    vm.btn_bell_class = 'btn-dark';
                    vm.shake_bell_class = '';
                    $scope.$applyAsync();
                };
            }]
};

export default displaySucesosComponent;