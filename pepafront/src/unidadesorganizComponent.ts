'use strict';
   
const unidadesorganizComponent =
{
  template: require('../Pages/unidadesorganiz.html'),
  bindings: {
  },
  controllerAs: "unidadesorganiz",
  controller: ['localData', 'datosBack', '$scope', '$state', '$timeout','$translate',
    function (localData, datosBack, $scope, $state, $timeout,$translate) {

      const vm = this;
      vm.ou = {};
      vm.ou_dt = {};
      vm.action_title = '';
      
      
      vm.hide = true;
      vm.active = 0;
      
      vm.form_title = $translate.instant('Organizaciones');
      localData.getSectoresList(false, false).then(function (resultado) {
        vm.sectoresList = resultado;
      }).catch(function () { });

      vm.uiOnParamsChanged = function (newParams) {
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
        if ($state.params.action === 'lista' || $state.params.action === 'agrega') {
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
        vm.ou = {};
        vm.action = action;
        const dtkey = vm.grilla.getdtKey();

        switch (action) {
          case 'agrega':
            vm.action_title = $translate.instant('Alta');
            
            vm.noteditable = false;
            vm.hide = false;
            vm.active = 2;
            break;
          case 'copia':
            if (dtkey && dtkey.length > 0) {
              vm.action_title = $translate.instant('Alta');
              datosBack.detalle('unidadesorganiz', dtkey)
                .then(function (response) {
                  vm.ou = response.data;
                  vm.ou.cod_ou = '';
                  vm.active = 2;
                  vm.hide = false;
                })
                .catch(function () { });
            }
            break;
          case 'edita':
            if (dtkey && dtkey.length > 0) {
              vm.action_title = $translate.instant('Modificación');
              datosBack.detalle('unidadesorganiz', dtkey)
                .then(function (response) {
                  vm.ou = response.data;
                  vm.active = 2;
                  vm.hide = false;
                })
                .catch(function () { });
            }
            break;
          default:
            break;
        }
      };

      vm.consulta = function () {
        const dtkey = vm.grilla.getdtKey();
        vm.ou_dt = {};
        if (dtkey !== '') {
          datosBack.detalle('unidadesorganiz', dtkey)
            .then(function (response) {
              vm.ou_dt = response.data;
            })
            .catch(function () { });
        }
      };

      vm.ok = function () {
        return datosBack.save(vm.action, 'unidadesorganiz', vm.ou, vm.grilla.getLastSelected())
          .then(function () {
            vm.grilla.fillGrid();
            vm.ou = {};
            localData.resetListaOU();
            $scope.$emit('abmOU', {});
            vm.active = 0;
            vm.hide = true;
            $state.go('.', { action: 'lista' });
          })
          .catch(function () { });
      };

      // delete record
      vm.confirmDelete = function () {
        datosBack.delete('unidadesorganiz', vm.grilla.getLastSelected()).then(function (response) {
          vm.grilla.fillGrid();
        }).catch(function (data) { });
      };

    },
  ]
};

export default unidadesorganizComponent;
