'use strict';
    
    
const movicredsectoresComponent =
{
  template: require('../Pages/movicredsectores.html'),
  bindings: {
  },
  controllerAs: "movicredsectores",
  controller: ['datosBack', '$state', '$timeout','$translate',
    function (datosBack, $state,$timeout,$translate) {

      const vm = this;
      vm.form_title = $translate.instant('Ocupación por sector');
      vm.movisector = {};
      
      

      vm.uiOnParamsChanged = function (newParams) {
        switch (newParams.action) {
          case "lista":
            vm.active = 0;
            break;
          case "detalle":
            vm.active = 1;
            vm.consulta();
            break;
          default:
            $state.go('.', {
              action: 'lista'
            });
            break;
        }
      };

      vm.$onInit = function () {
        if ($state.params.action === 'lista')
          vm.uiOnParamsChanged($state.params);
        else
          $state.go('.', {
            action: 'lista'
          });
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

      vm.consulta = function () {
        const dtkey = vm.grilla.getdtKey();
        vm.movisector = {};
        if (dtkey != "") {
          datosBack.detalle('movicredsectores', dtkey, false)
            .then(function (response) {
              vm.movisector = response.data;
              console.log(vm.movisector);
            })
            .catch(function () { });
        }
      };

      //delete record
      vm.confirmDelete = function () {
        datosBack.delete('movicredsectores', vm.grilla.getLastSelected()).then(function (response) {
          vm.grilla.fillGrid();
        }).catch(function (data) { });
      };

    },
  ]
};

export default movicredsectoresComponent;