'use strict';

const movieventosComponent= {
    template: require('./Pages/movieventos.html'),
    bindings: {},
    controllerAs: "movieventos",
    controller: ['$timeout', 'datosBack', '$state','$translate',
      function ($timeout, datosBack, $state,$translate) {

        const vm = this;
        vm.form_title = $translate.instant('Eventos');
        vm.evento = {};
        
        

        vm.$onInit = function () {
          vm.autoRefreshBtn = true;
          $timeout(function () { vm.grilla.fillGrid() },0);
        };

        const mystate = $state.$current;

        mystate.onExit = function () {
          vm.$onDestroy();
        };

        vm.$onDestroy = function () {
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

        vm.exportar = function (tipo) {
          datosBack.export(vm.grilla.getLoadOptions(), tipo).then(function (response) { }).catch(function (data) { });
        };
      },
    ]
  };

export default movieventosComponent;