'use strict';
const empresasComponent =
{
  template: require('.././Pages/Asis/empresas.html'),
  bindings: {
  },
  controllerAs: "empresas",
  controller:  ['$timeout', 'datosBack', '$state',
    function ($timeout, datosBack, $state) {
      const vm = this;
      vm.form_title = 'Empresas';
      vm.empresa = {};
      vm.action_title = '';
      vm.action = '';
      
      
      vm.hide = true;
      vm.active = 0;

      vm.uiOnParamsChanged = function (newParams) {
        switch (newParams.action) {
          case 'lista':
            vm.active = 0;
            break;
          case 'detalle':
            vm.active = 1;
            vm.consulta();
            break;
          default:
            $state.go('.', {
              action: 'lista',
            });
            break;
        }
      };

      vm.$onInit = function () {
        if ($state.params.action === 'lista') {
          vm.uiOnParamsChanged($state.params);
        } else {
          $state.go('.', {
            action: 'lista',
          });
        }
        $timeout(function () { vm.grilla.fillGrid() },0);
      };

      // Set search items for starting and reset.
      vm.gridOptions = {
        data: [],
        enableFullRowSelection: true,
        enablePaginationControls: false,
        enableRowHeaderSelection: true,
        enableRowSelection: true,
        excludeProperties: '__metadata',
        multiSelect: false,
        useExternalSorting: true,
        // multiSelect: false
      };

      vm.consulta = function () {
        const dtkey = vm.grilla.getdtKey();
        vm.empresa = {};
        if (dtkey !== '') {
          datosBack.detalle('empresas', dtkey)
            .then(function (response) {
              vm.empresa = response.data;
            })
            .catch(function () { });
        }
      };

      vm.updateEmpresas = function () {
        return datosBack.save(vm.action, 'empresas', {}, '')
          .then(function () {
            vm.grilla.fillGrid();
            vm.active = 0;
            vm.hide = true;
            $state.go('.', { action: 'lista' });
          })
          .catch(function () { });
      };
    },
  ]
};
export default empresasComponent;    