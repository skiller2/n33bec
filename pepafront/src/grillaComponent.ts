'use strict';
import angular from "angular";
import moment from "moment";

const grillaComponent = {
    template: require('../Pages/Templates/grilla_template.html'),
    controllerAs: "vm",
    bindings: {
        gridoptions: '<',
        api: '&',
        filtroextra: '<',
        onSelection: '&',
        urlback: '@',
        onclickAdd: '&',
        onclickCopy: '&',
        onclickEdit: '&',
        onclickDelete: '&',
        onclickDetail: '&',
        onclickExport: '&',
        checkOu: '@',
        autoRefresh: '<',
        showRefreshBtn: '<',
        showfilters: '<',
        copySref: '<',
        pagesize: '<'
    },
    require: {
        //modelCtrl: 'ngModel' 
    },
    controller: ['$element', 'cfg', 'datosBack', '$q', '$interval', '$state', 'ModalService', '$window', '$timeout', '$scope', function ($element, cfg, datosBack, $q, $interval, $state, ModalService, $window, $timeout, $scope) {
        const vm = this;

        vm.sort = [];
        vm.gridApi;
        vm.Timer;
        vm.gridoptionsloaded = false;
        vm.pageSize = 50;
        vm.canceled = false;
        vm.srefeditactive = false;
        vm.pfiltro = {};
        vm.pageNumber = 0;
        vm.totalPages = 0;
        vm.totalRecords = 0;
        vm.dateformatmodel = cfg.dateformatmodel;
        vm.dateformat = cfg.dateformat;
        vm.loadGrid = false;
        vm.cargaGrillaPending = false;
        vm.filtrosoptions = [];


        const gridOptionsDefault = {
            rowHeight: 30,
            infiniteScrollRowsFromEnd: 4,
            infiniteScrollUp: true,
            infiniteScrollDown: true,
            appScopeProvider: vm,
            excludeProperties: '__metadata',
            enablePaginationControls: false,
            enableRowSelection: true,
            enableRowHeaderSelection: true,
            multiSelect: false,
            enableFullRowSelection: true,
            useExternalSorting: true,
            data: [],
            onRegisterApi: function (gridApit) {
                vm.gridApi = gridApit;
                // declare the events
                vm.gridApi.core.on.sortChanged(null, function (grid, sortColumns) {
                    vm.sort = [];

                    angular.forEach(sortColumns, function (sortColumn) {
                        vm.sort.push({ fieldName: sortColumn.name, order: sortColumn.sort.direction });
                    });
                    vm.cargaGrilla();

                });

                vm.gridApi.selection.on.rowSelectionChanged(null, function (row) {
                    vm.lastselected = vm.getSelected();
                    vm.dtkey = vm.lastselected.selected;
                    vm.onSelection({ sel: vm.lastselected });



                });
                /*
                gridApi.infiniteScroll.on.needLoadMoreData(null, function () {
                    console.log('needLoadMoreData');


                    //scope.totalItems 
                    //scope.pageNumber 
                    //scope.totalPages 
                    gridApi.infiniteScroll.saveScrollPercentage();
                    scope.nextPage();

                    return gridApi.infiniteScroll.dataLoaded(scope.pageNumber > 0, scope.pageNumber < scope.totalPages).then(function() {scope.checkDataLength('down'); gridApi.infiniteScroll.dataRemovedBottom(scope.pageNumber > 0, scope.pageNumber < scope.totalPages); });

                });
                */

                /*
                gridApi.infiniteScroll.on.needLoadMoreDataTop(null, function () {
                    console.log('needLoadMoreDataTop');
                    gridApi.infiniteScroll.saveScrollPercentage();
                    scope.previousPage();
                    gridApi.infiniteScroll.resetScroll(scope.pageNumber > 0, scope.pageNumber < scope.totalPages );

                    return gridApi.infiniteScroll.dataLoaded(scope.pageNumber > 0, scope.pageNumber < scope.totalPages).then(function() {scope.checkDataLength('up'); gridApi.infiniteScroll.dataRemovedTop(scope.pageNumber > 0, scope.pageNumber < scope.totalPages);
                });
                });
                */
                /*
                 gridApi.selection.on.rowSelectionChangedBatch(null, function(row){
                 });
                 */

            },
        };

        vm.stop = () => {
            $interval.cancel(vm.Timer);
        };

        vm.start = () => {
            // stops any running interval to avoid two intervals running at the same time
            stop();
            // store the interval promise
            vm.Timer = $interval(function () {
                vm.cargaGrilla();
            }, 5000);
        };


        vm.cargaGrilla = function (pagina) {
            if (!vm.gridoptionsloaded) {
                vm.cargaGrillaPending = true;
                return;
            }
            if (pagina) {
                vm.pageNumber = 1;
            }
            const loadOptions = vm.getLoadOptions();
            datosBack.load(loadOptions).then(function (response) {
                vm.gridoptions.data = response.data.data;
                vm.totalItems = response.data.total;
                vm.pageNumber = response.data.current_page;
                vm.pageSize = response.data.per_page;
                vm.totalPages = vm.getTotalPages();
            }).catch(function (data) {
            }).finally(function () {
            });
        };

        vm.cargaGridOptions = function () {
            const loadOptions = {
                path: vm.urlback + '/gridOptions',
            };

            return datosBack.gridOptions(loadOptions).then(function (response) {
                vm.gridoptions.columnKeys = response.data.columnKeys;
                vm.gridoptions.columnDefs = response.data.columnDefs;
                vm.filtrosoptions = response.data.filtros;
                vm.rango = response.data.rango;
                vm.gridoptionsloaded = true;

                if (vm.cargaGrillaPending) {
                    vm.cargaGrillaPending = false;
                    vm.cargaGrilla();
                }

                if (vm.grid_visible)
                    vm.resizeGrid();


            }).catch(function (data) {
            });
        };

        vm.resizeGrid = () => {
            $element.find(".ui-grid-contents-wrapper").css("margin-right", "-30px");
            $element.find(".ui-grid-contents-wrapper").css("margin-bottom", "-30px");

            setTimeout(function () {
                $element.find(".ui-grid-contents-wrapper").css("margin-right", "-30px");
                $element.find(".ui-grid-contents-wrapper").css("margin-bottom", "-30px");
                if (vm.gridApi) {

                    vm.gridApi.core.handleWindowResize();
                    setTimeout(function () {
                        vm.gridApi.core.handleWindowResize();
                    }, 300);
                }
            }, 100);
        };

        vm.getFilterJson = (filtro, rango, filtroextra) => {
            const filterJson = { json: [], error: '' };
            const nombreRangoDesde = (rango) ? rango.desde.id : 'aud_stm_ingreso';
            const tipoRangoDesde = (rango) ? rango.desde.tipo : 'datetime';
            const nombreRangoHasta = (rango) ? rango.hasta.id : 'aud_stm_ingreso';
            const tipoRangoHasta = (rango) ? rango.hasta.tipo : 'datetime';

            if (filtro.selected !== '0' && filtro.text !== '') {
                let vc = filtro.text;
                if (filtro.selected == 'cod_credencial') {
                    vc = parseInt(vc.replace('-', ''));
                }
                filterJson.json.push({
                    NombreCampo: filtro.selected,
                    operacion: 'LIKE',
                    ValorCampo: vc,
                });
            }

            if (filtro.pAvailableFrom !== '' || filtro.pAvailableTo !== '') {
                if (filtro.pAvailableFrom !== '' && filtro.pAvailableTo !== '') {
                    if (filtro.pAvailableFrom > filtro.pAvailableTo) {
                        filterJson.error += 'Available To date should be greater or equal to Available From date.\n';
                    }
                }
                if (filtro.pAvailableFrom) {
                    let filtroFrom = '';
                    if (tipoRangoDesde == "date") {
                        filtroFrom = filtro.pAvailableFrom;
                    } else if (tipoRangoDesde == "datetime") {
                        filtroFrom = moment(filtro.pAvailableFrom, 'YYYY-MM-DD HH:mm:ss.SSS').utc().format(cfg.datetimeformatmodelMoment);
                    }

                    filterJson.json.push({
                        NombreCampo: nombreRangoDesde,
                        operacion: '>=',
                        ValorCampo: filtroFrom,
                    });
                }
                if (filtro.pAvailableTo) {
                    let filtroTo = '';
                    if (tipoRangoHasta == "date") {
                        filtroTo = filtro.pAvailableTo;
                    } else if (tipoRangoHasta == "datetime") {
                        filtroTo = moment(filtro.pAvailableTo, 'YYYY-MM-DD HH:mm:ss.SSS').set({ hour: 23, minute: 59, second: 59 }).utc().format(cfg.datetimeformatmodelMoment);
                    }
                    filterJson.json.push({
                        NombreCampo: nombreRangoHasta,
                        operacion: '<=',
                        ValorCampo: filtroTo,
                    });
                }
            }
            if (filtroextra && Object.keys(filtroextra).length > 0) {
                filterJson.json.push(filtroextra);
            }

            return filterJson;
        };


        vm.setDefaultSearchItems = () => {
            vm.pfiltro.selected = '0';
            // vm.pFiltroPersonas = { selected: "0" };
            vm.pfiltro.text = '';
            vm.pfiltro.pAvailableFrom = '';
            vm.pfiltro.pAvailableTo = '';
            vm.errorMessage = undefined;
            vm.activeFilter = vm.pfiltro;
        };

        vm.lastPage = function () {
            if (vm.pageNumber >= 1) {
                vm.pageNumber = vm.getTotalPages();
                return vm.cargaGrilla();
            }
            return false;
        };

        vm.previousPage = function () {
            if (vm.pageNumber > 1) {
                vm.pageNumber--;
                return vm.cargaGrilla();
            }
            return false;
        };

        vm.nextPage = function () {
            if (vm.pageNumber < vm.getTotalPages()) {
                vm.pageNumber++;
                return vm.cargaGrilla();
            }
            return false;
        };

        vm.firstPage = function () {
            if (vm.pageNumber > 1) {
                vm.pageNumber = 1;
                return vm.cargaGrilla();
            }
            return false;
        };

        vm.getTotalPages = function () {
            return Math.ceil(vm.totalItems / vm.pageSize);
        };

        vm.gotoSREF = function (path, params) {

            const sel = vm.gridApi.selection.getSelectedRows();
            if (sel.length > 0) {
                $state.go(path, params);
            } else {
                ModalService.alertMessage('Debe seleccionar un registro', 'Error', 'danger', []);
            }
        };

        vm.getSelected = function () {
            const sel = vm.gridApi.selection.getSelectedRows();
            const claves = vm.gridoptions.columnKeys;
            let registrosel = [];
            const registrossel = [];
            angular.forEach(sel, function (value, key) {
                registrosel = [];
                angular.forEach(claves, function (clave, key_clave) {
                    registrosel.push(value[clave]);
                });
                registrossel.push(registrosel);
            });
            return { selected: registrossel, urlback: vm.urlback };
        };

        vm.checkDataLength = function (discardDirection) {
            if (discardDirection === 'up') {
                //                                scope.previousPage();                            
            }

            if (discardDirection === 'down') {
                //                                scope.nextPage();
            }
            // work out whether we need to discard a page, if so discard from the direction passed in
            /*
             if (vm.lastPage - vm.firstPage > 3) {
               // we want to remove a page
               vm.gridApi.infiniteScroll.saveScrollPercentage();
          
               if( discardDirection === 'up' ){
                 $scope.data = $scope.data.slice(100);
                 vm.firstPage++;
                 $timeout(function() {
                   // wait for grid to ingest data changes
                   vm.gridApi.infiniteScroll.dataRemovedTop(vm.firstPage > 0, vm.lastPage < 4);
                 });
               } else {
                 $scope.data = $scope.data.slice(0, 400);
                 vm.lastPage--;
                 $timeout(function() {
                   // wait for grid to ingest data changes
                   vm.gridApi.infiniteScroll.dataRemovedBottom(vm.firstPage > 0, vm.lastPage < 4);
                 });
               }
             }
             */
        }

        vm.getLoadOptions = function () {
            const loadOptions = {
                filtro: vm.getFilterJson(vm.pfiltro, vm.rango, vm.filtroextra),
                path: vm.urlback + '/lista',
                pageNumber: vm.pageNumber,
                totalItems: vm.totalItems,
                pageSize: vm.pageSize,
                sort: vm.sort,
            };
            return loadOptions;
        };

        vm.getLastSelected = () => {
            if (vm.lastselected)
                return vm.lastselected;
            else
                return { selected: [] };
        }

        vm.getdtKey = () => {
            return vm.dtkey;
        }

        vm.$onInit = () => {
            if (!angular.isDefined(vm.gridoptions))
                vm.gridoptions = {};
            angular.extend(vm.gridoptions, gridOptionsDefault);
            vm.loadGrid = true;

            vm.api({
                API: {
                    fillGrid: vm.cargaGrilla, resizeGrid: vm.resizeGrid, getLastSelected: vm.getLastSelected, getdtKey: vm.getdtKey, getLoadOptions: vm.getLoadOptions, setFilter(newfilter) {
                        if (newfilter)
                            angular.extend(vm.pfiltro, newfilter);
                    }
                }
            });

            vm.addHasCallback = function () {
                return angular.isDefined($element[0].attributes["onclick-add"]);
            };
            vm.copyHasCallback = function () {
                return angular.isDefined($element[0].attributes["onclick-copy"]);
            };
            vm.editHasCallback = function () {
                return angular.isDefined($element[0].attributes["onclick-edit"]);
            };
            vm.deleteHasCallback = function () {
                return angular.isDefined($element[0].attributes["onclick-delete"]);
            };
            vm.detailHasCallback = function () {
                return angular.isDefined($element[0].attributes["onclick-detail"]);
            };
            vm.exportHasCallback = function () {
                return angular.isDefined($element[0].attributes["onclick-export"]);
            };

            if (!angular.isDefined(vm.copySref))
                vm.copySref = 'copia';

            if (!angular.isDefined(vm.showfilters))
                vm.showfilters = true;
            
            vm.setDefaultSearchItems();

            vm.cargaGridOptions();

            $scope.$watch(function () { return $element.is(':visible') }, function (newValue, oldValue) {
                vm.grid_visible = newValue;
                if (newValue == true && vm.gridoptionsloaded) {
                    vm.resizeGrid();
                }
            });


            //            if (angular.isDefined(attributes.pagesize))
            //            scope.pageSize = attributes.pagesize;

        }


        $scope.$on('cambiaSelOU', function (event, args) {
            if (vm.checkOu)
                vm.cargaGrilla();
        });


        $scope.$on('auth', function (event, args) {
            if (args.authenticated)
                vm.cargaGridOptions();
        });


        vm.onChangeAutoRefresh = (value: boolean) => {
            if (value)
                vm.start();
            else
                vm.stop();
        }

        vm.$onChanges = (changes) => {
            console.log("changes", changes);
            if (changes.autoRefresh) {
                vm.autorefreshint = changes.autoRefresh.currentValue;
                vm.onChangeAutoRefresh(vm.autorefreshint);
            }
        }
    }]
};

export default grillaComponent;
