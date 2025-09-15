'use strict';
import angular from 'angular';

const confgrupocredComponent =  {
    template: require('./Pages/confgrupocred.html'),
    bindings: {},
    controllerAs: "confgrupocred",
    controller: ['localData', 'datosBack', '$state', '$timeout','$translate',
      function (localData, datosBack, $state,$timeout,$translate) {

        const vm = this;
        vm.form_title = $translate.instant('Grupo de Tarjetas');
        
        
        vm.grupocred = {};
        vm.grupocred_dt = {};
        vm.action_title = '';
        
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
        vm.toggle = function (action, selected) {
          vm.grupocred = {};
          vm.action = action;
          const dtkey = vm.grilla.getdtKey();

          switch (action) {
            case 'agrega':
              vm.action_title = $translate.instant('Alta');
              
              vm.noteditable = false;
              vm.hide = false;
              break;
            case 'copia':
              if (dtkey && dtkey.length > 0) {
                vm.action_title = $translate.instant('Alta');
                datosBack.detalle('confgrupocred', dtkey)
                  .then(function (response) {
                    vm.grupocred = response.data;
                    vm.grupocred.cod_grupo = '';
                    // vm.active = 2;
                    vm.hide = false;
                  })
                  .catch(function () { });
              }
              break;
            case 'edita':
              if (dtkey && dtkey.length > 0) {
                vm.action_title = $translate.instant('Modificación');
                vm.noteditable = true;
                datosBack.detalle('confgrupocred', dtkey)
                  .then(function (response) {
                    vm.grupocred = response.data;
                    // vm.active = 2;
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
          vm.grupocred_dt = {};
          if (dtkey !== '') {
            datosBack.detalle('confgrupocred', dtkey)
              .then(function (response) {
                vm.grupocred_dt = response.data;
              })
              .catch(function () { });
          }
        };

        vm.ok = function () {
          return datosBack.save(vm.action, 'confgrupocred', vm.grupocred, vm.grilla.getLastSelected())
            .then(function () {
              vm.grilla.fillGrid();
              vm.grupocred = {};
              vm.active = 0;
              vm.hide = true;
              localData.resetGrupoCredList();
              $state.go('.', { action: 'lista' });
            })
            .catch(function () { });
        };

        // delete record
        vm.confirmDelete = function () {
          datosBack.delete('confgrupocred', vm.grilla.getLastSelected()).then(function (response) {
            vm.grilla.fillGrid();
            localData.resetGrupoCredList();
          }).catch(function (data) { });
        };

      },
    ]
  };
export default confgrupocredComponent;